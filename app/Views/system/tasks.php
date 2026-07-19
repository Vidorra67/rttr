<?php
use App\Support\Csrf;
$lastCronUrl = $lastCronUrl ?? null;
?>
<section class="page-header-card">
    <div>
        <p class="eyebrow">System</p>
        <h1>GEPLANTE AUFGABEN</h1>
        <p class="muted">Feste Tasks mit Lock, Laufprotokoll und HTTP-Cron-Token. Es werden keine beliebigen Dateien per URL ausgeführt.</p>
    </div>
</section>

<?php if (is_string($lastCronUrl) && $lastCronUrl !== ''): ?>
    <section class="card card--mint">
        <p class="eyebrow">Einmalige Cron-URL</p>
        <h2>JETZT KOPIEREN</h2>
        <p class="copy-line"><code><?= e($lastCronUrl) ?></code></p>
        <p class="muted">Der Token wird nur gehasht gespeichert und danach nicht mehr vollständig angezeigt.</p>
    </section>
<?php endif; ?>

<section class="card">
    <p class="eyebrow">Cron</p>
    <h2>AUFGABEN</h2>
    <?php if ($tasks === []): ?>
        <div class="empty-state"><div class="empty-state__icon">C</div><p>Keine Aufgaben vorhanden. Bitte Migration ausführen.</p></div>
    <?php else: ?>
        <div class="task-grid">
            <?php foreach ($tasks as $task): ?>
                <?php $urlPlaceholder = ($baseUrl !== '' ? $baseUrl : '') . '/cron/run?task=' . rawurlencode((string) $task['task_key']) . '&token=TOKEN'; ?>
                <article class="card card--inner">
                    <div class="card-row card-row--between">
                        <div>
                            <p class="eyebrow"><?= e($task['task_key']) ?></p>
                            <h3><?= e($task['label']) ?></h3>
                            <p class="muted"><?= e($task['description'] ?? '') ?></p>
                        </div>
                        <span class="status-chip <?= (int) ($task['is_active'] ?? 0) === 1 ? 'status-chip--ok' : 'status-chip--offen' ?>"><?= (int) ($task['is_active'] ?? 0) === 1 ? 'aktiv' : 'inaktiv' ?></span>
                    </div>
                    <dl class="status-list status-list--compact">
                        <div><dt>Intervall</dt><dd><?= e($task['recommended_interval'] ?? '—') ?></dd></div>
                        <div><dt>Letzter Lauf</dt><dd><?= e($task['last_run_at'] ?? '—') ?></dd></div>
                        <div><dt>Status</dt><dd><?= e($task['last_status'] ?? '—') ?></dd></div>
                        <div><dt>Cron-URL</dt><dd><code><?= e($urlPlaceholder) ?></code></dd></div>
                    </dl>
                    <div class="actions-row">
                        <form method="post" action="/system/tasks/run">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                            <button class="button button--primary button--small" type="submit">Jetzt testen</button>
                        </form>
                        <form method="post" action="/system/tasks/token">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                            <button class="button button--ghost button--small" type="submit">Token regenerieren</button>
                        </form>
                        <form method="post" action="/system/tasks/toggle">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                            <input type="hidden" name="active" value="<?= (int) ($task['is_active'] ?? 0) === 1 ? 0 : 1 ?>">
                            <button class="button button--ghost button--small" type="submit"><?= (int) ($task['is_active'] ?? 0) === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <p class="eyebrow">Protokoll</p>
    <h2>LETZTE LÄUFE</h2>
    <?php if ($runs === []): ?>
        <div class="empty-state"><div class="empty-state__icon">L</div><p>Noch keine Cronläufe protokolliert.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Start</th><th>Task</th><th>Status</th><th>Trigger</th><th>Dauer</th><th>Ausgabe</th></tr></thead>
                <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td><?= e($run['started_at'] ?? '') ?></td>
                        <td><?= e($run['task_key'] ?? '') ?></td>
                        <td><span class="status-chip status-chip--<?= e((string) ($run['status'] ?? 'offen')) ?>"><?= e($run['status'] ?? '') ?></span></td>
                        <td><?= e($run['triggered_by'] ?? '') ?></td>
                        <td><?= e(isset($run['duration_ms']) ? ((int) $run['duration_ms']) . ' ms' : '—') ?></td>
                        <td><?= e($run['output_text'] ?: $run['error_text'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
