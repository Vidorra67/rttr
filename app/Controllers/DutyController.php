<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CampYearService;
use App\Services\DutyService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class DutyController
{
    public function __construct(
        private readonly CampYearService $campYearService = new CampYearService(),
        private readonly DutyService $dutyService = new DutyService()
    ) {
    }

    public function index(): string
    {
        if (!Auth::requirePermission('duties.view')) {
            return '';
        }

        $activeCampYear = $this->campYearService->active();
        $activeDate = $this->dutyService->normalizeDateForCampYear($activeCampYear, Request::get('tag'));
        $duties = [];
        $dayTabs = [];
        $placeSuggestion = null;

        try {
            if ($activeCampYear !== null && $activeDate !== null) {
                $duties = $this->dutyService->dayDuties((int) $activeCampYear['id'], $activeDate);
                $dayTabs = $this->campYearService->dayTabs($activeCampYear, $activeDate);
                $placeSuggestion = $this->dutyService->suggestNextPlaceDutyOrder((int) $activeCampYear['id'], $activeDate);
            }
        } catch (Throwable $exception) {
            Logger::error('Duty list failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Die Dienstliste konnte nicht geladen werden. Details wurden protokolliert.');
        }

        return View::render('duties/index', [
            'title' => 'Dienste',
            'activeNav' => 'duties',
            'activeCampYear' => $activeCampYear,
            'activeDate' => $activeDate,
            'dayTabs' => $dayTabs,
            'duties' => $duties,
            'placeSuggestion' => $placeSuggestion,
            'statuses' => $this->dutyService->statuses(),
            'topbarDayChip' => $activeCampYear === null ? 'Lagerjahr offen' : $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    public function create(): string
    {
        if (!Auth::requirePermission('duties.manage')) {
            return '';
        }

        $activeCampYear = $this->campYearService->active();
        if ($activeCampYear === null) {
            Flash::add('error', 'Lege zuerst ein aktives Lagerjahr an.');
            Response::redirect('/dienste');
            return '';
        }

        $activeDate = $this->dutyService->normalizeDateForCampYear($activeCampYear, Request::get('tag'));
        $types = $this->dutyService->allTypes(true);
        $requestedType = $this->typeFromRequest($types, Request::get('typ'));
        $suggestedOrder = null;
        $orderIds = [];
        if (($requestedType['key_name'] ?? '') === 'platzdienst' && $activeDate !== null) {
            $suggestedOrder = $this->dutyService->suggestNextPlaceDutyOrder((int) $activeCampYear['id'], $activeDate);
            if (is_array($suggestedOrder)) {
                $orderIds[] = (int) $suggestedOrder['id'];
            }
        }

        $timeLabel = (string) ($requestedType['default_time_label'] ?? '');
        return $this->renderForm('Dienst anlegen', [
            'camp_year_id' => $activeCampYear['id'],
            'duty_date' => $activeDate,
            'duty_type_id' => $requestedType['id'] ?? '',
            'starts_at' => '',
            'ends_at' => '',
            'time_label' => $timeLabel,
            'title' => $requestedType['label'] ?? '',
            'description' => '',
            'status' => $orderIds === [] ? 'offen' : 'besetzt',
            'person_ids' => [],
            'order_ids' => $orderIds,
            'label_assignments' => [''],
        ], '/dienste', $suggestedOrder);
    }

    public function store(): string
    {
        if (!$this->guardManage()) {
            return '';
        }

        $payload = $this->payload();
        try {
            $this->dutyService->create($payload);
            Flash::add('success', 'Dienst wurde angelegt.');
            Response::redirect('/dienste?tag=' . urlencode((string) $payload['duty_date']));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Duty create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return $this->renderForm('Dienst anlegen', $payload, '/dienste');
        }
    }

    public function edit(): string
    {
        if (!Auth::requirePermission('duties.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/dienste/bearbeiten']), 404);
            return '';
        }

        $duty = $this->dutyService->find($id);
        if ($duty === null) {
            Response::html(View::render('errors/404', ['path' => '/dienste/bearbeiten']), 404);
            return '';
        }

        return $this->renderForm('Dienst bearbeiten', $duty, '/dienste/speichern');
    }

    public function update(): string
    {
        if (!$this->guardManage()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/dienste/speichern']), 404);
            return '';
        }

        $payload = $this->payload();
        try {
            $this->dutyService->update($id, $payload);
            Flash::add('success', 'Dienst wurde gespeichert.');
            Response::redirect('/dienste?tag=' . urlencode((string) $payload['duty_date']));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Duty update failed', ['duty_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            $payload['id'] = $id;
            return $this->renderForm('Dienst bearbeiten', $payload, '/dienste/speichern');
        }
    }

    public function status(): string
    {
        if (!Auth::requirePermission('duties.view')) {
            return '';
        }
        if (!Csrf::check((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        $id = Request::intFromPost('id');
        $status = trim((string) Request::post('status', ''));
        $date = trim((string) Request::post('duty_date', ''));
        if ($id !== null) {
            try {
                $this->dutyService->setStatus($id, $status);
                Flash::add('success', 'Dienststatus wurde aktualisiert.');
            } catch (Throwable $exception) {
                Logger::error('Duty status failed', ['duty_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }

        Response::redirect('/dienste' . ($date !== '' ? '?tag=' . urlencode($date) : ''));
        return '';
    }

    public function deactivate(): string
    {
        if (!$this->guardManage()) {
            return '';
        }

        $id = Request::intFromPost('id');
        $date = trim((string) Request::post('duty_date', ''));
        if ($id !== null) {
            try {
                $this->dutyService->deactivate($id);
                Flash::add('success', 'Dienst wurde entfernt.');
            } catch (Throwable $exception) {
                Logger::error('Duty deactivate failed', ['duty_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', 'Dienst konnte nicht entfernt werden.');
            }
        }

        Response::redirect('/dienste' . ($date !== '' ? '?tag=' . urlencode($date) : ''));
        return '';
    }

    public function types(): string
    {
        if (!Auth::requirePermission('duties.manage')) {
            return '';
        }

        return View::render('duty_types/index', [
            'title' => 'Dienstarten',
            'activeNav' => 'duties',
            'dutyTypes' => $this->dutyService->allTypes(false),
        ]);
    }

    public function createType(): string
    {
        if (!Auth::requirePermission('duties.manage')) {
            return '';
        }

        return $this->renderTypeForm('Dienstart anlegen', [
            'key_name' => '',
            'label' => '',
            'icon_key' => '◇',
            'default_time_label' => '',
            'assignment_mode' => 'mixed',
            'is_active' => 1,
            'sort_order' => 100,
        ], '/admin/dienstarten');
    }

    public function storeType(): string
    {
        if (!$this->guardManage()) {
            return '';
        }
        $payload = $this->typePayload();
        try {
            $this->dutyService->createType($payload);
            Flash::add('success', 'Dienstart wurde angelegt.');
            Response::redirect('/admin/dienstarten');
            return '';
        } catch (Throwable $exception) {
            Flash::add('error', $exception->getMessage());
            return $this->renderTypeForm('Dienstart anlegen', $payload, '/admin/dienstarten');
        }
    }

    public function editType(): string
    {
        if (!Auth::requirePermission('duties.manage')) {
            return '';
        }
        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/dienstarten/bearbeiten']), 404);
            return '';
        }
        $type = $this->dutyService->findType($id);
        if ($type === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/dienstarten/bearbeiten']), 404);
            return '';
        }
        return $this->renderTypeForm('Dienstart bearbeiten', $type, '/admin/dienstarten/speichern');
    }

    public function updateType(): string
    {
        if (!$this->guardManage()) {
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/dienstarten/speichern']), 404);
            return '';
        }
        $payload = $this->typePayload();
        try {
            $this->dutyService->updateType($id, $payload);
            Flash::add('success', 'Dienstart wurde gespeichert.');
            Response::redirect('/admin/dienstarten');
            return '';
        } catch (Throwable $exception) {
            Flash::add('error', $exception->getMessage());
            $payload['id'] = $id;
            return $this->renderTypeForm('Dienstart bearbeiten', $payload, '/admin/dienstarten/speichern');
        }
    }

    private function renderForm(string $title, array $duty, string $action, ?array $suggestedOrder = null): string
    {
        $activeCampYear = $this->campYearService->find((int) ($duty['camp_year_id'] ?? 0)) ?? $this->campYearService->active();
        $campYearId = $activeCampYear === null ? 0 : (int) $activeCampYear['id'];

        return View::render('duties/form', [
            'title' => $title,
            'activeNav' => 'duties',
            'activeCampYear' => $activeCampYear,
            'duty' => $duty,
            'dutyTypes' => $this->dutyService->allTypes(true),
            'statuses' => $this->dutyService->statuses(),
            'people' => $this->dutyService->peopleOptions(),
            'orders' => $campYearId > 0 ? $this->dutyService->orderOptions($campYearId) : [],
            'action' => $action,
            'backUrl' => '/dienste?tag=' . urlencode((string) ($duty['duty_date'] ?? '')),
            'suggestedOrder' => $suggestedOrder,
            'topbarDayChip' => $activeCampYear === null ? 'Lagerjahr offen' : $this->campYearService->dayLabel($activeCampYear),
        ]);
    }

    private function renderTypeForm(string $title, array $type, string $action): string
    {
        return View::render('duty_types/form', [
            'title' => $title,
            'activeNav' => 'duties',
            'dutyType' => $type,
            'assignmentModes' => $this->dutyService->assignmentModes(),
            'action' => $action,
            'backUrl' => '/admin/dienstarten',
        ]);
    }

    private function guardManage(): bool
    {
        if (!Auth::requirePermission('duties.manage')) {
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
            'duty_date' => Request::post('duty_date', ''),
            'duty_type_id' => Request::post('duty_type_id', ''),
            'starts_at' => Request::post('starts_at', ''),
            'ends_at' => Request::post('ends_at', ''),
            'time_label' => Request::post('time_label', ''),
            'title' => Request::post('title', ''),
            'description' => Request::post('description', ''),
            'status' => Request::post('status', 'offen'),
            'person_ids' => Request::post('person_ids', []),
            'order_ids' => Request::post('order_ids', []),
            'assignment_labels' => Request::post('assignment_labels', []),
        ];
    }

    private function typePayload(): array
    {
        return [
            'key_name' => Request::post('key_name', ''),
            'label' => Request::post('label', ''),
            'icon_key' => Request::post('icon_key', ''),
            'default_time_label' => Request::post('default_time_label', ''),
            'assignment_mode' => Request::post('assignment_mode', 'mixed'),
            'is_active' => Request::post('is_active') === '1',
            'sort_order' => Request::post('sort_order', 100),
        ];
    }

    private function typeFromRequest(array $types, mixed $rawKey): array
    {
        $key = trim((string) $rawKey);
        foreach ($types as $type) {
            if ($key !== '' && (string) $type['key_name'] === $key) {
                return $type;
            }
        }
        return $types[0] ?? [];
    }
}
