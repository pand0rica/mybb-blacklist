<?php
/**
 * Blacklist – ACP Module
 *
 * Handles: category CRUD, manual entry management.
 * Path: ACP → Tools → Blacklist
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// ------------------------------------------------------------------
// Language + permission check
// ------------------------------------------------------------------

$lang->load('blacklist');

if (!$mybb->usergroup['cancp']) {
    admin_redirect('index.php?module=home-index');
}

// ------------------------------------------------------------------
// Sub-actions
// ------------------------------------------------------------------

$sub_action = $mybb->get_input('action');

switch ($sub_action) {
    case 'categories':
    case '':
        blacklist_acp_categories();
        break;

    case 'add_category':
        blacklist_acp_add_category();
        break;

    case 'edit_category':
        blacklist_acp_edit_category();
        break;

    case 'delete_category':
        blacklist_acp_delete_category();
        break;

    case 'toggle_category':
        blacklist_acp_toggle_category();
        break;

    case 'entries':
        blacklist_acp_entries();
        break;

    case 'delete_entry':
        blacklist_acp_delete_entry();
        break;

    case 'resolve_entry':
        blacklist_acp_resolve_entry();
        break;

    case 'run_task':
        blacklist_acp_run_task();
        break;
}

// ------------------------------------------------------------------
// Shared nav tabs helper
// ------------------------------------------------------------------

function blacklist_acp_nav_tabs(string $active): void
{
    global $lang, $page;

    $tabs = [
        'categories'   => ['title' => $lang->blacklist_acp_categories,   'link' => 'index.php?module=tools-blacklist',                     'description' => $lang->blacklist_tab_desc_categories],
        'add_category' => ['title' => $lang->blacklist_acp_add_category, 'link' => 'index.php?module=tools-blacklist&action=add_category', 'description' => $lang->blacklist_tab_desc_add_category],
        'entries'      => ['title' => $lang->blacklist_acp_entries,       'link' => 'index.php?module=tools-blacklist&action=entries',       'description' => $lang->blacklist_tab_desc_entries],
        'run_task'     => ['title' => $lang->blacklist_acp_run_task,      'link' => 'index.php?module=tools-blacklist&action=run_task',      'description' => $lang->blacklist_tab_desc_run_task],
    ];

    $page->output_nav_tabs($tabs, $active);
}

// ------------------------------------------------------------------
// CSRF key check for GET action links
// ------------------------------------------------------------------

/**
 * Validates my_post_key for every state-changing ACP action.
 *
 * In the ACP, verify_post_check() only *returns* false on mismatch instead of
 * aborting (it never calls error() while IN_ADMINCP is defined). A bare
 * `verify_post_check(...)` call without checking the return value therefore
 * protects nothing. Every write handler – POST forms as well as the GET links
 * of the popup menu – calls this helper, which aborts with a flash message.
 */
function blacklist_acp_verify_key(): void
{
    global $mybb, $lang;

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message($lang->blacklist_invalid_key, 'error');
        admin_redirect('index.php?module=tools-blacklist');
    }
}

// ------------------------------------------------------------------
// Category list
// ------------------------------------------------------------------

