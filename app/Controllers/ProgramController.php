<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CampYearService;
use App\Services\ProgramService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class ProgramController
{
    public function __construct(
        private readonly CampYearService $campYearService = new CampYearService(),
        private readonly ProgramService $programService = new ProgramService()
    ) {
    }

    public function index(): string
    {
        if (!Auth::requirePermission('program.view')) {
            return '';
        }

        $activeCampYear = $this->campYearService->active();
        $activeDate = $this->programService->normalizeDateForCampYear($activeCampYear, Request::get('tag'));
        $items = [];
        $dayTabs = [];

        try {
            if ($activeCampYear !== null && $activeDate !== null) {
                $items = $this->programService->dayItems((int) $activeCampYear['id'], $activeDate);
                $dayTabs = $this->tabs($activeCampYear, $activeDate);
            }
        } catch (Throwable $exception) {
            Logger::error('Program list failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Das Programm konnte nicht geladen werden. Details wurden protokolliert.');
        }

        return View::render('program/index', [
            'title' => 'Programm',
            'activeNav' => 'program',
            'activeCampYear' => $activeCampYear,
            'activeDate' => $activeDate,
            'dayTabs' => $dayTabs,
            'items' => $items,
            'topbarDayChip' => $activeCampYear === null ? 'Lagerjahr offen' : $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    public function create(): string
    {
        if (!Auth::requirePermission('program.manage')) {
            return '';
        }

        $activeCampYear = $this->campYearService->active();
        if ($activeCampYear === null) {
            Flash::add('error', 'Lege zuerst ein aktives Lagerjahr an.');
            Response::redirect('/programm');
            return '';
        }

        $activeDate = $this->programService->normalizeDateForCampYear($activeCampYear, Request::get('tag'));

        return View::render('program/form', [
            'title' => 'Programmpunkt hinzufügen',
            'activeNav' => 'program',
            'activeCampYear' => $activeCampYear,
            'programItem' => [
                'camp_year_id' => $activeCampYear['id'],
                'program_date' => $activeDate,
                'starts_at' => '',
                'ends_at' => '',
                'title' => '',
                'category_key' => 'info',
                'location' => '',
                'responsible_label' => '',
                'description' => '',
                'sort_order' => 0,
                'order_ids' => [],
                'is_recurring' => 0,
                'recurring_label' => '',
            ],
            'categories' => $this->programService->categories(),
            'orderOptions' => $this->programService->orderOptions((int) $activeCampYear['id']),
            'action' => '/programm',
            'backUrl' => '/programm?tag=' . urlencode((string) $activeDate),
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
            $this->programService->create($payload);
            Flash::add('success', 'Programmpunkt wurde angelegt.');
            Response::redirect('/programm?tag=' . urlencode((string) $payload['program_date']));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Program create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return $this->renderForm('Programmpunkt hinzufügen', $payload, '/programm');
        }
    }

    public function edit(): string
    {
        if (!Auth::requirePermission('program.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/programm/bearbeiten']), 404);
            return '';
        }

        $programItem = $this->programService->find($id);
        if ($programItem === null) {
            Response::html(View::render('errors/404', ['path' => '/programm/bearbeiten']), 404);
            return '';
        }

        return $this->renderForm('Programmpunkt bearbeiten', $programItem, '/programm/speichern');
    }

    public function update(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/programm/speichern']), 404);
            return '';
        }

        $payload = $this->payload();
        try {
            $this->programService->update($id, $payload);
            Flash::add('success', 'Programmpunkt wurde gespeichert.');
            Response::redirect('/programm?tag=' . urlencode((string) $payload['program_date']));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Program update failed', ['program_item_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            $payload['id'] = $id;
            return $this->renderForm('Programmpunkt bearbeiten', $payload, '/programm/speichern');
        }
    }

    public function deactivate(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        $date = trim((string) Request::post('program_date', ''));
        if ($id !== null) {
            try {
                $this->programService->deactivate($id);
                Flash::add('success', 'Programmpunkt wurde entfernt.');
            } catch (Throwable $exception) {
                Logger::error('Program deactivate failed', ['program_item_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', 'Programmpunkt konnte nicht entfernt werden.');
            }
        }

        Response::redirect('/programm' . ($date !== '' ? '?tag=' . urlencode($date) : ''));
        return '';
    }

    private function renderForm(string $title, array $programItem, string $action): string
    {
        $activeCampYear = $this->campYearService->find((int) ($programItem['camp_year_id'] ?? 0)) ?? $this->campYearService->active();
        $campYearId = (int) ($activeCampYear['id'] ?? 0);

        return View::render('program/form', [
            'title' => $title,
            'activeNav' => 'program',
            'activeCampYear' => $activeCampYear,
            'programItem' => $programItem,
            'categories' => $this->programService->categories(),
            'orderOptions' => $campYearId > 0 ? $this->programService->orderOptions($campYearId) : [],
            'action' => $action,
            'backUrl' => '/programm?tag=' . urlencode((string) ($programItem['program_date'] ?? '')),
            'topbarDayChip' => $activeCampYear === null ? 'Lagerjahr offen' : $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    private function guardWrite(): bool
    {
        if (!Auth::requirePermission('program.manage')) {
            return false;
        }

        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return false;
        }

        return true;
    }

    private function payload(): array
    {
        return [
            'id' => Request::post('id', ''),
            'camp_year_id' => Request::post('camp_year_id', ''),
            'program_date' => Request::post('program_date', ''),
            'starts_at' => Request::post('starts_at', ''),
            'ends_at' => Request::post('ends_at', ''),
            'title' => Request::post('title', ''),
            'category_key' => Request::post('category_key', 'info'),
            'location' => Request::post('location', ''),
            'responsible_label' => Request::post('responsible_label', ''),
            'description' => Request::post('description', ''),
            'sort_order' => Request::post('sort_order', '0'),
            'order_ids' => Request::post('order_ids', []),
            'is_recurring' => Request::post('is_recurring', '0') === '1',
            'recurring_label' => Request::post('recurring_label', ''),
        ];
    }

    private function tabs(array $campYear, string $activeDate): array
    {
        $tabs = $this->campYearService->dayTabs($campYear, $activeDate);
        foreach ($tabs as &$tab) {
            $tab['href'] = '/programm?tag=' . urlencode((string) $tab['date']);
        }
        unset($tab);
        return $tabs;
    }
}
