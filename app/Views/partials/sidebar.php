<?php
use App\Support\Auth;

$navItems = [
    ['key' => 'dashboard', 'label' => 'Übersicht', 'href' => '/', 'permission' => 'dashboard.view', 'enabled' => true, 'icon' => 'dashboard'],
    ['key' => 'program', 'label' => 'Programm', 'href' => '/programm', 'permission' => 'program.view', 'enabled' => true, 'icon' => 'event'],
    ['key' => 'meals', 'label' => 'Essen', 'href' => '/essen', 'permission' => 'meals.view', 'enabled' => true, 'icon' => 'restaurant'],
    ['key' => 'duties', 'label' => 'Dienste', 'href' => '/dienste', 'permission' => 'duties.view', 'enabled' => true, 'icon' => 'assignment'],
    ['key' => 'points', 'label' => 'Ordnung', 'href' => '/ordnung', 'permission' => 'points.order.create', 'enabled' => true, 'icon' => 'rule'],
];
$adminItems = [
    ['key' => 'persons', 'label' => 'Personen', 'href' => '/admin/personen', 'permission' => 'persons.view', 'enabled' => true, 'icon' => 'groups'],
    ['key' => 'camp_years', 'label' => 'Lagerjahre', 'href' => '/admin/lagerjahre', 'permission' => 'camp_years.view', 'enabled' => true, 'icon' => 'event_available'],
    ['key' => 'orders', 'label' => 'Orden/Zelte', 'href' => '/admin/orden', 'permission' => 'orders.view', 'enabled' => true, 'icon' => 'shield'],
    ['key' => 'scores', 'label' => 'Auswertung', 'href' => '/admin/auswertung', 'permission' => 'exams.view', 'enabled' => true, 'icon' => 'leaderboard'],
    ['key' => 'imports', 'label' => 'Importe', 'href' => '/admin/importe', 'permission' => 'imports.manage', 'enabled' => true, 'icon' => 'upload_file'],
    ['key' => 'system', 'label' => 'Systemstatus', 'href' => '/system/status', 'permission' => 'settings.manage', 'enabled' => true, 'icon' => 'monitoring'],
    ['key' => 'system', 'label' => 'Backups', 'href' => '/system/backups', 'permission' => 'backups.manage', 'enabled' => true, 'icon' => 'database'],
    ['key' => 'system', 'label' => 'WebDAV', 'href' => '/system/webdav', 'permission' => 'webdav.manage', 'enabled' => true, 'icon' => 'cloud_upload'],
    ['key' => 'system', 'label' => 'Aufgaben', 'href' => '/system/tasks', 'permission' => 'cron.manage', 'enabled' => true, 'icon' => 'schedule'],
    ['key' => 'system', 'label' => 'Logs', 'href' => '/system/logs', 'permission' => 'logs.view', 'enabled' => true, 'icon' => 'article'],
];
?>
<aside class="sidebar" aria-label="Hauptnavigation">
    <a class="skip-link" href="#main-content">Zum Inhalt springen</a>
    <div class="brand">
        <?php require base_path('app/Views/partials/badge.php'); ?>
        <div>
            <span>Ritterlager</span>
            <strong>Verwaltung</strong>
        </div>
    </div>

    <nav class="nav-list" aria-label="Lagerbereiche">
        <?php foreach ($navItems as $item): ?>
            <?php if (Auth::can($item['permission'])): ?>
                <a href="<?= e($item['href']) ?>"
                   class="nav-item <?= $activeNav === $item['key'] ? 'is-active' : '' ?> <?= $item['enabled'] ? '' : 'nav-item--disabled' ?>"
                   <?= $item['enabled'] ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                    <span class="nav-icon material-symbols-rounded" aria-hidden="true"><?= e($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="nav-separator" aria-hidden="true"></div>

    <nav class="nav-list" aria-label="Verwaltung">
        <?php foreach ($adminItems as $item): ?>
            <?php if (Auth::can($item['permission'])): ?>
                <a href="<?= e($item['href']) ?>"
                   class="nav-item <?= $activeNav === $item['key'] ? 'is-active' : '' ?> <?= $item['enabled'] ? '' : 'nav-item--disabled' ?>"
                   <?= $item['enabled'] ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                    <span class="nav-icon material-symbols-rounded" aria-hidden="true"><?= e($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="/logout" class="logout-form">
        <?= \App\Support\Csrf::input() ?>
        <button type="submit" class="button button--ghost button--full">Abmelden</button>
    </form>
</aside>
