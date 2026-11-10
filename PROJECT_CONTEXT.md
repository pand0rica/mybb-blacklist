# Blacklist – Project Context

## Identität
- **Name:** Blacklist
- **Codename / Dateiname:** `blacklist` (Präfix: `blacklist_`, Klassen `Blacklist_*`)
- **Version:** 1.6.3
- **Entwickelt von:** pand0rica
- **Kurzbeschreibung:** Szenenbasierte Aktivitäts-Blacklist für MyBB-RPG-Foren mit Logbook-Integration.
- **MyBB:** 1.8.x · **PHP:** 7.4 + 8.x

## Zweck & Zielgruppe
Prüft für RPG-Foren, ob Charaktere (Accounts) in ihren aktiven Szenen posten, und erzeugt
Blacklist-Einträge – szenenbasiert über das Logbook-Plugin, nicht nur über globale
Aktivität. Blacklists laufen in **Runden** (runs): Team startet eine Runde, die Betroffenen melden sich
zurück, Team schließt die Runde ab.

## Architektur
- **Variante:** Klassen-/Core-Split (viele Kategorie-Typen, Fremd-Plugin-Adapter, Task,
  ACP-Modul, Frontend-Seite).
- **Dateilayout:**
  - `blacklist.php` – Frontend: aktuelle Blacklist, `?action=activity` (Live-Simulation,
    keine DB-Writes), `?action=activity&debug=1` (scene_wait-Trace, nur Team), POST-Aktionen
    `resolve`, `add`, `delete`, `team_resolve`, `close_run`, `new_run`.
  - `inc/plugins/blacklist.php` – Lifecycle, Settings, Templates, Stylesheet, Task-Sync,
    Hook-Handler, Index-Hinweis, Helfer (`blacklist_is_team()`, `blacklist_js_string()`).
  - `inc/plugins/blacklist/core/engine.php` – `Blacklist_Engine` (Erzeugung, Auswertung,
    Auflösung, Simulation, Runden).
  - `inc/plugins/blacklist/core/logbook_adapter.php` – `Blacklist_LogbookAdapter`.
  - `inc/plugins/blacklist/core/notifications.php` – `Blacklist_Notifications` (ein Post pro Runde).
  - `inc/plugins/blacklist/templates/*.tpl` – alle Templates; Liste in `blacklist_template_names()`.
  - `inc/plugins/blacklist/assets/blacklist.css` – Stylesheet (alle Themes, `attachedto` leer).
  - `admin/modules/tools/blacklist.php` – ACP → Tools → Blacklist (Kategorien, offene
    Einträge, Task-Status).
  - `inc/tasks/blacklist.php` – MyBB-Task `task_blacklist()`.
  - Sprachdateien `english` + `deutsch_du`, jeweils Frontend + `admin/` getrennt.

## Datenbank-Tabellen
| Tabelle (`TABLE_PREFIX .`) | Zweck | Wichtige Spalten |
|---|---|---|
| `blacklist_categories` | Kategorie-Definitionen | `name`, `active`, `type`, `threshold_days`, `group_ids`, `include_additional_groups`, `response_type`, `sort_order`, `away_exempt`, `frozen_profile_field` |
| `blacklist_entries` | Einzelne Einträge | `uid`, `category_id`, `scene_id`, `thread_id`, `timestamp_added`, `deadline`, `status` (open/resolved), `run_id`, `deleted_username` |
| `blacklist_runs` | Runden (aktiv/geschlossen) | `created_at`, `closed_at`, `closed_by_uid`, `status`, `notified` |

Schema-Ergänzungen älterer Versionen laufen idempotent in `blacklist_migrate_db()` (activate).

## Kategorie-Typen
- `scene_wait` – kein Post in aktiver Logbook-Szene seit X Tagen; nur Szenen mit fester
  Reihenfolge, geflaggt wird, wer dran ist (Free-Order-Szenen werden ignoriert).
- `registration` – inaktiv seit Registrierung.
- `login` – inaktiv seit lastactive/lastvisit.
- `posting` – kein globaler Post seit X Tagen.
- `group` – Gruppenmitgliedschaft (flaggt alle passenden Accounts).
- `no_active_scene` – keine legitime aktive Szene (≥ 2 aktive Teilnehmende mit existierendem
  Account), ohne Zeitschwelle.
- `no_subaccount` – seit X+ Tagen registriert, aber kein verknüpfter Zweitaccount
  (`users.as_uid`, EnhancedAccountSwitcher). Wird komplett übersprungen, wenn die Spalte fehlt.
