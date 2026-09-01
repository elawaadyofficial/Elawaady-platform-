<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('suppliers.view');

$page_title_admin = 'الموردون';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    admin_require('suppliers.approve');

    $action     = (string) ($_POST['action'] ?? '');
    $supplierId = admin_id('supplier_id');

    $supplier = $supplierId > 0
        ? fetch_one($conn, "SELECT id, name, status FROM platform_users WHERE id = ? AND account_type='supplier'", 'i', $supplierId)
        : null;

    if ($supplier === null) {
        admin_flash('error', 'المورد غير موجود.');
        admin_redirect('suppliers.php');
    }

    $admin = admin_user();

    if ($action === 'approve') {
        $stmt = $conn->prepare(
            "UPDATE platform_users
                SET status = 'active', approved_at = NOW(), approved_by = ?, rejection_reason = NULL
              WHERE id = ?"
        );
        $adminId = (int) $admin['id'];
        $stmt->bind_param('ii', $adminId, $supplierId);
        $stmt->execute();

        notify_user($supplierId, 'تم اعتماد حسابك كمورد',
            'يمكنك الآن تقديم خدماتك من لوحة المورد.', 'success', 'supplier-dashboard.php');

        admin_audit('supplier.approved', 'platform_users', $supplierId, (string) $supplier['name']);
        admin_flash('success', 'تم اعتماد المورد.');

    } elseif ($action === 'reject') {
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        $stmt = $conn->prepare(
            "UPDATE platform_users SET status = 'rejected', rejection_reason = ?, approved_by = ? WHERE id = ?"
        );
        $adminId = (int) $admin['id'];
        $stmt->bind_param('sii', $reason, $adminId, $supplierId);
        $stmt->execute();

        notify_user($supplierId, 'لم يتم اعتماد حساب المورد',
            $reason !== '' ? $reason : 'تواصل مع الدعم لمزيد من التفاصيل.', 'warning');

        admin_audit('supplier.rejected', 'platform_users', $supplierId, (string) $supplier['name'], $reason);
        admin_flash('success', 'تم رفض طلب المورد.');

    } elseif ($action === 'suspend') {
        $stmt = $conn->prepare("UPDATE platform_users SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param('i', $supplierId);
        $stmt->execute();

        $kill = $conn->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
        $kill->bind_param('i', $supplierId);
        $kill->execute();

        admin_audit('supplier.suspended', 'platform_users', $supplierId, (string) $supplier['name']);
        admin_flash('success', 'تم إيقاف المورد وإنهاء جلساته.');
    }

    admin_redirect('suppliers.php' . (($_POST['return'] ?? '') !== '' ? '?' . (string) $_POST['return'] : ''));
}

$status = (string) ($_GET['status'] ?? 'pending');
if (!in_array($status, ['', 'pending', 'active', 'suspended', 'rejected'], true)) {
    $status = '';
}

$where  = ["u.account_type = 'supplier'"];
$types  = '';
$params = [];

if ($status !== '') {
    $where[]  = 'u.status = ?';
    $types   .= 's';
    $params[] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM platform_users u $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 30);

$suppliers = fetch_all(
    $conn,
    "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.rejection_reason,
            sp.company_name, sp.services_desc, sp.rating, sp.completed_orders,
            (SELECT COUNT(*) FROM supplier_offers so WHERE so.supplier_id = u.id) AS offer_count
       FROM platform_users u
       LEFT JOIN supplier_profiles sp ON sp.user_id = u.id
       $whereSql
      ORDER BY u.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$counts = [];
foreach (fetch_all($conn, "SELECT status, COUNT(*) AS n FROM platform_users WHERE account_type='supplier' GROUP BY status") as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

$tabs = [
    'pending'   => 'بانتظار الاعتماد',
    'active'    => 'معتمدون',
    'suspended' => 'موقوفون',
    'rejected'  => 'مرفوضون',
    ''          => 'الكل',
];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="btn <?= $status === $key ? 'btn-primary' : 'btn-secondary' ?>"
       href="suppliers.php?status=<?= e($key) ?>">
      <?= e($label) ?><?= isset($counts[$key]) ? ' (' . $counts[$key] . ')' : '' ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">الموردون (<?= (int) ($totalRow['n'] ?? 0) ?>)</div>
  </div>

  <?php if ($suppliers): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th><th>الاسم</th><th>النشاط</th><th>التواصل</th>
            <th>الخدمات</th><th>الحالة</th><th>التسجيل</th><th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($suppliers as $supplier): ?>
          <tr>
            <td class="text-muted"><?= (int) $supplier['id'] ?></td>
            <td>
              <?= e((string) $supplier['name']) ?>
              <?php if (!empty($supplier['services_desc'])): ?>
                <div class="text-muted" style="font-size:11px; max-width:260px; white-space:normal;">
                  <?= e(mb_strimwidth((string) $supplier['services_desc'], 0, 90, '…')) ?>
                </div>
              <?php endif; ?>
            </td>
            <td><?= e((string) ($supplier['company_name'] ?: '—')) ?></td>
            <td dir="ltr" style="font-size:12px;">
              <?= e((string) $supplier['email']) ?><br>
              <span class="text-muted"><?= e((string) $supplier['phone']) ?></span>
            </td>
            <td><a href="supplier-offers.php?supplier_id=<?= (int) $supplier['id'] ?>"><?= (int) $supplier['offer_count'] ?></a></td>
            <td>
              <?= admin_badge(
                    match ($supplier['status']) {
                        'active'    => 'معتمد',
                        'pending'   => 'بانتظار المراجعة',
                        'suspended' => 'موقوف',
                        'rejected'  => 'مرفوض',
                        default     => (string) $supplier['status'],
                    },
                    match ($supplier['status']) {
                        'active'  => 'active',
                        'pending' => 'review',
                        default   => 'hidden',
                    }
                ) ?>
              <?php if (!empty($supplier['rejection_reason'])): ?>
                <div class="text-muted" style="font-size:11px; white-space:normal; max-width:200px;">
                  <?= e((string) $supplier['rejection_reason']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:12px;"><?= e(date('Y-m-d', strtotime((string) $supplier['created_at']))) ?></td>
            <td>
              <?php if (admin_can('suppliers.approve')): ?>
                <div class="flex-gap">
                  <?php if ($supplier['status'] !== 'active'): ?>
                    <?= admin_action_button('approve', ['supplier_id' => $supplier['id'], 'return' => 'status=' . $status],
                          'اعتماد', 'btn btn-primary btn-sm') ?>
                  <?php endif; ?>
                  <?php if ($supplier['status'] === 'pending'): ?>
                    <form method="post" class="inline-form" data-confirm="رفض طلب هذا المورد؟">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="supplier_id" value="<?= (int) $supplier['id'] ?>">
                      <input type="hidden" name="return" value="status=<?= e($status) ?>">
                      <input class="form-input" type="text" name="reason" placeholder="سبب الرفض" style="width:150px; display:inline-block;">
                      <button class="btn btn-danger btn-sm" type="submit">رفض</button>
                    </form>
                  <?php elseif ($supplier['status'] === 'active'): ?>
                    <?= admin_action_button('suspend', ['supplier_id' => $supplier['id'], 'return' => 'status=' . $status],
                          'إيقاف', 'btn btn-danger btn-sm', 'إيقاف هذا المورد؟') ?>
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
    <div class="empty-state"><div class="empty-icon">🏭</div><p>لا يوجد موردون في هذه الحالة.</p></div>
  <?php endif; ?>
</div>

<?php admin_layout_end(); ?>
