<?php
/** @var array|null $activeCampYear */
/** @var array $dayTabs */
/** @var string|null $activeDate */
/** @var array $participants */
/** @var array $orders */
/** @var array $categories */
/** @var array $ownTodayEntries */
/** @var array $payload */
/** @var App\Services\PointService $pointService */
use App\Support\Csrf;
use App\Support\Auth;

$participants = $participants ?? [];
$orders = $orders ?? [];
$categories = $categories ?? [];
$ownTodayEntries = $ownTodayEntries ?? [];
$payload = $payload ?? [];
$selectedCategoryId = (string) ($payload['category_id'] ?? '');
$selectedPersonId = (string) ($payload['person_id'] ?? '');
$selectedOrderId = (string) ($payload['order_id'] ?? '');
$scoringDate = (string) ($payload['scoring_date'] ?? ($activeDate ?? ''));
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Ordnung</p>
        <h2>Ordnung bewerten</h2>
        <p class="muted">Erfasse die tägliche Ordnung, Sauberkeit, Zeltwertung und Disziplin. Punkte werden positiv eingetragen.</p>
    </div>
    <div class="management-actions">
        <a class="button button--ghost" href="/punkte/zelt<?= !empty($activeDate) ? '?tag=' . e($activeDate) : '' ?>">Zelt</a>
        <a class="button button--ghost" href="/punkte/geschirr<?= !empty($activeDate) ? '?tag=' . e($activeDate) : '' ?>">Geschirr</a>
        <?php if (Auth::can('points.manage')): ?>
            <a class="button button--ghost" href="/punkte/spiel<?= !empty($activeDate) ? '?tag=' . e($activeDate) : '' ?>">Spiel</a>
            <a class="button button--ghost" href="/punkte/dienst<?= !empty($activeDate) ? '?tag=' . e($activeDate) : '' ?>">Dienst</a>
            <a class="button button--ghost" href="/admin/ordnungspunkte">Alle Einträge</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($activeCampYear === null): ?>
    <section class="card empty-state">
        <div class="empty-icon">!</div>
        <h2>Kein aktives Lagerjahr</h2>
        <p>Für Ordnungspunkte muss ein Lagerjahr aktiv sein.</p>
    </section>
