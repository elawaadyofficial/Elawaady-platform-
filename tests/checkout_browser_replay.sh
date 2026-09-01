#!/usr/bin/env bash
# Prove browser-level checkout idempotency through the real storefront entrypoints.
# Runs only against the isolated CI server/database supplied by Platform Integration.

set -euo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
STAMP="$(date +%s)$RANDOM"
EMAIL="checkout-replay-${STAMP}@exd.test"
PASSWORD="ReplayTest1234"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

csrf() {
  curl -fsS -b "$JAR" -c "$JAR" "$1" \
    | grep -oE 'name="csrf_token" value="[a-f0-9]+"' \
    | head -1 \
    | sed 's/.*value="//;s/"//'
}

sql() {
  php -r '
    require "db_connect.php";
    $r = $conn->query($argv[1])->fetch_row();
    echo $r === null ? "" : (string) $r[0];
  ' -- "$1"
}

TOKEN="$(csrf "$BASE/register.php")"
curl -fsS -o /dev/null -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=Checkout Replay&email=$EMAIL&phone=01000000020&password=$PASSWORD&confirm_password=$PASSWORD&agree=1&account_type=user" \
  "$BASE/register.php"

USER_ID="$(sql "SELECT id FROM platform_users WHERE email='$EMAIL' LIMIT 1")"
[ -n "$USER_ID" ] || { echo 'Replay test user was not created.' >&2; exit 1; }

TOKEN="$(csrf "$BASE/login.php")"
curl -fsS -o /dev/null -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&email=$EMAIL&password=$PASSWORD" \
  "$BASE/login.php"
grep -q exd_session "$JAR" || { echo 'Replay test user did not receive an authenticated session.' >&2; exit 1; }

SERVICE_ID="$(sql "SELECT s.id FROM store_services s WHERE s.is_active=1 AND NOT EXISTS (SELECT 1 FROM service_options o WHERE o.service_id=s.id AND o.is_required=1) ORDER BY s.id LIMIT 1")"
[ -n "$SERVICE_ID" ] || { echo 'No CI service without required options is available.' >&2; exit 1; }

php -r '
  require "db_connect.php";
  $stmt = $conn->prepare("UPDATE store_services SET price=25.00, currency=\"EGP\", show_price=1, ask_for_price=0, availability=\"available\", stock=NULL, min_quantity=1, max_quantity=10, quantity_step=1, allow_wallet_payment=1, buy_now_enabled=1 WHERE id=?");
  $stmt->bind_param("i", $argv[1]);
  $stmt->execute();
' -- "$SERVICE_ID"

php -r '
  require "lib/wallet.php";
  wallet_post((int)$argv[1], "credit", 100.00, "topup", null, "CI browser replay seed", "system", null);
' -- "$USER_ID"

PAGE="$(curl -fsS -b "$JAR" -c "$JAR" "$BASE/service.php?id=$SERVICE_ID")"
TOKEN="$(printf '%s' "$PAGE" | grep -oE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//')"
INTENT="$(printf '%s' "$PAGE" | grep -oE 'name="checkout_intent" value="[A-Za-z0-9_-]+"' | head -1 | sed 's/.*value="//;s/"//')"
[ -n "$TOKEN" ] && [ -n "$INTENT" ] || { echo 'Service page did not issue CSRF and checkout intent fields.' >&2; exit 1; }

post_checkout() {
  curl -sS -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
    -d "csrf_token=$TOKEN&checkout_intent=$INTENT&service_id=$SERVICE_ID&action=direct_buy&qty=1" \
    "$BASE/order_create.php"
}

FIRST_LOC="$(post_checkout)"
SECOND_LOC="$(post_checkout)"
FIRST_CODE="$(printf '%s' "$FIRST_LOC" | grep -oE 'code=[^&]+' | head -1 | sed 's/code=//')"
SECOND_CODE="$(printf '%s' "$SECOND_LOC" | grep -oE 'code=[^&]+' | head -1 | sed 's/code=//')"

[ -n "$FIRST_CODE" ] || { echo "First checkout did not redirect to an order: $FIRST_LOC" >&2; exit 1; }
[ "$FIRST_CODE" = "$SECOND_CODE" ] || { echo "Replay returned a different order: $FIRST_CODE vs $SECOND_CODE" >&2; exit 1; }

ORDER_COUNT="$(sql "SELECT COUNT(*) FROM orders WHERE user_id=$USER_ID AND idempotency_key='$INTENT'")"
DEBIT_COUNT="$(sql "SELECT COUNT(*) FROM wallet_transactions wt JOIN orders o ON o.id=wt.order_id WHERE o.user_id=$USER_ID AND o.idempotency_key='$INTENT' AND wt.direction='debit' AND wt.reason='order_payment'")"
PAYMENT_COUNT="$(sql "SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=$USER_ID AND o.idempotency_key='$INTENT' AND p.status='confirmed'")"
BALANCE="$(sql "SELECT balance FROM wallets WHERE user_id=$USER_ID")"

[ "$ORDER_COUNT" = "1" ] || { echo "Expected one replay-keyed order, got $ORDER_COUNT." >&2; exit 1; }
[ "$DEBIT_COUNT" = "1" ] || { echo "Expected one wallet debit, got $DEBIT_COUNT." >&2; exit 1; }
[ "$PAYMENT_COUNT" = "1" ] || { echo "Expected one confirmed payment, got $PAYMENT_COUNT." >&2; exit 1; }
[ "$BALANCE" = "75.00" ] || { echo "Expected wallet balance 75.00 after one 25.00 charge, got $BALANCE." >&2; exit 1; }

echo "PASS browser replay: one order ($FIRST_CODE), one debit, one payment, one charge."
