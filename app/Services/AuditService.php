<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use App\Support\Logger;
use PDO;
use Throwable;

final class AuditService
{
    public function record(string $actionKey, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("INSERT INTO audit_log
                (user_id, action_key, entity_type, entity_id, ip_address, user_agent, details_json, created_at)
                VALUES (:user_id, :action_key, :entity_type, :entity_id, :ip_address, :user_agent, :details_json, NOW())");
            $stmt->execute([
                'user_id' => $userId,
                'action_key' => $actionKey,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'details_json' => $details === [] ? null : json_encode($this->sanitize($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Audit log write failed', [
                'action_key' => $actionKey,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sanitize(array $details): array
    {
        foreach ($details as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'pin') || str_contains($lower, 'password') || str_contains($lower, 'token') || str_contains($lower, 'secret')) {
                $details[$key] = '[redacted]';
            }
        }
        return $details;
    }
}
