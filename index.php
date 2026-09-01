<?php
require_once "db_connect.php";
require_once "banner.php";
require_once "sections.php";
require_once "homepage.php";
require_once "lib/media.php";
require_once "settings_helper.php";

$page_title = "Elawaady XDigital Platform | المتجر الرسمي";

/*
|--------------------------------------------------------------------------
| The homepage is a loop over rows, not a fixed page.
|--------------------------------------------------------------------------
| homepage_sections decides which bands appear, in what order, in what shape
| and how many cards each holds. Every block below is a renderer keyed by
| section_type; the loop at the bottom walks the table and calls them.
|
| Adding a band, hiding one, reordering the store or changing a row's card
| count is an edit in the dashboard. Nothing here needs to change for it.
*/

$exd_sections = exd_homepage_sections($conn);

// One query feeds the whole varied rhythm, grouped in PHP rather than a query
// per category.
$section_categories   = fetch_all($conn, "SELECT * FROM store_categories WHERE is_active=1 AND show_home=1 ORDER BY sort_order ASC, id ASC LIMIT 8");
$section_services     = fetch_all($conn, "SELECT * FROM store_services WHERE is_active=1 ORDER BY sort_order ASC, id DESC");
$services_by_category = [];
foreach ($section_services as $row) {
    $services_by_category[(int) $row['category_id']][] = $row;
}

$exd_currency = setting('default_currency', 'EGP');

/** One service card, in the wide product shape. */
function exd_product_card(array $service, string $currency): string
{
    $media     = trim((string) ($service['main_image'] ?? $service['image'] ?? ''));
    $path      = parse_url($media, PHP_URL_PATH) ?: '';
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $isVideo   = in_array($extension, ['mp4', 'webm'], true);

    $href      = 'service.php?id=' . (int) $service['id'];
    $primary   = trim((string) ($service['primary_button_label'] ?? '')) ?: 'اشتري الآن';
    $secondary = trim((string) ($service['secondary_button_label'] ?? '')) ?: 'التفاصيل';

    $showPrice = (int) ($service['show_price'] ?? 1) === 1;
    $askPrice  = (int) ($service['ask_for_price'] ?? 0) === 1;
    $price     = (float) ($service['price'] ?? 0);
    $was       = (float) ($service['old_price'] ?? 0);

    $priceHtml = ($askPrice || !$showPrice || $price <= 0)
        ? '<strong>حسب الطلب</strong>'
        : '<strong>' . e(number_format($price, 2)) . ' ' . e((string) ($service['currency'] ?: $currency)) . '</strong>'
          . ($was > $price ? ' <s class="exd-tile__was">' . e(number_format($was, 2)) . '</s>' : '');

    $out = '<article class="product-card">';
    $out .= '<div class="product-cover">';
    if (!empty($service['badge'])) {
        $out .= '<span class="product-badge">' . e((string) $service['badge']) . '</span>';
    } elseif (!empty($service['category_name'])) {
        $out .= '<span class="product-badge">' . e((string) $service['category_name']) . '</span>';
    }
    $out .= '<div class="exd-media">';
    if ($media !== '' && $isVideo) {
        $out .= '<video src="' . e($media) . '" muted loop playsinline preload="metadata" aria-label="'
              . e((string) $service['name']) . '"></video>';
    } elseif ($media !== '') {
        $out .= '<img src="' . e($media) . '" alt="' . e((string) $service['name'])
              . '" loading="lazy" decoding="async">';
    } else {
        // No artwork is a correct state, not a gap to fill with a stand-in.
        $out .= '<div class="exd-media-fallback"><span>' . mb_substr(e((string) $service['name']), 0, 1) . '</span></div>';
    }
    $out .= '</div></div>';

    $out .= '<div class="product-body">';
    $out .= '<h3><a class="card-link" href="' . e($href) . '">' . e((string) $service['name']) . '</a></h3>';
    $out .= '<p>' . e(mb_strimwidth((string) ($service['description'] ?? ''), 0, 110, '…')) . '</p>';
    $out .= '<div class="product-bottom">' . $priceHtml . '</div>';
    $out .= '<div class="card-actions">';
    $out .= '<a class="btn btn-secondary btn-card btn-card--minor" href="' . e($href) . '">' . e($secondary) . '</a>';
    $out .= '<a class="btn btn-primary btn-card" href="' . e($href) . '">' . e($primary) . '</a>';
    return $out . '</div></div></article>';
}

require_once "header.php";
?>

<?php foreach ($exd_sections as $exd_section):
    $exd_type   = (string) $exd_section['section_type'];
    $exd_layout = (string) $exd_section['layout'];
    $exd_reveal = exd_section_reveal($exd_section);
