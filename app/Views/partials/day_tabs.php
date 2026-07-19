<?php
/** @var array $days */
/** @var string|null $activeDay */
$days = $days ?? [];
$activeDay = $activeDay ?? null;
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '/';
parse_str((string) (parse_url($currentUri, PHP_URL_QUERY) ?: ''), $currentQuery);
?>
<?php if ($days !== []): ?>
    <div class="day-tabs" role="tablist" aria-label="Lagertage">
        <?php foreach ($days as $day): ?>
            <?php
            $key = (string) ($day['key'] ?? $day['date'] ?? '');
            $isActive = isset($day['is_active']) ? (bool) $day['is_active'] : $key === (string) $activeDay;
            $href = (string) ($day['href'] ?? '');
            if ($href === '' || $href === '#') {
                $query = $currentQuery;
                if ($key !== '') {
                    $query['tag'] = $key;
                }
                $href = $currentPath . ($query !== [] ? '?' . http_build_query($query) : '');
            }
            $label = (string) ($day['label'] ?? 'Tag');
            $sub = (string) ($day['sub'] ?? ('Tag ' . ($day['day_number'] ?? '') . ' · ' . ($day['short_date'] ?? '')));
            ?>
            <a class="day-tab <?= $isActive ? 'is-active' : '' ?>" href="<?= e($href) ?>" role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                <span><?= e($label) ?></span>
                <?php if (trim($sub) !== ''): ?><small><?= e($sub) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
