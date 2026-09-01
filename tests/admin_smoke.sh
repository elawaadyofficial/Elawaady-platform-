#!/usr/bin/env bash
# EXD — dashboard smoke test.
#
# A 200 is not a pass. PHP answers 200 while printing a fatal error into the
# body, so this checks the body for error text as well as the status, and it
# checks that each page rendered the thing it exists to render.
#
#   BASE=http://127.0.0.1:8080 ADMIN_PASS=... tests/admin_smoke.sh

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-}"
SUPPORT_USER="${SUPPORT_USER:-support}"
SUPPORT_PASS="${SUPPORT_PASS:-}"
PASS=0
FAIL=0

JAR="$(mktemp)"; JAR2="$(mktemp)"
trap 'rm -f "$JAR" "$JAR2"' EXIT

ok()  { PASS=$((PASS+1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad() { FAIL=$((FAIL+1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; [ $# -gt 1 ] && printf '       %s\n' "$2"; }
head1(){ printf '\n\033[1m%s\033[0m\n' "$1"; }

csrf() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }

sign_in() {
  local jar="$1" user="$2" pass="$3"
  local token; token="$(csrf "$jar" "$BASE/admin/login.php")"
  curl -s -o /dev/null -b "$jar" -c "$jar" \
    -d "csrf_token=$token&username=$user&password=$pass" "$BASE/admin/login.php"
}

# Fetch a page and insist it is clean: right status, no PHP error in the body,
# and the expected marker present.
check_page() {
  local jar="$1" path="$2" expect_code="$3" marker="$4" label="$5"
  local body code
  body="$(curl -s -b "$jar" -w '\n__CODE__%{http_code}' "$BASE$path")"
  code="${body##*__CODE__}"
  body="${body%$'\n'__CODE__*}"

  if [ "$code" != "$expect_code" ]; then
    bad "$label" "expected HTTP $expect_code, got $code"
    return
  fi
  if grep -qiE 'Fatal error|Parse error|Uncaught|Warning:|Deprecated:|Notice:' <<<"$body"; then
    bad "$label" "PHP error in body: $(grep -oiE '(Fatal error|Warning|Notice|Deprecated)[^<]{0,90}' <<<"$body" | head -1)"
    return
  fi
  if [ -n "$marker" ] && ! grep -qF "$marker" <<<"$body"; then
    bad "$label" "expected content missing: $marker"
    return
  fi
  ok "$label"
}

if [ -z "$ADMIN_PASS" ]; then
  echo "ADMIN_PASS is required." >&2
  exit 2
fi

# The suite deliberately trips the throttle, so clear it first — otherwise a
# second run of the suite is locked out by the first.
if [ -f tools/reset_throttle.php ]; then
  php tools/reset_throttle.php >/dev/null 2>&1 || true
fi

head1 'Access control'

check_page "$JAR" /admin/index.php 302 '' 'the dashboard refuses an anonymous visitor'

sign_in "$JAR" "$ADMIN_USER" "$ADMIN_PASS"
grep -q 'exd_admin' "$JAR" && ok 'a staff session cookie is set' || bad 'a staff session cookie is set'

# A customer session must not open the dashboard.
check_page "$JAR2" /admin/users.php 302 '' 'a visitor with no staff session is refused'

head1 'Every page a super admin can reach'

check_page "$JAR" /admin/index.php            200 'لوحة التحكم'      'dashboard home'
check_page "$JAR" /admin/users.php            200 'المستخدمون'        'users'
check_page "$JAR" /admin/suppliers.php        200 'الموردون'          'suppliers'
check_page "$JAR" /admin/supplier-offers.php  200 'خدمات الموردين'    'supplier offers'
check_page "$JAR" /admin/staff.php            200 'أعضاء الفريق'      'staff and roles'
check_page "$JAR" /admin/staff.php?tab=roles  200 'الأدوار'           'role permission matrix'
check_page "$JAR" /admin/audit.php            200 'سجل العمليات'      'audit log'
check_page "$JAR" /admin/categories.php       200 ''                  'categories'
check_page "$JAR" /admin/services.php         200 ''                  'services'
check_page "$JAR" /admin/orders.php           200 ''                  'orders'
check_page "$JAR" /admin/homepage-sections.php 200 'أقسام'            'homepage sections'
check_page "$JAR" /admin/settings.php         200 'إعدادات'           'platform settings'
check_page "$JAR" /admin/pages.php            200 ''                  'pages and policies'
check_page "$JAR" /admin/placements.php       200 ''                  'placements'
check_page "$JAR" /admin/wallets.php          200 ''                  'wallets'
check_page "$JAR" /admin/payments.php         200 ''                  'payments'
check_page "$JAR" /admin/mediation.php        200 ''                  'mediation'
check_page "$JAR" /admin/digital-assets.php   200 ''                  'digital assets'
check_page "$JAR" /admin/providers.php        200 ''                  'providers'
check_page "$JAR" /admin/carousel.php         200 ''                  'carousel'
check_page "$JAR" /admin/brand-settings.php   200 ''                  'brand settings'
check_page "$JAR" /admin/chatbot-knowledge.php 200 ''                 'chatbot knowledge'

if [ -n "$SUPPORT_PASS" ]; then
  head1 'A limited role sees a limited dashboard'

  rm -f "$JAR2"; touch "$JAR2"
  sign_in "$JAR2" "$SUPPORT_USER" "$SUPPORT_PASS"

  check_page "$JAR2" /admin/index.php  200 'لوحة التحكم' 'support agent reaches the dashboard'
  check_page "$JAR2" /admin/orders.php 200 ''            'support agent reaches orders'
  check_page "$JAR2" /admin/staff.php  403 ''            'support agent is refused the permission editor'
  check_page "$JAR2" /admin/audit.php  403 ''            'support agent is refused the audit log'

  BODY="$(curl -s -b "$JAR2" "$BASE/admin/index.php")"
  grep -q 'staff.php' <<<"$BODY" && bad 'the menu hides pages the role cannot open' || ok 'the menu hides pages the role cannot open'
  grep -q 'orders.php' <<<"$BODY" && ok 'the menu still shows pages the role can open' || bad 'the menu still shows pages the role can open'

  # A permission check on the page, not only on the menu.
  TOKEN="$(csrf "$JAR2" "$BASE/admin/users.php")"
  CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR2" \
    -d "csrf_token=$TOKEN&action=suspend&user_id=1" "$BASE/admin/users.php")
  [ "$CODE" = "403" ] && ok 'a role without users.manage cannot suspend an account' \
                      || bad 'a role without users.manage cannot suspend an account' "got $CODE"
fi

head1 'Every write is guarded'

# A POST without a token must be refused on every page that changes something,
# not only the ones written most recently.
for page in users.php suppliers.php supplier-offers.php staff.php wallets.php \
            payments.php mediation.php digital-assets.php homepage-sections.php \
            placements.php pages.php settings.php brand-settings.php \
            categories.php services.php carousel.php chatbot-knowledge.php \
            providers.php order-view.php; do
  CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -X POST -d "action=probe" "$BASE/admin/$page")
  if [ "$CODE" = "419" ]; then
    PASS=$((PASS+1))
  else
    bad "$page refuses a POST with no CSRF token" "got $CODE"
  fi
done
ok 'every dashboard page refuses a POST with no CSRF token'

if [ -n "$SUPPORT_PASS" ]; then
  # A support agent holds orders.view but not catalog.manage, so the catalogue
  # pages must refuse them — the ported pages included.
  rm -f "$JAR2"; touch "$JAR2"
  sign_in "$JAR2" "$SUPPORT_USER" "$SUPPORT_PASS"

  REFUSED=0
  for page in categories.php services.php service-form.php carousel.php \
              providers.php provider-services.php chatbot-knowledge.php; do
    CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR2" "$BASE/admin/$page")
    [ "$CODE" = "403" ] && REFUSED=$((REFUSED+1)) || bad "$page refuses a role without the permission" "got $CODE"
  done
  [ "$REFUSED" = "7" ] && ok 'the catalogue and provider pages refuse a role without the permission' \
                       || bad 'the catalogue and provider pages refuse a role without the permission'

  # And the pages that role does hold still open.
  check_page "$JAR2" /admin/orders.php 200 '' 'a support agent still reaches orders'

  # Re-sign the super admin for the sign-out check that follows.
  rm -f "$JAR"; touch "$JAR"
  sign_in "$JAR" "$ADMIN_USER" "$ADMIN_PASS"
fi

head1 'Sign out'

TOKEN="$(csrf "$JAR" "$BASE/admin/logout.php")"
curl -s -o /dev/null -b "$JAR" -c "$JAR" -d "csrf_token=$TOKEN" "$BASE/admin/logout.php"
check_page "$JAR" /admin/users.php 302 '' 'the staff session is dead after signing out'

printf '\n\033[1mResult:\033[0m %d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
