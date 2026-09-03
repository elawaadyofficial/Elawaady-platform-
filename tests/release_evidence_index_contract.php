<?php

declare(strict_types=1);

$workflowPath = __DIR__ . '/../.github/workflows/release-evidence-index.yml';
$workflow = file_get_contents($workflowPath);
if ($workflow === false) {
    fwrite(STDERR, "Unable to read release evidence index workflow\n");
    exit(1);
}

$required = [
    'name: Release Evidence Index',
    '- Staging Handoff Final',
    'actions: read',
    'contents: read',
    "head_branch == 'chatgpt/store-build'",
    'persist-credentials: false',
    'release-evidence-index-${{ github.event.workflow_run.head_sha }}',
    'staging-final-handoff-$CANDIDATE_SHA',
    'release-integrity-$CANDIDATE_SHA',
    'release-candidate-$CANDIDATE_SHA',
    'REQUIRED_WORKFLOWS_JSON="$(jq -c \' .required_workflows\' config/staging-release-manifest.json)"',
    '(.accepted_workflow_runs | keys | sort) == ($required | sort)',
    '[.accepted_workflow_runs[].head_sha] | all(. == $sha)',
    'staging_validation_evidence_complete',
    'deployment_mode: "validation_only"',
    'production_deploy_allowed: false',
    'release-evidence-index.json',
    'actions/upload-artifact@v4',
];

foreach ($required as $needle) {
    if (strpos($workflow, $needle) === false) {
        fwrite(STDERR, "Missing release evidence contract token: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'elawaady.com',
    'permissions:\n  contents: write',
    'deployments: write',
    'packages: write',
    'id-token: write',
    'ssh ',
    'scp ',
    'rsync ',
    'length == 8',
];

foreach ($forbidden as $needle) {
    if (stripos($workflow, $needle) !== false) {
        fwrite(STDERR, "Forbidden release evidence capability/reference found: {$needle}\n");
        exit(1);
    }
}

if (substr_count($workflow, 'production_deploy_allowed: false') < 1) {
    fwrite(STDERR, "Release evidence index must explicitly prohibit production deployment\n");
    exit(1);
}

if (strpos($workflow, 'sha256sum "$FINAL"') === false
    || strpos($workflow, 'sha256sum "$INTEGRITY"') === false
    || strpos($workflow, 'sha256sum "$RELEASE"') === false) {
    fwrite(STDERR, "Release evidence index must hash all three evidence documents\n");
    exit(1);
}

echo "Release evidence index contract OK\n";
