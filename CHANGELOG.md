## v0.14.11 - Import-Datumskompatibilität

- Behebt MySQL/MariaDB-Fehler `Incorrect DATE value: ''` beim erneuten Import des Zeltlager Managers 2025.
- Die Importlogik vergleicht DATE-Spalten nicht mehr mit leeren Strings.
- Geburtstage werden weiterhin nur übernommen, wenn sie plausibel sind.

## v0.14.10 - 2026-06-29

### Behoben
- Migration `2026_06_25_000026_import_birthdate_rank_dominance_fix.php` schreibt jetzt in `app_versions.applied_at` statt in die nicht vorhandene Spalte `installed_at`.
- Der Fehler `Unknown column installed_at in field list` beim Ausführen der Migration ist damit behoben.

### Hinweise
- Keine neue Datenbankstruktur. Es wurde nur die fehlgeschlagene Migration korrigiert.

# Changelog

## v0.14.9

- Importlogik für Geburtsdaten korrigiert.
- Unplausible 1900-Geburtsdaten werden bereinigt.
- Personenanzeige und Migration nutzen den höchsten bekannten Rang statt eines niedrigeren aktiven Status.


## v0.14.8 - Import-Dubletten bei Personen

- Import erkennt bestehende Personen robuster über normalisierte Namen und Geburtsdatum.
- Dubletten aus früheren Importläufen werden sicher zusammengeführt.
- Lagerstatus, Rollen, Punkte, Prüfungen, Kontakte und Zuordnungen werden auf die Zielperson übertragen.
- Dubletten werden deaktiviert und nicht hart gelöscht.

# Changelog

## v0.14.7 - Ranghistorie und Importlogik korrigiert

- Anmeldehistorie nutzt jetzt den letzten bekannten Rang bis zum Quelljahr statt nur eine Zieljahresspalte.
- Rückstufungen werden beim Import und in der Personenverwaltung verhindert.
- Migration 000024 schützt vorhandene Lagerstatusdaten gegen technische Rückstufungen.


## v0.14.5 - 2026-06-29

### Behoben

- Personensuche nutzt jetzt eindeutige PDO-Platzhalter. Dadurch wird `SQLSTATE[HY093]: Invalid parameter number` bei Suchbegriffen wie „tobia“ verhindert.
- Teilnehmer-Ränge werden in Personenliste und Detailansicht zusätzlich aus dem letzten bekannten Lagerstatus herangezogen, wenn im aktiven Lagerjahr noch kein Rang gesetzt ist.
- Der Import „Zeltlager Manager 2025“ filtert die Anmeldungen jetzt sauber auf das Quelljahr und übernimmt zusätzlich Rang-/Beinamen-Daten aus der Anmeldehistorie.
- Neue Personen aus dem Import speichern den Beinamen jetzt direkt im Feld `nickname`.

### Datenbank

- Neue Migration `2026_06_25_000023_person_search_and_rank_backfill.php`.
- Die Migration füllt fehlende Rangangaben aus dem letzten bekannten Rang nach und verhindert technische Rückstufungen in bestehenden Lagerstatusdaten.

### Hinweise

- Nach dem Update Migration ausführen und danach den Zeltlager-Manager-Import 2025 erneut starten, damit fehlende Rang- und Beinameninformationen nachgezogen werden.

## v0.14.4 - 2026-06-29

### Behoben

- Freie Ordensfarben werden jetzt auch im Dashboard-Bereich „Aktive Einheiten“ verwendet.
- Die kleinen Ordens-Badges nutzen nun `color_hex` als Hintergrundfarbe und berechnen eine lesbare Textfarbe.
- CSS-Regel für `order-mini` korrigiert, damit die freie Farbe nicht nur als Textfarbe, sondern sichtbar als Badge-Farbe erscheint.

### Datenbank

- Keine Datenbankänderung.

### Hinweise

- Nach dem Upload Browsercache beziehungsweise Service Worker leeren, falls noch alte Farben sichtbar sind.


## v0.14.3 - Ordensfarben konsequent anwenden

- Freie Ordensfarben aus dem Colorpicker werden jetzt konsequent in Orden/Zelte-Karten, Badges, Mini-Badges, Teilnehmerlisten, Geburtstagsanzeige, Punkteansicht und Auswertung verwendet.
- Die Kartenleiste der Orden/Zelte nutzt jetzt `color_hex` statt nur die alte `color_key`-Fallbackfarbe.
- Mehrere Datenabfragen liefern `color_hex` nun mit, damit die Farbe nicht nur in der Bearbeitungsmaske gespeichert, sondern auch überall angezeigt wird.
- Keine Datenbankänderung.

# Changelog

## v0.14.7 - Ranghistorie und Importlogik korrigiert

- Anmeldehistorie nutzt jetzt den letzten bekannten Rang bis zum Quelljahr statt nur eine Zieljahresspalte.
- Rückstufungen werden beim Import und in der Personenverwaltung verhindert.
- Migration 000024 schützt vorhandene Lagerstatusdaten gegen technische Rückstufungen.


## v0.14.2 - 2026-06-29

### Neu

