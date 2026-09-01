<?php
/**
 * EXD — staff authentication and permissions.
 *
 * Staff are a separate population from customers and suppliers. An admin
 * session cannot be produced by the customer login and carries no customer
 * identity; a customer session grants nothing here.
 *
 * Authorisation is by permission key, never by role name. Code asks "may this
 * person confirm a payment?" and not "is this person the finance admin?", so
 * changing who holds a permission is an edit to the role matrix rather than a
 * change to every page that checks it.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/auth.php';

const ADMIN_COOKIE          = 'exd_admin';
const ADMIN_SESSION_HOURS   = 8;
const ADMIN_MAX_ATTEMPTS    = 5;
const ADMIN_LOCKOUT_MINUTES = 15;

function admin_boot(): void {
    auth_boot();
}

/** Resolve the signed-in staff member, or null. Cached for the request. */
function admin_user(): ?array {
    global $conn;
    static $resolved = false;
    static $admin    = null;

    if ($resolved) {
        return $admin;
    }
    $resolved = true;

    $cookie = (string) ($_COOKIE[ADMIN_COOKIE] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return null;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (strlen($selector) !== 32 || $validator === '') {
        return null;
    }

    $session = fetch_one(
        $conn,
        'SELECT id, admin_id, validator_hash, expires_at, revoked_at
           FROM admin_sessions WHERE selector = ? LIMIT 1',
        's',
        $selector
    );

    if ($session === null
        || $session['revoked_at'] !== null
        || strtotime((string) $session['expires_at']) < time()) {
        return null;
    }

    if (!hash_equals((string) $session['validator_hash'], hash('sha256', $validator))) {
        $stmt = $conn->prepare('UPDATE admin_sessions SET revoked_at = NOW() WHERE id = ?');
        $sessionId = (int) $session['id'];
        $stmt->bind_param('i', $sessionId);
        $stmt->execute();
        return null;
    }

    $admin = fetch_one(
        $conn,
        'SELECT id, username, display_name, email, is_active, is_super_admin
           FROM admin_users WHERE id = ? LIMIT 1',
        'i',
        (int) $session['admin_id']
    );

    if ($admin === null || (int) $admin['is_active'] !== 1) {
        $admin = null;
        return null;
    }

    return $admin;
}

function admin_check(): bool {
    return admin_user() !== null;
}

/** Every permission key this staff member holds, through any of their roles. */
function admin_permissions(): array {
    global $conn;
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $admin = admin_user();
    if ($admin === null) {
        return $cache = [];
    }

    // A super admin holds everything, including permissions added later.
    if ((int) $admin['is_super_admin'] === 1) {
        $rows = fetch_all($conn, 'SELECT permission_key FROM permissions');
        return $cache = array_column($rows, 'permission_key');
    }

    $rows = fetch_all(
        $conn,
        'SELECT DISTINCT p.permission_key
           FROM admin_roles ar
           JOIN role_permissions rp ON rp.role_id = ar.role_id
           JOIN permissions p       ON p.id = rp.permission_id
          WHERE ar.admin_id = ?',
        'i',
        (int) $admin['id']
    );

    return $cache = array_column($rows, 'permission_key');
}

function admin_can(string $permission): bool {
    return in_array($permission, admin_permissions(), true);
}

/** Any one of these is enough. */
function admin_can_any(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (admin_can($permission)) {
            return true;
        }
    }
    return false;
}

function admin_roles(): array {
    global $conn;
    $admin = admin_user();
    if ($admin === null) {
        return [];
    }
    return fetch_all(
        $conn,
        'SELECT r.role_key, r.name FROM admin_roles ar
           JOIN roles r ON r.id = ar.role_id WHERE ar.admin_id = ?',
        'i',
        (int) $admin['id']
    );
}

/** Guard a page. Not signed in goes to the login; signed in but not permitted
 *  gets a plain refusal rather than a redirect loop. */
function admin_require(string $permission = ''): void {
    if (!admin_check()) {
        header('Location: login.php');
        exit;
    }
    if ($permission !== '' && !admin_can($permission)) {
        http_response_code(403);
        require __DIR__ . '/../admin/denied.php';
        exit;
    }
}

