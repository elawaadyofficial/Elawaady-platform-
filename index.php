<?php
require_once "db_connect.php";
require_once "banner.php";
require_once "sections.php";
$page_title = "Elawaady XDigital Platform | المتجر الرسمي";
$featured_categories = fetch_all($conn, "SELECT * FROM store_categories WHERE is_active=1 ORDER BY sort_order ASC LIMIT 12");
$featured_services = fetch_all($conn, "SELECT s.*, c.name AS category_name FROM store_services s LEFT JOIN store_categories c ON c.id=s.category_id WHERE s.is_active=1 ORDER BY s.sort_order ASC, s.id DESC LIMIT 12");

// One query for the whole varied rhythm below, grouped in PHP rather than a
// query per category.
$section_categories = fetch_all($conn, "SELECT * FROM store_categories WHERE is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 8");
$section_services = fetch_all($conn, "SELECT * FROM store_services WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
$services_by_category = [];
foreach ($section_services as $row) {
    $services_by_category[(int) $row['category_id']][] = $row;
}
require_once "header.php";
?>

<section class="hero-showcase">
    <div class="container">
        <div class="hero-carousel" data-carousel>
            <article class="hero-slide is-active hero-slide-purple">
                <div class="hero-content">
                    <span class="eyebrow">ELAWAADY XDIGITAL</span>
                    <h1>كل خدماتك الرقمية<br><span>في متجر واحد</span></h1>
                    <p>اشتراكات ومنتجات رقمية، خدمات منصات التواصل، الذكاء الاصطناعي، التوثيق، الألعاب والبطاقات بتجربة شراء مرتبة وسريعة.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="categories.php">تصفح الأقسام</a>
                        <a class="btn btn-ghost" href="#featured">الأكثر طلبًا</a>
                    </div>
                </div>
                <div class="hero-visual" aria-hidden="true">
                    <div class="visual-orbit orbit-a"></div>
                    <div class="visual-orbit orbit-b"></div>
                    <div class="hero-device">EXD</div>
                    <span class="float-chip chip-one">AI</span>
                    <span class="float-chip chip-two">PRO</span>
                    <span class="float-chip chip-three">24/7</span>
                </div>
            </article>

            <article class="hero-slide hero-slide-blue">
                <div class="hero-content">
                    <span class="eyebrow">DIGITAL SUBSCRIPTIONS</span>
                    <h2>اشتراكات رقمية<br><span>بتقسيمة واضحة</span></h2>
                    <p>قسمنا المنتجات حسب المنصة ونوع الاشتراك والمدة، عشان توصل للخدمة المناسبة بسرعة من الموبايل أو الكمبيوتر.</p>
                    <div class="hero-actions"><a class="btn btn-primary" href="categories.php">شاهد الاشتراكات</a></div>
                </div>
                <div class="hero-visual"><div class="hero-device glass-device">PLUS</div></div>
            </article>

            <article class="hero-slide hero-slide-gold">
                <div class="hero-content">
                    <span class="eyebrow">PREMIUM DIGITAL EXPERIENCE</span>
                    <h2>منصات، ألعاب، بطاقات<br><span>وخدمات احترافية</span></h2>
                    <p>واجهة كاروسيل جاهزة لاستقبال صور الأنيميشن والبنرات الخاصة بالمتجر مع ضبط تلقائي للمقاسات.</p>
                    <div class="hero-actions"><a class="btn btn-primary" href="contact.php">اطلب خدمة</a></div>
                </div>
                <div class="hero-visual"><div class="hero-device gold-device">X</div></div>
            </article>

            <button class="carousel-arrow prev" type="button" data-prev aria-label="السابق">‹</button>
            <button class="carousel-arrow next" type="button" data-next aria-label="التالي">›</button>
            <div class="carousel-dots" data-dots></div>
        </div>
    </div>
</section>

<section class="quick-benefits">
    <div class="container benefits-grid">
        <div><span>⚡</span><b>تنفيذ سريع</b><small>حسب نوع الخدمة</small></div>
        <div><span>🛡️</span><b>تجربة آمنة</b><small>بيانات وطلبات منظمة</small></div>
        <div><span>💬</span><b>دعم مباشر</b><small>متابعة قبل وبعد الطلب</small></div>
        <div><span>💳</span><b>دفع مرن</b><small>وسائل متعددة قريبًا</small></div>
    </div>
</section>

<section class="section category-section">
    <div class="exd-railhead">
        <div class="section-title-row">
            <div><span class="mini-label">تصفح بسرعة</span><h2>أقسام المتجر</h2></div>
            <a href="categories.php" class="text-link">عرض كل الأقسام ←</a>
        </div>
    </div>
    <div class="category-scroller exd-rail exd-rail--cat">
            <?php foreach ($featured_categories as $cat): ?>
                <a class="category-tile" href="subcategories.php?category_id=<?= e($cat['id']) ?>">
                    <div class="category-art"><span><?= e($cat['icon']) ?></span></div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p><?= e($cat['description']) ?></p>
                </a>
        <?php endforeach; ?>
    </div>
</section>

<?php
// A banner row sits between the grids. It renders nothing until artwork is
// uploaded and pointed at the home_mid placement, so the page never shows an
// empty frame.
$home_mid = exd_banners_for($conn, 'home_mid');
if ($home_mid !== ''):
?>
<section class="exd-band exd-band--banner">
    <div class="exd-rail exd-rail--banner"><?= $home_mid ?></div>
</section>
<?php endif; ?>

<section class="section products-section" id="featured">
    <div class="exd-railhead">
        <div class="section-title-row">
            <div><span class="mini-label hot">مختاراتنا</span><h2>الأكثر طلبًا 🔥</h2></div>
            <a href="categories.php" class="text-link">عرض المزيد ←</a>
        </div>
    </div>
    <div class="exd-rail exd-rail--product">
        <?php foreach ($featured_services as $service): ?>
                <?php
                $service_media = trim((string)($service['image'] ?? ''));
                $service_media_path = parse_url($service_media, PHP_URL_PATH) ?: '';
                $service_media_ext = strtolower(pathinfo($service_media_path, PATHINFO_EXTENSION));
                $service_media_is_video = in_array($service_media_ext, ['mp4', 'webm'], true);
                ?>
                <?php
                $service_href = "service.php?id=" . (int) $service['id'];
                $order_link = trim((string) ($service['service_link'] ?? ''));
                $order_external = $order_link !== '';
                $order_href = $order_external ? $order_link : "contact.php";
                ?>
                <article class="product-card">
                    <div class="product-cover">
                        <span class="product-badge"><?= e($service['category_name']) ?></span>
                        <div class="exd-media">
                            <?php if ($service_media !== '' && $service_media_is_video): ?>
                                <video src="<?= e($service_media) ?>" muted loop playsinline preload="metadata" aria-label="<?= e($service['name']) ?>"></video>
                            <?php elseif ($service_media !== ''): ?>
                                <img src="<?= e($service_media) ?>" alt="<?= e($service['name']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="exd-media-fallback"><span><?= mb_substr(e($service['name']), 0, 1) ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="product-body">
                        <h3><a class="card-link" href="<?= e($service_href) ?>"><?= e($service['name']) ?></a></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="product-bottom">
                            <strong><?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?></strong>
                        </div>
                        <div class="card-actions">
                            <a class="btn btn-secondary btn-card btn-card--minor" href="<?= e($service_href) ?>">التفاصيل</a>
                            <a class="btn btn-primary btn-card" href="<?= e($order_href) ?>"<?= $order_external ? ' target="_blank" rel="noopener"' : '' ?>>شراء الآن</a>
                        </div>
                    </div>
                </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section feature-banner-section exd-band--banner">
    <div class="exd-railhead">
        <div class="feature-banner">
            <div>
                <h2>وساطة آمنة بضمان مالي كامل</h2>
                <p>احتجاز المبلغ حتى اكتمال الصفقة · حق اعتراض 48 ساعة · عمولة من 3%</p>
            </div>
            <a class="btn btn-light" href="contact.php">ابدأ صفقة ←</a>
        </div>
    </div>
</section>

<?= exd_category_bands($conn, $section_categories, $services_by_category) ?>

<?php
$home_bottom = exd_banners_for($conn, 'home_bottom');
if ($home_bottom !== ''):
?>
<section class="exd-band exd-band--banner">
    <div class="exd-rail exd-rail--banner"><?= $home_bottom ?></div>
</section>
<?php endif; ?>

<?= exd_deals_band($conn) ?>

<section class="section reviews-section">
    <div class="container">
        <div class="section-head centered"><span class="mini-label">ثقة العملاء</span><h2>آراء العملاء</h2><p>قسم تقييمات جاهز للربط بالتقييمات الحقيقية من لوحة الإدارة.</p></div>
        <div class="reviews-grid">
            <article class="review-card"><div class="stars">★★★★★</div><p>تجربة مرتبة وسريعة، كل تفاصيل الخدمة واضحة من البداية.</p><b>عميل Elawaady XDigital</b></article>
            <article class="review-card"><div class="stars">★★★★★</div><p>سهولة في الوصول للقسم المطلوب ودعم متابع للطلب.</p><b>عميل متجر</b></article>
            <article class="review-card"><div class="stars">★★★★★</div><p>واجهة بسيطة والمنتجات الرقمية متقسمة بشكل مريح جدًا.</p><b>عميل متكرر</b></article>
        </div>
    </div>
</section>

<section class="section stats-section">
    <div class="container stats-panel">
        <div><strong>+40</strong><span>قسم قابل للتوسع</span></div>
        <div><strong>24/7</strong><span>واجهة متاحة دائمًا</span></div>
        <div><strong>100%</strong><span>تجربة Responsive</span></div>
        <div><strong>EXD</strong><span>هوية رقمية موحدة</span></div>
    </div>
</section>

<section class="section faq-section">
    <div class="container faq-layout">
        <div class="faq-intro"><span class="mini-label">مساعدة سريعة</span><h2>الأسئلة الشائعة 💬</h2><p>نفس روح المتاجر الكبيرة لكن بهوية Elawaady XDigital وتقسيمة أسهل في التصفح.</p></div>
        <div class="faq-list">
            <details open><summary>كيف أطلب من المتجر؟</summary><p>اختر القسم ثم الخدمة المناسبة، راجع التفاصيل والمتطلبات، وبعدها أكمل الطلب بالطريقة المتاحة للخدمة.</p></details>
            <details><summary>هل كل الخدمات تنفيذها فوري؟</summary><p>لا. مدة التنفيذ تختلف حسب نوع المنتج أو الخدمة، وسيتم توضيحها داخل صفحة كل خدمة.</p></details>
            <details><summary>هل المتجر يعمل على الموبايل؟</summary><p>نعم، الواجهة مصممة Mobile First مع كاروسيل وبطاقات وأقسام تتكيف تلقائيًا مع حجم الشاشة.</p></details>
            <details><summary>كيف أتواصل مع الدعم؟</summary><p>من صفحة التواصل أو زر الدعم في أعلى المتجر، وسيتم ربط القنوات الرسمية بالنسخة النهائية.</p></details>
        </div>
    </div>
</section>

<section class="payment-strip">
    <div class="container payment-inner">
        <div><span class="mini-label">PAYMENTS</span><h3>وسائل الدفع</h3><p>ادفع بالطريقة التي تناسبك — محافظ إلكترونية وبطاقات وتحويل بنكي.</p></div>
        <div class="payment-placeholders" aria-label="وسائل الدفع المتاحة">
            <span><img src="assets/payments/01-instapay.webp" alt="InstaPay" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/02-fawry.webp" alt="فوري" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/03-paypal.webp" alt="PayPal" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/04-gpay.webp" alt="Google Pay" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/05-vodafone-cash.webp" alt="فودافون كاش" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/06-orange-cash.webp" alt="أورنج كاش" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/07-etisalat-cash.webp" alt="اتصالات كاش" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/08-stc-pay.webp" alt="stc pay" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/09-we-pay.webp" alt="WE Pay" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/10-apple-pay.webp" alt="Apple Pay" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/11-mastercard.webp" alt="Mastercard" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/12-mada.webp" alt="مدى" width="32" height="45" loading="lazy" decoding="async"></span>
            <span><img src="assets/payments/13-bank-transfer.webp" alt="تحويل بنكي" width="32" height="45" loading="lazy" decoding="async"></span>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
