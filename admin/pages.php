<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('cms.manage');

$page_title_admin = 'الصفحات والسياسات';

/**
 * Static pages and versioned policies.
 *
 * A policy is not an editable blob. Publishing a change creates a new version
 * row and leaves the old one intact, because policy_acceptances points at the
 * exact text a person agreed to. Editing the text in place would rewrite what
 * they consented to after the fact, which is precisely what an acceptance
 * record exists to prevent.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action  = (string) ($_POST['action'] ?? '');
    $admin   = admin_user();
    $adminId = (int) $admin['id'];

    if ($action === 'save_page') {
        $slug    = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) ($_POST['slug'] ?? ''))));
        $title   = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        $content = (string) ($_POST['content'] ?? '');
        $seoTitle = mb_substr(trim((string) ($_POST['seo_title'] ?? '')), 0, 255);
        $seoDesc  = mb_substr(trim((string) ($_POST['seo_description'] ?? '')), 0, 1000);
        $published = empty($_POST['is_published']) ? 0 : 1;
        $inFooter  = empty($_POST['show_in_footer']) ? 0 : 1;
        $sort      = (int) ($_POST['sort_order'] ?? 0);

        if ($slug === '' || $title === '') {
            admin_flash('error', 'العنوان والمعرّف مطلوبان.');
            admin_redirect('pages.php');
        }

        $stmt = $conn->prepare(
            'INSERT INTO static_pages (slug, title, content, seo_title, seo_description, is_published, show_in_footer, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title), content = VALUES(content),
                seo_title = VALUES(seo_title), seo_description = VALUES(seo_description),
                is_published = VALUES(is_published), show_in_footer = VALUES(show_in_footer),
                sort_order = VALUES(sort_order)'
        );
        $stmt->bind_param('sssssiii', $slug, $title, $content, $seoTitle, $seoDesc, $published, $inFooter, $sort);
        $stmt->execute();

        admin_audit('cms.page_saved', 'static_pages', null, $slug);
        admin_flash('success', 'تم حفظ الصفحة.');
        admin_redirect('pages.php?slug=' . urlencode($slug));

    } elseif ($action === 'publish_policy') {
        $policyId = admin_id('policy_id');
        $version  = mb_substr(trim((string) ($_POST['version'] ?? '')), 0, 40);
        $content  = (string) ($_POST['content'] ?? '');

        $policy = fetch_one($conn, 'SELECT id, policy_key, title FROM policies WHERE id = ?', 'i', $policyId);
        if ($policy === null || $version === '' || trim($content) === '') {
            admin_flash('error', 'رقم الإصدار والنص مطلوبان.');
            admin_redirect('pages.php?tab=policies');
        }

        $exists = fetch_one($conn,
            'SELECT id FROM policy_versions WHERE policy_id = ? AND version = ?', 'is', $policyId, $version);
        if ($exists !== null) {
            admin_flash('error', 'هذا الإصدار موجود بالفعل. استخدم رقمًا جديدًا — الإصدارات لا تُعدَّل.');
            admin_redirect('pages.php?tab=policies');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO policy_versions (policy_id, version, content, published_at, created_by)
                 VALUES (?, ?, ?, NOW(), ?)'
            );
            $stmt->bind_param('issi', $policyId, $version, $content, $adminId);
            $stmt->execute();
            $versionId = (int) $conn->insert_id;

            $upd = $conn->prepare('UPDATE policies SET current_version_id = ? WHERE id = ?');
            $upd->bind_param('ii', $versionId, $policyId);
            $upd->execute();

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            admin_flash('error', 'تعذّر نشر الإصدار.');
            admin_redirect('pages.php?tab=policies');
        }

        admin_audit('cms.policy_published', 'policies', $policyId,
            (string) $policy['title'] . ' v' . $version);
        admin_flash('success', 'تم نشر الإصدار ' . $version . '. الإصدارات السابقة محفوظة كما هي.');
        admin_redirect('pages.php?tab=policies');
    }

    admin_redirect('pages.php');
}

$tab = (string) ($_GET['tab'] ?? 'pages');
if (!in_array($tab, ['pages', 'policies'], true)) {
    $tab = 'pages';
}

$pages    = fetch_all($conn, 'SELECT * FROM static_pages ORDER BY sort_order, slug');
$editSlug = (string) ($_GET['slug'] ?? '');
$editing  = $editSlug !== ''
    ? fetch_one($conn, 'SELECT * FROM static_pages WHERE slug = ?', 's', $editSlug)
    : null;

$policies = fetch_all(
    $conn,
    'SELECT p.*, v.version AS current_version, v.published_at,
            (SELECT COUNT(*) FROM policy_versions pv WHERE pv.policy_id = p.id) AS version_count,
            (SELECT COUNT(*) FROM policy_acceptances pa
               JOIN policy_versions pv2 ON pv2.id = pa.policy_version_id
              WHERE pv2.policy_id = p.id) AS acceptance_count
       FROM policies p
       LEFT JOIN policy_versions v ON v.id = p.current_version_id
      ORDER BY p.id'
);

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="filter-bar">
  <a class="btn <?= $tab === 'pages' ? 'btn-primary' : 'btn-secondary' ?>" href="pages.php?tab=pages">الصفحات</a>
  <a class="btn <?= $tab === 'policies' ? 'btn-primary' : 'btn-secondary' ?>" href="pages.php?tab=policies">السياسات</a>
</div>

<?php if ($tab === 'pages'): ?>

  <div class="panel">
    <div class="panel-header"><div class="panel-title">الصفحات (<?= count($pages) ?>)</div></div>
    <?php if ($pages): ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>المعرّف</th><th>العنوان</th><th>منشورة</th><th>في الفوتر</th><th>الترتيب</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($pages as $page): ?>
            <tr>
              <td dir="ltr" style="font-size:12px;"><?= e((string) $page['slug']) ?></td>
              <td><?= e((string) $page['title']) ?></td>
              <td><?= (int) $page['is_published'] === 1 ? admin_badge('منشورة', 'active') : admin_badge('مسودة', 'inactive') ?></td>
              <td><?= (int) $page['show_in_footer'] === 1 ? 'نعم' : 'لا' ?></td>
              <td><?= (int) $page['sort_order'] ?></td>
              <td>
                <a class="btn btn-secondary btn-sm" href="pages.php?slug=<?= e((string) $page['slug']) ?>">تعديل</a>
                <a class="btn btn-secondary btn-sm" href="../page.php?slug=<?= e((string) $page['slug']) ?>" target="_blank" rel="noopener">عرض</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon">📄</div><p>لا توجد صفحات بعد.</p></div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-header">
      <div class="panel-title"><?= $editing !== null ? 'تعديل: ' . e((string) $editing['title']) : 'صفحة جديدة' ?></div>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_page">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">المعرّف <span class="req">*</span></label>
          <input class="form-input" type="text" name="slug" dir="ltr" required
                 value="<?= e((string) ($editing['slug'] ?? '')) ?>" <?= $editing !== null ? 'readonly' : '' ?>
                 placeholder="about">
        </div>
        <div class="form-group">
          <label class="form-label">العنوان <span class="req">*</span></label>
          <input class="form-input" type="text" name="title" required value="<?= e((string) ($editing['title'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">عنوان SEO</label>
          <input class="form-input" type="text" name="seo_title" value="<?= e((string) ($editing['seo_title'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">الترتيب</label>
          <input class="form-input sort-input" type="number" dir="ltr" name="sort_order"
                 value="<?= (int) ($editing['sort_order'] ?? 0) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">وصف SEO</label>
        <textarea class="form-input" name="seo_description" rows="2"><?= e((string) ($editing['seo_description'] ?? '')) ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">المحتوى</label>
        <textarea class="form-input form-textarea" name="content" rows="14"><?= e((string) ($editing['content'] ?? '')) ?></textarea>
        <small class="upload-hint">نص عادي. تُعرض الفقرات كما كُتبت.</small>
      </div>

      <label class="form-check">
        <input type="checkbox" name="is_published" value="1" <?= (int) ($editing['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
        <span>منشورة على الموقع</span>
      </label>
      <label class="form-check">
        <input type="checkbox" name="show_in_footer" value="1" <?= (int) ($editing['show_in_footer'] ?? 1) === 1 ? 'checked' : '' ?>>
        <span>تظهر في الفوتر</span>
      </label>

      <button class="btn btn-primary mt-8" type="submit">حفظ الصفحة</button>
      <?php if ($editing !== null): ?>
        <a class="btn btn-secondary" href="pages.php">صفحة جديدة</a>
      <?php endif; ?>
    </form>
  </div>

<?php else: ?>

  <div class="alert alert-info">
    الإصدار المنشور لا يُعدَّل. أي تغيير يُنشر كإصدار جديد، لأن سجلّ الموافقات يشير إلى النص
    الذي وافق عليه العميل بالضبط.
  </div>

  <?php foreach ($policies as $policy): ?>
    <?php
    $current = $policy['current_version_id'] !== null
        ? fetch_one($conn, 'SELECT content FROM policy_versions WHERE id = ?', 'i', (int) $policy['current_version_id'])
        : null;
    ?>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">
          <?= e((string) $policy['title']) ?>
          <span class="text-muted" style="font-size:12px;" dir="ltr"><?= e((string) $policy['policy_key']) ?></span>
        </div>
        <?= (int) $policy['requires_acceptance'] === 1 ? admin_badge('تتطلب موافقة', 'review') : '' ?>
      </div>

      <table class="kv">
        <tr><td>الإصدار الحالي</td><td dir="ltr"><?= e((string) ($policy['current_version'] ?? '—')) ?></td></tr>
        <tr><td>تاريخ النشر</td><td dir="ltr"><?= $policy['published_at'] ? e(date('Y-m-d H:i', strtotime((string) $policy['published_at']))) : '—' ?></td></tr>
        <tr><td>عدد الإصدارات</td><td><?= (int) $policy['version_count'] ?></td></tr>
        <tr><td>عدد الموافقات المسجّلة</td><td><?= (int) $policy['acceptance_count'] ?></td></tr>
      </table>

      <form method="post" class="mt-16">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="publish_policy">
        <input type="hidden" name="policy_id" value="<?= (int) $policy['id'] ?>">

        <div class="form-group">
          <label class="form-label">رقم الإصدار الجديد <span class="req">*</span></label>
          <input class="form-input" type="text" name="version" dir="ltr" required
                 placeholder="<?= e($policy['current_version'] ? 'أكبر من ' . (string) $policy['current_version'] : '1.0') ?>"
                 style="max-width:180px;">
        </div>

        <div class="form-group">
          <label class="form-label">النص</label>
          <textarea class="form-input form-textarea" name="content" rows="10"><?= e((string) ($current['content'] ?? '')) ?></textarea>
        </div>

        <button class="btn btn-primary" type="submit">نشر إصدار جديد</button>
      </form>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?php admin_layout_end(); ?>
