# Elawaady XDigital Store Build Status

Branch: `chatgpt/store-build`

## Verified progress
- Pulled the latest Gemini backend package from the connected Google Drive.
- Reproduced the package locally and ran the full automated suite.
- Found workspace-specific hard-coded `/working_dir/...` paths that break portability outside Gemini's environment.
- Replaced migration/storage paths with project-relative or environment-driven paths.
- Split production database initialization away from the staging seeder so production setup does not create staging credentials/balances.
- Re-ran the suite: **52 tests passed**.
- Started porting the corrected backend into this branch for integration with the existing repository.
- Added a safe `backend/.env.example` for Namecheap staging using MySQL and staging-only CORS.
- Added `backend/production_check.py` to fail fast if SQLite, placeholder secrets, missing DB credentials, or live `elawaady.com` CORS are used during staging.
- Hardened the staging preflight so deployment now also fails if the required backend source modules, SQL migrations/schema, test suite, or Python runtime dependencies are missing.
- Added root `staging_check.php`, a CLI-only storefront preflight that validates staging environment variables, required PHP files, the `mysqli` extension, and rejects an `APP_URL` pointing at live `elawaady.com`; it performs no database connection or mutation.

## Safety rules
- Do not modify the current live `elawaady.com` deployment during staging work.
- External providers and payment gateways remain sandbox/manual until real API documentation and hosting-side secrets are configured.
- Secrets must stay in hosting environment variables and must not be committed to GitHub.
- Run `php staging_check.php` and `python backend/production_check.py` with staging environment variables before any Passenger restart or staging cutover.

## Current blocker
The branch still contains only the deployment entry files from the corrected Python package. The complete `src/`, `database/` migrations/schema, and `tests/` tree still need to be imported before the Passenger app can boot from GitHub alone. The new preflight will intentionally block deployment until those files exist.

## Next build target
Import the complete corrected Python backend source, schema and tests into `backend/`, run the suite from the repository branch, then wire the storefront/admin UI to the backend API before staging deployment on `e-network.net`.
