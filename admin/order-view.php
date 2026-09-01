<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../provider_client.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: orders.php'); exit; }

$order = fetch_one($conn,
    "SELECT o.*, s.whatsapp_number, s.price AS svc_price, s.currency AS svc_currency
     FROM orders o
     LEFT JOIN store_services s ON s.id = o.service_id
     WHERE o.id = ?",
    "i", $id);
if (!$order) { header('Location: orders.php'); exit; }

$errors   = [];
$success  = '';
$currency = $order['svc_currency'] ?: 'ج.م';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = trim($_POST['act'] ?? '');

    if ($act === 'save_notes') {
        $admin_notes = mb_substr(trim($_POST['admin_notes'] ?? ''), 0, 5000);
        $stmt = $conn->prepare("UPDATE orders SET admin_notes=? WHERE id=?");
        $stmt->bind_param("si", $admin_notes, $id);
        $stmt->execute();
        $success = 'تم حفظ الملاحظات.';
        $order['admin_notes'] = $admin_notes;
    }

    elseif ($act === 'set_status') {
        $allowed = ['new','waiting_approval','waiting_payment','in_progress','progressing','completed','rejected','on_hold','cancelled','dispute'];
        $new_status = trim($_POST['order_status'] ?? '');
        if (in_array($new_status, $allowed, true)) {
            $stmt = $conn->prepare("UPDATE orders SET order_status=? WHERE id=?");
            $stmt->bind_param("si", $new_status, $id);
            $stmt->execute();
            $success = 'تم تحديث حالة الطلب.';
            $order['order_status'] = $new_status;
        }
    }

    elseif ($act === 'set_progress') {
        $done=max(0,(int)($_POST['completed_quantity']??0)); $total=max(1,(int)$order['quantity']); $done=min($done,$total); $remaining=$total-$done; $pct=round(($done/$total)*100,2);
        $st=$conn->prepare("UPDATE orders SET completed_quantity=?,remaining_quantity=?,progress_percent=?,order_status=IF(? >= quantity,'completed','progressing') WHERE id=?");
        $st->bind_param('iidii',$done,$remaining,$pct,$done,$id); $st->execute(); $success='تم تحديث تقدم الطلب.';
    }


    elseif ($act === 'send_provider') {
        if (empty($order['provider_id']) || empty($order['target_url'])) { $errors[]='لا يوجد مزود API أو رابط هدف لهذا الأوردر.'; }
        elseif (!empty($order['provider_order_id'])) { $errors[]='تم إرسال الأوردر للمزود بالفعل.'; }
        else {
            $svc=fetch_one($conn,'SELECT provider_service_id FROM store_services WHERE id=?','i',$order['service_id']); $pv=provider_get((int)$order['provider_id']);
            if(!$pv||empty($svc['provider_service_id'])) $errors[]='إعدادات المزود أو Service ID ناقصة.';
            else { try { $r=provider_add_order($pv,(string)$svc['provider_service_id'],$order['target_url'],(int)$order['quantity']); $po=(string)($r['order']??''); if(!$po) throw new RuntimeException($r['error']??'لم يرجع المزود رقم أوردر'); $st=$conn->prepare("UPDATE orders SET provider_order_id=?,provider_status='Pending',order_status='in_progress' WHERE id=?");$st->bind_param('si',$po,$id);$st->execute();$success='تم إرسال الأوردر للسيرفر. Provider Order: '.$po; } catch(Throwable $e){$errors[]=$e->getMessage();} }
        }
    }
    elseif ($act === 'sync_provider') {
        if(empty($order['provider_id'])||empty($order['provider_order_id'])) $errors[]='الأوردر غير مربوط بأوردر على السيرفر.';
        else { try { $pv=provider_get((int)$order['provider_id']); $r=provider_order_status($pv,(string)$order['provider_order_id']); $ps=(string)($r['status']??'Unknown'); $rem=isset($r['remains'])?max(0,(int)$r['remains']):max(0,(int)$order['remaining_quantity']); $total=max(1,(int)$order['quantity']); $done=max(0,$total-$rem); $pct=round($done/$total*100,2); $map=['Completed'=>'completed','In progress'=>'progressing','Processing'=>'in_progress','Pending'=>'in_progress','Canceled'=>'cancelled','Partial'=>'progressing']; $os=$map[$ps]??$order['order_status']; $st=$conn->prepare('UPDATE orders SET provider_status=?,remaining_quantity=?,completed_quantity=?,progress_percent=?,order_status=?,last_provider_sync_at=NOW() WHERE id=?');$st->bind_param('siidsi',$ps,$rem,$done,$pct,$os,$id);$st->execute();$success='تمت مزامنة حالة الأوردر من السيرفر.'; } catch(Throwable $e){$errors[]=$e->getMessage();} }
    }
    elseif ($act === 'set_payment') {
        $allowed_pay = ['pending','paid','failed','refunded'];
        $new_pay = trim($_POST['payment_status'] ?? '');
        if (in_array($new_pay, $allowed_pay, true)) {
            $stmt = $conn->prepare("UPDATE orders SET payment_status=? WHERE id=?");
            $stmt->bind_param("si", $new_pay, $id);
            $stmt->execute();
            $success = 'تم تحديث حالة الدفع.';
            $order['payment_status'] = $new_pay;
        }
    }

    elseif ($act === 'convert_mediation') {
        $stmt = $conn->prepare("UPDATE orders SET order_type='mediation', mediation_enabled=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = 'تم تحويل الطلب إلى وساطة.';
        $order['order_type']        = 'mediation';
        $order['mediation_enabled'] = 1;
    }

    if ($success) {
        header("Location: order-view.php?id=$id&saved=1");
        exit;
    }
}

