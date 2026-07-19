<?php
/** @var array|null $activeCampYear */
/** @var array $dayTabs */
/** @var string|null $activeDate */
/** @var array $orders */
/** @var array $payload */
use App\Support\Csrf;

$orders = $orders ?? [];
$payload = $payload ?? [];
$scoringDate = (string) ($payload['scoring_date'] ?? ($activeDate ?? ''));
$placements = $payload['placements'] ?? [];
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Punkte</p>
        <h2>Spielwertung erfassen</h2>
        <p class="muted">Trage ein Spiel ein und weise je Platzierung einen oder mehrere Orden zu.</p>
    </div>
    <a class="button button--ghost" href="/ordnung">Zurück</a>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card empty-state"><div class="empty-icon">!</div><h2>Kein aktives Lagerjahr</h2><p>Spielwertungen brauchen ein aktives Lagerjahr.</p></section>
<?php else: ?>
    <?php $days = $dayTabs ?? []; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>
    <form method="post" action="/punkte/spiel" class="card form-card point-batch-card">
        <?= Csrf::input() ?>
        <div class="form-grid">
            <label>Datum
                <input type="date" name="scoring_date" value="<?= e($scoringDate) ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
            </label>
            <label>Spielname
                <input type="text" name="game_title" value="<?= e($payload['game_title'] ?? '') ?>" maxlength="190" required placeholder="z. B. Spiel A">
            </label>
        </div>

        <div class="placement-list">
            <?php for ($place = 1; $place <= 6; $place++): ?>
                <?php
                $row = is_array($placements[$place] ?? null) ? $placements[$place] : [];
                $points = (string) ($row['points'] ?? max(0, 6 - $place));
                $selected = array_map('strval', is_array($row['order_ids'] ?? null) ? $row['order_ids'] : []);
                ?>
                <article class="placement-row">
                    <div class="placement-rank"><?= e($place) ?>. Platz</div>
                    <label>Punkte
                        <div class="stepper">
                            <button type="button" data-stepper="-1">−</button>
                            <input type="number" name="placements[<?= e($place) ?>][points]" min="0" max="100" value="<?= e($points) ?>">
                            <button type="button" data-stepper="1">+</button>
                        </div>
                    </label>
                    <div class="placement-orders">
                        <?php foreach ($orders as $order): ?>
                            <label class="role-option check-label">
                                <input type="checkbox" name="placements[<?= e($place) ?>][order_ids][]" value="<?= e($order['id']) ?>" <?= in_array((string) $order['id'], $selected, true) ? 'checked' : '' ?>>
                                <span><?= e($order['short_name']) ?> <small><?= e($order['name']) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endfor; ?>
        </div>
        <div class="form-actions"><button class="button button--primary" type="submit">Spielwertung speichern</button></div>
    </form>
<?php endif; ?>
