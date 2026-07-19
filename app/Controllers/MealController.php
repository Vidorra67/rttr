<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CampYearService;
use App\Services\MealService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class MealController
{
    public function __construct(
        private readonly CampYearService $campYearService = new CampYearService(),
        private readonly MealService $mealService = new MealService()
    ) {
    }

    public function index(): string
    {
        if (!Auth::requirePermission('meals.view')) {
            return '';
        }

        $activeCampYear = $this->campYearService->active();
        $activeDate = $this->mealService->normalizeDateForCampYear($activeCampYear, Request::get('tag'));
        $mealGroups = [];
        $dayTabs = [];

        try {
            if ($activeCampYear !== null && $activeDate !== null) {
                $mealGroups = $this->mealService->groupedForDate((int) $activeCampYear['id'], $activeDate);
                $dayTabs = $this->campYearService->dayTabs($activeCampYear, $activeDate);
            }
        } catch (Throwable $exception) {
            Logger::error('Meal list failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Der Speiseplan konnte nicht geladen werden. Details wurden protokolliert.');
        }

        return View::render('meals/index', [
            'title' => 'Essen',
            'activeNav' => 'meals',
            'activeCampYear' => $activeCampYear,
            'activeDate' => $activeDate,
            'dayTabs' => $dayTabs,
            'mealGroups' => $mealGroups,
            'topbarDayChip' => $activeCampYear === null ? 'Lagerjahr offen' : $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    public function create(): string
    {
        if (!Auth::requirePermission('meals.manage')) {
            return '';
        }

        $activeCampYear = $this->campYearService->active();
        if ($activeCampYear === null) {
            Flash::add('error', 'Lege zuerst ein aktives Lagerjahr an.');
            Response::redirect('/essen');
            return '';
        }

        $activeDate = $this->mealService->normalizeDateForCampYear($activeCampYear, Request::get('tag'));
        $mealType = $this->normalizeMealType(Request::get('typ'));

        return View::render('meals/form', [
            'title' => 'Mahlzeit hinzufügen',
            'activeNav' => 'meals',
            'activeCampYear' => $activeCampYear,
            'mealItem' => [
                'camp_year_id' => $activeCampYear['id'],
                'meal_date' => $activeDate,
                'meal_type' => $mealType,
                'meal_time' => $this->mealService->defaultTimeForType($mealType),
                'title' => '',
                'portions_total' => 0,
                'portions_vegetarian' => 0,
                'allergy_notes' => '',
                'kitchen_team_label' => '',
                'description' => '',
                'ingredients' => [],
            ],
            'mealTypes' => $this->mealService->mealTypes(),
            'action' => '/essen',
            'backUrl' => '/essen?tag=' . urlencode((string) $activeDate),
            'topbarDayChip' => $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    public function store(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $payload = $this->payload();
        try {
            $this->mealService->create($payload);
            Flash::add('success', 'Mahlzeit wurde angelegt.');
            Response::redirect('/essen?tag=' . urlencode((string) $payload['meal_date']));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Meal create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return $this->renderForm('Mahlzeit hinzufügen', $payload, '/essen');
        }
    }

    public function edit(): string
    {
        if (!Auth::requirePermission('meals.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/essen/bearbeiten']), 404);
            return '';
        }

        $mealItem = $this->mealService->find($id);
        if ($mealItem === null) {
            Response::html(View::render('errors/404', ['path' => '/essen/bearbeiten']), 404);
            return '';
        }

        return $this->renderForm('Mahlzeit bearbeiten', $mealItem, '/essen/speichern');
    }

    public function update(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/essen/speichern']), 404);
            return '';
        }

        $payload = $this->payload();
        try {
            $this->mealService->update($id, $payload);
            Flash::add('success', 'Mahlzeit wurde gespeichert.');
            Response::redirect('/essen?tag=' . urlencode((string) $payload['meal_date']));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Meal update failed', ['meal_item_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            $payload['id'] = $id;
            return $this->renderForm('Mahlzeit bearbeiten', $payload, '/essen/speichern');
        }
    }

    public function deactivate(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        $date = trim((string) Request::post('meal_date', ''));
        if ($id !== null) {
            try {
                $this->mealService->deactivate($id);
                Flash::add('success', 'Mahlzeit wurde entfernt.');
            } catch (Throwable $exception) {
                Logger::error('Meal deactivate failed', ['meal_item_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', 'Mahlzeit konnte nicht entfernt werden.');
            }
        }

        Response::redirect('/essen' . ($date !== '' ? '?tag=' . urlencode($date) : ''));
        return '';
    }

    private function renderForm(string $title, array $mealItem, string $action): string
    {
        $activeCampYear = $this->campYearService->find((int) ($mealItem['camp_year_id'] ?? 0)) ?? $this->campYearService->active();

        return View::render('meals/form', [
            'title' => $title,
            'activeNav' => 'meals',
            'activeCampYear' => $activeCampYear,
            'mealItem' => $mealItem,
            'mealTypes' => $this->mealService->mealTypes(),
            'action' => $action,
            'backUrl' => '/essen?tag=' . urlencode((string) ($mealItem['meal_date'] ?? '')),
            'topbarDayChip' => $activeCampYear === null ? 'Lagerjahr offen' : $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    private function guardWrite(): bool
    {
        if (!Auth::requirePermission('meals.manage')) {
            return false;
        }

        if (!Csrf::check((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return false;
        }

        return true;
    }

    private function payload(): array
    {
        return [
            'camp_year_id' => Request::post('camp_year_id', ''),
            'meal_date' => Request::post('meal_date', ''),
            'meal_type' => Request::post('meal_type', ''),
            'meal_time' => Request::post('meal_time', ''),
            'title' => Request::post('title', ''),
            'portions_total' => Request::post('portions_total', 0),
            'portions_vegetarian' => Request::post('portions_vegetarian', 0),
            'allergy_notes' => Request::post('allergy_notes', ''),
            'kitchen_team_label' => Request::post('kitchen_team_label', ''),
            'description' => Request::post('description', ''),
            'ingredient_name' => Request::post('ingredient_name', []),
            'ingredient_quantity' => Request::post('ingredient_quantity', []),
            'ingredient_unit' => Request::post('ingredient_unit', []),
            'ingredient_note' => Request::post('ingredient_note', []),
        ];
    }

    private function normalizeMealType(mixed $value): string
    {
        $key = trim((string) $value);
        return array_key_exists($key, $this->mealService->mealTypes()) ? $key : 'fruehstueck';
    }
}