if (isset($_GET['saved'])) $success = 'تم الحفظ بنجاح.';

$page_title_admin = 'تفاصيل الطلب: ' . $order['order_code'];

$status_labels = [
    'new'               => ['جديد',            'badge-active'],
    'waiting_approval'  => ['انتظار موافقة',    'badge-review'],
    'waiting_payment'   => ['انتظار الدفع',     'badge-review'],
    'in_progress'       => ['قيد التنفيذ',      'badge-active'],
    'progressing'       => ['جاري التقدم',      'badge-active'],
    'completed'         => ['اكتمال الأوردر',    'badge-active'],
    'rejected'          => ['مرفوض',            'badge-inactive'],
    'on_hold'           => ['معلق',             'badge-review'],
    'cancelled'         => ['ملغي',             'badge-inactive'],
    'dispute'           => ['نزاع',             'badge-hidden'],
];
$pay_labels = [
    'pending'  => ['pending',  'badge-review'],
    'paid'     => ['مدفوع',   'badge-active'],
    'failed'   => ['فاشل',    'badge-inactive'],
    'refunded' => ['مسترد',   'badge-hidden'],
];

include __DIR__ . '/layout.php';
?>

<?php if ($errors): ?><div class="alert alert-error" style="margin-bottom:16px"><?= e(implode(' — ',$errors)) ?></div><?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom:16px;"><?= e($success) ?></div>
<?php endif; ?>

