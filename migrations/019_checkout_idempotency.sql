-- ============================================================================
-- EXD — wallet checkout idempotency
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- A browser retry, double click, proxy retry or concurrent submit must not
-- create two paid orders for the same signed-in customer. The application
-- supplies a per-attempt idempotency key and this unique index is the final
-- database-level guard against a duplicate charge.
-- ============================================================================

ALTER TABLE orders
    ADD COLUMN idempotency_key VARCHAR(64) NULL AFTER order_code;

CREATE UNIQUE INDEX uq_orders_user_idempotency
    ON orders (user_id, idempotency_key);
