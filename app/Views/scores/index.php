<?php
/** @var array|null $activeCampYear */
/** @var array $orders */
/** @var int|null $selectedOrderId */
/** @var array $orderSummary */
/** @var array $matrix */
/** @var array $rankLevels */
/** @var bool $canManage */
use App\Support\Auth;

$activeCampYear = $activeCampYear ?? null;
$orders = $orders ?? [];
$selectedOrderId = $selectedOrderId ?? null;
$orderSummary = $orderSummary ?? [];
$matrix = $matrix ?? ['participants' => [], 'units' => []];
$participants = $matrix['participants'] ?? [];
$units = $matrix['units'] ?? [];
$canManage = $canManage ?? false;
?>

<?php if ($activeCampYear === null): ?>
    <section class="hero-card">
        <p class="eyebrow">Auswertung</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Lege zuerst ein aktives Lagerjahr an. Danach können Rangstufen, Lerneinheiten und Prüfungsergebnisse gepflegt werden.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--hero" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="section-head">
        <div>
            <p class="eyebrow">Auswertung</p>
            <h2><?= e($activeCampYear['name']) ?></h2>
            <p class="muted">Zwischenstand für Rangordnung, Lerneinheiten und Prüfungen. Die Endwertung muss fachlich geprüft werden.</p>
        </div>
        <div class="management-actions">
            <a class="button button--ghost" href="/admin/auswertung/export<?= $selectedOrderId ? '?order_id=' . e($selectedOrderId) : '' ?>">CSV exportieren</a>
            <?php if ($canManage): ?>
                <a class="button button--ghost" href="/admin/rangstufen">Rangstufen</a>
                <a class="button button--ghost" href="/admin/lerneinheiten">Lerneinheiten</a>
                <a class="button button--primary" href="/admin/pruefungen">Prüfung erfassen</a>
            <?php endif; ?>
        </div>
    </section>

    <article class="card score-warning-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Hinweis</p>
                <h2>Endwertung nicht automatisch freigeben</h2>
            </div>
            <span class="status-chip status-chip--offen">Prüfung nötig</span>
        </div>
        <p class="muted">Diese Ansicht berechnet einen Zwischenstand aus gespeicherten Prüfungsergebnissen. Sie ersetzt noch keine geprüfte Endwertung und übernimmt keine Excel-Formeln unkontrolliert.</p>
    </article>

    <form method="get" action="/admin/auswertung" class="card filter-card">
        <label>
            Orden/Zelt filtern
            <select name="order_id" onchange="this.form.submit()">
                <option value="">Alle Orden/Zelte</option>
                <?php foreach ($orders as $order): ?>
                    <?php if ((int) ($order['is_active'] ?? 0) !== 1) { continue; } ?>
                    <option value="<?= e($order['id']) ?>" <?= (int) $selectedOrderId === (int) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <section class="score-summary-grid">
        <?php if ($orderSummary === []): ?>
            <article class="card empty-state">
                <div class="empty-icon" aria-hidden="true">A</div>
                <h2>Noch keine Auswertung</h2>
                <p class="muted">Lege Orden/Zelte, Teilnehmer und Lerneinheiten an, um Zwischenstände zu sehen.</p>
            </article>
        <?php else: ?>
            <?php foreach ($orderSummary as $row): ?>
                <?php $order = $row['order'] ?? []; ?>
                <article class="card score-order-card">
                    <div class="section-head section-head--compact">
                        <div>
                            <p class="eyebrow">Orden/Zelt</p>
                            <h2><?= e($order['short_name'] ?? $order['name'] ?? 'Orden') ?></h2>
                        </div>
                        <?php $scoreOrderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($order['color_hex'] ?? '')) ? (string) $order['color_hex'] : null; ?>
                        <span class="order-mini order-mini--<?= e($order['color_key'] ?? 'blau') ?>" <?= $scoreOrderColor ? 'style="--order-color: ' . e($scoreOrderColor) . '; --order-mini-bg: ' . e($scoreOrderColor) . '22;"' : '' ?>><?= e($order['short_name'] ?? 'O') ?></span>
                    </div>
                    <div class="score-mini-stats">
                        <span><strong><?= e($row['participant_count']) ?></strong> Teilnehmer</span>
                        <span><strong><?= e($row['passed_count']) ?></strong> bestanden</span>
                        <span><strong><?= e($row['open_count']) ?></strong> offen</span>
                        <span><strong><?= e(number_format((float) $row['points_sum'], 1, ',', '.')) ?></strong> Punkte</span>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card score-matrix-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Teilnehmer</p>
                <h2>Zwischenstand</h2>
            </div>
            <span class="category-tag category-tag--lernen"><?= e(count($participants)) ?> Personen</span>
        </div>

        <?php if ($participants === [] || $units === []): ?>
            <div class="empty-state empty-state--compact">
                <div class="empty-icon" aria-hidden="true">L</div>
                <h2>Noch unvollständige Daten</h2>
                <p class="muted">Für die Matrix braucht es Teilnehmer und mindestens eine Lerneinheit.</p>
                <?php if ($canManage): ?>
                    <a class="button button--primary" href="/admin/lerneinheiten/neu">Lerneinheit anlegen</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="score-table-wrap">
                <table class="score-table">
                    <thead>
                        <tr>
                            <th>Teilnehmer</th>
                            <th>Beiname</th>
                            <th>Orden/Zelt</th>
                            <th>Rang</th>
                            <th>Nächstes Jahr</th>
                            <th>Punkte</th>
                            <th>Bestanden</th>
                            <th>Offen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <tr>
                                <td><strong><?= e($participant['display_name']) ?></strong></td>
                                <td><?= e($participant['nickname'] ?? '') ?></td>
                                <td><?= e($participant['order_short_name'] ?? $participant['order_name'] ?? 'offen') ?></td>
                                <td><?= e($participant['rank_level_label'] ?? $participant['rank_label'] ?? 'offen') ?></td>
                                <td><?php $suggestion = $participant['suggested_next_rank'] ?? null; ?><?= is_array($suggestion) ? e($suggestion['label']) . ($suggestion['eligible'] ? ' · möglich' : ' · offen') : e($participant['next_rank_level_label'] ?? $participant['next_rank_label'] ?? 'offen') ?></td>
                                <td><?= e(number_format((float) $participant['points_sum'], 1, ',', '.')) ?></td>
                                <td><?= e($participant['passed_count']) ?></td>
                                <td><?= e($participant['open_count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
