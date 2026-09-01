<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('settings.manage');

$page_title_admin = 'إعدادات المنصة';

/**
 * The settings that change how the platform behaves, not how it looks.
 *
 * Each one is read at the point it matters: the support channels appear in the
 * footer and on the contact page, the mediation switch decides whether the
 * mediation section renders at all, and the supplier switch decides whether
 * the supplier tab appears on the registration page. A control that changed
 * nothing would not be here.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $admin = admin_user();

    $payload = [
        'support_whatsapp'     => mb_substr(trim((string) ($_POST['support_whatsapp'] ?? '')), 0, 190),
        'support_whatsapp_alt' => mb_substr(trim((string) ($_POST['support_whatsapp_alt'] ?? '')), 0, 190),
        'support_telegram'     => mb_substr(trim((string) ($_POST['support_telegram'] ?? '')), 0, 190),
        'support_messenger'    => mb_substr(trim((string) ($_POST['support_messenger'] ?? '')), 0, 190),
        'support_email'        => mb_substr(trim((string) ($_POST['support_email'] ?? '')), 0, 190),
        'support_hours'        => mb_substr(trim((string) ($_POST['support_hours'] ?? '')), 0, 190),
        'default_currency'     => mb_substr(trim((string) ($_POST['default_currency'] ?? 'EGP')), 0, 10),
        'mediation_default_safety_days' => (string) max(0, min(90, (int) ($_POST['mediation_default_safety_days'] ?? 7))),
        'mediation_enabled'        => empty($_POST['mediation_enabled'])        ? '0' : '1',
        'supplier_signup_open'     => empty($_POST['supplier_signup_open'])     ? '0' : '1',
        'reviews_require_approval' => empty($_POST['reviews_require_approval']) ? '0' : '1',
    ];

    $errors = [];
    if ($payload['support_email'] !== '' && !filter_var($payload['support_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'بريد الدعم غير صحيح.';
    }
    foreach (['support_whatsapp', 'support_whatsapp_alt'] as $key) {
        if ($payload[$key] !== '' && !preg_match('/^[0-9+]{8,20}$/', $payload[$key])) {
            $errors[] = 'رقم الواتساب يجب أن يكون أرقامًا فقط بصيغة دولية.';
            break;
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            admin_flash('error', $error);
        }
        admin_redirect('settings.php');
    }

    save_site_settings($payload, (int) $admin['id']);
    admin_audit('settings.platform_updated', 'system_settings', null, 'إعدادات المنصة');
    admin_flash('success', 'تم حفظ الإعدادات.');
    admin_redirect('settings.php');
}

$s = load_site_settings();

// Show where each setting is actually consumed, so nobody has to guess.
$usage = [
    'support_whatsapp'         => 'الفوتر · صفحة التواصل · زر الطلب عبر واتساب',
    'support_telegram'         => 'الفوتر · صفحة التواصل',
    'support_email'            => 'الفوتر · صفحة التواصل',
    'mediation_enabled'        => 'قسم الوساطة في الرئيسية · صفحة الوساطة',
    'supplier_signup_open'     => 'تبويب «حساب مورد» في صفحة التسجيل',
    'reviews_require_approval' => 'ظهور تقييم جديد على صفحة الخدمة',
    'default_currency'         => 'الطلبات الجديدة والمحافظ',
    'mediation_default_safety_days' => 'مدة الأمان الافتراضية على صفقات الوساطة',
];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<form method="post">
  <?= csrf_field() ?>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">قنوات الدعم</div></div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">واتساب الدعم</label>
        <input class="form-input" type="text" name="support_whatsapp" dir="ltr"
               value="<?= e((string) $s['support_whatsapp']) ?>" placeholder="201055578777">
        <small class="upload-hint"><?= e($usage['support_whatsapp']) ?></small>
      </div>
      <div class="form-group">
        <label class="form-label">واتساب إضافي</label>
        <input class="form-input" type="text" name="support_whatsapp_alt" dir="ltr"
               value="<?= e((string) $s['support_whatsapp_alt']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">تيليجرام</label>
        <input class="form-input" type="text" name="support_telegram" dir="ltr"
               value="<?= e((string) $s['support_telegram']) ?>">
        <small class="upload-hint"><?= e($usage['support_telegram']) ?></small>
      </div>
      <div class="form-group">
        <label class="form-label">ماسنجر</label>
        <input class="form-input" type="text" name="support_messenger" dir="ltr"
               value="<?= e((string) $s['support_messenger']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">بريد الدعم</label>
        <input class="form-input" type="email" name="support_email" dir="ltr"
               value="<?= e((string) $s['support_email']) ?>">
        <small class="upload-hint"><?= e($usage['support_email']) ?></small>
      </div>
      <div class="form-group">
        <label class="form-label">ساعات العمل</label>
        <input class="form-input" type="text" name="support_hours"
               value="<?= e((string) $s['support_hours']) ?>" placeholder="يوميًا من 10ص حتى 12م">
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">سلوك المنصة</div></div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">العملة الافتراضية</label>
        <input class="form-input" type="text" name="default_currency" dir="ltr"
               value="<?= e((string) $s['default_currency']) ?>" maxlength="10">
        <small class="upload-hint"><?= e($usage['default_currency']) ?></small>
      </div>
      <div class="form-group">
        <label class="form-label">أيام الأمان الافتراضية للوساطة</label>
        <input class="form-input" type="number" min="0" max="90" dir="ltr"
               name="mediation_default_safety_days" value="<?= e((string) $s['mediation_default_safety_days']) ?>">
        <small class="upload-hint"><?= e($usage['mediation_default_safety_days']) ?></small>
      </div>
    </div>

    <div class="form-section">
      <label class="form-check">
        <input type="checkbox" name="mediation_enabled" value="1" <?= $s['mediation_enabled'] === '1' ? 'checked' : '' ?>>
        <span>تفعيل الوساطة الآمنة — <span class="text-muted"><?= e($usage['mediation_enabled']) ?></span></span>
      </label>
      <label class="form-check">
        <input type="checkbox" name="supplier_signup_open" value="1" <?= $s['supplier_signup_open'] === '1' ? 'checked' : '' ?>>
        <span>استقبال طلبات الموردين — <span class="text-muted"><?= e($usage['supplier_signup_open']) ?></span></span>
      </label>
      <label class="form-check">
        <input type="checkbox" name="reviews_require_approval" value="1" <?= $s['reviews_require_approval'] === '1' ? 'checked' : '' ?>>
        <span>مراجعة التقييمات قبل نشرها — <span class="text-muted"><?= e($usage['reviews_require_approval']) ?></span></span>
      </label>
    </div>
  </div>

  <button class="btn btn-primary" type="submit">حفظ الإعدادات</button>
</form>

<div class="panel">
  <div class="panel-header"><div class="panel-title">أسرار الاستضافة</div></div>
  <p class="text-muted" style="font-size:13px; line-height:2;">
    مفاتيح الـAPI وبيانات قاعدة البيانات ومفتاح التشفير تُضبط في متغيّرات البيئة على الاستضافة،
    ولا تُحفظ في قاعدة البيانات ولا في المستودع ولا تُعرض في هذه اللوحة.
  </p>
  <table class="kv">
    <tr><td dir="ltr">APP_ENV</td><td dir="ltr"><?= e((string) (getenv('APP_ENV') ?: '—')) ?></td></tr>
    <tr><td dir="ltr">APP_URL</td><td dir="ltr"><?= e((string) (getenv('APP_URL') ?: '—')) ?></td></tr>
    <tr><td dir="ltr">DB_NAME</td><td dir="ltr"><?= e((string) (getenv('DB_NAME') ?: '—')) ?></td></tr>
    <tr><td dir="ltr">APP_ENCRYPTION_KEY</td>
        <td><?= getenv('APP_ENCRYPTION_KEY') ? admin_badge('مضبوط', 'active') : admin_badge('غير مضبوط', 'hidden') ?></td></tr>
  </table>
</div>

<?php admin_layout_end(); ?>
