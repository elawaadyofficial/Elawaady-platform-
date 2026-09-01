<?php
/**
 * Retired.
 *
 * This file used to run ALTER TABLE against the live database, and it did so
 * without checking who was asking — no session, no permission, no record. Any
 * visitor who knew the URL could reshape the schema.
 *
 * The columns it added are now in migrations/016_service_inventory.sql, and
 * schema is applied from the terminal by php migrate.php. The route is kept so
 * an existing bookmark lands somewhere sensible rather than on a 404.
 */

require_once __DIR__ . '/auth.php';

header('Location: migrate.php');
exit;
