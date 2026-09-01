<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_helpers.php';
admin_require('media.manage');
require_once __DIR__ . '/upload_handler.php';

$page_title_admin = '🎠 إدارة الكاروسيل';

$msg      = '';
$msg_type = '';

// ── Reference data ──────────────────────────────────────────────────
$all_categories = fetch_all($conn,
    "SELECT id, name FROM store_categories WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
$all_services   = fetch_all($conn,
    "SELECT id, name FROM store_services WHERE is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 300");

// ── Handle POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    // ── Add / Edit ──
    if ($action === 'save') {
        try {
            $id         = (int)($_POST['id'] ?? 0);
            $title_ar   = trim($_POST['title_ar']   ?? '');
            $title_en   = trim($_POST['title_en']   ?? '');
            $link_type  = in_array($_POST['link_type'] ?? 'none',
                            ['service','category','custom','none'])
                          ? $_POST['link_type'] : 'none';
            $link_id    = ($link_type === 'service' || $link_type === 'category')
                          ? (int)($_POST['link_id'] ?? 0) : null;
            $custom_url = ($link_type === 'custom')
                          ? trim($_POST['custom_url'] ?? '') : null;
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $is_active  = isset($_POST['is_active']) ? 1 : 0;

            $dest    = __DIR__ . '/../uploads/carousel/';
            $new_img = upload_image('image', $dest);

            if ($id > 0) {
                $existing   = fetch_one($conn,
                    "SELECT image FROM homepage_carousel WHERE id=?", "i", $id);
                $image_path = $new_img ?: ($existing['image'] ?? '');
                $stmt = $conn->prepare(
                    "UPDATE homepage_carousel
                     SET image=?,title_ar=?,title_en=?,link_type=?,link_id=?,
                         custom_url=?,sort_order=?,is_active=?,updated_at=NOW()
                     WHERE id=?");
                $stmt->bind_param('ssssissii',
                    $image_path,$title_ar,$title_en,$link_type,
                    $link_id,$custom_url,$sort_order,$is_active,$id);
                $stmt->execute();
                $msg = '✅ تم تحديث البطاقة بنجاح.';
            } else {
                if (!$new_img) throw new Exception('يرجى اختيار صورة للبطاقة الجديدة.');
                $stmt = $conn->prepare(
                    "INSERT INTO homepage_carousel
                     (image,title_ar,title_en,link_type,link_id,custom_url,sort_order,is_active)
                     VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssissi',
                    $new_img,$title_ar,$title_en,$link_type,
                    $link_id,$custom_url,$sort_order,$is_active);
                $stmt->execute();
                $msg = '✅ تمت إضافة البطاقة بنجاح.';
            }
            $msg_type = 'success';
        } catch (Exception $e) {
            $msg = '❌ خطأ: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }

    // ── Toggle active ──
    if ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['is_active'] ?? 0);
        $stmt = $conn->prepare("UPDATE homepage_carousel SET is_active=? WHERE id=?");
        $stmt->bind_param('ii', $val, $id);
        $stmt->execute();
        $msg      = $val ? '✅ البطاقة نشطة الآن.' : '⚠️ البطاقة مخفية.';
        $msg_type = $val ? 'success' : 'warning';
    }

    // ── Delete ──
    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = fetch_one($conn, "SELECT image FROM homepage_carousel WHERE id=?", "i", $id);
        if ($row) {
            delete_upload($row['image']);
            $stmt = $conn->prepare("DELETE FROM homepage_carousel WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $msg      = '🗑️ تم حذف البطاقة.';
            $msg_type = 'warning';
        }
    }

    // ── Bulk reorder ──
    if ($action === 'reorder') {
        $orders = $_POST['order'] ?? [];
        $stmt   = $conn->prepare("UPDATE homepage_carousel SET sort_order=? WHERE id=?");
        foreach ($orders as $cid => $ord) {
            $cid = (int)$cid; $ord = (int)$ord;
            $stmt->bind_param('ii', $ord, $cid);
            $stmt->execute();
        }
        $msg      = '✅ تم حفظ الترتيب.';
        $msg_type = 'success';
    }

    // Redirect to clear POST (PRG pattern)
    $qs = http_build_query(array_filter(['msg'=>$msg,'mt'=>$msg_type]));
    header("Location: carousel.php?$qs");
    exit;
}

// ── Pick up flash from redirect ──
if (!$msg && isset($_GET['msg'])) {
    $msg      = htmlspecialchars($_GET['msg'], ENT_QUOTES);
    $msg_type = $_GET['mt'] ?? 'success';
}

// ── Edit mode ──
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_item = fetch_one($conn,
        "SELECT * FROM homepage_carousel WHERE id=?", "i", (int)$_GET['edit']);
}

