<?php

declare(strict_types=1);

$workflowPath = __DIR__ . '/../.github/workflows/staging-handoff-final.yml';
$workflow = (string) file_get_contents($workflowPath);

$requiredMarkers = [
    'name: Staging Handoff Final',
    '- Release Handoff Integrity',
    'actions: read',
    'contents: read',
    'persist-credentials: false',
    'release-integrity-$CANDIDATE_SHA',
    'candidate_tree_sha',
    'previous_known_good_sha',
    'integrity_attestation_sha256',
    'migration_checksum_sha256',
    'handoff_state: "staging_validation_ready"',
    'deployment_mode: "validation_only"',
    'production_deploy_allowed: false',
    'staging-final-handoff-${{ github.event.workflow_run.head_sha }}',
];

foreach ($requiredMarkers as $marker) {
    if (!str_contains($workflow, $marker)) {
        fwrite(STDERR, "final staging handoff workflow is missing contract marker: {$marker}\n");
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
    'rsync ',
    'ftp ',
];

foreach ($forbiddenMarkers as $marker) {
    if (str_contains($workflow, $marker)) {
        fwrite(STDERR, "final staging handoff workflow contains forbidden deployment marker: {$marker}\n");
        exit(1);
    }
}

fwrite(STDOUT, "final staging handoff contract OK\n");