<div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
  <a href="orders.php" class="btn btn-secondary btn-sm">← الطلبات</a>
  <h2 style="font-size:17px; font-weight:900; margin:0; font-family:monospace; color:var(--gold);">
    <?= e($order['order_code']) ?>
  </h2>
  <?php [$sl, $sc] = $status_labels[$order['order_status']] ?? ['—','badge-inactive']; ?>
  <span class="badge <?= $sc ?>"><?= $sl ?></span>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

  <!-- Left: Order details -->
  <div style="display:flex; flex-direction:column; gap:16px;">

    <div class="panel">
      <div class="panel-header"><div class="panel-title">📋 تفاصيل الطلب</div></div>
      <table class="admin-table">
        <tr><td class="text-muted">الخدمة</td>
          <td><a href="../service.php?id=<?= (int)$order['service_id'] ?>" target="_blank"
                 style="color:var(--cyan);"><?= e($order['service_name']) ?></a></td></tr>
        <tr><td class="text-muted">الكمية</td><td><?= (int)$order['quantity'] ?></td></tr>
        <tr><td class="text-muted">الرابط</td><td dir="ltr"><?= $order['target_url'] ? '<a href="'.e($order['target_url']).'" target="_blank">'.e($order['target_url']).'</a>' : '—' ?></td></tr>
        <tr><td class="text-muted">نوع الهدف</td><td><?= e($order['target_type'] ?: '—') ?></td></tr>
        <tr><td class="text-muted">الجودة</td><td><?= e($order['quality_option'] ?: '—') ?></td></tr>
        <tr><td class="text-muted">الضمان</td><td><?= e($order['warranty_option'] ?: '—') ?></td></tr>
        <tr><td class="text-muted">سعر الوحدة</td>
          <td><?= $order['unit_price'] > 0 ? number_format($order['unit_price'],2).' '.$currency : '—' ?></td></tr>
        <tr><td class="text-muted">الإجمالي</td>
          <td style="font-weight:700; color:var(--gold);">
            <?= $order['total_price'] > 0 ? number_format($order['total_price'],2).' '.$currency : 'حسب الطلب' ?>
          </td></tr>
        <tr><td class="text-muted">نوع الطلب</td><td><?= e($order['order_type']) ?></td></tr>
        <tr><td class="text-muted">وساطة</td>
          <td><?= $order['mediation_enabled'] ? '<span class="badge badge-active">نعم</span>' : 'لا' ?></td></tr>
        <tr><td class="text-muted">تاريخ الإنشاء</td>
          <td class="text-muted"><?= e($order['created_at']) ?></td></tr>
        <tr><td class="text-muted">آخر تحديث</td>
          <td class="text-muted"><?= e($order['updated_at']) ?></td></tr>
      </table>
    </div>

    <div class="panel">
      <div class="panel-header"><div class="panel-title">👤 بيانات العميل</div></div>
      <table class="admin-table">
        <tr><td class="text-muted">الاسم</td><td><?= e($order['customer_name'] ?: '—') ?></td></tr>
        <tr><td class="text-muted">الهاتف</td>
          <td><?= $order['customer_phone'] ? '<span style="color:var(--cyan);">'.e($order['customer_phone']).'</span>' : '—' ?></td></tr>
        <tr><td class="text-muted">البريد</td><td><?= e($order['customer_email'] ?: '—') ?></td></tr>
        <tr><td class="text-muted">ملاحظات العميل</td>
          <td style="white-space:pre-wrap; font-size:13px;"><?= e($order['customer_notes'] ?: '—') ?></td></tr>
      </table>
    </div>

    <?php if ($order['whatsapp_message']): ?>
    <div class="panel">
      <div class="panel-header"><div class="panel-title">💬 رسالة واتساب</div></div>
      <pre style="font-size:12px; color:var(--muted); white-space:pre-wrap; line-height:1.8;
                  background:var(--bg); padding:12px; border-radius:10px; font-family:monospace;">