<?php else: ?>
    <?php $days = $dayTabs ?? []; $activeDay = $activeDate; require base_path('app/Views/partials/day_tabs.php'); ?>

    <section class="point-form-layout">
        <article class="card form-card point-mobile-card">
            <form method="post" action="/ordnung/abziehen" class="form-grid">
                <?= Csrf::input() ?>

                <label class="form-grid__full">
                    <span>Bewertungsart</span>
                    <select name="category_id" required>
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category['id']) ?>" <?= $selectedCategoryId === (string) $category['id'] ? 'selected' : '' ?> data-scope="<?= e($category['scope'] ?? 'person') ?>" data-max="<?= e($category['max_points_per_entry'] ?? '') ?>">
                                <?= e($category['label']) ?> · max. <?= e((int) ($category['max_points_per_entry'] ?? 0)) ?> Punkte
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted">Teilnehmerwertungen brauchen ein Kind. Zeltwertungen brauchen einen Orden/Zelt.</small>
                </label>

                <label>
                    <span>Datum</span>
                    <input type="date" name="scoring_date" value="<?= e($scoringDate) ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
                </label>

                <label>
                    <span>Prüfung</span>
                    <select name="check_slot">
                        <option value="">ohne Auswahl</option>
                        <option value="morgens" <?= (string) ($payload['check_slot'] ?? '') === 'morgens' ? 'selected' : '' ?>>Morgens</option>
                        <option value="abends" <?= (string) ($payload['check_slot'] ?? '') === 'abends' ? 'selected' : '' ?>>Abends</option>
                        <option value="tag" <?= (string) ($payload['check_slot'] ?? '') === 'tag' ? 'selected' : '' ?>>Tageswertung</option>
                        <option value="fach_1" <?= (string) ($payload['check_slot'] ?? '') === 'fach_1' ? 'selected' : '' ?>>Fach 1</option>
                        <option value="fach_2" <?= (string) ($payload['check_slot'] ?? '') === 'fach_2' ? 'selected' : '' ?>>Fach 2</option>
                        <option value="fach_3" <?= (string) ($payload['check_slot'] ?? '') === 'fach_3' ? 'selected' : '' ?>>Fach 3</option>
                        <option value="einsatz_1" <?= (string) ($payload['check_slot'] ?? '') === 'einsatz_1' ? 'selected' : '' ?>>Einsatz 1</option>
                        <option value="einsatz_2" <?= (string) ($payload['check_slot'] ?? '') === 'einsatz_2' ? 'selected' : '' ?>>Einsatz 2</option>
                        <option value="einsatz_3" <?= (string) ($payload['check_slot'] ?? '') === 'einsatz_3' ? 'selected' : '' ?>>Einsatz 3</option>
                        <option value="freizeit" <?= (string) ($payload['check_slot'] ?? '') === 'freizeit' ? 'selected' : '' ?>>Freizeit/Bonus</option>
                    </select>
                    <small class="muted">Bei 2x täglich Morgens oder Abends auswählen. Bonuspunkte bitte mit Freizeit/Bonus buchen.</small>
                </label>

                <label class="form-grid__full">
                    <span>Kind suchen</span>
                    <input type="search" name="q" value="<?= e($payload['q'] ?? '') ?>" placeholder="Name eingeben und Formular erneut öffnen">
                </label>

                <label>
                    <span>Teilnehmer</span>
                    <select name="person_id">
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($participants as $participant): ?>
                            <option value="<?= e($participant['id']) ?>" <?= $selectedPersonId === (string) $participant['id'] ? 'selected' : '' ?>>
                                <?= e($participant['display_name']) ?><?= !empty($participant['order_short_name']) ? ' · ' . e($participant['order_short_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Orden/Zelt</span>
                    <select name="order_id">
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($orders as $order): ?>
                            <option value="<?= e($order['id']) ?>" <?= $selectedOrderId === (string) $order['id'] ? 'selected' : '' ?>><?= e($order['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Punkte</span>
                    <input type="number" name="points" min="0" max="90" value="<?= e($payload['points'] ?? '') ?>" required placeholder="z. B. 5">
                </label>

                <label class="form-grid__full">
                    <span>Notiz</span>
                    <textarea name="reason" rows="3" maxlength="500" placeholder="Optionaler Hinweis zur Bewertung."><?= e($payload['reason'] ?? '') ?></textarea>
                </label>

                <div class="point-rule-box form-grid__full">
                    <strong>Regeln</strong>
                    <p>Ordnung Zelt, Spiele, Platzdienst und Küchendienst zählen global für den Orden. Ordnung persönlich, Geschirr, Prüfung und Bonus zählen persönlich. Bonus: Jeder Mitarbeiter kann pro Freizeit max. 5 Punkte an einen Teilnehmer vergeben.</p>
                </div>

                <button type="submit" class="button button--primary button--full form-grid__full">Bewertung speichern</button>
            </form>
        </article>

        <article class="card">
            <div class="section-head section-head--compact">
                <div>
                    <p class="eyebrow">Heute</p>
                    <h2>Meine Einträge</h2>
                </div>
                <span class="status-chip status-chip--ok"><?= e(count($ownTodayEntries)) ?> Einträge</span>
            </div>
            <?php if ($ownTodayEntries === []): ?>
                <p class="muted">Du hast heute noch keine Ordnungspunkte erfasst.</p>
            <?php else: ?>
                <div class="point-entry-list">
                    <?php foreach ($ownTodayEntries as $entry): ?>
                        <div class="point-entry-row <?= !empty($entry['voided_at']) ? 'point-entry-row--voided' : '' ?>">
                            <strong><?= e((int) $entry['points']) ?></strong>
                            <span><?= e($entry['person_name'] ?: ($entry['order_name'] ?? 'Orden/Zelt')) ?></span>
                            <small><?= e($entry['category_label']) ?><?= !empty($entry['subject_label']) ? ' · ' . e($entry['subject_label']) : '' ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>
<?php endif; ?>
