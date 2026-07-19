<?php
/** @var array $dutyType */
/** @var array $assignmentModes */
/** @var string $action */
/** @var string $backUrl */
$dutyType = $dutyType ?? [];
$assignmentModes = $assignmentModes ?? [];
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Dienste</p>
        <h2><?= e($title ?? 'Dienstart') ?></h2>
        <p class="muted">Dienstarten sind Vorlagen für tägliche Aufgaben.</p>
    </div>
    <a class="button button--ghost" href="<?= e($backUrl ?? '/admin/dienstarten') ?>">Zurück</a>
</section>

<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= \App\Support\Csrf::input() ?>
    <?php if (!empty($dutyType['id'])): ?>
        <input type="hidden" name="id" value="<?= e($dutyType['id']) ?>">
    <?php endif; ?>

    <div class="form-grid">
        <label>
            Schlüssel
            <input type="text" name="key_name" value="<?= e($dutyType['key_name'] ?? '') ?>" maxlength="80" required placeholder="z. B. platzdienst">
        </label>
        <label>
            Name
            <input type="text" name="label" value="<?= e($dutyType['label'] ?? '') ?>" maxlength="190" required placeholder="z. B. Platzdienst">
        </label>
        <label>
            Icon-Kürzel
            <input type="text" name="icon_key" value="<?= e($dutyType['icon_key'] ?? '') ?>" maxlength="40" placeholder="assignment">
        </label>
        <label>
            Standard-Zeitlabel
            <input type="text" name="default_time_label" value="<?= e($dutyType['default_time_label'] ?? '') ?>" maxlength="80" placeholder="z. B. nach dem Frühstück">
        </label>
        <label>
            Zuweisungsart
            <select name="assignment_mode" required>
                <?php foreach ($assignmentModes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= (($dutyType['assignment_mode'] ?? 'mixed') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Sortierung
            <input type="number" name="sort_order" value="<?= e($dutyType['sort_order'] ?? 100) ?>" min="0" step="1">
        </label>
    </div>

    <label class="check-row check-row--single">
        <input type="checkbox" name="is_active" value="1" <?= !isset($dutyType['is_active']) || (int) $dutyType['is_active'] === 1 ? 'checked' : '' ?>>
        <span>Dienstart ist aktiv</span>
    </label>

    <div class="form-actions">
        <a class="button button--ghost" href="<?= e($backUrl ?? '/admin/dienstarten') ?>">Abbrechen</a>
        <button type="submit" class="button button--primary">Dienstart speichern</button>
    </div>
</form>
