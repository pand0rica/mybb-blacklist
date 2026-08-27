<?php
$l['blacklist_title']           = 'Blacklist';
$l['blacklist_activity_title']  = 'Aktivitätsprüfung';
$l['blacklist_activity_hint']   = 'Diese Ansicht zeigt, welche Blacklist-Einträge gerade erzeugt werden würden. Es wird nichts gespeichert.';

// Navigation
$l['blacklist_nav_current']  = 'Aktuelle Blacklist';
$l['blacklist_nav_activity'] = 'Aktivitätsübersicht';

// Entry labels
$l['blacklist_deadline']      = 'Frist';
$l['blacklist_days_inactive'] = 'Tage inaktiv';
$l['blacklist_would_be_listed'] = 'Würde gelistet werden';
$l['blacklist_activity_ok']     = 'Aktiv – kein Eintrag';

// Buttons
$l['blacklist_btn_im_active'] = 'Ich bleibe aktiv!';
$l['blacklist_btn_resolve']   = 'Erledigen';
$l['blacklist_btn_delete']    = 'Löschen';
$l['blacklist_btn_add']       = 'Eintrag hinzufügen';

// Team form labels
$l['blacklist_lbl_uid']      = 'Benutzer-ID:';
$l['blacklist_lbl_category'] = 'Kategorie:';
$l['blacklist_lbl_thread']   = 'Thread-ID (0 = keine):';

// Status messages
$l['blacklist_resolved_success'] = 'Dein Blacklist-Eintrag wurde erledigt.';
$l['blacklist_resolved_failed']  = 'Die Bedingung für die Selbstentfernung ist noch nicht erfüllt. Bitte poste zuerst in der betreffenden Szene.';
$l['blacklist_entry_added']      = 'Blacklist-Eintrag hinzugefügt.';
$l['blacklist_entry_deleted']    = 'Blacklist-Eintrag gelöscht.';

// Errors
$l['blacklist_invalid_entry'] = 'Ungültiger Blacklist-Eintrag.';
$l['blacklist_invalid_input'] = 'Ungültige Eingabe.';

// Empty state
$l['blacklist_no_entries']  = 'Keine offenen Blacklist-Einträge. Alles prima!';
$l['blacklist_no_affected'] = 'Aktuell keine betroffenen Accounts.';

// Activity simulation – condition descriptions (use sprintf with %d for threshold days)
$l['blacklist_condition_scene_wait']    = 'Kein Post in aktiver Szene seit %d Tagen';
$l['blacklist_condition_login']         = 'Kein Login seit %d Tagen';
$l['blacklist_condition_registration']  = 'Account vor %d+ Tagen registriert, seitdem inaktiv';
$l['blacklist_condition_posting']       = 'Kein Post weltweit seit %d Tagen';
$l['blacklist_condition_group']         = 'Mitglied in bestimmter Gruppe';
$l['blacklist_condition_no_subaccount'] = 'Seit %d+ Tagen registriert, kein Unteraccount verknüpft';
$l['blacklist_condition_no_active_scene'] = 'Keine legitime aktive Szene (mind. 2 Teilnehmer)';

// Activity simulation – date/detail labels
$l['blacklist_lbl_last_login']    = 'Letzter Login';
$l['blacklist_lbl_last_post']     = 'Letzter Post';
$l['blacklist_lbl_registered']    = 'Registriert';
$l['blacklist_lbl_last_activity'] = 'Letzte Aktivität';
$l['blacklist_lbl_scene_status']  = 'Szenen-Status';
$l['blacklist_lbl_frozen_until']  = 'Ablaufdatum';
$l['blacklist_lbl_days']          = 'Tage';
$l['blacklist_col_user']          = 'Charakter';
$l['blacklist_col_scenes']        = 'Inaktive Szenen';
$l['blacklist_col_scene']         = 'Szene';
$l['blacklist_col_category']      = 'Kategorie';
$l['blacklist_col_actions']       = 'Aktionen';
$l['blacklist_action_none']       = 'Keine';
$l['blacklist_status_resolved']   = 'Zurückgemeldet';
$l['blacklist_user_deleted']      = '(Account gelöscht)';

// Index notice (main account bar)
$l['blacklist_notice_title']          = 'Blacklist';
$l['blacklist_notice_link']           = 'Zur Blacklist';
$l['blacklist_notice_until']          = 'Frist';
$l['blacklist_notice_action_button']  = '"Ich bleibe aktiv!" auf der Blacklist klicken';
$l['blacklist_notice_action_posting'] = 'In der Szene posten';
$l['blacklist_notice_action_none']    = 'Team kontaktieren';

// Run management
$l['blacklist_btn_close_run']           = 'Als erledigt markieren';
$l['blacklist_btn_new_run']             = 'Neue Blacklist starten';
$l['blacklist_run_close_confirm']       = 'Diese Blacklist wirklich als erledigt markieren?';
$l['blacklist_run_closed_success']      = 'Blacklist wurde als erledigt markiert.';
$l['blacklist_run_started']             = 'Neue Blacklist gestartet.';
$l['blacklist_no_active_run']           = 'Keine aktive Blacklist vorhanden.';
$l['blacklist_run_closed_title']        = 'Blacklist abgeschlossen';
$l['blacklist_run_closed_notice']       = 'Diese Blacklist wurde am %s von %s als erledigt markiert.';
$l['blacklist_run_section_responded']       = 'Zurückgemeldet';
$l['blacklist_run_section_not_responded']   = 'Nicht zurückgemeldet';
$l['blacklist_run_no_entries']          = 'Keine Einträge.';

// Thread-Benachrichtigung beim Start eines neuen Runs (%1$s = Frist-Datum, %2$s = Blacklist-URL)
$l['blacklist_notify_message'] = "Eine neue Blacklist wurde veröffentlicht.\n\nRückmeldefrist: [b]%1\$s[/b]\n\n[url=%2\$s]Zur aktuellen Blacklist[/url]";
