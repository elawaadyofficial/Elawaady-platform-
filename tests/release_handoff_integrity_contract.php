<?php

declare(strict_types=1);

$workflowPath = __DIR__ . '/../.github/workflows/release-handoff-integrity.yml';
$workflow = (string) file_get_contents($workflowPath);

$requiredMarkers = [
    'name: Release Handoff Integrity',
    'workflows:',
    '- Release Candidate',
    'actions: read',
    'contents: read',
    'candidate_tree_sha',
    'handoff_sha256',
    'migration_checksum_sha256',
    'source_release_candidate_run_id',
    'source_handoff_artifact_id',
    'integrity_verified: true',
    'deployment_mode: "validation_only"',
    'production_deploy_allowed: false',
    'persist-credentials: false',
];

foreach ($requiredMarkers as $marker) {
    if (!str_contains($workflow, $marker)) {
        fwrite(STDERR, "release handoff integrity workflow is missing contract marker: {$marker}\n");
        exit(1);
    }
}

$forbiddenMarkers = [
    'elawaady.com',
    'permissions:\n  contents: write',
    'deploy-pages',
    'kubectl',
    'ssh ',
    'scp ',
];

foreach ($forbiddenMarkers as $marker) {
    if (str_contains($workflow, $marker)) {
        fwrite(STDERR, "release handoff integrity workflow contains forbidden deployment marker: {$marker}\n");
        exit(1);
    }
}

fwrite(STDOUT, "release handoff integrity contract OK\n");
