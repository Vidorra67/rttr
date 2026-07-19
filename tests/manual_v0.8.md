# Manuelle Tests v0.8.0

## Migration

1. `php scripts/maintenance/migrate.php` ausführen.
2. Prüfen, ob die Migration `2026_06_25_000009_extend_persons_camp_statuses` eingetragen wurde.
3. Prüfen, ob `camp_person_statuses` und `person_guardians` existieren.
4. Prüfen, ob `persons` die neuen Kontakt- und Hinweisfelder enthält.

## Personenverwaltung

1. Als Admin anmelden.
2. Person mit vollständigen Daten anlegen.
3. Geburtsdatum setzen.
4. Person als Teilnehmer markieren.
5. Person als Mitarbeiter markieren.
6. Person als Teilnehmer und Mitarbeiter markieren.
7. Orden/Zelt zuweisen.
8. Person speichern.
9. Detailansicht öffnen.
10. Prüfen, ob Status, Orden/Zelt, Rang und Geburtstagsinformation angezeigt werden.

## Geburtstage

1. Aktives Lagerjahr mit Start- und Enddatum anlegen.
2. Person mit Geburtstag innerhalb des Lagerzeitraums speichern.
3. Personenliste filtern nach „Geburtstag im Lager“.
4. Prüfen, ob die Person angezeigt wird.

## Sensible Daten

1. Medizinische Hinweise und Notfallkontakt speichern.
2. Als Admin Detailansicht öffnen.
3. Prüfen, ob sensible Felder sichtbar sind.
4. Als Benutzer ohne `persons.sensitive.view` anmelden.
5. Detailansicht öffnen.
6. Prüfen, ob sensible Felder ausgeblendet sind.

## Sicherheit

1. POST ohne CSRF gegen `/admin/personen/speichern` senden.
2. Erwartung: 403.
3. Nutzer ohne `persons.manage` darf keine Person bearbeiten.
4. Nutzer mit `persons.view`, aber ohne `persons.sensitive.view`, darf Detailansicht öffnen, sieht aber keine sensiblen Felder.
5. XSS-Test in Notizen durchführen. Erwartung: HTML wird escaped.

## Dashboard

1. Aktives Lagerjahr setzen.
2. Personen als Teilnehmer und Mitarbeiter markieren.
3. Übersicht öffnen.
4. Teilnehmer- und Mitarbeiterzahlen prüfen.
5. Person mit heutigem Geburtstag anlegen.
6. Übersicht öffnen und Geburtstagsbereich prüfen.
