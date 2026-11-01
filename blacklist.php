<?php
/**
 * Blacklist – Frontend Page
 *
 * blacklist.php              – current open blacklist (grouped by category)
 * blacklist.php?action=activity  – live simulation (no DB write)
 * blacklist.php?action=activity&debug=1 – scene_wait trace (team only)
 *
 * POST actions (all require my_post_key):
 *   resolve       – self-removal of the user's own entry
 *   add           – manual entry (team only)
 *   delete        – delete entry (team only)
 *   team_resolve  – resolve entry manually (team only)
 *   close_run     – mark the active run as done (team only)
 *   new_run       – start a new run (team only)
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'blacklist.php');

require_once './global.php';
require_once MYBB_ROOT . 'inc/plugins/blacklist/core/engine.php';

$lang->load('blacklist');

// Page title
add_breadcrumb($lang->blacklist_title, "blacklist.php");

$engine = new Blacklist_Engine();
$action = $mybb->get_input('action');

// Write actions are POST only; team actions additionally need team rights.
$blacklist_write_actions = ['resolve', 'add', 'delete', 'team_resolve', 'close_run', 'new_run'];
$blacklist_team_actions  = ['add', 'delete', 'team_resolve', 'close_run', 'new_run'];

if (in_array($action, $blacklist_write_actions, true)) {
    if ($mybb->request_method !== 'post') {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));

    if (in_array($action, $blacklist_team_actions, true) && !blacklist_is_team()) {
        error_no_permission();
    }
}

// ------------------------------------------------------------------
// Self-removal: resolve own entry
// ------------------------------------------------------------------

if ($action === 'resolve') {

    if (!$mybb->user['uid']) {
        error_no_permission();
    }

    $entry_id = (int) $mybb->get_input('entry_id', MyBB::INPUT_INT);

    if ($entry_id <= 0) {
        error($lang->blacklist_invalid_entry);
    }

    $success = $engine->attemptSelfRemoval($entry_id, (int) $mybb->user['uid']);

    if ($success) {
        redirect('blacklist.php', $lang->blacklist_resolved_success);
    } else {
        error($lang->blacklist_resolved_failed);
    }
}

// ------------------------------------------------------------------
// Team: add manual entry
// ------------------------------------------------------------------

if ($action === 'add') {

    $uid         = (int) $mybb->get_input('uid', MyBB::INPUT_INT);
    $category_id = (int) $mybb->get_input('category_id', MyBB::INPUT_INT);
    $thread_id   = (int) $mybb->get_input('thread_id', MyBB::INPUT_INT);

    if ($uid <= 0 || $category_id <= 0) {
        error($lang->blacklist_invalid_input);
    }

    $scene_id = $thread_id > 0
        ? (new Blacklist_LogbookAdapter())->getSceneIdByThread($thread_id)
        : null;

    $engine->createEntry($uid, $category_id, $scene_id, $thread_id > 0 ? $thread_id : null);

    redirect('blacklist.php', $lang->blacklist_entry_added);
}

// ------------------------------------------------------------------
// Team: delete entry
// ------------------------------------------------------------------

if ($action === 'delete') {

    $entry_id = (int) $mybb->get_input('entry_id', MyBB::INPUT_INT);

    if ($entry_id <= 0) {
        error($lang->blacklist_invalid_entry);
    }

    $engine->deleteEntry($entry_id);
    redirect('blacklist.php', $lang->blacklist_entry_deleted);
}

// ------------------------------------------------------------------
// Team: manually resolve an entry
// ------------------------------------------------------------------

if ($action === 'team_resolve') {

    $entry_id = (int) $mybb->get_input('entry_id', MyBB::INPUT_INT);

    if ($entry_id <= 0) {
        error($lang->blacklist_invalid_entry);
    }

    $engine->resolveEntry($entry_id);
    redirect('blacklist.php', $lang->blacklist_resolved_success);
}

// ------------------------------------------------------------------
// Team: close current run (mark as done)
// ------------------------------------------------------------------

if ($action === 'close_run') {

    $run = $engine->getActiveRun();

    if (!$run) {
        error($lang->blacklist_no_active_run);
    }

    $engine->closeRun((int) $run['id'], (int) $mybb->user['uid']);
    redirect('blacklist.php', $lang->blacklist_run_closed_success);
}

// ------------------------------------------------------------------
// Team: start a new run
// ------------------------------------------------------------------

if ($action === 'new_run') {

    if (!$engine->getActiveRun()) {
        $engine->createRun();
        $engine->run();
    }

    redirect('blacklist.php', $lang->blacklist_run_started);
}

// ------------------------------------------------------------------
// Debug: scene_wait trace  (action=activity&debug=1, team only)
// ------------------------------------------------------------------

if ($action === 'activity' && $mybb->get_input('debug', MyBB::INPUT_INT) && blacklist_is_team()) {
    $trace      = $engine->debugSceneWait();
    $debug_rows = '';

    foreach ($trace as $t) {
        $skip_reason = (string) ($t['skip_reason'] ?? '');

        if ($skip_reason !== '' && strpos($skip_reason, 'solo') !== false) {
            $tr_class = 'solo';
        } elseif ($skip_reason !== '' && strpos($skip_reason, 'not next') !== false) {
            $tr_class = 'not-next';
        } elseif (!empty($t['condition_met'])) {
            $tr_class = 'hit';
        } else {
            $tr_class = 'ok';
        }

        $d_category     = htmlspecialchars_uni($t['category_name']);
        $d_user         = htmlspecialchars_uni($t['username']) . ' (' . (int) $t['uid'] . ')';
        $d_scene_id     = (int) $t['scene_id'];
        $d_tid          = (int) $t['tid'];
        $d_free_order   = (int) $t['free_order'];
        $d_participants = $t['participant_count'] !== null ? (int) $t['participant_count'] : '–';
        $d_next_poster  = $t['next_poster_uid'] !== null ? (int) $t['next_poster_uid'] : '–';
        $d_last_post    = $t['last_post'] ? my_date('d.m.Y H:i', (int) $t['last_post']) : '–';
        $d_note         = isset($t['skip_reason_note']) ? '<br /><em>' . htmlspecialchars_uni($t['skip_reason_note']) . '</em>' : '';
        $d_days         = $t['days_waiting'] !== null ? (int) $t['days_waiting'] : '–';
        $d_threshold    = (int) $t['threshold_days'];
        $d_condition    = !empty($t['condition_met']) ? '<strong>' . $lang->blacklist_debug_yes . '</strong>' : $lang->blacklist_debug_no;
        $d_skip         = $skip_reason !== '' ? htmlspecialchars_uni($skip_reason) : '–';

        eval('$debug_rows .= "' . $templates->get('blacklist_debug_row') . '";');
    }

    if ($debug_rows === '') {
        $debug_rows = '<tr class="trow1"><td colspan="12" class="blacklist-empty-row">' . $lang->blacklist_debug_empty . '</td></tr>';
    }

    add_breadcrumb($lang->blacklist_debug_title);
    eval('$page = "' . $templates->get('blacklist_debug') . '";');
    output_page($page);
    exit;
}

// ------------------------------------------------------------------
// Activity simulation: all categories with affected users
// ------------------------------------------------------------------

if ($action === 'activity') {
    $simulation      = $engine->simulateAll();
    $categories_html = '';

    foreach ($simulation as $cat_data) {
        $category      = $cat_data['category'];
        $affected      = $cat_data['affected'];
        $cat_name      = htmlspecialchars_uni($category['name']);
        $threshold     = (int) $category['threshold_days'];
        $condition_str = blacklist_activity_condition($category['type'], $threshold, $lang);

        $col_user_header = $lang->blacklist_col_user;
        $col_header      = $category['type'] === 'scene_wait'
            ? $lang->blacklist_col_scenes
            : blacklist_activity_date_label($category['type'], $lang);

        $users_html = '';

        if (empty($affected)) {
            eval('$users_html = "' . $templates->get('blacklist_activity_empty_row') . '";');
        } else {
            foreach ($affected as $item) {
                $user        = $item['user'];
                $username    = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
                $profile_url = htmlspecialchars_uni($mybb->settings['bburl'] . '/member.php?action=profile&uid=' . (int) $user['uid']);
                $detail_html = '';

                if (!empty($item['scenes'])) {
                    $scene_items = '';
                    foreach ($item['scenes'] as $sc) {
                        $sc_scene = $sc['scene'];
                        $sc_days  = (int) $sc['days_inactive'];
                        $sc_date  = my_date('d.m.Y', (int) $sc['last_timestamp']);
                        $sc_subj  = htmlspecialchars_uni(
                            isset($sc_scene['subject']) ? $sc_scene['subject'] : '#' . (int) $sc_scene['tid']
                        );
                        $sc_link  = !empty($sc_scene['tid'])
                            ? '<a href="' . htmlspecialchars_uni(
                                $mybb->settings['bburl'] . '/showthread.php?tid=' . (int) $sc_scene['tid']
                              ) . '">' . $sc_subj . '</a>'
                            : $sc_subj;
                        eval('$scene_items .= "' . $templates->get('blacklist_activity_scene_item') . '";');
                    }
                    eval('$detail_html = "' . $templates->get('blacklist_activity_scene_list') . '";');
                } else {
                    $last_ts  = $item['last_timestamp'];
                    $date_str = $last_ts ? my_date('d.m.Y', (int) $last_ts) : '–';
                    $days_str = $item['days_inactive'] !== null
                        ? ' (' . (int) $item['days_inactive'] . ' ' . $lang->blacklist_lbl_days . ')'
                        : '';
                    eval('$detail_html = "' . $templates->get('blacklist_activity_date') . '";');
                }

                eval('$users_html .= "' . $templates->get('blacklist_activity_user_row') . '";');
            }
        }

        eval('$categories_html .= "' . $templates->get('blacklist_activity_category') . '";');
    }

    $no_entries_html = '';
    if (empty($simulation)) {
        eval('$no_entries_html = "' . $templates->get('blacklist_no_entries') . '";');
    }

    eval('$page = "' . $templates->get('blacklist_activity') . '";');
    output_page($page);
    exit;
}

// ------------------------------------------------------------------
// Main blacklist view
// ------------------------------------------------------------------

$active_run  = $engine->getActiveRun();
$closed_run  = $engine->getLastClosedRun();
$is_team     = blacklist_is_team();
$activity_url = htmlspecialchars_uni($mybb->settings['bburl'] . '/blacklist.php?action=activity');

$run_notice_html    = '';
$run_actions_html   = '';
$closed_run_html    = '';
$add_form_html      = '';
$deadline_info_html = '';
$intro_html         = '';
$categories_html    = '';
$no_entries_html    = '';

if ($active_run) {

    // ── Active run: show current entries ──────────────────────────
    $engine->resolveObsoleteSceneEntries();
    $all_entries = $engine->getAllEntries();
    $categories  = $engine->getAllCategories();

    $cat_index = [];
    foreach ($categories as $cat) {
        $cat_index[(int) $cat['id']] = $cat;
    }

    $grouped = [];
    foreach ($all_entries as $entry) {
        $cid = (int) $entry['category_id'];
        if (!isset($grouped[$cid])) {
            $grouped[$cid] = [];
        }
        $grouped[$cid][] = $entry;
    }

    // Max deadline for header
    $max_deadline = 0;
    foreach ($all_entries as $entry) {
        if ($entry['status'] === 'open' && (int) $entry['deadline'] > $max_deadline) {
            $max_deadline = (int) $entry['deadline'];
        }
    }
    if ($max_deadline > 0) {
        $dl_str      = my_date('d.m.Y', $max_deadline);
        $overdue_cls = $max_deadline < TIME_NOW ? ' blacklist-overdue' : '';
        eval('$deadline_info_html = "' . $templates->get('blacklist_deadline_info') . '";');
    }

    // Intro text
    $raw_intro = trim((string) ($mybb->settings['blacklist_intro_text'] ?? ''));
    if ($raw_intro !== '') {
        global $parser;
        if (!is_object($parser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';
            $parser = new postParser();
        }
        $parsed    = $parser->parse_message($raw_intro, [
            'allow_html'   => 1,
            'allow_mycode' => 1,
            'allow_smilies'=> 0,
            'allow_imgcode'=> 1,
            'nl2br'        => 1,
        ]);
        eval('$intro_html = "' . $templates->get('blacklist_intro') . '";');
    }

    // Build category tables
    foreach ($cat_index as $cid => $category) {
        if (empty($grouped[$cid])) {
            continue;
        }

        $entries_html = '';
        $cat_name     = htmlspecialchars_uni($category['name']);

        $has_scene_col = $category['type'] === 'scene_wait';
        if ($has_scene_col) {
            eval('$cat_col2_header = "' . $templates->get('blacklist_category_scene_header') . '";');
            $user_col_width = 'width="22%"';
        } else {
            $cat_col2_header = '';
            $user_col_width  = 'width="85%"';
        }

        foreach ($grouped[$cid] as $entry) {
            $entry_id       = (int) $entry['id'];
            $user_html      = blacklist_entry_user_html($entry);
            $thread_link    = '';
            $entry_col2     = '';
            $overdue_class  = '';
            $resolved_class = '';
            $is_resolved    = $entry['status'] === 'resolved';

            if ($is_resolved) {
                $resolved_class = ' blacklist-resolved';
            } else {
                $overdue_class = $entry['deadline'] < TIME_NOW ? ' blacklist-overdue' : '';
            }

            if ($has_scene_col) {
                if ((int) $entry['thread_id'] > 0 && !empty($entry['thread_subject'])) {
                    $t_url       = htmlspecialchars_uni($mybb->settings['bburl'] . '/showthread.php?tid=' . (int) $entry['thread_id']);
                    $t_subj      = htmlspecialchars_uni($entry['thread_subject']);
                    $thread_link = '<a href="' . $t_url . '">' . $t_subj . '</a>';
                }
                eval('$entry_col2 = "' . $templates->get('blacklist_entry_scene_cell') . '";');
            }

            $action_html = '';

            if ($is_resolved) {
                eval('$action_html = "' . $templates->get('blacklist_entry_resolved') . '";');
                if ($is_team) {
                    $action_html .= ' ' . blacklist_entry_button('delete', $entry_id, $lang->blacklist_btn_delete, $lang->blacklist_confirm_delete);
                }
            } else {
                if ((int) $entry['uid'] === (int) $mybb->user['uid']) {
                    $response = $entry['response_type'] ?? $category['response_type'];
                    if ($response === 'button' || $response === 'posting') {
                        $action_html .= blacklist_entry_button('resolve', $entry_id, $lang->blacklist_btn_im_active);
                    }
                }

                if ($is_team) {
                    $action_html .= blacklist_entry_button('team_resolve', $entry_id, $lang->blacklist_btn_resolve);
                    $action_html .= blacklist_entry_button('delete', $entry_id, $lang->blacklist_btn_delete, $lang->blacklist_confirm_delete);
                }

                if ($action_html === '') {
                    eval('$action_html = "' . $templates->get('blacklist_entry_no_action') . '";');
                }
            }

            eval('$entries_html .= "' . $templates->get('blacklist_entry') . '";');
        }

        eval('$categories_html .= "' . $templates->get('blacklist_category') . '";');
    }

    if (empty($categories_html)) {
        eval('$no_entries_html = "' . $templates->get('blacklist_no_entries') . '";');
    }

    // Team: add-entry form
    if ($is_team) {
        $all_cats_options = '';
        foreach ($cat_index as $cat) {
            $all_cats_options .= '<option value="' . (int) $cat['id'] . '">'
                . htmlspecialchars_uni($cat['name']) . '</option>';
        }
        eval('$add_form_html = "' . $templates->get('blacklist_add_form') . '";');

        // Team: close run button
        $run_actions_html = blacklist_run_button('close_run', $lang->blacklist_btn_close_run, $lang->blacklist_run_close_confirm);
    }

} elseif ($closed_run) {

    // ── Closed run: archived view ─────────────────────────────────
    $closed_by_uid = (int) $closed_run['closed_by_uid'];
    $closed_at_ts  = (int) $closed_run['closed_at'];
    $closed_date   = $closed_at_ts > 0 ? my_date('d.m.Y', $closed_at_ts) : '–';

    if ($closed_by_uid > 0) {
        $closer = $db->fetch_array($db->simple_select('users', 'uid, username, usergroup, displaygroup', 'uid = ' . $closed_by_uid));
        $closer_name = $closer
            ? format_name(htmlspecialchars_uni($closer['username']), $closer['usergroup'], $closer['displaygroup'])
            : '#' . $closed_by_uid;
    } else {
        $closer_name = '–';
    }

    $run_closed_text = sprintf($lang->blacklist_run_closed_notice, '<strong>' . $closed_date . '</strong>', $closer_name);
    eval('$run_notice_html = "' . $templates->get('blacklist_run_notice') . '";');

    $closed_entries = $engine->getEntriesForRun((int) $closed_run['id']);

    $responded     = [];
    $not_responded = [];
    foreach ($closed_entries as $entry) {
        if ($entry['status'] === 'resolved') {
            $responded[] = $entry;
        } else {
            $not_responded[] = $entry;
        }
    }

    $no_entries_row = '';
    eval('$no_entries_row = "' . $templates->get('blacklist_run_empty_row') . '";');

    // Section 1: responded
    $section_entries_html = '';
    foreach ($responded as $entry) {
        $user_html   = blacklist_entry_user_html($entry);
        $cat_name    = htmlspecialchars_uni($entry['category_name']);
        eval('$section_entries_html .= "' . $templates->get('blacklist_closed_entry') . '";');
    }
    if ($section_entries_html === '') {
        $section_entries_html = $no_entries_row;
    }
    $section_title     = $lang->blacklist_run_section_responded;
    $section_hdr_class = '';
    eval('$responded_section_html = "' . $templates->get('blacklist_run_section') . '";');

    // Section 2: did not respond
    $section_entries_html = '';
    foreach ($not_responded as $entry) {
        $user_html   = blacklist_entry_user_html($entry);
        $cat_name    = htmlspecialchars_uni($entry['category_name']);
        eval('$section_entries_html .= "' . $templates->get('blacklist_closed_entry') . '";');
    }
    if ($section_entries_html === '') {
        $section_entries_html = $no_entries_row;
    }
    $section_title     = $lang->blacklist_run_section_not_responded;
    $section_hdr_class = ' blacklist-tcat-danger';
    eval('$not_responded_section_html = "' . $templates->get('blacklist_run_section') . '";');

    $closed_run_html = $responded_section_html . $not_responded_section_html;

    // Team: new run button
    if ($is_team) {
        $run_actions_html = blacklist_run_button('new_run', $lang->blacklist_btn_new_run);
    }

} else {

    // ── No run yet ────────────────────────────────────────────────
    eval('$no_entries_html = "' . $templates->get('blacklist_no_entries') . '";');

    if ($is_team) {
        $run_actions_html = blacklist_run_button('new_run', $lang->blacklist_btn_new_run);
    }
}

eval('$page = "' . $templates->get('blacklist') . '";');
output_page($page);

// ------------------------------------------------------------------
// Entry rendering helpers
// ------------------------------------------------------------------

/**
 * Build the user cell HTML for a blacklist entry.
 *
 * For a normal account it returns a formatted, linked username. For a deleted
 * account (LEFT JOIN found no users row → username is null) it falls back to
 * the stored `deleted_username` plus a "(account deleted)" note and renders no
 * profile link, since the profile no longer exists.
 */
