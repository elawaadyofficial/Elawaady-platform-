# Elawaady XDigital — Staging Readiness

This document applies to `chatgpt/store-build` only. It is a validation and staging-preparation guide, not a production deployment instruction. The live `elawaady.com` store must remain untouched unless a separate reviewed production release is explicitly approved.

## Safety boundary

- Work only on `chatgpt/store-build` or another dedicated non-production branch.
- Do not push staging/build changes directly to `main`.
- `APP_URL` must not equal `https://elawaady.com` or any production alias during CI or staging validation.
- Never use production database credentials, deploy keys, FTP credentials, payment secrets, webhook secrets, or production host access in CI.
- Never run destructive SQL (`DROP`, `TRUNCATE`, schema reset, or destructive data cleanup) as part of staging readiness.
- `chatgpt/store-build` must never deploy directly to production.

## Required CI gates

A staging candidate is valid only when the `Staging Readiness` workflow accepts successful applicable runs for all of these workflows:

1. `Storefront Safety`
2. `Homepage Contract`
3. `Migration Smoke`
4. `Catalog Visual Contract`
5. `Backend production safety gate`
6. `Auth Integration`
7. `Order Wallet Integration`
8. `Platform Integration`

`Staging Readiness` is validation-only. It does not deploy anything.

The candidate must also preserve the production-isolation assertions embedded in the integration workflows. A failure of any required workflow on the candidate commit blocks staging readiness.

## Database readiness

- Build and integration tests must work from an isolated empty/local MariaDB database using repository migrations and CI fixtures only.
- Every schema change must be migration-based and additive unless a separately reviewed migration plan explicitly documents otherwise.
- No production schema import or production data dump is required for CI correctness.
- Before a real staging deployment, record the exact migration state and take a staging database backup.
- Before any future production migration, take and verify a production backup separately from this branch workflow.

## Application readiness

The staging candidate must preserve and test the critical flows already covered by CI, including:

- authentication and session handling;
- storefront/category/service routing;
- dashboard/account flows covered by the platform integration suite;
- order creation and status history;
- wallet ledger posting and reconciliation;
- atomic wallet checkout;
- checkout idempotency and browser double-submit replay protection;
- supplier/mediation integration paths covered by the platform suite;
- migration and storefront safety contracts.

Any new payment gateway or webhook integration remains blocked from production until server-side signature verification, replay protection, failure handling, and isolated staging tests are present.

## Staging environment requirements

Load environment configuration outside source control. At minimum document and provide staging-only values for:

- `APP_ENV`
- `APP_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

PHP must provide the extensions required by the application and CI, including `mysqli` and `mbstring` where used. Staging must use HTTPS before authentication/payment acceptance is considered release-ready, with secure session cookie settings (`Secure`, `HttpOnly`, appropriate `SameSite`).

## Manual staging QA

After deployment to an isolated staging host, verify at minimum:

- login/logout/session persistence;
- category → service → checkout navigation;
- one successful wallet-funded order;
- a deliberate repeated checkout submission does not duplicate the order or debit;
- account/order history reflects the transaction once;
- Arabic RTL layout and responsive behavior at 360px, 390px, 430px, 768px, 1024px, and 1440px+;
- search, menus, FAQ interactions, media fallbacks, keyboard navigation, and visible focus states;
- PHP errors and database credentials are not exposed to the browser;
- staging health check and critical routes return expected status codes.

## Rollback record

Before each staging deployment, record all of the following together:

- candidate commit SHA;
- previous known-good staging commit SHA;
- database backup identifier/location;
- last applied migration;
- deployment timestamp;
- operator/reviewer;
- any environment-variable changes made for the release.

If post-deploy health checks or critical smoke tests fail:

1. stop further rollout;
2. restore the previous known-good application commit;
3. restore the staging database backup only if the failed release changed data/schema in a way that cannot safely run against the previous application;
4. rerun health checks and critical checkout/auth smoke tests;
5. document the failure before preparing another candidate.

Do not attempt an automatic production rollback from `chatgpt/store-build`.

## GitHub branch protection gap

Repository branch protection is not currently enforced for `chatgpt/store-build`. Until required checks/rulesets are configured at GitHub repository level, `Staging Readiness` is the repository's explicit release gate but cannot prevent a privileged actor from bypassing it manually.

Recommended protected-branch requirements for a future release branch are the eight CI workflows listed above plus reviewed pull requests and prohibition of force pushes.

## Release path

1. Confirm the candidate SHA on `chatgpt/store-build`.
2. Confirm `Staging Readiness` is green for that candidate or an explicitly validated descendant under its workflow rules.
3. Review the diff against the intended release branch through a pull request.
4. Deploy only to an isolated staging environment.
5. Run manual staging QA and record the rollback point.
6. Fix regressions on the build branch and repeat the gate if needed.
7. Prepare production as a separate reviewed release after staging sign-off.

## Current blocker

The code-level staging gate is operational, but GitHub branch protection/required status checks are not enforced on `chatgpt/store-build`. Production deployment remains intentionally out of scope.

## Next concrete action

Add a machine-readable staging release manifest/check that records the candidate SHA and validates the documented required workflow set before a staging handoff, without adding any deployment capability or production credentials.
