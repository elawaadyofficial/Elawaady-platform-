<?php
/** Static contract for the browser-to-checkout intent boundary. */

function wiring_fail(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function wiring_expect(string $haystack, string $needle, string $message): void {
    if (!str_contains($haystack, $needle)) {
        wiring_fail($message);
    }
}

$service = file_get_contents(__DIR__ . '/../service.php');
$order   = file_get_contents(__DIR__ . '/../order_create.php');
if ($service === false || $order === false) {
    wiring_fail('could not read checkout entrypoints');
}

wiring_expect($service, 'lib/checkout_intent.php', 'service page must load checkout intent helper');
wiring_expect($service, 'checkout_intent_issue((int) $service[\'id\'])', 'service page must issue an intent bound to the rendered service');
wiring_expect($service, 'name="checkout_intent"', 'service form must submit the checkout intent');
wiring_expect($service, 'value="<?= e($checkoutIntent) ?>"', 'checkout intent must be HTML-escaped in the form');

wiring_expect($order, 'lib/checkout_intent.php', 'order handler must load checkout intent helper');
wiring_expect($order, 'checkout_intent_verify($idempotencyKey, $serviceId)', 'order handler must verify session and service binding');
wiring_expect($order, "'idempotency_key' => $idempotencyKey", 'verified intent must become the wallet checkout idempotency key');

$verifyPos = strpos($order, 'checkout_intent_verify($idempotencyKey, $serviceId)');
$quotePos  = strpos($order, 'service_quote($conn, $service, $quantity');
if ($verifyPos === false || $quotePos === false || $verifyPos > $quotePos) {
    wiring_fail('intent verification must happen before pricing and checkout work');
}

echo "PASS: service form issues a session/service-bound intent and order_create verifies it before checkout.\n";
