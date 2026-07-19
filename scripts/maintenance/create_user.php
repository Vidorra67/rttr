<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/app/Support/bootstrap.php';

use App\Services\PersonService;
use App\Support\Database;

$options = getopt('', [
    'first:',
    'last:',
    'display::',
    'birthdate::',
    'type::',
    'pin:',
    'role::',
]);

$first = trim((string) ($options['first'] ?? ''));
$last = trim((string) ($options['last'] ?? ''));
$pin = trim((string) ($options['pin'] ?? ''));
$role = trim((string) ($options['role'] ?? 'admin'));

if ($first === '' || $last === '' || $pin === '') {
    fwrite(STDERR, "Nutzung: php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin\n");
    exit(1);
}

if (!preg_match('/^\d{4,6}$/', $pin)) {
    fwrite(STDERR, "Fehler: PIN muss 4 bis 6 Ziffern enthalten.\n");
    exit(1);
}

try {
    Database::connection()->query('SELECT 1');
    $personId = (new PersonService())->create([
        'first_name' => $first,
        'last_name' => $last,
        'display_name' => (string) ($options['display'] ?? ''),
        'birthdate' => (string) ($options['birthdate'] ?? ''),
        'type_hint' => (string) ($options['type'] ?? 'mitarbeiter'),
        'is_active' => true,
        'is_login_enabled' => true,
        'pin' => $pin,
        'roles' => [$role],
    ]);

    echo "OK Benutzer angelegt person_id={$personId} role={$role}\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR " . $exception->getMessage() . "\n");
    exit(1);
}
