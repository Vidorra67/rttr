<?php
/** @var array $dutyTypes */
$dutyTypes = $dutyTypes ?? [];
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Dienste</p>
        <h2>Dienstarten</h2>
        <p class="muted">Pflege die Arten von Aufgaben, aus denen die tägliche Dienstliste entsteht.</p>
    </div>
    <div class="management-actions">
        <a class="button button--ghost" href="/dienste">Zur Dienstliste</a>
        <a class="button button--primary" href="/admin/dienstarten/neu">Dienstart anlegen</a>
    </div>
</section>

<?php if ($dutyTypes === []): ?>
    <article class="card empty-state">
        <div class="empty-icon" aria-hidden="true">◇</div>
        <h2>Noch keine Dienstarten</h2>
        <p class="muted">Lege zuerst Küchendienst, Platzdienst oder Nachtwache an.</p>
    </article>
<?php else: ?>
    <section class="card table-card">
        <div class="responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Schlüssel</th>
                        <th>Zuweisung</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dutyTypes as $type): ?>
                        <tr>
                            <td><strong><?= e($type['label']) ?></strong></td>
                            <td><?= e($type['key_name']) ?></td>
                            <td><?= e($type['assignment_mode']) ?></td>
                            <td><?= (int) $type['is_active'] === 1 ? '<span class="status-chip status-chip--ok">aktiv</span>' : '<span class="status-chip status-chip--muted">inaktiv</span>' ?></td>
                            <td><a class="button button--ghost" href="/admin/dienstarten/bearbeiten?id=<?= e($type['id']) ?>">Bearbeiten</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
