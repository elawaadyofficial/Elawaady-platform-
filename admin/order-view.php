<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../provider_client.php';
admin_require('orders.view');

/**
 * One order, and everything that may be done to it.
 *
 * Every write here checks the CSRF token and a permission, and every status
 * change goes through admin_order_can_move() — the workflow graph is declared
 * once in _helpers.php and no page may invent a transition. Each change writes
 * a row in order_status_history saying who made it and whether the customer
 * may see it, so the timeline the customer reads and the record staff audit
 * are the same data.
 */

// The token is checked before anything else on a POST, including before the
// page decides there is nothing to act on. A guard that runs after an early
// return is a guard with a gap in it.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

$id = admin_id('id');
if ($id === 0) {
    admin_redirect('orders.php');
}

/** Load the order fresh — after a write as well as before one. */
function load_order($conn, int $id): ?array {
    return fetch_one($conn, "
        SELECT o.*, s.whatsapp_number, s.currency AS svc_currency,
               s.order_receiver, s.execution_method, s.source_type,
               u.name AS account_name, u.email AS account_email,
               sup.name AS supplier_account_name
          FROM orders o
          LEFT JOIN store_services s   ON s.id  = o.service_id
          LEFT JOIN platform_users u   ON u.id  = o.user_id
          LEFT JOIN platform_users sup ON sup.id = o.supplier_id
         WHERE o.id = ?", 'i', $id);
}

$order = load_order($conn, $id);
if ($order === null) {
    admin_flash('error', 'الطلب غير موجود.');
    admin_redirect('orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = (string) ($_POST['action'] ?? '');
    $admin   = admin_user();
    $adminId = (int) $admin['id'];

    if ($action === 'set_status') {
        admin_require('orders.manage');

        $from = (string) $order['order_status'];
        $to   = (string) ($_POST['to_status'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
        $visible = empty($_POST['internal']) ? 1 : 0;

        if (!admin_order_can_move($from, $to)) {
            admin_flash('error', 'لا يمكن الانتقال من «' . admin_order_status_label($from)
                . '» إلى «' . admin_order_status_label($to) . '».');
            admin_redirect('order-view.php?id=' . $id);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE orders SET order_status = ? WHERE id = ?');
            $stmt->bind_param('si', $to, $id);
            $stmt->execute();

            $hist = $conn->prepare(
                'INSERT INTO order_status_history
                    (order_id, from_status, to_status, actor_type, actor_id, note, customer_visible)
                 VALUES (?, ?, ?, "admin", ?, ?, ?)'
            );
            $hist->bind_param('issisi', $id, $from, $to, $adminId, $note, $visible);
            $hist->execute();

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            admin_flash('error', 'تعذّر تحديث الحالة.');
            admin_redirect('order-view.php?id=' . $id);
        }

        // A refund returns the money the same way it arrived: through the ledger.
        if ($to === 'refunded' && $order['user_id'] !== null && (float) $order['total_price'] > 0
            && $order['payment_status'] === 'paid') {
            try {
                wallet_post((int) $order['user_id'], 'credit', (float) $order['total_price'],
                    'order_refund', $id, 'استرداد الطلب ' . (string) $order['order_code'], 'admin', $adminId);

                $pay = $conn->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?");
                $pay->bind_param('i', $id);
                $pay->execute();

                admin_flash('success', 'تم رد المبلغ إلى محفظة العميل.');
            } catch (Throwable $e) {
                admin_flash('error', 'تم تغيير الحالة لكن تعذّر رد المبلغ: ' . $e->getMessage());
            }
        }

        if ($order['user_id'] !== null && $visible === 1) {
            notify_user((int) $order['user_id'], 'تحديث على طلبك',
                (string) $order['order_code'] . ' — ' . admin_order_status_label($to),
                'info', 'order-track.php?code=' . urlencode((string) $order['order_code']));
        }

        admin_audit('order.status_changed', 'orders', $id,
            (string) $order['order_code'] . ': ' . $from . ' → ' . $to, $note);
        admin_flash('success', 'تم تحديث حالة الطلب.');

    } elseif ($action === 'save_notes') {
        admin_require('orders.manage');
        $notes = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 5000);
        $stmt  = $conn->prepare('UPDATE orders SET admin_notes = ? WHERE id = ?');
        $stmt->bind_param('si', $notes, $id);
        $stmt->execute();
        admin_audit('order.notes_saved', 'orders', $id, (string) $order['order_code']);
        admin_flash('success', 'تم حفظ الملاحظات.');

    } elseif ($action === 'confirm_payment') {
        admin_require('payments.confirm');

        if ($order['payment_status'] === 'paid') {
            admin_flash('error', 'هذا الطلب مدفوع بالفعل.');
            admin_redirect('order-view.php?id=' . $id);
        }

        $amount    = round((float) ($_POST['amount'] ?? $order['total_price']), 2);
        $method    = mb_substr(trim((string) ($_POST['method'] ?? '')), 0, 100);
        $reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 190);

        if ($amount <= 0) {
            admin_flash('error', 'المبلغ غير صحيح.');
            admin_redirect('order-view.php?id=' . $id);
        }

        $from = (string) $order['order_status'];
        $to   = admin_order_can_move($from, 'paid') ? 'paid' : $from;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "UPDATE orders
                    SET payment_status = 'paid', order_status = ?,
                        payment_confirmed_by = ?, payment_confirmed_at = NOW(),
                        payment_confirmed_amount = ?, payment_method_recorded = ?,
                        payment_reference = ?
                  WHERE id = ? AND payment_status <> 'paid'"
            );
            $stmt->bind_param('sidssi', $to, $adminId, $amount, $method, $reference, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new RuntimeException('تم تأكيد الدفع بالفعل.');
            }

            $hist = $conn->prepare(
                'INSERT INTO order_status_history
                    (order_id, from_status, to_status, actor_type, actor_id, note, customer_visible)
                 VALUES (?, ?, ?, "admin", ?, ?, 1)'
            );
            $note = 'تأكيد دفعة ' . number_format($amount, 2);
            $hist->bind_param('issis', $id, $from, $to, $adminId, $note);
            $hist->execute();

            $pay = $conn->prepare(
                "INSERT INTO payments (order_id, user_id, method_key, amount, currency, status,
                                       reference, reviewed_by, reviewed_at)
                 VALUES (?, ?, ?, ?, ?, 'confirmed', ?, ?, NOW())"
            );
            $userId   = $order['user_id'] !== null ? (int) $order['user_id'] : null;
            $currency = (string) $order['currency'];
            $methodKey = $method !== '' ? $method : 'manual';
            $pay->bind_param('iisdssi', $id, $userId, $methodKey, $amount, $currency, $reference, $adminId);
            $pay->execute();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            admin_flash('error', $e->getMessage());
            admin_redirect('order-view.php?id=' . $id);
        }

        if ($order['user_id'] !== null) {
            notify_user((int) $order['user_id'], 'تم تأكيد الدفع',
                (string) $order['order_code'], 'success',
                'order-track.php?code=' . urlencode((string) $order['order_code']));
        }

        admin_audit('order.payment_confirmed', 'orders', $id,
            (string) $order['order_code'] . ' — ' . number_format($amount, 2), $reference);
        admin_flash('success', 'تم تأكيد الدفع.');

    } elseif ($action === 'assign_supplier') {
        admin_require('orders.manage');
        $supplierId = max(0, (int) ($_POST['supplier_id'] ?? 0)) ?: null;

        if ($supplierId !== null) {
            $supplier = fetch_one($conn,
                "SELECT id, name FROM platform_users WHERE id = ? AND account_type = 'supplier' AND status = 'active'",
                'i', $supplierId);
            if ($supplier === null) {
                admin_flash('error', 'المورد غير موجود أو غير معتمد.');
                admin_redirect('order-view.php?id=' . $id);
            }
        }

        $stmt = $conn->prepare('UPDATE orders SET supplier_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $supplierId, $id);
        $stmt->execute();

        if ($supplierId !== null) {
            notify_user($supplierId, 'طلب جديد موجَّه إليك',
                (string) $order['order_code'] . ' — ' . (string) $order['service_name'],
                'info', 'supplier-dashboard.php?tab=orders');
        }

        admin_audit('order.supplier_assigned', 'orders', $id, (string) $order['order_code']);
        admin_flash('success', $supplierId !== null ? 'تم إسناد الطلب للمورد.' : 'تم إلغاء الإسناد.');

    } elseif ($action === 'provider_send') {
        admin_require('providers.manage');
        try {
            $service = fetch_one($conn,
                'SELECT provider_id, provider_service_id FROM store_services WHERE id = ?',
                'i', (int) $order['service_id']);

            if ($service === null || (int) $service['provider_id'] === 0) {
                throw new RuntimeException('هذه الخدمة غير مربوطة بمزود.');
            }
            if (trim((string) $order['target_url']) === '') {
                throw new RuntimeException('لا يوجد رابط هدف على هذا الطلب.');
            }

            $provider = provider_get((int) $service['provider_id']);
            if ($provider === null) {
                throw new RuntimeException('المزود غير متاح.');
            }

            $result = provider_add_order($provider, (string) $service['provider_service_id'],
                (string) $order['target_url'], (int) $order['quantity']);
            $remote = (string) ($result['order'] ?? '');
            if ($remote === '') {
                throw new RuntimeException((string) ($result['error'] ?? 'لم يرجع المزود رقم طلب.'));
            }

            $stmt = $conn->prepare(
                "UPDATE orders SET provider_order_id = ?, provider_status = 'Pending', order_status = 'in_progress'
                  WHERE id = ?"
            );
            $stmt->bind_param('si', $remote, $id);
            $stmt->execute();

            admin_audit('order.sent_to_provider', 'orders', $id, (string) $order['order_code'], $remote);
            admin_flash('success', 'تم إرسال الطلب للمزود. رقم الطلب لديه: ' . $remote);
        } catch (Throwable $e) {
            admin_flash('error', $e->getMessage());
        }

    } elseif ($action === 'provider_sync') {
        admin_require('providers.manage');
        try {
            if ((int) $order['provider_id'] === 0 || trim((string) $order['provider_order_id']) === '') {
                throw new RuntimeException('لا يوجد طلب لدى مزود لمزامنته.');
            }

            $provider = provider_get((int) $order['provider_id']);
            if ($provider === null) {
                throw new RuntimeException('المزود غير متاح.');
            }

            $result    = provider_order_status($provider, (string) $order['provider_order_id']);
            $remoteRaw = (string) ($result['status'] ?? 'Unknown');
            $quantity  = max(1, (int) $order['quantity']);
            $remaining = isset($result['remains'])
                ? max(0, (int) $result['remains'])
                : max(0, (int) $order['remaining_quantity']);
            $done    = max(0, $quantity - $remaining);
            $percent = round($done / $quantity * 100, 2);

            $map = [
                'Completed'   => 'completed',
                'In progress' => 'in_progress',
                'Processing'  => 'in_progress',
                'Pending'     => 'in_progress',
                'Partial'     => 'in_progress',
                'Canceled'    => 'cancelled',
            ];
            $mapped = $map[$remoteRaw] ?? (string) $order['order_status'];
            // Even a provider cannot push the order through a transition the
            // workflow forbids.
            $next = admin_order_can_move((string) $order['order_status'], $mapped)
                ? $mapped
                : (string) $order['order_status'];

            $stmt = $conn->prepare(
                'UPDATE orders
                    SET provider_status = ?, remaining_quantity = ?, completed_quantity = ?,
                        progress_percent = ?, order_status = ?, last_provider_sync_at = NOW()
                  WHERE id = ?'
            );
            $stmt->bind_param('siidsi', $remoteRaw, $remaining, $done, $percent, $next, $id);
            $stmt->execute();

            admin_audit('order.provider_synced', 'orders', $id, (string) $order['order_code'], $remoteRaw);
            admin_flash('success', 'تمت مزامنة حالة الطلب من المزود.');
        } catch (Throwable $e) {
            admin_flash('error', $e->getMessage());
        }
    }

    admin_redirect('order-view.php?id=' . $id);
}

