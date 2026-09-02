<?php
/**
 * EXD PHP staging deployment preflight.
 *
 * Read-only checks only: no database connection, migrations, DNS lookup,
 * HTTP request, or production deployment is attempted here.
 *
 * Usage: php tools/preflight.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$errors = [];
$passes = [];

function pass_check(string $message): void {
    global $passes;
    $passes[] = $message;
    echo "[PASS] {$message}\n";
}

function fail_check(string $message): void {
    global $errors;
    $errors[] = $message;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function env_required(string $name): ?string {
    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        fail_check("{$name} must be explicitly set.");
        return null;
    }
    pass_check("{$name} is set.");
    return trim((string) $value);
}

echo "EXD PHP staging deployment preflight\n";
echo "Mode: read-only validation; live deployment is disabled.\n\n";

if (PHP_VERSION_ID < 80000) {
    fail_check('PHP 8.0 or newer is required; found ' . PHP_VERSION . '.');
} else {
    pass_check('PHP runtime is supported (' . PHP_VERSION . ').');
}

foreach (['mysqli', 'mbstring', 'openssl', 'curl', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        fail_check("Required PHP extension is missing: {$extension}.");
    } else {
        pass_check("PHP extension loaded: {$extension}.");
    }
}

$env = strtolower((string) (env_required('APP_ENV') ?? ''));
if ($env !== '' && !in_array($env, ['development', 'staging'], true)) {
    fail_check('APP_ENV must be development or staging for this preflight.');
} elseif ($env !== '') {
    pass_check("APP_ENV is allowed for non-production handoff: {$env}.");
}

$url = env_required('APP_URL');
if ($url !== null) {
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        fail_check('APP_URL must be an absolute http or https URL.');
    } elseif ($host === 'elawaady.com' || str_ends_with($host, '.elawaady.com')) {
        fail_check('APP_URL points at the live elawaady.com domain; staging preflight refuses it.');
    } else {
        pass_check("APP_URL is non-production: {$scheme}://{$host}.");
    }
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $name) {
    env_required($name);
}

$port = env_required('DB_PORT');
if ($port !== null) {
    $portNumber = filter_var($port, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($portNumber === false) {
        fail_check('DB_PORT must be an integer between 1 and 65535.');
    } else {
        pass_check("DB_PORT is valid: {$portNumber}.");
    }
}

$key = env_required('APP_ENCRYPTION_KEY');
if ($key !== null) {
    if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
        fail_check('APP_ENCRYPTION_KEY must be exactly 64 hexadecimal characters.');
    } else {
        pass_check('APP_ENCRYPTION_KEY format is valid (value not displayed).');
    }
}

foreach (['index.php', 'database.sql', 'db_connect.php', 'migrate.php', 'tools/install.php'] as $path) {
    $absolute = $root . '/' . $path;
    if (!is_file($absolute) || filesize($absolute) === 0) {
        fail_check("Required runtime file is missing or empty: {$path}.");
    } else {
        pass_check("Runtime file present: {$path}.");
    }
}

$migrations = glob($root . '/migrations/*.sql') ?: [];
$validMigrations = array_values(array_filter(
    $migrations,
    static fn(string $path): bool => is_file($path) && filesize($path) > 0
));
if (!$validMigrations) {
    fail_check('No non-empty SQL migrations were found in migrations/.');
} else {
    pass_check(sprintf('%d non-empty SQL migration(s) found.', count($validMigrations)));
}

$uploads = $root . '/uploads';
if (!is_dir($uploads)) {
    fail_check('uploads/ directory is missing.');
} elseif (!is_writable($uploads)) {
    fail_check('uploads/ directory is not writable by the current PHP process.');
} else {
    pass_check('uploads/ directory exists and is writable.');
}

echo "\n";
if ($errors) {
    fwrite(STDERR, sprintf("Preflight failed: %d issue(s) must be fixed before staging handoff.\n", count($errors)));
    exit(1);
}

printf("Preflight passed: %d checks. Safe to continue with staging validation only.\n", count($passes));
exit(0);
