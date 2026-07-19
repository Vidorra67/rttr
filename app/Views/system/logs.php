<section class="page-header-card">
    <div>
        <p class="eyebrow">System</p>
        <h1>LOGS</h1>
        <p class="muted">Technische Logdateien aus dem geschützten Storage. Secrets werden durch den Logger gefiltert.</p>
    </div>
</section>

<section class="card">
    <p class="eyebrow">Dateien</p>
    <h2>LOGDATEIEN</h2>
    <?php if ($files === []): ?>
        <div class="empty-state"><div class="empty-state__icon">L</div><p>Noch keine Logdateien vorhanden.</p></div>
    <?php else: ?>
        <div class="chip-row">
            <?php foreach ($files as $file): ?>
                <a class="day-tab <?= $selected === $file['name'] ? 'is-active' : '' ?>" href="/system/logs?file=<?= e(rawurlencode($file['name'])) ?>">
                    <?= e($file['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($selected !== ''): ?>
<section class="card">
    <p class="eyebrow">Auszug</p>
    <h2><?= e($selected) ?></h2>
    <?php if ($lines === []): ?>
        <div class="empty-state"><div class="empty-state__icon">0</div><p>Keine lesbaren Einträge gefunden.</p></div>
    <?php else: ?>
        <pre class="log-view"><?php foreach ($lines as $line): ?><?= e($line) . "\n" ?><?php endforeach; ?></pre>
    <?php endif; ?>
</section>
<?php endif; ?>
