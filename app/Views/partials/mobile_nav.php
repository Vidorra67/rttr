<?php
use App\Support\Auth;

$mobileItems = [
    ['key' => 'dashboard', 'label' => 'Übersicht', 'href' => '/', 'permission' => 'dashboard.view', 'enabled' => true, 'icon' => 'dashboard'],
    ['key' => 'program', 'label' => 'Programm', 'href' => '/programm', 'permission' => 'program.view', 'enabled' => true, 'icon' => 'event'],
    ['key' => 'meals', 'label' => 'Essen', 'href' => '/essen', 'permission' => 'meals.view', 'enabled' => true, 'icon' => 'restaurant'],
    ['key' => 'duties', 'label' => 'Dienste', 'href' => '/dienste', 'permission' => 'duties.view', 'enabled' => true, 'icon' => 'assignment'],
    ['key' => 'points', 'label' => 'Ordnung', 'href' => '/ordnung', 'permission' => 'points.order.create', 'enabled' => true, 'icon' => 'rule'],
];
?>
<nav class="mobile-tabs" aria-label="Mobile Navigation">
    <?php foreach ($mobileItems as $item): ?>
        <?php if (Auth::can($item['permission'])): ?>
            <a href="<?= e($item['href']) ?>"
               class="mobile-tab <?= $activeNav === $item['key'] ? 'is-active' : '' ?> <?= $item['enabled'] ? '' : 'mobile-tab--disabled' ?>"
               <?= $item['enabled'] ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                <span class="material-symbols-rounded" aria-hidden="true"><?= e($item['icon']) ?></span>
                <strong><?= e($item['label']) ?></strong>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
