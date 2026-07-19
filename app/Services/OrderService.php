<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use PDO;
use RuntimeException;

final class OrderService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    public function allForCampYear(int $campYearId): array
    {
        $stmt = Database::connection()->prepare("SELECT o.*,
                lp.display_name AS leader_name,
                hp.display_name AS helper_name
            FROM orders o
            LEFT JOIN persons lp ON lp.id = o.leader_person_id
            LEFT JOIN persons hp ON hp.id = o.helper_person_id
            WHERE o.camp_year_id = :camp_year_id
            ORDER BY o.is_active DESC, o.sort_order ASC, o.name ASC");
        $stmt->execute(['camp_year_id' => $campYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($order) ? $order : null;
    }

    public function create(array $data): int
    {
        $this->validate($data);
        $pdo = Database::connection();

        $stmt = $pdo->prepare("INSERT INTO orders
            (camp_year_id, name, short_name, color_key, color_hex, leader_person_id, helper_person_id, sort_order, is_active, created_at, updated_at)
            VALUES (:camp_year_id, :name, :short_name, :color_key, :color_hex, :leader_person_id, :helper_person_id, :sort_order, :is_active, NOW(), NOW())");
        $stmt->execute($this->params($data));
        $id = (int) $pdo->lastInsertId();
        $this->audit('orders.created', 'order', $id, ['name' => (string) $data['name']]);
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->validate($data);
        $stmt = Database::connection()->prepare("UPDATE orders
            SET camp_year_id = :camp_year_id,
                name = :name,
                short_name = :short_name,
                color_key = :color_key,
                color_hex = :color_hex,
                leader_person_id = :leader_person_id,
                helper_person_id = :helper_person_id,
                sort_order = :sort_order,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id");
        $params = $this->params($data);
        $params['id'] = $id;
        $stmt->execute($params);

        if ($stmt->rowCount() === 0 && $this->find($id) === null) {
            throw new RuntimeException('Orden/Zelt wurde nicht gefunden.');
        }

        $this->audit('orders.updated', 'order', $id, ['name' => (string) $data['name']]);
    }

    public function staffOptions(): array
    {
        $stmt = Database::connection()->query("SELECT id, display_name, type_hint
            FROM persons
            WHERE deleted_at IS NULL AND is_active = 1
            ORDER BY display_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function colorOptions(): array
    {
        return [
            'blau' => 'Blau',
            'mint' => 'Mint',
            'info' => 'Info',
            'spiel' => 'Spiel',
            'mahlzeit' => 'Mahlzeit',
            'wache' => 'Wache',
            'warnung' => 'Warnung',
        ];
    }

    private function validate(array $data): void
    {
        if ((int) ($data['camp_year_id'] ?? 0) <= 0) {
            throw new RuntimeException('Lagerjahr ist erforderlich.');
        }
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new RuntimeException('Name ist erforderlich.');
        }
        if (trim((string) ($data['short_name'] ?? '')) === '') {
            throw new RuntimeException('Kürzel ist erforderlich.');
        }
    }

    private function params(array $data): array
    {
        return [
            'camp_year_id' => (int) $data['camp_year_id'],
            'name' => trim((string) $data['name']),
            'short_name' => trim((string) $data['short_name']),
            'color_key' => $this->nullableString($data['color_key'] ?? null),
            'color_hex' => $this->normalizeColorHex($data['color_hex'] ?? null, $data['color_key'] ?? null),
            'leader_person_id' => $this->nullableInt($data['leader_person_id'] ?? null),
            'helper_person_id' => $this->nullableInt($data['helper_person_id'] ?? null),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
    }


    private function normalizeColorHex(mixed $value, mixed $fallbackKey = null): ?string
    {
        $value = strtoupper(trim((string) $value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }
        $fallbacks = [
            'blau' => '#2B49E0',
            'mint' => '#0FDFA0',
            'info' => '#2B49E0',
            'spiel' => '#07B383',
            'mahlzeit' => '#C77B12',
            'wache' => '#3B3F8F',
            'warnung' => '#D6452F',
        ];
        $key = trim((string) $fallbackKey);
        return $fallbacks[$key] ?? '#2B49E0';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $int === false ? null : (int) $int;
    }

    private function audit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        $user = Auth::user();
        $this->auditService->record($action, is_array($user) ? (int) $user['user_id'] : null, $entityType, $entityId, $details);
    }
}
