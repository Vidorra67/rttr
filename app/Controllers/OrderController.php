<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CampYearService;
use App\Services\OrderService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class OrderController
{
    private CampYearService $campYearService;
    private OrderService $orderService;

    public function __construct()
    {
        $this->campYearService = new CampYearService();
        $this->orderService = new OrderService();
    }

    public function index(): string
    {
        if (!Auth::requirePermission('orders.view')) {
            return '';
        }

        try {
            $campYears = $this->campYearService->all();
            $campYear = $this->selectedCampYear($campYears);
            $orders = $campYear === null ? [] : $this->orderService->allForCampYear((int) $campYear['id']);

            return View::render('orders/index', [
                'title' => 'Orden/Zelte',
                'campYears' => $campYears,
                'campYear' => $campYear,
                'orders' => $orders,
            ]);
        } catch (Throwable $exception) {
            Logger::error('Orders index failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Orden/Zelte konnten nicht geladen werden.');
            return View::render('orders/index', [
                'title' => 'Orden/Zelte',
                'campYears' => [],
                'campYear' => null,
                'orders' => [],
            ]);
        }
    }

    public function create(): string
    {
        if (!Auth::requirePermission('orders.manage')) {
            return '';
        }

        return $this->form('Orden/Zelt anlegen', null, '/admin/orden');
    }

    public function store(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        try {
            $this->orderService->create($this->payload());
            Flash::add('success', 'Orden/Zelt wurde angelegt.');
            Response::redirect('/admin/orden?camp_year_id=' . (int) Request::post('camp_year_id', 0));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Order create failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            return $this->form('Orden/Zelt anlegen', $this->payload(), '/admin/orden');
        }
    }

    public function edit(): string
    {
        if (!Auth::requirePermission('orders.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/orden/bearbeiten']), 404);
            return '';
        }

        $order = $this->orderService->find($id);
        if ($order === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/orden/bearbeiten']), 404);
            return '';
        }

        return $this->form('Orden/Zelt bearbeiten', $order, '/admin/orden/speichern');
    }

    public function update(): string
    {
        if (!$this->guardWrite()) {
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/orden/speichern']), 404);
            return '';
        }

        try {
            $this->orderService->update($id, $this->payload());
            Flash::add('success', 'Orden/Zelt wurde gespeichert.');
            Response::redirect('/admin/orden?camp_year_id=' . (int) Request::post('camp_year_id', 0));
            return '';
        } catch (Throwable $exception) {
            Logger::error('Order update failed', ['order_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            $order = $this->payload();
            $order['id'] = $id;
            return $this->form('Orden/Zelt bearbeiten', $order, '/admin/orden/speichern');
        }
    }

    private function form(string $title, ?array $order, string $action): string
    {
        $campYears = $this->campYearService->all();
        $activeCampYear = $this->campYearService->active();

        if ($campYears === []) {
            Flash::add('error', 'Lege zuerst ein Lagerjahr an.');
        }

        return View::render('orders/form', [
            'title' => $title,
            'order' => $order,
            'action' => $action,
            'campYears' => $campYears,
            'activeCampYear' => $activeCampYear,
            'staffOptions' => $this->orderService->staffOptions(),
            'colorOptions' => $this->orderService->colorOptions(),
        ]);
    }

    private function selectedCampYear(array $campYears): ?array
    {
        if ($campYears === []) {
            return null;
        }

        $selectedId = Request::intFromGet('camp_year_id');
        if ($selectedId !== null) {
            foreach ($campYears as $campYear) {
                if ((int) $campYear['id'] === $selectedId) {
                    return $campYear;
                }
            }
        }

        foreach ($campYears as $campYear) {
            if ((int) $campYear['is_active'] === 1) {
                return $campYear;
            }
        }

        return $campYears[0];
    }

    private function guardWrite(): bool
    {
        if (!Auth::requirePermission('orders.manage')) {
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
            'camp_year_id' => Request::post('camp_year_id', ''),
            'name' => Request::post('name', ''),
            'short_name' => Request::post('short_name', ''),
            'color_key' => Request::post('color_key', ''),
            'color_hex' => Request::post('color_hex', ''),
            'leader_person_id' => Request::post('leader_person_id', ''),
            'helper_person_id' => Request::post('helper_person_id', ''),
            'sort_order' => Request::post('sort_order', '0'),
            'is_active' => Request::post('is_active', '0') === '1',
        ];
    }
}
