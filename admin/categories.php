<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('catalog.manage');
require_once __DIR__ . '/../db_connect.php';
$page_title_admin = 'إدارة الأقسام';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_require();
    $id   = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? 'main'; // main | sub

    if ($_POST['action'] === 'toggle') {
        $tbl = $type === 'sub' ? 'store_subcategories' : 'store_categories';
        $conn->query("UPDATE $tbl SET is_active = NOT is_active WHERE id=$id");
    }
    if ($_POST['action'] === 'delete') {
        if ($type === 'sub') {
            $conn->query("DELETE FROM store_subcategories WHERE id=$id");
        } else {
            $conn->query("DELETE FROM store_subcategories WHERE category_id=$id");
            $conn->query("DELETE FROM store_categories WHERE id=$id");
        }
    }
    header('Location: categories.php'); exit;
}

$main_cats = fetch_all($conn, "SELECT * FROM store_categories ORDER BY sort_order ASC, id ASC");
$sub_cats  = fetch_all($conn, "
    SELECT sc.*, c.name AS parent_name
    FROM store_subcategories sc
    LEFT JOIN store_categories c ON c.id=sc.category_id
    ORDER BY sc.category_id ASC, sc.sort_order ASC, sc.id ASC
");

include __DIR__ . '/layout.php';
?>

<div class="flex-between" style="margin-bottom:16px;">
  <div class="flex-gap">
    <a href="category-form.php?type=main" class="btn btn-primary">➕ قسم رئيسي</a>
    <a href="category-form.php?type=sub"  class="btn btn-secondary">➕ قسم فرعي</a>
  </div>
</div>

<!-- Main Categories -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">📁 الأقسام الرئيسية (<?= count($main_cats) ?>)</div>
  </div>
  <?php if ($main_cats): ?>
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>الأيقونة</th>
        <th>الاسم بالعربية</th>
        <th>الاسم بالإنجليزية</th>
        <th>الترتيب</th>
        <th>الحالة</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($main_cats as $c): ?>
      <tr>
        <td class="text-muted"><?= $c['id'] ?></td>
        <td style="font-size:22px;"><?= $c['icon'] ?? '' ?></td>
        <td><?= e($c['name']) ?></td>
        <td class="text-muted"><?= e($c['name_en'] ?? '') ?></td>
        <td class="text-muted"><?= $c['sort_order'] ?></td>
        <td>
          <span class="badge <?= $c['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
            <?= $c['is_active'] ? 'نشط' : 'مخفي' ?>
          </span>
        </td>
        <td>
          <div class="flex-gap">
            <a href="category-form.php?type=main&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">تعديل</a>
            <form method="POST" style="display:inline;">
<?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="type"   value="main">
              <input type="hidden" name="id"     value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">
                <?= $c['is_active'] ? 'إخفاء' : 'إظهار' ?>
              </button>
            </form>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('هل تريد حذف هذا القسم وكل أقسامه الفرعية؟');">
<?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="type"   value="main">
              <input type="hidden" name="id"     value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">حذف</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-icon">📁</div>
    <p>لا توجد أقسام رئيسية. <a href="category-form.php?type=main" style="color:var(--cyan);">أضف قسمًا</a></p>
  </div>
  <?php endif; ?>
</div>

<!-- Sub Categories -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">📂 الأقسام الفرعية (<?= count($sub_cats) ?>)</div>
    <a href="category-form.php?type=sub" class="btn btn-secondary btn-sm">➕ إضافة</a>
  </div>
  <?php if ($sub_cats): ?>
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>الأيقونة</th>
        <th>الاسم بالعربية</th>
        <th>القسم الرئيسي</th>
        <th>الترتيب</th>
        <th>الحالة</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sub_cats as $s): ?>
      <tr>
        <td class="text-muted"><?= $s['id'] ?></td>
        <td style="font-size:22px;"><?= $s['icon'] ?? '' ?></td>
        <td><?= e($s['name']) ?></td>
        <td class="text-muted"><?= e($s['parent_name'] ?? '') ?></td>
        <td class="text-muted"><?= $s['sort_order'] ?></td>
        <td>
          <span class="badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
            <?= $s['is_active'] ? 'نشط' : 'مخفي' ?>
          </span>
        </td>
        <td>
          <div class="flex-gap">
            <a href="category-form.php?type=sub&id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">تعديل</a>
            <form method="POST" style="display:inline;">
<?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="type"   value="sub">
              <input type="hidden" name="id"     value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">
                <?= $s['is_active'] ? 'إخفاء' : 'إظهار' ?>
              </button>
            </form>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('هل تريد حذف هذا القسم الفرعي؟');">
<?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="type"   value="sub">
              <input type="hidden" name="id"     value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">حذف</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-icon">📂</div>
    <p>لا توجد أقسام فرعية. <a href="category-form.php?type=sub" style="color:var(--cyan);">أضف قسمًا فرعيًا</a></p>
  </div>
  <?php endif; ?>
</div>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
