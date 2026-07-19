<?php
/** @var array $dashboard */
/** @var string $version */
use App\Support\Auth;

$activeCampYear = $dashboard['activeCampYear'] ?? null;
$stats = $dashboard['stats'] ?? [];
$dayTabs = $dashboard['dayTabs'] ?? [];
$orders = $dashboard['orders'] ?? [];
$birthdaysToday = $dashboard['birthdaysToday'] ?? [];
$nextProgramItem = $dashboard['nextProgramItem'] ?? null;
$todayMeals = $dashboard['todayMeals'] ?? [];
$openDutiesToday = $dashboard['openDutiesToday'] ?? [];
?>

<section class="dashboard-grid">
    <article class="card metric-card">
        <div class="metric-icon" aria-hidden="true">K</div>
        <p>Teilnehmer</p>
        <strong><?= e($stats['participants'] ?? 0) ?></strong>
    </article>
    <article class="card metric-card">
        <div class="metric-icon metric-icon--mint" aria-hidden="true">M</div>
        <p>Mitarbeiter</p>
        <strong><?= e($stats['staff'] ?? 0) ?></strong>
    </article>
    <article class="card metric-card">
        <div class="metric-icon metric-icon--mahlzeit" aria-hidden="true">O</div>
        <p>Orden/Zelte</p>
        <strong><?= e($stats['orders'] ?? 0) ?></strong>
    </article>
    <article class="card metric-card">
        <div class="metric-icon metric-icon--offen" aria-hidden="true">!</div>
        <p>Offene Dienste</p>
        <strong><?= e($stats['open_duties'] ?? 0) ?></strong>
    </article>
    <article class="card metric-card">
        <div class="metric-icon metric-icon--punkte" aria-hidden="true">O</div>
        <p>Punkte heute</p>
        <strong><?= e($stats['order_points_today'] ?? 0) ?></strong>
    </article>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="hero-card">
        <p class="eyebrow">Start</p>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Lege ein Lagerjahr an und setze es aktiv. Danach zeigt die Übersicht Lagertage, Orden/Zelte und Tagesbereiche an.</p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--hero" href="/admin/lagerjahre/neu">Lagerjahr anlegen</a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="hero-card">
        <p class="eyebrow">Aktives Lagerjahr</p>
        <h2><?= e($activeCampYear['name']) ?></h2>
        <p><?= e($activeCampYear['location_name'] ?: 'Ort offen') ?> · <?= e(date('d.m.Y', strtotime((string) $activeCampYear['starts_on']))) ?> bis <?= e(date('d.m.Y', strtotime((string) $activeCampYear['ends_on']))) ?></p>
        <?php if (Auth::can('camp_years.manage')): ?>
            <a class="button button--hero" href="/admin/lagerjahre/bearbeiten?id=<?= e($activeCampYear['id']) ?>">Lagerjahr bearbeiten</a>
        <?php endif; ?>
    </section>

    <?php $days = $dayTabs; $activeDay = $dashboard['currentCampDate'] ?? null; require base_path('app/Views/partials/day_tabs.php'); ?>
<?php endif; ?>

