<?php
/** @var array|null $activeCampYear */
/** @var string|null $activeDate */
/** @var array $dayTabs */
/** @var array $items */
use App\Support\Auth;

$activeCampYear = $activeCampYear ?? null;
$activeDate = $activeDate ?? null;
$items = $items ?? [];
$dayTabs = $dayTabs ?? [];
?>

<?php if ($activeCampYear === null): ?>
    <section class="hero-card">
        <p class="eyebrow">Programm</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Lege zuerst ein aktives Lagerjahr an. Danach kann der Tagesablauf als Timeline gepflegt werden.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--hero" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="section-head program-head">
        <div>
            <p class="eyebrow">Tagesablauf</p>
            <h2><?= e($activeCampYear['name']) ?></h2>
            <p class="muted">Programmpunkte werden nach Uhrzeit sortiert. Mahlzeiten werden in der Timeline mint markiert.</p>
        </div>
        <?php if (Auth::can('program.manage')): ?>
            <a class="button button--primary" href="/programm/neu?tag=<?= e((string) $activeDate) ?>">Programmpunkt hinzufügen</a>
        <?php endif; ?>
    </section>

    <?php $days = $dayTabs; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>

    <?php if ($items === []): ?>
        <article class="card empty-state">
            <div class="empty-icon" aria-hidden="true">□</div>
            <h2>Noch kein Programm</h2>
            <p class="muted">Für diesen Lagertag ist noch kein Programmpunkt eingetragen.</p>
            <?php if (Auth::can('program.manage')): ?>
                <a class="button button--primary" href="/programm/neu?tag=<?= e((string) $activeDate) ?>">Ersten Programmpunkt hinzufügen</a>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <section class="timeline program-timeline" aria-label="Programm des Lagertags">
            <?php foreach ($items as $item): ?>
                <?php
                $categoryKey = (string) ($item['category_key'] ?? 'info');
                $categoryTag = (string) ($item['category_tag'] ?? 'info');
                $startsAt = $item['starts_at'] ? substr((string) $item['starts_at'], 0, 5) : '–';
                $endsAt = $item['ends_at'] ? substr((string) $item['ends_at'], 0, 5) : '';
                $timeLabel = $startsAt . ($endsAt !== '' ? ' – ' . $endsAt : '');
                ?>
                <article class="timeline-item <?= $categoryKey === 'mahlzeit' ? 'timeline-item--meal' : '' ?> <?= (int) ($item['is_recurring'] ?? 0) === 1 ? 'timeline-item--recurring' : '' ?>">
                    <div class="timeline-time"><?= e($timeLabel) ?></div>
                    <div class="timeline-content program-item-card">
                        <div class="program-item-head">
                            <div>
                                <h3><?= e($item['title']) ?></h3>
                                <div class="chip-row">
                                    <span class="category-tag category-tag--<?= e($categoryTag) ?>"><?= e($item['category_label']) ?></span>
                                    <?php if (!empty($item['order_names'])): ?>
                                        <span class="role-chip"><?= e($item['order_names']) ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($item['is_recurring'] ?? 0) === 1): ?>
                                        <span class="status-chip status-chip--recurring"><?= e($item['recurring_label'] ?: 'wiederkehrend') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (Auth::can('program.manage')): ?>
                                <div class="management-actions">
                                    <a class="button button--ghost" href="/programm/bearbeiten?id=<?= e($item['id']) ?>">Bearbeiten</a>
                                    <form method="post" action="/programm/deaktivieren" class="inline-form">
                                        <?= \App\Support\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                        <input type="hidden" name="program_date" value="<?= e($item['program_date']) ?>">
                                        <button type="submit" class="button button--ghost button--danger">Entfernen</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item['location']) || !empty($item['responsible_label'])): ?>
                            <p class="program-meta">
                                <?php if (!empty($item['location'])): ?>Ort: <?= e($item['location']) ?><?php endif; ?>
                                <?php if (!empty($item['location']) && !empty($item['responsible_label'])): ?> · <?php endif; ?>
                                <?php if (!empty($item['responsible_label'])): ?>Verantwortlich: <?= e($item['responsible_label']) ?><?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($item['description'])): ?>
                            <p><?= nl2br(e($item['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
