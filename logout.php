<?php
require_once __DIR__ . '/lib/auth.php';
auth_boot();

// Signing out changes state, so it is a POST with a token like any other.
// A GET simply shows the confirmation rather than acting on it.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $user = auth_user();
    if ($user !== null) {
        audit_log($user['account_type'], (int) $user['id'], (string) $user['name'], 'account.logout');
    }
    auth_logout();
    header('Location: login.php?signedout=1');
    exit;
}

$page_title = 'تسجيل الخروج | Elawaady XDigital';
require_once __DIR__ . '/header.php';
?>

<section class="auth-wrap">
    <div class="container">
        <div class="auth-card reveal">
            <h1>تسجيل الخروج</h1>
            <p class="auth-sub">هل تريد إنهاء الجلسة على هذا الجهاز؟</p>
            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-full" type="submit">تأكيد الخروج</button>
            </form>
            <div class="auth-links"><a href="index.php">← العودة للمتجر</a></div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
