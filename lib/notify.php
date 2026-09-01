<?php
/**
 * EXD — notifications.
 *
 * A notification is a row, not an email. The account sees it the next time it
 * loads a page, which means a decision taken in the dashboard reaches the
 * person it concerns without depending on a mail provider being configured.
 * Email and push can be layered on top of the same rows later.
 */

require_once __DIR__ . '/../db_connect.php';

function notify_user(int $userId, string $title, string $body = '', string $kind = 'info', string $link = ''): void {
    global $conn;

    try {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (user_id, title, body, kind, link_url) VALUES (?, ?, ?, ?, ?)'
        );
        $title = mb_substr($title, 0, 255);
        $body  = mb_substr($body, 0, 1000);
        $stmt->bind_param('issss', $userId, $title, $body, $kind, $link);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // A notification is never worth failing the action that caused it.
        error_log('[EXD notify] ' . $e->getMessage());
    }
}

/** Notify every staff member who holds a permission — "someone should look at this". */
function notify_staff(string $permission, string $title, string $body = '', string $kind = 'info', string $link = ''): void {
    global $conn;

    try {
        $admins = fetch_all(
            $conn,
            'SELECT DISTINCT a.id
               FROM admin_users a
               LEFT JOIN admin_roles ar      ON ar.admin_id = a.id
               LEFT JOIN role_permissions rp ON rp.role_id = ar.role_id
               LEFT JOIN permissions p       ON p.id = rp.permission_id
              WHERE a.is_active = 1 AND (a.is_super_admin = 1 OR p.permission_key = ?)',
            's',
            $permission
        );

        $stmt = $conn->prepare(
            'INSERT INTO notifications (admin_id, title, body, kind, link_url) VALUES (?, ?, ?, ?, ?)'
        );
        $title = mb_substr($title, 0, 255);
        $body  = mb_substr($body, 0, 1000);

        foreach ($admins as $admin) {
            $adminId = (int) $admin['id'];
            $stmt->bind_param('issss', $adminId, $title, $body, $kind, $link);
            $stmt->execute();
        }
    } catch (mysqli_sql_exception $e) {
        error_log('[EXD notify] ' . $e->getMessage());
    }
}

function notifications_for_user(int $userId, int $limit = 20): array {
    global $conn;
    $limit = max(1, min(100, $limit));
    return fetch_all(
        $conn,
        'SELECT id, title, body, kind, link_url, read_at, created_at
           FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
        'i',
        $userId
    );
}

function notifications_unread_count(int $userId): int {
    global $conn;
    $row = fetch_one(
        $conn,
        'SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND read_at IS NULL',
        'i',
        $userId
    );
    return (int) ($row['n'] ?? 0);
}

function notifications_mark_read(int $userId): void {
    global $conn;
    $stmt = $conn->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}
