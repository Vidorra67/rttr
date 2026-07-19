<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Auth;
use App\Support\Config;
use App\Support\Database;
use App\Support\Session;
use PDO;

final class AuthService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    public function loginOptions(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT u.id AS user_id, p.id AS person_id, p.display_name, p.first_name, p.last_name
            FROM users u
            INNER JOIN persons p ON p.id = u.person_id
            WHERE u.is_login_enabled = 1
              AND p.is_active = 1
              AND p.deleted_at IS NULL
            ORDER BY p.display_name ASC, p.last_name ASC, p.first_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attempt(int $personId, string $pin): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT u.*, p.display_name, p.is_active, p.deleted_at
            FROM users u
            INNER JOIN persons p ON p.id = u.person_id
            WHERE u.person_id = :person_id
            LIMIT 1");
        $stmt->execute(['person_id' => $personId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user) || (int) ($user['is_login_enabled'] ?? 0) !== 1 || (int) ($user['is_active'] ?? 0) !== 1 || $user['deleted_at'] !== null) {
            $this->recordAttempt(null, $personId, false, 'not_login_enabled');
            return ['ok' => false, 'message' => 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.'];
        }

        if ($this->isLocked($user)) {
            $this->recordAttempt((int) $user['id'], $personId, false, 'locked');
            return ['ok' => false, 'message' => 'Login derzeit gesperrt. Bitte später erneut versuchen.'];
        }

        if (!$this->validPin($pin) || !password_verify($pin, (string) $user['pin_hash'])) {
            $this->failedAttempt($pdo, (int) $user['id']);
            $this->recordAttempt((int) $user['id'], $personId, false, 'invalid_pin');
            $this->auditService->record('auth.login_failed', (int) $user['id'], 'person', $personId, ['reason' => 'invalid_pin']);
            return ['ok' => false, 'message' => 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.'];
        }

        $roles = $this->rolesForPerson($pdo, $personId);
        $permissions = $this->permissionsForRoles($pdo, $roles);

        Session::regenerate();
        Auth::login([
            'user_id' => (int) $user['id'],
            'person_id' => $personId,
            'display_name' => (string) $user['display_name'],
            'roles' => array_column($roles, 'key_name'),
            'permissions' => $permissions,
        ]);

        $stmt = $pdo->prepare("UPDATE users
            SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = :ip, updated_at = NOW()
            WHERE id = :id");
        $stmt->execute([
            'id' => (int) $user['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $this->recordAttempt((int) $user['id'], $personId, true, 'ok');
        $this->auditService->record('auth.login_success', (int) $user['id'], 'person', $personId);

        return ['ok' => true, 'message' => ''];
    }

    public function logout(): void
    {
        $auth = Auth::user();
        if (is_array($auth)) {
            $this->auditService->record('auth.logout', (int) $auth['user_id'], 'person', (int) $auth['person_id']);
        }

        Auth::logout();
        Session::destroy();
    }

    public function hashPin(string $pin): string
    {
        if (!$this->validPin($pin)) {
            throw new \InvalidArgumentException('PIN muss 4 bis 6 Ziffern enthalten.');
        }

        return password_hash($pin, PASSWORD_DEFAULT);
    }

    private function validPin(string $pin): bool
    {
        return preg_match('/^\d{4,6}$/', $pin) === 1;
    }

    private function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }

        return strtotime((string) $user['locked_until']) > time();
    }

    private function failedAttempt(PDO $pdo, int $userId): void
    {
        $limit = (int) Config::get('app.security.login_max_attempts', 5);
        $lockMinutes = (int) Config::get('app.security.login_lock_minutes', 15);

        $lockMinutes = max(1, $lockMinutes);
        $stmt = $pdo->prepare("UPDATE users
            SET failed_login_count = failed_login_count + 1,
                locked_until = CASE WHEN failed_login_count + 1 >= :limit THEN DATE_ADD(NOW(), INTERVAL {$lockMinutes} MINUTE) ELSE locked_until END,
                updated_at = NOW()
            WHERE id = :id");
        $stmt->execute([
            'id' => $userId,
            'limit' => $limit,
        ]);
    }

    private function rolesForPerson(PDO $pdo, int $personId): array
    {
        $stmt = $pdo->prepare("SELECT r.key_name, r.label
            FROM roles r
            INNER JOIN person_roles pr ON pr.role_id = r.id
            WHERE pr.person_id = :person_id
            ORDER BY r.key_name ASC");
        $stmt->execute(['person_id' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function permissionsForRoles(PDO $pdo, array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        $roleKeys = array_column($roles, 'key_name');
        if (in_array('admin', $roleKeys, true)) {
            return ['*'];
        }

        $placeholders = [];
        $params = [];
        foreach ($roleKeys as $index => $roleKey) {
            $name = ':role_' . $index;
            $placeholders[] = $name;
            $params[ltrim($name, ':')] = $roleKey;
        }

        $sql = "SELECT DISTINCT rp.permission_key
            FROM role_permissions rp
            INNER JOIN roles r ON r.id = rp.role_id
            WHERE r.key_name IN (" . implode(',', $placeholders) . ")
            ORDER BY rp.permission_key ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function recordAttempt(?int $userId, int $personId, bool $success, string $reason): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("INSERT INTO login_attempts
                (user_id, person_id, ip_address, user_agent, success, reason, created_at)
                VALUES (:user_id, :person_id, :ip_address, :user_agent, :success, :reason, NOW())");
            $stmt->execute([
                'user_id' => $userId,
                'person_id' => $personId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'success' => $success ? 1 : 0,
                'reason' => $reason,
            ]);
        } catch (\Throwable) {
            // Login must not fail only because attempt logging failed.
        }
    }
}
