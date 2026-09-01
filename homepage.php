<?php
/**
 * EXD — the homepage, assembled from rows.
 *
 * index.php walks homepage_sections in sort_order and asks this file for the
 * data each row needs. What a section renders is decided by its section_type
 * and layout columns, and which items it holds by its source_filter — so
 * reordering the store, hiding a band or changing how many cards a row shows
 * is an edit in the dashboard, not a deployment.
 *
 * A section with nothing to show renders nothing. An empty frame with a
 * heading over it is worse than no section at all.
 */

require_once __DIR__ . '/db_connect.php';

/** The active sections, in the order the storefront draws them. */
function exd_homepage_sections($conn): array {
    try {
        return fetch_all(
            $conn,
            'SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order, id'
        );
    } catch (mysqli_sql_exception $e) {
        // Before migrations have run there is no table. The page still draws,
        // on the built-in order below.
        error_log('[EXD homepage] ' . $e->getMessage());
        return exd_homepage_fallback();
    }
}

/**
 * The order the store falls back to when the table is missing.
 *
 * This is not the source of truth — homepage_sections is — but a storefront
 * that renders nothing because a table is absent is worse than one that
 * renders its default shape.
 */
function exd_homepage_fallback(): array {
    $rows  = [];
    $order = 0;
    foreach ([
        ['hero',           'الواجهة',          'hero',           'hero',      null,          1],
        ['browse_cats',    'تصفح الأقسام',      'categories',     'keys',      null,         12],
        ['best_sellers',   'الأكثر مبيعًا',     'services',       'product',   'best_seller', 10],
        ['category_bands', 'أقسام الخدمات',     'category_bands', 'mixed',     null,          0],
        ['banners_mid',    'بنرات',            'banners',        'rail',      'home_mid',    8],
        ['offers',         'العروض',           'services',       'product',   'offers',     10],
        ['mediation',      'وساطة آمنة',        'mediation',      'feature',   null,          1],
        ['banners_bottom', 'بنرات',            'banners',        'rail',      'home_bottom', 8],
        ['reviews',        'آراء العملاء',      'reviews',        'grid',      null,          6],
        ['faq',            'الأسئلة الشائعة',   'faq',            'accordion', null,          8],
        ['payment_trust',  'طرق الدفع',        'payment',        'strip',     null,          1],
    ] as [$key, $title, $type, $layout, $filter, $limit]) {
        $rows[] = [
            'id' => 0, 'section_key' => $key, 'title' => $title, 'subtitle' => null,
            'section_type' => $type, 'layout' => $layout, 'source_filter' => $filter,
            'category_id' => null, 'banner_image' => null, 'banner_fit' => 'original',
            'item_limit' => $limit, 'link_url' => null, 'link_label' => null,
            'is_active' => 1, 'sort_order' => $order += 10,
        ];
    }
    return $rows;
}

/**
 * The services a section shows.
 *
 * A placement-backed filter reads service_placements, which is what the
 * dashboard's placements page writes. The others are ordinary orderings. Every
 * branch selects the same columns and none of them selects a supplier column,
 * so a supplier's identity cannot reach a customer through this path.
 */
