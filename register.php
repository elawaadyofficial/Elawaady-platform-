<?php
require_once __DIR__ . '/lib/auth.php';
auth_boot();

if (auth_check()) {
    header('Location: account.php');
    exit;
}

$page_title = 'إنشاء حساب | Elawaady XDigital';

$accountType = (string) ($_POST['account_type'] ?? $_GET['type'] ?? 'user');
if (!in_array($accountType, ['user', 'supplier'], true)) {
    $accountType = 'user';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $attempts = auth_throttle_hit(auth_client_ip() ?: 'unknown', 'register', 60);
    if ($attempts > 10) {
        $errors[] = 'محاولات كثيرة من هذا الجهاز. حاول لاحقًا.';
    } else {
        $errors = auth_validate_registration($_POST, $accountType);

        if (!$errors && auth_email_taken(strtolower(trim((string) $_POST['email'])))) {
            $errors[] = 'هذا البريد الإلكتروني مسجّل بالفعل.';
        }

        if (!$errors) {
            try {
                auth_register($_POST, $accountType);
                header('Location: login.php?registered=1');
                exit;
            } catch (mysqli_sql_exception $e) {
                error_log('[EXD register] ' . $e->getMessage());
                $errors[] = 'تعذّر إنشاء الحساب الآن. حاول مرة أخرى.';
            }
        }
    }
}

// The two account types this platform has. There is no third.
$types = [
    'user' => [
        'title' => 'حساب مستخدم',
        'icon'  => '◆',
        'desc'  => 'لشراء الخدمات ومتابعة الطلبات والمحفظة.',
    ],
    'supplier' => [
        'title' => 'حساب مورد',
        'icon'  => '◈',
        'desc'  => 'لتقديم الخدمات عبر المنصة بعد اعتماد الحساب.',
    ],
];

require_once __DIR__ . '/header.php';
?>

<section class="auth-wrap">
    <div class="container">
        <div class="auth-card auth-card-wide reveal">
            <h1>إنشاء حساب جديد</h1>
            <p class="auth-sub">اختر نوع الحساب المناسب لك.</p>

            <div class="account-type-tabs">
                <?php foreach ($types as $key => $info): ?>
                    <a class="type-tab <?= $accountType === $key ? 'is-active' : '' ?>"
                       href="register.php?type=<?= e($key) ?>">
                        <span class="type-icon"><?= e($info['icon']) ?></span>
                        <strong><?= e($info['title']) ?></strong>
                        <small><?= e($info['desc']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
            <?php endforeach; ?>

            <?php if ($accountType === 'supplier'): ?>
                <div class="alert alert-info">
                    <span>◷</span>
                    <div>حساب المورد يُراجَع قبل التفعيل. يمكنك الدخول ومتابعة حالة الطلب فور التسجيل.</div>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="account_type" value="<?= e($accountType) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">الاسم الكامل <span class="req">*</span></label>
                        <input class="form-input" type="text" id="name" name="name" required
                               autocomplete="name" value="<?= e((string) ($_POST['name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">رقم الهاتف <span class="req">*</span></label>
                        <input class="form-input" type="tel" id="phone" name="phone" dir="ltr" required
                               autocomplete="tel" value="<?= e((string) ($_POST['phone'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">البريد الإلكتروني <span class="req">*</span></label>
                    <input class="form-input" type="email" id="email" name="email" dir="ltr" required
                           autocomplete="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
                </div>

                <?php if ($accountType === 'supplier'): ?>
                    <div class="form-group">
                        <label class="form-label" for="company">اسم الشركة أو النشاط</label>
                        <input class="form-input" type="text" id="company" name="company"
                               value="<?= e((string) ($_POST['company'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="services_desc">الخدمات التي تقدّمها</label>
                        <textarea class="form-input form-textarea" id="services_desc"
                                  name="services_desc"><?= e((string) ($_POST['services_desc'] ?? '')) ?></textarea>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">كلمة المرور <span class="req">*</span></label>
                        <input class="form-input" type="password" id="password" name="password"
                               autocomplete="new-password" required>
                        <small class="form-hint">8 أحرف على الأقل، وتحتوي على حرف ورقم.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">تأكيد كلمة المرور <span class="req">*</span></label>
                        <input class="form-input" type="password" id="confirm_password"
                               name="confirm_password" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="agree" name="agree" value="1" required
                           <?= !empty($_POST['agree']) ? 'checked' : '' ?>>
                    <label for="agree">
                        أوافق على <a href="page.php?slug=terms">شروط الاستخدام</a>
                        و<a href="page.php?slug=privacy">سياسة الخصوصية</a>
                    </label>
                </div>

                <button class="btn btn-primary btn-full" type="submit">إنشاء الحساب</button>
            </form>

            <div class="auth-links">
                <span>لديك حساب بالفعل؟</span>
                <a href="login.php">تسجيل الدخول</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
