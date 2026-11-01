<?php
/**
 * Blacklist – Core Engine
 *
 * Handles generation, evaluation, and resolution of blacklist entries.
 *
 * Category types:
 *   registration   – inactive since registration date
 *   login          – inactive since last login
 *   scene_wait     – no post in active scene for X days
 *   posting        – no post anywhere for X days
 *   group          – user belongs to a specific group
 *   no_subaccount  – registered X+ days ago but has no linked sub-account (as_uid)
 *   no_active_scene– has no legitimate active scene (>=2 participants) right now
 *   frozen         – a date profile field ("frozen until") lies X+ days in the past
 *
 * Response types:
 *   none     – no self-service
 *   button   – click "I'm active", system re-checks category condition
 *   posting  – must post in the exact scene after timestamp_added
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/blacklist/core/logbook_adapter.php';
require_once MYBB_ROOT . 'inc/plugins/blacklist/core/notifications.php';

class Blacklist_Engine
{
    /** @var DB_Base */
    private $db;

    /** @var MyBB */
    private $mybb;

    /** @var Blacklist_LogbookAdapter */
    private $logbook;

    /** @var Blacklist_Notifications */
    private $notifier;

    /** @var bool|null Cached result of hasAccountSwitcher() */
    private $hasAccountSwitcher = null;

    public function __construct()
    {
        global $db, $mybb;
        $this->db       = $db;
        $this->mybb     = $mybb;
        $this->logbook  = new Blacklist_LogbookAdapter();
        $this->notifier = new Blacklist_Notifications();
    }

    // -------------------------------------------------------------------------
    // Entry generation (called by task)
    // -------------------------------------------------------------------------

    /**
     * Resolve all open scene_wait entries whose scene now has ≤1 active participant.
     * Called at task start and on the blacklist page so stale entries disappear promptly.
     */
    public function resolveObsoleteSceneEntries(): void
    {
        if (!$this->logbook->isLogbookInstalled()) {
            return;
        }

        $query = $this->db->query("
            SELECT e.id, e.scene_id
            FROM " . TABLE_PREFIX . "blacklist_entries e
            INNER JOIN " . TABLE_PREFIX . "blacklist_categories c ON c.id = e.category_id
            WHERE e.status   = 'open'
              AND c.type     = 'scene_wait'
              AND e.scene_id > 0
        ");

        while ($row = $this->db->fetch_array($query)) {
            if ($this->logbook->getActiveParticipantCount((int) $row['scene_id']) <= 1) {
                $this->resolveEntry((int) $row['id']);
            }
        }
    }

    /**
     * Main entry point for the scheduled task.
     * Loops over all active categories and generates missing entries.
     *
     * @return array ['created' => int, 'skipped' => int]
     */
    public function run(): array
    {
        $this->resolveObsoleteSceneEntries();

        $stats      = ['created' => 0, 'skipped' => 0];
        $categories = $this->getActiveCategories();

        if (empty($categories)) {
            return $stats;
        }

        foreach ($categories as $category) {
            $users = $this->getUsersForCategory($category);

            foreach ($users as $user) {
                $result = $this->processUserForCategory($user, $category);
                $stats['created']  += $result['created'];
                $stats['skipped']  += $result['skipped'];
            }
        }

        if ($stats['created'] > 0) {
            $run = $this->getActiveRun();
            if ($run && !(int) $run['notified']) {
                $deadline = TIME_NOW + ((int) $this->mybb->settings['blacklist_deadline_days'] * 86400);
                $this->notifier->notifyRunStarted((int) $run['id'], $deadline);
                $this->db->update_query('blacklist_runs', ['notified' => 1], 'id = ' . (int) $run['id']);
            }
        }

        return $stats;
    }

    /**
     * Process a single user against a single category.
     * Returns new entry counts.
     *
     * @param  array $user
     * @param  array $category
     * @return array
     */
    private function processUserForCategory(array $user, array $category): array
    {
        $created = 0;
        $skipped = 0;

        switch ($category['type']) {
            case 'scene_wait':
                $result   = $this->processSceneWait($user, $category);
                $created += $result['created'];
                $skipped += $result['skipped'];
                break;

            case 'registration':
            case 'login':
            case 'posting':
            case 'group':
            case 'no_subaccount':
            case 'no_active_scene':
            case 'frozen':
                if ($this->checkNonSceneCondition($user, $category)) {
                    if (!$this->hasOpenEntry($user['uid'], $category['id'])) {
                        $this->createEntry($user['uid'], $category['id'], null, null);
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
                break;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Scene-wait logic: iterate all active scenes the user is in.
     *
     * Fixed-order scenes (free_order=0): only flag the user whose turn it is.
     * Waiting time is measured from the last post by anyone in the scene.
     *
     * Free-order scenes (free_order=1): skipped entirely. Without a defined
     * turn order it is unclear whether this user is even the one who should
     * post next, so flagging them would be unfair.
     */
    private function processSceneWait(array $user, array $category): array
    {
        $created = 0;
        $skipped = 0;

        if (!$this->logbook->isLogbookInstalled()) {
            return ['created' => $created, 'skipped' => $skipped];
        }

        $scenes    = $this->logbook->getScenesByUser((int) $user['uid']);
        $threshold = (int) $category['threshold_days'] * 86400;
        $now       = TIME_NOW;

        foreach ($scenes as $scene) {
            $tid        = (int) $scene['tid'];
            $scene_id   = (int) $scene['scene_id'];
            $free_order = (int) $scene['free_order'];

            if ($tid <= 0) {
                continue;
            }

            // Solo scenes have no partner to wait for
            if ($this->logbook->getActiveParticipantCount($scene_id) <= 1) {
                $skipped++;
                continue;
            }

            if ($this->hasOpenEntryForScene($user['uid'], $category['id'], $scene_id)) {
                $skipped++;
                continue;
            }

            // Free-order scenes have no defined turn order, so it is unclear
            // whether this user is the one who should post next → skip.
            if ($free_order !== 0) {
                $skipped++;
                continue;
            }

            // Fixed turn order: only the user who is next gets flagged
            $next = $this->logbook->getNextPoster($scene_id, $tid);
            if ($next === null || (int) $next['user_id'] !== (int) $user['uid']) {
                $skipped++;
                continue;
            }
            // Waiting time = how long since the partner last posted
            $last_post = $this->logbook->getLastPostByAnyone($tid);

            if ($last_post === null) {
                $last_post = $this->getThreadDateline($tid);
            }

            if ($last_post === null) {
                continue;
            }

            if (($now - $last_post) >= $threshold) {
                $this->createEntry($user['uid'], $category['id'], $scene_id, $tid);
                $created++;
            } else {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Check conditions for non-scene categories.
     *
     * @param  array $user
     * @param  array $category
     * @return bool  true = condition met → entry should be created
     */
    private function checkNonSceneCondition(array $user, array $category): bool
    {
        $now       = TIME_NOW;
        $threshold = (int) $category['threshold_days'] * 86400;

        switch ($category['type']) {
            case 'registration':
                return ($now - (int) $user['regdate']) >= $threshold;

            case 'login':
                $last_active = max((int) $user['lastactive'], (int) $user['lastvisit']);
                return ($now - $last_active) >= $threshold;

            case 'posting':
                $last_post = $this->getGlobalLastPost((int) $user['uid']);
                if ($last_post === null) {
                    return ($now - (int) $user['regdate']) >= $threshold;
                }
                return ($now - $last_post) >= $threshold;

            case 'group':
                return true; // Group membership is already filtered in getUsersForCategory

            case 'no_subaccount':
                // Requires the EnhancedAccountSwitcher column users.as_uid.
                // Without it we cannot tell sub-accounts apart → never flag.
                if (!$this->hasAccountSwitcher()) {
                    return false;
                }
                if (($now - (int) $user['regdate']) < $threshold) {
                    return false;
                }
                return !$this->hasSubAccount((int) $user['uid']);

            case 'no_active_scene':
                // Momentary check: flag users without any legitimate active
                // scene. threshold_days is not used for this type.
                if (!$this->logbook->isLogbookInstalled()) {
                    return false;
                }
                return !$this->logbook->hasLegitimateActiveScene((int) $user['uid']);

            case 'frozen':
                return $this->checkFrozenCondition((int) $user['uid'], $category, $now, $threshold);

            default:
                return false;
        }
    }

    private function checkFrozenCondition(int $uid, array $category, int $now, int $threshold): bool
    {
        $fid    = 'fid' . (int) $category['frozen_profile_field'];
        $result = $this->db->simple_select('userfields', $fid, 'ufid = ' . (int) $uid);
        $row    = $this->db->fetch_array($result);

        if (!$row || empty($row[$fid])) {
            return false;
        }

        $frozen_until = strtotime($row[$fid]);
        if ($frozen_until === false || $frozen_until <= 0) {
            return false;
        }

        return ($now - $frozen_until) >= $threshold;
    }

    // -------------------------------------------------------------------------
    // Entry management
    // -------------------------------------------------------------------------

    /**
     * Create a new blacklist entry.
     *
     * @param  int      $uid
     * @param  int      $category_id
     * @param  int|null $scene_id
     * @param  int|null $thread_id
     * @return int      New entry ID
     */
    public function createEntry(int $uid, int $category_id, ?int $scene_id, ?int $thread_id): int
    {
        $now      = TIME_NOW;
        $deadline = $now + ((int) $this->mybb->settings['blacklist_deadline_days'] * 86400);

        $run = $this->getActiveRun();
        if (!$run) {
            $run_id = $this->createRun();
        } else {
            $run_id = (int) $run['id'];
        }

        $insert = [
            'uid'             => $uid,
            'category_id'     => $category_id,
            'scene_id'        => $scene_id ?? 0,
            'thread_id'       => $thread_id ?? 0,
            'timestamp_added' => $now,
            'deadline'        => $deadline,
            'status'          => 'open',
            'run_id'          => $run_id,
        ];

        $entry_id = $this->db->insert_query('blacklist_entries', $insert);

        return (int) $entry_id;
    }

    /**
     * Resolve an entry (mark as done).
     *
     * @param  int $entry_id
     */
    public function resolveEntry(int $entry_id): void
    {
        $this->db->update_query(
            'blacklist_entries',
            ['status' => 'resolved'],
            'id = ' . (int) $entry_id
        );
    }

    /**
     * Delete an entry (team only).
     *
     * @param  int $entry_id
     */
    public function deleteEntry(int $entry_id): void
    {
        $this->db->delete_query('blacklist_entries', 'id = ' . (int) $entry_id);
    }

    // -------------------------------------------------------------------------
    // Self-removal
    // -------------------------------------------------------------------------

    /**
     * Attempt to self-resolve an entry.
     * Returns true on success, false if condition not yet met.
     *
     * @param  int $entry_id
     * @param  int $uid
     * @return bool
     */
    public function attemptSelfRemoval(int $entry_id, int $uid): bool
    {
        $entry = $this->getEntry($entry_id);

        if (!$entry || (int) $entry['uid'] !== $uid || $entry['status'] !== 'open') {
            return false;
        }

        $category = $this->getCategory((int) $entry['category_id']);

        if (!$category) {
            return false;
        }

        if ($category['response_type'] === 'posting') {
            return $this->checkPostingRequirement($entry, $category);
        }

        if ($category['response_type'] === 'button') {
            return $this->checkButtonRequirement($entry, $category);
        }

        // response_type = none → self-removal not allowed
        return false;
    }

    /**
     * Posting requirement: user must have posted after timestamp_added.
     * Scene entries require the post in the scene's thread; entries without
     * a thread (non-scene categories like global posting inactivity) accept
     * any visible post anywhere in the forum.
     */
    private function checkPostingRequirement(array $entry, array $category): bool
    {
        $thread_id = (int) $entry['thread_id'];
        $uid       = (int) $entry['uid'];
        $ts        = (int) $entry['timestamp_added'];

        if ($thread_id <= 0) {
            $last_post = $this->getGlobalLastPost($uid);
            $post_time = ($last_post !== null && $last_post > $ts) ? $last_post : null;
        } else {
            $post_time = $this->logbook->getFirstPostAfter($uid, $thread_id, $ts);
        }

        if ($post_time !== null) {
            $this->resolveEntry((int) $entry['id']);
            return true;
        }

        return false;
    }

    /**
     * Button requirement: re-check whether the original category condition
     * is now met (i.e. user has been active since the entry was created).
     */
    private function checkButtonRequirement(array $entry, array $category): bool
    {
        $uid  = (int) $entry['uid'];
        $now  = TIME_NOW;
        $ts   = (int) $entry['timestamp_added'];

        switch ($category['type']) {
            case 'login':
                $user        = $this->getUser($uid);
                $last_active = max((int) $user['lastactive'], (int) $user['lastvisit']);
                $met         = $last_active > $ts;
                break;

            case 'posting':
                $last_post = $this->getGlobalLastPost($uid);
                $met       = $last_post !== null && $last_post > $ts;
                break;

            case 'scene_wait':
                // Button = user declares activity by clicking; no post required (that's response_type=posting)
                $met = true;
                break;

            case 'no_subaccount':
                $met = $this->hasSubAccount($uid);
                break;

            case 'no_active_scene':
                $met = $this->logbook->isLogbookInstalled()
                    && $this->logbook->hasLegitimateActiveScene($uid);
                break;

            default:
                $met = false;
        }

        if ($met) {
            $this->resolveEntry((int) $entry['id']);
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Debug
    // -------------------------------------------------------------------------

    /**
     * Returns a detailed trace of the scene_wait evaluation for every active
     * scene_wait category and every matching user/scene combination.
     * Used by blacklist.php?action=activity&debug=1 (team only).
     *
     * @return array  list of trace entries, each with keys:
     *   category_name, uid, username, scene_id, tid, free_order,
     *   participant_count, skip_reason (string|null),
     *   next_poster_uid (int|null), last_post (int|null), days_waiting (int|null),
     *   threshold_days, condition_met (bool)
     */
    public function debugSceneWait(): array
    {
        if (!$this->logbook->isLogbookInstalled()) {
            return [];
        }

        $trace      = [];
        $categories = $this->getActiveCategories();
        $now        = TIME_NOW;

        foreach ($categories as $category) {
            if ($category['type'] !== 'scene_wait') {
                continue;
            }

            $threshold = (int) $category['threshold_days'] * 86400;
            $users     = $this->getUsersForCategory($category);

            foreach ($users as $user) {
                $uid    = (int) $user['uid'];
                $scenes = $this->logbook->getScenesByUser($uid);

                foreach ($scenes as $scene) {
                    $tid        = (int) $scene['tid'];
                    $scene_id   = (int) $scene['scene_id'];
                    $free_order = (int) $scene['free_order'];

                    $entry = [
                        'category_name'   => $category['name'],
                        'threshold_days'  => (int) $category['threshold_days'],
                        'uid'             => $uid,
                        'username'        => $user['username'],
                        'scene_id'        => $scene_id,
                        'tid'             => $tid,
                        'free_order'      => $free_order,
                        'participant_count'=> null,
                        'skip_reason'     => null,
                        'next_poster_uid' => null,
                        'last_post'       => null,
                        'days_waiting'    => null,
                        'condition_met'   => false,
                    ];

                    if ($tid <= 0) {
                        $entry['skip_reason'] = 'tid <= 0';
                        $trace[] = $entry;
                        continue;
                    }

                    $count = $this->logbook->getActiveParticipantCount($scene_id);
                    $entry['participant_count'] = $count;

                    if ($count <= 1) {
                        $entry['skip_reason'] = 'solo scene (count=' . $count . ')';
                        $trace[] = $entry;
                        continue;
                    }

                    if ($free_order !== 0) {
                        $entry['skip_reason'] = 'free order scene (no fixed turn order)';
                        $trace[] = $entry;
                        continue;
                    }

                    $next = $this->logbook->getNextPoster($scene_id, $tid);
                    $entry['next_poster_uid'] = $next ? (int) $next['user_id'] : null;

                    if ($next === null || (int) $next['user_id'] !== $uid) {
                        $entry['skip_reason'] = 'not next poster (next=' . ($next ? $next['user_id'] : 'null') . ')';
                        $trace[] = $entry;
                        continue;
                    }

                    $last_post = $this->logbook->getLastPostByAnyone($tid);

                    if ($last_post === null) {
                        $last_post = $this->getThreadDateline($tid);
                        if ($last_post !== null) {
                            $entry['skip_reason_note'] = 'using thread dateline as fallback';
                        }
                    }

                    $entry['last_post'] = $last_post;

                    if ($last_post === null) {
                        $entry['skip_reason'] = 'no last_post and no thread dateline';
                        $trace[] = $entry;
                        continue;
                    }

                    $entry['days_waiting'] = (int) ceil(($now - $last_post) / 86400);
                    $entry['condition_met'] = ($now - $last_post) >= $threshold;

                    $trace[] = $entry;
                }
            }
        }

        return $trace;
    }

    // -------------------------------------------------------------------------
    // Activity simulation (blacklist.php?action=activity)
    // -------------------------------------------------------------------------

    /**
     * Returns the number of inactive days for a non-scene category.
     */
    private function getDaysInactiveForCategory(array $user, array $category): ?int
    {
        $now = TIME_NOW;

        switch ($category['type']) {
            case 'registration':
                return (int) ceil(($now - (int) $user['regdate']) / 86400);

            case 'login':
                $last_active = max((int) $user['lastactive'], (int) $user['lastvisit']);
                return (int) ceil(($now - $last_active) / 86400);

            case 'posting':
                $last_post = $this->getGlobalLastPost((int) $user['uid']);
                if ($last_post === null) {
                    return (int) ceil(($now - (int) $user['regdate']) / 86400);
                }
                return (int) ceil(($now - $last_post) / 86400);

            case 'no_subaccount':
                return (int) ceil(($now - (int) $user['regdate']) / 86400);

            case 'frozen':
                $frozen_until = $this->getFrozenUntilTimestamp((int) $user['uid'], $category);
                return $frozen_until !== null ? (int) ceil(($now - $frozen_until) / 86400) : null;

            default:
                return null;
        }
    }

    /**
     * Simulate blacklist for ALL users, grouped by active category (no DB writes).
     *
     * Each result entry:
     *   'category' => array
     *   'affected' => array of:
     *     'user'           => array
     *     'days_inactive'  => int|null  (null for scene_wait / group)
     *     'last_timestamp' => int|null  (null for scene_wait / group)
     *     'scenes'         => array[]   (only for scene_wait)
     *       each scene entry: 'scene', 'days_inactive', 'last_timestamp'
     *
     * @return array
     */
    public function simulateAll(): array
    {
        $results    = [];
        $categories = $this->getActiveCategories();

        foreach ($categories as $category) {
            $affected = [];
            $users    = $this->getUsersForCategory($category);

            if ($category['type'] === 'scene_wait') {
                if (!$this->logbook->isLogbookInstalled()) {
                    $results[] = ['category' => $category, 'affected' => []];
                    continue;
                }

                $threshold   = (int) $category['threshold_days'] * 86400;
                $now         = TIME_NOW;
                $user_scenes = [];

                foreach ($users as $user) {
                    $uid    = (int) $user['uid'];
                    $scenes = $this->logbook->getScenesByUser($uid);

                    foreach ($scenes as $scene) {
                        $tid        = (int) $scene['tid'];
                        $scene_id   = (int) $scene['scene_id'];
                        $free_order = (int) $scene['free_order'];

                        if ($tid <= 0) {
                            continue;
                        }

                        if ($this->logbook->getActiveParticipantCount($scene_id) <= 1) {
                            continue;
                        }

                        // Free-order scenes have no defined turn order → skip
                        if ($free_order !== 0) {
                            continue;
                        }

                        $next = $this->logbook->getNextPoster($scene_id, $tid);
                        if ($next === null || (int) $next['user_id'] !== $uid) {
                            continue; // Not this user's turn
                        }
                        $last_post = $this->logbook->getLastPostByAnyone($tid);

                        if ($last_post === null) {
                            $last_post = $this->getThreadDateline($tid);
                        }
                        if ($last_post === null) {
                            continue;
                        }

                        if (($now - $last_post) >= $threshold) {
                            $days = (int) ceil(($now - $last_post) / 86400);
                            if (!isset($user_scenes[$uid])) {
                                $user_scenes[$uid] = ['user' => $user, 'scenes' => []];
                            }
                            $user_scenes[$uid]['scenes'][] = [
                                'scene'          => $scene,
                                'days_inactive'  => $days,
                                'last_timestamp' => $last_post,
                            ];
                        }
                    }
                }

                foreach ($user_scenes as $entry) {
                    $affected[] = [
                        'user'           => $entry['user'],
                        'days_inactive'  => null,
                        'last_timestamp' => null,
                        'scenes'         => $entry['scenes'],
                    ];
                }
            } else {
                foreach ($users as $user) {
                    if (!$this->checkNonSceneCondition($user, $category)) {
                        continue;
                    }
                    $affected[] = [
                        'user'           => $user,
                        'days_inactive'  => $this->getDaysInactiveForCategory($user, $category),
                        'last_timestamp' => $this->getRelevantTimestamp($user, $category),
                        'scenes'         => [],
                    ];
                }
            }

            $results[] = ['category' => $category, 'affected' => $affected];
        }

        return $results;
    }

    /**
     * Returns the relevant reference timestamp for display in non-scene categories.
     */
    private function getRelevantTimestamp(array $user, array $category): ?int
    {
        switch ($category['type']) {
            case 'registration':
                return (int) $user['regdate'];
            case 'login':
                return max((int) $user['lastactive'], (int) $user['lastvisit']);
            case 'posting':
                $lp = $this->getGlobalLastPost((int) $user['uid']);
                return $lp !== null ? $lp : (int) $user['regdate'];
            case 'no_subaccount':
                return (int) $user['regdate'];
            case 'frozen':
                return $this->getFrozenUntilTimestamp((int) $user['uid'], $category);
            default:
                return null;
        }
    }

    private function getFrozenUntilTimestamp(int $uid, array $category): ?int
    {
        $fid    = 'fid' . (int) $category['frozen_profile_field'];
        $result = $this->db->simple_select('userfields', $fid, 'ufid = ' . (int) $uid);
        $row    = $this->db->fetch_array($result);

        if (!$row || empty($row[$fid])) {
            return null;
        }

        $ts = strtotime($row[$fid]);
        return ($ts !== false && $ts > 0) ? $ts : null;
    }

    // -------------------------------------------------------------------------
    // Data retrieval
    // -------------------------------------------------------------------------

    public function getEntry(int $entry_id): ?array
    {
        $query = $this->db->simple_select(
            'blacklist_entries',
            '*',
            'id = ' . (int) $entry_id
        );
        $row = $this->db->fetch_array($query);
        return $row ?: null;
    }

    /**
     * Returns all open and resolved entries for frontend display.
     * Open entries come first within each category (alphabetical status sort: open < resolved).
     */
    public function getAllEntries(): array
    {
        $run = $this->getActiveRun();
        if (!$run) {
            return [];
        }

        $run_id = (int) $run['id'];

        $query = $this->db->query("
            SELECT e.*, u.username, u.usergroup, u.displaygroup, u.additionalgroups,
                   c.name AS category_name, c.type AS category_type,
                   c.response_type,
                   t.subject AS thread_subject
            FROM " . TABLE_PREFIX . "blacklist_entries e
            LEFT  JOIN " . TABLE_PREFIX . "users u     ON u.uid = e.uid
            INNER JOIN " . TABLE_PREFIX . "blacklist_categories c ON c.id = e.category_id
            LEFT  JOIN " . TABLE_PREFIX . "threads t   ON t.tid = e.thread_id
            WHERE e.run_id = {$run_id}
              AND e.status IN ('open', 'resolved')
            ORDER BY c.id ASC, e.status ASC, e.timestamp_added ASC
        ");

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }
        return $rows;
    }

    // ── Run management ──────────────────────────────────────────────────────────

    public function getActiveRun(): ?array
    {
        $query = $this->db->simple_select(
            'blacklist_runs', '*', "status = 'active'",
            ['order_by' => 'id DESC', 'limit' => 1]
        );
        $row = $this->db->fetch_array($query);
        return $row ?: null;
    }

    public function getLastClosedRun(): ?array
    {
        $query = $this->db->simple_select(
            'blacklist_runs', '*', "status = 'closed'",
            ['order_by' => 'id DESC', 'limit' => 1]
        );
        $row = $this->db->fetch_array($query);
        return $row ?: null;
    }

    public function createRun(): int
    {
        return (int) $this->db->insert_query('blacklist_runs', [
            'created_at'    => TIME_NOW,
            'closed_at'     => 0,
            'closed_by_uid' => 0,
            'status'        => 'active',
        ]);
    }

    public function closeRun(int $run_id, int $uid): void
    {
        $this->db->update_query('blacklist_runs', [
            'status'        => 'closed',
            'closed_at'     => TIME_NOW,
            'closed_by_uid' => (int) $uid,
        ], 'id = ' . (int) $run_id);
    }

    public function getEntriesForRun(int $run_id): array
    {
        $query = $this->db->query("
            SELECT e.*, u.username, u.usergroup, u.displaygroup, u.additionalgroups,
                   c.name AS category_name, c.type AS category_type,
                   c.response_type,
                   t.subject AS thread_subject
            FROM " . TABLE_PREFIX . "blacklist_entries e
            LEFT  JOIN " . TABLE_PREFIX . "users u     ON u.uid = e.uid
            INNER JOIN " . TABLE_PREFIX . "blacklist_categories c ON c.id = e.category_id
            LEFT  JOIN " . TABLE_PREFIX . "threads t   ON t.tid = e.thread_id
            WHERE e.run_id = " . (int) $run_id . "
              AND e.status IN ('open', 'resolved')
            ORDER BY e.status ASC, e.timestamp_added ASC
        ");

        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getActiveCategories(): array
    {
        $query = $this->db->simple_select(
            'blacklist_categories',
            '*',
            'active = 1',
            ['order_by' => 'sort_order ASC']
        );
        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getAllCategories(): array
    {
        $query = $this->db->simple_select(
            'blacklist_categories',
            '*',
            '',
            ['order_by' => 'id ASC']
        );
        $rows = [];
        while ($row = $this->db->fetch_array($query)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getCategory(int $id): ?array
    {
        $query = $this->db->simple_select(
            'blacklist_categories',
            '*',
            'id = ' . (int) $id
        );
        $row = $this->db->fetch_array($query);
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function hasOpenEntry(int $uid, int $category_id): bool
    {
        $run = $this->getActiveRun();
        if (!$run) {
            return false;
        }

        $query = $this->db->simple_select(
            'blacklist_entries',
            'id',
            'uid = ' . (int) $uid
                . ' AND category_id = ' . (int) $category_id
                . " AND status = 'open'"
                . ' AND run_id = ' . (int) $run['id'],
            ['limit' => 1]
        );
        return (bool) $this->db->fetch_array($query);
    }

    private function hasOpenEntryForScene(int $uid, int $category_id, int $scene_id): bool
    {
        $run = $this->getActiveRun();
        if (!$run) {
            return false;
        }

        $query = $this->db->simple_select(
            'blacklist_entries',
            'id',
            'uid = ' . (int) $uid
                . ' AND category_id = ' . (int) $category_id
                . ' AND scene_id = ' . (int) $scene_id
                . " AND status = 'open'"
                . ' AND run_id = ' . (int) $run['id'],
            ['limit' => 1]
        );
        return (bool) $this->db->fetch_array($query);
    }

    private function getUser(int $uid): ?array
    {
        $query = $this->db->simple_select(
            'users',
            'uid, username, usergroup, additionalgroups, regdate, lastactive, lastvisit, away',
            'uid = ' . (int) $uid
        );
        $row = $this->db->fetch_array($query);
        return $row ?: null;
    }

    /**
     * Returns all users that could potentially match a given category
     * (based on group filter).
     */
    private function getUsersForCategory(array $category): array
    {
        $group_filter       = $this->buildGroupFilter($category);
        $away_filter        = (int) $category['away_exempt'] === 1 ? ' AND away = 0' : '';
        $subaccount_filter  = ($category['type'] === 'no_subaccount' && $this->hasAccountSwitcher())
            ? ' AND as_uid = 0'
            : '';

        $query = $this->db->query("
            SELECT uid, username, usergroup, displaygroup, additionalgroups, regdate, lastactive, lastvisit, away
            FROM " . TABLE_PREFIX . "users
            WHERE {$group_filter}{$away_filter}{$subaccount_filter}
        ");

        $users = [];
        while ($row = $this->db->fetch_array($query)) {
            $users[] = $row;
        }
        return $users;
    }

    /**
     * Build WHERE clause for group filtering.
     */
    private function buildGroupFilter(array $category): string
    {
        $group_ids = array_filter(
            array_map('intval', explode(',', (string) $category['group_ids']))
        );

        if (empty($group_ids)) {
            return '1=1';
        }

        $id_list = implode(',', $group_ids);
        $include_additional = (bool) $category['include_additional_groups'];

        if ($include_additional) {
            $clauses = ["usergroup IN ({$id_list})"];
            foreach ($group_ids as $gid) {
                $gid_escaped = (int) $gid;
                $clauses[]   = "FIND_IN_SET({$gid_escaped}, additionalgroups)";
            }
            return '(' . implode(' OR ', $clauses) . ')';
        }

        return "usergroup IN ({$id_list})";
    }

    private function userMatchesCategory(array $user, array $category): bool
    {
        $group_ids = array_filter(
            array_map('intval', explode(',', (string) $category['group_ids']))
        );

        if (empty($group_ids)) {
            return true;
        }

        $uid_group = (int) $user['usergroup'];
        $additional = array_filter(
            array_map('intval', explode(',', (string) $user['additionalgroups']))
        );

        if (in_array($uid_group, $group_ids)) {
            return true;
        }

        if ((bool) $category['include_additional_groups']) {
            foreach ($additional as $ag) {
                if (in_array($ag, $group_ids)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getThreadDateline(int $tid): ?int
    {
        $tid   = (int) $tid;
        $query = $this->db->simple_select('threads', 'dateline', 'tid = ' . $tid);
        $row   = $this->db->fetch_array($query);
        return $row ? (int) $row['dateline'] : null;
    }

    private function getGlobalLastPost(int $uid): ?int
    {
        $uid   = (int) $uid;
        $query = $this->db->query("
            SELECT MAX(dateline) AS last_post
            FROM " . TABLE_PREFIX . "posts
            WHERE uid = {$uid} AND visible = 1
        ");
        $row = $this->db->fetch_array($query);
        return isset($row['last_post']) && $row['last_post'] > 0
            ? (int) $row['last_post']
            : null;
    }

    /**
     * Returns true if any account has as_uid pointing to $uid (EnhancedAccountSwitcher).
     */
    private function hasSubAccount(int $uid): bool
    {
        if (!$this->hasAccountSwitcher()) {
            return true; // no switcher → cannot determine, treat as "has one" (never flag)
        }
        $query = $this->db->simple_select('users', 'uid', 'as_uid = ' . (int) $uid, ['limit' => 1]);
        return (bool) $this->db->fetch_array($query);
    }

    /**
     * True if the EnhancedAccountSwitcher column users.as_uid exists.
     * Cached for the lifetime of this engine instance.
     */
    private function hasAccountSwitcher(): bool
    {
        if ($this->hasAccountSwitcher === null) {
            $this->hasAccountSwitcher = $this->db->field_exists('as_uid', 'users');
        }
        return $this->hasAccountSwitcher;
    }
}
