<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PersonService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class PersonController
{
    private PersonService $personService;

    public function __construct()
    {
        $this->personService = new PersonService();
    }

    public function index(): string
    {
        if (!Auth::requirePermission('persons.view')) {
            return '';
        }

        $filters = $this->filters();
        try {
            $activeCampYear = $this->personService->activeCampYear();
            return View::render('persons/index', [
                'title' => 'Personen',
                'persons' => $this->personService->all($filters),
                'filters' => $filters,
                'activeCampYear' => $activeCampYear,
                'orders' => $this->personService->orderOptions($activeCampYear !== null ? (int) $activeCampYear['id'] : null),
                'canViewSensitive' => $this->personService->canViewSensitive(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Persons index failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Personen konnten nicht geladen werden.');
            return View::render('persons/index', [
                'title' => 'Personen',
                'persons' => [],
                'filters' => $filters,
                'activeCampYear' => null,
                'orders' => [],
                'canViewSensitive' => $this->personService->canViewSensitive(),
            ]);
        }
    }

    public function show(): string
    {
        if (!Auth::requirePermission('persons.view')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/personen/detail']), 404);
            return '';
        }

        $person = $this->personService->find($id);
        if ($person === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/personen/detail']), 404);
            return '';
        }

        return View::render('persons/show', [
            'title' => 'Person',
            'person' => $person,
            'activeCampYear' => $this->personService->activeCampYear(),
            'canViewSensitive' => $this->personService->canViewSensitive(),
        ]);
    }

    public function create(): string
    {
        if (!Auth::requirePermission('persons.manage')) {
            return '';
        }

        return View::render('persons/form', $this->formData('Person anlegen', null, '/admin/personen'));
    }

    public function store(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        try {
            $this->personService->create($this->payload());
            Flash::add('success', 'Person wurde angelegt.');
            Response::redirect('/admin/personen');
            return '';
        } catch (Throwable $exception) {
            Logger::error('Person create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return View::render('persons/form', $this->formData('Person anlegen', $this->payload(), '/admin/personen'));
        }
    }

    public function edit(): string
    {
        if (!Auth::requirePermission('persons.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/personen/bearbeiten']), 404);
            return '';
        }

        $person = $this->personService->find($id);
        if ($person === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/personen/bearbeiten']), 404);
            return '';
        }

        return View::render('persons/form', $this->formData('Person bearbeiten', $person, '/admin/personen/speichern'));
    }

    public function update(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/personen/speichern']), 404);
            return '';
        }

        try {
            $this->personService->update($id, $this->payload());
            Flash::add('success', 'Person wurde gespeichert.');
            Response::redirect('/admin/personen/detail?id=' . $id);
            return '';
        } catch (Throwable $exception) {
            Logger::error('Person update failed', ['person_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            $person = $this->payload();
            $person['id'] = $id;
            return View::render('persons/form', $this->formData('Person bearbeiten', $person, '/admin/personen/speichern'));
        }
    }

    public function toggleLogin(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id !== null) {
            try {
                $this->personService->setLoginEnabled($id, (string) Request::post('enabled', '0') === '1');
                Flash::add('success', 'Loginstatus wurde geändert.');
            } catch (Throwable $exception) {
                Logger::error('Login toggle failed', ['person_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', 'Loginstatus konnte nicht geändert werden.');
            }
        }

        Response::redirect('/admin/personen');
        return '';
    }

    public function resetPin(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        $pin = (string) Request::post('pin', '');
        if ($id !== null) {
            try {
                $this->personService->resetPin($id, $pin);
                Flash::add('success', 'PIN wurde geändert.');
            } catch (Throwable $exception) {
                Logger::error('PIN reset failed', ['person_id' => $id, 'message' => $exception->getMessage()]);
                Flash::add('error', $exception->getMessage());
            }
        }

        Response::redirect('/admin/personen');
        return '';
    }

    private function formData(string $title, ?array $person, string $action): array
    {
        $activeCampYear = $this->personService->activeCampYear();
        $campYearId = $activeCampYear !== null ? (int) $activeCampYear['id'] : null;
        $selectedCampYear = $this->nullableInt($person['camp_year_id'] ?? null) ?? $campYearId;

        return [
            'title' => $title,
            'person' => $person,
            'roles' => $this->personService->roles(),
            'campYears' => $this->personService->campYears(),
            'activeCampYear' => $activeCampYear,
            'selectedCampYearId' => $selectedCampYear,
            'orders' => $this->personService->orderOptions($selectedCampYear),
            'rankLevels' => $this->personService->rankOptions($selectedCampYear),
            'canViewSensitive' => $this->personService->canViewSensitive(),
            'action' => $action,
        ];
    }

    private function guardWrite(): bool
    {
        if (!Auth::requirePermission('persons.manage')) {
            return false;
        }

        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return false;
        }

        return true;
    }

    private function filters(): array
    {
        return [
            'q' => trim((string) Request::get('q', '')),
            'type' => (string) Request::get('type', ''),
            'order_id' => (string) Request::get('order_id', ''),
            'active' => (string) Request::get('active', ''),
            'birthday_in_camp' => (string) Request::get('birthday_in_camp', '') === '1' ? '1' : '',
        ];
    }

    private function payload(): array
    {
        return [
            'id' => Request::post('id'),
            'first_name' => Request::post('first_name', ''),
            'last_name' => Request::post('last_name', ''),
            'display_name' => Request::post('display_name', ''),
            'nickname' => Request::post('nickname', ''),
            'birthdate' => Request::post('birthdate', ''),
            'type_hint' => Request::post('type_hint', 'mitarbeiter'),
            'street' => Request::post('street', ''),
            'zip' => Request::post('zip', ''),
            'city' => Request::post('city', ''),
            'phone' => Request::post('phone', ''),
            'email' => Request::post('email', ''),
            'emergency_contact_name' => Request::post('emergency_contact_name', ''),
            'emergency_contact_phone' => Request::post('emergency_contact_phone', ''),
            'food_notes' => Request::post('food_notes', ''),
            'allergy_notes' => Request::post('allergy_notes', ''),
            'medical_notes' => Request::post('medical_notes', ''),
            'internal_notes' => Request::post('internal_notes', ''),
            'camp_year_id' => Request::post('camp_year_id', ''),
            'is_participant' => Request::post('is_participant', '0') === '1',
            'is_staff' => Request::post('is_staff', '0') === '1',
            'participant_status' => Request::post('participant_status', 'angemeldet'),
            'staff_status' => Request::post('staff_status', 'aktiv'),
            'order_id' => Request::post('order_id', ''),
            'rank_label' => Request::post('rank_label', ''),
            'rank_level_id' => Request::post('rank_level_id', ''),
            'next_rank_level_id' => Request::post('next_rank_level_id', ''),
            'next_rank_label' => Request::post('next_rank_label', ''),
            'promotion_status' => Request::post('promotion_status', 'offen'),
            'promotion_note' => Request::post('promotion_note', ''),
            'guardian_name' => Request::post('guardian_name', ''),
            'guardian_relation_label' => Request::post('guardian_relation_label', ''),
            'guardian_phone' => Request::post('guardian_phone', ''),
            'guardian_email' => Request::post('guardian_email', ''),
            'guardian_address_text' => Request::post('guardian_address_text', ''),
            'is_active' => Request::post('is_active', '0') === '1',
            'is_login_enabled' => Request::post('is_login_enabled', '0') === '1',
            'pin' => Request::post('pin', ''),
            'roles' => is_array(Request::post('roles', [])) ? Request::post('roles', []) : [],
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : (int) $filtered;
    }
}
