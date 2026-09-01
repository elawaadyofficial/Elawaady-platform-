<?php
/**
 * Isolated HTTP probe for checkout retry semantics.
 *
 * Test-only endpoint. It never reads storefront catalog tables and is only
 * intended to run behind PHP's local development server in CI. It exercises
 * the production session, CSRF, checkout-intent and atomic-checkout libraries
 * over real HTTP requests with a real cookie jar.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/checkout_intent.php';
require_once __DIR__ . '/../lib/checkout.php';

auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = (int) (getenv('CHECKOUT_HTTP_USER_ID') ?: 0);
$serviceId = (int) (getenv('CHECKOUT_HTTP_SERVICE_ID') ?: 9001);
if ($userId <= 0) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'test user is not configured']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'csrf_token' => csrf_token(),
        'checkout_intent' => checkout_intent_issue($serviceId),
        'service_id' => $serviceId,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

csrf_require();

$intent = trim((string) ($_POST['checkout_intent'] ?? ''));
if (!checkout_intent_verify($intent, $serviceId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid checkout intent']);
    exit;
}

try {
    $result = checkout_with_wallet([
        'user_id' => $userId,
        'service_id' => $serviceId,
        'service_name' => 'HTTP Retry Probe',
        'quantity' => 1,
        'unit_price' => 125.00,
        'options_total' => 0,
        'mediation_fee' => 0,
        'currency' => 'EGP',
        'idempotency_key' => $intent,
    ]);

    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
