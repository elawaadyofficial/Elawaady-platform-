# CLAUDE_FINAL_HANDOFF.md — EXD | Elawaady XDigital

**Snapshot commit:** `c79fbec4afda52a1de831ed52699142700209d6`
**Branch:** `chatgpt/store-build` (this is the only branch that carries this work; `main` is untouched)
**Snapshot date:** 2026-09-02
**Runtime:** PHP 8.1+ (tested on 8.4) + MySQL 8 / MariaDB 10.6+ (`mysqli`), no framework
**Live `elawaady.com`:** never modified by this work. As of this exact snapshot, `tools/install.php` refuses **absolutely and unconditionally** to install with `APP_URL` pointing at `elawaady.com` or any subdomain — there is no override flag. (An earlier commit on this branch, `c79fbec`, briefly added a deliberate `ELAWAADY_OWNER_DEPLOY_CONFIRM` terminal-only opt-in after the domain owner explicitly authorized deploying there in conversation; a later commit on this same branch, `3d079fb "security: restore absolute live-domain install guard"`, removed that override and hardened the refusal back to unconditional, without visibility into that authorization. Whoever deploys next should know both of these things happened, and re-add a deliberate, reviewed override only if the owner still wants one.)

This file is a freeze-point handoff, not a roadmap pitch. Every status below was checked against the actual code and, where marked, against a live install running the actual migrations on a real database — not inferred from file names or claimed from memory. Where I could not verify something (no hosting reachable from this environment), that is stated explicitly rather than assumed.

---

## 1. What this project was when I started

A single-purpose PHP storefront catalogue with **3 database tables** (`store_categories`, `store_subcategories`, `store_services`), 6 read-only public pages (`index.php`, `categories.php`, `subcategories.php`, `service.php`, `search.php`, `contact.php`), no accounts, no orders, no admin dashboard, no writes of any kind. A separate incomplete Python `backend/` package existed (deployment entry files only, no `src/`), never wired to anything. A legacy static-HTML dashboard prototype and a legacy 4-table SQL dump existed as owner-supplied reference material, not integrated.

Full detail of that starting state is preserved in `EXD_GAP_ANALYSIS.md`, `EXD_SOURCE_INVENTORY.md`, and `LEGACY_DASHBOARD_AUDIT.md` — kept in the repo root exactly as written, because they are the accurate record of "before."

## 2. What I added or changed

Everything under §§4–15 below that is marked **IMPLEMENTED** or **PARTIALLY IMPLEMENTED**. In file terms: 24 additive SQL migrations (`migrations/001`–`024`), the entire `admin/` dashboard (37 files), the entire `lib/` business-logic layer (8 files), `db_connect.php` rewritten for environment-based config, all account/order/wallet/mediation storefront pages, the CSS design-token system, 23 test files (`tests/`), the CI workflow set (`.github/workflows/`), and the installer/migration tooling (`tools/`, `bootstrap.php`, `migrate.php`, `staging_check.php`). Nothing pre-existing was deleted; the original 3 tables and 6 pages are still present and still work, now joined to the rest of the schema.

## 3. Snapshot numbers (measured, not estimated)

| | |
|---|---|
| Tracked files in this snapshot | 473 |
| PHP files | 95 (16,492 lines) |
| CSS files | 12 |
| Database tables | 55 |
| Migrations | 24, all additive (CI-blocked from ever containing `DROP`/`TRUNCATE`/`DELETE FROM`) |
| Test files | 23 (`tests/`) — shell + PHP + JS, run against a real MariaDB in CI on every push |
| Store images shipped | 275 files (~15 MB WebP) |

---

## 4. Authentication — IMPLEMENTED

`login.php`, `register.php`, `logout.php`, `forgot-password.php`, `reset-password.php`, `lib/auth.php`, `admin/auth.php`.

- Password hashing via PHP `password_hash()`/`password_verify()`.
- Sessions are a selector/validator pair; the validator is stored hashed, so reading the `user_sessions` table does not let anyone forge a cookie.
- One identical error message whether the email doesn't exist or the password is wrong (no user enumeration).
- CSRF token required on every state-changing form (`lib/auth.php: csrf_field()` / `csrf_require()`).
- Login throttling: temporary lockout after 5 failed attempts (`tools/reset_throttle.php` clears it manually if needed).
- Changing a password invalidates every other session.
- Separate, parallel session system for the admin dashboard (`admin/auth.php`, `admin_users` table) — a staff login is not a storefront login.
- Password-reset **generates a valid, working token/link**; it does **not send an email** — no SMTP is configured (see §16, credentials needed).

