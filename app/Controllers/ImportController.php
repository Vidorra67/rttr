<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BundledImportService;
use App\Services\ImportService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class ImportController
{
    private ImportService $importService;
    private BundledImportService $bundledImportService;

    public function __construct()
    {
        $this->importService = new ImportService();
        $this->bundledImportService = new BundledImportService();
    }

    public function index(): string
    {
        if (!Auth::requirePermission('imports.manage')) {
            return '';
        }

        try {
            $activeCampYear = $this->importService->activeCampYear();
            return View::render('imports/index', [
                'title' => 'Importe',
                'activeCampYear' => $activeCampYear,
                'topbarDayChip' => $this->importService->dayLabel($activeCampYear),
                'profiles' => $this->importService->profiles(),
                'runs' => $this->importService->runs(),
                'templates' => $this->bundledImportService->templates(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Import index failed', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Importe konnten nicht geladen werden.');
            return View::render('imports/index', [
                'title' => 'Importe',
                'activeCampYear' => null,
                'topbarDayChip' => 'Lagerjahr offen',
                'profiles' => $this->importService->profiles(),
                'runs' => [],
                'templates' => $this->bundledImportService->templates(),
            ]);
        }
    }

    public function preview(): string
    {
        if (!Auth::requirePermission('imports.manage')) {
            return '';
        }
        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        try {
            $runId = $this->importService->createPreview($_FILES['import_file'] ?? [], (string) Request::post('import_key', ''));
            Flash::add('success', 'Importdatei wurde gespeichert und vorbereitet.');
            Response::redirect('/admin/importe/vorschau?id=' . $runId);
            return '';
        } catch (Throwable $exception) {
            Logger::error('Import preview failed', ['message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            Response::redirect('/admin/importe');
            return '';
        }
    }

    public function show(): string
    {
        if (!Auth::requirePermission('imports.manage')) {
            return '';
        }

        $id = Request::intFromGet('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/importe/vorschau']), 404);
            return '';
        }

        $run = $this->importService->findRun($id);
        if ($run === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/importe/vorschau']), 404);
            return '';
        }

        $activeCampYear = $this->importService->activeCampYear();
        return View::render('imports/show', [
            'title' => 'Importvorschau',
            'activeCampYear' => $activeCampYear,
            'topbarDayChip' => $this->importService->dayLabel($activeCampYear),
            'profiles' => $this->importService->profiles(),
            'run' => $run,
        ]);
    }

    public function executeTemplate(): string
    {
        if (!Auth::requirePermission('imports.manage')) {
            return '';
        }
        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        $templateKey = (string) Request::post('template_key', '');
        try {
            $result = $this->bundledImportService->execute($templateKey);
            Flash::add('success', (string) ($result['message'] ?? 'Importvorlage wurde ausgeführt.') . ' Übernommen: ' . (int) ($result['imported_rows'] ?? 0) . ', übersprungen: ' . (int) ($result['skipped_rows'] ?? 0) . '.');
            if (!empty($result['run_id'])) {
                Response::redirect('/admin/importe/vorschau?id=' . (int) $result['run_id']);
                return '';
            }
        } catch (Throwable $exception) {
            Logger::error('Bundled import failed', ['template_key' => $templateKey, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
        }

        Response::redirect('/admin/importe');
        return '';
    }

    public function execute(): string
    {
        if (!Auth::requirePermission('imports.manage')) {
            return '';
        }
        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        $id = Request::intFromPost('id');
        if ($id === null) {
            Response::html(View::render('errors/404', ['path' => '/admin/importe/ausfuehren']), 404);
            return '';
        }

        try {
            $result = $this->importService->execute($id);
            Flash::add('success', 'Import wurde ausgeführt. Importiert: ' . (int) ($result['imported_rows'] ?? 0) . ', übersprungen: ' . (int) ($result['skipped_rows'] ?? 0) . '.');
            Response::redirect('/admin/importe/vorschau?id=' . $id);
            return '';
        } catch (Throwable $exception) {
            Logger::error('Import execute failed', ['import_run_id' => $id, 'message' => $exception->getMessage()]);
            Flash::add('error', $exception->getMessage());
            Response::redirect('/admin/importe/vorschau?id=' . $id);
            return '';
        }
    }
}
