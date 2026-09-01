<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
$page_title_admin = 'معرفة الشات بوت';

$errors  = [];
$success = '';

// ── Delete ──────────────────────────────────────────────────
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $del  = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM chatbot_knowledge WHERE id=?");
    $stmt->bind_param("i", $del);
    $stmt->execute();
    header('Location: chatbot-knowledge.php?msg=deleted'); exit;
}

// ── Toggle active ────────────────────────────────────────────
if (isset($_GET['toggle']) && ctype_digit($_GET['toggle'])) {
    $tid  = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE chatbot_knowledge SET is_active = 1 - is_active WHERE id=?");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    header('Location: chatbot-knowledge.php?msg=toggled'); exit;
}

// ── POST: add or edit ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid       = (int)($_POST['id']       ?? 0);
    $question  = mb_substr(trim($_POST['question']  ?? ''), 0, 1000);
    $answer    = mb_substr(trim($_POST['answer']    ?? ''), 0, 5000);
    $category  = mb_substr(trim($_POST['category']  ?? ''), 0, 100);
    $keywords  = mb_substr(trim($_POST['keywords']  ?? ''), 0, 500);
    $priority  = max(0, min(100, (int)($_POST['priority'] ?? 0)));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($question === '') $errors[] = 'السؤال مطلوب.';
    if ($answer   === '') $errors[] = 'الجواب مطلوب.';

    if (!$errors) {
        if ($pid > 0) {
            $stmt = $conn->prepare(
                "UPDATE chatbot_knowledge
                 SET question=?, answer=?, category=?, keywords=?, priority=?, is_active=?
                 WHERE id=?");
            $stmt->bind_param("ssssiii", $question, $answer, $category, $keywords, $priority, $is_active, $pid);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO chatbot_knowledge (question, answer, category, keywords, priority, is_active)
                 VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssis", $question, $answer, $category, $keywords, $priority, $is_active);
        }
        $stmt->execute();
        header('Location: chatbot-knowledge.php?msg=saved'); exit;
    }
}

// ── Edit mode ─────────────────────────────────────────────────
$edit_row = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $edit_row = fetch_one($conn, "SELECT * FROM chatbot_knowledge WHERE id=?", "i", (int)$_GET['edit']);
}

$msg_map = ['saved' => '✅ تم الحفظ.', 'deleted' => '🗑️ تم الحذف.', 'toggled' => '✅ تم التحديث.'];
if (isset($_GET['msg'])) $success = $msg_map[$_GET['msg']] ?? '';

$items = fetch_all($conn, "SELECT * FROM chatbot_knowledge ORDER BY priority DESC, id ASC");

include __DIR__ . '/layout.php';
?>

