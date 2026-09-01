<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../lib/mediation.php';
admin_require('mediation.view');

$page_title_admin = 'الوساطة';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    admin_require('mediation.manage');

    $action = (string) ($_POST['action'] ?? '');
    $admin  = admin_user();
    $adminId = (int) $admin['id'];

    if ($action === 'open') {
        $subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 255);
        $amount  = max(0.0, (float) ($_POST['deal_amount'] ?? 0));
        $fee     = max(0.0, (float) ($_POST['fee_amount'] ?? 0));
        $days    = max(0, min(90, (int) ($_POST['safety_days'] ?? 7)));
        $buyerId  = max(0, (int) ($_POST['buyer_id'] ?? 0));
        $sellerId = max(0, (int) ($_POST['seller_id'] ?? 0));

        if ($subject === '' || $amount <= 0) {
            admin_flash('error', 'الموضوع وقيمة الصفقة مطلوبان.');
            admin_redirect('mediation.php');
        }

        $conn->begin_transaction();
        try {
            $code = mediation_generate_code($conn);
            $stmt = $conn->prepare(
                'INSERT INTO mediations (case_code, subject, deal_amount, fee_amount, safety_days, mediator_admin_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssddii', $code, $subject, $amount, $fee, $days, $adminId);
            $stmt->execute();
            $mediationId = (int) $conn->insert_id;

            $party = $conn->prepare(
                'INSERT INTO mediation_parties (mediation_id, user_id, party_role, display_name) VALUES (?, ?, ?, ?)'
            );
            foreach ([['buyer', $buyerId], ['seller', $sellerId]] as [$role, $partyId]) {
                $userId = $partyId > 0 ? $partyId : null;
                $name   = mb_substr(trim((string) ($_POST[$role . '_name'] ?? '')), 0, 190);
                $party->bind_param('iiss', $mediationId, $userId, $role, $name);
                $party->execute();
            }

            $hist = $conn->prepare(
                "INSERT INTO mediation_status_history (mediation_id, from_status, to_status, actor_type, actor_id, note)
                 VALUES (?, NULL, 'opened', 'admin', ?, 'فتح الصفقة')"
            );
            $hist->bind_param('ii', $mediationId, $adminId);
            $hist->execute();

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            admin_flash('error', 'تعذّر فتح الصفقة.');
            admin_redirect('mediation.php');
        }

        admin_audit('mediation.opened', 'mediations', $mediationId, $subject);
        admin_flash('success', 'تم فتح صفقة الوساطة ' . $code . '.');

    } elseif ($action === 'move') {
        $mediationId = admin_id('mediation_id');
        $toStatus    = (string) ($_POST['to_status'] ?? '');
        $note        = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);

        [$ok, $message] = mediation_move($conn, $mediationId, $toStatus, 'admin', $adminId, $note);

        if ($ok) {
            $case = fetch_one($conn, 'SELECT case_code FROM mediations WHERE id = ?', 'i', $mediationId);
            foreach (fetch_all($conn, 'SELECT user_id FROM mediation_parties WHERE mediation_id = ? AND user_id IS NOT NULL', 'i', $mediationId) as $party) {
                notify_user((int) $party['user_id'], 'تحديث على صفقة الوساطة',
                    (string) ($case['case_code'] ?? '') . ' — ' . (mediation_statuses()[$toStatus] ?? $toStatus),
                    'info', 'mediation.php');
            }
            admin_audit('mediation.moved', 'mediations', $mediationId, $toStatus, $note);
        }

        admin_flash($ok ? 'success' : 'error', $message);
    }

    admin_redirect('mediation.php');
}

$status = (string) ($_GET['status'] ?? 'open');

$where  = ['1 = 1'];
$types  = '';
$params = [];

if ($status === 'open') {
    $where[] = "m.status NOT IN ('released','refunded','cancelled')";
} elseif ($status !== '' && isset(mediation_statuses()[$status])) {
    $where[]  = 'm.status = ?';
    $types   .= 's';
    $params[] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM mediations m $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 20);

