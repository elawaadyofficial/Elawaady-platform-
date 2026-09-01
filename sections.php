<?php
require_once __DIR__ . '/lib/media.php';
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
        // The frame takes the artwork's own shape, so a wide banner is not
        // cropped into a square and a square is not letterboxed into a strip.
        $art = '<div class="exd-tile__art"><img src="' . e($media)
             . '" alt="' . e($name) . '"' . media_size_attrs($media)
             . ' loading="lazy" decoding="async"></div>';
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
    // Each section announces itself with a full-width banner, then shows its
    // content in a shape the section before it did not use. Grid, rail, grid,
    // rail — a stacked block against a scrolling one.
    $shapes = ['keys', 'rail-poster', 'duo', 'rail-wide', 'keys', 'rail-chip'];

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
        $alt   = ($i % 2 === 1) ? ' exd-band--alt' : '';
        $href  = 'subcategories.php?category_id=' . $id;

        $body = '';
        switch ($shape) {
            case 'keys':
                $body = exd_key_grid(
                    $services,
                    fn($s) => 'service.php?id=' . (int) $s['id'],
                    '',
                    'exd-keys--square'
                );
                break;

            case 'duo':
                $body = exd_duo_grid(
                    $services,
                    fn($s) => 'service.php?id=' . (int) $s['id'],
                    'اكتشف المزيد',
                    'exd-duo--square'
                );
                break;

            default:
                $rail = str_replace('rail-', '', $shape);
                $take = $rail === 'wide' ? 9 : 14;
                $tiles = '';
                foreach (array_slice($services, 0, $take) as $service) {
                    $tiles .= exd_tile($service);
                }
                $body = '<div class="exd-rail exd-rail--' . $rail . '"'
                      . media_row_ratio_style(array_slice($services, 0, $take)) . '>' . $tiles . '</div>';
        }

        $out .= exd_section_banner($conn, $cat, (string) ($cat['description'] ?? ''))
              . '<section class="exd-band' . $alt . '">'
              . '<div class="exd-railhead"><div class="section-head section-head--bare">'
              . '<a class="section-head__all" href="' . e($href) . '">عرض الكل ←</a>'
              . '</div></div>'
              . $body
              . '</section>';

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
        "SELECT id, category_id, name, icon, image FROM store_subcategories
         WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 16"
    );

    if (!$rows) {
        return '';
    }

    $cards = '';
    foreach ($rows as $row) {
        $href = 'subcategories.php?category_id=' . (int) $row['category_id']
              . '&amp;subcategory_id=' . (int) $row['id'];
        $cards .= '<a class="exd-plat" href="' . $href . '">'
                . '<span class="exd-plat__art">' . exd_plat_art($row) . '</span>'
                . '<span class="exd-plat__name">' . e((string) $row['name']) . '</span>'
                . '<i class="exd-plat__go" aria-hidden="true">←</i>'
                . '</a>';
    }

    return '<section class="exd-band exd-band--strip">'
         . '<div class="exd-rail exd-rail--plat">' . $cards . '</div></section>';
}

/** Platform artwork, or its icon while the artwork is still to come. */
function exd_plat_art(array $row): string {
    $media = trim((string) ($row['image'] ?? ''));
    if ($media !== '') {
        return '<img src="' . e($media) . '" alt="' . e((string) $row['name'])
             . '" loading="lazy" decoding="async">';
    }
    // Every subcategory ships with a bullet in its icon column, which says
    // nothing and reads as a blank box in fonts that lack the glyph. The first
    // letter of the name at least identifies the row, so a bare bullet counts
    // as no icon here.
    $icon = trim((string) ($row['icon'] ?? ''));
    if ($icon === '•') {
        $icon = '';
    }

    return '<span class="exd-plat__mark">'
         . ($icon !== '' ? e($icon) : mb_substr(e((string) $row['name']), 0, 1)) . '</span>';
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

    return exd_title_banner('خصومات خاصة', 'أقل الأسعار المتاحة الآن', 'categories.php')
         . '<section class="exd-band exd-band--alt">'
         . '<div class="exd-rail exd-rail--chip">' . $tiles . '</div>'
         . '</section>';
}

/* ============================================================================
   Section grammar — banners, key grids, discovery grids
   ----------------------------------------------------------------------------
   A section announces itself with a full-width banner, then shows its content
   in one of two shapes: a stacked grid of key cards, or a rail. Two sections
   running never use the same shape.

   Nothing here invents artwork. When a banner has no image the band renders as
   type on the store's own ground, which is a real section header, not an empty
   picture frame.
   ========================================================================== */

/**
 * A full-width section header. Artwork when the dashboard has it, type when it
 * does not.
 */