- Rangwechsel für das Folgejahr wurden fachlich präzisiert: Knappe → Ritter, Ritter → Freiherr, Freiherr → Graf, Graf → Markgraf, Markgraf → Landgraf, Landgraf → Fürst, Fürst → Herzog, Herzog → Großherzog.
- Die Punkteschwellen wurden gemäß Rangordnung angepasst: 310, 320, 330, 340, 345, 350 und 280 Punkte.
- Ränge sind dauerhaft: Ein erreichter Rang kann im Folgejahr nicht verloren oder zurückgestuft werden. Das gilt auch für Herzöge, Großherzöge und Mitarbeiter.
- Rangstufen erhalten optional einen Wechseltext, zum Beispiel „Von Knappe zum Ritter“.
- Lerneinheiten können einer Rangstufe zugeordnet und mit einer Lerneinheit-ID gespeichert werden.
- Neue persönliche Bonuswertung „Bonus Freizeit“ mit max. 5 Punkten je Mitarbeiter, Teilnehmer, Tag und Freizeit-Slot.

### Geändert

- Küchendienstpunkte werden jetzt global pro Orden/Zelt erfasst, nicht mehr persönlich je Kind.
- Die Punktescopes wurden nachgezogen: Ordnung Zelt, Spiele, Platzdienst und Küchendienst sind Ordens-/Zeltwertungen. Ordnung persönlich, Geschirr, Prüfung und Bonus sind persönliche Wertungen.
- Die Rangverwaltung zeigt den Wechseltext und den Hinweis, dass erreichte Ränge erhalten bleiben.

### Datenbank

- Neue Migration `2026_06_25_000022_rank_progression_points_scope.php`.
- Neue Spalten `rank_levels.promotion_text`, `rank_levels.is_permanent`, `learning_units.unit_code` und `learning_units.rank_level_id`.
- Neue Kategorie `bonus_freizeit`.
- Neue Kategorie `kuechendienst` als globale Ordens-/Zeltwertung.
- Alte Zusatz-Küchendienst-Kategorien werden deaktiviert.

### Hinweise

- Nach dem Update Migration ausführen und danach neu anmelden.
- `public/migration.php` nach erfolgreicher Nutzung wieder löschen.

# CHANGELOG

## v0.14.1 - 2026-06-29

### Neu

- Feste Ritterlager-Rangfolge ergänzt: Knappe, Ritter, Freiherr, Graf, Markgraf, Landgraf, Fürst, Herzog, Großherzog.
- Beinamen werden als eigenes Personenfeld `nickname` geführt.
- Prüfungsergebnisse und Auswertung zeigen Beinamen und den Folgerang für das nächste Jahr.
- Rangstufen können Punkteschwellen und den nächsten Rang-Schlüssel speichern.

### Geändert

- Import aus `Zeltlager 2025 Manager.xlsx` übernimmt Beinamen und normalisiert Rangtexte wie `1 Knappe` auf die festen Rangstufen.
- Neue und bestehende Lagerjahre erhalten die Standardränge.

### Datenbank

- Neue Migration `2026_06_25_000021_ranks_bynames_progression.php`.

### Hinweise

- Nach dem Update `migration.php` ausführen.
- Danach den 2025-Import erneut starten, wenn Beinamen und Rangzuordnung aus der Excel-Datei nachgetragen werden sollen.

## v0.13.11 - 2026-06-29

### Behoben

- Migration `2026_06_25_000019_limit_all_day_modules_to_camp_days.php` wieder in das erwartete Migrationsformat gebracht.
- Versionseintrag der Migration an das bestehende `app_versions` Schema angepasst.

### Datenbank

- Keine neue Migration. Die bereits vorhandene Migration `000019` wurde korrigiert.

## v0.13.10 - 2026-06-29

### Behoben

- Programm, Essen, Dienste und Ordnung werden nun gemeinsam auf die Tage des aktiven Lagerjahres begrenzt.
- Einträge außerhalb von `starts_on` bis `ends_on` werden nicht mehr angezeigt und bei neuen Eingaben serverseitig blockiert.
- Die Ordnung-Tagesauswahl setzt jetzt das Bewertungsdatum korrekt über `tag=YYYY-MM-DD`.

### Datenbank

- Neue Migration `2026_06_25_000019_limit_all_day_modules_to_camp_days.php` deaktiviert vorhandene Programm-, Essen- und Dienst-Einträge außerhalb des Lagerzeitraums und storniert Ordnungseinträge außerhalb des Lagerzeitraums.


## v0.13.9 - 2026-06-29

### Behoben

- Speiseplan-Tage werden jetzt strikt aus dem Start- und Enddatum des aktiven Lagerjahres gebildet.
- Mahlzeiten außerhalb des Lagerzeitraums werden nicht mehr neu angelegt und per Migration deaktiviert.
- Manuelle Speiseplan-Einträge außerhalb der Lagertage werden serverseitig blockiert.
- Dateiimporte mit Profil `Speiseplan` überspringen Mahlzeiten außerhalb des Lagerzeitraums.

### Datenbank

- Neue Migration `2026_06_25_000018_limit_meals_to_camp_days.php` deaktiviert vorhandene Mahlzeiten außerhalb des zugehörigen Lagerzeitraums.

### Hinweise

- Nach dem Update `migration.php` ausführen.
- Danach den Speiseplan erneut prüfen. Die sichtbaren Tage entsprechen dann nur noch den Tagen des aktiven Lagerjahres.

## v0.13.8 - 2026-06-29

### Behoben

- DOCX-Import liest Tabellenzellen jetzt sauber und importiert keine Word-XML-Fragmente mehr als Diensttitel.
- Programmimport setzt „Danach: Mittagspause mit Kiosk, Schnitzen, Brennpeter und Specials“ jetzt auf 13:00 Uhr statt auf 09:00 Uhr.
- Speiseplanimport nutzt jetzt das Lagerjahr 2026 und bereinigt vorher alte Speiseplan-Importeinträge, damit keine Mehrfacheinträge entstehen.
- Day-Tabs erzeugen jetzt automatisch klickbare Links mit `tag`-Parameter, wenn eine Ansicht keine eigenen Links liefert.
- Bestehende fehlerhafte Import-Dienste aus „Aufgabenverteilung 2026“ werden per Migration deaktiviert.

