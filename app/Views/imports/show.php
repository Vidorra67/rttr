<?php
/** @var array $run */
/** @var array $profiles */
/** @var array $templates */
use App\Support\Csrf;

$run = $run ?? [];
$profiles = $profiles ?? [];
$templates = $templates ?? [];
$runKey = (string) ($run['import_key'] ?? '');
$templateKey = str_starts_with($runKey, 'template:') ? substr($runKey, 9) : null;
$runLabel = $templateKey !== null ? ($templates[$templateKey]['label'] ?? $runKey) : ($profiles[$runKey] ?? $runKey);
$summary = $run['summary'] ?? [];
$rows = $summary['preview_rows'] ?? [];
$warnings = $summary['warnings'] ?? [];
$errors = $run['errors'] ?? [];
$status = (string) ($run['status'] ?? 'uploaded');
$canExecute = in_array($status, ['uploaded', 'preview', 'partial', 'failed'], true);
$statusClass = in_array($status, ['ok', 'preview'], true) ? 'status-chip--ok' : ($status === 'failed' ? 'status-chip--offen' : 'status-chip--muted');
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Importvorschau</p>
        <h2><?= e($runLabel) ?></h2>
        <p class="muted"><?= e($run['original_name'] ?? 'Importdatei') ?> · <?= e((string) ($run['file_ext'] ?? '')) ?> · <?= e(number_format(((int) ($run['file_size'] ?? 0)) / 1024, 1, ',', '.')) ?> KB</p>
    </div>
    <div class="management-actions">
        <a class="button button--ghost" href="/admin/importe">Zurück</a>
        <?php if ($canExecute): ?>
            <form method="post" action="/admin/importe/ausfuehren" class="inline-form">
                <?= Csrf::input() ?>
                <input type="hidden" name="id" value="<?= e($run['id']) ?>">
                <button class="button button--primary" type="submit">Import ausführen</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="score-summary-grid import-summary-grid">
    <article class="card stat-card">
        <span class="stat-label">Status</span>
        <strong><span class="status-chip <?= e($statusClass) ?>"><?= e($status) ?></span></strong>
    </article>
    <article class="card stat-card">
        <span class="stat-label">Zeilen erkannt</span>
        <strong><?= e((string) ($run['total_rows'] ?? $summary['total_rows'] ?? 0)) ?></strong>
    </article>
    <article class="card stat-card">
        <span class="stat-label">Übernommen</span>
        <strong><?= e((string) ($run['imported_rows'] ?? 0)) ?></strong>
    </article>
    <article class="card stat-card">
        <span class="stat-label">Fehler</span>
        <strong><?= e((string) ($run['error_rows'] ?? count($errors))) ?></strong>
    </article>
</section>

<?php if ($warnings !== []): ?>
    <section class="card import-warning-card">
        <p class="eyebrow">Hinweise</p>
        <h2>Importhinweise</h2>
        <?php foreach ($warnings as $warning): ?>
            <p class="muted"><?= e($warning['error_text'] ?? 'Hinweis') ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="card table-card">
    <div class="section-head section-head--compact">
        <div>
            <p class="eyebrow">Vorschau</p>
            <h2>Erkannte Daten</h2>
        </div>
        <span class="category-tag category-tag--info">max. 30 Zeilen</span>
    </div>

    <?php if ($rows === []): ?>
        <div class="empty-state empty-state--compact">
            <div class="empty-icon" aria-hidden="true">I</div>
            <h2>Keine strukturierten Zeilen</h2>
            <p class="muted">Die Datei wurde gespeichert. Eine automatische Vorschau war mit der aktuellen Umgebung nicht möglich.</p>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_slice((array) $row, 0, 12) as $cell): ?>
                                <td><?= e($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card table-card">
    <div class="section-head section-head--compact">
        <div>
            <p class="eyebrow">Fehlerprotokoll</p>
            <h2>Importfehler und übersprungene Zeilen</h2>
        </div>
        <span class="status-chip <?= $errors === [] ? 'status-chip--ok' : 'status-chip--offen' ?>"><?= $errors === [] ? 'keine Fehler' : e(count($errors)) . ' Einträge' ?></span>
    </div>

    <?php if ($errors === []): ?>
        <p class="muted">Für diesen Importlauf sind keine Fehler protokolliert.</p>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Zeile</th>
                        <th>Feld</th>
                        <th>Hinweis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($errors as $error): ?>
                        <tr>
                            <td><?= e($error['source_row_number'] ?? '-') ?></td>
                            <td><?= e($error['field_name'] ?? '-') ?></td>
                            <td><?= e($error['error_text'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