function blacklist_entry_user_html(array $entry): string
{
    global $mybb, $lang, $templates;

    $user_deleted = !isset($entry['username']) || $entry['username'] === null || $entry['username'] === '';

    if ($user_deleted) {
        $stored = isset($entry['deleted_username']) ? (string) $entry['deleted_username'] : '';
        if ($stored === '') {
            $stored = '#' . (int) $entry['uid'];
        }
        $stored_name = htmlspecialchars_uni($stored);
        $html        = '';
        eval('$html = "' . $templates->get('blacklist_user_deleted') . '";');
        return $html;
    }

    $username    = format_name(htmlspecialchars_uni($entry['username']), $entry['usergroup'], $entry['displaygroup']);
    $profile_url = htmlspecialchars_uni($mybb->settings['bburl'] . '/member.php?action=profile&uid=' . (int) $entry['uid']);
    $html        = '';
    eval('$html = "' . $templates->get('blacklist_user_link') . '";');
    return $html;
}

/**
 * Renders one entry action (POST form + input.button) via template
 * blacklist_entry_button. $confirm adds a JS confirmation on submit.
 */
function blacklist_entry_button(string $action, int $entry_id, string $label, string $confirm = ''): string
{
    global $mybb, $templates;

    $button_action  = htmlspecialchars_uni($action);
    $button_label   = htmlspecialchars_uni($label);
    $button_confirm = $confirm !== '' ? ' onsubmit="return confirm(' . blacklist_js_string($confirm) . ');"' : '';
    $html           = '';
    eval('$html = "' . $templates->get('blacklist_entry_button') . '";');
    return $html;
}