### Geändert

- Aufgabenverteilung 2026 wird nur noch als Dienstarten-Grundlage importiert. Tägliche Dienste werden nur aus Platzdienst/Nachtwache im Programm erzeugt.
- Dienstkarten wurden kompakter gestaltet.

### Datenbank

- Neue Migration `2026_06_25_000017_import_cleanup_program_meals_duties.php` bereinigt fehlerhafte Importdaten und setzt die Mittagspause korrekt.

### Hinweise

- Nach dem Update `migration.php` ausführen und danach die Importe für Programm, Essen und Dienste erneut starten.
- Bereits manuell angelegte Dienste oder Mahlzeiten werden nicht verändert, außer sie stammen eindeutig aus den alten Importquellen.

## v0.13.7 - 2026-06-26

### Behoben

- Gebündelte Importvorlagen finden Quelldateien jetzt robuster.
- `BundledImportService` sucht neben `storage/import_sources` auch in `storage/imports`, im Projektroot und in `public`.
- Dateinamen werden zusätzlich normalisiert verglichen, damit Leerzeichen, Bindestriche oder abweichende Schreibweisen weniger schnell zu einem Fehlabbruch führen.
- Fehlermeldungen nennen jetzt geprüfte Pfade und gefundene Dateien statt nur „Quelldatei fehlt im Paket“.

### Hinweise

- Die empfohlene Ablage bleibt `storage/import_sources/`.
- Dateien sollten nicht dauerhaft in `public/` liegen.


## v0.13.6 - 2026-06-26

### Behoben

- HY093-Fehler beim Import behoben, wenn PDO/MySQL doppelt verwendete benannte Platzhalter nicht akzeptiert.
- Orden/Zelt-Suche in ImportService und BundledImportService nutzt jetzt eindeutige Platzhalter für Name und Kürzel.

### Datenbank

- Keine neue Migration.

### Hinweise

- Nach dem Update ist keine DB-Migration zwingend erforderlich. Falls noch offene Migrationen existieren, kann migration.php weiterhin genutzt werden.


## v0.13.5 - 2026-06-26

### Behoben

- `public/migration.php` findet das App-Verzeichnis jetzt robuster, wenn das ZIP versehentlich in einen Unterordner entpackt wurde oder `public/` separat liegt.
- Statt Fatal Error wird eine verständliche Diagnose mit geprüften Pfaden angezeigt, wenn `app/Support/bootstrap.php` nicht gefunden wird.

### Hinweise

- Erwartete Serverstruktur bleibt: `app/`, `config/`, `database/`, `routes/`, `storage/` und `public/` liegen gemeinsam im Projektverzeichnis. Der Webroot zeigt auf `public/`.

## v0.13.4 - 2026-06-26

### Behoben

- `public/migration.php` nutzt nicht mehr die zusätzliche `is_readable()`/`open_basedir`-Vorprüfung vor dem Bootstrap.
- Die Migration lädt den Bootstrap jetzt wie `setup.php` direkt. Dadurch wird der falsche Hinweis „Die Anwendung kann Dateien außerhalb von public/ nicht laden“ vermieden, wenn das Setup auf demselben Hosting bereits funktioniert.

### Datenbank

- Keine neue Migration.

### Hinweise

- `public/migration.php` bleibt ein temporäres Wartungswerkzeug und muss nach erfolgreicher Nutzung gelöscht werden.

## v0.13.3 - 2026-06-26

### Geändert

- Ordnungspunkte von reinem Punktabzug auf positive Bewertungsarten umgebaut.
- Neue Tageswertung: Ordnung persönlich, Sauberkeit Geschirr, Zelt, Disziplin und Pünktlichkeit, Ordnung Prüfung.
- Neue einmalige Lagerwertungen: Platzdienst, Prüfung Fach 1 bis 3, Zusatz Küchendienst 1 bis 3.
- Bewertung kann je nach Art für Teilnehmer oder Orden/Zelt erfasst werden.
- Tages- und Lagerlimits werden serverseitig geprüft.

### Datenbank

- Neue Migration `2026_06_25_000016_refactor_order_points_scoring.php`.
- `point_categories` um Bewertungsregeln erweitert.
- `point_entries` um Bewertungsdatum, Prüfabschnitt, Fach/Slot und Maximalpunkte erweitert.

### Hinweise

- Bestehende alte Ordnungseinträge bleiben erhalten. Die alte Kategorie `ordnung` wird deaktiviert und nicht mehr für neue Eingaben angeboten.

## v0.13.1 - 2026-06-26

### Neu

- `public/migration.php` ergänzt, damit offene Migrationen per Browser ausgeführt werden können.

### Security

- Die Datei ist bewusst ohne Login/Token, aber nur als temporäres Wartungswerkzeug gedacht. Nach erfolgreicher Migration muss sie gelöscht werden.

### Datenbank

- Keine neue Migration.


## v0.13.0 - 2026-06-26

### Neu

- Google Material Symbols für Menüpunkte und Diensticons ergänzt.
- Sechs feste Standardorden je Lagerjahr ergänzt.
- Importvorlagen für Zeltlager Manager 2025, Dummy-Lagerjahr 2000, Programm 2026, Speiseplan 2026 und Dienste/Aufgabenverteilung 2026 ergänzt.

### Geändert

- Neue Lagerjahre erhalten automatisch die sechs Standardorden/Zelte.
- Mobile Navigation unterstützt fünf Einträge sauberer.

