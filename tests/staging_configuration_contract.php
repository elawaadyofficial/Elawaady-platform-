<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$contractPath = $root . '/config/staging-configuration-contract.json';
$workflowPath = $root . '/.github/workflows/staging-configuration-contract.yml';

function fail_staging_config(string $message): never
{
    fwrite(STDERR, "staging configuration contract failed: {$message}\n");
    exit(1);
}

if (!is_file($contractPath)) {
    fail_staging_config('configuration contract is missing');
}

$contract = json_decode((string) file_get_contents($contractPath), true);
if (!is_array($contract)) {
    fail_staging_config('configuration contract is not valid JSON');
}

if (($contract['schema_version'] ?? null) !== 1) {
    fail_staging_config('unsupported schema version');
}
if (($contract['environment'] ?? null) !== 'staging') {
    fail_staging_config('environment must remain staging');
}
if (($contract['deployment_mode'] ?? null) !== 'validation_only') {
    fail_staging_config('deployment_mode must remain validation_only');
}
if (($contract['production_deploy_allowed'] ?? null) !== false) {
    fail_staging_config('production deployment must remain disabled');
}

$rules = $contract['rules'] ?? [];
foreach ([
    'contract_stores_variable_names_only',
    'secret_values_must_not_be_committed',
    'runtime_values_are_supplied_outside_git',
    'production_connectivity_is_not_part_of_validation',
] as $rule) {
    if (($rules[$rule] ?? null) !== true) {
        fail_staging_config("safety rule must remain true: {$rule}");
    }
}
if (($rules['staging_app_env_must_equal'] ?? null) !== 'staging') {
    fail_staging_config('staging APP_ENV requirement changed');
}

$profiles = $contract['profiles'] ?? null;
if (!is_array($profiles) || $profiles === []) {
    fail_staging_config('profiles are missing');
}

foreach ($profiles as $profileName => $profile) {
    if (!is_array($profile)) {
        fail_staging_config("invalid profile: {$profileName}");
    }

    $exampleRel = $profile['example_file'] ?? '';
    $runtimeRel = $profile['runtime_source'] ?? '';
    $required = $profile['required_variable_names'] ?? null;
    $sensitive = $profile['sensitive_variable_names'] ?? null;

    if (!is_string($exampleRel) || !is_file($root . '/' . $exampleRel)) {
        fail_staging_config("example file missing for {$profileName}");
    }
    if (!is_string($runtimeRel) || !is_file($root . '/' . $runtimeRel)) {
        fail_staging_config("runtime source missing for {$profileName}");
    }
    if (!is_array($required) || $required === [] || count($required) !== count(array_unique($required))) {
        fail_staging_config("required variable names invalid for {$profileName}");
    }
    if (!is_array($sensitive) || array_diff($sensitive, $required) !== []) {
        fail_staging_config("sensitive variable names invalid for {$profileName}");
    }

    $example = (string) file_get_contents($root . '/' . $exampleRel);
    $runtime = (string) file_get_contents($root . '/' . $runtimeRel);

    foreach ($required as $name) {
        if (!is_string($name) || !preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            fail_staging_config("invalid environment variable name in {$profileName}");
        }
        if (!preg_match('/^' . preg_quote($name, '/') . '=/m', $example)) {
            fail_staging_config("{$exampleRel} does not document {$name}");
        }
        if (!str_contains($runtime, $name)) {
            fail_staging_config("{$runtimeRel} does not reference {$name}");
        }
    }

    foreach ($sensitive as $name) {
        if (!preg_match('/^' . preg_quote($name, '/') . '=(.*)$/m', $example, $match)) {
            fail_staging_config("unable to inspect sensitive placeholder {$name}");
        }
        $value = trim($match[1]);
        if ($value !== '' && !str_contains(strtolower($value), 'replace') && !str_contains(strtolower($value), 'example')) {
            fail_staging_config("{$exampleRel} appears to contain a non-placeholder value for {$name}");
        }
    }
}

if (is_file($workflowPath)) {
    $workflow = (string) file_get_contents($workflowPath);
    foreach (['contents: read', 'persist-credentials: false', 'php tests/staging_configuration_contract.php'] as $needle) {
        if (!str_contains($workflow, $needle)) {
            fail_staging_config("workflow safety marker missing: {$needle}");
        }
    }
    foreach (['contents: write', 'deployments: write', 'packages: write', 'id-token: write', 'secrets.', 'ssh ', 'scp ', 'rsync '] as $forbidden) {
        if (str_contains(strtolower($workflow), strtolower($forbidden))) {
            fail_staging_config("workflow contains forbidden capability: {$forbidden}");
        }
    }
}

fwrite(STDOUT, "staging configuration contract: ok for " . count($profiles) . " runtime profiles\n");
