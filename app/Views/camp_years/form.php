<?php
/** @var array|null $campYear */
/** @var string $action */
use App\Support\Csrf;

$isEdit = is_array($campYear) && !empty($campYear['id']);
$value = static function (string $key, string $default = '') use ($campYear): string {
    return (string) ($campYear[$key] ?? $default);
};
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Lagerverwaltung</p>
        <h2><?= $isEdit ? 'Lagerjahr bearbeiten' : 'Lagerjahr anlegen' ?></h2>
    </div>
    <a class="button button--ghost" href="/admin/lagerjahre">Zurück</a>
</section>

<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($campYear['id']) ?>">
    <?php endif; ?>

    <div class="form-grid">
        <label>Name
            <input type="text" name="name" value="<?= e($value('name')) ?>" maxlength="190" required placeholder="Ritterlager 2026">
        </label>
        <label>Ort
            <input type="text" name="location_name" value="<?= e($value('location_name')) ?>" maxlength="190" placeholder="Zeltplatz">
        </label>
        <label>Startdatum
            <input type="date" name="starts_on" value="<?= e($value('starts_on')) ?>" required>
        </label>
        <label>Enddatum
            <input type="date" name="ends_on" value="<?= e($value('ends_on')) ?>" required>
        </label>
    </div>

    <label class="check-label">
        <input type="checkbox" name="is_active" value="1" <?= (int) ($campYear['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
        <span>Als aktives Lagerjahr verwenden</span>
    </label>

    <div class="form-actions">
        <a class="button button--ghost" href="/admin/lagerjahre">Abbrechen</a>
        <button class="button button--primary" type="submit">Lagerjahr speichern</button>
    </div>
</form>
