<?php
/** @var array $campYears */
/** @var array|null $campYear */
/** @var array $orders */
use App\Support\Auth;
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Lagerverwaltung</p>
        <h2>Orden/Zelte</h2>
    </div>
    <?php if (Auth::can('orders.manage') && $campYear !== null): ?>
        <a class="button button--primary" href="/admin/orden/neu?camp_year_id=<?= e($campYear['id']) ?>">Orden/Zelt anlegen</a>
    <?php endif; ?>
</section>

<?php if ($campYears !== []): ?>
    <form method="get" action="/admin/orden" class="card filter-card">
        <label>Lagerjahr
            <select name="camp_year_id" onchange="this.form.submit()">
                <?php foreach ($campYears as $year): ?>
                    <option value="<?= e($year['id']) ?>" <?= $campYear !== null && (int) $year['id'] === (int) $campYear['id'] ? 'selected' : '' ?>>
                        <?= e($year['name']) ?><?= (int) $year['is_active'] === 1 ? ' · aktiv' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
<?php endif; ?>

<?php if ($campYear === null): ?>
    <section class="card empty-state">
        <div class="empty-icon">+</div>
        <h2>Zuerst ein Lagerjahr anlegen</h2>
        <p>Orden und Zelte gehören immer zu einem Lagerjahr.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--primary" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php elseif ($orders === []): ?>
    <section class="card empty-state">
        <div class="empty-icon">+</div>
        <h2>Noch keine Orden/Zelte</h2>
        <p>Lege die Orden/Zelte für <?= e($campYear['name']) ?> an. Ein Orden ist hier gleichzeitig das Zelt.</p>
        <?php if (Auth::can('orders.manage')): ?>
            <a class="button button--primary" href="/admin/orden/neu?camp_year_id=<?= e($campYear['id']) ?>">Erstes Orden/Zelt anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="order-grid">
        <?php foreach ($orders as $order): ?>
            <?php $orderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($order['color_hex'] ?? '')) ? (string) $order['color_hex'] : null; ?>
            <article class="card order-card order-card--<?= e($order['color_key'] ?: 'blau') ?>" <?= $orderColor ? 'style="--order-color: ' . e($orderColor) . '; border-top-color: ' . e($orderColor) . ';"' : '' ?>>
                <div class="order-card-head">
                    <div class="order-badge" <?= $orderColor ? 'style="background: ' . e($orderColor) . '; color: #FFFFFF;"' : '' ?>><?= e($order['short_name']) ?></div>
                    <span class="status-chip <?= (int) $order['is_active'] === 1 ? 'status-chip--ok' : 'status-chip--muted' ?>">
                        <?= (int) $order['is_active'] === 1 ? 'aktiv' : 'inaktiv' ?>
                    </span>
                </div>
                <h2><?= e($order['name']) ?></h2>
                <div class="meta-grid order-meta">
                    <div><span>Leiter</span><strong><?= e($order['leader_name'] ?: 'offen') ?></strong></div>
                    <div><span>Helfer</span><strong><?= e($order['helper_name'] ?: 'offen') ?></strong></div>
                    <div><span>Reihenfolge</span><strong><?= e($order['sort_order']) ?></strong></div>
                </div>
                <?php if (Auth::can('orders.manage')): ?>
                    <div class="form-actions">
                        <a class="button button--ghost" href="/admin/orden/bearbeiten?id=<?= e($order['id']) ?>">Bearbeiten</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
