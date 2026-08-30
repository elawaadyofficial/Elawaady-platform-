<?php
/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
| Production values should be supplied through environment variables.
| Local defaults remain intentionally development-friendly.
*/

function env_value(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

$APP_ENV = env_value('APP_ENV', 'development');
$DB_HOST = env_value('DB_HOST', 'localhost');
$DB_NAME = env_value('DB_NAME', 'elawaady_store');
$DB_USER = env_value('DB_USER', 'root');
$DB_PASS = env_value('DB_PASS', '');
$DB_PORT = (int) env_value('DB_PORT', '3306');

$isProduction = strtolower((string) $APP_ENV) === 'production';

if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('[EXD DB] ' . $e->getMessage());

    if ($isProduction) {
        http_response_code(503);
        die('الخدمة غير متاحة مؤقتًا. يرجى المحاولة لاحقًا.');
    }

    die('Database connection failed: ' . $e->getMessage());
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetch_all($conn, $sql, $types = '', ...$params) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_one($conn, $sql, $types = '', ...$params) {
    $rows = fetch_all($conn, $sql, $types, ...$params);
    return $rows[0] ?? null;
}
?>
