<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CampYearController;
use App\Controllers\DutyController;
use App\Controllers\HomeController;
use App\Controllers\ImportController;
use App\Controllers\MealController;
use App\Controllers\OrderController;
use App\Controllers\PointController;
use App\Controllers\ProgramController;
use App\Controllers\PersonController;
use App\Controllers\ScoreController;
use App\Controllers\SystemController;
use App\Support\Router;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/programm', [ProgramController::class, 'index']);
$router->get('/programm/neu', [ProgramController::class, 'create']);
$router->post('/programm', [ProgramController::class, 'store']);
$router->get('/programm/bearbeiten', [ProgramController::class, 'edit']);
$router->post('/programm/speichern', [ProgramController::class, 'update']);
$router->post('/programm/deaktivieren', [ProgramController::class, 'deactivate']);


$router->get('/dienste', [DutyController::class, 'index']);
$router->get('/dienste/neu', [DutyController::class, 'create']);
$router->post('/dienste', [DutyController::class, 'store']);
$router->get('/dienste/bearbeiten', [DutyController::class, 'edit']);
$router->post('/dienste/speichern', [DutyController::class, 'update']);
$router->post('/dienste/status', [DutyController::class, 'status']);
$router->post('/dienste/deaktivieren', [DutyController::class, 'deactivate']);

$router->get('/admin/dienstarten', [DutyController::class, 'types']);
$router->get('/admin/dienstarten/neu', [DutyController::class, 'createType']);
$router->post('/admin/dienstarten', [DutyController::class, 'storeType']);
$router->get('/admin/dienstarten/bearbeiten', [DutyController::class, 'editType']);
$router->post('/admin/dienstarten/speichern', [DutyController::class, 'updateType']);


$router->get('/ordnung', [PointController::class, 'create']);
$router->post('/ordnung/abziehen', [PointController::class, 'store']);
$router->get('/admin/ordnungspunkte', [PointController::class, 'index']);
$router->get('/admin/ordnungspunkte/korrektur', [PointController::class, 'correction']);
$router->post('/admin/ordnungspunkte/korrektur', [PointController::class, 'storeCorrection']);
$router->post('/admin/ordnungspunkte/stornieren', [PointController::class, 'void']);
$router->get('/punkte/spiel', [PointController::class, 'competition']);
$router->post('/punkte/spiel', [PointController::class, 'storeCompetition']);
$router->get('/punkte/zelt', [PointController::class, 'batchZelt']);
$router->post('/punkte/zelt', [PointController::class, 'storeBatchZelt']);
$router->get('/punkte/geschirr', [PointController::class, 'batchGeschirr']);
$router->post('/punkte/geschirr', [PointController::class, 'storeBatchGeschirr']);
$router->get('/punkte/dienst', [PointController::class, 'dutyBonus']);
$router->post('/punkte/dienst', [PointController::class, 'storeDutyBonus']);

$router->get('/essen', [MealController::class, 'index']);
$router->get('/essen/neu', [MealController::class, 'create']);
$router->post('/essen', [MealController::class, 'store']);
$router->get('/essen/bearbeiten', [MealController::class, 'edit']);
$router->post('/essen/speichern', [MealController::class, 'update']);
$router->post('/essen/deaktivieren', [MealController::class, 'deactivate']);


