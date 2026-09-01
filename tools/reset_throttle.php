<?php
/**
 * Clear login and registration throttle counters.
 *
 * The integration tests deliberately trigger the throttle — that is one of the
 * things they check — so a second run of the suite would otherwise be locked
 * out by the first. This resets the counters between runs.
 *
 * It refuses to run outside development, because clearing rate limits on a
 * live site removes the protection they exist to provide.
 *
 * Usage:
 *   php tools/reset_throttle.php            clear every counter
 *   php tools/reset_throttle.php 127.0.0.1  clear counters for one address
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$env = strtolower((string) (getenv('APP_ENV') ?: ''));
if ($env !== 'development') {
    fwrite(STDERR, "Refusing to clear rate limits: APP_ENV is '$env', not development.\n");
    exit(1);
}

$prefix = $argv[1] ?? '';

if ($prefix === '') {
    $conn->query('DELETE FROM auth_throttle');
    $cleared = $conn->affected_rows;
} else {
    $stmt = $conn->prepare('DELETE FROM auth_throttle WHERE throttle_key LIKE ?');
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $cleared = $stmt->affected_rows;
}

// A locked account is the other half of the same protection.
$conn->query('UPDATE platform_users SET failed_login_count = 0, locked_until = NULL WHERE locked_until IS NOT NULL');
$users = $conn->affected_rows;
$conn->query('UPDATE admin_users SET failed_login_count = 0, locked_until = NULL WHERE locked_until IS NOT NULL');
$admins = $conn->affected_rows;

printf("Cleared %d throttle row(s), unlocked %d account(s) and %d staff account(s).\n", $cleared, $users, $admins);
