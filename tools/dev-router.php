<?php
/**
 * Router for PHP's built-in server, used only for local development.
 *
 * Without it the CLI server answers a request for a file that does not exist
 * by walking up and serving the nearest index.php. That turns a missing page
 * into a silent 200 and lets a test pass on a page nobody wrote. Apache and
 * LiteSpeed return 404, so this does too.
 */

$path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/..' . $path;

if ($path !== '/' && is_file($file)) {
    return false; // let the server handle a real file
}

if ($path === '/' || is_dir($file)) {
    $index = rtrim($file, '/') . '/index.php';
    if (is_file($index)) {
        $_SERVER['SCRIPT_FILENAME'] = $index;
        require $index;
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>404</title><h1>404 — Not Found</h1><p dir="ltr">' 
   . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</p>';
return true;
