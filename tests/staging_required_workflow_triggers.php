<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$readinessPath = $root . '/.github/workflows/staging-readiness.yml';

function fail_staging_trigger_contract(string $message): never
{
    fwrite(STDERR, "staging required workflow trigger contract failed: {$message}\n");
    exit(1);
}

if (!is_file($readinessPath)) {
    fail_staging_trigger_contract('staging-readiness.yml is missing');
}

$readiness = (string) file_get_contents($readinessPath);
if (!preg_match('/workflow_run:\s*\n\s+workflows:\s*\n((?:\s+-\s+.+\n)+)/', $readiness, $match)) {
    fail_staging_trigger_contract('could not read workflow_run prerequisites');
}

$required = [];
foreach (preg_split('/\R/', trim($match[1])) as $line) {
    $name = trim(preg_replace('/^-\s*/', '', trim($line)) ?? '');
    if ($name !== '') {
        $required[] = $name;
    }
}

if (count($required) !== 10) {
    fail_staging_trigger_contract('expected exactly 10 staging prerequisite workflows, found ' . count($required));
}
if (count(array_unique($required)) !== count($required)) {
    fail_staging_trigger_contract('staging prerequisite workflow names must be unique');
}

$workflowFiles = glob($root . '/.github/workflows/*.yml') ?: [];
$byName = [];
foreach ($workflowFiles as $path) {
    $workflow = (string) file_get_contents($path);
    if (preg_match('/^name:\s*(.+?)\s*$/m', $workflow, $nameMatch) !== 1) {
        continue;
    }
    $name = trim($nameMatch[1], " \t\n\r\0\x0B\"'");
    if (isset($byName[$name])) {
        fail_staging_trigger_contract("duplicate workflow name found: {$name}");
    }
    $byName[$name] = ['path' => $path, 'content' => $workflow];
}

foreach ($required as $name) {
    if (!isset($byName[$name])) {
        fail_staging_trigger_contract("required workflow file not found for: {$name}");
    }

    $workflow = $byName[$name]['content'];
    $relative = str_replace($root . '/', '', $byName[$name]['path']);

    if (preg_match('/^  push:\s*\n((?:(?: {4,}.*)|\s*)\n)*/m', $workflow, $pushMatch) !== 1) {
        fail_staging_trigger_contract("{$name} must run on push for exact-SHA evidence ({$relative})");
    }

    $pushBlock = $pushMatch[0];
    if (!str_contains($pushBlock, 'chatgpt/store-build')) {
        fail_staging_trigger_contract("{$name} push trigger must include chatgpt/store-build ({$relative})");
    }
    if (preg_match('/^\s+paths(?:-ignore)?:\s*/m', $pushBlock) === 1) {
        fail_staging_trigger_contract("{$name} push trigger must not be path-scoped ({$relative})");
    }
}

foreach ($required as $name) {
    if (!str_contains($readiness, "'{$name}'")) {
        fail_staging_trigger_contract("staging-readiness exact-SHA verifier is missing prerequisite: {$name}");
    }
}

if (!str_contains($readiness, 'run.name === name && run.head_sha === sha')) {
    fail_staging_trigger_contract('staging-readiness must keep exact candidate SHA filtering');
}
if (str_contains($readiness, 'compareCommits') || str_contains($readiness, 'latestApplicable')) {
    fail_staging_trigger_contract('ancestor workflow evidence reuse must remain disabled');
}

fwrite(STDOUT, 'staging required workflow trigger contract: ok for ' . count($required) . " exact-SHA prerequisites\n");