function exd_section_services($conn, array $section): array {
    $limit  = max(0, min(60, (int) ($section['item_limit'] ?? 8)));
    if ($limit === 0) {
        return [];
    }

    $filter     = (string) ($section['source_filter'] ?? '');
    $categoryId = (int) ($section['category_id'] ?? 0);

    $columns = 's.id, s.name, s.description, s.price, s.old_price, s.currency, s.image,
                s.main_image, s.service_link, s.badge, s.show_price, s.ask_for_price,
                s.primary_button_label, s.secondary_button_label, s.category_id,
                c.name AS category_name';

    $placements = ['best_seller', 'most_used', 'featured', 'newest', 'offers'];

    if (in_array($filter, $placements, true)) {
        $rows = fetch_all(
            $conn,
            "SELECT $columns
               FROM service_placements sp
               JOIN store_services s     ON s.id = sp.service_id
               LEFT JOIN store_categories c ON c.id = s.category_id
              WHERE sp.placement_key = ? AND s.is_active = 1
                AND (sp.starts_at IS NULL OR sp.starts_at <= NOW())
                AND (sp.ends_at   IS NULL OR sp.ends_at   >= NOW())
              ORDER BY sp.sort_order, s.id DESC
              LIMIT $limit",
            's',
            $filter
        );

        // 'offers' has an obvious meaning even with nothing pinned to it, so
        // it falls back to whatever is genuinely discounted rather than
        // rendering an empty band.
        if (!$rows && $filter === 'offers') {
            return fetch_all(
                $conn,
                "SELECT $columns
                   FROM store_services s
                   LEFT JOIN store_categories c ON c.id = s.category_id
                  WHERE s.is_active = 1 AND s.old_price IS NOT NULL AND s.old_price > s.price
                  ORDER BY (s.old_price - s.price) DESC, s.id DESC
                  LIMIT $limit"
            );
        }
        return $rows;
    }

    if ($categoryId > 0) {
        return fetch_all(
            $conn,
            "SELECT $columns
               FROM store_services s
               LEFT JOIN store_categories c ON c.id = s.category_id
              WHERE s.is_active = 1 AND s.category_id = ?
              ORDER BY s.sort_order, s.id DESC
              LIMIT $limit",
            'i',
            $categoryId
        );
    }

    return fetch_all(
        $conn,
        "SELECT $columns
           FROM store_services s
           LEFT JOIN store_categories c ON c.id = s.category_id
          WHERE s.is_active = 1
          ORDER BY s.sort_order, s.id DESC
          LIMIT $limit"
    );
}

/** The categories a section shows. */
function exd_section_categories($conn, array $section): array {
    $limit = max(1, min(48, (int) ($section['item_limit'] ?? 12)));
    return fetch_all(
        $conn,
        "SELECT * FROM store_categories
          WHERE is_active = 1 AND show_home = 1
          ORDER BY sort_order, id
          LIMIT $limit"
    );
}

