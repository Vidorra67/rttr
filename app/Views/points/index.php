<?php
/** @var array|null $activeCampYear */
/** @var array $filters */
/** @var array $entries */
/** @var array $orders */
/** @var array $participants */
/** @var array $categories */
/** @var array $totals */
use App\Support\Csrf;

$filters = $filters ?? [];
$entries = $entries ?? [];
$orders = $orders ?? [];
$participants = $participants ?? [];
$categories = $categories ?? [];
$totals = $totals ?? [];
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Auswertung</p>
        <h2>Ordnungspunkte</h2>
        <p class="muted">Bewertungen für Ordnung, Sauberkeit, Zelt, Disziplin, Prüfungen und Zusatzdienste. Einträge werden nicht hart gelöscht.</p>
    </div>
    <div class="management-actions">
        <a class="button button--ghost" href="/ordnung">Bewertung erfassen</a>
        <a class="button button--primary" href="/admin/ordnungspunkte/korrektur">Korrektur buchen</a>
    </div>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card empty-state">
        <div class="empty-icon">!</div>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Ordnungspunkte brauchen ein aktives Lagerjahr.</p>
    </section>
<?php else: ?>
    <?php if ($totals !== []): ?>
        <section class="point-total-grid">
            <?php foreach ($totals as $total): ?>
                <article class="card point-total-card">
                    <?php $totalOrderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($total['color_hex'] ?? '')) ? (string) $total['color_hex'] : null; ?>
                    <span class="order-mini order-mini--<?= e($total['color_key'] ?: 'blau') ?>" <?= $totalOrderColor ? 'style="--order-color: ' . e($totalOrderColor) . '; --order-mini-bg: ' . e($totalOrderColor) . '22;"' : '' ?>><?= e($total['short_name']) ?></span>
                    <div>
                        <p><?= e($total['name']) ?></p>
                        <strong><?= e((int) $total['points_sum']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="card filter-card">
        <form method="get" action="/admin/ordnungspunkte" class="filter-grid">
            <label>
                <span>Datum</span>
                <input type="date" name="date" value="<?= e($filters['date'] ?? '') ?>">
            </label>
            <label>
                <span>Bewertungsart</span>
                <select name="category_id">
                    <option value="">Alle</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Orden/Zelt</span>
                <select name="order_id">
                    <option value="">Alle</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= e($order['id']) ?>" <?= (string) ($filters['order_id'] ?? '') === (string) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Person</span>
                <select name="person_id">
                    <option value="">Alle</option>
                    <?php foreach ($participants as $participant): ?>
                        <option value="<?= e($participant['id']) ?>" <?= (string) ($filters['person_id'] ?? '') === (string) $participant['id'] ? 'selected' : '' ?>><?= e($participant['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="check-label filter-check">
                <input type="checkbox" name="include_voided" value="1" <?= !empty($filters['include_voided']) ? 'checked' : '' ?>>
                <span>Stornierte anzeigen</span>
            </label>
            <div class="filter-actions">
                <button type="submit" class="button button--primary">Filtern</button>
                <a class="button button--ghost" href="/admin/ordnungspunkte">Zurücksetzen</a>
            </div>
        </form>
    </section>

    <?php if ($entries === []): ?>
        <section class="card empty-state">
            <div class="empty-icon">O</div>
            <h2>Noch keine Ordnungspunkte</h2>
            <p>Über die mobile Schnellaktion können Mitarbeiter die täglichen Punkte erfassen.</p>
            <a class="button button--primary" href="/ordnung">Bewertung erfassen</a>
        </section>
    <?php else: ?>
        <section class="point-admin-list">
            <?php foreach ($entries as $entry): ?>
                <article class="card point-admin-card <?= !empty($entry['voided_at']) ? 'point-admin-card--voided' : '' ?>">
                    <div class="point-admin-main">
                        <div class="point-badge <?= (int) $entry['points'] < 0 ? 'point-badge--negative' : 'point-badge--positive' ?>"><?= e((int) $entry['points']) ?></div>
                        <div>
                            <h2><?= e($entry['person_name'] ?: ($entry['order_name'] ?? 'Orden/Zelt')) ?></h2>
                            <div class="chip-row">
                                <span class="category-tag category-tag--info"><?= e($entry['category_label']) ?></span>
                                <?php if (!empty($entry['subject_label'])): ?>
                                    <span class="status-chip status-chip--ok"><?= e($entry['subject_label']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['order_short_name'])): ?>
                                    <?php $entryOrderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($entry['order_color_hex'] ?? '')) ? (string) $entry['order_color_hex'] : null; ?>
                                    <span class="order-mini order-mini--<?= e($entry['order_color_key'] ?: 'blau') ?>" <?= $entryOrderColor ? 'style="--order-color: ' . e($entryOrderColor) . '; --order-mini-bg: ' . e($entryOrderColor) . '22;"' : '' ?>><?= e($entry['order_short_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['voided_at'])): ?>
                                    <span class="status-chip status-chip--offen">storniert</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($entry['reason'])): ?>
                                <p><?= nl2br(e($entry['reason'])) ?></p>
                            <?php endif; ?>
                            <p class="muted">
                                <?= e(date('d.m.Y', strtotime((string) ($entry['scoring_date'] ?: $entry['created_at'])))) ?>
                                · erfasst <?= e(date('d.m.Y H:i', strtotime((string) $entry['created_at']))) ?> Uhr
                                <?php if (!empty($entry['created_by_name'])): ?> · <?= e($entry['created_by_name']) ?><?php endif; ?>
                                <?php if (!empty($entry['voided_at'])): ?> · storniert am <?= e(date('d.m.Y H:i', strtotime((string) $entry['voided_at']))) ?> Uhr<?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if (empty($entry['voided_at'])): ?>
                        <form method="post" action="/admin/ordnungspunkte/stornieren" class="void-form">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= e($entry['id']) ?>">
                            <input type="text" name="void_reason" maxlength="255" placeholder="Stornogrund" required>
                            <button type="submit" class="button button--ghost button--danger">Stornieren</button>
                        </form>
                    <?php elseif (!empty($entry['void_reason'])): ?>
                        <p class="muted">Stornogrund: <?= e($entry['void_reason']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
