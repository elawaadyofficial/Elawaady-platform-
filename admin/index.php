<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_helpers.php';

$page_title_admin = 'لوحة التحكم';

/** One figure. Returns null when the viewer may not see it. */
function stat_if(string $permission, string $sql): ?int {
    if ($permission !== '' && !admin_can($permission)) {
        return null;
    }
    return admin_count($sql);
}

$cards = array_filter([
    ['label' => '📁 الأقسام',           'value' => stat_if('catalog.view',      'SELECT COUNT(*) AS n FROM store_categories')],
    ['label' => '🛍️ الخدمات النشطة',    'value' => stat_if('catalog.view',      'SELECT COUNT(*) AS n FROM store_services WHERE is_active = 1')],
    ['label' => '📋 طلبات جديدة',       'value' => stat_if('orders.view',       "SELECT COUNT(*) AS n FROM orders WHERE order_status = 'new'")],
    ['label' => '⏳ طلبات جارية',       'value' => stat_if('orders.view',       "SELECT COUNT(*) AS n FROM orders WHERE order_status IN ('paid','in_progress','waiting_payment','waiting_approval')")],
    ['label' => '🏭 موردون بانتظار',    'value' => stat_if('suppliers.view',    "SELECT COUNT(*) AS n FROM platform_users WHERE account_type='supplier' AND status='pending'")],
    ['label' => '📦 خدمات بانتظار',     'value' => stat_if('suppliers.services',"SELECT COUNT(*) AS n FROM supplier_offers WHERE review_status='pending_review'")],
    ['label' => '👤 حسابات المستخدمين', 'value' => stat_if('users.view',        "SELECT COUNT(*) AS n FROM platform_users WHERE account_type='user'")],
    ['label' => '🤝 وساطات مفتوحة',     'value' => stat_if('mediation.view',    "SELECT COUNT(*) AS n FROM mediations WHERE status NOT IN ('released','refunded','cancelled')")],
    ['label' => '💳 مدفوعات للمراجعة',  'value' => stat_if('payments.confirm',  "SELECT COUNT(*) AS n FROM payments WHERE status='submitted'")],
], static fn(array $card): bool => $card['value'] !== null);

$recent_orders = admin_can('orders.view')
    ? fetch_all($conn,
        'SELECT id, order_code, service_name, total_price, currency, order_status, created_at
           FROM orders ORDER BY id DESC LIMIT 8')
    : [];

$recent_audit = admin_can('audit.view')
    ? fetch_all($conn,
        'SELECT actor_type, actor_label, action, summary, created_at
           FROM audit_log ORDER BY id DESC LIMIT 8')
    : [];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="stat-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card">
      <span class="stat-num"><?= (int) $card['value'] ?></span>
      <div class="stat-label"><?= $card['label'] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($recent_orders): ?>
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">📋 آخر الطلبات</div>
      <a href="orders.php" class="btn btn-secondary btn-sm">عرض الكل</a>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>الكود</th><th>الخدمة</th><th>الإجمالي</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent_orders as $order): ?>
          <tr>
            <td dir="ltr" style="font-size:12px;" class="text-gold"><?= e((string) $order['order_code']) ?></td>
            <td style="font-size:13px;"><?= e(mb_strimwidth((string) $order['service_name'], 0, 34, '…')) ?></td>
            <td class="money"><?= e(number_format((float) $order['total_price'], 2)) ?> <?= e((string) $order['currency']) ?></td>
            <td><?= admin_badge(admin_order_status_label((string) $order['order_status']), admin_order_status_tone((string) $order['order_status'])) ?></td>
            <td class="text-muted" style="font-size:12px;" dir="ltr"><?= e(date('m-d H:i', strtotime((string) $order['created_at']))) ?></td>
            <td><a class="btn btn-secondary btn-sm" href="order-view.php?id=<?= (int) $order['id'] ?>">عرض</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($recent_audit): ?>
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">📜 آخر العمليات</div>
      <a href="audit.php" class="btn btn-secondary btn-sm">السجل الكامل</a>
    </div>
    <ul class="timeline">
      <?php foreach ($recent_audit as $entry): ?>
        <li>
          <strong dir="ltr" style="font-size:12px;"><?= e((string) $entry['action']) ?></strong>
          <?php if (!empty($entry['summary'])): ?>
            <span class="text-muted" style="font-size:12px;">— <?= e(mb_strimwidth((string) $entry['summary'], 0, 60, '…')) ?></span>
          <?php endif; ?>
          <time dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $entry['created_at']))) ?>
            <?= !empty($entry['actor_label']) ? ' · ' . e((string) $entry['actor_label']) : '' ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-header"><div class="panel-title">ℹ️ معلومات النظام</div></div>
  <table class="kv">
    <tr><td>PHP</td><td dir="ltr"><?= e(PHP_VERSION) ?></td></tr>
    <tr><td>قاعدة البيانات</td><td dir="ltr"><?= e((string) $conn->server_info) ?></td></tr>
    <tr><td>البيئة</td><td dir="ltr"><?= e((string) (getenv('APP_ENV') ?: 'development')) ?></td></tr>
    <tr><td>صلاحياتك</td><td><?= count(admin_permissions()) ?> صلاحية</td></tr>
    <tr><td>الترحيلات</td><td><?= admin_count('SELECT COUNT(*) AS n FROM schema_migrations') ?> مطبَّقة</td></tr>
  </table>
  <div class="confidential-note">
    تغييرات قاعدة البيانات تُشغَّل من الطرفية بـ <code dir="ltr">php migrate.php</code> فقط.
    لا توجد صفحة في اللوحة تعدّل بنية القاعدة.
  </div>
</div>

<?php admin_layout_end(); ?>
