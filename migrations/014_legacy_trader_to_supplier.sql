-- ============================================================================
-- EXD — retire the trader/merchant/vendor concept without losing data
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- The platform has exactly two account types: user and supplier. Legacy data
-- may still carry 'trader', 'merchant' or 'vendor'. Those rows are real
-- accounts belonging to real people, so they are re-labelled as suppliers
-- rather than removed.
--
-- This file only creates the room to record that: the columns that remember
-- what an account used to be called. The re-labelling itself is a data change
-- whose correct shape depends on what the legacy table actually looks like, so
-- it lives in tools/migrate_legacy_accounts.php, which reports what it would
-- change before it changes anything and can be re-run safely.
-- ============================================================================

ALTER TABLE platform_users ADD COLUMN legacy_account_label VARCHAR(40) NULL;
ALTER TABLE platform_users ADD COLUMN legacy_migrated_at DATETIME NULL;
ALTER TABLE platform_users ADD KEY idx_platform_users_legacy (legacy_account_label);

-- A service may still name its supplier in a free-text legacy field. The text
-- is kept; what changes is that no customer-facing query selects it, and the
-- visibility flag below defaults off for every service, new or existing.
ALTER TABLE store_services ADD COLUMN supplier_name VARCHAR(255) NULL;
ALTER TABLE store_services ADD COLUMN supplier_phone VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN supplier_visible TINYINT(1) NOT NULL DEFAULT 0;
