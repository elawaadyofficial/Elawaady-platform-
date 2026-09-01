<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/settings_helper.php';
auth_boot();

$code = mb_substr(trim((string) ($_GET['code'] ?? '')), 0, 30);

// The order code is the only key. It is random enough not to be guessed, and
// the query selects nothing about the supplier or the store's margin.
$order = $code !== ''
    ? fetch_one($conn, "
        SELECT order_code, service_id, service_name, quantity, total_price, currency,
               order_status, payment_status, created_at, user_id
          FROM orders WHERE order_code = ? LIMIT 1", 's', $code)
    : null;

if ($order === null) {
    http_response_code(404);
    $page_title = 'الطلب غير موجود';
    require_once __DIR__ . '/header.php';
    echo '<section class="page-hero"><div class="container">'
       . '<h1>لم نعثر على هذا الطلب</h1><p>راجع كود الطلب أو تواصل مع الدعم.</p>'
       . '<a class="btn btn-primary" href="index.php">العودة للمتجر</a></div></section>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$page_title = 'تم استلام طلبك | Elawaady XDigital';
$paid       = $order['payment_status'] === 'paid';
$whatsapp   = preg_replace('/\D/', '', setting('support_whatsapp', ''));

require_once __DIR__ . '/header.php';
?>

<section class="order-outcome">
    <div class="container narrow">
        <div class="outcome-card reveal">
            <div class="outcome-mark <?= $paid ? 'is-paid' : '' ?>" aria-hidden="true"><?= $paid ? '✓' : '◷' ?></div>

            <h1><?= $paid ? 'تم استلام طلبك ودفعه' : 'تم استلام طلبك' ?></h1>
            <p class="auth-sub">
                <?= $paid
                    ? 'دُفع المبلغ من محفظتك وبدأ الطلب مساره.'
                    : 'طلبك مسجّل الآن. الخطوة التالية هي إتمام الدفع.' ?>
            </p>

            <table class="kv-public">
                <tr><td>كود الطلب</td><td dir="ltr"><strong><?= e((string) $order['order_code']) ?></strong></td></tr>
                <tr><td>الخدمة</td><td><?= e((string) $order['service_name']) ?></td></tr>
                <tr><td>الكمية</td><td><?= (int) $order['quantity'] ?></td></tr>
                <tr><td>الإجمالي</td><td class="money"><?= e(number_format((float) $order['total_price'], 2)) ?> <?= e((string) $order['currency']) ?></td></tr>
                <tr><td>التاريخ</td><td dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $order['created_at']))) ?></td></tr>
            </table>

            <p class="form-hint">احتفظ بكود الطلب — به تتابع حالته في أي وقت.</p>

            <div class="outcome-actions">
                <a class="btn btn-primary" href="order-track.php?code=<?= e((string) $order['order_code']) ?>">تتبّع الطلب</a>
                <?php if ($whatsapp !== ''): ?>
                    <a class="btn btn-secondary" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">تواصل مع الدعم</a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="index.php">متابعة التسوق</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
