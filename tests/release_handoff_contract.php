<?php

declare(strict_types=1);

$manifestPath = __DIR__ . '/../config/staging-release-manifest.json';
$workflowPath = __DIR__ . '/../.github/workflows/release-candidate.yml';

$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$workflow = (string) file_get_contents($workflowPath);

$required = $manifest['required_workflows'] ?? null;
if (!is_array($required) || $required === []) {
    fwrite(STDERR, "staging manifest must define required_workflows\n");
    exit(1);
}

$needles = [
    'accepted_workflow_runs',
    'candidate_sha',
    'previous_known_good_sha',
    'staging_readiness_run_id',
    'migration_checksum_sha256',
    'deployment_mode: "validation_only"',
    'production_deploy_allowed: false',
    "jq -c '.required_workflows' config/staging-release-manifest.json",
];

foreach ($needles as $needle) {
    if (!str_contains($workflow, $needle)) {
        fwrite(STDERR, "release candidate workflow is missing contract marker: {$needle}\n");
        exit(1);
    }
}

if (str_contains($workflow, 'REQUIRED_WORKFLOWS_JSON:')) {
    fwrite(STDERR, "release candidate workflow must not duplicate the manifest workflow list in env metadata\n");
    exit(1);
}

if (str_contains($workflow, 'elawaady.com')) {
    fwrite(STDERR, "release candidate workflow must not reference the production host\n");
    exit(1);
}

fwrite(STDOUT, "release handoff contract OK; required workflows are sourced from the staging manifest (" . count($required) . ")\n");
