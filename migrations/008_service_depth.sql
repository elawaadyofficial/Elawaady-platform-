-- ============================================================================
-- EXD — service workflow, supplier binding and the multi-valued service tables
-- ----------------------------------------------------------------------------
-- Additive only. Every ALTER adds a nullable or defaulted column, so existing
-- store_services rows stay valid and the storefront renders them unchanged.
--
-- Design note. The scalar configuration of a service — how it is paid for, who
-- receives the order, which supplier fulfils it, what the buttons say — is
-- genuinely one-to-one with the service and stays on store_services, where the
-- admin form already edits it in one transaction. What is genuinely
-- many-per-service — gallery images, FAQ entries, purchase options, related
-- services, the placements a service appears in — becomes its own table below
-- rather than a comma-joined column.
-- ============================================================================

-- ── Fulfilment workflow ─────────────────────────────────────────────────────
ALTER TABLE store_services
  ADD COLUMN source_type ENUM('store','supplier') NOT NULL DEFAULT 'store';
ALTER TABLE store_services
  ADD COLUMN payment_method ENUM('auto','manual_support') NOT NULL DEFAULT 'auto';
ALTER TABLE store_services
  ADD COLUMN order_receiver ENUM('system','support','supplier') NOT NULL DEFAULT 'system';
ALTER TABLE store_services
  ADD COLUMN execution_method ENUM('api','admin_manual','support_manual','supplier_via_support')
      NOT NULL DEFAULT 'admin_manual';
ALTER TABLE store_services
  ADD COLUMN post_order_contact ENUM('none','store_support','support_whatsapp','elawaady_whatsapp')
      NOT NULL DEFAULT 'none';
ALTER TABLE store_services
  ADD COLUMN require_availability_confirmation TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services
  ADD COLUMN require_admin_approval_before_execution TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services
  ADD COLUMN auto_start_after_payment TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services
  ADD COLUMN allow_wallet_payment TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services
  ADD COLUMN show_payment_gateways TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services
  ADD COLUMN progress_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0;

-- ── Supplier binding. Never selected by a customer-facing query. ────────────
ALTER TABLE store_services ADD COLUMN supplier_id INT NULL;
ALTER TABLE store_services ADD COLUMN backup_supplier_id INT NULL;
ALTER TABLE store_services ADD COLUMN supplier_sell_price DECIMAL(12,2) NULL;
ALTER TABLE store_services ADD COLUMN supplier_internal_notes TEXT NULL;
ALTER TABLE store_services ADD COLUMN supplier_can_view_order TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN supplier_can_update_status TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN supplier_can_upload_delivery_proof TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services ADD COLUMN hide_customer_data_from_supplier TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_services ADD KEY idx_store_services_supplier (supplier_id);

-- ── Mediation, per service ──────────────────────────────────────────────────
ALTER TABLE store_services ADD COLUMN mediation_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN mediation_type VARCHAR(50) NOT NULL DEFAULT 'none';
ALTER TABLE store_services ADD COLUMN mediation_fee DECIMAL(10,2) NULL;
ALTER TABLE store_services ADD COLUMN mediation_fee_mode ENUM('fixed','percent') NOT NULL DEFAULT 'fixed';
ALTER TABLE store_services ADD COLUMN mediator_commission DECIMAL(10,2) NULL;
ALTER TABLE store_services ADD COLUMN show_mediation_terms TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE store_services ADD COLUMN mediation_safety_days INT NOT NULL DEFAULT 0;

-- ── Storefront presentation ─────────────────────────────────────────────────
ALTER TABLE store_services ADD COLUMN primary_button_label VARCHAR(80) NOT NULL DEFAULT 'اشتري الآن';
ALTER TABLE store_services ADD COLUMN secondary_button_label VARCHAR(80) NOT NULL DEFAULT 'أضف إلى السلة';
ALTER TABLE store_services
  ADD COLUMN image_background_mode ENUM('transparent','store','custom') NOT NULL DEFAULT 'transparent';
ALTER TABLE store_services ADD COLUMN image_custom_background VARCHAR(50) NULL;
ALTER TABLE store_services
  ADD COLUMN banner_fit ENUM('original','contain','cover','auto_height','full_width','custom')
      NOT NULL DEFAULT 'original';
