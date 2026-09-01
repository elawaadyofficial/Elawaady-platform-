<?php
/**
 * EXD — place an order.
 *
 * Accepts a POST from the service page, prices it server-side, and creates the
 * order. The price the browser showed is never trusted: everything is
 * recomputed here from the service row, so a tampered form cannot buy anything
 * cheaply.
 *
 * A signed-in customer with enough balance pays from the wallet in one
 * transaction. Everyone else gets an order awaiting payment and is handed to
 * whatever channel the service is configured for.
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/checkout_intent.php';
require_once __DIR__ . '/lib/pricing.php';
require_once __DIR__ . '/lib/checkout.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/settings_helper.php';

auth_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_require();

$user   = auth_user();
$userId = $user !== null ? (int) $user['id'] : 0;

/** Send the customer back to the service with something to read. */
function order_fail(int $serviceId, string $message): never {
    auth_boot();
    $_SESSION['order_error'] = $message;
    header('Location: service.php?id=' . $serviceId);
    exit;
}

$serviceId = max(0, (int) ($_POST['service_id'] ?? 0));
$action    = (string) ($_POST['action'] ?? 'direct_buy');
if (!in_array($action, ['direct_buy', 'cart', 'whatsapp', 'mediation'], true)) {
    $action = 'direct_buy';
}

if ($serviceId === 0) {
    header('Location: index.php');
    exit;
}

// Every service form carries a short-lived retry key issued into the same PHP
// session and bound to this exact service. This closes the gap where a caller
// could invent a syntactically valid idempotency key and still reach checkout.
$idempotencyKey = trim((string) ($_POST['checkout_intent'] ?? ''));
if (!checkout_intent_verify($idempotencyKey, $serviceId)) {
    order_fail($serviceId, 'انتهت صلاحية محاولة الشراء. أعد تحميل صفحة الخدمة وحاول مرة أخرى.');
}

