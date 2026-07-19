<?php
/** @var array|null $order */
/** @var string $action */
/** @var array $campYears */
/** @var array|null $activeCampYear */
/** @var array $staffOptions */
/** @var array $colorOptions */
use App\Support\Csrf;

$isEdit = is_array($order) && !empty($order['id']);
$value = static function (string $key, string $default = '') use ($order, $activeCampYear): string {
    if ($key === 'camp_year_id' && ($order[$key] ?? '') === '' && $activeCampYear !== null) {
        return (string) $activeCampYear['id'];
    }
    return (string) ($order[$key] ?? $default);
};
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Lagerverwaltung</p>
        <h2><?= $isEdit ? 'Orden/Zelt bearbeiten' : 'Orden/Zelt anlegen' ?></h2>
    </div>
    <a class="button button--ghost" href="/admin/orden">Zurück</a>
</section>

<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($order['id']) ?>">
    <?php endif; ?>

    <div class="form-grid">
        <label>Lagerjahr
            <select name="camp_year_id" required>
                <option value="">Bitte auswählen</option>
                <?php foreach ($campYears as $campYear): ?>
                    <option value="<?= e($campYear['id']) ?>" <?= (int) $value('camp_year_id') === (int) $campYear['id'] ? 'selected' : '' ?>>
                        <?= e($campYear['name']) ?><?= (int) $campYear['is_active'] === 1 ? ' · aktiv' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Name
            <input type="text" name="name" value="<?= e($value('name')) ?>" maxlength="190" required placeholder="Johanniter">
        </label>
        <label>Kürzel
            <input type="text" name="short_name" value="<?= e($value('short_name')) ?>" maxlength="80" required placeholder="JOH">
        </label>
        <label>Farbmarke
            <select name="color_key">
                <option value="">Standard Blau</option>
                <?php foreach ($colorOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $value('color_key') === (string) $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Fallback, falls keine freie Farbe gesetzt ist.</small>
        </label>
        <label>Freie Ordensfarbe
            <input type="color" name="color_hex" value="<?= e($value('color_hex', '#2B49E0')) ?>">
            <small class="muted">Diese Farbe wird für Badges und Karten verwendet.</small>
        </label>
        <label>Leiter
            <select name="leader_person_id">
                <option value="">offen</option>
                <?php foreach ($staffOptions as $person): ?>
                    <option value="<?= e($person['id']) ?>" <?= (int) $value('leader_person_id') === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Helfer
            <select name="helper_person_id">
                <option value="">offen</option>
                <?php foreach ($staffOptions as $person): ?>
                    <option value="<?= e($person['id']) ?>" <?= (int) $value('helper_person_id') === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Reihenfolge
            <input type="number" name="sort_order" value="<?= e($value('sort_order', '0')) ?>" min="0" step="1">
        </label>
    </div>

    <label class="check-label">
        <input type="checkbox" name="is_active" value="1" <?= (int) ($order['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <span>Orden/Zelt aktiv</span>
    </label>

    <div class="form-actions">
        <a class="button button--ghost" href="/admin/orden">Abbrechen</a>
        <button class="button button--primary" type="submit">Orden/Zelt speichern</button>
    </div>
</form>
