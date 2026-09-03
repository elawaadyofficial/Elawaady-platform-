<?php
/**
 * EXD migration readiness preflight.
 *
 * Offline/read-only validation only. This script never connects to MySQL and
 * never executes SQL. It verifies that the migration chain is deterministic,
 * contiguous, non-empty, and additive before any staging database is touched.
 *
 * Usage: php tools/migration_preflight.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$migrationDir = $root . '/migrations';
$errors = [];

function migration_fail(string $message): void
{
    global $errors;
    $errors[] = $message;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function destructive_sql(string $sql): ?string
{
    $code = preg_replace('/--[^\n]*/', '', $sql);
    $code = preg_replace('#/\*.*?\*/#s', '', (string) $code);
    $code = strtoupper((string) $code);

    $forbidden = [
        '/\bDROP\s+TABLE\b/' => 'DROP TABLE',
        '/\bDROP\s+DATABASE\b/' => 'DROP DATABASE',
        '/\bDROP\s+SCHEMA\b/' => 'DROP SCHEMA',
        '/\bDROP\s+COLUMN\b/' => 'DROP COLUMN',
        '/\bTRUNCATE\b/' => 'TRUNCATE',
        '/\bDELETE\s+FROM\b/' => 'DELETE FROM',
    ];

    foreach ($forbidden as $pattern => $label) {
        if (preg_match($pattern, $code) === 1) {
            return $label;
        }
    }

    return null;
}

echo "EXD migration readiness preflight\n";
echo "Mode: offline/read-only; no database connection or SQL execution.\n\n";

if (!is_dir($migrationDir)) {
    migration_fail('migrations/ directory is missing.');
} else {
    $files = glob($migrationDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    if ($files === []) {
        migration_fail('No migration files were found.');
    } else {
        $expectedNumber = 1;
        $seenNames = [];

        foreach ($files as $path) {
            $name = basename($path);
            if (!preg_match('/^(\d{3})_[a-z0-9][a-z0-9_]*\.sql$/', $name, $matches)) {
                migration_fail("Migration filename is invalid: {$name}. Expected NNN_snake_case.sql.");
                continue;
            }

            $number = (int) $matches[1];
            if ($number !== $expectedNumber) {
                migration_fail(sprintf('Migration sequence is not contiguous: expected %03d but found %03d (%s).', $expectedNumber, $number, $name));
                $expectedNumber = $number;
            }
            $expectedNumber++;

            if (isset($seenNames[$name])) {
                migration_fail("Duplicate migration filename: {$name}.");
                continue;
            }
            $seenNames[$name] = true;

            $sql = (string) file_get_contents($path);
            if (trim($sql) === '') {
                migration_fail("Migration is empty: {$name}.");
                continue;
            }

            $violation = destructive_sql($sql);
            if ($violation !== null) {
                migration_fail("Migration {$name} contains forbidden destructive SQL: {$violation}.");
                continue;
            }

            echo sprintf("[PASS] %s — %d bytes — sha256:%s\n", $name, strlen($sql), substr(hash('sha256', $sql), 0, 12));
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, sprintf("\nMigration preflight failed: %d issue(s). Nothing was executed.\n", count($errors)));
    exit(1);
}

printf("\nMigration preflight passed. Chain is contiguous and additive. Nothing was executed.\n");
exit(0);
