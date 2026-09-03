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
    'workflows:',
    '- Staging Readiness',
    "github.event.workflow_run.head_branch == 'chatgpt/store-build'",
    'github.event.workflow_run.head_sha',
    'persist-credentials: false',
    'repos/$REPO/branches/chatgpt/store-build',
    'tests/release_handoff_contract.php',
    'release-candidate-handoff.json',
    'deployment_mode: "validation_only"',
    'production_deploy_allowed: false',
    'Backend production safety gate',
    '.accepted_workflow_runs["Backend production safety gate"].head_sha == $sha',
] as $requiredWorkflowGuard) {
    if (!str_contains($workflow, $requiredWorkflowGuard)) {
        fail_release_semantics("release workflow guard missing: {$requiredWorkflowGuard}");
    }
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

if (!preg_match('/permissions:\s*\n(?:\s+[^\n]+\n)*\s+contents:\s*read/m', $workflow)) {
    fail_release_semantics('release workflow must retain read-only contents permission');
}

fwrite(STDOUT, "release candidate semantics: ok; validation-only handoff remains production-safe\n");
