<?php
// ACP: General
$l['blacklist_acp_title']         = 'Blacklist';
$l['blacklist_acp_categories']    = 'Categories';
$l['blacklist_acp_add_category']  = 'Add Category';
$l['blacklist_acp_edit_category'] = 'Edit Category';
$l['blacklist_acp_entries']       = 'Open Entries';
$l['blacklist_acp_run_task']      = 'Task Status';

// ACP: Tab descriptions
$l['blacklist_tab_desc_categories']   = 'Manage all blacklist categories – configure type, threshold and response behaviour.';
$l['blacklist_tab_desc_add_category'] = 'Create and configure a new blacklist category.';
$l['blacklist_tab_desc_entries']      = 'View all currently open blacklist entries, manually resolve or delete them.';
$l['blacklist_tab_desc_run_task']     = 'Manually trigger the blacklist task immediately without waiting for the scheduled time.';

// Category form labels
$l['blacklist_lbl_name']               = 'Name';
$l['blacklist_lbl_type']               = 'Type';
$l['blacklist_lbl_threshold']          = 'Threshold (days)';
$l['blacklist_lbl_response']           = 'Response type';
$l['blacklist_lbl_groups']             = 'Affected groups';
$l['blacklist_lbl_include_additional'] = 'Include additional groups';
$l['blacklist_lbl_away_exempt']        = 'Away status exempts';
$l['blacklist_lbl_active']             = 'Active';
$l['blacklist_lbl_sort_order']         = 'Sort order';
$l['blacklist_lbl_frozen_profile_field'] = 'Profile field (frozen-until date)';

// Descriptions
$l['blacklist_desc_name']               = 'Displayed name of this category.';
$l['blacklist_desc_type']               = 'What triggers this category.';
$l['blacklist_desc_threshold']          = 'Number of days of inactivity before an entry is created.';
$l['blacklist_desc_response']           = 'How users can remove themselves from this category.';
$l['blacklist_desc_groups']             = 'Only users in these groups are checked. Leave empty for all users.';
$l['blacklist_desc_include_additional'] = 'Also match users whose additional groups include the selected groups.';
$l['blacklist_desc_away_exempt']        = 'If enabled, users with the away/vacation status set will not be added to this category.';
$l['blacklist_desc_sort_order']         = 'Lower numbers appear first.';
$l['blacklist_desc_frozen_profile_field'] = 'Select the profile field that contains the "frozen until" date (must be parseable by strtotime, e.g. YYYY-MM-DD).';

// Types
$l['blacklist_type_scene_wait']    = 'Scene wait (no post in active scene)';
$l['blacklist_type_registration']  = 'Registration (inactive since registration)';
$l['blacklist_type_login']         = 'Login (inactive since last login)';
$l['blacklist_type_posting']       = 'Posting (no global post activity)';
$l['blacklist_type_group']         = 'Group membership';
$l['blacklist_type_no_subaccount'] = 'No sub-account (registered X+ days, no linked sub-account)';
$l['blacklist_type_no_active_scene'] = 'No current scene (no legitimate active scene with at least 2 participants)';
$l['blacklist_type_frozen']        = 'Frozen (profile date expired – still in "on ice" group)';

// Response types
$l['blacklist_resp_none']    = 'None (team must remove)';
$l['blacklist_resp_button']  = 'Button ("I\'m active")';
$l['blacklist_resp_posting'] = 'Post in scene (posting requirement)';

// Table columns
$l['blacklist_col_id']       = 'ID';
$l['blacklist_col_sort']     = 'Sort';
$l['blacklist_col_name']     = 'Name';
$l['blacklist_col_type']     = 'Type';
$l['blacklist_col_days']     = 'Days';
$l['blacklist_col_resp']     = 'Response';
$l['blacklist_col_active']   = 'Active';
$l['blacklist_col_actions']  = 'Actions';
$l['blacklist_col_user']     = 'User';
$l['blacklist_col_category'] = 'Category';
$l['blacklist_col_scene']    = 'Thread/Scene';
$l['blacklist_col_added']    = 'Added';
$l['blacklist_col_deadline'] = 'Deadline';
$l['blacklist_col_status']   = 'Status';

