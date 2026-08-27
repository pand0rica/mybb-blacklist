<?php
// ACP: Allgemein
$l['blacklist_acp_title']         = 'Blacklist';
$l['blacklist_acp_categories']    = 'Kategorien';
$l['blacklist_acp_add_category']  = 'Kategorie hinzufügen';
$l['blacklist_acp_edit_category'] = 'Kategorie bearbeiten';
$l['blacklist_acp_entries']       = 'Offene Einträge';
$l['blacklist_acp_run_task']      = 'Task-Status';

// ACP: Tab-Beschreibungen
$l['blacklist_tab_desc_categories']   = 'Alle Blacklist-Kategorien verwalten – Typ, Grenzwert und Rückmeldungsverhalten festlegen.';
$l['blacklist_tab_desc_add_category'] = 'Neue Blacklist-Kategorie anlegen und konfigurieren.';
$l['blacklist_tab_desc_entries']      = 'Alle aktuell offenen Blacklist-Einträge einsehen, manuell erledigen oder löschen.';
$l['blacklist_tab_desc_run_task']     = 'Den Blacklist-Task sofort manuell ausführen, ohne auf den geplanten Zeitpunkt zu warten.';

// Formular-Labels
$l['blacklist_lbl_name']               = 'Name';
$l['blacklist_lbl_type']               = 'Typ';
$l['blacklist_lbl_threshold']          = 'Grenzwert (Tage)';
$l['blacklist_lbl_response']           = 'Rückmeldungstyp';
$l['blacklist_lbl_groups']             = 'Betroffene Gruppen';
$l['blacklist_lbl_include_additional'] = 'Zusatzgruppen einbeziehen';
$l['blacklist_lbl_away_exempt']        = 'Abwesend schützt';
$l['blacklist_lbl_active']             = 'Aktiv';
$l['blacklist_lbl_sort_order']         = 'Sortierreihenfolge';
$l['blacklist_lbl_frozen_profile_field'] = 'Profilfeld (Auf-Eis-bis-Datum)';

// Beschreibungen
$l['blacklist_desc_name']               = 'Angezeigter Name dieser Kategorie.';
$l['blacklist_desc_type']               = 'Was diese Kategorie auslöst.';
$l['blacklist_desc_threshold']          = 'Anzahl Tage Inaktivität, bevor ein Eintrag erstellt wird.';
$l['blacklist_desc_response']           = 'Wie Nutzer sich selbst aus dieser Kategorie entfernen können.';
$l['blacklist_desc_groups']             = 'Nur Nutzer in diesen Gruppen werden geprüft. Leer = alle Nutzer.';
$l['blacklist_desc_include_additional'] = 'Auch Nutzer prüfen, deren Zusatzgruppen die gewählten Gruppen enthalten.';
$l['blacklist_desc_away_exempt']        = 'Wenn aktiviert, werden Nutzer mit gesetztem Abwesend-Status nicht in diese Kategorie eingetragen.';
$l['blacklist_desc_sort_order']         = 'Kleinere Zahlen erscheinen zuerst.';
$l['blacklist_desc_frozen_profile_field'] = 'Profilfeld auswählen, das das „Auf Eis bis"-Datum enthält (muss per strtotime lesbar sein, z. B. JJJJ-MM-TT).';

// Typen
$l['blacklist_type_scene_wait']    = 'Szenen-Wartezeit (kein Post in aktiver Szene)';
$l['blacklist_type_registration']  = 'Registrierung (inaktiv seit Registrierungsdatum)';
$l['blacklist_type_login']         = 'Login (inaktiv seit letztem Login)';
$l['blacklist_type_posting']       = 'Posting (keine globale Posting-Aktivität)';
$l['blacklist_type_group']         = 'Gruppenzugehörigkeit';
$l['blacklist_type_no_subaccount'] = 'Kein Unteraccount (seit X+ Tagen registriert, kein verknüpfter Unteraccount)';
$l['blacklist_type_no_active_scene'] = 'Keine aktuelle Szene (keine legitime aktive Szene mit mind. 2 Teilnehmern)';
$l['blacklist_type_frozen']        = 'Auf Eis (Profilfeld-Datum abgelaufen – noch in der Gruppe „Auf Eis")';

// Rückmeldungstypen
$l['blacklist_resp_none']    = 'Keine (Team muss entfernen)';
$l['blacklist_resp_button']  = 'Button („Ich bleibe aktiv")';
$l['blacklist_resp_posting'] = 'Post in Szene (Postingpflicht)';

// Tabellenspalten
$l['blacklist_col_id']       = 'ID';
$l['blacklist_col_sort']     = 'Sortierung';
$l['blacklist_col_name']     = 'Name';
$l['blacklist_col_type']     = 'Typ';
$l['blacklist_col_days']     = 'Tage';
$l['blacklist_col_resp']     = 'Rückmeldung';
$l['blacklist_col_active']   = 'Aktiv';
$l['blacklist_col_actions']  = 'Aktionen';
$l['blacklist_col_user']     = 'Benutzer';
$l['blacklist_col_category'] = 'Kategorie';
$l['blacklist_col_scene']    = 'Thread/Szene';
$l['blacklist_col_added']    = 'Hinzugefügt';
$l['blacklist_col_deadline'] = 'Frist';
$l['blacklist_col_status']   = 'Status';

