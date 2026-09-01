<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('cms.manage');

$page_title_admin = 'الهوية البصرية';

/**
 * Every control on this page writes a row in system_settings and is read back
 * by the storefront on the next request. There is nothing here that only looks
 * like a setting.
 *
 * The colour controls override design tokens rather than individual classes.
 * That is why there are eleven of them and not fifty: one token reaches every
 * surface built on it, so the store stays coherent when a colour changes, and
 * a control left empty means "keep the store's own value" — which is why the
 * default theme survives an untouched form.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $admin   = admin_user();
    $current = load_site_settings();

    $payload = [
        'brand_name_ar'  => mb_substr(trim((string) ($_POST['brand_name_ar'] ?? '')), 0, 190),
        'brand_name_en'  => mb_substr(trim((string) ($_POST['brand_name_en'] ?? '')), 0, 190),
        'brand_subtitle' => mb_substr(trim((string) ($_POST['brand_subtitle'] ?? '')), 0, 190),
        'licence_number' => mb_substr(trim((string) ($_POST['licence_number'] ?? '')), 0, 60),
    ];

    foreach (['logo_main', 'logo_header', 'logo_icon', 'logo_footer', 'logo_admin', 'favicon'] as $slot) {
        if (!empty($_POST['clear_' . $slot])) {
            $payload[$slot] = '';
            continue;
        }
        $payload[$slot] = upload_logo_file($slot, (string) ($current[$slot] ?? ''));
    }

    // A colour that is not a colour is stored as empty, so a stray value can
    // never reach a stylesheet.
    foreach ([
        'theme_bg_base', 'theme_bg_panel', 'theme_text', 'theme_text_muted',
        'theme_border', 'theme_accent', 'theme_accent_2',
        'theme_cta_from', 'theme_cta_to', 'theme_glow',
    ] as $key) {
        $payload[$key] = sanitize_css_color((string) ($_POST[$key] ?? ''));
    }

    $opacity = (float) ($_POST['theme_glow_opacity'] ?? 0);
    $payload['theme_glow_opacity'] = (string) max(0.0, min(1.0, $opacity));

    $payload['announcement_text']   = mb_substr(trim((string) ($_POST['announcement_text'] ?? '')), 0, 300);
    $payload['announcement_active'] = empty($_POST['announcement_active']) ? '0' : '1';

    save_site_settings($payload, (int) $admin['id']);
    admin_audit('settings.brand_updated', 'system_settings', null, 'الهوية البصرية');
    admin_flash('success', 'تم حفظ الهوية البصرية. التغيير ظاهر على المتجر الآن.');
    admin_redirect('brand-settings.php');
}

$s = load_site_settings();

$logoSlots = [
    'logo_main'   => 'الشعار الأساسي',
    'logo_header' => 'شعار الهيدر',
    'logo_footer' => 'شعار الفوتر',
    'logo_icon'   => 'الأيقونة المربعة',
    'logo_admin'  => 'شعار لوحة التحكم',
    'favicon'     => 'أيقونة المتصفح',
];

$themeSlots = [
    'theme_bg_base'    => ['خلفية الصفحة',        '--exd-bg-base'],
    'theme_bg_panel'   => ['خلفية الكروت',        '--exd-bg-panel'],
    'theme_text'       => ['لون النص',            '--exd-text'],
    'theme_text_muted' => ['لون النص الثانوي',    '--exd-text-muted'],
    'theme_border'     => ['لون الحدود',          '--exd-border'],
    'theme_accent'     => ['اللون المميز',        '--exd-violet-400'],
    'theme_accent_2'   => ['اللون المميز الثاني', '--exd-magenta-500'],
    'theme_cta_from'   => ['بداية تدرّج زر الشراء', '--exd-gradient-cta'],
    'theme_cta_to'     => ['نهاية تدرّج زر الشراء', '--exd-gradient-cta'],
    'theme_glow'       => ['لون التوهج',          '--exd-glow'],
];

$preview = build_theme_css($s);

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">اسم المتجر</div></div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">الاسم بالعربية</label>
        <input class="form-input" type="text" name="brand_name_ar" value="<?= e((string) $s['brand_name_ar']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">الاسم بالإنجليزية</label>
        <input class="form-input" type="text" name="brand_name_en" dir="ltr" value="<?= e((string) $s['brand_name_en']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">الوصف تحت الاسم</label>
        <input class="form-input" type="text" name="brand_subtitle" value="<?= e((string) $s['brand_subtitle']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">رقم الترخيص</label>
        <input class="form-input" type="text" name="licence_number" dir="ltr" value="<?= e((string) $s['licence_number']) ?>">
        <small class="upload-hint">يظهر في صفحة الوساطة وفي الفوتر.</small>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">الشعارات</div></div>
    <div class="form-grid">
      <?php foreach ($logoSlots as $slot => $label): ?>
        <div class="form-group">
          <label class="form-label"><?= e($label) ?></label>
          <?php $url = logo_url((string) $s[$slot], true); ?>
          <?php if ($url !== ''): ?>
            <div class="img-preview-wrap">
              <img src="<?= e($url) ?>" alt="" style="max-height:64px; max-width:160px; object-fit:contain;">
            </div>
            <label class="form-check">
              <input type="checkbox" name="clear_<?= e($slot) ?>" value="1">
              <span>حذف الشعار الحالي</span>
            </label>
          <?php endif; ?>
          <input class="form-input" type="file" name="<?= e($slot) ?>" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon">
          <small class="upload-hint">PNG أو WebP أو SVG — حتى 4 ميجابايت.</small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">ألوان المتجر</div></div>
    <p class="text-muted" style="font-size:13px; line-height:1.9;">
      كل لون هنا يستبدل متغيّرًا في نظام التصميم، فيتغيّر معه كل عنصر مبني عليه.
      الحقل الفارغ يعني «اترك لون المتجر كما هو» — وهي الحالة الطبيعية.
    </p>

    <div class="form-grid-3 mt-16">
      <?php foreach ($themeSlots as $key => [$label, $token]): ?>
        <div class="form-group">
          <label class="form-label"><?= e($label) ?></label>
          <div class="color-row">
            <input type="color" name="<?= e($key) ?>"
                   value="<?= e(preg_match('/^#[0-9a-f]{6}$/i', (string) $s[$key]) ? (string) $s[$key] : '#000000') ?>">
            <input class="form-input" type="text" name="<?= e($key) ?>" dir="ltr"
                   value="<?= e((string) $s[$key]) ?>" placeholder="اتركه فارغًا">
          </div>
          <small class="upload-hint" dir="ltr"><?= e($token) ?></small>
        </div>
      <?php endforeach; ?>

      <div class="form-group">
        <label class="form-label">شفافية التوهج</label>
        <input class="form-input" type="number" step="0.05" min="0" max="1" dir="ltr"
               name="theme_glow_opacity" value="<?= e((string) $s['theme_glow_opacity']) ?>">
      </div>
    </div>

    <?php if ($preview !== ''): ?>
      <div class="form-section">
        <div class="form-label">ما يُضاف إلى المتجر الآن</div>
        <pre dir="ltr" style="font-size:12px; overflow-x:auto; color:var(--cyan);"><?= e($preview) ?></pre>
      </div>
    <?php else: ?>
      <p class="text-muted" style="font-size:13px;">لا توجد تجاوزات مفعّلة — المتجر يستخدم ألوانه الأصلية.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">الشريط العلوي</div></div>
    <div class="form-group">
      <label class="form-label">نص الشريط</label>
      <input class="form-input" type="text" name="announcement_text"
             value="<?= e((string) $s['announcement_text']) ?>"
             placeholder="خدمات رقمية مختارة بعناية ودعم سريع">
    </div>
    <label class="form-check">
      <input type="checkbox" name="announcement_active" value="1" <?= $s['announcement_active'] === '1' ? 'checked' : '' ?>>
      <span>إظهار الشريط أعلى المتجر</span>
    </label>
  </div>

  <button class="btn btn-primary" type="submit">حفظ الهوية البصرية</button>
</form>

<?php admin_layout_end(); ?>
