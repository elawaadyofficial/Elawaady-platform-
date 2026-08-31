<?php
/*
|--------------------------------------------------------------------------
| EXD — homepage section renderer
|--------------------------------------------------------------------------
| Renders one band per category, cycling through the card shapes so no two
| sections in a row look alike. A category added tomorrow joins the rhythm
| with no code change and no layout picked by hand.
|
| Nothing here invents content. A service with no artwork gets an empty slot
| holding the right shape, not a made-up image.
*/

/**
 * One tile. $layout only decides which class the row carries; the tile itself
 * is the same markup in every shape, so the shapes stay in CSS.
 */
function exd_tile(array $service): string {
    $href  = 'service.php?id=' . (int) $service['id'];
    $name  = (string) ($service['name'] ?? '');
    $media = trim((string) ($service['image'] ?? ''));
    $ext   = strtolower(pathinfo((string) parse_url($media, PHP_URL_PATH), PATHINFO_EXTENSION));

    if ($media !== '' && in_array($ext, ['mp4', 'webm'], true)) {
        $art = '<div class="exd-tile__art"><video src="' . e($media)
             . '" muted loop playsinline preload="metadata" aria-label="' . e($name) . '"></video></div>';
    } elseif ($media !== '') {
        $art = '<div class="exd-tile__art"><img src="' . e($media) . '" alt="' . e($name)
             . '" loading="lazy" decoding="async"></div>';
    } else {
        // Holds the shape without pretending to be artwork.
        $art = '<div class="exd-tile__art exd-tile__art--empty">'
             . mb_substr(e($name), 0, 1) . '</div>';
    }

    $price = ((float) ($service['price'] ?? 0)) > 0
        ? number_format((float) $service['price'], 2) . ' ج.م'
        : 'حسب الطلب';

    // Only a genuine markdown earns the struck-through figure. A NULL or a
    // "was" that is not above the current price shows nothing at all.
    $was = (float) ($service['old_price'] ?? 0);
    $wasHtml = ($was > (float) ($service['price'] ?? 0))
        ? '<s class="exd-tile__was">' . e(number_format($was, 2)) . '</s>'
        : '';

    $meta = trim((string) ($service['status'] ?? ''));

    return '<a class="exd-tile" href="' . e($href) . '">'
         . $art
         . '<div class="exd-tile__body">'
         . '<h3 class="exd-tile__name">' . e($name) . '</h3>'
         . '<b class="exd-tile__price">' . $wasHtml . e($price) . '</b>'
         . ($meta !== '' ? '<small class="exd-tile__meta">' . e($meta) . '</small>' : '')
         . '</div></a>';
}

/**
 * The heading block: accent bar, subtitle, view-all pill.
 */
function exd_section_head(string $title, string $subtitle, string $allHref): string {
    return '<div class="section-head"><div>'
         . '<h2>' . e($title) . '</h2>'
         . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '')
         . '</div><a class="section-head__all" href="' . e($allHref) . '">عرض الكل ←</a></div>';
}

/**
 * One band per category, cycling poster -> wide -> chip so the page never
 * shows the same shape twice running. How many tiles fit each shape is what
 * decides the slice.
 */
function exd_category_bands($conn, array $categories, array $servicesByCategory): string {
    // A rail scrolls, so it can hold more than a grid row ever could. The take
    // is what fills the screen twice over, not what fits it once.
    $shapes = [
        ['row' => 'exd-rail exd-rail--poster', 'take' => 12],
        ['row' => 'exd-rail exd-rail--wide',   'take' => 9],
        ['row' => 'exd-rail exd-rail--chip',   'take' => 14],
    ];

    $out = '';
    $i = 0;

    foreach ($categories as $cat) {
        $id = (int) $cat['id'];
        $services = $servicesByCategory[$id] ?? [];

        // A category with nothing in it renders nothing, rather than an empty band.
        if (!$services) {
            continue;
        }

        $shape = $shapes[$i % count($shapes)];
        $alt = ($i % 2 === 1) ? ' exd-band--alt' : '';
        $href = 'subcategories.php?category_id=' . $id;

        $tiles = '';
        foreach (array_slice($services, 0, $shape['take']) as $service) {
            $tiles .= exd_tile($service);
        }

        $out .= '<section class="exd-band' . $alt . '">'
              . '<div class="exd-railhead">'
              . exd_section_head(
                    (string) $cat['name'],
                    (string) ($cat['description'] ?? ''),
                    $href
                )
              . '</div>'
              . '<div class="' . $shape['row'] . '">' . $tiles . '</div>'
              . '</section>';

        // The strip breaks the run of picture rows once, early, exactly where
        // the approved layout puts it.
        if ($i === 0) {
            $out .= exd_brand_strip($conn);
        }

        $i++;
    }

    return $out;
}

/**
 * A quiet row of name chips between two picture bands. Real subcategories, so
 * every chip goes somewhere; no logos are invented for it.
 */
function exd_brand_strip($conn): string {
    $rows = fetch_all(
        $conn,
        "SELECT id, category_id, name FROM store_subcategories
         WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 16"
    );

    if (!$rows) {
        return '';
    }

    $chips = '';
    foreach ($rows as $row) {
        $chips .= '<a class="exd-chiplink" href="subcategories.php?category_id='
                . (int) $row['category_id'] . '&amp;subcategory_id=' . (int) $row['id']
                . '">' . e((string) $row['name']) . '</a>';
    }

    return '<section class="exd-band exd-band--strip">'
         . '<div class="exd-rail exd-rail--strip">' . $chips . '</div></section>';
}

/**
 * The offers band. It reads the markdown from the data rather than deciding
 * one: a service is only here when its old price is really above its price,
 * so the whole band disappears while nothing is on offer.
 */
function exd_deals_band($conn): string {
    $rows = fetch_all(
        $conn,
        "SELECT * FROM store_services
         WHERE is_active = 1 AND old_price IS NOT NULL AND old_price > price
         ORDER BY (old_price - price) DESC, id DESC LIMIT 14"
    );

    if (!$rows) {
        return '';
    }

    $tiles = '';
    foreach ($rows as $row) {
        $tiles .= exd_tile($row);
    }

    return '<section class="exd-band exd-band--alt">'
         . '<div class="exd-railhead">'
         . exd_section_head('خصومات خاصة', 'أقل الأسعار المتاحة الآن', 'categories.php')
         . '</div>'
         . '<div class="exd-rail exd-rail--chip">' . $tiles . '</div>'
         . '</section>';
}
