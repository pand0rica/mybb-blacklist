# Blacklist – Agent Instructions

Dieses Repo wird mit dem **MyBB Plugin Dev Kit** entwickelt. Bevor du hier etwas tust:

1. Lies zuerst das Dev-Kit: **`D:\mybb-plugin-dev-kit\AGENTS.md`** und halte dich an alle dort
   verlinkten Standards. Das Kit ist die kanonische Quelle für Konventionen, Boilerplate,
   Debugging und Testing. (Git-Repo des Kits: `pand0rica/mybb-devkit` – in Cloud-Sessions
   dort auschecken, falls der lokale Pfad fehlt.)
2. Lies dann in diesem Repo: `PROJECT_CONTEXT.md` (Kontext, Tabellen, Hooks,
   „What NOT to change"), danach `README.md` und `CHANGELOG.md`.

Das Dev-Kit wird **nicht** in dieses Repo kopiert; es wird per absolutem Pfad referenziert.

## Sprache
Antworten Deutsch, Code/Bezeichner Englisch. PHP 7.4 + 8.x. Keine externen Libraries.

## Phasen (der Nutzer löst sie einzeln aus)

Das Plugin ist bereits umgesetzt (kein `IMPLEMENTATION_PLAN.md` mehr nötig). Neue Features
werden über Phase 1 (Rework/Erweiterung) geplant.

| Nutzer sagt | Phase | Folge dieser Kit-Datei |
|---|---|---|
| „erweitere / überarbeite …" | Plan | `D:\mybb-plugin-dev-kit\prompts\rework.md` → `workflows\1-plan.md` |
| „umsetzen / implementieren" | Umsetzung | `D:\mybb-plugin-dev-kit\workflows\2-implement.md` |
| „reviewe das Projekt" | Review | `D:\mybb-plugin-dev-kit\workflows\3-review.md` |
| „release das Projekt" | Release | `D:\mybb-plugin-dev-kit\workflows\4-release.md` |

## Die 5 goldenen Regeln (Kurzform, Details im Kit)
Keine Core-Edits · `TABLE_PREFIX` · `$mybb->get_input()` · POST + `verify_post_check()` ·
alles mit Präfix `blacklist_` (Klassen: `Blacklist_`). Ausgabe `htmlspecialchars_uni()`,
SQL `escape_string()`/`(int)`.