// ── Fetch all items ──
$items = fetch_all($conn,
    "SELECT * FROM homepage_carousel ORDER BY sort_order ASC, id ASC");

require_once __DIR__ . '/layout.php';
?>

<style>
/* ── Carousel admin page styles ── */
.car-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
.car-stat-card {
    background: var(--panel); border:1px solid var(--line);
    border-radius:12px; padding:16px 24px;
    min-width:120px; text-align:center;
}
.car-stat-card .stat-n { font-size:28px; font-weight:900; color:var(--orange); }
.car-stat-card .stat-l { font-size:11px; color:var(--muted); margin-top:2px; }

.car-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap:16px;
    margin-bottom:32px;
}
.car-card {
    background:var(--panel); border:1px solid var(--line);
    border-radius:14px; overflow:hidden;
    display:flex; flex-direction:column;
    transition: border-color .2s, box-shadow .2s;
    position:relative;
}
.car-card.inactive { opacity:.55; }
.car-card:hover { border-color: rgba(255,122,0,.35); box-shadow:0 4px 20px rgba(255,122,0,.12); }
.car-card-img {
    width:100%; aspect-ratio:16/9;
    object-fit:cover; display:block;
    background:#111;
}
.car-card-img-placeholder {
    width:100%; aspect-ratio:16/9;
    background:#1a1a1a; display:flex;
    align-items:center; justify-content:center;
    font-size:32px; color:#555;
}
.car-card-body {
    padding:10px 12px;
    flex:1; display:flex; flex-direction:column; gap:4px;
}
.car-card-order {
    position:absolute; top:8px; right:8px;
    background:rgba(0,0,0,.7); color:#fff;
    font-size:10px; font-weight:800;
    border-radius:6px; padding:2px 7px;
}
.car-card-status {
    position:absolute; top:8px; left:8px;
}
.car-status-dot {
    width:10px; height:10px; border-radius:50%;
    display:inline-block;
}
.car-status-dot.active   { background:#22c55e; box-shadow:0 0 6px #22c55e; }
.car-status-dot.inactive { background:#888; }
.car-card-title { font-size:12px; font-weight:700; color:#fff; line-height:1.35; }
.car-card-link  { font-size:10px; color:var(--muted); }
.car-card-actions {
    display:flex; gap:6px; padding:8px 12px 12px;
    border-top:1px solid var(--line);
}
.car-card-actions .btn-xs {
    flex:1; font-size:11px; padding:5px 8px;
    border-radius:7px; text-align:center;
    cursor:pointer; border:none; font-weight:700;
    text-decoration:none;
}
.btn-xs-edit   { background:rgba(255,122,0,.15); color:#ff9500; border:1px solid rgba(255,122,0,.25); }
.btn-xs-toggle { background:rgba(255,255,255,.07); color:#ccc;   border:1px solid rgba(255,255,255,.12); font-size:10px; }
.btn-xs-del    { background:rgba(220,38,38,.12);  color:#f87171; border:1px solid rgba(220,38,38,.2); }
.btn-xs-edit:hover   { background:rgba(255,122,0,.28); }
.btn-xs-toggle:hover { background:rgba(255,255,255,.14); }
.btn-xs-del:hover    { background:rgba(220,38,38,.24); }

/* form panel */
.car-form-panel {
    background:var(--panel); border:1px solid var(--line);
    border-radius:16px; padding:24px;
    margin-bottom:28px;
}
.car-form-panel h3 {
    margin:0 0 18px; font-size:16px; font-weight:800; color:#fff;
}
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group label { font-size:12px; font-weight:700; color:var(--muted); }
.form-group input,
.form-group select,
.form-group textarea {
    background:#111; border:1px solid var(--line);
    border-radius:8px; padding:8px 12px;
    color:#fff; font-size:13px;
    width:100%; box-sizing:border-box;
    font-family:inherit;
}
.form-group input:focus,
.form-group select:focus { outline:none; border-color:rgba(255,122,0,.5); }
.img-preview-wrap { width:100%; border-radius:10px; overflow:hidden; background:#0a0a0a; }
.img-preview-wrap img { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; }
.link-id-row { display:none; }
.link-id-row.show { display:flex; }
.toggle-check { display:flex; align-items:center; gap:10px; cursor:pointer; }
.toggle-check input[type=checkbox] { width:18px; height:18px; accent-color:#ff9500; cursor:pointer; }

/* reorder form */
.reorder-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(160px,1fr));
    gap:10px;
    margin-bottom:16px;
}
.reorder-item {
    background:#111; border:1px solid var(--line);
    border-radius:10px; padding:8px;
    display:flex; align-items:center; gap:8px;
}
.reorder-item img {
    width:48px; height:36px;
    object-fit:cover; border-radius:6px; flex-shrink:0;
}
.reorder-item input[type=number] {
    width:52px; background:#0a0a0a;
    border:1px solid var(--line);
    border-radius:6px; color:#fff;
    padding:4px 6px; font-size:12px;
}
.reorder-item-lbl { font-size:11px; color:#ccc; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

@media(max-width:600px) {
    .form-row-2,
    .form-row-3 { grid-template-columns:1fr; }
    .car-grid { grid-template-columns: repeat(auto-fill, minmax(150px,1fr)); gap:10px; }
}
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type === 'success' ? 'success' : ($msg_type === 'warning' ? 'warning' : 'error') ?>"
     style="margin-bottom:16px;"><?= $msg ?></div>
<?php endif; ?>

<!-- Stats bar -->
<?php
$total  = count($items);
$active = count(array_filter($items, fn($r) => $r['is_active']));
?>
<div class="car-stats">
    <div class="car-stat-card">
        <div class="stat-n"><?= $total ?></div>
        <div class="stat-l">إجمالي البطاقات</div>
    </div>
    <div class="car-stat-card">
        <div class="stat-n" style="color:#22c55e;"><?= $active ?></div>
        <div class="stat-l">نشطة</div>
    </div>
    <div class="car-stat-card">
        <div class="stat-n" style="color:#888;"><?= $total - $active ?></div>
        <div class="stat-l">مخفية</div>
    </div>
</div>

<!-- Add / Edit form -->
<div class="car-form-panel">
    <h3><?= $edit_item ? '✏️ تعديل البطاقة #' . (int)$edit_item['id'] : '➕ إضافة بطاقة جديدة' ?></h3>
    <form method="POST" enctype="multipart/form-data" id="carForm">
<?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <?php if ($edit_item): ?>
            <input type="hidden" name="id" value="<?= (int)$edit_item['id'] ?>">
        <?php endif; ?>

        <!-- Row 1: image + preview -->
        <div class="form-row-2" style="margin-bottom:14px; align-items:start;">
            <div class="form-group">
                <label>📸 الصورة <?= $edit_item ? '(اترك فارغًا للإبقاء على الحالية)' : '(مطلوب)' ?></label>
                <input type="file" name="image" accept="image/*"
                       onchange="previewImg(this)"
                       <?= $edit_item ? '' : 'required' ?>>
                <small style="color:#666;font-size:11px;">JPG / PNG / WebP — بحد أقصى 5MB</small>
            </div>
            <div class="form-group">
                <label>معاينة الصورة</label>
                <div class="img-preview-wrap" id="imgPreviewWrap">
                    <?php if ($edit_item && $edit_item['image']): ?>
                        <img id="imgPreview" src="<?= e($edit_item['image']) ?>" alt="">
                    <?php else: ?>
                        <div style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;color:#555;font-size:13px;">
                            لا توجد صورة
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Row 2: titles -->
        <div class="form-row-2" style="margin-bottom:14px;">
            <div class="form-group">
                <label>العنوان بالعربي</label>
                <input type="text" name="title_ar" maxlength="255"
                       value="<?= e($edit_item['title_ar'] ?? '') ?>"
                       placeholder="مثال: خدمات السوشيال ميديا">
            </div>
            <div class="form-group">
                <label>العنوان بالإنجليزي</label>
                <input type="text" name="title_en" maxlength="255"
                       value="<?= e($edit_item['title_en'] ?? '') ?>"
                       placeholder="e.g. Social Media Services">
            </div>
        </div>

        <!-- Row 3: link + sort + active -->
        <div class="form-row-3" style="margin-bottom:14px;">
            <div class="form-group">
                <label>نوع الرابط</label>
                <select name="link_type" id="linkType" onchange="updateLinkId()">
                    <?php foreach (['none'=>'بدون رابط','service'=>'خدمة','category'=>'قسم','custom'=>'رابط مخصص'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= (($edit_item['link_type'] ?? 'none') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>الترتيب</label>
                <input type="number" name="sort_order" min="0" max="9999"
                       value="<?= (int)($edit_item['sort_order'] ?? ($total + 1)) ?>">
            </div>
            <div class="form-group" style="justify-content:flex-end;">
                <label class="toggle-check" style="margin-top:auto;padding-bottom:6px;">
                    <input type="checkbox" name="is_active" value="1"
                           <?= ($edit_item['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span style="font-size:13px;color:#fff;">نشط — يظهر في الكاروسيل</span>
                </label>
            </div>
        </div>

        <!-- Dynamic: service picker -->
        <div class="form-row-2 link-id-row" id="svcRow" style="margin-bottom:14px;">
            <div class="form-group">
                <label>اختر الخدمة</label>
                <select name="link_id">
                    <option value="">-- اختر خدمة --</option>
                    <?php foreach ($all_services as $svc): ?>
                        <option value="<?= $svc['id'] ?>"
                            <?= ($edit_item && $edit_item['link_type']==='service' && (int)$edit_item['link_id']===$svc['id']) ? 'selected' : '' ?>>
                            #<?= $svc['id'] ?> — <?= e($svc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Dynamic: category picker -->
        <div class="form-row-2 link-id-row" id="catRow" style="margin-bottom:14px;">
            <div class="form-group">
                <label>اختر القسم</label>
                <select name="link_id">
                    <option value="">-- اختر قسمًا --</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= ($edit_item && $edit_item['link_type']==='category' && (int)$edit_item['link_id']===$cat['id']) ? 'selected' : '' ?>>
                            #<?= $cat['id'] ?> — <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Dynamic: custom URL -->
        <div class="form-row-2 link-id-row" id="urlRow" style="margin-bottom:14px;">
            <div class="form-group">
                <label>الرابط المخصص</label>
                <input type="url" name="custom_url" placeholder="https://..."
                       value="<?= e($edit_item['custom_url'] ?? '') ?>">
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" style="min-width:140px;">
                <?= $edit_item ? '💾 حفظ التعديلات' : '➕ إضافة البطاقة' ?>
            </button>
            <?php if ($edit_item): ?>
                <a href="carousel.php" class="btn btn-secondary">إلغاء التعديل</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Cards grid -->
<?php if ($items): ?>
<div class="panel-header" style="margin-bottom:14px;">
    <div class="panel-title">📋 بطاقات الكاروسيل (<?= count($items) ?>)</div>
</div>

<div class="car-grid">
    <?php foreach ($items as $item): ?>
    <div class="car-card <?= $item['is_active'] ? '' : 'inactive' ?>">
        <span class="car-card-order"><?= (int)$item['sort_order'] ?></span>
        <span class="car-card-status">
            <span class="car-status-dot <?= $item['is_active'] ? 'active' : 'inactive' ?>"></span>
        </span>
        <?php if ($item['image']): ?>
            <img class="car-card-img" src="<?= e($item['image']) ?>"
                 alt="<?= e($item['title_ar'] ?: $item['title_en'] ?: 'Card') ?>"
                 onerror="this.style.display='none'">
        <?php else: ?>
            <div class="car-card-img-placeholder">🖼️</div>
        <?php endif; ?>
        <div class="car-card-body">
            <?php if ($item['title_ar'] || $item['title_en']): ?>
                <div class="car-card-title">
                    <?= e($item['title_ar'] ?: $item['title_en']) ?>
                </div>
            <?php endif; ?>
            <div class="car-card-link">
                <?php
                $ltype = $item['link_type'];
                if ($ltype === 'service')  echo '🛍️ خدمة #' . (int)$item['link_id'];
                elseif ($ltype === 'category') echo '📁 قسم #' . (int)$item['link_id'];
                elseif ($ltype === 'custom' && $item['custom_url']) echo '🔗 ' . e(substr($item['custom_url'],0,30)) . '...';
                else echo 'بدون رابط';
                ?>
            </div>
        </div>
        <div class="car-card-actions">
            <a href="carousel.php?edit=<?= (int)$item['id'] ?>#carForm"
               class="btn-xs btn-xs-edit">✏️ تعديل</a>

            <form method="POST" style="flex:1;margin:0;">
<?= csrf_field() ?>
                <input type="hidden" name="action"    value="toggle">
                <input type="hidden" name="id"        value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="is_active" value="<?= $item['is_active'] ? 0 : 1 ?>">
                <button type="submit" class="btn-xs btn-xs-toggle" style="width:100%;">
                    <?= $item['is_active'] ? '🙈 إخفاء' : '👁️ إظهار' ?>
                </button>
            </form>

            <form method="POST" style="flex:0 0 auto;margin:0;"
                  onsubmit="return confirm('هل تريد حذف هذه البطاقة نهائيًا؟')">
<?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= (int)$item['id'] ?>">
                <button type="submit" class="btn-xs btn-xs-del">🗑️</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Reorder form -->
<details style="margin-top:8px;">
    <summary style="cursor:pointer;font-weight:700;color:var(--muted);font-size:13px;margin-bottom:12px;">
        🔃 إعادة الترتيب اليدوي
    </summary>
    <form method="POST" style="margin-top:12px;">
<?= csrf_field() ?>
        <input type="hidden" name="action" value="reorder">
        <div class="reorder-grid">
            <?php foreach ($items as $item): ?>
            <div class="reorder-item">
                <?php if ($item['image']): ?>
                    <img src="<?= e($item['image']) ?>" alt="">
                <?php endif; ?>
                <span class="reorder-item-lbl">
                    <?= e($item['title_ar'] ?: $item['title_en'] ?: '#' . $item['id']) ?>
                </span>
                <input type="number" name="order[<?= (int)$item['id'] ?>]"
                       value="<?= (int)$item['sort_order'] ?>" min="0" max="9999">
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary" style="min-width:160px;">
            💾 حفظ الترتيب
        </button>
    </form>
</details>

<?php else: ?>
<div style="text-align:center;padding:40px;color:#666;">
    لا توجد بطاقات بعد. أضف بطاقتك الأولى من النموذج أعلاه.
</div>
<?php endif; ?>

<script>
function previewImg(input) {
    const wrap = document.getElementById('imgPreviewWrap');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            wrap.innerHTML = '<img id="imgPreview" src="' + e.target.result + '" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateLinkId() {
    const lt = document.getElementById('linkType').value;
    document.getElementById('svcRow').classList.toggle('show', lt === 'service');
    document.getElementById('catRow').classList.toggle('show', lt === 'category');
    document.getElementById('urlRow').classList.toggle('show', lt === 'custom');
}

// Init on load
updateLinkId();
</script>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
