<?php
/** @var string $content */
use App\Support\Auth;

$title = $title ?? 'Ritterlager Manager';
$user = Auth::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$activeNav = $activeNav ?? match (true) {
    $currentPath === '/' => 'dashboard',
    str_starts_with($currentPath, '/programm') => 'program',
    str_starts_with($currentPath, '/essen') => 'meals',
    str_starts_with($currentPath, '/dienste') => 'duties',
    str_starts_with($currentPath, '/ordnung') || str_starts_with($currentPath, '/admin/ordnungspunkte') => 'points',
    str_starts_with($currentPath, '/admin/auswertung') || str_starts_with($currentPath, '/admin/rangstufen') || str_starts_with($currentPath, '/admin/lerneinheiten') || str_starts_with($currentPath, '/admin/pruefungen') => 'scores',
    str_starts_with($currentPath, '/admin/importe') => 'imports',
    str_starts_with($currentPath, '/admin/personen') => 'persons',
    str_starts_with($currentPath, '/admin/lagerjahre') => 'camp_years',
    str_starts_with($currentPath, '/admin/orden') => 'orders',
    str_starts_with($currentPath, '/system') => 'system',
    default => 'dashboard',
};
$topbarDayChip = $topbarDayChip ?? 'Lagerjahr offen';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2B49E0">
    <meta name="color-scheme" content="light">
    <title><?= e($title) ?> · Ritterlager Manager</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <div class="app-shell">
        <?php if ($user !== null): ?>
            <?php require base_path('app/Views/partials/sidebar.php'); ?>
        <?php endif; ?>

        <main class="main-content" id="main-content">
            <?php if ($user !== null): ?>
                <?php require base_path('app/Views/partials/topbar.php'); ?>
            <?php endif; ?>

            <?php require base_path('app/Views/partials/flash.php'); ?>
            <?= $content ?>
        </main>
    </div>

    <?php if ($user !== null): ?>
        <?php require base_path('app/Views/partials/mobile_nav.php'); ?>
    <?php endif; ?>
</body>
</html>
