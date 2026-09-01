<?php
/**
 * EXD — site settings.
 *
 * Settings live in the system_settings table, not in a file. A control in the
 * dashboard that writes to a JSON blob on disk is a control that does not
 * survive a deploy and cannot be read by anything else; every setting here is
 * a row, written by the dashboard and read by the storefront on the same
 * request.
 *
 * settings/site.json is still read once, and only once: when the table has no
 * row for a key, the value from the file seeds it. After that the file is
 * ignored. Nothing writes to it.
 *
 * The public interface is unchanged from the version that used the file —
 * $site_settings, load_site_settings(), save_site_settings(), logo_url(),
 * build_theme_css() — so the dashboard pages that call them keep working.
 */

require_once __DIR__ . '/db_connect.php';

define('SETTINGS_SEED_FILE', __DIR__ . '/settings/site.json');
define('LOGOS_UPLOAD_DIR', __DIR__ . '/uploads/logos');
define('LOGOS_UPLOAD_URL', 'uploads/logos');

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * The shape of the settings, and what each one falls back to. A key absent
 * from here is not a setting; save_site_settings ignores anything else, so a
 * crafted form field cannot invent a row.
 */
function site_settings_defaults(): array {
    return [
        // Identity
        'brand_name_ar'  => 'Elawaady XDigital',
        'brand_name_en'  => 'Elawaady XDigital',
        'brand_subtitle' => 'Digital Store & Services',
        'licence_number' => '767-766-857',

        // Logos, as webroot-relative paths
        'logo_main'   => '',
        'logo_header' => '',
        'logo_icon'   => '',
        'logo_footer' => '',
        'logo_admin'  => '',
        'favicon'     => '',

        // Theme overrides. Empty means "use the design system's own value",
        // which is the normal state — the storefront is already themed.
        'theme_bg_base'      => '',
        'theme_bg_panel'     => '',
        'theme_text'         => '',
        'theme_text_muted'   => '',
        'theme_border'       => '',
        'theme_accent'       => '',
        'theme_accent_2'     => '',
        'theme_cta_from'     => '',
        'theme_cta_to'       => '',
        'theme_glow'         => '',
        'theme_glow_opacity' => '',

        // Contact and support
        'support_whatsapp' => '',
        'support_whatsapp_alt' => '',
        'support_telegram' => '',
        'support_messenger' => '',
        'support_email'    => '',
        'support_hours'    => '',

        // Storefront behaviour
        'announcement_text'   => '',
        'announcement_active' => '1',
        'default_currency'    => 'EGP',
        'mediation_enabled'   => '1',
        'mediation_default_safety_days' => '7',
        'supplier_signup_open' => '1',
        'reviews_require_approval' => '1',
    ];
}

/**
 * Read every setting. The table wins; the seed file fills gaps on a fresh
 * install; the defaults above fill whatever is left.
 */
function load_site_settings(): array {
    global $conn;

    $settings = site_settings_defaults();

    $seed = [];
    if (is_file(SETTINGS_SEED_FILE)) {
        $decoded = json_decode((string) file_get_contents(SETTINGS_SEED_FILE), true);
        if (is_array($decoded)) {
            $seed = $decoded;
        }
    }

    $stored = [];
    try {
        foreach (fetch_all($conn, 'SELECT setting_key, setting_value FROM system_settings') as $row) {
            $stored[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
    } catch (mysqli_sql_exception $e) {
        // Before migrations have run there is no table. The storefront still
        // renders, on defaults.
        error_log('[EXD settings] ' . $e->getMessage());
        $stored = [];
    }

    foreach ($settings as $key => $default) {
        if (array_key_exists($key, $stored) && $stored[$key] !== '') {
            $settings[$key] = $stored[$key];
        } elseif (array_key_exists($key, $seed) && (string) $seed[$key] !== '') {
            $settings[$key] = (string) $seed[$key];
        }
    }

    return $settings;
}

/**
 * Write settings. Only keys the defaults declare are stored, and each write
 * records who made it.
 */
function save_site_settings(array $data, ?int $adminId = null): bool {
    global $conn;

    $allowed = site_settings_defaults();

    $stmt = $conn->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );

    foreach ($data as $key => $value) {
        if (!array_key_exists($key, $allowed)) {
            continue;
        }
        $group = str_starts_with($key, 'theme_')   ? 'theme'
               : (str_starts_with($key, 'logo_')   ? 'brand'
               : (str_starts_with($key, 'support_') ? 'support' : 'general'));
        $stringValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $stmt->bind_param('sssi', $key, $stringValue, $group, $adminId);
        $stmt->execute();
    }

    return true;
}

function setting(string $key, string $default = ''): string {
    global $site_settings;
    if (!is_array($site_settings)) {
        $site_settings = load_site_settings();
    }
    $value = (string) ($site_settings[$key] ?? '');
    return $value !== '' ? $value : $default;
}

/**
 * Accept an uploaded logo. Returns the stored path, or the existing one when
 * nothing usable was uploaded.
 *
 * The file is accepted on what it actually is, read from its bytes, not on the
 * extension in its name.
 */
function upload_logo_file(string $field, string $existing): string {
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $existing;
    }

    $tmp  = (string) $_FILES[$field]['tmp_name'];
    $size = (int) $_FILES[$field]['size'];

    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        return $existing;
    }
    if (!is_uploaded_file($tmp)) {
        return $existing;
    }

    $allowed = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
        'image/x-icon'  => 'ico',
    ];

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset($allowed[$mime])) {
        return $existing;
    }

    if (!is_dir(LOGOS_UPLOAD_DIR)) {
        mkdir(LOGOS_UPLOAD_DIR, 0755, true);
    }

    $name = $field . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $dest = LOGOS_UPLOAD_DIR . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        return $existing;
    }

    return LOGOS_UPLOAD_URL . '/' . $name;
}

