<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('payments.confirm');

$page_title_admin = 'المدفوعات';

/**
 * Manual payments — a bank transfer, a wallet transfer, a cash handover —
 * arrive as a claim, not as a fact. A customer submits one with a reference;
 * a member of staff confirms it against the receiving account and only then
 * does the money exist in the system.
 *
 * Confirming a payment credits the wallet through the ledger and moves the
 * order forward, in one transaction: the three cannot drift apart.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action    = (string) ($_POST['action'] ?? '');
    $paymentId = admin_id('payment_id');
    $admin     = admin_user();
    $adminId   = (int) $admin['id'];

    $payment = $paymentId > 0
        ? fetch_one($conn, 'SELECT * FROM payments WHERE id = ?', 'i', $paymentId)
        : null;

    if ($payment === null) {
        admin_flash('error', 'الدفعة غير موجودة.');
        admin_redirect('payments.php');
    }

    if ($payment['status'] === 'confirmed' && $action === 'confirm') {
        admin_flash('error', 'هذه الدفعة مؤكَّدة بالفعل.');
        admin_redirect('payments.php');
    }

    $note = mb_substr(trim((string) ($_POST['review_note'] ?? '')), 0, 500);

    if ($action === 'confirm') {
        $amount  = (float) $payment['amount'];
        $userId  = $payment['user_id'] !== null ? (int) $payment['user_id'] : 0;
        $orderId = $payment['order_id'] !== null ? (int) $payment['order_id'] : 0;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "UPDATE payments SET status = 'confirmed', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
                  WHERE id = ? AND status <> 'confirmed'"
            );
            $stmt->bind_param('isi', $adminId, $note, $paymentId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new RuntimeException('تم تأكيد هذه الدفعة بالفعل.');
            }

            if ($orderId > 0) {
                $order = fetch_one($conn, 'SELECT id, order_code, order_status, user_id FROM orders WHERE id = ? FOR UPDATE', 'i', $orderId);
                if ($order !== null) {
                    $upd = $conn->prepare(
                        "UPDATE orders
                            SET payment_status = 'paid', order_status = 'paid',
                                payment_confirmed_by = ?, payment_confirmed_at = NOW(),
                                payment_confirmed_amount = ?, payment_method_recorded = ?,
                                payment_reference = ?
                          WHERE id = ?"
                    );
                    $method    = (string) $payment['method_key'];
                    $reference = (string) ($payment['reference'] ?? '');
                    $upd->bind_param('idssi', $adminId, $amount, $method, $reference, $orderId);
                    $upd->execute();

                    $hist = $conn->prepare(
                        "INSERT INTO order_status_history (order_id, from_status, to_status, actor_type, actor_id, note)
                         VALUES (?, ?, 'paid', 'admin', ?, ?)"
                    );
                    $from     = (string) $order['order_status'];
                    $histNote = 'تأكيد دفعة ' . number_format($amount, 2);
                    $hist->bind_param('isis', $orderId, $from, $adminId, $histNote);
                    $hist->execute();
                }
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            admin_flash('error', $e->getMessage());
            admin_redirect('payments.php');
        }

        // A payment with no order is a wallet top-up, so it credits the wallet.
        // One with an order has already paid for that order, so it does not.
        if ($orderId === 0 && $userId > 0) {
            try {
                wallet_post($userId, 'credit', $amount, 'topup', null,
                    'دفعة مؤكَّدة ' . (string) ($payment['reference'] ?? ''), 'admin', $adminId);
            } catch (Throwable $e) {
                admin_flash('error', 'تم تأكيد الدفعة لكن تعذّر شحن المحفظة: ' . $e->getMessage());
            }
        }

        if ($userId > 0) {
            notify_user($userId, 'تم تأكيد دفعتك',
                'المبلغ ' . number_format($amount, 2) . ' ' . (string) $payment['currency'],
                'success', $orderId > 0 ? 'account.php?tab=orders' : 'account.php?tab=wallet');
        }

        admin_audit('payment.confirmed', 'payments', $paymentId, number_format($amount, 2), $note);
        admin_flash('success', 'تم تأكيد الدفعة.');

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare(
            "UPDATE payments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
              WHERE id = ?"
        );
        $stmt->bind_param('isi', $adminId, $note, $paymentId);
        $stmt->execute();

        if ($payment['user_id'] !== null) {
            notify_user((int) $payment['user_id'], 'لم يتم قبول الدفعة',
                $note !== '' ? $note : 'راجع بيانات التحويل وتواصل مع الدعم.', 'warning');
        }

        admin_audit('payment.rejected', 'payments', $paymentId, '', $note);
        admin_flash('success', 'تم رفض الدفعة وإبلاغ العميل.');
    }

    admin_redirect('payments.php?status=' . urlencode((string) ($_POST['return_status'] ?? 'submitted')));
}

$status = (string) ($_GET['status'] ?? 'submitted');
if (!in_array($status, ['', 'pending', 'submitted', 'confirmed', 'rejected', 'refunded'], true)) {
    $status = '';
}

$where  = ['1 = 1'];
$types  = '';
$params = [];

if ($status !== '') {
    $where[]  = 'p.status = ?';
    $types   .= 's';
    $params[] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn, "SELECT COUNT(*) AS n FROM payments p $whereSql", $types, ...$params);
$paging   = admin_paginate((int) ($totalRow['n'] ?? 0), 25);

$payments = fetch_all(
    $conn,
    "SELECT p.*, u.name AS payer_name, u.email AS payer_email, o.order_code
       FROM payments p
       LEFT JOIN platform_users u ON u.id = p.user_id
       LEFT JOIN orders o         ON o.id = p.order_id
       $whereSql
      ORDER BY p.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$methods = [];
foreach (fetch_all($conn, 'SELECT method_key, name FROM payment_methods') as $row) {
    $methods[$row['method_key']] = $row['name'];
}

$tabs = [
    'submitted' => 'بانتظار التأكيد',
    'confirmed' => 'مؤكَّدة',
    'rejected'  => 'مرفوضة',
    'pending'   => 'لم تُرسَل بعد',
    ''          => 'الكل',
];

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="btn <?= $status === $key ? 'btn-primary' : 'btn-secondary' ?>"
       href="payments.php?status=<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">المدفوعات (<?= (int) ($totalRow['n'] ?? 0) ?>)</div>
  </div>

  <?php if ($payments): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>الدافع</th><th>الطلب</th><th>الطريقة</th><th>المبلغ</th>
              <th>المرجع</th><th>الحالة</th><th>التاريخ</th><th>إجراءات</th></tr>
        </thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
          <tr>
            <td class="text-muted"><?= (int) $payment['id'] ?></td>
            <td>
              <?= e((string) ($payment['payer_name'] ?? '—')) ?>
              <div class="text-muted" style="font-size:11px;" dir="ltr"><?= e((string) ($payment['payer_email'] ?? '')) ?></div>
            </td>
            <td dir="ltr" style="font-size:12px;">
              <?= $payment['order_code']
                    ? '<a href="order-view.php?id=' . (int) $payment['order_id'] . '">' . e((string) $payment['order_code']) . '</a>'
                    : '<span class="text-muted">شحن محفظة</span>' ?>
            </td>
            <td><?= e($methods[$payment['method_key']] ?? (string) $payment['method_key']) ?></td>
            <td class="money text-gold"><?= e(number_format((float) $payment['amount'], 2)) ?> <?= e((string) $payment['currency']) ?></td>
            <td dir="ltr" style="font-size:12px;"><?= e((string) ($payment['reference'] ?? '—')) ?></td>
            <td><?= admin_badge(
                    match ($payment['status']) {
                        'confirmed' => 'مؤكَّدة',
                        'submitted' => 'بانتظار التأكيد',
                        'rejected'  => 'مرفوضة',
                        'refunded'  => 'مستردة',
                        default     => 'لم تُرسَل',
                    },
                    match ($payment['status']) {
                        'confirmed' => 'active',
                        'submitted' => 'review',
                        'rejected'  => 'hidden',
                        default     => 'inactive',
                    }
                ) ?></td>
            <td class="text-muted" style="font-size:12px;" dir="ltr">
              <?= e(date('Y-m-d H:i', strtotime((string) $payment['created_at']))) ?>
            </td>
            <td>
              <?php if ($payment['status'] === 'submitted' || $payment['status'] === 'pending'): ?>
                <form method="post" class="inline-form" data-confirm="تأكيد استلام هذا المبلغ فعليًا؟">
                  <?= csrf_field() ?>
                  <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                  <input type="hidden" name="return_status" value="<?= e($status) ?>">
                  <input class="form-input" type="text" name="review_note" placeholder="ملاحظة" style="width:130px; display:inline-block;">
                  <button class="btn btn-primary btn-sm" type="submit" name="action" value="confirm">تأكيد</button>
                  <button class="btn btn-danger btn-sm" type="submit" name="action" value="reject">رفض</button>
                </form>
              <?php elseif (!empty($payment['review_note'])): ?>
                <span class="text-muted" style="font-size:11px;"><?= e((string) $payment['review_note']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= admin_pager($paging, 'status=' . $status) ?>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">💳</div><p>لا توجد مدفوعات في هذه الحالة.</p></div>
  <?php endif; ?>
</div>

<div class="confidential-note">
  تأكيد الدفعة يسجّل الحركة في دفتر المحفظة وينقل الطلب إلى «مدفوع» في عملية واحدة،
  فلا يمكن أن يتم أحدهما دون الآخر. لا يوجد تعديل مباشر على الأرصدة من هنا.
</div>

<?php admin_layout_end(); ?>
