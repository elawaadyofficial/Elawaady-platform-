<?php
require_once __DIR__ . '/lib/auth.php';
auth_boot();

$page_title = 'تعيين كلمة مرور جديدة | Elawaady XDigital';
$token      = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$errors     = [];

if ($token === '') {
    $errors[] = 'الرابط غير صالح أو ناقص.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    csrf_require();

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $errors[] = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'كلمة المرور يجب أن تحتوي على حرف ورقم.';
    }
    if ($password !== $confirm) {
        $errors[] = 'كلمة المرور وتأكيدها غير متطابقتين.';
    }

    if (!$errors) {
        if (auth_complete_password_reset($token, $password)) {
            header('Location: login.php?reset=1');
            exit;
        }
        $errors[] = 'الرابط منتهي أو مستخدم من قبل. اطلب رابطًا جديدًا.';
    }
}

require_once __DIR__ . '/header.php';
?>

<section class="auth-wrap">
    <div class="container">
        <div class="auth-card reveal">
            <h1>تعيين كلمة مرور جديدة</h1>
            <p class="auth-sub">اختر كلمة مرور جديدة لحسابك.</p>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
            <?php endforeach; ?>

            <form method="post" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور الجديدة</label>
                    <input class="form-input" type="password" id="password" name="password"
                           autocomplete="new-password" required>
                    <small class="form-hint">8 أحرف على الأقل، وتحتوي على حرف ورقم.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">تأكيد كلمة المرور</label>
                    <input class="form-input" type="password" id="confirm_password"
                           name="confirm_password" autocomplete="new-password" required>
                </div>

                <button class="btn btn-primary btn-full" type="submit">حفظ كلمة المرور</button>
            </form>

            <div class="auth-links"><a href="login.php">← العودة لتسجيل الدخول</a></div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
