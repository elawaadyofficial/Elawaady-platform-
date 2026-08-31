-- ============================================================================
-- EXD — SMM / API providers
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- api_key_encrypted holds AES-256-GCM ciphertext produced with the key in the
-- APP_ENCRYPTION_KEY environment variable. No plaintext key is stored, and no
-- key of any form is committed to git or returned in a response.
-- ============================================================================

CREATE TABLE IF NOT EXISTS service_providers (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    name               VARCHAR(190)   NOT NULL,
    api_url            VARCHAR(500)   NOT NULL,
    api_key_encrypted  TEXT           NULL,
    api_format         VARCHAR(40)    NOT NULL DEFAULT 'smm_standard',
    currency           VARCHAR(10)    NOT NULL DEFAULT 'USD',
    exchange_rate      DECIMAL(12,4)  NOT NULL DEFAULT 1.0000,
    default_profit_percent DECIMAL(7,2) NOT NULL DEFAULT 30.00,
    is_active          TINYINT(1)     NOT NULL DEFAULT 1,
    last_balance       DECIMAL(14,4)  NULL,
    last_sync_at       DATETIME       NULL,
    created_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The provider's own catalogue, cached locally so pricing does not need a
-- network call on every page view.
CREATE TABLE IF NOT EXISTS provider_services (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    provider_id         INT            NOT NULL,
    remote_service_id   VARCHAR(100)   NOT NULL,
    name                VARCHAR(500)   NOT NULL,
    category            VARCHAR(255)   NULL,
    rate                DECIMAL(14,6)  NOT NULL DEFAULT 0,
    rate_per            INT            NOT NULL DEFAULT 1000,
    min_quantity        INT            NOT NULL DEFAULT 1,
    max_quantity        INT            NOT NULL DEFAULT 1000000,
    supports_refill     TINYINT(1)     NOT NULL DEFAULT 0,
    supports_cancel     TINYINT(1)     NOT NULL DEFAULT 0,
    raw_payload         TEXT           NULL,
    synced_at           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_provider_services (provider_id, remote_service_id),
    KEY idx_provider_services_provider (provider_id),
    CONSTRAINT fk_provider_services_provider FOREIGN KEY (provider_id)
        REFERENCES service_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_sync_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    provider_id  INT           NOT NULL,
    action       VARCHAR(60)   NOT NULL,
    status       ENUM('ok','error') NOT NULL DEFAULT 'ok',
    message      VARCHAR(500)  NULL,
    items_count  INT           NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_provider_sync_log_provider (provider_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bind a store service to a provider service and to a pricing rule.
ALTER TABLE store_services ADD COLUMN provider_id INT NULL;
ALTER TABLE store_services ADD COLUMN provider_service_id VARCHAR(100) NULL;
ALTER TABLE store_services ADD COLUMN provider_sync_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN provider_base_price DECIMAL(14,6) NULL;
ALTER TABLE store_services ADD COLUMN provider_price_per INT NOT NULL DEFAULT 1000;
ALTER TABLE store_services ADD COLUMN profit_percent DECIMAL(7,2) NOT NULL DEFAULT 30.00;
ALTER TABLE store_services ADD COLUMN price_mode VARCHAR(20) NOT NULL DEFAULT 'manual';
ALTER TABLE store_services ADD COLUMN last_provider_sync_at DATETIME NULL;
ALTER TABLE store_services ADD KEY idx_store_services_provider (provider_id);
