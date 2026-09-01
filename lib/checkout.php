<?php
/**
 * EXD — atomic wallet checkout.
 *
 * Creates the order, debits the wallet, records the payment and writes the
 * initial order timeline entry inside ONE database transaction. If any write
 * fails, every write is rolled back.
 */

require_once __DIR__ . '/../db_connect.php';

/**
 * Purchase one service using wallet balance.
 *
 * Required input keys:
 * - user_id, service_name, quantity, unit_price
 * Optional:
 * - service_id, options_total, mediation_fee, currency, target_url,
 *   target_type, quality_option, warranty_option, customer_notes
 *
 * @return array{order_id:int,order_code:string,total:float,balance_after:float}
 */
function checkout_with_wallet(array $input): array {
    global $conn;

    $userId = (int) ($input['user_id'] ?? 0);
    $serviceId = isset($input['service_id']) ? (int) $input['service_id'] : null;
    $serviceName = trim((string) ($input['service_name'] ?? ''));
    $quantity = (int) ($input['quantity'] ?? 0);
    $unitPrice = round((float) ($input['unit_price'] ?? 0), 2);
    $optionsTotal = round((float) ($input['options_total'] ?? 0), 2);
    $mediationFee = round((float) ($input['mediation_fee'] ?? 0), 2);
    $currency = strtoupper(trim((string) ($input['currency'] ?? 'EGP')));
    $targetUrl = trim((string) ($input['target_url'] ?? ''));
    $targetType = trim((string) ($input['target_type'] ?? ''));
    $qualityOption = trim((string) ($input['quality_option'] ?? ''));
    $warrantyOption = trim((string) ($input['warranty_option'] ?? ''));
    $customerNotes = trim((string) ($input['customer_notes'] ?? ''));

    if ($userId <= 0) {
        throw new InvalidArgumentException('user_id is required');
    }
    if ($serviceName === '' || mb_strlen($serviceName) > 255) {
        throw new InvalidArgumentException('service_name is required and must fit the order schema');
    }
    if ($quantity <= 0 || $quantity > 1000000) {
        throw new InvalidArgumentException('quantity must be positive');
    }
    if ($unitPrice < 0 || $optionsTotal < 0 || $mediationFee < 0) {
        throw new InvalidArgumentException('prices cannot be negative');
    }
    if (!preg_match('/^[A-Z]{3,10}$/', $currency)) {
        throw new InvalidArgumentException('currency must be a 3-10 letter code');
    }

    $total = round(($quantity * $unitPrice) + $optionsTotal + $mediationFee, 2);
    if ($total <= 0) {
        throw new InvalidArgumentException('checkout total must be positive');
    }

    $orderCode = 'EXD-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));

    $conn->begin_transaction();
    try {
        $user = fetch_one(
            $conn,
            "SELECT id, status FROM platform_users WHERE id = ? FOR UPDATE",
            'i',
            $userId
        );
        if ($user === null || (string) $user['status'] !== 'active') {
            throw new RuntimeException('الحساب غير متاح للشراء.');
        }

        $wallet = fetch_one(
            $conn,
            'SELECT id, balance, currency, is_frozen FROM wallets WHERE user_id = ? FOR UPDATE',
            'i',
            $userId
        );
        if ($wallet === null) {
            $createWallet = $conn->prepare('INSERT INTO wallets (user_id, currency) VALUES (?, ?)');
            $createWallet->bind_param('is', $userId, $currency);
            $createWallet->execute();
            $wallet = fetch_one(
                $conn,
                'SELECT id, balance, currency, is_frozen FROM wallets WHERE user_id = ? FOR UPDATE',
                'i',
                $userId
            );
        }

        if ($wallet === null) {
            throw new RuntimeException('تعذر إنشاء المحفظة.');
        }
        if ((int) $wallet['is_frozen'] === 1) {
            throw new RuntimeException('المحفظة موقوفة.');
        }
        if (strtoupper((string) $wallet['currency']) !== $currency) {
            throw new RuntimeException('عملة المحفظة لا تطابق عملة الطلب.');
        }

        $walletId = (int) $wallet['id'];
        $balanceBefore = round((float) $wallet['balance'], 2);
        $balanceAfter = round($balanceBefore - $total, 2);
        if ($balanceAfter < 0) {
            throw new RuntimeException('الرصيد غير كافٍ.');
        }

        $stmt = $conn->prepare(
            "INSERT INTO orders
                (order_code, user_id, service_id, service_name, quantity, unit_price,
                 options_total, mediation_fee, total_price, currency, payment_status,
                 order_status, target_url, target_type, quality_option, warranty_option,
                 customer_notes, payment_confirmed_by, payment_confirmed_at,
                 payment_confirmed_amount, payment_method_recorded)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'new', ?, ?, ?, ?, ?, ?, NOW(), ?, 'wallet')"
        );
        $confirmedBy = $userId;
        $stmt->bind_param(
            'siisiddddssssssid',
            $orderCode,
            $userId,
            $serviceId,
            $serviceName,
            $quantity,
            $unitPrice,
            $optionsTotal,
            $mediationFee,
            $total,
            $currency,
            $targetUrl,
            $targetType,
            $qualityOption,
            $warrantyOption,
            $customerNotes,
            $confirmedBy,
            $total
        );
        $stmt->execute();
        $orderId = (int) $conn->insert_id;

        $ledger = $conn->prepare(
            "INSERT INTO wallet_transactions
                (wallet_id, user_id, direction, amount, balance_after, currency, reason,
                 order_id, reference, note, created_by_type, created_by_id)
             VALUES (?, ?, 'debit', ?, ?, ?, 'order_payment', ?, ?, 'Atomic wallet checkout', 'user', ?)"
        );
        $ledger->bind_param(
            'iiddsisi',
            $walletId,
            $userId,
            $total,
            $balanceAfter,
            $currency,
            $orderId,
            $orderCode,
            $userId
        );
        $ledger->execute();

        $updateWallet = $conn->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
        $updateWallet->bind_param('di', $balanceAfter, $walletId);
        $updateWallet->execute();

        $payment = $conn->prepare(
            "INSERT INTO payments
                (order_id, user_id, method_key, amount, currency, status, reference,
                 reviewed_by, reviewed_at, review_note)
             VALUES (?, ?, 'wallet', ?, ?, 'confirmed', ?, ?, NOW(), 'Atomic wallet checkout')"
        );
        $payment->bind_param('iidssi', $orderId, $userId, $total, $currency, $orderCode, $userId);
        $payment->execute();

        $history = $conn->prepare(
            "INSERT INTO order_status_history
                (order_id, from_status, to_status, actor_type, actor_id, note, customer_visible)
             VALUES (?, NULL, 'new', 'user', ?, 'تم إنشاء الطلب والدفع من المحفظة.', 1)"
        );
        $history->bind_param('ii', $orderId, $userId);
        $history->execute();

        $conn->commit();

        return [
            'order_id' => $orderId,
            'order_code' => $orderCode,
            'total' => $total,
            'balance_after' => $balanceAfter,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
