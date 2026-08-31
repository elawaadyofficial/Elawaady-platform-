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

    $meta = trim((string) ($service['status'] ?? ''));

    return '<a class="exd-tile" href="' . e($href) . '">'
         . $art
         . '<div class="exd-tile__body">'
         . '<h3 class="exd-tile__name">' . e($name) . '</h3>'
         . '<b class="exd-tile__price">' . e($price) . '</b>'
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
    $shapes = [
        ['row' => 'exd-row--poster', 'take' => 5],
        ['row' => 'exd-row--wide',   'take' => 3],
        ['row' => 'exd-row--chip',   'take' => 6],
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

        $out .= '<section class="exd-band' . $alt . '"><div class="container">'
              . exd_section_head(
                    (string) $cat['name'],
                    (string) ($cat['description'] ?? ''),
                    $href
                )
              . '<div class="' . $shape['row'] . ' reveal-stagger">' . $tiles . '</div>'
              . '</div></section>';

        $i++;
    }

    return $out;
}
