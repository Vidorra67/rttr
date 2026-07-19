# Restore

## Grundsatz

Ein Backup ist nur vollständig, wenn ein Restore getestet werden kann. Der Ritterlager Manager bietet bewusst keinen Klick-Restore in Produktion. Restore bleibt ein kontrollierter manueller Prozess.

## Restore-Reihenfolge

1. Wartungsmodus oder Zugriffssperre auf Hostingebene aktivieren.
2. Aktuellen Ist-Zustand sichern.
3. Backupdatei aus `storage/backups` herunterladen.
4. Backup in separatem Ordner entpacken.
5. Codeversion und `VERSION` prüfen.
6. Projektdateien wiederherstellen.
7. Konfiguration prüfen, echte Secrets nicht blind überschreiben.
8. Datenbank aus SQL-Dump wiederherstellen.
9. `storage/uploads`, `storage/imports` und `storage/documents` wiederherstellen.
10. Dateirechte prüfen.
11. Migrationen prüfen.
12. Login testen.
13. Kernbereiche prüfen: Übersicht, Programm, Essen, Dienste, Personen, Ordnungspunkte.
14. Cronjobs erst aktivieren, wenn Umgebung eindeutig Produktion ist.
15. Wartungsmodus deaktivieren.

## Test-Restore

Empfohlen vor größeren Releases:

1. Backup im Produktivsystem erstellen.
2. In Testumgebung einspielen.
3. Mail- und Cronversand in Testumgebung deaktivieren.
4. Login und Fachmodule prüfen.
5. Storage-Dateien prüfen.
6. Log prüfen.

## Risiken

- Datenbank passt nicht zur Codeversion.
- Konfiguration wurde mit Testwerten überschrieben.
- Dateirechte verhindern Uploads, Backups oder Logs.
- Cronjobs laufen in Testumgebung und erzeugen echte Backups oder Aktionen.
- WebDAV-Zugang verweist auf Produktivablage.

## Hinweis

Vor einem produktiven Restore muss klar sein, welcher Datenstand, welche Codeversion und welche Dateien eingespielt werden.