### Datenbank

- Neue Migration `2026_06_25_000014_seed_default_orders_and_icons.php`.

### Security

- Importvorlagen laufen nur mit `imports.manage`, CSRF und Audit-Log.
- Quelldateien liegen in `storage/import_sources/` außerhalb des Webroots.


## v0.12.4 - 2026-06-26

### Behoben

- Migration `2026_06_25_000012_create_imports` für MySQL/MariaDB korrigiert. Die Spalte `row_number` wurde in `source_row_number` umbenannt, weil `ROW_NUMBER` in MySQL/MariaDB als SQL-Funktion bzw. reservierter Ausdruck zu Syntaxfehlern führen kann.

### Geändert

- ImportService und Import-Fehleransicht verwenden jetzt `source_row_number`.

### Datenbank

- Keine neue Migration. Die bestehende Import-Migration wurde installationssicher korrigiert, weil sie bei der Erstinstallation noch nicht erfolgreich abgeschlossen war.

### Hinweise

- Wenn der Setup-Lauf bereits teilweise Tabellen angelegt hat, kann setup.php nach diesem Hotfix erneut ausgeführt werden. Vorhandene Tabellen werden durch `CREATE TABLE IF NOT EXISTS` nicht gelöscht.

## v0.12.2 - 2026-06-26

### Behoben

- `public/index.php` und `public/setup.php` prüfen jetzt vor dem Laden der Anwendung, ob `open_basedir` den Zugriff auf das Projektverzeichnis erlaubt.
- Statt eines rohen Fatal Errors wird bei falscher Hosting-Konfiguration eine verständliche Hinweisseite ausgegeben.

### Dokumentation

- Hinweise für Plesk/Netcup ergänzt: Der Webroot bleibt `public/`, aber `open_basedir` muss das Projektverzeichnis oberhalb von `public/` einschließen.

### Hinweise

- Diese Version ändert keine Fachlogik und keine Datenbankstruktur.
- Wenn `open_basedir` nur auf `public/` zeigt, muss die PHP-Einstellung im Hosting angepasst werden. Das lässt sich nicht sicher rein per PHP-Code beheben.


## v0.12.1 - 2026-06-26

### Neu

- Einmaliges Web-Setup `public/setup.php` ergänzt.
- Setup schreibt `config/database.php`, führt alle Migrationen aus und kann den ersten Admin mit PIN anlegen.
- Setup-Sperre über `storage/setup.lock` ergänzt.
- Dokumentation `docs/deployment/SETUP.md` ergänzt.

### Security

- Setup nutzt CSRF-Token, schreibt keine PIN im Klartext und loggt keine Datenbankpasswörter.
- Nach erfolgreichem Setup muss `public/setup.php` gelöscht werden.

### Datenbank

- Keine neue Migration.

### Hinweise

- Das Setup ist für die Erstinstallation oder kontrollierte Testinstallation gedacht. Für spätere Updates bleibt der CLI-Migrationsweg empfohlen.

## v0.12.0 - 2026-06-26

### Neu

- Betriebsbereich für Backups, geplante Aufgaben und Logs ergänzt.
- Manuelles Backup mit Datenbank-Dump und Dateisicherung in geschütztem Storage ergänzt.
- Geplante Aufgaben `backup_daily`, `backup_weekly` und `cleanup_logs` mit Lock und Laufprotokoll ergänzt.
- HTTP-Cron-Endpunkt mit festem Task-Key und gehashtem Token ergänzt.
- CLI-Cron-Script `scripts/cron/run_task.php` ergänzt.
- Backup-Download über Controller mit Rechteprüfung ergänzt.

### Geändert

- Systemnavigation um Backups, Aufgaben und Logs erweitert.
- Systemstatus zeigt zusätzlich Backup-/Import-Schreibrechte und verfügbare Archiv-Erweiterungen.

### Behoben

- `Csrf::verify()` als Alias ergänzt, damit bestehende Controller konsistent mit der CSRF-Prüfung arbeiten.

### Security

- Cron-Tokens werden nur gehasht gespeichert und nach Regeneration nur einmalig als vollständige URL angezeigt.
- Backup-Dateien liegen außerhalb des Webroots und werden nur per geschütztem Download ausgeliefert.
- Cron-Endpunkt führt nur feste Task-Keys aus, keine beliebigen Dateien.

### Datenbank

- Neue Migration `2026_06_25_000013_create_operations.php`.
- Neue Tabellen `backup_runs`, `scheduled_tasks`, `scheduled_task_runs`, `webdav_sync_runs`.
- Neue Rechte `backups.manage`, `backups.download`, `cron.manage`, `logs.view`.

### Hinweise

- WebDAV ist nur vorbereitet und noch nicht als echter Sync umgesetzt.
- Falls `ZipArchive` fehlt, wird als Archiv-Fallback `PharData` genutzt.

## v0.11.0 - 2026-06-25

### Neu

- Adminbereich `Importe` für kontrollierte XLSX-, ODS- und DOCX-Importe.
- Geschützter Upload nach `storage/imports`.
- Importvorschau, Importlauf-Protokoll und Fehlerprotokoll.
- Tabellen `uploaded_files`, `import_runs` und `import_run_errors`.
- Recht `imports.manage` für Admin und Lagerleitung.

### Geändert

- Sidebar um `Importe` erweitert.
- Service-Worker-Cache auf v0.11.0 aktualisiert.

### Security

- Importuploads prüfen Endung, MIME-Type, Dateigröße und CSRF.
- Importdateien werden randomisiert benannt und außerhalb des Webroots gespeichert.
- Vorschau und Ausführung werden im Audit-Log protokolliert.

