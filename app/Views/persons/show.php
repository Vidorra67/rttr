<?php
/** @var array $person */
/** @var array|null $activeCampYear */
/** @var bool $canViewSensitive */
use App\Support\Auth;
$guardians = is_array($person['guardians'] ?? null) ? $person['guardians'] : [];
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Personendaten</p>
        <h2><?= e($person['display_name']) ?></h2>
    </div>
    <div class="button-row">
        <?php if (Auth::can('persons.manage')): ?>
            <a class="button button--primary" href="/admin/personen/bearbeiten?id=<?= e($person['id']) ?>">Bearbeiten</a>
        <?php endif; ?>
        <a class="button button--ghost" href="/admin/personen">Zurück</a>
    </div>
</section>

<section class="person-detail-grid">
    <article class="card detail-card">
        <p class="eyebrow">Stammdaten</p>
        <h2><?= e($person['first_name']) ?> <?= e($person['last_name']) ?></h2>
        <dl class="detail-list">
            <div><dt>Geburtsdatum</dt><dd><?= !empty($person['birthdate']) ? e(date('d.m.Y', strtotime((string) $person['birthdate']))) : 'nicht hinterlegt' ?></dd></div>
            <div><dt>Alter zum Lagerstart</dt><dd><?= $person['age_at_camp'] !== null ? e($person['age_at_camp']) . ' Jahre' : 'offen' ?></dd></div>
            <div><dt>Geburtstag im Lager</dt><dd><?= !empty($person['birthday_in_camp']) ? 'Ja' : 'Nein' ?></dd></div>
            <div><dt>Telefon</dt><dd><?= e($person['phone'] ?? 'nicht hinterlegt') ?></dd></div>
            <div><dt>E-Mail</dt><dd><?= e($person['email'] ?? 'nicht hinterlegt') ?></dd></div>
            <div><dt>Adresse</dt><dd><?= e(trim((string) ($person['street'] ?? '') . ', ' . (string) ($person['zip'] ?? '') . ' ' . (string) ($person['city'] ?? ''), ' ,')) ?: 'nicht hinterlegt' ?></dd></div>
        </dl>
    </article>

    <article class="card detail-card">
        <p class="eyebrow">Lagerstatus</p>
        <h2><?= $activeCampYear === null ? 'Kein aktives Lagerjahr' : e($activeCampYear['name']) ?></h2>
        <div class="chip-row">
            <?php if (!empty($person['is_participant_effective'])): ?><span class="role-chip">Teilnehmer</span><?php endif; ?>
            <?php if (!empty($person['is_staff_effective'])): ?><span class="role-chip">Mitarbeiter</span><?php endif; ?>
            <?php if (!empty($person['order_short_name'])): ?><?php $personOrderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($person['order_color_hex'] ?? '')) ? (string) $person['order_color_hex'] : null; ?><span class="order-mini order-mini--<?= e($person['order_color_key'] ?: 'blau') ?>" <?= $personOrderColor ? 'style="--order-color: ' . e($personOrderColor) . '; --order-mini-bg: ' . e($personOrderColor) . '22;"' : '' ?>><?= e($person['order_short_name']) ?></span><?php endif; ?>
        </div>
        <dl class="detail-list">
            <div><dt>Teilnehmerstatus</dt><dd><?= e($person['participant_status'] ?? 'offen') ?></dd></div>
            <div><dt>Mitarbeiterstatus</dt><dd><?= e($person['staff_status'] ?? 'offen') ?></dd></div>
            <div><dt>Orden/Zelt</dt><dd><?= e($person['order_name'] ?? 'nicht zugeordnet') ?></dd></div>
            <div><dt>Rang</dt><dd><?= e($person['rank_level_label'] ?? $person['rank_label'] ?? 'offen') ?></dd></div>
            <div><dt>Beiname</dt><dd><?= e($person['nickname'] ?? 'offen') ?></dd></div>
            <div><dt>Folgerang</dt><dd><?= e($person['next_rank_level_label'] ?? $person['next_rank_label'] ?? 'offen') ?><?= !empty($person['promotion_status']) ? ' · ' . e($person['promotion_status']) : '' ?></dd></div>
        </dl>
    </article>

    <article class="card detail-card">
        <p class="eyebrow">Essen & Hinweise</p>
        <h2>Küchenhinweise</h2>
        <dl class="detail-list">
            <div><dt>Essenshinweise</dt><dd><?= nl2br(e($person['food_notes'] ?? 'keine')) ?></dd></div>
            <?php if ($canViewSensitive): ?>
                <div><dt>Allergien</dt><dd><?= nl2br(e($person['allergy_notes'] ?? 'keine')) ?></dd></div>
            <?php endif; ?>
        </dl>
    </article>

    <?php if ($canViewSensitive): ?>
        <article class="card detail-card detail-card--sensitive">
            <p class="eyebrow">Geschützt</p>
            <h2>Notfall & Medizin</h2>
            <dl class="detail-list">
                <div><dt>Notfallkontakt</dt><dd><?= e($person['emergency_contact_name'] ?? 'nicht hinterlegt') ?></dd></div>
                <div><dt>Telefon Notfall</dt><dd><?= e($person['emergency_contact_phone'] ?? 'nicht hinterlegt') ?></dd></div>
                <div><dt>Medizinische Hinweise</dt><dd><?= nl2br(e($person['medical_notes'] ?? 'keine')) ?></dd></div>
                <div><dt>Interne Bemerkungen</dt><dd><?= nl2br(e($person['internal_notes'] ?? 'keine')) ?></dd></div>
            </dl>
            <?php if ($guardians !== []): ?>
                <h3>Weitere Kontakte</h3>
                <?php foreach ($guardians as $guardian): ?>
                    <p class="muted"><strong><?= e($guardian['name']) ?></strong><?= !empty($guardian['relation_label']) ? ' · ' . e($guardian['relation_label']) : '' ?><?= !empty($guardian['phone']) ? ' · ' . e($guardian['phone']) : '' ?><?= !empty($guardian['email']) ? ' · ' . e($guardian['email']) : '' ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <article class="card detail-card">
            <p class="eyebrow">Geschützt</p>
            <h2>Sensible Daten ausgeblendet</h2>
            <p class="muted">Notfallkontakte, Allergien, medizinische Hinweise und interne Bemerkungen sind nur für berechtigte Rollen sichtbar.</p>
        </article>
    <?php endif; ?>
</section>
