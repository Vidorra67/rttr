<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$bootstrapPath = BASE_PATH . '/app/Support/bootstrap.php';

if (!@is_file($bootstrapPath) || !@is_readable($bootstrapPath)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
    $openBasedir = ini_get('open_basedir') ?: 'nicht gesetzt';
    $docroot = $_SERVER['DOCUMENT_ROOT'] ?? 'unbekannt';
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ritterlager Manager - Hosting-Konfiguration</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#F5F7FF;color:#17204A;padding:24px;line-height:1.5}.box{max-width:860px;margin:0 auto;background:white;border:1px solid #E7EAF6;border-radius:16px;padding:22px}code{background:#F5F7FF;padding:2px 5px;border-radius:6px}.warn{color:#D6452F;font-weight:700}</style></head><body><div class="box">';
    echo '<h1>Hosting-Konfiguration prüfen</h1>';
    echo '<p class="warn">Die Anwendung kann Dateien außerhalb von <code>public/</code> nicht laden.</p>';
    echo '<p>Der Webroot soll auf <code>public/</code> zeigen. PHP muss aber zusätzlich Zugriff auf das Projektverzeichnis darüber haben, damit <code>app/</code>, <code>config/</code>, <code>routes/</code>, <code>database/</code> und <code>storage/</code> geladen werden können.</p>';
    echo '<p><strong>Aktueller DOCUMENT_ROOT:</strong><br><code>' . htmlspecialchars($docroot, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<p><strong>Aktuelles open_basedir:</strong><br><code>' . htmlspecialchars($openBasedir, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<p><strong>Erforderlich:</strong> open_basedir muss mindestens das Projektverzeichnis <code>' . htmlspecialchars(BASE_PATH, ENT_QUOTES, 'UTF-8') . '/</code> enthalten, nicht nur <code>public/</code>.</p>';
    echo '<p>In Plesk/Netcup: Domain → PHP-Einstellungen → <code>open_basedir</code> von <code>{DOCROOT}{/}{:}{TMP}{/}</code> auf <code>{WEBSPACEROOT}{/}{:}{TMP}{/}</code> ändern oder den absoluten Pfad zum Projektverzeichnis eintragen.</p>';
    echo '</div></body></html>';
    exit;
}

require $bootstrapPath;

use App\Support\Logger;
use App\Support\Response;
use App\Support\SecurityHeaders;
use App\Support\View;

$requestId = date('YmdHis') . '-' . bin2hex(random_bytes(4));

try {
    SecurityHeaders::apply();

    $router = require BASE_PATH . '/routes/web.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    $router->dispatch($method, $path);
} catch (Throwable $exception) {
    Logger::exception($exception, [
        'request_id' => $requestId,
        'route' => $_SERVER['REQUEST_URI'] ?? '/',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    ]);

    Response::html(View::render('errors/500', ['requestId' => $requestId]), 500);
}