/** The approved reviews a section shows, newest first. */
function exd_section_reviews($conn, array $section): array {
    $limit = max(1, min(24, (int) ($section['item_limit'] ?? 6)));
    try {
        return fetch_all(
            $conn,
            "SELECT r.rating, r.title, r.body, r.author_name, r.created_at,
                    COALESCE(u.name, r.author_name) AS display_name,
                    s.name AS service_name
               FROM reviews r
               LEFT JOIN platform_users u ON u.id = r.user_id
               LEFT JOIN store_services s ON s.id = r.service_id
              WHERE r.status = 'approved'
              ORDER BY r.id DESC
              LIMIT $limit"
        );
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}

/** A section's heading, or an empty string when it should carry none. */
function exd_section_heading(array $section): string {
    $title = trim((string) ($section['title'] ?? ''));
    if ($title === '' || in_array($section['section_type'], ['hero', 'banners', 'payment'], true)) {
        return '';
    }

    $subtitle = trim((string) ($section['subtitle'] ?? ''));
    $linkUrl  = trim((string) ($section['link_url'] ?? ''));
    $label    = trim((string) ($section['link_label'] ?? '')) ?: 'عرض الكل';

    $out  = '<div class="exd-railhead"><div class="section-title-row"><div>';
    if ($subtitle !== '') {
        $out .= '<span class="mini-label">' . e($subtitle) . '</span>';
    }
    $out .= '<h2>' . e($title) . '</h2></div>';
    if ($linkUrl !== '') {
        $out .= '<a class="text-link" href="' . e($linkUrl) . '">' . e($label) . ' ←</a>';
    }
    return $out . '</div></div>';
}

/**
 * The reveal class for a band.
 *
 * Rails opt out of the staggered reveal: their children start offscreen
 * horizontally, so an observer that waits for each one to enter the viewport
 * would leave most of the row invisible until it is scrolled.
 */
function exd_section_reveal(array $section): string {
    return in_array($section['layout'], ['rail', 'product', 'banner'], true) ? 'reveal' : 'reveal-stagger';
}

/**
 * The homepage carousel slides.
 *
 * These are the store's own artwork. An empty table means the hero falls back
 * to its typographic slide rather than showing a blank frame.
 */
function exd_carousel_slides($conn): array {
    try {
        return fetch_all(
            $conn,
            'SELECT image, image_mobile, title_ar, link_type, link_id, custom_url
               FROM homepage_carousel WHERE is_active = 1 AND image <> ""
              ORDER BY sort_order, id LIMIT 12'
        );
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}

/** Where a carousel slide points. A slide with no destination is not a link. */
function exd_carousel_href(array $slide): string {
    return match ((string) $slide['link_type']) {
        'service'  => 'service.php?id=' . (int) $slide['link_id'],
        'category' => 'subcategories.php?category_id=' . (int) $slide['link_id'],
        'custom'   => (string) ($slide['custom_url'] ?? '#'),
        default    => 'categories.php',
    };
}

/**
 * The questions the homepage answers.
 *
 * These come from the chatbot knowledge base, which is where the store already
 * keeps its answers — so the FAQ and the assistant cannot drift apart, and
 * editing one edits both.
 */
function exd_home_faq($conn, int $limit = 8): array {
    $limit = max(1, min(20, $limit));
    try {
        $rows = fetch_all(
            $conn,
            "SELECT question, answer FROM chatbot_knowledge
              WHERE is_active = 1 ORDER BY priority DESC, id LIMIT $limit"
        );
        if ($rows) {
            return $rows;
        }
    } catch (mysqli_sql_exception $e) {
        // fall through to the built-in set
    }

    return [
        ['question' => 'كيف أطلب من المتجر؟',
         'answer'   => 'اختر القسم ثم الخدمة المناسبة، راجع التفاصيل والمتطلبات، وبعدها أكمل الطلب بالطريقة المتاحة للخدمة.'],
        ['question' => 'هل كل الخدمات تنفيذها فوري؟',
         'answer'   => 'لا. مدة التنفيذ تختلف حسب نوع المنتج أو الخدمة، وهي موضحة داخل صفحة كل خدمة.'],
        ['question' => 'هل المتجر يعمل على الموبايل؟',
         'answer'   => 'نعم. الواجهة مصممة Mobile First وتتكيف تلقائيًا مع حجم الشاشة.'],
        ['question' => 'كيف أتواصل مع الدعم؟',
         'answer'   => 'من صفحة التواصل أو زر الدعم أعلى المتجر.'],
    ];
}

/**
 * The payment logos the store displays.
 *
 * Only files that are actually present are listed, so a missing logo leaves a
 * gap in the row rather than a broken image.
 */
function exd_payment_logos(): array {
    $known = [
        '01-instapay.webp'       => 'InstaPay',
        '02-fawry.webp'          => 'فوري',
        '03-paypal.webp'         => 'PayPal',
        '04-gpay.webp'           => 'Google Pay',
        '05-vodafone-cash.webp'  => 'فودافون كاش',
        '06-orange-cash.webp'    => 'أورنج كاش',
        '07-etisalat-cash.webp'  => 'اتصالات كاش',
        '08-stc-pay.webp'        => 'stc pay',
        '09-we-pay.webp'         => 'WE Pay',
        '10-apple-pay.webp'      => 'Apple Pay',
        '11-mastercard.webp'     => 'Mastercard',
        '12-mada.webp'           => 'مدى',
        '13-bank-transfer.webp'  => 'تحويل بنكي',
    ];

    $logos = [];
    foreach ($known as $file => $label) {
        $path = 'assets/payments/' . $file;
        if (is_file(__DIR__ . '/' . $path)) {
            $logos[] = ['src' => $path, 'alt' => $label];
        }
    }
    return $logos;
}
