-- ============================================================================
-- EXD — stock and availability on a service
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- These two columns were previously added by a browser-reachable script that
-- ran ALTER TABLE with no authentication at all. They belong here instead,
-- where the change is recorded, reviewed and applied once.
--
-- stock NULL means "not stock tracked", which is the correct default for a
-- service. Only a finite thing — a ready-made account, a card code — sets it.
-- ============================================================================

ALTER TABLE store_services ADD COLUMN stock INT NULL;
ALTER TABLE store_services ADD COLUMN availability VARCHAR(50) NOT NULL DEFAULT 'available';