function admin_attempt(string $username, string $password): array {
    global $conn;

    $username = trim($username);
    $ip       = auth_client_ip();

    $attempts = auth_throttle_hit(($ip ?: 'unknown') . '|admin|' . $username, 'admin_login', ADMIN_LOCKOUT_MINUTES);
    if ($attempts > ADMIN_MAX_ATTEMPTS) {
        return [false, 'تم إيقاف المحاولات مؤقتًا. حاول بعد ' . ADMIN_LOCKOUT_MINUTES . ' دقيقة.'];
    }

    $admin = fetch_one(
        $conn,
        'SELECT id, username, password, is_active, locked_until FROM admin_users WHERE username = ? LIMIT 1',
        's',
        $username
    );

    if ($admin === null) {
        password_verify($password, '$2y$12$usesomesillystringforsalt0000000000000000000000000000000000');
        return [false, 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
    }

    if ($admin['locked_until'] !== null && strtotime((string) $admin['locked_until']) > time()) {
        return [false, 'الحساب موقوف مؤقتًا بسبب محاولات دخول متكررة.'];
    }

    if (!password_verify($password, (string) $admin['password'])) {
        $stmt = $conn->prepare(
            'UPDATE admin_users
                SET failed_login_count = failed_login_count + 1,
                    locked_until = CASE WHEN failed_login_count + 1 >= ?
                                        THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                                        ELSE locked_until END
              WHERE id = ?'
        );
        $max     = ADMIN_MAX_ATTEMPTS;
        $minutes = ADMIN_LOCKOUT_MINUTES;
        $adminId = (int) $admin['id'];
        $stmt->bind_param('iii', $max, $minutes, $adminId);
        $stmt->execute();
        return [false, 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
    }

    if ((int) $admin['is_active'] !== 1) {
        return [false, 'هذا الحساب غير مفعّل.'];
    }

    if (password_needs_rehash((string) $admin['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt    = $conn->prepare('UPDATE admin_users SET password = ? WHERE id = ?');
        $adminId = (int) $admin['id'];
        $stmt->bind_param('si', $newHash, $adminId);
        $stmt->execute();
    }

    admin_start_session((int) $admin['id']);
    auth_throttle_clear(($ip ?: 'unknown') . '|admin|' . $username, 'admin_login');

    $stmt = $conn->prepare(
        'UPDATE admin_users
            SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?
          WHERE id = ?'
    );
    $adminId = (int) $admin['id'];
    $stmt->bind_param('si', $ip, $adminId);
    $stmt->execute();

    audit_log('admin', $adminId, (string) $admin['username'], 'admin.login');

    return [true, ''];
}

function admin_start_session(int $adminId): void {
    global $conn;

    admin_boot();
    session_regenerate_id(true);

    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + ADMIN_SESSION_HOURS * 3600);
    $ip        = auth_client_ip();
    $agent     = auth_user_agent();

    $stmt = $conn->prepare(
        'INSERT INTO admin_sessions (admin_id, selector, validator_hash, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $adminId, $selector, $hash, $ip, $agent, $expires);
    $stmt->execute();

    setcookie(ADMIN_COOKIE, $selector . ':' . $validator, [
        'expires'  => 0,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function admin_logout(): void {
    global $conn;

    $admin  = admin_user();
    $cookie = (string) ($_COOKIE[ADMIN_COOKIE] ?? '');

    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        $stmt = $conn->prepare('UPDATE admin_sessions SET revoked_at = NOW() WHERE selector = ?');
        $stmt->bind_param('s', $selector);
        $stmt->execute();
    }

    if ($admin !== null) {
        audit_log('admin', (int) $admin['id'], (string) $admin['username'], 'admin.logout');
    }

    setcookie(ADMIN_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Record a staff action against the entity it touched. */
function admin_audit(string $action, ?string $entityType = null, ?int $entityId = null, string $summary = '', string $details = ''): void {
    $admin = admin_user();
    audit_log(
        'admin',
        $admin !== null ? (int) $admin['id'] : null,
        $admin !== null ? (string) $admin['username'] : '',
        $action,
        $entityType,
        $entityId,
        $summary,
        $details
    );
}