// Status-Labels
$l['blacklist_active']   = 'Aktiv';
$l['blacklist_inactive'] = 'Inaktiv';
$l['blacklist_user_deleted'] = '(Account gelöscht)';

// Aktionen
$l['blacklist_edit']       = 'Bearbeiten';
$l['blacklist_delete']     = 'Löschen';
$l['blacklist_resolve']    = 'Erledigen';
$l['blacklist_activate']   = 'Aktivieren';
$l['blacklist_deactivate'] = 'Deaktivieren';
$l['blacklist_options']    = 'Optionen';
$l['blacklist_save']       = 'Speichern';

// Meldungen
$l['blacklist_category_saved']   = 'Kategorie gespeichert.';
$l['blacklist_category_updated'] = 'Kategorie aktualisiert.';
$l['blacklist_category_deleted'] = 'Kategorie gelöscht. Alle zugehörigen Einträge wurden entfernt.';
$l['blacklist_resolved_success'] = 'Eintrag als erledigt markiert.';
$l['blacklist_entry_deleted']    = 'Eintrag gelöscht.';
$l['blacklist_not_found']        = 'Kategorie nicht gefunden.';
$l['blacklist_no_categories']    = 'Noch keine Kategorien angelegt.';
$l['blacklist_no_entries_acp']   = 'Keine offenen Einträge.';
$l['blacklist_confirm_delete']   = 'Wirklich löschen?';
$l['blacklist_invalid_key']      = 'Ungültiger Post-Key. Bitte erneut versuchen.';
$l['blacklist_task_ran']         = 'Task abgeschlossen. Erstellt: %d Einträge, übersprungen: %d.';
$l['blacklist_task_synced']      = 'Task-Zeitplan aus den Einstellungen synchronisiert.';
$l['blacklist_task_error']       = 'Task-Fehler:';

// Task-Diagnoseseite
$l['blacklist_debug_task_status'] = 'Task-Status';
$l['blacklist_debug_field']       = 'Feld';
$l['blacklist_debug_db']          = 'In DB (tasks-Tabelle)';
$l['blacklist_debug_setting']     = 'Aus den Einstellungen';
$l['blacklist_debug_enabled']     = 'Aktiviert';
$l['blacklist_debug_hour']        = 'Stunde';
$l['blacklist_debug_minute']      = 'Minute';
$l['blacklist_debug_day']         = 'Tag des Monats';
$l['blacklist_debug_nextrun']     = 'Nächste Ausführung';
$l['blacklist_debug_lastrun']     = 'Letzte Ausführung';
$l['blacklist_debug_servertime']  = 'Serverzeit jetzt';
$l['blacklist_debug_yes']         = 'Ja';
$l['blacklist_debug_no']          = 'Nein';
$l['blacklist_debug_no_task']     = 'Task nicht in der Datenbank gefunden. Plugin deaktivieren und erneut aktivieren.';
$l['blacklist_debug_no_logs']     = 'Noch keine Log-Einträge.';
$l['blacklist_debug_log_title']   = 'Letzte 10 Task-Log-Einträge';
$l['blacklist_debug_log_time']    = 'Zeitpunkt';
$l['blacklist_debug_log_message'] = 'Meldung';
$l['blacklist_btn_sync_task']     = 'Task-Zeitplan aus Einstellungen synchronisieren';
$l['blacklist_btn_run_now']       = 'Task jetzt ausführen (erzwungen)';
$l['blacklist_debug_unit_sec']    = 'Sek.';
$l['blacklist_debug_unit_min']    = 'Min.';
$l['blacklist_debug_unit_hrs']    = 'Std.';
$l['blacklist_debug_unit_days']   = 'Tage';
$l['blacklist_debug_nextrun_in']      = 'in %s';
$l['blacklist_debug_nextrun_overdue'] = 'vor %s – wartet auf Seitenaufruf';
$l['blacklist_debug_not_synced']      = 'nicht synchron!';
$l['blacklist_debug_daily']           = '* (täglich)';

// Validierungsfehler
$l['blacklist_err_name_required'] = 'Bitte einen Kategorienamen eingeben.';
$l['blacklist_err_invalid_type']  = 'Ungültiger Kategorietyp gewählt.';
$l['blacklist_err_threshold']           = 'Grenzwert muss eine Zahl >= 1 sein.';
$l['blacklist_err_frozen_field_required'] = 'Bitte ein Profilfeld für das Auf-Eis-bis-Datum auswählen.';

// Thread-Benachrichtigung beim Start eines neuen Runs (%1$s = Frist-Datum, %2$s = Blacklist-URL).
// Dupliziert aus der Frontend-Sprachdatei, damit der ACP-Button "Jetzt ausführen" (der nur die
// Admin-Sprachdatei lädt) ebenfalls eine übersetzte Benachrichtigung postet.
$l['blacklist_notify_message'] = "Eine neue Blacklist wurde veröffentlicht.\n\nRückmeldefrist: [b]%1\$s[/b]\n\n[url=%2\$s]Zur aktuellen Blacklist[/url]";
