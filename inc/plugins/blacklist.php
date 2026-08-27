<?php
/**
 * Plugin Name: Blacklist
 * Plugin URI:  https://github.com/pand0rica/mybb-blacklist
 * Description: Scene-based activity blacklist with Logbook integration for RPG forums.
 * Author:      pand0rica
 * Version:     1.6.1
 * MyBB:        1.8.x
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// ---------------------------------------------------------------------------
// Hook registration
// ---------------------------------------------------------------------------

$plugins->add_hook('global_start',                  'blacklist_load_language');
$plugins->add_hook('newreply_do_newreply_end',      'blacklist_handle_new_post');
$plugins->add_hook('newthread_do_newthread_end',    'blacklist_handle_new_post');
$plugins->add_hook('logbook_scene_created',         'blacklist_on_scene_created');
$plugins->add_hook('logbook_scene_finished_changed','blacklist_on_scene_finished');
$plugins->add_hook('index_start',                   'blacklist_build_index_notice');
$plugins->add_hook('datahandler_user_delete_start', 'blacklist_handle_user_delete');

// ACP navigation
$plugins->add_hook('admin_tools_action_handler', 'blacklist_admin_tools_action_handler');
$plugins->add_hook('admin_tools_menu',           'blacklist_admin_tools_menu');
$plugins->add_hook('admin_load',                 'blacklist_load_admin_language');

// ---------------------------------------------------------------------------
// Plugin info
// ---------------------------------------------------------------------------

function blacklist_info(): array
{
    return [
        'name'          => 'Blacklist',
        'description'   => 'Scene-based activity blacklist with Logbook integration for RPG forums.',
        'website'       => 'https://github.com/pand0rica/mybb-blacklist',
        'author'        => 'pand0rica',
        'authorsite'    => 'https://github.com/pand0rica',
        'version'       => '1.6.1',
        'guid'          => '',
        'compatibility' => '18*',
    ];
}

// ---------------------------------------------------------------------------
// Install / Uninstall
// ---------------------------------------------------------------------------

function blacklist_is_installed(): bool
{
    global $db;
    return $db->table_exists('blacklist_categories');
}

function blacklist_install(): void
{
    global $db;

    $collation = $db->build_create_table_collation();

    if (!$db->table_exists('blacklist_categories')) {
        $db->write_query("
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "blacklist_categories (
                `id`                         INT(11) NOT NULL AUTO_INCREMENT,
                `name`                       VARCHAR(255) NOT NULL DEFAULT '',
                `active`                     TINYINT(1) NOT NULL DEFAULT 1,
                `type`                       VARCHAR(32) NOT NULL DEFAULT 'scene_wait',
                `threshold_days`             INT(11) NOT NULL DEFAULT 14,
                `group_ids`                  TEXT,
                `include_additional_groups`  TINYINT(1) NOT NULL DEFAULT 0,
                `response_type`              VARCHAR(32) NOT NULL DEFAULT 'none',
                `sort_order`                 INT(11) NOT NULL DEFAULT 0,
                `away_exempt`                TINYINT(1) NOT NULL DEFAULT 0,
                `frozen_profile_field`       INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$collation};
        ");
    }

    if (!$db->table_exists('blacklist_entries')) {
        $db->write_query("
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "blacklist_entries (
                `id`               INT(11) NOT NULL AUTO_INCREMENT,
                `uid`              INT(11) NOT NULL DEFAULT 0,
                `category_id`      INT(11) NOT NULL DEFAULT 0,
                `scene_id`         INT(11) NOT NULL DEFAULT 0,
                `thread_id`        INT(11) NOT NULL DEFAULT 0,
                `timestamp_added`  INT(11) NOT NULL DEFAULT 0,
                `deadline`         INT(11) NOT NULL DEFAULT 0,
                `status`           VARCHAR(16) NOT NULL DEFAULT 'open',
                `run_id`           INT(11) NOT NULL DEFAULT 0,
                `deleted_username` VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `idx_uid`         (`uid`),
                KEY `idx_category`    (`category_id`),
                KEY `idx_status`      (`status`),
                KEY `idx_run`         (`run_id`)
            ) ENGINE=InnoDB {$collation};
        ");
    }

    if (!$db->table_exists('blacklist_runs')) {
        $db->write_query("
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "blacklist_runs (
                `id`            INT(11) NOT NULL AUTO_INCREMENT,
                `created_at`    INT(11) NOT NULL DEFAULT 0,
                `closed_at`     INT(11) NOT NULL DEFAULT 0,
                `closed_by_uid` INT(11) NOT NULL DEFAULT 0,
                `status`        VARCHAR(16) NOT NULL DEFAULT 'active',
                `notified`      TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB {$collation};
        ");
    }

    // Settings are created here (install) so that activate/deactivate never
    // touch and thus never wipe the admin's configured values.
    blacklist_install_settings();
}

function blacklist_uninstall(): void
{
    global $db;

    $db->write_query('DROP TABLE IF EXISTS ' . TABLE_PREFIX . 'blacklist_entries');
    $db->write_query('DROP TABLE IF EXISTS ' . TABLE_PREFIX . 'blacklist_runs');
    $db->write_query('DROP TABLE IF EXISTS ' . TABLE_PREFIX . 'blacklist_categories');

    // Remove settings
    $db->delete_query('settings', "name LIKE 'blacklist_%'");
    $db->delete_query('settinggroups', "name = 'blacklist'");

    // Remove task
    $db->delete_query('tasks', "file = 'blacklist'");

    // Remove index template injection
    blacklist_remove_index_template_notice();

    // Remove templates and template group
    $db->delete_query('templates', "title LIKE 'blacklist%'");
    $db->delete_query('templategroups', "prefix = 'blacklist'");

    rebuild_settings();
}

// ---------------------------------------------------------------------------
// Activate / Deactivate
// ---------------------------------------------------------------------------

function blacklist_activate(): void
{
    global $db, $mybb;

    // NOTE: settings are intentionally NOT (re)installed here – they belong to
    // install/uninstall only, otherwise a reactivate would wipe admin config.
    blacklist_install_templates();
    blacklist_install_task();
    blacklist_sync_task_schedule();
    blacklist_install_stylesheet();
    blacklist_migrate_db();
    blacklist_inject_index_template();
}

function blacklist_migrate_db(): void
{
    global $db;

    if ($db->table_exists('blacklist_categories') && !$db->field_exists('away_exempt', 'blacklist_categories')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_categories ADD `away_exempt` TINYINT(1) NOT NULL DEFAULT 0");
    }

    if ($db->table_exists('blacklist_categories') && !$db->field_exists('frozen_profile_field', 'blacklist_categories')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_categories ADD `frozen_profile_field` INT(11) NOT NULL DEFAULT 0");
    }

    // Add sort_order to categories for tables created before it was part of CREATE TABLE
    if ($db->table_exists('blacklist_categories') && !$db->field_exists('sort_order', 'blacklist_categories')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_categories ADD `sort_order` INT(11) NOT NULL DEFAULT 0");
    }

    // Add deleted_username to entries table (added in 1.4.0)
    if ($db->table_exists('blacklist_entries') && !$db->field_exists('deleted_username', 'blacklist_entries')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_entries ADD `deleted_username` VARCHAR(255) NOT NULL DEFAULT ''");
    }

    // Add run_id to entries table (added in 1.2.0)
    if ($db->table_exists('blacklist_entries') && !$db->field_exists('run_id', 'blacklist_entries')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_entries ADD `run_id` INT(11) NOT NULL DEFAULT 0");
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_entries ADD KEY `idx_run` (`run_id`)");
    }

    // Create runs table and migrate existing entries (added in 1.2.0)
    if ($db->table_exists('blacklist_entries') && !$db->table_exists('blacklist_runs')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "blacklist_runs (
                `id`            INT(11) NOT NULL AUTO_INCREMENT,
                `created_at`    INT(11) NOT NULL DEFAULT 0,
                `closed_at`     INT(11) NOT NULL DEFAULT 0,
                `closed_by_uid` INT(11) NOT NULL DEFAULT 0,
                `status`        VARCHAR(16) NOT NULL DEFAULT 'active',
                `notified`      TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB {$collation};
        ");

        $run_id = $db->insert_query('blacklist_runs', [
            'created_at'    => TIME_NOW,
            'closed_at'     => 0,
            'closed_by_uid' => 0,
            'status'        => 'active',
        ]);

        if ($run_id > 0) {
            $db->update_query('blacklist_entries', ['run_id' => (int) $run_id], '1=1');
        }
    }

    // Add notified flag to runs table (added in 1.3.0)
    if ($db->table_exists('blacklist_runs') && !$db->field_exists('notified', 'blacklist_runs')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "blacklist_runs ADD `notified` TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Add task_minute setting if missing (added in 1.3.0)
    $existing = $db->fetch_array($db->simple_select('settings', 'sid', "name = 'blacklist_task_minute'"));
    if (!$existing) {
        $gid_row = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'blacklist'"));
        if ($gid_row) {
            $db->insert_query('settings', [
                'gid'         => (int) $gid_row['gid'],
                'name'        => 'blacklist_task_minute',
                'title'       => 'Task: minute',
                'description' => 'Minute (0–59) at which the task runs. Changes take effect immediately after saving.',
                'optionscode' => 'text',
                'value'       => '0',
                'disporder'   => 35,
            ]);
            rebuild_settings();
        }
    }

    // Add task_enabled setting if missing (added in 1.3.0)
    $existing = $db->fetch_array($db->simple_select('settings', 'sid', "name = 'blacklist_task_enabled'"));
    if (!$existing) {
        $gid_row = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'blacklist'"));
        if ($gid_row) {
            $db->insert_query('settings', [
                'gid'         => (int) $gid_row['gid'],
                'name'        => 'blacklist_task_enabled',
                'title'       => 'Task: enable automatic generation',
                'description' => 'Enable or disable the scheduled blacklist task. Changes take effect immediately after saving.',
                'optionscode' => 'yesno',
                'value'       => '0',
                'disporder'   => 15,
            ]);
            rebuild_settings();
        }
    }

    // Add intro text setting if missing (added in 1.1.0)
    $existing = $db->fetch_array($db->simple_select('settings', 'sid', "name = 'blacklist_intro_text'"));
    if (!$existing) {
        $gid_row = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'blacklist'"));
        if ($gid_row) {
            $db->insert_query('settings', [
                'gid'         => (int) $gid_row['gid'],
                'name'        => 'blacklist_intro_text',
                'title'       => 'Intro text (between deadline and list)',
                'description' => 'Optional free text shown between the deadline line and the blacklist entries. Supports HTML and BBCode. Leave empty to hide.',
                'optionscode' => 'textarea',
                'value'       => '',
                'disporder'   => 70,
            ]);
            rebuild_settings();
        }
    }
}

function blacklist_deactivate(): void
{
    global $db;

    // Remove templates and template group (recreated on activate)
    $db->delete_query('templates', "title LIKE 'blacklist%'");
    $db->delete_query('templategroups', "prefix = 'blacklist'");

    // Remove index template injection
    blacklist_remove_index_template_notice();

    // Deactivate task
    $db->update_query('tasks', ['enabled' => 0], "file = 'blacklist'");

    // Remove stylesheet from all themes
    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';
    $db->delete_query('themestylesheets', "name = 'blacklist.css'");
    $themes = $db->simple_select('themes', 'tid');
    while ($theme = $db->fetch_array($themes)) {
        update_theme_stylesheet_list((int) $theme['tid']);
    }
}

// ---------------------------------------------------------------------------
// Settings installation
// ---------------------------------------------------------------------------

function blacklist_install_settings(): void
{
    global $db;

    // Remove old group if exists
    $db->delete_query('settinggroups', "name = 'blacklist'");

    $gid = $db->insert_query('settinggroups', [
        'name'        => 'blacklist',
        'title'       => 'Blacklist',
        'description' => 'Settings for the scene-based Blacklist plugin.',
        'disporder'   => 80,
        'isdefault'   => 0,
    ]);

    $settings = [
        [
            'name'        => 'blacklist_deadline_days',
            'title'       => 'Deadline (days)',
            'description' => 'Number of days after which a blacklist entry expires. Default: 14.',
            'optionscode' => 'text',
            'value'       => '14',
            'disporder'   => 10,
        ],
        [
            'name'        => 'blacklist_task_enabled',
            'title'       => 'Task: enable automatic generation',
            'description' => 'Enable or disable the scheduled blacklist task. Changes take effect immediately after saving.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 15,
        ],
        [
            'name'        => 'blacklist_task_day',
            'title'       => 'Task: day of month',
            'description' => 'Day of the month on which the blacklist is generated automatically (1–28). Leave 0 for daily. Changes take effect immediately after saving.',
            'optionscode' => 'text',
            'value'       => '1',
            'disporder'   => 20,
        ],
        [
            'name'        => 'blacklist_task_hour',
            'title'       => 'Task: hour',
            'description' => 'Hour of day (0–23) at which the task runs. Changes take effect immediately after saving.',
            'optionscode' => 'text',
            'value'       => '3',
            'disporder'   => 30,
        ],
        [
            'name'        => 'blacklist_task_minute',
            'title'       => 'Task: minute',
            'description' => 'Minute (0–59) at which the task runs. Changes take effect immediately after saving.',
            'optionscode' => 'text',
            'value'       => '0',
            'disporder'   => 35,
        ],
        [
            'name'        => 'blacklist_notify_thread_id',
            'title'       => 'Notification: Thread ID',
            'description' => 'Thread ID to post in when new entries are created. Leave 0 to disable.',
            'optionscode' => 'text',
            'value'       => '0',
            'disporder'   => 40,
        ],
        [
            'name'        => 'blacklist_notify_user_id',
            'title'       => 'Notification: Poster UID',
            'description' => 'User ID that will post the notification. Leave 0 to disable.',
            'optionscode' => 'text',
            'value'       => '0',
            'disporder'   => 50,
        ],
        [
            'name'        => 'blacklist_mod_groups',
            'title'       => 'Moderator groups',
            'description' => 'Groups that can manage blacklist entries (besides admins).',
            'optionscode' => 'groupselect',
            'value'       => '',
            'disporder'   => 60,
        ],
        [
            'name'        => 'blacklist_intro_text',
            'title'       => 'Intro text (between deadline and list)',
            'description' => 'Optional free text shown between the deadline line and the blacklist entries. Supports HTML and BBCode. Leave empty to hide.',
            'optionscode' => 'textarea',
            'value'       => '',
            'disporder'   => 70,
        ],
    ];

    $db->delete_query('settings', "name LIKE 'blacklist_%'");

    foreach ($settings as $s) {
        $s['gid'] = $gid;
        $db->insert_query('settings', $s);
    }

    rebuild_settings();
}

// ---------------------------------------------------------------------------
// Task installation
// ---------------------------------------------------------------------------

function blacklist_install_task(): void
{
    global $db;

    $existing = $db->simple_select('tasks', 'tid', "file = 'blacklist'");

    if ($db->fetch_array($existing)) {
        return;
    }

    $db->insert_query('tasks', [
        'title'       => 'Blacklist Generator',
        'description' => 'Automatically generates blacklist entries based on scene activity.',
        'file'        => 'blacklist',
        'minute'      => '0',
        'hour'        => '3',
        'day'         => '1',
        'month'       => '*',
        'weekday'     => '*',
        'enabled'     => 1,
        'logging'     => 1,
        'nextrun'     => TIME_NOW,
    ]);
}

/**
 * Read blacklist_task_day / blacklist_task_hour from DB and update
 * the MyBB task entry (hour, day, nextrun) accordingly.
 */
