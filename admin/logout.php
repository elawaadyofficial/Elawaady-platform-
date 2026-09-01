<?php
require_once __DIR__ . '/../lib/admin_auth.php';
admin_boot();

// Signing out changes state, so it is a POST with a token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    admin_logout();
    header('Location: login.php?signedout=1');
    exit;
}

if (!admin_check()) {
    header('Location: login.php');
    exit;
}

$page_title_admin = 'تسجيل الخروج';
require_once __DIR__ . '/../settings_helper.php';
include __DIR__ . '/layout.php';
?>
<div class="panel">
  <div class="panel-header"><div class="panel-title">تسجيل الخروج</div></div>
  <p class="text-muted" style="margin-bottom:16px;">هل تريد إنهاء جلسة لوحة التحكم؟</p>
  <form method="post">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary">تأكيد الخروج</button>
    <a href="index.php" class="btn btn-secondary">إلغاء</a>
  </form>
</div>
    </div>
  </div>
</div>
</body>
</html>
