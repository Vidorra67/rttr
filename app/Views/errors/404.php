<section class="card">
    <p class="eyebrow">Fehler 404</p>
    <h1>SEITE NICHT GEFUNDEN</h1>
    <p>Die angeforderte Seite wurde nicht gefunden.</p>
    <?php if (!empty($path)): ?>
        <p class="muted">Pfad: <?= e($path) ?></p>
    <?php endif; ?>
</section>