?>

<?php if ($exd_type === 'hero'): ?>

    <?= exd_ticker([
        'دعم فني على مدار الساعة 24/7',
        'وساطة آمنة بضمان مالي كامل',
        'الوسيط لخدمات السوشيال ميديا — ترخيص ' . setting('licence_number', '767-766-857'),
        'حسابات موثقة وتسليم مرتب',
    ]) ?>

    <?php $exd_slides = exd_carousel_slides($conn); ?>
    <section class="hero-showcase">
        <div class="container">
            <div class="hero-carousel <?= $exd_slides ? 'hero-carousel--art' : '' ?>" data-carousel>
                <?php if ($exd_slides): ?>
                    <?php foreach ($exd_slides as $exd_i => $exd_slide): ?>
                        <article class="hero-slide hero-slide--art <?= $exd_i === 0 ? 'is-active' : '' ?>">
                            <a class="hero-slide__link" href="<?= e(exd_carousel_href($exd_slide)) ?>">
                                <picture>
                                    <?php if (!empty($exd_slide['image_mobile'])): ?>
                                        <source media="(max-width: 640px)" srcset="<?= e((string) $exd_slide['image_mobile']) ?>">
                                    <?php endif; ?>
                                    <img src="<?= e((string) $exd_slide['image']) ?>"
                                         alt="<?= e((string) ($exd_slide['title_ar'] ?? '')) ?>"
                                         <?= $exd_i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                                </picture>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
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
                <?php endif; ?>

                <?php if (count($exd_slides) > 1): ?>
                    <button class="carousel-arrow prev" type="button" data-prev aria-label="السابق">‹</button>
                    <button class="carousel-arrow next" type="button" data-next aria-label="التالي">›</button>
                    <div class="carousel-dots" data-dots></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?= exd_brand_strip($conn) ?>

    <section class="quick-benefits">
        <div class="container benefits-grid">
            <div><span>⚡</span><b>تنفيذ سريع</b><small>حسب نوع الخدمة</small></div>
            <div><span>🛡️</span><b>تجربة آمنة</b><small>بيانات وطلبات منظمة</small></div>
            <div><span>💬</span><b>دعم مباشر</b><small>متابعة قبل وبعد الطلب</small></div>
            <div><span>💳</span><b>دفع مرن</b><small>وسائل متعددة</small></div>
        </div>
    </section>

<?php elseif ($exd_type === 'categories'): ?>

    <?php $exd_cats = exd_section_categories($conn, $exd_section); ?>
    <?php if ($exd_cats): ?>
        <section class="section category-section">
            <?= exd_section_heading($exd_section) ?>
            <?= exd_key_grid($exd_cats, fn($c) => 'subcategories.php?category_id=' . (int) $c['id']) ?>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'banners'): ?>

    <?php
    // A banner row renders nothing until artwork is pointed at its placement,
    // so the page never shows an empty frame.
    $exd_placement = (string) ($exd_section['source_filter'] ?? '');
    $exd_banners   = $exd_placement === 'home_mid'
        ? exd_banners_for($conn, 'home_mid')
        : ($exd_placement === 'home_bottom'
            ? exd_banners_for($conn, 'home_bottom')
            : exd_banners_for($conn, $exd_placement));
    ?>
    <?php if (trim($exd_banners) !== ''): ?>
        <section class="exd-band exd-band--banner <?= e($exd_reveal) ?>">
            <div class="exd-rail exd-rail--banner"><?= $exd_banners ?></div>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'services'): ?>

    <?php $exd_items = exd_section_services($conn, $exd_section); ?>
    <?php if ($exd_items): ?>
        <section class="section products-section <?= e($exd_reveal) ?>"
                 id="<?= e((string) $exd_section['section_key']) ?>">
            <?= exd_section_heading($exd_section) ?>
            <?php if ($exd_layout === 'product'): ?>
                <div class="exd-rail exd-rail--product">
                    <?php foreach ($exd_items as $exd_item): ?><?= exd_product_card($exd_item, $exd_currency) ?><?php endforeach; ?>
                </div>
            <?php elseif ($exd_layout === 'keys'): ?>
                <?= exd_key_grid($exd_items, fn($s) => 'service.php?id=' . (int) $s['id'], '', 'exd-keys--square') ?>
            <?php elseif ($exd_layout === 'duo'): ?>
                <?= exd_duo_grid($exd_items, fn($s) => 'service.php?id=' . (int) $s['id'], 'اكتشف المزيد', 'exd-duo--square') ?>
            <?php else: ?>
                <div class="exd-rail exd-rail--poster"<?= media_row_ratio_style($exd_items) ?>>
                    <?php foreach ($exd_items as $exd_item): ?><?= exd_tile($exd_item) ?><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'category_bands'): ?>

    <?= exd_category_bands($conn, $section_categories, $services_by_category) ?>

