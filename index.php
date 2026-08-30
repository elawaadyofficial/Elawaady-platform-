<?php
require_once "db_connect.php";
$page_title = "Elawaady XDigital Platform | المتجر الرسمي";
$featured_categories = fetch_all($conn, "SELECT * FROM store_categories WHERE is_active=1 ORDER BY sort_order ASC LIMIT 12");
$featured_services = fetch_all($conn, "SELECT s.*, c.name AS category_name FROM store_services s LEFT JOIN store_categories c ON c.id=s.category_id WHERE s.is_active=1 ORDER BY s.sort_order ASC, s.id DESC LIMIT 12");
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
    <div class="container">
        <div class="section-title-row">
            <div><span class="mini-label">تصفح بسرعة</span><h2>أقسام المتجر</h2></div>
            <a href="categories.php" class="text-link">عرض كل الأقسام ←</a>
        </div>
        <div class="category-scroller">
            <?php foreach ($featured_categories as $cat): ?>
                <a class="category-tile" href="subcategories.php?category_id=<?= e($cat['id']) ?>">
                    <div class="category-art"><span><?= e($cat['icon']) ?></span></div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p><?= e($cat['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section products-section" id="featured">
    <div class="container">
        <div class="section-title-row">
            <div><span class="mini-label hot">مختاراتنا</span><h2>الأكثر طلبًا 🔥</h2></div>
            <a href="categories.php" class="text-link">عرض المزيد ←</a>
        </div>
        <div class="product-grid">
            <?php foreach (array_slice($featured_services, 0, 8) as $service): ?>
                <a class="product-card" href="service.php?id=<?= e($service['id']) ?>">
                    <div class="product-cover">
                        <span class="product-badge"><?= e($service['category_name']) ?></span>
                        <div class="product-placeholder"><?= mb_substr(e($service['name']), 0, 1) ?></div>
                    </div>
                    <div class="product-body">
                        <h3><?= e($service['name']) ?></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="product-bottom">
                            <strong><?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?></strong>
                            <span>اطلب الآن</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section feature-banner-section">
    <div class="container">
        <div class="feature-banner">
            <div>
                <span class="mini-label">واجهة مرنة</span>
                <h2>مكان مخصص للأنيميشن والبنرات</h2>
                <p>الواجهة مجهزة لاستقبال صور وفيديوهات WebP / GIF / MP4 داخل كاروسيل سريع، مع نسب أبعاد منفصلة للديسكتوب والموبايل بدون تشويه الصورة.</p>
            </div>
            <a class="btn btn-light" href="categories.php">استكشف المتجر</a>
        </div>
    </div>
</section>

<section class="section products-section alternate-products">
    <div class="container">
        <div class="section-title-row">
            <div><span class="mini-label">جديد المتجر</span><h2>خدمات ومنتجات جديدة ⚡</h2></div>
        </div>
        <div class="product-grid compact-grid">
            <?php foreach (array_slice($featured_services, 8, 4) as $service): ?>
                <a class="product-card" href="service.php?id=<?= e($service['id']) ?>">
                    <div class="product-cover small-cover"><div class="product-placeholder"><?= mb_substr(e($service['name']), 0, 1) ?></div></div>
                    <div class="product-body"><h3><?= e($service['name']) ?></h3><div class="product-bottom"><strong><?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?></strong><span>التفاصيل</span></div></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

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
        <div><span class="mini-label">PAYMENTS</span><h3>وسائل الدفع</h3><p>المكان جاهز لإضافة الشعارات الأصلية التي سترفعها للمتجر.</p></div>
        <div class="payment-placeholders" aria-label="وسائل الدفع القادمة">
            <span>VISA</span><span>MC</span><span>InstaPay</span><span>Wallet</span><span>Binance Pay</span><span>Kashier</span>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
