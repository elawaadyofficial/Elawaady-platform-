-- ============================================================================
-- EXD — put the right picture on the right service
-- ----------------------------------------------------------------------------
-- Additive and corrective only. No DROP, no TRUNCATE, no DELETE.
--
-- Migration 018 applied the master project's own seed, which mapped services
-- 1..20 onto carousel-01..20. That mapping was written when carousel-11..20
-- were different files. They are now square subscription artwork, so the seed
-- put Spotify on «برمجة بوت تيليجرام» and Disney+ on «حساب سناب شات جاهز».
--
-- These corrections are read off the artwork: service-card-49 has «ChatGPT
-- Plus Plan» printed on it, carousel-05 reads «الإعلانات الممولة», carousel-06
-- reads «حل مشاكل الحسابات».
--
-- Each UPDATE names the exact wrong file it replaces, so a service an
-- administrator has already re-illustrated is never touched.
-- ============================================================================

-- ── Exact matches: the product is named on its own card ─────────────────────
UPDATE store_services SET image = 'uploads/services/cards/service-card-49.webp',
                          main_image = 'uploads/services/cards/service-card-49.webp'
 WHERE id = 16 AND image = 'uploads/carousel/carousel-16.webp';   -- ChatGPT Plus

UPDATE store_services SET image = 'uploads/services/cards/service-card-43.webp',
                          main_image = 'uploads/services/cards/service-card-43.webp'
 WHERE id = 17 AND image = 'uploads/carousel/carousel-17.webp';   -- Midjourney Pro

UPDATE store_services SET image = 'uploads/services/cards/service-card-65.webp',
                          main_image = 'uploads/services/cards/service-card-65.webp'
 WHERE id = 18 AND image = 'uploads/carousel/carousel-18.webp';   -- Microsoft 365 Business Standard

UPDATE store_services SET image = 'uploads/services/cards/service-card-20.webp',
                          main_image = 'uploads/services/cards/service-card-20.webp'
 WHERE id = 19 AND image = 'uploads/carousel/carousel-19.webp';   -- Netflix

-- ── Themed banners, matched to what the banner says ─────────────────────────
UPDATE store_services SET image = 'uploads/carousel/carousel-08.webp',
                          main_image = 'uploads/carousel/carousel-08.webp'
 WHERE id = 1 AND image = 'uploads/carousel/carousel-01.webp';    -- نمو الحسابات

UPDATE store_services SET image = 'uploads/carousel/carousel-04.webp',
                          main_image = 'uploads/carousel/carousel-04.webp'
 WHERE id = 2 AND image = 'uploads/carousel/carousel-02.webp';    -- التوثيق والنشر الإعلامي

UPDATE store_services SET image = 'uploads/carousel/carousel-08.webp',
                          main_image = 'uploads/carousel/carousel-08.webp'
 WHERE id = 3 AND image = 'uploads/carousel/carousel-03.webp';    -- نمو الحسابات

UPDATE store_services SET image = 'uploads/carousel/carousel-08.webp',
                          main_image = 'uploads/carousel/carousel-08.webp'
 WHERE id = 4 AND image = 'uploads/carousel/carousel-04.webp';    -- نمو الحسابات

UPDATE store_services SET image = 'uploads/carousel/carousel-04.webp',
                          main_image = 'uploads/carousel/carousel-04.webp'
 WHERE id = 5 AND image = 'uploads/carousel/carousel-05.webp';    -- التوثيق والنشر الإعلامي

UPDATE store_services SET image = 'uploads/carousel/carousel-09.webp',
                          main_image = 'uploads/carousel/carousel-09.webp'
 WHERE id = 6 AND image = 'uploads/carousel/carousel-06.webp';    -- الحسابات الموثقة والمميزة

UPDATE store_services SET image = 'uploads/services/cards/service-card-10.webp',
                          main_image = 'uploads/services/cards/service-card-10.webp'
 WHERE id = 7 AND image = 'uploads/carousel/carousel-07.webp';    -- خدمات Youtube

UPDATE store_services SET image = 'uploads/carousel/carousel-04.webp',
                          main_image = 'uploads/carousel/carousel-04.webp'
 WHERE id = 8 AND image = 'uploads/carousel/carousel-08.webp';    -- التوثيق والنشر الإعلامي

UPDATE store_services SET image = 'uploads/carousel/carousel-05.webp',
                          main_image = 'uploads/carousel/carousel-05.webp'
 WHERE id = 9 AND image = 'uploads/carousel/carousel-09.webp';    -- الإعلانات الممولة

UPDATE store_services SET image = 'uploads/carousel/carousel-02.webp',
                          main_image = 'uploads/carousel/carousel-02.webp'
 WHERE id = 10 AND image = 'uploads/carousel/carousel-10.webp';   -- صناعة المحتوى

UPDATE store_services SET image = 'uploads/services/cards/service-card-07.webp',
                          main_image = 'uploads/services/cards/service-card-07.webp'
 WHERE id = 11 AND image = 'uploads/carousel/carousel-11.webp';   -- خدمات Telegram

UPDATE store_services SET image = 'uploads/services/cards/service-card-14.webp',
                          main_image = 'uploads/services/cards/service-card-14.webp'
 WHERE id = 12 AND image = 'uploads/carousel/carousel-12.webp';   -- خدمات السيرفرات

UPDATE store_services SET image = 'uploads/services/cards/service-card-11.webp',
                          main_image = 'uploads/services/cards/service-card-11.webp'
 WHERE id = 13 AND image = 'uploads/carousel/carousel-13.webp';   -- خدمات Snap chat

UPDATE store_services SET image = 'uploads/carousel/carousel-09.webp',
                          main_image = 'uploads/carousel/carousel-09.webp'
 WHERE id = 14 AND image = 'uploads/carousel/carousel-14.webp';   -- الحسابات الموثقة والمميزة

UPDATE store_services SET image = 'uploads/carousel/carousel-06.webp',
                          main_image = 'uploads/carousel/carousel-06.webp'
 WHERE id = 15 AND image = 'uploads/carousel/carousel-15.webp';   -- حل مشاكل الحسابات

UPDATE store_services SET image = 'uploads/carousel/carousel-10.webp',
                          main_image = 'uploads/carousel/carousel-10.webp'
 WHERE id = 20 AND image = 'uploads/carousel/carousel-20.webp';   -- خدمة العملاء
