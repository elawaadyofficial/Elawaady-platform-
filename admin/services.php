<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('catalog.manage');
require_once __DIR__ . '/../db_connect.php';
$page_title_admin = 'إدارة الخدمات';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    if ($_POST['action'] === 'toggle') {
        $conn->query("UPDATE store_services SET is_active = NOT is_active WHERE id=$id");
    }
    if ($_POST['action'] === 'delete') {
        $conn->query("DELETE FROM store_services WHERE id=$id");
    }
    header('Location: services.php' . (isset($_GET['s']) ? '?s='.urlencode($_GET['s']) : '')); exit;
}

$search = trim($_GET['s'] ?? '');
$filter_cat = (int)($_GET['cat'] ?? 0);

$where = "WHERE 1=1";
$params_types = '';
$params_vals = [];

if ($search !== '') {
    $where .= " AND (s.name LIKE ? OR s.service_code LIKE ?)";
    $params_types .= 'ss';
    $like = "%$search%";
    $params_vals[] = &$like;
    $params_vals[] = &$like;
}
if ($filter_cat > 0) {
    $where .= " AND s.category_id=$filter_cat";
}

$services = fetch_all($conn, "
    SELECT s.id, s.name, s.status, s.is_active, s.price, s.show_price, s.ask_for_price,
           s.service_type, s.badge, s.show_home,
           c.name AS cat_name, sc.name AS sub_name, s.created_at
    FROM store_services s
    LEFT JOIN store_categories c ON c.id=s.category_id
    LEFT JOIN store_subcategories sc ON sc.id=s.subcategory_id
    $where
    ORDER BY s.sort_order ASC, s.id DESC
");

$main_cats = fetch_all($conn, "SELECT id, name FROM store_categories ORDER BY sort_order ASC");

include __DIR__ . '/layout.php';
?>

<div class="flex-between" style="margin-bottom:16px; flex-wrap:wrap; gap:10px;">
  <a href="service-form.php" class="btn btn-primary">➕ إضافة خدمة</a>

  <form method="GET" class="search-bar" style="flex-wrap:wrap; gap:8px;">
    <input type="text" name="s" value="<?= e($search) ?>" placeholder="ابحث عن خدمة...">
    <select name="cat" class="form-select" style="min-height:38px; border-radius:999px; min-width:170px; padding:0 12px;">
      <option value="">كل الأقسام</option>
      <?php foreach ($main_cats as $mc): ?>
        <option value="<?= $mc['id'] ?>" <?= $filter_cat==$mc['id'] ? 'selected' : '' ?>><?= e($mc['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">بحث</button>
    <?php if ($search || $filter_cat): ?>
      <a href="services.php" class="btn btn-secondary">مسح</a>
    <?php endif; ?>
  </form>
</div>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">🛍️ الخدمات (<?= count($services) ?>)</div>
  </div>

  <?php if ($services): ?>
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>اسم الخدمة</th>
        <th>القسم</th>
        <th>النوع</th>
        <th>السعر</th>
        <th>الحالة</th>
        <th>الرئيسية</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($services as $s): ?>
      <tr>
        <td class="text-muted"><?= $s['id'] ?></td>
        <td>
          <?= e($s['name']) ?>
          <?php if ($s['badge']): ?>
            <span class="badge badge-review" style="margin-right:4px; font-size:10px;"><?= e($s['badge']) ?></span>
          <?php endif; ?>
        </td>
        <td class="text-muted">
          <?= e($s['cat_name'] ?? '—') ?>
          <?php if ($s['sub_name']): ?>
            <br><small><?= e($s['sub_name']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php
          $types = [
            'internal'=>'داخلي','supplier'=>'مورّد','mediation'=>'وساطة',
            'digital_product'=>'منتج','subscription'=>'اشتراك','topup'=>'شحن','special_offer'=>'عرض',
          ];
          echo '<span class="text-muted" style="font-size:12px;">' . e($types[$s['service_type']] ?? $s['service_type']) . '</span>';
          ?>
        </td>
        <td>
          <?php
          if ($s['ask_for_price']) echo '<span class="text-gold">اسأل</span>';
          elseif (!$s['show_price']) echo '<span class="text-muted">مخفي</span>';
          elseif ($s['price'] > 0) echo number_format($s['price'],2) . ' ج.م';
          else echo '<span class="text-muted">حسب الطلب</span>';
          ?>
        </td>
        <td>
          <?php
          $st = $s['status'] ?? '';
          $bc = match($st) {
            'active','متاحة','متاح' => 'badge-active',
            'hidden' => 'badge-hidden',
            'review' => 'badge-review',
            'inactive' => 'badge-inactive',
            default => $s['is_active'] ? 'badge-active' : 'badge-inactive',
          };
          ?>
          <span class="badge <?= $bc ?>"><?= e($st ?: ($s['is_active'] ? 'نشط' : 'مخفي')) ?></span>
        </td>
        <td>
          <?php if ($s['show_home']): ?>
            <span class="text-green" style="font-size:18px;">✓</span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="flex-gap">
            <a href="service-form.php?id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">تعديل</a>
            <form method="POST" style="display:inline;">
<?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">
                <?= $s['is_active'] ? 'إخفاء' : 'إظهار' ?>
              </button>
            </form>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('هل تريد حذف هذه الخدمة نهائيًا؟');">
<?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
    <div class="empty-icon">🛍️</div>
    <p>
      <?= $search ? 'لا نتائج لـ "'.e($search).'".' : 'لا توجد خدمات بعد.' ?>
      <a href="service-form.php" style="color:var(--cyan);">أضف أول خدمة</a>
    </p>
  </div>
  <?php endif; ?>
</div>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
