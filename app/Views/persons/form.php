<?php
/** @var array|null $person */
/** @var array $roles */
/** @var array $campYears */
/** @var array|null $activeCampYear */
/** @var int|null $selectedCampYearId */
/** @var array $orders */
/** @var array $rankLevels */
/** @var bool $canViewSensitive */
/** @var string $action */
use App\Support\Csrf;

$isEdit = is_array($person) && !empty($person['id']);
$assignedRoles = is_array($person['roles'] ?? null) ? $person['roles'] : [];
$guardians = is_array($person['guardians'] ?? null) ? $person['guardians'] : [];
$rankLevels = $rankLevels ?? [];
$guardian = $guardians[0] ?? [];
$isParticipant = (bool) ($person['is_participant'] ?? $person['is_participant_effective'] ?? (($person['type_hint'] ?? '') === 'teilnehmer' || ($person['type_hint'] ?? '') === 'beides'));
$isStaff = (bool) ($person['is_staff'] ?? $person['is_staff_effective'] ?? (($person['type_hint'] ?? 'mitarbeiter') === 'mitarbeiter' || ($person['type_hint'] ?? '') === 'beides'));
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Personenverwaltung</p>
        <h2><?= $isEdit ? 'Person bearbeiten' : 'Person anlegen' ?></h2>
    </div>
    <a class="button button--ghost" href="/admin/personen">Zurück</a>
</section>