/**
 * Renders a run action (close_run / new_run) via template blacklist_run_button.
 */
function blacklist_run_button(string $action, string $label, string $confirm = ''): string
{
    global $mybb, $templates;

    $run_action  = htmlspecialchars_uni($action);
    $run_label   = htmlspecialchars_uni($label);
    $run_confirm = $confirm !== '' ? ' onsubmit="return confirm(' . blacklist_js_string($confirm) . ');"' : '';
    $html        = '';
    eval('$html = "' . $templates->get('blacklist_run_button') . '";');
    return $html;
}

// ------------------------------------------------------------------
// Activity simulation helpers
// ------------------------------------------------------------------

function blacklist_activity_condition(string $type, int $days, $lang): string
{
    switch ($type) {
        case 'scene_wait':
            return sprintf($lang->blacklist_condition_scene_wait, $days);
        case 'login':
            return sprintf($lang->blacklist_condition_login, $days);
        case 'registration':
            return sprintf($lang->blacklist_condition_registration, $days);
        case 'posting':
            return sprintf($lang->blacklist_condition_posting, $days);
        case 'group':
            return $lang->blacklist_condition_group;
        case 'no_subaccount':
            return sprintf($lang->blacklist_condition_no_subaccount, $days);
        case 'no_active_scene':
            return $lang->blacklist_condition_no_active_scene;
        case 'frozen':
            return sprintf($lang->blacklist_condition_frozen, $days);
        default:
            return '';
    }
}

function blacklist_activity_date_label(string $type, $lang): string
{
    switch ($type) {
        case 'login':
            return $lang->blacklist_lbl_last_login;
        case 'registration':
        case 'no_subaccount':
            return $lang->blacklist_lbl_registered;
        case 'posting':
            return $lang->blacklist_lbl_last_post;
        case 'no_active_scene':
            return $lang->blacklist_lbl_scene_status;
        case 'frozen':
            return $lang->blacklist_lbl_frozen_until;
        default:
            return $lang->blacklist_lbl_last_activity;
    }
}
