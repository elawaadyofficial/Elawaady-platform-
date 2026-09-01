<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$contractPath = $root . '/config/staging-environment-contract.json';
$passengerPath = $root . '/backend/passenger_wsgi.py';
$fallbackPath = $root . '/backend/staging_fallback.py';

function fail_contract(string $message): never
{
    fwrite(STDERR, "STAGING CONTRACT FAIL: {$message}\n");
    exit(1);
}

foreach ([$contractPath, $passengerPath, $fallbackPath] as $path) {
    if (!is_file($path)) {
        fail_contract("missing required file: {$path}");
    }
}

$contract = json_decode((string) file_get_contents($contractPath), true);
if (!is_array($contract)) {
    fail_contract('contract JSON is invalid');
}

if (($contract['schema_version'] ?? null) !== 1) {
    fail_contract('unexpected schema version');
}
if (($contract['environment'] ?? null) !== 'staging') {
    fail_contract('environment must remain staging');
}
if (($contract['deployment_mode'] ?? null) !== 'validation_only') {
    fail_contract('deployment mode must remain validation_only');
}
if (($contract['production_deploy_allowed'] ?? true) !== false) {
    fail_contract('production deployment must remain forbidden');
}

$db = $contract['database'] ?? [];
foreach (['must_be_isolated', 'allow_production_host', 'allow_production_database', 'allow_shared_credentials'] as $key) {
    if (!array_key_exists($key, $db)) {
        fail_contract("database contract missing {$key}");
    }
}
if ($db['must_be_isolated'] !== true || $db['allow_production_host'] !== false || $db['allow_production_database'] !== false || $db['allow_shared_credentials'] !== false) {
    fail_contract('staging database isolation contract was weakened');
}

$health = $contract['health'] ?? [];
$paths = $health['paths'] ?? [];
foreach (['/health', '/api/v1/health'] as $requiredPath) {
    if (!in_array($requiredPath, $paths, true)) {
        fail_contract("missing health path {$requiredPath}");
    }
}
if (($health['ready_status_code'] ?? null) !== 200 || ($health['bootstrap_incomplete_status_code'] ?? null) !== 503) {
    fail_contract('health status code contract changed');
}
if (($health['bootstrap_incomplete_status'] ?? null) !== 'bootstrap_incomplete') {
    fail_contract('bootstrap fallback state changed');
}

$passenger = (string) file_get_contents($passengerPath);
$fallback = (string) file_get_contents($fallbackPath);

foreach (["APP_ENV = os.getenv(\"APP_ENV\", \"staging\")", "if APP_ENV in {\"production\", \"prod\"}", 'raise'] as $needle) {
    if (!str_contains($passenger, $needle)) {
        fail_contract("passenger production fail-fast guard missing: {$needle}");
    }
}
foreach (['/health', '/api/v1/health', '503 Service Unavailable', 'bootstrap_incomplete', 'Cache-Control', 'no-store', 'X-Robots-Tag', 'noindex, nofollow'] as $needle) {
    if (!str_contains($fallback, $needle)) {
        fail_contract("staging fallback contract missing: {$needle}");
    }
}

$forbidden = ['elawaady.com', 'ssh ', 'scp ', 'rsync ', 'appleboy/', 'webfactory/ssh-agent'];
$combined = strtolower((string) file_get_contents($contractPath));
foreach ($forbidden as $needle) {
    if (str_contains($combined, strtolower($needle))) {
        fail_contract("forbidden deployment/live-store reference found: {$needle}");
    }
}

echo "Staging environment contract: PASS\n";
