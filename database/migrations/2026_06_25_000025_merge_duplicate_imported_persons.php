<?php

declare(strict_types=1);

return [
    'id' => '2026_06_25_000025_merge_duplicate_imported_persons',
    'up' => static function (PDO $pdo): void {
        $tableExists = static function (PDO $pdo, string $table): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name");
            $stmt->execute(['table_name' => $table]);
            return (int) $stmt->fetchColumn() > 0;
        };

        $columnExists = static function (PDO $pdo, string $table, string $column): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name");
            $stmt->execute(['table_name' => $table, 'column_name' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        };

        if (!$tableExists($pdo, 'persons')) {
            return;
        }

        $normalizeName = static function (string $name): string {
            $name = mb_strtolower(trim($name), 'UTF-8');
            $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
            $name = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;
            $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? $name;
            return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        };

        $tokenKey = static function (string $normalizedName): string {
            $tokens = array_values(array_filter(explode(' ', $normalizedName), static fn (string $token): bool => $token !== ''));
            if (count($tokens) < 2) {
                return $normalizedName;
            }
            sort($tokens, SORT_STRING);
            return implode(' ', $tokens);
        };

        $rankSort = static function (PDO $pdo, ?int $rankLevelId, ?string $rankLabel): int {
            if ($rankLevelId !== null && $rankLevelId > 0) {
                $stmt = $pdo->prepare('SELECT sort_order FROM rank_levels WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $rankLevelId]);
                $sort = $stmt->fetchColumn();
                if ($sort !== false) {
                    return (int) $sort;
                }
            }

            $value = mb_strtolower(trim((string) $rankLabel), 'UTF-8');
            $value = preg_replace('/^\d+\s*/u', '', $value) ?? $value;
            $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
            $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? $value;
            return match ($value) {
                'knappe', 'kappe' => 10,
                'ritter' => 20,
                'freiherr' => 30,
                'graf' => 40,
                'markgraf' => 50,
                'landgraf' => 60,
                'fuerst', 'furst' => 70,
                'herzog' => 80,
                'grossherzog', 'großherzog' => 90,
                default => 0,
            };
        };

        $persons = $pdo->query("SELECT id, first_name, last_name, display_name, birthdate, nickname, type_hint, is_active, created_at, updated_at
            FROM persons
            WHERE deleted_at IS NULL
            ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($persons) || count($persons) < 2) {
            return;
        }

        $parent = [];
        foreach ($persons as $person) {
            $parent[(int) $person['id']] = (int) $person['id'];
        }
        $find = static function (int $id) use (&$parent, &$find): int {
            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }
            return $parent[$id];
        };
        $union = static function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        $buckets = [];
        foreach ($persons as $person) {
            $id = (int) $person['id'];
            $display = trim((string) ($person['display_name'] ?? ''));
            $fallback = trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
            $normalized = $normalizeName($display !== '' ? $display : $fallback);
            if ($normalized === '') {
                continue;
            }

            $buckets['exact:' . $normalized][] = $id;
            $birthdate = $person['birthdate'] ?? null;
            if ($birthdate !== null && $birthdate !== '') {
                $buckets['birth_tokens:' . $birthdate . ':' . $tokenKey($normalized)][] = $id;
            }
        }

        foreach ($buckets as $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) < 2) {
                continue;
            }
            $first = $ids[0];
            foreach (array_slice($ids, 1) as $id) {
                $union($first, $id);
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $id) {
            $groups[$find($id)][] = $id;
        }

        $personById = [];
        foreach ($persons as $person) {
            $personById[(int) $person['id']] = $person;
        }

        $userPersonIds = [];
        if ($tableExists($pdo, 'users')) {
            $userPersonIds = array_flip(array_map('intval', $pdo->query('SELECT DISTINCT person_id FROM users')->fetchAll(PDO::FETCH_COLUMN)));
        }

        $chooseKeeper = static function (array $ids) use ($personById, $userPersonIds): int {
            usort($ids, static function (int $a, int $b) use ($personById, $userPersonIds): int {
                $pa = $personById[$a];
                $pb = $personById[$b];
                $scoreA = (isset($userPersonIds[$a]) ? 1000 : 0) + (!empty($pa['birthdate']) ? 100 : 0) + (!empty($pa['nickname']) ? 10 : 0) + ((int) ($pa['is_active'] ?? 0));
                $scoreB = (isset($userPersonIds[$b]) ? 1000 : 0) + (!empty($pb['birthdate']) ? 100 : 0) + (!empty($pb['nickname']) ? 10 : 0) + ((int) ($pb['is_active'] ?? 0));
                if ($scoreA === $scoreB) {
                    return $a <=> $b;
                }
                return $scoreB <=> $scoreA;
            });
            return (int) $ids[0];
        };

        $updateKeeperBlanks = static function (PDO $pdo, int $keeperId, int $sourceId) use ($columnExists): void {
            $sourceStmt = $pdo->prepare('SELECT * FROM persons WHERE id = :id LIMIT 1');
            $sourceStmt->execute(['id' => $sourceId]);
            $source = $sourceStmt->fetch(PDO::FETCH_ASSOC);
            $keeperStmt = $pdo->prepare('SELECT * FROM persons WHERE id = :id LIMIT 1');
            $keeperStmt->execute(['id' => $keeperId]);
            $keeper = $keeperStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($source) || !is_array($keeper)) {
                return;
            }

            $fields = ['birthdate', 'nickname', 'street', 'zip', 'city', 'phone', 'email', 'emergency_contact_name', 'emergency_contact_phone', 'food_notes', 'allergy_notes', 'medical_notes', 'internal_notes'];
            $sets = [];
            $params = ['id' => $keeperId];
            foreach ($fields as $field) {
                if (!$columnExists($pdo, 'persons', $field)) {
                    continue;
                }
                $keeperValue = $keeper[$field] ?? null;
                $sourceValue = $source[$field] ?? null;
                if (($keeperValue === null || $keeperValue === '') && $sourceValue !== null && $sourceValue !== '') {
                    $sets[] = $field . ' = :' . $field;
                    $params[$field] = $sourceValue;
                }
            }
            if ($sets !== []) {
                $sets[] = 'updated_at = NOW()';
                $stmt = $pdo->prepare('UPDATE persons SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $stmt->execute($params);
            }
        };

        $mergeCampStatuses = static function (PDO $pdo, int $keeperId, int $sourceId) use ($tableExists, $rankSort): void {
            if (!$tableExists($pdo, 'camp_person_statuses')) {
                return;
            }
            $sourceStatuses = $pdo->prepare('SELECT * FROM camp_person_statuses WHERE person_id = :person_id');
            $sourceStatuses->execute(['person_id' => $sourceId]);
            foreach ($sourceStatuses->fetchAll(PDO::FETCH_ASSOC) as $sourceStatus) {
                $targetStmt = $pdo->prepare('SELECT * FROM camp_person_statuses WHERE person_id = :person_id AND camp_year_id = :camp_year_id LIMIT 1');
                $targetStmt->execute(['person_id' => $keeperId, 'camp_year_id' => (int) $sourceStatus['camp_year_id']]);
                $targetStatus = $targetStmt->fetch(PDO::FETCH_ASSOC);

                if (!is_array($targetStatus)) {
                    $update = $pdo->prepare('UPDATE camp_person_statuses SET person_id = :keeper_id, updated_at = NOW() WHERE id = :id');
                    $update->execute(['keeper_id' => $keeperId, 'id' => (int) $sourceStatus['id']]);
                    continue;
                }

                $sourceSort = $rankSort($pdo, isset($sourceStatus['rank_level_id']) ? (int) $sourceStatus['rank_level_id'] : null, $sourceStatus['rank_label'] ?? null);
                $targetSort = $rankSort($pdo, isset($targetStatus['rank_level_id']) ? (int) $targetStatus['rank_level_id'] : null, $targetStatus['rank_label'] ?? null);
                $useSourceRank = $sourceSort > $targetSort;

                $update = $pdo->prepare("UPDATE camp_person_statuses SET
                        is_participant = GREATEST(is_participant, :is_participant),
                        is_staff = GREATEST(is_staff, :is_staff),
                        order_id = COALESCE(order_id, :order_id),
                        rank_label = :rank_label,
                        rank_level_id = :rank_level_id,
                        next_rank_level_id = COALESCE(next_rank_level_id, :next_rank_level_id),
                        next_rank_label = COALESCE(next_rank_label, :next_rank_label),
                        promotion_status = IF(promotion_status = 'offen', :promotion_status, promotion_status),
                        promotion_note = COALESCE(promotion_note, :promotion_note),
                        promotion_decided_at = COALESCE(promotion_decided_at, :promotion_decided_at),
                        updated_at = NOW()
                    WHERE id = :id");
                $update->execute([
                    'id' => (int) $targetStatus['id'],
                    'is_participant' => (int) ($sourceStatus['is_participant'] ?? 0),
                    'is_staff' => (int) ($sourceStatus['is_staff'] ?? 0),
                    'order_id' => $sourceStatus['order_id'] ?? null,
                    'rank_label' => $useSourceRank ? ($sourceStatus['rank_label'] ?? null) : ($targetStatus['rank_label'] ?? null),
                    'rank_level_id' => $useSourceRank ? ($sourceStatus['rank_level_id'] ?? null) : ($targetStatus['rank_level_id'] ?? null),
                    'next_rank_level_id' => $sourceStatus['next_rank_level_id'] ?? null,
                    'next_rank_label' => $sourceStatus['next_rank_label'] ?? null,
                    'promotion_status' => $sourceStatus['promotion_status'] ?? 'offen',
                    'promotion_note' => $sourceStatus['promotion_note'] ?? null,
                    'promotion_decided_at' => $sourceStatus['promotion_decided_at'] ?? null,
                ]);

                $delete = $pdo->prepare('DELETE FROM camp_person_statuses WHERE id = :id');
                $delete->execute(['id' => (int) $sourceStatus['id']]);
            }
        };

        $mergeSimpleReferences = static function (PDO $pdo, int $keeperId, int $sourceId) use ($tableExists): void {
            if ($tableExists($pdo, 'person_guardians')) {
                $stmt = $pdo->prepare('UPDATE person_guardians SET person_id = :keeper_id, updated_at = NOW() WHERE person_id = :source_id');
                $stmt->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
            }
            if ($tableExists($pdo, 'point_entries')) {
                $stmt = $pdo->prepare('UPDATE point_entries SET person_id = :keeper_id WHERE person_id = :source_id');
                $stmt->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
            }
            if ($tableExists($pdo, 'duty_assignments')) {
                $stmt = $pdo->prepare("UPDATE duty_assignments SET person_id = :keeper_id WHERE assignee_type = 'person' AND person_id = :source_id");
                $stmt->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
            }
            if ($tableExists($pdo, 'login_attempts')) {
                $stmt = $pdo->prepare('UPDATE login_attempts SET person_id = :keeper_id WHERE person_id = :source_id');
                $stmt->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
            }
            if ($tableExists($pdo, 'orders')) {
                $stmt = $pdo->prepare('UPDATE orders SET leader_person_id = :keeper_id WHERE leader_person_id = :source_id');
                $stmt->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
                $stmt = $pdo->prepare('UPDATE orders SET helper_person_id = :keeper_id WHERE helper_person_id = :source_id');
                $stmt->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
            }
        };

        $mergeRoles = static function (PDO $pdo, int $keeperId, int $sourceId) use ($tableExists): void {
            if (!$tableExists($pdo, 'person_roles')) {
                return;
            }
            $insert = $pdo->prepare('INSERT IGNORE INTO person_roles (person_id, role_id, created_at)
                SELECT :keeper_id, role_id, NOW() FROM person_roles WHERE person_id = :source_id');
            $insert->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
            $delete = $pdo->prepare('DELETE FROM person_roles WHERE person_id = :source_id');
            $delete->execute(['source_id' => $sourceId]);
        };

        $mergeUsers = static function (PDO $pdo, int $keeperId, int $sourceId) use ($tableExists): void {
            if (!$tableExists($pdo, 'users')) {
                return;
            }
            $targetUser = $pdo->prepare('SELECT id FROM users WHERE person_id = :person_id LIMIT 1');
            $targetUser->execute(['person_id' => $keeperId]);
            $targetExists = $targetUser->fetchColumn() !== false;

            $sourceUser = $pdo->prepare('SELECT id FROM users WHERE person_id = :person_id LIMIT 1');
            $sourceUser->execute(['person_id' => $sourceId]);
            $sourceUserId = $sourceUser->fetchColumn();
            if ($sourceUserId === false) {
                return;
            }

            if (!$targetExists) {
                $update = $pdo->prepare('UPDATE users SET person_id = :keeper_id, updated_at = NOW() WHERE person_id = :source_id');
                $update->execute(['keeper_id' => $keeperId, 'source_id' => $sourceId]);
                return;
            }

            $disable = $pdo->prepare('UPDATE users SET is_login_enabled = 0, updated_at = NOW() WHERE person_id = :source_id');
            $disable->execute(['source_id' => $sourceId]);
        };

        $mergeOrderStaff = static function (PDO $pdo, int $keeperId, int $sourceId) use ($tableExists): void {
            if (!$tableExists($pdo, 'order_staff_assignments')) {
                return;
            }
            $rows = $pdo->prepare('SELECT * FROM order_staff_assignments WHERE person_id = :source_id');
            $rows->execute(['source_id' => $sourceId]);
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $exists = $pdo->prepare('SELECT id FROM order_staff_assignments WHERE order_id = :order_id AND person_id = :keeper_id AND role_key = :role_key LIMIT 1');
                $exists->execute(['order_id' => (int) $row['order_id'], 'keeper_id' => $keeperId, 'role_key' => (string) $row['role_key']]);
                if ($exists->fetchColumn() !== false) {
                    $delete = $pdo->prepare('DELETE FROM order_staff_assignments WHERE id = :id');
                    $delete->execute(['id' => (int) $row['id']]);
                    continue;
                }
                $update = $pdo->prepare('UPDATE order_staff_assignments SET person_id = :keeper_id WHERE id = :id');
                $update->execute(['keeper_id' => $keeperId, 'id' => (int) $row['id']]);
            }
        };

        $mergeExamResults = static function (PDO $pdo, int $keeperId, int $sourceId) use ($tableExists): void {
            if (!$tableExists($pdo, 'exam_results')) {
                return;
            }
            $rows = $pdo->prepare('SELECT * FROM exam_results WHERE person_id = :source_id');
            $rows->execute(['source_id' => $sourceId]);
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $exists = $pdo->prepare('SELECT * FROM exam_results WHERE camp_year_id = :camp_year_id AND person_id = :keeper_id AND learning_unit_id = :learning_unit_id LIMIT 1');
                $exists->execute([
                    'camp_year_id' => (int) $row['camp_year_id'],
                    'keeper_id' => $keeperId,
                    'learning_unit_id' => (int) $row['learning_unit_id'],
                ]);
                $target = $exists->fetch(PDO::FETCH_ASSOC);
                if (!is_array($target)) {
                    $update = $pdo->prepare('UPDATE exam_results SET person_id = :keeper_id, updated_at = NOW() WHERE id = :id');
                    $update->execute(['keeper_id' => $keeperId, 'id' => (int) $row['id']]);
                    continue;
                }

                $points = max((float) ($target['points'] ?? 0), (float) ($row['points'] ?? 0));
                $note = ($target['note'] ?? '') !== '' ? $target['note'] : ($row['note'] ?? null);
                $status = ($target['result_status'] ?? '') !== '' ? $target['result_status'] : ($row['result_status'] ?? null);
                $merge = $pdo->prepare('UPDATE exam_results SET points = :points, note = :note, result_status = :result_status, updated_at = NOW() WHERE id = :id');
                $merge->execute(['points' => $points, 'note' => $note, 'result_status' => $status, 'id' => (int) $target['id']]);
                $delete = $pdo->prepare('DELETE FROM exam_results WHERE id = :id');
                $delete->execute(['id' => (int) $row['id']]);
            }
        };

        $markMerged = static function (PDO $pdo, int $keeperId, int $sourceId) use ($columnExists): void {
            $noteSql = $columnExists($pdo, 'persons', 'internal_notes')
                ? ", internal_notes = CONCAT(COALESCE(internal_notes, ''), '\nAutomatisch als Duplikat mit Person #', :keeper_id_note, ' zusammengeführt.')"
                : '';
            $stmt = $pdo->prepare("UPDATE persons SET is_active = 0, deleted_at = NOW(), updated_at = NOW(), display_name = CONCAT(display_name, ' (Duplikat #', id, ')') {$noteSql} WHERE id = :source_id");
            $params = ['source_id' => $sourceId];
            if ($noteSql !== '') {
                $params['keeper_id_note'] = $keeperId;
            }
            $stmt->execute($params);
        };

        $merged = 0;
        foreach ($groups as $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) < 2) {
                continue;
            }
            $keeperId = $chooseKeeper($ids);
            foreach ($ids as $sourceId) {
                if ($sourceId === $keeperId) {
                    continue;
                }
                $updateKeeperBlanks($pdo, $keeperId, $sourceId);
                $mergeCampStatuses($pdo, $keeperId, $sourceId);
                $mergeRoles($pdo, $keeperId, $sourceId);
                $mergeUsers($pdo, $keeperId, $sourceId);
                $mergeOrderStaff($pdo, $keeperId, $sourceId);
                $mergeExamResults($pdo, $keeperId, $sourceId);
                $mergeSimpleReferences($pdo, $keeperId, $sourceId);
                $markMerged($pdo, $keeperId, $sourceId);
                $merged++;
            }
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_versions (version, applied_at, notes, created_at)
            VALUES (:version, NOW(), :notes, NOW())");
        $stmt->execute([
            'version' => '0.14.8',
            'notes' => 'Import-Dubletten in Personen werden sicher zusammengeführt und künftige Importe erkennen bestehende Personen robuster. Zusammengeführt: ' . $merged,
        ]);
    },
];