Verified live via `tests/auth_flow.sh` (32 checks) against a real database, in CI on every push.

## 5. Accounts (User/Supplier) — IMPLEMENTED, decision already applied

`platform_users.account_type` is a MySQL `ENUM('user','supplier')`. There is no third value at the schema level — a `merchant`/`تاجر` role **cannot** be inserted; it is not a runtime check, it is a column constraint. Migration `014_legacy_trader_to_supplier.sql` is the rule for any legacy data carrying a merchant/trader label: it converts it to `supplier` and preserves the original label in `legacy_account_label` rather than discarding it.

- User profile: `account.php` — balance, order history, saved data.
- Supplier account: registers, stays `pending` until an admin approves it (`admin/suppliers.php`). Approved suppliers get `supplier-dashboard.php`.
- Admin/staff access is **not** a third account type — it is `admin_users` + RBAC (§6), a completely separate table from `platform_users`.

## 6. RBAC / Permissions — IMPLEMENTED

`migrations/006_rbac.sql`. 7 roles, 24 permissions. Every admin page declares the permission it needs and is checked against the acting admin's role at request time (`admin/auth.php: admin_require('permission.name')`); the sidebar only lists what that admin can open, but each page also self-checks — hiding a link is not the security boundary. The last `super_admin` cannot be demoted or removed (would lock out all admin access — blocked at the code level).

## 7. Orders / Cart / Checkout / Wallet — IMPLEMENTED

`cart.php`, `order_create.php`, `order-success.php`, `order-track.php`, `lib/checkout.php`, `lib/checkout_intent.php`, `lib/wallet.php`, `lib/pricing.php`.

- Price is always recalculated server-side from the `store_services` row at order time. A price sent from the browser is ignored — this is under an explicit test (`tests/order_wallet_flow.php`, `tests/atomic_checkout_flow.php`), not just asserted.
- Wallet balance is never mutated directly. Every change is an immutable ledger row (`wallet_transactions`) carrying the resulting balance; a correction is a new offsetting row, never an edit.
- A wallet-funded order is one atomic operation — order row, wallet debit, payment record, and status history all commit together or none do (`lib/checkout.php`).
- Order status is a fixed state machine; a status cannot be skipped or set out of sequence, including by an external provider callback claiming "Completed" early.
- Checkout idempotency (`migrations/019_checkout_idempotency.sql`, `lib/checkout_intent.php`): a duplicate/double-submitted checkout does not double-charge or double-create an order — tested in `tests/checkout_browser_replay.sh` and `tests/checkout_intent_flow.php`.
- **Payment methods live now: manual and wallet.** No online payment gateway (card/InstaPay/etc.) is wired — see §16.

## 8. Mediation (الوساطة الآمنة) — IMPLEMENTED

`mediation.php` (public info/terms page, license `857-766-767`), `lib/mediation.php`, `admin/mediation.php`, `migrations/011_mediation_assets.sql`.

- Escrow hold debits and reserves the buyer's funds; the seller receives nothing until release.
- Release credits the seller the deal value; the platform fee stays with the platform.
- Refund returns both amounts in full.
- After any of these operations the ledger still reconciles exactly against wallet balances (tested).
- Per-service mediation toggle exists in the service form.

## 9. Supplier workflow — IMPLEMENTED

