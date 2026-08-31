<?php
/**
 * EXD — account authentication.
 *
 * Two kinds of identity exist on this platform and they never mix. A customer
 * or supplier signs in here, against platform_users. Staff sign in through
 * admin/, against admin_users. Nothing in this file can grant a staff
 * permission, and nothing in the admin login can produce a customer session.
 *
 * A session is a selector/validator pair. The browser holds both; the database
 * holds the selector in clear (so it can be looked up in one indexed read) and
 * only a SHA-256 of the validator. Someone who reads the sessions table
 * therefore cannot mint a working cookie from it.
 */

require_once __DIR__ . '/../db_connect.php';

const AUTH_COOKIE          = 'exd_session';
const AUTH_SESSION_HOURS   = 12;
const AUTH_REMEMBER_DAYS   = 30;
const AUTH_MAX_ATTEMPTS    = 5;
const AUTH_LOCKOUT_MINUTES = 15;

function auth_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => auth_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function auth_client_ip(): string {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function auth_user_agent(): string {
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
}

// ── CSRF ────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    auth_boot();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool {
    auth_boot();
    $expected = $_SESSION['csrf_token'] ?? '';
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

/** Verify the posted token, or stop. Every state-changing POST calls this. */
function csrf_require(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('انتهت صلاحية الجلسة. يُرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.');
    }
}

// ── Rate limiting ───────────────────────────────────────────────────────────

/**
 * Count one attempt against a key. Returns the number of attempts inside the
 * window, so the caller decides what is too many.
 */
function auth_throttle_hit(string $key, string $action, int $windowMinutes = 15): int {
    global $conn;

    $key = substr($key, 0, 190);
    $row = fetch_one(
        $conn,
        'SELECT attempts, first_at FROM auth_throttle WHERE throttle_key = ? AND action = ?',
        'ss',
        $key,
        $action
    );

    $now = date('Y-m-d H:i:s');

    if ($row === null) {
        $stmt = $conn->prepare(
            'INSERT INTO auth_throttle (throttle_key, action, attempts, first_at, last_at)
             VALUES (?, ?, 1, ?, ?)'
        );
        $stmt->bind_param('ssss', $key, $action, $now, $now);
        $stmt->execute();
        return 1;
    }

    // Outside the window the counter starts over rather than accumulating
    // forever, so a genuine user is not punished for a mistake last month.
    if (strtotime((string) $row['first_at']) < time() - $windowMinutes * 60) {
        $stmt = $conn->prepare(
            'UPDATE auth_throttle SET attempts = 1, first_at = ?, last_at = ?
              WHERE throttle_key = ? AND action = ?'
        );
        $stmt->bind_param('ssss', $now, $now, $key, $action);
        $stmt->execute();
        return 1;
    }

    $attempts = (int) $row['attempts'] + 1;
    $stmt = $conn->prepare(
        'UPDATE auth_throttle SET attempts = ?, last_at = ? WHERE throttle_key = ? AND action = ?'
    );
    $stmt->bind_param('isss', $attempts, $now, $key, $action);
    $stmt->execute();
    return $attempts;
}

function auth_throttle_clear(string $key, string $action): void {
    global $conn;
    $key  = substr($key, 0, 190);
    $stmt = $conn->prepare('DELETE FROM auth_throttle WHERE throttle_key = ? AND action = ?');
    $stmt->bind_param('ss', $key, $action);
    $stmt->execute();
}

function auth_throttle_remaining(string $key, string $action, int $limit = AUTH_MAX_ATTEMPTS): int {
    global $conn;
    $key = substr($key, 0, 190);
    $row = fetch_one(
        $conn,
        'SELECT attempts, first_at FROM auth_throttle WHERE throttle_key = ? AND action = ?',
        'ss',
        $key,
        $action
    );
    if ($row === null) {
        return $limit;
    }
    if (strtotime((string) $row['first_at']) < time() - AUTH_LOCKOUT_MINUTES * 60) {
        return $limit;
    }
    return max(0, $limit - (int) $row['attempts']);
}

// ── Registration ────────────────────────────────────────────────────────────

/**
 * Validate a registration payload. Returns a list of Arabic messages; an empty
 * list means the payload is good.
 */
