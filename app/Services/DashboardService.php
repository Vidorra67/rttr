<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use App\Support\Logger;
use PDO;
use Throwable;

final class DashboardService
{
    public function __construct(
        private readonly CampYearService $campYearService = new CampYearService(),
        private readonly OrderService $orderService = new OrderService(),
        private readonly ProgramService $programService = new ProgramService(),
        private readonly MealService $mealService = new MealService(),
        private readonly DutyService $dutyService = new DutyService(),
        private readonly PersonService $personService = new PersonService(),
        private readonly PointService $pointService = new PointService()
    ) {
    }

    public function data(): array
    {
        $activeCampYear = null;
        $dayTabs = [];
        $orders = [];
        $stats = [
            'participants' => 0,
            'staff' => 0,
            'orders' => 0,
            'open_duties' => 0,
            'order_points_today' => 0,
        ];
        $birthdaysToday = [];
        $nextProgramItem = null;
        $todayMeals = [];
        $openDutiesToday = [];

        try {
            $activeCampYear = $this->campYearService->active();
            if ($activeCampYear !== null) {
                $dayTabs = $this->campYearService->dayTabs($activeCampYear);
                $orders = $this->orderService->allForCampYear((int) $activeCampYear['id']);
                $stats['orders'] = count(array_filter($orders, static fn (array $order): bool => (int) $order['is_active'] === 1));
                $currentCampDate = $this->campYearService->currentCampDate($activeCampYear);
                if ($currentCampDate !== null) {
                    $nextProgramItem = $this->programService->nextForDate((int) $activeCampYear['id'], $currentCampDate);
                    $todayMeals = $this->mealService->groupedForDate((int) $activeCampYear['id'], $currentCampDate);
                    $stats['open_duties'] = $this->dutyService->openCountForDate((int) $activeCampYear['id'], $currentCampDate);
                    $openDutiesToday = $this->dutyService->todayOpenDuties((int) $activeCampYear['id'], $currentCampDate);
                    $stats['order_points_today'] = $this->sumOrderPointsForDate((int) $activeCampYear['id'], $currentCampDate);
                }
            }

            $stats['participants'] = $this->countPersonsByCampStatus((int) ($activeCampYear['id'] ?? 0), 'participant');
            $stats['staff'] = $this->countPersonsByCampStatus((int) ($activeCampYear['id'] ?? 0), 'staff');
            $birthdaysToday = $this->birthdaysToday($activeCampYear);
        } catch (Throwable $exception) {
            Logger::error('Dashboard data could not be fully loaded', ['message' => $exception->getMessage()]);
        }

        return [
            'activeCampYear' => $activeCampYear,
            'currentCampDate' => $this->campYearService->currentCampDate($activeCampYear),
            'dayLabel' => $this->campYearService->dayLabel($activeCampYear),
            'dayTabs' => $dayTabs,
            'orders' => $orders,
            'stats' => $stats,
            'birthdaysToday' => $birthdaysToday,
            'nextProgramItem' => $nextProgramItem,
            'todayMeals' => $todayMeals,
            'openDutiesToday' => $openDutiesToday,
        ];
    }

    private function sumOrderPointsForDate(int $campYearId, string $date): int
    {
        try {
            $stmt = Database::connection()->prepare("SELECT COALESCE(SUM(pe.points), 0)
                FROM point_entries pe
                WHERE pe.camp_year_id = :camp_year_id
                  AND COALESCE(pe.scoring_date, DATE(pe.created_at)) = :entry_date
                  AND pe.voided_at IS NULL");
            $stmt->execute([
                'camp_year_id' => $campYearId,
                'entry_date' => $date,
            ]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            Logger::error('Dashboard order points sum failed', ['message' => $exception->getMessage()]);
            return 0;
        }
    }

    private function countPersonsByCampStatus(int $campYearId, string $type): int
    {
        if ($campYearId > 0) {
            $column = $type === 'staff' ? 'is_staff' : 'is_participant';
            $stmt = Database::connection()->prepare("SELECT COUNT(*)
                FROM camp_person_statuses cps
                INNER JOIN persons p ON p.id = cps.person_id
                WHERE cps.camp_year_id = :camp_year_id
                  AND cps.$column = 1
                  AND p.deleted_at IS NULL
                  AND p.is_active = 1");
            $stmt->execute(['camp_year_id' => $campYearId]);
            return (int) $stmt->fetchColumn();
        }

        return $type === 'staff'
            ? $this->countPersonsByType(['mitarbeiter', 'beides'])
            : $this->countPersonsByType(['teilnehmer', 'beides']);
    }

    private function countPersonsByType(array $types): int
    {
        if ($types === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($types as $index => $type) {
            $placeholder = ':type_' . $index;
            $placeholders[] = $placeholder;
            $params[ltrim($placeholder, ':')] = $type;
        }

        $sql = "SELECT COUNT(*) FROM persons
            WHERE deleted_at IS NULL AND is_active = 1 AND type_hint IN (" . implode(',', $placeholders) . ")";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function birthdaysToday(?array $campYear): array
    {
        $stmt = Database::connection()->prepare("SELECT p.id, p.display_name, p.birthdate, p.type_hint,
                cps.is_participant, cps.is_staff, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex
            FROM persons p
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            WHERE p.deleted_at IS NULL
              AND p.is_active = 1
              AND p.birthdate IS NOT NULL
              AND DATE_FORMAT(p.birthdate, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
            ORDER BY p.display_name ASC
            LIMIT 8");
        $stmt->execute(['camp_year_id' => (int) ($campYear['id'] ?? 0)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
