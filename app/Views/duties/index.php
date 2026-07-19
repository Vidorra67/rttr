<?php
/** @var array|null $activeCampYear */
/** @var string|null $activeDate */
/** @var array $dayTabs */
/** @var array $duties */
/** @var array|null $placeSuggestion */
/** @var array $statuses */
use App\Support\Auth;

$activeCampYear = $activeCampYear ?? null;
$activeDate = $activeDate ?? null;
$dayTabs = $dayTabs ?? [];
$duties = $duties ?? [];
$placeSuggestion = $placeSuggestion ?? null;
$statuses = $statuses ?? [];
$openCount = count(array_filter($duties, static fn (array $duty): bool => ($duty['status'] ?? '') === 'offen'));
?>

<?php if ($activeCampYear === null): ?>
    <section class="hero-card">
        <p class="eyebrow">Dienste</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Lege zuerst ein aktives Lagerjahr an. Danach kann die tägliche Aufgabenverteilung gepflegt werden.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--hero" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="section-head duty-head">
        <div>
            <p class="eyebrow">Diensteinteilung</p>
            <h2><?= e($activeCampYear['name']) ?></h2>
            <p class="muted">Tägliche Aufgaben für Mitarbeiter und Orden/Zelte. Platzdienst kann nach Orden/Zelt rotiert werden.</p>
        </div>
        <div class="management-actions">
            <?php if (Auth::can('duties.manage')): ?>
                <a class="button button--ghost" href="/admin/dienstarten">Dienstarten</a>
                <a class="button button--ghost" href="/dienste/neu?tag=<?= e((string) $activeDate) ?>&amp;typ=platzdienst">Platzdienst vorschlagen</a>
                <a class="button button--primary" href="/dienste/neu?tag=<?= e((string) $activeDate) ?>">Dienst anlegen</a>
            <?php endif; ?>
        </div>
    </section>

    <?php $days = $dayTabs; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>

    <article class="card duty-overview-card">
        <div>
            <p class="eyebrow">Status heute</p>
            <h2><?= e($openCount) ?> Dienste offen</h2>
            <?php if (is_array($placeSuggestion)): ?>
                <p class="muted">Nächster vorgeschlagener Platzdienst: <?= e($placeSuggestion['name']) ?>.</p>
            <?php else: ?>
                <p class="muted">Für den Platzdienst ist noch kein Orden/Zelt-Vorschlag möglich.</p>
            <?php endif; ?>
        </div>
        <span class="status-chip <?= $openCount > 0 ? 'status-chip--offen' : 'status-chip--ok' ?>"><?= $openCount > 0 ? 'offen' : 'ok' ?></span>
    </article>

    <?php if ($duties === []): ?>
        <article class="card empty-state">
            <div class="empty-icon" aria-hidden="true"><span class="material-symbols-rounded">assignment</span></div>
            <h2>Noch keine Dienste</h2>
            <p class="muted">Für diesen Lagertag ist noch keine Aufgabe eingetragen.</p>
            <?php if (Auth::can('duties.manage')): ?>
                <a class="button button--primary" href="/dienste/neu?tag=<?= e((string) $activeDate) ?>">Ersten Dienst anlegen</a>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <section class="duty-list" aria-label="Dienste des Lagertags">
            <?php foreach ($duties as $duty): ?>
                <?php
                $status = (string) ($duty['status'] ?? 'offen');
                $timeParts = [];
                if (!empty($duty['starts_at'])) { $timeParts[] = substr((string) $duty['starts_at'], 0, 5); }
                if (!empty($duty['ends_at'])) { $timeParts[] = substr((string) $duty['ends_at'], 0, 5); }
                $timeLabel = $timeParts !== [] ? implode('–', $timeParts) . ' Uhr' : (string) ($duty['time_label'] ?? 'Zeit offen');
                ?>
                <article class="card duty-card duty-card--<?= e($status) ?>">
                    <div class="duty-card__head">
                        <div class="duty-icon material-symbols-rounded" aria-hidden="true"><?= e($duty['icon_key'] ?: 'assignment') ?></div>
                        <div>
                            <p class="eyebrow"><?= e($timeLabel !== '' ? $timeLabel : 'Zeit offen') ?></p>
                            <h3><?= e($duty['title']) ?></h3>
                            <p class="muted"><?= e($duty['duty_type_label'] ?? 'Dienst') ?></p>
                        </div>
                        <span class="status-chip status-chip--<?= e($status === 'offen' ? 'offen' : ($status === 'erledigt' ? 'ok' : 'muted')) ?>"><?= e($statuses[$status] ?? $status) ?></span>
                    </div>

                    <div class="duty-assignees">
                        <span class="role-chip"><?= e($duty['assignment_label'] ?? 'nicht besetzt') ?></span>
                    </div>

                    <?php if (!empty($duty['description'])): ?>
                        <p><?= nl2br(e($duty['description'])) ?></p>
                    <?php endif; ?>

                    <div class="management-actions duty-actions">
                        <?php if ($status !== 'erledigt'): ?>
                            <form method="post" action="/dienste/status" class="inline-form">
                                <?= \App\Support\Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= e($duty['id']) ?>">
                                <input type="hidden" name="duty_date" value="<?= e($duty['duty_date']) ?>">
                                <input type="hidden" name="status" value="erledigt">
                                <button type="submit" class="button button--ghost">Erledigt</button>
                            </form>
                        <?php endif; ?>

                        <?php if (Auth::can('duties.manage')): ?>
                            <?php if ($status !== 'offen'): ?>
                                <form method="post" action="/dienste/status" class="inline-form">
                                    <?= \App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= e($duty['id']) ?>">
                                    <input type="hidden" name="duty_date" value="<?= e($duty['duty_date']) ?>">
                                    <input type="hidden" name="status" value="offen">
                                    <button type="submit" class="button button--ghost">Wieder öffnen</button>
                                </form>
                            <?php endif; ?>
                            <a class="button button--ghost" href="/punkte/dienst?tag=<?= e($duty['duty_date']) ?>&amp;duty_id=<?= e($duty['id']) ?>">Punkte</a>
                            <a class="button button--ghost" href="/dienste/bearbeiten?id=<?= e($duty['id']) ?>">Bearbeiten</a>
                            <form method="post" action="/dienste/deaktivieren" class="inline-form">
                                <?= \App\Support\Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= e($duty['id']) ?>">
                                <input type="hidden" name="duty_date" value="<?= e($duty['duty_date']) ?>">
                                <button type="submit" class="button button--ghost button--danger">Entfernen</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
