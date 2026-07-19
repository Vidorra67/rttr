<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;
use App\Support\Auth;
use App\Support\Logger;
use App\Support\Request;
use App\Support\View;
use Throwable;

final class HomeController
{
    public function index(): string
    {
        if (!Auth::requireLogin()) {
            return '';
        }

        $dashboard = [
            'activeCampYear' => null,
            'currentCampDate' => null,
            'dayLabel' => 'Lagerjahr offen',
            'dayTabs' => [],
            'orders' => [],
            'stats' => [
                'participants' => 0,
                'staff' => 0,
                'orders' => 0,
                'open_duties' => 0,
                'order_points_today' => 0,
            ],
            'birthdaysToday' => [],
            'nextProgramItem' => null,
            'todayMeals' => [],
            'openDutiesToday' => [],
        ];

        try {
            $dashboard = (new DashboardService())->data(Request::get('tag'));
        } catch (Throwable $exception) {
            Logger::error('Dashboard data failed', ['message' => $exception->getMessage()]);
        }

        return View::render('home/index', [
            'title' => 'Übersicht',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'dashboard' => $dashboard,
            'topbarDayChip' => $dashboard['dayLabel'] ?? 'Lagerjahr offen',
        ]);
    }
}
