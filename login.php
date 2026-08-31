<?php
require_once __DIR__ . '/lib/auth.php';
auth_boot();

// Already signed in? There is nothing on this page for you.
if (auth_check()) {
    $user = auth_user();
    header('Location: ' . ($user['account_type'] === 'supplier' ? 'supplier-dashboard.php' : 'account.php'));
    exit;
}

$page_title = 'تسجيل الدخول | Elawaady XDigital';
$errors     = [];
$notice     = '';

// Where to land after signing in. Only a path inside this site is accepted, so
// the parameter cannot be used to bounce someone to another domain.
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if ($next !== '' && (str_contains($next, '://') || str_starts_with($next, '//'))) {
    $next = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($email === '' || $password === '') {
        $errors[] = 'يُرجى إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        [$ok, $user, $message] = auth_attempt($email, $password, $remember);
        if ($ok) {
            $target = $next !== '' ? $next
                : ($user['account_type'] === 'supplier' ? 'supplier-dashboard.php' : 'account.php');
            header('Location: ' . $target);
            exit;
        }
        $errors[] = $message;
    }
}

if (isset($_GET['registered'])) {
    $notice = 'تم إنشاء حسابك. سجّل الدخول للمتابعة.';
}
if (isset($_GET['reset'])) {
    $notice = 'تم تغيير كلمة المرور. سجّل الدخول بكلمة المرور الجديدة.';
}
if (isset($_GET['signedout'])) {
    $notice = 'تم تسجيل الخروج.';
}

require_once __DIR__ . '/header.php';
?>

<section class="auth-wrap">
    <div class="container">
        <div class="auth-card reveal">
            <h1>تسجيل الدخول</h1>
            <p class="auth-sub">ادخل إلى حسابك لمتابعة طلباتك ومحفظتك.</p>

            <?php if ($notice !== ''): ?>
                <div class="alert alert-success"><span>✅</span><div><?= e($notice) ?></div></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
            <?php endforeach; ?>

            <form method="post" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="form-group">
                    <label class="form-label" for="email">البريد الإلكتروني</label>
                    <input class="form-input" type="email" id="email" name="email" dir="ltr"
                           autocomplete="email" required
                           value="<?= e((string) ($_POST['email'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <div class="label-row">
                        <label class="form-label" for="password">كلمة المرور</label>
                        <a class="form-link" href="forgot-password.php">نسيت كلمة المرور؟</a>
                    </div>
                    <input class="form-input" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">تذكّرني على هذا الجهاز</label>
                </div>

                <button class="btn btn-primary btn-full" type="submit">تسجيل الدخول</button>
            </form>

            <div class="auth-links">
                <span>ليس لديك حساب؟</span>
                <a href="register.php">إنشاء حساب</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