// Status labels
$l['blacklist_active']   = 'Active';
$l['blacklist_inactive'] = 'Inactive';
$l['blacklist_user_deleted'] = '(account deleted)';

// Actions
$l['blacklist_edit']       = 'Edit';
$l['blacklist_delete']     = 'Delete';
$l['blacklist_resolve']    = 'Resolve';
$l['blacklist_activate']   = 'Activate';
$l['blacklist_deactivate'] = 'Deactivate';
$l['blacklist_options']    = 'Options';
$l['blacklist_save']       = 'Save';

// Messages
$l['blacklist_category_saved']   = 'Category saved.';
$l['blacklist_category_updated'] = 'Category updated.';
$l['blacklist_category_deleted'] = 'Category deleted. All associated entries have been removed.';
$l['blacklist_resolved_success'] = 'Entry marked as resolved.';
$l['blacklist_entry_deleted']    = 'Entry deleted.';
$l['blacklist_not_found']        = 'Category not found.';
$l['blacklist_no_categories']    = 'No categories defined yet.';
$l['blacklist_no_entries_acp']   = 'No open entries.';
$l['blacklist_confirm_delete']   = 'Are you sure you want to delete this?';
$l['blacklist_task_ran']         = 'Task completed. Created: %d entries, skipped: %d.';
$l['blacklist_task_synced']      = 'Task schedule synced from settings.';
$l['blacklist_task_error']       = 'Task error:';

// Task debug page
$l['blacklist_debug_task_status'] = 'Task Status';
$l['blacklist_debug_field']       = 'Field';
$l['blacklist_debug_db']          = 'In DB (tasks table)';
$l['blacklist_debug_setting']     = 'From settings';
$l['blacklist_debug_enabled']     = 'Enabled';
$l['blacklist_debug_hour']        = 'Hour';
$l['blacklist_debug_minute']      = 'Minute';
$l['blacklist_debug_day']         = 'Day of month';
$l['blacklist_debug_nextrun']     = 'Next run';
$l['blacklist_debug_lastrun']     = 'Last run';
$l['blacklist_debug_servertime']  = 'Server time now';
$l['blacklist_debug_yes']         = 'Yes';
$l['blacklist_debug_no']          = 'No';
$l['blacklist_debug_no_task']     = 'Task not found in database. Deactivate and reactivate the plugin.';
$l['blacklist_debug_no_logs']     = 'No log entries yet.';
$l['blacklist_debug_log_title']   = 'Last 10 Task Log Entries';
$l['blacklist_debug_log_time']    = 'Time';
$l['blacklist_debug_log_message'] = 'Message';
$l['blacklist_btn_sync_task']     = 'Sync task schedule from settings';
$l['blacklist_btn_run_now']       = 'Run task now (forced)';
$l['blacklist_debug_unit_sec']    = 'sec.';
$l['blacklist_debug_unit_min']    = 'min.';
$l['blacklist_debug_unit_hrs']    = 'hrs.';
$l['blacklist_debug_unit_days']   = 'days';
$l['blacklist_debug_nextrun_in']      = 'in %s';
$l['blacklist_debug_nextrun_overdue'] = '%s ago – waiting for a page view';
$l['blacklist_debug_not_synced']      = 'not in sync!';
$l['blacklist_debug_daily']           = '* (daily)';

// Validation errors
$l['blacklist_err_name_required'] = 'Please enter a category name.';
$l['blacklist_err_invalid_type']  = 'Invalid category type selected.';
$l['blacklist_err_threshold']           = 'Threshold must be a number >= 1.';
$l['blacklist_err_frozen_field_required'] = 'Please select a profile field for the frozen-until date.';

// Thread notification posted when a new run starts (%1$s = deadline date, %2$s = blacklist URL).
// Duplicated from the frontend language file so the ACP "run now" button (which loads only
// the admin language file) posts a localised notification too.
$l['blacklist_notify_message'] = "A new blacklist has been published.\n\nResponse deadline: [b]%1\$s[/b]\n\n[url=%2\$s]View the current blacklist[/url]";
