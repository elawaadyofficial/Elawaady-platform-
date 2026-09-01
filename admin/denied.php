<?php
/** Shown when a signed-in staff member reaches a page they may not use. */
$page_title_admin = 'صلاحية غير كافية';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../settings_helper.php';
include __DIR__ . '/layout.php';
?>
<div class="panel">
  <div class="panel-header"><div class="panel-title">صلاحية غير كافية</div></div>
  <div class="empty-state">
    <div class="empty-icon">⛔</div>
    <p>لا تملك الصلاحية اللازمة لفتح هذه الصفحة.</p>
    <p class="text-muted">إن كنت تحتاجها، اطلب من المدير العام إضافتها إلى دورك.</p>
    <a href="index.php" class="btn btn-secondary">العودة للوحة التحكم</a>
  </div>
</div>
    </div>
  </div>
</div>
</body>
</html>
