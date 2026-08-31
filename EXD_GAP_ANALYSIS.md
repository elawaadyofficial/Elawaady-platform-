# EXD | Elawaady XDigital — Gap Analysis

**Reference:** `EXD_CLAUDE_MASTER_HANDOFF_31-08-2026.md`
**Branch:** `chatgpt/store-build` · **Date:** 31/08/2026
**Method:** read the whole current tree, `database.sql`, the storefront pages, the
legacy dashboard audit and `dashboard_module_matrix.json`. No SQL was executed
against any database. `elawaady.com` was not contacted.

---

## 0. The headline

The master spec describes a platform: accounts, orders, wallet, suppliers,
mediation, SMM providers, a digital-asset marketplace, a CMS and an admin
dashboard. What exists today is a **storefront catalogue** — three tables and
six public pages, with no accounts, no orders and no writes of any kind.

Measured against the spec, the project is roughly **8% built**, and the 8% is
almost entirely the part the spec says least about: the visual layer.

That is not a criticism of the work so far. It is the number that should drive
sequencing, because most of the spec cannot be built in any order — orders need
accounts, mediation needs orders, supplier settlement needs mediation.

---

## 1. Existing

### Database — 3 tables

| Table | Columns | Seeded |
|---|---|---|
| `store_categories` | id, name, description, icon, sort_order, is_active, created_at | 43 rows |
| `store_subcategories` | + category_id, image (migration 002) | 89 rows |
| `store_services` | + subcategory_id, price, old_price (003), service_link, status, image | 20 rows |
| `store_section_banners` | migration 001 — placement, mode, artwork, link, visibility | 0 rows |

### Storefront — 6 public pages

`index.php` · `categories.php` · `subcategories.php` · `service.php` ·
`search.php` · `contact.php`, plus `header/footer`, the `banner.php` component
and `sections.php` (the homepage rhythm renderer).

All read-only. `db_connect.php` exposes `fetch_all()` and the `e()` escaping
helper; every query is a prepared statement.

### Design system

`exd-tokens.css` (palette sampled from the real store) · `style.css` ·
`storefront.css` · `exd-media.css` · `exd-sections.css` · `exd-layouts.css`
(full-width rails) · `exd-banner.css` · `motion.css` · `exd-interaction.css`.

### Safety infrastructure

`staging_check.php` rejects an `APP_URL` pointing at `elawaady.com` or its
subdomains. CI (`storefront-safety.yml`) lints every PHP file, guards the
homepage composition contract, blocks destructive SQL in `migrations/`, and
proves the live-domain guard both fires and does not over-fire.

### Legacy dashboard

17 page modules recovered and audited. **3 are database-backed**
(`categories`, `services`, `add_service`) and map partially onto the current
tables. **14 are static UI only** — markup with no data contract behind them.
The legacy SQL dump has 4 tables (`users`, `categories`, `services`,
`service_gallery`) and is incomplete relative to its own UI.

---

## 2. Missing

Everything below has **zero implementation** — no table, no page, no code path.

| # | Spec | Area |
|---|---|---|
| 1 | §2 | Accounts. No `users` table, no auth, no sessions, no roles, no 2FA |
| 2 | §2 | User profile: balance, payments, orders, favourites, notifications, activity |
| 3 | §2 | Supplier profile + separate limited Supplier Dashboard |
| 4 | §6 | Cart, checkout, orders, order statuses, order timeline |
| 5 | §5 | Service-level workflow: source_type, payment_method, order_receiver, execution_method, post_order_contact |
| 6 | §7 | Supplier purchase flow + its 14 order statuses + support handoff message |
| 7 | §8 | SMM/API providers, service import, mapping, profit rules, encrypted keys |
| 8 | §9 | Digital assets marketplace (7 platforms) + ownership transfer + 7-day safety |
| 9 | §10, §13 | Mediation module, mediation page, service-level mediation toggle |
| 10 | §11 | CMS: 18 editable policy/static pages |
| 11 | §12 | Admin dashboard — every module |
| 12 | §5, §8 | Wallet, payment gateways, transactions |
| 13 | §4 | Full service page (gallery, dynamic options, live price, reviews, FAQ) |
| 14 | §13 | Placement controls: featured / most-used / most-ordered / new / offers |
| 15 | — | System Settings (WhatsApp, Telegram, Messenger, support routing) |
| 16 | §9 | Policy versioning + acceptance log (account, IP, timestamp) |
| 17 | — | Notifications, audit log, media library |
| 18 | — | Reviews (stars, text, images) |
| 19 | — | Chatbot |

