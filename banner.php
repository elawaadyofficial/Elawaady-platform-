<?php
/*
|--------------------------------------------------------------------------
| EXD — Section banner component
|--------------------------------------------------------------------------
| One reusable renderer for every section banner in the store. The title is
| dynamic: it comes from the category row, so a category added tomorrow gets a
| banner with no code change.
|
| Every visual knob is a CSS custom property written onto the element. Changing
| a section's colours therefore cannot move anything, because layout reads only
| from the geometry properties, never from the colour ones.
|
| Configuration resolves in this order, first hit wins:
|   1. values passed directly to exd_banner()
|   2. the store_section_banners row for that category, when the table exists
|   3. the palette for the section's theme key
|   4. the EXD brand default
|
| Step 2 is what the admin panel will write to. Until that table exists the
| component runs on steps 3 and 4, so nothing here depends on the dashboard
| being built first.
*/

/**
 * Section palettes. Colours only — no palette may contain a geometry value.
 * Each is *inspired by* its domain; none reproduces another company's logo,
 * wordmark or brand asset.
 */
function exd_banner_themes(): array {
    return [
        // The EXD signature sweep, measured from the design reference.
        'brand' => [
            'grad'   => 'linear-gradient(270deg,#b12bed 0%,#e32dc9 26%,#fd448e 48%,#ff6e64 70%,#ffa105 100%)',
            'accent' => '#ff8a3d',
            'glow'   => 'rgba(224,60,190,.38)',
        ],
        'social' => [
            'grad'   => 'linear-gradient(270deg,#1d4ed8 0%,#4f46e5 34%,#7c3aed 68%,#a855f7 100%)',
            'accent' => '#60a5fa',
            'glow'   => 'rgba(79,70,229,.40)',
        ],
        'ai' => [
            'grad'   => 'linear-gradient(270deg,#6d28d9 0%,#7c3aed 30%,#0ea5e9 66%,#22d3ee 100%)',
            'accent' => '#22d3ee',
            'glow'   => 'rgba(34,211,238,.34)',
        ],
        'subscriptions' => [
            'grad'   => 'linear-gradient(270deg,#4c1d95 0%,#7e22ce 32%,#c026d3 66%,#f472b6 100%)',
            'accent' => '#f0abfc',
            'glow'   => 'rgba(192,38,211,.38)',
        ],
        'streaming' => [
            'grad'   => 'linear-gradient(270deg,#7f1d1d 0%,#dc2626 34%,#ec4899 70%,#a855f7 100%)',
            'accent' => '#fb7185',
            'glow'   => 'rgba(220,38,38,.38)',
        ],
        'verification' => [
            'grad'   => 'linear-gradient(270deg,#b12bed 0%,#e32dc9 26%,#fd448e 48%,#ff6e64 70%,#ffa105 100%)',
            'accent' => '#ffc248',
            'glow'   => 'rgba(255,110,100,.38)',
        ],
        'gaming' => [
            'grad'   => 'linear-gradient(270deg,#4338ca 0%,#7c3aed 30%,#d946ef 62%,#22d3ee 100%)',
            'accent' => '#a3e635',
            'glow'   => 'rgba(217,70,239,.38)',
        ],
        'giftcards' => [
            'grad'   => 'linear-gradient(270deg,#9a3412 0%,#ea580c 30%,#f59e0b 64%,#fcd34d 100%)',
            'accent' => '#fde68a',
            'glow'   => 'rgba(245,158,11,.40)',
        ],
        'music' => [
            'grad'   => 'linear-gradient(270deg,#065f46 0%,#059669 32%,#10b981 64%,#5eead4 100%)',
            'accent' => '#6ee7b7',
            'glow'   => 'rgba(16,185,129,.36)',
        ],
        'media' => [
            'grad'   => 'linear-gradient(270deg,#7c2d12 0%,#c2410c 32%,#f97316 66%,#fbbf24 100%)',
            'accent' => '#fdba74',
            'glow'   => 'rgba(249,115,22,.38)',
        ],
    ];
}

/**
 * Guess a theme key from an Arabic category name, so an unconfigured category
 * still gets a fitting palette instead of falling flat to brand.
 * Purely a default: an explicit theme or a database row always wins.
 */
function exd_banner_guess_theme(string $name): string {
    $map = [
        'social'        => ['تواصل', 'سوشيال', 'فيسبوك', 'إنستجرام', 'انستجرام', 'تيك توك', 'تويتر', 'سناب', 'لينكد', 'تيليجرام', 'واتساب', 'ديسكورد'],
        'ai'            => ['ذكاء', 'اصطناعي', 'AI'],
        'subscriptions' => ['اشتراك', 'اشتراكات'],
        'streaming'     => ['بث', 'مشاهدة', 'أفلام', 'يوتيوب'],
        'verification'  => ['توثيق', 'موثق'],
        'gaming'        => ['ألعاب', 'العاب', 'شحن', 'رصيد', 'اعتمادات'],
        'giftcards'     => ['بطاقات', 'كروت', 'هدايا'],
        'music'         => ['موسيق', 'أغاني', 'اغاني'],
        'media'         => ['أخبار', 'اخبار', 'إعلام', 'اعلام', 'نشر'],
    ];

    foreach ($map as $theme => $needles) {
        foreach ($needles as $needle) {
            if (mb_stripos($name, $needle) !== false) {
                return $theme;
            }
        }
    }

    return 'brand';
}