function exd_section_banner($conn, array $category, string $subtitle = ''): string {
    $art = exd_category_banner($conn, $category);
    $href = 'subcategories.php?category_id=' . (int) $category['id'];

    if (trim($art) !== '') {
        return '<section class="exd-secbanner">' . $art . '</section>';
    }

    return '<section class="exd-secbanner exd-secbanner--type">'
         . '<a class="exd-secbanner__inner" href="' . e($href) . '">'
         . '<span class="exd-secbanner__title">' . e((string) $category['name']) . '</span>'
         . ($subtitle !== '' ? '<span class="exd-secbanner__sub">' . e($subtitle) . '</span>' : '')
         . '</a></section>';
}

/**
 * A standalone full-width band that is not tied to a category — offers, the
 * payment strip, the licence line.
 */
function exd_title_banner(string $title, string $subtitle, string $href): string {
    return '<section class="exd-secbanner exd-secbanner--type">'
         . '<a class="exd-secbanner__inner" href="' . e($href) . '">'
         . '<span class="exd-secbanner__title">' . e($title) . '</span>'
         . ($subtitle !== '' ? '<span class="exd-secbanner__sub">' . e($subtitle) . '</span>' : '')
         . '</a></section>';
}

/**
 * One key card: artwork with a label riding on it. Used by both stacked grids.
 */
function exd_key_card(array $item, string $href, string $flag = ''): string {
    $name  = (string) ($item['name'] ?? '');
    $media = trim((string) ($item['image'] ?? ''));

    if ($media !== '') {
        $art = '<img src="' . e($media) . '" alt="' . e($name) . '"' . media_size_attrs($media)
             . ' loading="lazy" decoding="async">';
    } else {
        $icon = trim((string) ($item['icon'] ?? ''));
        // The glyph is wrapped so it paints above the tile's ring, which is an
        // absolutely positioned pseudo-element and would otherwise cover it.
        $art = '<span class="exd-key__mark"><span>'
             . ($icon !== '' ? e($icon) : mb_substr(e($name), 0, 1)) . '</span></span>';
    }

    return '<a class="exd-key" href="' . e($href) . '">'
         . ($flag !== '' ? '<span class="exd-key__flag">' . e($flag) . '</span>' : '')
         . '<span class="exd-key__art">' . $art . '</span>'
         . '<span class="exd-key__label">' . e($name) . '</span>'
         . '</a>';
}

/**
 * The stacked grid: three across, two rows. The shape the reference layout
 * uses for anything that is a way in rather than a thing to buy.
 */
function exd_key_grid(array $items, callable $hrefOf, string $flag = '', string $mod = ''): string {
    if (!$items) {
        return '';
    }

    $cards = '';
    foreach (array_slice($items, 0, 6) as $item) {
        $cards .= exd_key_card($item, $hrefOf($item), $flag);
    }

    return '<div class="exd-railhead"><div class="exd-keys' . ($mod !== '' ? ' ' . $mod : '')
         . '">' . $cards . '</div></div>';
}

/**
 * Two across, with the action spelled out under each card. Bigger artwork, so
 * it carries a section on its own without a rail under it.
 */
function exd_duo_grid(array $items, callable $hrefOf, string $action = 'اكتشف المزيد', string $mod = ''): string {
    if (!$items) {
        return '';
    }

    $cards = '';
    foreach (array_slice($items, 0, 6) as $item) {
        $href  = $hrefOf($item);
        $name  = (string) ($item['name'] ?? '');
        $media = trim((string) ($item['image'] ?? ''));

        $art = $media !== ''
            ? '<img src="' . e($media) . '" alt="' . e($name) . '" loading="lazy" decoding="async">'
            : '<span class="exd-key__mark">' . mb_substr(e($name), 0, 1) . '</span>';

        $cards .= '<article class="exd-duo__card">'
                . '<a class="card-link exd-duo__art" href="' . e($href) . '">' . $art . '</a>'
                . '<a class="exd-duo__action" href="' . e($href) . '">'
                . '<span>' . e($action) . '</span><i aria-hidden="true">←</i></a>'
                . '</article>';
    }

    return '<div class="exd-railhead"><div class="exd-duo' . ($mod !== '' ? ' ' . $mod : '')
         . '">' . $cards . '</div></div>';
}

/**
 * The announcement bar. Lines come from the caller, never from this file, so
 * the store's own claims are the only thing it can ever say.
 */
function exd_ticker(array $lines): string {
    if (!$lines) {
        return '';
    }

    $items = '';
    foreach ($lines as $line) {
        $items .= '<span class="exd-ticker__item"><i aria-hidden="true">✓</i>'
                . e((string) $line) . '</span>';
    }

    return '<div class="exd-ticker"><div class="exd-ticker__track">' . $items . $items . '</div></div>';
}
