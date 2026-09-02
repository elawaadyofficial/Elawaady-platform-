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
if (($contract['schema_version'] ?? null) !== 1) {
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
] as $rule) {
    if (($rules[$rule] ?? null) !== true) {
        fail_repo_governance("required rule changed: {$rule}");
    }
}

$checks = $contract['required_checks'] ?? null;
if (!is_array($checks) || $checks === []) {
    fail_repo_governance('required checks are missing');
}

$seenFiles = [];
$seenNames = [];
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

fwrite(STDOUT, 'repository governance contract: ok for ' . count($checks) . " required checks\n");
