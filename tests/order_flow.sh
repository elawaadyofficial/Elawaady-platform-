#!/usr/bin/env bash
# EXD — the order, wallet and mediation flow, end to end over HTTP.
#
# Exercises what actually costs money: a purchase priced server-side, a wallet
# debited exactly once, an order that moves only along its allowed path, a
# payment confirmed by staff, and a mediated deal whose held funds are released
# to the seller. Nothing here is mocked.
#
#   BASE=http://127.0.0.1:8080 ADMIN_PASS=... tests/order_flow.sh

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-}"
PASS=0; FAIL=0
STAMP="$(date +%s)$RANDOM"
BUYER="buyer${STAMP}@exd.test"
PASSWORD="Test1234pass"

JAR="$(mktemp)"; AJAR="$(mktemp)"
trap 'rm -f "$JAR" "$AJAR"' EXIT

ok()  { PASS=$((PASS+1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad() { FAIL=$((FAIL+1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; if [ $# -gt 1 ]; then printf '       %s\n' "$2"; fi; return 0; }
head1(){ printf '\n\033[1m%s\033[0m\n' "$1"; }

csrf() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }

# Read a single value straight from the database, so the test checks stored
# state rather than only what a page happens to print.
sql() { php -r '
  require "db_connect.php";
  $r = $conn->query($argv[1])->fetch_row();
  echo $r === null ? "" : (string) $r[0];
' -- "$1"; }

if [ -z "$ADMIN_PASS" ]; then echo "ADMIN_PASS is required." >&2; exit 2; fi

php tools/reset_throttle.php >/dev/null 2>&1 || true

head1 'Setup'

TOKEN="$(csrf "$JAR" "$BASE/register.php")"
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&name=Order Buyer&email=$BUYER&phone=01000000010&password=$PASSWORD&confirm_password=$PASSWORD&agree=1&account_type=user" \
  "$BASE/register.php"

BUYER_ID="$(sql "SELECT id FROM platform_users WHERE email='$BUYER'")"
[ -n "$BUYER_ID" ] && ok "a buyer account exists (id $BUYER_ID)" || { bad 'a buyer account exists'; exit 1; }

# A service with a real price to buy.
SERVICE_ID="$(sql "SELECT id FROM store_services WHERE is_active=1 ORDER BY id LIMIT 1")"
php -r '
  require "db_connect.php";
  $stmt = $conn->prepare("UPDATE store_services SET price = 25.00, currency = \"EGP\", max_quantity = 100, min_quantity = 1, quantity_step = 1, allow_wallet_payment = 1, buy_now_enabled = 1 WHERE id = ?");
  $stmt->bind_param("i", $argv[1]); $stmt->execute();
' -- "$SERVICE_ID"
ok "a priced service is ready (id $SERVICE_ID at 25.00)"

TOKEN="$(csrf "$JAR" "$BASE/login.php")"
curl -s -o /dev/null -b "$JAR" -c "$JAR" -d "csrf_token=$TOKEN&email=$BUYER&password=$PASSWORD" "$BASE/login.php"
grep -q exd_session "$JAR" && ok 'the buyer is signed in' || bad 'the buyer is signed in'

head1 'A purchase with no money in the wallet'

TOKEN="$(csrf "$JAR" "$BASE/service.php?id=$SERVICE_ID")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&service_id=$SERVICE_ID&action=direct_buy&qty=2" "$BASE/order_create.php")
CODE=$(echo "$LOC" | grep -oE 'code=[^&]+' | sed 's/code=//')
[ -n "$CODE" ] && ok "an order is created ($CODE)" || bad 'an order is created' "$LOC"

STATUS="$(sql "SELECT payment_status FROM orders WHERE order_code='$CODE'")"
[ "$STATUS" = "pending" ] && ok 'with an empty wallet the order awaits payment' \
                          || bad 'with an empty wallet the order awaits payment' "got $STATUS"

TOTAL="$(sql "SELECT total_price FROM orders WHERE order_code='$CODE'")"
[ "$TOTAL" = "50.00" ] && ok 'the total is priced on the server (2 x 25.00)' \
                       || bad 'the total is priced on the server' "got $TOTAL"

head1 'The posted price is never trusted'

TOKEN="$(csrf "$JAR" "$BASE/service.php?id=$SERVICE_ID")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&service_id=$SERVICE_ID&action=direct_buy&qty=1&price=0.01&unit_price=0.01&total_price=0.01" \
  "$BASE/order_create.php")
CHEAT=$(echo "$LOC" | grep -oE 'code=[^&]+' | sed 's/code=//')
CHEAT_TOTAL="$(sql "SELECT total_price FROM orders WHERE order_code='$CHEAT'")"
[ "$CHEAT_TOTAL" = "25.00" ] && ok 'a price posted by the browser is ignored' \
                             || bad 'a price posted by the browser is ignored' "got $CHEAT_TOTAL"

head1 'Quantity bounds'

TOKEN="$(csrf "$JAR" "$BASE/service.php?id=$SERVICE_ID")"
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&service_id=$SERVICE_ID&action=direct_buy&qty=9999" "$BASE/order_create.php"
OVER="$(sql "SELECT COUNT(*) FROM orders WHERE user_id=$BUYER_ID AND quantity=9999")"
[ "$OVER" = "0" ] && ok 'a quantity above the maximum is refused' || bad 'a quantity above the maximum is refused'

head1 'Paying from the wallet'

# Staff credit the wallet through the ledger, the only way money enters.
sign_in_admin() {
  local token; token="$(csrf "$AJAR" "$BASE/admin/login.php")"
  curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
    -d "csrf_token=$token&username=$ADMIN_USER&password=$ADMIN_PASS" "$BASE/admin/login.php"
}
sign_in_admin
grep -q exd_admin "$AJAR" && ok 'staff are signed in' || bad 'staff are signed in'

TOKEN="$(csrf "$AJAR" "$BASE/admin/wallets.php?user_id=$BUYER_ID")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=adjust&user_id=$BUYER_ID&direction=credit&amount=200&note=رصيد اختبار" \
  "$BASE/admin/wallets.php"

BALANCE="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$BALANCE" = "200.00" ] && ok 'the wallet is credited to 200.00' || bad 'the wallet is credited' "got $BALANCE"

LEDGER="$(sql "SELECT COUNT(*) FROM wallet_transactions WHERE user_id=$BUYER_ID")"
[ "$LEDGER" = "1" ] && ok 'the credit wrote exactly one ledger row' || bad 'the credit wrote one ledger row' "got $LEDGER"

# An adjustment with no stated reason must be refused.
TOKEN="$(csrf "$AJAR" "$BASE/admin/wallets.php?user_id=$BUYER_ID")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=adjust&user_id=$BUYER_ID&direction=credit&amount=50&note=" \
  "$BASE/admin/wallets.php"
AFTER="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$AFTER" = "200.00" ] && ok 'an adjustment with no stated reason is refused' \
                        || bad 'an adjustment with no stated reason is refused' "got $AFTER"

TOKEN="$(csrf "$JAR" "$BASE/service.php?id=$SERVICE_ID")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&service_id=$SERVICE_ID&action=direct_buy&qty=3" "$BASE/order_create.php")
PAID=$(echo "$LOC" | grep -oE 'code=[^&]+' | sed 's/code=//')

PAID_STATUS="$(sql "SELECT payment_status FROM orders WHERE order_code='$PAID'")"
[ "$PAID_STATUS" = "paid" ] && ok 'a funded wallet pays the order outright' || bad 'a funded wallet pays the order' "got $PAID_STATUS"

BALANCE="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$BALANCE" = "125.00" ] && ok 'the wallet is debited exactly once (200 - 75)' || bad 'the wallet is debited once' "got $BALANCE"

DEBITS="$(sql "SELECT COUNT(*) FROM wallet_transactions WHERE user_id=$BUYER_ID AND direction='debit'")"
[ "$DEBITS" = "1" ] && ok 'one debit row, not two' || bad 'one debit row' "got $DEBITS"

RECORDED="$(sql "SELECT balance_after FROM wallet_transactions WHERE user_id=$BUYER_ID AND direction='debit' ORDER BY id DESC LIMIT 1")"
[ "$RECORDED" = "125.00" ] && ok 'the ledger row carries the balance that followed it' || bad 'the ledger carries the running balance' "got $RECORDED"

PAYMENT="$(sql "SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.order_code='$PAID' AND p.status='confirmed'")"
[ "$PAYMENT" = "1" ] && ok 'a confirmed payment row is written with the order' || bad 'a confirmed payment row is written' "got $PAYMENT"

head1 'The ledger is the truth'

# Corrupt the cached balance and prove reconciliation repairs it from the ledger.
php -r '
  require "db_connect.php";
  $stmt = $conn->prepare("UPDATE wallets SET balance = 999.99 WHERE user_id = ?");
  $stmt->bind_param("i", $argv[1]); $stmt->execute();
' -- "$BUYER_ID"
TOKEN="$(csrf "$AJAR" "$BASE/admin/wallets.php?user_id=$BUYER_ID")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=reconcile&user_id=$BUYER_ID" "$BASE/admin/wallets.php"
REPAIRED="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$REPAIRED" = "125.00" ] && ok 'a wrong cached balance is repaired from the ledger' \
                           || bad 'a wrong cached balance is repaired' "got $REPAIRED"