**Nothing in this list can be delivered by CSS or templates.** Items 1–4 are
the foundation; 5–19 all depend on them.

---

## 3. Needs modification

| What | Now | Spec | Note |
|---|---|---|---|
| Main categories on the homepage | Icon tiles in a rail | §3 — each main category is a **full-width banner**, click opens the category page | Real layout change. The banner component already supports it |
| Category page | Subcategory cards only | §3 — banner, then a **sample** of subcategory cards, each with اطلب الآن + التفاصيل | Buttons exist on `subcategories.php`; `categories.php` still has none |
| Card grid | Rails (scrolling) on the homepage; 4/3/2 on inner pages | §3 — **Mobile 2 / Tablet 3 / Desktop 4** | Inner pages are 4/3/2 → should be 4/3/2 read as desktop/tablet/mobile ✓. Homepage rails are a deliberate deviation you approved; flag if you want grids there |
| `store_services` | 12 columns | Spec needs ~60 fields | Do **not** widen this table to 60 columns. Split: `service_workflow`, `service_mediation`, `service_placement`, `service_options`, `digital_assets` |
| `service.php` | 72 lines, basic | §4 — ~25 content blocks | Full rebuild, after orders exist |
| Service card buttons | Hardcoded التفاصيل + شراء الآن | §3 — customisable per service, default أضف إلى السلة + اشتري الآن | Needs the placement table + a cart |
| Icons vs images | Categories use emoji, services use images | §Service Images — image is mandatory per service, icon is a **helper only** | Services ✓. Categories/subcategories still icon-first |
| `database.sql` | Starts with 3 × `DROP TABLE` | — | **Must never be run against a populated database.** See §7 |
| Legacy merchant modules | 5 `merchant-*` pages exist in the legacy tree | Spec — no trader role, ever | Do not port. Any `تاجر` value in old data migrates to supplier |

---

## 4. Keep as-is

These are correct, in use, and should not be rebuilt:

- The three catalogue tables and their 152 seeded rows.
- `db_connect.php` — prepared statements and the `e()` escaping helper.
- `banner.php` / `store_section_banners` — already supports the spec's
  "banners with no forced size: original / contain / cover / auto-height /
  full-width / custom-height, no default crop".
- The whole CSS token layer and the rails.
- `staging_check.php` and the CI safety workflow.
- The 47 assets under `assets/` (banners, brand, catalog, fonts, payments).
- `sections.php` — the homepage rhythm is data-driven and picks up new
  categories with no code change.

---

## 5. Database changes

Every migration must be **additive**. `migrations/` is CI-guarded against
`DROP` / `TRUNCATE` / `DELETE FROM` and that guard stays.

**Gate 1 — identity (blocks everything else)**
`users`, `user_sessions`, `roles`, `suppliers`, `supplier_profiles`

**Gate 2 — commerce**
`carts`, `cart_items`, `orders`, `order_items`, `order_status_history`,
`payments`, `wallets`, `wallet_transactions`

**Gate 3 — service depth**
`service_workflow`, `service_mediation`, `service_placement`,
`service_options`, `service_option_values`, `service_gallery`, `service_faq`,
`service_related`

**Gate 4 — supplier & providers**
`supplier_services`, `supplier_settlements`, `api_providers`,
`provider_services`, `provider_sync_log`

**Gate 5 — mediation & assets**
`mediations`, `mediation_parties`, `mediation_status_history`,
`digital_assets`, `asset_transfers`, `asset_audience_stats`

**Gate 6 — platform**
`static_pages`, `policies`, `policy_versions`, `policy_acceptances`,
`system_settings`, `notifications`, `reviews`, `audit_log`, `media_library`

~40 tables. `store_services` gains foreign keys, not 50 columns.

