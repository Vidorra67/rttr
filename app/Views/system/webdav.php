<?php
use App\Support\Csrf;

$settings = is_array($settings ?? null) ? $settings : [];
$runs = is_array($runs ?? null) ? $runs : [];
?>
<section class="page-header-card">
    <div>
        <p class="eyebrow">System</p>
        <h1>WEBDAV</h1>
        <p class="muted">WebDAV ist eine zusätzliche Ablage für Backups. Die lokale Backupdatei im geschützten Storage bleibt die technische Wahrheit.</p>
    </div>
</section>

<section class="grid-two">
    <article class="card">
        <p class="eyebrow">Einstellungen</p>
        <h2>WEBDAV EINRICHTEN</h2>
        <form method="post" action="/system/webdav/save" class="form-grid">
            <?= Csrf::input() ?>
            <label class="check-row">
                <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                <span>WebDAV-Sync nach erfolgreichen Backups aktivieren</span>
            </label>

            <label>
                <span>WebDAV-URL</span>
                <input class="input" type="url" name="base_url" value="<?= e($settings['base_url'] ?? '') ?>" placeholder="https://cloud.example.de/remote.php/dav/files/benutzer">
                <small class="muted">Die URL muss auf den WebDAV-Basisordner zeigen. Unterordner werden automatisch angelegt.</small>
            </label>

            <label>
                <span>Benutzername</span>
                <input class="input" type="text" name="username" value="<?= e($settings['username'] ?? '') ?>" autocomplete="username">
            </label>

            <label>
                <span>Passwort oder App-Passwort</span>
                <input class="input" type="password" name="password" value="" autocomplete="new-password" placeholder="<?= !empty($settings['password_set']) ? 'Gespeichert. Leer lassen, um beizubehalten.' : 'Passwort eintragen' ?>">
                <small class="muted">Das Passwort wird nicht im Log ausgegeben. Für Nextcloud besser ein App-Passwort nutzen.</small>
            </label>

            <label>
                <span>Zielordner</span>
                <input class="input" type="text" name="remote_base_path" value="<?= e($settings['remote_base_path'] ?? 'ritterlager/backups') ?>" placeholder="ritterlager/backups">
            </label>

            <label>
                <span>Timeout in Sekunden</span>
                <input class="input" type="number" name="timeout_seconds" value="<?= e((string) ($settings['timeout_seconds'] ?? 45)) ?>" min="5" max="300">
            </label>

            <div class="actions-row">
                <button class="button button--primary" type="submit">Einstellungen speichern</button>
            </div>
        </form>
    </article>

    <article class="card">
        <p class="eyebrow">Aktionen</p>
        <h2>TEST & SYNC</h2>
        <dl class="status-list status-list--compact">
            <div><dt>Status</dt><dd><span class="status-chip <?= !empty($settings['enabled']) ? 'status-chip--ok' : 'status-chip--muted' ?>"><?= !empty($settings['enabled']) ? 'aktiv' : 'inaktiv' ?></span></dd></div>
            <div><dt>Passwort</dt><dd><?= !empty($settings['password_set']) ? 'gespeichert' : 'nicht gespeichert' ?></dd></div>
            <div><dt>Ziel</dt><dd><code><?= e(trim((string) ($settings['remote_base_path'] ?? 'ritterlager/backups'), '/')) ?></code></dd></div>
        </dl>
        <div class="notice-box notice-box--info">
            <strong>Hinweis:</strong> Der Sync läuft aus einer lokal vollständig erzeugten Datei. Pro Upload wird ein neues Filehandle geöffnet, damit typische Stream-Fehler bei WebDAV vermieden werden.
        </div>
        <div class="actions-row">
            <form method="post" action="/system/webdav/test">
                <?= Csrf::input() ?>
                <button class="button button--ghost" type="submit">WebDAV testen</button>
            </form>
            <form method="post" action="/system/webdav/sync-latest">
                <?= Csrf::input() ?>
                <button class="button button--primary" type="submit">Letztes Backup senden</button>
            </form>
        </div>
    </article>
</section>

<section class="card">
    <p class="eyebrow">Protokoll</p>
    <h2>WEBDAV-SYNC-LÄUFE</h2>
    <?php if ($runs === []): ?>
        <div class="empty-state"><div class="empty-state__icon">W</div><p>Noch keine WebDAV-Syncs vorhanden.</p><p class="muted">Starte einen Test oder erstelle ein Backup mit aktivem WebDAV-Sync.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Zeit</th>
                        <th>Quelle</th>
                        <th>Status</th>
                        <th>Lokal</th>
                        <th>Remote</th>
                        <th>Fehler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><?= e($run['created_at'] ?? '') ?></td>
                            <td><?= e(($run['source_type'] ?? '') . (!empty($run['source_id']) ? ' #' . $run['source_id'] : '')) ?></td>
                            <td><span class="status-chip status-chip--<?= e((string) ($run['status'] ?? 'skipped')) ?>"><?= e($run['status'] ?? '') ?></span></td>
                            <td><code><?= e($run['local_path'] ?? '') ?></code></td>
                            <td><code><?= e($run['remote_path'] ?? '') ?></code></td>
                            <td><?= !empty($run['last_error']) ? '<span class="text-danger">' . e($run['last_error']) . '</span>' : '<span class="muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
