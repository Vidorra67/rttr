<?php
/** @var array $persons */
/** @var array $filters */
/** @var array|null $activeCampYear */
/** @var array $orders */
/** @var bool $canViewSensitive */
use App\Support\Auth;
use App\Support\Csrf;
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Verwaltung</p>
        <h2>Personen & Lagerstatus</h2>
    </div>
    <?php if (Auth::can('persons.manage')): ?>
        <a class="button button--primary" href="/admin/personen/neu">Person anlegen</a>
    <?php endif; ?>
</section>

<section class="card filter-card">
    <form method="get" action="/admin/personen" class="filter-grid">
        <label>
            <span>Suche</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name, Telefon, E-Mail">
        </label>
        <label>
            <span>Status</span>
            <select name="type">
                <?php $type = (string) ($filters['type'] ?? ''); ?>
                <option value="" <?= $type === '' ? 'selected' : '' ?>>Alle</option>
                <option value="participant" <?= $type === 'participant' ? 'selected' : '' ?>>Teilnehmer</option>
                <option value="staff" <?= $type === 'staff' ? 'selected' : '' ?>>Mitarbeiter</option>
                <option value="both" <?= $type === 'both' ? 'selected' : '' ?>>Teilnehmer und Mitarbeiter</option>
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
            <span>Aktiv</span>
            <?php $active = (string) ($filters['active'] ?? ''); ?>
            <select name="active">
                <option value="" <?= $active === '' ? 'selected' : '' ?>>Alle</option>
                <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Aktiv</option>
                <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inaktiv</option>
            </select>
        </label>
        <label class="check-label filter-check">
            <input type="checkbox" name="birthday_in_camp" value="1" <?= (string) ($filters['birthday_in_camp'] ?? '') === '1' ? 'checked' : '' ?>>
            <span>Geburtstag im Lager</span>
        </label>
        <div class="filter-actions">
            <button type="submit" class="button button--primary">Filtern</button>
            <a class="button button--ghost" href="/admin/personen">Zurücksetzen</a>
        </div>
    </form>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card info-card">
        <p class="eyebrow">Hinweis</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p class="muted">Teilnehmer- und Mitarbeiterstatus, Orden/Zelt-Zuordnung und Geburtstage im Lager werden genauer angezeigt, sobald ein Lagerjahr aktiv ist.</p>
    </section>
<?php endif; ?>

<?php if ($persons === []): ?>
    <section class="card empty-state">
        <div class="empty-icon">+</div>
        <h2>Noch keine Personen</h2>
        <p>Lege Personen an und markiere sie je Lagerjahr als Teilnehmer, Mitarbeiter oder beides.</p>
        <?php if (Auth::can('persons.manage')): ?>
            <a class="button button--primary" href="/admin/personen/neu">Erste Person anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="person-list">
        <?php foreach ($persons as $person): ?>
            <article class="card person-card">
                <div class="person-main">
                    <div class="avatar" aria-hidden="true"><?= e(substr((string) $person['display_name'], 0, 1)) ?></div>
                    <div>
                        <h2><?= e($person['display_name']) ?></h2>
                        <p><?= e($person['first_name']) ?> <?= e($person['last_name']) ?><?= !empty($person['nickname']) ? ' · ' . e($person['nickname']) : '' ?></p>
                        <div class="chip-row">
                            <span class="status-chip <?= (int) $person['is_active'] === 1 ? 'status-chip--ok' : 'status-chip--offen' ?>">
                                <?= (int) $person['is_active'] === 1 ? 'aktiv' : 'inaktiv' ?>
                            </span>
                            <?php if (!empty($person['is_participant_effective'])): ?>
                                <span class="role-chip">Teilnehmer</span>
                            <?php endif; ?>
                            <?php if (!empty($person['is_staff_effective'])): ?>
                                <span class="role-chip">Mitarbeiter</span>
                            <?php endif; ?>
                            <?php if (!empty($person['order_short_name'])): ?>
                                <?php $personOrderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($person['order_color_hex'] ?? '')) ? (string) $person['order_color_hex'] : null; ?>
                                <span class="order-mini order-mini--<?= e($person['order_color_key'] ?: 'blau') ?>" <?= $personOrderColor ? 'style="--order-color: ' . e($personOrderColor) . '; --order-mini-bg: ' . e($personOrderColor) . '22;"' : '' ?>><?= e($person['order_short_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($person['birthday_in_camp'])): ?>
                                <span class="category-tag category-tag--spiel">Geburtstag im Lager</span>
                            <?php endif; ?>
                            <?php if ((int) ($person['is_login_enabled'] ?? 0) === 1): ?>
                                <span class="status-chip status-chip--ok">Login aktiv</span>
                            <?php endif; ?>
                        </div>
                        <p class="muted person-meta">
                            <?php if (!empty($person['birthdate'])): ?>
                                Geboren am <?= e(date('d.m.Y', strtotime((string) $person['birthdate']))) ?>
                                <?php if ($person['age_at_camp'] !== null): ?> · <?= e($person['age_at_camp']) ?> Jahre zum Lagerstart<?php endif; ?>
                            <?php else: ?>
                                Geburtsdatum fehlt
                            <?php endif; ?>
                            <?php if (!empty($person['order_name'])): ?> · <?= e($person['order_name']) ?><?php endif; ?><?php if (!empty($person['rank_level_label']) || !empty($person['rank_label'])): ?> · Rang: <?= e($person['rank_level_label'] ?? $person['rank_label']) ?><?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="person-actions">
                    <a class="button button--ghost" href="/admin/personen/detail?id=<?= e($person['id']) ?>">Details</a>
                    <?php if (Auth::can('persons.manage')): ?>
                        <a class="button button--ghost" href="/admin/personen/bearbeiten?id=<?= e($person['id']) ?>">Bearbeiten</a>
                        <?php if (!empty($person['user_id'])): ?>
                            <form method="post" action="/admin/personen/login" class="inline-form">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= e($person['id']) ?>">
                                <input type="hidden" name="enabled" value="<?= (int) ($person['is_login_enabled'] ?? 0) === 1 ? '0' : '1' ?>">
                                <button type="submit" class="button button--ghost">
                                    <?= (int) ($person['is_login_enabled'] ?? 0) === 1 ? 'Login deaktivieren' : 'Login aktivieren' ?>
                                </button>
                            </form>
                            <form method="post" action="/admin/personen/pin" class="inline-pin-form">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= e($person['id']) ?>">
                                <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="Neue PIN" required>
                                <button type="submit" class="button button--ghost">PIN ändern</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
