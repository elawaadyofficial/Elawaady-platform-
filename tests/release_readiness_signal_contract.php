<?php

declare(strict_types=1);

$workflow = __DIR__ . '/../.github/workflows/release-readiness-signal.yml';
if (!is_file($workflow)) {
    fwrite(STDERR, "Missing release readiness signal workflow\n");
    exit(1);
}

$text = file_get_contents($workflow);
if ($text === false) {
    fwrite(STDERR, "Unable to read release readiness signal workflow\n");
    exit(1);
}

$required = [
    'name: Release Readiness Signal',
    '- Release Evidence Verify',
    "github.event.workflow_run.head_branch == 'chatgpt/store-build'",
    "github.event.workflow_run.conclusion == 'success'",
    'actions: read',
    'contents: read',
    'persist-credentials: false',
    'release-evidence-verification-',
    'release-readiness-signal-',
    'production_deploy_allowed: false',
    'deployment_mode: "validation_only"',
    'readiness_state: "staging_handoff_ready"',
    'previous_known_good_sha',
    'verifier_run_id',
    'verifier_artifact_id',
];

foreach ($required as $needle) {
    if (!str_contains($text, $needle)) {
        fwrite(STDERR, "Missing required contract token: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'elawaady.com',
    'ssh ',
    'scp ',
    'rsync ',
    'appleboy/',
    'contents: write',
    'actions: write',
    'deployments: write',
    'packages: write',
];

foreach ($forbidden as $needle) {
    if (stripos($text, $needle) !== false) {
        fwrite(STDERR, "Forbidden deployment-capability token found: {$needle}\n");
        exit(1);
    }
}

echo "Release readiness signal workflow contract OK\n";
