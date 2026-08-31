-- ============================================================================
-- EXD — orders, order items and the order status timeline
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- user_id is nullable: an order placed before signing in is still a real
-- order, and is claimed by the account later. supplier_id records who fulfils
-- it and is never selected by a customer-facing query.
-- ============================================================================

CREATE TABLE IF NOT EXISTS orders (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    order_code         VARCHAR(30)    NOT NULL,
    user_id            INT            NULL,

    service_id         INT            NULL,
    service_name       VARCHAR(255)   NOT NULL DEFAULT '',

    customer_name      VARCHAR(200)   NULL,
    customer_phone     VARCHAR(50)    NULL,
    customer_email     VARCHAR(200)   NULL,

    quantity           INT            NOT NULL DEFAULT 1,
    unit_price         DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    options_total      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    mediation_fee      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total_price        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    currency           VARCHAR(10)    NOT NULL DEFAULT 'EGP',

    order_type         VARCHAR(30)    NOT NULL DEFAULT 'direct_buy',
    order_source       ENUM('store','supplier','provider') NOT NULL DEFAULT 'store',

    payment_status     ENUM('pending','awaiting_confirmation','paid','partially_paid','failed','refunded')
                         NOT NULL DEFAULT 'pending',
    order_status       VARCHAR(40)    NOT NULL DEFAULT 'new',

    -- Fulfilment target supplied by the buyer.
    target_url         VARCHAR(1000)  NULL,
    target_type        VARCHAR(60)    NULL,
    quality_option     VARCHAR(190)   NULL,
    warranty_option    VARCHAR(190)   NULL,

    customer_notes     TEXT           NULL,
    admin_notes        TEXT           NULL,
    whatsapp_message   TEXT           NULL,

    -- Mediation. mediation_id is filled once a mediation case opens.
    mediation_enabled  TINYINT(1)     NOT NULL DEFAULT 0,
    mediation_id       INT            NULL,

    -- Supplier fulfilment. Confidential.
    supplier_id        INT            NULL,
    supplier_status    VARCHAR(100)   NULL,
    supplier_cost      DECIMAL(12,2)  NULL,
    delivery_proof     VARCHAR(500)   NULL,
    safety_period_ends_at DATETIME    NULL,

    -- Payment confirmation, recorded by staff for manual methods.
    payment_confirmed_by     INT           NULL,
    payment_confirmed_at     DATETIME      NULL,
    payment_confirmed_amount DECIMAL(12,2) NULL,
    payment_method_recorded  VARCHAR(100)  NULL,
    payment_reference        VARCHAR(190)  NULL,
    payment_notes            TEXT          NULL,

    -- External SMM provider progress.
    provider_id        INT            NULL,
    provider_order_id  VARCHAR(190)   NULL,
    provider_status    VARCHAR(100)   NULL,
    start_count        BIGINT         NULL,
    current_count      BIGINT         NULL,
    completed_quantity INT            NOT NULL DEFAULT 0,
    remaining_quantity INT            NOT NULL DEFAULT 0,
    progress_percent   DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
    last_provider_sync_at DATETIME    NULL,

    created_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_orders_code (order_code),
    KEY idx_orders_user (user_id),
    KEY idx_orders_service (service_id),
    KEY idx_orders_status (order_status),
    KEY idx_orders_supplier (supplier_id),
    KEY idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The chosen option values for an order, one row each, priced at order time.
CREATE TABLE IF NOT EXISTS order_options (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT           NOT NULL,
    option_label  VARCHAR(190)  NOT NULL,
    value_label   VARCHAR(190)  NOT NULL,
    price_delta   DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    KEY idx_order_options_order (order_id),
    CONSTRAINT fk_order_options_order FOREIGN KEY (order_id)
        REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every status change, who made it and whether the customer may see it.
CREATE TABLE IF NOT EXISTS order_status_history (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT           NOT NULL,
    from_status    VARCHAR(40)   NULL,
    to_status      VARCHAR(40)   NOT NULL,
    actor_type     ENUM('admin','user','supplier','system') NOT NULL DEFAULT 'system',
    actor_id       INT           NULL,
    note           VARCHAR(500)  NULL,
    customer_visible TINYINT(1)  NOT NULL DEFAULT 1,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_order_status_history_order (order_id, created_at),
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id)
        REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The cart. Rows survive a session so a signed-in buyer keeps their basket.
CREATE TABLE IF NOT EXISTS carts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NULL,
    session_key CHAR(64)      NULL,
    status      ENUM('open','converted','abandoned') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_carts_user (user_id),
    KEY idx_carts_session (session_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cart_id      INT            NOT NULL,
    service_id   INT            NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,
    unit_price   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    options_json TEXT           NULL,
    target_url   VARCHAR(1000)  NULL,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_cart_items_cart (cart_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id)
        REFERENCES carts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
