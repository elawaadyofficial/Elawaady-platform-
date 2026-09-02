<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$contractPath = $root . '/config/repository-governance-contract.json';

function fail_repo_governance(string $message): never
{
    fwrite(STDERR, "repository governance contract failed: {$message}\n");
    exit(1);
}

if (!is_file($contractPath)) {
    fail_repo_governance('contract is missing');
}

$contract = json_decode((string) file_get_contents($contractPath), true);
if (!is_array($contract)) {
    fail_repo_governance('contract is not valid JSON');
}
if (($contract['schema_version'] ?? null) !== 2) {
    fail_repo_governance('unsupported schema version');
}
if (($contract['branch'] ?? null) !== 'chatgpt/store-build') {
    fail_repo_governance('governed branch changed unexpectedly');
}
if (($contract['production_deploy_allowed'] ?? null) !== false) {
    fail_repo_governance('production deployment must remain disabled');
}

$rules = $contract['rules'] ?? [];
foreach ([
    'required_checks_must_exist',
    'workflow_names_must_match',
    'validation_workflows_must_be_read_only',
    'live_deployment_must_remain_disabled',
    'branch_protection_readiness_must_match_workflows',
    'runtime_authority_must_match_repository',
] as $rule) {
    if (($rules[$rule] ?? null) !== true) {
        fail_repo_governance("required rule changed: {$rule}");
    }
}

$runtime = $contract['runtime_authority'] ?? null;
if (!is_array($runtime)) {
    fail_repo_governance('runtime authority is missing');
}
if (($runtime['production_runtime'] ?? null) !== 'php') {
    fail_repo_governance('authoritative production runtime must remain PHP');
}
if (($runtime['python_backend_reference_only'] ?? null) !== true) {
    fail_repo_governance('python backend must remain reference-only');
}
foreach ([
    'entrypoint' => 'index.php',
    'bootstrap_schema' => 'database.sql',
    'installer' => 'tools/install.php',
    'preflight' => 'tools/preflight.php',
] as $field => $expectedPath) {
    if (($runtime[$field] ?? null) !== $expectedPath) {
        fail_repo_governance("runtime authority path changed unexpectedly: {$field}");
    }
    if (!is_file($root . '/' . $expectedPath) || filesize($root . '/' . $expectedPath) === 0) {
        fail_repo_governance("authoritative runtime file is missing or empty: {$expectedPath}");
    }
}

$preflight = (string) file_get_contents($root . '/tools/preflight.php');
foreach ([
    "['development', 'staging']",
    "elawaady.com",
    "APP_ENCRYPTION_KEY",
    "['mysqli', 'mbstring', 'openssl', 'curl', 'fileinfo']",
] as $requiredGuard) {
    if (!str_contains($preflight, $requiredGuard)) {
        fail_repo_governance("PHP preflight guard is missing: {$requiredGuard}");
    }
}

$checks = $contract['required_checks'] ?? null;
if (!is_array($checks) || $checks === []) {
    fail_repo_governance('required checks are missing');
}

$seenFiles = [];
$seenNames = [];
$pathScopedChecks = [];
$missingPullRequestChecks = [];
foreach ($checks as $check) {
    if (!is_array($check)) {
        fail_repo_governance('invalid required-check entry');
    }
    $file = $check['workflow_file'] ?? '';
    $name = $check['workflow_name'] ?? '';
    if (!is_string($file) || !is_string($name) || $file === '' || $name === '') {
        fail_repo_governance('required-check fields are invalid');
    }
    if (isset($seenFiles[$file]) || isset($seenNames[$name])) {
        fail_repo_governance('duplicate workflow file or name');
    }
    $seenFiles[$file] = true;
    $seenNames[$name] = true;

    $path = $root . '/' . $file;
    if (!is_file($path)) {
        fail_repo_governance("required workflow missing: {$file}");
    }
    $workflow = (string) file_get_contents($path);
    if (!preg_match('/^name:\s*' . preg_quote($name, '/') . '\s*$/m', $workflow)) {
        fail_repo_governance("workflow name mismatch: {$file}");
    }
    if (!str_contains($workflow, 'contents: read')) {
        fail_repo_governance("workflow must remain read-only: {$file}");
    }
    foreach (['contents: write', 'deployments: write', 'packages: write', 'id-token: write'] as $forbidden) {
        if (str_contains(strtolower($workflow), strtolower($forbidden))) {
            fail_repo_governance("forbidden workflow permission in {$file}: {$forbidden}");
        }
    }

    if (preg_match('/^\s+paths:\s*$/m', $workflow) === 1 || preg_match('/^\s+paths-ignore:\s*$/m', $workflow) === 1) {
        $pathScopedChecks[] = $name;
    }
    if (preg_match('/^\s{2}pull_request:\s*$/m', $workflow) !== 1) {
        $missingPullRequestChecks[] = $name;
    }
}

foreach ([
    'Backend production safety gate',
    'Storefront Safety',
    'Platform Integration',
    'Staging Configuration Contract',
] as $requiredName) {
    if (!isset($seenNames[$requiredName])) {
        fail_repo_governance("core required check missing: {$requiredName}");
    }
}

if ($missingPullRequestChecks !== []) {
    fail_repo_governance('required checks must report on every pull request: ' . implode(', ', $missingPullRequestChecks));
}

$branchProtection = $contract['branch_protection'] ?? null;
if (!is_array($branchProtection)) {
    fail_repo_governance('branch protection readiness is missing');
}
$ready = $branchProtection['ready'] ?? null;
if (!is_bool($ready)) {
    fail_repo_governance('branch protection ready flag must be boolean');
}
$mode = $branchProtection['mode'] ?? null;
$expectedMode = $ready ? 'ready_to_enable' : 'planned';
if ($mode !== $expectedMode) {
    fail_repo_governance("branch protection mode must be {$expectedMode}");
}
$blockers = $branchProtection['blockers'] ?? null;
if (!is_array($blockers)) {
    fail_repo_governance('branch protection blockers must be an array');
}
if ($ready && $blockers !== []) {
    fail_repo_governance('branch protection cannot be ready while blockers remain');
}
if ($ready && $pathScopedChecks !== []) {
    fail_repo_governance('branch protection cannot be ready while required checks are path-scoped: ' . implode(', ', $pathScopedChecks));
}
if (!$ready && $blockers === []) {
    fail_repo_governance('branch protection must list at least one blocker when not ready');
}
if ($pathScopedChecks !== [] && !in_array('required_checks_are_path_scoped', $blockers, true)) {
    fail_repo_governance('path-scoped required checks must be recorded as a branch protection blocker');
}
if ($pathScopedChecks === [] && in_array('required_checks_are_path_scoped', $blockers, true)) {
    fail_repo_governance('path-scoped blocker is stale because all required checks now report on every pull request');
}
if (in_array('backend_runtime_not_committed', $blockers, true)) {
    fail_repo_governance('legacy Python backend import must not block the authoritative PHP runtime');
}

fwrite(STDOUT, 'repository governance contract: ok for ' . count($checks) . ' required checks; runtime=php; preflight=locked; branch protection ready=' . ($ready ? 'true' : 'false') . "\n");
