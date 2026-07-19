<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PointService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly CampYearService $campYearService = new CampYearService(),
        private readonly OrderService $orderService = new OrderService()
    ) {
    }

    public function activeCampYear(): ?array
    {
        return $this->campYearService->active();
    }

    public function dayTabs(?array $campYear, ?string $activeDate = null): array
    {
        return $campYear === null ? [] : $this->campYearService->dayTabs($campYear, $activeDate);
    }

    public function currentCampDate(?array $campYear): ?string
    {
        return $this->campYearService->currentCampDate($campYear);
    }

    public function normalizeDateForCampYear(?array $campYear, mixed $value): ?string
    {
        if ($campYear === null) {
            return null;
        }

        $candidate = trim((string) $value);
        if ($candidate === '' || $this->dateOrNull($candidate) === null) {
            return $this->currentCampDate($campYear);
        }

        $startsOn = (string) ($campYear['starts_on'] ?? '');
        $endsOn = (string) ($campYear['ends_on'] ?? '');
        if ($startsOn !== '' && $candidate < $startsOn) {
            return $startsOn;
        }
        if ($endsOn !== '' && $candidate > $endsOn) {
            return $endsOn;
        }
        return $candidate;
    }

    public function orderOptions(?int $campYearId = null): array
    {
        if ($campYearId === null || $campYearId <= 0) {
            return [];
        }

        return array_values(array_filter(
            $this->orderService->allForCampYear($campYearId),
            static fn (array $order): bool => (int) ($order['is_active'] ?? 0) === 1
        ));
    }

    public function categories(bool $forStaffFlow = false): array
    {
        $where = ['is_active = 1'];
        if ($forStaffFlow && !Auth::can('points.manage')) {
            $where[] = 'is_staff_selectable = 1';
        }

        $stmt = Database::connection()->query("SELECT * FROM point_categories
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sort_order ASC, label ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function participantOptions(int $campYearId, string $query = '', int $limit = 40): array
    {
        $where = [
            'p.deleted_at IS NULL',
            'p.is_active = 1',
            'COALESCE(cps.is_participant, IF(p.type_hint IN (\'teilnehmer\',\'beides\'), 1, 0)) = 1',
        ];
        $params = ['camp_year_id' => $campYearId];

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(p.display_name LIKE :query_display OR p.first_name LIKE :query_first OR p.last_name LIKE :query_last)';
            $likeQuery = '%' . $query . '%';
            $params['query_display'] = $likeQuery;
            $params['query_first'] = $likeQuery;
            $params['query_last'] = $likeQuery;
        }

        $stmt = Database::connection()->prepare("SELECT p.id, p.display_name, p.first_name, p.last_name, p.birthdate,
                cps.order_id, o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex
            FROM persons p
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.sort_order ASC, p.display_name ASC
            LIMIT " . max(1, min(250, $limit)));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entries(array $filters = [], int $limit = 200): array
    {
        $campYear = $this->activeCampYear();
        $campYearId = (int) ($filters['camp_year_id'] ?? ($campYear['id'] ?? 0));
        $where = ['pe.camp_year_id = :camp_year_id'];
        $params = ['camp_year_id' => $campYearId];

        $date = trim((string) ($filters['date'] ?? ''));
        if ($date !== '' && $this->dateOrNull($date) !== null) {
            $where[] = 'COALESCE(pe.scoring_date, DATE(pe.created_at)) = :entry_date';
            $params['entry_date'] = $date;
        }

        $categoryId = $this->intOrNull($filters['category_id'] ?? null);
        if ($categoryId !== null) {
            $where[] = 'pe.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $orderId = $this->intOrNull($filters['order_id'] ?? null);
        if ($orderId !== null) {
            $where[] = 'pe.order_id = :order_id';
            $params['order_id'] = $orderId;
        }

        $personId = $this->intOrNull($filters['person_id'] ?? null);
        if ($personId !== null) {
            $where[] = 'pe.person_id = :person_id';
            $params['person_id'] = $personId;
        }

        $createdBy = $this->intOrNull($filters['created_by'] ?? null);
        if ($createdBy !== null) {
            $where[] = 'pe.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }

        $includeVoided = !empty($filters['include_voided']);
        if (!$includeVoided) {
            $where[] = 'pe.voided_at IS NULL';
        }
        $where[] = 'COALESCE(pe.scoring_date, DATE(pe.created_at)) BETWEEN cy.starts_on AND cy.ends_on';

        $stmt = Database::connection()->prepare("SELECT pe.*, pc.key_name AS category_key, pc.label AS category_label,
                pc.scope AS category_scope, pc.cadence AS category_cadence, pc.max_points_per_entry,
                p.display_name AS person_name,
                o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex,
                cp.display_name AS created_by_name,
                vp.display_name AS voided_by_name
            FROM point_entries pe
            INNER JOIN point_categories pc ON pc.id = pe.category_id
            INNER JOIN camp_years cy ON cy.id = pe.camp_year_id
            LEFT JOIN persons p ON p.id = pe.person_id
            LEFT JOIN orders o ON o.id = pe.order_id
            LEFT JOIN users cu ON cu.id = pe.created_by
            LEFT JOIN persons cp ON cp.id = cu.person_id
            LEFT JOIN users vu ON vu.id = pe.voided_by
            LEFT JOIN persons vp ON vp.id = vu.person_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(pe.scoring_date, DATE(pe.created_at)) DESC, pe.created_at DESC, pe.id DESC
            LIMIT " . max(1, min(500, $limit)));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ownTodayEntries(?string $date = null): array
    {
        $user = Auth::user();
        $campYear = $this->activeCampYear();
        if ($user === null || $campYear === null) {
            return [];
        }

        $date = $date !== null && $this->dateOrNull($date) !== null
            ? $this->normalizeDateForCampYear($campYear, $date)
            : ($this->currentCampDate($campYear) ?? (new DateTimeImmutable('today'))->format('Y-m-d'));

        return $this->entries([
            'camp_year_id' => (int) $campYear['id'],
            'created_by' => (int) $user['user_id'],
            'date' => $date,
            'include_voided' => true,
        ], 20);
    }

    public function findEntry(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT pe.*, pc.key_name AS category_key, pc.label AS category_label,
                pc.scope AS category_scope, pc.cadence AS category_cadence, pc.max_points_per_entry,
                p.display_name AS person_name,
                o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex
            FROM point_entries pe
            INNER JOIN point_categories pc ON pc.id = pe.category_id
            INNER JOIN camp_years cy ON cy.id = pe.camp_year_id
            LEFT JOIN persons p ON p.id = pe.person_id
            LEFT JOIN orders o ON o.id = pe.order_id
            WHERE pe.id = :id
            LIMIT 1");
        $stmt->execute(['id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($entry) ? $entry : null;
    }

    public function createScoringEntry(array $data): int
    {
        $campYear = $this->activeCampYear();
        if ($campYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }

        $category = $this->categoryFromData($data, false);
        $target = $this->targetFromData($data, $category, (int) $campYear['id']);
        $scoringDate = $this->scoringDateFromData($data, $campYear);
        $slot = $this->slotFromData($data, $category);
        $points = $this->scorePointsFromData($data, $category, false);
        $reason = $this->reasonFromData($data, false);

        $this->assertEntryLimit((int) $campYear['id'], $category, $target, $scoringDate, $slot, null);

        return $this->insertEntry([
            'camp_year_id' => (int) $campYear['id'],
            'scoring_date' => $scoringDate,
            'person_id' => $target['person_id'],
            'order_id' => $target['order_id'],
            'category_id' => (int) $category['id'],
            'points' => $points,
            'reason' => $reason,
            'source_type' => 'bewertung',
            'check_slot' => $slot,
            'subject_label' => $this->subjectLabel($category, $slot),
            'max_points_at_entry' => (int) ($category['max_points_per_entry'] ?? 0),
        ]);
    }

    public function createCorrection(array $data): int
    {
        if (!Auth::can('points.manage')) {
            throw new RuntimeException('Für Korrekturen fehlt die Berechtigung.');
        }

        $campYear = $this->activeCampYear();
        if ($campYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }

        $category = $this->categoryFromData($data, true);
        $target = $this->targetFromData($data, $category, (int) $campYear['id']);
        $scoringDate = $this->scoringDateFromData($data, $campYear);
        $slot = $this->slotFromData($data, $category, true);

        $points = filter_var($data['points'] ?? null, FILTER_VALIDATE_INT);
        if ($points === false || $points === 0 || $points < -100 || $points > 100) {
            throw new RuntimeException('Korrekturwert muss zwischen -100 und 100 liegen und darf nicht 0 sein.');
        }

        $reason = $this->reasonFromData($data, true);

        return $this->insertEntry([
            'camp_year_id' => (int) $campYear['id'],
            'scoring_date' => $scoringDate,
            'person_id' => $target['person_id'],
            'order_id' => $target['order_id'],
            'category_id' => (int) $category['id'],
            'points' => (int) $points,
            'reason' => $reason,
            'source_type' => 'korrektur',
            'check_slot' => $slot,
            'subject_label' => $this->subjectLabel($category, $slot),
            'max_points_at_entry' => (int) ($category['max_points_per_entry'] ?? 0),
        ]);
    }

    public function voidEntry(int $entryId, string $reason): void
    {
        if (!Auth::can('points.manage')) {
            throw new RuntimeException('Für Storno fehlt die Berechtigung.');
        }

        $entry = $this->findEntry($entryId);
        if ($entry === null) {
            throw new RuntimeException('Punkteeintrag wurde nicht gefunden.');
        }
        if (!empty($entry['voided_at'])) {
            throw new RuntimeException('Punkteeintrag ist bereits storniert.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Bitte einen Stornogrund eintragen.');
        }

        $stmt = Database::connection()->prepare("UPDATE point_entries
            SET voided_at = NOW(), voided_by = :voided_by, void_reason = :void_reason
            WHERE id = :id AND voided_at IS NULL");
        $stmt->execute([
            'id' => $entryId,
            'voided_by' => $this->currentUserId(),
            'void_reason' => $reason,
        ]);

        $this->audit('point_entries.voided', 'point_entry', $entryId, [
            'person_id' => $entry['person_id'] !== null ? (int) $entry['person_id'] : null,
            'order_id' => $entry['order_id'] !== null ? (int) $entry['order_id'] : null,
            'points' => (int) $entry['points'],
            'source_type' => (string) $entry['source_type'],
        ]);
    }

    public function orderTotals(int $campYearId): array
    {
        $stmt = Database::connection()->prepare("SELECT o.id, o.name, o.short_name, o.color_key, o.color_hex,
                COALESCE(SUM(CASE WHEN pe.voided_at IS NULL THEN pe.points ELSE 0 END), 0) AS points_sum
            FROM orders o
            LEFT JOIN camp_years cy ON cy.id = o.camp_year_id
            LEFT JOIN point_entries pe ON pe.order_id = o.id AND pe.camp_year_id = o.camp_year_id
                AND COALESCE(pe.scoring_date, DATE(pe.created_at)) BETWEEN cy.starts_on AND cy.ends_on
            WHERE o.camp_year_id = :camp_year_id AND o.is_active = 1
            GROUP BY o.id
            ORDER BY points_sum DESC, o.sort_order ASC, o.name ASC");
        $stmt->execute(['camp_year_id' => $campYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dailyOrderScores(int $campYearId, string $date): array
    {
        $stmt = Database::connection()->prepare("SELECT o.id, o.name, o.short_name, o.color_key, o.color_hex,
                COALESCE(SUM(CASE WHEN pe.voided_at IS NULL THEN pe.points ELSE 0 END), 0) AS points_sum
            FROM orders o
            LEFT JOIN camp_years cy ON cy.id = o.camp_year_id
            LEFT JOIN point_entries pe ON pe.order_id = o.id
                AND pe.camp_year_id = o.camp_year_id
                AND COALESCE(pe.scoring_date, DATE(pe.created_at)) = :scoring_date
                AND :scoring_date BETWEEN cy.starts_on AND cy.ends_on
            WHERE o.camp_year_id = :camp_year_id AND o.is_active = 1
            GROUP BY o.id
            ORDER BY points_sum DESC, o.sort_order ASC, o.name ASC");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'scoring_date' => $date,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function participantsForOrder(int $campYearId, int $orderId): array
    {
        $this->assertOrderInCamp($orderId, $campYearId);
        $stmt = Database::connection()->prepare("SELECT p.id, p.display_name, p.first_name, p.last_name,
                cps.order_id, o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex
            FROM persons p
            INNER JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            INNER JOIN orders o ON o.id = cps.order_id
            WHERE cps.order_id = :order_id
              AND p.deleted_at IS NULL
              AND p.is_active = 1
              AND cps.is_participant = 1
            ORDER BY p.display_name ASC");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'order_id' => $orderId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dutiesForDate(int $campYearId, string $date): array
    {
        if ($this->dateOrNull($date) === null) {
            return [];
        }
        return (new DutyService())->dayDuties($campYearId, $date);
    }

    public function categoryByKey(string $key): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM point_categories WHERE key_name = :key_name AND is_active = 1 LIMIT 1');
        $stmt->execute(['key_name' => $key]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($category) ? $category : null;
    }

    public function createCompetitionEntries(array $data): int
    {
        if (!Auth::can('points.manage')) {
            throw new RuntimeException('Für Spielwertungen fehlt die Berechtigung.');
        }
        $campYear = $this->activeCampYear();
        if ($campYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }
        $category = $this->categoryByKey('spiel_wertung');
        if ($category === null) {
            throw new RuntimeException('Kategorie Spielwertung fehlt. Bitte Migration ausführen.');
        }
        $date = $this->scoringDateFromData($data, $campYear);
        $title = trim((string) ($data['game_title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Bitte einen Spielnamen eintragen.');
        }
        $placements = is_array($data['placements'] ?? null) ? $data['placements'] : [];
        $saved = 0;
        foreach ($placements as $place => $row) {
            if (!is_array($row)) {
                continue;
            }
            $points = filter_var($row['points'] ?? null, FILTER_VALIDATE_INT);
            $orderIds = is_array($row['order_ids'] ?? null) ? $row['order_ids'] : [];
            if ($points === false || $points < 0 || $orderIds === []) {
                continue;
            }
            foreach ($orderIds as $orderIdRaw) {
                $orderId = $this->intOrNull($orderIdRaw);
                if ($orderId === null) {
                    continue;
                }
                $this->assertOrderInCamp($orderId, (int) $campYear['id']);
                $this->saveEntryValue([
                    'camp_year_id' => (int) $campYear['id'],
                    'scoring_date' => $date,
                    'person_id' => null,
                    'order_id' => $orderId,
                    'category_id' => (int) $category['id'],
                    'points' => (int) $points,
                    'reason' => 'Spiel: ' . $title . ' · ' . (int) $place . '. Platz',
                    'source_type' => 'bewertung',
                    'check_slot' => 'platz_' . (int) $place,
                    'subject_label' => (int) $place . '. Platz',
                    'max_points_at_entry' => (int) ($category['max_points_per_entry'] ?? 100),
                ]);
                $saved++;
            }
        }
        if ($saved < 1) {
            throw new RuntimeException('Es wurde keine Platzierung gespeichert. Bitte Orden und Punkte eintragen.');
        }
        return $saved;
    }

    public function createBatchOrderAssessment(array $data, string $mode): int
    {
        $campYear = $this->activeCampYear();
        if ($campYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }
        $orderId = $this->intOrNull($data['order_id'] ?? null);
        if ($orderId === null) {
            throw new RuntimeException('Bitte einen Orden/Zelt auswählen.');
        }
        $this->assertOrderInCamp($orderId, (int) $campYear['id']);
        $date = $this->scoringDateFromData($data, $campYear);
        $slot = trim((string) ($data['check_slot'] ?? ''));
        if (!in_array($slot, ['morgens', 'abends'], true)) {
            throw new RuntimeException('Bitte Morgens oder Abends auswählen.');
        }

        $saved = 0;
        if ($mode === 'zelt') {
            $orderCategory = $this->categoryByKey('zelt_ordnung');
            if ($orderCategory === null) {
                throw new RuntimeException('Kategorie Zelt fehlt. Bitte Migration ausführen.');
            }
            $orderPoints = filter_var($data['order_points'] ?? null, FILTER_VALIDATE_INT);
            if ($orderPoints !== false) {
                $this->saveEntryValue([
                    'camp_year_id' => (int) $campYear['id'],
                    'scoring_date' => $date,
                    'person_id' => null,
                    'order_id' => $orderId,
                    'category_id' => (int) $orderCategory['id'],
                    'points' => max(0, min((int) $orderCategory['max_points_per_entry'], (int) $orderPoints)),
                    'reason' => trim((string) ($data['reason'] ?? 'Zeltwertung')),
                    'source_type' => 'bewertung',
                    'check_slot' => $slot,
                    'subject_label' => $this->subjectLabel($orderCategory, $slot),
                    'max_points_at_entry' => (int) $orderCategory['max_points_per_entry'],
                ]);
                $saved++;
            }
            $personCategoryKey = 'ordnung_persoenlich';
        } else {
            $personCategoryKey = 'sauberkeit_geschirr';
        }

        $personCategory = $this->categoryByKey($personCategoryKey);
        if ($personCategory === null) {
            throw new RuntimeException('Bewertungskategorie fehlt. Bitte Migration ausführen.');
        }
        $personPoints = is_array($data['person_points'] ?? null) ? $data['person_points'] : [];
        foreach ($personPoints as $personIdRaw => $pointsRaw) {
            $personId = $this->intOrNull($personIdRaw);
            $points = filter_var($pointsRaw, FILTER_VALIDATE_INT);
            if ($personId === null || $points === false) {
                continue;
            }
            $participant = $this->participant($personId, (int) $campYear['id']);
            if ($participant === null || (int) ($participant['order_id'] ?? 0) !== $orderId) {
                continue;
            }
            $this->saveEntryValue([
                'camp_year_id' => (int) $campYear['id'],
                'scoring_date' => $date,
                'person_id' => $personId,
                'order_id' => $orderId,
                'category_id' => (int) $personCategory['id'],
                'points' => max(0, min((int) $personCategory['max_points_per_entry'], (int) $points)),
                'reason' => trim((string) ($data['reason'] ?? ($mode === 'zelt' ? 'Zeltbewertung' : 'Geschirrbewertung'))),
                'source_type' => 'bewertung',
                'check_slot' => $slot,
                'subject_label' => $this->subjectLabel($personCategory, $slot),
                'max_points_at_entry' => (int) $personCategory['max_points_per_entry'],
            ]);
            $saved++;
        }
        if ($saved < 1) {
            throw new RuntimeException('Es wurde keine Bewertung gespeichert.');
        }
        return $saved;
    }

    public function createDutyBonusEntries(array $data): int
    {
        if (!Auth::can('points.manage')) {
            throw new RuntimeException('Für Dienstpunkte fehlt die Berechtigung.');
        }
        $campYear = $this->activeCampYear();
        if ($campYear === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }
        $date = $this->scoringDateFromData($data, $campYear);
        $dutyId = $this->intOrNull($data['duty_id'] ?? null);
        if ($dutyId === null) {
            throw new RuntimeException('Bitte einen Dienst auswählen.');
        }
        $duty = (new DutyService())->find($dutyId);
        if ($duty === null || (int) $duty['camp_year_id'] !== (int) $campYear['id']) {
            throw new RuntimeException('Dienst wurde nicht gefunden.');
        }

        $orderId = $this->intOrNull($data['order_id'] ?? null);
        if ($orderId === null) {
            $orderId = $this->orderIdFromDuty($dutyId, (int) $campYear['id']);
        }
        if ($orderId === null) {
            throw new RuntimeException('Bitte einen Orden/Zelt für die Dienstwertung auswählen.');
        }
        $this->assertOrderInCamp($orderId, (int) $campYear['id']);

        $category = $this->categoryByKey('kuechendienst');
        if ($category === null) {
            throw new RuntimeException('Kategorie Küchendienst fehlt. Bitte Migration ausführen.');
        }
        $slot = trim((string) ($data['check_slot'] ?? 'einsatz_1'));
        if (!in_array($slot, ['einsatz_1', 'einsatz_2', 'einsatz_3'], true)) {
            throw new RuntimeException('Bitte Einsatz 1, Einsatz 2 oder Einsatz 3 auswählen.');
        }
        $points = filter_var($data['points'] ?? null, FILTER_VALIDATE_INT);
        if ($points === false) {
            $points = (int) ($category['max_points_per_entry'] ?? 3);
        }
        $points = max(0, min((int) ($category['max_points_per_entry'] ?? 3), (int) $points));

        $this->saveEntryValue([
            'camp_year_id' => (int) $campYear['id'],
            'scoring_date' => $date,
            'person_id' => null,
            'order_id' => $orderId,
            'category_id' => (int) $category['id'],
            'points' => $points,
            'reason' => 'Dienst: ' . (string) ($duty['title'] ?? $duty['duty_type_label'] ?? 'Küchendienst'),
            'source_type' => 'bewertung',
            'check_slot' => $slot,
            'subject_label' => (string) ($duty['title'] ?? $duty['duty_type_label'] ?? 'Dienst'),
            'max_points_at_entry' => (int) $category['max_points_per_entry'],
        ]);

        return 1;
    }

    private function orderIdFromDuty(int $dutyId, int $campYearId): ?int
    {
        $stmt = Database::connection()->prepare("SELECT order_id FROM duty_assignments
            WHERE duty_id = :duty_id AND order_id IS NOT NULL
            ORDER BY id ASC LIMIT 1");
        $stmt->execute(['duty_id' => $dutyId]);
        $orderId = $stmt->fetchColumn();
        if ($orderId === false) {
            return null;
        }
        $orderId = (int) $orderId;
        $this->assertOrderInCamp($orderId, $campYearId);
        return $orderId;
    }

    private function categoryFromData(array $data, bool $allowAnyActive): array
    {
        $categoryId = $this->intOrNull($data['category_id'] ?? null);
        if ($categoryId === null) {
            throw new RuntimeException('Bitte eine Bewertungsart auswählen.');
        }

        $stmt = Database::connection()->prepare('SELECT * FROM point_categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($category)) {
            throw new RuntimeException('Bewertungsart wurde nicht gefunden oder ist nicht aktiv.');
        }

        if (!$allowAnyActive && !Auth::can('points.manage') && (int) ($category['is_staff_selectable'] ?? 0) !== 1) {
            throw new RuntimeException('Diese Bewertungsart ist für Mitarbeiter nicht freigegeben.');
        }

        return $category;
    }

    private function targetFromData(array $data, array $category, int $campYearId): array
    {
        $scope = (string) ($category['scope'] ?? 'person');
        if ($scope === 'order') {
            $orderId = $this->intOrNull($data['order_id'] ?? null);
            if ($orderId === null) {
                throw new RuntimeException('Bitte einen Orden/Zelt auswählen.');
            }
            $this->assertOrderInCamp($orderId, $campYearId);
            return ['person_id' => null, 'order_id' => $orderId];
        }

        $personId = $this->intOrNull($data['person_id'] ?? null);
        if ($personId === null) {
            throw new RuntimeException('Bitte ein Kind auswählen.');
        }

        $participant = $this->participant($personId, $campYearId);
        if ($participant === null) {
            throw new RuntimeException('Teilnehmer wurde im aktiven Lagerjahr nicht gefunden.');
        }

        return [
            'person_id' => $personId,
            'order_id' => $participant['order_id'] !== null ? (int) $participant['order_id'] : null,
        ];
    }

    private function scoringDateFromData(array $data, array $campYear): string
    {
        $date = $this->dateOrNull($data['scoring_date'] ?? null)
            ?? $this->currentCampDate($campYear)
            ?? (new DateTimeImmutable('today'))->format('Y-m-d');

        $startsOn = (string) ($campYear['starts_on'] ?? '');
        $endsOn = (string) ($campYear['ends_on'] ?? '');
        if ($startsOn !== '' && $endsOn !== '' && ($date < $startsOn || $date > $endsOn)) {
            throw new RuntimeException('Das Bewertungsdatum liegt außerhalb des aktiven Lagerzeitraums.');
        }

        return $date;
    }

    private function slotFromData(array $data, array $category, bool $allowEmpty = false): ?string
    {
        $slot = trim((string) ($data['check_slot'] ?? ''));
        $requiresSlot = (int) ($category['requires_slot'] ?? 0) === 1;
        $allowed = $this->slotOptions($category);

        if ($slot === '' && !$requiresSlot) {
            return null;
        }

        if ($slot === '' && $requiresSlot && !$allowEmpty) {
            throw new RuntimeException('Bitte eine Prüfung auswählen.');
        }

        if ($slot !== '' && $allowed !== [] && !array_key_exists($slot, $allowed)) {
            throw new RuntimeException('Diese Prüfung ist für die Bewertungsart nicht erlaubt.');
        }

        return $slot === '' ? null : $slot;
    }

    public function slotOptions(array $category): array
    {
        $raw = trim((string) ($category['slot_options'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $options = [];
        foreach (explode(',', $raw) as $part) {
            $key = trim($part);
            if ($key === '') {
                continue;
            }
            $options[$key] = match ($key) {
                'morgens' => 'Morgens',
                'abends' => 'Abends',
                'tag' => 'Tageswertung',
                'fach_1' => 'Fach 1',
                'fach_2' => 'Fach 2',
                'fach_3' => 'Fach 3',
                'einsatz_1' => 'Einsatz 1',
                'einsatz_2' => 'Einsatz 2',
                'einsatz_3' => 'Einsatz 3',
                default => ucfirst(str_replace('_', ' ', $key)),
            };
        }
        return $options;
    }

    private function subjectLabel(array $category, ?string $slot): ?string
    {
        if ($slot === null) {
            return null;
        }
        $options = $this->slotOptions($category);
        return $options[$slot] ?? $slot;
    }

    private function scorePointsFromData(array $data, array $category, bool $allowNegative): int
    {
        $points = filter_var($data['points'] ?? null, FILTER_VALIDATE_INT);
        if ($points === false) {
            throw new RuntimeException('Punktwert ist ungültig.');
        }

        $max = (int) ($category['max_points_per_entry'] ?? 0);
        if ($max <= 0) {
            $max = 100;
        }

        if (!$allowNegative && ($points < 0 || $points > $max)) {
            throw new RuntimeException('Punktwert muss zwischen 0 und ' . $max . ' liegen.');
        }

        if ($allowNegative && ($points < -100 || $points > 100 || $points === 0)) {
            throw new RuntimeException('Korrekturwert muss zwischen -100 und 100 liegen und darf nicht 0 sein.');
        }

        return (int) $points;
    }

    private function reasonFromData(array $data, bool $required): string
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($required && $reason === '') {
            throw new RuntimeException('Bitte einen Grund eintragen.');
        }
        if (strlen($reason) > 500) {
            throw new RuntimeException('Der Grund ist zu lang.');
        }
        return $reason;
    }

    private function assertEntryLimit(int $campYearId, array $category, array $target, string $date, ?string $slot, ?int $ignoreEntryId): void
    {
        $cadence = (string) ($category['cadence'] ?? 'daily');
        $scopeColumn = $target['person_id'] !== null ? 'person_id' : 'order_id';
        $scopeValue = $target['person_id'] ?? $target['order_id'];
        $params = [
            'camp_year_id' => $campYearId,
            'category_id' => (int) $category['id'],
            'scope_value' => (int) $scopeValue,
        ];
        $where = [
            'camp_year_id = :camp_year_id',
            'category_id = :category_id',
            $scopeColumn . ' = :scope_value',
            'voided_at IS NULL',
        ];

        if ((string) ($category['key_name'] ?? '') === 'bonus_freizeit') {
            $where[] = 'created_by = :created_by';
            $params['created_by'] = $this->currentUserId();
        }

        if ($ignoreEntryId !== null) {
            $where[] = 'id <> :ignore_entry_id';
            $params['ignore_entry_id'] = $ignoreEntryId;
        }

        if (in_array($cadence, ['daily', 'twice_daily'], true)) {
            $where[] = 'COALESCE(scoring_date, DATE(created_at)) = :scoring_date';
            $params['scoring_date'] = $date;
        }

        if ((int) ($category['requires_slot'] ?? 0) === 1) {
            $where[] = 'check_slot = :check_slot';
            $params['check_slot'] = (string) $slot;
        }

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM point_entries WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Für diese Bewertungsart gibt es bereits einen Eintrag. Bitte vorhandenen Eintrag stornieren oder eine Korrektur buchen.');
        }

        if ($cadence === 'camp_limited') {
            $limit = (int) ($category['max_entries_per_camp'] ?? 0);
            if ($limit > 0) {
                $where = [
                    'camp_year_id = :camp_year_id',
                    'category_id = :category_id',
                    $scopeColumn . ' = :scope_value',
                    'voided_at IS NULL',
                ];
                $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM point_entries WHERE ' . implode(' AND ', $where));
                $stmt->execute([
                    'camp_year_id' => $campYearId,
                    'category_id' => (int) $category['id'],
                    'scope_value' => (int) $scopeValue,
                ]);
                if ((int) $stmt->fetchColumn() >= $limit) {
                    throw new RuntimeException('Die maximale Anzahl für diese Lagerwertung ist bereits erreicht.');
                }
            }
        }
    }


    private function saveEntryValue(array $params): int
    {
        $where = [
            'camp_year_id = :camp_year_id',
            'category_id = :category_id',
            'COALESCE(scoring_date, DATE(created_at)) = :scoring_date',
            'voided_at IS NULL',
        ];
        $queryParams = [
            'camp_year_id' => (int) $params['camp_year_id'],
            'category_id' => (int) $params['category_id'],
            'scoring_date' => (string) $params['scoring_date'],
        ];
        if ($params['person_id'] !== null) {
            $where[] = 'person_id = :person_id';
            $queryParams['person_id'] = (int) $params['person_id'];
        } else {
            $where[] = 'person_id IS NULL';
        }
        if ($params['order_id'] !== null) {
            $where[] = 'order_id = :order_id';
            $queryParams['order_id'] = (int) $params['order_id'];
        } else {
            $where[] = 'order_id IS NULL';
        }
        if ($params['check_slot'] !== null && $params['check_slot'] !== '') {
            $where[] = 'check_slot = :check_slot';
            $queryParams['check_slot'] = (string) $params['check_slot'];
        } else {
            $where[] = '(check_slot IS NULL OR check_slot = \'\')';
        }
        $stmt = Database::connection()->prepare('SELECT id FROM point_entries WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($queryParams);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            $update = Database::connection()->prepare("UPDATE point_entries
                SET points = :points,
                    reason = :reason,
                    subject_label = :subject_label,
                    max_points_at_entry = :max_points_at_entry
                WHERE id = :id");
            $update->execute([
                'id' => (int) $existingId,
                'points' => (int) $params['points'],
                'reason' => (string) $params['reason'],
                'subject_label' => $params['subject_label'],
                'max_points_at_entry' => (int) ($params['max_points_at_entry'] ?? 0),
            ]);
            $this->audit('point_entries.updated', 'point_entry', (int) $existingId, [
                'points' => (int) $params['points'],
                'category_id' => (int) $params['category_id'],
            ]);
            return (int) $existingId;
        }
        return $this->insertEntry($params);
    }

    private function insertEntry(array $params): int
    {
        $stmt = Database::connection()->prepare("INSERT INTO point_entries
            (camp_year_id, scoring_date, person_id, order_id, category_id, points, reason, source_type, check_slot, subject_label, max_points_at_entry, created_by, created_at)
            VALUES (:camp_year_id, :scoring_date, :person_id, :order_id, :category_id, :points, :reason, :source_type, :check_slot, :subject_label, :max_points_at_entry, :created_by, NOW())");
        $stmt->execute([
            'camp_year_id' => (int) $params['camp_year_id'],
            'scoring_date' => (string) $params['scoring_date'],
            'person_id' => $params['person_id'] !== null ? (int) $params['person_id'] : null,
            'order_id' => $params['order_id'] !== null ? (int) $params['order_id'] : null,
            'category_id' => (int) $params['category_id'],
            'points' => (int) $params['points'],
            'reason' => (string) $params['reason'],
            'source_type' => (string) $params['source_type'],
            'check_slot' => $params['check_slot'],
            'subject_label' => $params['subject_label'],
            'max_points_at_entry' => (int) ($params['max_points_at_entry'] ?? 0),
            'created_by' => $this->currentUserId(),
        ]);

        $id = (int) Database::connection()->lastInsertId();
        $this->audit('point_entries.created', 'point_entry', $id, [
            'person_id' => $params['person_id'] !== null ? (int) $params['person_id'] : null,
            'order_id' => $params['order_id'] !== null ? (int) $params['order_id'] : null,
            'category_id' => (int) $params['category_id'],
            'points' => (int) $params['points'],
            'source_type' => (string) $params['source_type'],
        ]);
        return $id;
    }

    private function participant(int $personId, int $campYearId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT p.id, p.display_name, cps.order_id, o.name AS order_name, o.short_name AS order_short_name
            FROM persons p
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            WHERE p.id = :person_id
              AND p.deleted_at IS NULL
              AND p.is_active = 1
              AND COALESCE(cps.is_participant, IF(p.type_hint IN ('teilnehmer','beides'), 1, 0)) = 1
            LIMIT 1");
        $stmt->execute([
            'person_id' => $personId,
            'camp_year_id' => $campYearId,
        ]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($person) ? $person : null;
    }

    private function assertOrderInCamp(int $orderId, int $campYearId): void
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM orders WHERE id = :id AND camp_year_id = :camp_year_id AND is_active = 1');
        $stmt->execute([
            'id' => $orderId,
            'camp_year_id' => $campYearId,
        ]);
        if ((int) $stmt->fetchColumn() < 1) {
            throw new RuntimeException('Orden/Zelt wurde im aktiven Lagerjahr nicht gefunden.');
        }
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return $user === null ? null : (int) $user['user_id'];
    }

    private function audit(string $actionKey, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        $this->auditService->record($actionKey, $this->currentUserId(), $entityType, $entityId, $details);
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : (int) $filtered;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : null;
    }
}
