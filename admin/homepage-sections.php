<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('cms.manage');

$page_title_admin = 'أقسام الصفحة الرئيسية';

/**
 * The homepage is these rows, in this order.
 *
 * index.php walks homepage_sections and renders each active row through the
 * renderer its section_type names. Reordering here reorders the store;
 * unticking "ظاهر" removes a band from the page. Nothing about the homepage's
 * composition is written in PHP any more, which is the point: adding a section
 * is a row, not a deployment.
 */

$sectionTypes = [
    'hero'           => 'الواجهة الرئيسية',
    'categories'     => 'شبكة الأقسام',
    'banners'        => 'صف بنرات',
    'services'       => 'صف خدمات',
    'category_bands' => 'أقسام الخدمات الكاملة',
    'mediation'      => 'بلوك الوساطة',
    'reviews'        => 'آراء العملاء',
    'faq'            => 'الأسئلة الشائعة',
    'payment'        => 'طرق الدفع والثقة',
    'html'           => 'نص حر',
];

$layouts = [
    'rail'      => 'صف أفقي قابل للتمرير',
    'product'   => 'كروت منتجات عريضة',
    'keys'      => 'شبكة 3 أعمدة',
    'duo'       => 'شبكة عمودين',
    'grid'      => 'شبكة عادية',
    'hero'      => 'هيرو',
    'feature'   => 'بلوك مميز',
    'accordion' => 'قائمة منسدلة',
    'strip'     => 'شريط',
    'mixed'     => 'إيقاع متنوع',
];

// What a "services" section pulls. Each maps to a real query in sections.php.
$sourceFilters = [
    ''            => '— حسب الترتيب —',
    'best_seller' => 'الأكثر مبيعًا',
    'most_used'   => 'الأكثر استخدامًا',
    'newest'      => 'الأحدث',
    'offers'      => 'عليها عرض',
    'featured'    => 'مختارة يدويًا',
    'home_top'    => 'بنرات أعلى الصفحة',
    'home_mid'    => 'بنرات وسط الصفحة',
    'home_bottom' => 'بنرات أسفل الصفحة',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string) ($_POST['action'] ?? '');
    $admin  = admin_user();

    if ($action === 'save_all') {
        $ids = array_map('intval', (array) ($_POST['id'] ?? []));

        $stmt = $conn->prepare(
            'UPDATE homepage_sections
                SET title = ?, subtitle = ?, section_type = ?, layout = ?, source_filter = ?,
                    category_id = ?, item_limit = ?, link_url = ?, link_label = ?,
                    is_active = ?, sort_order = ?
              WHERE id = ?'
        );

        foreach ($ids as $id) {
            if ($id <= 0) { continue; }

            $title       = mb_substr(trim((string) ($_POST['title'][$id] ?? '')), 0, 190);
            $subtitle    = mb_substr(trim((string) ($_POST['subtitle'][$id] ?? '')), 0, 500);
            $type        = (string) ($_POST['section_type'][$id] ?? 'services');
            $layout      = (string) ($_POST['layout'][$id] ?? 'rail');
            $filter      = (string) ($_POST['source_filter'][$id] ?? '');
            $categoryId  = max(0, (int) ($_POST['category_id'][$id] ?? 0)) ?: null;
            $limit       = max(0, min(60, (int) ($_POST['item_limit'][$id] ?? 8)));
            $linkUrl     = mb_substr(trim((string) ($_POST['link_url'][$id] ?? '')), 0, 500);
            $linkLabel   = mb_substr(trim((string) ($_POST['link_label'][$id] ?? '')), 0, 120);
            $isActive    = !empty($_POST['is_active'][$id]) ? 1 : 0;
            $sortOrder   = (int) ($_POST['sort_order'][$id] ?? 0);

            // A value outside the known set is not stored; the row keeps a
            // renderer that exists rather than one the storefront cannot draw.
            if (!isset($sectionTypes[$type]))  { $type   = 'services'; }
            if (!isset($layouts[$layout]))     { $layout = 'rail'; }
            if (!isset($sourceFilters[$filter])) { $filter = ''; }

            $stmt->bind_param(
                'sssssiissiii',
                $title, $subtitle, $type, $layout, $filter,
                $categoryId, $limit, $linkUrl, $linkLabel,
                $isActive, $sortOrder, $id
            );
            $stmt->execute();
        }

        admin_audit('cms.homepage_sections_saved', 'homepage_sections', null, count($ids) . ' قسم');
        admin_flash('success', 'تم حفظ ترتيب الصفحة الرئيسية. افتح المتجر لتراه.');

    } elseif ($action === 'add') {
        $key   = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($_POST['section_key'] ?? ''))));
        $title = mb_substr(trim((string) ($_POST['new_title'] ?? '')), 0, 190);

        if ($key === '' || $title === '') {
            admin_flash('error', 'اسم القسم ومفتاحه مطلوبان.');
            admin_redirect('homepage-sections.php');
        }

        $exists = fetch_one($conn, 'SELECT id FROM homepage_sections WHERE section_key = ?', 's', $key);
        if ($exists !== null) {
            admin_flash('error', 'هذا المفتاح مستخدم بالفعل.');
            admin_redirect('homepage-sections.php');
        }

        $maxRow = fetch_one($conn, 'SELECT COALESCE(MAX(sort_order), 0) AS m FROM homepage_sections');
        $sort   = (int) ($maxRow['m'] ?? 0) + 10;

        $stmt = $conn->prepare(
            "INSERT INTO homepage_sections (section_key, title, section_type, layout, item_limit, sort_order, is_active)
             VALUES (?, ?, 'services', 'rail', 8, ?, 0)"
        );
        $stmt->bind_param('ssi', $key, $title, $sort);
        $stmt->execute();

        admin_audit('cms.homepage_section_added', 'homepage_sections', (int) $conn->insert_id, $title);
        admin_flash('success', 'تمت إضافة القسم. اضبط إعداداته ثم فعّله.');

    } elseif ($action === 'delete') {
        // Removing a section is removing a band from the storefront, so it is
        // deliberately a separate, confirmed action.
        $id      = admin_id('section_id');
        $section = fetch_one($conn, 'SELECT id, title, section_key FROM homepage_sections WHERE id = ?', 'i', $id);

        if ($section === null) {
            admin_flash('error', 'القسم غير موجود.');
            admin_redirect('homepage-sections.php');
        }

        $stmt = $conn->prepare('DELETE FROM homepage_sections WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        admin_audit('cms.homepage_section_deleted', 'homepage_sections', $id, (string) $section['title']);
        admin_flash('success', 'تم حذف القسم من الصفحة الرئيسية.');
    }

    admin_redirect('homepage-sections.php');
}

