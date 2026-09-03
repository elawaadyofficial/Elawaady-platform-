<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root . '/config/staging-release-manifest.json';
$workflowPath = $root . '/.github/workflows/staging-readiness.yml';

function fail_contract(string $message): never
{
    fwrite(STDERR, "staging manifest contract failed: {$message}\n");
    exit(1);
}

if (!is_file($manifestPath)) {
    fail_contract('manifest is missing');
}
if (!is_file($workflowPath)) {
    fail_contract('staging readiness workflow is missing');
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fail_contract('manifest is not valid JSON');
}

$requiredKeys = [
    'schema_version',
    'release_branch',
    'deployment_mode',
    'production_deploy_allowed',
    'production_host',
    'required_workflows',
    'handoff_requirements',
    'safety_boundaries',
];
foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $manifest)) {
        fail_contract("missing key: {$key}");
    }
}

if ($manifest['schema_version'] !== 1) {
    fail_contract('unsupported schema version');
}
if ($manifest['release_branch'] !== 'chatgpt/store-build') {
    fail_contract('release_branch must remain chatgpt/store-build');
}
if ($manifest['deployment_mode'] !== 'validation_only') {
    fail_contract('deployment_mode must remain validation_only');
}
if ($manifest['production_deploy_allowed'] !== false) {
    fail_contract('production deployment must remain disabled');
}
if ($manifest['production_host'] !== 'elawaady.com') {
    fail_contract('production host safety marker changed unexpectedly');
}

$required = $manifest['required_workflows'];
if (!is_array($required) || count($required) < 8 || count($required) !== count(array_unique($required))) {
    fail_contract('required_workflows must contain at least eight unique workflow names');
}

$workflow = (string) file_get_contents($workflowPath);
foreach ($required as $name) {
    if (!is_string($name) || $name === '') {
        fail_contract('required_workflows contains an invalid name');
    }
    if (!str_contains($workflow, "'{$name}'") && !str_contains($workflow, "- {$name}")) {
        fail_contract("workflow is not enforcing required gate: {$name}");
    }
}

if (!str_contains($workflow, "head_branch == 'chatgpt/store-build'")) {
    fail_contract('workflow branch guard is missing');
}
if (!str_contains($workflow, 'validation only and never deploys')) {
    fail_contract('workflow validation-only notice is missing');
}
if (!str_contains($workflow, 'run.name === name && run.head_sha === sha')) {
    fail_contract('workflow must require every prerequisite run on the exact candidate SHA');
}
if (!str_contains($workflow, 'no completed run found on exact candidate SHA')) {
    fail_contract('workflow exact-SHA failure path is missing');
}
if (str_contains($workflow, 'compareCommits') || str_contains($workflow, 'one of its ancestors') || str_contains($workflow, 'latestApplicable')) {
    fail_contract('workflow must not reuse ancestor workflow evidence');
}

$handoff = $manifest['handoff_requirements'];
foreach ([
    'record_candidate_sha',
    'record_previous_known_good_sha',
    'database_backup_required',
    'migration_state_required',
    'rollback_plan_required',
] as $key) {
    if (($handoff[$key] ?? null) !== true) {
        fail_contract("handoff requirement must remain true: {$key}");
    }
}

fwrite(STDOUT, "staging manifest contract: ok\n");
