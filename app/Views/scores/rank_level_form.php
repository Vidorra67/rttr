<?php
/** @var array $rank */
$rank = $rank ?? [];
$isEdit = !empty($rank['id']);
$action = $isEdit ? '/admin/rangstufen/speichern' : '/admin/rangstufen';
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Rangordnung</p>
        <h2><?= e($title ?? 'Rangstufe') ?></h2>
        <p class="muted">Rangstufen werden je Lagerjahr geführt. Der nächste Rang gilt als Vorschlag für das Folgejahr nach bestandener Prüfung oder erreichter Punktzahl.</p>
    </div>
    <a class="button button--ghost" href="/admin/rangstufen">Zurück</a>
</section>
<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= \App\Support\Csrf::input() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($rank['id']) ?>"><?php endif; ?>
    <div class="form-grid">
        <label>Bezeichnung<input type="text" name="label" value="<?= e($rank['label'] ?? '') ?>" maxlength="190" required placeholder="z. B. Knappe"></label>
        <label>Schlüssel<input type="text" name="key_name" value="<?= e($rank['key_name'] ?? '') ?>" maxlength="80" placeholder="z. B. knappe"></label>
        <label>Sortierung<input type="number" name="sort_order" value="<?= e($rank['sort_order'] ?? 100) ?>" min="0" max="9999"></label>
        <label>Punkteschwelle<input type="number" name="promotion_points_required" value="<?= e($rank['promotion_points_required'] ?? '') ?>" min="0" step="0.5" placeholder="optional"></label>
        <label>Nächster Rang-Schlüssel<input type="text" name="next_rank_key" value="<?= e($rank['next_rank_key'] ?? '') ?>" maxlength="80" placeholder="z. B. ritter"></label>
        <label class="form-grid__full">Wechseltext<input type="text" name="promotion_text" value="<?= e($rank['promotion_text'] ?? '') ?>" maxlength="255" placeholder="z. B. Von Knappe zum Ritter"></label>
    </div>
    <div class="form-actions"><a class="button button--ghost" href="/admin/rangstufen">Abbrechen</a><button class="button button--primary" type="submit">Rangstufe speichern</button></div>
</form>
