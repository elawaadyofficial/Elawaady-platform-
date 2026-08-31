-- ============================================================================
-- EXD — mediation cases and the digital-asset marketplace
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- Mediation holds a buyer's money until delivery is confirmed. The held amount
-- lives in wallet_transactions like any other movement; this table records the
-- case, its parties and its timeline.
-- ============================================================================

CREATE TABLE IF NOT EXISTS mediations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    case_code         VARCHAR(30)    NOT NULL,
    order_id          INT            NULL,
    service_id        INT            NULL,

    subject           VARCHAR(255)   NOT NULL,
    description       TEXT           NULL,
    deal_amount       DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    fee_amount        DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    currency          VARCHAR(10)    NOT NULL DEFAULT 'EGP',

    status            ENUM('opened','terms_accepted','funds_held','in_delivery',
                           'delivered','safety_period','released','refunded',
                           'disputed','cancelled')
                        NOT NULL DEFAULT 'opened',

    mediator_admin_id INT            NULL,
    safety_days       INT            NOT NULL DEFAULT 0,
    safety_ends_at    DATETIME       NULL,

    terms_version_id  INT            NULL,
    opened_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at         DATETIME       NULL,
    updated_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_mediations_code (case_code),
    KEY idx_mediations_status (status),
    KEY idx_mediations_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mediation_parties (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    mediation_id  INT           NOT NULL,
    user_id       INT           NULL,
    party_role    ENUM('buyer','seller') NOT NULL,
    display_name  VARCHAR(190)  NULL,
    contact       VARCHAR(190)  NULL,
    accepted_at   DATETIME      NULL,
    accepted_ip   VARCHAR(45)   NULL,

    KEY idx_mediation_parties_case (mediation_id),
    CONSTRAINT fk_mediation_parties_case FOREIGN KEY (mediation_id)
        REFERENCES mediations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mediation_status_history (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    mediation_id  INT           NOT NULL,
    from_status   VARCHAR(40)   NULL,
    to_status     VARCHAR(40)   NOT NULL,
    actor_type    ENUM('admin','user','system') NOT NULL DEFAULT 'system',
    actor_id      INT           NULL,
    note          VARCHAR(500)  NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_mediation_history_case (mediation_id, created_at),
    CONSTRAINT fk_mediation_history_case FOREIGN KEY (mediation_id)
        REFERENCES mediations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Digital assets ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS digital_assets (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id               INT            NULL,
    platform                  VARCHAR(80)    NOT NULL,
    asset_type                VARCHAR(80)    NOT NULL,
    title                     VARCHAR(255)   NOT NULL,
    description               TEXT           NULL,

    followers_count           BIGINT         NOT NULL DEFAULT 0,
    monetization_status       VARCHAR(120)   NULL,
    audience_country          VARCHAR(120)   NULL,
    creation_year             INT            NULL,

    price                     DECIMAL(14,2)  NOT NULL,
    currency                  VARCHAR(10)    NOT NULL DEFAULT 'EGP',

    lifetime_guarantee        TINYINT(1)     NOT NULL DEFAULT 1,
    ownership_by_elawaady_only TINYINT(1)    NOT NULL DEFAULT 1,
    safety_days               INT            NOT NULL DEFAULT 7,

    cover_image               VARCHAR(500)   NULL,
    review_status             ENUM('pending_review','listed','reserved','sold','rejected')
                                NOT NULL DEFAULT 'pending_review',
    admin_notes               TEXT           NULL,
    reviewed_by               INT            NULL,
    reviewed_at               DATETIME       NULL,

    created_at                TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_digital_assets_status (review_status),
    KEY idx_digital_assets_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_asset_stats (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    asset_id    INT           NOT NULL,
    metric_key  VARCHAR(80)   NOT NULL,
    metric_label VARCHAR(190) NOT NULL,
    metric_value VARCHAR(190) NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,

    KEY idx_digital_asset_stats_asset (asset_id, sort_order),
    CONSTRAINT fk_digital_asset_stats_asset FOREIGN KEY (asset_id)
        REFERENCES digital_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ownership transfer is the part that can go wrong, so every step is recorded.
CREATE TABLE IF NOT EXISTS asset_transfers (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    asset_id         INT           NOT NULL,
    order_id         INT           NULL,
    buyer_user_id    INT           NULL,
    status           ENUM('initiated','credentials_shared','buyer_verified',
                          'safety_period','completed','reverted','disputed')
                       NOT NULL DEFAULT 'initiated',
    safety_days      INT           NOT NULL DEFAULT 7,
    safety_ends_at   DATETIME      NULL,
    handled_by       INT           NULL,
    note             VARCHAR(500)  NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_asset_transfers_asset (asset_id),
    CONSTRAINT fk_asset_transfers_asset FOREIGN KEY (asset_id)
        REFERENCES digital_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