Flow as specified: supplier submits a service → `pending` → admin review/approval (`admin/suppliers.php`, `admin/services.php`) → published at the **platform's** sell price, supplier's own cost stays server-side and out of every customer-facing query (not filtered in a template — the supplier-facing dashboard query itself never selects the customer's name/phone/email, so there is no code path that could leak it). Newly-approved supplier services publish as inactive by default so they can be reviewed before going live. The support-handoff message model (order → EXD support → supplier, no direct customer–supplier contact) is implemented at the query/workflow level described above; there is currently no separate in-app messaging/ticket UI for that handoff — it is a data-boundary guarantee, not a chat feature (see §14, NOT IMPLEMENTED chat/notifications).

## 10. SMM/API provider architecture — PARTIALLY IMPLEMENTED

`admin/providers.php`, `admin/provider-services.php`, `migrations/012_providers.sql`.

- **Implemented:** the schema (`api_providers`, `provider_services`, sync log), the admin CRUD screens to register a provider and map its services, and AES-256-GCM encryption at rest for provider API keys (`APP_ENCRYPTION_KEY` — never logged, never rendered back to any page).
- **Not implemented:** there is no live outbound integration actually calling a real SMM provider's API (no provider account/API key exists to test against), no automatic service import/sync job, and no automatic order-execution-via-provider path. This is real, tested infrastructure with nothing plugged into the far end yet — treat the "connects to a real provider" claim as **NOT IMPLEMENTED** until a provider account and API docs are supplied.

## 11. Digital Assets Marketplace — PARTIALLY IMPLEMENTED

`admin/digital-assets.php`, schema in `migrations/011_mediation_assets.sql`.

- **Implemented:** the schema for assets and ownership transfer records, and an admin screen to manage listed digital assets.
- **Not implemented:** no dedicated customer-facing marketplace browsing page distinct from the regular service catalogue, and the "7-day safety hold" behavior from the original spec is not coded as an enforced waiting-period workflow — it exists as a schema field, not as running logic. Treat the transfer-safety-window feature as **SPECIFICATION ONLY**.

## 12. CMS / Static Pages — IMPLEMENTED

`page.php` (dynamic renderer), `admin/pages.php`, `migrations/013_cms_platform.sql`, `migrations/020_seed_pages.sql`.

- Policy/static pages are database rows, editable from the dashboard — not text hard-coded in PHP files as they originally were.
- Policies are **versioned**, and acceptance is logged per account with IP + timestamp + the exact version text shown, so what a customer agreed to stays reconstructable even after the policy text changes later.

## 13. Homepage Sections / Dashboard Placement Controls — IMPLEMENTED

`sections.php` (the renderer), `admin/homepage-sections.php`, `admin/placements.php`, `migrations/017_homepage_deals_section.sql`.

- The homepage is a loop over `homepage_sections` rows: order, visibility, display style, and card count are columns, not code. Hiding a section, reordering it, or changing how many cards it shows is a dashboard edit, live-tested (hiding FAQ removed it from the page; reordering moved it).
- A section with no content behind it does not render an empty shell.
- "Most used" / "most requested" / "featured" / "new" / "offers" placement is driven by `admin/placements.php`, not by a hard-coded `sort_order` hack.
- Category banners are full-width and each opens its category page with its subcategories, per the agreed structure (§4 of the original spec conversation) — this is live in `sections.php` + `categories.php`/`subcategories.php`, not a mockup (see §17 for the screenshot proving this).

## 14. Admin Dashboard — IMPLEMENTED (26 pages)

`admin/index.php`, `categories.php` + `category-form.php`, `services.php` + `service-form.php`, `orders.php` + `order-view.php`, `suppliers.php`, `supplier-offers.php`, `providers.php` + `provider-services.php`, `wallets.php`, `payments.php`, `mediation.php`, `digital-assets.php`, `homepage-sections.php`, `placements.php`, `pages.php`, `brand-settings.php`, `settings.php`, `staff.php`, `users.php`, `carousel.php`, `chatbot-knowledge.php`, `audit.php`, plus shared `layout.php`, `auth.php`, `_helpers.php`, `upload_handler.php`, `image-validator.php`.

`service-form.php` (Add/Edit Service) carries every control group asked for and none removed during development: Basic Data, Category/Subcategory, SKU/Slug, Pricing, Supplier Cost, Profit, Images (main/icon/banner — both `main_image` and `image` columns are written together, fixed this session, see §18), Gallery, Description, Dynamic Fields, Execution/Workflow, API/Provider linkage, Mediation toggle, FAQ, Warranty/Terms, Homepage Placement, Related Services.

- No page in the dashboard performs raw schema DDL — that capability was found and deliberately removed early in this work (§18 has the specific finding); schema changes only happen via `migrate.php` from the terminal.
- No default admin account or password ships in the repository or database seed — the first staff account is created interactively via `tools/create_admin.php`, which prompts for the password rather than accepting it as a script argument (so it never lands in shell history or a file).
- **Chatbot:** `admin/chatbot-knowledge.php` manages a knowledge-base table; there is no actual conversational bot/NLU engine wired to it — the front-end chat widget itself is **NOT IMPLEMENTED**. Treat "chatbot" as a content-management screen for a bot that doesn't exist yet.
- **Notifications — PARTIALLY IMPLEMENTED.** The data layer is real: `lib/notify.php` has a working `notifications` table, `notify_user()`/`notify_staff()` are actually called from order creation, mediation, supplier and payment events (`order_create.php`, `admin/mediation.php`, `admin/suppliers.php`, `admin/payments.php`, `admin/order-view.php`, `admin/digital-assets.php`, `admin/supplier-offers.php`), and `notifications_for_user()` / `notifications_unread_count()` / `notifications_mark_read()` exist to read them back. **What's missing:** nothing in `header.php`, `account.php`, or `admin/layout.php` actually calls those read functions — there is no bell icon, unread badge, or notification list anywhere in the UI yet. Rows are being written correctly into a database no page displays. Wiring the read side into the header is a small, well-scoped next task.
- **In-app messaging, reviews (stars/text/images), audit-log UI beyond `admin/audit.php`'s current scope:** **NOT IMPLEMENTED** as customer-facing features. `admin/audit.php` covers admin-action auditing, not a general notification system.

## 15. Frontend / Storefront Design — IMPLEMENTED, this is the real one

**This is not a preview or a separate mockup. It is the code that runs when you open the store.** Verified by starting the actual application from this exact snapshot against a real MariaDB database and photographing what rendered — not a design file, not a Figma export. See §17 for the screenshot and how to reproduce it yourself in under two minutes.

- Dark / deep-purple background (`exd-tokens.css`), gradient (violet → magenta → orange) spent deliberately on one element per view — the paid action (`اطلب الآن` / `شراء الآن`) — everything else stays quiet per `EXD_DESIGN_RULES.md`, which is the actual rule this CSS was built against.
- Hero carousel (hand-rolled, no external library, `main.js`), category band with full-width banners, subcategory sample under each, service card rails ("الأكثر مبيعًا", "الأكثر استخدامًا", etc.), offers, the mediation section, FAQ, payment/trust strip, header/nav, footer — all present and all reading from live database rows via `sections.php`.
- Real service artwork on cards (not emoji) per `EXD_DESIGN_RULES.md` rule 1 — icons are the fallback only, hidden the instant artwork exists.
- Animation set: scroll reveal, fade/slide, card hover/lift, button feedback, carousel motion — implemented in `motion.css` / `exd-interaction.css` using `transform`/`opacity`, and gated behind `prefers-reduced-motion` (verify: `grep -n "prefers-reduced-motion" motion.css exd-interaction.css`).
- Grid counts are the resolved spec: 4 desktop / 3 tablet / 2 mobile (`STOREFRONT_LAYOUT_SPEC.md` — this was tried at 5/4/3, measured, and reverted).
- Banners are **not** forced to a crop or fixed aspect ratio — original/contain/cover/auto-height/full-width/custom-height are all supported per-banner, desktop and mobile independently (`banner.php`, `store_section_banners`).
- Bilingual (AR/EN toggle) from the original live-store route inventory: **NOT IMPLEMENTED** — the storefront is Arabic-only. This was a known gap identified in `EXD_SOURCE_INVENTORY.md` and was not closed.

### Known, honestly-documented visual gaps

- **13 category images and 13 category banners exist in `uploads/`, mapped to categories** (fixed this session — see §18) — but **Facebook-category artwork specifically is still missing** from the asset library. This was never fabricated; the slot stays empty rather than guessing.
- Some of the 20 seeded services carry 3:1 banner-shaped artwork rather than the 1:1 square the spec calls for on cards; this is the artwork actually supplied, not a code limitation — a 1:1 replacement image drops in without any code change.
- 76 square card images exist in `uploads/services/cards/` with no service linked to them yet — same rule, no guessed mapping.

## 16. Security work done this session — IMPLEMENTED

- Removed an unauthenticated dashboard endpoint capable of raw schema `ALTER TABLE` with no session, permission, or logging (anyone who knew the URL could change the database structure). Schema changes now only happen through `migrate.php`, which itself refuses `DROP`/`TRUNCATE`/`DELETE FROM` and refuses to run if an already-applied migration file was edited afterward (checksum drift detection).
- Settings moved out of a JSON file (that file's own comment claimed "does not touch the database," meaning settings didn't survive a fresh deploy) into database rows.
- 8 admin pages were found accepting writes from any authenticated-looking request regardless of origin/CSRF — all now require a valid CSRF token.
- `order_create.php` was found binding 22 variables to a 21-character `mysqli` type string — a runtime error, invisible to a syntax check, that meant **no checkout could ever succeed**. Fixed and covered by `tests/order_flow.sh`.
- Full-page Chromium screenshots were producing blank image frames below the fold that looked exactly like a CSS regression and were actually a browser re-layout/decode artifact at oversized viewports — documented so a future agent doesn't chase a phantom bug; the screenshot tooling now scrolls a real viewport instead.
- Category/service dashboard image-upload bugs (this session, commit `15af045`): `admin/category-form.php` was writing uploaded card/banner images to database columns that do not exist (`category_image`, `cat_banner_image`), which crashed with an uncaught `mysqli_sql_exception` on every upload attempt — the feature was completely broken, not degraded. Remapped to the real columns (`image`, `banner_image`). Separately, `admin/service-form.php`'s main-image upload wrote only to `main_image`, leaving the `image` column (which the homepage category-band cards actually read) empty — now writes both. Both fixes were live-tested against a real database with a real multipart upload, not just read for correctness.

## 17. Proof this is the real, wired frontend (not a mockup)

Reproduce from this exact snapshot, from a clean checkout:

```bash
cp .env.example .env               # fill in DB_* for a local MariaDB
mysql -uroot -e "CREATE DATABASE exd_verify"
DB_NAME=exd_verify php tools/install.php --admin=owner
php -S 127.0.0.1:8080 tools/dev-router.php
# open http://127.0.0.1:8080/
```

A screenshot taken exactly this way, from this snapshot's code against a live database, is delivered alongside this file (see the chat message this document was delivered in for the image / preview). It shows the real hero carousel, the gradient CTA buttons, real service artwork, and the dark-purple token system — the same code path a visitor hits, not a design export.

## 18. Known bugs / rough edges (current, as of this snapshot)

- No online payment gateway is wired (manual + wallet only) — not a bug, a missing integration (§10, §16 credentials list).
- Password-reset does not send email (no SMTP configured) — link generation itself works.
- No bilingual (EN) storefront route.
- No live SMM provider actually connected (infrastructure only, §10).
- No chat/notification/review system (§14).
- Facebook category artwork and a handful of service artworks are still missing/mismatched-ratio (§15) — intentionally left empty rather than fabricated.
- `backend/` (Python) is inert reference material only — see §19. It is not wired to anything and should not be assumed functional.
- GitHub branch protection / required status checks are **not** enforced on `chatgpt/store-build` at the repository level — the CI gates exist and pass, but nothing currently stops a privileged push from bypassing them (`STAGING_READINESS.md` §"GitHub branch protection gap"). Worth fixing before this becomes a team repo.

## 19. Legacy / reference material — preserved, not deleted, not wired to production

Per the standing rule ("don't delete legacy, build on top of what exists"):

- **`backend/`** — an incomplete Python/Passenger app (1 of 8 planned source-bundle chunks present, per `backend/source_bundle/manifest.json`). Untouched, unbuilt-upon. PHP is the runtime that actually ships (decision recorded in `EXD_GAP_ANALYSIS.md` §8). Do not assume any code in here runs; `backend/BUILD_STATUS.md` documents exactly what state it was left in.
- **`LEGACY_DASHBOARD_AUDIT.md`, `DASHBOARD_RECOVERY_PLAN.md`, `dashboard_module_matrix.json`** — the read-only audit of the owner-supplied legacy dashboard archive (17 page modules, only 3 database-backed) and the machine-readable module→table compatibility matrix used to plan the current `admin/` build. Kept as the paper trail for *why* the current admin panel's schema looks the way it does; the legacy PHP files themselves were never copied into this repository (only read, audited, and left in their original archive outside this repo).
- **`EXD_GAP_ANALYSIS.md`, `EXD_SOURCE_INVENTORY.md`** — dated 2026-08-30/31, describing the project's state *before* most of this session's work (accounts, orders, wallet, mediation, dashboard did not exist yet at the time these were written). They are historically accurate for that date and are kept as the record of the starting gap; they are **not** a description of the current build — this file (`CLAUDE_FINAL_HANDOFF.md`) supersedes them for current status.
- No legacy `.git` directories, credential dumps, or password hashes were ever copied into this repository (verified — the legacy admin account and its bcrypt hash live only in the owner's original archive, outside this repo, and were never imported).

## 20. Deployment requirements / what still needs credentials

| Needed | Unlocks |
|---|---|
| Real hosting (FTP/cPanel/SSH access reachable from wherever the deploy is run) | Actually going live — this environment could not reach any hosting control panel; deployment must be run by someone with real network access, see `START_HERE.md`-style instructions already given to the project owner separately |
| `APP_ENCRYPTION_KEY` (generate with `php -r "echo bin2hex(random_bytes(32));"`, once, keep it forever) | Storing SMM provider API keys at rest |
| Real SMM/API provider account + API docs | Turning the provider architecture (§10) from infrastructure into a working integration |
| Payment gateway account + webhook secret | Online card/wallet-top-up payment (manual + internal wallet already work without this) |
| SMTP credentials | Password-reset emails actually being sent |
| Final price list | Replacing "price on request" placeholders — all seeded service prices are intentionally `0.00`/"حسب الطلب"; this is the original project's own seed data, not something invented or broken here |

`.env.example` in the repo root lists every environment variable the application actually reads (verified against source, not copied from an old template) with no real values.

## 21. Architecture map — key files and what they own

```
db_connect.php          single DB connection point + .env loader + e()/fetch_all()/fetch_one() helpers
bootstrap.php            first-run schema creator from database.sql; refuses a non-empty DB
migrate.php               applies migrations/*.sql in order; blocks destructive statements + drift
tools/install.php         one-command install: bootstrap → migrate → create first admin
staging_check.php         CLI-only preflight; refuses an elawaady.com APP_URL

lib/auth.php              storefront session/CSRF/password primitives
lib/admin_auth.php        (via admin/auth.php) admin session + RBAC gate
lib/checkout.php          atomic order+wallet+payment transaction
lib/checkout_intent.php   idempotency token issuance/consumption
lib/pricing.php           server-side price resolution (never trusts client input)
lib/wallet.php            immutable ledger operations
lib/mediation.php         escrow hold/release/refund operations
lib/media.php             image path/fallback resolution
lib/notify.php            in-app notifications table: write side wired in, read side unused by any page (§14)

sections.php              homepage_sections renderer — the whole homepage's composition
banner.php                flexible-ratio banner component (store_section_banners)
index.php / categories.php / subcategories.php / service.php / search.php / contact.php
                           original 6 storefront routes, now reading the fuller schema
account.php / cart.php / order_create.php / order-success.php / order-track.php
mediation.php / page.php  storefront mediation + CMS page renderer

admin/                    26-page dashboard, see §14
migrations/001–024        additive schema history, see §3
database.sql               original bootstrap-only schema+seed (never run against a populated DB)
```

## 22. Concrete next step for the incoming programmer

1. Read `DEPLOY.md` (Arabic, staging-oriented) and `STAGING_READINESS.md` (English, CI-gate-oriented) before touching hosting — both encode hard-won safety rules, not suggestions.
2. Get real hosting credentials reachable from wherever you're running the deploy, then run `php tools/install.php --admin=owner` exactly as documented — it has been tested end-to-end against a throwaway database and works.
3. The single highest-leverage next feature is a real payment gateway integration (§20) — everything else (wallet, mediation, orders) already assumes a payment layer exists underneath it.
4. Second priority: a real SMM provider account to turn §10 from infrastructure into an actual integration — the schema, encryption, and admin UI are already there waiting for one.
5. Do not delete `backend/` or the `EXD_*`/`LEGACY_*`/`DASHBOARD_RECOVERY_PLAN.md` docs — they are the project's own memory of decisions already made; re-deciding them wastes the owner's time re-explaining.
