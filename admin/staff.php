<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('rbac.manage');

$page_title_admin = 'الفريق والصلاحيات';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string) ($_POST['action'] ?? '');
    $me     = admin_user();

    if ($action === 'set_roles') {
        $staffId = admin_id('admin_id');
        $roleIds = array_map('intval', (array) ($_POST['roles'] ?? []));

        $staff = fetch_one($conn, 'SELECT id, username, is_super_admin FROM admin_users WHERE id = ?', 'i', $staffId);
        if ($staff === null) {
            admin_flash('error', 'الحساب غير موجود.');
            admin_redirect('staff.php');
        }

        // The last super admin cannot be stripped of the role, or the dashboard
        // locks everyone out of its own permission editor.
        $superCount = (int) (fetch_one($conn, 'SELECT COUNT(*) AS n FROM admin_users WHERE is_super_admin = 1 AND is_active = 1')['n'] ?? 0);
        $superRole  = fetch_one($conn, "SELECT id FROM roles WHERE role_key = 'super_admin'");
        $keepsSuper = $superRole !== null && in_array((int) $superRole['id'], $roleIds, true);

        if ((int) $staff['is_super_admin'] === 1 && !$keepsSuper && $superCount <= 1) {
            admin_flash('error', 'لا يمكن إزالة آخر مدير عام من النظام.');
            admin_redirect('staff.php');
        }

        $conn->begin_transaction();
        try {
            $clear = $conn->prepare('DELETE FROM admin_roles WHERE admin_id = ?');
            $clear->bind_param('i', $staffId);
            $clear->execute();

            $insert = $conn->prepare('INSERT IGNORE INTO admin_roles (admin_id, role_id, granted_by) VALUES (?, ?, ?)');
            $grantedBy = (int) $me['id'];
            foreach ($roleIds as $roleId) {
                if ($roleId <= 0) { continue; }
                $insert->bind_param('iii', $staffId, $roleId, $grantedBy);
                $insert->execute();
            }

            $isSuper = $keepsSuper ? 1 : 0;
            $flag = $conn->prepare('UPDATE admin_users SET is_super_admin = ? WHERE id = ?');
            $flag->bind_param('ii', $isSuper, $staffId);
            $flag->execute();

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('[EXD rbac] ' . $e->getMessage());
            admin_flash('error', 'تعذّر حفظ الأدوار.');
            admin_redirect('staff.php');
        }

        admin_audit('rbac.roles_changed', 'admin_users', $staffId, (string) $staff['username'],
            'roles: ' . implode(',', $roleIds));
        admin_flash('success', 'تم تحديث أدوار ' . (string) $staff['username'] . '.');

    } elseif ($action === 'toggle_active') {
        $staffId = admin_id('admin_id');

        if ($staffId === (int) $me['id']) {
            admin_flash('error', 'لا يمكنك إيقاف حسابك أنت.');
            admin_redirect('staff.php');
        }

        $staff = fetch_one($conn, 'SELECT id, username, is_active FROM admin_users WHERE id = ?', 'i', $staffId);
        if ($staff === null) {
            admin_flash('error', 'الحساب غير موجود.');
            admin_redirect('staff.php');
        }

        $next = (int) $staff['is_active'] === 1 ? 0 : 1;
        $stmt = $conn->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?');
        $stmt->bind_param('ii', $next, $staffId);
        $stmt->execute();

        if ($next === 0) {
            $kill = $conn->prepare('UPDATE admin_sessions SET revoked_at = NOW() WHERE admin_id = ? AND revoked_at IS NULL');
            $kill->bind_param('i', $staffId);
            $kill->execute();
        }

        admin_audit($next === 1 ? 'staff.activated' : 'staff.deactivated', 'admin_users', $staffId, (string) $staff['username']);
        admin_flash('success', $next === 1 ? 'تم تفعيل الحساب.' : 'تم إيقاف الحساب وإنهاء جلساته.');

    } elseif ($action === 'set_role_permissions') {
        $roleId        = admin_id('role_id');
        $permissionIds = array_map('intval', (array) ($_POST['permissions'] ?? []));

        $role = fetch_one($conn, 'SELECT id, role_key, name FROM roles WHERE id = ?', 'i', $roleId);
        if ($role === null) {
            admin_flash('error', 'الدور غير موجود.');
            admin_redirect('staff.php?tab=roles');
        }
        if ($role['role_key'] === 'super_admin') {
            admin_flash('error', 'دور المدير العام يملك كل الصلاحيات بحكم تعريفه ولا يُحرَّر.');
            admin_redirect('staff.php?tab=roles');
        }

        $conn->begin_transaction();
        try {
            $clear = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $clear->bind_param('i', $roleId);
            $clear->execute();

            $insert = $conn->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permissionIds as $permissionId) {
                if ($permissionId <= 0) { continue; }
                $insert->bind_param('ii', $roleId, $permissionId);
                $insert->execute();
            }
            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            admin_flash('error', 'تعذّر حفظ الصلاحيات.');
            admin_redirect('staff.php?tab=roles');
        }

        admin_audit('rbac.permissions_changed', 'roles', $roleId, (string) $role['name']);
        admin_flash('success', 'تم تحديث صلاحيات دور ' . (string) $role['name'] . '.');
        admin_redirect('staff.php?tab=roles');
    }

    admin_redirect('staff.php');
}

$tab = (string) ($_GET['tab'] ?? 'staff');
if (!in_array($tab, ['staff', 'roles'], true)) {
    $tab = 'staff';
}

