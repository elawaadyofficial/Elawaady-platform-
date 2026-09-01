<?php
/**
 * EXD Image Validator — called before assigning any auto-imported image
 * to a service, category, carousel slot, or payment strip.
 *
 * Usage:
 *   $result = exp_validate_image($filepath, $intended_role);
 *   if (!$result['ok']) { echo $result['reason']; }
 */

/**
 * @param string $filepath  Absolute path to the image file
 * @param string $role      Expected role: 'service_card' | 'section_banner' | 'carousel' | 'payment' | 'brand' | 'any'
 * @return array  ['ok' => bool, 'reason' => string, 'w' => int, 'h' => int, 'ratio' => float]
 */
function exp_validate_image(string $filepath, string $role = 'any'): array {
    $name = strtolower(basename($filepath));

    // ── 1. Name-based screenshot detection ───────────────────────────────────
    $bad_keywords = ['screenshot', 'screen_', 'screen-', 'replit', 'chatgpt', 'browser', 'mobile_cap', 'capture'];
    foreach ($bad_keywords as $kw) {
        if (strpos($name, $kw) !== false) {
            return ['ok' => false, 'reason' => "Filename contains rejected keyword '$kw'", 'w' => 0, 'h' => 0, 'ratio' => 0];
        }
    }

    // ── 2. Dimension checks ──────────────────────────────────────────────────
    $info = @getimagesize($filepath);
    if (!$info) {
        return ['ok' => false, 'reason' => 'Could not read image dimensions', 'w' => 0, 'h' => 0, 'ratio' => 0];
    }
    [$w, $h] = $info;
    $ratio = $h > 0 ? round($w / $h, 3) : 0;  // w:h ratio (>1 = landscape, <1 = portrait)
    $h_over_w = $w > 0 ? round($h / $w, 3) : 0;

    // Reject very tall portrait images (likely mobile screenshots)
    if ($h_over_w > 2.2) {
        return ['ok' => false, 'reason' => "Image is too tall (h/w = $h_over_w). Likely a mobile screenshot.", 'w' => $w, 'h' => $h, 'ratio' => $ratio];
    }

    // Known mobile screenshot dimensions (reject exact matches)
    $known_screenshot_dims = [
        [1080, 1920], [750, 1334], [1440, 2560], [1080, 2340],
        [1080, 2400], [828, 1792], [1284, 2778], [1170, 2532],
    ];
    foreach ($known_screenshot_dims as [$sw, $sh]) {
        if ($w === $sw && $h === $sh) {
            return ['ok' => false, 'reason' => "Matches known mobile screenshot resolution {$sw}×{$sh}", 'w' => $w, 'h' => $h, 'ratio' => $ratio];
        }
    }

    // ── 3. Role-specific ratio checks ────────────────────────────────────────
    switch ($role) {
        case 'service_card':
            // Must be roughly square (0.75 – 1.35 w:h)
            if ($ratio < 0.75 || $ratio > 1.35) {
                return ['ok' => false, 'reason' => "Service card must be roughly square (ratio $ratio). Got {$w}×{$h}.", 'w' => $w, 'h' => $h, 'ratio' => $ratio];
            }
            break;
        case 'section_banner':
            // Must be wide slim (w:h > 2.2)
            if ($ratio < 2.2) {
                return ['ok' => false, 'reason' => "Section banner must be wide/slim (ratio > 2.2, got $ratio). Got {$w}×{$h}.", 'w' => $w, 'h' => $h, 'ratio' => $ratio];
            }
            break;
        case 'carousel':
            // Must not be taller than wide (portrait = likely screenshot)
            // Square (1:1) is acceptable for carousel; only reject portrait (h > w * 1.2)
            if ($h > $w * 1.2) {
                return ['ok' => false, 'reason' => "Carousel image must not be portrait (ratio $ratio). Got {$w}×{$h}.", 'w' => $w, 'h' => $h, 'ratio' => $ratio];
            }
            break;
        case 'payment':
            // Payment strip: wide is ideal, but at minimum landscape (ratio > 1.0)
            if ($ratio < 1.0) {
                return ['ok' => false, 'reason' => "Payment strip image should be landscape (ratio > 1.0, got $ratio). Got {$w}×{$h}.", 'w' => $w, 'h' => $h, 'ratio' => $ratio];
            }
            break;
    }

    return ['ok' => true, 'reason' => 'Valid', 'w' => $w, 'h' => $h, 'ratio' => $ratio];
}

/**
 * Scan a folder and return rejected images with reasons.
 * @param string $folder  Absolute path to folder
 * @param string $role    Expected role for all images in this folder
 * @return array  List of ['file', 'reason', 'w', 'h']
 */
function exp_scan_folder(string $folder, string $role = 'any'): array {
    $rejected = [];
    foreach (glob(rtrim($folder, '/') . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) as $f) {
        $result = exp_validate_image($f, $role);
        if (!$result['ok']) {
            $rejected[] = ['file' => $f, 'reason' => $result['reason'], 'w' => $result['w'], 'h' => $result['h']];
        }
    }
    return $rejected;
}
