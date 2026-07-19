<?php
/** @var array $loginOptions */
/** @var string|null $loadError */
?>
<div class="auth-page">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand">
            <?php require base_path('app/Views/partials/badge.php'); ?>
            <div>
                <p class="eyebrow">Förderverein Ritterlager e.V.</p>
                <h1 id="login-title">Ritterlager Verwaltung</h1>
            </div>
        </div>
        <p class="login-copy">Wähle deinen Namen aus und melde dich mit deinem 4 bis 6 stelligen Code an.</p>

        <?php require base_path('app/Views/partials/flash.php'); ?>

        <?php if ($loadError !== null): ?>
            <div class="flash flash--error"><?= e($loadError) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" class="login-form js-pin-form" autocomplete="off">
            <?= \App\Support\Csrf::input() ?>
            <label for="person_id">Person</label>
            <select id="person_id" name="person_id" required>
                <option value="">Bitte auswählen</option>
                <?php foreach ($loginOptions as $option): ?>
                    <option value="<?= e($option['person_id']) ?>"><?= e($option['display_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="pin_display">Code</label>
            <input id="pin_display" class="pin-display" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" readonly aria-describedby="pin-help">
            <input id="pin" name="pin" type="hidden" value="">
            <p id="pin-help" class="field-help">Der Code wird nicht im Klartext angezeigt.</p>

            <div class="pin-pad" aria-label="PIN eingeben">
                <?php foreach ([1,2,3,4,5,6,7,8,9] as $digit): ?>
                    <button type="button" data-pin-digit="<?= $digit ?>"><?= $digit ?></button>
                <?php endforeach; ?>
                <button type="button" data-pin-clear aria-label="Letzte Ziffer löschen">⌫</button>
                <button type="button" data-pin-digit="0">0</button>
                <button type="button" data-pin-reset>Leeren</button>
            </div>

            <button type="submit" class="button button--primary login-submit">Anmelden</button>
        </form>
    </section>
</div>
