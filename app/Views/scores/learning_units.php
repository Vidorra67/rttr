<?php
/** @var array|null $activeCampYear */
/** @var array $learningUnits */
/** @var bool $canManage */
$learningUnits = $learningUnits ?? [];
$canManage = $canManage ?? false;
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Auswertung</p>
        <h2>Lerneinheiten</h2>
        <p class="muted">Pflege Lerneinheiten wie Knoten, Natur, Ritterkunde, Feuer, Meteorologie oder Navigation.</p>
    </div>
    <div class="management-actions">
        <a class="button button--ghost" href="/admin/auswertung">Zur Auswertung</a>
        <?php if ($canManage): ?><a class="button button--primary" href="/admin/lerneinheiten/neu">Lerneinheit anlegen</a><?php endif; ?>
    </div>
</section>
<?php if (($activeCampYear ?? null) === null): ?>
    <article class="card empty-state"><div class="empty-icon">L</div><h2>Kein aktives Lagerjahr</h2><p class="muted">Lerneinheiten benötigen ein aktives Lagerjahr.</p></article>
<?php elseif ($learningUnits === []): ?>
    <article class="card empty-state"><div class="empty-icon">L</div><h2>Noch keine Lerneinheiten</h2><p class="muted">Lege die erste Lerneinheit an, damit Prüfungsergebnisse erfasst werden können.</p></article>
<?php else: ?>
    <section class="card-list">
        <?php foreach ($learningUnits as $unit): ?>
            <article class="card list-card">
                <div>
                    <p class="eyebrow">Sortierung <?= e($unit['sort_order']) ?></p>
                    <h3><?= e($unit['title']) ?></h3>
                    <p class="muted"><?= e($unit['responsible_label'] ?: 'Verantwortliche offen') ?></p>
                </div>
                <span class="category-tag category-tag--<?= e($unit['category_key'] === 'bibelarbeit' ? 'bibel' : ($unit['category_key'] === 'spiel' ? 'spiel' : ($unit['category_key'] === 'wettbewerb' ? 'info' : 'lernen'))) ?>"><?= e($unit['category_key']) ?></span>
                <?php if ($canManage): ?>
                    <div class="management-actions">
                        <a class="button button--ghost" href="/admin/lerneinheiten/bearbeiten?id=<?= e($unit['id']) ?>">Bearbeiten</a>
                        <form method="post" action="/admin/lerneinheiten/deaktivieren" class="inline-form">
                            <?= \App\Support\Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= e($unit['id']) ?>">
                            <button type="submit" class="button button--ghost button--danger">Deaktivieren</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
