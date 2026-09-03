#!/usr/bin/env bash
set -euo pipefail

# Safe, repeatable deployment rehearsal for disposable development/staging only.
# It never targets the public store and never performs external HTTP requests.

fail() {
  printf 'staging rehearsal failed: %s\n' "$1" >&2
  exit 1
}

: "${APP_ENV:?APP_ENV is required}"
: "${APP_URL:?APP_URL is required}"
: "${ADMIN_PASS:?ADMIN_PASS is required}"

case "$APP_ENV" in
  development|staging) ;;
  *) fail "APP_ENV must be development or staging" ;;
esac

php -r '
$url = getenv("APP_URL") ?: "";
$parts = parse_url($url);
if (!is_array($parts) || !isset($parts["host"])) {
    fwrite(STDERR, "staging rehearsal failed: APP_URL must be an absolute URL\n");
    exit(1);
}
$host = strtolower(rtrim((string) $parts["host"], "."));
if ($host === "elawaady.com" || str_ends_with($host, ".elawaady.com")) {
    fwrite(STDERR, "staging rehearsal failed: live elawaady.com domain is forbidden\n");
    exit(1);
}
if (!in_array($host, ["127.0.0.1", "localhost", "::1"], true)) {
    fwrite(STDERR, "staging rehearsal failed: APP_URL must use a loopback host\n");
    exit(1);
}
' || exit 1

printf '%s\n' '==> PHP runtime preflight'
php tools/preflight.php

printf '%s\n' '==> Offline migration preflight'
php tools/migration_preflight.php

printf '%s\n' '==> Install into disposable database'
printf '%s\n%s\n' "$ADMIN_PASS" "$ADMIN_PASS" | php tools/install.php --admin=admin

printf '%s\n' '==> Verify migration convergence'
php migrate.php --status | tee /tmp/exd-rehearsal-migration-status.log
grep -Eq 'Pending:[[:space:]]+0' /tmp/exd-rehearsal-migration-status.log

php migrate.php --dry-run | tee /tmp/exd-rehearsal-migration-dry-run.log
grep -Fq 'Database is up to date; nothing to apply.' /tmp/exd-rehearsal-migration-dry-run.log

php migrate.php | tee /tmp/exd-rehearsal-migration-repeat.log
grep -Fq 'Database is up to date; nothing to apply.' /tmp/exd-rehearsal-migration-repeat.log

printf '%s\n' '==> Local HTTP smoke test'
php -S 127.0.0.1:8080 -t . tools/dev-router.php > /tmp/exd-rehearsal-server.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

ready=0
for _ in $(seq 1 30); do
  if curl --fail --silent --show-error --max-time 2 -o /tmp/exd-rehearsal-index.html http://127.0.0.1:8080/index.php; then
    ready=1
    break
  fi
  sleep 1
done

if [[ "$ready" -ne 1 ]]; then
  cat /tmp/exd-rehearsal-server.log >&2 || true
  fail 'local PHP server did not become ready'
fi

if [[ ! -s /tmp/exd-rehearsal-index.html ]]; then
  fail 'storefront smoke response was empty'
fi

if grep -qiE 'Fatal error|Parse error|Uncaught' /tmp/exd-rehearsal-server.log; then
  grep -iE 'Fatal error|Parse error|Uncaught' /tmp/exd-rehearsal-server.log | head -20 >&2
  fail 'PHP error reached the rehearsal server log'
fi

printf '%s\n' 'staging deployment rehearsal: ok (loopback-only; production untouched)'
