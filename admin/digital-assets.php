<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('assets.view');

$page_title_admin = 'الأصول الرقمية';

/**
 * Accounts, pages and channels offered for sale.
 *
 * Nothing is listed on the storefront until a member of staff has reviewed it,
 * because the thing being sold here is access to someone else's property and a
 * wrong listing is a fraud, not a typo. Every listing carries a safety period:
 * the buyer's money is not final until it has passed.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    admin_require('assets.manage');

    $action  = (string) ($_POST['action'] ?? '');
    $assetId = admin_id('asset_id');
    $admin   = admin_user();
    $adminId = (int) $admin['id'];

    if ($action === 'create') {
        $platform  = mb_substr(trim((string) ($_POST['platform'] ?? '')), 0, 80);
        $type      = mb_substr(trim((string) ($_POST['asset_type'] ?? '')), 0, 80);
        $title     = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        $price     = max(0.0, (float) ($_POST['price'] ?? 0));
        $followers = max(0, (int) ($_POST['followers_count'] ?? 0));
        $safety    = max(0, min(90, (int) ($_POST['safety_days'] ?? 7)));
        $desc      = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 4000);
        $supplier  = max(0, (int) ($_POST['supplier_id'] ?? 0)) ?: null;

        if ($platform === '' || $title === '' || $price <= 0) {
            admin_flash('error', 'المنصة والعنوان والسعر مطلوبة.');
            admin_redirect('digital-assets.php');
        }

        $stmt = $conn->prepare(
            'INSERT INTO digital_assets
                (supplier_id, platform, asset_type, title, description, followers_count, price, safety_days)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssidi', $supplier, $platform, $type, $title, $desc, $followers, $price, $safety);
        $stmt->execute();

        admin_audit('asset.created', 'digital_assets', (int) $conn->insert_id, $title);
        admin_flash('success', 'تمت إضافة الأصل. راجعه ثم انشره.');

    } elseif (in_array($action, ['list', 'reject', 'unlist', 'sold'], true)) {
        $asset = $assetId > 0
            ? fetch_one($conn, 'SELECT id, title, review_status, supplier_id FROM digital_assets WHERE id = ?', 'i', $assetId)
            : null;

        if ($asset === null) {
            admin_flash('error', 'الأصل غير موجود.');
            admin_redirect('digital-assets.php');
        }

        $next = match ($action) {
            'list'   => 'listed',
            'reject' => 'rejected',
            'unlist' => 'pending_review',
            'sold'   => 'sold',
        };
        $notes = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 2000);

        $stmt = $conn->prepare(
            'UPDATE digital_assets SET review_status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('ssii', $next, $notes, $adminId, $assetId);
        $stmt->execute();

        if ($asset['supplier_id'] !== null) {
            notify_user((int) $asset['supplier_id'],
                $next === 'listed' ? 'تم نشر الأصل الرقمي' : 'تحديث على الأصل الرقمي',
                (string) $asset['title'], $next === 'rejected' ? 'warning' : 'info');
        }

        admin_audit('asset.' . $action, 'digital_assets', $assetId, (string) $asset['title'], $notes);
        admin_flash('success', 'تم تحديث حالة الأصل.');
    }

    admin_redirect('digital-assets.php?status=' . urlencode((string) ($_POST['return_status'] ?? 'pending_review')));
}

$status = (string) ($_GET['status'] ?? 'pending_review');
if (!in_array($status, ['', 'pending_review', 'listed', 'reserved', 'sold', 'rejected'], true)) {
    $status = '';
}

$where  = ['1 = 1'];
$types  = '';
$params = [];

if ($status !== '') {
    $where[]  = 'a.review_status = ?';
    $types   .= 's';
    $params[] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM digital_assets a $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 25);

$assets = fetch_all(
    $conn,
    "SELECT a.*, u.name AS supplier_name
       FROM digital_assets a
       LEFT JOIN platform_users u ON u.id = a.supplier_id
       $whereSql
      ORDER BY a.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$statusLabels = [
    'pending_review' => 'قيد المراجعة',
    'listed'         => 'معروض',
    'reserved'       => 'محجوز',
    'sold'           => 'مباع',
    'rejected'       => 'مرفوض',
];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <?php foreach ($statusLabels as $key => $label): ?>
    <a class="btn <?= $status === $key ? 'btn-primary' : 'btn-secondary' ?>"
       href="digital-assets.php?status=<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <a class="btn <?= $status === '' ? 'btn-primary' : 'btn-secondary' ?>" href="digital-assets.php?status=">الكل</a>
</div>

<div class="panel">
  <div class="panel-header"><div class="panel-title">الأصول (<?= (int) ($totalRow['n'] ?? 0) ?>)</div></div>

  <?php if ($assets): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>المنصة</th><th>العنوان</th><th>المتابعون</th><th>السعر</th>
              <th>أيام الأمان</th><th>المورد</th><th>الحالة</th><th>إجراءات</th></tr>
        </thead>
        <tbody>
        <?php foreach ($assets as $asset): ?>
          <tr>
            <td class="text-muted"><?= (int) $asset['id'] ?></td>
            <td><?= e((string) $asset['platform']) ?></td>
            <td>
              <?= e((string) $asset['title']) ?>
              <?php if (!empty($asset['asset_type'])): ?>
                <div class="text-muted" style="font-size:11px;"><?= e((string) $asset['asset_type']) ?></div>
              <?php endif; ?>
            </td>
            <td dir="ltr"><?= e(number_format((int) $asset['followers_count'])) ?></td>
            <td class="money text-gold"><?= e(number_format((float) $asset['price'], 2)) ?> <?= e((string) $asset['currency']) ?></td>
            <td><?= (int) $asset['safety_days'] ?></td>
            <td class="text-muted" style="font-size:12px;"><?= e((string) ($asset['supplier_name'] ?? '—')) ?></td>
            <td><?= admin_badge($statusLabels[$asset['review_status']] ?? (string) $asset['review_status'],
                    match ($asset['review_status']) {
                        'listed', 'sold' => 'active',
                        'rejected'       => 'hidden',
                        default          => 'review',
                    }) ?></td>
            <td>
              <?php if (admin_can('assets.manage')): ?>
                <div class="flex-gap">
                  <?php if ($asset['review_status'] !== 'listed'): ?>
                    <?= admin_action_button('list', ['asset_id' => $asset['id'], 'return_status' => $status], 'نشر', 'btn btn-primary btn-sm') ?>
                  <?php else: ?>
                    <?= admin_action_button('unlist', ['asset_id' => $asset['id'], 'return_status' => $status], 'سحب') ?>
                    <?= admin_action_button('sold',   ['asset_id' => $asset['id'], 'return_status' => $status], 'تم البيع') ?>
                  <?php endif; ?>
                  <?php if ($asset['review_status'] === 'pending_review'): ?>
                    <?= admin_action_button('reject', ['asset_id' => $asset['id'], 'return_status' => $status],
                          'رفض', 'btn btn-danger btn-sm', 'رفض هذا الأصل؟') ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= admin_pager($paging, 'status=' . $status) ?>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">💎</div><p>لا توجد أصول في هذه الحالة.</p></div>
  <?php endif; ?>
</div>

<?php if (admin_can('assets.manage')): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">إضافة أصل</div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">المنصة <span class="req">*</span></label>
          <input class="form-input" type="text" name="platform" required placeholder="YouTube">
        </div>
        <div class="form-group">
          <label class="form-label">النوع</label>
          <input class="form-input" type="text" name="asset_type" placeholder="قناة">
        </div>
        <div class="form-group">
          <label class="form-label">العنوان <span class="req">*</span></label>
          <input class="form-input" type="text" name="title" required>
        </div>
        <div class="form-group">
          <label class="form-label">عدد المتابعين</label>
          <input class="form-input" type="number" min="0" dir="ltr" name="followers_count" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">السعر <span class="req">*</span></label>
          <input class="form-input" type="number" step="0.01" min="0.01" dir="ltr" name="price" required>
        </div>
        <div class="form-group">
          <label class="form-label">أيام الأمان</label>
          <input class="form-input" type="number" min="0" max="90" dir="ltr" name="safety_days"
                 value="<?= e((string) setting('mediation_default_safety_days', '7')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">رقم حساب المورد</label>
          <input class="form-input" type="number" min="0" dir="ltr" name="supplier_id" placeholder="0">
        </div>
        <div class="form-group form-full">
          <label class="form-label">الوصف</label>
          <textarea class="form-input form-textarea" name="description"></textarea>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">إضافة</button>
    </form>
    <div class="confidential-note">
      نقل الملكية يتم عبر المنصة فقط، وخلال فترة الأمان يظل المبلغ محجوزًا حتى يؤكد المشتري الاستلام.
    </div>
  </div>
<?php endif; ?>

<?php admin_layout_end(); ?>
