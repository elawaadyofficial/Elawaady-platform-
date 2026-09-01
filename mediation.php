<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/mediation.php';
require_once __DIR__ . '/settings_helper.php';
auth_boot();

$page_title = 'الوساطة الآمنة | Elawaady XDigital';

$enabled  = setting('mediation_enabled', '1') === '1';
$licence  = setting('licence_number', '767-766-857');
$whatsapp = preg_replace('/\D/', '', setting('support_whatsapp', ''));
$user     = auth_user();

// A customer arriving from a mediated order carries its code.
$orderCode = mb_substr(trim((string) ($_GET['order'] ?? '')), 0, 30);
$order     = $orderCode !== ''
    ? fetch_one($conn, "
        SELECT order_code, service_name, total_price, mediation_fee, currency, order_status
          FROM orders WHERE order_code = ? AND mediation_enabled = 1 LIMIT 1", 's', $orderCode)
    : null;

// The person's own cases, if they are signed in. A case is visible to its
// parties and to staff — never to anyone else.
$cases = [];
if ($user !== null) {
    $cases = fetch_all(
        $conn,
        'SELECT m.case_code, m.subject, m.deal_amount, m.fee_amount, m.currency,
                m.status, m.safety_ends_at, m.opened_at, mp.party_role
           FROM mediations m
           JOIN mediation_parties mp ON mp.mediation_id = m.id
          WHERE mp.user_id = ?
          ORDER BY m.id DESC LIMIT 20',
        'i',
        (int) $user['id']
    );
}

$statuses = mediation_statuses();

$policy = fetch_one(
    $conn,
    "SELECT p.title, v.content, v.version
       FROM policies p
       LEFT JOIN policy_versions v ON v.id = p.current_version_id
      WHERE p.policy_key = 'mediation_terms' LIMIT 1"
);

$steps = [
    ['1', 'اتفاق موثّق',      'يُسجَّل الطرفان وقيمة الصفقة وطريقة التسليم قبل تحويل أي مبلغ.'],
    ['2', 'حجز المبلغ',       'يُخصم المبلغ من محفظة المشتري ويُحتجَز لدى المنصة — لا يصل للبائع بعد.'],
    ['3', 'التسليم',          'يسلّم البائع ما اتُّفق عليه، وتوثَّق كل خطوة في سجل الصفقة.'],
    ['4', 'فترة الأمان',      'يفحص المشتري ما استلمه خلال المدة المتفق عليها قبل أن يصبح التحرير نهائيًا.'],
    ['5', 'التحرير أو الاسترداد', 'يُحرَّر المبلغ للبائع عند التأكيد، أو يُسترَد للمشتري إذا لم يتم التسليم.'],
];

require_once __DIR__ . '/header.php';
?>

<section class="mediation-hero">
    <div class="container">
        <span class="mini-label">وساطة آمنة</span>
        <h1>المبلغ محجوز حتى تستلم</h1>
        <p>
            الوساطة الرقمية من Elawaady XDigital تنظّم البيع والشراء بين طرفين: المنصة تحتجز
            المبلغ، توثّق كل خطوة، ولا تحرّره إلا بعد التسليم وانتهاء فترة الأمان.
        </p>
        <p class="mediation-licence">
            نشاط <strong>الوسيط لخدمات السوشيال ميديا</strong> — ترخيص رقم
            <span dir="ltr"><?= e($licence) ?></span>
        </p>

        <?php if (!$enabled): ?>
            <div class="alert alert-info">
                <span>◷</span><div>خدمة الوساطة متوقفة مؤقتًا. تواصل مع الدعم لمعرفة موعد عودتها.</div>
            </div>
        <?php elseif ($whatsapp !== ''): ?>
            <div class="hero-actions">
                <a class="btn btn-primary" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('أريد فتح صفقة وساطة') ?>"
                   target="_blank" rel="noopener">ابدأ صفقة وساطة</a>
                <a class="btn btn-ghost" href="#mediation-steps">كيف تعمل؟</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($order !== null): ?>
    <section class="section">
        <div class="container narrow">
            <div class="outcome-card reveal">
                <h2>صفقتك</h2>
                <table class="kv-public">
                    <tr><td>كود الطلب</td><td dir="ltr"><strong><?= e((string) $order['order_code']) ?></strong></td></tr>
                    <tr><td>الخدمة</td><td><?= e((string) $order['service_name']) ?></td></tr>
                    <tr><td>قيمة الصفقة</td><td class="money"><?= e(number_format((float) $order['total_price'] - (float) $order['mediation_fee'], 2)) ?> <?= e((string) $order['currency']) ?></td></tr>
                    <tr><td>رسوم الوساطة</td><td class="money"><?= e(number_format((float) $order['mediation_fee'], 2)) ?></td></tr>
                </table>
                <p class="form-hint">سيتواصل معك فريق الوساطة لاستكمال بيانات الطرف الآخر وبدء الحجز.</p>
                <?php if ($whatsapp !== ''): ?>
                    <a class="btn btn-primary" target="_blank" rel="noopener"
                       href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('صفقة وساطة للطلب ' . (string) $order['order_code']) ?>">
                        تواصل مع فريق الوساطة
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section" id="mediation-steps">
    <div class="container">
        <div class="section-head centered">
            <span class="mini-label">كيف تعمل</span>
            <h2>خمس خطوات، كل واحدة موثّقة</h2>
        </div>
        <ol class="mediation-steps reveal-stagger">
            <?php foreach ($steps as [$number, $title, $body]): ?>
                <li>
                    <span class="step-num"><?= e($number) ?></span>
                    <div>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($body) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<?php if ($cases): ?>
    <section class="section">
        <div class="container narrow">
            <h2 class="account-section-title">صفقاتك</h2>
            <div class="account-table-wrap reveal">
                <table class="account-table">
                    <thead><tr><th>الكود</th><th>الموضوع</th><th>صفتك</th><th>القيمة</th><th>الحالة</th></tr></thead>
                    <tbody>
                    <?php foreach ($cases as $case): ?>
                        <tr>
                            <td dir="ltr"><?= e((string) $case['case_code']) ?></td>
                            <td><?= e((string) $case['subject']) ?></td>
                            <td><?= $case['party_role'] === 'buyer' ? 'مشترٍ' : 'بائع' ?></td>
                            <td class="money"><?= e(number_format((float) $case['deal_amount'], 2)) ?> <?= e((string) $case['currency']) ?></td>
                            <td><span class="pill"><?= e($statuses[$case['status']] ?? (string) $case['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="container narrow">
        <div class="section-head centered">
            <span class="mini-label">قواعد لا تتغيّر</span>
            <h2>ما تضمنه الوساطة وما لا تضمنه</h2>
        </div>

        <div class="mediation-rules reveal-stagger">
            <article>
                <h3>ما نضمنه</h3>
                <ul class="clean-list">
                    <li>المبلغ لا يصل للبائع قبل التسليم.</li>
                    <li>كل خطوة مسجّلة بتاريخها ومن نفّذها.</li>
                    <li>فترة أمان بعد التسليم قبل أن يصبح التحرير نهائيًا.</li>
                    <li>استرداد كامل إذا لم يتم التسليم المتفق عليه.</li>
                </ul>
            </article>
            <article>
                <h3>ما لا نضمنه</h3>
                <ul class="clean-list">
                    <li>أي اتفاق تم خارج القنوات الرسمية للمنصة.</li>
                    <li>أي تحويل مالي مباشر بين الطرفين دون المرور بالوساطة.</li>
                    <li>ما يخالف شروط المنصة التي يستضيفها الأصل الرقمي.</li>
                </ul>
            </article>
        </div>

        <?php if ($policy !== null && !empty($policy['content'])): ?>
            <article class="content-block reveal">
                <h2><?= e((string) $policy['title']) ?>
                    <?php if (!empty($policy['version'])): ?>
                        <span class="text-muted" style="font-size:.7em;" dir="ltr">v<?= e((string) $policy['version']) ?></span>
                    <?php endif; ?>
                </h2>
                <p><?= nl2br(e((string) $policy['content'])) ?></p>
            </article>
        <?php else: ?>
            <p class="account-note">
                شروط الوساطة الكاملة متاحة في <a href="page.php?slug=mediation_terms">صفحة الشروط</a>.
            </p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
