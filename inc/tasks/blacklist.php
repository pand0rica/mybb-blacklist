<?php
/**
 * Blacklist – Scheduled Task
 *
 * Called by MyBB's task system on the configured day/hour.
 * Generates blacklist entries for all active categories.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

function task_blacklist(array $task): void
{
    global $db, $mybb, $lang;

    $lang->load('blacklist');

    require_once MYBB_ROOT . 'inc/plugins/blacklist/core/engine.php';

    $engine = new Blacklist_Engine();

    try {
        $stats = $engine->run();

        add_task_log(
            $task,
            sprintf(
                'Blacklist task complete. Created: %d, Skipped: %d.',
                $stats['created'],
                $stats['skipped']
            )
        );
    } catch (\Throwable $e) {
        add_task_log(
            $task,
            'Blacklist task error: ' . $e->getMessage()
        );
    }
}
