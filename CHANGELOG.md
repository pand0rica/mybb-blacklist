# Changelog

## [Unreleased]

### Fixed
- **PHP 8 warning on the blacklist page.** `$usercpnav` is initialised before the page template is evaluated, so the undefined-variable warning is gone.
- **Scene finished hook.** Logbook hook `logbook_scene_finished_changed`: the handler waited for a `finished` flag that Logbook no longer sends (finished is stored per participant now), so it never did anything. Open entries of a scene were only cleaned up by the task or when opening the blacklist page. The handler now resolves the toggling participant's own entries for that scene once they are no longer active, and all entries of the scene once at most one active participant is left.

### Changed
- Stylesheet uses plain colour/size values instead of the `--h4w-*` custom properties of one specific forum theme. (Forum-specific theming lives on the separate `h4w` branch.)

## Unreleased

### Changed
- **ACP CSRF protection via MyBB's native mechanism** (dev kit `debugging.md` #23): the own
  `blacklist_acp_verify_key()` helper is gone. `admin/index.php` already validates the
  `my_post_key` of every ACP POST and downgrades invalid requests to GET; all write handlers
  only act on POST. “Activate/Deactivate” in the category menu now uses the core pattern
  `AdminCP.deleteConfirmation()` (confirm + POST), like “Delete”; a plain GET request no
  longer toggles a category. The unused `blacklist_invalid_key` language string was removed.

### Added
- `PLUGIN_POST.html` (forum presentation post) is now part of the repository.

## 1.6.3

### Changed
- German texts are now gender-inclusive (dev kit `conventions.md` §10): "Nutzer"/"Benutzer" → "Accounts"/"Betroffene", "Teilnehmer" → "Teilnehmende" in the frontend and admin language files (`blacklist_lbl_uid`, `blacklist_col_user`, `blacklist_desc_*`, `blacklist_type_no_active_scene`, `blacklist_condition_no_active_scene`, debug column labels). No template changes – no reactivation needed.
- `AGENTS.md` / `PROJECT_CONTEXT.md` updated to the same rule.

---

## 1.6.2

**Upgrade:** after uploading the files, deactivate and reactivate the plugin once – this reloads the templates (several were added) and the stylesheet. Settings and data are kept.

### Security
- ACP **Open Entries → Resolve/Delete**: the handlers called `verify_post_check()` without checking its return value. In the ACP that function only returns `false` instead of aborting, and the handlers did not require POST either, so a crafted GET link (`index.php?module=tools-blacklist&action=delete_entry&id=X`) could delete or resolve entries without a valid post key (CSRF). Both handlers now require POST and validate the key via `blacklist_acp_verify_key()`. All other ACP write handlers (category add/edit, task sync/run) use the same checked helper instead of the ineffective bare call.
- Frontend team actions (`add`, `delete`, `team_resolve`, `close_run`, `new_run`) and the self-removal (`resolve`) are now POST-only; a non-team user calling a team action gets `error_no_permission()` instead of silently landing on the blacklist page.

### Fixed
- Activity simulation showed an empty condition line for `frozen` categories (missing case + language key `blacklist_condition_frozen`).
- ACP menu entry forced the *frontend* language file (`$lang->load('blacklist', true)`), which does not contain `blacklist_acp_title`; it now loads the admin language file, where the key lives.
- ACP module now calls `$lang->load('blacklist')` as its first statement (before the permission redirect) and uses the translated title in all breadcrumbs instead of a hardcoded "Blacklist".
- Stylesheet installer quoted integer IDs in WHERE clauses (`tid = '1'`, `sid = '5'`); now plain `(int)` comparisons.

### Changed
- Response type *Post in scene* on entries without a scene (non-scene categories such as posting inactivity, `thread_id = 0`): the "I'm active!" check now accepts any visible post after the entry was created. Previously such entries could never be resolved by the user. The `blacklist_resolved_failed` text was adjusted.
- Frontend action buttons follow the MyBB button convention: every action (I'm active!, Resolve, Delete, Add entry, Mark as done, Start new blacklist) is its own `<form>` with an `<input type="submit" class="button">`. The custom-styled `<button class="blacklist-icon-btn">` with inline SVG icons is gone, so buttons now take their look from the theme. Deleting an entry on the public page asks for confirmation.
- All remaining inline HTML of the frontend page and the forum index notice was moved into templates (the forum index notice now uses `blacklist_index_notice` / `blacklist_index_notice_row`; also new: `blacklist_deadline_info`, `blacklist_category_scene_header`, `blacklist_entry_scene_cell`, `blacklist_entry_resolved`, `blacklist_entry_no_action`, `blacklist_add_form`, `blacklist_run_button`, `blacklist_run_notice`, `blacklist_run_empty_row`, `blacklist_user_link`, `blacklist_user_deleted`, `blacklist_activity_empty_row`, `blacklist_activity_scene_list`, `blacklist_activity_scene_item`, `blacklist_activity_date`, `blacklist_debug`, `blacklist_debug_row`). `blacklist_entry_button` is now a generic action button. Inline `style` attributes moved into `blacklist.css`; colours use the `--h4w-*` CSS variables with fallbacks.
- The team debug trace (`blacklist.php?action=activity&debug=1`) is now a normal forum page (header/footer, templates) and fully translatable instead of a raw HTML page with hardcoded German strings.
- Plugin templates are added to `$templatelist` on `index.php` and `blacklist.php` (fewer queries).
- Confirmation texts in JavaScript attributes are escaped via the new helper `blacklist_js_string()` (JSON + HTML escaping) instead of `addslashes()`.
- Removed: unused template `blacklist_activity_row`, unused language keys (`blacklist_days_inactive`, `blacklist_would_be_listed`, `blacklist_activity_ok`), the no-op `logbook_scene_created` hook handler, the unused `{$usercpnav}` placeholder and an unused `global $db`.

### Docs
- README: update instructions, corrected activity-simulation description (shows all affected accounts; there is no `&uid=` parameter), index-notice behaviour without EnhancedAccountSwitcher, debug trace, tips, precise deactivate/uninstall cleanup.
- Added `AGENTS.md`, `PROJECT_CONTEXT.md`; `CLAUDE.md` is now a pointer to them. `LICENSE` now contains the complete GPL-3.0 text.

---

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
