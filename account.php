<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/wallet.php';
auth_boot();
auth_require_login('account.php');

$user   = auth_user();
$userId = (int) $user['id'];

// A supplier has its own workspace; this page is the customer's.
if ($user['account_type'] === 'supplier') {
    header('Location: supplier-dashboard.php');
    exit;
}

$page_title = 'حسابي | Elawaady XDigital';
$tab        = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'orders', 'wallet', 'profile', 'security'], true)) {
    $tab = 'overview';
}

$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name     = trim((string) ($_POST['name'] ?? ''));
        $phone    = trim((string) ($_POST['phone'] ?? ''));
        $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
        $country  = trim((string) ($_POST['country'] ?? ''));

        if (mb_strlen($name) < 3) {
            $errors[] = 'الاسم قصير جدًا.';
        }
        if (!preg_match('/^[0-9+\s()-]{8,20}$/', $phone)) {
            $errors[] = 'رقم الهاتف غير صحيح.';
        }

        if (!$errors) {
            $stmt = $conn->prepare(
                'UPDATE platform_users SET name = ?, phone = ?, whatsapp = ?, country = ? WHERE id = ?'
            );
            $stmt->bind_param('ssssi', $name, $phone, $whatsapp, $country, $userId);
            $stmt->execute();
            audit_log('user', $userId, $name, 'account.profile_updated', 'platform_users', $userId);
            $notice = 'تم حفظ بياناتك.';
            $user   = fetch_one($conn, 'SELECT * FROM platform_users WHERE id = ?', 'i', $userId);
        }
        $tab = 'profile';
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $next    = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $row = fetch_one($conn, 'SELECT password_hash FROM platform_users WHERE id = ?', 'i', $userId);

        if (!password_verify($current, (string) $row['password_hash'])) {
            $errors[] = 'كلمة المرور الحالية غير صحيحة.';
        } elseif (strlen($next) < 8 || !preg_match('/[A-Za-z]/', $next) || !preg_match('/[0-9]/', $next)) {
            $errors[] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل وتحتوي على حرف ورقم.';
        } elseif ($next !== $confirm) {
            $errors[] = 'كلمة المرور الجديدة وتأكيدها غير متطابقتين.';
        } else {
            $hash = password_hash($next, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'UPDATE platform_users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?'
            );
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            audit_log('user', $userId, (string) $user['name'], 'account.password_changed', 'platform_users', $userId);
            $notice = 'تم تغيير كلمة المرور.';
        }
        $tab = 'security';
    }

    if ($action === 'revoke_sessions') {
        // Keep this device signed in; end every other one.
        $cookie   = (string) ($_COOKIE[AUTH_COOKIE] ?? '');
        $selector = str_contains($cookie, ':') ? explode(':', $cookie, 2)[0] : '';
        $stmt = $conn->prepare(
            'UPDATE user_sessions SET revoked_at = NOW()
              WHERE user_id = ? AND revoked_at IS NULL AND selector <> ?'
        );
        $stmt->bind_param('is', $userId, $selector);
        $stmt->execute();
        audit_log('user', $userId, (string) $user['name'], 'account.sessions_revoked', 'platform_users', $userId);
        $notice = 'تم إنهاء الجلسات الأخرى.';
        $tab    = 'security';
    }
}

$wallet  = wallet_for($userId);
$orders  = fetch_all(
    $conn,
    'SELECT id, order_code, service_name, quantity, total_price, currency,
            order_status, payment_status, created_at
       FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50',
    'i',
    $userId
);
$ledger  = wallet_transactions($userId, 25);
$sessions = fetch_all(
    $conn,
    'SELECT selector, ip_address, user_agent, created_at, last_seen_at, expires_at
       FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW()
      ORDER BY id DESC LIMIT 10',
    'i',
    $userId
);

$currentSelector = str_contains((string) ($_COOKIE[AUTH_COOKIE] ?? ''), ':')
    ? explode(':', (string) $_COOKIE[AUTH_COOKIE], 2)[0]
    : '';

/** Arabic labels for the order workflow. */
function order_status_label(string $status): string {
    return [
        'new'               => 'جديد',
        'waiting_approval'  => 'بانتظار الاعتماد',
        'waiting_payment'   => 'بانتظار الدفع',
        'paid'              => 'مدفوع',
        'in_progress'       => 'قيد التنفيذ',
        'delivered'         => 'تم التسليم',
        'completed'         => 'مكتمل',
        'cancelled'         => 'ملغي',
        'refunded'          => 'مسترد',
        'dispute'           => 'نزاع',
    ][$status] ?? $status;
}

