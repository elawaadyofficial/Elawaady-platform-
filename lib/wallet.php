<?php
/**
 * EXD — the wallet ledger.
 *
 * A balance is never written directly. Every movement of money appends one
 * immutable row to wallet_transactions carrying the balance that followed it,
 * and wallets.balance is a cache of that running total. A mistake is corrected
 * by posting the opposite entry, never by editing history.
 */

require_once __DIR__ . '/../db_connect.php';

/** Fetch a wallet, creating it if this account somehow has none. */
function wallet_for(int $userId): array {
    global $conn;

    $wallet = fetch_one($conn, 'SELECT * FROM wallets WHERE user_id = ? LIMIT 1', 'i', $userId);
    if ($wallet !== null) {
        return $wallet;
    }

    $stmt = $conn->prepare('INSERT INTO wallets (user_id) VALUES (?)');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return fetch_one($conn, 'SELECT * FROM wallets WHERE user_id = ? LIMIT 1', 'i', $userId);
}

/**
 * Post one entry. Returns the new balance.
 *
 * The row is written inside a transaction that locks the wallet, so two
 * concurrent debits cannot both read the same starting balance.
 */
function wallet_post(
    int $userId,
    string $direction,
    float $amount,
    string $reason,
    ?int $orderId = null,
    string $note = '',
    string $createdByType = 'system',
    ?int $createdById = null
): float {
    global $conn;

    if (!in_array($direction, ['credit', 'debit'], true)) {
        throw new InvalidArgumentException('direction must be credit or debit');
    }
    if ($amount <= 0) {
        throw new InvalidArgumentException('amount must be positive');
    }

    $conn->begin_transaction();
    try {
        $row = fetch_one($conn, 'SELECT id, balance, is_frozen FROM wallets WHERE user_id = ? FOR UPDATE', 'i', $userId);
        if ($row === null) {
            $create = $conn->prepare('INSERT INTO wallets (user_id) VALUES (?)');
            $create->bind_param('i', $userId);
            $create->execute();
            $row = fetch_one($conn, 'SELECT id, balance, is_frozen FROM wallets WHERE user_id = ? FOR UPDATE', 'i', $userId);
        }

        if ((int) $row['is_frozen'] === 1) {
            throw new RuntimeException('المحفظة موقوفة.');
        }

        $walletId = (int) $row['id'];
        $balance  = (float) $row['balance'];
        $after    = $direction === 'credit' ? $balance + $amount : $balance - $amount;

        if ($after < 0) {
            throw new RuntimeException('الرصيد غير كافٍ.');
        }

        $insert = $conn->prepare(
            'INSERT INTO wallet_transactions
                (wallet_id, user_id, direction, amount, balance_after, reason, order_id, note,
                 created_by_type, created_by_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->bind_param(
            'iisddsissi',
            $walletId, $userId, $direction, $amount, $after, $reason, $orderId, $note,
            $createdByType, $createdById
        );
        $insert->execute();

        $update = $conn->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
        $update->bind_param('di', $after, $walletId);
        $update->execute();

        $conn->commit();
        return $after;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Recompute the cached balance from the ledger. The ledger is the truth; this
 * only repairs the cache, and it reports whether the two disagreed.
 */
function wallet_reconcile(int $userId): array {
    global $conn;

    $wallet = wallet_for($userId);
    $walletId = (int) $wallet['id'];

    $row = fetch_one(
        $conn,
        "SELECT COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END), 0) AS total
           FROM wallet_transactions WHERE wallet_id = ?",
        'i',
        $walletId
    );

    $ledger = round((float) ($row['total'] ?? 0), 2);
    $cached = round((float) $wallet['balance'], 2);

    if (abs($ledger - $cached) >= 0.01) {
        $stmt = $conn->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
        $stmt->bind_param('di', $ledger, $walletId);
        $stmt->execute();
    }

    return ['ledger' => $ledger, 'cached' => $cached, 'repaired' => abs($ledger - $cached) >= 0.01];
}

function wallet_transactions(int $userId, int $limit = 25): array {
    global $conn;
    $limit = max(1, min(200, $limit));
    return fetch_all(
        $conn,
        'SELECT direction, amount, balance_after, reason, order_id, note, created_at
           FROM wallet_transactions WHERE user_id = ?
          ORDER BY id DESC LIMIT ' . $limit,
        'i',
        $userId
    );
}

/** Arabic labels for the reasons the ledger records. */
function wallet_reason_label(string $reason): string {
    return [
        'topup'            => 'شحن رصيد',
        'order_payment'    => 'دفع طلب',
        'order_refund'     => 'استرداد طلب',
        'mediation_hold'   => 'حجز وساطة',
        'mediation_release' => 'تحرير وساطة',
        'mediation_refund' => 'استرداد وساطة',
        'settlement'       => 'تسوية مورد',
        'adjustment'       => 'تسوية إدارية',
    ][$reason] ?? $reason;
}