<?= e($order['whatsapp_message']) ?></pre>
    </div>
    <?php endif; ?>

    <?php if ($order['supplier_name'] || $order['supplier_contact']): ?>
    <div class="panel">
      <div class="panel-header"><div class="panel-title">🏭 بيانات المورد</div></div>
      <table class="admin-table">
        <tr><td class="text-muted">المورد</td><td><?= e($order['supplier_name'] ?: '—') ?></td></tr>
        <tr><td class="text-muted">التواصل</td><td><?= e($order['supplier_contact'] ?: '—') ?></td></tr>
      </table>
    </div>
    <?php endif; ?>

  </div>

  <!-- Right: Actions -->
  <div style="display:flex; flex-direction:column; gap:16px;">

    <!-- Quick Actions -->
    <div class="panel">
      <div class="panel-header"><div class="panel-title">⚡ إجراءات سريعة</div></div>
      <div style="display:flex; flex-direction:column; gap:8px; padding:4px 0;">

        <?php if ($order['whatsapp_number']): ?>
          <?php
          $wa_num = preg_replace('/\D/','',$order['whatsapp_number']);
          $wa_txt = urlencode("مرحبًا، بخصوص الطلب: " . $order['order_code'] . " — " . $order['service_name']);
          ?>
          <a href="https://wa.me/<?= $wa_num ?>?text=<?= $wa_txt ?>" target="_blank"
             class="btn btn-secondary" style="background:#25d366; border-color:#25d366; color:#fff;">
            💬 فتح واتساب
          </a>
        <?php endif; ?>

        <form method="post" style="margin:0;" onsubmit="return confirm('تأكيد تغيير الحالة؟');">
          <input type="hidden" name="act" value="set_status">
          <div style="display:flex; gap:6px;">
            <select name="order_status" class="form-select" style="flex:1;">
              <?php foreach ($status_labels as $v => [$l]): ?>
                <option value="<?= $v ?>" <?= $order['order_status']===$v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">تحديث الحالة</button>
          </div>
        </form>

        <form method="post" style="margin:0;" onsubmit="return confirm('تأكيد تغيير حالة الدفع؟');">
          <input type="hidden" name="act" value="set_payment">
          <div style="display:flex; gap:6px;">
            <select name="payment_status" class="form-select" style="flex:1;">
              <?php foreach ($pay_labels as $v => [$l]): ?>
                <option value="<?= $v ?>" <?= $order['payment_status']===$v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">تحديث الدفع</button>
          </div>
        </form>


        <?php if(!empty($order['provider_id'])): ?>
          <?php if(empty($order['provider_order_id'])): ?><form method="post"><input type="hidden" name="act" value="send_provider"><button class="btn btn-primary" style="width:100%" onclick="return confirm('إرسال الأوردر إلى سيرفر المزود؟')">🚀 إرسال للسيرفر</button></form><?php else: ?><form method="post"><input type="hidden" name="act" value="sync_provider"><button class="btn btn-secondary" style="width:100%">🔄 مزامنة من السيرفر</button></form><div class="text-muted" dir="ltr">Provider #<?= e($order['provider_order_id']) ?> · <?= e($order['provider_status']??'') ?></div><?php endif; ?>
        <?php endif; ?>
        <?php if (!$order['mediation_enabled']): ?>
        <form method="post" style="margin:0;" onsubmit="return confirm('تحويل الطلب إلى وساطة؟');">
          <input type="hidden" name="act" value="convert_mediation">
          <button type="submit" class="btn btn-secondary" style="width:100%;">🤝 تحويل إلى وساطة</button>
        </form>
        <?php endif; ?>

      </div>
    </div>


    <div class="panel">
      <div class="panel-header"><div class="panel-title">📈 عداد تقدم الأوردر</div></div>
      <?php $done=(int)($order['completed_quantity']??0); $remaining=max(0,(int)$order['quantity']-$done); $pct=(float)($order['progress_percent']??(($order['quantity']>0)?$done/$order['quantity']*100:0)); ?>
      <div style="padding:8px 0"><div style="height:12px;background:var(--bg);border-radius:999px;overflow:hidden"><div style="height:100%;width:<?= max(0,min(100,$pct)) ?>%;background:linear-gradient(90deg,var(--cyan),var(--gold))"></div></div><div style="display:flex;justify-content:space-between;margin-top:8px;font-size:13px"><span>تم: <?= number_format($done) ?></span><span>متبقي: <?= number_format($remaining) ?></span><strong><?= number_format($pct,1) ?>%</strong></div></div>
      <form method="post" style="display:flex;gap:8px"><input type="hidden" name="act" value="set_progress"><input type="number" min="0" max="<?= (int)$order['quantity'] ?>" name="completed_quantity" value="<?= $done ?>" class="form-input"><button class="btn btn-primary">تحديث التقدم</button></form>
    </div>

    <!-- Admin Notes -->
    <div class="panel">
      <div class="panel-header"><div class="panel-title">📝 ملاحظات الإدارة (سرية)</div></div>
      <form method="post" style="padding:4px 0;">
        <input type="hidden" name="act" value="save_notes">
        <textarea name="admin_notes" class="form-textarea" rows="5"
                  placeholder="ملاحظات داخلية — لا تُعرض للعميل..."><?= e($order['admin_notes']) ?></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:10px; width:100%;">حفظ الملاحظات</button>
      </form>
    </div>

    <!-- Payment & Status summary -->
    <div class="panel">
      <div class="panel-header"><div class="panel-title">📊 الحالة الحالية</div></div>
      <table class="admin-table">
        <?php [$sl2, $sc2] = $status_labels[$order['order_status']] ?? ['—','badge-inactive']; ?>
        <?php [$pl2, $pc2] = $pay_labels[$order['payment_status']] ?? ['—','badge-inactive']; ?>
        <tr><td class="text-muted">حالة الطلب</td>
          <td><span class="badge <?= $sc2 ?>"><?= $sl2 ?></span></td></tr>
        <tr><td class="text-muted">حالة الدفع</td>
          <td><span class="badge <?= $pc2 ?>"><?= $pl2 ?></span></td></tr>
      </table>
    </div>

  </div>
</div>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
