<?php
/**
 * EXD — how artwork is framed.
 *
 * The store promises that what is uploaded is what is displayed: no crop, and
 * transparency preserved. Two ways to keep that promise both fail on their
 * own — a fixed square frame crops a wide banner, and `contain` inside a fixed
 * frame letterboxes it into a small strip.
 *
 * So the frame takes the artwork's own shape. The image's real dimensions are
 * read once per file and cached, and emitted as a custom property the CSS uses
 * for aspect-ratio. Square art gets a square frame, a 3:1 banner gets a 3:1
 * frame, and neither is cut or padded.
 *
 * A file that cannot be measured — a remote URL, a video, a missing path —
 * falls back to the frame's own default, which is what the layout would have
 * done anyway.
 */

/** The width/height of a local image, or null. Measured once per request. */
function media_dimensions(string $path): ?array {
    static $cache = [];

    $path = trim($path);
    if ($path === '' || str_contains($path, '://')) {
        return null;
    }

    if (array_key_exists($path, $cache)) {
        return $cache[$path];
    }

    $file = __DIR__ . '/../' . ltrim($path, '/');
    if (!is_file($file)) {
        return $cache[$path] = null;
    }

    $size = @getimagesize($file);
    if ($size === false || (int) $size[0] <= 0 || (int) $size[1] <= 0) {
        return $cache[$path] = null;
    }

    return $cache[$path] = ['width' => (int) $size[0], 'height' => (int) $size[1]];
}

/**
 * The style attribute that makes a frame match its artwork.
 *
 * Returns an empty string when the shape cannot be known, so the element keeps
 * whatever the stylesheet already gives it.
 */
function media_ratio_style(string $path, float $minRatio = 0.6, float $maxRatio = 3.2): string {
    $size = media_dimensions($path);
    if ($size === null) {
        return '';
    }

    $ratio = $size['width'] / $size['height'];

    // A frame is clamped at the extremes: an artwork ten times wider than it is
    // tall would otherwise collapse a card to a hairline in a grid.
    $ratio = max($minRatio, min($maxRatio, $ratio));

    return ' style="--exd-art-ratio: ' . rtrim(rtrim(number_format($ratio, 4, '.', ''), '0'), '.') . ';"';
}

/** Width and height attributes, so the browser reserves the space before load. */
function media_size_attrs(string $path): string {
    $size = media_dimensions($path);
    if ($size === null) {
        return '';
    }
    return ' width="' . $size['width'] . '" height="' . $size['height'] . '"';
}

/**
 * One shape for a whole row.
 *
 * Cards in a row must share a shape or the row reads as broken, but a row of
 * 3:1 banners inside 1:1 frames wastes two thirds of every card. So the row
 * takes the shape of the artwork it actually holds: the median ratio of its
 * images, applied once to the container.
 *
 * The median rather than the mean, because one odd image should not drag the
 * whole row out of shape.
 *
 * Returns an empty string when the row has no measurable artwork, leaving the
 * stylesheet's own ratio in place.
 */
function media_row_ratio_style(array $items, string ...$fields): string {
    if (!$fields) {
        $fields = ['main_image', 'image'];
    }

    $ratios = [];
    foreach ($items as $item) {
        foreach ($fields as $field) {
            $path = trim((string) ($item[$field] ?? ''));
            if ($path === '') {
                continue;
            }
            $size = media_dimensions($path);
            if ($size !== null) {
                $ratios[] = $size['width'] / $size['height'];
                break;
            }
        }
    }

    if (!$ratios) {
        return '';
    }

    sort($ratios);
    $middle = (int) floor((count($ratios) - 1) / 2);
    $ratio  = count($ratios) % 2 === 1
        ? $ratios[$middle]
        : ($ratios[$middle] + $ratios[$middle + 1]) / 2;

    // Kept inside a range a card can actually wear.
    $ratio = max(0.7, min(3.0, $ratio));

    return ' style="--exd-art-ratio: ' . rtrim(rtrim(number_format($ratio, 4, '.', ''), '0'), '.') . ';"';
}
