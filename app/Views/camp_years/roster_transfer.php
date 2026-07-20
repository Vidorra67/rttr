<?php
/** @var array $toCampYear */
/** @var array|null $fromCampYear */
/** @var array $earlierYears */
/** @var array $roster */
use App\Support\Csrf;

$toCampYear = $toCampYear ?? [];
$fromCampYear = $fromCampYear ?? null;
$earlierYears = $earlierYears ?? [];
$roster = $roster ?? [];
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Lagerverwaltung</p>
        <h2>Teilnehmer übernehmen</h2>
        <p class="muted">Übernimm einzelne Teilnehmer aus einem früheren Lagerjahr nach <strong><?= e($toCampYear['name'] ?? '') ?></strong>. Bestätigte Rangaufstiege werden dabei automatisch angewendet.</p>
    </div>
    <a class="button button--ghost" href="/admin/lagerjahre">Zurück</a>
</section>

<?php if ($fromCampYear === null): ?>
    <section class="card empty-state">
        <div class="empty-icon">!</div>
        <h2>Kein früheres Lagerjahr</h2>
        <p class="muted">Es gibt kein Lagerjahr vor <?= e($toCampYear['name'] ?? '') ?>, aus dem übernommen werden könnte.</p>
    </section>
<?php else: ?>
    <?php if (count($earlierYears) > 1): ?>
        <form method="get" action="/admin/lagerjahre/uebernahme" class="card filter-card">
            <input type="hidden" name="to" value="<?= e($toCampYear['id']) ?>">
            <label>Quelljahr
                <select name="from" data-autosubmit>
                    <?php foreach ($earlierYears as $year): ?>
                        <option value="<?= e($year['id']) ?>" <?= (int) $fromCampYear['id'] === (int) $year['id'] ? 'selected' : '' ?>><?= e($year['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    <?php endif; ?>

    <?php if ($roster === []): ?>
        <section class="card empty-state">
            <div class="empty-icon">✓</div>
            <h2>Nichts zu übernehmen</h2>
            <p class="muted">Alle Teilnehmer aus <?= e($fromCampYear['name']) ?> sind entweder schon in <?= e($toCampYear['name'] ?? '') ?> erfasst oder es gab keine Teilnehmer.</p>
        </section>
    <?php else: ?>
        <form method="post" action="/admin/lagerjahre/uebernahme" class="card table-card">
            <?= Csrf::input() ?>
            <input type="hidden" name="to" value="<?= e($toCampYear['id']) ?>">
            <input type="hidden" name="from" value="<?= e($fromCampYear['id']) ?>">
            <div class="section-head section-head--compact">
                <div>
                    <p class="eyebrow">Aus <?= e($fromCampYear['name']) ?></p>
                    <h2>Wer ist wieder dabei?</h2>
                </div>
                <span class="category-tag category-tag--info"><?= e(count($roster)) ?> Teilnehmer</span>
            </div>
            <p class="muted">Kein Häkchen ist vorausgewählt. Markiere nur die Teilnehmer, die tatsächlich wieder mitmachen.</p>
            <div class="score-table-wrap">
                <table class="score-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Teilnehmer</th>
                            <th>Beiname</th>
                            <th>Orden/Zelt</th>
                            <th>Rang bisher</th>
                            <th>Wird</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roster as $person): ?>
                            <tr>
                                <td><input type="checkbox" name="person_ids[]" value="<?= e($person['id']) ?>"></td>
                                <td><strong><?= e($person['display_name']) ?></strong></td>
                                <td><?= e($person['nickname'] ?? '') ?></td>
                                <td><?= e($person['order_short_name'] ?? $person['order_name'] ?? 'offen') ?></td>
                                <td><?= e($person['rank_level_label'] ?? $person['rank_label'] ?? 'offen') ?></td>
                                <td>
                                    <?= e($person['will_become_rank_label']) ?>
                                    <?php if ($person['promotion_confirmed']): ?>
                                        <span class="status-chip status-chip--ok">bestätigt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button class="button button--primary" type="submit">Ausgewählte übernehmen</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
