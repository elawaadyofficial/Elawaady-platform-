# Elawaady XDigital — Branch Protection Runbook

This runbook applies to `chatgpt/store-build` only. It does not authorize deployment to `elawaady.com`, production database access, or a production release. The repository governance contract keeps `production_deploy_allowed=false`.

## Purpose

Turn the branch from CI-ready into merge-ready by enabling GitHub branch protection with the exact status-check contexts already validated by the repository test suite.

## Required status checks

Configure these exact GitHub check contexts as required before merge:

1. `safety`
   - Workflow: `Backend production safety gate`
   - File: `.github/workflows/backend-safety.yml`
2. `PHP storefront syntax and staging preflight`
   - Workflow: `Storefront Safety`
   - File: `.github/workflows/storefront-safety.yml`
3. `Authentication, dashboard, orders, suppliers and mediation`
   - Workflow: `Platform Integration`
   - File: `.github/workflows/platform-integration.yml`
4. `validate-staging-configuration`
   - Workflow: `Staging Configuration Contract`
   - File: `.github/workflows/staging-configuration-contract.yml`

The canonical source for these names is `config/repository-governance-contract.json`. Do not rename a required job without updating the contract and passing `tests/branch_protection_check_names.php`.

## Recommended protection settings

For `chatgpt/store-build`:

- Require a pull request before merging.
- Require the four status checks above to pass before merging.
- Require branches to be up to date before merging when GitHub permits this with the selected checks.
- Block force pushes.
- Block branch deletion.
- Require conversation resolution before merging.
- Do not allow bypass for ordinary contributors.
- Keep direct production deployment disabled; branch protection is a source-control gate, not a deployment approval.

If repository policy later requires signed commits or multiple approving reviews, add those as a separate governance change rather than silently changing this runbook.

## Verification after enabling

After the GitHub setting is changed, verify all of the following:

1. The branch reports `protected: true` through GitHub's branch API.
2. Required status-check enforcement is enabled rather than `off`.
3. The four required check contexts match the exact names above.
4. A test pull request cannot merge while any required check is pending or failing.
5. A green candidate SHA can merge only through the protected review path.
6. `config/repository-governance-contract.json` still contains `production_deploy_allowed: false`.

Example read-only verification endpoint:

```text
GET /repos/elawaadyofficial/Elawaady-platform-/branches/chatgpt/store-build
```

Expected protection state after configuration:

```text
protected = true
required_status_checks.enforcement_level != off
```

## Failure handling

If GitHub refuses one of the required contexts because it has not been observed recently, run the corresponding workflow on the branch, confirm the check name from the resulting check run, and retry. Do not weaken or remove a safety check merely to make branch protection configurable.

If a workflow job is intentionally renamed, first update the governance contract and its regression test on this build branch, wait for CI to pass, then update the GitHub protection context in the same reviewed change window.

## Merge-readiness definition

`chatgpt/store-build` is source-control merge-ready only when:

- all repository CI is green on the intended HEAD;
- GitHub reports the branch as protected;
- the four required checks above are enforced;
- the staging rehearsal remains isolated and loopback-only;
- no production credentials, live database access, or live deployment step has been introduced.

Production readiness remains a separate release decision with its own staging QA, backup/rollback plan, approval, and deployment procedure.
