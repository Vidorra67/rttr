<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Logger;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class CampYearService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    public function all(): array
    {
        $stmt = Database::connection()->query("SELECT * FROM camp_years ORDER BY starts_on DESC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): ?array
    {
        $stmt = Database::connection()->query("SELECT * FROM camp_years WHERE is_active = 1 ORDER BY starts_on DESC, id DESC LIMIT 1");
        $campYear = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($campYear) ? $campYear : null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM camp_years WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $campYear = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($campYear) ? $campYear : null;
    }

    public function create(array $data): int
    {
        $this->validate($data);
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $isActive = !empty($data['is_active']);
            if ($isActive) {
                $pdo->exec('UPDATE camp_years SET is_active = 0, updated_at = NOW() WHERE is_active = 1');
            }

            $stmt = $pdo->prepare("INSERT INTO camp_years
                (name, location_name, starts_on, ends_on, is_active, created_at, updated_at)
                VALUES (:name, :location_name, :starts_on, :ends_on, :is_active, NOW(), NOW())");
            $stmt->execute([
                'name' => trim((string) $data['name']),
                'location_name' => $this->nullableString($data['location_name'] ?? null),
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'is_active' => $isActive ? 1 : 0,
            ]);
            $id = (int) $pdo->lastInsertId();
            $this->ensureDefaultOrders($pdo, $id);
            $this->ensureDefaultRanks($pdo, $id);
            $pdo->commit();

            $this->audit('camp_years.created', 'camp_year', $id, ['name' => (string) $data['name'], 'is_active' => $isActive]);
            $this->generateRecurringDuties($id);
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
            $isActive = !empty($data['is_active']);
            if ($isActive) {
                $stmt = $pdo->prepare('UPDATE camp_years SET is_active = 0, updated_at = NOW() WHERE is_active = 1 AND id <> :id');
                $stmt->execute(['id' => $id]);
            }

            $stmt = $pdo->prepare("UPDATE camp_years
                SET name = :name,
                    location_name = :location_name,
                    starts_on = :starts_on,
                    ends_on = :ends_on,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id");
            $stmt->execute([
                'id' => $id,
                'name' => trim((string) $data['name']),
                'location_name' => $this->nullableString($data['location_name'] ?? null),
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'is_active' => $isActive ? 1 : 0,
            ]);

            if ($stmt->rowCount() === 0 && $this->find($id) === null) {
                throw new RuntimeException('Lagerjahr wurde nicht gefunden.');
            }

            $pdo->commit();
            $this->audit('camp_years.updated', 'camp_year', $id, ['name' => (string) $data['name'], 'is_active' => $isActive]);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function setActive(int $id): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $campYear = $this->find($id);
            if ($campYear === null) {
                throw new RuntimeException('Lagerjahr wurde nicht gefunden.');
            }

            $pdo->exec('UPDATE camp_years SET is_active = 0, updated_at = NOW() WHERE is_active = 1');
            $stmt = $pdo->prepare('UPDATE camp_years SET is_active = 1, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $pdo->commit();
            $this->audit('camp_years.activated', 'camp_year', $id, ['name' => (string) $campYear['name']]);
            $this->generateRecurringDuties($id);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function dayTabs(?array $campYear, ?string $activeDate = null): array
    {
        if ($campYear === null || empty($campYear['starts_on']) || empty($campYear['ends_on'])) {
            return [];
        }

        $start = $this->date((string) $campYear['starts_on']);
        $end = $this->date((string) $campYear['ends_on']);
        if ($end < $start) {
            return [];
        }

        $activeDate ??= $this->currentCampDate($campYear);
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $days = [];
        $index = 1;
        foreach ($period as $date) {
            $days[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $this->weekdayShort($date),
                'short_date' => $date->format('d.m.'),
                'day_number' => $index,
                'is_active' => $date->format('Y-m-d') === $activeDate,
            ];
            $index++;
        }

        return $days;
    }

    public function currentCampDate(?array $campYear): ?string
    {
        if ($campYear === null) {
            return null;
        }

        $today = new DateTimeImmutable('today');
        $start = $this->date((string) $campYear['starts_on']);
        $end = $this->date((string) $campYear['ends_on']);
        if ($today < $start) {
            return $start->format('Y-m-d');
        }
        if ($today > $end) {
            return $end->format('Y-m-d');
        }
        return $today->format('Y-m-d');
    }

    public function dayLabel(?array $campYear): string
    {
        if ($campYear === null) {
            return 'Lagerjahr offen';
        }

        $date = $this->currentCampDate($campYear);
        if ($date === null) {
            return 'Lagerjahr offen';
        }

        $tabs = $this->dayTabs($campYear, $date);
        foreach ($tabs as $tab) {
            if ($tab['date'] === $date) {
                return 'Tag ' . $tab['day_number'] . ' · ' . $tab['label'] . ' ' . $tab['short_date'];
            }
        }

        return (string) $campYear['name'];
    }

    public function validate(array $data): void
    {
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new RuntimeException('Name ist erforderlich.');
        }
        if (!$this->validDate((string) ($data['starts_on'] ?? ''))) {
            throw new RuntimeException('Startdatum ist ungültig.');
        }
        if (!$this->validDate((string) ($data['ends_on'] ?? ''))) {
            throw new RuntimeException('Enddatum ist ungültig.');
        }
        if ($this->date((string) $data['ends_on']) < $this->date((string) $data['starts_on'])) {
            throw new RuntimeException('Enddatum darf nicht vor dem Startdatum liegen.');
        }
    }

    private function generateRecurringDuties(int $campYearId): void
    {
        try {
            (new DutyService())->generateRecurringDutiesForCampYear($campYearId);
        } catch (\Throwable $exception) {
            Logger::error('Recurring duty generation failed', [
                'camp_year_id' => $campYearId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureDefaultOrders(PDO $pdo, int $campYearId): void
    {
        $orders = [
            ['Johanniter', 'JOH', 'blau', 10],
            ['Falkner', 'FAL', 'spiel', 20],
            ['Samariter', 'SAM', 'mint', 30],
            ['Petrusker', 'PET', 'mahlzeit', 40],
            ['Morgensternritter', 'MOR', 'wache', 50],
            ['Malteser', 'MAL', 'info', 60],
        ];

        $stmt = $pdo->prepare("INSERT INTO orders
            (camp_year_id, name, short_name, color_key, sort_order, is_active, created_at, updated_at)
            VALUES (:camp_year_id, :name, :short_name, :color_key, :sort_order, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = updated_at");

        foreach ($orders as [$name, $shortName, $colorKey, $sortOrder]) {
            $stmt->execute([
                'camp_year_id' => $campYearId,
                'name' => $name,
                'short_name' => $shortName,
                'color_key' => $colorKey,
                'sort_order' => $sortOrder,
            ]);
        }
    }


    private function ensureDefaultRanks(PDO $pdo, int $campYearId): void
    {
        $ranks = [
            ['knappe', 'Knappe', 10, 310, 'ritter'],
            ['ritter', 'Ritter', 20, 320, 'freiherr'],
            ['freiherr', 'Freiherr', 30, 330, 'graf'],
            ['graf', 'Graf', 40, 340, 'markgraf'],
            ['markgraf', 'Markgraf', 50, 345, 'landgraf'],
            ['landgraf', 'Landgraf', 60, 350, 'fuerst'],
            ['fuerst', 'Fürst', 70, 280, 'herzog'],
            ['herzog', 'Herzog', 80, null, 'grossherzog'],
            ['grossherzog', 'Großherzog', 90, null, null],
        ];

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

        foreach ($ranks as [$key, $label, $sortOrder, $pointsRequired, $nextKey]) {
            $stmt->execute([
                'camp_year_id' => $campYearId,
                'key_name' => $key,
                'label' => $label,
                'sort_order' => $sortOrder,
                'promotion_points_required' => $pointsRequired,
                'next_rank_key' => $nextKey,
            ]);
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

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function weekdayShort(DateTimeImmutable $date): string
    {
        return ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'][(int) $date->format('w')];
    }

    private function audit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        $user = Auth::user();
        $this->auditService->record($action, is_array($user) ? (int) $user['user_id'] : null, $entityType, $entityId, $details);
    }
}
