<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class ProgramService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly OrderService $orderService = new OrderService()
    ) {
    }

    public function categories(): array
    {
        return [
            'info' => ['label' => 'Info', 'tag' => 'info'],
            'freizeit' => ['label' => 'Freizeit', 'tag' => 'info'],
            'spiel' => ['label' => 'Spiel', 'tag' => 'spiel'],
            'bibelarbeit' => ['label' => 'Bibelarbeit', 'tag' => 'bibel'],
            'mahlzeit' => ['label' => 'Mahlzeit', 'tag' => 'mahlzeit'],
            'wache' => ['label' => 'Wache', 'tag' => 'wache'],
            'nachtruhe' => ['label' => 'Nachtruhe', 'tag' => 'wache'],
            'lernen' => ['label' => 'Lernen', 'tag' => 'bibel'],
            'wettbewerb' => ['label' => 'Wettbewerb', 'tag' => 'spiel'],
        ];
    }

    public function categoryLabel(?string $key): string
    {
        $categories = $this->categories();
        return $categories[$key ?? '']['label'] ?? 'Info';
    }

    public function categoryTag(?string $key): string
    {
        $categories = $this->categories();
        return $categories[$key ?? '']['tag'] ?? 'info';
    }

    public function dayItems(int $campYearId, string $programDate): array
    {
        $stmt = Database::connection()->prepare("SELECT pi.*,
                GROUP_CONCAT(o.short_name ORDER BY o.sort_order ASC, o.name ASC SEPARATOR ', ') AS order_names
            FROM program_items pi
            INNER JOIN camp_years cy ON cy.id = pi.camp_year_id
            LEFT JOIN program_item_orders pio ON pio.program_item_id = pi.id
            LEFT JOIN orders o ON o.id = pio.order_id
            WHERE pi.camp_year_id = :camp_year_id
              AND pi.program_date = :program_date
              AND pi.deleted_at IS NULL
              AND pi.is_visible = 1
              AND pi.program_date BETWEEN cy.starts_on AND cy.ends_on
            GROUP BY pi.id
            ORDER BY pi.starts_at IS NULL ASC, pi.starts_at ASC, pi.sort_order ASC, pi.id ASC");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'program_date' => $programDate,
        ]);

        return array_map(function (array $item): array {
            $item['category_label'] = $this->categoryLabel((string) ($item['category_key'] ?? ''));
            $item['category_tag'] = $this->categoryTag((string) ($item['category_key'] ?? ''));
            return $item;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function nextForDate(int $campYearId, string $programDate): ?array
    {
        $params = [
            'camp_year_id' => $campYearId,
            'program_date' => $programDate,
        ];
        $timeCondition = '';
        if ($programDate === (new DateTimeImmutable('today'))->format('Y-m-d')) {
            $timeCondition = 'AND (pi.starts_at IS NULL OR pi.starts_at >= :current_time)';
            $params['current_time'] = (new DateTimeImmutable())->format('H:i:s');
        }

        $stmt = Database::connection()->prepare("SELECT pi.* FROM program_items pi
            INNER JOIN camp_years cy ON cy.id = pi.camp_year_id
            WHERE pi.camp_year_id = :camp_year_id
              AND pi.program_date = :program_date
              AND pi.deleted_at IS NULL
              AND pi.is_visible = 1
              AND pi.program_date BETWEEN cy.starts_on AND cy.ends_on
              {$timeCondition}
            ORDER BY pi.starts_at IS NULL ASC, pi.starts_at ASC, pi.sort_order ASC, pi.id ASC
            LIMIT 1");
        $stmt->execute($params);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($item)) {
            return null;
        }

        $item['category_label'] = $this->categoryLabel((string) ($item['category_key'] ?? ''));
        $item['category_tag'] = $this->categoryTag((string) ($item['category_key'] ?? ''));
        return $item;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM program_items WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($item)) {
            return null;
        }

        $item['order_ids'] = $this->orderIds($id);
        return $item;
    }

    public function create(array $data): int
    {
        $this->validate($data);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("INSERT INTO program_items
                (camp_year_id, program_date, starts_at, ends_at, title, category_key, location, responsible_label, description, sort_order, is_visible, is_recurring, recurring_label, created_by, updated_by, created_at, updated_at)
                VALUES (:camp_year_id, :program_date, :starts_at, :ends_at, :title, :category_key, :location, :responsible_label, :description, :sort_order, 1, :is_recurring, :recurring_label, :created_by, :updated_by, NOW(), NOW())");
            $params = $this->params($data);
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            $this->syncOrders($id, $this->validOrderIds((int) $data['camp_year_id'], $this->orderIdsFromData($data)));
            $pdo->commit();

            $this->audit('program_items.created', 'program_item', $id, [
                'title' => (string) $data['title'],
                'program_date' => (string) $data['program_date'],
            ]);
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
        $this->validate($data);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE program_items
                SET camp_year_id = :camp_year_id,
                    program_date = :program_date,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    title = :title,
                    category_key = :category_key,
                    location = :location,
                    responsible_label = :responsible_label,
                    description = :description,
                    sort_order = :sort_order,
                    is_recurring = :is_recurring,
                    recurring_label = :recurring_label,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL");
            $params = $this->params($data);
            unset($params['created_by']);
            $params['id'] = $id;
            $stmt->execute($params);

            if ($stmt->rowCount() === 0 && $this->find($id) === null) {
                throw new RuntimeException('Programmpunkt wurde nicht gefunden.');
            }

            $this->syncOrders($id, $this->validOrderIds((int) $data['camp_year_id'], $this->orderIdsFromData($data)));
            $pdo->commit();
            $this->audit('program_items.updated', 'program_item', $id, [
                'title' => (string) $data['title'],
                'program_date' => (string) $data['program_date'],
            ]);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deactivate(int $id): void
    {
        $item = $this->find($id);
        if ($item === null) {
            throw new RuntimeException('Programmpunkt wurde nicht gefunden.');
        }

        $stmt = Database::connection()->prepare('UPDATE program_items
            SET is_visible = 0, deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'updated_by' => $this->currentUserId(),
        ]);

        $this->audit('program_items.deactivated', 'program_item', $id, [
            'title' => (string) $item['title'],
            'program_date' => (string) $item['program_date'],
        ]);
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

    private function validate(array $data): void
    {
        if ((int) ($data['camp_year_id'] ?? 0) <= 0) {
            throw new RuntimeException('Lagerjahr ist erforderlich.');
        }
        if (!$this->validDate((string) ($data['program_date'] ?? ''))) {
            throw new RuntimeException('Datum ist ungültig.');
        }
        $this->assertDateWithinCampYear((int) $data['camp_year_id'], (string) $data['program_date']);
        if (trim((string) ($data['title'] ?? '')) === '') {
            throw new RuntimeException('Titel ist erforderlich.');
        }
        $categoryKey = (string) ($data['category_key'] ?? '');
        if (!array_key_exists($categoryKey, $this->categories())) {
            throw new RuntimeException('Kategorie ist ungültig.');
        }
        if (!$this->validTimeOrEmpty((string) ($data['starts_at'] ?? ''))) {
            throw new RuntimeException('Startzeit ist ungültig.');
        }
        if (!$this->validTimeOrEmpty((string) ($data['ends_at'] ?? ''))) {
            throw new RuntimeException('Endzeit ist ungültig.');
        }
    }

    private function params(array $data): array
    {
        $userId = $this->currentUserId();
        return [
            'camp_year_id' => (int) $data['camp_year_id'],
            'program_date' => (string) $data['program_date'],
            'starts_at' => $this->nullableTime($data['starts_at'] ?? null),
            'ends_at' => $this->nullableTime($data['ends_at'] ?? null),
            'title' => trim((string) $data['title']),
            'category_key' => (string) $data['category_key'],
            'location' => $this->nullableString($data['location'] ?? null),
            'responsible_label' => $this->nullableString($data['responsible_label'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_recurring' => !empty($data['is_recurring']) ? 1 : 0,
            'recurring_label' => $this->nullableString($data['recurring_label'] ?? null),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function syncOrders(int $programItemId, array $orderIds): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM program_item_orders WHERE program_item_id = :program_item_id');
        $stmt->execute(['program_item_id' => $programItemId]);

        if ($orderIds === []) {
            return;
        }

        $insert = $pdo->prepare('INSERT IGNORE INTO program_item_orders (program_item_id, order_id, created_at)
            VALUES (:program_item_id, :order_id, NOW())');
        foreach ($orderIds as $orderId) {
            $insert->execute([
                'program_item_id' => $programItemId,
                'order_id' => $orderId,
            ]);
        }
    }


    private function validOrderIds(int $campYearId, array $orderIds): array
    {
        if ($campYearId <= 0 || $orderIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['camp_year_id' => $campYearId];
        foreach ($orderIds as $index => $orderId) {
            $placeholder = 'order_id_' . $index;
            $placeholders[] = ':' . $placeholder;
            $params[$placeholder] = $orderId;
        }

        $stmt = Database::connection()->prepare('SELECT id FROM orders WHERE camp_year_id = :camp_year_id AND id IN (' . implode(',', $placeholders) . ')');
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function orderIds(int $programItemId): array
    {
        $stmt = Database::connection()->prepare('SELECT order_id FROM program_item_orders WHERE program_item_id = :program_item_id ORDER BY order_id ASC');
        $stmt->execute(['program_item_id' => $programItemId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function orderIdsFromData(array $data): array
    {
        $raw = $data['order_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }


    private function assertDateWithinCampYear(int $campYearId, string $programDate): void
    {
        $stmt = Database::connection()->prepare('SELECT starts_on, ends_on FROM camp_years WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campYearId]);
        $campYear = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($campYear)) {
            throw new RuntimeException('Lagerjahr wurde nicht gefunden.');
        }

        $date = new DateTimeImmutable($programDate . ' 00:00:00');
        $start = new DateTimeImmutable((string) $campYear['starts_on'] . ' 00:00:00');
        $end = new DateTimeImmutable((string) $campYear['ends_on'] . ' 00:00:00');
        if ($date < $start || $date > $end) {
            throw new RuntimeException('Programmpunkte sind nur innerhalb der Lagertage erlaubt.');
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
        $value = trim($value);
        if ($value === '') {
            return true;
        }
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
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