### Datenbank

- Neue Migration `2026_06_25_000012_create_imports.php`.

### Hinweise

- PHP-ZipArchive wird für die inhaltliche Vorschau von XLSX, ODS und DOCX benötigt.
- Komplexe Excel-Formeln werden nicht übernommen und nicht ausgeführt.

## v0.10.0 - 2026-06-25

### Neu

- Auswertungsbereich für Rangordnung, Lerneinheiten und Prüfungsergebnisse ergänzt.
- Rangstufen können je aktivem Lagerjahr angelegt und bearbeitet werden.
- Lerneinheiten können je aktivem Lagerjahr angelegt, bearbeitet und deaktiviert werden.
- Prüfungsergebnisse können je Teilnehmer und Lerneinheit erfasst und aktualisiert werden.
- Zwischenstand je Orden/Zelt und je Teilnehmer ergänzt.
- CSV-Export der Auswertung ergänzt.
- Rangzuordnung je Teilnehmer vorbereitet.

### Geändert

- Navigation enthält für berechtigte Rollen den Adminpunkt „Auswertung“.
- Auswertungen verwenden Karten und mobile Tabellen mit horizontalem Scrollbereich statt breiter Tabellenwüste.

### Security

- Auswertung ist über `exams.view` geschützt.
- Pflege von Rangstufen, Lerneinheiten, Prüfungsergebnissen und Rangzuordnung ist über `exams.manage` oder `points.manage` geschützt.
- Alle Schreibaktionen nutzen CSRF.
- Änderungen an Rangstufen, Lerneinheiten, Prüfungsergebnissen und Rangzuordnungen werden im Audit-Log protokolliert.
- Auswertungen enthalten keine medizinischen Hinweise, Notfallkontakte oder internen Teilnehmernotizen.

### Datenbank

- Neue Migration `2026_06_25_000011_create_rank_learning_exam_results` ergänzt.
- Neue Tabellen: `rank_levels`, `learning_units`, `exam_results`.
- Tabelle `camp_person_statuses` wird um `rank_level_id` erweitert, falls die Spalte noch nicht existiert.
- Neue Rechte: `exams.view`, `exams.manage`.

### Hinweise

- Die Endwertung wird bewusst nicht automatisch freigegeben. Die Ansicht ist ein Zwischenstand und muss fachlich geprüft werden.
- Es gibt noch keinen Excel-Import für alte Prüfungs- oder Auswertungsdaten.
- Die bestehenden Excel-Formeln werden nicht blind nachgebaut.

## v0.8.0 - 2026-06-25

### Neu

- Personenverwaltung um vollständige Teilnehmerdaten erweitert.
- Lagerjahrbezogener Teilnehmer- und Mitarbeiterstatus ergänzt.
- Zuordnung von Personen zu Orden/Zelten im aktiven Lagerjahr ergänzt.
- Personendetailansicht mit Stammdaten, Lagerstatus, Essenshinweisen und geschütztem Bereich ergänzt.
- Filter für Personenliste ergänzt: Suche, Teilnehmer, Mitarbeiter, Orden/Zelt, Aktivstatus und Geburtstag im Lager.
- Geburtstage im Lagerzeitraum werden berechnet und in der Personenliste sichtbar gemacht.
- Notfallkontakte und weitere Kontaktperson vorbereitet.

### Geändert

- Dashboard-Zählung für Teilnehmer und Mitarbeiter nutzt bei aktivem Lagerjahr den lagerjahrbezogenen Status.
- Geburtstagsbereich der Übersicht verlinkt auf Personendetails.
- Personenformular ist jetzt in Kartenbereiche für Stammdaten, Lagerstatus, Kontakt, Hinweise und Login gegliedert.

### Security

- Sensible Felder wie Notfallkontakt, Allergien, medizinische Hinweise und interne Bemerkungen sind in der Detailansicht nur mit `persons.sensitive.view` oder `persons.manage` sichtbar.
- Änderungen an sensiblen Personendaten werden im Audit-Log als geschützte Änderung protokolliert, ohne Klartextinhalte zu speichern.
- Schreibaktionen der erweiterten Personenverwaltung verwenden weiter CSRF.
- Personenansicht und Personendetails bleiben über `persons.view` geschützt.

### Datenbank

- Neue Migration `2026_06_25_000009_extend_persons_camp_statuses` ergänzt.
- Tabelle `persons` um Kontakt-, Adress-, Essens-, Allergie-, medizinische und interne Hinweise erweitert.
- Neue Tabellen: `camp_person_statuses`, `person_guardians`.
- Neues Recht: `persons.sensitive.view` für Admin und Lagerleitung.

### Hinweise

- Es gibt noch keine komplexe medizinische Rollenverwaltung. Die Freigabe läuft zunächst über `persons.sensitive.view`.
- Es gibt noch keinen Excel-Import. Vollständige Personendaten werden manuell gepflegt.
- Elternkommunikation und öffentliche Formulare sind nicht enthalten.

## v0.7.0 - 2026-06-25

### Neu

- Dienstmodul mit Tagesansicht, Day-Tabs und mobilen Dienstkarten ergänzt.
- Dienstartenverwaltung für Küchendienst, Spüldienst, Platzdienst, Nachtwache und weitere Aufgaben ergänzt.
- Zuweisungen an Personen, Orden/Zelte und Freitext-Teams ergänzt.
- Platzdienst-Vorschlag nach zuletzt verwendetem Orden/Zelt und Sortierung der Orden/Zelte ergänzt.
- Statuswechsel für Dienste ergänzt. Zugewiesene Mitarbeiter können einen Dienst als erledigt markieren.
- Übersicht zeigt offene Dienste des aktuellen Lagertags.

