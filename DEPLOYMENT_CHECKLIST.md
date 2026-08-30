# Elawaady XDigital — Staging Deployment Checklist

This branch is for staging/build work only. Do not deploy it over the live `elawaady.com` store until every item below is verified.

## Environment

- Set `APP_ENV=production` only on the target staging/production host.
- Supply `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` through host environment variables.
- Never commit `.env`, local config files, database dumps, API keys, payment secrets, or webhook secrets.
- Confirm PHP has `mysqli` and `mbstring` enabled.
- Use HTTPS and force secure cookies once authentication is connected.

## Database

- Import the intended schema into a staging database first.
- Use a dedicated least-privilege MySQL user for the application.
- Verify `utf8mb4` charset/collation.
- Test category, service, search, account, and order queries against staging data.
- Take a backup before any future production migration.

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

- `display_errors` must be off in production.
- Confirm database errors do not expose credentials or server details.
- Validate all user-controlled IDs and form inputs before write operations are enabled.
- Add CSRF protection to authenticated/admin write forms before production.
- Confirm session cookie flags (`Secure`, `HttpOnly`, `SameSite`).
- Verify payment/webhook signatures server-side when gateways are connected.
- No force-push, reset, or direct production deployment from `chatgpt/store-build`.

## Release Process

1. Re-read latest `chatgpt/store-build` HEAD.
2. Run staging QA and database backup.
3. Review diff against the intended release branch.
4. Merge through a reviewed PR.
5. Deploy to staging first.
6. Smoke-test critical flows.
7. Only then schedule production release separately.
