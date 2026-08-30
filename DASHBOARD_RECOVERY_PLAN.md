# EXD Legacy Dashboard Recovery & Merge Plan

Status: audit baseline only. No legacy dashboard code has been executed, imported, or connected to production.

## Source audited

The user-provided `Dashboard.zip` recovery bundle was inspected read-only before any merge.

After excluding embedded `.git/` data, macOS metadata (`__MACOSX`, `.DS_Store`) and directories, the bundle contains:

- 30 real project files
- 24 PHP files
- 17 dashboard page modules
- 1 CSS file
- 1 JavaScript file
- legacy auth/config/includes/assets directories
- `config/elawaady.sql`
- `config/db.php`

The original archive contains 1,195 ZIP entries largely because it includes an embedded Git repository and macOS metadata. Those entries must never be copied wholesale into this repository.

## Legacy page inventory

The recovered dashboard currently exposes these page modules:

- `add_service.php`
- `carousel.php`
- `categories.php`
- `chatbot.php`
- `merchant-catalog.php`
- `merchant-orders.php`
- `merchant-profile.php`
- `merchants.php`
- `orders.php`
- `permissions.php`
- `services.php`
- `settings.php`
- `supplier-dashboard.php`
- `supplier-earnings.php`
- `supplier-profile.php`
- `supplier-services.php`
- `suppliers.php`

Additional recovered application files include `index.php`, auth pages, navigation/header includes, assets, DB config, and the SQL dump.

## Database findings

The legacy SQL dump creates only four tables:

- `categories`
- `service_gallery`
- `services`
- `users`

This is materially smaller than the functional dashboard surface. The presence of order, merchant, supplier, permissions, chatbot, settings and carousel pages does **not** prove that the supplied SQL dump contains the complete schema needed by those modules.

Therefore the legacy SQL is a recovery reference, not a production-ready migration.

## Security findings before merge

1. `config/db.php` uses hard-coded local database credentials. It must not be copied as-is. Runtime database credentials must come from environment/configuration outside source control.
2. The legacy connection error path prints the raw PDO exception. Production/staging code must not disclose DB connection details to users.
3. The archive contains an embedded `.git/` directory. It must be excluded from every import/recovery operation.
4. No SQL from the recovery bundle may be executed against `elawaady.com` or any production database.
5. Legacy auth/session/permissions behavior requires review before reuse.

## Merge strategy

The recovered dashboard is a **legacy source of truth for features and workflows**, not a drop-in replacement for the current repository.

Use this sequence:

1. Preserve current `chatgpt/store-build` as the integration baseline.
2. Extract legacy code into an isolated local review directory only.
3. Build a module-to-data contract for every legacy page.
4. Compare each required table/column with the current `database.sql` and backend schema.
5. Resolve authentication, role and permission semantics before exposing admin routes.
6. Port modules incrementally behind staging-only access.
7. Replace hard-coded credentials and unsafe error output before first staging execution.
8. Add schema changes only as additive, reviewable migrations; never destructive imports.
9. Verify each migrated module with PHP lint, authorization checks and staging data before enabling the next module.

## Initial module priority

### Gate 1 — shared admin foundation

- authentication/session handling
- role and permission model
- environment-backed DB connection
- shared header/navigation/layout
- CSRF and authorization checks for write actions

### Gate 2 — catalog foundation

- categories
- services
- service gallery
- add/edit service flow

This gate is the first required connection point to the Storefront because product media, prices, badges and service data should have a clear admin source instead of becoming hard-coded UI data.

### Gate 3 — commerce operations

- orders
- merchant catalog/orders/profile
- supplier services/earnings/profile/dashboard

Do not begin this gate until the required order/merchant/supplier schema is explicitly reconstructed and tested; it is not fully represented by the supplied legacy SQL dump.

### Gate 4 — platform operations

- carousel
- settings
- chatbot
- remaining permissions/administrative controls

## Storefront ownership boundary

During active storefront implementation by the other frontend agent, recovery work should avoid editing shared storefront files (`index.php`, `service.php`, header/footer, storefront CSS/JS, tokens, motion and media styles) unless the ownership split is explicitly changed.

Admin/backend recovery may proceed independently through documentation, schema contracts, migrations, tests, CI and isolated admin modules.

## Production safety rule

Nothing in this recovery plan authorizes changes to the live `elawaady.com` store. Production remains untouched until staging passes the existing safety gates, the reconstructed schema is reviewed, and a rollback path exists.

## Next concrete action

Create a machine-readable legacy dashboard module/data matrix from the recovered PHP files and compare its referenced tables/columns against the current repository `database.sql`. The output should identify, per module, whether its data dependencies are present, missing, ambiguous or unsafe before any legacy code is imported.
