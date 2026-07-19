<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class MealService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    public function mealTypes(): array
    {
        return [
            'fruehstueck' => ['label' => 'Frühstück', 'time' => '08:40', 'sort' => 10],
            'mittagessen' => ['label' => 'Mittagessen', 'time' => '12:30', 'sort' => 20],
            'abendessen' => ['label' => 'Abendessen', 'time' => '18:00', 'sort' => 30],
        ];
    }

    public function typeLabel(?string $type): string
    {
        $types = $this->mealTypes();
        return $types[$type ?? '']['label'] ?? 'Mahlzeit';
    }

    public function defaultTimeForType(?string $type): string
    {
        $types = $this->mealTypes();
        return $types[$type ?? '']['time'] ?? '';
    }

    public function forDate(int $campYearId, string $mealDate): array
    {
        $stmt = Database::connection()->prepare("SELECT mi.* FROM meal_items mi
            INNER JOIN camp_years cy ON cy.id = mi.camp_year_id
            WHERE mi.camp_year_id = :camp_year_id
              AND mi.meal_date = :meal_date
              AND mi.deleted_at IS NULL
              AND mi.meal_date BETWEEN cy.starts_on AND cy.ends_on
            ORDER BY FIELD(mi.meal_type, 'fruehstueck', 'mittagessen', 'abendessen'), mi.meal_time IS NULL ASC, mi.meal_time ASC, mi.id ASC");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'meal_date' => $mealDate,
        ]);

        return array_map(function (array $item): array {
            $item['meal_type_label'] = $this->typeLabel((string) ($item['meal_type'] ?? ''));
            return $item;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function groupedForDate(int $campYearId, string $mealDate): array
    {
        $items = [];
        foreach ($this->mealTypes() as $key => $type) {
            $items[$key] = [
                'key' => $key,
                'label' => $type['label'],
                'default_time' => $type['time'],
                'item' => null,
            ];
        }

        foreach ($this->forDate($campYearId, $mealDate) as $item) {
            $typeKey = (string) ($item['meal_type'] ?? '');
            if (!isset($items[$typeKey])) {
                continue;
            }
            $items[$typeKey]['item'] = $item;
        }

        return array_values($items);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM meal_items WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($item)) {
            return null;
        }

        $item['ingredients'] = $this->ingredients($id);
        return $item;
    }

    public function create(array $data): int
    {
        $this->validate($data);
        $this->assertNoDuplicateMeal((int) $data['camp_year_id'], (string) $data['meal_date'], (string) $data['meal_type']);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("INSERT INTO meal_items
                (camp_year_id, meal_date, meal_type, meal_time, title, portions_total, portions_vegetarian, allergy_notes, kitchen_team_label, description, created_by, updated_by, created_at, updated_at)
                VALUES (:camp_year_id, :meal_date, :meal_type, :meal_time, :title, :portions_total, :portions_vegetarian, :allergy_notes, :kitchen_team_label, :description, :created_by, :updated_by, NOW(), NOW())");
            $params = $this->params($data);
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            $this->syncIngredients($id, $this->ingredientsFromData($data));
            $pdo->commit();

            $this->audit('meal_items.created', 'meal_item', $id, [
                'title' => (string) $data['title'],
                'meal_date' => (string) $data['meal_date'],
                'meal_type' => (string) $data['meal_type'],
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
        $this->assertNoDuplicateMeal((int) $data['camp_year_id'], (string) $data['meal_date'], (string) $data['meal_type'], $id);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE meal_items
                SET camp_year_id = :camp_year_id,
                    meal_date = :meal_date,
                    meal_type = :meal_type,
                    meal_time = :meal_time,
                    title = :title,
                    portions_total = :portions_total,
                    portions_vegetarian = :portions_vegetarian,
                    allergy_notes = :allergy_notes,
                    kitchen_team_label = :kitchen_team_label,
                    description = :description,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL");
            $params = $this->params($data);
            unset($params['created_by']);
            $params['id'] = $id;
            $stmt->execute($params);

            if ($stmt->rowCount() === 0 && $this->find($id) === null) {
                throw new RuntimeException('Mahlzeit wurde nicht gefunden.');
            }

            $this->syncIngredients($id, $this->ingredientsFromData($data));
            $pdo->commit();

            $this->audit('meal_items.updated', 'meal_item', $id, [
                'title' => (string) $data['title'],
                'meal_date' => (string) $data['meal_date'],
                'meal_type' => (string) $data['meal_type'],
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
            throw new RuntimeException('Mahlzeit wurde nicht gefunden.');
        }

        $stmt = Database::connection()->prepare('UPDATE meal_items
            SET deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'updated_by' => $this->currentUserId(),
        ]);

        $this->audit('meal_items.deactivated', 'meal_item', $id, [
            'title' => (string) $item['title'],
            'meal_date' => (string) $item['meal_date'],
            'meal_type' => (string) $item['meal_type'],
        ]);
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


    private function assertNoDuplicateMeal(int $campYearId, string $mealDate, string $mealType, ?int $ignoreId = null): void
    {
        $params = [
            'camp_year_id' => $campYearId,
            'meal_date' => $mealDate,
            'meal_type' => $mealType,
        ];
        $ignoreSql = '';
        if ($ignoreId !== null) {
            $ignoreSql = ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM meal_items
            WHERE camp_year_id = :camp_year_id
              AND meal_date = :meal_date
              AND meal_type = :meal_type
              AND deleted_at IS NULL" . $ignoreSql);
        $stmt->execute($params);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Diese Mahlzeit ist für den Tag bereits eingetragen. Bitte bearbeite den bestehenden Eintrag.');
        }
    }

    private function validate(array $data): void
    {
        if ((int) ($data['camp_year_id'] ?? 0) <= 0) {
            throw new RuntimeException('Lagerjahr ist erforderlich.');
        }
        if (!$this->validDate((string) ($data['meal_date'] ?? ''))) {
            throw new RuntimeException('Datum ist ungültig.');
        }
        $this->assertDateWithinCampYear((int) $data['camp_year_id'], (string) $data['meal_date']);
        $mealType = (string) ($data['meal_type'] ?? '');
        if (!array_key_exists($mealType, $this->mealTypes())) {
            throw new RuntimeException('Mahlzeit-Typ ist ungültig.');
        }
        if (trim((string) ($data['title'] ?? '')) === '') {
            throw new RuntimeException('Gericht oder Beschreibung ist erforderlich.');
        }
        if (!$this->validTimeOrEmpty((string) ($data['meal_time'] ?? ''))) {
            throw new RuntimeException('Uhrzeit ist ungültig.');
        }
        if ((int) ($data['portions_total'] ?? 0) < 0) {
            throw new RuntimeException('Portionen dürfen nicht negativ sein.');
        }
        if ((int) ($data['portions_vegetarian'] ?? 0) < 0) {
            throw new RuntimeException('Vegetarische Portionen dürfen nicht negativ sein.');
        }
    }

    private function params(array $data): array
    {
        $userId = $this->currentUserId();
        return [
            'camp_year_id' => (int) $data['camp_year_id'],
            'meal_date' => (string) $data['meal_date'],
            'meal_type' => (string) $data['meal_type'],
            'meal_time' => $this->nullableTime($data['meal_time'] ?? null),
            'title' => trim((string) $data['title']),
            'portions_total' => max(0, (int) ($data['portions_total'] ?? 0)),
            'portions_vegetarian' => max(0, (int) ($data['portions_vegetarian'] ?? 0)),
            'allergy_notes' => $this->nullableString($data['allergy_notes'] ?? null),
            'kitchen_team_label' => $this->nullableString($data['kitchen_team_label'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function ingredients(int $mealItemId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM meal_ingredients WHERE meal_item_id = :meal_item_id ORDER BY id ASC');
        $stmt->execute(['meal_item_id' => $mealItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function syncIngredients(int $mealItemId, array $ingredients): void
    {
        $pdo = Database::connection();
        $delete = $pdo->prepare('DELETE FROM meal_ingredients WHERE meal_item_id = :meal_item_id');
        $delete->execute(['meal_item_id' => $mealItemId]);

        if ($ingredients === []) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO meal_ingredients (meal_item_id, name, quantity, unit, note, created_at, updated_at)
            VALUES (:meal_item_id, :name, :quantity, :unit, :note, NOW(), NOW())');
        foreach ($ingredients as $ingredient) {
            $insert->execute([
                'meal_item_id' => $mealItemId,
                'name' => $ingredient['name'],
                'quantity' => $ingredient['quantity'],
                'unit' => $ingredient['unit'],
                'note' => $ingredient['note'],
            ]);
        }
    }

    private function ingredientsFromData(array $data): array
    {
        $names = $data['ingredient_name'] ?? [];
        $quantities = $data['ingredient_quantity'] ?? [];
        $units = $data['ingredient_unit'] ?? [];
        $notes = $data['ingredient_note'] ?? [];

        if (!is_array($names)) {
            return [];
        }

        $ingredients = [];
        foreach ($names as $index => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }
            $ingredients[] = [
                'name' => mb_substr($name, 0, 190),
                'quantity' => $this->nullableDecimal($quantities[$index] ?? null),
                'unit' => $this->nullableString($units[$index] ?? null),
                'note' => $this->nullableString($notes[$index] ?? null),
            ];
        }

        return $ingredients;
    }

    private function assertDateWithinCampYear(int $campYearId, string $mealDate): void
    {
        $stmt = Database::connection()->prepare('SELECT starts_on, ends_on FROM camp_years WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $campYearId]);
        $campYear = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($campYear)) {
            throw new RuntimeException('Lagerjahr wurde nicht gefunden.');
        }

        $date = new DateTimeImmutable($mealDate . ' 00:00:00');
        $start = new DateTimeImmutable((string) $campYear['starts_on'] . ' 00:00:00');
        $end = new DateTimeImmutable((string) $campYear['ends_on'] . ' 00:00:00');
        if ($date < $start || $date > $end) {
            throw new RuntimeException('Speiseplan-Einträge sind nur innerhalb der Lagertage erlaubt.');
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

    private function nullableDecimal(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return number_format((float) $value, 3, '.', '');
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
