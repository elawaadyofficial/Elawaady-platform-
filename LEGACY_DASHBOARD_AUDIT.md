# EXD — Legacy Dashboard Audit (verified facts)

Read-only audit of the owner-supplied archives. Nothing here was executed; no SQL
was run against any database; no archive contents were copied into this
repository. Companion to `DASHBOARD_RECOVERY_PLAN.md`, which holds the merge
*strategy*; this file holds the *measured evidence* behind it.

Audited 2026-08-30 from Google Drive folder "داش بورد القديم".

| Archive | SHA-256 | Bytes | Entries |
|---|---|---|---|
| `Dashboard.zip` | `ba9dde3391c559eecaee202895e332c35f6b32a65e8290de4b12821121f9ba40` | 4,096,791 | 1,195 |
| `Elawaady-platform--main.zip` | `6ba90bf27ae7a584221d7d830471cc8adc4db42391fa635f311fee12fba3c081` | 17,728 | 14 |
| `elawaady-xdigital-store.zip` | `96f1d5f247b7640a1088a3baf83176b79e1b2d350622a2e0059f66a951170131` | 16,743 | 13 |

`Dashboard.zip` holds 28 real files; the remaining ~1,167 entries are an embedded
`.git/` directory and macOS metadata. The two small archives are older snapshots
of this repository's own storefront — this branch is ahead of both. Nothing in
them needs recovering.

---

## 1. What the legacy dashboard actually runs on

9,205 lines across 23 PHP/CSS/JS files. Only **five** files touch the database:

| Page | Lines | DB? | Tables |
|---|---|---|---|
| `pages/add_service.php` | 742 | yes | services, categories, service_gallery |
| `pages/categories.php` | 366 | yes | categories |
| `pages/services.php` | 292 | yes | categories, services |
| `auth/login.php` | 137 | yes | users |
| `auth/fix_pass.php` | 7 | yes | users |
| `pages/supplier-services.php` | 598 | **no** | — |
| `pages/carousel.php` | 496 | **no** | — |
| `pages/suppliers.php` | 462 | **no** | — |
| `pages/merchants.php` | 416 | **no** | — |
| `pages/merchant-profile.php` | 394 | **no** | — |
| `pages/merchant-orders.php` | 384 | **no** | — |
| `pages/orders.php` | 375 | **no** | — |
| `pages/supplier-profile.php` | 374 | **no** | — |
| `index.php` | 367 | **no** | — |
| `pages/settings.php` | 362 | **no** | — |
| `pages/supplier-earnings.php` | 294 | **no** | — |
| `pages/supplier-dashboard.php` | 257 | **no** | — |
| `pages/merchant-catalog.php` | 219 | **no** | — |
| `pages/chatbot.php` | 121 | **no** | — |
| `pages/permissions.php` | 100 | **no** | — |
| `auth/register.php` | 512 | **no** | — |

**The catalog admin is real software. Orders, merchants, suppliers, permissions,
settings, carousel, chatbot and registration are front-end mockups with
hard-coded demo rows and no persistence.** Any plan that treats them as working
features to "reconnect" is wrong; they are UI specifications to build behind.

## 2. Legacy schema — 4 tables

`config/elawaady.sql` is a phpMyAdmin dump (MySQL 8.0.40, generated 2026-05-25).
It contains **no** `DROP`, `TRUNCATE` or `DELETE` statements.

- `categories` — 10 cols. Self-referencing `parent_id`; bilingual `name_ar` /
  `name_en`; `slug`; `sort_order`; `status`. Seeded with 6 demo rows only.
- `services` — **79 cols** (detailed below).
- `service_gallery` — `service_id`, `image_path`, `sort_order`.
- `users` — `username`, `password` (bcrypt), `email`. **One row.**

No tables exist for orders, merchants, suppliers, permissions, settings,
carousel, chatbot or media. This matches §1: those modules were never persisted.

### `services` — the real business model

This single table encodes most of the platform brief, and is the most valuable
thing in the archive:

- **Identity** `name_ar` `name_en` `slug` `internal_code` `sort_order`
- **State** `status` (نشط/غير نشط/مخفي/قيد المراجعة) · `is_active`
- **Type** `service_type` (داخلي/مورّد/وساطة/منتج رقمي/اشتراك/شحن ألعاب/عرض خاص)
- **Merchandising** `badge` (7 values) · `show_home` `show_offers` `show_slider`
- **Taxonomy** `category_id` `subcategory_id`
- **Pricing** `price` `old_price` `currency` (EGP/USD/SAR/AED) `supplier_cost`
  `platform_commission` `marketer_commission` `show_price` `ask_price`
- **Content** `short_desc` `full_desc` `features` `requirements` `terms`
  `refund_policy` `delivery_time` `important_note` `internal_notes`
- **Per-service theming** 7 colour fields + 2 gradient fields
- **Media** `image_main` `image_icon` `image_banner` `image_url` + gallery table
- **Ordering** `order_method` (واتساب/تليجرام بوت/محادثة داخلية/نموذج طلب/دفع
  مباشر/وساطة) `order_link` `whatsapp` `telegram_bot` `requires_approval`
  `requires_prepay` `enable_buy_now` `enable_cart`
- **Mediation / escrow** `mediation_enabled` `mediation_type` (7 values)
  `mediation_fee` `mediator_commission` `mediator_phone`
  `mediation_whatsapp_group` `emergency_phone` `show_mediation_terms`
- **Supplier** `supplier_name` `supplier_contact` `supplier_priority` `show_supplier`
- **SEO** `seo_title` `seo_desc` `seo_keywords` `noindex` `in_sitemap`

This repository's `store_services` has 12 columns and covers none of the type,
mediation, supplier, commission, ordering, theming or SEO dimensions.

## 3. Recoverable prototype pages inside the embedded `.git`

The archive's `.git/` is not junk: it carries 30 commits of the HTML dashboard
prototype, including pages **deleted before the final commit** and absent from
the published prototype. They are recoverable from the git objects and are
marked **preserve until reviewed**:

`escrow_chat.html` (mediation chat) · `users.html` · `admins.html` ·
`vendors.html` · `providers.html` · `dashboard.html` · `order-view.html` ·
`scraping.html` · `smm_services.html` · `smm_compare.html` ·
`social_links.html` · `home_sections.html` · `testimonials.html` ·
`chat.html` · `profile.html` · `side_menu.html` · plus
`assets/css/pages.css`, `assets/script/components.js`,
`assets/script/pages-script.js`.

`escrow_chat.html` matters most — mediation is a core EXD offering with no
design anywhere else.

## 4. Security findings — blocking

1. **`auth/fix_pass.php` is an unauthenticated admin-password reset endpoint**
   with the replacement password written in clear text in the source. It must
   never be ported, and the credential it sets must be rotated wherever it was
   ever deployed. Not reproduced here.
2. `config/db.php` hard-codes host/user/password and prints the raw PDO
   exception on failure. This repository's `db_connect.php` already does this
   correctly (environment variables, suppressed error detail in production) —
   keep the current file, do not port the legacy one.
3. The SQL dump contains one real admin account (username + bcrypt hash +
   address). Treat the dump as sensitive; never commit it here.
4. No API keys or tokens were found anywhere else in the archive.
5. `login.php` uses a prepared statement and `password_verify` — sound. It has
   no rate limiting, no CSRF token and no session regeneration; all three are
   required before exposure.

## 5. Data safety

The legacy dump's category seed is 6 demo rows, not production data. It is a
**development snapshot**, not a backup of the live store. It must not be treated
as a source of catalogue truth, and must never be loaded into a database holding
real data.
