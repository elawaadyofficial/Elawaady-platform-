<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function fail_release_semantics(string $message): never
{
    fwrite(STDERR, "release candidate semantics failed: {$message}\n");
    exit(1);
}

$checklistPath = $root . '/DEPLOYMENT_CHECKLIST.md';
$workflowPath = $root . '/.github/workflows/release-candidate.yml';

if (!is_file($checklistPath) || !is_file($workflowPath)) {
    fail_release_semantics('required release files are missing');
}

$checklist = (string) file_get_contents($checklistPath);
$workflow = (string) file_get_contents($workflowPath);

foreach ([
    '## Safety Boundary',
    '## Environment',
    '## Preflight',
    '## Isolated Deployment Rehearsal',
    '## Database',
    '## Storefront QA',
    '## Performance',
    '## Security / Release Gate',
    '## Release Process',
] as $requiredSection) {
    if (!str_contains($checklist, $requiredSection)) {
        fail_release_semantics("deployment checklist section missing: {$requiredSection}");
    }
}

foreach ([
    'APP_ENV=staging',
    'tools/preflight.php',
    'tools/migration_preflight.php',
    'tools/staging_rehearsal.sh',
    '127.0.0.1',
    'elawaady.com',
    'production database',
    'branch protection',
    'required checks',
] as $requiredSafetyConcept) {
    if (stripos($checklist, $requiredSafetyConcept) === false) {
        fail_release_semantics("deployment checklist safety concept missing: {$requiredSafetyConcept}");
    }
}

foreach ([
    'workflow_run:',
    'Staging Readiness',
    'chatgpt/store-build',
    'github.event.workflow_run.head_sha',
    'persist-credentials: false',
    'tests/release_handoff_contract.php',
    'release-candidate-handoff.json',
    'validation_only',
    'production_deploy_allowed',
    'Backend production safety gate',
] as $requiredWorkflowConcept) {
    if (!str_contains($workflow, $requiredWorkflowConcept)) {
        fail_release_semantics("release workflow safety concept missing: {$requiredWorkflowConcept}");
    }
}

if (!str_contains($workflow, "permissions:\n  actions: read\n  contents: read")) {
    fail_release_semantics('release workflow permissions must remain actions:read and contents:read');
}

if (!str_contains($workflow, 'deployment_mode: "validation_only"') || !str_contains($workflow, 'production_deploy_allowed: false')) {
    fail_release_semantics('release handoff must remain validation-only with production deployment disabled');
}

if (!str_contains($workflow, 'CANDIDATE_SHA: ${{ github.event.workflow_run.head_sha }}')) {
    fail_release_semantics('release candidate SHA must come from the completed staging-readiness run');
}

if (!str_contains($workflow, 'CURRENT_SHA="$(gh api "repos/$REPO/branches/chatgpt/store-build"')) {
    fail_release_semantics('release candidate must be compared with the current governed branch HEAD');
}

if (!str_contains($workflow, 'Backend production safety must always be proven on the exact candidate SHA.')) {
    fail_release_semantics('backend safety exact-SHA rule is missing');
}

foreach ([
    'contents: write',
    'deployments: write',
    'packages: write',
    'id-token: write',
    'docker login',
    'kubectl ',
    'scp ',
    'rsync ',
    'ssh ',
] as $forbiddenWorkflowCapability) {
    if (stripos($workflow, $forbiddenWorkflowCapability) !== false) {
        fail_release_semantics("release workflow gained forbidden capability: {$forbiddenWorkflowCapability}");
    }
}

fwrite(STDOUT, "release candidate semantics: ok; validation-only handoff remains production-safe\n");