// The same rule as the service page: no supplier column is selected, so the
// order confirmation cannot reveal who fulfils it.
$service = fetch_one($conn, "
    SELECT id, name, description, price, old_price, currency, show_price, ask_for_price,
           min_quantity, max_quantity, quantity_step, availability, stock,
           price_mode, provider_base_price, provider_price_per, profit_percent,
           mediation_enabled, mediation_fee, mediation_fee_mode, mediation_safety_days,
           order_type, order_link, whatsapp_number, payment_method, post_order_contact,
           allow_wallet_payment, requires_approval, requires_advance_payment,
           order_receiver, execution_method, source_type, supplier_id,
           buy_now_enabled, cart_enabled, category_id
      FROM store_services WHERE id = ? AND is_active = 1
", 'i', $serviceId);

if ($service === null) {
    order_fail($serviceId, 'هذه الخدمة لم تعد متاحة.');
}

if ($service['availability'] !== 'available'
    || ($service['stock'] !== null && (int) $service['stock'] <= 0)) {
    order_fail($serviceId, 'هذه الخدمة غير متاحة حاليًا.');
}

$quantity = max(1, (int) ($_POST['qty'] ?? $service['min_quantity']));
if (!service_quantity_valid($service, $quantity)) {
    order_fail($serviceId, 'الكمية المطلوبة خارج الحدود المسموحة لهذه الخدمة.');
}

$targetUrl = trim((string) ($_POST['target_url'] ?? ''));
if ($targetUrl !== '' && !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    order_fail($serviceId, 'رابط الهدف غير صحيح.');
}
$targetUrl = mb_substr($targetUrl, 0, 1000);

$notes = mb_substr(trim((string) ($_POST['customer_notes'] ?? '')), 0, 1000);

$chosenOptions = array_values(array_filter(
    array_map('strval', (array) ($_POST['options'] ?? [])),
    static fn(string $v): bool => $v !== ''
));

$useMediation = !empty($_POST['use_mediation'])
    && (int) $service['mediation_enabled'] === 1
    && setting('mediation_enabled', '1') === '1';

// Everything about the money is computed here, from the database row.
$quote = service_quote($conn, $service, $quantity, $chosenOptions, $useMediation);

if ($action === 'cart') {
    // The cart survives a session so a signed-in buyer keeps their basket.
    $sessionKey = (string) ($_SESSION['cart_key'] ?? '');
    if ($sessionKey === '') {
        $sessionKey = bin2hex(random_bytes(32));
        $_SESSION['cart_key'] = $sessionKey;
    }

    $cart = $userId > 0
        ? fetch_one($conn, "SELECT id FROM carts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", 'i', $userId)
        : fetch_one($conn, "SELECT id FROM carts WHERE session_key = ? AND status = 'open' ORDER BY id DESC LIMIT 1", 's', $sessionKey);

    if ($cart === null) {
        $stmt = $conn->prepare('INSERT INTO carts (user_id, session_key) VALUES (?, ?)');
        $ownerId = $userId > 0 ? $userId : null;
        $stmt->bind_param('is', $ownerId, $sessionKey);
        $stmt->execute();
        $cartId = (int) $conn->insert_id;
    } else {
        $cartId = (int) $cart['id'];
    }

    $stmt = $conn->prepare(
        'INSERT INTO cart_items (cart_id, service_id, quantity, unit_price, options_json, target_url)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $optionsJson = json_encode($quote['option_lines'], JSON_UNESCAPED_UNICODE);
    $unit        = $quote['unit_price'];
    $stmt->bind_param('iiidss', $cartId, $serviceId, $quantity, $unit, $optionsJson, $targetUrl);
    $stmt->execute();

    header('Location: cart.php?added=1');
    exit;
}

/** Build the message the customer carries to the support channel. */
function order_whatsapp_message(array $service, string $code, int $quantity, array $quote, string $targetUrl, string $notes): string {
    $lines   = ['مرحبًا، أود طلب الخدمة التالية:'];
    $lines[] = '• الخدمة: ' . $service['name'];
    $lines[] = '• كود الطلب: ' . $code;
    $lines[] = '• الكمية: ' . $quantity;
    if ($targetUrl !== '') {
        $lines[] = '• الرابط: ' . $targetUrl;
    }
    foreach ($quote['option_lines'] as $line) {
        $lines[] = '• ' . $line['option_label'] . ': ' . $line['value_label'];
    }
    if ($quote['total'] > 0) {
        $lines[] = '• الإجمالي: ' . number_format($quote['total'], 2) . ' ' . $quote['currency'];
    }
    if ($notes !== '') {
        $lines[] = '• ملاحظات: ' . $notes;
    }
    return implode("\n", $lines);
}

// A signed-in active customer with wallet payment enabled always enters the
// atomic checkout first. Do not pre-check the cached balance here: on a retry,
// the first request may already have debited it. checkout_with_wallet() checks
// idempotency before checking the remaining balance, so the retry can return the
// original paid order instead of accidentally creating a second unpaid order.
$paidFromWallet   = false;
$checkoutReplayed = false;
$orderCode        = '';
$orderId          = 0;

if ($userId > 0
    && $user['status'] === 'active'
    && (int) $service['allow_wallet_payment'] === 1
    && $quote['total'] > 0) {
    try {
        $result = checkout_with_wallet([
            'user_id'         => $userId,
            'service_id'      => $serviceId,
            'service_name'    => (string) $service['name'],
            'quantity'        => $quantity,
            'unit_price'      => $quote['unit_price'],
            'options_total'   => $quote['options_total'],
            'mediation_fee'   => $quote['mediation_fee'],
            'currency'        => strtoupper($quote['currency'] === 'ج.م' ? 'EGP' : $quote['currency']),
            'target_url'      => $targetUrl,
            'customer_notes'  => $notes,
            'idempotency_key' => $idempotencyKey,
        ]);
        $paidFromWallet   = true;
        $checkoutReplayed = (bool) ($result['replayed'] ?? false);
        $orderCode        = (string) $result['order_code'];
        $orderId          = (int) $result['order_id'];
    } catch (RuntimeException $e) {
        // Only ordinary wallet-unavailable states may continue as an unpaid
        // order. Idempotency conflicts and transactional failures must stop;
        // falling through would turn a failed retry into a duplicate order.
        $fallbackErrors = [
            'الرصيد غير كافٍ.',
            'المحفظة موقوفة.',
            'عملة المحفظة لا تطابق عملة الطلب.',
        ];
        if (in_array($e->getMessage(), $fallbackErrors, true)) {
            error_log('[EXD checkout fallback] ' . $e->getMessage());
        } else {
            error_log('[EXD checkout blocked] ' . $e->getMessage());
            order_fail($serviceId, 'تعذّر إتمام عملية الشراء بأمان. أعد تحميل الصفحة وحاول مرة أخرى.');
        }
    } catch (Throwable $e) {
        error_log('[EXD checkout failure] ' . $e->getMessage());
        order_fail($serviceId, 'تعذّر إتمام عملية الشراء بأمان. أعد تحميل الصفحة وحاول مرة أخرى.');
    }
}

if (!$paidFromWallet) {
    $orderCode = 'EXD-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));

    $customerName  = $user !== null ? (string) $user['name']  : mb_substr(trim((string) ($_POST['customer_name'] ?? '')), 0, 200);
    $customerPhone = $user !== null ? (string) $user['phone'] : mb_substr(trim((string) ($_POST['customer_phone'] ?? '')), 0, 50);
    $customerEmail = $user !== null ? (string) $user['email'] : mb_substr(trim((string) ($_POST['customer_email'] ?? '')), 0, 200);

    $orderStatus = (int) $service['requires_approval'] === 1 ? 'waiting_approval' : 'waiting_payment';
    $orderSource = (string) $service['source_type'] === 'supplier' ? 'supplier' : 'store';
    $whatsapp    = order_whatsapp_message($service, $orderCode, $quantity, $quote, $targetUrl, $notes);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO orders
                (order_code, user_id, service_id, service_name,
                 customer_name, customer_phone, customer_email,
                 quantity, unit_price, options_total, mediation_fee, total_price, currency,
                 order_type, order_source, payment_status, order_status,
                 target_url, customer_notes, whatsapp_message,
                 mediation_enabled, supplier_id, remaining_quantity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)"
        );
        $ownerId      = $userId > 0 ? $userId : null;
        $currencyCode = (string) $quote['currency'];
        $unit         = $quote['unit_price'];
        $optionsTotal = $quote['options_total'];
        $mediationFee = $quote['mediation_fee'];
        $total        = $quote['total'];
        $mediationOn  = $useMediation ? 1 : 0;
        $supplierId   = $service['supplier_id'] !== null ? (int) $service['supplier_id'] : null;

        $stmt->bind_param(
            'siissssiddddsssssssiii',
            $orderCode, $ownerId, $serviceId, $service['name'],
            $customerName, $customerPhone, $customerEmail,
            $quantity, $unit, $optionsTotal, $mediationFee, $total, $currencyCode,
            $action, $orderSource, $orderStatus,
            $targetUrl, $notes, $whatsapp,
            $mediationOn, $supplierId, $quantity
        );
        $stmt->execute();
        $orderId = (int) $conn->insert_id;

        foreach ($quote['option_lines'] as $line) {
            $opt = $conn->prepare(
                'INSERT INTO order_options (order_id, option_label, value_label, price_delta) VALUES (?, ?, ?, ?)'
            );
            $opt->bind_param('issd', $orderId, $line['option_label'], $line['value_label'], $line['price_delta']);
            $opt->execute();
        }

        $hist = $conn->prepare(
            "INSERT INTO order_status_history (order_id, from_status, to_status, actor_type, actor_id, note)
             VALUES (?, NULL, ?, ?, ?, 'إنشاء الطلب')"
        );
        $actorType = $userId > 0 ? 'user' : 'system';
        $actorId   = $userId > 0 ? $userId : null;
        $hist->bind_param('issi', $orderId, $orderStatus, $actorType, $actorId);
        $hist->execute();

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log('[EXD order] ' . $e->getMessage());
        order_fail($serviceId, 'تعذّر إنشاء الطلب الآن. حاول مرة أخرى أو تواصل مع الدعم.');
    }
}

