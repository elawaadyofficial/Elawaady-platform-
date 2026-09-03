<?php

declare(strict_types=1);

$workflowPath = __DIR__ . '/../.github/workflows/release-evidence-verify.yml';
$workflow = file_get_contents($workflowPath);
if ($workflow === false) {
    fwrite(STDERR, "Unable to read release evidence verifier workflow\n");
    exit(1);
}

$required = [
    'name: Release Evidence Verify',
    '- Release Evidence Index',
    'actions: read',
    'contents: read',
    "head_branch == 'chatgpt/store-build'",
    'persist-credentials: false',
    'release-evidence-index-$CANDIDATE_SHA',
    'REQUIRED_WORKFLOWS_JSON="$(jq -c \'.required_workflows\' config/staging-release-manifest.json)"',
    '(.accepted_workflow_runs | keys | sort) == ($required | sort)',
    '[.accepted_workflow_runs[].head_sha] | all(. == $sha)',
    "git rev-parse 'HEAD^{tree}'",
    "git ls-files 'migrations/*.sql'",
    '.evidence_chain.release_candidate.sha256',
    '.evidence_chain.release_integrity.sha256',
    '.evidence_chain.staging_final_handoff.sha256',
    '.accepted_workflow_runs',
    'verifier_state: "pass"',
    'deployment_mode: "validation_only"',
    'production_deploy_allowed: false',
    'release-evidence-verification.json',
    'actions/upload-artifact@v4',
];

foreach ($required as $needle) {
    if (strpos($workflow, $needle) === false) {
        fwrite(STDERR, "Missing independent verifier contract token: {$needle}\n");
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
        fwrite(STDERR, "Forbidden verifier capability/reference found: {$needle}\n");
        exit(1);
    }
}

if (substr_count($workflow, 'sha256sum "$RELEASE"') < 1
    || substr_count($workflow, 'sha256sum "$INTEGRITY"') < 1
    || substr_count($workflow, 'sha256sum "$FINAL"') < 1
    || substr_count($workflow, 'sha256sum "$INDEX"') < 1) {
    fwrite(STDERR, "Independent verifier must hash the index and all upstream evidence documents\n");
    exit(1);
}

if (strpos($workflow, 'CURRENT_MIGRATION_CHECKSUM') === false
    || strpos($workflow, 'CURRENT_TREE') === false) {
    fwrite(STDERR, "Independent verifier must recompute candidate tree and migration checksum\n");
    exit(1);
}

echo "Release evidence independent verifier contract OK\n";