function blacklist_acp_categories(): void
{
    global $db, $mybb, $lang, $page;

    $page->add_breadcrumb_item($lang->blacklist_acp_title, 'index.php?module=tools-blacklist');
    $page->output_header($lang->blacklist_acp_title);
    blacklist_acp_nav_tabs('categories');

    $table = new Table();
    $table->construct_header($lang->blacklist_col_id,    ['width' => '5%']);
    $table->construct_header($lang->blacklist_col_sort,  ['width' => '8%', 'class' => 'align_center']);
    $table->construct_header($lang->blacklist_col_name,  ['width' => '22%']);
    $table->construct_header($lang->blacklist_col_type,  ['width' => '15%']);
    $table->construct_header($lang->blacklist_col_days,  ['width' => '10%']);
    $table->construct_header($lang->blacklist_col_resp,  ['width' => '15%']);
    $table->construct_header($lang->blacklist_col_active,['width' => '10%']);
    $table->construct_header($lang->blacklist_col_actions,['width' => '20%', 'class' => 'align_center']);

    $query = $db->simple_select('blacklist_categories', '*', '', ['order_by' => 'sort_order, id']);

    while ($cat = $db->fetch_array($query)) {
        $active_label = $cat['active']
            ? '<span style="color:green">' . $lang->blacklist_active . '</span>'
            : '<span style="color:#c00">' . $lang->blacklist_inactive . '</span>';

        $toggle_label = $cat['active'] ? $lang->blacklist_deactivate : $lang->blacklist_activate;
        $cat_id       = (int) $cat['id'];
        $post_key     = $mybb->post_code;

        // Native ACP controls dropdown (like forum management "Options").
        // Toggle + delete are GET links carrying my_post_key; their handlers
        // validate the key (blacklist_acp_verify_key), so they stay CSRF-safe
        // even though they are GET requests.
        $popup = new PopupMenu('blacklist_cat_' . $cat_id, $lang->blacklist_options);
        $popup->add_item(
            $lang->blacklist_edit,
            'index.php?module=tools-blacklist&amp;action=edit_category&amp;id=' . $cat_id
        );
        $popup->add_item(
            $toggle_label,
            'index.php?module=tools-blacklist&amp;action=toggle_category&amp;id=' . $cat_id . '&amp;my_post_key=' . $post_key
        );
        $popup->add_item(
            $lang->blacklist_delete,
            'index.php?module=tools-blacklist&amp;action=delete_category&amp;id=' . $cat_id . '&amp;my_post_key=' . $post_key,
            'return AdminCP.deleteConfirmation(this, ' . blacklist_js_string($lang->blacklist_confirm_delete) . ')'
        );

        $table->construct_cell((int) $cat['id']);
        $table->construct_cell((int) $cat['sort_order'], ['class' => 'align_center']);
        $table->construct_cell(htmlspecialchars_uni($cat['name']));
        $table->construct_cell(htmlspecialchars_uni($cat['type']));
        $table->construct_cell((int) $cat['threshold_days']);
        $table->construct_cell(htmlspecialchars_uni($cat['response_type']));
        $table->construct_cell($active_label);
        $table->construct_cell($popup->fetch(), ['class' => 'align_center']);
        $table->construct_row();
    }

    if ($table->num_rows() === 0) {
        $table->construct_cell($lang->blacklist_no_categories, ['colspan' => 8]);
        $table->construct_row();
    }

    $table->output($lang->blacklist_acp_categories);
    $page->output_footer();
}

// ------------------------------------------------------------------
// Add category
// ------------------------------------------------------------------

function blacklist_acp_add_category(): void
{
    global $db, $mybb, $lang, $page;

    if ($mybb->request_method === 'post') {
        blacklist_acp_verify_key();

        $input  = blacklist_acp_collect_category_input();
        $errors = blacklist_acp_validate_category($input);

        if (!empty($errors)) {
            blacklist_acp_category_form(null, $input, $errors);
            return;
        }

        $db->insert_query('blacklist_categories', blacklist_acp_build_category_data($input));
        flash_message($lang->blacklist_category_saved, 'success');
        admin_redirect('index.php?module=tools-blacklist');
    }

    blacklist_acp_category_form(null, [], []);
}

// ------------------------------------------------------------------
// Edit category
// ------------------------------------------------------------------

function blacklist_acp_edit_category(): void
{
    global $db, $mybb, $lang, $page;

    $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
    $query = $db->simple_select('blacklist_categories', '*', 'id = ' . $id);
    $cat   = $db->fetch_array($query);

    if (!$cat) {
        flash_message($lang->blacklist_not_found, 'error');
        admin_redirect('index.php?module=tools-blacklist');
    }

    if ($mybb->request_method === 'post') {
        blacklist_acp_verify_key();

        $input  = blacklist_acp_collect_category_input();
        $errors = blacklist_acp_validate_category($input);

        if (!empty($errors)) {
            blacklist_acp_category_form($cat, $input, $errors);
            return;
        }

        $db->update_query('blacklist_categories', blacklist_acp_build_category_data($input), 'id = ' . $id);
        flash_message($lang->blacklist_category_saved, 'success');
        admin_redirect('index.php?module=tools-blacklist');
    }

    blacklist_acp_category_form($cat, $cat, []);
}

// ------------------------------------------------------------------
// Delete category
// ------------------------------------------------------------------