$page_title_admin = 'طلب ' . (string) $order['order_code'];

$timeline = fetch_all(
    $conn,
    'SELECT h.from_status, h.to_status, h.actor_type, h.note, h.customer_visible, h.created_at,
            a.username AS admin_name
       FROM order_status_history h
       LEFT JOIN admin_users a ON a.id = h.actor_id AND h.actor_type = "admin"
      WHERE h.order_id = ? ORDER BY h.id',
    'i',
    $id
);

$options  = fetch_all($conn, 'SELECT option_label, value_label, price_delta FROM order_options WHERE order_id = ?', 'i', $id);
$payments = fetch_all($conn, 'SELECT method_key, amount, currency, status, reference, created_at FROM payments WHERE order_id = ? ORDER BY id DESC', 'i', $id);

$suppliers = admin_can('orders.manage')
    ? fetch_all($conn, "SELECT id, name FROM platform_users WHERE account_type='supplier' AND status='active' ORDER BY name LIMIT 100")
    : [];

$nextStatuses = admin_order_transitions()[(string) $order['order_status']] ?? [];
$currency     = (string) ($order['currency'] ?: $order['svc_currency'] ?: 'EGP');

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= e((string) $order['service_name']) ?>
      <span class="text-muted" style="font-size:12px;" dir="ltr"><?= e((string) $order['order_code']) ?></span>
    </div>
    <?= admin_badge(admin_order_status_label((string) $order['order_status']),
          admin_order_status_tone((string) $order['order_status'])) ?>
  </div>

  <div class="detail-grid">
    <table class="kv">
      <tr><td>الكمية</td><td><?= (int) $order['quantity'] ?></td></tr>
      <tr><td>سعر الوحدة</td><td class="money"><?= e(number_format((float) $order['unit_price'], 2)) ?></td></tr>
      <?php if ((float) $order['options_total'] > 0): ?>
        <tr><td>إضافات</td><td class="money"><?= e(number_format((float) $order['options_total'], 2)) ?></td></tr>
      <?php endif; ?>
      <?php if ((float) $order['mediation_fee'] > 0): ?>
        <tr><td>رسوم الوساطة</td><td class="money"><?= e(number_format((float) $order['mediation_fee'], 2)) ?></td></tr>
      <?php endif; ?>
      <tr><td>الإجمالي</td><td class="money text-gold"><?= e(number_format((float) $order['total_price'], 2)) ?> <?= e($currency) ?></td></tr>
      <tr><td>حالة الدفع</td><td><?= e((string) $order['payment_status']) ?></td></tr>
      <tr><td>مصدر الطلب</td><td><?= e((string) $order['order_source']) ?></td></tr>
      <tr><td>تاريخ الطلب</td><td dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $order['created_at']))) ?></td></tr>
    </table>

    <table class="kv">
      <tr><td>الحساب</td><td><?= e((string) ($order['account_name'] ?? 'ضيف')) ?></td></tr>
      <tr><td>الاسم</td><td><?= e((string) ($order['customer_name'] ?: '—')) ?></td></tr>
      <tr><td>الهاتف</td><td dir="ltr"><?= e((string) ($order['customer_phone'] ?: '—')) ?></td></tr>
      <tr><td>البريد</td><td dir="ltr"><?= e((string) ($order['customer_email'] ?: $order['account_email'] ?: '—')) ?></td></tr>
      <?php if (!empty($order['target_url'])): ?>
        <tr><td>الهدف</td><td dir="ltr" style="word-break:break-all;"><?= e((string) $order['target_url']) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($options as $option): ?>
        <tr><td><?= e((string) $option['option_label']) ?></td><td><?= e((string) $option['value_label']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!empty($order['customer_notes'])): ?>
        <tr><td>ملاحظات العميل</td><td style="white-space:normal;"><?= nl2br(e((string) $order['customer_notes'])) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php if (admin_can('orders.manage')): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">تغيير الحالة</div></div>

    <?php if ($nextStatuses): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_status">
        <div class="filter-bar">
          <div class="form-group">
            <label class="form-label">الحالة التالية</label>
            <select class="form-select" name="to_status">
              <?php foreach ($nextStatuses as $next): ?>
                <option value="<?= e($next) ?>"><?= e(admin_order_status_label($next)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">ملاحظة</label>
            <input class="form-input" type="text" name="note" placeholder="تظهر للعميل ما لم تحدّدها كداخلية">
          </div>
          <label class="form-check">
            <input type="checkbox" name="internal" value="1">
            <span>ملاحظة داخلية</span>
          </label>
          <button class="btn btn-primary" type="submit">تنفيذ</button>
        </div>
      </form>
      <p class="text-muted" style="font-size:12px;">
        الحالة الحالية «<?= e(admin_order_status_label((string) $order['order_status'])) ?>» —
        الانتقالات المسموحة منها هي المعروضة فقط.
      </p>
    <?php else: ?>
      <p class="text-muted">هذه الحالة نهائية ولا يوجد انتقال منها.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (admin_can('payments.confirm') && $order['payment_status'] !== 'paid'): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">تأكيد الدفع</div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="confirm_payment">
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">المبلغ المستلم</label>
          <input class="form-input" type="number" step="0.01" min="0.01" dir="ltr"
                 name="amount" value="<?= e(number_format((float) $order['total_price'], 2, '.', '')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">الطريقة</label>
          <input class="form-input" type="text" name="method" placeholder="vodafone / instapay / bank">
        </div>
        <div class="form-group">
          <label class="form-label">المرجع</label>
          <input class="form-input" type="text" name="reference" dir="ltr" placeholder="رقم العملية">
        </div>
      </div>
      <button class="btn btn-primary" type="submit">تأكيد استلام المبلغ</button>
    </form>
    <div class="confidential-note">
      لا تؤكّد الدفع إلا بعد التحقق من وصول المبلغ فعليًا للحساب المستلم.
    </div>
  </div>
<?php endif; ?>

<?php if ($suppliers): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">إسناد لمورد</div></div>
    <form method="post" class="filter-bar">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign_supplier">
      <div class="form-group" style="flex:1;">
        <label class="form-label">المورد</label>
        <select class="form-select" name="supplier_id">
          <option value="0">— بدون —</option>
          <?php foreach ($suppliers as $supplier): ?>
            <option value="<?= (int) $supplier['id'] ?>"
                    <?= (int) $order['supplier_id'] === (int) $supplier['id'] ? 'selected' : '' ?>>
              <?= e((string) $supplier['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-secondary" type="submit">حفظ</button>
    </form>
    <div class="confidential-note">
      المورد يرى الخدمة والكمية والهدف فقط. اسم العميل وهاتفه وبريده لا تصل إليه.
    </div>
  </div>
<?php endif; ?>

<?php if (admin_can('providers.manage') && (int) $order['provider_id'] > 0): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">مزود الخدمة</div></div>
    <table class="kv">
      <tr><td>رقم الطلب لدى المزود</td><td dir="ltr"><?= e((string) ($order['provider_order_id'] ?: '—')) ?></td></tr>
      <tr><td>حالته لدى المزود</td><td dir="ltr"><?= e((string) ($order['provider_status'] ?: '—')) ?></td></tr>
      <tr><td>التقدّم</td><td><?= e(number_format((float) $order['progress_percent'], 2)) ?>%</td></tr>
      <tr><td>آخر مزامنة</td><td dir="ltr"><?= $order['last_provider_sync_at'] ? e((string) $order['last_provider_sync_at']) : '—' ?></td></tr>
    </table>
    <div class="flex-gap mt-8">
      <?php if (trim((string) $order['provider_order_id']) === ''): ?>
        <?= admin_action_button('provider_send', ['id' => $id], 'إرسال للمزود', 'btn btn-primary btn-sm',
              'إرسال هذا الطلب إلى المزود الآن؟') ?>
      <?php else: ?>
        <?= admin_action_button('provider_sync', ['id' => $id], 'مزامنة الحالة') ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-header"><div class="panel-title">مسار الطلب</div></div>
  <?php if ($timeline): ?>
    <ul class="timeline">
      <?php foreach ($timeline as $step): ?>
        <li>
          <strong><?= e(admin_order_status_label((string) $step['to_status'])) ?></strong>
          <?php if ($step['from_status']): ?>
            <span class="text-muted" style="font-size:12px;">
              من <?= e(admin_order_status_label((string) $step['from_status'])) ?>
            </span>
          <?php endif; ?>
          <?= (int) $step['customer_visible'] === 0 ? admin_badge('داخلية', 'inactive') : '' ?>
          <?php if (!empty($step['note'])): ?>
            <div class="text-muted" style="font-size:12px;"><?= e((string) $step['note']) ?></div>
          <?php endif; ?>
          <time dir="ltr">
            <?= e(date('Y-m-d H:i', strtotime((string) $step['created_at']))) ?>
            <?= !empty($step['admin_name']) ? ' · ' . e((string) $step['admin_name']) : '' ?>
          </time>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="empty-state"><p>لا توجد حركات مسجّلة على هذا الطلب.</p></div>
  <?php endif; ?>
</div>

<?php if ($payments): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">المدفوعات</div></div>
    <table class="admin-table">
      <thead><tr><th>الطريقة</th><th>المبلغ</th><th>الحالة</th><th>المرجع</th><th>التاريخ</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $payment): ?>
        <tr>
          <td><?= e((string) $payment['method_key']) ?></td>
          <td class="money"><?= e(number_format((float) $payment['amount'], 2)) ?> <?= e((string) $payment['currency']) ?></td>
          <td><?= e((string) $payment['status']) ?></td>
          <td dir="ltr" style="font-size:12px;"><?= e((string) ($payment['reference'] ?? '—')) ?></td>
          <td class="text-muted" style="font-size:12px;" dir="ltr"><?= e(date('Y-m-d H:i', strtotime((string) $payment['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if (admin_can('orders.manage')): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">ملاحظات داخلية</div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_notes">
      <div class="form-group">
        <textarea class="form-input form-textarea" name="admin_notes" rows="4"><?= e((string) ($order['admin_notes'] ?? '')) ?></textarea>
      </div>
      <button class="btn btn-secondary" type="submit">حفظ</button>
    </form>
  </div>
<?php endif; ?>

<p><a class="btn btn-secondary" href="orders.php">← كل الطلبات</a></p>

<?php admin_layout_end(); ?>
