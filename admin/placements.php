<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('catalog.manage');

$page_title_admin = 'أماكن العرض';

/**
 * Which services appear where.
 *
 * A homepage section with source_filter 'best_seller' renders the services
 * that hold the 'best_seller' placement, in the order set here. That is the
 * whole mechanism: this page writes service_placements, sections.php reads it.
 */

$placements = [
    'best_seller' => 'الأكثر مبيعًا',
    'most_used'   => 'الأكثر استخدامًا',
    'featured'    => 'مختارة',
    'newest'      => 'الأحدث',
    'offers'      => 'عروض',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action    = (string) ($_POST['action'] ?? '');
    $placement = (string) ($_POST['placement'] ?? '');

    if (!isset($placements[$placement])) {
        admin_flash('error', 'مكان عرض غير معروف.');
        admin_redirect('placements.php');
    }

    if ($action === 'add') {
        $serviceId = admin_id('service_id');
        $service   = $serviceId > 0
            ? fetch_one($conn, 'SELECT id, name FROM store_services WHERE id = ?', 'i', $serviceId)
            : null;

        if ($service === null) {
            admin_flash('error', 'الخدمة غير موجودة.');
            admin_redirect('placements.php?placement=' . urlencode($placement));
        }

        $maxRow = fetch_one($conn,
            'SELECT COALESCE(MAX(sort_order), 0) AS m FROM service_placements WHERE placement_key = ?',
            's', $placement);
        $sort = (int) ($maxRow['m'] ?? 0) + 10;

        $stmt = $conn->prepare(
            'INSERT INTO service_placements (service_id, placement_key, sort_order)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)'
        );
        $stmt->bind_param('isi', $serviceId, $placement, $sort);
        $stmt->execute();

        admin_audit('catalog.placement_added', 'store_services', $serviceId,
            (string) $service['name'] . ' → ' . $placements[$placement]);
        admin_flash('success', 'تمت إضافة الخدمة إلى ' . $placements[$placement] . '.');

    } elseif ($action === 'remove') {
        $serviceId = admin_id('service_id');
        $stmt = $conn->prepare('DELETE FROM service_placements WHERE service_id = ? AND placement_key = ?');
        $stmt->bind_param('is', $serviceId, $placement);
        $stmt->execute();

        admin_audit('catalog.placement_removed', 'store_services', $serviceId, $placements[$placement]);
        admin_flash('success', 'تمت إزالة الخدمة من ' . $placements[$placement] . '.');

    } elseif ($action === 'reorder') {
        $orders = (array) ($_POST['sort_order'] ?? []);
        $stmt   = $conn->prepare(
            'UPDATE service_placements SET sort_order = ? WHERE service_id = ? AND placement_key = ?'
        );
        foreach ($orders as $serviceId => $sortOrder) {
            $serviceId = (int) $serviceId;
            $sortOrder = (int) $sortOrder;
            if ($serviceId <= 0) { continue; }
            $stmt->bind_param('iis', $sortOrder, $serviceId, $placement);
            $stmt->execute();
        }
        admin_audit('catalog.placement_reordered', null, null, $placements[$placement]);
        admin_flash('success', 'تم حفظ الترتيب.');
    }

    admin_redirect('placements.php?placement=' . urlencode($placement));
}

$placement = (string) ($_GET['placement'] ?? 'best_seller');
if (!isset($placements[$placement])) {
    $placement = 'best_seller';
}

$assigned = fetch_all(
    $conn,
    'SELECT sp.service_id, sp.sort_order, s.name, s.price, s.currency, s.is_active, s.image
       FROM service_placements sp
       JOIN store_services s ON s.id = sp.service_id
      WHERE sp.placement_key = ?
      ORDER BY sp.sort_order, s.name',
    's',
    $placement
);

$assignedIds = array_column($assigned, 'service_id');

$search = trim((string) ($_GET['q'] ?? ''));
$candidates = $search !== ''
    ? fetch_all($conn,
        'SELECT id, name, price, currency FROM store_services
          WHERE name LIKE ? AND is_active = 1 ORDER BY name LIMIT 25',
        's', '%' . $search . '%')
    : fetch_all($conn,
        'SELECT id, name, price, currency FROM store_services
          WHERE is_active = 1 ORDER BY id DESC LIMIT 25');

$counts = [];
foreach (fetch_all($conn, 'SELECT placement_key, COUNT(*) AS n FROM service_placements GROUP BY placement_key') as $row) {
    $counts[$row['placement_key']] = (int) $row['n'];
}

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <?php foreach ($placements as $key => $label): ?>
    <a class="btn <?= $placement === $key ? 'btn-primary' : 'btn-secondary' ?>"
       href="placements.php?placement=<?= e($key) ?>">
      <?= e($label) ?><?= isset($counts[$key]) ? ' (' . $counts[$key] . ')' : '' ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title"><?= e($placements[$placement]) ?> — الخدمات المعروضة</div>
  </div>

  <?php if ($assigned): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reorder">
      <input type="hidden" name="placement" value="<?= e($placement) ?>">

      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>الترتيب</th><th>الخدمة</th><th>السعر</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($assigned as $row): ?>
            <tr>
              <td>
                <input class="form-input sort-input" type="number" dir="ltr"
                       name="sort_order[<?= (int) $row['service_id'] ?>]" value="<?= (int) $row['sort_order'] ?>">
              </td>
              <td>
                <a href="service-form.php?id=<?= (int) $row['service_id'] ?>"><?= e((string) $row['name']) ?></a>
              </td>
              <td class="money"><?= e(number_format((float) $row['price'], 2)) ?> <?= e((string) $row['currency']) ?></td>
              <td><?= (int) $row['is_active'] === 1 ? admin_badge('نشطة', 'active') : admin_badge('مخفية', 'inactive') ?></td>
              <td>
                <?= admin_action_button('remove',
                      ['service_id' => $row['service_id'], 'placement' => $placement],
                      'إزالة', 'btn btn-danger btn-sm') ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <button class="btn btn-primary mt-8" type="submit">حفظ الترتيب</button>
    </form>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">📌</div>
      <p>لا توجد خدمات في هذا المكان — القسم لن يظهر على المتجر حتى تُضاف خدمة واحدة على الأقل.</p>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-header"><div class="panel-title">إضافة خدمة</div></div>

  <form class="filter-bar" method="get">
    <input type="hidden" name="placement" value="<?= e($placement) ?>">
    <div class="form-group">
      <label class="form-label">ابحث عن خدمة</label>
      <input class="form-input" type="search" name="q" value="<?= e($search) ?>" placeholder="اسم الخدمة">
    </div>
    <button class="btn btn-secondary" type="submit">بحث</button>
  </form>

  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>#</th><th>الخدمة</th><th>السعر</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($candidates as $service): ?>
        <tr>
          <td class="text-muted"><?= (int) $service['id'] ?></td>
          <td><?= e((string) $service['name']) ?></td>
          <td class="money"><?= e(number_format((float) $service['price'], 2)) ?> <?= e((string) $service['currency']) ?></td>
          <td>
            <?php if (in_array((int) $service['id'], array_map('intval', $assignedIds), true)): ?>
              <span class="text-muted" style="font-size:12px;">مضافة</span>
            <?php else: ?>
              <?= admin_action_button('add',
                    ['service_id' => $service['id'], 'placement' => $placement],
                    'إضافة', 'btn btn-secondary btn-sm') ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_layout_end(); ?>