function blacklist_acp_delete_category(): void
{
    global $db, $mybb, $lang;

    blacklist_acp_verify_key();

    $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);

    $db->delete_query('blacklist_categories', 'id = ' . $id);
    $db->delete_query('blacklist_entries', 'category_id = ' . $id);

    flash_message($lang->blacklist_category_deleted, 'success');
    admin_redirect('index.php?module=tools-blacklist');
}

// ------------------------------------------------------------------
// Toggle active
// ------------------------------------------------------------------

function blacklist_acp_toggle_category(): void
{
    global $db, $mybb, $lang;

    blacklist_acp_verify_key();

    $id    = (int) $mybb->get_input('id', MyBB::INPUT_INT);
    $query = $db->simple_select('blacklist_categories', 'active', 'id = ' . $id);
    $cat   = $db->fetch_array($query);

    if (!$cat) {
        flash_message($lang->blacklist_not_found, 'error');
        admin_redirect('index.php?module=tools-blacklist');
    }

    $new_active = $cat['active'] ? 0 : 1;
    $db->update_query('blacklist_categories', ['active' => $new_active], 'id = ' . $id);

    flash_message($lang->blacklist_category_updated, 'success');
    admin_redirect('index.php?module=tools-blacklist');
}

// ------------------------------------------------------------------
// Category form (add + edit)
// ------------------------------------------------------------------

