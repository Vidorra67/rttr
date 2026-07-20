<?php
/** @var array $campYears */
use App\Support\Auth;
use App\Support\Csrf;
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Lagerverwaltung</p>
        <h2>Lagerjahre</h2>
    </div>
    <?php if (Auth::can('camp_years.manage')): ?>
        <a class="button button--primary" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
    <?php endif; ?>
</section>

<?php if ($campYears === []): ?>
    <section class="card empty-state">
        <div class="empty-icon">+</div>
        <h2>Noch kein Lagerjahr</h2>
        <p>Lege zuerst ein Lagerjahr mit Start- und Enddatum an. Daraus entstehen die Lagertage für Übersicht, Programm, Essen und Dienste.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--primary" href="/admin/lagerjahre/neu">Erstes Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card-list">
        <?php foreach ($campYears as $campYear): ?>
            <?php
            $starts = date('d.m.Y', strtotime((string) $campYear['starts_on']));
            $ends = date('d.m.Y', strtotime((string) $campYear['ends_on']));
            $isActive = (int) $campYear['is_active'] === 1;
            ?>
            <article class="card management-card">
                <div>
                    <div class="chip-row">
                        <span class="status-chip <?= $isActive ? 'status-chip--ok' : 'status-chip--muted' ?>"><?= $isActive ? 'Aktives Lagerjahr' : 'inaktiv' ?></span>
                    </div>
                    <h2><?= e($campYear['name']) ?></h2>
                    <p class="muted"><?= e($campYear['location_name'] ?: 'Ort offen') ?> · <?= e($starts) ?> bis <?= e($ends) ?></p>
                </div>
                <?php if (Auth::can('camp_years.manage')): ?>
                    <div class="management-actions">
                        <a class="button button--ghost" href="/admin/lagerjahre/bearbeiten?id=<?= e($campYear['id']) ?>">Bearbeiten</a>
                        <a class="button button--ghost" href="/admin/lagerjahre/uebernahme?to=<?= e($campYear['id']) ?>">Teilnehmer übernehmen</a>
                        <?php if (!$isActive): ?>
                            <form method="post" action="/admin/lagerjahre/aktiv" class="inline-form">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= e($campYear['id']) ?>">
                                <button class="button button--ghost" type="submit">Aktiv setzen</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
