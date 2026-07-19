<?php
/** @var array|null $activeCampYear */
/** @var array $dayTabs */
/** @var string|null $activeDate */
/** @var array $duties */
/** @var array $orders */
/** @var array $payload */
use App\Support\Csrf;

$duties = $duties ?? [];
$orders = $orders ?? [];
$payload = $payload ?? [];
$scoringDate = (string) ($payload['scoring_date'] ?? ($activeDate ?? ''));
$selectedDutyId = (string) ($payload['duty_id'] ?? '');
$selectedOrderId = (string) ($payload['order_id'] ?? '');
$slot = (string) ($payload['check_slot'] ?? 'einsatz_1');
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Punkte</p>
        <h2>Küchendienst bewerten</h2>
        <p class="muted">Küchendienst wird als globale Ordens-/Zeltwertung erfasst. Pro Einsatz sind bis zu 3 Punkte möglich.</p>
    </div>
    <a class="button button--ghost" href="/ordnung">Zurück</a>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card empty-state"><div class="empty-icon">!</div><h2>Kein aktives Lagerjahr</h2><p>Küchendienstpunkte brauchen ein aktives Lagerjahr.</p></section>
<?php else: ?>
    <?php $days = $dayTabs ?? []; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>
    <form method="post" action="/punkte/dienst" class="card form-card point-batch-card">
        <?= Csrf::input() ?>
        <div class="form-grid">
            <label>Datum
                <input type="date" name="scoring_date" value="<?= e($scoringDate) ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
            </label>
            <label>Dienst
                <select name="duty_id" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($duties as $duty): ?>
                        <option value="<?= e($duty['id']) ?>" <?= $selectedDutyId === (string) $duty['id'] ? 'selected' : '' ?>><?= e($duty['title'] ?: $duty['duty_type_label']) ?><?= !empty($duty['time_label']) ? ' · ' . e($duty['time_label']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Orden/Zelt
                <select name="order_id" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= e($order['id']) ?>" <?= $selectedOrderId === (string) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Einsatz
                <select name="check_slot">
                    <option value="einsatz_1" <?= $slot === 'einsatz_1' ? 'selected' : '' ?>>Einsatz 1</option>
                    <option value="einsatz_2" <?= $slot === 'einsatz_2' ? 'selected' : '' ?>>Einsatz 2</option>
                    <option value="einsatz_3" <?= $slot === 'einsatz_3' ? 'selected' : '' ?>>Einsatz 3</option>
                </select>
            </label>
            <label>Küchendienstpunkte, max. 3
                <div class="stepper">
                    <button type="button" data-stepper="-1">−</button>
                    <input type="number" name="points" min="0" max="3" value="<?= e($payload['points'] ?? '3') ?>">
                    <button type="button" data-stepper="1">+</button>
                </div>
            </label>
        </div>
        <div class="point-rule-box">
            <strong>Regel</strong>
            <p>Küchendienst zählt global für den Orden/Zelt. Die einzelnen Kinder erhalten hier keine persönlichen Küchendienstpunkte.</p>
        </div>
        <div class="form-actions"><button class="button button--primary" type="submit">Küchendienstpunkte speichern</button></div>
    </form>
<?php endif; ?>