<section class="section-stack dashboard-sections">
    <article class="card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Nächster Programmpunkt</p>
                <?php if ($nextProgramItem === null): ?>
                    <h2>Noch kein Programm</h2>
                <?php else: ?>
                    <h2><?= e($nextProgramItem['title']) ?></h2>
                <?php endif; ?>
            </div>
            <?php if ($nextProgramItem === null): ?>
                <span class="category-tag category-tag--info">offen</span>
            <?php else: ?>
                <span class="category-tag category-tag--<?= e($nextProgramItem['category_tag'] ?? 'info') ?>"><?= e($nextProgramItem['category_label'] ?? 'Info') ?></span>
            <?php endif; ?>
        </div>
        <?php if ($nextProgramItem === null): ?>
            <p class="muted">Für den aktuellen Lagertag ist noch kein sichtbarer Programmpunkt eingetragen.</p>
            <?php if (Auth::can('program.manage') && $activeCampYear !== null): ?>
                <a class="button button--ghost" href="/programm/neu?tag=<?= e((string) ($dashboard['currentCampDate'] ?? '')) ?>">Programmpunkt hinzufügen</a>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted">
                <?= !empty($nextProgramItem['starts_at']) ? e(substr((string) $nextProgramItem['starts_at'], 0, 5) . ' Uhr') : 'Zeit offen' ?>
                <?php if (!empty($nextProgramItem['location'])): ?> · <?= e($nextProgramItem['location']) ?><?php endif; ?>
                <?php if (!empty($nextProgramItem['responsible_label'])): ?> · <?= e($nextProgramItem['responsible_label']) ?><?php endif; ?>
            </p>
            <a class="button button--ghost" href="/programm?tag=<?= e((string) $nextProgramItem['program_date']) ?>">Programm öffnen</a>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Essen heute</p>
                <h2><?= $todayMeals === [] ? 'Noch kein Speiseplan' : 'Speiseplan' ?></h2>
            </div>
            <span class="category-tag category-tag--mahlzeit">Mahlzeit</span>
        </div>
        <?php if ($todayMeals === []): ?>
            <p class="muted">Für den aktuellen Lagertag ist noch keine Mahlzeit eingetragen.</p>
            <?php if (Auth::can('meals.manage') && $activeCampYear !== null): ?>
                <a class="button button--ghost" href="/essen/neu?tag=<?= e((string) ($dashboard['currentCampDate'] ?? '')) ?>">Mahlzeit hinzufügen</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="meal-summary-list">
                <?php foreach ($todayMeals as $mealGroup): ?>
                    <?php $mealItem = $mealGroup['item'] ?? null; ?>
                    <div class="meal-summary-row">
                        <strong><?= e($mealGroup['label'] ?? 'Mahlzeit') ?></strong>
                        <span><?= $mealItem === null ? 'offen' : e($mealItem['title']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="button button--ghost" href="/essen?tag=<?= e((string) ($dashboard['currentCampDate'] ?? '')) ?>">Speiseplan öffnen</a>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Offene Dienste heute</p>
                <h2><?= $openDutiesToday === [] ? 'Keine offenen Dienste' : 'Dienstliste' ?></h2>
            </div>
            <span class="status-chip <?= ($stats['open_duties'] ?? 0) > 0 ? 'status-chip--offen' : 'status-chip--ok' ?>"><?= e($stats['open_duties'] ?? 0) ?> offen</span>
        </div>
        <?php if ($openDutiesToday === []): ?>
            <p class="muted">Für den aktuellen Lagertag sind keine offenen Dienste eingetragen.</p>
            <?php if (Auth::can('duties.manage') && $activeCampYear !== null): ?>
                <a class="button button--ghost" href="/dienste/neu?tag=<?= e((string) ($dashboard['currentCampDate'] ?? '')) ?>">Dienst anlegen</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="duty-summary-list">
                <?php foreach ($openDutiesToday as $duty): ?>
                    <div class="duty-summary-row">
                        <strong><?= e($duty['title']) ?></strong>
                        <span><?= e($duty['assignment_label'] ?? 'nicht besetzt') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="button button--ghost" href="/dienste?tag=<?= e((string) ($dashboard['currentCampDate'] ?? '')) ?>">Dienste öffnen</a>
        <?php endif; ?>
    </article>


    <?php if (Auth::can('points.order.create')): ?>
        <article class="card point-dashboard-card">
            <div class="section-head section-head--compact">
                <div>
                    <p class="eyebrow">Ordnung</p>
                    <h2>Ordnung bewerten</h2>
                </div>
                <span class="category-tag category-tag--info">Bewertung</span>
            </div>
            <p class="muted">Schnelle mobile Aktion für Ordnung, Sauberkeit, Zelt und Disziplin. Punkte werden positiv erfasst.</p>
            <div class="management-actions">
                <a class="button button--primary" href="/ordnung">Bewertung erfassen</a>
                <?php if (Auth::can('points.manage')): ?>
                    <a class="button button--ghost" href="/admin/ordnungspunkte">Alle Einträge</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>

    <article class="card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Geburtstage</p>
                <h2>Heute</h2>
            </div>
            <span class="category-tag category-tag--spiel">Heute</span>
        </div>
        <?php if ($birthdaysToday === []): ?>
            <p class="muted">Heute ist in den vorhandenen Personendaten kein Geburtstag hinterlegt.</p>
        <?php else: ?>
            <div class="chip-row">
                <?php foreach ($birthdaysToday as $person): ?>
                    <a class="role-chip" href="/admin/personen/detail?id=<?= e($person['id']) ?>"><?= e($person['display_name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="section-head section-head--compact">
            <div>
                <p class="eyebrow">Orden/Zelte</p>
                <h2><?= $orders === [] ? 'Noch keine Einheiten' : 'Aktive Einheiten' ?></h2>
            </div>
            <?php if (Auth::can('orders.manage') && $activeCampYear !== null): ?>
                <a class="button button--ghost" href="/admin/orden?camp_year_id=<?= e($activeCampYear['id']) ?>">Verwalten</a>
            <?php endif; ?>
        </div>
        <?php if ($orders === []): ?>
            <p class="muted">Lege Johanniter, Falkner, Samariter, Petrusker, Morgensternritter und Malteser als Orden/Zelte an.</p>
        <?php else: ?>
            <?php
            $orderTextColor = static function (?string $hex): string {
                $hex = strtoupper(trim((string) $hex));
                if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                    return '#FFFFFF';
                }
                $r = hexdec(substr($hex, 1, 2));
                $g = hexdec(substr($hex, 3, 2));
                $b = hexdec(substr($hex, 5, 2));
                $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                return $luminance > 150 ? '#17204A' : '#FFFFFF';
            };
            ?>
            <div class="order-mini-grid">
                <?php foreach ($orders as $order): ?>
                    <?php if ((int) $order['is_active'] !== 1) { continue; } ?>
                    <?php $orderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($order['color_hex'] ?? '')) ? strtoupper((string) $order['color_hex']) : null; ?>
                    <span class="order-mini <?= $orderColor ? 'order-mini--custom' : 'order-mini--' . e($order['color_key'] ?: 'blau') ?>" <?= $orderColor ? 'style="--order-color: ' . e($orderColor) . '; --order-mini-bg: ' . e($orderColor) . '; --order-mini-text: ' . e($orderTextColor($orderColor)) . ';"' : '' ?>><?= e($order['short_name']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<p class="muted app-version">Version <?= e($version) ?></p>