### Security

- Dienstverwaltung über `duties.manage` geschützt.
- Dienstanzeige über `duties.view` geschützt.
- Schreibaktionen verwenden CSRF.
- Dienständerungen, Zuweisungen und Statuswechsel werden im Audit-Log protokolliert.

### Datenbank

- Migration `2026_06_25_000008_create_duties.php` ergänzt.
- Neue Tabellen: `duty_types`, `duties`, `duty_assignments`, `duty_rotation_rules`.

### Hinweise

- Kein DOCX-Import der Aufgabenverteilung in dieser Phase.
- Keine automatische komplexe Personalplanung.
- Platzdienst-Rotation ist als Vorschlag umgesetzt und überschreibt keine bestehenden Dienste.

## v0.6.0 - 2026-06-25

### Neu

- Essen-Modul mit Tagesansicht für Frühstück, Mittagessen und Abendessen ergänzt.
- Mahlzeiten können angelegt, bearbeitet und deaktiviert werden.
- Mahlzeiten speichern Uhrzeit, Gericht, Portionen gesamt, vegetarische Portionen, Allergiehinweise, Küchenteam und Notizen.
- Zutaten können als Vorbereitung für eine spätere Einkaufsliste gespeichert werden.
- Übersicht zeigt „Essen heute“ mit den vorhandenen Mahlzeiten des aktuellen Lagertags.
- Navigation aktiviert den Bereich „Essen“ in Sidebar und mobiler Bottom-Navigation.

### Geändert

- Dashboard-Empty-State für Essen wurde durch echte Speiseplandaten ersetzt.
- Speiseplanansicht nutzt die vorhandenen Day-Tabs und das Kartenlayout des Ritterlager-Designsystems.

### Security

- Speiseplananzeige ist über `meals.view` geschützt.
- Speiseplanbearbeitung ist über `meals.manage` geschützt.
- Alle Essen-Schreibaktionen nutzen CSRF-Prüfung.
- Anlage, Änderung und Deaktivierung von Mahlzeiten werden im Audit-Log protokolliert.
- Eingaben werden validiert und in Views escaped ausgegeben.

### Datenbank

- Neue Migration `2026_06_25_000007_create_meal_items` ergänzt.
- Neue Tabellen: `meal_items`, `meal_ingredients`.
- Rechte `meals.view` und `meals.manage` werden für Rollen abgesichert.

### Hinweise

- ODS-Import ist bewusst noch nicht enthalten. Mahlzeiten werden in dieser Phase manuell gepflegt.
- Die Einkaufsliste ist nur vorbereitet, aber noch nicht vollständig automatisiert.
- Allergiehinweise werden als allgemeine Küchenhinweise gespeichert. Feiner berechtigte Teilnehmer-Allergiedaten folgen später mit der vollständigen Teilnehmerdatenphase.

## v0.5.0 - 2026-06-25

### Neu

- Programmmodul mit Tagesansicht und Timeline ergänzt.
- Programmpunkte können angelegt, bearbeitet und deaktiviert werden.
- Day-Tabs verlinken jetzt auf die Programmansicht je Lagertag.
- Kategorien für Info, Freizeit, Spiel, Bibelarbeit, Mahlzeit, Wache, Nachtruhe, Lernen und Wettbewerb ergänzt.
- Betroffene Orden/Zelte können einem Programmpunkt zugeordnet werden.
- Übersicht zeigt den nächsten Programmpunkt des aktuellen Lagertags, sobald Programmdaten vorhanden sind.

### Geändert

- Navigation aktiviert den Bereich „Programm“ in Sidebar und mobiler Bottom-Navigation.
- Dashboard-Empty-State für Programm wurde durch echte Programmdaten ersetzt.

### Security

- Programmanzeige ist über `program.view` geschützt.
- Programmbearbeitung ist über `program.manage` geschützt.
- Alle Programm-Schreibaktionen nutzen CSRF-Prüfung.
- Anlage, Änderung und Deaktivierung von Programmpunkten werden im Audit-Log protokolliert.
- Eingaben werden validiert und in Views escaped ausgegeben.

### Datenbank

- Neue Migration `2026_06_25_000006_create_program_items` ergänzt.
- Neue Tabellen: `program_items`, `program_item_orders`.
- Bestehende Rechte `program.view` und `program.manage` werden für Rollen abgesichert.

### Hinweise

- Es gibt noch keinen DOCX-Import. Programmpunkte werden in dieser Phase manuell gepflegt.
- Speiseplan, Dienstplanung und Punktewertung bleiben unverändert und folgen in späteren Phasen.

## v0.4.0 - 2026-06-25

### Neu

- Lagerjahre mit Startdatum, Enddatum, Ort und aktivem Lagerjahr ergänzt.
- Orden/Zelte als eine fachliche Einheit ergänzt. Es gibt keine separate Zelte-Haupttabelle.
- Verwaltungsbereiche für Lagerjahre und Orden/Zelte ergänzt.
- Leiter und Helfer je Orden/Zelt können aus der Personenliste zugewiesen werden.
- Lagertage werden aus Start- und Enddatum berechnet und als Day-Tabs auf der Übersicht vorbereitet.
- Übersicht zeigt aktives Lagerjahr, Tageschip, Personenkennzahlen, Orden/Zelte, Geburtstage heute und saubere Empty-States für Programm, Essen und Dienste.

### Geändert

