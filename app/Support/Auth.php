<?php

declare(strict_types=1);

namespace App\Support;

final class Auth
{
    public static function check(): bool
    {
        if (!isset($_SESSION['auth']['user_id'], $_SESSION['auth']['person_id'])) {
            return false;
        }

        $timeout = (int) Config::get('app.session.timeout_minutes', 120) * 60;
        $lastActivity = (int) ($_SESSION['auth']['last_activity'] ?? time());
        if ($timeout > 0 && (time() - $lastActivity) > $timeout) {
            self::logout();
            return false;
        }

        $_SESSION['auth']['last_activity'] = time();
        return true;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return is_array($_SESSION['auth']) ? $_SESSION['auth'] : null;
    }

    public static function login(array $payload): void
    {
        $_SESSION['auth'] = [
            'user_id' => (int) $payload['user_id'],
            'person_id' => (int) $payload['person_id'],
            'display_name' => (string) $payload['display_name'],
            'roles' => array_values($payload['roles'] ?? []),
            'permissions' => array_values($payload['permissions'] ?? []),
            'logged_in_at' => date('c'),
            'last_activity' => time(),
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['auth']);
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        if (in_array('admin', $user['roles'] ?? [], true)) {
            return true;
        }

        $permissions = $user['permissions'] ?? [];
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        return in_array($role, $user['roles'] ?? [], true);
    }

    public static function requireLogin(): bool
    {
        if (self::check()) {
            return true;
        }

        Response::redirect('/login');
        return false;
    }

    public static function requirePermission(string $permission): bool
    {
        if (!self::requireLogin()) {
            return false;
        }

        if (self::can($permission)) {
            return true;
        }

        Response::html(View::render('errors/403', ['permission' => $permission]), 403);
        return false;
    }
}
