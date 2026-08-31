<?php
require_once __DIR__ . '/lib/auth.php';
auth_boot();

$page_title = 'استعادة كلمة المرور | Elawaady XDigital';
$sent       = false;
$errors     = [];
$devLink    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email = trim((string) ($_POST['email'] ?? ''));

    $attempts = auth_throttle_hit(auth_client_ip() ?: 'unknown', 'password_reset', 60);
    if ($attempts > 5) {
        $errors[] = 'طلبات كثيرة. حاول بعد قليل.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح.';
    } else {
        $token = auth_create_password_reset($email);

        // The answer is the same whether or not the address is registered.
        // Telling the difference would turn this form into an account finder.
        $sent = true;

        if ($token !== null) {
            // Delivery is wired to the mail provider at integration time. Until
            // then the link is written to the error log rather than shown, so
            // no page ever discloses a live reset token.
            error_log('[EXD reset] token issued for ' . $email);

            if (strtolower((string) (getenv('APP_ENV') ?: '')) === 'development') {
                $devLink = 'reset-password.php?token=' . urlencode($token);
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<section class="auth-wrap">
    <div class="container">
        <div class="auth-card reveal">
            <h1>استعادة كلمة المرور</h1>

            <?php if ($sent): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div>
                        <strong>تم استلام طلبك.</strong>
                        <p>إذا كان هذا البريد مسجّلًا لدينا، ستصلك رسالة بها رابط إعادة التعيين.</p>
                    </div>
                </div>
                <?php if ($devLink !== ''): ?>
                    <div class="alert alert-info">
                        <span>⚙</span>
                        <div>وضع التطوير — <a href="<?= e($devLink) ?>">رابط إعادة التعيين</a></div>
                    </div>
                <?php endif; ?>
                <div class="auth-links"><a href="login.php">← العودة لتسجيل الدخول</a></div>
            <?php else: ?>
                <p class="auth-sub">أدخل بريدك المسجّل وسنرسل إليك رابط إعادة التعيين.</p>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
                <?php endforeach; ?>

                <form method="post" class="auth-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="email">البريد الإلكتروني</label>
                        <input class="form-input" type="email" id="email" name="email" dir="ltr" required
                               value="<?= e((string) ($_POST['email'] ?? '')) ?>">
                    </div>
                    <button class="btn btn-primary btn-full" type="submit">إرسال الرابط</button>
                </form>

                <div class="auth-links">
                    <a href="login.php">← تسجيل الدخول</a>
                    <a href="register.php">إنشاء حساب</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