---

## 6. Frontend changes

**Now (no backend needed)**
1. Main-category banners on the homepage (§3) — the component exists.
2. `categories.php` gets the card language + buttons the other pages have.
3. Category/subcategory image slots, so an icon is never the only visual.
4. The standalone **الوساطة الآمنة** page — static first, CMS-backed later.
5. Placement sections rendered from a table instead of `sort_order`.

**Blocked on Gate 1–2**
Cart, checkout, order tracking, the full service page, reviews, favourites,
the customer account area, the supplier "contact support to complete" flow.

---

## 7. Dashboard changes

There is **no dashboard in this repository**. The legacy one is 17 pages of
which 14 have no data behind them. Building the admin surface the spec
describes — services, orders by source, providers, suppliers, assets,
mediation, CMS, audit — is the single largest body of work in the project, and
`Add/Edit Service` alone spans 18 field groups (§Dashboard).

Recommendation: build the dashboard **against the same gates**, one module per
gate, rather than as a separate project. The legacy static pages are useful as
layout reference only.

---

## 8. Security / risk

| # | Risk | Severity | Action |
|---|---|---|---|
| 1 | `database.sql` opens with 3 × `DROP TABLE` | **Critical** | It is a bootstrap file, not a migration. Add a refusal guard so it cannot run against a database that already has rows |
| 2 | No authentication at all | **Critical** | Nothing may be built that writes user data until Gate 1 lands. Adding a cart or wallet before auth would expose it publicly |
| 3 | Supplier identity leaking to customers | **High** | Enforce at the query layer, not the template. A supplier's name must not be in the response a customer receives at all |
| 4 | Provider API keys | **High** | Encrypted at rest, server-side only, never in a response, never in git. No secret store exists yet |
| 5 | Policy acceptance is a legal record | **High** | Version every policy; log account + IP + timestamp on acceptance. §9 requires this for the YouTube mediation terms |
| 6 | Legacy `db.php` credentials + raw PDO error output | **High** | Do not port. Errors must never reach the browser |
| 7 | Money handling: mediation holds, supplier settlement, 7-day safety | **High** | Needs an immutable ledger and an audit trail, not a mutable balance column |
| 8 | Two runtimes | **Open decision** | See below |

### The open decision — PHP or Python?

The repo contains **two incompatible stacks**: the PHP storefront that runs
today, and `backend/` — a Python/Passenger app whose own `BUILD_STATUS.md`
says only the deployment entry files were imported, with `src/`, migrations,
schema and tests still missing.

Building the platform twice is not viable, and choosing later costs more than
choosing now. **This blocks Gate 1**, because auth is written once, in one
language.

My recommendation is **PHP**: it is what runs, what the storefront is written
in, what the legacy dashboard used, and what shared hosting deploys without
Passenger configuration. The Python tree would stay in the repo, untouched,
until you decide otherwise.

---

## 9. Proposed sequence

| Gate | Delivers | Depends on |
|---|---|---|
| 0 | Runtime decision · `database.sql` guard · category banners · categories page · mediation page | — |
| 1 | Users, sessions, roles, supplier accounts, admin login | Gate 0 |
| 2 | Service workflow + placement + mediation toggle in the schema | Gate 1 |
| 3 | Cart, orders, statuses, timeline | Gate 2 |
| 4 | Supplier flow + support handoff + settlement holds | Gate 3 |
| 5 | Mediation module + policy versioning + acceptance log | Gate 3 |
| 6 | Providers/API + digital assets | Gate 4 |
| 7 | CMS, settings, notifications, reviews, audit | Gate 1 |
| 8 | Admin dashboard modules | rides gates 1–7 |

No gate is "production ready" on a passing syntax check. Each needs the real
tests the spec names: database, RBAC, orders, supplier workflow, mediation,
payment, API, and failure paths.

---

## 10. Two things I do not have

1. **The Google Drive asset folder.** The message said a link would be
   attached; none arrived. Until it does I am holding to the standing rule —
   no invented artwork, no guessed service-to-image mapping, media slots kept
   ready and empty.
2. **A confirmed runtime.** See §8.

Neither blocks Gate 0.
