<?php
$l['blacklist_title']           = 'Blacklist';
$l['blacklist_activity_title']  = 'Activity Check';
$l['blacklist_activity_hint']   = 'This view shows which blacklist entries would be generated right now. Nothing is saved.';

// Navigation
$l['blacklist_nav_current']  = 'Current Blacklist';
$l['blacklist_nav_activity'] = 'Activity Simulation';

// Entry labels
$l['blacklist_deadline']      = 'Deadline';
$l['blacklist_days_inactive'] = 'Days inactive';
$l['blacklist_would_be_listed'] = 'Would be blacklisted';
$l['blacklist_activity_ok']     = 'Active – no entry';

// Buttons
$l['blacklist_btn_im_active'] = 'I\'m active!';
$l['blacklist_btn_resolve']   = 'Resolve';
$l['blacklist_btn_delete']    = 'Delete';
$l['blacklist_btn_add']       = 'Add entry';

// Team form labels
$l['blacklist_lbl_uid']      = 'User ID:';
$l['blacklist_lbl_category'] = 'Category:';
$l['blacklist_lbl_thread']   = 'Thread ID (0 = none):';

// Status messages
$l['blacklist_resolved_success'] = 'Your blacklist entry has been resolved.';
$l['blacklist_resolved_failed']  = 'The condition for removal has not been met yet. Please post first (for scene entries: in the relevant scene).';
$l['blacklist_entry_added']      = 'Blacklist entry added.';
$l['blacklist_entry_deleted']    = 'Blacklist entry deleted.';

// Errors
$l['blacklist_invalid_entry'] = 'Invalid blacklist entry.';
$l['blacklist_invalid_input'] = 'Invalid input data.';

// Empty state
$l['blacklist_no_entries']  = 'No open blacklist entries. Great!';
$l['blacklist_no_affected'] = 'No affected accounts right now.';

// Activity simulation – condition descriptions (use sprintf with %d for threshold days)
$l['blacklist_condition_scene_wait']    = 'No post in active scene for %d days';
$l['blacklist_condition_login']         = 'No login for %d days';
$l['blacklist_condition_registration']  = 'Account registered %d+ days ago, inactive since';
$l['blacklist_condition_posting']       = 'No global post for %d days';
$l['blacklist_condition_group']         = 'Member of a specific group';
$l['blacklist_condition_no_subaccount'] = 'Registered %d+ days ago, no sub-account linked';
$l['blacklist_condition_no_active_scene'] = 'No legitimate active scene (at least 2 participants)';

// Activity simulation – date/detail labels
$l['blacklist_lbl_last_login']    = 'Last login';
$l['blacklist_lbl_last_post']     = 'Last post';
$l['blacklist_lbl_registered']    = 'Registered';
$l['blacklist_lbl_last_activity'] = 'Last activity';
$l['blacklist_lbl_scene_status']  = 'Scene status';
$l['blacklist_lbl_frozen_until']  = 'Frozen until';
$l['blacklist_lbl_days']          = 'days';
$l['blacklist_col_user']          = 'Character';
$l['blacklist_col_scenes']        = 'Inactive scenes';
$l['blacklist_col_scene']         = 'Scene';
$l['blacklist_col_category']      = 'Category';
$l['blacklist_col_actions']       = 'Actions';
$l['blacklist_action_none']       = 'None';
$l['blacklist_status_resolved']   = 'Responded';
$l['blacklist_user_deleted']      = '(account deleted)';

// Index notice (main account bar)
$l['blacklist_notice_title']          = 'Blacklist';
$l['blacklist_notice_link']           = 'View blacklist';
$l['blacklist_notice_until']          = 'Deadline';
$l['blacklist_notice_action_button']  = 'Click "I\'m active!" on the blacklist page';
$l['blacklist_notice_action_posting'] = 'Post in the scene';
$l['blacklist_notice_action_none']    = 'Contact the team';

// Run management
$l['blacklist_btn_close_run']           = 'Mark as done';
$l['blacklist_btn_new_run']             = 'Start new blacklist';
$l['blacklist_run_close_confirm']       = 'Mark this blacklist as done?';
$l['blacklist_run_closed_success']      = 'Blacklist has been marked as done.';
$l['blacklist_run_started']             = 'New blacklist started.';
$l['blacklist_no_active_run']           = 'No active blacklist found.';
$l['blacklist_run_closed_title']        = 'Blacklist completed';
$l['blacklist_run_closed_notice']       = 'This blacklist was marked as done on %s by %s.';
$l['blacklist_run_section_responded']       = 'Responded';
$l['blacklist_run_section_not_responded']   = 'Did not respond – accounts deleted';
$l['blacklist_run_no_entries']          = 'No entries.';

// Thread notification posted when a new run starts (%1$s = deadline date, %2$s = blacklist URL)
$l['blacklist_notify_message'] = "A new blacklist has been published.\n\nResponse deadline: [b]%1\$s[/b]\n\n[url=%2\$s]View the current blacklist[/url]";