// A replay is the same completed action, not a new business event. Avoid
// duplicate customer/staff notifications while still redirecting to the same
// confirmation page and order code.
if (!$checkoutReplayed) {
    if ($userId > 0) {
        notify_user($userId, 'تم استلام طلبك', $orderCode . ' — ' . (string) $service['name'],
            'success', 'order-track.php?code=' . urlencode($orderCode));
    }

    // Staff see a new order without having to refresh a list.
    notify_staff('orders.view', 'طلب جديد', $orderCode . ' — ' . (string) $service['name'],
        'info', 'order-view.php?id=' . $orderId);
}

// A mediated deal continues on the mediation page; a support-routed service
// hands the customer to the channel it is configured for; everything else
// lands on the confirmation.
$destination = 'order-success.php?code=' . urlencode($orderCode);

if ($useMediation) {
    $destination = 'mediation.php?order=' . urlencode($orderCode);
} elseif ($action === 'whatsapp' || $service['order_receiver'] === 'support') {
    $number = preg_replace('/\D/', '', (string) ($service['whatsapp_number'] ?: setting('support_whatsapp', '')));
    if ($number !== '') {
        $message = order_whatsapp_message($service, $orderCode, $quantity, $quote, $targetUrl, $notes);
        $destination = 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }
}

header('Location: ' . $destination);
exit;