<?php if ($success): ?>
<div class="alert alert-success" style="margin-bottom:16px;"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger" style="margin-bottom:16px;"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 380px; gap:20px; align-items:start;">

  <!-- ── Left: list ────────────────────────────────────────── -->
  <div>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">🤖 قاعدة المعرفة
          <span style="color:var(--muted); font-weight:400; font-size:13px;">(<?= count($items) ?> سؤال)</span>
        </div>
        <?php if ($edit_row): ?>
          <a href="chatbot-knowledge.php" class="btn btn-secondary btn-sm">+ إضافة جديد</a>
        <?php endif; ?>
      </div>

      <?php if ($items): ?>
      <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>السؤال / الجواب</th>
            <th>الفئة</th>
            <th>الكلمات المفتاحية</th>
            <th style="text-align:center;">أولوية</th>
            <th style="text-align:center;">الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
          <tr style="<?= $edit_row && $edit_row['id']==$it['id'] ? 'background:rgba(255,107,0,.08);' : '' ?>">
            <td>
              <div style="font-weight:700; font-size:13px; color:#fff; margin-bottom:3px;">
                <?= e(mb_substr($it['question'], 0, 70)) ?><?= mb_strlen($it['question'])>70 ? '…' : '' ?>
              </div>
              <div style="font-size:11px; color:var(--muted);">
                <?= e(mb_substr($it['answer'], 0, 90)) ?>…
              </div>
            </td>
            <td style="font-size:12px; color:var(--gold); white-space:nowrap;">
              <?= e($it['category'] ?: '—') ?>
            </td>
            <td style="font-size:11px; color:var(--muted); max-width:160px;">
              <?= e(mb_substr($it['keywords'], 0, 50)) ?>
            </td>
            <td style="text-align:center; font-weight:700;">
              <?= (int)$it['priority'] ?>
            </td>
            <td style="text-align:center;">
              <a href="chatbot-knowledge.php?toggle=<?= $it['id'] ?>"
                 class="badge <?= $it['is_active'] ? 'badge-active' : 'badge-inactive' ?>"
                 style="text-decoration:none; cursor:pointer; min-width:50px; display:inline-block; text-align:center;">
                <?= $it['is_active'] ? 'نشط' : 'معطل' ?>
              </a>
            </td>
            <td>
              <div style="display:flex; gap:6px;">
                <a href="chatbot-knowledge.php?edit=<?= $it['id'] ?>"
                   class="btn btn-secondary btn-sm">تعديل</a>
                <a href="chatbot-knowledge.php?delete=<?= $it['id'] ?>"
                   class="btn btn-secondary btn-sm" style="color:var(--red);"
                   onclick="return confirm('حذف هذا السؤال نهائيًا؟')">حذف</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">🤖</div>
        <p>لا توجد أسئلة بعد.<br>أضف أول سؤال من النموذج.</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Quick test panel -->
    <div class="panel" style="margin-top:16px;">
      <div class="panel-header"><div class="panel-title">🔍 اختبار الشات بوت</div></div>
      <div style="display:flex; gap:8px; padding:4px 0;">
        <input type="text" id="test-q" class="form-input" placeholder="اكتب سؤالاً لاختبار الرد..."
               style="flex:1;" onkeydown="if(event.key==='Enter') testBot()">
        <button class="btn btn-primary btn-sm" onclick="testBot()">اختبار</button>
      </div>
      <div id="test-result" style="margin-top:12px; display:none;"></div>
    </div>
  </div>

  <!-- ── Right: form ──────────────────────────────────────── -->
  <div class="panel" style="position:sticky; top:20px;">
    <div class="panel-header">
      <div class="panel-title"><?= $edit_row ? '✏️ تعديل السؤال' : '➕ إضافة سؤال جديد' ?></div>
    </div>
    <form method="post" style="display:flex; flex-direction:column; gap:14px;">
      <?php if ($edit_row): ?>
        <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">السؤال <span style="color:var(--red);">*</span></label>
        <textarea name="question" class="form-textarea" rows="3" required
                  placeholder="مثال: كيف يمكنني طلب خدمة؟"><?= e($edit_row['question'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">الجواب <span style="color:var(--red);">*</span></label>
        <textarea name="answer" class="form-textarea" rows="5" required
                  placeholder="اكتب الجواب الكامل الذي سيظهر للزائر..."><?= e($edit_row['answer'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">الفئة</label>
        <input type="text" name="category" class="form-input"
               value="<?= e($edit_row['category'] ?? '') ?>"
               list="cb-cats"
               placeholder="الطلبات، الدفع، التوثيق...">
        <datalist id="cb-cats">
          <option>الطلبات</option><option>الدفع</option><option>التوثيق</option>
          <option>الاشتراكات</option><option>الوساطة</option><option>الدعم الفني</option>
          <option>الاسترجاع</option><option>مدة التنفيذ</option>
        </datalist>
      </div>

      <div class="form-group">
        <label class="form-label">
          الكلمات المفتاحية
          <span style="color:var(--muted); font-weight:400;">(مفصولة بفاصلة)</span>
        </label>
        <input type="text" name="keywords" class="form-input"
               value="<?= e($edit_row['keywords'] ?? '') ?>"
               placeholder="طلب, خدمة, كيف, اطلب, شراء">
        <div style="font-size:11px; color:var(--muted); margin-top:4px;">
          تحسّن دقة البحث — كلما كانت الكلمات أدق، كانت الإجابة أصح.
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">
          الأولوية
          <span style="color:var(--muted); font-weight:400;">(0 = عادي — الأعلى يظهر أولاً)</span>
        </label>
        <input type="number" name="priority" class="form-input" min="0" max="100"
               value="<?= (int)($edit_row['priority'] ?? 0) ?>">
      </div>

      <label class="form-check">
        <input type="checkbox" name="is_active" value="1"
               <?= ($edit_row['is_active'] ?? 1) ? 'checked' : '' ?>>
        نشط — يظهر في الشات بوت
      </label>

      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">
          <?= $edit_row ? '💾 حفظ التعديلات' : '➕ إضافة السؤال' ?>
        </button>
        <?php if ($edit_row): ?>
          <a href="chatbot-knowledge.php" class="btn btn-secondary">إلغاء</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
function testBot() {
    var q = document.getElementById('test-q').value.trim();
    if (!q) return;
    var res = document.getElementById('test-result');
    res.style.display = 'block';
    res.innerHTML = '<span style="color:var(--muted); font-size:13px;">⏳ جارٍ البحث...</span>';
    fetch('../chatbot-search.php?q=' + encodeURIComponent(q))
        .then(function(r){return r.json();})
        .then(function(data){
            if (data.found) {
                res.innerHTML = '<div style="background:rgba(37,211,102,.08);border:1px solid rgba(37,211,102,.2);border-radius:10px;padding:12px;">'
                    + '<div style="font-size:11px;color:var(--green);font-weight:700;margin-bottom:6px;">✅ تم إيجاد إجابة</div>'
                    + '<div style="font-size:12px;color:var(--muted);margin-bottom:4px;">السؤال المطابق: ' + data.question + '</div>'
                    + '<div style="font-size:13px;color:#fff;white-space:pre-wrap;">' + data.answer + '</div></div>';
            } else {
                res.innerHTML = '<div style="background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.2);border-radius:10px;padding:12px;font-size:13px;color:var(--red);">❌ لم يُعثر على إجابة — سيظهر رابط واتساب للزائر</div>';
            }
        })
        .catch(function(){
            res.innerHTML = '<div style="color:var(--red);font-size:13px;">خطأ في الاتصال</div>';
        });
}
document.getElementById('test-q').addEventListener('keydown', function(e){ if(e.key==='Enter') testBot(); });
</script>

    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
</body>
</html>
