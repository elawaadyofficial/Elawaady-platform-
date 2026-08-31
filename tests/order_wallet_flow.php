<?php
/**
 * Isolated order + wallet integration smoke test.
 *
 * Intended for CI against an ephemeral MariaDB database only. It verifies that
 * wallet ledger invariants hold when an order payment is posted and that an
 * insufficient-funds attempt leaves both balance and ledger unchanged.
 */

require_once __DIR__ . '/../lib/wallet.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function scalar(mysqli $conn, string $sql): string {
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
    $row = $result->fetch_row();
    return (string) ($row[0] ?? '');
}

$email = 'wallet-ci-' . bin2hex(random_bytes(4)) . '@example.test';
$passwordHash = password_hash('CI-only-password-123!', PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO platform_users (account_type, name, email, phone, password_hash, status)
     VALUES ('user', 'CI Wallet User', ?, '01000000000', ?, 'active')"
);
$stmt->bind_param('ss', $email, $passwordHash);
$stmt->execute();
$userId = (int) $conn->insert_id;
assert_true($userId > 0, 'test user should be created');

$afterTopup = wallet_post($userId, 'credit', 1000.00, 'topup', null, 'CI seed balance', 'system', null);
assert_true(abs($afterTopup - 1000.00) < 0.001, 'top-up should produce a 1000.00 balance');

$orderCode = 'CI-' . strtoupper(bin2hex(random_bytes(5)));
$stmt = $conn->prepare(
    "INSERT INTO orders
        (order_code, user_id, service_name, quantity, unit_price, total_price, currency, payment_status, order_status)
     VALUES (?, ?, 'CI test service', 1, 250.00, 250.00, 'EGP', 'pending', 'new')"
);
$stmt->bind_param('si', $orderCode, $userId);
$stmt->execute();
$orderId = (int) $conn->insert_id;
assert_true($orderId > 0, 'test order should be created');

$afterPayment = wallet_post($userId, 'debit', 250.00, 'order_payment', $orderId, 'CI order payment', 'user', $userId);
assert_true(abs($afterPayment - 750.00) < 0.001, 'order debit should leave 750.00');

$wallet = wallet_for($userId);
$walletId = (int) $wallet['id'];
assert_true(abs((float) $wallet['balance'] - 750.00) < 0.001, 'cached wallet balance should be 750.00');

$linkedOrderId = (int) scalar(
    $conn,
    "SELECT order_id FROM wallet_transactions
      WHERE wallet_id = {$walletId} AND reason = 'order_payment'
      ORDER BY id DESC LIMIT 1"
);
assert_true($linkedOrderId === $orderId, 'order payment ledger row must reference the order');

$beforeCount = (int) scalar($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = {$walletId}");
$insufficientRejected = false;
try {
    wallet_post($userId, 'debit', 800.00, 'order_payment', $orderId, 'must fail', 'user', $userId);
} catch (RuntimeException $e) {
    $insufficientRejected = true;
}
assert_true($insufficientRejected, 'insufficient funds must be rejected');

$afterCount = (int) scalar($conn, "SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = {$walletId}");
assert_true($afterCount === $beforeCount, 'failed debit must not append a ledger row');

$wallet = wallet_for($userId);
assert_true(abs((float) $wallet['balance'] - 750.00) < 0.001, 'failed debit must not change balance');

// Simulate accidental cache drift. Reconciliation must repair from immutable ledger truth.
$conn->query("UPDATE wallets SET balance = 999.00 WHERE id = {$walletId}");
$reconciliation = wallet_reconcile($userId);
assert_true($reconciliation['repaired'] === true, 'reconciliation should detect cache drift');
assert_true(abs((float) $reconciliation['ledger'] - 750.00) < 0.001, 'ledger truth should remain 750.00');

$wallet = wallet_for($userId);
assert_true(abs((float) $wallet['balance'] - 750.00) < 0.001, 'reconciliation should restore cached balance to ledger truth');

echo "PASS: order + wallet transactional invariants hold in isolated CI.\n";
