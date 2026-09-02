<?php
/**
 * EXD — first install.
 *
 * Brings an empty database up to a working store in one command: the
 * catalogue, every migration, and a staff account whose password you type
 * here rather than find in a file.
 *
 * Safe to point at a database that already has data — it refuses, and tells
 * you to run php migrate.php instead.
 *
 *   php tools/install.php --admin=admin
 *   php tools/install.php --check      report what it would do, change nothing
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$options   = getopt('', ['admin:', 'check', 'help']);
$checkOnly = isset($options['check']);
$adminName = trim((string) ($options['admin'] ?? 'admin'));

if (isset($options['help'])) {
    echo "php tools/install.php [--admin=USERNAME] [--check]\n";
    exit(0);
}

$root = dirname(__DIR__);

function step(string $label): void {
    printf("\n\033[1m%s\033[0m\n", $label);
}

function run(string $command, string $root): int {
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        return 1;
    }
    foreach ([1, 2] as $fd) {
        while (($line = fgets($pipes[$fd])) !== false) {
            echo '  ' . $line;
        }
        fclose($pipes[$fd]);
    }
    return proc_close($process);
}

$php = escapeshellarg(PHP_BINARY);

step('1. Environment');

$env = strtolower((string) (getenv('APP_ENV') ?: ''));
$url = (string) (getenv('APP_URL') ?: '');

printf("  APP_ENV  %s\n", $env !== '' ? $env : '(not set)');
printf("  APP_URL  %s\n", $url !== '' ? $url : '(not set)');
printf("  DB_NAME  %s\n", (string) (getenv('DB_NAME') ?: '(not set)'));
printf("  encryption key %s\n", getenv('APP_ENCRYPTION_KEY') ? 'set' : 'NOT SET — provider API keys cannot be stored');

if ($env === 'production') {
    fwrite(STDERR, "\nRefusing to install against production.\n");
    exit(1);
}

// Never let a staging install point at the live store — unless the person
// running this, at the terminal, deliberately opts in. The owner is the only
// one who can make that call, and only for a run they are themselves
// executing: this cannot be set from inside a CI job or a committed file, so
// it stays a conscious, one-time human decision rather than a default that
// quietly weakens for everyone.
if ($url !== '') {
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    $isElawaady = $host === 'elawaady.com' || str_ends_with($host, '.elawaady.com');
    $ownerOverride = getenv('ELAWAADY_OWNER_DEPLOY_CONFIRM') === 'yes-deploy-elawaady-now';
    if ($isElawaady && !$ownerOverride) {
        fwrite(STDERR, "\nRefusing to install: APP_URL points at the live elawaady.com domain.\n");
        fwrite(STDERR, "If the domain owner has deliberately decided to deploy here, re-run with:\n");
        fwrite(STDERR, "    ELAWAADY_OWNER_DEPLOY_CONFIRM=yes-deploy-elawaady-now php tools/install.php --admin=owner\n");
        exit(1);
    }
    if ($isElawaady && $ownerOverride) {
        fwrite(STDERR, "\n⚠ ELAWAADY_OWNER_DEPLOY_CONFIRM is set — installing onto elawaady.com.\n");
    }
}

step('2. Catalogue');

$code = run($php . ' bootstrap.php' . ($checkOnly ? ' --check' : ''), $root);
if ($code !== 0) {
    fwrite(STDERR, "\nThe database is not empty. To add schema to a database in use run:\n    php migrate.php\n");
    exit(1);
}

step('3. Migrations');

$code = run($php . ' migrate.php' . ($checkOnly ? ' --dry-run' : ''), $root);
if ($code !== 0) {
    fwrite(STDERR, "\nMigrations failed. Nothing further was attempted.\n");
    exit(1);
}

step('4. Staff account');

if ($checkOnly) {
    echo "  would create '$adminName' as super_admin (password typed at the prompt)\n";
    echo "\n--check given: nothing was written.\n";
    exit(0);
}

$existing = fetch_one($conn, 'SELECT id FROM admin_users WHERE username = ?', 's', $adminName);
if ($existing !== null) {
    echo "  '$adminName' already exists — skipping.\n";
} else {
    echo "  Creating '$adminName'. Choose a password of at least 12 characters.\n";
    $code = run($php . ' tools/create_admin.php --username=' . escapeshellarg($adminName)
              . ' --role=super_admin', $root);
    if ($code !== 0) {
        fwrite(STDERR, "\nThe staff account was not created. Run tools/create_admin.php to finish.\n");
        exit(1);
    }
}

step('Done');

$counts = fetch_one($conn, '
    SELECT (SELECT COUNT(*) FROM store_categories)  AS categories,
           (SELECT COUNT(*) FROM store_services)    AS services,
           (SELECT COUNT(*) FROM homepage_sections) AS sections,
           (SELECT COUNT(*) FROM permissions)       AS permissions,
           (SELECT COUNT(*) FROM schema_migrations) AS migrations');

printf("  %d categories · %d services · %d homepage sections · %d permissions · %d migrations\n",
    (int) $counts['categories'], (int) $counts['services'], (int) $counts['sections'],
    (int) $counts['permissions'], (int) $counts['migrations']);

echo "\n  Storefront: " . ($url !== '' ? $url : '/') . "\n";
echo "  Dashboard:  " . ($url !== '' ? rtrim($url, '/') . '/admin/login.php' : '/admin/login.php') . "\n";
exit(0);