$router->get('/admin/auswertung', [ScoreController::class, 'index']);
$router->get('/admin/auswertung/export', [ScoreController::class, 'export']);
$router->get('/admin/rangstufen', [ScoreController::class, 'rankLevels']);
$router->get('/admin/rangstufen/neu', [ScoreController::class, 'createRankLevel']);
$router->post('/admin/rangstufen', [ScoreController::class, 'storeRankLevel']);
$router->get('/admin/rangstufen/bearbeiten', [ScoreController::class, 'editRankLevel']);
$router->post('/admin/rangstufen/speichern', [ScoreController::class, 'updateRankLevel']);
$router->get('/admin/lerneinheiten', [ScoreController::class, 'learningUnits']);
$router->get('/admin/lerneinheiten/neu', [ScoreController::class, 'createLearningUnit']);
$router->post('/admin/lerneinheiten', [ScoreController::class, 'storeLearningUnit']);
$router->get('/admin/lerneinheiten/bearbeiten', [ScoreController::class, 'editLearningUnit']);
$router->post('/admin/lerneinheiten/speichern', [ScoreController::class, 'updateLearningUnit']);
$router->post('/admin/lerneinheiten/deaktivieren', [ScoreController::class, 'deactivateLearningUnit']);
$router->get('/admin/pruefungen', [ScoreController::class, 'exams']);
$router->post('/admin/pruefungen', [ScoreController::class, 'saveExam']);
$router->post('/admin/pruefungen/rang', [ScoreController::class, 'saveRankAssignment']);
$router->post('/admin/pruefungen/aufstieg', [ScoreController::class, 'savePromotion']);


$router->get('/admin/importe', [ImportController::class, 'index']);
$router->post('/admin/importe/vorschau', [ImportController::class, 'preview']);
$router->post('/admin/importe/vorlage', [ImportController::class, 'executeTemplate']);
$router->get('/admin/importe/vorschau', [ImportController::class, 'show']);
$router->post('/admin/importe/ausfuehren', [ImportController::class, 'execute']);

$router->get('/admin/personen', [PersonController::class, 'index']);
$router->get('/admin/personen/detail', [PersonController::class, 'show']);
$router->get('/admin/personen/neu', [PersonController::class, 'create']);
$router->post('/admin/personen', [PersonController::class, 'store']);
$router->get('/admin/personen/bearbeiten', [PersonController::class, 'edit']);
$router->post('/admin/personen/speichern', [PersonController::class, 'update']);
$router->post('/admin/personen/login', [PersonController::class, 'toggleLogin']);
$router->post('/admin/personen/pin', [PersonController::class, 'resetPin']);

$router->get('/admin/lagerjahre', [CampYearController::class, 'index']);
$router->get('/admin/lagerjahre/neu', [CampYearController::class, 'create']);
$router->post('/admin/lagerjahre', [CampYearController::class, 'store']);
$router->get('/admin/lagerjahre/bearbeiten', [CampYearController::class, 'edit']);
$router->post('/admin/lagerjahre/speichern', [CampYearController::class, 'update']);
$router->post('/admin/lagerjahre/aktiv', [CampYearController::class, 'activate']);

$router->get('/admin/orden', [OrderController::class, 'index']);
$router->get('/admin/orden/neu', [OrderController::class, 'create']);
$router->post('/admin/orden', [OrderController::class, 'store']);
$router->get('/admin/orden/bearbeiten', [OrderController::class, 'edit']);
$router->post('/admin/orden/speichern', [OrderController::class, 'update']);

$router->get('/system/status', [SystemController::class, 'status']);
$router->get('/system/backups', [SystemController::class, 'backups']);
$router->post('/system/backups/start', [SystemController::class, 'startBackup']);
$router->get('/system/backups/download', [SystemController::class, 'downloadBackup']);
$router->get('/system/tasks', [SystemController::class, 'tasks']);
$router->post('/system/tasks/run', [SystemController::class, 'runTask']);
$router->post('/system/tasks/token', [SystemController::class, 'regenerateTaskToken']);
$router->post('/system/tasks/toggle', [SystemController::class, 'toggleTask']);
$router->get('/system/webdav', [SystemController::class, 'webdav']);
$router->post('/system/webdav/save', [SystemController::class, 'saveWebdav']);
$router->post('/system/webdav/test', [SystemController::class, 'testWebdav']);
$router->post('/system/webdav/sync-latest', [SystemController::class, 'syncLatestWebdav']);
$router->get('/system/logs', [SystemController::class, 'logs']);
$router->get('/cron/run', [SystemController::class, 'cronRun']);
$router->get('/health', [SystemController::class, 'health']);

return $router;
