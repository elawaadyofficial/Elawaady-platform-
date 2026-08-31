-- ============================================================================
-- EXD — staff identity and role-based access control
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE, no ALTER of existing tables.
--
-- Staff are administrators and team members. They are not platform accounts:
-- a customer or supplier can never hold a permission from this table.
-- Permissions are seeded here so a fresh install has a working matrix; the
-- seeds are INSERT IGNORE so re-running never disturbs edited rows.
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin_users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(100)  NOT NULL,
    password            VARCHAR(255)  NOT NULL,
    display_name        VARCHAR(190)  NULL,
    email               VARCHAR(190)  NULL,
    is_active           TINYINT(1)    NOT NULL DEFAULT 1,
    is_super_admin      TINYINT(1)    NOT NULL DEFAULT 0,
    failed_login_count  INT           NOT NULL DEFAULT 0,
    locked_until        DATETIME      NULL,
    last_login_at       DATETIME      NULL,
    last_login_ip       VARCHAR(45)   NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    role_key     VARCHAR(60)   NOT NULL,
    name         VARCHAR(190)  NOT NULL,
    description  VARCHAR(500)  NULL,
    is_system    TINYINT(1)    NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    permission_key  VARCHAR(100)  NOT NULL,
    name            VARCHAR(190)  NOT NULL,
    module          VARCHAR(60)   NOT NULL,

    UNIQUE KEY uq_permissions_key (permission_key),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id        INT NOT NULL,
    permission_id  INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id)
        REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id)
        REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_roles (
    admin_id    INT NOT NULL,
    role_id     INT NOT NULL,
    granted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    granted_by  INT NULL,
    PRIMARY KEY (admin_id, role_id),
    CONSTRAINT fk_admin_roles_admin FOREIGN KEY (admin_id)
        REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_roles_role FOREIGN KEY (role_id)
        REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT           NOT NULL,
    selector        CHAR(32)      NOT NULL,
    validator_hash  CHAR(64)      NOT NULL,
    ip_address      VARCHAR(45)   NULL,
    user_agent      VARCHAR(500)  NULL,
    expires_at      DATETIME      NOT NULL,
    revoked_at      DATETIME      NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_admin_sessions_selector (selector),
    KEY idx_admin_sessions_admin (admin_id),
    CONSTRAINT fk_admin_sessions_admin FOREIGN KEY (admin_id)
        REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every privileged action writes one row here. Append only.
CREATE TABLE IF NOT EXISTS audit_log (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_type    ENUM('admin','user','supplier','system') NOT NULL DEFAULT 'admin',
    actor_id      INT           NULL,
    actor_label   VARCHAR(190)  NULL,
    action        VARCHAR(100)  NOT NULL,
    entity_type   VARCHAR(60)   NULL,
    entity_id     INT           NULL,
    summary       VARCHAR(500)  NULL,
    details       TEXT          NULL,
    ip_address    VARCHAR(45)   NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_audit_log_entity (entity_type, entity_id),
    KEY idx_audit_log_actor (actor_type, actor_id),
    KEY idx_audit_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed roles ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO roles (role_key, name, description, is_system) VALUES
  ('super_admin',   'مدير عام',        'صلاحية كاملة على كل وحدات لوحة التحكم.', 1),
  ('catalog_admin', 'مسؤول الكتالوج',  'الأقسام والخدمات والصور والبنرات.', 1),
  ('orders_admin',  'مسؤول الطلبات',   'الطلبات وحالاتها والدفع والتسليم.', 1),
  ('support_agent', 'موظف دعم',        'متابعة الطلبات والرد على العملاء دون تعديل الأسعار.', 1),
  ('finance_admin', 'مسؤول مالي',      'المحفظة والمعاملات وتسويات الموردين.', 1),
  ('mediator',      'وسيط',            'إدارة صفقات الوساطة فقط.', 1),
  ('content_admin', 'مسؤول المحتوى',   'الصفحات والسياسات وأقسام الصفحة الرئيسية.', 1);

-- ── Seed permissions ────────────────────────────────────────────────────────
INSERT IGNORE INTO permissions (permission_key, name, module) VALUES
  ('catalog.view',       'عرض الكتالوج',            'catalog'),
  ('catalog.manage',     'إدارة الأقسام والخدمات',  'catalog'),
  ('media.manage',       'إدارة الصور والبنرات',    'catalog'),
  ('orders.view',        'عرض الطلبات',             'orders'),
  ('orders.manage',      'تغيير حالة الطلبات',      'orders'),
  ('orders.refund',      'استرداد الطلبات',         'orders'),
  ('suppliers.view',     'عرض الموردين',            'suppliers'),
  ('suppliers.approve',  'اعتماد الموردين',         'suppliers'),
  ('suppliers.services', 'اعتماد خدمات الموردين',   'suppliers'),
  ('users.view',         'عرض الحسابات',            'users'),
  ('users.manage',       'إدارة الحسابات',          'users'),
  ('wallet.view',        'عرض المحفظة',             'finance'),
  ('wallet.manage',      'إدارة رصيد المحفظة',      'finance'),
  ('payments.confirm',   'تأكيد المدفوعات',         'finance'),
  ('settlements.manage', 'تسويات الموردين',         'finance'),
  ('mediation.view',     'عرض الوساطات',            'mediation'),
  ('mediation.manage',   'إدارة الوساطات',          'mediation'),
  ('assets.view',        'عرض الأصول الرقمية',      'assets'),
  ('assets.manage',      'إدارة الأصول الرقمية',    'assets'),
  ('providers.manage',   'إدارة مزودي الـAPI',      'providers'),
  ('cms.manage',         'إدارة الصفحات والأقسام',  'cms'),
  ('settings.manage',    'إعدادات المنصة',          'settings'),
  ('rbac.manage',        'إدارة الأدوار والصلاحيات','settings'),
  ('audit.view',         'عرض سجل العمليات',        'settings');

-- ── Seed the default role matrix ────────────────────────────────────────────
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.role_key='super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.permission_key IN ('catalog.view','catalog.manage','media.manage','cms.manage')
WHERE r.role_key='catalog_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.permission_key IN ('orders.view','orders.manage','orders.refund','catalog.view','users.view','payments.confirm')
WHERE r.role_key='orders_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.permission_key IN ('orders.view','orders.manage','catalog.view','users.view','mediation.view')
WHERE r.role_key='support_agent';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.permission_key IN ('wallet.view','wallet.manage','payments.confirm','settlements.manage','orders.view','orders.refund')
WHERE r.role_key='finance_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.permission_key IN ('mediation.view','mediation.manage','orders.view')
WHERE r.role_key='mediator';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.permission_key IN ('cms.manage','media.manage','catalog.view')
WHERE r.role_key='content_admin';
