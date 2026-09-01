<?php
/** Isolated CI verification for browser checkout-intent lifecycle. */

session_id('exd-checkout-intent-ci-' . bin2hex(random_bytes(4)));
require_once __DIR__ . '/../lib/checkout_intent.php';

function intent_fail(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function intent_assert(bool $condition, string $message): void {
    if (!$condition) {
        intent_fail($message);
    }
}

$key = checkout_intent_issue(42);
intent_assert((bool) preg_match('/^ci_[a-f0-9]{48}$/', $key), 'issued key must use the strict checkout-intent format');
intent_assert(checkout_intent_verify($key, 42), 'fresh intent must verify for its service');
intent_assert(checkout_intent_verify($key, 42), 'intent must remain reusable for an HTTP retry');
intent_assert(!checkout_intent_verify($key, 43), 'intent must not cross service boundaries');
intent_assert(!checkout_intent_verify('ci_deadbeef', 42), 'malformed intent must be rejected');

$_SESSION['checkout_intents'][$key]['issued_at'] = time() - CHECKOUT_INTENT_TTL_SECONDS - 1;
intent_assert(!checkout_intent_verify($key, 42), 'expired intent must be rejected');
intent_assert(!isset($_SESSION['checkout_intents'][$key]), 'expired intent must be pruned from the session');

$lastKey = '';
for ($i = 0; $i < CHECKOUT_INTENT_MAX_ACTIVE + 5; $i++) {
    $lastKey = checkout_intent_issue(100 + $i);
}
intent_assert(count($_SESSION['checkout_intents']) <= CHECKOUT_INTENT_MAX_ACTIVE, 'session must cap active checkout intents');
intent_assert(checkout_intent_verify($lastKey, 100 + CHECKOUT_INTENT_MAX_ACTIVE + 4), 'newest intent must survive pruning');

checkout_intent_forget($lastKey);
intent_assert(!checkout_intent_verify($lastKey, 100 + CHECKOUT_INTENT_MAX_ACTIVE + 4), 'forgotten intent must not verify');

echo "PASS: checkout intents are session-scoped, service-bound, retryable, bounded and expiring.\n";
