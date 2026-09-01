<?php
/** Isolated CI verification for atomic and idempotent wallet checkout. */

require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/checkout.php';

function fail_now(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fail_now($message);
    }
}

function scalar_value(mysqli $conn, string $sql): string {
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
    $row = $result->fetch_row();
    return (string) ($row[0] ?? '');
}

$email = 'checkout-ci-' . bin2hex(random_bytes(4)) . '@example.test';
$passwordHash = password_hash('CI-only-password-123!', PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO platform_users (account_type, name, email, phone, password_hash, status)
     VALUES ('user', 'CI Checkout User', ?, '01000000001', ?, 'active')"
);
$stmt->bind_param('ss', $email, $passwordHash);
$stmt->execute();
$userId = (int) $conn->insert_id;
assert_true($userId > 0, 'test user should be created');

$seedBalance = wallet_post($userId, 'credit', 1000.00, 'topup', null, 'CI seed', 'system', null);
assert_true(abs($seedBalance - 1000.00) < 0.001, 'seed balance should be 1000.00');

$result = checkout_with_wallet([
    'user_id' => $userId,
    'service_name' => 'CI Atomic Service',
    'quantity' => 2,
    'unit_price' => 100.00,
    'options_total' => 25.00,
    'mediation_fee' => 5.00,
    'currency' => 'EGP',
    'target_url' => 'https://example.test/target',
]);

assert_true($result['order_id'] > 0, 'checkout should create an order');
assert_true(abs($result['total'] - 230.00) < 0.001, 'checkout total should equal 230.00');
assert_true(abs($result['balance_after'] - 770.00) < 0.001, 'wallet balance should become 770.00');
assert_true($result['replayed'] === false, 'new checkout must not be marked as replayed');

$orderId = (int) $result['order_id'];
$orderState = scalar_value($conn, "SELECT CONCAT(payment_status, ':', order_status, ':', payment_method_recorded) FROM orders WHERE id = {$orderId}");
assert_true($orderState === 'paid:new:wallet', 'order must be atomically marked paid via wallet');

$paymentCount = (int) scalar_value($conn, "SELECT COUNT(*) FROM payments WHERE order_id = {$orderId} AND method_key='wallet' AND status='confirmed'");
assert_true($paymentCount === 1, 'exactly one confirmed wallet payment should exist');

$ledgerCount = (int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE order_id = {$orderId} AND reason='order_payment' AND direction='debit'");
assert_true($ledgerCount === 1, 'exactly one linked wallet debit should exist');

$historyCount = (int) scalar_value($conn, "SELECT COUNT(*) FROM order_status_history WHERE order_id = {$orderId} AND to_status='new'");
assert_true($historyCount === 1, 'initial order timeline entry should exist');

// A retry with the same idempotency key must return the original result and
// must not create a second order, debit, payment or history entry.
$idempotencyKey = 'ci-retry-' . bin2hex(random_bytes(8));
$idempotentInput = [
    'user_id' => $userId,
    'service_name' => 'CI Retry Safe Service',
    'quantity' => 1,
    'unit_price' => 100.00,
    'currency' => 'EGP',
    'idempotency_key' => $idempotencyKey,
];

$beforeRetryOrders = (int) scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE user_id = {$userId}");
$beforeRetryLedger = (int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$userId} AND reason='order_payment'");
$beforeRetryPayments = (int) scalar_value($conn, "SELECT COUNT(*) FROM payments WHERE user_id = {$userId} AND method_key='wallet'");

$firstAttempt = checkout_with_wallet($idempotentInput);
$secondAttempt = checkout_with_wallet($idempotentInput);

assert_true($firstAttempt['replayed'] === false, 'first idempotent attempt should create the order');
assert_true($secondAttempt['replayed'] === true, 'second idempotent attempt should be replayed');
assert_true($secondAttempt['order_id'] === $firstAttempt['order_id'], 'retry must return the original order id');
assert_true($secondAttempt['order_code'] === $firstAttempt['order_code'], 'retry must return the original order code');
assert_true(abs($firstAttempt['balance_after'] - 670.00) < 0.001, 'first retry-safe purchase should debit exactly once');
assert_true(abs($secondAttempt['balance_after'] - 670.00) < 0.001, 'replayed purchase must not debit again');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE user_id = {$userId}") === $beforeRetryOrders + 1, 'same idempotency key must create exactly one order');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$userId} AND reason='order_payment'") === $beforeRetryLedger + 1, 'same idempotency key must append exactly one debit');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM payments WHERE user_id = {$userId} AND method_key='wallet'") === $beforeRetryPayments + 1, 'same idempotency key must create exactly one payment');

