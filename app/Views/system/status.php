<section class="card">
    <p class="eyebrow">System</p>
    <h1>SYSTEMSTATUS</h1>
    <dl class="status-list">
        <?php foreach ($checks as $label => $value): ?>
            <div>
                <dt><?= e($label) ?></dt>
                <dd><?= e(is_bool($value) ? ($value ? 'ja' : 'nein') : $value) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>
