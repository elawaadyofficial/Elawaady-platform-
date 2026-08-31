-- EXD — storefront migration 003
-- Adds the "was" price a deal needs, so a discount section can show the old
-- figure struck through beside the new one.
--
-- Additive only: one nullable column. Nothing is dropped, truncated or
-- rewritten, and every existing row keeps NULL, which the storefront reads as
-- "not on offer" and renders as a plain price.

ALTER TABLE store_services
    ADD COLUMN old_price DECIMAL(10,2) NULL AFTER price;
