<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PersonService
{
    private const SENSITIVE_FIELDS = [
        'emergency_contact_name',
        'emergency_contact_phone',
        'allergy_notes',
        'medical_notes',
        'internal_notes',
    ];

    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly AuthService $authService = new AuthService(),
        private readonly CampYearService $campYearService = new CampYearService()
    ) {
    }

    public function all(array $filters = []): array
    {
        $pdo = Database::connection();
        $campYearId = $this->activeCampYearId();
        $params = ['camp_year_id' => $campYearId ?? 0];
        $where = ['p.deleted_at IS NULL'];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(p.display_name LIKE :query_display OR p.first_name LIKE :query_first OR p.last_name LIKE :query_last OR p.email LIKE :query_email)';
            $likeQuery = '%' . $query . '%';
            $params['query_display'] = $likeQuery;
            $params['query_first'] = $likeQuery;
            $params['query_last'] = $likeQuery;
            $params['query_email'] = $likeQuery;
        }

        $active = (string) ($filters['active'] ?? '');
        if ($active === '1' || $active === '0') {
            $where[] = 'p.is_active = :is_active';
            $params['is_active'] = (int) $active;
        }

        $type = (string) ($filters['type'] ?? '');
        if ($type === 'participant') {
            $where[] = 'COALESCE(cps.is_participant, IF(p.type_hint IN (\'teilnehmer\',\'beides\'), 1, 0)) = 1';
        } elseif ($type === 'staff') {
            $where[] = 'COALESCE(cps.is_staff, IF(p.type_hint IN (\'mitarbeiter\',\'beides\'), 1, 0)) = 1';
        } elseif ($type === 'both') {
            $where[] = 'COALESCE(cps.is_participant, IF(p.type_hint IN (\'teilnehmer\',\'beides\'), 1, 0)) = 1';
            $where[] = 'COALESCE(cps.is_staff, IF(p.type_hint IN (\'mitarbeiter\',\'beides\'), 1, 0)) = 1';
        }

        $orderId = filter_var($filters['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($orderId !== false && $orderId !== null) {
            $where[] = 'cps.order_id = :order_id';
            $params['order_id'] = (int) $orderId;
        }

        $sql = "SELECT p.*, u.id AS user_id, u.is_login_enabled, u.locked_until, u.last_login_at,
                cps.is_participant, cps.is_staff, cps.participant_status, cps.staff_status, cps.order_id, cps.rank_label, cps.rank_level_id, cps.next_rank_level_id, cps.next_rank_label, cps.promotion_status, cps.promotion_note,
                rl.label AS rank_level_label, nrl.label AS next_rank_level_label,
                o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex
            FROM persons p
            LEFT JOIN users u ON u.person_id = p.id
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            LEFT JOIN rank_levels nrl ON nrl.id = cps.next_rank_level_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.is_active DESC, p.display_name ASC, p.last_name ASC, p.first_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $campYear = $this->activeCampYear();
        foreach ($persons as $index => &$person) {
            $person['roles'] = $this->roleKeysForPerson((int) $person['id']);
            $person['is_participant_effective'] = $this->isParticipant($person);
            $person['is_staff_effective'] = $this->isStaff($person);
            $person['age_at_camp'] = $this->ageAtCamp($person['birthdate'] ?? null, $campYear);
            $person['birthday_in_camp'] = $this->birthdayInCamp($person['birthdate'] ?? null, $campYear);
            $this->applyRankFallback($person, $campYearId);

            if (!empty($filters['birthday_in_camp']) && !$person['birthday_in_camp']) {
                unset($persons[$index]);
            }
        }
        unset($person);

        return array_values($persons);
    }

    public function find(int $id, ?int $campYearId = null): ?array
    {
        $campYearId ??= $this->activeCampYearId();
        $stmt = Database::connection()->prepare("SELECT p.*, u.id AS user_id, u.is_login_enabled, u.locked_until, u.last_login_at,
                cps.is_participant, cps.is_staff, cps.participant_status, cps.staff_status, cps.order_id, cps.rank_label, cps.rank_level_id, cps.next_rank_level_id, cps.next_rank_label, cps.promotion_status, cps.promotion_note,
                rl.label AS rank_level_label, nrl.label AS next_rank_level_label,
                o.name AS order_name, o.short_name AS order_short_name, o.color_key AS order_color_key, o.color_hex AS order_color_hex
            FROM persons p
            LEFT JOIN users u ON u.person_id = p.id
            LEFT JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            LEFT JOIN rank_levels nrl ON nrl.id = cps.next_rank_level_id
            WHERE p.id = :id AND p.deleted_at IS NULL
            LIMIT 1");
        $stmt->execute(['id' => $id, 'camp_year_id' => $campYearId ?? 0]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($person)) {
            return null;
        }

        $campYear = $campYearId !== null ? $this->campYearService->find($campYearId) : $this->activeCampYear();
        $person['roles'] = $this->roleKeysForPerson($id);
        $person['guardians'] = $this->guardiansForPerson($id);
        $person['is_participant_effective'] = $this->isParticipant($person);
        $person['is_staff_effective'] = $this->isStaff($person);
        $person['age_at_camp'] = $this->ageAtCamp($person['birthdate'] ?? null, $campYear);
        $person['birthday_in_camp'] = $this->birthdayInCamp($person['birthdate'] ?? null, $campYear);
        $this->applyRankFallback($person, $campYearId);
        return $person;
    }


    private function applyRankFallback(array &$person, ?int $currentCampYearId = null): void
    {
        $fallback = $this->highestKnownRankForDisplay((int) $person['id'], $currentCampYearId);
        if ($fallback === null) {
            return;
        }

        $pdo = Database::connection();
        $currentSort = $this->rankSortOrder(
            $pdo,
            isset($person['rank_level_id']) ? (int) $person['rank_level_id'] : null,
            (string) ($person['rank_label'] ?? $person['rank_level_label'] ?? '')
        );
        $fallbackSort = isset($fallback['rank_sort_order']) ? (int) $fallback['rank_sort_order'] : 0;

        if (!empty($person['rank_level_label']) || !empty($person['rank_label'])) {
            if ($currentSort !== null && $currentSort >= $fallbackSort) {
                return;
            }
        }

        $person['rank_level_id'] = $fallback['rank_level_id'] ?? null;
        $person['rank_level_label'] = $fallback['rank_level_label'] ?? null;
        $person['rank_label'] = $fallback['rank_label'] ?? null;
        $person['rank_fallback_from_year'] = $fallback['camp_year_name'] ?? null;
    }

    private function highestKnownRankForDisplay(int $personId, ?int $currentCampYearId = null): ?array
    {
        $stmt = Database::connection()->prepare("SELECT cps.rank_label, cps.rank_level_id,
                rl.label AS rank_level_label,
                COALESCE(rl.sort_order, 0) AS rank_sort_order,
                cy.name AS camp_year_name,
                cy.starts_on
            FROM camp_person_statuses cps
            INNER JOIN camp_years cy ON cy.id = cps.camp_year_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            WHERE cps.person_id = :person_id
              AND (:current_camp_year_id IS NULL OR cps.camp_year_id <> :current_camp_year_id_compare)
              AND (
                cps.rank_level_id IS NOT NULL
                OR (cps.rank_label IS NOT NULL AND cps.rank_label <> '')
              )
            ORDER BY COALESCE(rl.sort_order, 0) DESC, cy.starts_on DESC, cy.id DESC
            LIMIT 1");
        $stmt->execute([
            'person_id' => $personId,
            'current_camp_year_id' => $currentCampYearId,
            'current_camp_year_id_compare' => $currentCampYearId,
        ]);
        $rank = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($rank) ? $rank : null;
    }

    public function roles(): array
    {
        $stmt = Database::connection()->query("SELECT id, key_name, label FROM roles ORDER BY key_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function campYears(): array
    {
        return $this->campYearService->all();
    }

    public function activeCampYear(): ?array
    {
        return $this->campYearService->active();
    }

    public function activeCampYearId(): ?int
    {
        $campYear = $this->activeCampYear();
        return $campYear === null ? null : (int) $campYear['id'];
    }

    public function orderOptions(?int $campYearId = null): array
    {
        $campYearId ??= $this->activeCampYearId();
        if ($campYearId === null) {
            return [];
        }

        $stmt = Database::connection()->prepare("SELECT id, name, short_name, color_key, color_hex
            FROM orders
            WHERE camp_year_id = :camp_year_id AND is_active = 1
            ORDER BY sort_order ASC, name ASC");
        $stmt->execute(['camp_year_id' => $campYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function rankOptions(?int $campYearId = null): array
    {
        $campYearId ??= $this->activeCampYearId();
        if ($campYearId === null) {
            return [];
        }

        $stmt = Database::connection()->prepare("SELECT id, key_name, label, sort_order, promotion_points_required, next_rank_key
            FROM rank_levels
            WHERE camp_year_id = :camp_year_id
            ORDER BY sort_order ASC, label ASC");
        $stmt->execute(['camp_year_id' => $campYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function previousYearRoster(int $fromCampYearId, int $toCampYearId): array
    {
        $stmt = Database::connection()->prepare("SELECT p.id, p.display_name, p.nickname,
                cps.order_id, cps.is_staff, cps.rank_label, cps.rank_level_id, cps.next_rank_level_id, cps.next_rank_label, cps.promotion_status,
                o.name AS order_name, o.short_name AS order_short_name,
                rl.label AS rank_level_label, rl.key_name AS rank_level_key,
                nrl.label AS next_rank_level_label, nrl.key_name AS next_rank_level_key
            FROM persons p
            INNER JOIN camp_person_statuses cps ON cps.person_id = p.id AND cps.camp_year_id = :from_camp_year_id
            LEFT JOIN orders o ON o.id = cps.order_id
            LEFT JOIN rank_levels rl ON rl.id = cps.rank_level_id
            LEFT JOIN rank_levels nrl ON nrl.id = cps.next_rank_level_id
            WHERE p.deleted_at IS NULL
              AND p.is_active = 1
              AND cps.is_participant = 1
              AND NOT EXISTS (
                  SELECT 1 FROM camp_person_statuses existing
                  WHERE existing.person_id = p.id
                    AND existing.camp_year_id = :to_camp_year_id
                    AND existing.is_participant = 1
              )
            ORDER BY o.sort_order ASC, p.display_name ASC");
        $stmt->execute([
            'from_camp_year_id' => $fromCampYearId,
            'to_camp_year_id' => $toCampYearId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $confirmed = (string) ($row['promotion_status'] ?? '') === 'bestaetigt'
                && ((int) ($row['next_rank_level_id'] ?? 0) > 0 || !empty($row['next_rank_label']));
            $row['promotion_confirmed'] = $confirmed;
            $row['will_become_rank_label'] = $confirmed
                ? (string) ($row['next_rank_level_label'] ?? $row['next_rank_label'])
                : (string) ($row['rank_level_label'] ?? $row['rank_label'] ?? 'offen');
        }
        unset($row);

        return $rows;
    }

    public function transferParticipants(int $fromCampYearId, int $toCampYearId, array $personIds): int
    {
        if ($personIds === []) {
            return 0;
        }

        if ($this->campYearService->find($toCampYearId) === null) {
            throw new RuntimeException('Ziel-Lagerjahr wurde nicht gefunden.');
        }

        $roster = [];
        foreach ($this->previousYearRoster($fromCampYearId, $toCampYearId) as $row) {
            $roster[(int) $row['id']] = $row;
        }

        $pdo = Database::connection();
        $transferred = 0;

        foreach ($personIds as $rawPersonId) {
            $personId = filter_var($rawPersonId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($personId === false || !isset($roster[$personId])) {
                continue;
            }
            $row = $roster[$personId];

            $rankLevelId = null;
            $rankLabel = null;
            $sourceKey = $row['promotion_confirmed']
                ? ($row['next_rank_level_key'] ?? null)
                : ($row['rank_level_key'] ?? null);
            if ($sourceKey !== null && $sourceKey !== '') {
                $translated = $this->rankLevelForKey($pdo, $toCampYearId, (string) $sourceKey);
                if ($translated !== null) {
                    $rankLevelId = (int) $translated['id'];
                    $rankLabel = (string) $translated['label'];
                }
            }
            if ($rankLabel === null) {
                $rankLabel = (string) $row['will_become_rank_label'];
            }

            $orderId = null;
            if (!empty($row['order_name'])) {
                $orderStmt = $pdo->prepare('SELECT id FROM orders WHERE camp_year_id = :camp_year_id AND name = :name LIMIT 1');
                $orderStmt->execute(['camp_year_id' => $toCampYearId, 'name' => (string) $row['order_name']]);
                $foundOrderId = $orderStmt->fetchColumn();
                $orderId = $foundOrderId !== false ? (int) $foundOrderId : null;
            }

            $stmt = $pdo->prepare("INSERT INTO camp_person_statuses
                (camp_year_id, person_id, is_participant, is_staff, participant_status, staff_status, order_id, rank_label, rank_level_id, next_rank_level_id, next_rank_label, promotion_status, created_at, updated_at)
                VALUES (:camp_year_id, :person_id, 1, :is_staff, 'angemeldet', 'aktiv', :order_id, :rank_label, :rank_level_id, NULL, NULL, 'offen', NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    is_participant = 1,
                    is_staff = GREATEST(is_staff, VALUES(is_staff)),
                    order_id = VALUES(order_id),
                    rank_label = VALUES(rank_label),
                    rank_level_id = VALUES(rank_level_id),
                    updated_at = NOW()");
            $stmt->execute([
                'camp_year_id' => $toCampYearId,
                'person_id' => $personId,
                'is_staff' => (int) ($row['is_staff'] ?? 0) === 1 ? 1 : 0,
                'order_id' => $orderId,
                'rank_label' => $this->nullableString($rankLabel),
                'rank_level_id' => $rankLevelId,
            ]);
            $transferred++;

            $this->audit('camp_person_statuses.rolled_over', 'person', $personId, [
                'from_camp_year_id' => $fromCampYearId,
                'to_camp_year_id' => $toCampYearId,
                'rank_label' => $rankLabel,
                'promotion_applied' => $row['promotion_confirmed'],
            ]);
        }

        return $transferred;
    }

    public function create(array $data): int
    {
        $this->validatePersonData($data);
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $displayName = $this->displayName($data);
            $typeHint = $this->typeHintFromStatus($data);
            $stmt = $pdo->prepare("INSERT INTO persons
                (first_name, last_name, display_name, nickname, birthdate, type_hint, street, zip, city, phone, email,
                 emergency_contact_name, emergency_contact_phone, food_notes, allergy_notes, medical_notes, internal_notes,
                 is_active, created_at, updated_at)
                VALUES (:first_name, :last_name, :display_name, :nickname, :birthdate, :type_hint, :street, :zip, :city, :phone, :email,
                 :emergency_contact_name, :emergency_contact_phone, :food_notes, :allergy_notes, :medical_notes, :internal_notes,
                 :is_active, NOW(), NOW())");
            $stmt->execute($this->personParams($data, $displayName, $typeHint));
            $personId = (int) $pdo->lastInsertId();

            $this->syncCampStatus($pdo, $personId, $data);
            $this->syncPrimaryGuardian($pdo, $personId, $data);
            $this->syncRoles($pdo, $personId, $data['roles'] ?? []);
            $this->syncUser($pdo, $personId, !empty($data['is_login_enabled']), (string) ($data['pin'] ?? ''));

            $pdo->commit();
            $this->audit('persons.created', 'person', $personId, ['display_name' => $displayName]);
            if ($this->hasSensitivePayload($data)) {
                $this->audit('persons.sensitive_updated', 'person', $personId, ['changed' => true, 'fields' => $this->changedSensitiveFields([], $data)]);
            }
            return $personId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $personId, array $data): void
    {
        $this->validatePersonData($data, true);
        $before = $this->find($personId, $this->nullableInt($data['camp_year_id'] ?? null));
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $displayName = $this->displayName($data);
            $typeHint = $this->typeHintFromStatus($data);
            $stmt = $pdo->prepare("UPDATE persons
                SET first_name = :first_name,
                    last_name = :last_name,
                    display_name = :display_name,
                    nickname = :nickname,
                    birthdate = :birthdate,
                    type_hint = :type_hint,
                    street = :street,
                    zip = :zip,
                    city = :city,
                    phone = :phone,
                    email = :email,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_phone = :emergency_contact_phone,
                    food_notes = :food_notes,
                    allergy_notes = :allergy_notes,
                    medical_notes = :medical_notes,
                    internal_notes = :internal_notes,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL");
            $params = $this->personParams($data, $displayName, $typeHint);
            $params['id'] = $personId;
            $stmt->execute($params);

            if ($stmt->rowCount() === 0 && $this->find($personId) === null) {
                throw new RuntimeException('Person wurde nicht gefunden.');
            }

            $this->syncCampStatus($pdo, $personId, $data);
            $this->syncPrimaryGuardian($pdo, $personId, $data);
            $this->syncRoles($pdo, $personId, $data['roles'] ?? []);
            $this->syncUser($pdo, $personId, !empty($data['is_login_enabled']), (string) ($data['pin'] ?? ''), true);

            $pdo->commit();
            $this->audit('persons.updated', 'person', $personId, ['display_name' => $displayName]);

            $changedSensitiveFields = $this->changedSensitiveFields(is_array($before) ? $before : [], $data);
            if ($changedSensitiveFields !== []) {
                $this->audit('persons.sensitive_updated', 'person', $personId, ['changed' => true, 'fields' => $changedSensitiveFields]);
            }
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function setLoginEnabled(int $personId, bool $enabled): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET is_login_enabled = :enabled, updated_at = NOW() WHERE person_id = :person_id");
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'person_id' => $personId]);
        $this->audit($enabled ? 'users.login_enabled' : 'users.login_disabled', 'person', $personId);
    }

    public function resetPin(int $personId, string $pin): void
    {
        $hash = $this->authService->hashPin($pin);
        $stmt = Database::connection()->prepare("UPDATE users
            SET pin_hash = :pin_hash, failed_login_count = 0, locked_until = NULL, updated_at = NOW()
            WHERE person_id = :person_id");
        $stmt->execute(['pin_hash' => $hash, 'person_id' => $personId]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Für diese Person ist noch kein Login angelegt.');
        }

        $this->audit('users.pin_changed', 'person', $personId);
    }

    public function canViewSensitive(): bool
    {
        return Auth::can('persons.sensitive.view') || Auth::can('persons.manage');
    }

    public function birthdayInCamp(mixed $birthdate, ?array $campYear): bool
    {
        if ($birthdate === null || $birthdate === '' || $campYear === null) {
            return false;
        }

        try {
            $birth = new DateTimeImmutable((string) $birthdate);
            $start = new DateTimeImmutable((string) $campYear['starts_on']);
            $end = new DateTimeImmutable((string) $campYear['ends_on']);
            $candidate = $birth->setDate((int) $start->format('Y'), (int) $birth->format('m'), (int) $birth->format('d'));
            if ($candidate < $start && $start->format('Y') !== $end->format('Y')) {
                $candidate = $candidate->modify('+1 year');
            }
            return $candidate >= $start && $candidate <= $end;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ageAtCamp(mixed $birthdate, ?array $campYear): ?int
    {
        if ($birthdate === null || $birthdate === '' || $campYear === null) {
            return null;
        }

        try {
            $birth = new DateTimeImmutable((string) $birthdate);
            $reference = new DateTimeImmutable((string) $campYear['starts_on']);
            return (int) $birth->diff($reference)->y;
        } catch (\Throwable) {
            return null;
        }
    }

    private function validatePersonData(array $data, bool $update = false): void
    {
        if (trim((string) ($data['first_name'] ?? '')) === '') {
            throw new RuntimeException('Vorname ist erforderlich.');
        }
        if (trim((string) ($data['last_name'] ?? '')) === '') {
            throw new RuntimeException('Nachname ist erforderlich.');
        }
        if (!empty($data['birthdate']) && $this->nullableDate($data['birthdate']) === null) {
            throw new RuntimeException('Geburtsdatum ist ungültig.');
        }
        if (!empty($data['is_participant']) && empty($data['birthdate'])) {
            throw new RuntimeException('Für Teilnehmer ist ein Geburtsdatum erforderlich.');
        }
        if (!empty($data['email']) && !filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-Mail-Adresse ist ungültig.');
        }
        if (!empty($data['guardian_email']) && !filter_var((string) $data['guardian_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-Mail-Adresse des Kontakts ist ungültig.');
        }
        if (!empty($data['is_login_enabled']) && !$update && trim((string) ($data['pin'] ?? '')) === '') {
            throw new RuntimeException('Für einen aktiven Login muss eine PIN gesetzt werden.');
        }
        if (trim((string) ($data['pin'] ?? '')) !== '' && preg_match('/^\d{4,6}$/', (string) $data['pin']) !== 1) {
            throw new RuntimeException('PIN muss 4 bis 6 Ziffern enthalten.');
        }
    }

    private function personParams(array $data, string $displayName, string $typeHint): array
    {
        return [
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'display_name' => $displayName,
            'nickname' => $this->nullableString($data['nickname'] ?? null),
            'birthdate' => $this->nullableDate($data['birthdate'] ?? null),
            'type_hint' => $typeHint,
            'street' => $this->nullableString($data['street'] ?? null),
            'zip' => $this->nullableString($data['zip'] ?? null),
            'city' => $this->nullableString($data['city'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'emergency_contact_name' => $this->nullableString($data['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => $this->nullableString($data['emergency_contact_phone'] ?? null),
            'food_notes' => $this->nullableString($data['food_notes'] ?? null),
            'allergy_notes' => $this->nullableString($data['allergy_notes'] ?? null),
            'medical_notes' => $this->nullableString($data['medical_notes'] ?? null),
            'internal_notes' => $this->nullableString($data['internal_notes'] ?? null),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
    }

    private function syncCampStatus(PDO $pdo, int $personId, array $data): void
    {
        $campYearId = $this->nullableInt($data['camp_year_id'] ?? null);
        if ($campYearId === null) {
            return;
        }

        $isParticipant = !empty($data['is_participant']);
        $isStaff = !empty($data['is_staff']);
        if (!$isParticipant && !$isStaff) {
            $stmt = $pdo->prepare('DELETE FROM camp_person_statuses WHERE camp_year_id = :camp_year_id AND person_id = :person_id');
            $stmt->execute(['camp_year_id' => $campYearId, 'person_id' => $personId]);
            return;
        }

        $orderId = $this->nullableInt($data['order_id'] ?? null);
        if ($orderId !== null && !$this->orderBelongsToCampYear($pdo, $orderId, $campYearId)) {
            throw new RuntimeException('Orden/Zelt gehört nicht zum gewählten Lagerjahr.');
        }

        $rankSelection = $this->rankSelectionWithoutDowngrade($pdo, $campYearId, $personId, $data);

        $stmt = $pdo->prepare("INSERT INTO camp_person_statuses
            (camp_year_id, person_id, is_participant, is_staff, participant_status, staff_status, order_id, rank_label, rank_level_id, next_rank_level_id, next_rank_label, promotion_status, promotion_note, created_at, updated_at)
            VALUES (:camp_year_id, :person_id, :is_participant, :is_staff, :participant_status, :staff_status, :order_id, :rank_label, :rank_level_id, :next_rank_level_id, :next_rank_label, :promotion_status, :promotion_note, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                is_participant = VALUES(is_participant),
                is_staff = VALUES(is_staff),
                participant_status = VALUES(participant_status),
                staff_status = VALUES(staff_status),
                order_id = VALUES(order_id),
                rank_label = VALUES(rank_label),
                rank_level_id = VALUES(rank_level_id),
                next_rank_level_id = VALUES(next_rank_level_id),
                next_rank_label = VALUES(next_rank_label),
                promotion_status = VALUES(promotion_status),
                promotion_note = VALUES(promotion_note),
                updated_at = NOW()");
        $stmt->execute([
            'camp_year_id' => $campYearId,
            'person_id' => $personId,
            'is_participant' => $isParticipant ? 1 : 0,
            'is_staff' => $isStaff ? 1 : 0,
            'participant_status' => $this->allowedValue((string) ($data['participant_status'] ?? 'angemeldet'), ['angemeldet', 'warteliste', 'abgemeldet', 'abgeschlossen'], 'angemeldet'),
            'staff_status' => $this->allowedValue((string) ($data['staff_status'] ?? 'aktiv'), ['aktiv', 'inaktiv', 'angefragt'], 'aktiv'),
            'order_id' => $orderId,
            'rank_label' => $rankSelection['label'],
            'rank_level_id' => $rankSelection['id'],
            'next_rank_level_id' => $this->nullableInt($data['next_rank_level_id'] ?? null),
            'next_rank_label' => $this->nextRankLabelFromData($pdo, $campYearId, $data),
            'promotion_status' => $this->allowedValue((string) ($data['promotion_status'] ?? 'offen'), ['offen', 'vorgeschlagen', 'bestaetigt', 'abgelehnt'], 'offen'),
            'promotion_note' => $this->nullableString($data['promotion_note'] ?? null),
        ]);
        $this->audit('persons.camp_status_updated', 'person', $personId, ['camp_year_id' => $campYearId]);
    }


    private function rankSelectionWithoutDowngrade(PDO $pdo, int $campYearId, int $personId, array $data): array
    {
        $desiredId = $this->nullableInt($data['rank_level_id'] ?? null);
        $desiredLabel = $this->rankLabelFromData($pdo, $campYearId, $data);
        $desiredSort = $this->rankSortOrder($pdo, $desiredId, $desiredLabel);

        $highest = $this->highestKnownRankForPerson($pdo, $personId);
        if ($highest !== null && ($desiredSort === null || (int) $highest['sort_order'] > $desiredSort)) {
            $sameRankInCamp = $this->rankLevelForKey($pdo, $campYearId, (string) $highest['key_name']);
            if ($sameRankInCamp !== null) {
                return ['id' => (int) $sameRankInCamp['id'], 'label' => (string) $sameRankInCamp['label']];
            }
            return ['id' => null, 'label' => (string) $highest['label']];
        }

        return ['id' => $desiredId, 'label' => $desiredLabel];
    }

    private function highestKnownRankForPerson(PDO $pdo, int $personId): ?array
    {
        $stmt = $pdo->prepare("SELECT rl.key_name, rl.label, rl.sort_order
            FROM camp_person_statuses cps
            INNER JOIN rank_levels rl ON rl.id = cps.rank_level_id
            WHERE cps.person_id = :person_id
            ORDER BY rl.sort_order DESC, cps.updated_at DESC, cps.id DESC
            LIMIT 1");
        $stmt->execute(['person_id' => $personId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function rankLevelForKey(PDO $pdo, int $campYearId, string $keyName): ?array
    {
        $stmt = $pdo->prepare('SELECT id, label, sort_order FROM rank_levels WHERE camp_year_id = :camp_year_id AND key_name = :key_name LIMIT 1');
        $stmt->execute(['camp_year_id' => $campYearId, 'key_name' => $keyName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function rankSortOrder(PDO $pdo, ?int $rankLevelId, ?string $rankLabel): ?int
    {
        if ($rankLevelId !== null) {
            $stmt = $pdo->prepare('SELECT sort_order FROM rank_levels WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $rankLevelId]);
            $sort = $stmt->fetchColumn();
            if ($sort !== false) {
                return (int) $sort;
            }
        }
        $key = $this->normalizeRankKey((string) $rankLabel);
        if ($key === null) {
            return null;
        }
        return match ($key) {
            'knappe' => 10,
            'ritter' => 20,
            'freiherr' => 30,
            'graf' => 40,
            'markgraf' => 50,
            'landgraf' => 60,
            'fuerst' => 70,
            'herzog' => 80,
            'grossherzog' => 90,
            default => null,
        };
    }


    private function rankLabelFromData(PDO $pdo, int $campYearId, array $data): ?string
    {
        $rankLevelId = $this->nullableInt($data['rank_level_id'] ?? null);
        if ($rankLevelId !== null) {
            $stmt = $pdo->prepare('SELECT label FROM rank_levels WHERE id = :id AND camp_year_id = :camp_year_id LIMIT 1');
            $stmt->execute(['id' => $rankLevelId, 'camp_year_id' => $campYearId]);
            $label = $stmt->fetchColumn();
            if ($label !== false) {
                return (string) $label;
            }
        }
        return $this->nullableString($data['rank_label'] ?? null);
    }

    private function nextRankLabelFromData(PDO $pdo, int $campYearId, array $data): ?string
    {
        $rankLevelId = $this->nullableInt($data['next_rank_level_id'] ?? null);
        if ($rankLevelId !== null) {
            $stmt = $pdo->prepare('SELECT label FROM rank_levels WHERE id = :id AND camp_year_id = :camp_year_id LIMIT 1');
            $stmt->execute(['id' => $rankLevelId, 'camp_year_id' => $campYearId]);
            $label = $stmt->fetchColumn();
            if ($label !== false) {
                return (string) $label;
            }
        }
        return $this->nullableString($data['next_rank_label'] ?? null);
    }

    private function orderBelongsToCampYear(PDO $pdo, int $orderId, int $campYearId): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE id = :id AND camp_year_id = :camp_year_id');
        $stmt->execute(['id' => $orderId, 'camp_year_id' => $campYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function syncPrimaryGuardian(PDO $pdo, int $personId, array $data): void
    {
        $name = trim((string) ($data['guardian_name'] ?? ''));
        $relation = $this->nullableString($data['guardian_relation_label'] ?? null);
        $phone = $this->nullableString($data['guardian_phone'] ?? null);
        $email = $this->nullableString($data['guardian_email'] ?? null);
        $address = $this->nullableString($data['guardian_address_text'] ?? null);

        $pdo->prepare('DELETE FROM person_guardians WHERE person_id = :person_id')->execute(['person_id' => $personId]);
        if ($name === '' && $phone === null && $email === null && $address === null) {
            return;
        }
        if ($name === '') {
            $name = 'Kontakt';
        }

        $stmt = $pdo->prepare("INSERT INTO person_guardians
            (person_id, name, relation_label, phone, email, address_text, created_at, updated_at)
            VALUES (:person_id, :name, :relation_label, :phone, :email, :address_text, NOW(), NOW())");
        $stmt->execute([
            'person_id' => $personId,
            'name' => $name,
            'relation_label' => $relation,
            'phone' => $phone,
            'email' => $email,
            'address_text' => $address,
        ]);
        $this->audit('persons.guardian_updated', 'person', $personId, ['changed' => true]);
    }

    private function syncUser(PDO $pdo, int $personId, bool $loginEnabled, string $pin, bool $allowEmptyPin = false): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE person_id = :person_id LIMIT 1');
        $stmt->execute(['person_id' => $personId]);
        $userId = $stmt->fetchColumn();

        if ($userId === false) {
            if (!$loginEnabled && trim($pin) === '') {
                return;
            }
            if (trim($pin) === '') {
                throw new RuntimeException('Für einen neuen Login muss eine PIN gesetzt werden.');
            }

            $stmt = $pdo->prepare("INSERT INTO users
                (person_id, pin_hash, is_login_enabled, failed_login_count, created_at, updated_at)
                VALUES (:person_id, :pin_hash, :is_login_enabled, 0, NOW(), NOW())");
            $stmt->execute([
                'person_id' => $personId,
                'pin_hash' => $this->authService->hashPin($pin),
                'is_login_enabled' => $loginEnabled ? 1 : 0,
            ]);
            $this->audit('users.created', 'person', $personId);
            return;
        }

        if (trim($pin) !== '') {
            $stmt = $pdo->prepare("UPDATE users
                SET pin_hash = :pin_hash, is_login_enabled = :is_login_enabled, failed_login_count = 0, locked_until = NULL, updated_at = NOW()
                WHERE person_id = :person_id");
            $stmt->execute([
                'person_id' => $personId,
                'pin_hash' => $this->authService->hashPin($pin),
                'is_login_enabled' => $loginEnabled ? 1 : 0,
            ]);
            $this->audit('users.pin_changed', 'person', $personId);
            return;
        }

        if (!$allowEmptyPin && $loginEnabled) {
            throw new RuntimeException('PIN ist erforderlich.');
        }

        $stmt = $pdo->prepare("UPDATE users SET is_login_enabled = :is_login_enabled, updated_at = NOW() WHERE person_id = :person_id");
        $stmt->execute([
            'person_id' => $personId,
            'is_login_enabled' => $loginEnabled ? 1 : 0,
        ]);
    }

    private function syncRoles(PDO $pdo, int $personId, array $roleKeys): void
    {
        $roleKeys = array_values(array_unique(array_filter(array_map('strval', $roleKeys))));
        $pdo->prepare('DELETE FROM person_roles WHERE person_id = :person_id')->execute(['person_id' => $personId]);

        if ($roleKeys === []) {
            return;
        }

        $select = $pdo->prepare('SELECT id FROM roles WHERE key_name = :key_name LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO person_roles (person_id, role_id, created_at) VALUES (:person_id, :role_id, NOW())');

        foreach ($roleKeys as $roleKey) {
            $select->execute(['key_name' => $roleKey]);
            $roleId = $select->fetchColumn();
            if ($roleId !== false) {
                $insert->execute(['person_id' => $personId, 'role_id' => (int) $roleId]);
            }
        }
    }

    private function guardiansForPerson(int $personId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM person_guardians WHERE person_id = :person_id ORDER BY id ASC');
        $stmt->execute(['person_id' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function roleKeysForPerson(int $personId): array
    {
        $stmt = Database::connection()->prepare("SELECT r.key_name
            FROM roles r
            INNER JOIN person_roles pr ON pr.role_id = r.id
            WHERE pr.person_id = :person_id
            ORDER BY r.key_name ASC");
        $stmt->execute(['person_id' => $personId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function displayName(array $data): string
    {
        $display = trim((string) ($data['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        return trim((string) $data['first_name'] . ' ' . (string) $data['last_name']);
    }

    private function typeHintFromStatus(array $data): string
    {
        $participant = !empty($data['is_participant']);
        $staff = !empty($data['is_staff']);
        if ($participant && $staff) {
            return 'beides';
        }
        if ($participant) {
            return 'teilnehmer';
        }
        if ($staff) {
            return 'mitarbeiter';
        }

        $type = (string) ($data['type_hint'] ?? 'mitarbeiter');
        return in_array($type, ['teilnehmer', 'mitarbeiter', 'beides'], true) ? $type : 'mitarbeiter';
    }

    private function isParticipant(array $person): bool
    {
        if ($person['is_participant'] !== null) {
            return (int) $person['is_participant'] === 1;
        }
        return in_array((string) ($person['type_hint'] ?? ''), ['teilnehmer', 'beides'], true);
    }

    private function isStaff(array $person): bool
    {
        if ($person['is_staff'] !== null) {
            return (int) $person['is_staff'] === 1;
        }
        return in_array((string) ($person['type_hint'] ?? ''), ['mitarbeiter', 'beides'], true);
    }

    private function normalizeRankKey(string $rankLabel): ?string
    {
        $value = trim($rankLabel);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/^\d+\s*/u', '', $value) ?? $value;
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? $value;
        return match ($value) {
            'knappe', 'kappe' => 'knappe',
            'ritter' => 'ritter',
            'freiherr' => 'freiherr',
            'graf' => 'graf',
            'markgraf' => 'markgraf',
            'landgraf' => 'landgraf',
            'fuerst', 'furst' => 'fuerst',
            'herzog' => 'herzog',
            'grossherzog', 'großherzog' => 'grossherzog',
            default => null,
        };
    }


    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $int === false ? null : (int) $int;
    }

    private function allowedValue(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function hasSensitivePayload(array $data): bool
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (trim((string) ($data[$field] ?? '')) !== '') {
                return true;
            }
        }
        if (trim((string) ($data['guardian_name'] ?? '')) !== '' || trim((string) ($data['guardian_phone'] ?? '')) !== '' || trim((string) ($data['guardian_email'] ?? '')) !== '') {
            return true;
        }
        return false;
    }

    private function changedSensitiveFields(array $before, array $after): array
    {
        $changed = [];
        foreach (self::SENSITIVE_FIELDS as $field) {
            if ((string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')) {
                $changed[] = $field;
            }
        }
        foreach (['guardian_name', 'guardian_phone', 'guardian_email'] as $field) {
            if (trim((string) ($after[$field] ?? '')) !== '') {
                $changed[] = $field;
            }
        }
        return array_values(array_unique($changed));
    }

    private function audit(string $actionKey, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        $auth = Auth::user();
        $this->auditService->record($actionKey, is_array($auth) ? (int) $auth['user_id'] : null, $entityType, $entityId, $details);
    }
}
