<?php
/** @var array|null $activeCampYear */
/** @var array $profiles */
/** @var array $runs */
/** @var array $templates */
use App\Support\Csrf;

$activeCampYear = $activeCampYear ?? null;
$profiles = $profiles ?? [];
$runs = $runs ?? [];
$templates = $templates ?? [];
$statusLabels = [
    'uploaded' => 'hochgeladen',
    'preview' => 'Vorschau',
    'running' => 'läuft',
    'ok' => 'ok',
    'failed' => 'Fehler',
    'partial' => 'teilweise',
];
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Importe</p>
        <h2>Dateien kontrolliert übernehmen</h2>
        <p class="muted">XLSX, ODS und DOCX werden geschützt gespeichert, geprüft und erst nach Vorschau ausgeführt.</p>
    </div>
</section>

<?php if ($templates !== []): ?>
        <section class="card import-template-card">
            <div class="section-head section-head--compact">
                <div>
                    <p class="eyebrow">Mitgelieferte Vorlagen</p>
                    <h2>Startdaten übernehmen</h2>
                    <p class="muted">Diese Importe nutzen die im Paket geschützten Quelldateien aus <code>storage/import_sources</code>. Bestehende Datensätze werden nicht blind überschrieben.</p>
                </div>
                <span class="status-chip status-chip--ok">bereit</span>
            </div>
            <div class="template-grid">
                <?php foreach ($templates as $key => $template): ?>
                    <article class="template-card">
                        <span class="material-symbols-rounded template-icon" aria-hidden="true">upload_file</span>
                        <div>
                            <h3><?= e($template['label'] ?? $key) ?></h3>
                            <p class="muted"><?= e($template['description'] ?? '') ?></p>
                            <small><?= e($template['source'] ?? '') ?></small>
                        </div>
                        <form method="post" action="/admin/importe/vorlage">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="template_key" value="<?= e($key) ?>">
                            <button class="button button--ghost" type="submit">Import starten</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

<?php if ($activeCampYear === null): ?>
    <section class="hero-card">
        <p class="eyebrow">Importe</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Setze zuerst ein aktives Lagerjahr. Danach können Importdateien eindeutig zugeordnet werden.</p>
        <a class="button button--hero" href="/admin/lagerjahre">Lagerjahre öffnen</a>
    </section>
<?php else: ?>

    <section class="import-grid">
        <article class="card import-upload-card">
            <div class="section-head section-head--compact">
                <div>
                    <p class="eyebrow">Upload</p>
                    <h2>Importdatei hochladen</h2>
                </div>
                <span class="status-chip status-chip--ok">geschützt</span>
            </div>
            <form method="post" action="/admin/importe/vorschau" enctype="multipart/form-data" class="form-grid">
                <?= Csrf::input() ?>
                <label>
                    Importprofil
                    <select name="import_key" required>
                        <?php foreach ($profiles as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Datei
                    <input type="file" name="import_file" accept=".xlsx,.ods,.docx" required>
                    <span class="field-hint">Erlaubt sind XLSX, ODS und DOCX bis 10 MB. Speicherung erfolgt in storage/imports.</span>
                </label>
                <div class="button-row">
                    <button class="button button--primary" type="submit">Vorschau erstellen</button>
                </div>
            </form>
        </article>

        <article class="card import-help-card">
            <p class="eyebrow">Regeln</p>
            <h2>Kein blindes Überschreiben</h2>
            <p class="muted">Der Import legt nur eindeutig erkennbare neue Datensätze an. Bestehende Personen, Orden/Zelte, Programmpunkte, Mahlzeiten, Dienstarten und Lerneinheiten werden nicht überschrieben.</p>
            <div class="chip-row">
                <span class="category-tag category-tag--info">Vorschau</span>
                <span class="category-tag category-tag--lernen">Protokoll</span>
                <span class="category-tag category-tag--spiel">Audit</span>
            </div>
        </article>
    </section>

    <section class="card table-card import-runs-card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Importläufe</p>
                <h2>Bisherige Importe</h2>
            </div>
            <span class="category-tag category-tag--info"><?= e(count($runs)) ?> Läufe</span>
        </div>

        <?php if ($runs === []): ?>
            <div class="empty-state empty-state--compact">
                <div class="empty-icon" aria-hidden="true"><span class="material-symbols-rounded">upload_file</span></div>
                <h2>Noch kein Import</h2>
                <p class="muted">Lade die erste Datei hoch. Danach erscheint hier der Importlauf mit Vorschau und Ergebnis.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Profil</th>
                            <th>Datei</th>
                            <th>Status</th>
                            <th>Zeilen</th>
                            <th>Ergebnis</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runs as $run): ?>
                            <?php $status = (string) ($run['status'] ?? 'uploaded'); ?>
                            <tr>
                                <td><?= e(date('d.m.Y H:i', strtotime((string) $run['created_at']))) ?></td>
                                <td><?php $runKey = (string) ($run['import_key'] ?? ''); $templateKey = str_starts_with($runKey, 'template:') ? substr($runKey, 9) : null; ?><?= e($templateKey !== null ? ($templates[$templateKey]['label'] ?? $runKey) : ($profiles[$runKey] ?? $runKey)) ?></td>
                                <td><?= e($run['original_name'] ?? 'Datei') ?></td>
                                <td><span class="status-chip <?= in_array($status, ['ok', 'preview'], true) ? 'status-chip--ok' : ($status === 'failed' ? 'status-chip--offen' : 'status-chip--muted') ?>"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                                <td><?= e((string) ($run['total_rows'] ?? 0)) ?></td>
                                <td><?= e((string) ($run['imported_rows'] ?? 0)) ?> übernommen, <?= e((string) ($run['skipped_rows'] ?? 0)) ?> übersprungen</td>
                                <td><a class="button button--ghost" href="/admin/importe/vorschau?id=<?= e($run['id']) ?>">Öffnen</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
