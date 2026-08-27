<?php
/**
 * Blacklist – Logbook Adapter
 *
 * Abstraction layer between Blacklist and the Logbook plugin.
 * All queries go through this file so that internal Logbook structure
 * changes only require updates here.
 *
 * Logbook DB schema (as analysed):
 *   mybb_logbook       – id, user_id, reminder_days
 *   mybb_logbook_entry – id, scene_id, logbook_id, accepted, finished
 *   mybb_scene         – id, tid, scene_type, free_order, ort, title
 *
 * Active scenes criteria:
 *   scene_type IN (0,1) – Normal / AU (both have a real thread)
 *   logbook_entry.finished = 0
 *   logbook_entry.accepted = 1
 *   scene.tid > 0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Blacklist_LogbookAdapter
{
    /** @var DB_Base */
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Returns all active scenes for a given user.
     *
     * @param  int   $uid
     * @return array Array of rows: scene_id, tid, subject, scene_type, free_order, ort, title
     */
    public function getScenesByUser(int $uid): array
    {
        $uid = (int) $uid;

        $query = $this->db->query("
            SELECT
                s.id   AS scene_id,
                s.tid,
                s.scene_type,
                s.free_order,
                s.ort,
                s.title,
                t.subject,
                e.id   AS entry_id,
                e.finished,
                e.accepted
            FROM " . TABLE_PREFIX . "logbook_entry e
            INNER JOIN " . TABLE_PREFIX . "logbook l      ON l.id = e.logbook_id
            INNER JOIN " . TABLE_PREFIX . "scene s         ON s.id = e.scene_id
            LEFT  JOIN " . TABLE_PREFIX . "threads t       ON t.tid = s.tid
            WHERE l.user_id  = {$uid}
              AND e.finished  = 0
              AND e.accepted  = 1
              AND s.scene_type IN (0, 1)
              AND s.tid > 0
        ");

        $scenes = [];
        while ($row = $this->db->fetch_array($query)) {
            $scenes[] = $row;
        }

        return $scenes;
    }

    /**
     * Returns the timestamp of the first post made by a user in a thread
     * that is strictly after a given timestamp.
     *
     * Used to verify posting-requirement self-removal.
     *
     * @param  int      $uid
     * @param  int      $tid
     * @param  int      $after_timestamp
     * @return int|null Timestamp of qualifying post, or null
     */
    public function getFirstPostAfter(int $uid, int $tid, int $after_timestamp): ?int
    {
        $uid             = (int) $uid;
        $tid             = (int) $tid;
        $after_timestamp = (int) $after_timestamp;

        $query = $this->db->query("
            SELECT MIN(dateline) AS first_post
            FROM " . TABLE_PREFIX . "posts
            WHERE uid      = {$uid}
              AND tid      = {$tid}
              AND dateline > {$after_timestamp}
              AND visible  = 1
        ");

        $row = $this->db->fetch_array($query);

        return isset($row['first_post']) && $row['first_post'] > 0
            ? (int) $row['first_post']
            : null;
    }

    /**
     * Returns the scene_id for a given thread ID, or null if no scene exists.
     *
     * @param  int      $tid
     * @return int|null
     */
    public function getSceneIdByThread(int $tid): ?int
    {
        $tid = (int) $tid;

        $query = $this->db->query("
            SELECT id FROM " . TABLE_PREFIX . "scene
            WHERE tid = {$tid}
            LIMIT 1
        ");

        $row = $this->db->fetch_array($query);

        return $row ? (int) $row['id'] : null;
    }

    /**
     * Returns the number of active (accepted, unfinished) participants in a scene.
     *
     * @param  int $scene_id
     * @return int
     */
    public function getActiveParticipantCount(int $scene_id): int
    {
        $scene_id = (int) $scene_id;

        $query = $this->db->query("
            SELECT COUNT(*) AS cnt
            FROM " . TABLE_PREFIX . "logbook_entry e
            JOIN " . TABLE_PREFIX . "logbook l ON l.id = e.logbook_id
            JOIN " . TABLE_PREFIX . "users u   ON u.uid = l.user_id
            WHERE e.scene_id = {$scene_id}
              AND e.finished  = 0
              AND e.accepted  = 1
        ");

        $row = $this->db->fetch_array($query);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Returns true if the user has at least one "legitimate" active scene.
     *
     * A scene counts as legitimate when it is active (accepted, unfinished,
     * scene_type 0/1, tid > 0) AND has at least two active participants whose
     * accounts still exist. A scene where only one participant remains – or
     * where every remaining participant's account has been deleted – does NOT
     * count. getActiveParticipantCount() already joins the users table, so
     * deleted accounts are excluded from the count automatically.
     *
     * @param  int  $uid
     * @return bool
     */
    public function hasLegitimateActiveScene(int $uid): bool
    {
        foreach ($this->getScenesByUser($uid) as $scene) {
            if ((int) $scene['tid'] <= 0) {
                continue;
            }
            if ($this->getActiveParticipantCount((int) $scene['scene_id']) >= 2) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the next user who needs to post in a fixed-order scene.
     * Delegates to logbook_db_get_scene_next_poster() from the Logbook plugin.
     *
     * Returns null when free-order scenes, when the function is unavailable,
     * or when no active participants remain.
     *
     * @param  int        $scene_id
     * @param  int        $tid
     * @return array|null ['user_id' => int, 'username' => string] or null
     */
    public function getNextPoster(int $scene_id, int $tid): ?array
    {
        if (!function_exists('logbook_db_get_scene_next_poster')) {
            return null;
        }
        return logbook_db_get_scene_next_poster($scene_id, $tid);
    }

    /**
     * Returns the timestamp of the most recent visible post in a thread,
     * regardless of who posted it.
     *
     * Used to measure how long the next poster has kept their partner waiting.
     *
     * @param  int      $tid
     * @return int|null Timestamp or null if no posts exist
     */
    public function getLastPostByAnyone(int $tid): ?int
    {
        $tid = (int) $tid;

        if ($tid <= 0) {
            return null;
        }

        $query = $this->db->query("
            SELECT MAX(dateline) AS last_post
            FROM " . TABLE_PREFIX . "posts
            WHERE tid     = {$tid}
              AND visible = 1
        ");

        $row = $this->db->fetch_array($query);

        return isset($row['last_post']) && $row['last_post'] > 0
            ? (int) $row['last_post']
            : null;
    }

    /**
     * Check whether the Logbook plugin tables exist.
     * Used to gracefully disable Blacklist features if Logbook is not installed.
     *
     * @return bool
     */
    public function isLogbookInstalled(): bool
    {
        global $db;
        return $db->table_exists('scene') && $db->table_exists('logbook');
    }
}
