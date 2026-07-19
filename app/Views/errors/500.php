<section class="card">
    <p class="eyebrow">Fehler 500</p>
    <h1>TECHNISCHER FEHLER</h1>
    <p>Die Aktion konnte nicht ausgeführt werden. Details wurden protokolliert.</p>
    <?php if (!empty($requestId)): ?>
        <p class="muted">Fehler-ID: <?= e($requestId) ?></p>
    <?php endif; ?>
</section>
