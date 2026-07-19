<?php
/** @var string $mode */
/** @var array|null $activeCampYear */
/** @var array $dayTabs */
/** @var string|null $activeDate */
/** @var array $orders */
/** @var array $participants */
/** @var array $payload */
use App\Support\Csrf;

$mode = $mode ?? 'zelt';
$isZelt = $mode === 'zelt';
$orders = $orders ?? [];
$participants = $participants ?? [];
$payload = $payload ?? [];
$scoringDate = (string) ($payload['scoring_date'] ?? ($activeDate ?? ''));
$selectedOrderId = (string) ($payload['order_id'] ?? '');
$slot = (string) ($payload['check_slot'] ?? 'morgens');
$defaultPersonPoints = $isZelt ? 5 : 5;
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Punkte</p>
        <h2><?= $isZelt ? 'Zelt bewerten' : 'Geschirr bewerten' ?></h2>
        <p class="muted"><?= $isZelt ? 'Bewerte zuerst das Zelt als Gesamtorden und danach die Kinder.' : 'Wähle ein Zelt und erfasse die Geschirr-/Sauberkeitspunkte der Kinder.' ?></p>
    </div>
    <a class="button button--ghost" href="/ordnung">Zurück</a>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card empty-state"><div class="empty-icon">!</div><h2>Kein aktives Lagerjahr</h2><p>Diese Bewertung braucht ein aktives Lagerjahr.</p></section>
<?php else: ?>
    <?php $days = $dayTabs ?? []; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>

    <form method="get" action="/punkte/<?= e($mode) ?>" class="card filter-card compact-filter">
        <input type="hidden" name="tag" value="<?= e($scoringDate) ?>">
        <label>Orden/Zelt
            <select name="order_id" onchange="this.form.submit()">
                <option value="">Bitte auswählen</option>
                <?php foreach ($orders as $order): ?>
                    <option value="<?= e($order['id']) ?>" <?= $selectedOrderId === (string) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button class="button button--ghost" type="submit">Laden</button></noscript>
    </form>

    <?php if ($selectedOrderId === ''): ?>
        <section class="card empty-state"><div class="empty-icon">O</div><h2>Orden/Zelt auswählen</h2><p>Danach werden die Kinder dieses Zeltes angezeigt.</p></section>
    <?php else: ?>
        <form method="post" action="/punkte/<?= e($mode) ?>" class="card form-card point-batch-card">
            <?= Csrf::input() ?>
            <input type="hidden" name="order_id" value="<?= e($selectedOrderId) ?>">
            <div class="form-grid">
                <label>Datum
                    <input type="date" name="scoring_date" value="<?= e($scoringDate) ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
                </label>
                <label>Prüfung
                    <select name="check_slot" required>
                        <option value="morgens" <?= $slot === 'morgens' ? 'selected' : '' ?>>Morgens</option>
                        <option value="abends" <?= $slot === 'abends' ? 'selected' : '' ?>>Abends</option>
                    </select>
                </label>
                <?php if ($isZelt): ?>
                    <label>Zelt gesamt, max. 5
                        <div class="stepper">
                            <button type="button" data-stepper="-1">−</button>
                            <input type="number" name="order_points" min="0" max="5" value="<?= e($payload['order_points'] ?? '5') ?>">
                            <button type="button" data-stepper="1">+</button>
                        </div>
                    </label>
                <?php endif; ?>
                <label class="form-grid__full">Notiz
                    <input type="text" name="reason" value="<?= e($payload['reason'] ?? '') ?>" maxlength="500" placeholder="optional">
                </label>
            </div>

            <?php if ($participants === []): ?>
                <p class="muted">Für diesen Orden sind noch keine Teilnehmer zugeordnet.</p>
            <?php else: ?>
                <div class="point-row-list">
                    <?php foreach ($participants as $person): ?>
                        <?php $current = $payload['person_points'][$person['id']] ?? (string) $defaultPersonPoints; ?>
                        <div class="point-person-row">
                            <strong><?= e($person['display_name']) ?></strong>
                            <span class="muted"><?= e($person['order_short_name'] ?? '') ?></span>
                            <div class="stepper">
                                <button type="button" data-stepper="-1">−</button>
                                <input type="number" name="person_points[<?= e($person['id']) ?>]" min="0" max="5" value="<?= e($current) ?>">
                                <button type="button" data-stepper="1">+</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="form-actions"><button class="button button--primary" type="submit">Bewertungen speichern</button></div>
        </form>
    <?php endif; ?>
<?php endif; ?>