$roles       = fetch_all($conn, 'SELECT id, role_key, name, description FROM roles ORDER BY id');
$permissions = fetch_all($conn, 'SELECT id, permission_key, name, module FROM permissions ORDER BY module, id');

$staffList = fetch_all(
    $conn,
    'SELECT id, username, display_name, email, is_active, is_super_admin, last_login_at, created_at
       FROM admin_users ORDER BY id'
);

$staffRoles = [];
foreach (fetch_all($conn, 'SELECT admin_id, role_id FROM admin_roles') as $row) {
    $staffRoles[(int) $row['admin_id']][] = (int) $row['role_id'];
}

$rolePermissions = [];
foreach (fetch_all($conn, 'SELECT role_id, permission_id FROM role_permissions') as $row) {
    $rolePermissions[(int) $row['role_id']][] = (int) $row['permission_id'];
}

$modules = [];
foreach ($permissions as $permission) {
    $modules[$permission['module']][] = $permission;
}

$moduleLabels = [
    'catalog'   => 'الكتالوج',
    'orders'    => 'الطلبات',
    'suppliers' => 'الموردون',
    'users'     => 'الحسابات',
    'finance'   => 'المالية',
    'mediation' => 'الوساطة',
    'assets'    => 'الأصول الرقمية',
    'providers' => 'المزودون',
    'cms'       => 'المحتوى',
    'settings'  => 'النظام',
];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <a class="btn <?= $tab === 'staff' ? 'btn-primary' : 'btn-secondary' ?>" href="staff.php?tab=staff">أعضاء الفريق</a>
  <a class="btn <?= $tab === 'roles' ? 'btn-primary' : 'btn-secondary' ?>" href="staff.php?tab=roles">الأدوار والصلاحيات</a>
</div>

<?php if ($tab === 'staff'): ?>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">أعضاء الفريق (<?= count($staffList) ?>)</div></div>

    <?php foreach ($staffList as $staff): ?>
      <div class="form-section">
        <div class="flex-between">
          <div>
            <strong><?= e((string) ($staff['display_name'] ?: $staff['username'])) ?></strong>
            <span class="text-muted" style="font-size:12px;" dir="ltr">@<?= e((string) $staff['username']) ?></span>
            <?= (int) $staff['is_super_admin'] === 1 ? admin_badge('مدير عام', 'active') : '' ?>
            <?= (int) $staff['is_active'] === 1 ? '' : admin_badge('موقوف', 'hidden') ?>
          </div>
          <div class="flex-gap">
            <span class="text-muted" style="font-size:12px;">
              آخر دخول: <?= $staff['last_login_at'] ? e(date('Y-m-d H:i', strtotime((string) $staff['last_login_at']))) : 'لم يدخل بعد' ?>
            </span>
            <?= admin_action_button('toggle_active', ['admin_id' => $staff['id']],
                  (int) $staff['is_active'] === 1 ? 'إيقاف' : 'تفعيل',
                  (int) $staff['is_active'] === 1 ? 'btn btn-danger btn-sm' : 'btn btn-secondary btn-sm') ?>
          </div>
        </div>

        <form method="post" class="mt-8">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_roles">
          <input type="hidden" name="admin_id" value="<?= (int) $staff['id'] ?>">

          <div class="flex-gap" style="flex-wrap:wrap;">
            <?php foreach ($roles as $role): ?>
              <label class="form-check">
                <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>"
                       <?= in_array((int) $role['id'], $staffRoles[(int) $staff['id']] ?? [], true) ? 'checked' : '' ?>>
                <span><?= e((string) $role['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <button class="btn btn-secondary btn-sm mt-8" type="submit">حفظ الأدوار</button>
        </form>
      </div>
    <?php endforeach; ?>

    <div class="confidential-note">
      لإضافة عضو جديد للفريق شغّل على الخادم:
      <code dir="ltr">php tools/create_admin.php --username=NAME --role=ROLE</code>.
      كلمة المرور تُدخَل في الطرفية ولا تُحفظ في المستودع.
    </div>
  </div>

<?php else: ?>

  <?php foreach ($roles as $role): ?>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title"><?= e((string) $role['name']) ?>
          <span class="text-muted" style="font-size:12px;" dir="ltr"><?= e((string) $role['role_key']) ?></span>
        </div>
      </div>
      <p class="text-muted" style="font-size:13px;"><?= e((string) ($role['description'] ?? '')) ?></p>

      <?php if ($role['role_key'] === 'super_admin'): ?>
        <p class="text-muted" style="font-size:13px;">
          هذا الدور يملك كل الصلاحيات، بما فيها أي صلاحية تُضاف مستقبلًا. لا يُحرَّر من هنا.
        </p>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_role_permissions">
          <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">

          <?php foreach ($modules as $module => $items): ?>
            <div class="form-section">
              <div class="form-label"><?= e($moduleLabels[$module] ?? $module) ?></div>
              <div class="flex-gap" style="flex-wrap:wrap;">
                <?php foreach ($items as $permission): ?>
                  <label class="form-check">
                    <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>"
                           <?= in_array((int) $permission['id'], $rolePermissions[(int) $role['id']] ?? [], true) ? 'checked' : '' ?>>
                    <span><?= e((string) $permission['name']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <button class="btn btn-primary btn-sm" type="submit">حفظ صلاحيات هذا الدور</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?php admin_layout_end(); ?>
