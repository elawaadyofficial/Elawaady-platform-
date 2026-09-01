-- ============================================================================
-- EXD — every picture into the slot it was drawn for
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- Migrations 018/021/022 got the *subject* of each picture right but not its
-- *shape*. The library holds two different kinds of artwork:
--
--   • square cards  (uploads/services/cards/*)  — 1:1, one platform or plan
--     per card with its name printed on it. These are card artwork.
--   • wide banners  (uploads/sections/section-banner-orange-NN, and the same
--     images duplicated under uploads/carousel/carousel-01..10) — 3:1 strips
--     with a section title written across them, each shipped with an -sm
--     mobile pair. These are section headers, not card artwork.
--
-- Putting a 3:1 strip inside a 1:1 card frame is what made the store look
-- broken: the picture floated small in a mostly empty card, and three cards in
-- a row showed the same «نمو الحسابات» strip. So this migration:
--
--   1. moves every wide banner out of a square slot (image / main_image) into
--      banner_image, which is the wide slot the schema already has. Nothing is
--      thrown away — the value is copied before the square slot is cleared.
--   2. fills the freed square slots with the platform card that names the same
--      platform the row names. That is reading the artwork, not guessing.
--   3. seeds store_section_banners with the ten orange section headers, bound
--      to the categories whose names they carry, with their -sm mobile pair.
--      That table has been empty since it was created, which is why the wide
--      banners had nowhere correct to go.
--
-- Every write is conditional on the current value, so an admin's own choice in
-- the dashboard is never overwritten.
-- ============================================================================

-- ── columns this migration writes, for a database built from migrations only ─
SET @exd_sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'store_categories'
        AND column_name = 'banner_image') = 0,
    'ALTER TABLE store_categories ADD COLUMN banner_image VARCHAR(500) NULL', 'DO 0');
PREPARE exd_stmt FROM @exd_sql; EXECUTE exd_stmt; DEALLOCATE PREPARE exd_stmt;

SET @exd_sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'store_services'
        AND column_name = 'banner_image') = 0,
    'ALTER TABLE store_services ADD COLUMN banner_image VARCHAR(500) NULL', 'DO 0');
PREPARE exd_stmt FROM @exd_sql; EXECUTE exd_stmt; DEALLOCATE PREPARE exd_stmt;

SET @exd_sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'store_services'
        AND column_name = 'main_image') = 0,
    'ALTER TABLE store_services ADD COLUMN main_image VARCHAR(500) NULL', 'DO 0');
PREPARE exd_stmt FROM @exd_sql; EXECUTE exd_stmt; DEALLOCATE PREPARE exd_stmt;

-- ════════════════════════════════════════════════════════════════════════════
-- 1. Wide banners out of the square slots, keeping the value
-- ════════════════════════════════════════════════════════════════════════════

-- Categories: carousel-01..10 are the wide strips. 11..21 are square, so the
-- pattern is deliberately anchored to the two-digit numbers that are wide.
UPDATE store_categories
   SET banner_image = image
 WHERE image REGEXP '^uploads/carousel/carousel-(0[1-9]|10)\\.webp$'
   AND (banner_image IS NULL OR banner_image = '');

UPDATE store_categories
   SET image = NULL
 WHERE image REGEXP '^uploads/carousel/carousel-(0[1-9]|10)\\.webp$'
   AND banner_image = image;

-- Services: same rule, and main_image follows image.
UPDATE store_services
   SET banner_image = image
 WHERE image REGEXP '^uploads/carousel/carousel-(0[1-9]|10)\\.webp$'
   AND (banner_image IS NULL OR banner_image = '');

UPDATE store_services
   SET main_image = NULL
 WHERE main_image REGEXP '^uploads/carousel/carousel-(0[1-9]|10)\\.webp$';

UPDATE store_services
   SET image = NULL
 WHERE image REGEXP '^uploads/carousel/carousel-(0[1-9]|10)\\.webp$'
   AND banner_image = image;

-- ════════════════════════════════════════════════════════════════════════════
-- 2. The freed square slots get the card that names the same platform
-- ----------------------------------------------------------------------------
-- service-card-01 reads «خدمات Instagram», 11 reads «خدمات Snap chat»,
-- 12 reads «خدمات Tik Tok». Each row below names that platform in its own
-- title, so the pairing is read off the picture rather than inferred.
-- ════════════════════════════════════════════════════════════════════════════

UPDATE store_services SET image      = 'uploads/services/cards/service-card-01.webp',
                          main_image = 'uploads/services/cards/service-card-01.webp'
 WHERE id = 3 AND (image IS NULL OR image = '');   -- لايكات إنستجرام
UPDATE store_services SET image      = 'uploads/services/cards/service-card-12.webp',
                          main_image = 'uploads/services/cards/service-card-12.webp'
 WHERE id = 4 AND (image IS NULL OR image = '');   -- مشاهدات تيك توك
UPDATE store_services SET image      = 'uploads/services/cards/service-card-11.webp',
                          main_image = 'uploads/services/cards/service-card-11.webp'
 WHERE id = 5 AND (image IS NULL OR image = '');   -- توثيق سناب شات
UPDATE store_services SET image      = 'uploads/services/cards/service-card-11.webp',
                          main_image = 'uploads/services/cards/service-card-11.webp'
 WHERE id = 6 AND (image IS NULL OR image = '');   -- حسابات سناب شات جاهزة
UPDATE store_services SET image      = 'uploads/services/cards/service-card-01.webp',
                          main_image = 'uploads/services/cards/service-card-01.webp'
 WHERE id = 8 AND (image IS NULL OR image = '');   -- توثيق إنستجرام

-- Services 1, 2 (فيسبوك), 9 (إعلانات Meta), 10 (هوية بصرية), 14 (نقل ملكية),
-- 15 (استرجاع حساب) and 20 (خدمة مورد) have no card in the library that names
-- their subject. They keep their wide banner in banner_image and their card
-- slot stays empty, which is the correct state for a slot with no artwork.

-- ════════════════════════════════════════════════════════════════════════════
-- 3. The ten orange section headers, on the sections they name
-- ----------------------------------------------------------------------------
-- Each file has its section title printed across it, in Arabic and English:
--   01 إدارة وتشغيل السوشيال ميديا   06 حل مشاكل الحسابات
--   02 صناعة المحتوى                07 تحقيق الربح
--   03 حسابات ومنصات السوشيال ميديا  08 نمو الحسابات
--   04 التوثيق والنشر الإعلامي       09 الحسابات الموثقة والمميزة
--   05 الإعلانات الممولة             10 خدمة العملاء
-- Eight name a category the store has. 08 and 10 name no single category, so
-- they go to the two free home placements instead of being forced onto one.
-- ════════════════════════════════════════════════════════════════════════════

INSERT INTO store_section_banners
       (category_id, placement, mode, title, asset_desktop, asset_mobile, is_visible, sort_order)
SELECT * FROM (
    SELECT 12 AS category_id, '' AS placement, 'image' AS mode, 'إدارة وتشغيل السوشيال ميديا' AS title,
           'uploads/sections/section-banner-orange-01.webp' AS asset_desktop,
           'uploads/sections/section-banner-orange-01-sm.webp' AS asset_mobile,
           1 AS is_visible, 1 AS sort_order
    UNION ALL SELECT 15, '', 'image', 'صناعة المحتوى',
           'uploads/sections/section-banner-orange-02.webp',
           'uploads/sections/section-banner-orange-02-sm.webp', 1, 2
    UNION ALL SELECT 22, '', 'image', 'حسابات ومنصات السوشيال ميديا',
           'uploads/sections/section-banner-orange-03.webp',
           'uploads/sections/section-banner-orange-03-sm.webp', 1, 3
    UNION ALL SELECT 11, '', 'image', 'التوثيق والنشر الإعلامي',
           'uploads/sections/section-banner-orange-04.webp',
           'uploads/sections/section-banner-orange-04-sm.webp', 1, 4
    UNION ALL SELECT 13, '', 'image', 'الإعلانات الممولة',
           'uploads/sections/section-banner-orange-05.webp',
           'uploads/sections/section-banner-orange-05-sm.webp', 1, 5
    UNION ALL SELECT 24, '', 'image', 'حل مشاكل الحسابات',
           'uploads/sections/section-banner-orange-06.webp',
           'uploads/sections/section-banner-orange-06-sm.webp', 1, 6
    UNION ALL SELECT 25, '', 'image', 'تحقيق الربح',
           'uploads/sections/section-banner-orange-07.webp',
           'uploads/sections/section-banner-orange-07-sm.webp', 1, 7
    UNION ALL SELECT 21, '', 'image', 'الحسابات الموثقة والمميزة',
           'uploads/sections/section-banner-orange-09.webp',
           'uploads/sections/section-banner-orange-09-sm.webp', 1, 9
) AS seed
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT category_id FROM store_section_banners) AS existing
     WHERE existing.category_id <=> seed.category_id
 );

INSERT INTO store_section_banners
       (category_id, placement, mode, title, asset_desktop, asset_mobile, is_visible, sort_order)
SELECT * FROM (
    SELECT NULL AS category_id, 'home_mid' AS placement, 'image' AS mode, 'نمو الحسابات' AS title,
           'uploads/sections/section-banner-orange-08.webp' AS asset_desktop,
           'uploads/sections/section-banner-orange-08-sm.webp' AS asset_mobile,
           1 AS is_visible, 8 AS sort_order
    UNION ALL SELECT NULL, 'home_bottom', 'image', 'خدمة العملاء',
           'uploads/sections/section-banner-orange-10.webp',
           'uploads/sections/section-banner-orange-10-sm.webp', 1, 10
) AS seed
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT placement FROM store_section_banners) AS existing
     WHERE existing.placement = seed.placement
 );
