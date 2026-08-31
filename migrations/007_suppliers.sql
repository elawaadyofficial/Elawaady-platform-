-- ============================================================================
-- EXD — supplier profiles, offers and settlements
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- A supplier is a platform_users row with account_type='supplier'. This file
-- adds the commercial detail that hangs off that account.
--
-- Supplier identity is confidential. Nothing in this schema may be joined into
-- a customer-facing query: the storefront and the customer's order view never
-- select supplier_id, supplier name, phone or any column below.
-- ============================================================================

CREATE TABLE IF NOT EXISTS supplier_profiles (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    user_id               INT           NOT NULL,

    company_name          VARCHAR(190)  NULL,
    bio                   TEXT          NULL,
    services_desc         TEXT          NULL,
    website               VARCHAR(500)  NULL,
    telegram              VARCHAR(120)  NULL,

    -- Settlement details. Read by finance staff only.
    payout_method         VARCHAR(60)   NULL,
    payout_details        VARCHAR(500)  NULL,

    default_commission    DECIMAL(7,2)  NOT NULL DEFAULT 0.00,
    rating                DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    completed_orders      INT           NOT NULL DEFAULT 0,
    cancelled_orders      INT           NOT NULL DEFAULT 0,

    -- Per-supplier ceiling on what the supplier may see of an order.
    can_view_customer_data TINYINT(1)   NOT NULL DEFAULT 0,
    can_update_status      TINYINT(1)   NOT NULL DEFAULT 1,

    verified_at           DATETIME      NULL,
    notes_internal        TEXT          NULL,
    created_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_supplier_profiles_user (user_id),
    CONSTRAINT fk_supplier_profiles_user FOREIGN KEY (user_id)
        REFERENCES platform_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What a supplier proposes. Nothing here is visible on the storefront until an
-- administrator approves it and it is published as a store_services row.
CREATE TABLE IF NOT EXISTS supplier_offers (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id           INT           NOT NULL,
    category_id           INT           NULL,
    subcategory_id        INT           NULL,

    title                 VARCHAR(255)  NOT NULL,
    description           TEXT          NULL,
    requirements          TEXT          NULL,
    execution_time        VARCHAR(100)  NULL,

    supplier_price        DECIMAL(12,2) NULL,
    suggested_sell_price  DECIMAL(12,2) NULL,
    currency              VARCHAR(10)   NOT NULL DEFAULT 'EGP',
    availability          VARCHAR(100)  NOT NULL DEFAULT 'available',

    image                 VARCHAR(500)  NULL,
    gallery               TEXT          NULL,

    review_status         ENUM('draft','pending_review','approved','rejected','withdrawn')
                            NOT NULL DEFAULT 'pending_review',
    admin_notes           TEXT          NULL,
    reviewed_by           INT           NULL,
    reviewed_at           DATETIME      NULL,
    published_service_id  INT           NULL,

    created_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_supplier_offers_supplier (supplier_id),
    KEY idx_supplier_offers_status (review_status),
    CONSTRAINT fk_supplier_offers_supplier FOREIGN KEY (supplier_id)
        REFERENCES platform_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money owed to a supplier for delivered work. Append-only in practice: a
-- correction is a new row, never an edit.
CREATE TABLE IF NOT EXISTS supplier_settlements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id    INT            NOT NULL,
    order_id       INT            NULL,
    amount         DECIMAL(14,2)  NOT NULL,
    currency       VARCHAR(10)    NOT NULL DEFAULT 'EGP',
    direction      ENUM('credit','debit') NOT NULL DEFAULT 'credit',
    status         ENUM('held','payable','paid','cancelled') NOT NULL DEFAULT 'held',
    hold_until     DATETIME       NULL,
    paid_at        DATETIME       NULL,
    reference      VARCHAR(190)   NULL,
    note           VARCHAR(500)   NULL,
    created_by     INT            NULL,
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_supplier_settlements_supplier (supplier_id, status),
    KEY idx_supplier_settlements_order (order_id),
    CONSTRAINT fk_supplier_settlements_supplier FOREIGN KEY (supplier_id)
        REFERENCES platform_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