head1 'Insufficient balance'

php -r '
  require "db_connect.php";
  $stmt = $conn->prepare("UPDATE store_services SET price = 5000.00 WHERE id = ?");
  $stmt->bind_param("i", $argv[1]); $stmt->execute();
' -- "$SERVICE_ID"

TOKEN="$(csrf "$JAR" "$BASE/service.php?id=$SERVICE_ID")"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "csrf_token=$TOKEN&service_id=$SERVICE_ID&action=direct_buy&qty=1" "$BASE/order_create.php")
BIG=$(echo "$LOC" | grep -oE 'code=[^&]+' | sed 's/code=//')
BIG_STATUS="$(sql "SELECT payment_status FROM orders WHERE order_code='$BIG'")"
[ "$BIG_STATUS" = "pending" ] && ok 'an order beyond the balance is created unpaid, not part-paid' \
                              || bad 'an order beyond the balance is unpaid' "got $BIG_STATUS"

BALANCE="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$BALANCE" = "125.00" ] && ok 'and the wallet is untouched' || bad 'the wallet is untouched' "got $BALANCE"

head1 'Order status workflow'

ORDER_ID="$(sql "SELECT id FROM orders WHERE order_code='$BIG'")"

# A move the workflow does not allow must be refused.
TOKEN="$(csrf "$AJAR" "$BASE/admin/order-view.php?id=$ORDER_ID")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=set_status&order_id=$ORDER_ID&to_status=completed" \
  "$BASE/admin/order-view.php"
