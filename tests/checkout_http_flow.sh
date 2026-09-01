#!/usr/bin/env bash
# EXD — HTTP double-submit checkout integration test.
#
# Uses a real PHP session cookie and two identical POST requests against the
# isolated checkout probe. The test user's balance is exactly one purchase, so
# request two only succeeds if idempotency is checked before remaining balance.

set -euo pipefail

BASE="${BASE:-http://127.0.0.1:8081/tests/checkout_http_probe.php}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-elawaady_store}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
TEST_USER_ID="${CHECKOUT_HTTP_USER_ID:-90001}"

json_get() {
  local key="$1"
  php -r '$j=json_decode(stream_get_contents(STDIN), true); $k=$argv[1]; if (!is_array($j) || !array_key_exists($k,$j)) exit(2); $v=$j[$k]; echo is_bool($v) ? ($v ? "true" : "false") : $v;' "$key"
}

db_scalar() {
  MYSQL_PWD="$DB_PASS" mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" "$DB_NAME" -Nse "$1"
}

printf '\nHTTP checkout retry\n'
BOOT=$(curl -fsS -b "$JAR" -c "$JAR" "$BASE")
CSRF=$(printf '%s' "$BOOT" | json_get csrf_token)
INTENT=$(printf '%s' "$BOOT" | json_get checkout_intent)
SERVICE_ID=$(printf '%s' "$BOOT" | json_get service_id)

[ -n "$CSRF" ]
[ -n "$INTENT" ]
[ "$SERVICE_ID" = "9001" ]

# State-changing requests without CSRF must still be rejected at the HTTP edge.
CODE=$(curl -sS -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -X POST -d "checkout_intent=$INTENT" "$BASE")
[ "$CODE" = "419" ] || { echo "expected missing-CSRF request to return 419, got $CODE" >&2; exit 1; }

POST_DATA="csrf_token=$CSRF&checkout_intent=$INTENT"
FIRST=$(curl -fsS -b "$JAR" -c "$JAR" -X POST -d "$POST_DATA" "$BASE")
SECOND=$(curl -fsS -b "$JAR" -c "$JAR" -X POST -d "$POST_DATA" "$BASE")

FIRST_OK=$(printf '%s' "$FIRST" | json_get ok)
SECOND_OK=$(printf '%s' "$SECOND" | json_get ok)
FIRST_REPLAY=$(printf '%s' "$FIRST" | json_get replayed)
SECOND_REPLAY=$(printf '%s' "$SECOND" | json_get replayed)
FIRST_ID=$(printf '%s' "$FIRST" | json_get order_id)
SECOND_ID=$(printf '%s' "$SECOND" | json_get order_id)
FIRST_CODE=$(printf '%s' "$FIRST" | json_get order_code)
SECOND_CODE=$(printf '%s' "$SECOND" | json_get order_code)
SECOND_BALANCE=$(printf '%s' "$SECOND" | json_get balance_after)

[ "$FIRST_OK" = "true" ]
[ "$SECOND_OK" = "true" ]
[ "$FIRST_REPLAY" = "false" ] || { echo 'first request was unexpectedly marked replayed' >&2; exit 1; }
[ "$SECOND_REPLAY" = "true" ] || { echo 'second request was not idempotently replayed' >&2; exit 1; }
[ "$FIRST_ID" = "$SECOND_ID" ] || { echo 'double submit returned different order ids' >&2; exit 1; }
[ "$FIRST_CODE" = "$SECOND_CODE" ] || { echo 'double submit returned different order codes' >&2; exit 1; }
[ "$SECOND_BALANCE" = "0" ] || { echo "expected exhausted wallet to remain 0 on replay, got $SECOND_BALANCE" >&2; exit 1; }

ORDERS=$(db_scalar "SELECT COUNT(*) FROM orders WHERE user_id=$TEST_USER_ID AND idempotency_key='$INTENT'")
DEBITS=$(db_scalar "SELECT COUNT(*) FROM wallet_transactions WHERE user_id=$TEST_USER_ID AND direction='debit' AND reason='order_payment'")
PAYMENTS=$(db_scalar "SELECT COUNT(*) FROM payments WHERE user_id=$TEST_USER_ID AND method_key='wallet' AND status='confirmed'")
HISTORY=$(db_scalar "SELECT COUNT(*) FROM order_status_history WHERE order_id=$FIRST_ID AND to_status='new'")
BALANCE=$(db_scalar "SELECT CAST(balance AS DECIMAL(12,2)) FROM wallets WHERE user_id=$TEST_USER_ID")

[ "$ORDERS" = "1" ] || { echo "expected 1 order, got $ORDERS" >&2; exit 1; }
[ "$DEBITS" = "1" ] || { echo "expected 1 wallet debit, got $DEBITS" >&2; exit 1; }
[ "$PAYMENTS" = "1" ] || { echo "expected 1 payment, got $PAYMENTS" >&2; exit 1; }
[ "$HISTORY" = "1" ] || { echo "expected 1 initial status event, got $HISTORY" >&2; exit 1; }
[ "$BALANCE" = "0.00" ] || { echo "expected wallet balance 0.00, got $BALANCE" >&2; exit 1; }

printf 'PASS same browser form twice -> one order, one debit, one payment, same order code\n'