- Navigation wurde um Lagerjahre und Orden/Zelte im Verwaltungsbereich erweitert.
- Dashboard wurde von einem statischen Layout auf echte Grunddaten aus Lagerjahr, Personen und Orden/Zelten umgestellt.

### Security

- Lagerjahrverwaltung ist über `camp_years.manage` geschützt.
- Orden/Zelte-Verwaltung ist über `orders.manage` geschützt.
- Schreibaktionen nutzen CSRF-Prüfung.
- Änderungen an Lagerjahren und Orden/Zelten werden im Audit-Log protokolliert.

### Datenbank

- Neue Migration `2026_06_25_000005_create_camp_years_orders` ergänzt.
- Neue Tabellen: `camp_years`, `orders`, `order_staff_assignments`.
- Neue Rechte: `camp_years.view`, `camp_years.manage`, `orders.view`, `orders.manage`.

### Hinweise

- Teilnehmerzuordnung zu Orden/Zelten folgt später mit der vollständigen Teilnehmerdatenphase.
- Programm, Essen und Dienste werden nur als vorbereitete Empty-States angezeigt.

## v0.3.0 - 2026-06-25

### Neu

- Ritterlager-Designsystem mit verbindlichen Farb-Tokens, Typografie, Karten, Chips, Day-Tabs, Hero-Box und Timeline-Grundlayout umgesetzt.
- Desktop-App-Shell mit fester Sidebar und Topbar ergänzt.
- Mobile Bottom-Navigation für Übersicht, Programm, Essen und Dienste ergänzt.
- Loginseite auf das neue Ritterlager-Design umgestellt.
- PWA-Manifest, App-Icons, Service Worker und Offline-Hinweisseite ergänzt.
- Wiederverwendbare View-Partials für Badge, Sidebar, Topbar, Mobile Navigation und Day-Tabs ergänzt.

### Geändert

- PIN-Touchpad-JavaScript aus der Login-View in `public/assets/js/app.js` ausgelagert.
- Übersicht zeigt nun das künftige Layout für Tageskennzahlen, Day-Tabs, Hero-Box und Timeline als fachlich leeren Zustand.
- Navigation bleibt rollenabhängig und serverseitig geschützt. Noch nicht umgesetzte Fachbereiche sind sichtbar vorbereitet, aber deaktiviert.

### Security

- Content-Security-Policy ergänzt und auf Self-Assets sowie Google Fonts eingeschränkt.
- Inline-JavaScript aus der Loginseite entfernt, damit `script-src 'self'` möglich ist.
- XSS-sichere Ausgabe in den angepassten Views beibehalten.

### Datenbank

- Keine Datenbankänderungen.

### Hinweise

- Die PWA ist bewusst onlinepflichtig. Der Service Worker cached nur Shell-Assets und zeigt bei fehlender Verbindung eine Hinweisseite.
- Programm, Essen, Dienste, Punkte und Importe sind weiterhin nicht fachlich umgesetzt.

## v0.2.0 - 2026-06-25

### Neu

- Einheitliches Personen-, Benutzer- und Rollenmodell ergänzt.
- PIN-Login per Personenauswahl und 4 bis 6 stelligem Touchpad-Code umgesetzt.
- Adminzugang ist kein eigener Login mehr, sondern eine Rolle mit zusätzlichen Rechten und Menüpunkten.
- Personenverwaltung mit Anlage, Bearbeitung, Aktivstatus, Loginstatus, Rollen und PIN-Änderung ergänzt.
- Serverseitige Rechteprüfung für geschützte Routen ergänzt.
- CLI-Script `scripts/maintenance/create_user.php` für den ersten Adminbenutzer ergänzt.

### Security

- PINs werden ausschließlich mit `password_hash` gespeichert und mit `password_verify` geprüft.
- Session-ID wird nach erfolgreichem Login rotiert.
- Login-Fehlversuche werden gezählt und nach mehreren Fehlversuchen temporär gesperrt.
- Login, Logout, fehlgeschlagene Logins, Rollenänderungen und PIN-Änderungen werden im Audit-Log protokolliert.
- CSRF-Prüfung für Login, Logout, Personenänderungen, Rollenänderungen, Loginstatus und PIN-Änderungen ergänzt.
- PINs werden nicht geloggt und nicht im Klartext gespeichert.

### Datenbank

- Neue Migration `2026_06_25_000004_create_persons_users_roles` ergänzt.
- Neue Tabellen: `persons`, `users`, `roles`, `role_permissions`, `person_roles`, `login_attempts`.
- Systemrollen und Basisberechtigungen werden per Migration angelegt.

### Hinweise

- Es gibt weiterhin keine Mandantenfähigkeit. Das Projekt arbeitet später mit Lagerjahren.
- Es gibt noch keine vollständige Teilnehmerdetailmaske. Diese folgt in einer späteren Phase.
- Programm, Essen, Dienste, Punkte und Importe sind noch nicht umgesetzt.
- Der erste Admin muss nach Migrationen per CLI angelegt werden.


## v0.1.0 - 2026-06-25

### Neu

- Neues PHP-8.3/PDO-Projektgerüst für den Ritterlager Manager angelegt.
- `public/` als einziger Webroot vorbereitet.
- Geschützte Ordnerstruktur für `storage/`, `config/`, `database/`, `scripts/` und `docs/` angelegt.
- Minimaler Router, Controller-Struktur, View-Rendering und zentrale Fehlerseiten ergänzt.
- Zentrale Security-Header, Session-Grundlage, CSRF-Helper und Logger vorbereitet.
- Migration-Runner und initiale Migrationen für `app_versions`, `settings`, `audit_log` und `schema_migrations` erstellt.
- Deployment-, Cron-, Restore- und Security-Dokumentation angelegt.

