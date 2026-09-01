<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../settings_helper.php';
admin_boot();

if (admin_check()) {
    header('Location: index.php');
    exit;
}

$error  = '';
$notice = isset($_GET['signedout']) ? 'تم تسجيل الخروج.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'يُرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        [$ok, $message] = admin_attempt($username, $password);
        if ($ok) {
            header('Location: index.php');
            exit;
        }
        $error = $message;
    }
}

$admin_logo = logo_url($site_settings['logo_admin'] ?: $site_settings['logo_main'], true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل دخول الإدارة | <?= e($site_settings['brand_name_en']) ?></title>
<link rel="stylesheet" href="style.css">
<meta name="robots" content="noindex,nofollow">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div style="text-align:center; margin-bottom:20px;">
      <?php if ($admin_logo): ?>
        <img src="<?= e($admin_logo) ?>" alt="<?= e($site_settings['brand_name_en']) ?>"
             style="max-height:72px; max-width:200px; object-fit:contain; margin:0 auto 12px; display:block;">
      <?php else: ?>
        <div class="brand-mark" style="margin:0 auto 8px; width:56px; height:56px; font-size:20px;">XD</div>
      <?php endif; ?>
      <h1><?= e($site_settings['brand_name_en']) ?></h1>
      <p>لوحة التحكم</p>
    </div>

    <?php if ($notice !== ''): ?>
      <div class="alert alert-success"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-group" style="margin-bottom:14px;">
        <label class="form-label">اسم المستخدم</label>
        <input type="text" name="username" class="form-input" dir="ltr" required autofocus
               value="<?= e((string) ($_POST['username'] ?? '')) ?>">
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">كلمة المرور</label>
        <input type="password" name="password" class="form-input" dir="ltr" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; min-height:44px; font-size:15px;">دخول</button>
    </form>
  </div>
</div>
</body>
</html>