function blacklist_sync_task_schedule(): void
{
    global $db;

    $task = $db->fetch_array(
        $db->simple_select('tasks', 'tid, minute, hour, day, enabled', "file = 'blacklist'")
    );
    if (!$task) {
        return;
    }

    // Read settings directly from DB – $mybb->settings may be stale during settings save
    $hour_row    = $db->fetch_array($db->simple_select('settings', 'value', "name = 'blacklist_task_hour'"));
    $day_row     = $db->fetch_array($db->simple_select('settings', 'value', "name = 'blacklist_task_day'"));
    $minute_row  = $db->fetch_array($db->simple_select('settings', 'value', "name = 'blacklist_task_minute'"));
    $enabled_row = $db->fetch_array($db->simple_select('settings', 'value', "name = 'blacklist_task_enabled'"));

    $hour    = isset($hour_row['value'])   ? max(0, min(23, (int) $hour_row['value']))   : 3;
    $day     = isset($day_row['value'])    ? max(0, min(28, (int) $day_row['value']))    : 1;
    $minute  = isset($minute_row['value']) ? max(0, min(59, (int) $minute_row['value'])) : 0;
    $enabled = isset($enabled_row['value']) ? (int) $enabled_row['value']                : 0;

    $new_day = $day > 0 ? (string) $day : '*';

    $schedule_changed = (
        (int) $task['hour']    !== $hour    ||
        (int) $task['minute']  !== $minute  ||
        $task['day']           !== $new_day
    );
    $enabled_changed = ((int) $task['enabled'] !== $enabled);

    if (!$schedule_changed && !$enabled_changed) {
        return;
    }

    $update = [
        'enabled' => $enabled,
        'minute'  => (string) $minute,
        'hour'    => (string) $hour,
        'day'     => $new_day,
    ];

    // Only recalculate nextrun when the actual schedule changed,
    // not when only enabled toggled – otherwise we'd overwrite a
    // pending nextrun that MyBB is waiting to fire.
    if ($schedule_changed) {
        $update['nextrun'] = blacklist_compute_next_run($hour, $minute, $day);
    }

    $db->update_query('tasks', $update, "file = 'blacklist'");
}

