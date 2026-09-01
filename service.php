<?php
require_once "db_connect.php";
require_once "lib/auth.php";
require_once "lib/pricing.php";
require_once "settings_helper.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// The customer-facing query names its columns. It does not select supplier_id,
// supplier_name, supplier_phone, supplier_cost or any commission field, so a
// supplier's identity and the store's margin cannot reach this page at all —
// the protection is in the query, not in remembering not to print something.
$service = fetch_one($conn, "
    SELECT s.id, s.name, s.name_en, s.description, s.description_full, s.features,
           s.requirements, s.execution_time, s.terms, s.refund_policy, s.important_note,
           s.price, s.old_price, s.currency, s.show_price, s.ask_for_price,
           s.image, s.main_image, s.icon_image, s.banner_image, s.gallery_images,
           s.badge, s.service_link, s.status, s.availability, s.stock,
           s.min_quantity, s.max_quantity, s.quantity_step,
           s.price_mode, s.provider_base_price, s.provider_price_per, s.profit_percent,
           s.mediation_enabled, s.mediation_type, s.mediation_fee, s.mediation_fee_mode,
           s.show_mediation_terms, s.mediation_safety_days,
           s.primary_button_label, s.secondary_button_label,
           s.order_type, s.order_link, s.whatsapp_number,
           s.payment_method, s.post_order_contact, s.allow_wallet_payment,
           s.requires_approval, s.requires_advance_payment,
           s.buy_now_enabled, s.cart_enabled, s.seo_title, s.seo_description,
           s.category_id, s.subcategory_id,
           c.name AS category_name, sc.name AS subcategory_name
      FROM store_services s
      LEFT JOIN store_categories c     ON c.id = s.category_id
      LEFT JOIN store_subcategories sc ON sc.id = s.subcategory_id
     WHERE s.id = ? AND s.is_active = 1
", "i", $id);

if (!$service) {
    http_response_code(404);
    $page_title = 'الخدمة غير موجودة';
    require_once "header.php";
    echo '<section class="page-hero"><div class="container">'
       . '<h1>الخدمة غير موجودة</h1>'
       . '<p>الرابط قديم أو الخدمة لم تعد متاحة.</p>'
       . '<a class="btn btn-primary" href="categories.php">تصفح الأقسام</a>'
       . '</div></section>';
    require_once "footer.php";
    exit;
}

$page_title       = ($service['seo_title'] ?: $service['name']) . ' | Elawaady XDigital';
$meta_description = (string) ($service['seo_description'] ?: $service['description']);

$user     = auth_user();
$currency = (string) ($service['currency'] ?: setting('default_currency', 'EGP'));

$minQuantity = max(1, (int) $service['min_quantity']);
$quantity    = max($minQuantity, (int) ($_GET['qty'] ?? $minQuantity));
$quote       = service_quote($conn, $service, $quantity);

$showPrice = (int) $service['show_price'] === 1 && (int) $service['ask_for_price'] === 0 && $quote['unit_price'] > 0;

$options = fetch_all(
    $conn,
    'SELECT id, option_key, label, input_type, is_required, help_text
       FROM service_options WHERE service_id = ? ORDER BY sort_order, id',
    'i',
    (int) $service['id']
);

$optionValues = [];
if ($options) {
    foreach (fetch_all(
        $conn,
        'SELECT ov.option_id, ov.label, ov.value_key, ov.price_delta, ov.delta_mode, ov.is_default
           FROM service_option_values ov
           JOIN service_options o ON o.id = ov.option_id
          WHERE o.service_id = ? AND ov.is_active = 1
          ORDER BY ov.sort_order, ov.id',
        'i',
        (int) $service['id']
    ) as $row) {
        $optionValues[(int) $row['option_id']][] = $row;
    }
}

$gallery = fetch_all(
    $conn,
    'SELECT image, caption, media_type FROM service_gallery WHERE service_id = ? ORDER BY sort_order, id LIMIT 12',
    'i',
    (int) $service['id']
);

$faq = fetch_all(
    $conn,
    'SELECT question, answer FROM service_faq WHERE service_id = ? AND is_active = 1 ORDER BY sort_order, id',
    'i',
    (int) $service['id']
);

$reviews = fetch_all(
    $conn,
    "SELECT r.rating, r.title, r.body, COALESCE(u.name, r.author_name) AS display_name, r.created_at
       FROM reviews r
       LEFT JOIN platform_users u ON u.id = r.user_id
      WHERE r.service_id = ? AND r.status = 'approved'
      ORDER BY r.id DESC LIMIT 8",
    'i',
    (int) $service['id']
);

$ratingRow = fetch_one(
    $conn,
    "SELECT AVG(rating) AS avg_rating, COUNT(*) AS n FROM reviews WHERE service_id = ? AND status = 'approved'",
    'i',
    (int) $service['id']
);

$related = fetch_all(
    $conn,
    'SELECT s.id, s.name, s.price, s.old_price, s.currency, s.image, s.main_image
       FROM store_services s
      WHERE s.is_active = 1 AND s.category_id = ? AND s.id <> ?
      ORDER BY s.sort_order, s.id DESC LIMIT 10',
    'ii',
    (int) $service['category_id'],
    (int) $service['id']
);

/** Resolve the hero media and whether it is a video. */
$heroMedia   = trim((string) ($service['main_image'] ?: $service['image'] ?: ''));
$heroPath    = $heroMedia !== '' ? (parse_url($heroMedia, PHP_URL_PATH) ?: $heroMedia) : '';
$heroIsVideo = in_array(strtolower(pathinfo($heroPath, PATHINFO_EXTENSION)), ['mp4', 'webm'], true);

/** A text block written as lines becomes a list; anything else stays prose. */
function service_lines(?string $text): array {
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn(string $l): bool => $l !== ''));
}

