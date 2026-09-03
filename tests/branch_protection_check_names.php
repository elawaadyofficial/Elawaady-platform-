<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$contract = json_decode((string) file_get_contents($root . '/config/repository-governance-contract.json'), true);

if (!is_array($contract)) {
    fwrite(STDERR, "branch protection check names: invalid governance contract\n");
    exit(1);
}

if (($contract['rules']['check_names_must_match_jobs'] ?? null) !== true) {
    fwrite(STDERR, "branch protection check names: enforcement rule is disabled\n");
    exit(1);
}

$expected = [
    '.github/workflows/backend-safety.yml' => ['workflow' => 'Backend production safety gate', 'check' => 'safety'],
    '.github/workflows/storefront-safety.yml' => ['workflow' => 'Storefront Safety', 'check' => 'PHP storefront syntax and staging preflight'],
    '.github/workflows/platform-integration.yml' => ['workflow' => 'Platform Integration', 'check' => 'Authentication, dashboard, orders, suppliers and mediation'],
    '.github/workflows/staging-configuration-contract.yml' => ['workflow' => 'Staging Configuration Contract', 'check' => 'validate-staging-configuration'],
];

$checks = $contract['required_checks'] ?? [];
$seen = [];
foreach ($checks as $check) {
    if (!is_array($check)) {
        continue;
    }
    $file = $check['workflow_file'] ?? '';
    if (!isset($expected[$file])) {
        continue;
    }

    $workflowName = $check['workflow_name'] ?? '';
    $checkName = $check['check_name'] ?? '';
    if ($workflowName !== $expected[$file]['workflow'] || $checkName !== $expected[$file]['check']) {
        fwrite(STDERR, "branch protection check names: contract mismatch for {$file}\n");
        exit(1);
    }

    $workflow = (string) file_get_contents($root . '/' . $file);
    if (!preg_match('/^name:\s*' . preg_quote($workflowName, '/') . '\s*$/m', $workflow)) {
        fwrite(STDERR, "branch protection check names: workflow name mismatch for {$file}\n");
        exit(1);
    }

    $explicitJobName = preg_match('/^\s{4}name:\s*' . preg_quote($checkName, '/') . '\s*$/m', $workflow) === 1;
    $implicitJobName = preg_match('/^\s{2}' . preg_quote($checkName, '/') . ':\s*$/m', $workflow) === 1;
    if (!$explicitJobName && !$implicitJobName) {
        fwrite(STDERR, "branch protection check names: GitHub check name no longer matches a job in {$file}: {$checkName}\n");
        exit(1);
    }

    $seen[$file] = true;
}

foreach (array_keys($expected) as $file) {
    if (!isset($seen[$file])) {
        fwrite(STDERR, "branch protection check names: required workflow missing from contract: {$file}\n");
        exit(1);
    }
}

fwrite(STDOUT, "branch protection check names: ok; exact contexts are locked for 4 required checks\n");
