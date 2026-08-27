# Blacklist Plugin – Developer Notes

## What this plugin does
Scene-based activity blacklist for MyBB 1.8 RPG forums.
Integrates with the Logbook plugin (F:\Code\mybb-plugins\logbooks) to check whether users post in their active scenes.

## DB tables
- `mybb_blacklist_categories` – category definitions
- `mybb_blacklist_entries` – individual blacklist entries (has `run_id` FK)
- `mybb_blacklist_runs` – blacklist run tracking (active/closed cycles)

## Logbook dependency
- Tables used: `mybb_logbook`, `mybb_logbook_entry`, `mybb_scene`
- All access via `Blacklist_LogbookAdapter` (inc/plugins/blacklist/core/logbook_adapter.php)
- Never access Logbook tables directly outside this adapter

## Category types
- `scene_wait` – no post in active Logbook scene for X days
- `registration` – inactive since regdate
- `login` – inactive since lastactive/lastvisit
- `posting` – no global post in X days
- `group` – group membership (just flags all matching users)
- `no_active_scene` – has no legitimate active scene right now (a scene only counts when it has >=2 active participants whose accounts still exist; no time threshold)
- `no_subaccount` – registered X+ days ago but has no linked sub-account (`users.as_uid`, EnhancedAccountSwitcher). Skipped entirely when the `as_uid` column is absent.
- `frozen` – a configured date profile field ("frozen until") lies X+ days in the past

## Response types
- `none` – team must resolve manually
- `button` – user clicks "I'm active", condition is re-checked
- `posting` – user must post in the exact thread AFTER timestamp_added

## Hooks used from Logbook
- `logbook_scene_finished_changed` – auto-resolve entries when scene is finished

## ACP path
ACP → Tools → Blacklist

## Frontend
- `blacklist.php` – current blacklist (grouped by category)
- `blacklist.php?action=activity` – live simulation, no DB writes

## Task
`inc/tasks/blacklist.php` – generates entries, registered as MyBB task.
Configurable: day of month + hour via ACP settings.
