# Elawaady XDigital — Staging Deployment Checklist

This branch is for staging/build work only. Do not deploy it over the live `elawaady.com` store until a separate reviewed production release is explicitly approved.

## Safety Boundary

- Use `APP_ENV=development` or `APP_ENV=staging` for installer/rehearsal work. `tools/install.php` and `tools/staging_rehearsal.sh` deliberately refuse production mode.
- Never point staging/install/rehearsal tooling at `elawaady.com` or any `*.elawaady.com` host.
- `tools/staging_rehearsal.sh` is intentionally loopback-only (`127.0.0.1`, `localhost`, or `::1`) and must remain that way.
- No force-push, reset, direct production deployment, production database access, or production migration from `chatgpt/store-build`.
- Never commit `.env`, database dumps, API keys, payment secrets, webhook secrets, or real credentials.

## Required Runtime

- PHP 8+.
- Required PHP extensions: `mysqli`, `mbstring`, `openssl`, `curl`, and `fileinfo`.
- MySQL 8 / MariaDB 10.6+.
- Supply `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` through environment variables.
- Supply a persistent 64-character hexadecimal `APP_ENCRYPTION_KEY` outside source control.
- Use a dedicated least-privilege database user for a real staging environment.

## Preflight

Before any staging handoff, run the read-only checks first:

```bash
php tools/preflight.php
php tools/migration_preflight.php
```

Both must pass before installation or migrations are attempted.

## Isolated Deployment Rehearsal

Run the complete rehearsal only against a disposable database and loopback URL. Example values below are placeholders, not production credentials:

```bash
export APP_ENV=staging
export APP_URL=http://127.0.0.1:8080
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=elawaady_store_rehearsal
export DB_USER=elawaady_rehearsal
export DB_PASS='replace-with-disposable-db-password'
export ADMIN_PASS='replace-with-disposable-admin-password'
export APP_ENCRYPTION_KEY='replace-with-64-hex-character-test-key'

bash tools/staging_rehearsal.sh
```

The rehearsal must complete all of the following without contacting the public store:

- PHP runtime preflight.
- Offline migration safety preflight.
- Install into the disposable database.
- `migrate.php --status` with `Pending: 0`.
- `migrate.php --dry-run` returning no pending work.
- Re-running `migrate.php` as an idempotent no-op.
- Local HTTP storefront smoke test.
- Server log check with no fatal/parse/uncaught PHP errors.

## Database

- Import/bootstrap only into the intended staging or disposable database.
- Verify `utf8mb4` charset/collation.
- Test category, service, search, account, order, wallet, supplier, and mediation queries against staging data.
- Treat migration checksum drift as a release blocker; do not edit an already-applied migration to make drift disappear.
- Take and verify a backup before any future production migration. Production migration is a separate release activity and is not performed from this branch.

## Storefront QA

Test at minimum: 360px, 390px, 430px, 768px, 1024px, and 1440px+.

Verify:

- RTL layout and Arabic typography.
- Hero autoplay, swipe, arrows, dots, and reduced-motion behavior.
- Category drag/scroll carousel.
- Product cards and service links.
- Search form.
- Header mobile menu.
- FAQ interactions.
- Payment/logo marquee.
- Images/video slots without stretching.
- Keyboard navigation and visible focus states.

## Performance

- Convert large artwork to WebP/AVIF where practical.
- Lazy-load below-the-fold images/video.
- Keep decorative animation on transform/opacity paths.
- Avoid autoplaying heavy MP4 assets on low-bandwidth mobile connections.
- Check Lighthouse/Core Web Vitals on the staging URL before release.

## Security / Release Gate

- `display_errors` must be off for a future production release.
- Confirm database errors do not expose credentials or server details.
- Validate all user-controlled IDs and form inputs before write operations are enabled.
- Require CSRF protection on authenticated/admin write forms before production.
- Confirm session cookie flags (`Secure`, `HttpOnly`, `SameSite`) for HTTPS environments.
- Verify payment/webhook signatures server-side when gateways are connected.
- Require successful GitHub checks for the exact candidate SHA before handoff.
- Branch protection/required checks must be enabled before treating the branch as merge-ready.

## Release Process

1. Re-read the latest `chatgpt/store-build` HEAD and confirm the intended SHA.
2. Confirm all GitHub checks for that SHA are green.
3. Run the isolated staging rehearsal against a disposable database.
4. Deploy the candidate to a non-live staging host using staging-only credentials.
5. Run storefront, authenticated-flow, database, security, and performance QA on staging.
6. Review the diff against the intended release branch through a reviewed PR.
7. Confirm branch protection and required checks are active before merge.
8. Prepare a separate production change plan, backup/rollback plan, maintenance window, and approval.
9. Only then schedule production release separately; never repurpose the staging rehearsal to target the live store.