<?php elseif ($exd_type === 'mediation'): ?>

    <?php if (setting('mediation_enabled', '1') === '1'): ?>
        <section class="section feature-banner-section exd-band--banner <?= e($exd_reveal) ?>">
            <div class="exd-railhead">
                <div class="feature-banner">
                    <div>
                        <h2><?= e((string) $exd_section['title']) ?></h2>
                        <p><?= e((string) ($exd_section['subtitle'] ?: 'احتجاز المبلغ حتى اكتمال الصفقة · فترة أمان بعد التسليم · ترخيص ' . setting('licence_number', '767-766-857'))) ?></p>
                    </div>
                    <a class="btn btn-light" href="<?= e((string) ($exd_section['link_url'] ?: 'mediation.php')) ?>">
                        <?= e((string) ($exd_section['link_label'] ?: 'ابدأ صفقة')) ?> ←
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'reviews'): ?>

    <?php $exd_reviews = exd_section_reviews($conn, $exd_section); ?>
    <?php if ($exd_reviews): ?>
        <section class="section reviews-section <?= e($exd_reveal) ?>">
            <div class="container">
                <div class="section-head centered">
                    <span class="mini-label"><?= e((string) ($exd_section['subtitle'] ?: 'ثقة العملاء')) ?></span>
                    <h2><?= e((string) $exd_section['title']) ?></h2>
                </div>
                <div class="reviews-grid">
                    <?php foreach ($exd_reviews as $exd_review): ?>
                        <article class="review-card">
                            <div class="stars"><?= str_repeat('★', max(1, min(5, (int) $exd_review['rating']))) ?></div>
                            <p><?= e(mb_strimwidth((string) ($exd_review['body'] ?? ''), 0, 180, '…')) ?></p>
                            <b><?= e((string) ($exd_review['display_name'] ?: 'عميل')) ?></b>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'faq'): ?>

    <?php $exd_faq = exd_home_faq($conn, (int) $exd_section['item_limit']); ?>
    <?php if ($exd_faq): ?>
        <section class="section faq-section <?= e($exd_reveal) ?>">
            <div class="container faq-layout">
                <div class="faq-intro">
                    <span class="mini-label"><?= e((string) ($exd_section['subtitle'] ?: 'مساعدة سريعة')) ?></span>
                    <h2><?= e((string) $exd_section['title']) ?></h2>
                </div>
                <div class="faq-list">
                    <?php foreach ($exd_faq as $exd_i => $exd_entry): ?>
                        <details <?= $exd_i === 0 ? 'open' : '' ?>>
                            <summary><?= e((string) $exd_entry['question']) ?></summary>
                            <p><?= nl2br(e((string) $exd_entry['answer'])) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'payment'): ?>

    <?= exd_title_banner('طرق دفع آمنة ومتعددة', 'ادفع بالطريقة التي تناسبك', 'contact.php') ?>

    <section class="payment-strip <?= e($exd_reveal) ?>">
        <div class="container payment-inner">
            <div>
                <span class="mini-label">PAYMENTS</span>
                <h3>وسائل الدفع</h3>
                <p>ادفع بالطريقة التي تناسبك — محافظ إلكترونية وبطاقات وتحويل بنكي.</p>
            </div>
            <div class="payment-placeholders" aria-label="وسائل الدفع المتاحة">
                <?php foreach (exd_payment_logos() as $exd_logo): ?>
                    <span><img src="<?= e($exd_logo['src']) ?>" alt="<?= e($exd_logo['alt']) ?>"
                               width="32" height="45" loading="lazy" decoding="async"></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<?php elseif ($exd_type === 'html'): ?>

    <?php if (trim((string) ($exd_section['subtitle'] ?? '')) !== ''): ?>
        <section class="section <?= e($exd_reveal) ?>">
            <div class="container">
                <?= exd_section_heading($exd_section) ?>
                <p class="section-free-text"><?= nl2br(e((string) $exd_section['subtitle'])) ?></p>
            </div>
        </section>
    <?php endif; ?>

<?php elseif ($exd_type === 'deals'): ?>

    <?= exd_deals_band($conn) ?>

<?php endif; ?>

<?php endforeach; ?>

<?php require_once "footer.php"; ?>
