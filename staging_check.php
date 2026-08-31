<?php
/**
 * Elawaady XDigital storefront staging preflight.
 *
 * CLI only. This script does not connect to the database and does not mutate
 * files, schema, or data. It validates the minimum PHP storefront environment
 * before a staging deployment/restart.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function env_required(string $key): ?string {
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        return null;
    }
    return trim($value);
}

function is_live_elawaady_host(string $host): bool {
    $host = strtolower(rtrim($host, '.'));
    return $host === 'elawaady.com' || str_ends_with($host, '.elawaady.com');
}

$errors = [];
$warnings = [];

$appEnv = strtolower((string) (getenv('APP_ENV') ?: ''));
$appUrl = trim((string) (getenv('APP_URL') ?: ''));

if (!in_array($appEnv, ['staging', 'development'], true)) {
    $errors[] = 'APP_ENV must be staging (or development for local checks).';
}

if ($appUrl !== '') {
    $parts = parse_url($appUrl);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        $errors[] = 'APP_URL must be a valid absolute http(s) URL.';
    } elseif (is_live_elawaady_host($host)) {
        $errors[] = 'APP_URL points at the live elawaady.com domain. Staging preflight aborted.';
    }
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    if (env_required($key) === null) {
        $errors[] = $key . ' is required for staging.';
    }
}

if (!extension_loaded('mysqli')) {
    $errors[] = 'PHP mysqli extension is required.';
}

$requiredFiles = [
    'index.php',
    'header.php',
    'footer.php',
    'db_connect.php',
    'storefront.css',
    'exd-tokens.css',
    'exd-media.css',
    'main.js',
];

foreach ($requiredFiles as $file) {
    if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = 'Missing required storefront file: ' . $file;
    }
}

if ($appUrl === '') {
    $warnings[] = 'APP_URL is not set; set it explicitly to the staging URL before cutover.';
}

if ($errors) {
    fwrite(STDERR, "EXD storefront staging preflight: FAILED\n");
    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "EXD storefront staging preflight: OK\n");
foreach ($warnings as $warning) {
    fwrite(STDOUT, ' ! ' . $warning . "\n");
}

fwrite(STDOUT, "No database connection or data mutation was performed.\n");
exit(0);
