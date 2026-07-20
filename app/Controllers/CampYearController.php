<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CampYearService;
use App\Services\PersonService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class CampYearController
{
    private CampYearService $campYearService;

    public function __construct()
    {
        $this->campYearService = new CampYearService();
    }

    public function index(): string
    {
        if (!Auth::requirePermission('camp_years.view')) {
            return '';
        }

        try {
            return View::render('camp_years/index', [
                'title' => 'Lagerjahre',
                'campYears' => $this->campYearService->all(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Camp years index failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Lagerjahre konnten nicht geladen werden.');
            return View::render('camp_years/index', [
                'title' => 'Lagerjahre',
                'campYears' => [],
            ]);
        }
    }

    public function create(): string
    {
        if (!Auth::requirePermission('camp_years.manage')) {
            return '';
        }

        return View::render('camp_years/form', [
            'title' => 'Lagerjahr anlegen',
            'campYear' => null,
            'action' => '/admin/lagerjahre',
        ]);
    }

    public function store(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        try {
            $this->campYearService->create($this->payload());
            Flash::add('success', 'Lagerjahr wurde angelegt.');
            Response::redirect('/admin/lagerjahre');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Camp year create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('camp_years/form', [
                'title' => 'Lagerjahr anlegen',
                'campYear' => $this->payload(),
                'action' => '/admin/lagerjahre',
            ]);
        }
    }

    public function edit(): string
    {
        if (!Auth::requirePermission('camp_years.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/lagerjahre/bearbeiten']), 404);
            return '';
        }

        $campYear = $this->campYearService->find($id);
        if ($campYear === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/lagerjahre/bearbeiten']), 404);
            return '';
        }

        return View::render('camp_years/form', [
            'title' => 'Lagerjahr bearbeiten',
            'campYear' => $campYear,
            'action' => '/admin/lagerjahre/speichern',
        ]);
    }

    public function update(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/lagerjahre/speichern']), 404);
            return '';
        }

        try {
            $this->campYearService->update($id, $this->payload());
            Flash::add('success', 'Lagerjahr wurde gespeichert.');
            Response::redirect('/admin/lagerjahre');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Camp year update failed', ['camp_year_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            $campYear = $this->payload();
            $campYear['id'] = $id;
            return View::render('camp_years/form', [
                'title' => 'Lagerjahr bearbeiten',
                'campYear' => $campYear,
                'action' => '/admin/lagerjahre/speichern',
            ]);
        }
    }

    public function activate(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $this->campYearService->setActive($id);
                Flash::add('success', 'Aktives Lagerjahr wurde geändert.');
            } catch (Throwable $exception) {
                Logger::error('Camp year activate failed', ['camp_year_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', 'Aktives Lagerjahr konnte nicht geändert werden.');
            }
        }

        Response::redirect('/admin/lagerjahre');
        return '';
    }

    public function rosterTransfer(): string
    {
        if (!Auth::requirePermission('camp_years.manage')) {
            return '';
        }

        $toId = Request::intFromGet('to');
        $toCampYear = $toId !== null ? $this->campYearService->find($toId) : null;
        if ($toId === null || $toCampYear === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/lagerjahre/uebernahme']), 404);
            return '';
        }

        $earlierYears = array_values(array_filter(
            $this->campYearService->all(),
            static fn (array $year): bool => (int) $year['id'] !== $toId
                && strtotime((string) $year['starts_on']) < strtotime((string) $toCampYear['starts_on'])
        ));

        $fromId = Request::intFromGet('from');
        $fromCampYear = $fromId !== null ? $this->campYearService->find($fromId) : null;
        if ($fromCampYear === null) {
            $fromCampYear = $earlierYears[0] ?? null;
        }

        $roster = $fromCampYear !== null
            ? (new PersonService())->previousYearRoster((int) $fromCampYear['id'], $toId)
            : [];

        return View::render('camp_years/roster_transfer', [
            'title' => 'Teilnehmer übernehmen',
            'toCampYear' => $toCampYear,
            'fromCampYear' => $fromCampYear,
            'earlierYears' => $earlierYears,
            'roster' => $roster,
        ]);
    }

    public function storeRosterTransfer(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $toId = Request::intFromPost('to');
        $fromId = Request::intFromPost('from');
        $personIds = Request::post('person_ids', []);

        if ($toId === null || $fromId === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/lagerjahre/uebernahme']), 404);
            return '';
        }

        try {
            $count = (new PersonService())->transferParticipants($fromId, $toId, is_array($personIds) ? $personIds : []);
            Flash::add('success', $count . ' Teilnehmer wurden übernommen.');
        } catch (Throwable $exception) {
            Logger::error('Roster transfer failed', ['from' => $fromId, 'to' => $toId, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }

        Response::redirect('/admin/lagerjahre/uebernahme?to=' . $toId . '&from=' . $fromId);
        return '';
    }

    private function guardWrite(): bool
    {
        if (!Auth::requirePermission('camp_years.manage')) {
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
            'name' => Request::post('name', ''),
            'location_name' => Request::post('location_name', ''),
            'starts_on' => Request::post('starts_on', ''),
            'ends_on' => Request::post('ends_on', ''),
            'is_active' => Request::post('is_active', '0') === '1',
        ];
    }
}
