<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('suppliers.services');

$page_title_admin = 'خدمات الموردين';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action  = (string) ($_POST['action'] ?? '');
    $offerId = admin_id('offer_id');

    $offer = $offerId > 0
        ? fetch_one($conn,
            'SELECT o.*, u.name AS supplier_name
               FROM supplier_offers o
               JOIN platform_users u ON u.id = o.supplier_id
              WHERE o.id = ?',
            'i', $offerId)
        : null;

    if ($offer === null) {
        admin_flash('error', 'الخدمة غير موجودة.');
        admin_redirect('supplier-offers.php');
    }

    $admin   = admin_user();
    $adminId = (int) $admin['id'];
    $notes   = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 2000);

    if ($action === 'approve') {
        // Approving publishes a real store service. The customer-facing row
        // carries the sell price the store sets; the supplier's own price
        // stays on the offer, and supplier_visible stays 0, so nothing about
        // who fulfils it reaches the storefront.
        $categoryId    = max(0, (int) ($_POST['category_id'] ?? 0));
        $sellPrice     = max(0.0, (float) ($_POST['sell_price'] ?? 0));
        $supplierPrice = (float) ($offer['supplier_price'] ?? 0);

        if ($categoryId === 0) {
            admin_flash('error', 'اختر القسم قبل الاعتماد.');
            admin_redirect('supplier-offers.php?status=pending_review');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO store_services
                    (category_id, name, description, price, is_active, status,
                     source_type, order_receiver, execution_method,
                     supplier_id, supplier_sell_price, supplier_visible,
                     hide_customer_data_from_supplier)
                 VALUES (?, ?, ?, ?, 0, 'review', 'supplier', 'support', 'supplier_via_support', ?, ?, 0, 1)"
            );
            $title       = (string) $offer['title'];
            $description = (string) ($offer['description'] ?? '');
            $supplierId  = (int) $offer['supplier_id'];
            $stmt->bind_param('issdid', $categoryId, $title, $description, $sellPrice, $supplierId, $supplierPrice);
            $stmt->execute();
            $serviceId = (int) $conn->insert_id;

            $update = $conn->prepare(
                "UPDATE supplier_offers
                    SET review_status = 'approved', admin_notes = ?, reviewed_by = ?,
                        reviewed_at = NOW(), published_service_id = ?
                  WHERE id = ?"
            );
            $update->bind_param('siii', $notes, $adminId, $serviceId, $offerId);
            $update->execute();

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('[EXD offer approve] ' . $e->getMessage());
            admin_flash('error', 'تعذّر نشر الخدمة.');
            admin_redirect('supplier-offers.php?status=pending_review');
        }

        notify_user((int) $offer['supplier_id'], 'تم اعتماد خدمتك',
            (string) $offer['title'], 'success', 'supplier-dashboard.php?tab=offers');

        admin_audit('offer.approved', 'supplier_offers', $offerId, (string) $offer['title']);
        admin_flash('success', 'تم اعتماد الخدمة ونشرها كمسودة غير نشطة — راجعها قبل التفعيل.');

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare(
            "UPDATE supplier_offers
                SET review_status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
              WHERE id = ?"
        );
        $stmt->bind_param('sii', $notes, $adminId, $offerId);
        $stmt->execute();

        notify_user((int) $offer['supplier_id'], 'لم يتم اعتماد الخدمة',
            $notes !== '' ? $notes : (string) $offer['title'], 'warning', 'supplier-dashboard.php?tab=offers');

        admin_audit('offer.rejected', 'supplier_offers', $offerId, (string) $offer['title'], $notes);
        admin_flash('success', 'تم رفض الخدمة وإبلاغ المورد.');
    }

    admin_redirect('supplier-offers.php?status=' . urlencode((string) ($_POST['return_status'] ?? 'pending_review')));
}

$status     = (string) ($_GET['status'] ?? 'pending_review');
$supplierId = max(0, (int) ($_GET['supplier_id'] ?? 0));

if (!in_array($status, ['', 'pending_review', 'approved', 'rejected'], true)) {
    $status = '';
}

$where  = ['1 = 1'];
$types  = '';
$params = [];

if ($status !== '') {
    $where[]  = 'o.review_status = ?';
    $types   .= 's';
    $params[] = $status;
}
if ($supplierId > 0) {
    $where[]  = 'o.supplier_id = ?';
    $types   .= 'i';
    $params[] = $supplierId;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM supplier_offers o $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 20);

