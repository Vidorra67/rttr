<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationPath
    ) {
    }

    public function run(): array
    {
        $this->ensureMigrationTable();
        $executed = [];
        $useTransaction = $this->shouldUseTransaction();

        foreach ($this->migrationFiles() as $file) {
            $migration = require $file;
            if (!is_array($migration) || empty($migration['id']) || !is_callable($migration['up'] ?? null)) {
                throw new RuntimeException('Invalid migration file: ' . basename($file));
            }

            $id = (string) $migration['id'];
            if ($this->hasRun($id)) {
                continue;
            }

            try {
                if ($useTransaction && !$this->pdo->inTransaction()) {
                    $this->pdo->beginTransaction();
                }

                $migration['up']($this->pdo);

                $stmt = $this->pdo->prepare('INSERT IGNORE INTO schema_migrations (migration, executed_at) VALUES (:migration, NOW())');
                $stmt->execute(['migration' => $id]);

                if ($useTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }

                $executed[] = $id;
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw new RuntimeException(
                    'Migration fehlgeschlagen (' . $id . '): ' . $exception->getMessage(),
                    (int) $exception->getCode(),
                    $exception
                );
            }
        }

        return $executed;
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL UNIQUE,
            executed_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function hasRun(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :migration');
        $stmt->execute(['migration' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function migrationFiles(): array
    {
        $files = glob(rtrim($this->migrationPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);
        return $files;
    }

    private function shouldUseTransaction(): bool
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            return false;
        }

        // MySQL/MariaDB führen DDL-Anweisungen wie CREATE TABLE und ALTER TABLE mit implizitem Commit aus.
        // Eine umschließende PDO-Transaktion endet dadurch unerwartet und kann in Shared-Hosting-Setups
        // den Fehler "There is no active transaction" auslösen. Die Migrationen sind deshalb für MySQL
        // bewusst idempotent aufgebaut und werden ohne äußere Transaktion ausgeführt.
        return !in_array($driver, ['mysql'], true);
    }
}
