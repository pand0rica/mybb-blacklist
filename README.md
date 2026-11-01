# Blacklist

**Version 1.6.1**

A scene-based activity blacklist plugin for MyBB 1.8 RPG forums with Logbook integration.

The plugin automatically checks whether characters (users) are posting in their active scenes and creates blacklist entries when they become inactive — based on individual scenes, not just global user activity. Blacklists are organized in runs (cycles): the team opens a run, users respond, and the team closes it when done.

---

## Requirements

- MyBB 1.8.x
- PHP 7.4 or higher
- **Logbook plugin** (required for scene-based categories — install and activate it first)
- **EnhancedAccountSwitcher plugin** (optional — required for the index notice bar feature)

---

## Installation

1. Upload all files to your MyBB root directory, keeping the folder structure intact.
2. Go to **ACP → Configuration → Plugins**.
3. Find **Blacklist** and click **Activate**.
4. Go to **ACP → Tools → Blacklist** to create categories.
5. Go to **ACP → Configuration → Settings → Blacklist** to configure deadlines, task schedule and notifications.

---

## Configuration

| Setting | Description |
|---|---|
| **Deadline (days)** | Number of days after entry creation until the response deadline. Default: 14. |
| **Task: enable automatic generation** | Enable or disable the scheduled blacklist task. |
| **Task: day of month** | Day (1–28) on which the task runs automatically. 0 = daily. Changes take effect immediately after saving. |
| **Task: hour** | Hour (0–23) at which the task runs. Changes take effect immediately after saving. |
| **Task: minute** | Minute (0–59) at which the task runs. Changes take effect immediately after saving. |
| **Notification: Thread ID** | Thread ID to post in when a new blacklist run is started. 0 = disabled. |
| **Notification: Poster UID** | User ID that posts the notification. Required if Thread ID is set. |
| **Moderator groups** | Groups that can manage blacklist entries (besides admins). |
| **Intro text** | Optional HTML/BBCode text shown between the deadline line and the entry list on the public page. |

---

## Usage

### First steps after activation

1. Activate the plugin via **ACP → Plugins**.
2. Go to **ACP → Tools → Blacklist → Categories** and create at least one category.
3. Verify the Logbook plugin is installed and active (required for `scene_wait` categories).
4. Configure the task schedule in **ACP → Configuration → Settings → Blacklist**.
5. Optionally configure the notification thread.

### Managing categories

Go to **ACP → Tools → Blacklist → Categories**.

![ACP: Category list](docs/screenshots/acp-overview.png)

Click **Add Category** and fill in:

- **Name** – Displayed on the public blacklist page (e.g. "Scene inactivity")
- **Type** – What triggers the category (see below)
- **Threshold (days)** – How many days of inactivity trigger an entry
- **Response type** – How users can remove themselves
- **Affected groups** – Leave empty to check all users; otherwise select specific groups
- **Include additional groups** – Also match users whose additional groups contain the selected groups
- **Away status exempts** – If enabled, users with the away/vacation flag set are skipped

![ACP: Add category form](docs/screenshots/acp-categories.png)

#### Category types

| Type | Description |
|---|---|
| **Scene wait** | No post in an active Logbook scene for X days. Only fixed-order scenes are evaluated: the user whose turn it is gets flagged. Free-order scenes are ignored, because without a defined turn order it is unclear who is supposed to post next. |
| **Registration** | Inactive since registration date |
| **Login** | Inactive since last login |
| **Posting** | No post anywhere for X days |
| **Group membership** | All users in matching groups get an entry |
| **No sub-account** | Registered X+ days ago but has no linked sub-account (requires EnhancedAccountSwitcher) |
| **No current scene** | User has no legitimate active scene right now. A scene only counts when it has at least 2 active participants whose accounts still exist – a scene down to a single remaining participant (or where the other participants' accounts were deleted) does not count. No time threshold; requires Logbook. |
| **Frozen** | A configured date profile field ("frozen until") lies X+ days in the past |

#### Response types

| Type | Description |
|---|---|
| **None** | Entry can only be removed by the team |
| **Button** | User clicks "I'm active!"; entry is resolved immediately |
| **Post in scene** | User must post in the exact scene thread **after** the entry was created. For entries without a scene (e.g. posting inactivity), any post in the forum after the entry was created counts |

### Blacklist runs (cycles)

The blacklist is organized in **runs**. A run represents one complete blacklist cycle:

1. **Start**: The task creates entries automatically, or a team member clicks **Start new blacklist** on the blacklist page. Entries are created immediately on start.
2. **Response phase**: Users see the blacklist at `blacklist.php` and respond by clicking the button, posting in their scene, or contacting the team.
3. **Close**: A team member clicks **Mark as done**. The page then shows two sections:
   - **Responded** – users who resolved their entry
   - **Did not respond – accounts deleted** – users who did not
4. **Repeat**: Click **Start new blacklist** to begin the next cycle.

Only one run can be active at a time. Closing a run does not delete entries — they remain visible in the archive view.

![Closed blacklist run](docs/screenshots/blacklist-done.png)

### Index notice bar

If EnhancedAccountSwitcher is installed, main accounts (the account with `as_uid = 0`) automatically see a notice banner on the forum index when any of their characters have an open blacklist entry. The banner lists affected characters, deadlines and the required action.

![Index notice bar](docs/screenshots/blacklist-index-notice.png)

### Public blacklist

Users and visitors can see the current blacklist at:

```
https://yourforum.com/blacklist.php
```

Entries are grouped by category. Scene/thread columns are only shown for categories that need them. If a user has a self-removal option, the appropriate button appears next to their entry.

![Public blacklist (user view)](docs/screenshots/blacklist-user.png)

### Activity simulation

Any logged-in user can check which entries would be generated for them right now — without anything being saved:

```
https://yourforum.com/blacklist.php?action=activity
```

Team members can check any user by appending `&uid=X`.

![Activity simulation](docs/screenshots/blacklist-activity.png)

### Team management

Users in the configured moderator groups (or admins) see additional controls on the blacklist page:

- **Resolve** – mark an entry as done manually
- **Delete** – permanently remove an entry
- **Add entry** – manually add an entry for any user
- **Mark as done** – close the current run
- **Start new blacklist** – start a new run (only visible when no run is active)

![Public blacklist (team view)](docs/screenshots/blacklist-admin.png)

Go to **ACP → Tools → Blacklist → Open Entries** for a full management overview.

### Automatic task

The blacklist is generated automatically by MyBB's task system. Configure the schedule in **ACP → Configuration → Settings → Blacklist** (enable, day, hour, minute). Changes take effect immediately after saving — no plugin reactivation needed.

To monitor or trigger the task manually: **ACP → Tools → Blacklist → Task Status**. This page shows:
- Whether the task is enabled and whether the DB matches the settings
- Next and last run timestamps
- The last 10 task log entries (including any errors)
- Buttons to sync the schedule or force a run

### Thread notification

Set a Thread ID and Poster UID in the settings. When a new blacklist run is started and entries are created, the configured user posts exactly **one** message in that thread stating the response deadline and linking to the blacklist. No individual users are listed.

---

## Uninstallation

1. Go to **ACP → Configuration → Plugins** → **Deactivate** Blacklist.
2. Click **Uninstall**.

This removes all database tables (`blacklist_categories`, `blacklist_entries`, `blacklist_runs`), all settings, templates, the scheduled task and the stylesheet from all themes. The Logbook plugin is not affected.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
