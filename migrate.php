<?php
/**
 * EXD — migration runner.
 *
 * Applies every file in migrations/ exactly once, in filename order, recording
 * each one in schema_migrations. Re-running is a no-op: a migration that has
 * already been applied is skipped, so this is safe on a database holding real
 * data.
 *
 * It refuses to run anything destructive. Before a file is executed it is read
 * and rejected if it contains DROP TABLE, DROP DATABASE, TRUNCATE or an
 * unqualified DELETE — the same guard CI enforces, applied again at the moment
 * of execution rather than only at review time.
 *
 * Usage:
 *   php migrate.php            apply pending migrations
 *   php migrate.php --status   list what is applied and what is pending
 *   php migrate.php --dry-run  show what would run, execute nothing
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db_connect.php';

const MIGRATIONS_DIR = __DIR__ . '/migrations';

$options = array_slice($argv, 1);
$status  = in_array('--status', $options, true);
$dryRun  = in_array('--dry-run', $options, true);

/**
 * Reject a migration that could destroy data. The check is deliberately blunt:
 * a legitimate additive migration never needs any of these words.
 */
function destructive_statement(string $sql): ?string {
    // Strip comments so a word inside an explanation is not mistaken for code.
    $code = preg_replace('/--[^\n]*/', '', $sql);
    $code = preg_replace('#/\*.*?\*/#s', '', (string) $code);
    $code = strtoupper((string) $code);

    $forbidden = [
        '/\bDROP\s+TABLE\b/'    => 'DROP TABLE',
        '/\bDROP\s+DATABASE\b/' => 'DROP DATABASE',
        '/\bDROP\s+SCHEMA\b/'   => 'DROP SCHEMA',
        '/\bTRUNCATE\b/'        => 'TRUNCATE',
        '/\bDELETE\s+FROM\b/'   => 'DELETE FROM',
        '/\bDROP\s+COLUMN\b/'   => 'DROP COLUMN',
    ];

    foreach ($forbidden as $pattern => $label) {
        if (preg_match($pattern, $code)) {
            return $label;
        }
    }
    return null;
}

/**
 * Split a migration file into statements.
 *
 * A semicolon only ends a statement when it is real code. One inside a quoted
 * string, or inside a `--` line comment or a block comment, is prose — and the
 * comments in these migrations are written in full sentences, so this
 * distinction is not theoretical.
 */
function split_statements(string $sql): array {
    $statements = [];
    $buffer     = '';
    $length     = strlen($sql);
    $i          = 0;

    while ($i < $length) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        // Line comment: drop it, keep the newline so tokens stay separated.
        if ($char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }

        // Block comment: drop it entirely.
        if ($char === '/' && $next === '*') {
            $i += 2;
            while ($i < $length && !($sql[$i] === '*' && ($i + 1 < $length && $sql[$i + 1] === '/'))) {
                $i++;
            }
            $i += 2;
            $buffer .= ' ';
            continue;
        }

        // Quoted string or identifier: copy verbatim, escapes included.
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote   = $char;
            $buffer .= $char;
            $i++;
            while ($i < $length) {
                if ($sql[$i] === '\\' && $quote !== '`') {
                    $buffer .= substr($sql, $i, 2);
                    $i += 2;
                    continue;
                }
                // A doubled quote is an escaped quote, not the end.
                if ($sql[$i] === $quote && ($i + 1 < $length && $sql[$i + 1] === $quote)) {
                    $buffer .= $quote . $quote;
                    $i += 2;
                    continue;
                }
                $buffer .= $sql[$i];
                if ($sql[$i] === $quote) {
                    $i++;
                    break;
                }
                $i++;
            }
            continue;
        }

        if ($char === ';') {
            $statements[] = $buffer;
            $buffer = '';
            $i++;
            continue;
        }

        $buffer .= $char;
        $i++;
    }

    $statements[] = $buffer;

    return array_values(array_filter(
        array_map('trim', $statements),
        static fn(string $statement): bool => $statement !== ''
    ));
}

$conn->query(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename    VARCHAR(190) NOT NULL PRIMARY KEY,
        checksum    CHAR(64)     NOT NULL,
        applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        duration_ms INT          NOT NULL DEFAULT 0
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = [];
foreach (fetch_all($conn, 'SELECT filename, checksum FROM schema_migrations') as $row) {
    $applied[$row['filename']] = $row['checksum'];
}

$files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
sort($files, SORT_STRING);

if (!$files) {
    fwrite(STDERR, "No migrations found in migrations/.\n");
    exit(1);
}

$pending  = [];
$drifted  = [];

foreach ($files as $path) {
    $name     = basename($path);
    $contents = (string) file_get_contents($path);
    $checksum = hash('sha256', $contents);

    if (!isset($applied[$name])) {
        $pending[] = [$name, $path, $contents, $checksum];
        continue;
    }
    if ($applied[$name] !== $checksum) {
        $drifted[] = $name;
    }
}

if ($drifted) {
    fwrite(STDERR, "Refusing to run: these migrations changed after they were applied.\n");
    foreach ($drifted as $name) {
        fwrite(STDERR, ' - ' . $name . "\n");
    }
    fwrite(STDERR, "An applied migration is history. Add a new migration instead of editing one.\n");
    exit(1);
}

if ($status) {
    printf("Applied: %d   Pending: %d\n", count($applied), count($pending));
    foreach ($files as $path) {
        $name = basename($path);
        printf("  %s %s\n", isset($applied[$name]) ? '[x]' : '[ ]', $name);
    }
    exit(0);
}

if (!$pending) {
    echo "Database is up to date; nothing to apply.\n";
    exit(0);
}

foreach ($pending as [$name, $path, $contents, $checksum]) {
    $violation = destructive_statement($contents);
    if ($violation !== null) {
        fwrite(STDERR, "Refusing to run $name: it contains $violation.\n");
        fwrite(STDERR, "Migrations must be additive. Nothing was applied.\n");
        exit(1);
    }
}

foreach ($pending as [$name, $path, $contents, $checksum]) {
    $statements = split_statements($contents);

    if ($dryRun) {
        printf("would apply %s (%d statements)\n", $name, count($statements));
        continue;
    }

    printf("applying %s ... ", $name);
    $startedAt = microtime(true);

    try {
        foreach ($statements as $statement) {
            $conn->query($statement);
        }
    } catch (mysqli_sql_exception $e) {
        echo "FAILED\n";
        fwrite(STDERR, '  ' . $e->getMessage() . "\n");
        fwrite(STDERR, "Migration $name did not complete. Later migrations were not attempted.\n");
        exit(1);
    }

    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

    $stmt = $conn->prepare(
        'INSERT INTO schema_migrations (filename, checksum, duration_ms) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('ssi', $name, $checksum, $durationMs);
    $stmt->execute();

    printf("ok (%d statements, %d ms)\n", count($statements), $durationMs);
}

echo $dryRun ? "Dry run complete; nothing was executed.\n" : "All pending migrations applied.\n";
exit(0);