### Security

- Security-Header zentral vorbereitet.
- Session-Cookie mit HttpOnly, SameSite=Lax und Secure-Erkennung vorbereitet.
- Direkter Zugriff auf geschützte Ordner per `.htaccess` blockiert.
- Technische Fehler werden ins zentrale App-Log geschrieben und nicht roh im Browser ausgegeben.

### Datenbank

- Migrationen für technische Basistabellen ergänzt.
- Migrationen sind wiederholbar und werden über `schema_migrations` protokolliert.

### Hinweise

- Es gibt in v0.1.0 noch keinen produktiven Login und keine Fachmodule.
- Die echte Authentifizierung per Dropdown und PIN folgt in v0.2.
- Mandantenfähigkeit wurde bewusst nicht eingebaut. Das Projekt nutzt später Lagerjahre statt Mandanten.

## v0.9.0 - 2026-06-25

### Neu

- Mobiler Ordnungspunkte-Flow unter `/ordnung`.
- Adminliste für Ordnungspunkte unter `/admin/ordnungspunkte`.
- Korrektur- und Storno-Funktion für Admins und Lagerleitung.
- Neue Dashboard-Kennzahl „Ordnung heute“.
- Navigationseintrag „Ordnung“ für berechtigte Nutzer.

### Security

- Kategorie Ordnung wird serverseitig erzwungen.
- Normale Mitarbeiter können nur -1, -2 oder -3 buchen.
- CSRF-Schutz für Abzug, Korrektur und Storno.
- Storno statt harter Löschung.
- Audit-Log für Anlage und Storno von Punkteeinträgen.

### Datenbank

- Neue Migration `2026_06_25_000010_create_point_entries.php`.
- Neue Tabellen `point_categories` und `point_entries`.

### Hinweise

- Nach dem Update Migration ausführen und neu anmelden.
- Turnierwertung, Prüfungen, Wettbewerbe, Endauswertung und Import folgen später.

## v0.12.4 - 2026-06-26

### Behoben

- MigrationRunner für MySQL/MariaDB angepasst. DDL-Migrationen werden dort nicht mehr in eine äußere PDO-Transaktion gelegt, weil MySQL bei CREATE TABLE und ALTER TABLE implizit committet und dadurch der Fehler „There is no active transaction“ entstehen kann.

### Hinweise

- Wenn das Setup bereits teilweise Tabellen erstellt hat, kann `public/setup.php` nach diesem Fix erneut gestartet werden. Bereits ausgeführte Migrationen werden über `schema_migrations` übersprungen, und die vorhandenen `CREATE TABLE IF NOT EXISTS`-Migrationen bleiben idempotent.

## v0.13.2 - 2026-06-26

### Neu

- Systembereich `System → WebDAV` ergänzt.
- WebDAV-Einstellungen, Testfunktion und manueller Sync des letzten Backups ergänzt.
- Automatischer WebDAV-Sync nach erfolgreichem Backup, wenn WebDAV aktiv und vollständig konfiguriert ist.
- WebDAV-Sync-Protokoll über `webdav_sync_runs` sichtbar.

### Behoben

- `public/migration.php` bricht bei falscher Standard-Datenbankkonfiguration nicht mehr nur mit `root@localhost` ab, sondern zeigt ein Formular zum Speichern der echten Datenbankdaten.

### Datenbank

- Neue Migration `2026_06_25_000015_webdav_settings.php`.
- Neue Berechtigung `webdav.manage`.

### Hinweise

- Nach dem Update Migration ausführen und neu anmelden.
- `public/migration.php` nach erfolgreicher Nutzung wieder löschen.
- `config/database.php` bei Updates nicht überschreiben.

## v0.14.0 - 2026-06-29

### Neu

- Wiederkehrende Programmpunkte können markiert werden und werden in der Timeline optisch anders dargestellt.
- Orden/Zelte können zusätzlich zur bisherigen Farbmarke eine freie Farbe per Colorpicker erhalten.
- Neue Punkteansicht „Spielwertung“ mit Platzierungen. Eine Platzierung kann mehrere Orden enthalten.
- Neue Punkteansicht „Zelt bewerten“ mit Gesamtwertung für den Orden und Einzelwertungen für alle Kinder des Zelts.
- Neue Punkteansicht „Geschirr bewerten“ mit Einzelwertungen für alle Kinder eines Ordens/Zelts.
- Neue Punkteansicht „Dienstpunkte“ für Zusatz-Küchendienst-Wertungen je Kind.

### Geändert

- Punkte-Erfassungsansichten nutzen kompaktere Zeilen mit Plus/Minus-Steuerung.
- Dienstkarten verlinken für berechtigte Nutzer direkt auf die Dienstpunkte-Erfassung.

### Datenbank

- Neue Migration `2026_06_25_000020_points_views_recurring_order_colors.php`.
- Neue Spalte `orders.color_hex`.
- Neue Spalten `program_items.is_recurring` und `program_items.recurring_label`.
- Neue Kategorie `spiel_wertung`.

### Hinweise

- Nach dem Update Migration ausführen und danach neu anmelden.

## v0.14.6 - Migration 000023 app_versions Fix

- Korrigiert die Migration `2026_06_25_000023_person_search_and_rank_backfill.php`.
- `app_versions` nutzt in diesem Projekt `notes`, nicht `description`.
- Keine neue Fachlogik und keine neue Migration. Die fehlgeschlagene Migration 000023 wird korrigiert und kann erneut ausgeführt werden.