ALTER TABLE store_services ADD COLUMN banner_custom_height INT NULL;
ALTER TABLE store_services ADD COLUMN main_image VARCHAR(500) NULL;
ALTER TABLE store_services ADD COLUMN icon_image VARCHAR(500) NULL;
ALTER TABLE store_services ADD COLUMN banner_image VARCHAR(500) NULL;

-- ── Quantity and option ranges ──────────────────────────────────────────────
ALTER TABLE store_services ADD COLUMN min_quantity INT NOT NULL DEFAULT 1;
ALTER TABLE store_services ADD COLUMN max_quantity INT NOT NULL DEFAULT 1000000;
ALTER TABLE store_services ADD COLUMN quantity_step INT NOT NULL DEFAULT 1;

-- ── Category and subcategory presentation ───────────────────────────────────
ALTER TABLE store_categories ADD COLUMN banner_display_mode VARCHAR(30) NOT NULL DEFAULT 'original';
ALTER TABLE store_categories ADD COLUMN banner_full_width TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_categories ADD COLUMN home_subcategory_limit INT NOT NULL DEFAULT 6;
ALTER TABLE store_categories ADD COLUMN show_home TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE store_categories ADD COLUMN banner_image VARCHAR(500) NULL;

ALTER TABLE store_subcategories ADD COLUMN button_primary_label VARCHAR(80) NOT NULL DEFAULT 'اطلب الآن';
ALTER TABLE store_subcategories ADD COLUMN button_secondary_label VARCHAR(80) NOT NULL DEFAULT 'التفاصيل';
ALTER TABLE store_subcategories ADD COLUMN show_home TINYINT(1) NOT NULL DEFAULT 1;

-- ── Many-per-service tables ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS service_gallery (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    service_id  INT           NOT NULL,
    image       VARCHAR(500)  NOT NULL,
    caption     VARCHAR(255)  NULL,
    media_type  ENUM('image','video') NOT NULL DEFAULT 'image',
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_service_gallery_service (service_id, sort_order),
    CONSTRAINT fk_service_gallery_service FOREIGN KEY (service_id)
        REFERENCES store_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_faq (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    service_id  INT           NOT NULL,
    question    VARCHAR(500)  NOT NULL,
    answer      TEXT          NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,

    KEY idx_service_faq_service (service_id, sort_order),
    CONSTRAINT fk_service_faq_service FOREIGN KEY (service_id)
        REFERENCES store_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A purchase option is a choice the buyer makes: quality, warranty, target
-- type, delivery speed. Its values carry their own price delta.
CREATE TABLE IF NOT EXISTS service_options (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    service_id   INT           NOT NULL,
    option_key   VARCHAR(60)   NOT NULL,
    label        VARCHAR(190)  NOT NULL,
    input_type   ENUM('select','radio','text','url','number') NOT NULL DEFAULT 'select',
    is_required  TINYINT(1)    NOT NULL DEFAULT 0,
    help_text    VARCHAR(500)  NULL,
    sort_order   INT           NOT NULL DEFAULT 0,

    KEY idx_service_options_service (service_id, sort_order),
    CONSTRAINT fk_service_options_service FOREIGN KEY (service_id)
        REFERENCES store_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_option_values (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    option_id      INT           NOT NULL,
    label          VARCHAR(190)  NOT NULL,
    value_key      VARCHAR(120)  NOT NULL,
    price_delta    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    delta_mode     ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
    is_default     TINYINT(1)    NOT NULL DEFAULT 0,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order     INT           NOT NULL DEFAULT 0,

    KEY idx_service_option_values_option (option_id, sort_order),
    CONSTRAINT fk_service_option_values_option FOREIGN KEY (option_id)
        REFERENCES service_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_related (
    service_id          INT NOT NULL,
    related_service_id  INT NOT NULL,
    sort_order          INT NOT NULL DEFAULT 0,
    PRIMARY KEY (service_id, related_service_id),
    CONSTRAINT fk_service_related_service FOREIGN KEY (service_id)
        REFERENCES store_services(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_related_related FOREIGN KEY (related_service_id)
        REFERENCES store_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
