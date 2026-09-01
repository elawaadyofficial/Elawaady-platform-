-- ============================================================================
-- EXD — artwork for the categories whose pictures name themselves
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- Not a guess. Each file below has the platform's name printed on the image:
-- service-card-01 reads «خدمات Instagram», 05 reads «خدمات Whatsapp», 12 reads
-- «خدمات Tik Tok». Matching those to the category of the same name is reading
-- the artwork, not inventing a mapping.
--
-- Categories whose artwork does not exist in the library are left alone. They
-- keep their emoji, which is the correct state for a category with no picture,
-- and the dashboard can assign one later.
--
-- Every UPDATE is conditional on the column being empty, so a category already
-- given artwork in the dashboard keeps it.
-- ============================================================================

SET @exd_has_cat_image := (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'store_categories' AND column_name = 'image'
);
SET @exd_sql := IF(@exd_has_cat_image = 0,
    'ALTER TABLE store_categories ADD COLUMN image VARCHAR(500) NULL', 'DO 0');
PREPARE exd_stmt FROM @exd_sql;
EXECUTE exd_stmt;
DEALLOCATE PREPARE exd_stmt;

-- ── The platforms, each read off its own card ───────────────────────────────
UPDATE store_categories SET image = 'uploads/services/cards/service-card-01.webp' WHERE id = 2  AND (image IS NULL OR image = '');  -- خدمات Instagram
UPDATE store_categories SET image = 'uploads/services/cards/service-card-12.webp' WHERE id = 3  AND (image IS NULL OR image = '');  -- خدمات Tik Tok
UPDATE store_categories SET image = 'uploads/services/cards/service-card-11.webp' WHERE id = 4  AND (image IS NULL OR image = '');  -- خدمات Snap chat
UPDATE store_categories SET image = 'uploads/services/cards/service-card-10.webp' WHERE id = 5  AND (image IS NULL OR image = '');  -- خدمات Youtube
UPDATE store_categories SET image = 'uploads/services/cards/service-card-09.webp' WHERE id = 6  AND (image IS NULL OR image = '');  -- خدمات X Twitter
UPDATE store_categories SET image = 'uploads/services/cards/service-card-07.webp' WHERE id = 7  AND (image IS NULL OR image = '');  -- خدمات Telegram
UPDATE store_categories SET image = 'uploads/services/cards/service-card-05.webp' WHERE id = 8  AND (image IS NULL OR image = '');  -- خدمات Whatsapp
UPDATE store_categories SET image = 'uploads/services/cards/service-card-02.webp' WHERE id = 9  AND (image IS NULL OR image = '');  -- خدمات Discord
UPDATE store_categories SET image = 'uploads/services/cards/service-card-06.webp' WHERE id = 10 AND (image IS NULL OR image = '');  -- خدمات LinkedIn
UPDATE store_categories SET image = 'uploads/services/cards/service-card-03.webp' WHERE id = 20 AND (image IS NULL OR image = '');  -- خدمات Messenger, وهي منصة ميتا
UPDATE store_categories SET image = 'uploads/services/cards/service-card-04.webp' WHERE id = 19 AND (image IS NULL OR image = '');  -- خدمات Google

-- ── The themed cards ────────────────────────────────────────────────────────
UPDATE store_categories SET image = 'uploads/services/cards/service-card-08.webp' WHERE id = 13 AND (image IS NULL OR image = '');  -- Google Ads
UPDATE store_categories SET image = 'uploads/services/cards/service-card-14.webp' WHERE id = 18 AND (image IS NULL OR image = '');  -- خدمات السيرفرات
UPDATE store_categories SET image = 'uploads/services/cards/service-card-13.webp' WHERE id = 31 AND (image IS NULL OR image = '');  -- عرض الملوك — الخدمات الخاصة
UPDATE store_categories SET image = 'uploads/services/cards/service-card-20.webp' WHERE id = 35 AND (image IS NULL OR image = '');  -- Netflix — الاشتراكات الرقمية
UPDATE store_categories SET image = 'uploads/services/cards/service-card-49.webp' WHERE id = 33 AND (image IS NULL OR image = '');  -- ChatGPT Plus — الذكاء الاصطناعي
UPDATE store_categories SET image = 'uploads/services/cards/service-card-68.webp' WHERE id = 34 AND (image IS NULL OR image = '');  -- Microsoft 365

-- ── The wide banners, on the categories they describe ───────────────────────
-- carousel-01..10 are 3:1 banners with their subject written across them.
UPDATE store_categories SET image = 'uploads/carousel/carousel-03.webp' WHERE id = 1  AND (image IS NULL OR image = '');  -- حسابات ومنصات السوشيال ميديا
UPDATE store_categories SET image = 'uploads/carousel/carousel-04.webp' WHERE id = 11 AND (image IS NULL OR image = '');  -- التوثيق والنشر الإعلامي
UPDATE store_categories SET image = 'uploads/carousel/carousel-01.webp' WHERE id = 12 AND (image IS NULL OR image = '');  -- إدارة وتشغيل السوشيال ميديا
UPDATE store_categories SET image = 'uploads/carousel/carousel-02.webp' WHERE id = 15 AND (image IS NULL OR image = '');  -- صناعة المحتوى
UPDATE store_categories SET image = 'uploads/carousel/carousel-06.webp' WHERE id = 24 AND (image IS NULL OR image = '');  -- حل مشاكل الحسابات
UPDATE store_categories SET image = 'uploads/carousel/carousel-07.webp' WHERE id = 25 AND (image IS NULL OR image = '');  -- تحقيق الربح
UPDATE store_categories SET image = 'uploads/carousel/carousel-09.webp' WHERE id = 21 AND (image IS NULL OR image = '');  -- الحسابات الموثقة والمميزة
UPDATE store_categories SET image = 'uploads/carousel/carousel-08.webp' WHERE id = 22 AND (image IS NULL OR image = '');  -- نمو الحسابات
UPDATE store_categories SET image = 'uploads/carousel/carousel-10.webp' WHERE id = 37 AND (image IS NULL OR image = '');  -- خدمة العملاء — الموردون والشركاء
