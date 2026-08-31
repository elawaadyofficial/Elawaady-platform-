-- ============================================================================
-- EXD — CMS, homepage composition, settings, notifications and reviews
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- homepage_sections is what makes the storefront dynamic: the homepage renders
-- the rows of this table in sort_order, and an inactive row renders nothing.
-- Adding a section is a row, not a code change.
--
-- Policies are versioned because acceptance is a legal record: policy_versions
-- keeps the exact text that was shown, and policy_acceptances records who
-- accepted which version, from which address, at what time.
-- ============================================================================

CREATE TABLE IF NOT EXISTS homepage_sections (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    section_key    VARCHAR(100)  NOT NULL,
    title          VARCHAR(190)  NOT NULL,
    subtitle       VARCHAR(500)  NULL,
    section_type   VARCHAR(60)   NOT NULL DEFAULT 'services',
    layout         VARCHAR(40)   NOT NULL DEFAULT 'rail',
    source_filter  VARCHAR(60)   NULL,
    category_id    INT           NULL,
    banner_image   VARCHAR(500)  NULL,
    banner_fit     VARCHAR(30)   NOT NULL DEFAULT 'original',
    item_limit     INT           NOT NULL DEFAULT 8,
    link_url       VARCHAR(500)  NULL,
    link_label     VARCHAR(120)  NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order     INT           NOT NULL DEFAULT 0,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_homepage_sections_key (section_key),
    KEY idx_homepage_sections_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Where a service appears. A service can hold several placements at once.
CREATE TABLE IF NOT EXISTS service_placements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    service_id     INT           NOT NULL,
    placement_key  VARCHAR(60)   NOT NULL,
    sort_order     INT           NOT NULL DEFAULT 0,
    starts_at      DATETIME      NULL,
    ends_at        DATETIME      NULL,

    UNIQUE KEY uq_service_placements (service_id, placement_key),
    KEY idx_service_placements_key (placement_key, sort_order),
    CONSTRAINT fk_service_placements_service FOREIGN KEY (service_id)
        REFERENCES store_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homepage_carousel (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    image       VARCHAR(500)  NOT NULL DEFAULT '',
    image_mobile VARCHAR(500) NULL,
    title_ar    VARCHAR(255)  NULL,
    title_en    VARCHAR(255)  NULL,
    link_type   ENUM('service','category','custom','none') NOT NULL DEFAULT 'none',
    link_id     INT           NULL,
    custom_url  VARCHAR(500)  NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_homepage_carousel_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS static_pages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(190)  NOT NULL,
    title           VARCHAR(255)  NOT NULL,
    content         LONGTEXT      NOT NULL,
    seo_title       VARCHAR(255)  NULL,
    seo_description TEXT          NULL,
    is_published    TINYINT(1)    NOT NULL DEFAULT 1,
    show_in_footer  TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order      INT           NOT NULL DEFAULT 0,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_static_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    policy_key   VARCHAR(100)  NOT NULL,
    title        VARCHAR(255)  NOT NULL,
    requires_acceptance TINYINT(1) NOT NULL DEFAULT 0,
    current_version_id  INT       NULL,

    UNIQUE KEY uq_policies_key (policy_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_versions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    policy_id   INT           NOT NULL,
    version     VARCHAR(40)   NOT NULL,
    content     LONGTEXT      NOT NULL,
    published_at DATETIME     NULL,
    created_by  INT           NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_policy_versions (policy_id, version),
    CONSTRAINT fk_policy_versions_policy FOREIGN KEY (policy_id)
        REFERENCES policies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_acceptances (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    policy_version_id INT           NOT NULL,
    user_id           INT           NULL,
    order_id          INT           NULL,
    mediation_id      INT           NULL,
    accepted_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address        VARCHAR(45)   NULL,
    user_agent        VARCHAR(500)  NULL,

    KEY idx_policy_acceptances_version (policy_version_id),
    KEY idx_policy_acceptances_user (user_id),
    CONSTRAINT fk_policy_acceptances_version FOREIGN KEY (policy_version_id)
        REFERENCES policy_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key    VARCHAR(120)  NOT NULL PRIMARY KEY,
    setting_value  TEXT          NULL,
    setting_group  VARCHAR(60)   NOT NULL DEFAULT 'general',
    value_type     VARCHAR(20)   NOT NULL DEFAULT 'text',
    label          VARCHAR(190)  NULL,
    updated_by     INT           NULL,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT           NULL,
    admin_id      INT           NULL,
    title         VARCHAR(255)  NOT NULL,
    body          VARCHAR(1000) NULL,
    kind          VARCHAR(60)   NOT NULL DEFAULT 'info',
    link_url      VARCHAR(500)  NULL,
    read_at       DATETIME      NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notifications_user (user_id, read_at),
    KEY idx_notifications_admin (admin_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    service_id   INT           NOT NULL,
    user_id      INT           NULL,
    order_id     INT           NULL,
    rating       TINYINT       NOT NULL DEFAULT 5,
    title        VARCHAR(190)  NULL,
    body         TEXT          NULL,
    author_name  VARCHAR(190)  NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by  INT           NULL,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_reviews_service (service_id, status),
    CONSTRAINT fk_reviews_service FOREIGN KEY (service_id)
        REFERENCES store_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    review_id   INT           NOT NULL,
    image       VARCHAR(500)  NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,

    KEY idx_review_images_review (review_id),
    CONSTRAINT fk_review_images_review FOREIGN KEY (review_id)
        REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_library (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    path          VARCHAR(500)  NOT NULL,
    original_name VARCHAR(255)  NULL,
    mime_type     VARCHAR(100)  NULL,
    width         INT           NULL,
    height        INT           NULL,
    file_size     INT           NULL,
    purpose       VARCHAR(60)   NULL,
    uploaded_by   INT           NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_media_library_path (path),
    KEY idx_media_library_purpose (purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatbot_knowledge (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    question    TEXT          NOT NULL,
    answer      TEXT          NOT NULL,
    category    VARCHAR(100)  NOT NULL DEFAULT 'general',
    keywords    TEXT          NULL,
    priority    INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── The homepage, as rows ───────────────────────────────────────────────────
-- This is the reference order. An administrator reorders, hides or retitles
-- any of these without a deployment.
INSERT IGNORE INTO homepage_sections
  (section_key, title, section_type, layout, source_filter, item_limit, sort_order, link_url, link_label) VALUES
  ('hero',           'الواجهة',                'hero',       'hero',    NULL,          1,  10, NULL, NULL),
  ('browse_cats',    'تصفح الأقسام',           'categories', 'keys',    NULL,         12,  20, 'categories.php', 'كل الأقسام'),
  ('banners_top',    'بنرات',                  'banners',    'rail',    'home_top',    8,  30, NULL, NULL),
  ('best_sellers',   'الأكثر مبيعًا',           'services',   'product', 'best_seller', 10, 40, NULL, NULL),
  ('most_used',      'الأكثر استخدامًا',        'services',   'rail',    'most_used',   12, 50, NULL, NULL),
  ('category_bands', 'أقسام الخدمات',          'category_bands','mixed', NULL,          0,  60, NULL, NULL),
  ('banners_mid',    'بنرات',                  'banners',    'rail',    'home_mid',    8,  70, NULL, NULL),
  ('offers',         'العروض',                 'services',   'product', 'offers',      10, 80, NULL, NULL),
  ('mediation',      'وساطة آمنة',             'mediation',  'feature', NULL,           1,  90, 'mediation.php', 'تفاصيل الوساطة'),
  ('banners_bottom', 'بنرات',                  'banners',    'rail',    'home_bottom', 8, 100, NULL, NULL),
  ('reviews',        'آراء العملاء',           'reviews',    'grid',    NULL,           6, 110, NULL, NULL),
  ('faq',            'الأسئلة الشائعة',        'faq',        'accordion', NULL,         8, 120, NULL, NULL),
  ('payment_trust',  'طرق الدفع والثقة',       'payment',    'strip',   NULL,           1, 130, NULL, NULL);

INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group, value_type, label) VALUES
  ('brand_name_ar',      'إعوضي إكس ديجيتال',   'brand',   'text', 'اسم المتجر بالعربية'),
  ('brand_name_en',      'Elawaady XDigital',   'brand',   'text', 'اسم المتجر بالإنجليزية'),
  ('licence_number',     '767-766-857',         'brand',   'text', 'رقم الترخيص'),
  ('support_whatsapp',   '',                    'support', 'text', 'واتساب الدعم'),
  ('support_telegram',   '',                    'support', 'text', 'تيليجرام الدعم'),
  ('support_email',      '',                    'support', 'text', 'بريد الدعم'),
  ('default_currency',   'EGP',                 'general', 'text', 'العملة الافتراضية'),
  ('mediation_enabled',  '1',                   'general', 'bool', 'تفعيل الوساطة'),
  ('mediation_default_safety_days', '7',        'general', 'int',  'أيام الأمان الافتراضية'),
  ('supplier_signup_open', '1',                 'general', 'bool', 'استقبال طلبات الموردين');

INSERT IGNORE INTO policies (policy_key, title, requires_acceptance) VALUES
  ('terms',            'شروط الاستخدام',        1),
  ('privacy',          'سياسة الخصوصية',        1),
  ('mediation_terms',  'شروط الوساطة',          1),
  ('supplier_terms',   'شروط المورد',           1),
  ('refund',           'سياسة الاسترجاع',       0),
  ('payment_policy',   'سياسة الدفع',           0),
  ('delivery_policy',  'سياسة التسليم',         0);
