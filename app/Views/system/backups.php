<?php
use App\Support\Auth;
use App\Support\Csrf;
?>
<section class="page-header-card">
    <div>
        <p class="eyebrow">System</p>
        <h1>BACKUPS</h1>
        <p class="muted">Sichert Datenbank und Dateien in den geschützten Storage. Downloads laufen nur über diese Rechteprüfung.</p>
    </div>
    <div class="actions-row">
        <?php if (Auth::can('webdav.manage')): ?>
            <a class="button button--ghost" href="/system/webdav">WebDAV einrichten</a>
        <?php endif; ?>
        <form method="post" action="/system/backups/start">
            <?= Csrf::input() ?>
            <button class="button button--primary" type="submit">Backup starten</button>
        </form>
    </div>
</section>

<section class="card">
    <p class="eyebrow">Backup-Läufe</p>
    <h2>LETZTE BACKUPS</h2>
    <?php if ($runs === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon">B</div>
            <p>Noch keine Backups vorhanden.</p>
            <p class="muted">Starte ein manuelles Backup oder nutze die geplanten Aufgaben.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Start</th>
                        <th>Typ</th>
                        <th>Status</th>
                        <th>Größe</th>
                        <th>Dauer</th>
                        <th>WebDAV</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><?= e($run['started_at'] ?? '') ?></td>
                            <td><?= e($run['backup_type'] ?? '') ?></td>
                            <td><span class="status-chip status-chip--<?= e((string) ($run['status'] ?? 'offen')) ?>"><?= e($run['status'] ?? '') ?></span></td>
                            <td><?= e(isset($run['file_size']) && $run['file_size'] !== null ? number_format(((int) $run['file_size']) / 1024 / 1024, 2, ',', '.') . ' MB' : '—') ?></td>
                            <td><?= e(isset($run['duration_ms']) && $run['duration_ms'] !== null ? ((int) $run['duration_ms']) . ' ms' : '—') ?></td>
                            <td><?= e($run['webdav_status'] ?? 'not_configured') ?></td>
                            <td>
                                <?php if (($run['status'] ?? '') === 'ok' && !empty($run['file_path']) && Auth::can('backups.download')): ?>
                                    <a class="button button--ghost button--small" href="/system/backups/download?id=<?= (int) $run['id'] ?>">Download</a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($run['last_error'])): ?>
                            <tr>
                                <td colspan="7"><span class="text-danger"><?= e($run['last_error']) ?></span></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