function auth_validate_registration(array $input, string $accountType): array {
    $errors = [];

    $name  = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $pass  = (string) ($input['password'] ?? '');
    $again = (string) ($input['confirm_password'] ?? '');

    if (mb_strlen($name) < 3) {
        $errors[] = 'الاسم قصير جدًا — أدخل الاسم الكامل.';
    }
    if (mb_strlen($name) > 190) {
        $errors[] = 'الاسم طويل جدًا.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح.';
    }
    // Egyptian mobile numbers and international forms both pass; letters do not.
    if (!preg_match('/^[0-9+\s()-]{8,20}$/', $phone)) {
        $errors[] = 'رقم الهاتف غير صحيح.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    }
    if ($pass !== '' && !preg_match('/[A-Za-z]/', $pass)) {
        $errors[] = 'كلمة المرور يجب أن تحتوي على حرف واحد على الأقل.';
    }
    if ($pass !== '' && !preg_match('/[0-9]/', $pass)) {
        $errors[] = 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل.';
    }
    if ($pass !== $again) {
        $errors[] = 'كلمة المرور وتأكيدها غير متطابقتين.';
    }
    if (!in_array($accountType, ['user', 'supplier'], true)) {
        $errors[] = 'نوع الحساب غير صالح.';
    }
    if (empty($input['agree'])) {
        $errors[] = 'يجب الموافقة على شروط الاستخدام وسياسة الخصوصية.';
    }

    return $errors;
}

function auth_email_taken(string $email): bool {
    global $conn;
    $row = fetch_one($conn, 'SELECT id FROM platform_users WHERE email = ? LIMIT 1', 's', $email);
    return $row !== null;
}

/**
 * Create an account. A user is active at once; a supplier is pending until an
 * administrator approves it, which is why the two share one column and one
 * table rather than two code paths.
 *
 * Returns the new user id.
 */
function auth_register(array $input, string $accountType): int {
    global $conn;

    $name   = trim((string) $input['name']);
    $email  = strtolower(trim((string) $input['email']));
    $phone  = trim((string) $input['phone']);
    $hash   = password_hash((string) $input['password'], PASSWORD_DEFAULT);
    $status = $accountType === 'supplier' ? 'pending' : 'active';
    $now    = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO platform_users
                (account_type, name, email, phone, password_hash, password_changed_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssss', $accountType, $name, $email, $phone, $hash, $now, $status);
        $stmt->execute();
        $userId = (int) $conn->insert_id;

        // Every account owns a wallet from the moment it exists, so no code
        // path ever has to ask whether one is there.
        $wallet = $conn->prepare('INSERT INTO wallets (user_id) VALUES (?)');
        $wallet->bind_param('i', $userId);
        $wallet->execute();

        if ($accountType === 'supplier') {
            $profile = $conn->prepare(
                'INSERT INTO supplier_profiles (user_id, company_name, services_desc) VALUES (?, ?, ?)'
            );
            $company  = trim((string) ($input['company'] ?? ''));
            $services = trim((string) ($input['services_desc'] ?? ''));
            $profile->bind_param('iss', $userId, $company, $services);
            $profile->execute();
        }

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        throw $e;
    }

    audit_log('user', $userId, $name, 'account.registered', 'platform_users', $userId,
        $accountType === 'supplier' ? 'تسجيل حساب مورد بانتظار الاعتماد' : 'تسجيل حساب مستخدم');

    return $userId;
}

// ── Sign in and out ─────────────────────────────────────────────────────────

/**
 * Attempt a sign-in. Returns [ok, user|null, message].
 *
 * The failure message never distinguishes "no such account" from "wrong
 * password", because that difference tells an attacker which addresses are
 * registered.
 */
function auth_attempt(string $email, string $password, bool $remember = false): array {
    global $conn;

    $email = strtolower(trim($email));
    $ip    = auth_client_ip();

    $byIp = auth_throttle_hit($ip !== '' ? $ip : 'unknown', 'login', AUTH_LOCKOUT_MINUTES);
    if ($byIp > AUTH_MAX_ATTEMPTS * 4) {
        return [false, null, 'محاولات كثيرة من هذا الجهاز. حاول بعد ' . AUTH_LOCKOUT_MINUTES . ' دقيقة.'];
    }

    $key      = $ip . '|' . $email;
    $attempts = auth_throttle_hit($key, 'login', AUTH_LOCKOUT_MINUTES);
    if ($attempts > AUTH_MAX_ATTEMPTS) {
        return [false, null, 'تم إيقاف المحاولات مؤقتًا. حاول بعد ' . AUTH_LOCKOUT_MINUTES . ' دقيقة.'];
    }

    $user = fetch_one(
        $conn,
        'SELECT id, account_type, name, email, password_hash, status, locked_until
           FROM platform_users WHERE email = ? LIMIT 1',
        's',
        $email
    );

    // Hash something anyway when the account does not exist, so a missing
    // address does not answer measurably faster than a wrong password.
    if ($user === null) {
        password_verify($password, '$2y$12$usesomesillystringforsalt0000000000000000000000000000000000');
        return [false, null, 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'];
    }

    if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
        return [false, null, 'الحساب موقوف مؤقتًا بسبب محاولات دخول متكررة.'];
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        auth_register_failure((int) $user['id']);
        return [false, null, 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'];
    }

    if ($user['status'] === 'suspended') {
        return [false, null, 'هذا الحساب موقوف. تواصل مع الدعم.'];
    }
    if ($user['status'] === 'rejected') {
        return [false, null, 'لم يتم اعتماد هذا الحساب. تواصل مع الدعم.'];
    }
    if ($user['status'] === 'pending') {
        // A pending supplier signs in and sees its own status page; it simply
        // has nothing else it may do yet.
        $message = 'حسابك قيد المراجعة. يمكنك الدخول لمتابعة حالة الطلب.';
    } else {
        $message = '';
    }

    // A correct password on a newer cost factor is rehashed transparently.
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt    = $conn->prepare('UPDATE platform_users SET password_hash = ? WHERE id = ?');
        $userId  = (int) $user['id'];
        $stmt->bind_param('si', $newHash, $userId);
        $stmt->execute();
    }

    auth_throttle_clear($key, 'login');
    auth_start_session((int) $user['id'], $remember);

    $stmt = $conn->prepare(
        'UPDATE platform_users
            SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?
          WHERE id = ?'
    );
    $userId = (int) $user['id'];
    $stmt->bind_param('si', $ip, $userId);
    $stmt->execute();

    audit_log($user['account_type'], $userId, (string) $user['name'], 'account.login', 'platform_users', $userId);

    return [true, $user, $message];
}

function auth_register_failure(int $userId): void {
    global $conn;
    $stmt = $conn->prepare(
        'UPDATE platform_users
            SET failed_login_count = failed_login_count + 1,
                locked_until = CASE WHEN failed_login_count + 1 >= ?
                                    THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                                    ELSE locked_until END
          WHERE id = ?'
    );
    $max     = AUTH_MAX_ATTEMPTS;
    $minutes = AUTH_LOCKOUT_MINUTES;
    $stmt->bind_param('iii', $max, $minutes, $userId);
    $stmt->execute();
}

function auth_start_session(int $userId, bool $remember = false): void {
    global $conn;

    auth_boot();
    session_regenerate_id(true);

    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $lifetime  = $remember ? AUTH_REMEMBER_DAYS * 86400 : AUTH_SESSION_HOURS * 3600;
    $expires   = date('Y-m-d H:i:s', time() + $lifetime);
    $ip        = auth_client_ip();
    $agent     = auth_user_agent();
    $rememberFlag = $remember ? 1 : 0;

    $stmt = $conn->prepare(
        'INSERT INTO user_sessions
            (user_id, selector, validator_hash, ip_address, user_agent, remember_me, expires_at, last_seen_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('issssis', $userId, $selector, $hash, $ip, $agent, $rememberFlag, $expires);
    $stmt->execute();

    setcookie(AUTH_COOKIE, $selector . ':' . $validator, [
        'expires'  => $remember ? time() + $lifetime : 0,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_SESSION['user_id'] = $userId;
}

/** Resolve the signed-in account, or null. Cached for the request. */
function auth_user(): ?array {
    global $conn;
    static $resolved = false;
    static $user     = null;

    if ($resolved) {
        return $user;
    }
    $resolved = true;

    $cookie = (string) ($_COOKIE[AUTH_COOKIE] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return null;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (strlen($selector) !== 32 || $validator === '') {
        return null;
    }

    $session = fetch_one(
        $conn,
        'SELECT id, user_id, validator_hash, expires_at, revoked_at
           FROM user_sessions WHERE selector = ? LIMIT 1',
        's',
        $selector
    );

    if ($session === null
        || $session['revoked_at'] !== null
        || strtotime((string) $session['expires_at']) < time()) {
        return null;
    }

    if (!hash_equals((string) $session['validator_hash'], hash('sha256', $validator))) {
        // A valid selector with a wrong validator is a stolen or guessed
        // cookie. Revoke the session rather than merely refusing this request.
        $stmt = $conn->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ?');
        $sessionId = (int) $session['id'];
        $stmt->bind_param('i', $sessionId);
        $stmt->execute();
        return null;
    }

    $user = fetch_one(
        $conn,
        'SELECT id, account_type, name, email, phone, avatar, status, created_at
           FROM platform_users WHERE id = ? LIMIT 1',
        'i',
        (int) $session['user_id']
    );

    if ($user === null || $user['status'] === 'suspended') {
        $user = null;
        return null;
    }

    $touch = $conn->prepare('UPDATE user_sessions SET last_seen_at = NOW() WHERE id = ?');
    $sessionId = (int) $session['id'];
    $touch->bind_param('i', $sessionId);
    $touch->execute();

    return $user;
}

function auth_check(): bool {
    return auth_user() !== null;
}

function auth_is_supplier(): bool {
    $user = auth_user();
    return $user !== null && $user['account_type'] === 'supplier';
}

/** A supplier whose account is still pending may sign in but may not trade. */
function auth_is_approved_supplier(): bool {
    $user = auth_user();
    return $user !== null && $user['account_type'] === 'supplier' && $user['status'] === 'active';
}

function auth_logout(): void {
    global $conn;

    $cookie = (string) ($_COOKIE[AUTH_COOKIE] ?? '');
    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        $stmt = $conn->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE selector = ?');
        $stmt->bind_param('s', $selector);
        $stmt->execute();
    }

    setcookie(AUTH_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    auth_boot();
    $_SESSION = [];
    session_destroy();
}

/** Send anyone who is not signed in to the login page, remembering where. */
function auth_require_login(string $redirectTo = ''): void {
    if (auth_check()) {
        return;
    }
    $target = $redirectTo !== '' ? $redirectTo : ($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?next=' . urlencode($target));
    exit;
}

// ── Password reset ──────────────────────────────────────────────────────────

/**
 * Issue a reset token. Returns the token to mail, or null when the address is
 * unknown — the caller shows the same message either way.
 */
function auth_create_password_reset(string $email): ?string {
    global $conn;

    $email = strtolower(trim($email));
    $user  = fetch_one($conn, 'SELECT id FROM platform_users WHERE email = ? LIMIT 1', 's', $email);
    if ($user === null) {
        return null;
    }

    $selector = bin2hex(random_bytes(16));
    $token    = bin2hex(random_bytes(32));
    $hash     = hash('sha256', $token);
    $expires  = date('Y-m-d H:i:s', time() + 3600);
    $ip       = auth_client_ip();
    $userId   = (int) $user['id'];

    $stmt = $conn->prepare(
        'INSERT INTO password_resets (user_id, selector, token_hash, expires_at, request_ip)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $userId, $selector, $hash, $expires, $ip);
    $stmt->execute();

    return $selector . ':' . $token;
}

/** Consume a reset token and set the new password. Returns true on success. */
function auth_complete_password_reset(string $combined, string $newPassword): bool {
    global $conn;

    if (!str_contains($combined, ':')) {
        return false;
    }
    [$selector, $token] = explode(':', $combined, 2);

    $reset = fetch_one(
        $conn,
        'SELECT id, user_id, token_hash, expires_at, used_at
           FROM password_resets WHERE selector = ? LIMIT 1',
        's',
        $selector
    );

    if ($reset === null
        || $reset['used_at'] !== null
        || strtotime((string) $reset['expires_at']) < time()
        || !hash_equals((string) $reset['token_hash'], hash('sha256', $token))) {
        return false;
    }

    $hash   = password_hash($newPassword, PASSWORD_DEFAULT);
    $userId = (int) $reset['user_id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'UPDATE platform_users
                SET password_hash = ?, password_changed_at = NOW(),
                    failed_login_count = 0, locked_until = NULL
              WHERE id = ?'
        );
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();

        $mark = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $resetId = (int) $reset['id'];
        $mark->bind_param('i', $resetId);
        $mark->execute();

        // Changing a password ends every other session on every other device.
        $kill = $conn->prepare(
            'UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL'
        );
        $kill->bind_param('i', $userId);
        $kill->execute();

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return false;
    }

    audit_log('user', $userId, '', 'account.password_reset', 'platform_users', $userId);
    return true;
}

// ── Audit ───────────────────────────────────────────────────────────────────

function audit_log(
    string $actorType,
    ?int $actorId,
    string $actorLabel,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    string $summary = '',
    string $details = ''
): void {
    global $conn;

    if (!in_array($actorType, ['admin', 'user', 'supplier', 'system'], true)) {
        $actorType = 'system';
    }

    try {
        $stmt = $conn->prepare(
            'INSERT INTO audit_log
                (actor_type, actor_id, actor_label, action, entity_type, entity_id, summary, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ip = auth_client_ip();
        $stmt->bind_param(
            'sisssisss',
            $actorType, $actorId, $actorLabel, $action, $entityType, $entityId, $summary, $details, $ip
        );
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // An audit write must never take a request down with it.
        error_log('[EXD audit] ' . $e->getMessage());
    }
}