function payment_status_label(string $status): string {
    return [
        'pending'               => 'بانتظار الدفع',
        'awaiting_confirmation' => 'بانتظار التأكيد',
        'paid'                  => 'مدفوع',
        'partially_paid'        => 'مدفوع جزئيًا',
        'failed'                => 'فشل',
        'refunded'              => 'مسترد',
    ][$status] ?? $status;
}

$tabs = [
    'overview' => 'نظرة عامة',
    'orders'   => 'طلباتي',
    'wallet'   => 'المحفظة',
    'profile'  => 'بياناتي',
    'security' => 'الأمان',
];

require_once __DIR__ . '/header.php';
?>

<section class="account-shell">
    <div class="container">

        <header class="account-head reveal">
            <div>
                <h1>مرحبًا، <?= e((string) $user['name']) ?></h1>
                <p>عضو منذ <?= e(date('Y/m/d', strtotime((string) $user['created_at']))) ?></p>
            </div>
            <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit">تسجيل الخروج</button>
            </form>
        </header>

        <nav class="account-tabs reveal" aria-label="أقسام الحساب">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="account-tab <?= $tab === $key ? 'is-active' : '' ?>"
                   href="account.php?tab=<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success"><span>✅</span><div><?= e($notice) ?></div></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
        <?php endforeach; ?>

        <?php if ($tab === 'overview'): ?>
            <div class="account-stats reveal-stagger">
                <div class="stat-card">
                    <span class="stat-value"><?= e(number_format((float) $wallet['balance'], 2)) ?></span>
                    <span class="stat-label">رصيد المحفظة (<?= e((string) $wallet['currency']) ?>)</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= count($orders) ?></span>
                    <span class="stat-label">إجمالي الطلبات</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?=
                        count(array_filter($orders, static fn(array $o): bool =>
                            in_array($o['order_status'], ['new','waiting_approval','waiting_payment','paid','in_progress'], true)))
                    ?></span>
                    <span class="stat-label">طلبات جارية</span>
                </div>
            </div>

            <?php if ($orders): ?>
                <h2 class="account-section-title">آخر الطلبات</h2>
                <?php $recent = array_slice($orders, 0, 5); ?>
                <div class="account-table-wrap">
                    <table class="account-table">
                        <thead><tr><th>الكود</th><th>الخدمة</th><th>الإجمالي</th><th>الحالة</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $order): ?>
                            <tr>
                                <td dir="ltr"><a href="order-track.php?code=<?= e((string) $order['order_code']) ?>"><?= e((string) $order['order_code']) ?></a></td>
                                <td><?= e((string) $order['service_name']) ?></td>
                                <td><?= e(number_format((float) $order['total_price'], 2)) ?> <?= e((string) $order['currency']) ?></td>
                                <td><span class="pill pill--<?= e((string) $order['order_status']) ?>"><?= e(order_status_label((string) $order['order_status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state reveal">
                    <p>لا توجد طلبات بعد.</p>
                    <a class="btn btn-primary" href="index.php">تصفح الخدمات</a>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'orders'): ?>
            <?php if ($orders): ?>
                <div class="account-table-wrap reveal">
                    <table class="account-table">
                        <thead><tr><th>الكود</th><th>الخدمة</th><th>الكمية</th><th>الإجمالي</th><th>الدفع</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td dir="ltr"><a href="order-track.php?code=<?= e((string) $order['order_code']) ?>"><?= e((string) $order['order_code']) ?></a></td>
                                <td><?= e((string) $order['service_name']) ?></td>
                                <td><?= (int) $order['quantity'] ?></td>
                                <td><?= e(number_format((float) $order['total_price'], 2)) ?> <?= e((string) $order['currency']) ?></td>
                                <td><?= e(payment_status_label((string) $order['payment_status'])) ?></td>
                                <td><span class="pill pill--<?= e((string) $order['order_status']) ?>"><?= e(order_status_label((string) $order['order_status'])) ?></span></td>
                                <td dir="ltr"><?= e(date('Y/m/d', strtotime((string) $order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state reveal"><p>لا توجد طلبات بعد.</p><a class="btn btn-primary" href="index.php">تصفح الخدمات</a></div>
            <?php endif; ?>

        <?php elseif ($tab === 'wallet'): ?>
            <div class="wallet-head reveal">
                <div class="stat-card stat-card--wide">
                    <span class="stat-value"><?= e(number_format((float) $wallet['balance'], 2)) ?> <?= e((string) $wallet['currency']) ?></span>
                    <span class="stat-label">الرصيد المتاح</span>
                </div>
                <?php if ((float) $wallet['held_balance'] > 0): ?>
                    <div class="stat-card">
                        <span class="stat-value"><?= e(number_format((float) $wallet['held_balance'], 2)) ?></span>
                        <span class="stat-label">محجوز في وساطة</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($ledger): ?>
                <div class="account-table-wrap reveal">
                    <table class="account-table">
                        <thead><tr><th>العملية</th><th>المبلغ</th><th>الرصيد بعدها</th><th>التاريخ</th></tr></thead>
                        <tbody>
                        <?php foreach ($ledger as $row): ?>
                            <tr>
                                <td><?= e(wallet_reason_label((string) $row['reason'])) ?></td>
                                <td class="<?= $row['direction'] === 'credit' ? 'amount-in' : 'amount-out' ?>">
                                    <?= $row['direction'] === 'credit' ? '+' : '−' ?><?= e(number_format((float) $row['amount'], 2)) ?>
                                </td>
                                <td><?= e(number_format((float) $row['balance_after'], 2)) ?></td>
                                <td dir="ltr"><?= e(date('Y/m/d H:i', strtotime((string) $row['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state reveal"><p>لا توجد حركات على المحفظة بعد.</p></div>
            <?php endif; ?>

            <p class="account-note">لشحن الرصيد تواصل مع الدعم عبر <a href="contact.php">صفحة التواصل</a>.</p>

        <?php elseif ($tab === 'profile'): ?>
            <form method="post" class="account-form reveal">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">الاسم الكامل</label>
                        <input class="form-input" type="text" id="name" name="name" required
                               value="<?= e((string) $user['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">رقم الهاتف</label>
                        <input class="form-input" type="tel" id="phone" name="phone" dir="ltr" required
                               value="<?= e((string) $user['phone']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="whatsapp">واتساب</label>
                        <input class="form-input" type="tel" id="whatsapp" name="whatsapp" dir="ltr"
                               value="<?= e((string) ($user['whatsapp'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="country">الدولة</label>
                        <input class="form-input" type="text" id="country" name="country"
                               value="<?= e((string) ($user['country'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input class="form-input" type="email" dir="ltr" value="<?= e((string) $user['email']) ?>" disabled>
                    <small class="form-hint">لتغيير البريد تواصل مع الدعم.</small>
                </div>

                <button class="btn btn-primary" type="submit">حفظ التغييرات</button>
            </form>

        <?php else: ?>
            <form method="post" class="account-form reveal">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <h2 class="account-section-title">تغيير كلمة المرور</h2>

                <div class="form-group">
                    <label class="form-label" for="current_password">كلمة المرور الحالية</label>
                    <input class="form-input" type="password" id="current_password"
                           name="current_password" autocomplete="current-password" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="new_password">كلمة المرور الجديدة</label>
                        <input class="form-input" type="password" id="new_password"
                               name="new_password" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">تأكيدها</label>
                        <input class="form-input" type="password" id="confirm_password"
                               name="confirm_password" autocomplete="new-password" required>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">تغيير كلمة المرور</button>
            </form>

            <h2 class="account-section-title">الأجهزة النشطة</h2>
            <div class="account-table-wrap reveal">
                <table class="account-table">
                    <thead><tr><th>الجهاز</th><th>العنوان</th><th>آخر نشاط</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?= e(mb_strimwidth((string) ($session['user_agent'] ?? '—'), 0, 48, '…')) ?></td>
                            <td dir="ltr"><?= e((string) ($session['ip_address'] ?? '—')) ?></td>
                            <td dir="ltr"><?= e($session['last_seen_at'] ? date('Y/m/d H:i', strtotime((string) $session['last_seen_at'])) : '—') ?></td>
                            <td><?= $session['selector'] === $currentSelector ? '<span class="pill pill--current">هذا الجهاز</span>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="post" class="account-form reveal">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke_sessions">
                <button class="btn btn-ghost" type="submit">إنهاء الجلسات على الأجهزة الأخرى</button>
            </form>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
