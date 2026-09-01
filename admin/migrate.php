<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('settings.manage');

$page_title_admin = 'حالة قاعدة البيانات';

/**
 * This page used to run ALTER TABLE from the browser and print a default
 * administrator password. It no longer changes anything.
 *
 * Schema is applied from the terminal by php migrate.php, which records what
 * it ran and refuses anything destructive. A page that can reshape the
 * database is one stolen session away from reshaping it badly, and a schema
 * change made through a browser leaves no record of who made it or when. This
 * page now only reports what has been applied.
 */

$applied = [];
try {
    $applied = fetch_all($conn,
        'SELECT filename, applied_at, duration_ms FROM schema_migrations ORDER BY filename');
} catch (mysqli_sql_exception $e) {
    $applied = [];
}

$appliedNames = array_column($applied, 'filename');

$onDisk = array_map('basename', glob(__DIR__ . '/../migrations/*.sql') ?: []);
sort($onDisk);
$pending = array_values(array_diff($onDisk, $appliedNames));

include __DIR__ . '/layout.php';
?>

<div class="panel">
  <div class="panel-header"><div class="panel-title">الترحيلات المطبَّقة (<?= count($applied) ?>)</div></div>

  <?php if ($applied): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>الملف</th><th>تاريخ التطبيق</th><th>المدة</th></tr></thead>
        <tbody>
        <?php foreach ($applied as $row): ?>
          <tr>
            <td dir="ltr" style="font-size:12px;"><?= e((string) $row['filename']) ?></td>
            <td dir="ltr" class="text-muted" style="font-size:12px;">
              <?= e(date('Y-m-d H:i', strtotime((string) $row['applied_at']))) ?>
            </td>
            <td dir="ltr" class="text-muted" style="font-size:12px;"><?= (int) $row['duration_ms'] ?> ms</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">🗄️</div>
      <p>لم يُسجَّل أي ترحيل بعد.</p>
    </div>
  <?php endif; ?>
</div>

<?php if ($pending): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">ترحيلات بانتظار التطبيق (<?= count($pending) ?>)</div></div>
    <ul style="padding-inline-start:18px; line-height:2;">
      <?php foreach ($pending as $file): ?>
        <li dir="ltr" style="font-size:12px;"><?= e($file) ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="alert alert-info">
      شغّل على الخادم: <code dir="ltr">php migrate.php</code>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert-success">قاعدة البيانات محدَّثة — لا توجد ترحيلات معلّقة.</div>
<?php endif; ?>

<div class="confidential-note">
  هذه الصفحة للقراءة فقط. تعديل بنية قاعدة البيانات يتم من الطرفية عبر
  <code dir="ltr">php migrate.php</code>، الذي يرفض أي أمر حذف أو إفراغ ويسجّل كل ما طبّقه.
  لإنشاء حساب إدارة استخدم <code dir="ltr">php tools/create_admin.php</code>.
</div>

<?php admin_layout_end(); ?>
