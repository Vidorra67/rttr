<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class DutyService
{
    private const STATUSES = [
        'offen' => 'offen',
        'besetzt' => 'besetzt',
        'erledigt' => 'erledigt',
        'ausgefallen' => 'ausgefallen',
    ];

    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly OrderService $orderService = new OrderService()
    ) {
    }

    public function statuses(): array
    {
        return [
            'offen' => 'Offen',
            'besetzt' => 'Besetzt',
            'erledigt' => 'Erledigt',
            'ausgefallen' => 'Ausgefallen',
        ];
    }

    public function assignmentModes(): array
    {
        return [
            'person' => 'Personen',
            'order' => 'Orden/Zelt',
            'mixed' => 'Personen und Orden/Zelte',
            'label' => 'Freitext',
        ];
    }

    public function allTypes(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM duty_types';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, label ASC';
        $stmt = Database::connection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findType(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM duty_types WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($type) ? $type : null;
    }

    public function findTypeByKey(string $keyName): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM duty_types WHERE key_name = :key_name LIMIT 1');
        $stmt->execute(['key_name' => $keyName]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($type) ? $type : null;
    }

    public function createType(array $data): int
    {
        $this->validateType($data);
        $stmt = Database::connection()->prepare("INSERT INTO duty_types
            (key_name, label, icon_key, default_time_label, assignment_mode, is_active, sort_order, created_at, updated_at)
            VALUES (:key_name, :label, :icon_key, :default_time_label, :assignment_mode, :is_active, :sort_order, NOW(), NOW())");
        $stmt->execute($this->typeParams($data));
        $id = (int) Database::connection()->lastInsertId();
        $this->audit('duty_types.created', 'duty_type', $id, ['label' => (string) $data['label']]);
        return $id;
    }

    public function updateType(int $id, array $data): void
    {
        $this->validateType($data, $id);
        $stmt = Database::connection()->prepare("UPDATE duty_types
            SET key_name = :key_name,
                label = :label,
                icon_key = :icon_key,
                default_time_label = :default_time_label,
                assignment_mode = :assignment_mode,
                is_active = :is_active,
                sort_order = :sort_order,
                updated_at = NOW()
            WHERE id = :id");
        $params = $this->typeParams($data);
        $params['id'] = $id;
        $stmt->execute($params);
        if ($stmt->rowCount() === 0 && $this->findType($id) === null) {
            throw new RuntimeException('Dienstart wurde nicht gefunden.');
        }
        $this->audit('duty_types.updated', 'duty_type', $id, ['label' => (string) $data['label']]);
    }

    public function dayDuties(int $campYearId, string $dutyDate): array
    {
        $stmt = Database::connection()->prepare("SELECT d.*, dt.label AS duty_type_label, dt.key_name AS duty_type_key, dt.icon_key, dt.assignment_mode
            FROM duties d
            INNER JOIN duty_types dt ON dt.id = d.duty_type_id
            INNER JOIN camp_years cy ON cy.id = d.camp_year_id
            WHERE d.camp_year_id = :camp_year_id
              AND d.duty_date = :duty_date
              AND d.deleted_at IS NULL
              AND d.duty_date BETWEEN cy.starts_on AND cy.ends_on
            ORDER BY d.starts_at IS NULL ASC, d.starts_at ASC, dt.sort_order ASC, d.id ASC");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'duty_date' => $dutyDate,
        ]);
        $duties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($duties as &$duty) {
            $duty['assignments'] = $this->assignments((int) $duty['id']);
            $duty['assignment_label'] = $this->assignmentLabel($duty['assignments']);
        }
        unset($duty);

        return $duties;
    }

    public function openCountForDate(int $campYearId, string $dutyDate): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM duties d
            INNER JOIN camp_years cy ON cy.id = d.camp_year_id
            WHERE d.camp_year_id = :camp_year_id
              AND d.duty_date = :duty_date
              AND d.status = 'offen'
              AND d.deleted_at IS NULL
              AND d.duty_date BETWEEN cy.starts_on AND cy.ends_on");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'duty_date' => $dutyDate,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function todayOpenDuties(int $campYearId, string $dutyDate, int $limit = 5): array
    {
        $stmt = Database::connection()->prepare("SELECT d.*, dt.label AS duty_type_label, dt.key_name AS duty_type_key
            FROM duties d
            INNER JOIN duty_types dt ON dt.id = d.duty_type_id
            INNER JOIN camp_years cy ON cy.id = d.camp_year_id
            WHERE d.camp_year_id = :camp_year_id
              AND d.duty_date = :duty_date
              AND d.status = 'offen'
              AND d.deleted_at IS NULL
              AND d.duty_date BETWEEN cy.starts_on AND cy.ends_on
            ORDER BY d.starts_at IS NULL ASC, d.starts_at ASC, dt.sort_order ASC, d.id ASC
            LIMIT " . max(1, $limit));
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'duty_date' => $dutyDate,
        ]);
        $duties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($duties as &$duty) {
            $duty['assignments'] = $this->assignments((int) $duty['id']);
            $duty['assignment_label'] = $this->assignmentLabel($duty['assignments']);
        }
        unset($duty);
        return $duties;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT d.*, dt.label AS duty_type_label, dt.key_name AS duty_type_key, dt.assignment_mode
            FROM duties d
            INNER JOIN duty_types dt ON dt.id = d.duty_type_id
            WHERE d.id = :id AND d.deleted_at IS NULL
            LIMIT 1");
        $stmt->execute(['id' => $id]);
        $duty = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($duty)) {
            return null;
        }

        $duty['assignments'] = $this->assignments($id);
        $duty['person_ids'] = $this->assignmentIds($duty['assignments'], 'person');
        $duty['order_ids'] = $this->assignmentIds($duty['assignments'], 'order');
        $duty['label_assignments'] = array_values(array_filter(array_map(
            static fn (array $assignment): string => (string) ($assignment['label'] ?? ''),
            array_filter($duty['assignments'], static fn (array $assignment): bool => (string) $assignment['assignee_type'] === 'label')
        )));
        return $duty;
    }

    public function create(array $data): int
    {
        $this->validateDuty($data);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("INSERT INTO duties
                (camp_year_id, duty_date, duty_type_id, starts_at, ends_at, time_label, title, description, status, created_by, updated_by, created_at, updated_at)
                VALUES (:camp_year_id, :duty_date, :duty_type_id, :starts_at, :ends_at, :time_label, :title, :description, :status, :created_by, :updated_by, NOW(), NOW())");
            $params = $this->dutyParams($data);
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            $this->syncAssignments($id, $data);
            $pdo->commit();
            $this->audit('duties.created', 'duty', $id, ['title' => (string) $data['title'], 'duty_date' => (string) $data['duty_date']]);
            return $id;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, array $data): void
    {
        $this->validateDuty($data);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE duties
                SET camp_year_id = :camp_year_id,
                    duty_date = :duty_date,
                    duty_type_id = :duty_type_id,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    time_label = :time_label,
                    title = :title,
                    description = :description,
                    status = :status,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL");
            $params = $this->dutyParams($data);
            unset($params['created_by']);
            $params['id'] = $id;
            $stmt->execute($params);
            if ($stmt->rowCount() === 0 && $this->find($id) === null) {
                throw new RuntimeException('Dienst wurde nicht gefunden.');
            }
            $this->syncAssignments($id, $data);
            $pdo->commit();
            $this->audit('duties.updated', 'duty', $id, ['title' => (string) $data['title'], 'duty_date' => (string) $data['duty_date']]);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deactivate(int $id): void
    {
        $duty = $this->find($id);
        if ($duty === null) {
            throw new RuntimeException('Dienst wurde nicht gefunden.');
        }
        $stmt = Database::connection()->prepare('UPDATE duties
            SET deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'updated_by' => $this->currentUserId(),
        ]);
        $this->audit('duties.deactivated', 'duty', $id, ['title' => (string) $duty['title'], 'duty_date' => (string) $duty['duty_date']]);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!array_key_exists($status, self::STATUSES)) {
            throw new RuntimeException('Status ist ungültig.');
        }
        $duty = $this->find($id);
        if ($duty === null) {
            throw new RuntimeException('Dienst wurde nicht gefunden.');
        }
        if (!Auth::can('duties.manage') && !$this->isAssignedToCurrentPerson($duty)) {
            throw new RuntimeException('Du darfst diesen Dienst nicht ändern.');
        }
        if (!Auth::can('duties.manage') && $status !== 'erledigt') {
            throw new RuntimeException('Zugewiesene Mitarbeiter dürfen nur auf erledigt setzen.');
        }

        $stmt = Database::connection()->prepare('UPDATE duties SET status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'updated_by' => $this->currentUserId(),
        ]);
        $this->audit('duties.status_changed', 'duty', $id, ['status' => $status, 'title' => (string) $duty['title']]);
    }

    public function peopleOptions(): array
    {
        $stmt = Database::connection()->query("SELECT id, display_name, type_hint FROM persons
            WHERE deleted_at IS NULL AND is_active = 1
            ORDER BY display_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function orderOptions(int $campYearId): array
    {
        return array_values(array_filter(
            $this->orderService->allForCampYear($campYearId),
            static fn (array $order): bool => (int) $order['is_active'] === 1
        ));
    }

    public function normalizeDateForCampYear(?array $campYear, mixed $value): ?string
    {
        if ($campYear === null) {
            return null;
        }

        $candidate = trim((string) $value);
        if ($candidate === '' || !$this->validDate($candidate)) {
            return (new CampYearService())->currentCampDate($campYear);
        }

        $date = new DateTimeImmutable($candidate . ' 00:00:00');
        $start = new DateTimeImmutable((string) $campYear['starts_on'] . ' 00:00:00');
        $end = new DateTimeImmutable((string) $campYear['ends_on'] . ' 00:00:00');

        if ($date < $start) {
            return $start->format('Y-m-d');
        }
        if ($date > $end) {
            return $end->format('Y-m-d');
        }
        return $date->format('Y-m-d');
    }

    public function suggestNextPlaceDutyOrder(int $campYearId, string $dutyDate): ?array
    {
        $orders = $this->orderOptions($campYearId);
        if ($orders === []) {
            return null;
        }

        $placeType = $this->findTypeByKey('platzdienst');
        if ($placeType === null) {
            return $orders[0];
        }

        $stmt = Database::connection()->prepare("SELECT da.order_id
            FROM duties d
            INNER JOIN duty_assignments da ON da.duty_id = d.id AND da.assignee_type = 'order'
            WHERE d.camp_year_id = :camp_year_id
              AND d.duty_type_id = :duty_type_id
              AND d.duty_date < :duty_date
              AND d.deleted_at IS NULL
            ORDER BY d.duty_date DESC, d.id DESC
            LIMIT 1");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'duty_type_id' => (int) $placeType['id'],
            'duty_date' => $dutyDate,
        ]);
        $lastOrderId = $stmt->fetchColumn();
        if ($lastOrderId === false) {
            return $orders[0];
        }

        $orderIds = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $currentIndex = array_search((int) $lastOrderId, $orderIds, true);
        if ($currentIndex === false) {
            return $orders[0];
        }
        $nextIndex = ($currentIndex + 1) % count($orders);
        return $orders[$nextIndex];
    }

    private function assignments(int $dutyId): array
    {
        $stmt = Database::connection()->prepare("SELECT da.*, p.display_name AS person_name, o.short_name AS order_short_name, o.name AS order_name
            FROM duty_assignments da
            LEFT JOIN persons p ON p.id = da.person_id
            LEFT JOIN orders o ON o.id = da.order_id
            WHERE da.duty_id = :duty_id
            ORDER BY da.id ASC");
        $stmt->execute(['duty_id' => $dutyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assignmentIds(array $assignments, string $type): array
    {
        $key = $type . '_id';
        $ids = [];
        foreach ($assignments as $assignment) {
            if ((string) ($assignment['assignee_type'] ?? '') === $type && !empty($assignment[$key])) {
                $ids[] = (int) $assignment[$key];
            }
        }
        return $ids;
    }

    private function assignmentLabel(array $assignments): string
    {
        $labels = [];
        foreach ($assignments as $assignment) {
            $type = (string) ($assignment['assignee_type'] ?? '');
            if ($type === 'person' && !empty($assignment['person_name'])) {
                $labels[] = (string) $assignment['person_name'];
            } elseif ($type === 'order' && !empty($assignment['order_short_name'])) {
                $labels[] = (string) $assignment['order_short_name'];
            } elseif ($type === 'label' && !empty($assignment['label'])) {
                $labels[] = (string) $assignment['label'];
            }
        }
        return $labels === [] ? 'nicht besetzt' : implode(', ', $labels);
    }

    private function validateType(array $data, ?int $ignoreId = null): void
    {
        $keyName = $this->slug((string) ($data['key_name'] ?? ''));
        if ($keyName === '') {
            throw new RuntimeException('Schlüssel der Dienstart ist erforderlich.');
        }
        if (trim((string) ($data['label'] ?? '')) === '') {
            throw new RuntimeException('Name der Dienstart ist erforderlich.');
        }
        $assignmentMode = (string) ($data['assignment_mode'] ?? 'mixed');
        if (!array_key_exists($assignmentMode, $this->assignmentModes())) {
            throw new RuntimeException('Zuweisungsart ist ungültig.');
        }

        $params = ['key_name' => $keyName];
        $ignoreSql = '';
        if ($ignoreId !== null) {
            $ignoreSql = ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM duty_types WHERE key_name = :key_name' . $ignoreSql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Dieser Dienstart-Schlüssel ist bereits vergeben.');
        }
    }

    private function typeParams(array $data): array
    {
        return [
            'key_name' => $this->slug((string) ($data['key_name'] ?? '')),
            'label' => trim((string) $data['label']),
            'icon_key' => $this->nullableString($data['icon_key'] ?? null),
            'default_time_label' => $this->nullableString($data['default_time_label'] ?? null),
            'assignment_mode' => (string) ($data['assignment_mode'] ?? 'mixed'),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 100)),
        ];
    }

    private function validateDuty(array $data): void
    {
        if ((int) ($data['camp_year_id'] ?? 0) <= 0) {
            throw new RuntimeException('Lagerjahr ist erforderlich.');
        }
        if (!$this->validDate((string) ($data['duty_date'] ?? ''))) {
            throw new RuntimeException('Datum ist ungültig.');
        }
        $this->assertDateWithinCampYear((int) $data['camp_year_id'], (string) $data['duty_date']);
        if ((int) ($data['duty_type_id'] ?? 0) <= 0 || $this->findType((int) $data['duty_type_id']) === null) {
            throw new RuntimeException('Dienstart ist ungültig.');
        }
        if (trim((string) ($data['title'] ?? '')) === '') {
            throw new RuntimeException('Titel ist erforderlich.');
        }
        if (!array_key_exists((string) ($data['status'] ?? 'offen'), self::STATUSES)) {
            throw new RuntimeException('Status ist ungültig.');
        }
        if (!$this->validTimeOrEmpty((string) ($data['starts_at'] ?? ''))) {
            throw new RuntimeException('Startzeit ist ungültig.');
        }
        if (!$this->validTimeOrEmpty((string) ($data['ends_at'] ?? ''))) {
            throw new RuntimeException('Endzeit ist ungültig.');
        }
    }

    private function dutyParams(array $data): array
    {
        $userId = $this->currentUserId();
        return [
            'camp_year_id' => (int) $data['camp_year_id'],
            'duty_date' => (string) $data['duty_date'],
            'duty_type_id' => (int) $data['duty_type_id'],
            'starts_at' => $this->nullableTime($data['starts_at'] ?? null),
            'ends_at' => $this->nullableTime($data['ends_at'] ?? null),
            'time_label' => $this->nullableString($data['time_label'] ?? null),
            'title' => trim((string) $data['title']),
            'description' => $this->nullableString($data['description'] ?? null),
            'status' => (string) ($data['status'] ?? 'offen'),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function syncAssignments(int $dutyId, array $data): void
    {
        $pdo = Database::connection();
        $delete = $pdo->prepare('DELETE FROM duty_assignments WHERE duty_id = :duty_id');
        $delete->execute(['duty_id' => $dutyId]);

        $insert = $pdo->prepare("INSERT INTO duty_assignments (duty_id, assignee_type, person_id, order_id, label, created_at)
            VALUES (:duty_id, :assignee_type, :person_id, :order_id, :label, NOW())");

        foreach ($this->idsFromData($data['person_ids'] ?? []) as $personId) {
            $insert->execute([
                'duty_id' => $dutyId,
                'assignee_type' => 'person',
                'person_id' => $personId,
                'order_id' => null,
                'label' => null,
            ]);
        }

        foreach ($this->validOrderIds((int) $data['camp_year_id'], $this->idsFromData($data['order_ids'] ?? [])) as $orderId) {
            $insert->execute([
                'duty_id' => $dutyId,
                'assignee_type' => 'order',
                'person_id' => null,
                'order_id' => $orderId,
                'label' => null,
            ]);
        }

        foreach ($this->labelsFromData($data['assignment_labels'] ?? []) as $label) {
            $insert->execute([
                'duty_id' => $dutyId,
                'assignee_type' => 'label',
                'person_id' => null,
                'order_id' => null,
                'label' => $label,
            ]);
        }
    }

    private function idsFromData(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $raw) {
            $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function labelsFromData(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $labels = [];
        foreach ($value as $raw) {
            $label = trim((string) $raw);
            if ($label !== '') {
                $labels[] = mb_substr($label, 0, 190);
            }
        }
        return array_values(array_unique($labels));
    }

    private function validOrderIds(int $campYearId, array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $orders = $this->orderOptions($campYearId);
        $allowed = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        return array_values(array_intersect($orderIds, $allowed));
    }

    private function isAssignedToCurrentPerson(array $duty): bool
    {
        $user = Auth::user();
        if (!is_array($user)) {
            return false;
        }
        $personId = (int) ($user['person_id'] ?? 0);
        foreach (($duty['assignments'] ?? []) as $assignment) {
            if ((string) ($assignment['assignee_type'] ?? '') === 'person' && (int) ($assignment['person_id'] ?? 0) === $personId) {
                return true;
            }
        }
        return false;
    }


    private function assertDateWithinCampYear(int $campYearId, string $dutyDate): void
    {
        $stmt = Database::connection()->prepare('SELECT starts_on, ends_on FROM camp_years WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campYearId]);
        $campYear = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($campYear)) {
            throw new RuntimeException('Lagerjahr wurde nicht gefunden.');
        }

        $date = new DateTimeImmutable($dutyDate . ' 00:00:00');
        $start = new DateTimeImmutable((string) $campYear['starts_on'] . ' 00:00:00');
        $end = new DateTimeImmutable((string) $campYear['ends_on'] . ' 00:00:00');
        if ($date < $start || $date > $end) {
            throw new RuntimeException('Dienste sind nur innerhalb der Lagertage erlaubt.');
        }
    }

    private function validDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function validTimeOrEmpty(string $value): bool
    {
        if (trim($value) === '') {
            return true;
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            return false;
        }
        $time = DateTimeImmutable::createFromFormat('!H:i', $value);
        return $time instanceof DateTimeImmutable && $time->format('H:i') === $value;
    }

    private function nullableTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value . ':00';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value) ?? '';
        $value = trim($value, '_-');
        return mb_substr($value, 0, 80);
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return is_array($user) ? (int) $user['user_id'] : null;
    }

    private function audit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        $this->auditService->record($action, $this->currentUserId(), $entityType, $entityId, $details);
    }
}
