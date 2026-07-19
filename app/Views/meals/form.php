<?php
/** @var array|null $activeCampYear */
/** @var array $mealItem */
/** @var array $mealTypes */
/** @var string $action */
/** @var string $backUrl */
$mealItem = $mealItem ?? [];
$mealTypes = $mealTypes ?? [];
$mealTime = !empty($mealItem['meal_time']) ? substr((string) $mealItem['meal_time'], 0, 5) : '';
$ingredients = $mealItem['ingredients'] ?? [];
if ($ingredients === []) {
    $ingredients = [
        ['name' => '', 'quantity' => '', 'unit' => '', 'note' => ''],
        ['name' => '', 'quantity' => '', 'unit' => '', 'note' => ''],
        ['name' => '', 'quantity' => '', 'unit' => '', 'note' => ''],
    ];
}
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Essen</p>
        <h2><?= e($title ?? 'Mahlzeit') ?></h2>
        <p class="muted">Trage Gericht, Portionen, Hinweise und optional erste Zutaten ein. Die Einkaufsliste wird erst später vollständig automatisiert.</p>
    </div>
    <a class="button button--ghost" href="<?= e($backUrl ?? '/essen') ?>">Zurück</a>
</section>

<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= \App\Support\Csrf::input() ?>
    <?php if (!empty($mealItem['id'])): ?>
        <input type="hidden" name="id" value="<?= e($mealItem['id']) ?>">
    <?php endif; ?>
    <input type="hidden" name="camp_year_id" value="<?= e($mealItem['camp_year_id'] ?? '') ?>">

    <div class="form-grid">
        <label>
            Datum
            <input type="date" name="meal_date" value="<?= e($mealItem['meal_date'] ?? '') ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
        </label>
        <label>
            Mahlzeit
            <select name="meal_type" required>
                <?php foreach ($mealTypes as $key => $type): ?>
                    <option value="<?= e($key) ?>" <?= (($mealItem['meal_type'] ?? 'fruehstueck') === $key) ? 'selected' : '' ?>><?= e($type['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Uhrzeit
            <input type="time" name="meal_time" value="<?= e($mealTime) ?>">
        </label>
        <label>
            Portionen gesamt
            <input type="number" name="portions_total" value="<?= e($mealItem['portions_total'] ?? 0) ?>" min="0" step="1">
        </label>
        <label>
            Vegetarische Portionen
            <input type="number" name="portions_vegetarian" value="<?= e($mealItem['portions_vegetarian'] ?? 0) ?>" min="0" step="1">
        </label>
        <label>
            Küchenteam oder Verantwortliche
            <input type="text" name="kitchen_team_label" value="<?= e($mealItem['kitchen_team_label'] ?? '') ?>" maxlength="190" placeholder="z. B. Hans/Chris, Küchenteam">
        </label>
    </div>

    <label class="field-title">
        Gericht oder Beschreibung
        <input type="text" name="title" value="<?= e($mealItem['title'] ?? '') ?>" maxlength="190" required placeholder="z. B. Frühstück, Käsespätzle, Grillabend">
    </label>

    <label class="field-title">
        Beschreibung oder Notiz
        <textarea name="description" maxlength="5000"><?= e($mealItem['description'] ?? '') ?></textarea>
    </label>

    <label class="field-title">
        Allergiehinweise
        <textarea name="allergy_notes" maxlength="5000" placeholder="Nur allgemeine Küchenhinweise eintragen. Personenbezogene Details später mit eigenen Rechten pflegen."><?= e($mealItem['allergy_notes'] ?? '') ?></textarea>
    </label>

    <div class="field-title">Zutaten als Vorbereitung für die Einkaufsliste</div>
    <p class="muted">Optional. Diese Angaben werden gespeichert, aber die automatische Einkaufsliste ist noch nicht Teil dieser Phase.</p>
    <div class="ingredient-grid">
        <?php foreach ($ingredients as $ingredient): ?>
            <div class="ingredient-row">
                <input type="text" name="ingredient_name[]" value="<?= e($ingredient['name'] ?? '') ?>" maxlength="190" placeholder="Zutat">
                <input type="text" name="ingredient_quantity[]" value="<?= e($ingredient['quantity'] ?? '') ?>" placeholder="Menge">
                <input type="text" name="ingredient_unit[]" value="<?= e($ingredient['unit'] ?? '') ?>" maxlength="40" placeholder="Einheit">
                <input type="text" name="ingredient_note[]" value="<?= e($ingredient['note'] ?? '') ?>" maxlength="255" placeholder="Notiz">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <a class="button button--ghost" href="<?= e($backUrl ?? '/essen') ?>">Abbrechen</a>
        <button type="submit" class="button button--primary">Mahlzeit speichern</button>
    </div>
</form>
