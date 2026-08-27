<?php
/**
 * Blacklist – Frontend Page
 *
 * blacklist.php              – current open blacklist (grouped by category)
 * blacklist.php?action=activity  – live simulation (no DB write)
 * blacklist.php?action=resolve&entry_id=X&my_post_key=... – self-removal
 * blacklist.php?action=add&uid=X (team only)
 * blacklist.php?action=delete&entry_id=X (team only)
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

// ------------------------------------------------------------------
// Self-removal: resolve own entry
// ------------------------------------------------------------------

if ($action === 'resolve') {
    verify_post_check($mybb->get_input('my_post_key'));

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

if ($action === 'add' && blacklist_is_team()) {
    verify_post_check($mybb->get_input('my_post_key'));

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

if ($action === 'delete' && blacklist_is_team()) {
    verify_post_check($mybb->get_input('my_post_key'));

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

if ($action === 'team_resolve' && blacklist_is_team()) {
    verify_post_check($mybb->get_input('my_post_key'));

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

if ($action === 'close_run' && blacklist_is_team()) {
    verify_post_check($mybb->get_input('my_post_key'));

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

if ($action === 'new_run' && blacklist_is_team()) {
    verify_post_check($mybb->get_input('my_post_key'));

    if (!$engine->getActiveRun()) {
        $engine->createRun();
        $engine->run();
    }

    redirect('blacklist.php', $lang->blacklist_run_started);
}

// ------------------------------------------------------------------
// Debug: scene_wait trace  (action=activity&debug=1, team only)
// ------------------------------------------------------------------

if ($action === 'activity' && $mybb->get_input('debug') && blacklist_is_team()) {
    $trace = $engine->debugSceneWait();

    echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
       . '<title>Blacklist Debug: scene_wait</title>'
       . '<style>body{font-family:sans-serif;font-size:12px;padding:20px}'
       . 'table{border-collapse:collapse;width:100%}'
       . 'th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}'
       . 'th{background:#ddd}.skip{color:#c00}.hit{color:#a60;font-weight:bold}'
       . 'tr.solo{background:#fee} tr.not-next{background:#ffd} tr.hit{background:#ffc} tr.ok{background:#efe}'
       . '</style></head><body>';
    echo '<h2>Blacklist Debug – scene_wait</h2>';
    echo '<p><a href="blacklist.php?action=activity">&larr; Zurück</a></p>';
    echo '<p>Legende: <span style="background:#fee;padding:2px 6px">Solo-Szene</span> '
       . '<span style="background:#ffd;padding:2px 6px">Nicht dran</span> '
       . '<span style="background:#ffc;padding:2px 6px">Bedingung erfüllt</span> '
       . '<span style="background:#efe;padding:2px 6px">OK</span></p>';

    if (empty($trace)) {
        echo '<p>Keine scene_wait-Kategorien aktiv oder Logbook nicht installiert.</p>';
    } else {
        echo '<table><tr><th>Kategorie</th><th>Nutzer (uid)</th><th>scene_id</th>'
           . '<th>tid</th><th>free_order</th><th>Teilnehmer</th><th>next_poster_uid</th>'
           . '<th>last_post</th><th>Tage</th><th>Schwelle</th><th>Bedingung</th><th>Skip-Grund</th></tr>';

        foreach ($trace as $t) {
            if ($t['skip_reason'] && strpos($t['skip_reason'], 'solo') !== false) {
                $tr_class = 'solo';
            } elseif ($t['skip_reason'] && strpos($t['skip_reason'], 'not next') !== false) {
                $tr_class = 'not-next';
            } elseif ($t['condition_met']) {
                $tr_class = 'hit';
            } else {
                $tr_class = 'ok';
            }

            $skip      = $t['skip_reason'] ? '<span class="skip">' . htmlspecialchars($t['skip_reason']) . '</span>' : '–';
            $condition = $t['condition_met'] ? '<span class="hit">JA</span>' : 'nein';
            $last_post = $t['last_post'] ? date('d.m.Y H:i', $t['last_post']) : '–';
            $note      = isset($t['skip_reason_note']) ? '<br><i>' . htmlspecialchars($t['skip_reason_note']) . '</i>' : '';

            echo '<tr class="' . $tr_class . '">'
               . '<td>' . htmlspecialchars($t['category_name']) . '</td>'
               . '<td>' . htmlspecialchars($t['username']) . ' (' . $t['uid'] . ')</td>'
               . '<td>' . $t['scene_id'] . '</td>'
               . '<td>' . $t['tid'] . '</td>'
               . '<td>' . $t['free_order'] . '</td>'
               . '<td>' . ($t['participant_count'] !== null ? $t['participant_count'] : '–') . '</td>'
               . '<td>' . ($t['next_poster_uid'] !== null ? $t['next_poster_uid'] : '–') . '</td>'
               . '<td>' . $last_post . $note . '</td>'
               . '<td>' . ($t['days_waiting'] !== null ? $t['days_waiting'] : '–') . '</td>'
               . '<td>' . $t['threshold_days'] . '</td>'
               . '<td>' . $condition . '</td>'
               . '<td>' . $skip . '</td>'
               . '</tr>';
        }

        echo '</table>';
    }

    echo '</body></html>';
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
            $users_html = '<tr class="trow1"><td colspan="2" style="text-align:center;font-style:italic;opacity:0.75;padding:0.8em">'
                . $lang->blacklist_no_affected . '</td></tr>';
        } else {
            foreach ($affected as $item) {
                $user        = $item['user'];
                $username    = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
                $profile_url = $mybb->settings['bburl'] . '/member.php?action=profile&uid=' . (int) $user['uid'];
                $detail_html = '';

                if (!empty($item['scenes'])) {
                    $detail_html = '<ul class="blacklist-scene-list">';
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
                        $detail_html .= '<li class="blacklist-scene-list-item">'
                            . $sc_link
                            . ' <span class="blacklist-scene-meta">'
                            . $sc_date
                            . ' (' . $sc_days . ' ' . $lang->blacklist_lbl_days . ')'
                            . '</span></li>';
                    }
                    $detail_html .= '</ul>';
                } else {
                    $last_ts  = $item['last_timestamp'];
                    $date_str = $last_ts ? my_date('d.m.Y', (int) $last_ts) : '–';
                    $days_str = $item['days_inactive'] !== null
                        ? ' (' . (int) $item['days_inactive'] . ' ' . $lang->blacklist_lbl_days . ')'
                        : '';
                    $detail_html = '<span class="blacklist-detail-date">' . $date_str . $days_str . '</span>';
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
$activity_url = $mybb->settings['bburl'] . '/blacklist.php?action=activity';
$post_key    = $mybb->post_code;

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
        $deadline_info_html = '<p class="blacklist-deadline-info smalltext' . $overdue_cls . '">'
            . $lang->blacklist_deadline . ': <strong>' . $dl_str . '</strong></p>';
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
    $svg_attrs = 'fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="blacklist-icon"';
    $svg_check = '<svg viewBox="0 0 16 16" ' . $svg_attrs . ' aria-hidden="true"><polyline points="3,8 6,12 13,4"/></svg>';
    $svg_trash = '<svg viewBox="0 0 16 16" ' . $svg_attrs . ' aria-hidden="true"><polyline points="3,5 13,5"/><path d="M5 5v8h6V5M7 5V3h2v2"/></svg>';

    foreach ($cat_index as $cid => $category) {
        if (empty($grouped[$cid])) {
            continue;
        }

        $entries_html = '';
        $cat_name     = htmlspecialchars_uni($category['name']);

        $has_scene_col = $category['type'] === 'scene_wait';
        if ($has_scene_col) {
            $cat_col2_header = '<td class="tcat" width="63%"><strong>' . $lang->blacklist_col_scene . '</strong></td>';
            $user_col_width  = 'width="22%"';
        } else {
            $cat_col2_header = '';
            $user_col_width  = 'width="85%"';
        }

        foreach ($grouped[$cid] as $entry) {
            $user_html      = blacklist_entry_user_html($entry);
            $thread_link    = '';
            $entry_col2     = '';
            $overdue_class  = '';
            $resolved_class = '';
            $resolve_btn    = '';
            $team_btns      = '';
            $is_resolved    = $entry['status'] === 'resolved';

            if ($is_resolved) {
                $resolved_class = ' blacklist-resolved';
            } else {
                $overdue_class = $entry['deadline'] < TIME_NOW ? ' blacklist-overdue' : '';
            }

            if ($has_scene_col && (int) $entry['thread_id'] > 0 && !empty($entry['thread_subject'])) {
                $t_url       = $mybb->settings['bburl'] . '/showthread.php?tid=' . (int) $entry['thread_id'];
                $t_subj      = htmlspecialchars_uni($entry['thread_subject']);
                $thread_link = '<a href="' . $t_url . '">' . $t_subj . '</a>';
            }

            if ($has_scene_col) {
                $entry_col2 = '<td class="smalltext">' . $thread_link . '</td>';
            }

            if ($is_resolved) {
                $action_html = '<span class="blacklist-status-resolved smalltext">' . $svg_check . ' ' . $lang->blacklist_status_resolved . '</span>';
                if ($is_team) {
                    $entry_id    = (int) $entry['id'];
                    $action_html .= ' <form method="post" action="blacklist.php" style="display:inline;white-space:nowrap">'
                        . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
                        . '<input type="hidden" name="entry_id" value="' . $entry_id . '">'
                        . '<button name="action" value="delete" class="blacklist-icon-btn" title="' . $lang->blacklist_btn_delete . '">' . $svg_trash . ' ' . $lang->blacklist_btn_delete . '</button>'
                        . '</form>';
                }
            } else {
                if ((int) $entry['uid'] === (int) $mybb->user['uid']) {
                    $response = $entry['response_type'] ?? $category['response_type'];
                    if ($response === 'button' || $response === 'posting') {
                        $entry_id = (int) $entry['id'];
                        eval('$resolve_btn = "' . $templates->get('blacklist_entry_button') . '";');
                    }
                }

                if ($is_team) {
                    $entry_id  = (int) $entry['id'];
                    $team_btns = '<form method="post" action="blacklist.php" style="display:inline;white-space:nowrap">'
                        . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
                        . '<input type="hidden" name="entry_id" value="' . $entry_id . '">'
                        . '<button name="action" value="team_resolve" class="blacklist-icon-btn" title="' . $lang->blacklist_btn_resolve . '">' . $svg_check . ' ' . $lang->blacklist_btn_resolve . '</button>'
                        . '<button name="action" value="delete" class="blacklist-icon-btn" title="' . $lang->blacklist_btn_delete . '">' . $svg_trash . ' ' . $lang->blacklist_btn_delete . '</button>'
                        . '</form>';
                }

                $action_html = $resolve_btn . $team_btns;
                if ($action_html === '') {
                    $action_html = '<span class="smalltext">' . $lang->blacklist_action_none . '</span>';
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
        $add_form_html = '<div class="tborder blacklist-add-bar">'
            . '<form method="post" action="blacklist.php" class="blacklist-add-form">'
            . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
            . '<input type="hidden" name="action" value="add">'
            . '<label class="smalltext">' . $lang->blacklist_lbl_uid . '<input type="number" name="uid" min="1" required style="width:70px"></label>'
            . '<label class="smalltext">' . $lang->blacklist_lbl_category . '<select name="category_id">' . $all_cats_options . '</select></label>'
            . '<label class="smalltext">' . $lang->blacklist_lbl_thread . '<input type="number" name="thread_id" min="0" value="0" style="width:70px"></label>'
            . '<input type="submit" class="button" value="+ ' . $lang->blacklist_btn_add . '">'
            . '</form>'
            . '</div>';
    }

    // Team: close run button
    if ($is_team) {
        $run_actions_html = '<div class="blacklist-run-actions">'
            . '<form method="post" action="blacklist.php">'
            . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
            . '<button name="action" value="close_run" class="button" onclick="return confirm(\'' . $lang->blacklist_run_close_confirm . '\')">'
            . $lang->blacklist_btn_close_run
            . '</button></form></div>';
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

    $run_notice_html = '<table border="0" cellspacing="1" cellpadding="8" class="tborder blacklist-run-notice" style="margin-bottom:12px;width:100%">'
        . '<tr><td class="tcat"><strong>' . $lang->blacklist_run_closed_title . '</strong></td></tr>'
        . '<tr><td class="trow1">' . sprintf($lang->blacklist_run_closed_notice, '<strong>' . $closed_date . '</strong>', $closer_name) . '</td></tr>'
        . '</table>';

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

    $no_entries_row = '<tr class="trow1"><td colspan="2" class="smalltext" style="text-align:center;font-style:italic;padding:0.8em">'
        . $lang->blacklist_run_no_entries . '</td></tr>';

    // Section 1: Zurückgemeldet
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

    // Section 2: Nicht zurückgemeldet
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
        $run_actions_html = '<div class="blacklist-run-actions">'
            . '<form method="post" action="blacklist.php">'
            . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
            . '<button name="action" value="new_run" class="button">'
            . $lang->blacklist_btn_new_run
            . '</button></form></div>';
    }

} else {

    // ── No run yet ────────────────────────────────────────────────
    eval('$no_entries_html = "' . $templates->get('blacklist_no_entries') . '";');

    if ($is_team) {
        $run_actions_html = '<div class="blacklist-run-actions">'
            . '<form method="post" action="blacklist.php">'
            . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
            . '<button name="action" value="new_run" class="button">'
            . $lang->blacklist_btn_new_run
            . '</button></form></div>';
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
    global $mybb, $lang;

    $user_deleted = !isset($entry['username']) || $entry['username'] === null || $entry['username'] === '';

    if ($user_deleted) {
        $stored = isset($entry['deleted_username']) ? (string) $entry['deleted_username'] : '';
        if ($stored === '') {
            $stored = '#' . (int) $entry['uid'];
        }
        return '<span class="blacklist-deleted-user">' . htmlspecialchars_uni($stored) . '</span>'
            . ' <span class="blacklist-deleted-note smalltext">' . $lang->blacklist_user_deleted . '</span>';
    }

    $username    = format_name(htmlspecialchars_uni($entry['username']), $entry['usergroup'], $entry['displaygroup']);
    $profile_url = htmlspecialchars_uni($mybb->settings['bburl'] . '/member.php?action=profile&uid=' . (int) $entry['uid']);

    return '<a href="' . $profile_url . '">' . $username . '</a>';
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
