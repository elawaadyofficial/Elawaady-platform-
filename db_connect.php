<?php
/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
| عدّل البيانات التالية حسب بيانات cPanel / MySQL عند الرفع على الدومين
*/
$DB_HOST = "localhost";
$DB_NAME = "elawaady_store";
$DB_USER = "root";
$DB_PASS = "";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetch_all($conn, $sql, $types = "", ...$params) {
    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_one($conn, $sql, $types = "", ...$params) {
    $rows = fetch_all($conn, $sql, $types, ...$params);
    return $rows[0] ?? null;
}
?>
