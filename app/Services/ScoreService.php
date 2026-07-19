<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class ScoreService
{
    public const RESULT_LABELS = [
        'offen' => 'Offen',
        'bestanden' => 'Bestanden',
        'nicht_bestanden' => 'Nicht bestanden',
        'teilgenommen' => 'Teilgenommen',
        'befreit' => 'Befreit',
    ];

    public const PROMOTION_STATUS_LABELS = [
        'offen' => 'Offen',
        'vorgeschlagen' => 'Vorgeschlagen',
        'bestaetigt' => 'Bestätigt',
        'abgelehnt' => 'Abgelehnt',
    ];

    public const STANDARD_RANKS = [
        'knappe' => ['label' => 'Knappe', 'sort_order' => 10, 'promotion_points_required' => 310, 'next_rank_key' => 'ritter', 'promotion_text' => 'Von Knappe zum Ritter'],
        'ritter' => ['label' => 'Ritter', 'sort_order' => 20, 'promotion_points_required' => 320, 'next_rank_key' => 'freiherr', 'promotion_text' => 'Von Ritter zum Freiherr'],
        'freiherr' => ['label' => 'Freiherr', 'sort_order' => 30, 'promotion_points_required' => 330, 'next_rank_key' => 'graf', 'promotion_text' => 'Vom Freiherr zum Graf'],
        'graf' => ['label' => 'Graf', 'sort_order' => 40, 'promotion_points_required' => 340, 'next_rank_key' => 'markgraf', 'promotion_text' => 'Vom Graf zum Markgraf'],
        'markgraf' => ['label' => 'Markgraf', 'sort_order' => 50, 'promotion_points_required' => 345, 'next_rank_key' => 'landgraf', 'promotion_text' => 'Vom Markgraf zum Landgraf'],
        'landgraf' => ['label' => 'Landgraf', 'sort_order' => 60, 'promotion_points_required' => 350, 'next_rank_key' => 'fuerst', 'promotion_text' => 'Vom Landgraf zum Fürst'],
        'fuerst' => ['label' => 'Fürst', 'sort_order' => 70, 'promotion_points_required' => 280, 'next_rank_key' => 'herzog', 'promotion_text' => 'Vom Fürst zum Herzog'],
        'herzog' => ['label' => 'Herzog', 'sort_order' => 80, 'promotion_points_required' => null, 'next_rank_key' => 'grossherzog', 'promotion_text' => 'Vom Herzog zum Großherzog'],
        'grossherzog' => ['label' => 'Großherzog', 'sort_order' => 90, 'promotion_points_required' => null, 'next_rank_key' => null, 'promotion_text' => 'Höchster Rang'],
    ];

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

    public function dayLabel(?array $campYear): string
    {
        return $this->campYearService->dayLabel($campYear);
    }

    public function canManage(): bool
    {
        return Auth::can('exams.manage') || Auth::can('points.manage');
    }

    public function rankLevels(int $campYearId): array
    {
        $this->ensureStandardRanks($campYearId);
        $stmt = Database::connection()->prepare("SELECT * FROM rank_levels
            WHERE camp_year_id = :camp_year_id
            ORDER BY sort_order ASC, label ASC");
        $stmt->execute(['camp_year_id' => $campYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rankLevel(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rank_levels WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $rank = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($rank) ? $rank : null;
    }

    public function saveRankLevel(array $data, ?int $id = null): int
    {
        if (!$this->canManage()) {
            throw new RuntimeException('Für Rangstufen fehlt die Berechtigung.');
        }

        $campYearId = $this->campYearIdFromData($data);
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Bezeichnung ist erforderlich.');
        }
        if (mb_strlen($label) > 190) {
            throw new RuntimeException('Bezeichnung ist zu lang.');
        }

        $keyName = trim((string) ($data['key_name'] ?? ''));
        if ($keyName === '') {
            $keyName = $this->slug($label);
        }
        if (!preg_match('/^[a-z0-9_\-]{2,80}$/', $keyName)) {
            throw new RuntimeException('Schlüssel darf nur Kleinbuchstaben, Zahlen, Unterstrich und Bindestrich enthalten.');
        }

        $sortOrder = $this->sortOrder($data['sort_order'] ?? 100);
        $promotionPointsRequired = $this->decimalOrNull($data['promotion_points_required'] ?? null);
        $nextRankKey = $this->normalizeRankKey((string) ($data['next_rank_key'] ?? ''));
        $promotionText = $this->nullableString($data['promotion_text'] ?? null);

        if ($id === null) {
            if ($this->columnExists('rank_levels', 'promotion_text')) {
                $stmt = Database::connection()->prepare("INSERT INTO rank_levels
                    (camp_year_id, key_name, label, sort_order, promotion_points_required, next_rank_key, promotion_text, is_permanent, is_system_rank, created_at, updated_at)
                    VALUES (:camp_year_id, :key_name, :label, :sort_order, :promotion_points_required, :next_rank_key, :promotion_text, 1, 0, NOW(), NOW())");
                $stmt->execute([
                    'camp_year_id' => $campYearId,
                    'key_name' => $keyName,
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'promotion_points_required' => $promotionPointsRequired,
                    'next_rank_key' => $nextRankKey,
                    'promotion_text' => $promotionText,
                ]);
            } else {
                $stmt = Database::connection()->prepare("INSERT INTO rank_levels
                    (camp_year_id, key_name, label, sort_order, promotion_points_required, next_rank_key, is_system_rank, created_at, updated_at)
                    VALUES (:camp_year_id, :key_name, :label, :sort_order, :promotion_points_required, :next_rank_key, 0, NOW(), NOW())");
                $stmt->execute([
                    'camp_year_id' => $campYearId,
                    'key_name' => $keyName,
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'promotion_points_required' => $promotionPointsRequired,
                    'next_rank_key' => $nextRankKey,
                ]);
            }
            $id = (int) Database::connection()->lastInsertId();
            $this->audit('rank_levels.created', 'rank_level', $id, ['label' => $label]);
            return $id;
        }

        if ($this->columnExists('rank_levels', 'promotion_text')) {
            $stmt = Database::connection()->prepare("UPDATE rank_levels
                SET key_name = :key_name, label = :label, sort_order = :sort_order, promotion_points_required = :promotion_points_required, next_rank_key = :next_rank_key, promotion_text = :promotion_text, is_permanent = 1, updated_at = NOW()
                WHERE id = :id AND camp_year_id = :camp_year_id");
            $stmt->execute([
                'id' => $id,
                'camp_year_id' => $campYearId,
                'key_name' => $keyName,
                'label' => $label,
                'sort_order' => $sortOrder,
                'promotion_points_required' => $promotionPointsRequired,
                'next_rank_key' => $nextRankKey,
                'promotion_text' => $promotionText,
            ]);
        } else {
            $stmt = Database::connection()->prepare("UPDATE rank_levels
                SET key_name = :key_name, label = :label, sort_order = :sort_order, promotion_points_required = :promotion_points_required, next_rank_key = :next_rank_key, updated_at = NOW()
                WHERE id = :id AND camp_year_id = :camp_year_id");
            $stmt->execute([
                'id' => $id,
                'camp_year_id' => $campYearId,
                'key_name' => $keyName,
                'label' => $label,
                'sort_order' => $sortOrder,
                'promotion_points_required' => $promotionPointsRequired,
                'next_rank_key' => $nextRankKey,
            ]);
        }
        $this->audit('rank_levels.updated', 'rank_level', $id, ['label' => $label]);
        return $id;
    }

    public function learningUnits(int $campYearId, bool $includeDeleted = false): array
    {
        $where = ['camp_year_id = :camp_year_id'];
        if (!$includeDeleted) {
            $where[] = 'deleted_at IS NULL';
        }
        $stmt = Database::connection()->prepare("SELECT * FROM learning_units
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sort_order ASC, title ASC");
        $stmt->execute(['camp_year_id' => $campYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function learningUnit(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM learning_units WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($unit) ? $unit : null;
    }

    public function saveLearningUnit(array $data, ?int $id = null): int
    {
        if (!$this->canManage()) {
            throw new RuntimeException('Für Lerneinheiten fehlt die Berechtigung.');
        }

        $campYearId = $this->campYearIdFromData($data);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Titel ist erforderlich.');
        }
        if (mb_strlen($title) > 190) {
            throw new RuntimeException('Titel ist zu lang.');
        }

        $categoryKey = (string) ($data['category_key'] ?? 'lernen');
        if (!in_array($categoryKey, ['lernen', 'bibelarbeit', 'spiel', 'info', 'wettbewerb'], true)) {
            $categoryKey = 'lernen';
        }
        $responsibleLabel = $this->nullableString($data['responsible_label'] ?? null);
        $sortOrder = $this->sortOrder($data['sort_order'] ?? 100);
        $promotionPointsRequired = $this->decimalOrNull($data['promotion_points_required'] ?? null);
        $nextRankKey = $this->normalizeRankKey((string) ($data['next_rank_key'] ?? ''));

        if ($id === null) {
            $stmt = Database::connection()->prepare("INSERT INTO learning_units
                (camp_year_id, title, category_key, responsible_label, sort_order, created_at, updated_at)
                VALUES (:camp_year_id, :title, :category_key, :responsible_label, :sort_order, NOW(), NOW())");
            $stmt->execute([
                'camp_year_id' => $campYearId,
                'title' => $title,
                'category_key' => $categoryKey,
                'responsible_label' => $responsibleLabel,
                'sort_order' => $sortOrder,
            ]);
            $id = (int) Database::connection()->lastInsertId();
            $this->audit('learning_units.created', 'learning_unit', $id, ['title' => $title]);
            return $id;
        }

        $stmt = Database::connection()->prepare("UPDATE learning_units
            SET title = :title,
                category_key = :category_key,
                responsible_label = :responsible_label,
                sort_order = :sort_order,
                updated_at = NOW()
            WHERE id = :id AND camp_year_id = :camp_year_id");
        $stmt->execute([
            'id' => $id,
            'camp_year_id' => $campYearId,
            'title' => $title,
            'category_key' => $categoryKey,
            'responsible_label' => $responsibleLabel,
            'sort_order' => $sortOrder,
        ]);
        $this->audit('learning_units.updated', 'learning_unit', $id, ['title' => $title]);
        return $id;
    }

    public function deactivateLearningUnit(int $id): void
    {
        if (!$this->canManage()) {
            throw new RuntimeException('Für Lerneinheiten fehlt die Berechtigung.');
        }
        $stmt = Database::connection()->prepare("UPDATE learning_units SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $this->audit('learning_units.deactivated', 'learning_unit', $id);
    }

    public function orders(int $campYearId): array
    {
        return $this->orderService->allForCampYear($campYearId);
    }

    public function participants(int $campYearId, ?int $orderId = null): array
    {
        $where = [
            'p.deleted_at IS NULL',
            'p.is_active = 1',
            'COALESCE(cps.is_participant, IF(p.type_hint IN (\'teilnehmer\',\'beides\'), 1, 0)) = 1',
        ];
        $params = ['camp_year_id' => $campYearId];
        if ($orderId !== null) {
            $where[] = 'cps.order_id = :order_id';
            $params['order_id'] = $orderId;
        }

        $stmt = Database::connection()->prepare("SELECT p.id, p.display_name, p.nickname, p.birthdate,
                cps.order_id, cps.rank_label, cps.rank_level_id, cps.next_rank_level_id, cps.next_rank_label, cps.promotion_status, cps.promotion_note,
                o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex,
                rl.label AS rank_level_label, rl.key_name AS rank_level_key, rl.promotion_points_required, rl.next_rank_key, rl.promotion_text, nrl.label AS next_rank_level_label
            FROM persons p
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            LEFT JOIN rank_levels nrl ON nrl.id = cps.next_rank_level_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.sort_order ASC, p.display_name ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveParticipantRank(int $campYearId, int $personId, ?int $rankLevelId, ?string $rankLabel = null): void
    {
        if (!$this->canManage()) {
            throw new RuntimeException('Für Rangänderungen fehlt die Berechtigung.');
        }

        $rankLabel = $this->nullableString($rankLabel);
        if ($rankLevelId !== null) {
            $rank = $this->rankLevel($rankLevelId);
            if ($rank === null || (int) $rank['camp_year_id'] !== $campYearId) {
                throw new RuntimeException('Rangstufe gehört nicht zum aktiven Lagerjahr.');
            }
            $this->assertRankNotDowngraded($campYearId, $personId, $rank);
            $rankLabel = (string) $rank['label'];
        }

        $stmt = Database::connection()->prepare("INSERT INTO camp_person_statuses
            (camp_year_id, person_id, is_participant, is_staff, participant_status, staff_status, rank_label, rank_level_id, created_at, updated_at)
            VALUES (:camp_year_id, :person_id, 1, 0, 'angemeldet', 'aktiv', :rank_label, :rank_level_id, NOW(), NOW())
            ON DUPLICATE KEY UPDATE rank_label = VALUES(rank_label), rank_level_id = VALUES(rank_level_id), updated_at = NOW()");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'person_id' => $personId,
            'rank_label' => $rankLabel,
            'rank_level_id' => $rankLevelId,
        ]);
        $this->audit('camp_person_statuses.rank_updated', 'person', $personId, ['camp_year_id' => $campYearId, 'rank_level_id' => $rankLevelId]);
    }


    public function savePromotion(int $campYearId, int $personId, ?int $nextRankLevelId, string $status, ?string $note = null): void
    {
        if (!$this->canManage()) {
            throw new RuntimeException('Für Rangvorschläge fehlt die Berechtigung.');
        }

        $status = $this->allowedValue($status, array_keys(self::PROMOTION_STATUS_LABELS), 'offen');
        $nextRankLabel = null;
        if ($nextRankLevelId !== null) {
            $rank = $this->rankLevel($nextRankLevelId);
            if ($rank === null || (int) $rank['camp_year_id'] !== $campYearId) {
                throw new RuntimeException('Folgerang gehört nicht zum aktiven Lagerjahr.');
            }
            $nextRankLabel = (string) $rank['label'];
        }

        $stmt = Database::connection()->prepare("INSERT INTO camp_person_statuses
            (camp_year_id, person_id, is_participant, is_staff, participant_status, staff_status, next_rank_level_id, next_rank_label, promotion_status, promotion_note, promotion_decided_at, created_at, updated_at)
            VALUES (:camp_year_id, :person_id, 1, 0, 'angemeldet', 'aktiv', :next_rank_level_id, :next_rank_label, :promotion_status, :promotion_note, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                next_rank_level_id = VALUES(next_rank_level_id),
                next_rank_label = VALUES(next_rank_label),
                promotion_status = VALUES(promotion_status),
                promotion_note = VALUES(promotion_note),
                promotion_decided_at = NOW(),
                updated_at = NOW()");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'person_id' => $personId,
            'next_rank_level_id' => $nextRankLevelId,
            'next_rank_label' => $nextRankLabel,
            'promotion_status' => $status,
            'promotion_note' => $this->nullableString($note),
        ]);
        $this->audit('camp_person_statuses.promotion_updated', 'person', $personId, ['camp_year_id' => $campYearId, 'next_rank_level_id' => $nextRankLevelId, 'status' => $status]);
    }

    public function examMatrix(int $campYearId, ?int $orderId = null): array
    {
        $participants = $this->participants($campYearId, $orderId);
        $units = $this->learningUnits($campYearId);
        $pointSums = $this->pointSumsByPerson($campYearId);
        $results = $this->examResults($campYearId, $orderId);
        $byPersonUnit = [];
        foreach ($results as $result) {
            $byPersonUnit[(int) $result['person_id']][(int) $result['learning_unit_id']] = $result;
        }

        foreach ($participants as &$participant) {
            $participant['results'] = [];
            $participant['points_sum'] = (float) ($pointSums[(int) $participant['id']] ?? 0.0);
            $participant['passed_count'] = 0;
            $participant['open_count'] = 0;
            foreach ($units as $unit) {
                $result = $byPersonUnit[(int) $participant['id']][(int) $unit['id']] ?? null;
                if ($result === null) {
                    $participant['results'][(int) $unit['id']] = null;
                    $participant['open_count']++;
                    continue;
                }
                $participant['results'][(int) $unit['id']] = $result;
                if ($result['points'] !== null) {
                    $participant['exam_points_sum'] = ($participant['exam_points_sum'] ?? 0.0) + (float) $result['points'];
                }
                if ((string) $result['result_status'] === 'bestanden') {
                    $participant['passed_count']++;
                }
                if ((string) $result['result_status'] === 'offen') {
                    $participant['open_count']++;
                }
            }
            $participant['exam_points_sum'] = (float) ($participant['exam_points_sum'] ?? 0.0);
            $participant['suggested_next_rank'] = $this->suggestedNextRank($campYearId, $participant, count($units));
        }
        unset($participant);

        return [
            'participants' => $participants,
            'units' => $units,
        ];
    }

    public function examResults(int $campYearId, ?int $orderId = null): array
    {
        $where = ['er.camp_year_id = :camp_year_id'];
        $params = ['camp_year_id' => $campYearId];
        if ($orderId !== null) {
            $where[] = 'cps.order_id = :order_id';
            $params['order_id'] = $orderId;
        }

        $stmt = Database::connection()->prepare("SELECT er.*, lu.title AS learning_unit_title,
                p.display_name AS person_name, p.nickname,
                o.name AS order_name, o.short_name AS order_short_name,
                ap.display_name AS assessed_by_name
            FROM exam_results er
            INNER JOIN learning_units lu ON lu.id = er.learning_unit_id
            INNER JOIN persons p ON p.id = er.person_id
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = er.camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            LEFT JOIN users au ON au.id = er.assessed_by
            LEFT JOIN persons ap ON ap.id = au.person_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY er.updated_at DESC, er.created_at DESC, er.id DESC
            LIMIT 500");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveExamResult(array $data): int
    {
        if (!$this->canManage()) {
            throw new RuntimeException('Für Prüfungsergebnisse fehlt die Berechtigung.');
        }

        $campYearId = $this->campYearIdFromData($data);
        $personId = $this->positiveInt($data['person_id'] ?? null, 'Bitte Teilnehmer auswählen.');
        $learningUnitId = $this->positiveInt($data['learning_unit_id'] ?? null, 'Bitte Lerneinheit auswählen.');
        $status = (string) ($data['result_status'] ?? 'offen');
        if (!array_key_exists($status, self::RESULT_LABELS)) {
            throw new RuntimeException('Status ist ungültig.');
        }

        $points = $this->decimalOrNull($data['points'] ?? null);
        if ($points !== null && ($points < -100 || $points > 100)) {
            throw new RuntimeException('Punktewert muss zwischen -100 und 100 liegen.');
        }
        $note = $this->nullableString($data['note'] ?? null);
        if ($note !== null && mb_strlen($note) > 500) {
            throw new RuntimeException('Notiz ist zu lang.');
        }

        $stmt = Database::connection()->prepare("INSERT INTO exam_results
            (camp_year_id, person_id, learning_unit_id, result_status, points, note, assessed_by, assessed_at, created_at, updated_at)
            VALUES (:camp_year_id, :person_id, :learning_unit_id, :result_status, :points, :note, :assessed_by, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE result_status = VALUES(result_status), points = VALUES(points), note = VALUES(note), assessed_by = VALUES(assessed_by), assessed_at = NOW(), updated_at = NOW()");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'person_id' => $personId,
            'learning_unit_id' => $learningUnitId,
            'result_status' => $status,
            'points' => $points,
            'note' => $note,
            'assessed_by' => $this->currentUserId(),
        ]);

        $id = (int) Database::connection()->lastInsertId();
        if ($id === 0) {
            $lookup = Database::connection()->prepare("SELECT id FROM exam_results
                WHERE camp_year_id = :camp_year_id AND person_id = :person_id AND learning_unit_id = :learning_unit_id LIMIT 1");
            $lookup->execute([
                'camp_year_id' => $campYearId,
                'person_id' => $personId,
                'learning_unit_id' => $learningUnitId,
            ]);
            $id = (int) $lookup->fetchColumn();
        }

        $this->audit('exam_results.saved', 'exam_result', $id, [
            'person_id' => $personId,
            'learning_unit_id' => $learningUnitId,
            'result_status' => $status,
            'points' => $points,
        ]);
        return $id;
    }

    public function orderSummary(int $campYearId): array
    {
        $orders = $this->orders($campYearId);
        $summary = [];
        foreach ($orders as $order) {
            if ((int) ($order['is_active'] ?? 0) !== 1) {
                continue;
            }
            $matrix = $this->examMatrix($campYearId, (int) $order['id']);
            $participantCount = count($matrix['participants']);
            $passedCount = 0;
            $openCount = 0;
            $pointsSum = 0.0;
            foreach ($matrix['participants'] as $participant) {
                $passedCount += (int) $participant['passed_count'];
                $openCount += (int) $participant['open_count'];
                $pointsSum += (float) $participant['points_sum'];
            }
            $summary[] = [
                'order' => $order,
                'participant_count' => $participantCount,
                'passed_count' => $passedCount,
                'open_count' => $openCount,
                'points_sum' => $pointsSum,
            ];
        }
        return $summary;
    }

    public function exportCsv(int $campYearId, ?int $orderId = null): string
    {
        $matrix = $this->examMatrix($campYearId, $orderId);
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('CSV konnte nicht erstellt werden.');
        }

        $header = ['Teilnehmer', 'Beiname', 'Orden/Zelt', 'Rang', 'Nächstes Jahr', 'Punkte gesamt', 'Bestanden', 'Offen'];
        foreach ($matrix['units'] as $unit) {
            $header[] = (string) $unit['title'];
        }
        fputcsv($fh, $header, ';');

        foreach ($matrix['participants'] as $participant) {
            $row = [
                (string) $participant['display_name'],
                (string) ($participant['nickname'] ?? ''),
                (string) ($participant['order_short_name'] ?? $participant['order_name'] ?? ''),
                (string) ($participant['rank_level_label'] ?? $participant['rank_label'] ?? ''),
                (string) ($participant['next_rank_level_label'] ?? $participant['next_rank_label'] ?? ''),
                number_format((float) $participant['points_sum'], 2, ',', ''),
                (string) $participant['passed_count'],
                (string) $participant['open_count'],
            ];
            foreach ($matrix['units'] as $unit) {
                $result = $participant['results'][(int) $unit['id']] ?? null;
                if ($result === null) {
                    $row[] = 'offen';
                    continue;
                }
                $label = self::RESULT_LABELS[(string) $result['result_status']] ?? (string) $result['result_status'];
                if ($result['points'] !== null) {
                    $label .= ' (' . number_format((float) $result['points'], 2, ',', '') . ')';
                }
                $row[] = $label;
            }
            fputcsv($fh, $row, ';');
        }

        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        return "\xEF\xBB\xBF" . $csv;
    }

    private function campYearIdFromData(array $data): int
    {
        $id = $this->intOrNull($data['camp_year_id'] ?? null);
        if ($id !== null) {
            return $id;
        }
        $active = $this->activeCampYear();
        if ($active === null) {
            throw new RuntimeException('Es ist kein aktives Lagerjahr gesetzt.');
        }
        return (int) $active['id'];
    }

    private function positiveInt(mixed $value, string $message): int
    {
        $id = $this->intOrNull($value);
        if ($id === null) {
            throw new RuntimeException($message);
        }
        return $id;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : (int) $filtered;
    }

    private function sortOrder(mixed $value): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        return $filtered === false ? 100 : (int) $filtered;
    }


    public function ensureStandardRanks(int $campYearId): void
    {
        $pdo = Database::connection();
        $hasPromotionText = $this->columnExists('rank_levels', 'promotion_text');
        $hasPermanentFlag = $this->columnExists('rank_levels', 'is_permanent');

        if ($hasPromotionText && $hasPermanentFlag) {
            $stmt = $pdo->prepare("INSERT INTO rank_levels
                (camp_year_id, key_name, label, sort_order, promotion_points_required, next_rank_key, promotion_text, is_permanent, is_system_rank, created_at, updated_at)
                VALUES (:camp_year_id, :key_name, :label, :sort_order, :promotion_points_required, :next_rank_key, :promotion_text, 1, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    sort_order = VALUES(sort_order),
                    promotion_points_required = VALUES(promotion_points_required),
                    next_rank_key = VALUES(next_rank_key),
                    promotion_text = VALUES(promotion_text),
                    is_permanent = 1,
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
                    'promotion_text' => $rank['promotion_text'],
                ]);
            }
            return;
        }

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

    private function suggestedNextRank(int $campYearId, array $participant, int $unitCount): ?array
    {
        $nextRankLevelId = $participant['next_rank_level_id'] ?? null;
        if ($nextRankLevelId !== null && (int) $nextRankLevelId > 0) {
            return [
                'id' => (int) $nextRankLevelId,
                'label' => (string) ($participant['next_rank_level_label'] ?? $participant['next_rank_label'] ?? ''),
                'source' => 'manuell',
                'eligible' => (string) ($participant['promotion_status'] ?? 'offen') === 'bestaetigt',
            ];
        }

        $nextKey = (string) ($participant['next_rank_key'] ?? '');
        if ($nextKey === '') {
            $rankKey = $this->normalizeRankKey((string) ($participant['rank_level_key'] ?? $participant['rank_label'] ?? ''));
            $nextKey = $rankKey !== null ? (string) (self::STANDARD_RANKS[$rankKey]['next_rank_key'] ?? '') : '';
        }
        if ($nextKey === '') {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT id, label FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $stmt->execute(['camp_year_id' => $campYearId, 'key_name' => $nextKey]);
        $rank = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($rank)) {
            return null;
        }

        $pointsRequired = $participant['promotion_points_required'] ?? null;
        $hasEnoughPoints = $pointsRequired === null || (float) ($participant['points_sum'] ?? 0) >= (float) $pointsRequired;
        $hasPassedExam = $unitCount > 0 && (int) ($participant['passed_count'] ?? 0) >= $unitCount;

        return [
            'id' => (int) $rank['id'],
            'label' => (string) $rank['label'],
            'source' => $hasPassedExam ? 'prüfung' : ($hasEnoughPoints ? 'punkte' : 'vorschlag'),
            'eligible' => $hasPassedExam || $hasEnoughPoints,
        ];
    }

    private function pointSumsByPerson(int $campYearId): array
    {
        $stmt = Database::connection()->prepare('SELECT person_id, SUM(points) AS points_sum FROM point_entries WHERE camp_year_id = :camp_year_id AND person_id IS NOT NULL AND voided_at IS NULL GROUP BY person_id');
        $stmt->execute(['camp_year_id' => $campYearId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['person_id']] = (float) $row['points_sum'];
        }
        return $out;
    }

    private function normalizeRankKey(string $value): ?string
    {
        $value = trim($value);
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


    private function assertRankNotDowngraded(int $campYearId, int $personId, array $newRank): void
    {
        $stmt = Database::connection()->prepare("SELECT rl.sort_order, rl.label, rl.key_name, cps.is_staff
            FROM camp_person_statuses cps
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            WHERE cps.camp_year_id = :camp_year_id AND cps.person_id = :person_id
            LIMIT 1");
        $stmt->execute(['camp_year_id' => $campYearId, 'person_id' => $personId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current) || $current['sort_order'] === null) {
            return;
        }

        $currentSort = (int) $current['sort_order'];
        $newSort = (int) ($newRank['sort_order'] ?? 0);
        if ($newSort > 0 && $newSort < $currentSort) {
            throw new RuntimeException('Ein erreichter Rang kann im nächsten Jahr nicht verloren oder zurückgestuft werden. Aktueller Rang: ' . (string) ($current['label'] ?? 'unbekannt') . '.');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function allowedValue(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', (string) $value);
        if (!is_numeric($normalized)) {
            throw new RuntimeException('Punktewert ist ungültig.');
        }
        return (float) $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?: 'rang';
        return trim($value, '_') ?: 'rang';
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
}
