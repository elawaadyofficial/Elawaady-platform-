<?php
/**
 * Shared image upload helper for Elawaady Admin
 */

define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

$ALLOWED_EXTS  = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
$ALLOWED_MIMES = [
    'image/jpeg', 'image/png', 'image/webp',
    'image/svg+xml', 'image/gif',
];

/**
 * Upload a single image from $_FILES[$key].
 * Returns the web-root-relative path (e.g. /uploads/services/main/abc.jpg)
 * or null if no file was uploaded.
 * Throws Exception on validation failure.
 *
 * @param string $key       The $_FILES key
 * @param string $dest_dir  Absolute filesystem directory (trailing slash)
 */
function upload_image(string $key, string $dest_dir): ?string
{
    global $ALLOWED_EXTS, $ALLOWED_MIMES;

    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$key];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'حجم الملف يتجاوز الحد المسموح به.',
            UPLOAD_ERR_FORM_SIZE  => 'حجم الملف يتجاوز الحد المسموح به.',
            UPLOAD_ERR_PARTIAL    => 'لم يكتمل رفع الملف، حاول مجددًا.',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد مؤقت غير موجود على الخادم.',
            UPLOAD_ERR_CANT_WRITE => 'تعذّر الكتابة على الخادم.',
        ];
        throw new Exception($msgs[$file['error']] ?? 'خطأ غير معروف أثناء الرفع.');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('حجم الملف يتجاوز 5 ميجابايت.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXTS, true)) {
        throw new Exception('نوع الملف غير مسموح. المسموح: ' . implode(', ', $ALLOWED_EXTS));
    }

    // MIME check (skip for SVG since finfo may return text/plain)
    if ($ext !== 'svg') {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $ALLOWED_MIMES, true)) {
            throw new Exception('محتوى الملف غير مسموح: ' . $mime);
        }
    }

    // Ensure directory exists
    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true)) {
        throw new Exception('تعذّر إنشاء مجلد الرفع.');
    }

    // Unique safe filename
    $filename  = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $full_path = $dest_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        throw new Exception('فشل في نقل الملف إلى الخادم.');
    }

    // Return path relative to web root (project root = one level up from admin/)
    $web_root = realpath(__DIR__ . '/../');
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr(realpath($full_path), strlen($web_root)));
    return $rel; // e.g. /uploads/services/main/1234_abc.jpg
}

/**
 * Upload a file from a multi-file input ($_FILES[$key]['tmp_name'][$index]).
 */
function upload_image_from_array(array $files_group, int $index, string $dest_dir): ?string
{
    global $ALLOWED_EXTS, $ALLOWED_MIMES;

    if (!isset($files_group['tmp_name'][$index])) return null;
    if ($files_group['error'][$index] !== UPLOAD_ERR_OK) return null;
    if ($files_group['size'][$index] > MAX_UPLOAD_SIZE) {
        throw new Exception('أحد الملفات يتجاوز 5 ميجابايت.');
    }

    $ext = strtolower(pathinfo($files_group['name'][$index], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXTS, true)) {
        throw new Exception('نوع ملف غير مسموح: ' . $ext);
    }

    if ($ext !== 'svg') {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $files_group['tmp_name'][$index]);
        finfo_close($finfo);
        if (!in_array($mime, $ALLOWED_MIMES, true)) {
            throw new Exception('محتوى ملف غير مسموح.');
        }
    }

    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true)) {
        throw new Exception('تعذّر إنشاء مجلد الرفع.');
    }

    $filename  = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $full_path = $dest_dir . $filename;

    if (!move_uploaded_file($files_group['tmp_name'][$index], $full_path)) {
        throw new Exception('فشل في نقل أحد الملفات.');
    }

    $web_root = realpath(__DIR__ . '/../');
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr(realpath($full_path), strlen($web_root)));
    return $rel;
}

/**
 * Delete a file by its web-root-relative path (safe: only inside /uploads/).
 */
function delete_upload(string $rel_path): void
{
    if (strpos($rel_path, '/uploads/') !== 0) return; // safety guard
    $full = realpath(__DIR__ . '/../') . $rel_path;
    if (file_exists($full)) @unlink($full);
}

/**
 * Return an absolute URL-ready src for an image, or '' if empty.
 */
function img_src(?string $rel): string
{
    return $rel ? htmlspecialchars($rel, ENT_QUOTES) : '';
}
