<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/wallet.php';
auth_boot();
auth_require_login('supplier-dashboard.php');

$user = auth_user();
if ($user['account_type'] !== 'supplier') {
    header('Location: account.php');
    exit;
}

$supplierId = (int) $user['id'];
$approved   = $user['status'] === 'active';
$page_title = 'لوحة المورد | Elawaady XDigital';

$errors = [];
$notice = '';

$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'offers', 'orders', 'settlements', 'profile'], true)) {
    $tab = 'overview';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');

    // A supplier whose account is still pending may look, but may not trade.
    if (!$approved && $action !== 'update_profile') {
        $errors[] = 'حسابك قيد المراجعة — لا يمكن تنفيذ هذا الإجراء بعد.';
    } elseif ($action === 'submit_offer') {
        $title       = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price       = (float) ($_POST['supplier_price'] ?? 0);
        $execution   = trim((string) ($_POST['execution_time'] ?? ''));

        if (mb_strlen($title) < 5) {
            $errors[] = 'عنوان الخدمة قصير جدًا.';
        }
        if ($price < 0) {
            $errors[] = 'السعر غير صحيح.';
        }

        if (!$errors) {
            $stmt = $conn->prepare(
                'INSERT INTO supplier_offers
                    (supplier_id, title, description, supplier_price, execution_time, review_status)
                 VALUES (?, ?, ?, ?, ?, "pending_review")'
            );
            $stmt->bind_param('issds', $supplierId, $title, $description, $price, $execution);
            $stmt->execute();
            audit_log('supplier', $supplierId, (string) $user['name'], 'offer.submitted',
                'supplier_offers', (int) $conn->insert_id, $title);
            $notice = 'تم إرسال الخدمة للمراجعة.';
        }
        $tab = 'offers';
    } elseif ($action === 'update_profile') {
        $company  = trim((string) ($_POST['company_name'] ?? ''));
        $bio      = trim((string) ($_POST['bio'] ?? ''));
        $services = trim((string) ($_POST['services_desc'] ?? ''));
        $telegram = trim((string) ($_POST['telegram'] ?? ''));

        $exists = fetch_one($conn, 'SELECT id FROM supplier_profiles WHERE user_id = ?', 'i', $supplierId);
        if ($exists === null) {
            $stmt = $conn->prepare(
                'INSERT INTO supplier_profiles (user_id, company_name, bio, services_desc, telegram)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('issss', $supplierId, $company, $bio, $services, $telegram);
        } else {
            $stmt = $conn->prepare(
                'UPDATE supplier_profiles
                    SET company_name = ?, bio = ?, services_desc = ?, telegram = ?
                  WHERE user_id = ?'
            );
            $stmt->bind_param('ssssi', $company, $bio, $services, $telegram, $supplierId);
        }
        $stmt->execute();
        audit_log('supplier', $supplierId, (string) $user['name'], 'supplier.profile_updated',
            'supplier_profiles', $supplierId);
        $notice = 'تم حفظ بيانات المورد.';
        $tab    = 'profile';
    }
}

$profile = fetch_one($conn, 'SELECT * FROM supplier_profiles WHERE user_id = ?', 'i', $supplierId) ?? [];
$offers  = fetch_all(
    $conn,
    'SELECT id, title, supplier_price, currency, review_status, admin_notes, created_at
       FROM supplier_offers WHERE supplier_id = ? ORDER BY id DESC LIMIT 50',
    'i',
    $supplierId
);

// What a supplier may see of an order is deliberately narrow: what to do, and
// when it came in. The buyer's name, phone and email are not selected here at
// all, so no template mistake can leak them.
$orders = fetch_all(
    $conn,
    'SELECT o.order_code, o.service_name, o.quantity, o.order_status, o.supplier_status,
            o.target_url, o.created_at
       FROM orders o WHERE o.supplier_id = ? ORDER BY o.id DESC LIMIT 50',
    'i',
    $supplierId
);

$settlements = fetch_all(
    $conn,
    'SELECT amount, currency, status, hold_until, paid_at, note, created_at
       FROM supplier_settlements WHERE supplier_id = ? ORDER BY id DESC LIMIT 50',
    'i',
    $supplierId
);

$payable = 0.0;
$held    = 0.0;
foreach ($settlements as $row) {
    if ($row['status'] === 'payable') { $payable += (float) $row['amount']; }
    if ($row['status'] === 'held')    { $held    += (float) $row['amount']; }
}

function offer_status_label(string $status): string {
    return [
        'draft'          => 'مسودة',
        'pending_review' => 'قيد المراجعة',
        'approved'       => 'معتمدة',
        'rejected'       => 'مرفوضة',
        'withdrawn'      => 'مسحوبة',
    ][$status] ?? $status;
}

function settlement_status_label(string $status): string {
    return [
        'held'      => 'محجوزة',
        'payable'   => 'مستحقة',
        'paid'      => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ][$status] ?? $status;
}

$tabs = [
    'overview'    => 'نظرة عامة',
    'offers'      => 'خدماتي',
    'orders'      => 'الطلبات',
    'settlements' => 'المستحقات',
    'profile'     => 'بيانات المورد',
];

require_once __DIR__ . '/header.php';
?>

<section class="account-shell">
    <div class="container">

        <header class="account-head reveal">
            <div>
                <h1>لوحة المورد</h1>
                <p><?= e((string) $user['name']) ?><?= !empty($profile['company_name']) ? ' — ' . e((string) $profile['company_name']) : '' ?></p>
            </div>
            <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit">تسجيل الخروج</button>
            </form>
        </header>

        <?php if (!$approved): ?>
            <div class="alert alert-info">
                <span>◷</span>
                <div>
                    <strong>حسابك قيد المراجعة.</strong>
                    <p>يمكنك استكمال بيانات المورد الآن. سيتم تفعيل باقي اللوحة فور اعتماد الحساب.</p>
                </div>
            </div>
        <?php endif; ?>

        <nav class="account-tabs reveal" aria-label="أقسام لوحة المورد">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="account-tab <?= $tab === $key ? 'is-active' : '' ?>"
                   href="supplier-dashboard.php?tab=<?= e($key) ?>"><?= e($label) ?></a>
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
                    <span class="stat-value"><?= count($offers) ?></span>
                    <span class="stat-label">الخدمات المقدَّمة</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= count(array_filter($offers, static fn(array $o): bool => $o['review_status'] === 'approved')) ?></span>
                    <span class="stat-label">معتمدة</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= count($orders) ?></span>
                    <span class="stat-label">طلبات موجَّهة إليك</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= e(number_format($payable, 2)) ?></span>
                    <span class="stat-label">مستحقات جاهزة للصرف</span>
                </div>
            </div>

            <?php if ($held > 0): ?>
                <p class="account-note">مبلغ <?= e(number_format($held, 2)) ?> محجوز حتى انتهاء فترة الأمان على الطلبات المسلَّمة.</p>
            <?php endif; ?>

        <?php elseif ($tab === 'offers'): ?>
            <?php if ($approved): ?>
                <form method="post" class="account-form reveal">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="submit_offer">
                    <h2 class="account-section-title">تقديم خدمة جديدة</h2>

                    <div class="form-group">
                        <label class="form-label" for="title">عنوان الخدمة</label>
                        <input class="form-input" type="text" id="title" name="title" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="supplier_price">سعرك للمنصة</label>
                            <input class="form-input" type="number" step="0.01" min="0"
                                   id="supplier_price" name="supplier_price" dir="ltr">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="execution_time">مدة التنفيذ</label>
                            <input class="form-input" type="text" id="execution_time"
                                   name="execution_time" placeholder="خلال 24 ساعة">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">وصف الخدمة</label>
                        <textarea class="form-input form-textarea" id="description" name="description"></textarea>
                    </div>

                    <button class="btn btn-primary" type="submit">إرسال للمراجعة</button>
                </form>
            <?php endif; ?>

            <?php if ($offers): ?>
                <h2 class="account-section-title">الخدمات المقدَّمة</h2>
                <div class="account-table-wrap reveal">
                    <table class="account-table">
                        <thead><tr><th>الخدمة</th><th>سعرك</th><th>الحالة</th><th>ملاحظات الإدارة</th><th>التاريخ</th></tr></thead>
                        <tbody>
                        <?php foreach ($offers as $offer): ?>
                            <tr>
                                <td><?= e((string) $offer['title']) ?></td>
                                <td><?= $offer['supplier_price'] !== null ? e(number_format((float) $offer['supplier_price'], 2)) : '—' ?></td>
                                <td><span class="pill pill--<?= $offer['review_status'] === 'approved' ? 'completed' : ($offer['review_status'] === 'rejected' ? 'cancelled' : 'new') ?>"><?= e(offer_status_label((string) $offer['review_status'])) ?></span></td>
                                <td><?= e(mb_strimwidth((string) ($offer['admin_notes'] ?? '—'), 0, 60, '…')) ?></td>
                                <td dir="ltr"><?= e(date('Y/m/d', strtotime((string) $offer['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state reveal"><p>لم تقدّم أي خدمة بعد.</p></div>
            <?php endif; ?>

        <?php elseif ($tab === 'orders'): ?>
            <?php if ($orders): ?>
                <div class="account-table-wrap reveal">
                    <table class="account-table">
                        <thead><tr><th>الكود</th><th>الخدمة</th><th>الكمية</th><th>الهدف</th><th>حالة الطلب</th><th>التاريخ</th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td dir="ltr"><?= e((string) $order['order_code']) ?></td>
                                <td><?= e((string) $order['service_name']) ?></td>
                                <td><?= (int) $order['quantity'] ?></td>
                                <td dir="ltr"><?= e(mb_strimwidth((string) ($order['target_url'] ?? '—'), 0, 40, '…')) ?></td>
                                <td><span class="pill pill--<?= e((string) $order['order_status']) ?>"><?= e((string) ($order['supplier_status'] ?? $order['order_status'])) ?></span></td>
                                <td dir="ltr"><?= e(date('Y/m/d', strtotime((string) $order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="account-note">بيانات العميل لا تظهر للمورد. أي تواصل يتم عبر دعم المنصة.</p>
            <?php else: ?>
                <div class="empty-state reveal"><p>لا توجد طلبات موجَّهة إليك بعد.</p></div>
            <?php endif; ?>

        <?php elseif ($tab === 'settlements'): ?>
            <?php if ($settlements): ?>
                <div class="account-table-wrap reveal">
                    <table class="account-table">
                        <thead><tr><th>المبلغ</th><th>الحالة</th><th>متاح بعد</th><th>ملاحظة</th><th>التاريخ</th></tr></thead>
                        <tbody>
                        <?php foreach ($settlements as $row): ?>
                            <tr>
                                <td><?= e(number_format((float) $row['amount'], 2)) ?> <?= e((string) $row['currency']) ?></td>
                                <td><?= e(settlement_status_label((string) $row['status'])) ?></td>
                                <td dir="ltr"><?= e($row['hold_until'] ? date('Y/m/d', strtotime((string) $row['hold_until'])) : '—') ?></td>
                                <td><?= e(mb_strimwidth((string) ($row['note'] ?? '—'), 0, 50, '…')) ?></td>
                                <td dir="ltr"><?= e(date('Y/m/d', strtotime((string) $row['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state reveal"><p>لا توجد مستحقات بعد.</p></div>
            <?php endif; ?>

        <?php else: ?>
            <form method="post" class="account-form reveal">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="company_name">اسم الشركة أو النشاط</label>
                        <input class="form-input" type="text" id="company_name" name="company_name"
                               value="<?= e((string) ($profile['company_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="telegram">تيليجرام</label>
                        <input class="form-input" type="text" id="telegram" name="telegram" dir="ltr"
                               value="<?= e((string) ($profile['telegram'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="services_desc">الخدمات التي تقدّمها</label>
                    <textarea class="form-input form-textarea" id="services_desc"
                              name="services_desc"><?= e((string) ($profile['services_desc'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bio">نبذة</label>
                    <textarea class="form-input form-textarea" id="bio"
                              name="bio"><?= e((string) ($profile['bio'] ?? '')) ?></textarea>
                </div>

                <button class="btn btn-primary" type="submit">حفظ البيانات</button>
            </form>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
