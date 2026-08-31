-- ============================================================================
-- EXD — platform accounts, sessions and password recovery
-- ----------------------------------------------------------------------------
-- Additive only. Creates new tables and touches nothing that already exists.
-- No DROP, no TRUNCATE, no DELETE, no ALTER of an existing table.
--
-- Account types are exactly two: user and supplier. There is no trader,
-- merchant or vendor type anywhere in this schema, and none may be added.
-- Staff identity lives in admin_users (migration 006), not here.
--
-- Run on staging first:
--   php migrate.php
-- ============================================================================

CREATE TABLE IF NOT EXISTS platform_users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    account_type        ENUM('user','supplier') NOT NULL DEFAULT 'user',

    name                VARCHAR(190)  NOT NULL,
    email               VARCHAR(190)  NOT NULL,
    phone               VARCHAR(40)   NOT NULL,
    whatsapp            VARCHAR(40)   NULL,
    country             VARCHAR(100)  NULL,
    avatar              VARCHAR(500)  NULL,

    -- Authentication. password_hash holds a PHP password_hash() digest and is
    -- never read back into a response.
    password_hash       VARCHAR(255)  NOT NULL,
    password_changed_at DATETIME      NULL,

    -- A user account is active on signup; a supplier account is pending until
    -- an administrator approves it. Both use the same column.
    status              ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'active',
    approved_at         DATETIME      NULL,
    approved_by         INT           NULL,
    rejection_reason    VARCHAR(500)  NULL,

    email_verified_at   DATETIME      NULL,
    two_factor_enabled  TINYINT(1)    NOT NULL DEFAULT 0,
    two_factor_secret   VARCHAR(255)  NULL,

    -- Failed-login throttling, evaluated per account.
    failed_login_count  INT           NOT NULL DEFAULT 0,
    locked_until        DATETIME      NULL,
    last_login_at       DATETIME      NULL,
    last_login_ip       VARCHAR(45)   NULL,

    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_platform_users_email (email),
    KEY idx_platform_users_type_status (account_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Server-side sessions. The browser holds only the random selector+validator
-- pair; the validator is stored hashed so a database read cannot mint a
-- session.
CREATE TABLE IF NOT EXISTS user_sessions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    selector        CHAR(32)      NOT NULL,
    validator_hash  CHAR(64)      NOT NULL,
    ip_address      VARCHAR(45)   NULL,
    user_agent      VARCHAR(500)  NULL,
    remember_me     TINYINT(1)    NOT NULL DEFAULT 0,
    expires_at      DATETIME      NOT NULL,
    revoked_at      DATETIME      NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    DATETIME      NULL,

    UNIQUE KEY uq_user_sessions_selector (selector),
    KEY idx_user_sessions_user (user_id),
    KEY idx_user_sessions_expiry (expires_at),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id)
        REFERENCES platform_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens. Single use, short lived, stored hashed.
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    selector    CHAR(32)      NOT NULL,
    token_hash  CHAR(64)      NOT NULL,
    expires_at  DATETIME      NOT NULL,
    used_at     DATETIME      NULL,
    request_ip  VARCHAR(45)   NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_password_resets_selector (selector),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
        REFERENCES platform_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting keyed by whatever the caller chooses (ip, ip+email, ...).
-- Rows are disposable; the application prunes them.
CREATE TABLE IF NOT EXISTS auth_throttle (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    throttle_key  VARCHAR(190)  NOT NULL,
    action        VARCHAR(60)   NOT NULL,
    attempts      INT           NOT NULL DEFAULT 1,
    first_at      DATETIME      NOT NULL,
    last_at       DATETIME      NOT NULL,

    UNIQUE KEY uq_auth_throttle (throttle_key, action),
    KEY idx_auth_throttle_last (last_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
