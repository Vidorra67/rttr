<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BundledImportService
{
    private const DEFAULT_ORDERS = [
        ['key' => 'johanniter', 'name' => 'Johanniter', 'short_name' => 'JOH', 'color_key' => 'blau', 'sort_order' => 10],
        ['key' => 'falkner', 'name' => 'Falkner', 'short_name' => 'FAL', 'color_key' => 'spiel', 'sort_order' => 20],
        ['key' => 'samariter', 'name' => 'Samariter', 'short_name' => 'SAM', 'color_key' => 'mint', 'sort_order' => 30],
        ['key' => 'petrusker', 'name' => 'Petrusker', 'short_name' => 'PET', 'color_key' => 'mahlzeit', 'sort_order' => 40],
        ['key' => 'morgensternritter', 'name' => 'Morgensternritter', 'short_name' => 'MOR', 'color_key' => 'wache', 'sort_order' => 50],
        ['key' => 'malteser', 'name' => 'Malteser', 'short_name' => 'MAL', 'color_key' => 'info', 'sort_order' => 60],
    ];

    private const STANDARD_RANKS = [
        'knappe' => ['label' => 'Knappe', 'sort_order' => 10, 'promotion_points_required' => 310, 'next_rank_key' => 'ritter'],
        'ritter' => ['label' => 'Ritter', 'sort_order' => 20, 'promotion_points_required' => 320, 'next_rank_key' => 'freiherr'],
        'freiherr' => ['label' => 'Freiherr', 'sort_order' => 30, 'promotion_points_required' => 330, 'next_rank_key' => 'graf'],
        'graf' => ['label' => 'Graf', 'sort_order' => 40, 'promotion_points_required' => 340, 'next_rank_key' => 'markgraf'],
        'markgraf' => ['label' => 'Markgraf', 'sort_order' => 50, 'promotion_points_required' => 345, 'next_rank_key' => 'landgraf'],
        'landgraf' => ['label' => 'Landgraf', 'sort_order' => 60, 'promotion_points_required' => 350, 'next_rank_key' => 'fuerst'],
        'fuerst' => ['label' => 'Fürst', 'sort_order' => 70, 'promotion_points_required' => 280, 'next_rank_key' => 'herzog'],
        'herzog' => ['label' => 'Herzog', 'sort_order' => 80, 'promotion_points_required' => null, 'next_rank_key' => 'grossherzog'],
        'grossherzog' => ['label' => 'Großherzog', 'sort_order' => 90, 'promotion_points_required' => null, 'next_rank_key' => null],
    ];

    private const ORDER_ALIASES = [
        'johanniter' => 'Johanniter',
        'johanniter6' => 'Johanniter',
        'johanniter7' => 'Johanniter',
        'joh' => 'Johanniter',
        'falkner' => 'Falkner',
        'falkner1' => 'Falkner',
        'fal' => 'Falkner',
        'samariter' => 'Samariter',
        'sam' => 'Samariter',
        'petrusker' => 'Petrusker',
        'pet' => 'Petrusker',
        'morgensternritter' => 'Morgensternritter',
        'morgenstern' => 'Morgensternritter',
        'morgis' => 'Morgensternritter',
        'mor' => 'Morgensternritter',
        'malteser' => 'Malteser',
        'mal' => 'Malteser',
    ];

    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly CampYearService $campYearService = new CampYearService()
    ) {
    }

    public function templates(): array
    {
        return [
            'default_orders_active' => [
                'label' => '6 Standardorden im aktiven Lagerjahr anlegen',
                'description' => 'Johanniter, Falkner, Samariter, Petrusker, Morgensternritter und Malteser werden für das aktive Lagerjahr angelegt. Nicht genutzte Orden können deaktiviert werden.',
                'source' => 'Systemvorlage',
            ],
            'zeltlager_manager_2025' => [
                'label' => 'Zeltlager Manager 2025 importieren',
                'description' => 'Übernimmt Teilnehmer, Orden/Zelte, Ränge, Beinamen, Lerneinheiten und Punktesummen aus der gelieferten XLSX-Datei in das Lagerjahr 2025.',
                'source' => 'Zeltlager 2025 Manager.xlsx',
            ],
            'zeltlager_manager_2000_dummy' => [
                'label' => 'Dummy-Lagerjahr 2000 aus 2025 erzeugen',
                'description' => 'Nutzt dieselben Daten wie 2025, ordnet sie aber einem Test-Lagerjahr 2000 zu. Bestehende Personen werden wiederverwendet.',
                'source' => 'Zeltlager 2025 Manager.xlsx',
            ],
            'program_2026_docx' => [
                'label' => 'Programm 2026 importieren',
                'description' => 'Übernimmt den Tagesablauf aus Ritterlagerprogramm 2026.docx in das aktive Lagerjahr oder erstellt ein Lagerjahr 2026.',
                'source' => 'Ritterlagerprogramm 2026.docx',
            ],
            'meals_2026_ods' => [
                'label' => 'Speiseplan 2026 importieren',
                'description' => 'Übernimmt Frühstück, Mittagessen und Abendessen aus Speiseplan_2026.ods in das aktive Lagerjahr oder erstellt ein Lagerjahr 2026.',
                'source' => 'Speiseplan_2026.ods',
            ],
            'duties_2026_docx' => [
                'label' => 'Dienste und Aufgabenverteilung 2026 importieren',
                'description' => 'Übernimmt Dienstarten aus Aufgabenverteilung 2026.docx und Platzdienst/Nachtwache aus dem Programm.',
                'source' => 'Aufgabenverteilung 2026.docx + Ritterlagerprogramm 2026.docx',
            ],
        ];
    }

    public function execute(string $templateKey): array
    {
        if (!array_key_exists($templateKey, $this->templates())) {
            throw new RuntimeException('Importvorlage ist unbekannt.');
        }

        $pdo = Database::connection();
        $runId = $this->createRun($pdo, $templateKey);
        $pdo->beginTransaction();

        try {
            $result = match ($templateKey) {
                'default_orders_active' => $this->importDefaultOrdersForActiveCampYear($pdo),
                'zeltlager_manager_2025' => $this->importZeltlagerManager($pdo, 2025, false),
                'zeltlager_manager_2000_dummy' => $this->importZeltlagerManager($pdo, 2000, true),
                'program_2026_docx' => $this->importProgram2026($pdo),
                'meals_2026_ods' => $this->importMeals2026($pdo),
                'duties_2026_docx' => $this->importDuties2026($pdo),
                default => throw new RuntimeException('Importvorlage ist unbekannt.'),
            };

            $this->finishRun($pdo, $runId, 'ok', $result);
            $pdo->commit();

            $this->auditService->record('imports.template_executed', $this->currentUserId(), 'import_run', $runId, [
                'template_key' => $templateKey,
                'imported_rows' => $result['imported_rows'] ?? 0,
                'skipped_rows' => $result['skipped_rows'] ?? 0,
            ]);

            return $result + ['run_id' => $runId, 'status' => 'ok'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->markFailed($runId, $exception->getMessage());
            throw $exception;
        }
    }

    public function ensureDefaultOrdersForCampYear(PDO $pdo, int $campYearId): array
    {
        $inserted = 0;
        $skipped = 0;
        $stmt = $pdo->prepare("INSERT INTO orders
            (camp_year_id, name, short_name, color_key, sort_order, is_active, created_at, updated_at)
            VALUES (:camp_year_id, :name, :short_name, :color_key, :sort_order, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = updated_at");

        foreach (self::DEFAULT_ORDERS as $order) {
            $before = $this->orderIdByName($pdo, $campYearId, $order['name']);
            $stmt->execute([
                'camp_year_id' => $campYearId,
                'name' => $order['name'],
                'short_name' => $order['short_name'],
                'color_key' => $order['color_key'],
                'sort_order' => $order['sort_order'],
            ]);
            $before === null ? $inserted++ : $skipped++;
        }

        return ['imported_rows' => $inserted, 'skipped_rows' => $skipped, 'error_rows' => 0];
    }

    private function importDefaultOrdersForActiveCampYear(PDO $pdo): array
    {
        $campYear = $this->campYearService->active();
        if ($campYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }
        $result = $this->ensureDefaultOrdersForCampYear($pdo, (int) $campYear['id']);
        $result['message'] = 'Standardorden wurden für das aktive Lagerjahr geprüft.';
        return $result;
    }

    private function importZeltlagerManager(PDO $pdo, int $year, bool $dummy): array
    {
        $path = $this->sourcePath('Zeltlager 2025 Manager.xlsx');
        $campYearId = $this->ensureCampYear($pdo, $year, $dummy ? 'Ritterlager 2000 Dummy' : 'Ritterlager 2025', $year . '-07-28', $year . '-08-03', false);
        $this->ensureDefaultOrdersForCampYear($pdo, $campYearId);
        $this->ensureStandardRanksForCampYear($pdo, $campYearId);
        $sheets = $this->readXlsxWorkbook($path);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $sourceYear = $dummy ? 2025 : $year;
        foreach ($this->extractRegistrations($sheets['Anmeldungen'] ?? [], $sourceYear) as $row) {
            try {
                $personId = $this->ensurePerson($pdo, $row['name'], $row['birthdate'], $row['rank'], $row['beiname']);
                $orderId = $this->orderIdByName($pdo, $campYearId, $row['order_name']);
                if ($orderId === null) {
                    $orderId = $this->ensureOrderByName($pdo, $campYearId, $row['order_name']);
                }
                if ($this->ensureCampPersonStatus($pdo, $campYearId, $personId, true, false, $orderId, $row['rank'])) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $errors[] = ['row' => $row['source_row'] ?? null, 'message' => $exception->getMessage()];
            }
        }

        $imported += $this->importRankHistory($pdo, $campYearId, $sheets['Anmeldunghistorie'] ?? [], $sourceYear);
        $imported += $this->importRankAndLearningUnits($pdo, $campYearId, $sheets['Rangordnung..Lerneinheiten'] ?? []);
        $imported += $this->importOrderPointSummaries($pdo, $campYearId, $sheets);

        return [
            'camp_year_id' => $campYearId,
            'total_rows' => $imported + $skipped + count($errors),
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'error_rows' => count($errors),
            'errors' => $errors,
            'message' => $dummy ? 'Dummy-Lagerjahr 2000 wurde aus den 2025-Daten inklusive Rängen und Beinamen erzeugt.' : 'Zeltlager Manager 2025 wurde inklusive Beinamen und jeweils höchstem/latest bekannten Rang aus der Anmeldehistorie importiert.',
        ];
    }

    private function importProgram2026(PDO $pdo): array
    {
        $campYearId = $this->campYearForImportYear($pdo, 2026, 'Ritterlager 2026', '2026-07-25', '2026-08-01');
        $days = $this->campDates($pdo, $campYearId);
        $table = $this->readDocxFirstTable($this->sourcePath('Ritterlagerprogramm 2026.docx'));
        $imported = 0;
        $skipped = 0;

        $dayCount = min(count($days), count($table[0] ?? $days));

        foreach (array_slice($days, 0, $dayCount) as $dateIndex => $date) {
            $responsible = $this->cleanText((string) ($table[1][$dateIndex] ?? ''));

            foreach ($this->lines((string) ($table[2][0] ?? '')) as $line) {
                $category = str_contains($this->normalizeKey($line), 'fruehstueck') ? 'mahlzeit' : 'info';
                if ($this->insertProgramLine($pdo, $campYearId, $date, $line, $category)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }

            $mainMorning = $this->cleanText((string) ($table[4][$dateIndex] ?? ''));
            if ($mainMorning !== '' && $mainMorning !== '-') {
                $time = $this->inferTime($mainMorning, '09:30:00');
                if ($this->insertProgramItem($pdo, $campYearId, $date, $time, $this->stripLeadingTime($mainMorning), $this->programCategory($mainMorning), '', $responsible !== '' ? $responsible : null, null)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }

            foreach ($this->lines((string) ($table[5][0] ?? '')) as $line) {
                $lineKey = $this->normalizeKey($line);
                if (str_contains($lineKey, 'mittagessen')) {
                    $title = $this->stripLeadingTime($line);
                    $category = 'mahlzeit';
                    $fallback = '12:30:00';
                } elseif (str_starts_with($lineKey, 'danach') || str_contains($lineKey, 'mittagspause')) {
                    $title = preg_replace('/^Danach:\s*/iu', '', $line) ?? $line;
                    $category = 'freizeit';
                    $fallback = '13:00:00';
                } else {
                    $title = $line;
                    $category = 'info';
                    $fallback = '13:00:00';
                }

                if ($this->insertProgramItem($pdo, $campYearId, $date, $fallback, $title, $category, '', null, null)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }

            $afternoon = $this->cleanText((string) ($table[6][$dateIndex] ?? ''));
            if ($afternoon !== '') {
                foreach ($this->splitProgramCell($afternoon) as $line) {
                    if ($this->insertProgramLine($pdo, $campYearId, $date, $line, $this->programCategory($line), '14:30:00', $responsible !== '' ? $responsible : null)) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                }
            }

            foreach ($this->lines((string) ($table[7][0] ?? '')) as $line) {
                if ($this->insertProgramLine($pdo, $campYearId, $date, $line, 'mahlzeit')) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }

            $evening = $this->cleanText((string) ($table[8][$dateIndex] ?? ''));
            if ($evening !== '') {
                foreach ($this->splitProgramCell($evening) as $line) {
                    if ($this->insertProgramLine($pdo, $campYearId, $date, $line, $this->programCategory($line), '19:30:00', $responsible !== '' ? $responsible : null)) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                }
            }

            foreach ($this->lines((string) ($table[10][0] ?? '')) as $line) {
                if ($this->insertProgramLine($pdo, $campYearId, $date, $line, str_contains(mb_strtolower($line), 'nacht') ? 'nachtruhe' : 'info')) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'camp_year_id' => $campYearId,
            'total_rows' => $imported + $skipped,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'error_rows' => 0,
            'message' => 'Programm 2026 wurde aus dem DOCX übernommen. Mittagspause wird jetzt um 13:00 Uhr angelegt.',
        ];
    }

    private function importMeals2026(PDO $pdo): array
    {
        $campYearId = $this->campYearForImportYear($pdo, 2026, 'Ritterlager 2026', '2026-07-25', '2026-08-01');
        $days = $this->campDates($pdo, $campYearId);
        $rows = $this->readOdsFirstTable($this->sourcePath('Speiseplan_2026.ods'));
        $lunchTitles = $this->mealTitlesFromRows($rows, 'Mittagessen');
        $dinnerTitles = $this->mealTitlesFromRows($rows, 'Abendessen');

        $this->cleanupImportedMeals($pdo, $campYearId);

        $imported = 0;
        $skipped = 0;
        foreach ($days as $index => $date) {
            if ($this->insertMeal($pdo, $campYearId, $date, 'fruehstueck', '08:40:00', 'Frühstück', 'Import aus Speiseplan 2026')) {
                $imported++;
            } else {
                $skipped++;
            }
            $lunch = $lunchTitles[$index] ?? null;
            if ($lunch !== null && $lunch !== '') {
                if ($this->insertMeal($pdo, $campYearId, $date, 'mittagessen', '12:30:00', $lunch, 'Import aus Speiseplan 2026')) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
            $dinner = $dinnerTitles[$index] ?? null;
            if ($dinner !== null && $dinner !== '') {
                if ($this->insertMeal($pdo, $campYearId, $date, 'abendessen', '18:00:00', $dinner, 'Import aus Speiseplan 2026')) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'camp_year_id' => $campYearId,
            'total_rows' => $imported + $skipped,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'error_rows' => 0,
            'message' => 'Speiseplan 2026 wurde aus ODS übernommen. Alte Speiseplan-Importeinträge wurden vorher deaktiviert, damit keine Mehrfacheinträge entstehen.',
        ];
    }

    private function importDuties2026(PDO $pdo): array
    {
        $campYearId = $this->campYearForImportYear($pdo, 2026, 'Ritterlager 2026', '2026-07-25', '2026-08-01');
        $days = $this->campDates($pdo, $campYearId);
        $this->ensureDefaultOrdersForCampYear($pdo, $campYearId);
        $this->markLegacyImportDutiesDeleted($pdo, $campYearId);

        $imported = 0;
        $skipped = 0;
        $taskTable = $this->readDocxFirstTable($this->sourcePath('Aufgabenverteilung 2026.docx'));
        foreach ($taskTable as $row) {
            for ($i = 0; $i < count($row); $i += 2) {
                $label = $this->cleanText((string) ($row[$i] ?? ''));
                if ($label === '' || in_array($label, ['Funktionen', 'Aufgaben'], true) || $this->looksLikeXmlFragment($label)) {
                    continue;
                }
                $before = $this->dutyTypeIdByLabel($pdo, $label);
                $this->ensureDutyType($pdo, $label, $this->iconForDuty($label), 'nach Bedarf');
                $before === null ? $imported++ : $skipped++;
            }
        }

        $programTable = $this->readDocxFirstTable($this->sourcePath('Ritterlagerprogramm 2026.docx'));
        $dutyRow = $programTable[9] ?? [];
        $platzdienstTypeId = $this->ensureDutyType($pdo, 'Platzdienst', 'delete', 'ganztägig');
        $nachtwacheTypeId = $this->ensureDutyType($pdo, 'Nachtwache', 'dark_mode', '22:00 - 06:00');
        foreach ($days as $index => $date) {
            $label = $this->cleanText((string) ($dutyRow[$index] ?? ''));
            if ($label === '' || str_contains($this->normalizeKey($label), 'nachtwache') || $this->looksLikeXmlFragment($label)) {
                continue;
            }

            $assigned = false;
            $dutyId = $this->ensureDuty($pdo, $campYearId, $date, $platzdienstTypeId, 'Platzdienst', 'ganztägig', null, null, 'Import Programm 2026');
            if ($dutyId <= 0) {
                $skipped++;
                continue;
            }
            foreach ($this->splitOrderNames($label) as $orderName) {
                $orderId = $this->orderIdByName($pdo, $campYearId, $orderName);
                if ($orderId !== null) {
                    $assigned = $this->ensureDutyAssignmentOrder($pdo, $dutyId, $orderId) || $assigned;
                }
            }
            if (!$assigned) {
                $assigned = $this->ensureDutyAssignmentLabel($pdo, $dutyId, $label);
            }
            $assigned ? $imported++ : $skipped++;

            $nightDutyId = $this->ensureDuty($pdo, $campYearId, $date, $nachtwacheTypeId, 'Nachtwache', '22:00 - 06:00', '22:00:00', null, 'Import Programm 2026');
            if ($nightDutyId <= 0) {
                $skipped++;
                continue;
            }
            $nightAssigned = false;
            foreach ($this->splitOrderNames($label) as $orderName) {
                $orderId = $this->orderIdByName($pdo, $campYearId, $orderName);
                if ($orderId !== null) {
                    $nightAssigned = $this->ensureDutyAssignmentOrder($pdo, $nightDutyId, $orderId) || $nightAssigned;
                }
            }
            if (!$nightAssigned) {
                $nightAssigned = $this->ensureDutyAssignmentLabel($pdo, $nightDutyId, $label);
            }
            $nightAssigned ? $imported++ : $skipped++;
        }

        return [
            'camp_year_id' => $campYearId,
            'total_rows' => $imported + $skipped,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'error_rows' => 0,
            'message' => 'Dienstarten wurden aus Aufgabenverteilung übernommen. Tagesdienste werden nur aus Platzdienst/Nachtwache des Programms erzeugt.',
        ];
    }

    private function ensureCampYear(PDO $pdo, int $year, string $name, string $startsOn, string $endsOn, bool $active): int
    {
        $stmt = $pdo->prepare('SELECT id FROM camp_years WHERE name = :name OR YEAR(starts_on) = :year ORDER BY id ASC LIMIT 1');
        $stmt->execute(['name' => $name, 'year' => $year]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
        if ($active) {
            $pdo->exec('UPDATE camp_years SET is_active = 0, updated_at = NOW() WHERE is_active = 1');
        }
        $insert = $pdo->prepare("INSERT INTO camp_years (name, location_name, starts_on, ends_on, is_active, created_at, updated_at)
            VALUES (:name, 'Prinzbach', :starts_on, :ends_on, :is_active, NOW(), NOW())");
        $insert->execute(['name' => $name, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'is_active' => $active ? 1 : 0]);
        return (int) $pdo->lastInsertId();
    }

    private function activeOrCreate2026(PDO $pdo): int
    {
        return $this->campYearForImportYear($pdo, 2026, 'Ritterlager 2026', '2026-07-25', '2026-08-01');
    }

    private function campYearForImportYear(PDO $pdo, int $year, string $name, string $startsOn, string $endsOn): int
    {
        $stmt = $pdo->prepare('SELECT id FROM camp_years WHERE YEAR(starts_on) = :year OR name LIKE :name_like ORDER BY is_active DESC, id ASC LIMIT 1');
        $stmt->execute(['year' => $year, 'name_like' => '%' . (string) $year . '%']);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            $id = (int) $existing;
            $this->ensureDefaultOrdersForCampYear($pdo, $id);
            return $id;
        }

        $id = $this->ensureCampYear($pdo, $year, $name, $startsOn, $endsOn, true);
        $this->ensureDefaultOrdersForCampYear($pdo, $id);
        return $id;
    }

    private function campDates(PDO $pdo, int $campYearId): array
    {
        $stmt = $pdo->prepare('SELECT starts_on, ends_on FROM camp_years WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campYearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }
        $start = new DateTimeImmutable((string) $row['starts_on']);
        $end = new DateTimeImmutable((string) $row['ends_on']);
        $dates = [];
        while ($start <= $end && count($dates) < 14) {
            $dates[] = $start->format('Y-m-d');
            $start = $start->modify('+1 day');
        }
        return $dates;
    }

    private function extractRegistrations(array $rows, int $sourceYear): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $yearValue = trim((string) ($row[0] ?? ''));
            if ($yearValue !== '' && (int) round((float) str_replace(',', '.', $yearValue)) !== $sourceYear) {
                continue;
            }

            $name = trim((string) ($row[2] ?? ''));
            $rank = trim((string) ($row[3] ?? ''));
            $order = trim((string) ($row[4] ?? ''));
            if ($name === '' || $name === 'Name' || $order === '' || !preg_match('/[A-Za-zÄÖÜäöüß]/u', $name)) {
                continue;
            }
            $out[] = [
                'source_row' => $index + 1,
                'name' => $name,
                'rank' => $rank,
                'order_name' => $this->normalizeOrderName($order),
                'beiname' => trim((string) ($row[5] ?? '')),
                'birthdate' => $this->dateValue(trim((string) ($row[6] ?? ''))),
            ];
        }
        return $out;
    }

    private function ensurePerson(PDO $pdo, string $name, ?string $birthdate, string $rank, string $beiname): int
    {
        $normalizedName = $this->normalizePersonName($name);
        if ($normalizedName === '') {
            throw new RuntimeException('Person ohne Namen kann nicht importiert werden.');
        }

        $id = $this->findExistingImportPersonId($pdo, $name, $birthdate);
        if ($id !== null) {
            $updates = [];
            $params = ['id' => $id];
            if ($birthdate !== null && $this->isPlausibleBirthdate($birthdate)) {
                $updates[] = "birthdate = CASE
                    WHEN birthdate IS NULL OR birthdate < '1930-01-01' OR birthdate > CURRENT_DATE()
                    THEN :birthdate
                    ELSE birthdate
                END";
                $params['birthdate'] = $birthdate;
            }
            if (trim($beiname) !== '') {
                $updates[] = "nickname = COALESCE(NULLIF(nickname, ''), :nickname)";
                $params['nickname'] = trim($beiname);
            }
            if ($updates !== []) {
                $updates[] = 'updated_at = NOW()';
                $updatePerson = $pdo->prepare('UPDATE persons SET ' . implode(', ', $updates) . ' WHERE id = :id');
                $updatePerson->execute($params);
            }
            return $id;
        }

        [$first, $last] = $this->splitName($name);
        $notes = trim($beiname) !== '' ? 'Beiname: ' . trim($beiname) : null;
        $insert = $pdo->prepare("INSERT INTO persons
            (first_name, last_name, display_name, nickname, birthdate, type_hint, internal_notes, is_active, created_at, updated_at)
            VALUES (:first_name, :last_name, :display_name, :nickname, :birthdate, 'teilnehmer', :internal_notes, 1, NOW(), NOW())");
        $insert->execute([
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => $this->displayNameForImport($name),
            'nickname' => trim($beiname) !== '' ? trim($beiname) : null,
            'birthdate' => ($birthdate !== null && $this->isPlausibleBirthdate($birthdate)) ? $birthdate : null,
            'internal_notes' => $notes,
        ]);
        return (int) $pdo->lastInsertId();
    }

    private function findExistingImportPersonId(PDO $pdo, string $name, ?string $birthdate): ?int
    {
        $incomingNormalized = $this->normalizePersonName($name);
        $incomingTokenKey = $this->personTokenKey($incomingNormalized);

        $stmt = $pdo->query("SELECT id, display_name, first_name, last_name, birthdate, nickname, is_active
            FROM persons
            WHERE deleted_at IS NULL
            ORDER BY is_active DESC, id ASC");
        $bestId = null;
        $bestScore = PHP_INT_MAX;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $person) {
            $displayName = trim((string) ($person['display_name'] ?? ''));
            $fallbackName = trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
            $candidateNormalized = $this->normalizePersonName($displayName !== '' ? $displayName : $fallbackName);
            if ($candidateNormalized === '') {
                continue;
            }

            $candidateBirthdate = $person['birthdate'] ?? null;
            $sameBirthdate = $birthdate !== null && $candidateBirthdate !== null && $birthdate === $candidateBirthdate;
            $oneBirthdateMissing = $birthdate === null || $candidateBirthdate === null;
            $candidateTokenKey = $this->personTokenKey($candidateNormalized);

            $score = null;
            if ($candidateNormalized === $incomingNormalized && $sameBirthdate) {
                $score = 0;
            } elseif ($candidateNormalized === $incomingNormalized && $oneBirthdateMissing) {
                $score = 10;
            } elseif ($sameBirthdate && $candidateTokenKey !== '' && $candidateTokenKey === $incomingTokenKey) {
                $score = 20;
            } elseif ($candidateNormalized === $incomingNormalized) {
                $score = 30;
            }

            if ($score !== null && $score < $bestScore) {
                $bestScore = $score;
                $bestId = (int) $person['id'];
            }
        }

        return $bestId;
    }

    private function displayNameForImport(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        return $name;
    }

    private function normalizePersonName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
        $name = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        return $name;
    }

    private function personTokenKey(string $normalizedName): string
    {
        $tokens = array_values(array_filter(explode(' ', $normalizedName), static fn (string $token): bool => $token !== ''));
        if (count($tokens) < 2) {
            return $normalizedName;
        }
        sort($tokens, SORT_STRING);
        return implode(' ', $tokens);
    }

    private function ensureCampPersonStatus(PDO $pdo, int $campYearId, int $personId, bool $participant, bool $staff, ?int $orderId, string $rankLabel): bool
    {
        $incomingLabel = $this->normalizeRankLabel($rankLabel);
        $incomingRankLevelId = $this->rankLevelIdByLabel($pdo, $campYearId, $rankLabel);
        $incomingSort = $this->rankSortOrderByLabel($pdo, $campYearId, $rankLabel);

        $existingStmt = $pdo->prepare("SELECT cps.id, cps.rank_label, cps.rank_level_id, rl.sort_order AS rank_sort_order
            FROM camp_person_statuses cps
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            WHERE cps.camp_year_id = :camp_year_id AND cps.person_id = :person_id
            LIMIT 1");
        $existingStmt->execute(['camp_year_id' => $campYearId, 'person_id' => $personId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($existing)) {
            $insert = $pdo->prepare("INSERT INTO camp_person_statuses
                (camp_year_id, person_id, is_participant, is_staff, participant_status, staff_status, order_id, rank_label, rank_level_id, created_at, updated_at)
                VALUES (:camp_year_id, :person_id, :is_participant, :is_staff, 'angemeldet', 'aktiv', :order_id, :rank_label, :rank_level_id, NOW(), NOW())");
            $insert->execute([
                'camp_year_id' => $campYearId,
                'person_id' => $personId,
                'is_participant' => $participant ? 1 : 0,
                'is_staff' => $staff ? 1 : 0,
                'order_id' => $orderId,
                'rank_label' => $incomingLabel,
                'rank_level_id' => $incomingRankLevelId,
            ]);
            return $insert->rowCount() > 0;
        }

        $existingSort = $existing['rank_sort_order'] !== null
            ? (int) $existing['rank_sort_order']
            : $this->rankSortOrderByLabel($pdo, $campYearId, (string) ($existing['rank_label'] ?? ''));

        $rankShouldUpdate = $incomingSort !== null && ($existingSort === null || $incomingSort >= $existingSort);
        $rankLabelForUpdate = $rankShouldUpdate ? $incomingLabel : ($existing['rank_label'] ?? null);
        $rankLevelIdForUpdate = $rankShouldUpdate ? $incomingRankLevelId : ($existing['rank_level_id'] ?? null);

        $update = $pdo->prepare("UPDATE camp_person_statuses
            SET is_participant = GREATEST(is_participant, :is_participant),
                is_staff = GREATEST(is_staff, :is_staff),
                order_id = COALESCE(:order_id, order_id),
                rank_label = :rank_label,
                rank_level_id = :rank_level_id,
                updated_at = NOW()
            WHERE id = :id");
        $update->execute([
            'id' => (int) $existing['id'],
            'is_participant' => $participant ? 1 : 0,
            'is_staff' => $staff ? 1 : 0,
            'order_id' => $orderId,
            'rank_label' => $rankLabelForUpdate,
            'rank_level_id' => $rankLevelIdForUpdate,
        ]);
        return $update->rowCount() > 0;
    }


    private function importRankHistory(PDO $pdo, int $campYearId, array $rows, int $sourceYear): int
    {
        if ($rows === []) {
            return 0;
        }

        $yearColumns = [];
        foreach ($rows as $row) {
            foreach ($row as $columnIndex => $cell) {
                $year = $this->yearFromCell($cell);
                if ($year !== null && $year >= 1900 && $year <= 2100) {
                    $yearColumns[$year] = (int) $columnIndex;
                }
            }
            if ($yearColumns !== []) {
                break;
            }
        }
        ksort($yearColumns);
        if ($yearColumns === []) {
            return 0;
        }

        $candidateYears = array_values(array_filter(array_keys($yearColumns), static fn (int $year): bool => $year <= $sourceYear));
        if ($candidateYears === []) {
            return 0;
        }

        $imported = 0;
        foreach ($rows as $rowIndex => $row) {
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '' || $name === 'Name' || !preg_match('/[A-Za-zÄÖÜäöüß]/u', $name)) {
                continue;
            }

            $history = $this->latestRankHistoryEntry($row, $yearColumns, $candidateYears);
            if ($history === null) {
                continue;
            }

            $beiname = trim((string) ($row[1] ?? ''));
            $birthdate = $this->dateValue(trim((string) ($row[2] ?? '')));
            $rank = $history['rank'];
            $order = $history['order'];

            $personId = $this->ensurePerson($pdo, $name, $birthdate, $rank, $beiname);
            $orderId = null;
            if ($order !== '') {
                $orderId = $this->orderIdByName($pdo, $campYearId, $this->normalizeOrderName($order));
                if ($orderId === null) {
                    $orderId = $this->ensureOrderByName($pdo, $campYearId, $this->normalizeOrderName($order));
                }
            }

            if ($this->ensureCampPersonStatus($pdo, $campYearId, $personId, true, false, $orderId, $rank)) {
                $imported++;
            }
        }

        return $imported;
    }

    private function latestRankHistoryEntry(array $row, array $yearColumns, array $candidateYears): ?array
    {
        $latest = null;
        foreach ($candidateYears as $year) {
            $rankColumn = $yearColumns[$year];
            $orderColumn = $rankColumn + 1;
            $rank = trim((string) ($row[$rankColumn] ?? ''));
            $order = trim((string) ($row[$orderColumn] ?? ''));
            if ($rank === '' && $order === '') {
                continue;
            }
            if ($rank !== '') {
                $latest = ['year' => $year, 'rank' => $rank, 'order' => $order];
                continue;
            }
            if ($latest !== null && $order !== '') {
                $latest['order'] = $order;
                $latest['year'] = $year;
            }
        }
        return $latest;
    }


    private function yearFromCell(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return null;
        }
        $year = (int) round((float) $normalized);
        return $year >= 1900 && $year <= 2100 ? $year : null;
    }

    private function importRankAndLearningUnits(PDO $pdo, int $campYearId, array $rows): int
    {
        $imported = 0;
        foreach ($rows as $row) {
            $rank = trim((string) ($row[1] ?? ''));
            if ($rank !== '' && $rank !== 'Rang') {
                $key = $this->normalizeRankKey($rank);
                if ($key !== null) {
                    $pointsRequired = $this->numericOrNull($row[3] ?? null);
                    $stmt = $pdo->prepare("UPDATE rank_levels SET promotion_points_required = COALESCE(:points_required, promotion_points_required), updated_at = NOW()
                        WHERE camp_year_id = :camp_year_id AND key_name = :key_name");
                    $stmt->execute(['camp_year_id' => $campYearId, 'key_name' => $key, 'points_required' => $pointsRequired]);
                    $imported += $stmt->rowCount() > 0 ? 1 : 0;
                }
            }
            $unit = trim((string) ($row[5] ?? ''));
            if ($unit !== '' && $unit !== 'Lerneinheit') {
                $stmt = $pdo->prepare("INSERT INTO learning_units (camp_year_id, title, category_key, responsible_label, sort_order, created_at, updated_at)
                    SELECT :camp_year_id, :title, 'lernen', :responsible_label, 100, NOW(), NOW()
                    WHERE NOT EXISTS (SELECT 1 FROM learning_units WHERE camp_year_id = :camp_year_id_check AND title = :title_check AND deleted_at IS NULL)");
                $stmt->execute([
                    'camp_year_id' => $campYearId,
                    'title' => $unit,
                    'responsible_label' => trim((string) ($row[6] ?? '')) ?: null,
                    'camp_year_id_check' => $campYearId,
                    'title_check' => $unit,
                ]);
                $imported += $stmt->rowCount() > 0 ? 1 : 0;
            }
        }
        return $imported;
    }

    private function importOrderPointSummaries(PDO $pdo, int $campYearId, array $sheets): int
    {
        $imported = 0;
        foreach (self::DEFAULT_ORDERS as $order) {
            $rows = $sheets[$order['name']] ?? [];
            $orderId = $this->orderIdByName($pdo, $campYearId, $order['name']);
            if ($rows === [] || $orderId === null) {
                continue;
            }
            $categoryIds = $this->pointCategoryIds($pdo);
            foreach ($rows as $row) {
                $name = trim((string) ($row[0] ?? ''));
                if ($name === '' || in_array($name, ['Name', 'Team', 'GESAMT'], true) || preg_match('/^\d+\s/', $name)) {
                    continue;
                }
                $personId = $this->personIdByDisplayName($pdo, $name);
                if ($personId === null) {
                    continue;
                }
                $values = [
                    'ordnung' => (int) round((float) ($row[1] ?? 0)),
                    'spiel' => (int) round((float) ($row[2] ?? 0)),
                    'wettbewerb' => (int) round((float) ($row[4] ?? 0)),
                    'bonus' => (int) round((float) ($row[5] ?? 0)),
                ];
                foreach ($values as $key => $points) {
                    if ($points === 0 || !isset($categoryIds[$key])) {
                        continue;
                    }
                    if ($this->insertImportedPoint($pdo, $campYearId, $personId, $orderId, $categoryIds[$key], $points, 'Import Zeltlager Manager Punktesumme: ' . $key)) {
                        $imported++;
                    }
                }
            }
        }
        return $imported;
    }

    private function pointCategoryIds(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT key_name, id FROM point_categories');
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['key_name']] = (int) $row['id'];
        }
        return $out;
    }

    private function insertImportedPoint(PDO $pdo, int $campYearId, int $personId, int $orderId, int $categoryId, int $points, string $reason): bool
    {
        $exists = $pdo->prepare("SELECT id FROM point_entries WHERE camp_year_id = :camp_year_id AND person_id = :person_id AND category_id = :category_id AND source_type = 'import' AND reason = :reason LIMIT 1");
        $exists->execute(['camp_year_id' => $campYearId, 'person_id' => $personId, 'category_id' => $categoryId, 'reason' => $reason]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO point_entries (camp_year_id, person_id, order_id, category_id, points, reason, source_type, created_by, created_at)
            VALUES (:camp_year_id, :person_id, :order_id, :category_id, :points, :reason, 'import', :created_by, NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'person_id' => $personId,
            'order_id' => $orderId,
            'category_id' => $categoryId,
            'points' => $points,
            'reason' => $reason,
            'created_by' => $this->currentUserId(),
        ]);
        return true;
    }

    private function insertProgramLine(PDO $pdo, int $campYearId, string $date, string $line, string $category, ?string $fallbackTime = null, ?string $responsible = null): bool
    {
        $time = $this->inferTime($line, $fallbackTime ?? '09:00:00');
        return $this->insertProgramItem($pdo, $campYearId, $date, $time, $this->stripLeadingTime($line), $category, '', $responsible, null);
    }

    private function insertProgramItem(PDO $pdo, int $campYearId, string $date, ?string $time, string $title, string $category, string $location = '', ?string $responsible = null, ?string $description = null): bool
    {
        if (!$this->dateIsInCampYear($pdo, $campYearId, $date)) {
            return false;
        }
        $title = trim($title);
        if ($title === '') {
            return false;
        }
        $exists = $pdo->prepare("SELECT id FROM program_items WHERE camp_year_id = :camp_year_id AND program_date = :program_date AND title = :title AND COALESCE(starts_at, '') = COALESCE(:starts_at, '') AND deleted_at IS NULL LIMIT 1");
        $exists->execute(['camp_year_id' => $campYearId, 'program_date' => $date, 'title' => $title, 'starts_at' => $time]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO program_items
            (camp_year_id, program_date, starts_at, ends_at, title, category_key, location, responsible_label, description, sort_order, is_visible, created_by, updated_by, created_at, updated_at)
            VALUES (:camp_year_id, :program_date, :starts_at, NULL, :title, :category_key, :location, :responsible_label, :description, 100, 1, :created_by, :updated_by, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'program_date' => $date,
            'starts_at' => $time,
            'title' => mb_substr($title, 0, 190),
            'category_key' => $category,
            'location' => $location !== '' ? $location : null,
            'responsible_label' => $responsible !== null && trim($responsible) !== '' ? mb_substr(trim($responsible), 0, 190) : null,
            'description' => $description,
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ]);
        return true;
    }

    private function insertMeal(PDO $pdo, int $campYearId, string $date, string $mealType, string $time, string $title, string $description): bool
    {
        if (!$this->dateIsInCampYear($pdo, $campYearId, $date)) {
            return false;
        }
        $exists = $pdo->prepare('SELECT id FROM meal_items WHERE camp_year_id = :camp_year_id AND meal_date = :meal_date AND meal_type = :meal_type AND title = :title AND deleted_at IS NULL LIMIT 1');
        $exists->execute(['camp_year_id' => $campYearId, 'meal_date' => $date, 'meal_type' => $mealType, 'title' => $title]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO meal_items
            (camp_year_id, meal_date, meal_type, meal_time, title, portions_total, portions_vegetarian, allergy_notes, kitchen_team_label, description, created_by, updated_by, created_at, updated_at)
            VALUES (:camp_year_id, :meal_date, :meal_type, :meal_time, :title, 0, 0, NULL, 'Küchenteam', :description, :created_by, :updated_by, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'meal_date' => $date,
            'meal_type' => $mealType,
            'meal_time' => $time,
            'title' => mb_substr($title, 0, 190),
            'description' => $description,
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ]);
        return true;
    }


    private function dateIsInCampYear(PDO $pdo, int $campYearId, string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM camp_years WHERE id = :id AND :entry_date BETWEEN starts_on AND ends_on');
        $stmt->execute(['id' => $campYearId, 'entry_date' => $date]);
        return (int) $stmt->fetchColumn() > 0;
    }


    private function ensureStandardRanksForCampYear(PDO $pdo, int $campYearId): void
    {
        $stmt = $pdo->prepare("INSERT INTO rank_levels
            (camp_year_id, key_name, label, sort_order, promotion_points_required, next_rank_key, is_system_rank, created_at, updated_at)
            VALUES (:camp_year_id, :key_name, :label, :sort_order, :promotion_points_required, :next_rank_key, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                sort_order = VALUES(sort_order),
                promotion_points_required = VALUES(promotion_points_required),
                next_rank_key = VALUES(next_rank_key),
                is_system_rank = 1,
                updated_at = NOW()");
        foreach (self::STANDARD_RANKS as $key => $rank) {
            $stmt->execute([
                'camp_year_id' => $campYearId,
                'key_name' => $key,
                'label' => $rank['label'],
                'sort_order' => $rank['sort_order'],
                'promotion_points_required' => $rank['promotion_points_required'],
                'next_rank_key' => $rank['next_rank_key'],
            ]);
        }
    }

    private function rankLevelIdByLabel(PDO $pdo, int $campYearId, string $rankLabel): ?int
    {
        $key = $this->normalizeRankKey($rankLabel);
        if ($key === null) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $stmt->execute(['camp_year_id' => $campYearId, 'key_name' => $key]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function normalizeRankLabel(string $rankLabel): ?string
    {
        $key = $this->normalizeRankKey($rankLabel);
        if ($key === null) {
            $rankLabel = trim($rankLabel);
            return $rankLabel === '' ? null : $rankLabel;
        }
        return self::STANDARD_RANKS[$key]['label'] ?? trim($rankLabel);
    }

    private function normalizeRankKey(string $rankLabel): ?string
    {
        $value = trim($rankLabel);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/^\d+\s*/u', '', $value) ?? $value;
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? $value;
        return match ($value) {
            'knappe', 'kappe' => 'knappe',
            'ritter' => 'ritter',
            'freiherr' => 'freiherr',
            'graf' => 'graf',
            'markgraf' => 'markgraf',
            'landgraf' => 'landgraf',
            'fuerst', 'furst' => 'fuerst',
            'herzog' => 'herzog',
            'grossherzog', 'großherzog' => 'grossherzog',
            default => null,
        };
    }

    private function rankSortOrderByLabel(PDO $pdo, int $campYearId, string $rankLabel): ?int
    {
        $key = $this->normalizeRankKey($rankLabel);
        if ($key === null) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT sort_order FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $stmt->execute(['camp_year_id' => $campYearId, 'key_name' => $key]);
        $sort = $stmt->fetchColumn();
        if ($sort !== false) {
            return (int) $sort;
        }
        return self::STANDARD_RANKS[$key]['sort_order'] ?? null;
    }


    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', (string) $value);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function ensureDutyType(PDO $pdo, string $label, string $iconKey, string $defaultTimeLabel): int
    {
        $key = $this->slug($label);
        $stmt = $pdo->prepare('SELECT id FROM duty_types WHERE key_name = :key_name LIMIT 1');
        $stmt->execute(['key_name' => $key]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $insert = $pdo->prepare("INSERT INTO duty_types (key_name, label, icon_key, default_time_label, assignment_mode, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :label, :icon_key, :default_time_label, 'mixed', 1, 100, NOW(), NOW())");
        $insert->execute(['key_name' => $key, 'label' => $label, 'icon_key' => $iconKey, 'default_time_label' => $defaultTimeLabel]);
        return (int) $pdo->lastInsertId();
    }

    private function ensureDuty(PDO $pdo, int $campYearId, string $date, int $dutyTypeId, string $title, string $timeLabel, ?string $startsAt, ?string $endsAt, string $description): int
    {
        if (!$this->dateIsInCampYear($pdo, $campYearId, $date)) {
            return 0;
        }
        $exists = $pdo->prepare('SELECT id FROM duties WHERE camp_year_id = :camp_year_id AND duty_date = :duty_date AND duty_type_id = :duty_type_id AND title = :title AND deleted_at IS NULL LIMIT 1');
        $exists->execute(['camp_year_id' => $campYearId, 'duty_date' => $date, 'duty_type_id' => $dutyTypeId, 'title' => $title]);
        $id = $exists->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $stmt = $pdo->prepare("INSERT INTO duties
            (camp_year_id, duty_date, duty_type_id, starts_at, ends_at, time_label, title, description, status, created_by, updated_by, created_at, updated_at)
            VALUES (:camp_year_id, :duty_date, :duty_type_id, :starts_at, :ends_at, :time_label, :title, :description, 'offen', :created_by, :updated_by, NOW(), NOW())");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'duty_date' => $date,
            'duty_type_id' => $dutyTypeId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'time_label' => $timeLabel,
            'title' => $title,
            'description' => $description,
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ]);
        return (int) $pdo->lastInsertId();
    }

    private function ensureDutyAssignmentLabel(PDO $pdo, int $dutyId, string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }
        $exists = $pdo->prepare("SELECT id FROM duty_assignments WHERE duty_id = :duty_id AND assignee_type = 'label' AND label = :label LIMIT 1");
        $exists->execute(['duty_id' => $dutyId, 'label' => $label]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO duty_assignments (duty_id, assignee_type, label, created_at) VALUES (:duty_id, 'label', :label, NOW())");
        $stmt->execute(['duty_id' => $dutyId, 'label' => $label]);
        return true;
    }

    private function ensureDutyAssignmentOrder(PDO $pdo, int $dutyId, int $orderId): bool
    {
        $exists = $pdo->prepare("SELECT id FROM duty_assignments WHERE duty_id = :duty_id AND assignee_type = 'order' AND order_id = :order_id LIMIT 1");
        $exists->execute(['duty_id' => $dutyId, 'order_id' => $orderId]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO duty_assignments (duty_id, assignee_type, order_id, created_at) VALUES (:duty_id, 'order', :order_id, NOW())");
        $stmt->execute(['duty_id' => $dutyId, 'order_id' => $orderId]);
        return true;
    }

    private function mealTitlesFromRows(array $rows, string $section): array
    {
        foreach ($rows as $row) {
            if (isset($row[0]) && trim((string) $row[0]) === $section) {
                $titles = [];
                foreach (array_slice($row, 1) as $value) {
                    $value = $this->cleanText((string) $value);
                    if ($value === '' || is_numeric(str_replace(',', '.', $value))) {
                        continue;
                    }
                    $key = $this->normalizeKey($value);
                    if (in_array($key, ['g', 'kg', 'l', 'ml', 'st', 'tl', 'el'], true)) {
                        continue;
                    }
                    $titles[] = $value;
                }
                return $titles;
            }
        }
        return [];
    }

    private function readXlsxWorkbook(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive ist für XLSX-Import erforderlich.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('XLSX-Datei konnte nicht geöffnet werden.');
        }
        $shared = $this->xlsxSharedStrings($zip);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relsXml)) {
            $zip->close();
            throw new RuntimeException('XLSX-Arbeitsmappe ist unvollständig.');
        }
        $targets = $this->xlsxSheetTargets($workbookXml, $relsXml);
        $sheets = [];
        foreach ($targets as $name => $target) {
            $xml = $zip->getFromName($target);
            if (is_string($xml)) {
                $sheets[$name] = $this->xlsxRowsFromXml($xml, $shared, 500);
            }
        }
        $zip->close();
        return $sheets;
    }

    private function readDocxFirstTable(string $path): array
    {
        $xml = $this->zipText($path, 'word/document.xml', 'DOCX');
        preg_match('/<w:tbl(?:\s[^>]*)?>(.*?)<\/w:tbl>/s', $xml, $tableMatch);
        if (!isset($tableMatch[1])) {
            return [];
        }
        preg_match_all('/<w:tr(?:\s[^>]*)?>(.*?)<\/w:tr>/s', $tableMatch[1], $rowMatches);
        $rows = [];
        foreach ($rowMatches[1] as $rowXml) {
            preg_match_all('/<w:tc(?:\s[^>]*)?>(.*?)<\/w:tc>/s', $rowXml, $cellMatches);
            $row = [];
            foreach ($cellMatches[1] as $cellXml) {
                preg_match_all('/<w:p(?:\s[^>]*)?>(.*?)<\/w:p>/s', $cellXml, $paragraphMatches);
                $parts = [];
                foreach ($paragraphMatches[1] as $paragraphXml) {
                    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $paragraphXml, $textMatches);
                    $text = $this->cleanText(html_entity_decode(implode('', $textMatches[1] ?? []), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
                $row[] = implode("\n", $parts);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function readOdsFirstTable(string $path): array
    {
        $xml = $this->zipText($path, 'content.xml', 'ODS');
        preg_match('/<table:table[^>]*>(.*?)<\/table:table>/s', $xml, $tableMatch);
        if (!isset($tableMatch[1])) {
            return [];
        }
        preg_match_all('/<table:table-row[^>]*>(.*?)<\/table:table-row>/s', $tableMatch[1], $rowMatches);
        $rows = [];
        foreach ($rowMatches[1] as $rowXml) {
            preg_match_all('/<table:table-cell([^>]*)>(.*?)<\/table:table-cell>/s', $rowXml, $cellMatches, PREG_SET_ORDER);
            $row = [];
            foreach ($cellMatches as $cellMatch) {
                $repeat = 1;
                if (preg_match('/table:number-columns-repeated="(\d+)"/', $cellMatch[1], $repeatMatch)) {
                    $repeat = min((int) $repeatMatch[1], 20);
                }
                preg_match_all('/<text:p[^>]*>(.*?)<\/text:p>/s', $cellMatch[2], $textMatches);
                $value = trim(html_entity_decode(strip_tags(implode("\n", $textMatches[1] ?? [])), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                for ($i = 0; $i < $repeat; $i++) {
                    $row[] = $value;
                }
            }
            if ($this->rowHasValue($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function zipText(string $path, string $entry, string $type): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive ist für ' . $type . '-Import erforderlich.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException($type . '-Datei konnte nicht geöffnet werden.');
        }
        $content = $zip->getFromName($entry);
        $zip->close();
        if (!is_string($content)) {
            throw new RuntimeException($type . '-Inhalt wurde nicht gefunden.');
        }
        return $content;
    }

    private function xlsxSheetTargets(string $workbookXml, string $relsXml): array
    {
        preg_match_all('/<Relationship[^>]+Id="([^"]+)"[^>]+Target="([^"]+)"/s', $relsXml, $relMatches, PREG_SET_ORDER);
        $rels = [];
        foreach ($relMatches as $match) {
            $rels[$match[1]] = 'xl/' . ltrim($match[2], '/');
        }
        preg_match_all('/<sheet[^>]+name="([^"]+)"[^>]+r:id="([^"]+)"/s', $workbookXml, $sheetMatches, PREG_SET_ORDER);
        $targets = [];
        foreach ($sheetMatches as $match) {
            $name = html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $rid = $match[2];
            if (isset($rels[$rid])) {
                $targets[$name] = $rels[$rid];
            }
        }
        return $targets;
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

    private function xlsxRowsFromXml(string $xml, array $shared, int $maxRows): array
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
                $value = '';
                if (preg_match('/<v[^>]*>(.*?)<\/v>/s', $cellXml, $valueMatch)) {
                    $value = html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if (str_contains($attrs, 't="s"')) {
                        $value = $shared[(int) $value] ?? '';
                    }
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
            if (count($rows) >= $maxRows) {
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

    private function orderIdByName(PDO $pdo, int $campYearId, string $name): ?int
    {
        $name = $this->normalizeOrderName($name);
        if ($name === '') {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE camp_year_id = :camp_year_id AND (name = :order_name OR short_name = :order_short_name) LIMIT 1');
        $stmt->execute(['camp_year_id' => $campYearId, 'order_name' => $name, 'order_short_name' => $name]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function ensureOrderByName(PDO $pdo, int $campYearId, string $name): ?int
    {
        $name = $this->normalizeOrderName($name);
        if ($name === '') {
            return null;
        }
        $existing = $this->orderIdByName($pdo, $campYearId, $name);
        if ($existing !== null) {
            return $existing;
        }
        $short = mb_substr(mb_strtoupper($name), 0, 3);
        $stmt = $pdo->prepare("INSERT INTO orders (camp_year_id, name, short_name, color_key, sort_order, is_active, created_at, updated_at)
            VALUES (:camp_year_id, :name, :short_name, 'blau', 100, 1, NOW(), NOW())");
        $stmt->execute(['camp_year_id' => $campYearId, 'name' => $name, 'short_name' => $short]);
        return (int) $pdo->lastInsertId();
    }

    private function normalizeOrderName(string $value): string
    {
        $key = $this->normalizeKey($value);
        return self::ORDER_ALIASES[$key] ?? trim($value);
    }

    private function personIdByDisplayName(PDO $pdo, string $name): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM persons WHERE display_name = :display_name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['display_name' => $name]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function createRun(PDO $pdo, string $templateKey): int
    {
        $stmt = $pdo->prepare("INSERT INTO import_runs
            (camp_year_id, import_key, original_file_id, status, started_at, total_rows, imported_rows, skipped_rows, error_rows, summary_json, created_by, created_at)
            VALUES (NULL, :import_key, NULL, 'running', NOW(), 0, 0, 0, 0, NULL, :created_by, NOW())");
        $stmt->execute(['import_key' => 'template:' . $templateKey, 'created_by' => $this->currentUserId()]);
        return (int) $pdo->lastInsertId();
    }

    private function finishRun(PDO $pdo, int $runId, string $status, array $result): void
    {
        $stmt = $pdo->prepare("UPDATE import_runs SET status = :status, camp_year_id = :camp_year_id, finished_at = NOW(), total_rows = :total_rows, imported_rows = :imported_rows, skipped_rows = :skipped_rows, error_rows = :error_rows, summary_json = :summary_json WHERE id = :id");
        $stmt->execute([
            'id' => $runId,
            'status' => $status,
            'camp_year_id' => $result['camp_year_id'] ?? null,
            'total_rows' => (int) ($result['total_rows'] ?? 0),
            'imported_rows' => (int) ($result['imported_rows'] ?? 0),
            'skipped_rows' => (int) ($result['skipped_rows'] ?? 0),
            'error_rows' => (int) ($result['error_rows'] ?? 0),
            'summary_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if (!empty($result['errors']) && is_array($result['errors'])) {
            $errorStmt = $pdo->prepare("INSERT INTO import_run_errors (import_run_id, source_row_number, field_name, error_text, raw_row_json, created_at)
                VALUES (:import_run_id, :source_row_number, :field_name, :error_text, :raw_row_json, NOW())");
            foreach ($result['errors'] as $error) {
                $errorStmt->execute([
                    'import_run_id' => $runId,
                    'source_row_number' => isset($error['row']) ? (int) $error['row'] : null,
                    'field_name' => $error['field'] ?? 'template',
                    'error_text' => $error['message'] ?? 'Importhinweis',
                    'raw_row_json' => json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }

    private function markFailed(int $runId, string $message): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("UPDATE import_runs SET status = 'failed', finished_at = NOW(), error_rows = 1, summary_json = :summary_json WHERE id = :id");
            $stmt->execute(['id' => $runId, 'summary_json' => json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        } catch (Throwable) {
            // Logging happens in controller.
        }
    }

    private function sourcePath(string $filename): string
    {
        $candidates = [
            storage_path('import_sources/' . $filename),
            storage_path('imports/' . $filename),
            base_path($filename),
            base_path('public/' . $filename),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $normalizedTarget = $this->normalizeSourceFilename($filename);
        $searchRoots = [
            storage_path('import_sources'),
            storage_path('imports'),
            base_path(),
            base_path('public'),
        ];

        $checkedRoots = [];
        $foundFiles = [];

        foreach ($searchRoots as $root) {
            if (!is_dir($root)) {
                $checkedRoots[] = $root . ' (nicht vorhanden)';
                continue;
            }

            $checkedRoots[] = $root;

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo->isFile()) {
                        continue;
                    }

                    $path = $fileInfo->getPathname();
                    $base = $fileInfo->getBasename();
                    $foundFiles[] = $path;

                    if ($this->normalizeSourceFilename($base) === $normalizedTarget) {
                        return $path;
                    }
                }
            } catch (Throwable) {
                // Wenn ein Suchpfad nicht gelesen werden darf, wird er nur als geprüft protokolliert.
            }
        }

        $examples = array_slice(array_map(static function (string $path): string {
            return $path;
        }, $foundFiles), 0, 20);

        $message = 'Quelldatei fehlt im Paket: ' . $filename . "\n"
            . 'Erwartete Ablage bevorzugt: ' . storage_path('import_sources/' . $filename) . "\n"
            . 'Geprüfte Ordner: ' . implode(' | ', $checkedRoots);

        if ($examples !== []) {
            $message .= "\n" . 'Gefundene Dateien, Auswahl: ' . implode(' | ', $examples);
        }

        throw new RuntimeException($message);
    }

    private function normalizeSourceFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = strtolower($filename);
        $filename = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $filename);
        return preg_replace('/[^a-z0-9]+/', '', $filename) ?? $filename;
    }

    private function splitProgramCell(string $cell): array
    {
        $parts = preg_split('/\n+/', trim($cell)) ?: [];
        $out = [];
        $buffer = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\d{1,2}[:.]\d{2}|^\d{1,2}\s*Uhr/i', $part) || $buffer === '') {
                if ($buffer !== '') {
                    $out[] = $buffer;
                }
                $buffer = $part;
            } else {
                $buffer .= ' ' . $part;
            }
        }
        if ($buffer !== '') {
            $out[] = $buffer;
        }
        return $out;
    }

    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\n+/', $text) ?: []), static fn (string $line): bool => $line !== ''));
    }

    private function inferTime(string $text, string $default): ?string
    {
        if (preg_match('/(\d{1,2})[.:](\d{2})\s*(?:Uhr)?/iu', $text, $match)) {
            return sprintf('%02d:%02d:00', (int) $match[1], (int) $match[2]);
        }
        if (preg_match('/(\d{1,2})\s*Uhr/iu', $text, $match)) {
            return sprintf('%02d:00:00', (int) $match[1]);
        }
        return $default;
    }

    private function stripLeadingTime(string $text): string
    {
        $text = preg_replace('/^\s*\d{1,2}[.:]\d{2}\s*(?:[-–]\s*\d{1,2}[.:]\d{2}\s*)?(?:Uhr)?\s*[-–]?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/^\s*\d{1,2}\s*Uhr\s*[-–]?\s*/iu', '', $text) ?? $text;
        return trim($text);
    }

    private function programCategory(string $text): string
    {
        $key = $this->normalizeKey($text);
        return match (true) {
            str_contains($key, 'essen') || str_contains($key, 'tafel') || str_contains($key, 'fruehstueck') => 'mahlzeit',
            str_contains($key, 'bibel') || str_contains($key, 'geschichte') => 'bibelarbeit',
            str_contains($key, 'spiel') || str_contains($key, 'brennball') || str_contains($key, 'schach') => 'spiel',
            str_contains($key, 'lern') || str_contains($key, 'pruefung') => 'lernen',
            str_contains($key, 'wettbewerb') => 'wettbewerb',
            str_contains($key, 'nacht') || str_contains($key, 'wache') => 'wache',
            default => 'info',
        };
    }

    private function iconForDuty(string $label): string
    {
        $key = $this->normalizeKey($label);
        return match (true) {
            str_contains($key, 'koch') || str_contains($key, 'kueche') || str_contains($key, 'getraenk') => 'restaurant',
            str_contains($key, 'feuer') => 'local_fire_department',
            str_contains($key, 'kiosk') => 'storefront',
            str_contains($key, 'sanit') => 'medical_services',
            str_contains($key, 'flagge') => 'flag',
            str_contains($key, 'zelt') => 'camping',
            str_contains($key, 'punkte') || str_contains($key, 'bewertung') => 'rule',
            str_contains($key, 'lager') => 'inventory_2',
            str_contains($key, 'spiel') => 'sports_esports',
            default => 'assignment',
        };
    }


    private function dutyTypeIdByLabel(PDO $pdo, string $label): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM duty_types WHERE label = :label LIMIT 1');
        $stmt->execute(['label' => $label]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function cleanupImportedMeals(PDO $pdo, int $campYearId): void
    {
        $stmt = $pdo->prepare("UPDATE meal_items SET deleted_at = NOW(), updated_at = NOW() WHERE camp_year_id = :camp_year_id AND description = 'Import aus Speiseplan 2026' AND deleted_at IS NULL");
        $stmt->execute(['camp_year_id' => $campYearId]);
    }

    private function markLegacyImportDutiesDeleted(PDO $pdo, int $campYearId): void
    {
        $stmt = $pdo->prepare("UPDATE duties SET deleted_at = NOW(), updated_at = NOW() WHERE camp_year_id = :camp_year_id AND description = 'Import Aufgabenverteilung 2026' AND deleted_at IS NULL");
        $stmt->execute(['camp_year_id' => $campYearId]);
    }

    private function splitOrderNames(string $label): array
    {
        $parts = preg_split('/\s*(?:\/|,|\+| und )\s*/iu', trim($label)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = $this->normalizeOrderName($part);
            if ($part !== '' && !$this->looksLikeXmlFragment($part)) {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function looksLikeXmlFragment(string $text): bool
    {
        $trimmed = trim($text);
        return $trimmed !== '' && (str_starts_with($trimmed, '<w:') || str_contains($trimmed, '</w:') || str_contains($trimmed, 'w:val='));
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

    private function dateValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $this->isPlausibleBirthdate($value) ? $value : null;
        }

        if (is_numeric($value)) {
            $serial = (float) str_replace(',', '.', $value);
            if ($serial < 10958 || $serial > 60000) { // vor 1930 oder weit außerhalb realer Geburtstage
                return null;
            }
            $date = gmdate('Y-m-d', ((int) round($serial) - 25569) * 86400);
            return $this->isPlausibleBirthdate($date) ? $date : null;
        }

        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $value, $match)) {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = (int) $match[3];
            if (checkdate($month, $day, $year)) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                return $this->isPlausibleBirthdate($date) ? $date : null;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        $date = date('Y-m-d', $timestamp);
        return $this->isPlausibleBirthdate($date) ? $date : null;
    }

    private function isPlausibleBirthdate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        try {
            $birth = new DateTimeImmutable($date);
            $min = new DateTimeImmutable('1930-01-01');
            $max = new DateTimeImmutable('today');
            return $birth >= $min && $birth <= $max;
        } catch (Throwable) {
            return false;
        }
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

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return is_array($user) ? (int) ($user['user_id'] ?? 0) : null;
    }
}