- `frozen` – ein konfiguriertes Datums-Profilfeld („eingefroren bis") liegt X+ Tage zurück.

## Response-Typen
- `none` – nur das Team kann auflösen.
- `button` – Betroffene klicken „Ich bleibe aktiv!", die Bedingung wird neu geprüft.
- `posting` – Betroffene müssen im exakten Thread **nach** `timestamp_added` posten. Einträge ohne
  `thread_id` (Nicht-Szenen-Kategorien) akzeptieren jeden sichtbaren Post nach
  `timestamp_added`.

## Settings (settinggroup `blacklist`)
| Setting | optionscode | Zweck |
|---|---|---|
| `blacklist_deadline_days` | text | Frist in Tagen (Default 14) |
| `blacklist_task_enabled` | yesno | Automatische Generierung an/aus |
| `blacklist_task_day` | text | Tag im Monat (1–28, 0 = täglich) |
| `blacklist_task_hour` | text | Stunde (0–23) |
| `blacklist_task_minute` | text | Minute (0–59) |
| `blacklist_notify_thread_id` | text | Thread für die Runden-Benachrichtigung (0 = aus) |
| `blacklist_notify_user_id` | text | Poster-UID der Benachrichtigung |
| `blacklist_mod_groups` | groupselect | Gruppen mit Team-Rechten (zusätzlich Admins/Super-Mods) |
| `blacklist_intro_text` | textarea | Optionaler Introtext (HTML/BBCode) |

Settings werden in `install()` angelegt und in `uninstall()` entfernt; `activate()` rüstet
nur fehlende Settings nach.

## Hooks
| Hook | Handler | Zweck |
|---|---|---|
| `global_start` | `blacklist_load_language` | Frontend-Sprachdatei + Template-Cache-Liste |
| `newreply_do_newreply_end`, `newthread_do_newthread_end` | `blacklist_handle_new_post` | `posting`-Einträge im Thread auflösen |
| `logbook_scene_finished_changed` | `blacklist_on_scene_finished` | Eigene Szenen-Einträge des Beendenden auflösen; bei ≤1 aktivem Teilnehmer alle Einträge der Szene |
| `index_start` | `blacklist_build_index_notice` | Hinweisleiste `{$blacklist_index_notice}` im Template `index` |
| `datahandler_user_delete_start` | `blacklist_handle_user_delete` | Usernamen vor dem Löschen sichern (`deleted_username`) |
| `admin_tools_menu`, `admin_tools_action_handler` | `blacklist_admin_tools_*` | ACP-Menü |
| `admin_load` | `blacklist_load_admin_language` | Admin-Sprachdatei + Task-Schedule-Sync |

## Eigene Hooks / API
Keine.

## Abhängigkeiten
- **Logbook** (erforderlich für `scene_wait`/`no_active_scene`): Tabellen `logbook`,
  `logbook_entry`, `scene`. Zugriff **ausschließlich** über `Blacklist_LogbookAdapter`
  (`isLogbookInstalled()` prüft die Tabellen).
- **EnhancedAccountSwitcher** (optional): `users.as_uid` für `no_subaccount` und die
  Index-Hinweisleiste; per `field_exists()` geprüft.

## Berechtigungen
- Blacklist und Aktivitätssimulation: alle Besucher; Self-Removal nur eingeloggt und nur
  für eigene Einträge.
- Team (`blacklist_is_team()`): `cancp`, `issupermod` oder Mitglied einer
  `blacklist_mod_groups`-Gruppe (Primär- oder Zusatzgruppe).
- Alle schreibenden Frontend-Aktionen: POST + `verify_post_check()`; Team-Aktionen ohne
  Team-Recht → `error_no_permission()`.
- ACP: schreibende Aktionen laufen ausschließlich unter `request_method === 'post'`; den
  `my_post_key` prüft `admin/index.php` selbst (Dev-Kit `debugging.md` #23). Popup-Aktionen
  (Aktivieren/Deaktivieren, Löschen) nutzen das Core-Muster Link mit `my_post_key` +
  `AdminCP.deleteConfirmation()`, das den Link nach Rückfrage als POST absendet.

## What NOT to change
- Logbook-Tabellen nirgends außerhalb von `Blacklist_LogbookAdapter` ansprechen.
- `LEFT JOIN users` in Eintragsabfragen beibehalten – Einträge gelöschter Accounts bleiben
  mit `deleted_username` sichtbar.
- `hasOpenEntry()`/`hasOpenEntryForScene()` bleiben auf die aktive Runde beschränkt
  (sonst legt der Task 0 Einträge an, siehe CHANGELOG 1.3.0).
- `scene_wait` ignoriert Free-Order-Szenen bewusst (1.5.0).
- Settings nie in `activate()`/`deactivate()` löschen/neu anlegen.
- Die Runden-Benachrichtigung nutzt `update_thread_counters()`/`update_forum_counters()`/
  `update_forum_lastpost()` – kein `update_query(['replies' => 'replies+1'])`.
- Templates werden in `activate()` neu aus den `.tpl`-Dateien geladen: nach Template-
  Änderungen Plugin einmal deaktivieren + aktivieren.