// Reusing a key for a materially different purchase is a caller bug and must
// fail rather than silently returning an unrelated order.
$keyReuseRejected = false;
try {
    $different = $idempotentInput;
    $different['unit_price'] = 101.00;
    checkout_with_wallet($different);
} catch (RuntimeException $e) {
    $keyReuseRejected = str_contains($e->getMessage(), 'مفتاح الطلب');
}
assert_true($keyReuseRejected, 'same idempotency key with different checkout data must be rejected');
assert_true(abs((float) scalar_value($conn, "SELECT balance FROM wallets WHERE user_id = {$userId}") - 670.00) < 0.001, 'rejected key reuse must not change wallet balance');

// Force a failure after order + ledger + wallet update, when the payment row is
// inserted. The service must roll the entire transaction back.
$conn->query("CREATE TRIGGER ci_fail_wallet_payment BEFORE INSERT ON payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CI forced payment failure'");

$beforeOrders = (int) scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE user_id = {$userId}");
$beforeLedger = (int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$userId}");
$beforeHistory = (int) scalar_value($conn, "SELECT COUNT(*) FROM order_status_history h JOIN orders o ON o.id=h.order_id WHERE o.user_id = {$userId}");
$beforeBalance = (float) scalar_value($conn, "SELECT balance FROM wallets WHERE user_id = {$userId}");

$forcedFailure = false;
try {
    checkout_with_wallet([
        'user_id' => $userId,
        'service_name' => 'CI Rollback Service',
        'quantity' => 1,
        'unit_price' => 50.00,
        'currency' => 'EGP',
    ]);
} catch (Throwable $e) {
    $forcedFailure = str_contains($e->getMessage(), 'CI forced payment failure');
}
$conn->query('DROP TRIGGER ci_fail_wallet_payment');

assert_true($forcedFailure, 'forced downstream payment failure should surface');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE user_id = {$userId}") === $beforeOrders, 'failed checkout must roll back order creation');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$userId}") === $beforeLedger, 'failed checkout must roll back ledger debit');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM order_status_history h JOIN orders o ON o.id=h.order_id WHERE o.user_id = {$userId}") === $beforeHistory, 'failed checkout must not leave timeline rows');
assert_true(abs((float) scalar_value($conn, "SELECT balance FROM wallets WHERE user_id = {$userId}") - $beforeBalance) < 0.001, 'failed checkout must restore wallet balance');

// Insufficient funds must also leave no side effects.
$beforeOrders = (int) scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE user_id = {$userId}");
$beforeLedger = (int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$userId}");
$insufficient = false;
try {
    checkout_with_wallet([
        'user_id' => $userId,
        'service_name' => 'CI Too Expensive',
        'quantity' => 1,
        'unit_price' => 9999.00,
        'currency' => 'EGP',
    ]);
} catch (RuntimeException $e) {
    $insufficient = true;
}
assert_true($insufficient, 'insufficient balance should reject checkout');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE user_id = {$userId}") === $beforeOrders, 'insufficient checkout must not create an order');
assert_true((int) scalar_value($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$userId}") === $beforeLedger, 'insufficient checkout must not append ledger rows');

echo "PASS: atomic checkout is retry-safe, commits once, and rolls back every partial failure.\n";
