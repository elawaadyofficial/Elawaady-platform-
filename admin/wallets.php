<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('wallet.view');

$page_title_admin = 'المحافظ';

/**
 * Balances are never edited here.
 *
 * An adjustment posts a ledger entry through wallet_post(), which locks the
 * wallet, appends an immutable row carrying the balance that followed it, and
 * updates the cached total. A correction to a mistake is another entry in the
 * opposite direction — the history stays intact and stays auditable.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    admin_require('wallet.manage');

    $action = (string) ($_POST['action'] ?? '');
    $userId = admin_id('user_id');
    $admin  = admin_user();

    $account = $userId > 0
        ? fetch_one($conn, 'SELECT id, name, account_type FROM platform_users WHERE id = ?', 'i', $userId)
        : null;

    if ($account === null) {
        admin_flash('error', 'الحساب غير موجود.');
        admin_redirect('wallets.php');
    }

    if ($action === 'adjust') {
        $amount    = round((float) ($_POST['amount'] ?? 0), 2);
        $direction = (string) ($_POST['direction'] ?? 'credit');
        $note      = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);

        if (!in_array($direction, ['credit', 'debit'], true)) {
            admin_flash('error', 'نوع الحركة غير صحيح.');
            admin_redirect('wallets.php');
        }
        if ($amount <= 0) {
            admin_flash('error', 'المبلغ يجب أن يكون أكبر من صفر.');
            admin_redirect('wallets.php');
        }
        if ($note === '') {
            // An unexplained movement of someone's money is not acceptable in
            // an audit, so the reason is required rather than optional.
            admin_flash('error', 'اكتب سبب التسوية — كل حركة على رصيد عميل يجب أن يكون لها سبب مسجّل.');
            admin_redirect('wallets.php');
        }

        try {
            $balance = wallet_post(
                $userId, $direction, $amount,
                $direction === 'credit' ? 'topup' : 'adjustment',
                null, $note, 'admin', (int) $admin['id']
            );
            admin_audit('wallet.adjusted', 'platform_users', $userId,
                (string) $account['name'] . ' — ' . $direction . ' ' . number_format($amount, 2), $note);
            admin_flash('success', 'تم تسجيل الحركة. الرصيد الجديد: ' . number_format($balance, 2));
        } catch (Throwable $e) {
            admin_flash('error', $e->getMessage());
        }

    } elseif ($action === 'freeze' || $action === 'unfreeze') {
        $frozen = $action === 'freeze' ? 1 : 0;
        wallet_for($userId);
        $stmt = $conn->prepare('UPDATE wallets SET is_frozen = ? WHERE user_id = ?');
        $stmt->bind_param('ii', $frozen, $userId);
        $stmt->execute();

        admin_audit('wallet.' . $action, 'platform_users', $userId, (string) $account['name']);
        admin_flash('success', $frozen === 1 ? 'تم تجميد المحفظة.' : 'تم رفع التجميد.');

    } elseif ($action === 'reconcile') {
        $result = wallet_reconcile($userId);
        admin_audit('wallet.reconciled', 'platform_users', $userId, (string) $account['name'],
            'ledger ' . $result['ledger'] . ' cached ' . $result['cached']);
        admin_flash(
            $result['repaired'] ? 'error' : 'success',
            $result['repaired']
                ? 'الرصيد المخزّن كان ' . number_format($result['cached'], 2)
                  . ' والدفتر يقول ' . number_format($result['ledger'], 2) . ' — تم تصحيحه من الدفتر.'
                : 'الرصيد مطابق للدفتر.'
        );
    }

    admin_redirect('wallets.php' . ($userId > 0 ? '?user_id=' . $userId : ''));
}

$focusId = max(0, (int) ($_GET['user_id'] ?? 0));
$search  = trim((string) ($_GET['q'] ?? ''));

$where  = ['1 = 1'];
$types  = '';
$params = [];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $like     = '%' . $search . '%';
    $types   .= 'ss';
    $params[] = $like; $params[] = $like;
}
if ($focusId > 0) {
    $where[]  = 'u.id = ?';
    $types   .= 'i';
    $params[] = $focusId;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRow = fetch_one($conn,
    "SELECT COUNT(*) AS n FROM wallets w JOIN platform_users u ON u.id = w.user_id $whereSql",
    $types, ...$params);
$paging = admin_paginate((int) ($totalRow['n'] ?? 0), 25);

$wallets = fetch_all(
    $conn,
    "SELECT w.id, w.user_id, w.balance, w.held_balance, w.currency, w.is_frozen,
            u.name, u.email, u.account_type
       FROM wallets w
       JOIN platform_users u ON u.id = w.user_id
       $whereSql
      ORDER BY w.balance DESC, w.id DESC
      LIMIT {$paging['per_page']} OFFSET {$paging['offset']}",
    $types,
    ...$params
);

$ledger = $focusId > 0 ? wallet_transactions($focusId, 40) : [];

$totalsRow = fetch_one($conn, 'SELECT COALESCE(SUM(balance), 0) AS total, COALESCE(SUM(held_balance), 0) AS held FROM wallets');

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="stat-grid">
  <div class="stat-card">
    <span class="stat-num"><?= e(number_format((float) ($totalsRow['total'] ?? 0), 2)) ?></span>
    <div class="stat-label">💰 إجمالي أرصدة العملاء</div>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= e(number_format((float) ($totalsRow['held'] ?? 0), 2)) ?></span>
    <div class="stat-label">🔒 محجوز في وساطة</div>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= (int) ($totalRow['n'] ?? 0) ?></span>
    <div class="stat-label">👛 عدد المحافظ</div>
  </div>
</div>

<form class="filter-bar" method="get">
  <div class="form-group">
    <label class="form-label">بحث</label>
    <input class="form-input" type="search" name="q" value="<?= e($search) ?>" placeholder="اسم أو بريد">
  </div>
  <button class="btn btn-secondary" type="submit">بحث</button>
  <?php if ($search !== '' || $focusId > 0): ?>
    <a class="btn btn-secondary" href="wallets.php">إلغاء</a>
  <?php endif; ?>
</form>

<div class="panel">
  <div class="panel-header"><div class="panel-title">المحافظ</div></div>

  <?php if ($wallets): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>الحساب</th><th>النوع</th><th>الرصيد</th><th>محجوز</th><th>الحالة</th><th>إجراءات</th></tr>
        </thead>
        <tbody>
        <?php foreach ($wallets as $wallet): ?>
          <tr>
            <td>
              <a href="wallets.php?user_id=<?= (int) $wallet['user_id'] ?>"><?= e((string) $wallet['name']) ?></a>
              <div class="text-muted" style="font-size:11px;" dir="ltr"><?= e((string) $wallet['email']) ?></div>
            </td>
            <td><?= $wallet['account_type'] === 'supplier' ? 'مورد' : 'مستخدم' ?></td>
            <td class="money text-gold"><?= e(number_format((float) $wallet['balance'], 2)) ?> <?= e((string) $wallet['currency']) ?></td>
            <td class="money"><?= e(number_format((float) $wallet['held_balance'], 2)) ?></td>
            <td><?= (int) $wallet['is_frozen'] === 1 ? admin_badge('مجمّدة', 'hidden') : admin_badge('نشطة', 'active') ?></td>
            <td>
              <?php if (admin_can('wallet.manage')): ?>
                <div class="flex-gap">
                  <?= admin_action_button('reconcile', ['user_id' => $wallet['user_id']], 'مطابقة') ?>
                  <?php if ((int) $wallet['is_frozen'] === 1): ?>
                    <?= admin_action_button('unfreeze', ['user_id' => $wallet['user_id']], 'رفع التجميد') ?>
                  <?php else: ?>
                    <?= admin_action_button('freeze', ['user_id' => $wallet['user_id']], 'تجميد',
                          'btn btn-danger btn-sm', 'تجميد محفظة هذا الحساب؟') ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= admin_pager($paging, http_build_query(array_filter(['q' => $search]))) ?>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">👛</div><p>لا توجد محافظ مطابقة.</p></div>
  <?php endif; ?>
</div>

<?php if ($focusId > 0 && admin_can('wallet.manage')): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">تسوية يدوية</div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="adjust">
      <input type="hidden" name="user_id" value="<?= $focusId ?>">
      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">النوع</label>
          <select class="form-select" name="direction">
            <option value="credit">إضافة رصيد</option>
            <option value="debit">خصم رصيد</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">المبلغ</label>
          <input class="form-input" type="number" step="0.01" min="0.01" dir="ltr" name="amount" required>
        </div>
        <div class="form-group">
          <label class="form-label">السبب <span class="req">*</span></label>
          <input class="form-input" type="text" name="note" required placeholder="تحويل بنكي مؤكَّد #12345">
        </div>
      </div>
      <button class="btn btn-primary" type="submit">تسجيل الحركة</button>
    </form>
    <div class="confidential-note">
      لا يوجد تعديل مباشر على الرصيد. كل حركة تُسجَّل في الدفتر بسببها ومن قام بها،
      والتصحيح يكون بحركة معاكسة لا بتعديل حركة قديمة.
    </div>
  </div>
<?php endif; ?>

<?php if ($ledger): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">دفتر الحركات</div></div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>التاريخ</th><th>السبب</th><th>المبلغ</th><th>الرصيد بعدها</th><th>ملاحظة</th></tr></thead>
        <tbody>
        <?php foreach ($ledger as $row): ?>
          <tr>
            <td class="text-muted" style="font-size:12px;" dir="ltr">
              <?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?>
            </td>
            <td><?= e(wallet_reason_label((string) $row['reason'])) ?></td>
            <td class="money <?= $row['direction'] === 'credit' ? 'text-green' : 'text-red' ?>">
              <?= $row['direction'] === 'credit' ? '+' : '−' ?><?= e(number_format((float) $row['amount'], 2)) ?>
            </td>
            <td class="money"><?= e(number_format((float) $row['balance_after'], 2)) ?></td>
            <td class="text-muted" style="font-size:12px; white-space:normal; max-width:240px;">
              <?= e((string) ($row['note'] ?? '—')) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php admin_layout_end(); ?>