/**
 * Compute the next Unix timestamp at which the task should run.
 */
function blacklist_compute_next_run(int $hour, int $minute, int $day): int
{
    $now   = TIME_NOW;
    $month = (int) date('n', $now);
    $year  = (int) date('Y', $now);

    if ($day > 0) {
        $candidate = mktime($hour, $minute, 0, $month, $day, $year);
        if ($candidate <= $now) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            $candidate = mktime($hour, $minute, 0, $month, $day, $year);
        }
    } else {
        // day = 0 → run daily
        $candidate = mktime($hour, $minute, 0, $month, (int) date('j', $now), $year);
        if ($candidate <= $now) {
            $candidate = mktime($hour, $minute, 0, $month, (int) date('j', $now) + 1, $year);
        }
    }

    return (int) $candidate;
}

// ---------------------------------------------------------------------------
// Template installation
// ---------------------------------------------------------------------------

function blacklist_install_template_group(): void
{
    global $db;

    $db->delete_query('templategroups', "prefix = 'blacklist'");
    $db->insert_query('templategroups', [
        'title'     => 'Blacklist',
        'prefix'    => 'blacklist',
        'isdefault' => 0,
    ]);
}

function blacklist_install_templates(): void
{
    global $db;

    blacklist_install_template_group();
    $db->delete_query('templates', "title LIKE 'blacklist%'");

    $templates_dir = MYBB_ROOT . 'inc/plugins/blacklist/templates/';

    $files = [
        'blacklist'              => 'blacklist.tpl',
        'blacklist_intro'        => 'blacklist_intro.tpl',
        'blacklist_category'     => 'blacklist_category.tpl',
        'blacklist_entry'        => 'blacklist_entry.tpl',
        'blacklist_entry_button' => 'blacklist_entry_button.tpl',
        'blacklist_activity'          => 'blacklist_activity.tpl',
        'blacklist_activity_row'     => 'blacklist_activity_row.tpl',
        'blacklist_activity_category'=> 'blacklist_activity_category.tpl',
        'blacklist_activity_user_row'=> 'blacklist_activity_user_row.tpl',
        'blacklist_no_entries'       => 'blacklist_no_entries.tpl',
        'blacklist_run_section'      => 'blacklist_run_section.tpl',
        'blacklist_closed_entry'     => 'blacklist_closed_entry.tpl',
    ];

    foreach ($files as $title => $file) {
        $path = $templates_dir . $file;

        if (!file_exists($path)) {
            continue;
        }

        $content = file_get_contents($path);

        $db->insert_query('templates', [
            'title'    => $db->escape_string($title),
            'template' => $db->escape_string($content),
            'sid'      => -2,
            'version'  => '1800',
            'dateline' => TIME_NOW,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Stylesheet installation
// ---------------------------------------------------------------------------

function blacklist_install_stylesheet(): void
{
    global $db;

    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

    $css_file = MYBB_ROOT . 'inc/plugins/blacklist/assets/blacklist.css';

    if (!file_exists($css_file)) {
        return;
    }

    $css_escaped = $db->escape_string(file_get_contents($css_file));

    $themes = $db->simple_select('themes', 'tid');
    while ($theme = $db->fetch_array($themes)) {
        $tid = (int) $theme['tid'];

        $existing = $db->fetch_array(
            $db->simple_select('themestylesheets', 'sid', "name = 'blacklist.css' AND tid = '{$tid}'")
        );

        if ($existing) {
            $db->update_query('themestylesheets', [
                'stylesheet'   => $css_escaped,
                'lastmodified' => TIME_NOW,
            ], "sid = '" . (int) $existing['sid'] . "'", 1);
        } else {
            $sid = $db->insert_query('themestylesheets', [
                'name'         => 'blacklist.css',
                'tid'          => $tid,
                'attachedto'   => '',
                'stylesheet'   => $css_escaped,
                'cachefile'    => 'blacklist.css',
                'lastmodified' => TIME_NOW,
            ]);
            $db->update_query('themestylesheets', ['cachefile' => "css.php?stylesheet={$sid}"], "sid = '{$sid}'", 1);
        }

        update_theme_stylesheet_list($tid);
    }
}

// ---------------------------------------------------------------------------
// Hook handlers
// ---------------------------------------------------------------------------

function blacklist_load_language(): void
{
    global $lang;
    $lang->load('blacklist');
}

/**
 * Hook: newreply_do_newreply_end / newthread_do_newthread_end
 * Auto-resolve posting-requirement entries when a user posts.
 */
function blacklist_handle_new_post(): void
{
    global $mybb, $db, $pid, $tid;

    if (!isset($mybb->user['uid']) || $mybb->user['uid'] <= 0) {
        return;
    }

    $uid = (int) $mybb->user['uid'];
    $current_tid = isset($tid) ? (int) $tid : 0;

    if ($current_tid <= 0) {
        return;
    }

    require_once MYBB_ROOT . 'inc/plugins/blacklist/core/engine.php';
    $engine = new Blacklist_Engine();

    // Find open posting-requirement entries for this user in this thread
    $query = $db->query("
        SELECT e.id, e.timestamp_added
        FROM " . TABLE_PREFIX . "blacklist_entries e
        INNER JOIN " . TABLE_PREFIX . "blacklist_categories c ON c.id = e.category_id
        WHERE e.uid       = {$uid}
          AND e.thread_id = {$current_tid}
          AND e.status    = 'open'
          AND c.response_type = 'posting'
    ");

    while ($entry = $db->fetch_array($query)) {
        // The new post's timestamp is roughly TIME_NOW; entry was added before this post
        $engine->resolveEntry((int) $entry['id']);
    }
}

/**
 * Hook: logbook_scene_created
 * Immediately check scene participants against open categories when a new scene is created.
 */
function blacklist_on_scene_created(array &$hook_data): void
{
    // Intentionally lightweight – full check runs via scheduled task.
    // Could be extended to immediately flag participants if needed.
}

/**
 * Hook: logbook_scene_finished_changed
 * When a scene is marked finished, auto-resolve any open scene_wait entries for that scene.
 */
function blacklist_on_scene_finished(array &$hook_data): void
{
    global $db;

    if (empty($hook_data['scene_id']) || empty($hook_data['finished'])) {
        return;
    }

    $scene_id = (int) $hook_data['scene_id'];

    // Resolve all open entries tied to this scene
    $db->update_query(
        'blacklist_entries',
        ['status' => 'resolved'],
        "scene_id = {$scene_id} AND status = 'open'"
    );
}

/**
 * Hook: datahandler_user_delete_start
 * Runs just before MyBB removes the user row(s). For every account that is
 * about to be deleted and still has blacklist entries, we persist the current
 * username onto those entries (`deleted_username`). This keeps the account
 * visible on the blacklist afterwards – the frontend then shows the stored
 * name plus a "account deleted" note instead of dropping the entry entirely.
 *
 * @param UserDataHandler $dh
 */
function blacklist_handle_user_delete(&$dh): void
{
    global $db;

    if (!$db->table_exists('blacklist_entries') || !$db->field_exists('deleted_username', 'blacklist_entries')) {
        return;
    }

    // $dh->delete_uids is still an array of ints at this point (set before the hook fires)
    $uids = isset($dh->delete_uids) ? (array) $dh->delete_uids : [];

    foreach ($uids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
            continue;
        }

        // Only touch accounts that actually have entries not yet flagged as deleted
        $check = $db->simple_select(
            'blacklist_entries',
            'id',
            'uid = ' . $uid . " AND deleted_username = ''",
            ['limit' => 1]
        );
        if (!$db->fetch_array($check)) {
            continue;
        }

        // Capture the username while the user row still exists
        $user = $db->fetch_array($db->simple_select('users', 'username', 'uid = ' . $uid));
        $name = ($user && $user['username'] !== '') ? $user['username'] : ('#' . $uid);

        $db->update_query(
            'blacklist_entries',
            ['deleted_username' => $db->escape_string($name)],
            'uid = ' . $uid . " AND deleted_username = ''"
        );
    }
}

// ---------------------------------------------------------------------------
// Index notice: template injection helpers
// ---------------------------------------------------------------------------

/**
 * Appends {$blacklist_index_notice} after {$header} in all index templates.
 * Runs on activate(); safe to call multiple times (checks for duplicates).
 */
function blacklist_inject_index_template(): void
{
    global $db;

    $query = $db->simple_select('templates', 'tid, template', "title = 'index'");
    while ($tpl = $db->fetch_array($query)) {
        if (strpos($tpl['template'], '{$blacklist_index_notice}') !== false) {
            continue;
        }
        $new = str_replace('{$header}', '{$header}' . "\n" . '{$blacklist_index_notice}', $tpl['template']);
        if ($new === $tpl['template']) {
            // Fallback: prepend if {$header} not found
            $new = '{$blacklist_index_notice}' . $tpl['template'];
        }
        $db->update_query('templates', [
            'template' => $db->escape_string($new),
            'dateline' => TIME_NOW,
        ], 'tid = ' . (int) $tpl['tid']);
    }
}

/**
 * Removes {$blacklist_index_notice} from all index templates.
 * Runs on deactivate().
 */
function blacklist_remove_index_template_notice(): void
{
    global $db;

    $query = $db->simple_select('templates', 'tid, template', "title = 'index'");
    while ($tpl = $db->fetch_array($query)) {
        if (strpos($tpl['template'], '{$blacklist_index_notice}') === false) {
            continue;
        }
        $new = str_replace("\n" . '{$blacklist_index_notice}', '', $tpl['template']);
        $new = str_replace('{$blacklist_index_notice}', '', $new);
        $db->update_query('templates', [
            'template' => $db->escape_string($new),
            'dateline' => TIME_NOW,
        ], 'tid = ' . (int) $tpl['tid']);
    }
}

// ---------------------------------------------------------------------------
// Index notice: hook handler
// ---------------------------------------------------------------------------

/**
 * Hook: index_start
 * Builds the blacklist notice for the main account if any of their
 * characters have open entries in the active run.
 */
function blacklist_build_index_notice(): void
{
    global $mybb, $db, $lang, $blacklist_index_notice;

    $blacklist_index_notice = '';

    if (!isset($mybb->user['uid']) || $mybb->user['uid'] <= 0) {
        return;
    }

    $current_uid = (int) $mybb->user['uid'];

    // Determine which accounts to check (as_uid: EnhancedAccountSwitcher).
    // - Main account (as_uid = 0): check the main account itself plus all of
    //   its linked sub-accounts (characters) -> one aggregated notice.
    // - Sub-account (playing as a character) or no switcher present: check
    //   only the currently logged-in account's own entries.
    $has_switcher = array_key_exists('as_uid', $mybb->user);
    $is_main      = $has_switcher && (int) $mybb->user['as_uid'] === 0;

    $uids = [$current_uid];
    if ($is_main) {
        $sub_query = $db->simple_select('users', 'uid', 'as_uid = ' . $current_uid);
        while ($sub = $db->fetch_array($sub_query)) {
            $uids[] = (int) $sub['uid'];
        }
    }

    $uids = array_values(array_unique($uids));
    if (empty($uids)) {
        return;
    }

    // Active run
    $run = $db->fetch_array(
        $db->simple_select('blacklist_runs', 'id', "status = 'active'", ['order_by' => 'id DESC', 'limit' => 1])
    );
    if (!$run) {
        return;
    }

    $run_id   = (int) $run['id'];
    $uid_list = implode(',', $uids);

    // Open entries for these characters
    $query = $db->query("
        SELECT e.uid, e.deadline,
               u.username, u.usergroup, u.displaygroup,
               c.name AS cat_name, c.response_type AS cat_response
        FROM " . TABLE_PREFIX . "blacklist_entries e
        INNER JOIN " . TABLE_PREFIX . "users u ON u.uid = e.uid
        INNER JOIN " . TABLE_PREFIX . "blacklist_categories c ON c.id = e.category_id
        WHERE e.uid    IN ({$uid_list})
          AND e.run_id  = {$run_id}
          AND e.status  = 'open'
        ORDER BY e.deadline ASC
    ");

    $entries = [];
    while ($row = $db->fetch_array($query)) {
        $entries[] = $row;
    }

    if (empty($entries)) {
        return;
    }

    $blacklist_url = htmlspecialchars_uni($mybb->settings['bburl'] . '/blacklist.php');

    $rows_html = '';
    foreach ($entries as $e) {
        $char_name = format_name(htmlspecialchars_uni($e['username']), $e['usergroup'], $e['displaygroup']);
        $deadline  = my_date('d.m.Y', (int) $e['deadline']);
        $cat       = htmlspecialchars_uni($e['cat_name']);

        switch ($e['cat_response']) {
            case 'button':
                $action = $lang->blacklist_notice_action_button;
                break;
            case 'posting':
                $action = $lang->blacklist_notice_action_posting;
                break;
            default:
                $action = $lang->blacklist_notice_action_none;
                break;
        }

        $rows_html .= '<li>' . $char_name . ' &mdash; ' . $cat
            . ' &mdash; ' . $lang->blacklist_notice_until . ': <strong>' . $deadline . '</strong>'
            . ' &mdash; ' . $action . '</li>';
    }

    $blacklist_index_notice =
        '<table border="0" cellspacing="1" cellpadding="0" class="tborder" style="margin-bottom:10px;width:100%">'
        . '<tr><td class="tcat" style="padding:7px 12px">'
        . '<strong>' . $lang->blacklist_notice_title . '</strong>'
        . ' &nbsp;<a href="' . $blacklist_url . '" class="smalltext">' . $lang->blacklist_notice_link . ' &rarr;</a>'
        . '</td></tr>'
        . '<tr><td class="trow2" style="padding:8px 12px">'
        . '<ul style="margin:0;padding-left:1.4em;line-height:1.8">' . $rows_html . '</ul>'
        . '</td></tr>'
        . '</table>';
}

// ---------------------------------------------------------------------------
// ACP navigation hooks
// ---------------------------------------------------------------------------

function blacklist_admin_tools_action_handler(&$actions)
{
    $actions['blacklist'] = [
        'active' => 'blacklist',
        'file'   => 'blacklist.php',
    ];

    return $actions;
}

function blacklist_admin_tools_menu(&$sub_menu)
{
    global $lang;

    $lang->load('blacklist', true);

    $sub_menu[] = [
        'id'    => 'blacklist',
        'title' => isset($lang->blacklist_acp_title) ? $lang->blacklist_acp_title : 'Blacklist',
        'link'  => 'index.php?module=tools-blacklist',
    ];
}

function blacklist_load_admin_language(): void
{
    global $lang;
    $lang->load('blacklist');
    blacklist_sync_task_schedule();
}

// ---------------------------------------------------------------------------
// Permission helper
// ---------------------------------------------------------------------------

/**
 * Returns true if the current user can manage blacklist entries.
 */
function blacklist_is_team(): bool
{
    global $mybb;

    if (!isset($mybb->user['uid']) || $mybb->user['uid'] <= 0) {
        return false;
    }

    // Admins always have access
    if ($mybb->usergroup['cancp'] || $mybb->usergroup['issupermod']) {
        return true;
    }

    $mod_groups = array_filter(
        array_map('intval', explode(',', (string) ($mybb->settings['blacklist_mod_groups'] ?? '')))
    );

    if (empty($mod_groups)) {
        return false;
    }

    $uid_group   = (int) $mybb->user['usergroup'];
    $add_groups  = array_filter(
        array_map('intval', explode(',', (string) ($mybb->user['additionalgroups'] ?? '')))
    );

    if (in_array($uid_group, $mod_groups)) {
        return true;
    }

    foreach ($add_groups as $ag) {
        if (in_array($ag, $mod_groups)) {
            return true;
        }
    }

    return false;
}
