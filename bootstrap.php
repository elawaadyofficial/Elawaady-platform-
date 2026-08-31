<?php
/**
 * EXD — first-install bootstrap.
 *
 * database.sql creates the catalogue from nothing, and it opens with DROP
 * TABLE. That makes it correct exactly once, on an empty database, and
 * catastrophic anywhere else. This script is the only supported way to run it,
 * and it refuses unless the target database is genuinely empty.
 *
 * "Empty" means every table it is about to drop either does not exist or holds
 * zero rows. One row anywhere is enough to stop.
 *
 * Usage:
 *   php bootstrap.php            check, then create the catalogue and migrate
 *   php bootstrap.php --check    report only, change nothing
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$checkOnly = in_array('--check', array_slice($argv, 1), true);

// The tables database.sql drops. If any of them holds data, stop.
const BOOTSTRAP_DROPS = ['store_services', 'store_subcategories', 'store_categories'];

// Tables that mean the platform is in use. Their presence with rows is a
// stronger signal still: a real installation, not a half-seeded catalogue.
const LIVE_TABLES = ['platform_users', 'orders', 'payments', 'wallet_transactions', 'mediations'];

$appEnv = strtolower((string) (getenv('APP_ENV') ?: ''));
if ($appEnv === 'production') {
    fwrite(STDERR, "Refusing to bootstrap: APP_ENV is production.\n");
    fwrite(STDERR, "This script drops tables. It is never run against production.\n");
    exit(1);
}

function table_row_count(mysqli $conn, string $table): ?int {
    $exists = fetch_one(
        $conn,
        'SELECT COUNT(*) AS n FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?',
        's',
        $table
    );
    if ((int) ($exists['n'] ?? 0) === 0) {
        return null;
    }
    // The table name comes from a constant in this file, never from input.
    $row = $conn->query('SELECT COUNT(*) AS n FROM `' . $table . '`')->fetch_assoc();
    return (int) ($row['n'] ?? 0);
}

$occupied = [];
foreach (array_merge(BOOTSTRAP_DROPS, LIVE_TABLES) as $table) {
    $count = table_row_count($conn, $table);
    if ($count === null) {
        printf("  %-22s absent\n", $table);
        continue;
    }
    printf("  %-22s %d row(s)\n", $table, $count);
    if ($count > 0) {
        $occupied[$table] = $count;
    }
}

if ($occupied) {
    fwrite(STDERR, "\nRefusing to bootstrap: this database already holds data.\n");
    foreach ($occupied as $table => $count) {
        fwrite(STDERR, sprintf(" - %s has %d row(s)\n", $table, $count));
    }
    fwrite(STDERR, "\nNothing was changed. To add schema to a database in use, run:\n");
    fwrite(STDERR, "    php migrate.php\n");
    exit(1);
}

echo "\nDatabase is empty; bootstrap is safe.\n";

if ($checkOnly) {
    echo "--check given: nothing was executed.\n";
    exit(0);
}

$sqlPath = __DIR__ . '/database.sql';
if (!is_file($sqlPath)) {
    fwrite(STDERR, "database.sql not found.\n");
    exit(1);
}

// database.sql names its own database with CREATE DATABASE / USE. Those two
// statements are stripped so the bootstrap lands in the database the
// environment points at, not one hardcoded in the file.
$sql = (string) file_get_contents($sqlPath);
$sql = preg_replace('/^\s*CREATE\s+DATABASE[^;]*;/im', '', $sql) ?? $sql;
$sql = preg_replace('/^\s*USE\s+[^;]*;/im', '', $sql) ?? $sql;

echo "Running database.sql ...\n";
try {
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }
} catch (mysqli_sql_exception $e) {
    fwrite(STDERR, 'Bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Catalogue created.\n\n";
echo "Now run:  php migrate.php\n";
exit(0);