function blacklist_acp_category_form(?array $cat, array $data, array $errors): void
{
    global $db, $mybb, $lang, $page;

    $is_edit = $cat !== null;
    $title   = $is_edit ? $lang->blacklist_acp_edit_category : $lang->blacklist_acp_add_category;
    $action  = $is_edit
        ? 'index.php?module=tools-blacklist&action=edit_category&id=' . (int) $cat['id']
        : 'index.php?module=tools-blacklist&action=add_category';

    $page->add_breadcrumb_item($lang->blacklist_acp_title, 'index.php?module=tools-blacklist');
    $page->add_breadcrumb_item($title);
    $page->output_header($title);
    blacklist_acp_nav_tabs($is_edit ? 'categories' : 'add_category');

    if (!empty($errors)) {
        $page->output_inline_error($errors);
    }

    $form = new Form($action, 'post');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($title);

    // Name
    $form_container->output_row(
        $lang->blacklist_lbl_name . ' *',
        $lang->blacklist_desc_name,
        $form->generate_text_box('name', htmlspecialchars_uni($data['name'] ?? ''), ['style' => 'width:300px'])
    );

    // Type
    $type_options = [
        'scene_wait'    => $lang->blacklist_type_scene_wait,
        'registration'  => $lang->blacklist_type_registration,
        'login'         => $lang->blacklist_type_login,
        'posting'       => $lang->blacklist_type_posting,
        'group'           => $lang->blacklist_type_group,
        'no_subaccount'   => $lang->blacklist_type_no_subaccount,
        'no_active_scene' => $lang->blacklist_type_no_active_scene,
        'frozen'          => $lang->blacklist_type_frozen,
    ];
    $form_container->output_row(
        $lang->blacklist_lbl_type . ' *',
        $lang->blacklist_desc_type,
        $form->generate_select_box('type', $type_options, $data['type'] ?? 'scene_wait', ['id' => 'blacklist_type'])
    );

    // Profile field (frozen type only)
    $profile_fields_query  = $db->simple_select('profilefields', 'fid, name', '', ['order_by' => 'disporder']);
    $profile_field_options = [0 => '—'];
    while ($pf = $db->fetch_array($profile_fields_query)) {
        $profile_field_options[(int) $pf['fid']] = htmlspecialchars_uni($pf['name']) . ' (fid' . $pf['fid'] . ')';
    }
    $current_type = $data['type'] ?? 'scene_wait';
    // Invisible anchor so JS can find the next sibling row rendered by output_row()
    echo '<tr id="blacklist_frozen_anchor" style="display:none"><td colspan="2"></td></tr>';
    $form_container->output_row(
        $lang->blacklist_lbl_frozen_profile_field . ' *',
        $lang->blacklist_desc_frozen_profile_field,
        $form->generate_select_box('frozen_profile_field', $profile_field_options, (int) ($data['frozen_profile_field'] ?? 0))
    );
    echo '<script>
(function() {
    var anchor    = document.getElementById("blacklist_frozen_anchor");
    var frozenRow = anchor ? anchor.nextElementSibling : null;
    var typeSelect = document.getElementById("blacklist_type");
    function toggleFrozenRow() {
        if (!frozenRow) return;
        frozenRow.style.display = (typeSelect.value === "frozen") ? "" : "none";
    }
    toggleFrozenRow();
    typeSelect.addEventListener("change", toggleFrozenRow);
})();
</script>';

    // Threshold
    $form_container->output_row(
        $lang->blacklist_lbl_threshold . ' *',
        $lang->blacklist_desc_threshold,
        $form->generate_text_box('threshold_days', (int) ($data['threshold_days'] ?? 14), ['style' => 'width:80px'])
    );

    // Response type
    $resp_options = [
        'none'    => $lang->blacklist_resp_none,
        'button'  => $lang->blacklist_resp_button,
        'posting' => $lang->blacklist_resp_posting,
    ];
    $form_container->output_row(
        $lang->blacklist_lbl_response . ' *',
        $lang->blacklist_desc_response,
        $form->generate_select_box('response_type', $resp_options, $data['response_type'] ?? 'none')
    );

    // Groups
    $all_groups_query = $db->simple_select('usergroups', 'gid, title', '', ['order_by' => 'title']);
    $groups = [];
    while ($g = $db->fetch_array($all_groups_query)) {
        $groups[(int) $g['gid']] = htmlspecialchars_uni($g['title']);
    }

    $selected_groups = [];
    if (!empty($data['group_ids'])) {
        // group_ids arrives as an array (POST re-render) or a comma string (DB row)
        $group_ids_source = is_array($data['group_ids'])
            ? $data['group_ids']
            : explode(',', (string) $data['group_ids']);
        $selected_groups = array_map('intval', $group_ids_source);
    }

    $form_container->output_row(
        $lang->blacklist_lbl_groups,
        $lang->blacklist_desc_groups,
        $form->generate_select_box('group_ids[]', $groups, $selected_groups, ['multiple' => true, 'size' => 8])
    );

    // Include additional groups
    $form_container->output_row(
        $lang->blacklist_lbl_include_additional,
        $lang->blacklist_desc_include_additional,
        $form->generate_yes_no_radio('include_additional_groups', (int) ($data['include_additional_groups'] ?? 0))
    );

    // Away exempt
    $form_container->output_row(
        $lang->blacklist_lbl_away_exempt,
        $lang->blacklist_desc_away_exempt,
        $form->generate_yes_no_radio('away_exempt', (int) ($data['away_exempt'] ?? 0))
    );

    // Active
    $form_container->output_row(
        $lang->blacklist_lbl_active,
        '',
        $form->generate_yes_no_radio('active', (int) ($data['active'] ?? 1))
    );

    // Sort order
    $form_container->output_row(
        $lang->blacklist_lbl_sort_order,
        $lang->blacklist_desc_sort_order,
        $form->generate_text_box('sort_order', (int) ($data['sort_order'] ?? 0), ['style' => 'width:80px'])
    );

    $form_container->end();

    $buttons = [$form->generate_submit_button($lang->blacklist_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

// ------------------------------------------------------------------
// Collect category input (typed) from the submitted form
// ------------------------------------------------------------------

/**
 * Reads all category form fields via $mybb->get_input() with explicit types
 * instead of touching $mybb->input directly. Returns an array with the same
 * keys the validate/build/form helpers expect.
 */
function blacklist_acp_collect_category_input(): array
{
    global $mybb;

    return [
        'name'                      => $mybb->get_input('name', MyBB::INPUT_STRING),
        'type'                      => $mybb->get_input('type', MyBB::INPUT_STRING),
        'threshold_days'            => $mybb->get_input('threshold_days', MyBB::INPUT_INT),
        'response_type'             => $mybb->get_input('response_type', MyBB::INPUT_STRING),
        'group_ids'                 => $mybb->get_input('group_ids', MyBB::INPUT_ARRAY),
        'include_additional_groups' => $mybb->get_input('include_additional_groups', MyBB::INPUT_INT),
        'away_exempt'               => $mybb->get_input('away_exempt', MyBB::INPUT_INT),
        'active'                    => $mybb->get_input('active', MyBB::INPUT_INT),
        'sort_order'                => $mybb->get_input('sort_order', MyBB::INPUT_INT),
        'frozen_profile_field'      => $mybb->get_input('frozen_profile_field', MyBB::INPUT_INT),
    ];
}

// ------------------------------------------------------------------
// Validate category input
// ------------------------------------------------------------------

function blacklist_acp_validate_category(array $data): array
{
    global $lang;
    $errors = [];

    if (empty(trim($data['name'] ?? ''))) {
        $errors[] = $lang->blacklist_err_name_required;
    }

    $valid_types = ['scene_wait', 'registration', 'login', 'posting', 'group', 'no_subaccount', 'no_active_scene', 'frozen'];
    if (!in_array($data['type'] ?? '', $valid_types, true)) {
        $errors[] = $lang->blacklist_err_invalid_type;
    }

    if (($data['type'] ?? '') === 'frozen' && (int) ($data['frozen_profile_field'] ?? 0) < 1) {
        $errors[] = $lang->blacklist_err_frozen_field_required;
    }

    if (!is_numeric($data['threshold_days'] ?? '') || (int) $data['threshold_days'] < 1) {
        $errors[] = $lang->blacklist_err_threshold;
    }

    return $errors;
}

// ------------------------------------------------------------------
// Build category DB array from POST input
// ------------------------------------------------------------------

function blacklist_acp_build_category_data(array $data): array
{
    global $db;

    $group_ids_raw = $data['group_ids'] ?? [];
    if (is_array($group_ids_raw)) {
        $group_ids = implode(',', array_map('intval', $group_ids_raw));
    } else {
        $group_ids = implode(',', array_map('intval', explode(',', (string) $group_ids_raw)));
    }

    return [
        'name'                       => $db->escape_string(trim($data['name'] ?? '')),
        'active'                     => (int) ($data['active'] ?? 1),
        'type'                       => $db->escape_string($data['type'] ?? 'scene_wait'),
        'threshold_days'             => max(1, (int) ($data['threshold_days'] ?? 14)),
        'group_ids'                  => $db->escape_string($group_ids),
        'include_additional_groups'  => (int) ($data['include_additional_groups'] ?? 0),
        'response_type'              => $db->escape_string($data['response_type'] ?? 'none'),
        'sort_order'                 => (int) ($data['sort_order'] ?? 0),
        'away_exempt'                => (int) ($data['away_exempt'] ?? 0),
        'frozen_profile_field'       => (int) ($data['frozen_profile_field'] ?? 0),
    ];
}

// ------------------------------------------------------------------
// Entries list
// ------------------------------------------------------------------

function blacklist_acp_entries(): void
{
    global $db, $mybb, $lang, $page;

    $page->add_breadcrumb_item($lang->blacklist_acp_title, 'index.php?module=tools-blacklist');
    $page->add_breadcrumb_item($lang->blacklist_acp_entries);
    $page->output_header($lang->blacklist_acp_entries);
    blacklist_acp_nav_tabs('entries');

    $table = new Table();
    $table->construct_header($lang->blacklist_col_id,       ['width' => '5%']);
    $table->construct_header($lang->blacklist_col_user,     ['width' => '20%']);
    $table->construct_header($lang->blacklist_col_category, ['width' => '20%']);
    $table->construct_header($lang->blacklist_col_scene,    ['width' => '20%']);
    $table->construct_header($lang->blacklist_col_added,    ['width' => '10%']);
    $table->construct_header($lang->blacklist_col_deadline, ['width' => '10%']);
    $table->construct_header($lang->blacklist_col_status,   ['width' => '5%']);
    $table->construct_header($lang->blacklist_col_actions,  ['width' => '10%', 'class' => 'align_center']);

    $query = $db->query("
        SELECT e.*, u.username, c.name AS cat_name, t.subject AS thread_subject
        FROM " . TABLE_PREFIX . "blacklist_entries e
        LEFT  JOIN " . TABLE_PREFIX . "users u ON u.uid = e.uid
        INNER JOIN " . TABLE_PREFIX . "blacklist_categories c ON c.id = e.category_id
        LEFT  JOIN " . TABLE_PREFIX . "threads t ON t.tid = e.thread_id
        WHERE e.status = 'open'
        ORDER BY c.id ASC, e.timestamp_added ASC
    ");

    while ($entry = $db->fetch_array($query)) {
        $entry_id    = (int) $entry['id'];
        $profile_url = 'index.php?module=user-users&action=edit&uid=' . (int) $entry['uid'];

        // Deleted account: no users row → fall back to stored name, no edit link
        $user_deleted = !isset($entry['username']) || $entry['username'] === null || $entry['username'] === '';
        if ($user_deleted) {
            $stored = isset($entry['deleted_username']) && $entry['deleted_username'] !== ''
                ? $entry['deleted_username']
                : '#' . (int) $entry['uid'];
            $user_cell = '<del>' . htmlspecialchars_uni($stored) . '</del> '
                . '<em>' . $lang->blacklist_user_deleted . '</em>';
        } else {
            $user_cell = '<a href="' . $profile_url . '">' . htmlspecialchars_uni($entry['username']) . '</a>';
        }

        $thread_link = '';
        if ((int) $entry['thread_id'] > 0 && !empty($entry['thread_subject'])) {
            $thread_link = '<a href="' . $mybb->settings['bburl']
                . '/showthread.php?tid=' . (int) $entry['thread_id'] . '" target="_blank">'
                . htmlspecialchars_uni($entry['thread_subject']) . '</a>';
        }

        $action_html =
            '<form method="post" action="index.php?module=tools-blacklist&action=resolve_entry" style="display:inline">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
            . '<input type="hidden" name="id" value="' . $entry_id . '">'
            . '<input type="submit" value="' . $lang->blacklist_resolve . '" class="button">'
            . '</form> '
            . '<form method="post" action="index.php?module=tools-blacklist&action=delete_entry" style="display:inline"'
            . ' onsubmit="return confirm(' . blacklist_js_string($lang->blacklist_confirm_delete) . ');">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
            . '<input type="hidden" name="id" value="' . $entry_id . '">'
            . '<input type="submit" value="' . $lang->blacklist_delete . '" class="button">'
            . '</form>';

        $table->construct_cell($entry_id);
        $table->construct_cell($user_cell);
        $table->construct_cell(htmlspecialchars_uni($entry['cat_name']));
        $table->construct_cell($thread_link ?: '–');
        $table->construct_cell(my_date('d.m.Y', (int) $entry['timestamp_added']));
        $table->construct_cell(my_date('d.m.Y', (int) $entry['deadline']));
        $table->construct_cell(htmlspecialchars_uni($entry['status']));
        $table->construct_cell($action_html, ['class' => 'align_center']);
        $table->construct_row();
    }

    if ($table->num_rows() === 0) {
        $table->construct_cell($lang->blacklist_no_entries_acp, ['colspan' => 8]);
        $table->construct_row();
    }

    $table->output($lang->blacklist_acp_entries);
    $page->output_footer();
}

// ------------------------------------------------------------------
// Delete / Resolve single entry (ACP)
// ------------------------------------------------------------------

function blacklist_acp_delete_entry(): void
{
    global $db, $mybb, $lang;

    if ($mybb->request_method !== 'post') {
        admin_redirect('index.php?module=tools-blacklist&action=entries');
    }
    blacklist_acp_verify_key();

    $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
    $db->delete_query('blacklist_entries', 'id = ' . $id);

    flash_message($lang->blacklist_entry_deleted, 'success');
    admin_redirect('index.php?module=tools-blacklist&action=entries');
}

function blacklist_acp_resolve_entry(): void
{
    global $db, $mybb, $lang;

    if ($mybb->request_method !== 'post') {
        admin_redirect('index.php?module=tools-blacklist&action=entries');
    }
    blacklist_acp_verify_key();

    $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
    $db->update_query('blacklist_entries', ['status' => 'resolved'], 'id = ' . $id);

    flash_message($lang->blacklist_resolved_success, 'success');
    admin_redirect('index.php?module=tools-blacklist&action=entries');
}

// ------------------------------------------------------------------
// Task status + debug page
// ------------------------------------------------------------------

function blacklist_acp_run_task(): void
{
    global $db, $mybb, $lang, $page;

    // POST: sync schedule
    if ($mybb->request_method === 'post' && $mybb->get_input('do') === 'sync') {
        blacklist_acp_verify_key();
        blacklist_sync_task_schedule();
        flash_message($lang->blacklist_task_synced, 'success');
        admin_redirect('index.php?module=tools-blacklist&action=run_task');
    }

    // POST: run task now
    if ($mybb->request_method === 'post' && $mybb->get_input('do') === 'run') {
        blacklist_acp_verify_key();

        require_once MYBB_ROOT . 'inc/plugins/blacklist/core/engine.php';
        $engine = new Blacklist_Engine();

        $error = null;
        $stats = ['created' => 0, 'skipped' => 0];
        try {
            $stats = $engine->run();
        } catch (\Throwable $e) {
            $error = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        }

        if ($error) {
            flash_message($lang->blacklist_task_error . ' ' . htmlspecialchars_uni($error), 'error');
        } else {
            flash_message(
                sprintf($lang->blacklist_task_ran, $stats['created'], $stats['skipped']),
                'success'
            );
        }
        admin_redirect('index.php?module=tools-blacklist&action=run_task');
    }

    // ── Render debug page ──────────────────────────────────────────────────────

    $page->add_breadcrumb_item($lang->blacklist_acp_title, 'index.php?module=tools-blacklist');
    $page->add_breadcrumb_item($lang->blacklist_acp_run_task);
    $page->output_header($lang->blacklist_acp_run_task);
    blacklist_acp_nav_tabs('run_task');

    // Read task row
    $task = $db->fetch_array(
        $db->simple_select('tasks', '*', "file = 'blacklist'")
    );

    // Read settings from DB (fresh)
    $get_setting = function(string $name) use ($db): string {
        $row = $db->fetch_array($db->simple_select('settings', 'value', "name = '" . $db->escape_string($name) . "'"));
        return $row ? (string) $row['value'] : '–';
    };

    $s_enabled = $get_setting('blacklist_task_enabled');
    $s_hour    = $get_setting('blacklist_task_hour');
    $s_minute  = $get_setting('blacklist_task_minute');
    $s_day     = $get_setting('blacklist_task_day');

    $now = TIME_NOW;

    // ── Section 1: Task row ───────────────────────────────────────────────────
    $table = new Table();
    $table->construct_header($lang->blacklist_debug_field,   ['width' => '35%']);
    $table->construct_header($lang->blacklist_debug_db,      ['width' => '35%']);
    $table->construct_header($lang->blacklist_debug_setting, ['width' => '30%']);

    if ($task) {
        $last_log = $db->fetch_array(
            $db->simple_select('tasklog', 'dateline', 'tid = ' . (int) $task['tid'], ['order_by' => 'lid DESC', 'limit' => 1])
        );
        $last_log_time = $last_log ? (int) $last_log['dateline'] : 0;

        $nextrun_ts   = (int) $task['nextrun'];
        $nextrun_diff = $nextrun_ts - $now;
        $nextrun_fmt  = my_date('d.m.Y H:i:s', $nextrun_ts);
        if ($nextrun_diff > 0) {
            $nextrun_fmt .= ' <span style="color:green">(' . sprintf($lang->blacklist_debug_nextrun_in, blacklist_fmt_seconds($nextrun_diff)) . ')</span>';
        } else {
            $nextrun_fmt .= ' <span style="color:#c00">(' . sprintf($lang->blacklist_debug_nextrun_overdue, blacklist_fmt_seconds(-$nextrun_diff)) . ')</span>';
        }

        $enabled_label = $task['enabled']
            ? '<span style="color:green"><strong>' . $lang->blacklist_debug_yes . '</strong></span>'
            : '<span style="color:#c00"><strong>' . $lang->blacklist_debug_no . '</strong></span>';
        $s_enabled_label = $s_enabled === '1'
            ? '<span style="color:green">' . $lang->blacklist_debug_yes . '</span>'
            : '<span style="color:#c00">' . $lang->blacklist_debug_no . '</span>';

        $match_enabled = ((int) $task['enabled'] === (int) $s_enabled);
        $match_hour    = ((int) $task['hour']    === (int) $s_hour);
        $match_minute  = ((int) $task['minute']  === (int) $s_minute);
        $match_day     = ($task['day'] === ($s_day !== '0' ? $s_day : '*'));

        $ok   = ' <span style="color:green">✓</span>';
        $fail = ' <span style="color:#c00">✗ ' . $lang->blacklist_debug_not_synced . '</span>';

        $rows = [
            [$lang->blacklist_debug_enabled,  $enabled_label,                  $s_enabled_label . ($match_enabled ? $ok : $fail)],
            [$lang->blacklist_debug_hour,      (int) $task['hour'],             $s_hour          . ($match_hour    ? $ok : $fail)],
            [$lang->blacklist_debug_minute,    (int) $task['minute'],           $s_minute        . ($match_minute  ? $ok : $fail)],
            [$lang->blacklist_debug_day,       htmlspecialchars_uni($task['day']), ($s_day === '0' ? $lang->blacklist_debug_daily : $s_day) . ($match_day ? $ok : $fail)],
            [$lang->blacklist_debug_nextrun,   $nextrun_fmt, ''],
            [$lang->blacklist_debug_lastrun,   !empty($last_log_time) ? my_date('d.m.Y H:i:s', $last_log_time) : '–', ''],
            [$lang->blacklist_debug_servertime, my_date('d.m.Y H:i:s', $now) . ' (Unix: ' . $now . ')', ''],
        ];

        $alt = 1;
        foreach ($rows as $row) {
            $table->construct_cell('<strong>' . $row[0] . '</strong>');
            $table->construct_cell($row[1]);
            $table->construct_cell($row[2]);
            $table->construct_row(['class' => 'trow' . $alt]);
            $alt = $alt === 1 ? 2 : 1;
        }
    } else {
        $table->construct_cell($lang->blacklist_debug_no_task, ['colspan' => 3]);
        $table->construct_row();
    }

    $table->output($lang->blacklist_debug_task_status);

    // ── Section 2: Action buttons ────────────────────────────────────────────
    $form_sync = new Form('index.php?module=tools-blacklist&action=run_task', 'post');
    echo $form_sync->generate_hidden_field('my_post_key', $mybb->post_code);
    echo $form_sync->generate_hidden_field('do', 'sync');
    $form_sync->output_submit_wrapper([$form_sync->generate_submit_button($lang->blacklist_btn_sync_task)]);
    $form_sync->end();

    $form_run = new Form('index.php?module=tools-blacklist&action=run_task', 'post');
    echo $form_run->generate_hidden_field('my_post_key', $mybb->post_code);
    echo $form_run->generate_hidden_field('do', 'run');
    $form_run->output_submit_wrapper([$form_run->generate_submit_button($lang->blacklist_btn_run_now)]);
    $form_run->end();

    // ── Section 3: Task log ───────────────────────────────────────────────────
    if ($task) {
        $log_table = new Table();
        $log_table->construct_header($lang->blacklist_debug_log_time,    ['width' => '25%']);
        $log_table->construct_header($lang->blacklist_debug_log_message, ['width' => '75%']);

        $logs = $db->simple_select(
            'tasklog', 'dateline, data',
            'tid = ' . (int) $task['tid'],
            ['order_by' => 'lid DESC', 'limit' => 10]
        );

        $log_count = 0;
        while ($log = $db->fetch_array($logs)) {
            $log_table->construct_cell(my_date('d.m.Y H:i:s', (int) $log['dateline']));
            $log_table->construct_cell(htmlspecialchars_uni($log['data']));
            $log_table->construct_row();
            $log_count++;
        }

        if ($log_count === 0) {
            $log_table->construct_cell($lang->blacklist_debug_no_logs, ['colspan' => 2]);
            $log_table->construct_row();
        }

        $log_table->output($lang->blacklist_debug_log_title);
    }

    $page->output_footer();
}

function blacklist_fmt_seconds(int $seconds): string
{
    global $lang;
    if ($seconds < 60)    { return $seconds             . ' ' . $lang->blacklist_debug_unit_sec; }
    if ($seconds < 3600)  { return round($seconds / 60) . ' ' . $lang->blacklist_debug_unit_min; }
    if ($seconds < 86400) { return round($seconds / 3600) . ' ' . $lang->blacklist_debug_unit_hrs; }
    return round($seconds / 86400) . ' ' . $lang->blacklist_debug_unit_days;
}
