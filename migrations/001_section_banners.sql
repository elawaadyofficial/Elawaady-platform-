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
-- Every column is nullable or defaulted, and a category with no row here
-- renders no banner at all, so creating the table changes nothing on the
-- storefront until artwork is uploaded and a row points at it.
--
-- Banner artwork is supplied as finished files and placed at its own size:
-- asset_desktop and asset_mobile are paths under assets/banners/, and no
-- aspect ratio is imposed anywhere.
-- ============================================================================

CREATE TABLE IF NOT EXISTS store_section_banners (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    -- NULL means a standalone banner: not tied to any category, placed by
    -- 'placement' instead. That is how a banner of any size gets added freely.
    category_id     INT           NULL,

    -- Where a standalone banner appears, e.g. home_top, home_mid, home_bottom.
    -- Ignored when category_id is set.
    placement       VARCHAR(40)   NOT NULL DEFAULT '',

    -- 'image' places the supplied artwork at its own proportions. 'composed'
    -- draws the gradient pill around the title instead. Empty picks image when
    -- artwork exists and renders nothing when it does not.
    mode            VARCHAR(20)   NOT NULL DEFAULT '',

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

    -- One banner per category, but any number of standalone banners: MySQL
    -- lets a UNIQUE key hold repeated NULLs, so both hold at once.
    UNIQUE KEY uniq_category (category_id),
    KEY idx_placement_order (placement, is_visible, sort_order),
    KEY idx_visible_order (is_visible, sort_order),
    CONSTRAINT fk_section_banner_category
        FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
