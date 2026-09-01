<?php
/**
 * Shared pieces for the dashboard pages.
 *
 * These exist so a page can be about its subject rather than about markup. A
 * page that manages suppliers should read as a list of things you can do to a
 * supplier, not as three hundred lines of table scaffolding.
 */

require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/notify.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../settings_helper.php';

/** Flash a message across a redirect, so a POST never leaves a resubmittable page. */
function admin_flash(string $type, string $message): void {
    admin_boot();
    $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
}

function admin_flash_render(): string {
    admin_boot();
    $messages = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);

    $out = '';
    foreach ($messages as $flash) {
        $class = match ($flash['type']) {
            'success' => 'alert-success',
            'error'   => 'alert-error',
            default   => 'alert-info',
        };
        $out .= '<div class="alert ' . $class . '">' . e($flash['message']) . '</div>';
    }
    return $out;
}

/** Redirect after a write. Always used, so a refresh never repeats an action. */
function admin_redirect(string $to): never {
    header('Location: ' . $to);
    exit;
}

/** A page-size-limited slice plus the paging links that go with it. */
function admin_paginate(int $total, int $perPage = 30): array {
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per_page' => $perPage];
}

function admin_pager(array $paging, string $baseQuery = ''): string {
    if ($paging['pages'] <= 1) {
        return '';
    }
    $out = '<div class="pager">';
    for ($i = 1; $i <= $paging['pages']; $i++) {
        if ($paging['pages'] > 12 && $i > 3 && $i < $paging['pages'] - 2 && abs($i - $paging['page']) > 1) {
            if ($i === 4) {
                $out .= '<span class="pager-gap">…</span>';
            }
            continue;
        }
        $href = '?' . ($baseQuery !== '' ? $baseQuery . '&' : '') . 'page=' . $i;
        $out .= '<a class="pager-link' . ($i === $paging['page'] ? ' is-current' : '') . '" href="'
              . e($href) . '">' . $i . '</a>';
    }
    return $out . '</div>';
}

/** A coloured status chip. */
function admin_badge(string $label, string $tone = 'inactive'): string {
    $class = match ($tone) {
        'active', 'success' => 'badge-active',
        'review', 'warning' => 'badge-review',
        'hidden', 'danger'  => 'badge-hidden',
        default             => 'badge-inactive',
    };
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

/**
 * A one-button form that posts an action with a CSRF token.
 *
 * Every state change in the dashboard goes through one of these rather than a
 * link, because a link is a GET and a GET must not change anything.
 */
function admin_action_button(
    string $action,
    array $fields,
    string $label,
    string $class = 'btn btn-secondary btn-sm',
    string $confirm = ''
): string {
    $out = '<form method="post" class="inline-form"'
         . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>';
    $out .= csrf_field();
    $out .= '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $name => $value) {
        $out .= '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }
    return $out . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
}

/** Arabic labels for the order workflow, used by both the dashboard and the store. */
function admin_order_status_label(string $status): string {
    return [
        'new'              => 'جديد',
        'waiting_approval' => 'بانتظار الاعتماد',
        'waiting_payment'  => 'بانتظار الدفع',
        'paid'             => 'مدفوع',
        'in_progress'      => 'قيد التنفيذ',
        'delivered'        => 'تم التسليم',
        'completed'        => 'مكتمل',
        'cancelled'        => 'ملغي',
        'refunded'         => 'مسترد',
        'dispute'          => 'نزاع',
    ][$status] ?? $status;
}

function admin_order_status_tone(string $status): string {
    return match ($status) {
        'completed', 'delivered'                          => 'active',
        'new', 'waiting_approval', 'waiting_payment'      => 'review',
        'paid', 'in_progress'                             => 'active',
        'dispute'                                         => 'hidden',
        default                                           => 'inactive',
    };
}

/**
 * The order workflow, as a graph.
 *
 * A status may only move to a status this table allows. Encoding it once means
 * no page can invent a transition, and a status that leads nowhere — completed,
 * refunded — is simply an empty list.
 */
function admin_order_transitions(): array {
    return [
        'new'              => ['waiting_approval', 'waiting_payment', 'paid', 'in_progress', 'cancelled'],
        'waiting_approval' => ['waiting_payment', 'paid', 'in_progress', 'cancelled'],
        'waiting_payment'  => ['paid', 'cancelled'],
        'paid'             => ['in_progress', 'refunded', 'dispute'],
        'in_progress'      => ['delivered', 'completed', 'dispute', 'cancelled'],
        'delivered'        => ['completed', 'dispute'],
        'completed'        => ['dispute'],
        'cancelled'        => [],
        'refunded'         => [],
        'dispute'          => ['in_progress', 'completed', 'refunded', 'cancelled'],
    ];
}

function admin_order_can_move(string $from, string $to): bool {
    return in_array($to, admin_order_transitions()[$from] ?? [], true);
}

/** Count something for a sidebar badge, tolerating a table that is not there yet. */
function admin_count(string $sql, string $types = '', ...$params): int {
    global $conn;
    try {
        $row = fetch_one($conn, $sql, $types, ...$params);
        return (int) ($row['n'] ?? 0);
    } catch (mysqli_sql_exception $e) {
        return 0;
    }
}

/** Read a positive integer from the request, or 0. */
function admin_id(string $key): int {
    return max(0, (int) ($_POST[$key] ?? $_GET[$key] ?? 0));
}

/** Close the layout. Every page that opened it ends with this. */
function admin_layout_end(): void {
    echo "\n    </div>\n  </div>\n</div>\n</body>\n</html>\n";
}
