-- ============================================================================
-- EXD — the rest of the service record
-- ----------------------------------------------------------------------------
-- Additive only. Every column is nullable or defaulted, so existing rows stay
-- valid and the storefront renders them unchanged.
--
-- These are the fields the dashboard's service editor writes: identity and
-- SEO, the long-form content blocks, merchandising flags, the commission
-- figures, the per-service ordering channel, and the per-service colour
-- overrides. They are one-to-one with a service and are always read together
-- with it, so they belong on the row rather than behind a join.
--
-- The colour columns are per-service overrides applied as inline custom
-- properties. Left empty — which is the normal state — a service inherits the
-- store's own theme, and the design system stays in charge.
-- ============================================================================

-- ── Identity and SEO ────────────────────────────────────────────────────────
ALTER TABLE store_services ADD COLUMN name_en VARCHAR(255) NULL;
ALTER TABLE store_services ADD COLUMN slug VARCHAR(255) NULL;
ALTER TABLE store_services ADD COLUMN service_code VARCHAR(100) NULL;
ALTER TABLE store_services ADD COLUMN seo_title VARCHAR(255) NULL;
ALTER TABLE store_services ADD COLUMN seo_description TEXT NULL;
ALTER TABLE store_services ADD COLUMN seo_keywords TEXT NULL;
ALTER TABLE store_services ADD COLUMN noindex TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN show_sitemap TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services ADD KEY idx_store_services_slug (slug);

-- ── Long-form content ───────────────────────────────────────────────────────
ALTER TABLE store_services ADD COLUMN description_full TEXT NULL;
ALTER TABLE store_services ADD COLUMN features TEXT NULL;
ALTER TABLE store_services ADD COLUMN requirements TEXT NULL;
ALTER TABLE store_services ADD COLUMN execution_time VARCHAR(255) NULL;
ALTER TABLE store_services ADD COLUMN terms TEXT NULL;
ALTER TABLE store_services ADD COLUMN refund_policy TEXT NULL;
ALTER TABLE store_services ADD COLUMN important_note TEXT NULL;
ALTER TABLE store_services ADD COLUMN admin_notes TEXT NULL;
ALTER TABLE store_services ADD COLUMN gallery_images TEXT NULL;

-- ── Merchandising ───────────────────────────────────────────────────────────
ALTER TABLE store_services ADD COLUMN service_type VARCHAR(50) NOT NULL DEFAULT 'internal';
ALTER TABLE store_services ADD COLUMN badge VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN show_home TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN show_offers TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN show_slider TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN service_tags VARCHAR(500) NULL;
ALTER TABLE store_services ADD COLUMN buy_now_enabled TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services ADD COLUMN cart_enabled TINYINT(1) NOT NULL DEFAULT 1;

-- ── Pricing and commission. Cost and commission are staff-facing only. ──────
ALTER TABLE store_services ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'EGP';
ALTER TABLE store_services ADD COLUMN show_price TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services ADD COLUMN ask_for_price TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN supplier_cost DECIMAL(12,2) NULL;
ALTER TABLE store_services ADD COLUMN platform_commission DECIMAL(12,2) NULL;
ALTER TABLE store_services ADD COLUMN marketer_commission DECIMAL(12,2) NULL;
ALTER TABLE store_services ADD COLUMN supplier_priority INT NOT NULL DEFAULT 0;

-- ── How an order is placed and where it goes ────────────────────────────────
ALTER TABLE store_services ADD COLUMN order_type VARCHAR(50) NOT NULL DEFAULT 'direct_buy';
ALTER TABLE store_services ADD COLUMN order_link VARCHAR(500) NULL;
ALTER TABLE store_services ADD COLUMN whatsapp_number VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN telegram_bot VARCHAR(255) NULL;
ALTER TABLE store_services ADD COLUMN requires_approval TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN requires_advance_payment TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN mediator_phone VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN mediation_whatsapp_group VARCHAR(190) NULL;
ALTER TABLE store_services ADD COLUMN emergency_phone VARCHAR(50) NULL;

-- ── Per-service option lists, stored as the editor writes them ──────────────
ALTER TABLE store_services ADD COLUMN target_types TEXT NULL;
ALTER TABLE store_services ADD COLUMN quality_options TEXT NULL;
ALTER TABLE store_services ADD COLUMN warranty_options TEXT NULL;

-- ── Per-service colour overrides. Empty means "use the store's theme". ──────
ALTER TABLE store_services ADD COLUMN card_bg_color VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN page_bg_color VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN primary_color VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN secondary_color VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN button_color VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN text_color_custom VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN border_color VARCHAR(50) NULL;
ALTER TABLE store_services ADD COLUMN card_gradient TEXT NULL;
ALTER TABLE store_services ADD COLUMN button_gradient TEXT NULL;

-- ── Category and subcategory identity, to match ─────────────────────────────
ALTER TABLE store_categories ADD COLUMN name_en VARCHAR(255) NULL;
ALTER TABLE store_categories ADD COLUMN slug VARCHAR(255) NULL;
ALTER TABLE store_categories ADD COLUMN icon_image VARCHAR(500) NULL;

ALTER TABLE store_subcategories ADD COLUMN name_en VARCHAR(255) NULL;
ALTER TABLE store_subcategories ADD COLUMN slug VARCHAR(255) NULL;
ALTER TABLE store_subcategories ADD COLUMN icon_image VARCHAR(500) NULL;
ALTER TABLE store_subcategories ADD COLUMN banner_image VARCHAR(500) NULL;
