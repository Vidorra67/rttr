<?php
/** @var array|null $activeCampYear */
/** @var array $orders */
/** @var int|null $selectedOrderId */
/** @var array $participants */
/** @var array $learningUnits */
/** @var array $rankLevels */
/** @var array $results */
/** @var array $resultLabels */
/** @var array $promotionStatusLabels */
/** @var bool $canManage */
$participants = $participants ?? [];
$learningUnits = $learningUnits ?? [];
$rankLevels = $rankLevels ?? [];
$results = $results ?? [];
$resultLabels = $resultLabels ?? [];
$promotionStatusLabels = $promotionStatusLabels ?? [];
$canManage = $canManage ?? false;
?>
<section class="section-head">
    <div><p class="eyebrow">Auswertung</p><h2>Prüfungsergebnisse</h2><p class="muted">Erfasse einzelne Ergebnisse je Teilnehmer und Lerneinheit. Änderungen werden protokolliert.</p></div>
    <div class="management-actions"><a class="button button--ghost" href="/admin/auswertung">Zur Auswertung</a><a class="button button--ghost" href="/admin/lerneinheiten">Lerneinheiten</a></div>
</section>
<?php if (($activeCampYear ?? null) === null): ?>
    <article class="card empty-state"><div class="empty-icon">P</div><h2>Kein aktives Lagerjahr</h2><p class="muted">Prüfungsergebnisse benötigen ein aktives Lagerjahr.</p></article>
<?php else: ?>
    <form method="get" action="/admin/pruefungen" class="card filter-card">
        <label>Orden/Zelt filtern<select name="order_id" data-autosubmit><option value="">Alle Orden/Zelte</option><?php foreach (($orders ?? []) as $order): ?><?php if ((int) ($order['is_active'] ?? 0) !== 1) { continue; } ?><option value="<?= e($order['id']) ?>" <?= (int) ($selectedOrderId ?? 0) === (int) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option><?php endforeach; ?></select></label>
    </form>

    <?php if ($canManage): ?>
        <section class="exam-entry-grid">
            <form method="post" action="/admin/pruefungen" class="card form-card">
                <?= \App\Support\Csrf::input() ?>
                <p class="eyebrow">Prüfung erfassen</p>
                <h2>Ergebnis speichern</h2>
                <div class="form-grid">
                    <label>Teilnehmer<select name="person_id" required><option value="">Bitte wählen</option><?php foreach ($participants as $person): ?><option value="<?= e($person['id']) ?>"><?= e($person['display_name']) ?><?= !empty($person['nickname']) ? ' · ' . e($person['nickname']) : '' ?><?= !empty($person['order_short_name']) ? ' · ' . e($person['order_short_name']) : '' ?></option><?php endforeach; ?></select></label>
                    <label>Lerneinheit<select name="learning_unit_id" required><option value="">Bitte wählen</option><?php foreach ($learningUnits as $unit): ?><option value="<?= e($unit['id']) ?>"><?= e($unit['title']) ?></option><?php endforeach; ?></select></label>
                    <label>Status<select name="result_status"><?php foreach ($resultLabels as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label>Punkte<input type="text" name="points" inputmode="decimal" placeholder="optional"></label>
                </div>
                <label class="field-title">Notiz<textarea name="note" maxlength="500" placeholder="Keine sensiblen Teilnehmernotizen eintragen."></textarea></label>
                <div class="form-actions"><button class="button button--primary" type="submit">Ergebnis speichern</button></div>
            </form>

            <form method="post" action="/admin/pruefungen/rang" class="card form-card">
                <?= \App\Support\Csrf::input() ?>
                <p class="eyebrow">Rangordnung</p>
                <h2>Rang zuweisen</h2>
                <div class="form-grid">
                    <label>Teilnehmer<select name="person_id" required><option value="">Bitte wählen</option><?php foreach ($participants as $person): ?><option value="<?= e($person['id']) ?>"><?= e($person['display_name']) ?><?= !empty($person['nickname']) ? ' · ' . e($person['nickname']) : '' ?><?= !empty($person['order_short_name']) ? ' · ' . e($person['order_short_name']) : '' ?></option><?php endforeach; ?></select></label>
                    <label>Rangstufe<select name="rank_level_id"><option value="">Freier Text</option><?php foreach ($rankLevels as $rank): ?><option value="<?= e($rank['id']) ?>"><?= e($rank['label']) ?></option><?php endforeach; ?></select></label>
                    <label>Rang frei<input type="text" name="rank_label" maxlength="190" placeholder="optional, wenn keine Rangstufe passt"></label>
                </div>
                <div class="form-actions"><button class="button button--primary" type="submit">Rang speichern</button></div>
            </form>

            <form method="post" action="/admin/pruefungen/aufstieg" class="card form-card">
                <?= \App\Support\Csrf::input() ?>
                <p class="eyebrow">Folgejahr</p>
                <h2>Rang für nächstes Jahr</h2>
                <div class="form-grid">
                    <label>Teilnehmer<select name="person_id" required><option value="">Bitte wählen</option><?php foreach ($participants as $person): ?><option value="<?= e($person['id']) ?>"><?= e($person['display_name']) ?><?= !empty($person['nickname']) ? ' · ' . e($person['nickname']) : '' ?><?= !empty($person['order_short_name']) ? ' · ' . e($person['order_short_name']) : '' ?></option><?php endforeach; ?></select></label>
                    <label>Nächster Rang<select name="next_rank_level_id"><option value="">Noch offen</option><?php foreach ($rankLevels as $rank): ?><option value="<?= e($rank['id']) ?>"><?= e($rank['label']) ?></option><?php endforeach; ?></select></label>
                    <label>Status<select name="promotion_status"><?php foreach ($promotionStatusLabels as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                </div>
                <label class="field-title">Notiz<input type="text" name="promotion_note" maxlength="500" placeholder="z. B. Prüfung bestanden oder Punkteschwelle erreicht"></label>
                <div class="form-actions"><button class="button button--primary" type="submit">Folgerang speichern</button></div>
            </form>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="section-head section-head--compact"><div><p class="eyebrow">Letzte Einträge</p><h2>Prüfungsergebnisse</h2></div><span class="category-tag category-tag--lernen"><?= e(count($results)) ?> Einträge</span></div>
        <?php if ($results === []): ?>
            <p class="muted">Es wurden noch keine Prüfungsergebnisse gespeichert.</p>
        <?php else: ?>
            <div class="score-table-wrap"><table class="score-table"><thead><tr><th>Teilnehmer</th><th>Beiname</th><th>Orden/Zelt</th><th>Lerneinheit</th><th>Status</th><th>Punkte</th><th>Geprüft von</th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><strong><?= e($result['person_name']) ?></strong></td><td><?= e($result['nickname'] ?? '') ?></td><td><?= e($result['order_short_name'] ?? $result['order_name'] ?? 'offen') ?></td><td><?= e($result['learning_unit_title']) ?></td><td><?= e($resultLabels[$result['result_status']] ?? $result['result_status']) ?></td><td><?= $result['points'] === null ? 'offen' : e(number_format((float) $result['points'], 1, ',', '.')) ?></td><td><?= e($result['assessed_by_name'] ?? 'offen') ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
