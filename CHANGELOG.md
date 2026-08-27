# Changelog

## 1.6.1

### Fixed
- Closed-run archive view (`blacklist.php` after a run is marked done) showed a blank second column header instead of "Category". The `blacklist_run_section` template uses `{$lang->blacklist_col_category}`, but that key existed only in the **admin** language file (`inc/languages/*/admin/blacklist.lang.php`), not in the **frontend** file — and the frontend never loads the admin language file. Added `blacklist_col_category` to both frontend language files (english + deutsch_du) so the header renders on the public page.

### Changed
- The run-start thread notification text is now translatable via the new `blacklist_notify_message` language key instead of a hardcoded German string. The key is duplicated into the frontend and admin language files of both languages, because the notification can be triggered from the task/frontend (frontend lang loaded) and from the ACP "run now" button (admin lang loaded).
- The ACP task-status page no longer contains hardcoded German strings ("in", "vor … wartet auf Seitenaufruf", "nicht synchron!", "* (täglich)"). These now use new admin language keys (`blacklist_debug_nextrun_in`, `blacklist_debug_nextrun_overdue`, `blacklist_debug_not_synced`, `blacklist_debug_daily`).

---

## 1.6.0

### New
- New category type `no_active_scene` ("No current scene"): flags users who have no legitimate active scene right now. A scene only counts as legitimate when it has at least 2 active participants (accepted, unfinished, scene_type 0/1, `tid > 0`) whose accounts still exist — a scene down to a single remaining participant, or where the other participants' accounts were deleted, does not count. Momentary check with no time threshold; requires Logbook. Supported response types: `none` and `button` (button re-checks whether a legitimate scene now exists).

### Security
- ACP category **delete** and **activate/deactivate** were triggered via unprotected GET links (CSRF). They now live in a native ACP controls dropdown (`PopupMenu`, like forum management) and carry `my_post_key`, and their handlers validate the key (`verify_post_check(..., true)` with the return value checked — required because in the ACP `verify_post_check()` does not abort on its own). Deleting a category also removes its entries, so this closed a link-based data-loss vector.

### Changed (UI)
- ACP category actions are now a single **Options** dropdown (Edit / Activate-Deactivate / Delete) instead of inline links/buttons, matching MyBB's forum management look.

### Fixed
- `no_subaccount` category no longer causes an "Unknown column as_uid" SQL error on forums without the EnhancedAccountSwitcher plugin: the `users.as_uid` column is now guarded with `field_exists()` and the type is skipped when the column is absent.
- Group multi-select was not repopulated when a category form failed validation (array vs. comma-string mismatch); the form now handles both.

### Changed
- ACP category form input is now read via typed `$mybb->get_input()` instead of raw `$mybb->input`.
- Removed the redundant second load of `blacklist.css` (the manually injected `<link>` on every page); the globally attached theme stylesheet already covers all pages.
- Removed dead code (unused engine/adapter methods) and stray repo artifacts.

---

## 1.5.0

### Changed
- `scene_wait` now ignores free-order scenes (`free_order = 1`) entirely. Previously every participant in a free-order scene was flagged based on their own last post, even though without a fixed turn order it is unclear who is supposed to post next. Only fixed-order scenes are evaluated now, flagging the user whose turn it is. Affects the generated blacklist, the activity simulation and the debug trace.

### Fixed
- Run-start notification post: the forum index kept showing the previously newest thread as the forum's "last post" after the notification was posted. The forum's `lastposttid`/`lastpostsubject` pointers were never updated; the last-post bookkeeping now uses MyBB's `update_thread_counters()` / `update_forum_counters()` / `update_forum_lastpost()`.
- Same notification code reset the thread's reply count and the forum's post count to 0, because `update_query(['replies' => 'replies+1'])` quotes the value instead of evaluating it as SQL. Counters are now incremented correctly.

---

## 1.4.0

### Fixed
- Deleted accounts no longer vanish from an active (or closed) blacklist. The username is now captured onto the entry (`deleted_username`) the moment the account is deleted (`datahandler_user_delete_start` hook), and the entry stays listed with a struck-through name and an "(account deleted)" note instead of disappearing.
- Frontend and ACP entry queries switched from `INNER JOIN users` to `LEFT JOIN users` so entries survive after their user row is gone.

### New
- New column `blacklist_entries.deleted_username` (added automatically on plugin reactivation).

---

## 1.3.0

### New
- Task scheduling: settings (day, hour, minute, enable/disable) now directly update the MyBB task schedule on every ACP page load — no more stale schedules
- Task minute setting added (0–59)
- Task enable/disable toggle in plugin settings
- ACP Task Status page: shows DB state vs. settings, sync status, last/next run times, last 10 log entries, "Sync schedule" and "Force run" buttons

### Fixed
- `hasOpenEntry()` / `hasOpenEntryForScene()` returned true for entries from closed runs (no active run → no `run_id` filter applied), causing the task to skip all users and create zero entries
- CSRF vulnerability: ACP delete/resolve entry actions now use POST + `verify_post_check()` instead of unprotected GET links
- Duplicate `$lang->load()` call in ACP module removed
- Direct `$_GET['id']` access replaced with `$mybb->get_input()`
- `ufid='{$uid}'` SQL pattern replaced with `ufid = (int)` for consistency
- `isset($mybb->input['debug'])` replaced with `$mybb->get_input('debug')`
- Task debug page "Last run" column was incorrectly showing `nextrun` value instead of last log timestamp

---

## 1.2.0

### New
- **Run/cycle system**: blacklist runs can be marked as done by the team; the closed view shows two sections (responded / did not respond); a new run can be started afterwards
- **Auto-populate on run start**: clicking "Start new blacklist" immediately runs the task and populates entries
- **Index notice bar**: main accounts (as_uid = 0, EnhancedAccountSwitcher) see a banner on the forum index listing affected characters, deadlines and required actions
- **Single thread notification per run**: instead of one post per blacklisted user, exactly one post is created when a new run starts, stating the deadline and linking to the blacklist
- **Column adaptation**: the scene/thread column is only shown for `scene_wait` categories
- **Away exempt** flag per category: users with away status can be excluded
- `no_subaccount` category type: flags users who registered X+ days ago but have no linked sub-account
- `frozen` category type: flags users whose "frozen until" profile field date has passed
- Intro text setting: optional HTML/BBCode text shown between deadline line and entry list

### Fixed
- Button response type for `scene_wait` category now resolves immediately on click; previously incorrectly required a post in the scene
- `hasOpenEntry()` and `hasOpenEntryForScene()` scoped to active run only, preventing stale entries from blocking new ones after a run restart

---

## 1.1.0

### New
- Intro text setting: optional free text between the deadline line and the blacklist entries

---

## 1.0.0

- Initial release
- Scene-based activity checking via Logbook integration
- Category system with 5 types and 3 response modes
- Posting requirement with per-thread, per-timestamp validation
- MyBB task for scheduled generation
- Thread notification support
- Activity simulation page (no DB writes)
- ACP: category CRUD, manual entry management, task runner
