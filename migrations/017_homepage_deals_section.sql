-- ============================================================================
-- EXD — the offers band becomes a managed section
-- ----------------------------------------------------------------------------
-- Additive only. No DROP, no TRUNCATE, no DELETE.
--
-- The discounted-services band used to be printed after the section loop, so
-- it was the one part of the homepage an administrator could not move or hide.
-- It is a row now, like everything else.
-- ============================================================================

INSERT IGNORE INTO homepage_sections
  (section_key, title, subtitle, section_type, layout, item_limit, sort_order, is_active)
VALUES
  ('deals', 'عروض وخصومات', 'وفّر أكثر', 'deals', 'rail', 14, 85, 1);
