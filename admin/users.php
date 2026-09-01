<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('users.view');

$page_title_admin = 'المستخدمون';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    admin_require('users.manage');

    $action = (string) ($_POST['action'] ?? '');
    $userId = admin_id('user_id');

    $user = $userId > 0
        ? fetch_one($conn, "SELECT id, name, status, account_type FROM platform_users WHERE id = ? AND account_type='user'", 'i', $userId)
        : null;

    if ($user === null) {
        admin_flash('error', 'الحساب غير موجود.');
        admin_redirect('users.php');
    }

    if ($action === 'suspend' || $action === 'activate') {
        $status = $action === 'suspend' ? 'suspended' : 'active';
        $stmt = $conn->prepare('UPDATE platform_users SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $userId);
        $stmt->execute();

        // Suspending an account must also end its sessions, or the person
        // stays signed in on a device we just decided to lock out.
        if ($status === 'suspended') {
            $kill = $conn->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
            $kill->bind_param('i', $userId);
            $kill->execute();
        }

        admin_audit('user.' . $action, 'platform_users', $userId, (string) $user['name']);
        admin_flash('success', $action === 'suspend' ? 'تم إيقاف الحساب وإنهاء جلساته.' : 'تم تفعيل الحساب.');
    }

    admin_redirect('users.php' . (isset($_POST['return']) ? '?' . (string) $_POST['return'] : ''));
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, ['', 'active', 'pending', 'suspended'], true)) {
    $status = '';
}

$where  = ["account_type = 'user'"];
$types  = '';
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like     = '%' . $search . '%';
    $types   .= 'sss';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status !== '') {
    $where[]  = 'status = ?';
    $types   .= 's';
    $params[] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM platform_users $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 30);

// The password hash is never selected. It is not needed to render a list, and
// a column that is never read cannot be leaked by a template mistake.
$users = fetch_all(
    $conn,
    "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.last_login_at,
            COALESCE(w.balance, 0) AS balance,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count
       FROM platform_users u
       LEFT JOIN wallets w ON w.user_id = u.id
       $whereSql
      ORDER BY u.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$baseQuery = http_build_query(array_filter(['q' => $search, 'status' => $status]));

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<form class="filter-bar" method="get">
  <div class="form-group">
    <label class="form-label">بحث</label>
    <input class="form-input" type="search" name="q" value="<?= e($search) ?>" placeholder="اسم، بريد، هاتف">
  </div>
  <div class="form-group">
    <label class="form-label">الحالة</label>
    <select class="form-select" name="status">
      <option value="">الكل</option>
      <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>نشط</option>
      <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>موقوف</option>
    </select>
  </div>
  <button class="btn btn-secondary" type="submit">تصفية</button>
  <?php if ($search !== '' || $status !== ''): ?>
    <a class="btn btn-secondary" href="users.php">إلغاء</a>
  <?php endif; ?>
</form>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">المستخدمون (<?= (int) ($totalRow['n'] ?? 0) ?>)</div>
  </div>

  <?php if ($users): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th><th>الاسم</th><th>البريد</th><th>الهاتف</th>
            <th>الطلبات</th><th>الرصيد</th><th>الحالة</th><th>آخر دخول</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td class="text-muted"><?= (int) $user['id'] ?></td>
            <td><?= e((string) $user['name']) ?></td>
            <td dir="ltr" style="font-size:12px;"><?= e((string) $user['email']) ?></td>
            <td dir="ltr" style="font-size:12px;"><?= e((string) $user['phone']) ?></td>
            <td><?= (int) $user['order_count'] ?></td>
            <td class="money"><?= e(number_format((float) $user['balance'], 2)) ?></td>
            <td><?= admin_badge(
                    $user['status'] === 'active' ? 'نشط' : ($user['status'] === 'suspended' ? 'موقوف' : 'معلّق'),
                    $user['status'] === 'active' ? 'active' : ($user['status'] === 'suspended' ? 'hidden' : 'review')
                ) ?></td>
            <td class="text-muted" style="font-size:12px;">
              <?= $user['last_login_at'] ? e(date('Y-m-d', strtotime((string) $user['last_login_at']))) : '—' ?>
            </td>
            <td>
              <?php if (admin_can('users.manage')): ?>
                <?php if ($user['status'] === 'suspended'): ?>
                  <?= admin_action_button('activate', ['user_id' => $user['id'], 'return' => $baseQuery], 'تفعيل') ?>
                <?php else: ?>
                  <?= admin_action_button('suspend', ['user_id' => $user['id'], 'return' => $baseQuery],
                        'إيقاف', 'btn btn-danger btn-sm', 'إيقاف هذا الحساب وإنهاء جلساته؟') ?>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= admin_pager($paging, $baseQuery) ?>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">👤</div><p>لا توجد حسابات مطابقة.</p></div>
  <?php endif; ?>
</div>

<?php admin_layout_end(); ?>
