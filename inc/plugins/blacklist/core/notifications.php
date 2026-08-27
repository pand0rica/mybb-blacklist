<?php
/**
 * Blacklist – Thread Notifications
 *
 * Posts a message in a configured thread when new blacklist entries are created.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class Blacklist_Notifications
{
    /** @var DB_Base */
    private $db;

    /** @var MyBB */
    private $mybb;

    public function __construct()
    {
        global $db, $mybb;
        $this->db   = $db;
        $this->mybb = $mybb;
    }

    /**
     * Post a single notification when a blacklist run is started.
     * Called once per run after all entries are generated.
     *
     * Setting: blacklist_notify_thread_id (thread ID to post in)
     * Setting: blacklist_notify_user_id   (UID to post as; 0 = skip)
     *
     * @param int $run_id
     * @param int $deadline  Unix timestamp of the response deadline
     */
    public function notifyRunStarted(int $run_id, int $deadline): void
    {
        global $lang;

        $thread_id = (int) ($this->mybb->settings['blacklist_notify_thread_id'] ?? 0);
        $poster_id = (int) ($this->mybb->settings['blacklist_notify_user_id'] ?? 0);

        if ($thread_id <= 0 || $poster_id <= 0) {
            return;
        }

        $thread = $this->getThread($thread_id);
        if (!$thread) {
            return;
        }

        $poster = $this->getUser($poster_id);
        if (!$poster) {
            return;
        }

        $blacklist_url = $this->mybb->settings['bburl'] . '/blacklist.php';
        $deadline_date = date('d.m.Y', $deadline);

        // Language key lives in both the frontend and admin language files, because
        // notifyRunStarted() runs from the task/frontend (frontend lang loaded) as
        // well as from the ACP "run now" button (admin lang loaded).
        $message = sprintf($lang->blacklist_notify_message, $deadline_date, $blacklist_url);

        $this->insertPost($thread, $poster, $message);
    }

    private function insertPost(array $thread, array $poster, string $message): void
    {
        $now = TIME_NOW;

        $post_data = [
            'tid'        => (int) $thread['tid'],
            'fid'        => (int) $thread['fid'],
            'uid'        => (int) $poster['uid'],
            'username'   => $this->db->escape_string($poster['username']),
            'message'    => $this->db->escape_string($message),
            'dateline'   => $now,
            'ipaddress'  => '',
            'includesig' => 0,
            'smilieoff'  => 0,
            'visible'    => 1,
        ];

        $pid = $this->db->insert_query('posts', $post_data);

        if (!$pid) {
            return;
        }

        // Update thread and forum counters. The previous manual version was
        // broken: update_query() quotes values instead of evaluating SQL, so
        // "replies+1" reset the counter to 0, and the forum's lastposttid /
        // lastpostsubject pointers were never touched — making the index show
        // the previously newest thread as the "last post" even though the
        // timestamp was already current.
        require_once MYBB_ROOT . 'inc/functions.php';

        // Thread: bump the reply count with a real increment ...
        update_thread_counters((int) $thread['tid'], ['replies' => '+1']);

        // ... and point the thread's last-post fields at the new reply.
        // These are plain values, so update_query quoting is fine here.
        $this->db->update_query(
            'threads',
            [
                'lastpost'      => $now,
                'lastposter'    => $this->db->escape_string($poster['username']),
                'lastposteruid' => (int) $poster['uid'],
            ],
            'tid = ' . (int) $thread['tid']
        );

        // Forum: bump the post count and recalculate the last-post pointer
        // (lastpost/lastposttid/lastpostsubject/lastposter) from the forum's
        // newest visible thread, which is now this one.
        update_forum_counters((int) $thread['fid'], ['posts' => '+1']);
        update_forum_lastpost((int) $thread['fid']);
    }

    private function getThread(int $tid): ?array
    {
        $query = $this->db->simple_select('threads', 'tid, fid, subject', 'tid = ' . $tid);
        $row   = $this->db->fetch_array($query);
        return $row ?: null;
    }

    private function getUser(int $uid): ?array
    {
        $query = $this->db->simple_select('users', 'uid, username', 'uid = ' . $uid);
        $row   = $this->db->fetch_array($query);
        return $row ?: null;
    }

}
