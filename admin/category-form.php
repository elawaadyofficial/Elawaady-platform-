<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('catalog.manage');
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/upload_handler.php';

$type = $_GET['type'] ?? 'main'; // main | sub
$id   = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$main_cats = fetch_all($conn, "SELECT id, name FROM store_categories ORDER BY sort_order ASC, name ASC");

$row = [
    'name'=>'','name_en'=>'','slug'=>'','icon'=>'','description'=>'',
    'sort_order'=>0,'is_active'=>1,'category_id'=>0,
    'icon_image'=>'','category_image'=>'','cat_banner_image'=>'',
];

if ($is_edit) {
    if ($type === 'sub') {
        $db_row = fetch_one($conn, "SELECT * FROM store_subcategories WHERE id=?", "i", $id);
    } else {
        $db_row = fetch_one($conn, "SELECT * FROM store_categories WHERE id=?", "i", $id);
    }
    if (!$db_row) { header('Location: categories.php'); exit; }
    $row = array_merge($row, $db_row);
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $name        = trim($_POST['name'] ?? '');
    $name_en     = trim($_POST['name_en'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $icon        = trim($_POST['icon'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $category_id = (int)($_POST['category_id'] ?? 0);

    if ($name === '') $errors[] = 'اسم القسم بالعربية مطلوب.';
    if ($type === 'sub' && $category_id <= 0) $errors[] = 'يجب اختيار القسم الرئيسي.';

    if (!$slug && $name) {
        $slug = strtolower(preg_replace('/\s+/', '-', $name_en ?: $name));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    }

    if (!$errors) {
        if ($type === 'sub') {
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE store_subcategories SET name=?,name_en=?,slug=?,icon=?,description=?,sort_order=?,is_active=?,category_id=? WHERE id=?");
                $stmt->bind_param("sssssiiii", $name,$name_en,$slug,$icon,$description,$sort_order,$is_active,$category_id,$id);
            } else {
                $stmt = $conn->prepare("INSERT INTO store_subcategories (name,name_en,slug,icon,description,sort_order,is_active,category_id) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssiii", $name,$name_en,$slug,$icon,$description,$sort_order,$is_active,$category_id);
            }
        } else {
            if ($is_edit) {
                $stmt = $conn->prepare("UPDATE store_categories SET name=?,name_en=?,slug=?,icon=?,description=?,sort_order=?,is_active=? WHERE id=?");
                $stmt->bind_param("sssssiii", $name,$name_en,$slug,$icon,$description,$sort_order,$is_active,$id);
            } else {
                $stmt = $conn->prepare("INSERT INTO store_categories (name,name_en,slug,icon,description,sort_order,is_active) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssii", $name,$name_en,$slug,$icon,$description,$sort_order,$is_active);
            }
        }

        if ($stmt->execute()) {
            $save_id = $is_edit ? $id : $conn->insert_id;
            $tbl = $type === 'sub' ? 'store_subcategories' : 'store_categories';

            // Handle image uploads
            $img_upd = []; $img_vals = [];
            $upload_map = [
                'icon_image_file'     => [__DIR__ . '/../uploads/categories/icons/',   'icon_image'],
                'category_image_file' => [__DIR__ . '/../uploads/categories/images/',  'category_image'],
                'banner_image_file'   => [__DIR__ . '/../uploads/categories/banners/', 'cat_banner_image'],
            ];
            foreach ($upload_map as $field => [$dir, $col]) {
                try {
                    $path = upload_image($field, $dir);
                    if ($path !== null) { $img_upd[] = "$col=?"; $img_vals[] = $path; }
                } catch (Exception $e) { $errors[] = $e->getMessage(); }
            }
            if ($img_upd && !$errors) {
                $img_vals[] = $save_id;
                $types = str_repeat('s', count($img_vals) - 1) . 'i';
                $su = $conn->prepare("UPDATE $tbl SET " . implode(',', $img_upd) . " WHERE id=?");
                $su->bind_param($types, ...$img_vals);
                $su->execute();
            }
            if (!$errors) {
                header('Location: categories.php'); exit;
            }
        } else {
            $errors[] = 'خطأ في قاعدة البيانات: ' . $stmt->error;
        }
        $row = array_merge($row, compact('name','name_en','slug','icon','description','sort_order','is_active','category_id'));
    } else {
        $row = array_merge($row, compact('name','name_en','slug','icon','description','sort_order','is_active','category_id'));
    }
}

// Reload fresh from DB to get image paths
if ($is_edit) {
    $tbl = $type === 'sub' ? 'store_subcategories' : 'store_categories';
    $fresh = fetch_one($conn, "SELECT icon_image, category_image, cat_banner_image FROM $tbl WHERE id=?", "i", $id);
    if ($fresh) $row = array_merge($row, $fresh);
}

$page_title_admin = ($is_edit ? 'تعديل' : 'إضافة') . ' ' . ($type==='sub' ? 'قسم فرعي' : 'قسم رئيسي');
include __DIR__ . '/layout.php';

function cat_img_field(string $label, string $file_key, string $current_path, string $preview_id): void { ?>
  <div class="form-group img-upload-group">
    <label class="form-label"><?= $label ?></label>
    <?php if ($current_path): ?>
      <div class="img-preview-wrap" id="<?= $preview_id ?>-wrap">
        <img src="<?= img_src($current_path) ?>" alt="<?= $label ?>" id="<?= $preview_id ?>-cur">
      </div>
    <?php else: ?>
      <div class="img-preview-placeholder" id="<?= $preview_id ?>-placeholder">
        <span class="upload-icon">🖼️</span>
        <span>لا توجد صورة</span>
      </div>
    <?php endif; ?>
    <div style="margin-top:6px;">
      <img id="<?= $preview_id ?>-new" style="display:none; width:100%; max-height:140px; object-fit:cover; border-radius:10px; border:1px solid var(--line); margin-bottom:6px;" alt="">
    </div>
    <div class="file-input-wrap">
      <label class="file-input-btn" for="<?= $file_key ?>">
        📁 <?= $current_path ? 'استبدال الصورة' : 'رفع صورة' ?>
      </label>
      <input type="file" name="<?= $file_key ?>" id="<?= $file_key ?>"
             class="hidden-file" accept="image/jpeg,image/png,image/webp,image/svg+xml"
             onchange="previewSingle(this,'<?= $preview_id ?>-new')">
    </div>
    <div class="upload-hint">PNG، JPG، WEBP، SVG — بحد أقصى 5 ميجابايت</div>
  </div>
<?php }
?>

<div style="margin-bottom:16px;">
  <a href="categories.php" class="btn btn-secondary btn-sm">← العودة للأقسام</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title"><?= $page_title_admin ?></div>
  </div>

  <form method="POST" enctype="multipart/form-data">
<?= csrf_field() ?>
    <div class="form-grid">

      <div class="form-group">
        <label class="form-label">الاسم بالعربية <span class="req">*</span></label>
        <input type="text" name="name" class="form-input"
               value="<?= e($row['name']) ?>" required placeholder="مثال: خدمات إنستجرام">
      </div>

      <div class="form-group">
        <label class="form-label">الاسم بالإنجليزية</label>
        <input type="text" name="name_en" class="form-input" dir="ltr"
               value="<?= e($row['name_en'] ?? '') ?>" placeholder="Instagram Services">
      </div>

      <div class="form-group">
        <label class="form-label">Slug (رابط)</label>
        <input type="text" name="slug" class="form-input" dir="ltr"
               value="<?= e($row['slug'] ?? '') ?>" placeholder="instagram-services">
      </div>

      <div class="form-group">
        <label class="form-label">الأيقونة النصية (إيموجي)</label>
        <input type="text" name="icon" class="form-input"
               value="<?= e($row['icon'] ?? '') ?>" placeholder="📸">
      </div>

      <?php if ($type === 'sub'): ?>
      <div class="form-group">
        <label class="form-label">القسم الرئيسي <span class="req">*</span></label>
        <select name="category_id" class="form-select" required>
          <option value="">-- اختر القسم --</option>
          <?php foreach ($main_cats as $mc): ?>
            <option value="<?= $mc['id'] ?>"
              <?= ($row['category_id'] ?? 0) == $mc['id'] ? 'selected' : '' ?>>
              <?= e($mc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">الترتيب</label>
        <input type="number" name="sort_order" class="form-input"
               value="<?= (int)($row['sort_order'] ?? 0) ?>" min="0">
      </div>

      <div class="form-group form-full">
        <label class="form-label">الوصف</label>
        <textarea name="description" class="form-textarea"><?= e($row['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" name="is_active" value="1"
                 <?= ($row['is_active'] ?? 1) ? 'checked' : '' ?>>
          نشط (مرئي للزوار)
        </label>
      </div>

    </div>

    <!-- Image Uploads -->
    <div style="margin-top:20px; border-top:1px solid var(--line); padding-top:20px;">
      <div class="panel-title" style="margin-bottom:16px;">🖼️ صور القسم</div>
      <div class="form-grid">
        <?php
        cat_img_field('أيقونة القسم',    'icon_image_file',     $row['icon_image'] ?? '',     'cat-icon');
        cat_img_field('صورة القسم',      'category_image_file', $row['category_image'] ?? '',  'cat-img');
        cat_img_field('بانر القسم',      'banner_image_file',   $row['cat_banner_image'] ?? '', 'cat-banner');
        ?>
      </div>
    </div>

    <div style="display:flex; gap:10px; margin-top:24px; flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">
        <?= $is_edit ? '💾 حفظ التعديلات' : '➕ إضافة القسم' ?>
      </button>
      <a href="categories.php" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<script>
function previewSingle(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    const file = input.files[0];
    if (!file) { preview.style.display = 'none'; return; }
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(file);
}
</script>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
