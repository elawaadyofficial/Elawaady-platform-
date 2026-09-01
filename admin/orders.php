<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('orders.view');

$page_title_admin = 'الطلبات';

// Filters
$filter_status  = trim($_GET['status'] ?? '');
$filter_type    = trim($_GET['type']   ?? '');
$filter_search  = trim($_GET['q']      ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 25;
$offset         = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($filter_status !== '') {
    $where[]  = 'o.order_status = ?';
    $params[] = $filter_status;
    $types   .= 's';
}
if ($filter_type !== '') {
    $where[]  = 'o.order_type = ?';
    $params[] = $filter_type;
    $types   .= 's';
}
if ($filter_search !== '') {
    $like     = '%' . $filter_search . '%';
    $where[]  = '(o.order_code LIKE ? OR o.service_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_name LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'ssss';
}

$where_sql = implode(' AND ', $where);
$count_row = fetch_one($conn, "SELECT COUNT(*) AS n FROM orders o WHERE $where_sql",
    $types, ...$params);
$total     = (int)($count_row['n'] ?? 0);
$total_pages = max(1, (int)ceil($total / $per_page));

$params_limit = array_merge($params, [$per_page, $offset]);
$types_limit  = $types . 'ii';
$orders = fetch_all($conn,
    "SELECT o.id, o.order_code, o.service_name, o.service_id,
            o.customer_name, o.customer_phone,
            o.quantity, o.total_price,
            o.order_type, o.order_status, o.payment_status,
            o.mediation_enabled, o.created_at
     FROM orders o WHERE $where_sql
     ORDER BY o.id DESC LIMIT ? OFFSET ?",
    $types_limit, ...$params_limit);

$status_labels = [
    'new'               => ['جديد',           'badge-active'],
    'waiting_approval'  => ['انتظار موافقة',   'badge-review'],
    'waiting_payment'   => ['انتظار الدفع',    'badge-review'],
    'in_progress'       => ['قيد التنفيذ',     'badge-active'],
    'completed'         => ['مكتمل',           'badge-active'],
    'cancelled'         => ['ملغي',            'badge-inactive'],
    'dispute'           => ['نزاع',            'badge-hidden'],
];
$pay_labels = [
    'pending'  => ['pending',  'badge-review'],
    'paid'     => ['مدفوع',   'badge-active'],
    'failed'   => ['فاشل',    'badge-inactive'],
    'refunded' => ['مسترد',   'badge-hidden'],
];
$type_labels = [
    'whatsapp'    => '📱 واتساب',
    'cart'        => '🛒 سلة',
    'direct_buy'  => '⚡ شراء مباشر',
    'mediation'   => '🤝 وساطة',
];

include __DIR__ . '/layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
  <h2 style="font-size:18px; font-weight:900; margin:0;">
    الطلبات <span style="color:var(--muted); font-weight:400; font-size:14px;">(<?= $total ?>)</span>
  </h2>
</div>

<!-- Filters -->
<form method="get" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
  <input type="text" name="q" class="form-input" value="<?= e($filter_search) ?>"
         placeholder="بحث بكود / اسم / هاتف..." style="flex:1; min-width:180px; max-width:280px;">
  <select name="status" class="form-select" style="min-width:140px;">
    <option value="">كل الحالات</option>
    <?php foreach ($status_labels as $v => [$l]) : ?>
      <option value="<?= $v ?>" <?= $filter_status === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" class="form-select" style="min-width:130px;">
    <option value="">كل الأنواع</option>
    <?php foreach ($type_labels as $v => $l) : ?>
      <option value="<?= $v ?>" <?= $filter_type === $v ? 'selected' : '' ?>><?= strip_tags($l) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">بحث</button>
  <?php if ($filter_search || $filter_status || $filter_type): ?>
    <a href="orders.php" class="btn btn-secondary">مسح</a>
  <?php endif; ?>
</form>

<div class="panel">
  <?php if ($orders): ?>
  <div style="overflow-x:auto;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>الكود</th>
        <th>الخدمة</th>
        <th>العميل</th>
        <th>الكمية</th>
        <th>الإجمالي</th>
        <th>النوع</th>
        <th>الحالة</th>
        <th>الدفع</th>
        <th>التاريخ</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <?php
        [$sl, $sc] = $status_labels[$o['order_status']] ?? ['—', 'badge-inactive'];
        [$pl, $pc] = $pay_labels[$o['payment_status']]  ?? ['—', 'badge-inactive'];
        $tl = $type_labels[$o['order_type']] ?? $o['order_type'];
      ?>
      <tr>
        <td>
          <a href="order-view.php?id=<?= $o['id'] ?>"
             style="color:var(--gold); font-family:monospace; font-weight:700; font-size:12px; text-decoration:none;">
            <?= e($o['order_code']) ?>
          </a>
        </td>
        <td>
          <a href="../service.php?id=<?= (int)$o['service_id'] ?>" target="_blank"
             style="color:#fff; text-decoration:none; font-size:13px;">
            <?= e(mb_substr($o['service_name'], 0, 30)) ?>
          </a>
        </td>
        <td style="font-size:12px; color:var(--muted);">
          <?= e($o['customer_name'] ?: '—') ?>
          <?php if ($o['customer_phone']): ?>
            <br><span style="color:var(--cyan);"><?= e($o['customer_phone']) ?></span>
          <?php endif; ?>
        </td>
        <td style="text-align:center;"><?= (int)$o['quantity'] ?></td>
        <td style="font-weight:700; color:var(--gold);">
          <?= $o['total_price'] > 0 ? number_format($o['total_price'], 2) : '—' ?>
        </td>
        <td style="font-size:12px;"><?= $tl ?></td>
        <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
        <td><span class="badge <?= $pc ?>"><?= $pl ?></span></td>
        <td class="text-muted" style="font-size:12px; white-space:nowrap;">
          <?= date('Y-m-d', strtotime($o['created_at'])) ?>
          <br><?= date('H:i', strtotime($o['created_at'])) ?>
        </td>
        <td>
          <a href="order-view.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm">عرض</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <p>لا توجد طلبات<?= ($filter_search || $filter_status || $filter_type) ? ' تطابق البحث' : ' بعد' ?>.</p>
  </div>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="display:flex; gap:6px; justify-content:center; margin-top:18px; flex-wrap:wrap;">
  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <?php
    $qs = http_build_query(array_filter([
        'q' => $filter_search, 'status' => $filter_status, 'type' => $filter_type, 'page' => $p
    ]));
    ?>
    <a href="orders.php?<?= $qs ?>"
       class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
      <?= $p ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
