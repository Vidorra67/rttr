<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ScoreService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class ScoreController
{
    private ScoreService $scoreService;

    public function __construct()
    {
        $this->scoreService = new ScoreService();
    }

    public function index(): string
    {
        if (!Auth::requirePermission('exams.view')) {
            return '';
        }

        $activeCampYear = $this->scoreService->activeCampYear();
        $campYearId = $activeCampYear !== null ? (int) $activeCampYear['id'] : null;
        $orderId = Request::intFromGet('order_id');

        try {
            return View::render('scores/index', [
                'title' => 'Auswertung',
                'activeCampYear' => $activeCampYear,
                'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
                'orders' => $campYearId === null ? [] : $this->scoreService->orders($campYearId),
                'selectedOrderId' => $orderId,
                'rankLevels' => $campYearId === null ? [] : $this->scoreService->rankLevels($campYearId),
                'orderSummary' => $campYearId === null ? [] : $this->scoreService->orderSummary($campYearId),
                'matrix' => $campYearId === null ? ['participants' => [], 'units' => []] : $this->scoreService->examMatrix($campYearId, $orderId),
                'resultLabels' => ScoreService::RESULT_LABELS,
                'canManage' => $this->scoreService->canManage(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Score index failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Auswertung konnte nicht geladen werden.');
            return View::render('scores/index', [
                'title' => 'Auswertung',
                'activeCampYear' => $activeCampYear,
                'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
                'orders' => [],
                'selectedOrderId' => $orderId,
                'rankLevels' => [],
                'orderSummary' => [],
                'matrix' => ['participants' => [], 'units' => []],
                'resultLabels' => ScoreService::RESULT_LABELS,
                'canManage' => false,
            ]);
        }
    }

    public function export(): string
    {
        if (!Auth::requirePermission('exams.view')) {
            return '';
        }

        $activeCampYear = $this->scoreService->activeCampYear();
        if ($activeCampYear === null) {
            Response::redirect('/admin/auswertung');
            return '';
        }

        try {
            $csv = $this->scoreService->exportCsv((int) $activeCampYear['id'], Request::intFromGet('order_id'));
            $filename = 'ritterlager_auswertung_' . date('Y-m-d_His') . '.csv';
            http_response_code(200);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('X-Content-Type-Options: nosniff');
            echo $csv;
            exit;
        } catch (Throwable $exception) {
            Logger::error('Score export failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'CSV-Export konnte nicht erstellt werden.');
            Response::redirect('/admin/auswertung');
            return '';
        }
    }

    public function rankLevels(): string
    {
        if (!Auth::requirePermission('exams.view')) {
            return '';
        }
        return View::render('scores/rank_levels', $this->rankLevelData());
    }

    public function createRankLevel(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        return View::render('scores/rank_level_form', $this->rankLevelFormData(null, 'Rangstufe anlegen'));
    }

    public function storeRankLevel(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }

        try {
            $this->scoreService->saveRankLevel($this->rankPayload());
            Flash::add('success', 'Rangstufe wurde gespeichert.');
            Response::redirect('/admin/rangstufen');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Rank level store failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('scores/rank_level_form', $this->rankLevelFormData(null, 'Rangstufe anlegen', $this->rankPayload()));
        }
    }

    public function editRankLevel(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        $rank = $this->rankFromRequest();
        return View::render('scores/rank_level_form', $this->rankLevelFormData($rank, 'Rangstufe bearbeiten'));
    }

    public function updateRankLevel(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id === null) {
            Flash::add('error', 'Rangstufe wurde nicht gefunden.');
            Response::redirect('/admin/rangstufen');
            return '';
        }

        try {
            $this->scoreService->saveRankLevel($this->rankPayload(), $id);
            Flash::add('success', 'Rangstufe wurde aktualisiert.');
            Response::redirect('/admin/rangstufen');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Rank level update failed', ['rank_level_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('scores/rank_level_form', $this->rankLevelFormData($this->rankPayload() + ['id' => $id], 'Rangstufe bearbeiten'));
        }
    }

    public function learningUnits(): string
    {
        if (!Auth::requirePermission('exams.view')) {
            return '';
        }
        return View::render('scores/learning_units', $this->learningUnitData());
    }

    public function createLearningUnit(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        return View::render('scores/learning_unit_form', $this->learningUnitFormData(null, 'Lerneinheit anlegen'));
    }

    public function storeLearningUnit(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        try {
            $this->scoreService->saveLearningUnit($this->learningUnitPayload());
            Flash::add('success', 'Lerneinheit wurde gespeichert.');
            Response::redirect('/admin/lerneinheiten');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Learning unit store failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('scores/learning_unit_form', $this->learningUnitFormData($this->learningUnitPayload(), 'Lerneinheit anlegen'));
        }
    }

    public function editLearningUnit(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        $unit = $this->learningUnitFromRequest();
        return View::render('scores/learning_unit_form', $this->learningUnitFormData($unit, 'Lerneinheit bearbeiten'));
    }

    public function updateLearningUnit(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id === null) {
            Flash::add('error', 'Lerneinheit wurde nicht gefunden.');
            Response::redirect('/admin/lerneinheiten');
            return '';
        }
        try {
            $this->scoreService->saveLearningUnit($this->learningUnitPayload(), $id);
            Flash::add('success', 'Lerneinheit wurde aktualisiert.');
            Response::redirect('/admin/lerneinheiten');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Learning unit update failed', ['learning_unit_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('scores/learning_unit_form', $this->learningUnitFormData($this->learningUnitPayload() + ['id' => $id], 'Lerneinheit bearbeiten'));
        }
    }

    public function deactivateLearningUnit(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $this->scoreService->deactivateLearningUnit($id);
                Flash::add('success', 'Lerneinheit wurde deaktiviert.');
            } catch (Throwable $exception) {
                Logger::error('Learning unit deactivate failed', ['learning_unit_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }
        Response::redirect('/admin/lerneinheiten');
        return '';
    }

    public function exams(): string
    {
        if (!Auth::requirePermission('exams.view')) {
            return '';
        }

        $activeCampYear = $this->scoreService->activeCampYear();
        $campYearId = $activeCampYear !== null ? (int) $activeCampYear['id'] : null;
        $orderId = Request::intFromGet('order_id');

        return View::render('scores/exams', [
            'title' => 'Prüfungsergebnisse',
            'activeCampYear' => $activeCampYear,
            'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
            'orders' => $campYearId === null ? [] : $this->scoreService->orders($campYearId),
            'selectedOrderId' => $orderId,
            'participants' => $campYearId === null ? [] : $this->scoreService->participants($campYearId, $orderId),
            'learningUnits' => $campYearId === null ? [] : $this->scoreService->learningUnits($campYearId),
            'rankLevels' => $campYearId === null ? [] : $this->scoreService->rankLevels($campYearId),
            'results' => $campYearId === null ? [] : $this->scoreService->examResults($campYearId, $orderId),
            'resultLabels' => ScoreService::RESULT_LABELS,
            'promotionStatusLabels' => ScoreService::PROMOTION_STATUS_LABELS,
            'canManage' => $this->scoreService->canManage(),
            'payload' => $this->examPayload(),
        ]);
    }

    public function saveExam(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        try {
            $this->scoreService->saveExamResult($this->examPayload());
            Flash::add('success', 'Prüfungsergebnis wurde gespeichert.');
        } catch (Throwable $exception) {
            Logger::error('Exam result save failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }
        Response::redirect('/admin/pruefungen');
        return '';
    }

    public function saveRankAssignment(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        $activeCampYear = $this->scoreService->activeCampYear();
        $personId = Request::intFromPost('person_id');
        if ($activeCampYear === null || $personId === null) {
            Flash::add('error', 'Rang konnte nicht gespeichert werden.');
            Response::redirect('/admin/pruefungen');
            return '';
        }
        try {
            $rankLevelId = Request::intFromPost('rank_level_id');
            $this->scoreService->saveParticipantRank((int) $activeCampYear['id'], $personId, $rankLevelId, (string) Request::post('rank_label', ''));
            Flash::add('success', 'Rang wurde gespeichert.');
        } catch (Throwable $exception) {
            Logger::error('Rank assignment save failed', ['person_id' => $personId, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }
        Response::redirect('/admin/pruefungen');
        return '';
    }


    public function savePromotion(): string
    {
        if (!$this->requireManage()) {
            return '';
        }
        if (!$this->verifyCsrf()) {
            return '';
        }
        $activeCampYear = $this->scoreService->activeCampYear();
        $personId = Request::intFromPost('person_id');
        if ($activeCampYear === null || $personId === null) {
            Flash::add('error', 'Rangvorschlag konnte nicht gespeichert werden.');
            Response::redirect('/admin/pruefungen');
            return '';
        }
        try {
            $this->scoreService->savePromotion(
                (int) $activeCampYear['id'],
                $personId,
                Request::intFromPost('next_rank_level_id'),
                (string) Request::post('promotion_status', 'offen'),
                (string) Request::post('promotion_note', '')
            );
            Flash::add('success', 'Folgerang wurde gespeichert.');
        } catch (Throwable $exception) {
            Logger::error('Promotion save failed', ['person_id' => $personId, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }
        Response::redirect('/admin/pruefungen');
        return '';
    }

    private function rankLevelData(): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'title' => 'Rangstufen',
            'activeCampYear' => $activeCampYear,
            'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
            'rankLevels' => $activeCampYear === null ? [] : $this->scoreService->rankLevels((int) $activeCampYear['id']),
            'canManage' => $this->scoreService->canManage(),
        ];
    }

    private function learningUnitData(): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'title' => 'Lerneinheiten',
            'activeCampYear' => $activeCampYear,
            'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
            'learningUnits' => $activeCampYear === null ? [] : $this->scoreService->learningUnits((int) $activeCampYear['id']),
            'canManage' => $this->scoreService->canManage(),
        ];
    }

    private function rankLevelFormData(array|null $rank, string $title, ?array $payload = null): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'title' => $title,
            'activeCampYear' => $activeCampYear,
            'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
            'rank' => $payload ?? $rank ?? [],
        ];
    }

    private function learningUnitFormData(array|null $unit, string $title): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'title' => $title,
            'activeCampYear' => $activeCampYear,
            'topbarDayChip' => $this->scoreService->dayLabel($activeCampYear),
            'unit' => $unit ?? [],
        ];
    }

    private function rankFromRequest(): array
    {
        $id = Request::intFromGet('id');
        $rank = $id === null ? null : $this->scoreService->rankLevel($id);
        if ($rank === null) {
            Flash::add('error', 'Rangstufe wurde nicht gefunden.');
            Response::redirect('/admin/rangstufen');
        }
        return $rank ?? [];
    }

    private function learningUnitFromRequest(): array
    {
        $id = Request::intFromGet('id');
        $unit = $id === null ? null : $this->scoreService->learningUnit($id);
        if ($unit === null) {
            Flash::add('error', 'Lerneinheit wurde nicht gefunden.');
            Response::redirect('/admin/lerneinheiten');
        }
        return $unit ?? [];
    }

    private function rankPayload(): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'camp_year_id' => $activeCampYear['id'] ?? Request::post('camp_year_id', ''),
            'key_name' => Request::post('key_name', ''),
            'label' => Request::post('label', ''),
            'sort_order' => Request::post('sort_order', '100'),
            'promotion_points_required' => Request::post('promotion_points_required', ''),
            'next_rank_key' => Request::post('next_rank_key', ''),
            'promotion_text' => Request::post('promotion_text', ''),
        ];
    }

    private function learningUnitPayload(): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'camp_year_id' => $activeCampYear['id'] ?? Request::post('camp_year_id', ''),
            'title' => Request::post('title', ''),
            'category_key' => Request::post('category_key', 'lernen'),
            'responsible_label' => Request::post('responsible_label', ''),
            'sort_order' => Request::post('sort_order', '100'),
        ];
    }

    private function examPayload(): array
    {
        $activeCampYear = $this->scoreService->activeCampYear();
        return [
            'camp_year_id' => $activeCampYear['id'] ?? Request::post('camp_year_id', ''),
            'person_id' => Request::post('person_id', ''),
            'learning_unit_id' => Request::post('learning_unit_id', ''),
            'result_status' => Request::post('result_status', 'bestanden'),
            'points' => Request::post('points', ''),
            'note' => Request::post('note', ''),
        ];
    }

    private function requireManage(): bool
    {
        if (!Auth::requireLogin()) {
            return false;
        }
        if ($this->scoreService->canManage()) {
            return true;
        }
        Response::html(View::render('errors/403', ['permission' => 'exams.manage']), 403);
        return false;
    }

    private function verifyCsrf(): bool
    {
        if (Csrf::verify(Request::post('_csrf'))) {
            return true;
        }
        Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
        return false;
    }
}
