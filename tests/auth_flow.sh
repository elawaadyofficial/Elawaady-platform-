#!/usr/bin/env bash
# EXD — authentication integration test.
#
# Drives the real pages over HTTP with a real cookie jar, so it exercises the
# session cookie, the CSRF token and the database exactly as a browser would.
# A passing PHP syntax check proves none of this.
#
#   BASE=http://127.0.0.1:8080 tests/auth_flow.sh

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
JAR="$(mktemp)"
JAR2="$(mktemp)"
PASS=0
FAIL=0
STAMP="$(date +%s)$RANDOM"
USER_EMAIL="user${STAMP}@exd.test"
SUPP_EMAIL="supplier${STAMP}@exd.test"
PASSWORD="Test1234pass"

cleanup() { rm -f "$JAR" "$JAR2"; }
trap cleanup EXIT

ok()   { PASS=$((PASS+1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad()  { FAIL=$((FAIL+1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; [ $# -gt 1 ] && printf '       %s\n' "$2"; }
head1(){ printf '\n\033[1m%s\033[0m\n' "$1"; }

# Pull the CSRF token out of a rendered form.
csrf() {
  local jar="$1" url="$2"
  curl -s -b "$jar" -c "$jar" "$url" \
    | grep -o 'name="csrf_token" value="[a-f0-9]*"' \
    | head -1 | sed 's/.*value="//; s/"//'
}

status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

# The suite deliberately trips the throttle, so clear it first — otherwise a
# second run of the suite is locked out by the first.
if [ -f tools/reset_throttle.php ]; then
  php tools/reset_throttle.php >/dev/null 2>&1 || true
fi

head1 'Registration'

TOKEN="$(csrf "$JAR" "$BASE/register.php")"
[ -n "$TOKEN" ] && ok 'register form issues a CSRF token' || bad 'register form issues a CSRF token'

# A POST with no token must be refused, not silently accepted.
CODE=$(status -X POST -b "$JAR" -c "$JAR" \
  -d "name=No Token&email=notoken${STAMP}@exd.test&phone=01000000000&password=$PASSWORD&confirm_password=$PASSWORD&agree=1&account_type=user" \
  "$BASE/register.php")
[ "$CODE" = "419" ] && ok 'registration without a CSRF token is refused (419)' \
                    || bad 'registration without a CSRF token is refused' "got $CODE"

# Weak password must be rejected by validation, not by the database.
BODY=$(curl -s -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=Weak Pass&email=weak${STAMP}@exd.test&phone=01000000000&password=short&confirm_password=short&agree=1&account_type=user" \
  "$BASE/register.php")
echo "$BODY" | grep -q '8 أحرف' && ok 'short password is rejected' || bad 'short password is rejected'

# A password with no digit must be rejected.
TOKEN="$(csrf "$JAR" "$BASE/register.php")"
BODY=$(curl -s -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=No Digit&email=nodigit${STAMP}@exd.test&phone=01000000000&password=abcdefghij&confirm_password=abcdefghij&agree=1&account_type=user" \
  "$BASE/register.php")
echo "$BODY" | grep -q 'رقم واحد' && ok 'password with no digit is rejected' || bad 'password with no digit is rejected'

# Unticked terms must be rejected.
TOKEN="$(csrf "$JAR" "$BASE/register.php")"
BODY=$(curl -s -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=No Agree&email=noagree${STAMP}@exd.test&phone=01000000000&password=$PASSWORD&confirm_password=$PASSWORD&account_type=user" \
  "$BASE/register.php")
echo "$BODY" | grep -q 'الموافقة على شروط' && ok 'registration requires accepting the terms' || bad 'registration requires accepting the terms'

# The real thing.
TOKEN="$(csrf "$JAR" "$BASE/register.php")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=Test User&email=$USER_EMAIL&phone=01000000001&password=$PASSWORD&confirm_password=$PASSWORD&agree=1&account_type=user" \
  "$BASE/register.php")
echo "$LOC" | grep -q 'registered=1' && ok 'a user account is created' || bad 'a user account is created' "$LOC"

# The same address a second time must not create a second account.
TOKEN="$(csrf "$JAR" "$BASE/register.php")"
BODY=$(curl -s -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=Duplicate&email=$USER_EMAIL&phone=01000000002&password=$PASSWORD&confirm_password=$PASSWORD&agree=1&account_type=user" \
  "$BASE/register.php")
echo "$BODY" | grep -q 'مسجّل بالفعل' && ok 'a duplicate email is refused' || bad 'a duplicate email is refused'

# A supplier account.
TOKEN="$(csrf "$JAR" "$BASE/register.php?type=supplier")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=Test Supplier&email=$SUPP_EMAIL&phone=01000000003&password=$PASSWORD&confirm_password=$PASSWORD&agree=1&account_type=supplier&company=EXD Test Co" \
  "$BASE/register.php")
echo "$LOC" | grep -q 'registered=1' && ok 'a supplier account is created' || bad 'a supplier account is created' "$LOC"

head1 'Sign in'

# Wrong password must fail, and must not say which half was wrong.
TOKEN="$(csrf "$JAR" "$BASE/login.php")"
BODY=$(curl -s -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&email=$USER_EMAIL&password=WrongPassword9" "$BASE/login.php")
echo "$BODY" | grep -q 'البريد الإلكتروني أو كلمة المرور غير صحيحة' \
  && ok 'a wrong password fails without revealing which field was wrong' \
  || bad 'a wrong password fails without revealing which field was wrong'

# An unknown address gets the identical message.
TOKEN="$(csrf "$JAR" "$BASE/login.php")"
BODY=$(curl -s -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&email=nobody${STAMP}@exd.test&password=$PASSWORD" "$BASE/login.php")
echo "$BODY" | grep -q 'البريد الإلكتروني أو كلمة المرور غير صحيحة' \
  && ok 'an unknown address gets the same message as a wrong password' \
  || bad 'an unknown address gets the same message as a wrong password'

# The real sign-in.
rm -f "$JAR"; touch "$JAR"
TOKEN="$(csrf "$JAR" "$BASE/login.php")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&email=$USER_EMAIL&password=$PASSWORD" "$BASE/login.php")
echo "$LOC" | grep -q 'account.php' && ok 'a user signs in and lands on the account page' || bad 'a user signs in' "$LOC"

grep -q 'exd_session' "$JAR" && ok 'a session cookie is set' || bad 'a session cookie is set'
grep -q 'HttpOnly' "$JAR" && ok 'the session cookie is HttpOnly' || bad 'the session cookie is HttpOnly'

BODY=$(curl -s -b "$JAR" -c "$JAR" "$BASE/account.php")
echo "$BODY" | grep -q 'Test User' && ok 'the account page shows the signed-in user' || bad 'the account page shows the signed-in user'
echo "$BODY" | grep -q 'رصيد المحفظة' && ok 'a wallet exists for the new account' || bad 'a wallet exists for the new account'

# A tampered cookie must not authenticate.
SELECTOR=$(grep exd_session "$JAR" | awk '{print $7}' | cut -d: -f1)
CODE=$(status -b "exd_session=${SELECTOR}:deadbeef" "$BASE/account.php")
[ "$CODE" = "302" ] && ok 'a forged validator does not authenticate' || bad 'a forged validator does not authenticate' "got $CODE"

# ...and the real cookie is now dead, because a bad validator revokes the session.
CODE=$(status -b "$JAR" "$BASE/account.php")
[ "$CODE" = "302" ] && ok 'a forged validator revokes the session it targeted' \
                    || bad 'a forged validator revokes the session it targeted' "got $CODE"

head1 'Supplier account state'

rm -f "$JAR2"; touch "$JAR2"
TOKEN="$(csrf "$JAR2" "$BASE/login.php")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR2" -c "$JAR2" \
  -d "csrf_token=$TOKEN&email=$SUPP_EMAIL&password=$PASSWORD" "$BASE/login.php")
echo "$LOC" | grep -q 'supplier-dashboard.php' && ok 'a supplier lands on the supplier dashboard' || bad 'a supplier lands on the supplier dashboard' "$LOC"

BODY=$(curl -s -b "$JAR2" -c "$JAR2" "$BASE/supplier-dashboard.php")
echo "$BODY" | grep -q 'قيد المراجعة' && ok 'a new supplier is pending, not active' || bad 'a new supplier is pending, not active'

# A pending supplier may not submit an offer.
TOKEN="$(csrf "$JAR2" "$BASE/supplier-dashboard.php?tab=offers")"
BODY=$(curl -s -b "$JAR2" -c "$JAR2" \
  -d "csrf_token=$TOKEN&action=submit_offer&title=Should not be accepted&supplier_price=10" \
  "$BASE/supplier-dashboard.php")
echo "$BODY" | grep -q 'لا يمكن تنفيذ هذا الإجراء' && ok 'a pending supplier cannot submit an offer' || bad 'a pending supplier cannot submit an offer'

# A supplier cannot reach the customer account page.
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR2" "$BASE/account.php")
echo "$LOC" | grep -q 'supplier-dashboard' && ok 'a supplier is redirected away from the customer account page' || bad 'a supplier is redirected away from the customer account page' "$LOC"

head1 'Sign out'

TOKEN="$(csrf "$JAR2" "$BASE/logout.php")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR2" -c "$JAR2" \
  -d "csrf_token=$TOKEN" "$BASE/logout.php")
echo "$LOC" | grep -q 'signedout=1' && ok 'sign-out redirects to the login page' || bad 'sign-out redirects' "$LOC"

CODE=$(status -b "$JAR2" "$BASE/supplier-dashboard.php")
[ "$CODE" = "302" ] && ok 'the session is dead after signing out' || bad 'the session is dead after signing out' "got $CODE"

head1 'Password reset'

TOKEN="$(csrf "$JAR" "$BASE/forgot-password.php")"
BODY=$(curl -s -b "$JAR" -c "$JAR" -d "csrf_token=$TOKEN&email=$USER_EMAIL" "$BASE/forgot-password.php")
echo "$BODY" | grep -q 'تم استلام طلبك' && ok 'a reset request is accepted' || bad 'a reset request is accepted'

RESET_LINK=$(echo "$BODY" | grep -o 'reset-password.php?token=[^"]*' | head -1)
[ -n "$RESET_LINK" ] && ok 'a reset link is issued in development' || bad 'a reset link is issued in development'

# An unknown address must produce the identical page, or this form finds accounts.
TOKEN="$(csrf "$JAR" "$BASE/forgot-password.php")"
BODY2=$(curl -s -b "$JAR" -c "$JAR" -d "csrf_token=$TOKEN&email=ghost${STAMP}@exd.test" "$BASE/forgot-password.php")
echo "$BODY2" | grep -q 'تم استلام طلبك' && ok 'an unknown address gets the same reset response' || bad 'an unknown address gets the same reset response'
echo "$BODY2" | grep -q 'reset-password.php?token=' && bad 'an unknown address must not produce a token' || ok 'an unknown address produces no token'

if [ -n "$RESET_LINK" ]; then
  NEWPASS="Reset5678word"
  TOKEN="$(csrf "$JAR" "$BASE/$RESET_LINK")"
  RAWTOKEN=$(echo "$RESET_LINK" | sed 's/.*token=//')
  LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
    -d "csrf_token=$TOKEN&token=$RAWTOKEN&password=$NEWPASS&confirm_password=$NEWPASS" \
    "$BASE/reset-password.php")
  echo "$LOC" | grep -q 'reset=1' && ok 'the password is reset' || bad 'the password is reset' "$LOC"

  # The old password must no longer work.
  rm -f "$JAR"; touch "$JAR"
  TOKEN="$(csrf "$JAR" "$BASE/login.php")"
  BODY=$(curl -s -b "$JAR" -c "$JAR" -d "csrf_token=$TOKEN&email=$USER_EMAIL&password=$PASSWORD" "$BASE/login.php")
  echo "$BODY" | grep -q 'غير صحيحة' && ok 'the old password stops working' || bad 'the old password stops working'

  # The new one must.
  TOKEN="$(csrf "$JAR" "$BASE/login.php")"
  LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
    -d "csrf_token=$TOKEN&email=$USER_EMAIL&password=$NEWPASS" "$BASE/login.php")
  echo "$LOC" | grep -q 'account.php' && ok 'the new password works' || bad 'the new password works' "$LOC"

  # A reset token is single use.
  TOKEN="$(csrf "$JAR" "$BASE/$RESET_LINK")"
  BODY=$(curl -s -b "$JAR" -c "$JAR" \
    -d "csrf_token=$TOKEN&token=$RAWTOKEN&password=Another9pass&confirm_password=Another9pass" \
    "$BASE/reset-password.php")
  echo "$BODY" | grep -q 'منتهي أو مستخدم' && ok 'a reset token cannot be used twice' || bad 'a reset token cannot be used twice'
fi

head1 'Brute force'

rm -f "$JAR2"; touch "$JAR2"
LOCKED=0
for i in $(seq 1 8); do
  TOKEN="$(csrf "$JAR2" "$BASE/login.php")"
  BODY=$(curl -s -b "$JAR2" -c "$JAR2" -d "csrf_token=$TOKEN&email=$SUPP_EMAIL&password=Wrong${i}pass" "$BASE/login.php")
  if echo "$BODY" | grep -q 'إيقاف المحاولات مؤقتًا'; then LOCKED=$i; break; fi
done
[ "$LOCKED" -gt 0 ] && ok "repeated wrong passwords lock the attempt (after $LOCKED tries)" \
                    || bad 'repeated wrong passwords lock the attempt'

printf '\n\033[1mResult:\033[0m %d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
