-- ============================================================================
-- EXD — attach the store's own artwork to the catalogue
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- Every mapping below is the owner's own, taken from the EXD master project's
-- seed: service id 1 is 'متابعين فيسبوك' and carries carousel-01, and so on
-- down the twenty seeded services. Nothing here is a guess about which picture
-- belongs to which service.
--
-- The files are the same artwork converted to WebP at the sizes the storefront
-- renders — 173 MB of PNG became 15 MB with the same pixels on screen and
-- transparency intact.
--
-- Each UPDATE is conditional on the image being empty, so a service that has
-- already been given artwork in the dashboard keeps it.
-- ============================================================================

UPDATE store_services SET image = 'uploads/carousel/carousel-01.webp', main_image = 'uploads/carousel/carousel-01.webp' WHERE id = 1  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-02.webp', main_image = 'uploads/carousel/carousel-02.webp' WHERE id = 2  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-03.webp', main_image = 'uploads/carousel/carousel-03.webp' WHERE id = 3  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-04.webp', main_image = 'uploads/carousel/carousel-04.webp' WHERE id = 4  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-05.webp', main_image = 'uploads/carousel/carousel-05.webp' WHERE id = 5  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-06.webp', main_image = 'uploads/carousel/carousel-06.webp' WHERE id = 6  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-07.webp', main_image = 'uploads/carousel/carousel-07.webp' WHERE id = 7  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-08.webp', main_image = 'uploads/carousel/carousel-08.webp' WHERE id = 8  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-09.webp', main_image = 'uploads/carousel/carousel-09.webp' WHERE id = 9  AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-10.webp', main_image = 'uploads/carousel/carousel-10.webp' WHERE id = 10 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-11.webp', main_image = 'uploads/carousel/carousel-11.webp' WHERE id = 11 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-12.webp', main_image = 'uploads/carousel/carousel-12.webp' WHERE id = 12 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-13.webp', main_image = 'uploads/carousel/carousel-13.webp' WHERE id = 13 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-14.webp', main_image = 'uploads/carousel/carousel-14.webp' WHERE id = 14 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-15.webp', main_image = 'uploads/carousel/carousel-15.webp' WHERE id = 15 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-16.webp', main_image = 'uploads/carousel/carousel-16.webp' WHERE id = 16 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-17.webp', main_image = 'uploads/carousel/carousel-17.webp' WHERE id = 17 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-18.webp', main_image = 'uploads/carousel/carousel-18.webp' WHERE id = 18 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-19.webp', main_image = 'uploads/carousel/carousel-19.webp' WHERE id = 19 AND (image IS NULL OR image = '');
UPDATE store_services SET image = 'uploads/carousel/carousel-20.webp', main_image = 'uploads/carousel/carousel-20.webp' WHERE id = 20 AND (image IS NULL OR image = '');

-- ── The homepage carousel ───────────────────────────────────────────────────
-- Each slide points at the service it depicts, so the hero is a way into the
-- catalogue rather than decoration.
INSERT IGNORE INTO homepage_carousel (image, title_ar, link_type, link_id, sort_order, is_active) VALUES
  ('uploads/carousel/carousel-01.webp', 'متابعين فيسبوك',                'service',  1,  1, 1),
  ('uploads/carousel/carousel-02.webp', 'توثيق صفحات فيسبوك',            'service',  2,  2, 1),
  ('uploads/carousel/carousel-03.webp', 'لايكات إنستجرام',                'service',  3,  3, 1),
  ('uploads/carousel/carousel-04.webp', 'مشاهدات تيك توك',               'service',  4,  4, 1),
  ('uploads/carousel/carousel-05.webp', 'توثيق سناب شات',                'service',  5,  5, 1),
  ('uploads/carousel/carousel-07.webp', 'ساعات مشاهدة يوتيوب',            'service',  7,  6, 1),
  ('uploads/carousel/carousel-09.webp', 'إعلانات ممولة Meta',             'service',  9,  7, 1),
  ('uploads/carousel/carousel-16.webp', 'ChatGPT Plus',                  'service', 16,  8, 1);

-- ── Where services appear ───────────────────────────────────────────────────
-- A starting arrangement so a fresh install has a populated homepage. It is
-- editable in the dashboard under «أماكن العرض», which is the point: these are
-- not hard-coded choices, they are rows.
INSERT IGNORE INTO service_placements (service_id, placement_key, sort_order) VALUES
  (1,  'best_seller', 10), (3,  'best_seller', 20), (4,  'best_seller', 30),
  (7,  'best_seller', 40), (16, 'best_seller', 50), (9,  'best_seller', 60),
  (2,  'most_used',   10), (5,  'most_used',   20), (8,  'most_used',   30),
  (11, 'most_used',   40), (12, 'most_used',   50), (10, 'most_used',   60),
  (16, 'featured',    10), (17, 'featured',    20), (18, 'featured',    30),
  (19, 'featured',    40), (6,  'featured',    50), (13, 'featured',    60);

-- ── Brand assets ────────────────────────────────────────────────────────────
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group, label) VALUES
  ('logo_main',   'uploads/brand/main-logo.webp',   'brand', 'الشعار الأساسي'),
  ('logo_header', 'uploads/brand/header-logo.webp', 'brand', 'شعار الهيدر'),
  ('logo_footer', 'uploads/brand/footer-logo.webp', 'brand', 'شعار الفوتر'),
  ('logo_admin',  'uploads/brand/admin-logo.webp',  'brand', 'شعار لوحة التحكم'),
  ('favicon',     'uploads/brand/favicon.webp',     'brand', 'أيقونة المتصفح');
