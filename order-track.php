<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/settings_helper.php';
auth_boot();

$page_title = 'تتبّع الطلب | Elawaady XDigital';

$code  = mb_substr(trim((string) ($_GET['code'] ?? $_POST['code'] ?? '')), 0, 30);
$order = null;
$error = '';

if ($code !== '') {
    // The code alone identifies the order. The query selects nothing about the
    // supplier, the store's cost or its margin, so this page cannot leak them
    // however it is rendered.
    $order = fetch_one($conn, "
        SELECT id, order_code, user_id, service_id, service_name, quantity,
               unit_price, options_total, mediation_fee, total_price, currency,
               order_status, payment_status, target_url, customer_notes,
               progress_percent, completed_quantity, remaining_quantity,
               created_at, updated_at
          FROM orders WHERE order_code = ? LIMIT 1", 's', $code);

    if ($order === null) {
        $error = 'لم نعثر على طلب بهذا الكود.';
    }
}

$timeline = [];
$options  = [];

if ($order !== null) {
    $timeline = fetch_all(
        $conn,
        'SELECT to_status, note, created_at FROM order_status_history
          WHERE order_id = ? AND customer_visible = 1 ORDER BY id',
        'i',
        (int) $order['id']
    );
    $options = fetch_all(
        $conn,
        'SELECT option_label, value_label, price_delta FROM order_options WHERE order_id = ?',
        'i',
        (int) $order['id']
    );
}

/** The customer-facing name of an order state. */
function track_status_label(string $status): string {
    return [
        'new'              => 'تم الاستلام',
        'waiting_approval' => 'بانتظار المراجعة',
        'waiting_payment'  => 'بانتظار الدفع',
        'paid'             => 'تم الدفع',
        'in_progress'      => 'قيد التنفيذ',
        'delivered'        => 'تم التسليم',
        'completed'        => 'مكتمل',
        'cancelled'        => 'ملغي',
        'refunded'         => 'تم الاسترداد',
        'dispute'          => 'قيد المراجعة',
    ][$status] ?? $status;
}

$whatsapp = preg_replace('/\D/', '', setting('support_whatsapp', ''));

require_once __DIR__ . '/header.php';
?>

<section class="order-outcome">
    <div class="container narrow">

        <div class="outcome-card reveal">
            <h1>تتبّع الطلب</h1>
            <p class="auth-sub">أدخل كود الطلب الذي وصلك عند الشراء.</p>

            <form method="get" class="auth-form">
                <div class="form-group">
                    <label class="form-label" for="code">كود الطلب</label>
                    <input class="form-input" type="text" id="code" name="code" dir="ltr" required
                           value="<?= e($code) ?>" placeholder="EXD-260901-XXXXXXXXXX">
                </div>
                <button class="btn btn-primary btn-full" type="submit">عرض الحالة</button>
            </form>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
            <?php endif; ?>
        </div>

        <?php if ($order !== null): ?>
            <div class="outcome-card reveal">
                <div class="flex-between">
                    <h2><?= e((string) $order['service_name']) ?></h2>
                    <span class="pill pill--<?= e((string) $order['order_status']) ?>">
                        <?= e(track_status_label((string) $order['order_status'])) ?>
                    </span>
                </div>

                <table class="kv-public">
                    <tr><td>كود الطلب</td><td dir="ltr"><strong><?= e((string) $order['order_code']) ?></strong></td></tr>
                    <tr><td>الكمية</td><td><?= (int) $order['quantity'] ?></td></tr>
                    <?php foreach ($options as $option): ?>
                        <tr><td><?= e((string) $option['option_label']) ?></td><td><?= e((string) $option['value_label']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ((float) $order['mediation_fee'] > 0): ?>
                        <tr><td>رسوم الوساطة</td><td class="money"><?= e(number_format((float) $order['mediation_fee'], 2)) ?></td></tr>
                    <?php endif; ?>
                    <tr><td>الإجمالي</td><td class="money"><?= e(number_format((float) $order['total_price'], 2)) ?> <?= e((string) $order['currency']) ?></td></tr>
                    <tr><td>حالة الدفع</td><td><?= e(track_status_label((string) $order['payment_status'])) ?></td></tr>
                    <tr><td>تاريخ الطلب</td><td dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $order['created_at']))) ?></td></tr>
                </table>

                <?php if ((float) $order['progress_percent'] > 0): ?>
                    <div class="progress-track" role="progressbar"
                         aria-valuenow="<?= e((string) round((float) $order['progress_percent'])) ?>"
                         aria-valuemin="0" aria-valuemax="100">
                        <span style="width: <?= e((string) min(100, (float) $order['progress_percent'])) ?>%"></span>
                    </div>
                    <p class="form-hint">
                        تم تنفيذ <?= e(number_format((int) $order['completed_quantity'])) ?>
                        من <?= e(number_format((int) $order['quantity'])) ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($timeline): ?>
                <div class="outcome-card reveal">
                    <h2>مسار الطلب</h2>
                    <ul class="timeline-public">
                        <?php foreach ($timeline as $step): ?>
                            <li>
                                <strong><?= e(track_status_label((string) $step['to_status'])) ?></strong>
                                <?php if (!empty($step['note'])): ?>
                                    <span class="text-muted"> — <?= e((string) $step['note']) ?></span>
                                <?php endif; ?>
                                <time dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $step['created_at']))) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($whatsapp !== ''): ?>
                <p class="account-note">
                    لأي استفسار عن هذا الطلب
                    <a href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('استفسار عن الطلب ' . (string) $order['order_code']) ?>"
                       target="_blank" rel="noopener">تواصل مع الدعم</a>.
                </p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
