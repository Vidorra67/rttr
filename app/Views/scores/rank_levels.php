<?php
/** @var array|null $activeCampYear */
/** @var array $rankLevels */
/** @var bool $canManage */
use App\Support\Auth;
$rankLevels = $rankLevels ?? [];
$canManage = $canManage ?? false;
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Auswertung</p>
        <h2>Rangstufen</h2>
        <p class="muted">Pflege die feste Rangfolge. Standard ist: Knappe, Ritter, Freiherr, Graf, Markgraf, Landgraf, Fürst, Herzog, Großherzog.</p>
    </div>
    <div class="management-actions">
        <a class="button button--ghost" href="/admin/auswertung">Zur Auswertung</a>
        <?php if ($canManage): ?>
            <a class="button button--primary" href="/admin/rangstufen/neu">Rangstufe anlegen</a>
        <?php endif; ?>
    </div>
</section>
<?php if (($activeCampYear ?? null) === null): ?>
    <article class="card empty-state"><div class="empty-icon">R</div><h2>Kein aktives Lagerjahr</h2><p class="muted">Rangstufen benötigen ein aktives Lagerjahr.</p></article>
<?php elseif ($rankLevels === []): ?>
    <article class="card empty-state"><div class="empty-icon">R</div><h2>Noch keine Rangstufen</h2><p class="muted">Lege die ersten Rangstufen an, zum Beispiel Knappe, Ritter oder Freiherr.</p></article>
<?php else: ?>
    <section class="card-list">
        <?php foreach ($rankLevels as $rank): ?>
            <article class="card list-card">
                <div>
                    <p class="eyebrow">Sortierung <?= e($rank['sort_order']) ?></p>
                    <h3><?= e($rank['label']) ?></h3>
                    <p class="muted">Schlüssel: <?= e($rank['key_name']) ?><?= !empty($rank['next_rank_key']) ? ' · nächster Rang: ' . e($rank['next_rank_key']) : '' ?><?= $rank['promotion_points_required'] !== null ? ' · Schwelle: ' . e(number_format((float) $rank['promotion_points_required'], 0, ',', '.')) . ' Punkte' : '' ?></p>
                    <?php if (!empty($rank['promotion_text'])): ?><p class="muted"><?= e($rank['promotion_text']) ?> · erreichte Ränge bleiben im Folgejahr erhalten</p><?php endif; ?>
                </div>
                <?php if ($canManage): ?><a class="button button--ghost" href="/admin/rangstufen/bearbeiten?id=<?= e($rank['id']) ?>">Bearbeiten</a><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