/**
 * Read a category's saved banner settings. Returns an empty array when the
 * admin table has not been created yet, so the storefront never depends on it.
 */
function exd_banner_settings($conn, ?int $categoryId): array {
    static $rows = null;

    if ($categoryId === null) {
        return [];
    }

    if ($rows === null) {
        $rows = [];
        $check = @$conn->query("SHOW TABLES LIKE 'store_section_banners'");
        if ($check && $check->num_rows > 0) {
            $result = @$conn->query(
                "SELECT * FROM store_section_banners WHERE is_visible = 1"
            );
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[(int) $row['category_id']] = $row;
                }
            }
        }
    }

    return $rows[$categoryId] ?? [];
}

/**
 * Render one section banner.
 *
 * Accepted keys, all optional except title:
 *   title asset asset_mobile theme gradient accent glow text_color font
 *   font_size asset_scale asset_position height radius link visible class
 */
function exd_banner(array $b): string {
    if (array_key_exists('visible', $b) && !$b['visible']) {
        return '';
    }

    $title = trim((string) ($b['title'] ?? ''));
    if ($title === '') {
        return '';
    }

    $themes = exd_banner_themes();
    $key = $b['theme'] ?? exd_banner_guess_theme($title);
    $theme = $themes[$key] ?? $themes['brand'];

    // Colour properties. Layout never reads any of these.
    $vars = [
        '--exd-banner-grad'   => $b['gradient']   ?? $theme['grad'],
        '--exd-banner-accent' => $b['accent']     ?? $theme['accent'],
        '--exd-banner-glow'   => $b['glow']       ?? $theme['glow'],
        '--exd-banner-fg'     => $b['text_color'] ?? null,
    ];

    // Geometry and type properties. Only set when overridden, so the
    // stylesheet's responsive defaults keep working.
    $vars['--exd-banner-font']        = $b['font']         ?? null;
    $vars['--exd-banner-size']        = $b['font_size']    ?? null;
    $vars['--exd-banner-asset-scale'] = $b['asset_scale']  ?? null;
    $vars['--exd-banner-h']           = $b['height']       ?? null;
    $vars['--exd-banner-radius']      = $b['radius']       ?? null;

    $style = '';
    foreach ($vars as $prop => $value) {
        if ($value !== null && $value !== '') {
            $style .= $prop . ':' . $value . ';';
        }
    }

    $position = ($b['asset_position'] ?? 'end') === 'start' ? 'start' : 'end';
    $href = $b['link'] ?? null;
    $tag = $href ? 'a' : 'div';
    $class = 'exd-banner' . (isset($b['class']) ? ' ' . $b['class'] : '');

    $attrs = 'class="' . e($class) . '"'
        . ' data-asset-position="' . $position . '"'
        . ($style !== '' ? ' style="' . e($style) . '"' : '')
        . ($href ? ' href="' . e($href) . '"' : '')
        . ' aria-label="' . e($title) . '"';

    // Asset slot: a real render when one is configured, an abstract decorative
    // cluster otherwise. No product imagery is ever invented here.
    $asset = trim((string) ($b['asset'] ?? ''));
    $assetMobile = trim((string) ($b['asset_mobile'] ?? ''));

    if ($asset !== '') {
        if ($assetMobile !== '') {
            $stage = '<picture>'
                . '<source media="(max-width: 560px)" srcset="' . e($assetMobile) . '">'
                . '<img src="' . e($asset) . '" alt="" loading="lazy" decoding="async">'
                . '</picture>';
        } else {
            $stage = '<img src="' . e($asset) . '" alt="" loading="lazy" decoding="async">';
        }
    } else {
        $stage = '<span class="exd-banner__orbs" aria-hidden="true"><i></i><i></i><i></i></span>';
    }

    return '<' . $tag . ' ' . $attrs . '>'
        . '<span class="exd-banner__pill">'
        . '<span class="exd-banner__title">' . e($title) . '</span>'
        . '<span class="exd-banner__stage">' . $stage . '</span>'
        . '</span>'
        . '</' . $tag . '>';
}

/**
 * Render the banner for a category row, merging any saved admin settings.
 * $category needs at least id and name.
 */
function exd_category_banner($conn, array $category): string {
    $id = isset($category['id']) ? (int) $category['id'] : null;
    $saved = exd_banner_settings($conn, $id);

    return exd_banner([
        'title'          => $saved['title']         ?? ($category['name'] ?? ''),
        'theme'          => $saved['theme']         ?? null,
        'gradient'       => $saved['gradient']      ?? null,
        'accent'         => $saved['accent']        ?? null,
        'glow'           => $saved['glow']          ?? null,
        'text_color'     => $saved['text_color']    ?? null,
        'font'           => $saved['font_family']   ?? null,
        'font_size'      => $saved['font_size']     ?? null,
        'asset'          => $saved['asset_desktop'] ?? null,
        'asset_mobile'   => $saved['asset_mobile']  ?? null,
        'asset_scale'    => $saved['asset_scale']   ?? null,
        'asset_position' => $saved['asset_position'] ?? null,
        'height'         => $saved['banner_height'] ?? null,
        'radius'         => $saved['border_radius'] ?? null,
        'link'           => $saved['link']          ?? ($id ? 'subcategories.php?category_id=' . $id : null),
        'visible'        => !isset($saved['is_visible']) || (int) $saved['is_visible'] === 1,
    ]);
}
