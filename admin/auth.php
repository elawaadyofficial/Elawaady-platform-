<?php
/**
 * Guard for every dashboard page.
 *
 * Include it first, then declare what the page needs:
 *
 *     require_once __DIR__ . '/auth.php';
 *     admin_require('orders.manage');
 *
 * Including this file alone only proves the visitor is staff. A page that
 * changes anything must name the permission it needs.
 */

require_once __DIR__ . '/../lib/admin_auth.php';

admin_boot();

if (!admin_check()) {
    header('Location: login.php');
    exit;
}
