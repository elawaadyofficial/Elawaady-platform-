-- Add an optional visual asset to main storefront categories.
-- Additive only: existing category rows remain valid and continue to fall back to icons.
ALTER TABLE store_categories
  ADD COLUMN image VARCHAR(500) NULL AFTER icon;
