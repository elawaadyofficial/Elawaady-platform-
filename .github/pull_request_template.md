## Elawaady XDigital release review

### Scope
- [ ] This PR is limited to the intended storefront/staging work.
- [ ] No direct changes target the live `elawaady.com` deployment.
- [ ] No production database credentials, dumps, API keys, payment secrets, webhook secrets, or `.env` files are included.

### Required CI gates
- [ ] **Storefront Safety** passes on the final commit.
- [ ] **Homepage Contract** passes when homepage composition files are changed.
- [ ] **Migration Smoke** passes when storefront migrations are changed.

### Database safety
- [ ] Schema changes are additive/non-destructive unless separately reviewed and explicitly approved.
- [ ] Migrations were validated against CI/staging data only; no migration was executed against the live production database from this branch.
- [ ] A rollback/backup plan exists before any future production migration.

### Staging validation
- [ ] `DEPLOYMENT_CHECKLIST.md` was reviewed for this change.
- [ ] Deployment target is staging first.
- [ ] Critical storefront flows were smoke-tested on staging before any production-release decision.

### Known release blockers
Document unresolved blockers here. A PR with unresolved production-readiness blockers may be merged for continued staging development, but must not be treated as production-ready.

- 
