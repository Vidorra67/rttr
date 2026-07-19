<?php
/** @var array|null $activeCampYear */
/** @var array $duty */
/** @var array $dutyTypes */
/** @var array $statuses */
/** @var array $people */
/** @var array $orders */
/** @var string $action */
/** @var string $backUrl */
/** @var array|null $suggestedOrder */
$duty = $duty ?? [];
$dutyTypes = $dutyTypes ?? [];
$statuses = $statuses ?? [];
$people = $people ?? [];
$orders = $orders ?? [];
$personIds = array_map('intval', $duty['person_ids'] ?? []);
$orderIds = array_map('intval', $duty['order_ids'] ?? []);
$labels = $duty['label_assignments'] ?? [''];
if ($labels === []) { $labels = ['']; }
$startTime = !empty($duty['starts_at']) ? substr((string) $duty['starts_at'], 0, 5) : '';
$endTime = !empty($duty['ends_at']) ? substr((string) $duty['ends_at'], 0, 5) : '';
?>

<section class="section-head">
    <div>
        <p class="eyebrow">Dienste</p>
        <h2><?= e($title ?? 'Dienst') ?></h2>
        <p class="muted">Lege eine Tagesaufgabe an und weise Personen, Orden/Zelte oder ein Team per Freitext zu.</p>
    </div>
    <a class="button button--ghost" href="<?= e($backUrl ?? '/dienste') ?>">Zurück</a>
</section>

<?php if (is_array($suggestedOrder)): ?>
    <article class="card duty-suggestion">
        <p class="eyebrow">Platzdienst-Vorschlag</p>
        <h2><?= e($suggestedOrder['name']) ?></h2>
        <p class="muted">Der Vorschlag basiert auf der letzten gespeicherten Platzdienst-Zuweisung und der Sortierung der Orden/Zelte. Bestehende Dienste werden nicht überschrieben.</p>
    </article>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card form-card">
    <?= \App\Support\Csrf::input() ?>
    <?php if (!empty($duty['id'])): ?>
        <input type="hidden" name="id" value="<?= e($duty['id']) ?>">
    <?php endif; ?>
    <input type="hidden" name="camp_year_id" value="<?= e($duty['camp_year_id'] ?? '') ?>">

    <div class="form-grid">
        <label>
            Datum
            <input type="date" name="duty_date" value="<?= e($duty['duty_date'] ?? '') ?>" min="<?= e($activeCampYear['starts_on'] ?? '') ?>" max="<?= e($activeCampYear['ends_on'] ?? '') ?>" required>
        </label>
        <label>
            Dienstart
            <select name="duty_type_id" required>
                <?php foreach ($dutyTypes as $type): ?>
                    <option value="<?= e($type['id']) ?>" <?= ((int) ($duty['duty_type_id'] ?? 0) === (int) $type['id']) ? 'selected' : '' ?>><?= e($type['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Status
            <select name="status" required>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= (($duty['status'] ?? 'offen') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Startzeit
            <input type="time" name="starts_at" value="<?= e($startTime) ?>">
        </label>
        <label>
            Endzeit
            <input type="time" name="ends_at" value="<?= e($endTime) ?>">
        </label>
        <label>
            Zeitlabel
            <input type="text" name="time_label" value="<?= e($duty['time_label'] ?? '') ?>" maxlength="80" placeholder="z. B. nach dem Frühstück">
        </label>
    </div>

    <label class="field-title">
        Titel
        <input type="text" name="title" value="<?= e($duty['title'] ?? '') ?>" maxlength="190" required placeholder="z. B. Platzdienst, Nachtwache, Küchendienst">
    </label>

    <label class="field-title">
        Beschreibung oder Notiz
        <textarea name="description" maxlength="5000"><?= e($duty['description'] ?? '') ?></textarea>
    </label>

    <div class="assignment-layout">
        <fieldset class="card nested-card">
            <legend>Personen zuweisen</legend>
            <?php if ($people === []): ?>
                <p class="muted">Es sind noch keine aktiven Personen vorhanden.</p>
            <?php else: ?>
                <div class="checkbox-grid">
                    <?php foreach ($people as $person): ?>
                        <label class="check-row">
                            <input type="checkbox" name="person_ids[]" value="<?= e($person['id']) ?>" <?= in_array((int) $person['id'], $personIds, true) ? 'checked' : '' ?>>
                            <span><?= e($person['display_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <fieldset class="card nested-card">
            <legend>Orden/Zelte zuweisen</legend>
            <?php if ($orders === []): ?>
                <p class="muted">Für das aktive Lagerjahr sind noch keine Orden/Zelte angelegt.</p>
            <?php else: ?>
                <div class="checkbox-grid">
                    <?php foreach ($orders as $order): ?>
                        <label class="check-row">
                            <input type="checkbox" name="order_ids[]" value="<?= e($order['id']) ?>" <?= in_array((int) $order['id'], $orderIds, true) ? 'checked' : '' ?>>
                            <span><?= e($order['short_name']) ?> · <?= e($order['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>
    </div>

    <div class="field-title">Freitext-Zuweisung</div>
    <p class="muted">Optional, zum Beispiel „Küchenteam“, „Grafen“ oder „alle Freizeitleiter“.</p>
    <div class="label-assignment-grid">
        <?php foreach ($labels as $label): ?>
            <input type="text" name="assignment_labels[]" value="<?= e($label) ?>" maxlength="190" placeholder="Team oder Hinweis">
        <?php endforeach; ?>
        <input type="text" name="assignment_labels[]" value="" maxlength="190" placeholder="weiteres Team">
    </div>

    <div class="form-actions">
        <a class="button button--ghost" href="<?= e($backUrl ?? '/dienste') ?>">Abbrechen</a>
        <button type="submit" class="button button--primary">Dienst speichern</button>
    </div>
</form>
