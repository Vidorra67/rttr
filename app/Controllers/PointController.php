<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PointService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class PointController
{
    private PointService $pointService;

    public function __construct()
    {
        $this->pointService = new PointService();
    }

    public function create(): string
    {
        if (!Auth::requirePermission('points.order.create')) {
            return '';
        }

        return View::render('points/create', $this->formData('Ordnung bewerten', $this->payload()));
    }

    public function store(): string
    {
        if (!Auth::requirePermission('points.order.create')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $this->pointService->createScoringEntry($this->payload());
            Flash::add('success', 'Bewertung wurde gespeichert.');
            Response::redirect('/ordnung');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Order score create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('points/create', $this->formData('Ordnung bewerten', $this->payload()));
        }
    }

    public function index(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }

        $filters = $this->filters();
        try {
            $activeCampYear = $this->pointService->activeCampYear();
            return View::render('points/index', [
                'title' => 'Ordnungspunkte',
                'activeCampYear' => $activeCampYear,
                'filters' => $filters,
                'entries' => $activeCampYear === null ? [] : $this->pointService->entries($filters),
                'orders' => $this->pointService->orderOptions($activeCampYear !== null ? (int) $activeCampYear['id'] : null),
                'participants' => $activeCampYear === null ? [] : $this->pointService->participantOptions((int) $activeCampYear['id'], '', 150),
                'categories' => $this->pointService->categories(true),
                'totals' => $activeCampYear === null ? [] : $this->pointService->orderTotals((int) $activeCampYear['id']),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Point entries index failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Ordnungspunkte konnten nicht geladen werden.');
            return View::render('points/index', [
                'title' => 'Ordnungspunkte',
                'activeCampYear' => null,
                'filters' => $filters,
                'entries' => [],
                'orders' => [],
                'participants' => [],
                'categories' => [],
                'totals' => [],
            ]);
        }
    }

    public function correction(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }

        $activeCampYear = $this->pointService->activeCampYear();
        return View::render('points/correction', [
            'title' => 'Punktekorrektur',
            'activeCampYear' => $activeCampYear,
            'participants' => $activeCampYear === null ? [] : $this->pointService->participantOptions((int) $activeCampYear['id'], '', 200),
            'orders' => $this->pointService->orderOptions($activeCampYear !== null ? (int) $activeCampYear['id'] : null),
            'categories' => $this->pointService->categories(true),
            'payload' => $this->payload(),
            'pointService' => $this->pointService,
        ]);
    }

    public function storeCorrection(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $this->pointService->createCorrection($this->payload());
            Flash::add('success', 'Korrektur wurde gebucht.');
            Response::redirect('/admin/ordnungspunkte');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Point correction failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return $this->correction();
        }
    }

    public function void(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $this->pointService->voidEntry($id, (string) Request::post('void_reason', ''));
                Flash::add('success', 'Punkteeintrag wurde storniert.');
            } catch (Throwable $exception) {
                Logger::error('Point entry void failed', ['entry_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }

        Response::redirect('/admin/ordnungspunkte');
        return '';
    }


    public function competition(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }
        return View::render('points/competition', $this->competitionData($this->competitionPayload()));
    }

    public function storeCompetition(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }
        $payload = $this->competitionPayload();
        try {
            $count = $this->pointService->createCompetitionEntries($payload);
            Flash::add('success', $count . ' Spielwertungen wurden gespeichert.');
            Response::redirect('/admin/ordnungspunkte');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Competition score failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('points/competition', $this->competitionData($payload));
        }
    }

    public function batchZelt(): string
    {
        if (!Auth::requirePermission('points.order.create')) {
            return '';
        }
        return View::render('points/batch_order', $this->batchData('zelt', $this->batchPayload()));
    }

    public function storeBatchZelt(): string
    {
        return $this->storeBatch('zelt');
    }

    public function batchGeschirr(): string
    {
        if (!Auth::requirePermission('points.order.create')) {
            return '';
        }
        return View::render('points/batch_order', $this->batchData('geschirr', $this->batchPayload()));
    }

    public function storeBatchGeschirr(): string
    {
        return $this->storeBatch('geschirr');
    }

    public function dutyBonus(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }
        return View::render('points/duty_bonus', $this->dutyData($this->dutyPayload()));
    }

    public function storeDutyBonus(): string
    {
        if (!Auth::requirePermission('points.manage')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }
        $payload = $this->dutyPayload();
        try {
            $count = $this->pointService->createDutyBonusEntries($payload);
            Flash::add('success', $count . ' Dienstpunkte wurden gespeichert.');
            Response::redirect('/admin/ordnungspunkte');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Duty bonus score failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('points/duty_bonus', $this->dutyData($payload));
        }
    }

    private function storeBatch(string $mode): string
    {
        if (!Auth::requirePermission('points.order.create')) {
            return '';
        }
        if (!Csrf::verify(Request::post('_csrf'))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }
        $payload = $this->batchPayload();
        try {
            $count = $this->pointService->createBatchOrderAssessment($payload, $mode);
            Flash::add('success', $count . ' Bewertungen wurden gespeichert.');
            Response::redirect('/ordnung');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Batch order score failed', ['mode' => $mode, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('points/batch_order', $this->batchData($mode, $payload));
        }
    }

    private function competitionData(array $payload): array
    {
        $activeCampYear = $this->pointService->activeCampYear();
        $activeDate = $this->pointService->normalizeDateForCampYear($activeCampYear, $payload['scoring_date'] ?? Request::get('tag', ''));
        $payload['scoring_date'] = $activeDate ?? '';
        return [
            'title' => 'Spielwertung erfassen',
            'activeCampYear' => $activeCampYear,
            'dayTabs' => $this->pointService->dayTabs($activeCampYear, $activeDate),
            'activeDate' => $activeDate,
            'orders' => $this->pointService->orderOptions($activeCampYear !== null ? (int) $activeCampYear['id'] : null),
            'payload' => $payload,
        ];
    }

    private function batchData(string $mode, array $payload): array
    {
        $activeCampYear = $this->pointService->activeCampYear();
        $campYearId = $activeCampYear !== null ? (int) $activeCampYear['id'] : null;
        $activeDate = $this->pointService->normalizeDateForCampYear($activeCampYear, $payload['scoring_date'] ?? Request::get('tag', ''));
        $payload['scoring_date'] = $activeDate ?? '';
        $orders = $this->pointService->orderOptions($campYearId);
        $orderId = filter_var($payload['order_id'] ?? Request::get('order_id', ''), FILTER_VALIDATE_INT);
        $participants = ($campYearId !== null && $orderId !== false && $orderId > 0)
            ? $this->pointService->participantsForOrder($campYearId, (int) $orderId)
            : [];
        return [
            'title' => $mode === 'zelt' ? 'Zelt bewerten' : 'Geschirr bewerten',
            'mode' => $mode,
            'activeCampYear' => $activeCampYear,
            'dayTabs' => $this->pointService->dayTabs($activeCampYear, $activeDate),
            'activeDate' => $activeDate,
            'orders' => $orders,
            'participants' => $participants,
            'payload' => $payload,
        ];
    }

    private function dutyData(array $payload): array
    {
        $activeCampYear = $this->pointService->activeCampYear();
        $campYearId = $activeCampYear !== null ? (int) $activeCampYear['id'] : null;
        $activeDate = $this->pointService->normalizeDateForCampYear($activeCampYear, $payload['scoring_date'] ?? Request::get('tag', ''));
        $payload['scoring_date'] = $activeDate ?? '';
        return [
            'title' => 'Küchendienst bewerten',
            'activeCampYear' => $activeCampYear,
            'dayTabs' => $this->pointService->dayTabs($activeCampYear, $activeDate),
            'activeDate' => $activeDate,
            'duties' => ($campYearId !== null && $activeDate !== null) ? $this->pointService->dutiesForDate($campYearId, $activeDate) : [],
            'orders' => $this->pointService->orderOptions($campYearId),
            'payload' => $payload,
        ];
    }

    private function competitionPayload(): array
    {
        return [
            'game_title' => Request::post('game_title', Request::get('game_title', '')),
            'scoring_date' => Request::post('scoring_date', Request::get('scoring_date', '')),
            'placements' => Request::post('placements', []),
        ];
    }

    private function batchPayload(): array
    {
        return [
            'order_id' => Request::post('order_id', Request::get('order_id', '')),
            'scoring_date' => Request::post('scoring_date', Request::get('scoring_date', '')),
            'check_slot' => Request::post('check_slot', Request::get('check_slot', 'morgens')),
            'order_points' => Request::post('order_points', '5'),
            'person_points' => Request::post('person_points', []),
            'reason' => Request::post('reason', ''),
        ];
    }

    private function dutyPayload(): array
    {
        return [
            'duty_id' => Request::post('duty_id', Request::get('duty_id', '')),
            'scoring_date' => Request::post('scoring_date', Request::get('scoring_date', '')),
            'category_key' => Request::post('category_key', 'zusatz_kuechendienst_1'),
            'person_points' => Request::post('person_points', []),
            'q' => Request::post('q', Request::get('q', '')),
        ];
    }

    private function formData(string $title, array $payload): array
    {
        $activeCampYear = $this->pointService->activeCampYear();
        $campYearId = $activeCampYear !== null ? (int) $activeCampYear['id'] : null;
        $query = trim((string) ($payload['q'] ?? Request::get('q', '')));
        $requestedDate = $payload['scoring_date'] ?? Request::get('tag', '');
        $activeDate = $this->pointService->normalizeDateForCampYear($activeCampYear, $requestedDate);
        $payload['scoring_date'] = $activeDate ?? '';

        return [
            'title' => $title,
            'activeCampYear' => $activeCampYear,
            'dayTabs' => $this->pointService->dayTabs($activeCampYear, $activeDate),
            'activeDate' => $activeDate,
            'participants' => $campYearId === null ? [] : $this->pointService->participantOptions($campYearId, $query),
            'orders' => $this->pointService->orderOptions($campYearId),
            'categories' => $this->pointService->categories(true),
            'ownTodayEntries' => $this->pointService->ownTodayEntries($activeDate),
            'payload' => $payload,
            'pointService' => $this->pointService,
        ];
    }

    private function payload(): array
    {
        return [
            'category_id' => Request::post('category_id', Request::get('category_id', '')),
            'person_id' => Request::post('person_id', Request::get('person_id', '')),
            'order_id' => Request::post('order_id', Request::get('order_id', '')),
            'scoring_date' => Request::post('scoring_date', Request::get('scoring_date', '')),
            'check_slot' => Request::post('check_slot', Request::get('check_slot', '')),
            'points' => Request::post('points', ''),
            'reason' => Request::post('reason', ''),
            'q' => Request::post('q', Request::get('q', '')),
        ];
    }

    private function filters(): array
    {
        return [
            'date' => trim((string) Request::get('date', '')),
            'category_id' => Request::get('category_id', ''),
            'order_id' => Request::get('order_id', ''),
            'person_id' => Request::get('person_id', ''),
            'include_voided' => (string) Request::get('include_voided', '') === '1',
        ];
    }
}
