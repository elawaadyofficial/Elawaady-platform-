<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('audit.view');

$page_title_admin = 'سجل العمليات';

// The audit log is append-only. There is no delete here and no edit — a record
// you can rewrite is not a record.

$actorType = (string) ($_GET['actor_type'] ?? '');
$action    = trim((string) ($_GET['action'] ?? ''));

if (!in_array($actorType, ['', 'admin', 'user', 'supplier', 'system'], true)) {
    $actorType = '';
}

$where  = ['1 = 1'];
$types  = '';
$params = [];

if ($actorType !== '') {
    $where[]  = 'actor_type = ?';
    $types   .= 's';
    $params[] = $actorType;
}
if ($action !== '') {
    $where[]  = 'action LIKE ?';
    $types   .= 's';
    $params[] = '%' . $action . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM audit_log $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 50);

$entries = fetch_all(
    $conn,
    "SELECT id, actor_type, actor_id, actor_label, action, entity_type, entity_id,
            summary, ip_address, created_at
       FROM audit_log $whereSql
      ORDER BY id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$actorLabels = ['admin' => 'إدارة', 'user' => 'مستخدم', 'supplier' => 'مورد', 'system' => 'النظام'];

include __DIR__ . '/layout.php';
?>

<form class="filter-bar" method="get">
  <div class="form-group">
    <label class="form-label">الفاعل</label>
    <select class="form-select" name="actor_type">
      <option value="">الكل</option>
      <?php foreach ($actorLabels as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $actorType === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label class="form-label">الإجراء</label>
    <input class="form-input" type="search" name="action" dir="ltr" value="<?= e($action) ?>" placeholder="order.status">
  </div>
  <button class="btn btn-secondary" type="submit">تصفية</button>
</form>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">سجل العمليات (<?= (int) ($totalRow['n'] ?? 0) ?>)</div>
  </div>

  <?php if ($entries): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>التاريخ</th><th>الفاعل</th><th>الإجراء</th><th>الهدف</th><th>تفاصيل</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
          <tr>
            <td class="text-muted" style="font-size:12px;" dir="ltr">
              <?= e(date('Y-m-d H:i', strtotime((string) $entry['created_at']))) ?>
            </td>
            <td>
              <?= e($actorLabels[$entry['actor_type']] ?? (string) $entry['actor_type']) ?>
              <?php if (!empty($entry['actor_label'])): ?>
                <span class="text-muted" style="font-size:11px;"><?= e((string) $entry['actor_label']) ?></span>
              <?php endif; ?>
            </td>
            <td dir="ltr" style="font-size:12px;" class="text-cyan"><?= e((string) $entry['action']) ?></td>
            <td class="text-muted" style="font-size:12px;" dir="ltr">
              <?= $entry['entity_type'] ? e((string) $entry['entity_type']) . '#' . (int) $entry['entity_id'] : '—' ?>
            </td>
            <td style="font-size:12px; white-space:normal; max-width:280px;">
              <?= e(mb_strimwidth((string) ($entry['summary'] ?? ''), 0, 90, '…')) ?>
            </td>
            <td class="text-muted" style="font-size:11px;" dir="ltr"><?= e((string) ($entry['ip_address'] ?? '—')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= admin_pager($paging, http_build_query(array_filter(['actor_type' => $actorType, 'action' => $action]))) ?>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">📜</div><p>لا توجد عمليات مسجّلة بهذه المواصفات.</p></div>
  <?php endif; ?>
</div>

<?php admin_layout_end(); ?>
