<?php
/**
 * Dashboard chrome.
 *
 * The navigation is built from what the signed-in staff member may actually
 * do. A link to a page they would be refused is not rendered — the permission
 * check on the page itself is the real guard, and this keeps the menu honest
 * rather than advertising doors that do not open.
 */

require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../settings_helper.php';

// The layout can be included from inside a function — admin_require() renders
// denied.php that way — where a global set at file scope is not in scope. Fetch
// it explicitly rather than assuming.
$site_settings = $GLOBALS['site_settings'] ?? null;
if (!is_array($site_settings)) {
    $site_settings = load_site_settings();
}

if (!isset($page_title_admin)) {
    $page_title_admin = 'لوحة التحكم';
}

$current_admin = basename((string) $_SERVER['PHP_SELF']);
$admin_account = admin_user();
$admin_logo    = logo_url($site_settings['logo_admin'] ?: $site_settings['logo_main'], true);

/**
 * One navigation entry.
 *
 * $permission '' means every staff member may see it.
 */
function admin_nav_item(string $href, string $icon, string $label, string $permission = '', array $alsoActive = []): void {
    global $current_admin;

    if ($permission !== '' && !admin_can($permission)) {
        return;
    }

    $active = $current_admin === $href || in_array($current_admin, $alsoActive, true);
    printf(
        '<a href="%s" class="%s"><span class="nav-icon">%s</span> %s</a>' . "\n",
        e($href),
        $active ? 'active' : '',
        e($icon),
        e($label)
    );
}

/** A group heading, printed only if the person can reach something under it. */
function admin_nav_group(string $label, array $permissions): void {
    if ($permissions !== [] && !admin_can_any($permissions)) {
        return;
    }
    printf('<span class="nav-label">%s</span>' . "\n", e($label));
}

$pending_suppliers = admin_can('suppliers.approve')
    ? admin_count("SELECT COUNT(*) AS n FROM platform_users WHERE account_type='supplier' AND status='pending'")
    : 0;
$pending_offers = admin_can('suppliers.services')
    ? admin_count("SELECT COUNT(*) AS n FROM supplier_offers WHERE review_status='pending_review'")
    : 0;
$new_orders_badge = admin_can('orders.view')
    ? admin_count("SELECT COUNT(*) AS n FROM orders WHERE order_status='new'")
    : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title_admin) ?> | Admin — <?= e($site_settings['brand_name_en']) ?></title>
<link rel="stylesheet" href="style.css">
<script src="admin.js" defer></script>
<meta name="robots" content="noindex,nofollow">
<?php if ($site_settings['favicon']): ?>
<link rel="icon" href="<?= e(logo_url($site_settings['favicon'], true)) ?>" type="image/png">
<?php endif; ?>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="admin-wrap">

  <aside class="admin-sidebar" id="admin-sidebar">
    <button class="sidebar-close-btn" type="button" data-close-sidebar aria-label="إغلاق القائمة">✕</button>

    <div class="sidebar-brand">
      <?php if ($admin_logo): ?>
        <img src="<?= e($admin_logo) ?>" alt="<?= e($site_settings['brand_name_en']) ?>" class="admin-sidebar-logo">
      <?php else: ?>
        <div class="brand-mark">XD</div>
        <div>
          <strong><?= e($site_settings['brand_name_en']) ?></strong>
          <small>لوحة التحكم</small>
        </div>
      <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
      <?php admin_nav_group('الرئيسية', []); ?>
      <?php admin_nav_item('index.php', '🏠', 'لوحة التحكم'); ?>

      <?php admin_nav_group('الكتالوج', ['catalog.view', 'catalog.manage']); ?>
      <?php admin_nav_item('categories.php', '📁', 'الأقسام', 'catalog.view', ['category-form.php']); ?>
      <?php admin_nav_item('services.php', '🛍️', 'الخدمات', 'catalog.view', ['service-form.php']); ?>
      <?php admin_nav_item('placements.php', '📌', 'أماكن العرض', 'catalog.manage'); ?>

      <?php admin_nav_group('المبيعات', ['orders.view']); ?>
      <?php admin_nav_item('orders.php', '📋', 'الطلبات' . ($new_orders_badge > 0 ? " ($new_orders_badge)" : ''), 'orders.view', ['order-view.php']); ?>
      <?php admin_nav_item('payments.php', '💳', 'المدفوعات', 'payments.confirm'); ?>
      <?php admin_nav_item('mediation.php', '🤝', 'الوساطة', 'mediation.view'); ?>

      <?php admin_nav_group('الحسابات', ['users.view', 'suppliers.view']); ?>
      <?php admin_nav_item('users.php', '👤', 'المستخدمون', 'users.view'); ?>
      <?php admin_nav_item('suppliers.php', '🏭', 'الموردون' . ($pending_suppliers > 0 ? " ($pending_suppliers)" : ''), 'suppliers.view'); ?>
      <?php admin_nav_item('supplier-offers.php', '📦', 'خدمات الموردين' . ($pending_offers > 0 ? " ($pending_offers)" : ''), 'suppliers.services'); ?>
      <?php admin_nav_item('wallets.php', '👛', 'المحافظ', 'wallet.view'); ?>

      <?php admin_nav_group('الأصول والمزودون', ['assets.view', 'providers.manage']); ?>
      <?php admin_nav_item('digital-assets.php', '💎', 'الأصول الرقمية', 'assets.view'); ?>
      <?php admin_nav_item('providers.php', '🔌', 'مزودو API', 'providers.manage'); ?>
      <?php admin_nav_item('provider-services.php', '⬇️', 'سحب خدمات المزودين', 'providers.manage'); ?>

      <?php admin_nav_group('الواجهة', ['cms.manage', 'media.manage']); ?>
      <?php admin_nav_item('homepage-sections.php', '🧩', 'أقسام الرئيسية', 'cms.manage'); ?>
      <?php admin_nav_item('carousel.php', '🎠', 'الكاروسيل', 'media.manage'); ?>
      <?php admin_nav_item('pages.php', '📄', 'الصفحات والسياسات', 'cms.manage'); ?>
      <?php admin_nav_item('brand-settings.php', '🎨', 'الهوية البصرية', 'cms.manage'); ?>
      <?php admin_nav_item('chatbot-knowledge.php', '🤖', 'الشات بوت', 'cms.manage'); ?>

      <?php admin_nav_group('النظام', ['settings.manage', 'rbac.manage', 'audit.view']); ?>
      <?php admin_nav_item('settings.php', '⚙️', 'إعدادات المنصة', 'settings.manage'); ?>
      <?php admin_nav_item('staff.php', '🛡️', 'الفريق والصلاحيات', 'rbac.manage'); ?>
      <?php admin_nav_item('audit.php', '📜', 'سجل العمليات', 'audit.view'); ?>

      <span class="nav-label">عام</span>
      <a href="../index.php" target="_blank" rel="noopener"><span class="nav-icon">🌐</span> عرض الموقع</a>
    </nav>

    <div class="sidebar-footer">
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="sidebar-logout"><span class="nav-icon">🚪</span> تسجيل الخروج</button>
      </form>
    </div>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle-btn" type="button" data-toggle-sidebar aria-label="فتح القائمة">☰</button>
        <div class="topbar-title"><?= e($page_title_admin) ?></div>
      </div>
      <div class="topbar-right">
        <span class="text-muted" style="font-size:12px;">
          <?= e((string) ($admin_account['display_name'] ?: $admin_account['username'])) ?>
        </span>
      </div>
    </div>
    <div class="admin-content">
