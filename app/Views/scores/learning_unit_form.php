<?php
/** @var array $unit */
$unit = $unit ?? [];
$isEdit = !empty($unit['id']);
$action = $isEdit ? '/admin/lerneinheiten/speichern' : '/admin/lerneinheiten';
$categories = ['lernen' => 'Lernen', 'bibelarbeit' => 'Bibelarbeit', 'spiel' => 'Spiel', 'info' => 'Info', 'wettbewerb' => 'Wettbewerb'];
?>
<section class="section-head">
    <div><p class="eyebrow">Lerneinheit</p><h2><?= e($title ?? 'Lerneinheit') ?></h2><p class="muted">Diese Daten werden für Prüfungsergebnisse und Zwischenstände genutzt.</p></div>
    <a class="button button--ghost" href="/admin/lerneinheiten">Zurück</a>
</section>
<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= \App\Support\Csrf::input() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($unit['id']) ?>"><?php endif; ?>
    <div class="form-grid">
        <label>Titel<input type="text" name="title" value="<?= e($unit['title'] ?? '') ?>" maxlength="190" required placeholder="z. B. Knoten"></label>
        <label>Kategorie<select name="category_key"><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>" <?= (($unit['category_key'] ?? 'lernen') === $key) ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Verantwortliche<input type="text" name="responsible_label" value="<?= e($unit['responsible_label'] ?? '') ?>" maxlength="190" placeholder="z. B. Micha + Tristan"></label>
        <label>Sortierung<input type="number" name="sort_order" value="<?= e($unit['sort_order'] ?? 100) ?>" min="0" max="9999"></label>
    </div>
    <div class="form-actions"><a class="button button--ghost" href="/admin/lerneinheiten">Abbrechen</a><button class="button button--primary" type="submit">Lerneinheit speichern</button></div>
</form>
