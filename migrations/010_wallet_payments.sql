-- ============================================================================
-- EXD — wallet, ledger and payments
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- A balance is never edited in place. wallets.balance is a cached total that
-- the application recomputes from wallet_transactions, and every movement of
-- money writes one immutable transaction row carrying the balance that
-- followed it. A correction is a new row, never an update.
-- ============================================================================

CREATE TABLE IF NOT EXISTS wallets (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT            NOT NULL,
    balance      DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    held_balance DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    currency     VARCHAR(10)    NOT NULL DEFAULT 'EGP',
    is_frozen    TINYINT(1)     NOT NULL DEFAULT 0,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_wallets_user (user_id),
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id)
        REFERENCES platform_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    wallet_id       INT            NOT NULL,
    user_id         INT            NOT NULL,
    direction       ENUM('credit','debit') NOT NULL,
    amount          DECIMAL(14,2)  NOT NULL,
    balance_after   DECIMAL(14,2)  NOT NULL,
    currency        VARCHAR(10)    NOT NULL DEFAULT 'EGP',
    reason          VARCHAR(60)    NOT NULL,
    order_id        INT            NULL,
    reference       VARCHAR(190)   NULL,
    note            VARCHAR(500)   NULL,
    created_by_type ENUM('admin','user','system') NOT NULL DEFAULT 'system',
    created_by_id   INT            NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_wallet_transactions_wallet (wallet_id, created_at),
    KEY idx_wallet_transactions_user (user_id),
    KEY idx_wallet_transactions_order (order_id),
    CONSTRAINT fk_wallet_transactions_wallet FOREIGN KEY (wallet_id)
        REFERENCES wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment methods the store offers. Credentials are never stored here.
CREATE TABLE IF NOT EXISTS payment_methods (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    method_key     VARCHAR(60)   NOT NULL,
    name           VARCHAR(190)  NOT NULL,
    kind           ENUM('manual','gateway','wallet') NOT NULL DEFAULT 'manual',
    instructions   TEXT          NULL,
    account_label  VARCHAR(190)  NULL,
    logo           VARCHAR(500)  NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order     INT           NOT NULL DEFAULT 0,

    UNIQUE KEY uq_payment_methods_key (method_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT            NULL,
    user_id         INT            NULL,
    method_key      VARCHAR(60)    NOT NULL,
    amount          DECIMAL(14,2)  NOT NULL,
    currency        VARCHAR(10)    NOT NULL DEFAULT 'EGP',
    status          ENUM('pending','submitted','confirmed','rejected','refunded')
                      NOT NULL DEFAULT 'pending',
    reference       VARCHAR(190)   NULL,
    proof_image     VARCHAR(500)   NULL,
    payer_note      VARCHAR(500)   NULL,
    reviewed_by     INT            NULL,
    reviewed_at     DATETIME       NULL,
    review_note     VARCHAR(500)   NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_payments_order (order_id),
    KEY idx_payments_user (user_id),
    KEY idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payment_methods (method_key, name, kind, sort_order) VALUES
  ('wallet',      'رصيد المحفظة',      'wallet', 1),
  ('vodafone',    'فودافون كاش',       'manual', 2),
  ('instapay',    'إنستا باي',         'manual', 3),
  ('bank',        'تحويل بنكي',        'manual', 4),
  ('support',     'التنسيق مع الدعم',  'manual', 5);
