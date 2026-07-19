<?php
/** @var array|null $activeCampYear */
/** @var array $participants */
/** @var array $orders */
/** @var array $categories */
/** @var array $payload */
use App\Support\Csrf;

$participants = $participants ?? [];
$orders = $orders ?? [];
$categories = $categories ?? [];
$payload = $payload ?? [];
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Ordnung</p>
        <h2>Punktekorrektur</h2>
        <p class="muted">Korrekturen bleiben als eigener Eintrag nachvollziehbar.</p>
    </div>
    <a class="button button--ghost" href="/admin/ordnungspunkte">Zur Liste</a>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card empty-state">
        <div class="empty-icon">!</div>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Für Korrekturen muss ein Lagerjahr aktiv sein.</p>
    </section>
<?php else: ?>
    <section class="card form-card">
        <form method="post" action="/admin/ordnungspunkte/korrektur" class="form-grid">
            <?= Csrf::input() ?>
            <label>
                <span>Bewertungsart</span>
                <select name="category_id" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= (string) ($payload['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Datum</span>
                <input type="date" name="scoring_date" value="<?= e($payload['scoring_date'] ?? '') ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>">
            </label>
            <label>
                <span>Prüfung</span>
                <input type="text" name="check_slot" maxlength="40" value="<?= e($payload['check_slot'] ?? '') ?>" placeholder="morgens, abends, fach_1 ...">
            </label>
            <label>
                <span>Teilnehmer</span>
                <select name="person_id">
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($participants as $participant): ?>
                        <option value="<?= e($participant['id']) ?>" <?= (string) ($payload['person_id'] ?? '') === (string) $participant['id'] ? 'selected' : '' ?>>
                            <?= e($participant['display_name']) ?><?= !empty($participant['order_short_name']) ? ' · ' . e($participant['order_short_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Orden/Zelt</span>
                <select name="order_id">
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= e($order['id']) ?>" <?= (string) ($payload['order_id'] ?? '') === (string) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Korrekturpunkte</span>
                <input type="number" name="points" min="-100" max="100" value="<?= e($payload['points'] ?? '') ?>" required>
            </label>
            <label class="form-grid__full">
                <span>Grund</span>
                <textarea name="reason" rows="4" maxlength="500" required><?= e($payload['reason'] ?? '') ?></textarea>
            </label>
            <div class="form-actions form-grid__full">
                <button type="submit" class="button button--primary">Korrektur buchen</button>
                <a class="button button--ghost" href="/admin/ordnungspunkte">Abbrechen</a>
            </div>
        </form>
    </section>
<?php endif; ?>
