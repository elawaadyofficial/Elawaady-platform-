<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('catalog.manage');
require_once __DIR__ . '/../db_connect.php';

$id      = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$errors  = [];

// ── Gallery image removal ────────────────────────────────────
if ($is_edit && isset($_GET['rm_gallery']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/upload_handler.php';
    $img_to_rm  = $_GET['rm_gallery'];
    $current    = fetch_one($conn, "SELECT gallery_images FROM store_services WHERE id=?", "i", $id);
    $gallery    = json_decode($current['gallery_images'] ?? '[]', true) ?: [];
    $new_gallery= array_values(array_filter($gallery, fn($p) => $p !== $img_to_rm));
    $gjson      = json_encode($new_gallery);
    $s          = $conn->prepare("UPDATE store_services SET gallery_images=? WHERE id=?");
    $s->bind_param("si", $gjson, $id);
    $s->execute();
    delete_upload($img_to_rm);
    header("Location: service-form.php?id=$id&saved=1"); exit;
}

$main_cats = fetch_all($conn, "SELECT id,name FROM store_categories ORDER BY sort_order,name");
$sub_cats  = fetch_all($conn, "SELECT id,name,category_id FROM store_subcategories ORDER BY category_id,sort_order,name");
$providers = fetch_all($conn, "SELECT id,name FROM service_providers WHERE is_active=1 ORDER BY name");

$row = [
    'name'=>'','name_en'=>'','slug'=>'','service_code'=>'',
    'status'=>'active','service_type'=>'internal','badge'=>'',
    'show_home'=>0,'show_offers'=>0,'show_slider'=>0,
    'category_id'=>0,'subcategory_id'=>0,
    'price'=>'','old_price'=>'','currency'=>'EGP','show_price'=>1,'ask_for_price'=>0,
    'supplier_cost'=>'','platform_commission'=>'','marketer_commission'=>'',
    'description'=>'','description_full'=>'','features'=>'','requirements'=>'',
    'execution_time'=>'','terms'=>'','refund_policy'=>'','important_note'=>'','admin_notes'=>'',
    'card_bg_color'=>'','page_bg_color'=>'','primary_color'=>'','secondary_color'=>'',
    'button_color'=>'','text_color_custom'=>'','border_color'=>'',
    'card_gradient'=>'','button_gradient'=>'','image'=>'','banner_image'=>'',
    'main_image'=>'','icon_image'=>'','gallery_images'=>'[]',
    'order_type'=>'whatsapp','order_link'=>'','whatsapp_number'=>'','telegram_bot'=>'',
    'requires_approval'=>0,'requires_advance_payment'=>0,
    'mediation_enabled'=>0,'mediation_type'=>'none','mediation_fee'=>'','mediator_commission'=>'',
    'mediator_phone'=>'','mediation_whatsapp_group'=>'','emergency_phone'=>'','show_mediation_terms'=>0,
    'supplier_name'=>'','supplier_phone'=>'','supplier_visible'=>0,'supplier_priority'=>0,
    'seo_title'=>'','seo_description'=>'','seo_keywords'=>'','noindex'=>0,'show_sitemap'=>1,
    'sort_order'=>0,'is_active'=>1,
    'buy_now_enabled'=>1,'cart_enabled'=>1,'service_tags'=>'',
    'provider_id'=>'','provider_service_id'=>'','provider_sync_enabled'=>0,'provider_base_price'=>'','provider_price_per'=>1000,'profit_percent'=>30,'price_mode'=>'manual',
    'min_quantity'=>1,'max_quantity'=>1000000,'quantity_step'=>1,'target_types'=>'account,page,group,channel,post,reel,video,live','quality_options'=>'عادي,عرب,أجانب,مصريين,خليجيين','warranty_options'=>'بدون ضمان,ضمان سنة,ضمان مدى الحياة,ضمان تعويض',
    'source_type'=>'store','payment_method'=>'auto','order_receiver'=>'system','execution_method'=>'admin_manual','post_order_contact'=>'none','require_availability_confirmation'=>0,'require_admin_approval_before_execution'=>0,'auto_start_after_payment'=>0,'allow_wallet_payment'=>1,'show_payment_gateways'=>1,'progress_tracking_enabled'=>0,'backup_supplier_id'=>'','supplier_internal_notes'=>'','supplier_can_view_order'=>0,'supplier_can_update_status'=>0,'supplier_can_upload_delivery_proof'=>1,'hide_customer_data_from_supplier'=>1,'primary_button_label'=>'اشتري الآن','secondary_button_label'=>'أضف إلى السلة','image_background_mode'=>'transparent','image_custom_background'=>'','banner_fit'=>'original','banner_custom_height'=>'',
];

if ($is_edit) {
    $db_row = fetch_one($conn, "SELECT * FROM store_services WHERE id=?", "i", $id);
    if (!$db_row) { header('Location: services.php'); exit; }
    $row = array_merge($row, $db_row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Collect all fields
    $f = [];
    $str_fields = [
        'name','name_en','slug','service_code','status','service_type','badge',
        'currency','execution_time','description','description_full','features',
        'requirements','terms','refund_policy','important_note','admin_notes',
        'card_bg_color','page_bg_color','primary_color','secondary_color',
        'button_color','text_color_custom','border_color','card_gradient','button_gradient',
        'image','banner_image','order_type','order_link','whatsapp_number','telegram_bot',
        'mediation_type','mediator_phone','mediation_whatsapp_group','emergency_phone',
        'supplier_name','supplier_phone','seo_title','seo_description','seo_keywords',
        'service_tags','provider_service_id','price_mode','target_types','quality_options','warranty_options','source_type','payment_method','order_receiver','execution_method','post_order_contact','supplier_internal_notes','primary_button_label','secondary_button_label','image_background_mode','image_custom_background','banner_fit',
    ];
    foreach ($str_fields as $k) $f[$k] = trim($_POST[$k] ?? '');

    $num_fields = ['price','old_price','supplier_cost','platform_commission','marketer_commission',
                   'mediation_fee','mediator_commission','supplier_priority','sort_order','category_id','subcategory_id',
                   'provider_id','provider_base_price','provider_price_per','profit_percent','min_quantity','max_quantity','quantity_step'];
    foreach ($num_fields as $k) $f[$k] = $_POST[$k] ?? '';

    $bool_fields = ['show_home','show_offers','show_slider','show_price','ask_for_price',
                    'requires_approval','requires_advance_payment','mediation_enabled',
                    'show_mediation_terms','supplier_visible','noindex','show_sitemap','is_active',
                    'buy_now_enabled','cart_enabled','provider_sync_enabled','require_availability_confirmation','require_admin_approval_before_execution','auto_start_after_payment','allow_wallet_payment','show_payment_gateways','progress_tracking_enabled','supplier_can_view_order','supplier_can_update_status','supplier_can_upload_delivery_proof','hide_customer_data_from_supplier'];
    foreach ($bool_fields as $k) $f[$k] = isset($_POST[$k]) ? 1 : 0;

    // Validation
    if ($f['name'] === '') $errors[] = 'اسم الخدمة بالعربية مطلوب.';
    if ((int)$f['category_id'] <= 0) $errors[] = 'يجب اختيار القسم الرئيسي.';
    if ($f['price'] !== '' && !is_numeric($f['price'])) $errors[] = 'السعر يجب أن يكون رقمًا صحيحًا.';

    if (!$f['slug'] && $f['name']) {
        $f['slug'] = strtolower(preg_replace('/\s+/', '-', $f['name_en'] ?: $f['name']));
        $f['slug'] = preg_replace('/[^a-z0-9\-]/', '', $f['slug']);
    }

    if (!$errors) {
        // Build field arrays dynamically — avoids manual bind_param type-string bugs
        $data_s = [
            'name','name_en','slug','service_code','status','service_type','badge',
            'currency','execution_time','description','description_full','features',
            'requirements','terms','refund_policy','important_note','admin_notes',
            'card_bg_color','page_bg_color','primary_color','secondary_color',
            'button_color','text_color_custom','border_color','card_gradient','button_gradient',
            'image','banner_image',
            'order_type','order_link','whatsapp_number','telegram_bot',
            'mediation_type','mediator_phone','mediation_whatsapp_group','emergency_phone',
            'supplier_name','supplier_phone',
            'seo_title','seo_description','seo_keywords',
            'service_tags','provider_service_id','price_mode','target_types','quality_options','warranty_options','source_type','payment_method','order_receiver','execution_method','post_order_contact','supplier_internal_notes','primary_button_label','secondary_button_label','image_background_mode','image_custom_background','banner_fit',
        ];
        $data_i = [
            'show_home','show_offers','show_slider','is_active',
            'show_price','ask_for_price',
            'requires_approval','requires_advance_payment',
            'mediation_enabled','show_mediation_terms',
            'supplier_visible','noindex','show_sitemap',
            'buy_now_enabled','cart_enabled','provider_sync_enabled','require_availability_confirmation','require_admin_approval_before_execution','auto_start_after_payment','allow_wallet_payment','show_payment_gateways','progress_tracking_enabled','supplier_can_view_order','supplier_can_update_status','supplier_can_upload_delivery_proof','hide_customer_data_from_supplier',
        ];
        $data_d = [
            'price','old_price','supplier_cost','platform_commission','marketer_commission',
            'mediation_fee','mediator_commission','provider_base_price','profit_percent',
        ];

        $cols = []; $types = ''; $vals = [];
        foreach ($data_s as $col) { $cols[] = $col; $types .= 's'; $vals[] = $f[$col]; }
        foreach ($data_i as $col) { $cols[] = $col; $types .= 'i'; $vals[] = (int)$f[$col]; }
        foreach ($data_d as $col) {
            $raw = $f[$col] ?? '';
            $cols[] = $col; $types .= 'd'; $vals[] = ($raw !== '' && $raw !== null) ? (float)$raw : null;
        }
        $cols[] = 'category_id';       $types .= 'i'; $vals[] = (int)$f['category_id'];
        $cols[] = 'subcategory_id';    $types .= 'i'; $vals[] = ((int)$f['subcategory_id'] ?: null);
        $cols[] = 'sort_order';        $types .= 'i'; $vals[] = (int)$f['sort_order'];
        $cols[] = 'supplier_priority'; $types .= 'i'; $vals[] = (int)($f['supplier_priority'] ?? 0);
        foreach (['provider_id','provider_price_per','min_quantity','max_quantity','quantity_step'] as $ic) { $cols[]=$ic; $types.='i'; $vals[]=(int)($f[$ic] ?? 0); }

        if ($is_edit) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            $sql = "UPDATE store_services SET $set WHERE id=?";
            $types .= 'i';
            $vals[] = $id;
        } else {
            $ph  = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO store_services (" . implode(',', $cols) . ") VALUES ($ph)";
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) { $errors[] = 'خطأ في تحضير الاستعلام: ' . $conn->error; }
        else {
            $stmt->bind_param($types, ...$vals);

            if ($stmt->execute()) {
                $new_id = $is_edit ? $id : $conn->insert_id;

                // ── Image uploads (separate UPDATE after main save) ──────
                require_once __DIR__ . '/upload_handler.php';
                $img_upd = []; $img_vals = [];
                $upload_map = [
                    'main_image_file'   => [__DIR__ . '/../uploads/services/main/',    'main_image'],
                    'icon_image_file'   => [__DIR__ . '/../uploads/services/icons/',   'icon_image'],
                    'banner_image_file' => [__DIR__ . '/../uploads/services/banners/', 'banner_image'],
                ];
                foreach ($upload_map as $field => [$dir, $col]) {
                    try {
                        $path = upload_image($field, $dir);
                        if ($path !== null) { $img_upd[] = "$col=?"; $img_vals[] = $path; }
                    } catch (Exception $e) { $errors[] = $e->getMessage(); }
                }

                // Gallery images — merge with existing
                $existing_raw = fetch_one($conn, "SELECT gallery_images FROM store_services WHERE id=?", "i", $new_id);
                $existing_gallery = json_decode($existing_raw['gallery_images'] ?? '[]', true) ?: [];
                if (!empty($_FILES['gallery']['name'][0])) {
                    foreach ($_FILES['gallery']['tmp_name'] as $gi => $gtmp) {
                        if ($_FILES['gallery']['error'][$gi] !== UPLOAD_ERR_OK) continue;
                        try {
                            $gpath = upload_image_from_array($_FILES['gallery'], $gi, __DIR__ . '/../uploads/services/gallery/');
                            if ($gpath) $existing_gallery[] = $gpath;
                        } catch (Exception $e) { $errors[] = $e->getMessage(); }
                    }
                    $img_upd[]  = "gallery_images=?";
                    $img_vals[] = json_encode(array_values($existing_gallery));
                }

                if ($img_upd && !$errors) {
                    $img_vals[] = $new_id;
                    $itypes = str_repeat('s', count($img_vals) - 1) . 'i';
                    $su = $conn->prepare("UPDATE store_services SET " . implode(',', $img_upd) . " WHERE id=?");
                    $su->bind_param($itypes, ...$img_vals);
                    $su->execute();
                }

                if (!$errors) {
                    header("Location: service-form.php?id=$new_id&saved=1"); exit;
                }
            } else {
                $errors[] = 'خطأ في قاعدة البيانات: ' . $stmt->error;
            }
        }
    }

    $row = array_merge($row, $f);
}

$page_title_admin = ($is_edit ? 'تعديل' : 'إضافة') . ' خدمة';
include __DIR__ . '/layout.php';

$saved = isset($_GET['saved']);
$SERVICE_TYPES = ['internal'=>'داخلي','supplier'=>'مورّد','mediation'=>'وساطة','digital_product'=>'منتج رقمي','subscription'=>'اشتراك','topup'=>'شحن ألعاب','special_offer'=>'عرض خاص'];
$BADGES = [''=>'بدون','new'=>'جديد','featured'=>'مميز','best_seller'=>'الأكثر مبيعًا','limited_offer'=>'عرض محدود','discount'=>'خصم','recommended'=>'موصى به'];
$STATUSES = ['active'=>'نشط','inactive'=>'غير نشط','hidden'=>'مخفي','review'=>'قيد المراجعة'];
$ORDER_TYPES = ['whatsapp'=>'واتساب','telegram'=>'تليجرام بوت','internal_chat'=>'محادثة داخلية','order_form'=>'نموذج طلب','direct_payment'=>'دفع مباشر','mediation'=>'وساطة'];
$MED_TYPES = ['none'=>'لا يوجد','sell'=>'بيع','buy'=>'شراء','ownership_transfer'=>'نقل ملكية','digital_service'=>'خدمة رقمية','accounts'=>'حسابات','ads'=>'إعلانات'];
?>

<div style="margin-bottom:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
  <a href="services.php" class="btn btn-secondary btn-sm">← العودة للخدمات</a>
  <?php if ($is_edit): ?>
    <a href="../service.php?id=<?= $id ?>" class="btn btn-secondary btn-sm" target="_blank">👁 عرض في الموقع</a>
  <?php endif; ?>
</div>

<?php if ($saved): ?>
<div class="alert alert-success">✅ تم حفظ الخدمة بنجاح.</div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="alert alert-error" style="flex-direction:column; align-items:flex-start;">
  <?php foreach ($errors as $e): ?>
    <div>⚠️ <?= e($e) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" id="svc-form" enctype="multipart/form-data">
<?= csrf_field() ?>

<!-- ── 1. BASIC INFO ─────────────────────────────────────── -->
<details class="form-section" open>
  <summary>📋 المعلومات الأساسية</summary>
  <div class="form-section-body">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">الاسم بالعربية <span class="req">*</span></label>
        <input type="text" name="name" class="form-input" value="<?= e($row['name']) ?>" required placeholder="مثال: متابعين إنستجرام حقيقيين">
      </div>
      <div class="form-group">
        <label class="form-label">الاسم بالإنجليزية</label>
        <input type="text" name="name_en" class="form-input" dir="ltr" value="<?= e($row['name_en']) ?>" placeholder="Real Instagram Followers">
      </div>
      <div class="form-group">
        <label class="form-label">Slug (رابط)</label>
        <input type="text" name="slug" class="form-input" dir="ltr" value="<?= e($row['slug']) ?>" placeholder="real-instagram-followers">
      </div>
      <div class="form-group">
        <label class="form-label">كود الخدمة الداخلي</label>
        <input type="text" name="service_code" class="form-input" dir="ltr" value="<?= e($row['service_code']) ?>" placeholder="SVC-001">
      </div>
      <div class="form-group">
        <label class="form-label">الحالة</label>
        <select name="status" class="form-select">
          <?php foreach ($STATUSES as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= $row['status']==$val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">نوع الخدمة</label>
        <select name="service_type" class="form-select">
          <?php foreach ($SERVICE_TYPES as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= $row['service_type']==$val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">الشارة / Badge</label>
        <select name="badge" class="form-select">
          <?php foreach ($BADGES as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= $row['badge']==$val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">الترتيب</label>
        <input type="number" name="sort_order" class="form-input" value="<?= (int)$row['sort_order'] ?>" min="0">
      </div>
    </div>
    <div class="form-grid" style="margin-top:12px;">
      <label class="form-check"><input type="checkbox" name="is_active" value="1" <?= $row['is_active'] ? 'checked' : '' ?>> نشط (مرئي)</label>
      <label class="form-check"><input type="checkbox" name="show_home" value="1" <?= $row['show_home'] ? 'checked' : '' ?>> ظهور في الرئيسية</label>
      <label class="form-check"><input type="checkbox" name="show_offers" value="1" <?= $row['show_offers'] ? 'checked' : '' ?>> ظهور في العروض</label>
      <label class="form-check"><input type="checkbox" name="show_slider" value="1" <?= $row['show_slider'] ? 'checked' : '' ?>> ظهور في السلايدر</label>
    </div>
  </div>
</details>

<!-- ── 2. CATEGORY ───────────────────────────────────────── -->
<details class="form-section" open>
  <summary>📁 القسم والتصنيف</summary>
  <div class="form-section-body">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">القسم الرئيسي <span class="req">*</span></label>
        <select name="category_id" class="form-select" required id="main-cat">
          <option value="">-- اختر القسم --</option>
          <?php foreach ($main_cats as $mc): ?>
            <option value="<?= $mc['id'] ?>" <?= $row['category_id']==$mc['id'] ? 'selected' : '' ?>><?= e($mc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">القسم الفرعي</label>
        <select name="subcategory_id" class="form-select" id="sub-cat">
          <option value="">-- اختر الفرعي --</option>
          <?php foreach ($sub_cats as $sc): ?>
            <option value="<?= $sc['id'] ?>"
                    data-parent="<?= $sc['category_id'] ?>"
                    <?= $row['subcategory_id']==$sc['id'] ? 'selected' : '' ?>>
              <?= e($sc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</details>

<!-- ── 3. PRICING ────────────────────────────────────────── -->
<details class="form-section">
  <summary>💰 الأسعار والتكاليف</summary>
  <div class="form-section-body">
    <div class="form-grid-3">
      <div class="form-group">
        <label class="form-label">السعر (للعميل)</label>
        <input type="number" name="price" class="form-input" step="0.01" min="0"
               value="<?= $row['price'] !== '' ? e($row['price']) : '' ?>" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">السعر القديم (شطب)</label>
        <input type="number" name="old_price" class="form-input" step="0.01" min="0"
               value="<?= $row['old_price'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">العملة</label>
        <select name="currency" class="form-select">
          <?php foreach (['EGP'=>'ج.م','USD'=>'$','SAR'=>'ر.س','AED'=>'د.إ'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($row['currency']??'EGP')==$k ? 'selected' : '' ?>><?= $k ?> (<?= $v ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">تكلفة المورّد</label>
        <input type="number" name="supplier_cost" class="form-input" step="0.01" min="0"
               value="<?= $row['supplier_cost'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">عمولة المنصة</label>
        <input type="number" name="platform_commission" class="form-input" step="0.01" min="0"
               value="<?= $row['platform_commission'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">عمولة المسوّق</label>
        <input type="number" name="marketer_commission" class="form-input" step="0.01" min="0"
               value="<?= $row['marketer_commission'] ?? '' ?>" placeholder="0.00">
      </div>
    </div>
    <div class="form-grid" style="margin-top:12px;">
      <label class="form-check"><input type="checkbox" name="show_price" value="1" <?= ($row['show_price']??1) ? 'checked' : '' ?>> إظهار السعر للعميل</label>
      <label class="form-check"><input type="checkbox" name="ask_for_price" value="1" <?= $row['ask_for_price'] ? 'checked' : '' ?>> "اسأل عن السعر" بدل السعر</label>
    </div>
  </div>
</details>


<!-- ── 3B. PROVIDER / SOCIAL AUTOMATION ───────────────────── -->
<details class="form-section" open>
  <summary>🔌 مزود API والتسعير التلقائي</summary>
  <div class="form-section-body">
    <div class="form-grid-3">
      <div class="form-group"><label class="form-label">مزود الخدمة / السيرفر</label><select name="provider_id" class="form-select"><option value="0">— بدون مزود —</option><?php foreach($providers as $pv): ?><option value="<?= (int)$pv['id'] ?>" <?= (int)$row['provider_id']===(int)$pv['id']?'selected':'' ?>><?= e($pv['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">Service ID على السيرفر</label><input name="provider_service_id" class="form-input" value="<?= e($row['provider_service_id']) ?>" dir="ltr"></div>
      <div class="form-group"><label class="form-label">طريقة التسعير</label><select name="price_mode" class="form-select"><option value="manual" <?= $row['price_mode']==='manual'?'selected':'' ?>>يدوي</option><option value="provider_auto" <?= $row['price_mode']==='provider_auto'?'selected':'' ?>>تلقائي من API + الربح</option></select></div>
      <div class="form-group"><label class="form-label">سعر المزود الأساسي</label><input type="number" step="0.000001" min="0" name="provider_base_price" class="form-input" value="<?= e($row['provider_base_price']) ?>"></div>
      <div class="form-group"><label class="form-label">السعر عند المزود لكل كمية</label><input type="number" min="1" name="provider_price_per" class="form-input" value="<?= (int)$row['provider_price_per'] ?>"><small class="text-muted">غالبًا 1000 في سيرفرات SMM.</small></div>
      <div class="form-group"><label class="form-label">نسبة الربح %</label><input type="number" step="0.01" min="0" name="profit_percent" class="form-input" value="<?= e($row['profit_percent']) ?>" placeholder="30"></div>
      <div class="form-group"><label class="form-label">أقل كمية</label><input type="number" min="1" name="min_quantity" class="form-input" value="<?= (int)$row['min_quantity'] ?>"></div>
      <div class="form-group"><label class="form-label">أقصى كمية</label><input type="number" min="1" name="max_quantity" class="form-input" value="<?= (int)$row['max_quantity'] ?>"></div>
      <div class="form-group"><label class="form-label">خطوة الكمية</label><input type="number" min="1" name="quantity_step" class="form-input" value="<?= (int)$row['quantity_step'] ?>"></div>
    </div>
    <label class="form-check" style="margin:12px 0"><input type="checkbox" name="provider_sync_enabled" value="1" <?= $row['provider_sync_enabled']?'checked':'' ?>> تفعيل مزامنة السعر والحالة من السيرفر</label>
    <div class="form-grid">
      <div class="form-group"><label class="form-label">أنواع الهدف المتاحة</label><input name="target_types" class="form-input" value="<?= e($row['target_types']) ?>"><small class="text-muted">account,page,group,channel,post,reel,video,live</small></div>
      <div class="form-group"><label class="form-label">خيارات الجودة</label><textarea name="quality_options" class="form-textarea"><?= e($row['quality_options']) ?></textarea><small class="text-muted">افصل الخيارات بفاصلة.</small></div>
      <div class="form-group"><label class="form-label">خيارات الضمان</label><textarea name="warranty_options" class="form-textarea"><?= e($row['warranty_options']) ?></textarea><small class="text-muted">مثال: بدون ضمان، ضمان سنة، ضمان مدى الحياة، ضمان تعويض.</small></div>
    </div>
  </div>
</details>

<!-- ── 4. CONTENT ─────────────────────────────────────────── -->
<details class="form-section">
  <summary>📝 المحتوى والوصف</summary>
  <div class="form-section-body" style="display:flex; flex-direction:column; gap:14px;">
    <div class="form-group">
      <label class="form-label">الوصف المختصر (يظهر في كارد الخدمة)</label>
      <textarea name="description" class="form-textarea" placeholder="وصف موجز يظهر في قوائم الخدمات..."><?= e($row['description']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">الوصف الكامل (صفحة الخدمة)</label>
      <textarea name="description_full" class="form-textarea" style="min-height:120px;" placeholder="وصف تفصيلي كامل..."><?= e($row['description_full']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">المميزات والفوائد</label>
      <textarea name="features" class="form-textarea" placeholder="كل ميزة في سطر منفصل..."><?= e($row['features']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">المتطلبات قبل التنفيذ</label>
      <textarea name="requirements" class="form-textarea" placeholder="ما يحتاج العميل تقديمه..."><?= e($row['requirements']) ?></textarea>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">مدة التنفيذ</label>
        <input type="text" name="execution_time" class="form-input" value="<?= e($row['execution_time']) ?>" placeholder="مثال: 24-48 ساعة">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">شروط الخدمة</label>
      <textarea name="terms" class="form-textarea" placeholder="شروط خاصة بهذه الخدمة..."><?= e($row['terms']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">سياسة الاسترداد</label>
      <textarea name="refund_policy" class="form-textarea" placeholder="هل يوجد استرداد؟ وكيف؟"><?= e($row['refund_policy']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">ملاحظة مهمة (للعميل)</label>
      <textarea name="important_note" class="form-textarea" placeholder="تنبيه مهم يُعرض قبل الطلب..."><?= e($row['important_note']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">ملاحظات داخلية (لا تظهر للعميل)</label>
      <textarea name="admin_notes" class="form-textarea" placeholder="ملاحظات للمدير والفريق فقط..."><?= e($row['admin_notes']) ?></textarea>
    </div>
  </div>
</details>

<!-- ── 5. DESIGN ──────────────────────────────────────────── -->
<details class="form-section">
  <summary>🎨 التصميم والألوان والصور</summary>
  <div class="form-section-body">
    <div class="form-grid">
      <?php
      $color_fields = [
        'card_bg_color'=>'لون خلفية الكارد',
        'page_bg_color'=>'لون خلفية الصفحة',
        'primary_color'=>'اللون الأساسي',
        'secondary_color'=>'اللون الثانوي',
        'button_color'=>'لون الزر',
        'text_color_custom'=>'لون النص',
        'border_color'=>'لون الحدود',
      ];
      foreach ($color_fields as $fn=>$lbl):
        $val = $row[$fn] ?? '';
      ?>
      <div class="form-group">
        <label class="form-label"><?= $lbl ?></label>
        <div class="color-row">
          <input type="color" value="<?= $val ?: '#000000' ?>"
                 onchange="document.getElementById('<?= $fn ?>').value=this.value">
          <input type="text" id="<?= $fn ?>" name="<?= $fn ?>" class="form-input"
                 value="<?= e($val) ?>" placeholder="#000000" dir="ltr">
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="form-grid" style="margin-top:14px;">
      <div class="form-group form-full">
        <label class="form-label">CSS Gradient للكارد</label>
        <input type="text" name="card_gradient" class="form-input" dir="ltr"
               value="<?= e($row['card_gradient']) ?>"
               placeholder="linear-gradient(135deg, #ff00c8, #00e5ff)">
      </div>
      <div class="form-group form-full">
        <label class="form-label">CSS Gradient للزر</label>
        <input type="text" name="button_gradient" class="form-input" dir="ltr"
               value="<?= e($row['button_gradient']) ?>"
               placeholder="linear-gradient(90deg, #ffd600, #ff00c8)">
      </div>
    </div>

    <!-- Image Upload Fields -->
    <div style="margin-top:20px; border-top:1px solid var(--line); padding-top:18px;">
      <div style="font-size:13px; font-weight:700; color:var(--muted); margin-bottom:14px;">📸 صور الخدمة</div>
      <div class="form-grid">

        <!-- Main Image (1:1 square) -->
        <div class="form-group img-upload-group">
          <label class="form-label">الصورة الرئيسية <span style="color:var(--muted);font-weight:400;">(1:1 مربعة — 800×800 px)</span></label>
          <?php $cur_main = $row['main_image'] ?: $row['image']; ?>
          <?php if ($cur_main): ?>
            <div class="img-preview-wrap" style="max-width:160px;">
              <img src="<?= e($cur_main) ?>" alt="الصورة الرئيسية" style="aspect-ratio:1;object-fit:cover;">
            </div>
          <?php else: ?>
            <div class="img-preview-placeholder" style="aspect-ratio:1;width:120px;">
              <span class="upload-icon">🖼️</span><span>لا توجد صورة</span>
            </div>
          <?php endif; ?>
          <img id="main-img-new" style="display:none; width:120px; aspect-ratio:1; object-fit:cover; border-radius:10px; border:1px solid var(--line); margin-top:6px;" alt="">
          <div class="file-input-wrap" style="margin-top:6px;">
            <label class="file-input-btn" for="main_image_file">
              📁 <?= $cur_main ? 'استبدال الصورة' : 'رفع صورة' ?>
            </label>
            <input type="file" name="main_image_file" id="main_image_file" class="hidden-file"
                   accept="image/jpeg,image/png,image/webp,image/svg+xml"
                   onchange="previewSingle(this,'main-img-new')">
          </div>
          <div class="upload-hint">PNG، JPG، WEBP — 5MB كحد أقصى — نسبة 1:1 مثالية</div>
        </div>

        <!-- Icon Image (1:1) -->
        <div class="form-group img-upload-group">
          <label class="form-label">أيقونة الخدمة <span style="color:var(--muted);font-weight:400;">(1:1 — 200×200 px)</span></label>
          <?php if ($row['icon_image']): ?>
            <div class="img-preview-wrap" style="max-width:100px;">
              <img src="<?= e($row['icon_image']) ?>" alt="أيقونة" style="aspect-ratio:1;object-fit:contain;">
            </div>
          <?php else: ?>
            <div class="img-preview-placeholder" style="width:80px;height:80px;">
              <span class="upload-icon">🔣</span><span>لا توجد أيقونة</span>
            </div>
          <?php endif; ?>
          <img id="icon-img-new" style="display:none; width:80px; aspect-ratio:1; object-fit:contain; border-radius:10px; border:1px solid var(--line); margin-top:6px;" alt="">
          <div class="file-input-wrap" style="margin-top:6px;">
            <label class="file-input-btn" for="icon_image_file">
              📁 <?= $row['icon_image'] ? 'استبدال الأيقونة' : 'رفع أيقونة' ?>
            </label>
            <input type="file" name="icon_image_file" id="icon_image_file" class="hidden-file"
                   accept="image/jpeg,image/png,image/webp,image/svg+xml"
                   onchange="previewSingle(this,'icon-img-new')">
          </div>
          <div class="upload-hint">PNG، JPG، WEBP، SVG — 5MB كحد أقصى</div>
        </div>

        <!-- Banner Image (4:1 wide) -->
        <div class="form-group img-upload-group form-full">
          <label class="form-label">بانر الخدمة <span style="color:var(--muted);font-weight:400;">(4:1 عريض — 1600×400 px)</span></label>
          <?php if ($row['banner_image']): ?>
            <div class="img-preview-wrap" style="max-width:100%; width:100%;">
              <img src="<?= e($row['banner_image']) ?>" alt="بانر" style="width:100%;aspect-ratio:4/1;object-fit:cover;">
            </div>
          <?php else: ?>
            <div class="img-preview-placeholder" style="width:100%;aspect-ratio:4/1;height:auto;flex-direction:row;">
              <span class="upload-icon">🏞️</span><span>لا يوجد بانر — يُعرض في أعلى صفحة الخدمة</span>
            </div>
          <?php endif; ?>
          <img id="banner-img-new" style="display:none; width:100%; aspect-ratio:4/1; object-fit:cover; border-radius:10px; border:1px solid var(--line); margin-top:6px;" alt="">
          <div class="file-input-wrap" style="margin-top:6px;">
            <label class="file-input-btn" for="banner_image_file">
              📁 <?= $row['banner_image'] ? 'استبدال البانر' : 'رفع بانر' ?>
            </label>
            <input type="file" name="banner_image_file" id="banner_image_file" class="hidden-file"
                   accept="image/jpeg,image/png,image/webp,image/svg+xml"
                   onchange="previewSingle(this,'banner-img-new')">
          </div>
          <div class="upload-hint">PNG، JPG، WEBP — 5MB كحد أقصى — أبعاد مثالية: 1600×400 بكسل (نسبة 4:1)</div>
        </div>

        <!-- Legacy URL fallback -->
        <div class="form-group form-full">
          <label class="form-label" style="color:rgba(255,255,255,.35);">رابط صورة خارجي (بديل URL — إذا لم ترفع صورة)</label>
          <input type="text" name="image" class="form-input" dir="ltr"
                 value="<?= e($row['image']) ?>" placeholder="https://..." style="opacity:.5;">
        </div>

      </div>
    </div>
  </div>
</details>

<!-- ── 6. ORDER SETTINGS ──────────────────────────────────── -->
<details class="form-section">
  <summary>📦 إعدادات الطلب</summary>
  <div class="form-section-body">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">طريقة الطلب</label>
        <select name="order_type" class="form-select">
          <?php foreach ($ORDER_TYPES as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= ($row['order_type']??'whatsapp')==$val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">رابط الطلب</label>
        <input type="text" name="order_link" class="form-input" dir="ltr"
               value="<?= e($row['order_link']) ?>" placeholder="https://...">
      </div>
      <div class="form-group">
        <label class="form-label">رقم واتساب</label>
        <input type="text" name="whatsapp_number" class="form-input" dir="ltr"
               value="<?= e($row['whatsapp_number']) ?>" placeholder="201XXXXXXXXX">
      </div>
      <div class="form-group">
        <label class="form-label">رابط بوت تليجرام</label>
        <input type="text" name="telegram_bot" class="form-input" dir="ltr"
               value="<?= e($row['telegram_bot']) ?>" placeholder="https://t.me/...">
      </div>
    </div>
    <div class="form-grid" style="margin-top:12px;">
      <label class="form-check"><input type="checkbox" name="requires_approval" value="1" <?= $row['requires_approval'] ? 'checked' : '' ?>> يتطلب موافقة قبل التنفيذ</label>
      <label class="form-check"><input type="checkbox" name="requires_advance_payment" value="1" <?= $row['requires_advance_payment'] ? 'checked' : '' ?>> يتطلب دفع مقدم</label>
    </div>
    <div class="form-grid" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--line);">
      <label class="form-check"><input type="checkbox" name="buy_now_enabled" value="1" <?= ($row['buy_now_enabled'] ?? 1) ? 'checked' : '' ?>> تفعيل زر "شراء الآن" ⚡</label>
      <label class="form-check"><input type="checkbox" name="cart_enabled" value="1" <?= ($row['cart_enabled'] ?? 1) ? 'checked' : '' ?>> تفعيل "إضافة للسلة" 🛒</label>
    </div>
    <div class="form-group" style="margin-top:14px;">
      <label class="form-label">وسوم الخدمة (Tags)
        <span style="color:var(--muted); font-weight:400;">(مفصولة بفاصلة — تُستخدم في الخدمات المشابهة)</span>
      </label>
      <input type="text" name="service_tags" class="form-input"
             value="<?= e($row['service_tags'] ?? '') ?>"
             placeholder="فيسبوك, سوشيال ميديا, متابعين, شحن, ألعاب...">
    </div>
  </div>
</details>

<!-- ── 7. MEDIATION ───────────────────────────────────────── -->
<details class="form-section">
  <summary>🤝 إعدادات الوساطة</summary>
  <div class="form-section-body">
    <div style="margin-bottom:12px;">
      <label class="form-check"><input type="checkbox" name="mediation_enabled" value="1" <?= $row['mediation_enabled'] ? 'checked' : '' ?>> تفعيل الوساطة لهذه الخدمة</label>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">نوع الوساطة</label>
        <select name="mediation_type" class="form-select">
          <?php foreach ($MED_TYPES as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= ($row['mediation_type']??'none')==$val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">رسوم الوساطة</label>
        <input type="number" name="mediation_fee" class="form-input" step="0.01" min="0"
               value="<?= $row['mediation_fee'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">عمولة الوسيط</label>
        <input type="number" name="mediator_commission" class="form-input" step="0.01" min="0"
               value="<?= $row['mediator_commission'] ?? '' ?>" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label">هاتف الوسيط الافتراضي</label>
        <input type="text" name="mediator_phone" class="form-input" dir="ltr"
               value="<?= e($row['mediator_phone']) ?>" placeholder="201XXXXXXXXX">
      </div>
      <div class="form-group">
        <label class="form-label">مجموعة واتساب الوساطة</label>
        <input type="text" name="mediation_whatsapp_group" class="form-input" dir="ltr"
               value="<?= e($row['mediation_whatsapp_group']) ?>" placeholder="رقم أو رابط المجموعة">
      </div>
      <div class="form-group">
        <label class="form-label">هاتف الطوارئ</label>
        <input type="text" name="emergency_phone" class="form-input" dir="ltr"
               value="<?= e($row['emergency_phone']) ?>" placeholder="201XXXXXXXXX">
      </div>
    </div>
    <div style="margin-top:12px;">
      <label class="form-check"><input type="checkbox" name="show_mediation_terms" value="1" <?= $row['show_mediation_terms'] ? 'checked' : '' ?>> عرض شروط الوساطة قبل الطلب</label>
    </div>
  </div>
</details>

<!-- ── 8. SUPPLIER ────────────────────────────────────────── -->
<details class="form-section">
  <summary>🏭 إعدادات المورّد</summary>
  <div class="form-section-body">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">اسم المورّد</label>
        <input type="text" name="supplier_name" class="form-input"
               value="<?= e($row['supplier_name']) ?>" placeholder="اسم المورّد">
      </div>
      <div class="form-group">
        <label class="form-label">جهة تواصل المورّد</label>
        <input type="text" name="supplier_phone" class="form-input"
               value="<?= e($row['supplier_phone']) ?>" placeholder="رقم أو معرف">
      </div>
      <div class="form-group">
        <label class="form-label">أولوية المورّد</label>
        <input type="number" name="supplier_priority" class="form-input" min="0"
               value="<?= (int)($row['supplier_priority'] ?? 0) ?>">
      </div>
    </div>
    <div style="margin-top:12px;">
      <label class="form-check"><input type="checkbox" name="supplier_visible" value="1" <?= $row['supplier_visible'] ? 'checked' : '' ?>> إظهار اسم المورّد للعميل</label>
    </div>
  </div>
</details>

<!-- ── 9. SEO ─────────────────────────────────────────────── -->
<details class="form-section">
  <summary>🔍 إعدادات SEO</summary>
  <div class="form-section-body" style="display:flex; flex-direction:column; gap:14px;">
    <div class="form-group">
      <label class="form-label">عنوان SEO</label>
      <input type="text" name="seo_title" class="form-input"
             value="<?= e($row['seo_title']) ?>" placeholder="عنوان الصفحة في محركات البحث">
    </div>
    <div class="form-group">
      <label class="form-label">وصف SEO</label>
      <textarea name="seo_description" class="form-textarea" placeholder="وصف الصفحة في نتائج البحث (150-160 حرف)..."><?= e($row['seo_description']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">كلمات مفتاحية</label>
      <input type="text" name="seo_keywords" class="form-input"
             value="<?= e($row['seo_keywords']) ?>" placeholder="كلمة1, كلمة2, كلمة3">
    </div>
    <div class="form-grid">
      <label class="form-check"><input type="checkbox" name="noindex" value="1" <?= $row['noindex'] ? 'checked' : '' ?>> Noindex (لا تفهرس في جوجل)</label>
      <label class="form-check"><input type="checkbox" name="show_sitemap" value="1" <?= ($row['show_sitemap']??1) ? 'checked' : '' ?>> إظهار في Sitemap</label>
    </div>
  </div>
</details>

<!-- ── GALLERY ──────────────────────────────────────────── -->
<details class="form-section">
  <summary>🖼️ معرض الصور</summary>
  <div class="form-section-body">

    <?php
    $gallery_imgs = json_decode($row['gallery_images'] ?? '[]', true) ?: [];
    if ($gallery_imgs):
    ?>
    <div style="font-size:13px; color:var(--muted); margin-bottom:10px;">الصور الحالية في المعرض (<?= count($gallery_imgs) ?>)</div>
    <div class="gallery-grid">
      <?php foreach ($gallery_imgs as $gimg): ?>
        <?php if (!$gimg) continue; ?>
        <div class="gallery-item">
          <img src="<?= e($gimg) ?>" alt="معرض"
               onerror="this.closest('.gallery-item').style.display='none'">
          <a href="?id=<?= $id ?>&rm_gallery=<?= urlencode($gimg) ?>"
             class="gallery-item-remove"
             onclick="return confirm('هل تريد حذف هذه الصورة من المعرض؟')"
             title="حذف الصورة">×</a>
        </div>
      <?php endforeach; ?>
    </div>
    <hr style="border-color:var(--line); margin:16px 0;">
    <?php endif; ?>

    <div class="form-group img-upload-group">
      <label class="form-label">إضافة صور لمعرض الخدمة <span style="color:var(--muted);font-weight:400;">(1:1 مربعة — يمكن رفع أكثر من صورة)</span></label>
      <div class="file-input-wrap">
        <label class="file-input-btn" for="gallery">
          📁 اختر صور المعرض (متعدد)
        </label>
        <input type="file" name="gallery[]" id="gallery" class="hidden-file"
               accept="image/jpeg,image/png,image/webp,image/svg+xml"
               multiple onchange="previewGallery(this)">
      </div>
      <div class="upload-hint">يمكنك اختيار أكثر من صورة في نفس الوقت — PNG، JPG، WEBP — 5MB كحد أقصى لكل صورة — نسبة 1:1 مثالية</div>
      <div class="img-new-previews" id="gallery-preview"></div>
    </div>

  </div>
</details>

<!-- Submit -->
<div style="position:sticky; bottom:0; background:rgba(8,8,8,.92); backdrop-filter:blur(10px); border-top:1px solid var(--line); padding:14px 0; margin-top:8px; display:flex; gap:10px; flex-wrap:wrap; z-index:20;">
  <button type="submit" class="btn btn-primary" style="min-height:44px; padding:0 28px; font-size:15px;">
    <?= $is_edit ? '💾 حفظ التعديلات' : '➕ إضافة الخدمة' ?>
  </button>
  <a href="services.php" class="btn btn-secondary">إلغاء</a>
</div>


<div class="card" style="margin-top:18px"><div class="card-header"><h3>⚙️ إدارة الطلب والتنفيذ — Service-Level Workflow</h3></div><div class="card-body">
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px"><span class="badge">STORE / SUPPLIER</span><span class="badge">AUTO / MANUAL PAYMENT</span><span class="badge">API / MANUAL / SUPPORT</span></div>
<div class="form-grid">
<label>مصدر الخدمة<select name="source_type" class="form-control"><option value="store" <?=($row['source_type']??'store')==='store'?'selected':''?>>خدمة متجر</option><option value="supplier" <?=($row['source_type']??'')==='supplier'?'selected':''?>>خدمة مورد</option></select></label>
<label>طريقة الدفع<select name="payment_method" class="form-control"><option value="auto">دفع أوتوماتيك من المتجر</option><option value="manual_support">دفع يدوي عن طريق الدعم</option></select></label>
<label>جهة استلام الطلب<select name="order_receiver" class="form-control"><option value="system">النظام / المتجر</option><option value="support">الدعم</option><option value="supplier">المورد</option></select><small>خدمة المورد تُدار افتراضيًا عبر الدعم؛ ممنوع تحويل العميل مباشرة للمورد.</small></label>
<label>طريقة التنفيذ<select name="execution_method" class="form-control"><option value="api">تلقائي API</option><option value="admin_manual">يدوي بواسطة الإدارة</option><option value="support_manual">يدوي بواسطة الدعم</option><option value="supplier_via_support">مورد بعد تواصل الدعم</option></select></label>
<label>التواصل بعد إنشاء الطلب<select name="post_order_contact" class="form-control"><option value="none">بدون تحويل</option><option value="store_support">دعم المتجر</option><option value="support_whatsapp">واتساب الدعم</option><option value="elawaady_whatsapp">واتساب العوضي</option></select></label>
<label>زر أساسي<input name="primary_button_label" class="form-control" value="<?=htmlspecialchars($row['primary_button_label']??'اشتري الآن')?>"></label>
<label>زر ثانوي<input name="secondary_button_label" class="form-control" value="<?=htmlspecialchars($row['secondary_button_label']??'أضف إلى السلة')?>"></label>
<label>خلفية PNG<select name="image_background_mode" class="form-control"><option value="transparent">شفافة — لون المتجر يظهر خلفها</option><option value="store">خلفية المتجر</option><option value="custom">مخصصة</option></select></label>
<label>عرض البانر<select name="banner_fit" class="form-control"><option value="original">Original Ratio</option><option value="contain">Contain</option><option value="cover">Cover</option><option value="auto_height">Auto Height</option><option value="full_width">Full Width</option><option value="custom">Custom</option></select></label>
</div>
<div class="checkbox-grid" style="margin-top:14px">
<?php foreach(['require_availability_confirmation'=>'تأكيد التوفر قبل الدفع','require_admin_approval_before_execution'=>'موافقة الإدارة قبل التنفيذ','auto_start_after_payment'=>'بدء تلقائي بعد الدفع','allow_wallet_payment'=>'السماح بالدفع من الرصيد','show_payment_gateways'=>'إظهار بوابات الدفع','progress_tracking_enabled'=>'عداد تقدم الطلب','supplier_can_view_order'=>'المورد يرى الأوردر','supplier_can_update_status'=>'المورد يحدث التنفيذ','supplier_can_upload_delivery_proof'=>'المورد يرفع إثبات التسليم','hide_customer_data_from_supplier'=>'إخفاء بيانات العميل عن المورد'] as $k=>$lab): ?><label><input type="checkbox" name="<?=$k?>" <?=!empty($row[$k])?'checked':''?>> <?=$lab?></label><?php endforeach; ?>
</div>
</div></div>
</form>

<script>
// ── Subcategory filter ───────────────────────────────────────
const mainCat = document.getElementById('main-cat');
const subCat  = document.getElementById('sub-cat');
function filterSubs() {
    const selected = mainCat.value;
    Array.from(subCat.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = opt.dataset.parent !== selected;
    });
    if (subCat.selectedOptions[0]?.hidden) subCat.value = '';
}
mainCat.addEventListener('change', filterSubs);
filterSubs();

// ── Image preview helpers ─────────────────────────────────────
function previewSingle(input, previewId) {
    const el = document.getElementById(previewId);
    if (!el) return;
    const file = input.files[0];
    if (!file) { el.style.display = 'none'; return; }
    const reader = new FileReader();
    reader.onload = e => { el.src = e.target.result; el.style.display = 'block'; };
    reader.readAsDataURL(file);
}

function previewGallery(input) {
    const wrap = document.getElementById('gallery-preview');
    if (!wrap) return;
    wrap.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            wrap.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}
</script>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
