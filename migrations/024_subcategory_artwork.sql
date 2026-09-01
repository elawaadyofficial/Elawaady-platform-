-- ============================================================================
-- EXD — artwork for the subcategories whose pictures name themselves
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- Subcategories were the last tier in the store with no pictures at all: the
-- quick-link row on the home page was 89 rows of a bullet character. The rule
-- for filling them is the same one used for categories and services — the
-- artwork names itself, so matching it is reading rather than guessing:
--
--   service-card-01 reads «خدمات Instagram»       → every إنستجرام row
--   service-card-11 reads «خدمات Snap chat»       → every سناب شات row
--   service-card-12 reads «خدمات Tik Tok»         → every تيك توك row
--   service-card-30 reads «أشتراكات منصة Spotify» → the Spotify row
--   service-card-76 reads «Business Standard»     → the Business Standard row
--
-- A subcategory whose subject has no picture in the library is left alone. It
-- keeps its fallback tile, which is the correct state for a slot with no
-- artwork, and the dashboard can assign one later. Facebook is the largest of
-- those gaps: the library has a card for every platform except that one.
--
-- Every UPDATE is conditional on the column being empty, so a subcategory
-- given artwork in the dashboard keeps it.
-- ============================================================================

SET @exd_sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'store_subcategories'
        AND column_name = 'image') = 0,
    'ALTER TABLE store_subcategories ADD COLUMN image VARCHAR(500) NULL', 'DO 0');
PREPARE exd_stmt FROM @exd_sql; EXECUTE exd_stmt; DEALLOCATE PREPARE exd_stmt;

-- ── Platforms, matched on the platform each row names ───────────────────────
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-01.webp'
 WHERE id IN (7, 8, 9, 10, 11, 12, 32, 56) AND (image IS NULL OR image = '');   -- Instagram
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-12.webp'
 WHERE id IN (13, 14, 15, 16, 17, 18, 33, 39, 57) AND (image IS NULL OR image = '');  -- Tik Tok
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-11.webp'
 WHERE id IN (19, 20, 21, 22, 23, 24, 34, 40, 58) AND (image IS NULL OR image = '');  -- Snap chat
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-10.webp'
 WHERE id IN (25, 26, 27, 28, 29, 30, 59) AND (image IS NULL OR image = '');    -- Youtube
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-04.webp'
 WHERE id = 35 AND (image IS NULL OR image = '');                                -- Google
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-05.webp'
 WHERE id IN (36, 44) AND (image IS NULL OR image = '');                         -- Whatsapp
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-08.webp'
 WHERE id = 38 AND (image IS NULL OR image = '');                                -- Google Ads
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-07.webp'
 WHERE id = 43 AND (image IS NULL OR image = '');                                -- Telegram
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-14.webp'
 WHERE id = 54 AND (image IS NULL OR image = '');                                -- خدمات السيرفرات

-- ── AI plans, matched on the plan printed on the capsule ────────────────────
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-49.webp'
 WHERE id = 61 AND (image IS NULL OR image = '');   -- ChatGPT Plus Plan
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-52.webp'
 WHERE id = 63 AND (image IS NULL OR image = '');   -- Google Gemini Pro Plan
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-43.webp'
 WHERE id = 64 AND (image IS NULL OR image = '');   -- Midjourney Pro
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-40.webp'
 WHERE id = 65 AND (image IS NULL OR image = '');   -- Canva Pro

-- ── Microsoft 365, matched on the plan name printed on each card ────────────
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-71.webp'
 WHERE id = 69 AND (image IS NULL OR image = '');   -- Microsoft 365 Personal
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-75.webp'
 WHERE id = 70 AND (image IS NULL OR image = '');   -- Microsoft 365 Family
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-72.webp'
 WHERE id = 71 AND (image IS NULL OR image = '');   -- Business Basic
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-76.webp'
 WHERE id = 72 AND (image IS NULL OR image = '');   -- Business Standard
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-69.webp'
 WHERE id = 73 AND (image IS NULL OR image = '');   -- Business Premium

-- ── Streaming and music, matched on the platform printed on each card ───────
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-20.webp'
 WHERE id = 77 AND (image IS NULL OR image = '');   -- Netflix
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-30.webp'
 WHERE id = 78 AND (image IS NULL OR image = '');   -- Spotify
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-17.webp'
 WHERE id = 79 AND (image IS NULL OR image = '');   -- YouTube Premium
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-32.webp'
 WHERE id = 81 AND (image IS NULL OR image = '');   -- Amazon Prime Video
UPDATE store_subcategories SET image = 'uploads/services/cards/service-card-19.webp'
 WHERE id = 83 AND (image IS NULL OR image = '');   -- OSN+
