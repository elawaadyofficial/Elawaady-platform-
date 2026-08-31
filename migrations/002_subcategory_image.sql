-- ============================================================================
-- EXD — artwork for subcategories
-- ----------------------------------------------------------------------------
-- Additive only: adds one nullable column. Nothing is dropped, truncated,
-- deleted or redefined, so this is safe against a database holding real data.
--
-- Every subcategory gets its own artwork, the same way every service does.
-- The icon stays in place as a fallback for rows with no image yet.
--
-- Run once, on staging first:
--   mysql -u USER -p DBNAME < migrations/002_subcategory_image.sql
--
-- MySQL has no ADD COLUMN IF NOT EXISTS, so running this twice reports
-- "Duplicate column name 'image'". That error is harmless: it means the column
-- is already there and nothing was changed.
-- ============================================================================

ALTER TABLE store_subcategories
    ADD COLUMN image VARCHAR(255) NULL AFTER description;