<form method="post" action="<?= e($action) ?>" class="form-stack" autocomplete="off">
    <?= Csrf::input() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($person['id']) ?>">
    <?php endif; ?>

    <section class="card form-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Stammdaten</p>
                <h2>Person</h2>
            </div>
            <span class="status-chip status-chip--ok">Grunddaten</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Vorname</span>
                <input type="text" name="first_name" value="<?= e($person['first_name'] ?? '') ?>" required>
            </label>
            <label>
                <span>Nachname</span>
                <input type="text" name="last_name" value="<?= e($person['last_name'] ?? '') ?>" required>
            </label>
            <label>
                <span>Anzeigename</span>
                <input type="text" name="display_name" value="<?= e($person['display_name'] ?? '') ?>" placeholder="optional">
            </label>
            <label>
                <span>Beiname</span>
                <input type="text" name="nickname" value="<?= e($person['nickname'] ?? '') ?>" placeholder="z. B. der Weise">
            </label>
            <label>
                <span>Geburtsdatum</span>
                <input type="date" name="birthdate" value="<?= e($person['birthdate'] ?? '') ?>">
                <small>Für Teilnehmer erforderlich. Geburtstage im Lager werden daraus berechnet.</small>
            </label>
            <label>
                <span>Telefon</span>
                <input type="text" name="phone" value="<?= e($person['phone'] ?? '') ?>">
            </label>
            <label>
                <span>E-Mail</span>
                <input type="email" name="email" value="<?= e($person['email'] ?? '') ?>">
            </label>
            <label>
                <span>Straße</span>
                <input type="text" name="street" value="<?= e($person['street'] ?? '') ?>">
            </label>
            <label>
                <span>PLZ</span>
                <input type="text" name="zip" value="<?= e($person['zip'] ?? '') ?>">
            </label>
            <label>
                <span>Ort</span>
                <input type="text" name="city" value="<?= e($person['city'] ?? '') ?>">
            </label>
            <label class="check-label">
                <input type="checkbox" name="is_active" value="1" <?= (int) ($person['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Person ist aktiv</span>
            </label>
        </div>
    </section>

    <section class="card form-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Lagerjahr</p>
                <h2>Teilnehmer- und Mitarbeiterstatus</h2>
            </div>
            <?php if ($activeCampYear !== null): ?><span class="category-tag category-tag--info"><?= e($activeCampYear['name']) ?></span><?php endif; ?>
        </div>
        <div class="form-grid">
            <label>
                <span>Lagerjahr</span>
                <select name="camp_year_id">
                    <option value="">Kein Lagerjahr</option>
                    <?php foreach ($campYears as $campYear): ?>
                        <option value="<?= e($campYear['id']) ?>" <?= (int) ($selectedCampYearId ?? 0) === (int) $campYear['id'] ? 'selected' : '' ?>><?= e($campYear['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="check-label">
                <input type="checkbox" name="is_participant" value="1" <?= $isParticipant ? 'checked' : '' ?>>
                <span>Teilnehmer in diesem Lagerjahr</span>
            </label>
            <label class="check-label">
                <input type="checkbox" name="is_staff" value="1" <?= $isStaff ? 'checked' : '' ?>>
                <span>Mitarbeiter in diesem Lagerjahr</span>
            </label>
            <label>
                <span>Teilnehmerstatus</span>
                <select name="participant_status">
                    <?php foreach (['angemeldet' => 'Angemeldet', 'warteliste' => 'Warteliste', 'abgemeldet' => 'Abgemeldet', 'abgeschlossen' => 'Abgeschlossen'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($person['participant_status'] ?? 'angemeldet') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Mitarbeiterstatus</span>
                <select name="staff_status">
                    <?php foreach (['aktiv' => 'Aktiv', 'inaktiv' => 'Inaktiv', 'angefragt' => 'Angefragt'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($person['staff_status'] ?? 'aktiv') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Orden/Zelt</span>
                <select name="order_id">
                    <option value="">Nicht zugeordnet</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?= e($order['id']) ?>" <?= (int) ($person['order_id'] ?? 0) === (int) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Rang</span>
                <select name="rank_level_id">
                    <option value="">Freier Text</option>
                    <?php foreach ($rankLevels as $rank): ?>
                        <option value="<?= e($rank['id']) ?>" <?= (int) ($person['rank_level_id'] ?? 0) === (int) $rank['id'] ? 'selected' : '' ?>><?= e($rank['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="rank_label" value="<?= e($person['rank_label'] ?? '') ?>" placeholder="freier Rang, falls nötig">
            </label>
            <label>
                <span>Rang nächstes Jahr</span>
                <select name="next_rank_level_id">
                    <option value="">Noch offen</option>
                    <?php foreach ($rankLevels as $rank): ?>
                        <option value="<?= e($rank['id']) ?>" <?= (int) ($person['next_rank_level_id'] ?? 0) === (int) $rank['id'] ? 'selected' : '' ?>><?= e($rank['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="next_rank_label" value="<?= e($person['next_rank_label'] ?? '') ?>" placeholder="freier Folgerang, falls nötig">
            </label>
            <label>
                <span>Aufstiegsstatus</span>
                <select name="promotion_status">
                    <?php foreach (['offen' => 'Offen', 'vorgeschlagen' => 'Vorgeschlagen', 'bestaetigt' => 'Bestätigt', 'abgelehnt' => 'Abgelehnt'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($person['promotion_status'] ?? 'offen') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Aufstiegsnotiz</span>
                <input type="text" name="promotion_note" value="<?= e($person['promotion_note'] ?? '') ?>" placeholder="optional">
            </label>
        </div>
        <input type="hidden" name="type_hint" value="<?= e($person['type_hint'] ?? 'mitarbeiter') ?>">
    </section>

    <section class="card form-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Kontakt</p>
                <h2>Notfallkontakt</h2>
            </div>
            <span class="status-chip status-chip--offen">sensibel</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Notfallkontakt Name</span>
                <input type="text" name="emergency_contact_name" value="<?= e($person['emergency_contact_name'] ?? '') ?>">
            </label>
            <label>
                <span>Notfallkontakt Telefon</span>
                <input type="text" name="emergency_contact_phone" value="<?= e($person['emergency_contact_phone'] ?? '') ?>">
            </label>
            <label>
                <span>Weitere Kontaktperson</span>
                <input type="text" name="guardian_name" value="<?= e($guardian['name'] ?? $person['guardian_name'] ?? '') ?>">
            </label>
            <label>
                <span>Beziehung</span>
                <input type="text" name="guardian_relation_label" value="<?= e($guardian['relation_label'] ?? $person['guardian_relation_label'] ?? '') ?>" placeholder="Mutter, Vater, ...">
            </label>
            <label>
                <span>Telefon weiterer Kontakt</span>
                <input type="text" name="guardian_phone" value="<?= e($guardian['phone'] ?? $person['guardian_phone'] ?? '') ?>">
            </label>
            <label>
                <span>E-Mail weiterer Kontakt</span>
                <input type="email" name="guardian_email" value="<?= e($guardian['email'] ?? $person['guardian_email'] ?? '') ?>">
            </label>
            <label class="form-wide">
                <span>Adresse weiterer Kontakt</span>
                <textarea name="guardian_address_text" rows="2"><?= e($guardian['address_text'] ?? $person['guardian_address_text'] ?? '') ?></textarea>
            </label>
        </div>
    </section>

    <section class="card form-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Hinweise</p>
                <h2>Essen, Allergien und Medizin</h2>
            </div>
            <span class="status-chip status-chip--offen">geschützt</span>
        </div>
        <div class="form-grid">
            <label class="form-wide">
                <span>Essenshinweise</span>
                <textarea name="food_notes" rows="3" placeholder="z. B. vegetarisch, kein Schwein, Besonderheiten"><?= e($person['food_notes'] ?? '') ?></textarea>
            </label>
            <label class="form-wide">
                <span>Allergien</span>
                <textarea name="allergy_notes" rows="3"><?= e($person['allergy_notes'] ?? '') ?></textarea>
            </label>
            <label class="form-wide">
                <span>Medizinische Hinweise</span>
                <textarea name="medical_notes" rows="3"><?= e($person['medical_notes'] ?? '') ?></textarea>
            </label>
            <label class="form-wide">
                <span>Interne Bemerkungen</span>
                <textarea name="internal_notes" rows="3"><?= e($person['internal_notes'] ?? '') ?></textarea>
            </label>
        </div>
    </section>

    <section class="card form-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Login</p>
                <h2>PIN und Rollen</h2>
            </div>
            <span class="category-tag category-tag--info">Dropdown + PIN</span>
        </div>
        <div class="form-grid">
            <label class="check-label">
                <input type="checkbox" name="is_login_enabled" value="1" <?= (int) ($person['is_login_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span>Login aktivieren</span>
            </label>
            <label>
                <span><?= $isEdit ? 'Neue PIN setzen' : 'PIN' ?></span>
                <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="4 bis 6 Ziffern">
            </label>
        </div>

        <div class="role-select">
            <span class="field-title">Rollen</span>
            <div class="role-grid">
                <?php foreach ($roles as $role): ?>
                    <label class="check-label role-option">
                        <input type="checkbox" name="roles[]" value="<?= e($role['key_name']) ?>" <?= in_array($role['key_name'], $assignedRoles, true) ? 'checked' : '' ?>>
                        <span><?= e($role['label']) ?> <small><?= e($role['key_name']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="form-actions sticky-actions">
        <button type="submit" class="button button--primary">Speichern</button>
        <a class="button button--ghost" href="/admin/personen">Abbrechen</a>
    </div>
</form>
