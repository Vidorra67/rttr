<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ImportService
{
    public const PROFILES = [
        'persons' => 'Personen und Teilnehmerdaten',
        'orders' => 'Orden/Zelte und Zuordnungen',
        'staff' => 'Mitarbeiter und Rollenhinweise',
        'program' => 'Programm',
        'meals' => 'Speiseplan',
        'duties' => 'Dienstarten und Aufgabenverteilung',
        'learning_units' => 'Rangordnung und Lerneinheiten',
        'exams' => 'Prüfungsergebnisse',
        'order_points' => 'Ordnung/Disziplin',
    ];

    private const ALLOWED_EXTENSIONS = ['xlsx', 'ods', 'docx'];
    private const MAX_FILE_SIZE = 10_485_760;

    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly CampYearService $campYearService = new CampYearService()
    ) {
    }

    public function activeCampYear(): ?array
    {
        return $this->campYearService->active();
    }

    public function dayLabel(?array $campYear): string
    {
        return $this->campYearService->dayLabel($campYear);
    }

    public function profiles(): array
    {
        return self::PROFILES;
    }

    public function runs(): array
    {
        $stmt = Database::connection()->query("SELECT ir.*, uf.original_name, uf.file_ext, uf.file_size, p.display_name AS created_by_name
            FROM import_runs ir
            LEFT JOIN uploaded_files uf ON uf.id = ir.original_file_id
            LEFT JOIN users u ON u.id = ir.created_by
            LEFT JOIN persons p ON p.id = u.person_id
            ORDER BY ir.created_at DESC, ir.id DESC
            LIMIT 80");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRun(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT ir.*, uf.original_name, uf.stored_name, uf.storage_path, uf.mime_type, uf.file_ext, uf.file_size, uf.checksum_sha256,
                p.display_name AS created_by_name
            FROM import_runs ir
            LEFT JOIN uploaded_files uf ON uf.id = ir.original_file_id
            LEFT JOIN users u ON u.id = ir.created_by
            LEFT JOIN persons p ON p.id = u.person_id
            WHERE ir.id = :id
            LIMIT 1");
        $stmt->execute(['id' => $id]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($run)) {
            return null;
        }

        $run['summary'] = $this->decodeJson($run['summary_json'] ?? null);
        $run['errors'] = $this->runErrors($id);
        return $run;
    }

    public function createPreview(array $file, string $importKey): int
    {
        $this->validateProfile($importKey);
        $activeCampYear = $this->activeCampYear();
        if ($activeCampYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }

        $stored = $this->storeUploadedFile($file, $importKey, (int) $activeCampYear['id']);
        $preview = $this->previewFile($stored['absolute_path'], $stored['file_ext'], $importKey);

        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO import_runs
            (camp_year_id, import_key, original_file_id, status, started_at, finished_at, total_rows, imported_rows, skipped_rows, error_rows, summary_json, created_by, created_at)
            VALUES (:camp_year_id, :import_key, :original_file_id, 'preview', NOW(), NOW(), :total_rows, 0, 0, :error_rows, :summary_json, :created_by, NOW())");
        $stmt->execute([
            'camp_year_id' => (int) $activeCampYear['id'],
            'import_key' => $importKey,
            'original_file_id' => $stored['uploaded_file_id'],
            'total_rows' => (int) ($preview['total_rows'] ?? 0),
            'error_rows' => count($preview['errors'] ?? []),
            'summary_json' => json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $this->currentUserId(),
        ]);
        $runId = (int) $pdo->lastInsertId();
        $linkStmt = $pdo->prepare('UPDATE uploaded_files SET owner_id = :owner_id WHERE id = :id');
        $linkStmt->execute(['owner_id' => $runId, 'id' => $stored['uploaded_file_id']]);

        $this->persistPreviewErrors($runId, $preview['errors'] ?? []);
        $this->auditService->record('imports.preview_created', $this->currentUserId(), 'import_run', $runId, [
            'import_key' => $importKey,
            'file_ext' => $stored['file_ext'],
            'file_size' => $stored['file_size'],
        ]);

        return $runId;
    }

    public function execute(int $runId): array
    {
        $run = $this->findRun($runId);
        if ($run === null) {
            throw new RuntimeException('Importlauf wurde nicht gefunden.');
        }
        if (!in_array((string) $run['status'], ['uploaded', 'preview', 'partial', 'failed'], true)) {
            throw new RuntimeException('Dieser Importlauf kann nicht erneut ausgeführt werden.');
        }

        $localPath = $this->absoluteStoragePath((string) ($run['storage_path'] ?? ''));
        if (!is_file($localPath)) {
            throw new RuntimeException('Importdatei wurde im Storage nicht gefunden.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->markRunning($pdo, $runId);
            $preview = $this->previewFile($localPath, (string) ($run['file_ext'] ?? ''), (string) $run['import_key']);
            $result = $this->processRows($pdo, $run, $preview);

            $status = ($result['error_rows'] ?? 0) > 0 ? 'partial' : 'ok';
            $summary = $preview;
            $summary['execution'] = $result;

            $stmt = $pdo->prepare("UPDATE import_runs
                SET status = :status,
                    finished_at = NOW(),
                    total_rows = :total_rows,
                    imported_rows = :imported_rows,
                    skipped_rows = :skipped_rows,
                    error_rows = :error_rows,
                    summary_json = :summary_json
                WHERE id = :id");
            $stmt->execute([
                'status' => $status,
                'total_rows' => (int) ($result['total_rows'] ?? 0),
                'imported_rows' => (int) ($result['imported_rows'] ?? 0),
                'skipped_rows' => (int) ($result['skipped_rows'] ?? 0),
                'error_rows' => (int) ($result['error_rows'] ?? 0),
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $runId,
            ]);

            $this->replaceRunErrors($pdo, $runId, $result['errors'] ?? []);
            $pdo->commit();

            $this->auditService->record('imports.executed', $this->currentUserId(), 'import_run', $runId, [
                'import_key' => (string) $run['import_key'],
                'status' => $status,
                'imported_rows' => (int) ($result['imported_rows'] ?? 0),
                'skipped_rows' => (int) ($result['skipped_rows'] ?? 0),
                'error_rows' => (int) ($result['error_rows'] ?? 0),
            ]);

            return $result + ['status' => $status];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->markFailed($runId, $exception->getMessage());
            throw $exception;
        }
    }

    private function storeUploadedFile(array $file, string $importKey, int $campYearId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Datei konnte nicht hochgeladen werden.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Die Datei ist leer oder größer als 10 MB.');
        }

        $originalName = (string) ($file['name'] ?? 'import');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Erlaubt sind nur XLSX, ODS und DOCX.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Upload konnte nicht verifiziert werden.');
        }

        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmpPath) : 'application/octet-stream';
        if (!$this->isAllowedMime($mime, $ext)) {
            throw new RuntimeException('Der Dateityp passt nicht zur erlaubten Importdatei.');
        }

        $relativeDir = 'imports/' . date('Y') . '/' . date('m');
        $absoluteDir = storage_path($relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Importordner konnte nicht erstellt werden.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Datei konnte nicht im geschützten Storage gespeichert werden.');
        }
        if (!is_file($absolutePath)) {
            throw new RuntimeException('Gespeicherte Importdatei wurde nicht gefunden.');
        }

        $checksum = hash_file('sha256', $absolutePath) ?: null;
        $storagePath = $relativeDir . '/' . $storedName;
        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO uploaded_files
            (owner_type, owner_id, category, original_name, stored_name, storage_path, mime_type, file_ext, file_size, checksum_sha256, visibility, uploaded_by, created_at)
            VALUES ('import_run', NULL, :category, :original_name, :stored_name, :storage_path, :mime_type, :file_ext, :file_size, :checksum_sha256, 'private', :uploaded_by, NOW())");
        $stmt->execute([
            'category' => $importKey,
            'original_name' => mb_substr($originalName, 0, 255),
            'stored_name' => $storedName,
            'storage_path' => $storagePath,
            'mime_type' => mb_substr($mime, 0, 190),
            'file_ext' => $ext,
            'file_size' => $size,
            'checksum_sha256' => $checksum,
            'uploaded_by' => $this->currentUserId(),
        ]);

        return [
            'uploaded_file_id' => (int) $pdo->lastInsertId(),
            'absolute_path' => $absolutePath,
            'storage_path' => $storagePath,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'file_ext' => $ext,
            'file_size' => $size,
            'checksum_sha256' => $checksum,
            'camp_year_id' => $campYearId,
        ];
    }

    private function previewFile(string $absolutePath, string $ext, string $importKey): array
    {
        $this->validateProfile($importKey);
        $rows = [];
        $errors = [];
        $warnings = [];

        if (!class_exists(ZipArchive::class)) {
            $warnings[] = [
                'source_row_number' => null,
                'field_name' => 'parser',
                'error_text' => 'PHP-ZipArchive ist nicht verfügbar. Die Datei wurde sicher gespeichert, aber noch nicht inhaltlich gelesen.',
                'raw_row_json' => null,
            ];
        } elseif ($ext === 'xlsx') {
            $rows = $this->readXlsxRows($absolutePath, $warnings, $errors);
        } elseif ($ext === 'ods') {
            $rows = $this->readOdsRows($absolutePath, $warnings, $errors);
        } elseif ($ext === 'docx') {
            $rows = $this->readDocxRows($absolutePath, $warnings, $errors);
        } else {
            $errors[] = ['source_row_number' => null, 'field_name' => 'file_ext', 'error_text' => 'Dateityp wird nicht unterstützt.', 'raw_row_json' => null];
        }

        return [
            'profile' => $importKey,
            'profile_label' => self::PROFILES[$importKey] ?? $importKey,
            'file_ext' => $ext,
            'total_rows' => count($rows),
            'preview_rows' => array_slice($rows, 0, 30),
            'warnings' => $warnings,
            'errors' => $errors,
            'note' => 'Vorschau liest maximal die ersten 30 Zeilen. Ausführung schreibt nur eindeutig zuordenbare Datensätze und überschreibt keine vorhandenen Daten.',
        ];
    }

    private function processRows(PDO $pdo, array $run, array $preview): array
    {
        $rows = $preview['preview_rows'] ?? [];
        $profile = (string) ($run['import_key'] ?? '');
        $campYearId = (int) ($run['camp_year_id'] ?? 0);
        $errors = [];
        $imported = 0;
        $skipped = 0;

        if ($campYearId <= 0) {
            return [
                'total_rows' => count($rows),
                'imported_rows' => 0,
                'skipped_rows' => count($rows),
                'error_rows' => 1,
                'errors' => [[
                    'source_row_number' => null,
                    'field_name' => 'camp_year_id',
                    'error_text' => 'Import braucht ein aktives Lagerjahr.',
                    'raw_row_json' => null,
                ]],
                'message' => 'Kein aktives Lagerjahr.',
            ];
        }

        if ($rows === []) {
            $errors[] = [
                'source_row_number' => null,
                'field_name' => 'preview',
                'error_text' => 'Keine strukturierten Zeilen erkannt. Die Datei wurde gespeichert, aber es wurden keine Fachdaten geschrieben.',
                'raw_row_json' => null,
            ];
            return [
                'total_rows' => 0,
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'error_rows' => 1,
                'errors' => $errors,
                'message' => 'Keine strukturierten Zeilen erkannt.',
            ];
        }

        $header = $this->normalizeHeader($rows[0] ?? []);
        $dataRows = array_slice($rows, 1);

        foreach ($dataRows as $offset => $row) {
            $rowNumber = $offset + 2;
            $mapped = $this->mapRow($header, $row);
            try {
                $changed = match ($profile) {
                    'persons', 'staff' => $this->importPersonRow($pdo, $mapped, $campYearId, $profile),
                    'orders' => $this->importOrderRow($pdo, $mapped, $campYearId),
                    'program' => $this->importProgramRow($pdo, $mapped, $campYearId),
                    'meals' => $this->importMealRow($pdo, $mapped, $campYearId),
                    'duties' => $this->importDutyTypeRow($pdo, $mapped),
                    'learning_units' => $this->importLearningUnitRow($pdo, $mapped, $campYearId),
                    default => false,
                };
                if ($changed) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $skipped++;
                $errors[] = [
                    'source_row_number' => $rowNumber,
                    'field_name' => $profile,
                    'error_text' => $exception->getMessage(),
                    'raw_row_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        if (in_array($profile, ['exams', 'order_points'], true)) {
            $errors[] = [
                'source_row_number' => null,
                'field_name' => $profile,
                'error_text' => 'Dieses Profil braucht eine fachliche Feldzuordnung. In v0.11 wird es nur gespeichert und protokolliert.',
                'raw_row_json' => null,
            ];
        }

        return [
            'total_rows' => count($dataRows),
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'error_rows' => count($errors),
            'errors' => $errors,
            'message' => 'Import abgeschlossen. Bestehende Datensätze wurden nicht überschrieben.',
        ];
    }

    private function importPersonRow(PDO $pdo, array $row, int $campYearId, string $profile): bool
    {
        $first = $this->value($row, ['vorname', 'first_name']);
        $last = $this->value($row, ['nachname', 'last_name']);
        $display = $this->value($row, ['name', 'anzeigename', 'display_name']);
        if ($display === '') {
            $display = trim($first . ' ' . $last);
        }
        if ($display === '') {
            return false;
        }

        $birthdate = $this->dateValue($this->value($row, ['geburtstag', 'geburtsdatum', 'birthdate']));
        if ($this->personExists($pdo, $display, $birthdate)) {
            return false;
        }

        if ($first === '' && $last === '') {
            [$first, $last] = $this->splitName($display);
        }

        $isStaff = $profile === 'staff' || $this->truthy($this->value($row, ['mitarbeiter', 'staff']));
        $isParticipant = $profile === 'persons' || $this->truthy($this->value($row, ['teilnehmer', 'participant']));
        $typeHint = $isStaff && $isParticipant ? 'beides' : ($isStaff ? 'mitarbeiter' : 'teilnehmer');

        $stmt = $pdo->prepare("INSERT INTO persons
            (first_name, last_name, display_name, birthdate, type_hint, street, zip, city, phone, email, emergency_contact_name, emergency_contact_phone,
             food_notes, allergy_notes, medical_notes, internal_notes, is_active, created_at, updated_at)
            VALUES (:first_name, :last_name, :display_name, :birthdate, :type_hint, :street, :zip, :city, :phone, :email, :emergency_contact_name, :emergency_contact_phone,
             :food_notes, :allergy_notes, :medical_notes, :internal_notes, 1, NOW(), NOW())");
        $stmt->execute([
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => $display,
            'birthdate' => $birthdate,
            'type_hint' => $typeHint,
            'street' => $this->value($row, ['strasse', 'straße', 'street']),
            'zip' => $this->value($row, ['plz', 'zip']),
            'city' => $this->value($row, ['ort', 'city']),
            'phone' => $this->value($row, ['telefon', 'phone']),
            'email' => $this->value($row, ['email', 'e_mail']),
            'emergency_contact_name' => $this->value($row, ['notfallkontakt', 'kontakt']),
            'emergency_contact_phone' => $this->value($row, ['notfalltelefon', 'notfall_tel']),
            'food_notes' => $this->value($row, ['essen', 'essenshinweise']),
            'allergy_notes' => $this->value($row, ['allergien', 'allergy_notes']),
            'medical_notes' => $this->value($row, ['medizin', 'medical_notes']),
            'internal_notes' => $this->value($row, ['bemerkung', 'notiz', 'internal_notes']),
        ]);
        $personId = (int) $pdo->lastInsertId();
        $orderId = $this->orderIdByLabel($pdo, $campYearId, $this->value($row, ['orden', 'zelt', 'order']));
        $rankLabel = $this->value($row, ['rang', 'rank']);

        $statusStmt = $pdo->prepare("INSERT INTO camp_person_statuses
            (camp_year_id, person_id, is_participant, is_staff, participant_status, staff_status, order_id, rank_label, created_at, updated_at)
            VALUES (:camp_year_id, :person_id, :is_participant, :is_staff, 'angemeldet', 'aktiv', :order_id, :rank_label, NOW(), NOW())");
        $statusStmt->execute([
            'camp_year_id' => $campYearId,
            'person_id' => $personId,
            'is_participant' => $isParticipant ? 1 : 0,
            'is_staff' => $isStaff ? 1 : 0,
            'order_id' => $orderId,
            'rank_label' => $rankLabel !== '' ? $rankLabel : null,
        ]);
        return true;
    }

    private function importOrderRow(PDO $pdo, array $row, int $campYearId): bool
    {
        $name = $this->value($row, ['orden', 'zelt', 'name', 'gruppe']);
        if ($name === '' || $this->orderIdByLabel($pdo, $campYearId, $name) !== null) {
            return false;
        }
        $short = $this->value($row, ['kuerzel', 'kürzel', 'short_name']);
        if ($short === '') {
            $short = mb_substr($name, 0, 3);
        }
        $stmt = $pdo->prepare("INSERT INTO orders
            (camp_year_id, name, short_name, color_key, sort_order, is_active, created_at, updated_at)
            VALUES (:camp_year_id, :name, :short_name, :color_key, :sort_order, 1, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'name' => $name,
            'short_name' => $short,
            'color_key' => $this->value($row, ['farbe', 'color_key']) ?: 'blau',
            'sort_order' => (int) ($this->value($row, ['sortierung', 'sort_order']) ?: 100),
        ]);
        return true;
    }

    private function importProgramRow(PDO $pdo, array $row, int $campYearId): bool
    {
        $date = $this->dateValue($this->value($row, ['datum', 'tag', 'program_date']));
        $title = $this->value($row, ['titel', 'programmpunkt', 'title']);
        if ($date === null || $title === '') {
            return false;
        }
        $starts = $this->timeValue($this->value($row, ['start', 'uhrzeit', 'starts_at']));
        $existing = $pdo->prepare("SELECT id FROM program_items WHERE camp_year_id = :camp_year_id AND program_date = :program_date AND COALESCE(starts_at, '') = COALESCE(:starts_at, '') AND title = :title AND deleted_at IS NULL LIMIT 1");
        $existing->execute(['camp_year_id' => $campYearId, 'program_date' => $date, 'starts_at' => $starts, 'title' => $title]);
        if ($existing->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO program_items
            (camp_year_id, program_date, starts_at, ends_at, title, category_key, location, responsible_label, description, sort_order, is_visible, created_by, updated_by, created_at, updated_at)
            VALUES (:camp_year_id, :program_date, :starts_at, :ends_at, :title, :category_key, :location, :responsible_label, :description, 100, 1, :created_by, :updated_by, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'program_date' => $date,
            'starts_at' => $starts,
            'ends_at' => $this->timeValue($this->value($row, ['ende', 'ends_at'])),
            'title' => $title,
            'category_key' => $this->categoryValue($this->value($row, ['kategorie', 'category_key']), 'info'),
            'location' => $this->value($row, ['ort', 'location']),
            'responsible_label' => $this->value($row, ['verantwortlich', 'responsible']),
            'description' => $this->value($row, ['beschreibung', 'notiz', 'description']),
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ]);
        return true;
    }

    private function importMealRow(PDO $pdo, array $row, int $campYearId): bool
    {
        $date = $this->dateValue($this->value($row, ['datum', 'tag', 'meal_date']));
        $title = $this->value($row, ['gericht', 'titel', 'title']);
        if ($date === null || $title === '') {
            return false;
        }
        if (!$this->dateIsInsideCampYear($pdo, $campYearId, $date)) {
            return false;
        }
        $mealType = $this->mealTypeValue($this->value($row, ['mahlzeit', 'typ', 'meal_type']));
        $existing = $pdo->prepare("SELECT id FROM meal_items WHERE camp_year_id = :camp_year_id AND meal_date = :meal_date AND meal_type = :meal_type AND title = :title AND deleted_at IS NULL LIMIT 1");
        $existing->execute(['camp_year_id' => $campYearId, 'meal_date' => $date, 'meal_type' => $mealType, 'title' => $title]);
        if ($existing->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO meal_items
            (camp_year_id, meal_date, meal_type, meal_time, title, portions_total, portions_vegetarian, allergy_notes, kitchen_team_label, description, created_by, updated_by, created_at, updated_at)
            VALUES (:camp_year_id, :meal_date, :meal_type, :meal_time, :title, :portions_total, :portions_vegetarian, :allergy_notes, :kitchen_team_label, :description, :created_by, :updated_by, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'meal_date' => $date,
            'meal_type' => $mealType,
            'meal_time' => $this->timeValue($this->value($row, ['uhrzeit', 'zeit', 'meal_time'])),
            'title' => $title,
            'portions_total' => (int) ($this->value($row, ['portionen', 'portionen_gesamt', 'portions_total']) ?: 0),
            'portions_vegetarian' => (int) ($this->value($row, ['vegetarisch', 'portions_vegetarian']) ?: 0),
            'allergy_notes' => $this->value($row, ['allergien', 'allergy_notes']),
            'kitchen_team_label' => $this->value($row, ['kuechenteam', 'küchenteam', 'kitchen_team']),
            'description' => $this->value($row, ['beschreibung', 'notiz', 'description']),
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ]);
        return true;
    }

    private function dateIsInsideCampYear(PDO $pdo, int $campYearId, string $date): bool
    {
        $stmt = $pdo->prepare('SELECT starts_on, ends_on FROM camp_years WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campYearId]);
        $campYear = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($campYear)) {
            return false;
        }

        return $date >= (string) $campYear['starts_on'] && $date <= (string) $campYear['ends_on'];
    }

    private function importDutyTypeRow(PDO $pdo, array $row): bool
    {
        $label = $this->value($row, ['dienstart', 'aufgabe', 'funktion', 'name', 'title']);
        if ($label === '') {
            $label = trim(implode(' ', array_slice(array_filter($row, static fn ($v): bool => trim((string) $v) !== ''), 0, 2)));
        }
        if ($label === '') {
            return false;
        }
        $key = $this->slug($label);
        $existing = $pdo->prepare('SELECT id FROM duty_types WHERE key_name = :key_name LIMIT 1');
        $existing->execute(['key_name' => $key]);
        if ($existing->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO duty_types
            (key_name, label, icon_key, default_time_label, assignment_mode, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :label, 'task', :default_time_label, 'mixed', 1, 100, NOW(), NOW())");
        $stmt->execute([
            'key_name' => $key,
            'label' => $label,
            'default_time_label' => $this->value($row, ['zeit', 'default_time_label']),
        ]);
        return true;
    }

    private function importLearningUnitRow(PDO $pdo, array $row, int $campYearId): bool
    {
        $title = $this->value($row, ['lerneinheit', 'unterricht', 'titel', 'title']);
        if ($title === '') {
            return false;
        }
        $existing = $pdo->prepare('SELECT id FROM learning_units WHERE camp_year_id = :camp_year_id AND title = :title AND deleted_at IS NULL LIMIT 1');
        $existing->execute(['camp_year_id' => $campYearId, 'title' => $title]);
        if ($existing->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO learning_units
            (camp_year_id, title, category_key, responsible_label, sort_order, created_at, updated_at)
            VALUES (:camp_year_id, :title, :category_key, :responsible_label, 100, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'title' => $title,
            'category_key' => $this->categoryValue($this->value($row, ['kategorie', 'category_key']), 'lernen'),
            'responsible_label' => $this->value($row, ['verantwortlich', 'responsible']),
        ]);
        return true;
    }

    private function readXlsxRows(string $path, array &$warnings, array &$errors): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $errors[] = ['source_row_number' => null, 'field_name' => 'xlsx', 'error_text' => 'XLSX konnte nicht geöffnet werden.', 'raw_row_json' => null];
            return [];
        }
        $shared = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!is_string($sheetXml)) {
            $errors[] = ['source_row_number' => null, 'field_name' => 'xlsx', 'error_text' => 'Erstes Tabellenblatt wurde nicht gefunden.', 'raw_row_json' => null];
            return [];
        }
        return array_slice($this->xlsxRowsFromXml($sheetXml, $shared), 0, 250);
    }

    private function readOdsRows(string $path, array &$warnings, array &$errors): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $errors[] = ['source_row_number' => null, 'field_name' => 'ods', 'error_text' => 'ODS konnte nicht geöffnet werden.', 'raw_row_json' => null];
            return [];
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if (!is_string($xml)) {
            $errors[] = ['source_row_number' => null, 'field_name' => 'ods', 'error_text' => 'ODS-Inhalt wurde nicht gefunden.', 'raw_row_json' => null];
            return [];
        }
        preg_match_all('/<table:table-row[^>]*>(.*?)<\/table:table-row>/s', $xml, $rowMatches);
        $rows = [];
        foreach ($rowMatches[1] as $rowXml) {
            preg_match_all('/<table:table-cell([^>]*)>(.*?)<\/table:table-cell>/s', $rowXml, $cellMatches, PREG_SET_ORDER);
            $row = [];
            foreach ($cellMatches as $cellMatch) {
                $repeat = 1;
                if (preg_match('/table:number-columns-repeated="(\d+)"/', $cellMatch[1], $repeatMatch)) {
                    $repeat = min((int) $repeatMatch[1], 20);
                }
                $value = $this->cleanXmlText($cellMatch[2]);
                for ($i = 0; $i < $repeat; $i++) {
                    $row[] = $value;
                }
            }
            if ($this->rowHasValue($row)) {
                $rows[] = $row;
            }
            if (count($rows) >= 250) {
                break;
            }
        }
        return $rows;
    }

    private function readDocxRows(string $path, array &$warnings, array &$errors): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $errors[] = ['source_row_number' => null, 'field_name' => 'docx', 'error_text' => 'DOCX konnte nicht geöffnet werden.', 'raw_row_json' => null];
            return [];
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml)) {
            $errors[] = ['source_row_number' => null, 'field_name' => 'docx', 'error_text' => 'DOCX-Inhalt wurde nicht gefunden.', 'raw_row_json' => null];
            return [];
        }
        preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml, $paragraphMatches);
        $rows = [['text']];
        foreach ($paragraphMatches[1] as $paragraphXml) {
            preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraphXml, $textMatches);
            $text = trim(html_entity_decode(implode('', $textMatches[1] ?? []), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($text !== '') {
                $rows[] = [$text];
            }
            if (count($rows) >= 250) {
                break;
            }
        }
        return $rows;
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml)) {
            return [];
        }
        preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $xml, $matches);
        $strings = [];
        foreach ($matches[1] as $siXml) {
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $siXml, $textMatches);
            $strings[] = html_entity_decode(implode('', $textMatches[1] ?? []), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $strings;
    }

    private function xlsxRowsFromXml(string $xml, array $shared): array
    {
        preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $xml, $rowMatches);
        $rows = [];
        foreach ($rowMatches[1] as $rowXml) {
            preg_match_all('/<c([^>]*)>(.*?)<\/c>/s', $rowXml, $cellMatches, PREG_SET_ORDER);
            $row = [];
            foreach ($cellMatches as $cellMatch) {
                $attrs = $cellMatch[1];
                $cellXml = $cellMatch[2];
                $index = null;
                if (preg_match('/r="([A-Z]+)(\d+)"/', $attrs, $refMatch)) {
                    $index = $this->columnIndex($refMatch[1]);
                }
                preg_match('/<v[^>]*>(.*?)<\/v>/s', $cellXml, $valueMatch);
                $value = isset($valueMatch[1]) ? html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
                if (str_contains($attrs, 't="s"')) {
                    $value = $shared[(int) $value] ?? '';
                } elseif (str_contains($attrs, 't="inlineStr"')) {
                    preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $cellXml, $textMatches);
                    $value = html_entity_decode(implode('', $textMatches[1] ?? []), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                if ($index === null) {
                    $row[] = trim((string) $value);
                } else {
                    $row[$index] = trim((string) $value);
                }
            }
            if ($row !== []) {
                ksort($row);
                $normalized = [];
                $max = max(array_keys($row));
                for ($i = 0; $i <= $max; $i++) {
                    $normalized[] = (string) ($row[$i] ?? '');
                }
                if ($this->rowHasValue($normalized)) {
                    $rows[] = $normalized;
                }
            }
            if (count($rows) >= 250) {
                break;
            }
        }
        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }
        return $index - 1;
    }

    private function cleanXmlText(string $xml): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<text:line-break/>', '<text:s/>'], [' ', ' '], $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function normalizeHeader(array $row): array
    {
        $header = [];
        foreach ($row as $index => $label) {
            $normalized = $this->normalizeKey((string) $label);
            if ($normalized !== '') {
                $header[$index] = $normalized;
            }
        }
        return $header;
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function mapRow(array $header, array $row): array
    {
        $mapped = [];
        foreach ($row as $index => $value) {
            $key = $header[$index] ?? 'spalte_' . ($index + 1);
            $mapped[$key] = trim((string) $value);
        }
        return $mapped;
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeKey($key);
            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }
        return '';
    }

    private function dateValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $value, $match)) {
            $year = (int) $match[3];
            $year = $year < 100 ? 2000 + $year : $year;
            return sprintf('%04d-%02d-%02d', $year, (int) $match[2], (int) $match[1]);
        }
        if (is_numeric($value)) {
            $timestamp = ((int) $value - 25569) * 86400;
            return gmdate('Y-m-d', $timestamp);
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function timeValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})[:.](\d{2})/', $value, $match)) {
            return sprintf('%02d:%02d:00', (int) $match[1], (int) $match[2]);
        }
        if (is_numeric($value) && (float) $value > 0 && (float) $value < 1) {
            $seconds = (int) round((float) $value * 86400);
            return gmdate('H:i:s', $seconds);
        }
        return null;
    }

    private function mealTypeValue(string $value): string
    {
        $key = $this->normalizeKey($value);
        return match (true) {
            str_contains($key, 'frueh') || str_contains($key, 'fruh') => 'fruehstueck',
            str_contains($key, 'mittag') => 'mittagessen',
            str_contains($key, 'abend') => 'abendessen',
            default => 'mittagessen',
        };
    }

    private function categoryValue(string $value, string $default): string
    {
        $key = $this->normalizeKey($value);
        return match (true) {
            str_contains($key, 'spiel') => 'spiel',
            str_contains($key, 'bibel') || str_contains($key, 'geist') => 'bibelarbeit',
            str_contains($key, 'essen') || str_contains($key, 'mahl') => 'mahlzeit',
            str_contains($key, 'wach') || str_contains($key, 'nacht') => 'wache',
            str_contains($key, 'lern') || str_contains($key, 'unterricht') => 'lernen',
            str_contains($key, 'wett') => 'wettbewerb',
            default => $key !== '' ? mb_substr($key, 0, 80) : $default,
        };
    }

    private function truthy(string $value): bool
    {
        return in_array($this->normalizeKey($value), ['1', 'ja', 'yes', 'true', 'x'], true);
    }

    private function personExists(PDO $pdo, string $displayName, ?string $birthdate): bool
    {
        if ($birthdate !== null) {
            $stmt = $pdo->prepare('SELECT id FROM persons WHERE display_name = :display_name AND birthdate = :birthdate AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['display_name' => $displayName, 'birthdate' => $birthdate]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM persons WHERE display_name = :display_name AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['display_name' => $displayName]);
        }
        return $stmt->fetchColumn() !== false;
    }

    private function orderIdByLabel(PDO $pdo, int $campYearId, string $label): ?int
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE camp_year_id = :camp_year_id AND (name = :label_name OR short_name = :label_short_name) AND is_active = 1 LIMIT 1');
        $stmt->execute(['camp_year_id' => $campYearId, 'label_name' => $label, 'label_short_name' => $label]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function splitName(string $display): array
    {
        $parts = preg_split('/\s+/', trim($display)) ?: [];
        if (count($parts) <= 1) {
            return [$display, ''];
        }
        $last = array_pop($parts);
        return [implode(' ', $parts), (string) $last];
    }

    private function slug(string $label): string
    {
        $slug = $this->normalizeKey($label);
        return $slug !== '' ? mb_substr($slug, 0, 80) : 'import_' . bin2hex(random_bytes(4));
    }

    private function rowHasValue(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }
        return false;
    }

    private function validateProfile(string $importKey): void
    {
        if (!array_key_exists($importKey, self::PROFILES)) {
            throw new RuntimeException('Importprofil ist unbekannt.');
        }
    }

    private function isAllowedMime(string $mime, string $ext): bool
    {
        if ($mime === 'application/zip' || $mime === 'application/octet-stream') {
            return true;
        }
        $allowed = [
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'ods' => [
                'application/vnd.oasis.opendocument.spreadsheet',
            ],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ];
        return in_array($mime, $allowed[$ext] ?? [], true);
    }

    private function absoluteStoragePath(string $storagePath): string
    {
        $storagePath = ltrim(str_replace(['..', '\\'], ['', '/'], $storagePath), '/');
        return storage_path($storagePath);
    }

    private function markRunning(PDO $pdo, int $runId): void
    {
        $stmt = $pdo->prepare("UPDATE import_runs SET status = 'running', started_at = NOW(), finished_at = NULL WHERE id = :id");
        $stmt->execute(['id' => $runId]);
    }

    private function markFailed(int $runId, string $message): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("UPDATE import_runs SET status = 'failed', finished_at = NOW(), error_rows = error_rows + 1 WHERE id = :id");
            $stmt->execute(['id' => $runId]);
            $this->persistPreviewErrors($runId, [[
                'source_row_number' => null,
                'field_name' => 'execution',
                'error_text' => $message,
                'raw_row_json' => null,
            ]]);
        } catch (Throwable) {
            // Already logged by caller where relevant.
        }
    }

    private function persistPreviewErrors(int $runId, array $errors): void
    {
        $pdo = Database::connection();
        $this->replaceRunErrors($pdo, $runId, $errors);
    }

    private function replaceRunErrors(PDO $pdo, int $runId, array $errors): void
    {
        $delete = $pdo->prepare('DELETE FROM import_run_errors WHERE import_run_id = :import_run_id');
        $delete->execute(['import_run_id' => $runId]);
        if ($errors === []) {
            return;
        }
        $stmt = $pdo->prepare("INSERT INTO import_run_errors
            (import_run_id, source_row_number, field_name, error_text, raw_row_json, created_at)
            VALUES (:import_run_id, :source_row_number, :field_name, :error_text, :raw_row_json, NOW())");
        foreach ($errors as $error) {
            $stmt->execute([
                'import_run_id' => $runId,
                'source_row_number' => $error['source_row_number'] ?? null,
                'field_name' => $error['field_name'] ?? null,
                'error_text' => (string) ($error['error_text'] ?? 'Unbekannter Importfehler'),
                'raw_row_json' => $error['raw_row_json'] ?? null,
            ]);
        }
    }

    private function runErrors(int $runId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM import_run_errors WHERE import_run_id = :import_run_id ORDER BY id ASC');
        $stmt->execute(['import_run_id' => $runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return isset($user['user_id']) ? (int) $user['user_id'] : null;
    }
}