$offers = fetch_all(
    $conn,
    "SELECT o.*, u.name AS supplier_name, sp.company_name
       FROM supplier_offers o
       JOIN platform_users u          ON u.id = o.supplier_id
       LEFT JOIN supplier_profiles sp ON sp.user_id = o.supplier_id
       $whereSql
      ORDER BY o.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$categories = fetch_all($conn, 'SELECT id, name FROM store_categories WHERE is_active = 1 ORDER BY sort_order, name');

$tabs = ['pending_review' => 'قيد المراجعة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة', '' => 'الكل'];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="btn <?= $status === $key ? 'btn-primary' : 'btn-secondary' ?>"
       href="supplier-offers.php?status=<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <?php if ($supplierId > 0): ?>
    <a class="btn btn-secondary" href="supplier-offers.php">إلغاء تصفية المورد</a>
  <?php endif; ?>
</div>

<?php if ($offers): ?>
  <?php foreach ($offers as $offer): ?>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title"><?= e((string) $offer['title']) ?></div>
        <?= admin_badge(
              match ($offer['review_status']) {
                  'approved' => 'معتمدة',
                  'rejected' => 'مرفوضة',
                  default    => 'قيد المراجعة',
              },
              match ($offer['review_status']) {
                  'approved' => 'active',
                  'rejected' => 'hidden',
                  default    => 'review',
              }
          ) ?>
      </div>

      <div class="detail-grid">
        <table class="kv">
          <tr><td>المورد</td><td><?= e((string) $offer['supplier_name']) ?></td></tr>
          <tr><td>النشاط</td><td><?= e((string) ($offer['company_name'] ?: '—')) ?></td></tr>
          <tr><td>سعر المورد</td><td class="money"><?= $offer['supplier_price'] !== null ? e(number_format((float) $offer['supplier_price'], 2)) . ' ' . e((string) $offer['currency']) : '—' ?></td></tr>
          <tr><td>مدة التنفيذ</td><td><?= e((string) ($offer['execution_time'] ?: '—')) ?></td></tr>
          <tr><td>تاريخ التقديم</td><td dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $offer['created_at']))) ?></td></tr>
        </table>

        <div>
          <div class="form-label">الوصف</div>
          <p class="text-muted" style="font-size:13px; line-height:1.9;">
            <?= nl2br(e((string) ($offer['description'] ?: '—'))) ?>
          </p>
          <?php if (!empty($offer['admin_notes'])): ?>
            <div class="form-label mt-8">ملاحظات الإدارة</div>
            <p class="text-muted" style="font-size:12px;"><?= nl2br(e((string) $offer['admin_notes'])) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($offer['review_status'] === 'pending_review'): ?>
        <form method="post" class="mt-16">
          <?= csrf_field() ?>
          <input type="hidden" name="offer_id" value="<?= (int) $offer['id'] ?>">
          <input type="hidden" name="return_status" value="<?= e($status) ?>">

          <div class="form-grid-3">
            <div class="form-group">
              <label class="form-label">القسم <span class="req">*</span></label>
              <select class="form-select" name="category_id">
                <option value="0">— اختر القسم —</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= (int) $category['id'] ?>"><?= e((string) $category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">سعر البيع للعميل</label>
              <input class="form-input" type="number" step="0.01" min="0" name="sell_price" dir="ltr"
                     value="<?= e(number_format((float) ($offer['suggested_sell_price'] ?: $offer['supplier_price'] ?: 0), 2, '.', '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">ملاحظات</label>
              <input class="form-input" type="text" name="admin_notes" placeholder="سبب القبول أو الرفض">
            </div>
          </div>

          <div class="flex-gap mt-8">
            <button class="btn btn-primary" type="submit" name="action" value="approve">اعتماد ونشر</button>
            <button class="btn btn-danger" type="submit" name="action" value="reject">رفض</button>
          </div>
        </form>
      <?php elseif ($offer['published_service_id']): ?>
        <p class="mt-8">
          <a class="btn btn-secondary btn-sm" href="service-form.php?id=<?= (int) $offer['published_service_id'] ?>">
            فتح الخدمة المنشورة
          </a>
        </p>
      <?php endif; ?>

      <div class="confidential-note">
        بيانات المورد وسعره لا تظهر للعميل. الخدمة المنشورة تحمل سعر البيع فقط، والتواصل يمر عبر الدعم.
      </div>
    </div>
  <?php endforeach; ?>
  <?= admin_pager($paging, 'status=' . $status) ?>
<?php else: ?>
  <div class="panel">
    <div class="empty-state"><div class="empty-icon">📦</div><p>لا توجد خدمات في هذه الحالة.</p></div>
  </div>
<?php endif; ?>

<?php admin_layout_end(); ?>