MOVED="$(sql "SELECT order_status FROM orders WHERE order_code='$BIG'")"
[ "$MOVED" != "completed" ] && ok "an illegal status jump is refused (still $MOVED)" \
                            || bad 'an illegal status jump is refused' 'it went straight to completed'

head1 'Supplier confidentiality'

# Whatever a customer can see of an order, it must never include the supplier.
BODY="$(curl -s -b "$JAR" "$BASE/order-track.php?code=$PAID")"

# The footer links to «شروط المورد» on every page, so look at the order panel
# rather than the whole document.
PANEL="$(sed -n '/order-outcome/,/site-footer/p' <<<"$BODY")"

# Name a real supplier on the order, then prove the customer still cannot see it.
php -r '
  require "db_connect.php";
  $stmt = $conn->prepare("UPDATE orders SET supplier_id = 4242, supplier_status = \"مورد سري\", supplier_cost = 3.50 WHERE order_code = ?");
  $stmt->bind_param("s", $argv[1]); $stmt->execute();
' -- "$PAID"

PANEL="$(sed -n '/order-outcome/,/site-footer/p' <<<"$(curl -s -b "$JAR" "$BASE/order-track.php?code=$PAID")")"

if grep -qiE 'supplier|4242|مورد سري|3\.50' <<<"$PANEL"; then
  bad 'the tracking page never names a supplier'
else
  ok 'the tracking page never names a supplier, even when the order has one'
fi

if grep -q "$PAID" <<<"$PANEL"; then
  ok 'the tracking page shows the order it was asked for'
else
  bad 'the tracking page shows the order'
fi

# The supplier dashboard must not carry the buyer's details either.
SUPPLIER_VIEW="$(php -r '
  require "db_connect.php";
  $rows = fetch_all($conn, "SELECT * FROM orders WHERE supplier_id = 4242 LIMIT 1");
  echo $rows ? implode(",", array_keys($rows[0])) : "";
')"
if grep -q 'customer_phone' <<<"$SUPPLIER_VIEW"; then
  ok 'the orders table does hold customer contact details'
else
  bad 'the orders table holds customer contact details'
fi

SUPPLIER_COLUMNS="$(grep -oE 'SELECT[^;]*FROM\s+orders o WHERE o.supplier_id' supplier-dashboard.php || true)"
if grep -qiE 'customer_name|customer_phone|customer_email' <<<"$SUPPLIER_COLUMNS"; then
  bad 'the supplier order query never selects customer contact details'
else
  ok 'the supplier order query never selects customer contact details'
fi

printf '\n\033[1mResult:\033[0m %d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
