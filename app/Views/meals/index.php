<?php
/** @var array|null $activeCampYear */
/** @var string|null $activeDate */
/** @var array $dayTabs */
/** @var array $mealGroups */
use App\Support\Auth;

$activeCampYear = $activeCampYear ?? null;
$activeDate = $activeDate ?? null;
$dayTabs = $dayTabs ?? [];
$mealGroups = $mealGroups ?? [];
?>

<?php if ($activeCampYear === null): ?>
    <section class="hero-card">
        <p class="eyebrow">Essen</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Lege zuerst ein aktives Lagerjahr an. Danach kann der Speiseplan je Lagertag gepflegt werden.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--hero" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="section-head meal-head">
        <div>
            <p class="eyebrow">Speiseplan &amp; Portionen</p>
            <h2><?= e($activeCampYear['name']) ?></h2>
            <p class="muted">Frühstück, Mittagessen und Abendessen werden je Tag als Karten angezeigt. Einkaufsliste und ODS-Import folgen später.</p>
        </div>
        <div class="management-actions">
            <button type="button" class="button button--ghost" disabled aria-disabled="true">Einkaufsliste</button>
            <?php if (Auth::can('meals.manage')): ?>
                <a class="button button--primary" href="/essen/neu?tag=<?= e((string) $activeDate) ?>">Mahlzeit hinzufügen</a>
            <?php endif; ?>
        </div>
    </section>

    <?php $days = $dayTabs; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>

    <?php if ($mealGroups === []): ?>
        <article class="card empty-state">
            <div class="empty-icon" aria-hidden="true">◦</div>
            <h2>Noch kein Speiseplan</h2>
            <p class="muted">Für diesen Lagertag ist noch keine Mahlzeit eingetragen.</p>
            <?php if (Auth::can('meals.manage')): ?>
                <a class="button button--primary" href="/essen/neu?tag=<?= e((string) $activeDate) ?>">Erste Mahlzeit hinzufügen</a>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <section class="meal-grid" aria-label="Speiseplan des Lagertags">
            <?php foreach ($mealGroups as $group): ?>
                <?php
                $item = $group['item'] ?? null;
                $mealType = (string) ($group['key'] ?? '');
                $timeLabel = $item !== null && !empty($item['meal_time']) ? substr((string) $item['meal_time'], 0, 5) . ' Uhr' : (string) ($group['default_time'] ?? '');
                ?>
                <article class="card meal-card <?= $item === null ? 'meal-card--empty' : '' ?>">
                    <div class="meal-card__top">
                        <div>
                            <p class="eyebrow"><?= e($timeLabel !== '' ? $timeLabel : 'Zeit offen') ?></p>
                            <h3><?= e($group['label'] ?? 'Mahlzeit') ?></h3>
                        </div>
                        <span class="category-tag category-tag--mahlzeit">Mahlzeit</span>
                    </div>

                    <?php if ($item === null): ?>
                        <p class="muted">Noch nicht geplant.</p>
                        <?php if (Auth::can('meals.manage')): ?>
                            <a class="button button--ghost" href="/essen/neu?tag=<?= e((string) $activeDate) ?>&amp;typ=<?= e($mealType) ?>">Eintragen</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <h2><?= e($item['title']) ?></h2>
                        <div class="meal-chips">
                            <span class="role-chip"><?= e((int) ($item['portions_total'] ?? 0)) ?> Portionen</span>
                            <?php if ((int) ($item['portions_vegetarian'] ?? 0) > 0): ?>
                                <span class="status-chip status-chip--ok"><?= e((int) $item['portions_vegetarian']) ?> vegetarisch</span>
                            <?php endif; ?>
                            <?php if (!empty($item['allergy_notes'])): ?>
                                <span class="status-chip status-chip--offen">Allergien</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item['description'])): ?>
                            <p><?= nl2br(e($item['description'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($item['allergy_notes'])): ?>
                            <div class="meal-note meal-note--warning">
                                <strong>Allergiehinweise</strong>
                                <p><?= nl2br(e($item['allergy_notes'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['kitchen_team_label'])): ?>
                            <p class="meal-kitchen">Küche: <?= e($item['kitchen_team_label']) ?></p>
                        <?php endif; ?>

                        <?php if (Auth::can('meals.manage')): ?>
                            <div class="management-actions meal-actions">
                                <a class="button button--ghost" href="/essen/bearbeiten?id=<?= e($item['id']) ?>">Bearbeiten</a>
                                <form method="post" action="/essen/deaktivieren" class="inline-form">
                                    <?= \App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <input type="hidden" name="meal_date" value="<?= e($item['meal_date']) ?>">
                                    <button type="submit" class="button button--ghost button--danger">Entfernen</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