/** Resolve a stored path for the page that is rendering it. */
function logo_url(string $path, bool $is_admin = false): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    return $is_admin ? '../' . ltrim($path, '/') : ltrim($path, '/');
}

/**
 * Accept a colour only if it is one. Anything else returns empty, so a value
 * from a form can never end up as arbitrary text inside a stylesheet.
 */
function sanitize_css_color(string $val): string {
    $val = trim($val);
    if ($val === '') {
        return '';
    }
    if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $val)) {
        return $val;
    }
    if (preg_match('/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(?:,\s*[\d.]+\s*)?\)$/i', $val)) {
        return $val;
    }
    if (preg_match('/^hsla?\(\s*[\d.]+\s*(?:deg)?\s*,\s*[\d.]+%\s*,\s*[\d.]+%\s*(?:,\s*[\d.]+\s*)?\)$/i', $val)) {
        return $val;
    }
    return '';
}

function hex_to_rgba_string(string $hex, float $alpha): string {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return 'rgba(0,0,0,' . $alpha . ')';
    }
    return sprintf(
        'rgba(%d,%d,%d,%s)',
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.')
    );
}

/**
 * Build the admin's theme overrides.
 *
 * This writes design tokens, not rules aimed at class names. The storefront is
 * already built on those tokens, so overriding one changes every surface that
 * uses it — consistently, with no `!important`, and with nothing to break when
 * a component is renamed. A setting left empty emits nothing at all, which is
 * why the default theme survives an untouched settings page.
 */
function build_theme_css(array $s): string {
    $map = [
        'theme_bg_base'    => '--exd-bg-base',
        'theme_bg_panel'   => '--exd-bg-panel',
        'theme_text'       => '--exd-text',
        'theme_text_muted' => '--exd-text-muted',
        'theme_border'     => '--exd-border',
        'theme_accent'     => '--exd-violet-400',
        'theme_accent_2'   => '--exd-magenta-500',
    ];

    $vars = [];
    foreach ($map as $key => $token) {
        $colour = sanitize_css_color((string) ($s[$key] ?? ''));
        if ($colour !== '') {
            $vars[] = '  ' . $token . ': ' . $colour;
        }
    }

    $ctaFrom = sanitize_css_color((string) ($s['theme_cta_from'] ?? ''));
    $ctaTo   = sanitize_css_color((string) ($s['theme_cta_to'] ?? ''));
    if ($ctaFrom !== '' && $ctaTo !== '') {
        $vars[] = '  --exd-gradient-cta: linear-gradient(135deg, ' . $ctaFrom . ' 0%, ' . $ctaTo . ' 100%)';
    }

    $glow = sanitize_css_color((string) ($s['theme_glow'] ?? ''));
    if ($glow !== '') {
        $opacity = (float) ($s['theme_glow_opacity'] ?? 0.25);
        $opacity = max(0.0, min(1.0, $opacity));
        $vars[] = '  --exd-glow: ' . (str_starts_with($glow, '#')
            ? hex_to_rgba_string($glow, $opacity)
            : $glow);
    }

    if (!$vars) {
        return '';
    }

    return ":root {\n" . implode(";\n", $vars) . ";\n}\n";
}

if (!isset($site_settings) || !is_array($site_settings)) {
    $site_settings = load_site_settings();
}