$sections   = fetch_all($conn, 'SELECT * FROM homepage_sections ORDER BY sort_order, id');
$categories = fetch_all($conn, 'SELECT id, name FROM store_categories WHERE is_active = 1 ORDER BY sort_order, name');

include __DIR__ . '/layout.php';
?>

<?= admin_flash_render() ?>

<div class="alert alert-info">
  ترتيب الصفوف هنا هو ترتيب الصفحة الرئيسية حرفيًا. الرقم الأصغر يظهر أولًا،
  وإلغاء «ظاهر» يخفي القسم من المتجر فورًا.
</div>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_all">

  <?php foreach ($sections as $section): $id = (int) $section['id']; ?>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">
          <?= e((string) $section['title']) ?>
          <span class="text-muted" style="font-size:12px;" dir="ltr"><?= e((string) $section['section_key']) ?></span>
        </div>
        <?= (int) $section['is_active'] === 1 ? admin_badge('ظاهر', 'active') : admin_badge('مخفي', 'inactive') ?>
      </div>

      <input type="hidden" name="id[]" value="<?= $id ?>">

      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label">العنوان</label>
          <input class="form-input" type="text" name="title[<?= $id ?>]" value="<?= e((string) $section['title']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">النوع</label>
          <select class="form-select" name="section_type[<?= $id ?>]">
            <?php foreach ($sectionTypes as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $section['section_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">الشكل</label>
          <select class="form-select" name="layout[<?= $id ?>]">
            <?php foreach ($layouts as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $section['layout'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">المصدر</label>
          <select class="form-select" name="source_filter[<?= $id ?>]">
            <?php foreach ($sourceFilters as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= (string) ($section['source_filter'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">قسم محدد</label>
          <select class="form-select" name="category_id[<?= $id ?>]">
            <option value="0">— كل الأقسام —</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id'] ?>" <?= (int) ($section['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                <?= e((string) $category['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">عدد الكروت</label>
          <input class="form-input" type="number" min="0" max="60" dir="ltr"
                 name="item_limit[<?= $id ?>]" value="<?= (int) $section['item_limit'] ?>">
        </div>

        <div class="form-group">
          <label class="form-label">رابط «عرض الكل»</label>
          <input class="form-input" type="text" dir="ltr" name="link_url[<?= $id ?>]"
                 value="<?= e((string) ($section['link_url'] ?? '')) ?>" placeholder="categories.php">
        </div>
        <div class="form-group">
          <label class="form-label">نص الرابط</label>
          <input class="form-input" type="text" name="link_label[<?= $id ?>]"
                 value="<?= e((string) ($section['link_label'] ?? '')) ?>" placeholder="عرض الكل">
        </div>
        <div class="form-group">
          <label class="form-label">الترتيب</label>
          <input class="form-input sort-input" type="number" dir="ltr"
                 name="sort_order[<?= $id ?>]" value="<?= (int) $section['sort_order'] ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">الوصف تحت العنوان</label>
        <input class="form-input" type="text" name="subtitle[<?= $id ?>]"
               value="<?= e((string) ($section['subtitle'] ?? '')) ?>">
      </div>

      <div class="flex-between mt-8">
        <label class="form-check">
          <input type="checkbox" name="is_active[<?= $id ?>]" value="1" <?= (int) $section['is_active'] === 1 ? 'checked' : '' ?>>
          <span>ظاهر على المتجر</span>
        </label>
      </div>
    </div>
  <?php endforeach; ?>

  <button class="btn btn-primary" type="submit">حفظ كل الأقسام</button>
</form>

<div class="panel mt-24">
  <div class="panel-header"><div class="panel-title">حذف قسم</div></div>
  <div class="flex-gap" style="flex-wrap:wrap;">
    <?php foreach ($sections as $section): ?>
      <?= admin_action_button('delete', ['section_id' => $section['id']],
            'حذف: ' . (string) $section['title'], 'btn btn-danger btn-sm',
            'حذف «' . (string) $section['title'] . '» من الصفحة الرئيسية؟') ?>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><div class="panel-title">إضافة قسم جديد</div></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">المفتاح <span class="req">*</span></label>
        <input class="form-input" type="text" name="section_key" dir="ltr" placeholder="winter_offers" required>
        <small class="upload-hint">حروف إنجليزية صغيرة وأرقام وشرطة سفلية فقط.</small>
      </div>
      <div class="form-group">
        <label class="form-label">العنوان <span class="req">*</span></label>
        <input class="form-input" type="text" name="new_title" placeholder="عروض الشتاء" required>
      </div>
    </div>
    <button class="btn btn-secondary" type="submit">إضافة</button>
  </form>
</div>

<?php admin_layout_end(); ?>
