# Elawaady XDigital Store Architecture Baseline

This document is the implementation guardrail for the `chatgpt/store-build` branch. It keeps the existing EXD storefront as the functional base while the larger marketplace interface is built incrementally.

## Non-negotiable safety boundary

- The live `elawaady.com` store is not a deployment target for this branch.
- Development work stays on `chatgpt/store-build` until staging checks and manual review pass.
- Existing routes, database behavior, and legacy storefront functions are preserved unless a replacement is explicitly reviewed.
- Database schema changes must be additive, reviewed, and tested against an isolated staging database first.

## Storefront layers

### 1. Foundation

Existing PHP storefront and database access remain the compatibility layer:

- `db_connect.php`
- `header.php`
- `footer.php`
- current service/category/search routes

### 2. Design system

New visual work should consume the additive EXD layers rather than rewriting legacy styles in place:

- `exd-tokens.css` — shared EXD design tokens
- `exd-media.css` — reusable service image/video slots
- `storefront.css` — storefront presentation
- `motion.css` — motion and interaction layer

### 3. Marketplace homepage

The homepage should grow as independent sections so each can be reviewed or rolled back separately. Planned order:

1. announcement/header/mega navigation
2. large hero carousel and search
3. quick-access services
4. main and popular categories
5. best sellers and featured services
6. promotional banner system
7. digital subscriptions
8. AI and software
9. social-media services
10. verification services
11. gaming, gift cards, and credits
12. accounts/channels/pages marketplace
13. websites, stores, protection, and recovery
14. new arrivals and special offers
15. supplier/reseller marketplace
16. trust, statistics, reviews, brands, payments, FAQ
17. support CTA and expanded footer

Each section must work independently with empty or partial database content and must not break the legacy storefront when disabled.

## Reusable component contracts

### Service media

Service cards and service-detail pages use the existing `store_services.image` field as the current media source. Components must support image, MP4/WebM media, and a branded EXD fallback without requiring a schema migration.

### Cards

New cards should share spacing, radii, shadows, typography, focus states, and responsive behavior through EXD tokens. Route-specific markup may vary, but visual primitives should not be duplicated without a reason.

### Responsive behavior

Desktop, tablet, and mobile layouts are treated as intentional compositions. Desktop layouts must not simply be compressed for mobile.

## Delivery gates

Before a staging cutover:

1. `Storefront Safety` CI must pass.
2. `php staging_check.php` must pass with isolated staging environment values.
3. The live domain must remain rejected by staging checks.
4. No production database credentials may be used.
5. Changed routes must be smoke-tested on staging.
6. A rollback commit must be recorded before cutover.
7. Backend/API integration remains blocked until the backend safety gate passes with a complete source/test package.

## Immediate implementation sequence

1. Finish media-slot consistency across remaining service/category surfaces.
2. Add section primitives and separators without replacing legacy routes.
3. Build the enlarged homepage section-by-section behind additive CSS/markup.
4. Reconcile old EXD dashboard/assets before visual replacement of admin surfaces.
5. Connect Storefront to API only after backend source, migrations, tests, and safety checks are complete.
