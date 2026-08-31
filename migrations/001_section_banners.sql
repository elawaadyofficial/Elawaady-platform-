-- ============================================================================
-- EXD — section banner settings
-- ----------------------------------------------------------------------------
-- Additive only. Creates one new table and touches nothing that exists.
-- There is no DROP, no TRUNCATE, no DELETE and no ALTER of an existing table
-- anywhere in this file, so it is safe to run against a database holding real
-- data. It is deliberately NOT part of database.sql, which still begins with
-- DROP statements and must never be run on anything but an empty database.
--
-- Run it manually, on staging first:
--   mysql -u USER -p DBNAME < migrations/001_section_banners.sql
--
-- Every column is nullable or defaulted. A category with no row here still
-- renders its banner from the theme palette, so creating the table changes
-- nothing until the admin panel writes to it.
-- ============================================================================

CREATE TABLE IF NOT EXISTS store_section_banners (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT NOT NULL,

    -- NULL means: use the category's own name, so renaming a category renames
    -- its banner with no extra step.
    title           VARCHAR(255)  NULL,

    -- Palette key: brand, social, ai, subscriptions, streaming, verification,
    -- gaming, giftcards, music, media. NULL lets the component pick from the
    -- category name.
    theme           VARCHAR(40)   NULL,

    -- Explicit colour overrides. Any of these beats the palette.
    gradient        VARCHAR(400)  NULL,
    accent          VARCHAR(40)   NULL,
    glow            VARCHAR(60)   NULL,
    text_color      VARCHAR(40)   NULL,

    -- Type.
    font_family     VARCHAR(160)  NULL,
    font_size       VARCHAR(40)   NULL,

    -- 3D asset slots. Paths relative to the site root.
    asset_desktop   VARCHAR(255)  NULL,
    asset_mobile    VARCHAR(255)  NULL,
    asset_scale     VARCHAR(20)   NULL,
    asset_position  VARCHAR(10)   NOT NULL DEFAULT 'end',

    -- Geometry overrides. Leaving these NULL keeps the responsive defaults.
    banner_height   VARCHAR(30)   NULL,
    border_radius   VARCHAR(30)   NULL,

    link            VARCHAR(255)  NULL,
    is_visible      TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order      INT           NOT NULL DEFAULT 0,

    created_at      TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_category (category_id),
    KEY idx_visible_order (is_visible, sort_order),
    CONSTRAINT fk_section_banner_category
        FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
