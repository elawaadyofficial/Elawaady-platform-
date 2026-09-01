<?php
/**
 * EXD — create or update a staff account.
 *
 * There is no default administrator and no password in this repository. The
 * first account is created here, on the machine that runs the site, with a
 * password the operator chooses. That is the only way a credential never ends
 * up in git.
 *
 * Usage:
 *   php tools/create_admin.php --username=admin --role=super_admin
 *   php tools/create_admin.php --username=sara --role=support_agent --name="سارة"
 *   php tools/create_admin.php --username=admin --reset-password
 *   php tools/create_admin.php --list
 *
 * The password is read from the terminal, never from an argument, so it does
 * not land in the shell history or the process list.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$options = getopt('', ['username:', 'role:', 'name:', 'email:', 'reset-password', 'list', 'help']);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__, false, null, 0, 1200);
    exit(0);
}

if (isset($options['list'])) {
    $rows = fetch_all(
        $conn,
        'SELECT a.id, a.username, a.display_name, a.is_active, a.is_super_admin, a.last_login_at,
                GROUP_CONCAT(r.role_key ORDER BY r.role_key) AS roles
           FROM admin_users a
           LEFT JOIN admin_roles ar ON ar.admin_id = a.id
           LEFT JOIN roles r        ON r.id = ar.role_id
          GROUP BY a.id ORDER BY a.id'
    );
    if (!$rows) {
        echo "No staff accounts exist yet.\n";
        exit(0);
    }
    printf("%-4s %-18s %-22s %-8s %s\n", 'ID', 'USERNAME', 'ROLES', 'ACTIVE', 'LAST LOGIN');
    foreach ($rows as $row) {
        printf(
            "%-4d %-18s %-22s %-8s %s\n",
            $row['id'],
            $row['username'],
            (int) $row['is_super_admin'] === 1 ? 'super_admin*' : ($row['roles'] ?? '—'),
            (int) $row['is_active'] === 1 ? 'yes' : 'no',
            $row['last_login_at'] ?? 'never'
        );
    }
    exit(0);
}

$username = trim((string) ($options['username'] ?? ''));
if ($username === '') {
    fwrite(STDERR, "--username is required. Try --help.\n");
    exit(1);
}
if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
    fwrite(STDERR, "Username must be 3-60 characters of letters, digits, dot, dash or underscore.\n");
    exit(1);
}

/** Read a password without echoing it. */
function prompt_password(string $label): string {
    fwrite(STDOUT, $label);
    $usedStty = false;
    if (function_exists('shell_exec') && stripos(PHP_OS_FAMILY, 'Windows') === false) {
        $previous = shell_exec('stty -g 2>/dev/null');
        if ($previous !== null && trim((string) $previous) !== '') {
            shell_exec('stty -echo 2>/dev/null');
            $usedStty = true;
        }
    }
    $value = rtrim((string) fgets(STDIN), "\r\n");
    if ($usedStty) {
        shell_exec('stty echo 2>/dev/null');
    }
    fwrite(STDOUT, "\n");
    return $value;
}

$existing = fetch_one($conn, 'SELECT id, username FROM admin_users WHERE username = ? LIMIT 1', 's', $username);

if ($existing !== null && !isset($options['reset-password']) && !isset($options['role'])) {
    fwrite(STDERR, "Account '$username' already exists. Pass --reset-password or --role to change it.\n");
    exit(1);
}

$password = '';
if ($existing === null || isset($options['reset-password'])) {
    $password = prompt_password('Password: ');
    $confirm  = prompt_password('Confirm:  ');

    if ($password !== $confirm) {
        fwrite(STDERR, "Passwords do not match. Nothing was changed.\n");
        exit(1);
    }
    if (strlen($password) < 12) {
        fwrite(STDERR, "A dashboard password must be at least 12 characters. Nothing was changed.\n");
        exit(1);
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        fwrite(STDERR, "The password must contain a letter and a digit. Nothing was changed.\n");
        exit(1);
    }
}

$roleKey = trim((string) ($options['role'] ?? ''));
if ($roleKey !== '') {
    $role = fetch_one($conn, 'SELECT id, role_key, name FROM roles WHERE role_key = ? LIMIT 1', 's', $roleKey);
    if ($role === null) {
        $available = array_column(fetch_all($conn, 'SELECT role_key FROM roles ORDER BY id'), 'role_key');
        fwrite(STDERR, "Unknown role '$roleKey'. Available: " . implode(', ', $available) . "\n");
        exit(1);
    }
} else {
    $role = null;
}

$displayName = trim((string) ($options['name'] ?? $username));
$email       = trim((string) ($options['email'] ?? ''));
$isSuper     = $roleKey === 'super_admin' ? 1 : 0;

$conn->begin_transaction();
try {
    if ($existing === null) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO admin_users (username, password, display_name, email, is_super_admin)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssi', $username, $hash, $displayName, $email, $isSuper);
        $stmt->execute();
        $adminId = (int) $conn->insert_id;
        $verb = 'created';
    } else {
        $adminId = (int) $existing['id'];
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'UPDATE admin_users SET password = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?'
            );
            $stmt->bind_param('si', $hash, $adminId);
            $stmt->execute();
        }
        if ($isSuper === 1) {
            $stmt = $conn->prepare('UPDATE admin_users SET is_super_admin = 1 WHERE id = ?');
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
        }
        $verb = 'updated';
    }

    if ($role !== null) {
        $roleId = (int) $role['id'];
        $stmt = $conn->prepare('INSERT IGNORE INTO admin_roles (admin_id, role_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $adminId, $roleId);
        $stmt->execute();
    }

    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

printf("Staff account '%s' %s (id %d)%s.\n", $username, $verb, $adminId,
    $role !== null ? ", role " . $role['role_key'] : '');
echo "Sign in at /admin/login.php\n";
exit(0);
