# Elawaady XDigital — Staging Readiness Checklist

This checklist applies to `chatgpt/store-build` only. It is intentionally designed to protect the live `elawaady.com` store from accidental staging or deployment activity.

## Safety boundary

- [ ] Work is performed only on `chatgpt/store-build` (or a dedicated non-production branch).
- [ ] `main` is not modified as part of staging preparation.
- [ ] `APP_URL` does not equal `https://elawaady.com` or any production alias.
- [ ] No production database credentials are used in CI or staging.
- [ ] No production deploy key, production FTP account, or production host is referenced.
- [ ] No destructive SQL or schema reset is executed.

## Storefront validation

- [ ] GitHub Actions `Storefront Safety` workflow passes.
- [ ] All root PHP files pass syntax lint.
- [ ] `php staging_check.php` passes with staging-only environment values.
- [ ] The preflight guard fails when `APP_URL=https://elawaady.com` (expected safety behavior).
- [ ] Existing storefront routes render without PHP fatal errors.
- [ ] Media slots gracefully fall back when `store_services.image` is empty or invalid.
- [ ] Responsive behavior is checked on desktop, tablet, and mobile before visual sign-off.

## Architecture preservation

- [ ] Existing EXD storefront functionality is preserved before visual replacement.
- [ ] Old store / dashboard assets are inventoried before large UI rewrites.
- [ ] New homepage sections are added incrementally rather than replacing legacy flows in one step.
- [ ] Existing service/category/order/payment behavior is treated as preserve-first until reviewed.
- [ ] New CSS layers remain additive and avoid destructive global overrides.

## Data and backend readiness

- [ ] Backend package is complete (`src/`, database/migrations, tests, production checks).
- [ ] Backend safety workflow passes before any Storefront ↔ API staging integration.
- [ ] Database changes, if required, are migration-based and reversible.
- [ ] Staging uses isolated database credentials and isolated data.
- [ ] No schema import is executed automatically during deployment.

## Deployment readiness

- [ ] Staging host is separate from `elawaady.com`.
- [ ] Staging environment variables are documented and loaded outside source control.
- [ ] A rollback point (commit SHA + database backup/migration state) is recorded before deployment.
- [ ] Deployment is manual or environment-gated until staging validation is complete.
- [ ] Health checks pass after staging deployment.
- [ ] Visual QA and functional QA are completed before any production proposal.

## Current known blockers

1. The Python backend is still incomplete in this branch; the backend production safety workflow must not be treated as deploy-ready until `src/`, migrations/database assets, and tests are present and passing.
2. Original old-store / dashboard assets and the final font package are still required before any major visual rebuild that claims fidelity to the legacy EXD identity.
3. Production deployment is intentionally out of scope until staging validation and explicit approval.

## Next concrete action

Complete the missing backend package and make the `Backend production safety gate` pass on `chatgpt/store-build`, then wire Storefront ↔ API against isolated staging configuration only.
