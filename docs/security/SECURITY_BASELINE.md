# Security Baseline

## Aktiver Stand bis v0.3.0

- `public/` als einziger Webroot vorgesehen.
- `.htaccess`-Schutz für geschützte Ordner ergänzt.
- Security-Header zentral gesetzt.
- Session-Grundlage vorbereitet.
- CSRF-Helper vorbereitet.
- Zentrale Fehlerbehandlung vorbereitet.
- App-Log mit Redaction für Secrets vorbereitet.

## Grundregeln

- Keine Secrets im Log.
- Keine technischen Fehlerdetails im Browser.
- Alle Schreibaktionen mit CSRF absichern.
- Rechte nie nur über ausgeblendete Menüs prüfen.
- Downloads später immer über Controller mit Rechteprüfung.

## Login und PIN ab v0.2.0

- PINs werden mit `password_hash` gespeichert.
- Die Prüfung erfolgt mit `password_verify`.
- Nach erfolgreichem Login wird die Session-ID rotiert.
- Fehlversuche werden gezählt und können den Login temporär sperren.
- Login, Logout, fehlgeschlagene Logins, Rollenänderungen und PIN-Änderungen werden im Audit-Log protokolliert.
- PINs werden nicht im Klartext gespeichert und nicht geloggt.
- Adminrechte werden über Rollen und Berechtigungen vergeben, nicht über einen separaten Login.


## Content-Security-Policy

Ab v0.3.0 setzt die Anwendung eine CSP über den zentralen Security-Header. Erlaubt sind eigene Skripte und Styles sowie Google Fonts. Inline-JavaScript wurde aus der Loginseite entfernt. Bei neuen externen Assets muss die CSP bewusst angepasst und getestet werden.



## Rechte ab v0.4.0

Lagerjahre werden über `camp_years.view` und `camp_years.manage` geschützt. Orden/Zelte werden über `orders.view` und `orders.manage` geschützt. Schreibaktionen nutzen CSRF und werden im Audit-Log protokolliert.


## Rechte ab v0.5.0

Das Programmmodul nutzt `program.view` für die Anzeige und `program.manage` für Anlage, Änderung und Deaktivierung. Alle Schreibaktionen sind CSRF-geschützt. Änderungen an Programmpunkten werden im Audit-Log protokolliert.

## Rechte ab v0.7.0

Das Essen-Modul nutzt `meals.view` für die Anzeige und `meals.manage` für Anlage, Änderung und Deaktivierung. Alle Schreibaktionen sind CSRF-geschützt. Änderungen an Mahlzeiten werden im Audit-Log protokolliert.

Allergiehinweise im Speiseplan sind in dieser Phase als allgemeine Küchenhinweise gedacht. Personenbezogene Allergie- und Gesundheitsdaten müssen später in der Teilnehmerdatenphase feiner berechtigt werden.


## Dienstrechte

Die Dienstliste ist über `duties.view` geschützt. Anlage, Bearbeitung, Zuweisung und administrative Statuswechsel benötigen `duties.manage`. Zugewiesene Mitarbeiter dürfen einen eigenen Dienst als erledigt markieren. Alle Schreibaktionen verwenden CSRF und werden im Audit-Log protokolliert.

## Ergänzung v0.8.0: sensible Personendaten

Seit v0.8.0 speichert die Anwendung erweiterte Teilnehmerdaten. Dazu gehören Notfallkontakte, Allergien, medizinische Hinweise und interne Bemerkungen. Diese Felder gelten als sensibel.

Regeln:

- Anzeige sensibler Felder nur mit `persons.sensitive.view` oder `persons.manage`.
- Änderungen an sensiblen Feldern werden im Audit-Log protokolliert, aber ohne Klartextinhalte.
- Technische Logs dürfen keine medizinischen Hinweise, Allergien oder Notfallkontakte enthalten.
- Exporte dieser Daten sind in v0.8.0 noch nicht umgesetzt und brauchen später eigene Rechte.


## v0.9.0 Ordnungspunkte

Die Route `/ordnung` ist durch `points.order.create` geschützt. Die Adminliste `/admin/ordnungspunkte` und Korrekturen/Storno sind durch `points.manage` geschützt. Nach dem Update muss die Migration `2026_06_25_000010_create_point_entries.php` ausgeführt werden. Einträge werden nicht hart gelöscht, sondern über `voided_at`, `voided_by` und `void_reason` storniert.

## v0.11.0 Auswertung und Prüfungen

- Auswertungen sind über `exams.view` geschützt.
- Schreibaktionen für Rangstufen, Lerneinheiten, Prüfungsergebnisse und Rangzuordnung benötigen `exams.manage` oder `points.manage`.
- Alle Schreibaktionen nutzen CSRF.
- Änderungen werden im Audit-Log protokolliert.
- Medizinische Hinweise, Notfallkontakte und interne Teilnehmernotizen werden in Auswertungen nicht ausgegeben.
- CSV-Export ist nur für eingeloggte Nutzer mit `exams.view` erreichbar.


## Import-Sicherheit

Importe sind nur mit `imports.manage` erlaubt. Uploads werden außerhalb des Webroots in `storage/imports` gespeichert. Erlaubt sind XLSX, ODS und DOCX bis 10 MB. Dateiendung und MIME-Type werden geprüft. Makros, Formeln und eingebettete Inhalte werden nicht ausgeführt.

## Betriebssicherheit ab v0.12.0

- Backupdateien liegen außerhalb des Webroots.
- Backupdownload erfolgt nur über Controller mit `backups.download`.
- HTTP-Cron führt nur feste Task-Keys aus.
- Cron-Tokens werden ausschließlich gehasht gespeichert.
- Task-Läufe nutzen `locked_until` gegen parallele Ausführung.
- Task-Läufe werden protokolliert.
- Logs bleiben im geschützten Storage.
- Secrets, PINs, Passwörter, Tokens und CSRF-Werte dürfen nicht in Logs geschrieben werden.