require_once "header.php";
?>

<nav class="breadcrumb" aria-label="مسار التصفح">
    <div class="container">
        <a href="index.php">الرئيسية</a>
        <span>/</span>
        <a href="subcategories.php?category_id=<?= (int) $service['category_id'] ?>"><?= e((string) $service['category_name']) ?></a>
        <?php if ($service['subcategory_name']): ?>
            <span>/</span><span><?= e((string) $service['subcategory_name']) ?></span>
        <?php endif; ?>
    </div>
</nav>

<section class="service-detail">
    <div class="container service-layout">

        <div class="service-visual reveal">
            <div class="exd-media exd-media--service">
                <?php if ($heroMedia !== '' && $heroIsVideo): ?>
                    <video src="<?= e($heroMedia) ?>" preload="metadata" playsinline muted loop controls></video>
                <?php elseif ($heroMedia !== ''): ?>
                    <img src="<?= e($heroMedia) ?>" alt="<?= e((string) $service['name']) ?>" fetchpriority="high" decoding="async">
                <?php else: ?>
                    <div class="exd-media-fallback" aria-hidden="true"><span><?= mb_substr(e((string) $service['name']), 0, 1) ?></span></div>
                <?php endif; ?>
            </div>

            <?php if ($gallery): ?>
                <div class="service-gallery reveal-stagger">
                    <?php foreach ($gallery as $shot): ?>
                        <figure class="exd-media">
                            <?php if ($shot['media_type'] === 'video'): ?>
                                <video src="<?= e((string) $shot['image']) ?>" muted loop playsinline preload="metadata"></video>
                            <?php else: ?>
                                <img src="<?= e((string) $shot['image']) ?>" alt="<?= e((string) ($shot['caption'] ?? $service['name'])) ?>" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="service-buy reveal">
            <?php if ($service['badge']): ?>
                <span class="pill pill--badge"><?= e((string) $service['badge']) ?></span>
            <?php endif; ?>

            <h1><?= e((string) $service['name']) ?></h1>

            <?php if ((int) ($ratingRow['n'] ?? 0) > 0): ?>
                <div class="service-rating">
                    <span class="stars"><?= str_repeat('★', (int) round((float) $ratingRow['avg_rating'])) ?></span>
                    <span class="text-muted"><?= e(number_format((float) $ratingRow['avg_rating'], 1)) ?>
                        من <?= (int) $ratingRow['n'] ?> تقييم</span>
                </div>
            <?php endif; ?>

            <p class="service-lede"><?= e((string) $service['description']) ?></p>

            <div class="service-price" id="service-price"
                 data-unit="<?= e((string) $quote['unit_price']) ?>"
                 data-currency="<?= e($currency) ?>">
                <?php if ($showPrice): ?>
                    <strong data-price-total><?= e(number_format($quote['total'], 2)) ?></strong>
                    <span class="service-currency"><?= e($currency) ?></span>
                    <?php if ((float) $service['old_price'] > $quote['unit_price']): ?>
                        <s><?= e(number_format((float) $service['old_price'], 2)) ?></s>
                    <?php endif; ?>
                <?php else: ?>
                    <strong>السعر حسب الطلب</strong>
                <?php endif; ?>
            </div>

            <?php if ($service['availability'] !== 'available' || ($service['stock'] !== null && (int) $service['stock'] <= 0)): ?>
                <div class="alert alert-info"><span>◷</span><div>هذه الخدمة غير متاحة حاليًا. تواصل مع الدعم لمعرفة موعد توفرها.</div></div>
            <?php endif; ?>

            <form class="service-order" method="post" action="order_create.php">
                <?= csrf_field() ?>
                <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">

                <?php if ((int) $service['max_quantity'] > 1): ?>
                    <div class="form-group">
                        <label class="form-label" for="qty">الكمية</label>
                        <input class="form-input" type="number" id="qty" name="qty" dir="ltr"
                               value="<?= (int) $quantity ?>"
                               min="<?= (int) $service['min_quantity'] ?>"
                               max="<?= (int) $service['max_quantity'] ?>"
                               step="<?= (int) $service['quantity_step'] ?>"
                               data-quantity>
                        <small class="form-hint">
                            من <?= e(number_format((int) $service['min_quantity'])) ?>
                            إلى <?= e(number_format((int) $service['max_quantity'])) ?>
                        </small>
                    </div>
                <?php endif; ?>

                <?php foreach ($options as $option): ?>
                    <?php $values = $optionValues[(int) $option['id']] ?? []; ?>
                    <?php if (!$values && in_array($option['input_type'], ['select', 'radio'], true)) { continue; } ?>
                    <div class="form-group">
                        <label class="form-label" for="opt-<?= (int) $option['id'] ?>">
                            <?= e((string) $option['label']) ?>
                            <?= (int) $option['is_required'] === 1 ? '<span class="req">*</span>' : '' ?>
                        </label>
                        <?php if (in_array($option['input_type'], ['select', 'radio'], true)): ?>
                            <select class="form-input" id="opt-<?= (int) $option['id'] ?>"
                                    name="options[]" data-option
                                    <?= (int) $option['is_required'] === 1 ? 'required' : '' ?>>
                                <?php if ((int) $option['is_required'] !== 1): ?>
                                    <option value="">— بدون —</option>
                                <?php endif; ?>
                                <?php foreach ($values as $value): ?>
                                    <option value="<?= e((string) $value['value_key']) ?>"
                                            data-delta="<?= e((string) $value['price_delta']) ?>"
                                            data-mode="<?= e((string) $value['delta_mode']) ?>"
                                            <?= (int) $value['is_default'] === 1 ? 'selected' : '' ?>>
                                        <?= e((string) $value['label']) ?>
                                        <?php if ((float) $value['price_delta'] > 0): ?>
                                            (+<?= e(number_format((float) $value['price_delta'], 2)) ?><?= $value['delta_mode'] === 'percent' ? '%' : '' ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input class="form-input" type="<?= $option['input_type'] === 'url' ? 'url' : ($option['input_type'] === 'number' ? 'number' : 'text') ?>"
                                   id="opt-<?= (int) $option['id'] ?>"
                                   name="option_text[<?= e((string) $option['option_key']) ?>]"
                                   <?= (int) $option['is_required'] === 1 ? 'required' : '' ?>>
                        <?php endif; ?>
                        <?php if (!empty($option['help_text'])): ?>
                            <small class="form-hint"><?= e((string) $option['help_text']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (in_array($service['order_type'], ['smm', 'direct_buy'], true) || $service['price_mode'] === 'provider_auto'): ?>
                    <div class="form-group">
                        <label class="form-label" for="target_url">رابط الهدف</label>
                        <input class="form-input" type="url" id="target_url" name="target_url" dir="ltr"
                               placeholder="https://...">
                        <small class="form-hint">رابط الحساب أو المنشور الذي تريد تنفيذ الخدمة عليه.</small>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="customer_notes">ملاحظات</label>
                    <textarea class="form-input form-textarea" id="customer_notes" name="customer_notes"
                              rows="3" placeholder="أي تفاصيل تريد أن نعرفها"></textarea>
                </div>

                <?php if ((int) $service['mediation_enabled'] === 1 && setting('mediation_enabled', '1') === '1'): ?>
                    <label class="form-check">
                        <input type="checkbox" name="use_mediation" value="1">
                        <span>
                            تنفيذ الطلب عبر <a href="mediation.php">الوساطة الآمنة</a>
                            <?php if ((float) $service['mediation_fee'] > 0): ?>
                                — رسوم <?= e(number_format((float) $service['mediation_fee'], 2)) ?><?= $service['mediation_fee_mode'] === 'percent' ? '%' : ' ' . e($currency) ?>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endif; ?>

                <div class="service-actions">
                    <?php if ((int) $service['buy_now_enabled'] === 1): ?>
                        <button class="btn btn-primary btn-full" type="submit" name="action" value="direct_buy">
                            <?= e((string) ($service['primary_button_label'] ?: 'اشتري الآن')) ?>
                        </button>
                    <?php endif; ?>
                    <?php if ((int) $service['cart_enabled'] === 1): ?>
                        <button class="btn btn-secondary btn-full" type="submit" name="action" value="cart">
                            <?= e((string) ($service['secondary_button_label'] ?: 'أضف إلى السلة')) ?>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($user === null): ?>
                    <p class="form-hint">
                        <a href="login.php?next=service.php%3Fid=<?= (int) $service['id'] ?>">سجّل الدخول</a>
                        لمتابعة طلبك ودفعه من محفظتك، أو أكمل كضيف وسيتواصل معك الدعم.
                    </p>
                <?php endif; ?>
            </form>

            <ul class="service-assurances">
                <?php if ($service['execution_time']): ?>
                    <li><span>◷</span> مدة التنفيذ: <?= e((string) $service['execution_time']) ?></li>
                <?php endif; ?>
                <?php if ((int) $service['requires_approval'] === 1): ?>
                    <li><span>✓</span> يُراجع الطلب قبل التنفيذ</li>
                <?php endif; ?>
                <?php if ((int) $service['mediation_enabled'] === 1): ?>
                    <li><span>🛡️</span> متاحة عبر الوساطة الآمنة</li>
                <?php endif; ?>
                <li><span>💬</span> دعم متابع قبل الطلب وبعده</li>
            </ul>
        </div>
    </div>
</section>

<?php
$detailBlocks = array_filter([
    'الوصف الكامل'   => (string) $service['description_full'],
    'ما تحصل عليه'   => (string) $service['features'],
    'المتطلبات'      => (string) $service['requirements'],
    'الشروط'         => (string) $service['terms'],
    'سياسة الاسترجاع' => (string) $service['refund_policy'],
], static fn(string $v): bool => trim($v) !== '');
?>

<?php if ($detailBlocks || $service['important_note']): ?>
    <section class="section service-content">
        <div class="container narrow">
            <?php if ($service['important_note']): ?>
                <div class="alert alert-info">
                    <span>!</span><div><?= nl2br(e((string) $service['important_note'])) ?></div>
                </div>
            <?php endif; ?>

            <?php foreach ($detailBlocks as $heading => $body): ?>
                <?php $lines = service_lines($body); ?>
                <article class="content-block reveal">
                    <h2><?= e($heading) ?></h2>
                    <?php if (count($lines) > 1): ?>
                        <ul class="clean-list">
                            <?php foreach ($lines as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?= nl2br(e($body)) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($faq): ?>
    <section class="section faq-section">
        <div class="container narrow">
            <h2 class="account-section-title">أسئلة عن هذه الخدمة</h2>
            <div class="faq-list">
                <?php foreach ($faq as $i => $entry): ?>
                    <details <?= $i === 0 ? 'open' : '' ?>>
                        <summary><?= e((string) $entry['question']) ?></summary>
                        <p><?= nl2br(e((string) $entry['answer'])) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($reviews): ?>
    <section class="section reviews-section">
        <div class="container">
            <div class="section-head centered"><h2>آراء من اشتروا هذه الخدمة</h2></div>
            <div class="reviews-grid reveal-stagger">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-card">
                        <div class="stars"><?= str_repeat('★', max(1, min(5, (int) $review['rating']))) ?></div>
                        <?php if (!empty($review['title'])): ?><b><?= e((string) $review['title']) ?></b><?php endif; ?>
                        <p><?= e((string) $review['body']) ?></p>
                        <b><?= e((string) ($review['display_name'] ?: 'عميل')) ?></b>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($related): ?>
    <section class="section">
        <div class="exd-railhead">
            <div class="section-title-row">
                <div><span class="mini-label">من نفس القسم</span><h2>قد يناسبك أيضًا</h2></div>
                <a class="text-link" href="subcategories.php?category_id=<?= (int) $service['category_id'] ?>">عرض القسم ←</a>
            </div>
        </div>
        <div class="exd-rail exd-rail--poster">
            <?php require_once "sections.php"; ?>
            <?php foreach ($related as $item): ?><?= exd_tile($item) ?><?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once "footer.php"; ?>
