<?php
/** @var array|null $activeCampYear */
/** @var array $programItem */
/** @var array $categories */
/** @var array $orderOptions */
/** @var string $action */
/** @var string $backUrl */
$programItem = $programItem ?? [];
$categories = $categories ?? [];
$orderOptions = $orderOptions ?? [];
$selectedOrders = array_map('intval', $programItem['order_ids'] ?? []);
$startsAt = !empty($programItem['starts_at']) ? substr((string) $programItem['starts_at'], 0, 5) : '';
$endsAt = !empty($programItem['ends_at']) ? substr((string) $programItem['ends_at'], 0, 5) : '';
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Programm</p>
        <h2><?= e($title ?? 'Programmpunkt') ?></h2>
        <p class="muted">Trage hier den Tagesablauf ein. Sichtbar ist der Punkt danach in der Programm-Timeline.</p>
    </div>
    <a class="button button--ghost" href="<?= e($backUrl ?? '/programm') ?>">Zurück</a>
</section>

<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= \App\Support\Csrf::input() ?>
    <?php if (!empty($programItem['id'])): ?>
        <input type="hidden" name="id" value="<?= e($programItem['id']) ?>">
    <?php endif; ?>
    <input type="hidden" name="camp_year_id" value="<?= e($programItem['camp_year_id'] ?? '') ?>">

    <div class="form-grid">
        <label>
            Datum
            <input type="date" name="program_date" value="<?= e($programItem['program_date'] ?? '') ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
        </label>
        <label>
            Kategorie
            <select name="category_key" required>
                <?php foreach ($categories as $key => $category): ?>
                    <option value="<?= e($key) ?>" <?= (($programItem['category_key'] ?? 'info') === $key) ? 'selected' : '' ?>><?= e($category['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Startzeit
            <input type="time" name="starts_at" value="<?= e($startsAt) ?>">
        </label>
        <label>
            Endzeit
            <input type="time" name="ends_at" value="<?= e($endsAt) ?>">
        </label>
        <label>
            Titel
            <input type="text" name="title" value="<?= e($programItem['title'] ?? '') ?>" maxlength="190" required>
        </label>
        <label>
            Ort
            <input type="text" name="location" value="<?= e($programItem['location'] ?? '') ?>" maxlength="190">
        </label>
        <label>
            Verantwortlich
            <input type="text" name="responsible_label" value="<?= e($programItem['responsible_label'] ?? '') ?>" maxlength="190" placeholder="z. B. Timo, Grafen, Küchenteam">
        </label>
        <label>
            Sortierung bei gleicher Uhrzeit
            <input type="number" name="sort_order" value="<?= e($programItem['sort_order'] ?? 0) ?>" min="0" step="1">
        </label>
        <label>
            Wiederkehrend
            <span class="check-label check-label--field">
                <input type="checkbox" name="is_recurring" value="1" <?= (int) ($programItem['is_recurring'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span>anders markieren</span>
            </span>
        </label>
        <label>
            Wiederkehrend-Text
            <input type="text" name="recurring_label" value="<?= e($programItem['recurring_label'] ?? '') ?>" maxlength="190" placeholder="z. B. täglich, fix, wiederkehrend">
        </label>
    </div>

    <label class="field-title">
        Beschreibung oder Notiz
        <textarea name="description" maxlength="5000"><?= e($programItem['description'] ?? '') ?></textarea>
    </label>

    <div class="field-title">Betroffene Orden/Zelte</div>
    <?php if ($orderOptions === []): ?>
        <p class="muted">Es sind noch keine aktiven Orden/Zelte für dieses Lagerjahr angelegt. Der Programmpunkt kann trotzdem gespeichert werden.</p>
    <?php else: ?>
        <div class="role-grid">
            <?php foreach ($orderOptions as $order): ?>
                <label class="role-option check-label">
                    <input type="checkbox" name="order_ids[]" value="<?= e($order['id']) ?>" <?= in_array((int) $order['id'], $selectedOrders, true) ? 'checked' : '' ?>>
                    <span><?= e($order['name']) ?> <small><?= e($order['short_name']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <a class="button button--ghost" href="<?= e($backUrl ?? '/programm') ?>">Abbrechen</a>
        <button type="submit" class="button button--primary">Programmpunkt speichern</button>
    </div>
</form>