$cases = fetch_all(
    $conn,
    "SELECT m.* FROM mediations m $whereSql ORDER BY m.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$partiesByCase = [];
if ($cases) {
    $ids = implode(',', array_map(static fn(array $c): int => (int) $c['id'], $cases));
    foreach (fetch_all($conn,
        "SELECT mp.mediation_id, mp.party_role, mp.display_name, mp.user_id, u.name AS account_name
           FROM mediation_parties mp
           LEFT JOIN platform_users u ON u.id = mp.user_id
          WHERE mp.mediation_id IN ($ids)") as $row) {
        $partiesByCase[(int) $row['mediation_id']][$row['party_role']] = $row;
    }
}

$statuses = mediation_statuses();

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <a class="btn <?= $status === 'open' ? 'btn-primary' : 'btn-secondary' ?>" href="mediation.php?status=open">المفتوحة</a>
  <?php foreach (['funds_held', 'delivered', 'safety_period', 'disputed', 'released', 'refunded'] as $key): ?>
    <a class="btn <?= $status === $key ? 'btn-primary' : 'btn-secondary' ?>"
       href="mediation.php?status=<?= e($key) ?>"><?= e($statuses[$key]) ?></a>
  <?php endforeach; ?>
  <a class="btn <?= $status === '' ? 'btn-primary' : 'btn-secondary' ?>" href="mediation.php?status=">الكل</a>
</div>

<?php if ($cases): ?>
  <?php foreach ($cases as $case): $id = (int) $case['id']; $from = (string) $case['status']; ?>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">
          <?= e((string) $case['subject']) ?>
          <span class="text-muted" style="font-size:12px;" dir="ltr"><?= e((string) $case['case_code']) ?></span>
        </div>
        <?= admin_badge($statuses[$from] ?? $from,
              in_array($from, ['released'], true) ? 'active'
              : (in_array($from, ['disputed', 'cancelled'], true) ? 'hidden' : 'review')) ?>
      </div>

      <div class="detail-grid">
        <table class="kv">
          <tr><td>قيمة الصفقة</td><td class="money"><?= e(number_format((float) $case['deal_amount'], 2)) ?> <?= e((string) $case['currency']) ?></td></tr>
          <tr><td>رسوم الوساطة</td><td class="money"><?= e(number_format((float) $case['fee_amount'], 2)) ?></td></tr>
          <tr><td>أيام الأمان</td><td><?= (int) $case['safety_days'] ?></td></tr>
          <tr><td>تنتهي فترة الأمان</td><td dir="ltr"><?= $case['safety_ends_at'] ? e(date('Y-m-d H:i', strtotime((string) $case['safety_ends_at']))) : '—' ?></td></tr>
          <tr><td>فُتحت</td><td dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $case['opened_at']))) ?></td></tr>
        </table>

        <table class="kv">
          <?php foreach (['buyer' => 'المشتري', 'seller' => 'البائع'] as $role => $label): ?>
            <?php $party = $partiesByCase[$id][$role] ?? null; ?>
            <tr>
              <td><?= e($label) ?></td>
              <td><?= e((string) ($party['account_name'] ?? $party['display_name'] ?? '—')) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <?php if (admin_can('mediation.manage') && (mediation_transitions()[$from] ?? []) !== []): ?>
        <form method="post" class="mt-16">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="mediation_id" value="<?= $id ?>">
          <div class="filter-bar">
            <div class="form-group">
              <label class="form-label">الحالة التالية</label>
              <select class="form-select" name="to_status">
                <?php foreach (mediation_transitions()[$from] as $next): ?>
                  <option value="<?= e($next) ?>"><?= e($statuses[$next] ?? $next) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="flex:1;">
              <label class="form-label">ملاحظة</label>
              <input class="form-input" type="text" name="note">
            </div>
            <button class="btn btn-primary" type="submit">تنفيذ</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?= admin_pager($paging, 'status=' . $status) ?>
<?php else: ?>
  <div class="panel">
    <div class="empty-state"><div class="empty-icon">🤝</div><p>لا توجد صفقات في هذه الحالة.</p></div>
  </div>
<?php endif; ?>

<?php if (admin_can('mediation.manage')): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">فتح صفقة وساطة</div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="open">
      <div class="form-grid-3">
        <div class="form-group form-full">
          <label class="form-label">موضوع الصفقة <span class="req">*</span></label>
          <input class="form-input" type="text" name="subject" required placeholder="بيع قناة يوتيوب">
        </div>
        <div class="form-group">
          <label class="form-label">قيمة الصفقة <span class="req">*</span></label>
          <input class="form-input" type="number" step="0.01" min="0.01" dir="ltr" name="deal_amount" required>
        </div>
        <div class="form-group">
          <label class="form-label">رسوم الوساطة</label>
          <input class="form-input" type="number" step="0.01" min="0" dir="ltr" name="fee_amount" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">أيام الأمان</label>
          <input class="form-input" type="number" min="0" max="90" dir="ltr" name="safety_days"
                 value="<?= e((string) setting('mediation_default_safety_days', '7')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">رقم حساب المشتري</label>
          <input class="form-input" type="number" min="0" dir="ltr" name="buyer_id" placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">اسم المشتري</label>
          <input class="form-input" type="text" name="buyer_name">
        </div>
        <div class="form-group">
          <label class="form-label">رقم حساب البائع</label>
          <input class="form-input" type="number" min="0" dir="ltr" name="seller_id" placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">اسم البائع</label>
          <input class="form-input" type="text" name="seller_name">
        </div>
      </div>
      <button class="btn btn-primary" type="submit">فتح الصفقة</button>
    </form>
    <div class="confidential-note">
      حجز المبلغ يخصم من رصيد المشتري ويضعه في «محجوز». التحرير يُضيفه لرصيد البائع،
      والاسترداد يعيده للمشتري. كل خطوة حركة مسجّلة في الدفتر، لا تعديل مباشر على رصيد.
    </div>
  </div>
<?php endif; ?>

<?php admin_layout_end(); ?>
