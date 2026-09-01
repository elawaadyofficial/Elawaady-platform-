#!/usr/bin/env bash
# EXD — the supplier lifecycle and the mediation ledger, end to end.
#
# A supplier signs up, is refused everything until approved, is approved,
# offers a service, has it published, and is assigned an order without ever
# seeing who bought it. Then a mediated deal holds a buyer's money, releases it
# to a seller, and refunds another — with the ledger checked after each move.
#
#   BASE=http://127.0.0.1:8080 ADMIN_PASS=... tests/supplier_mediation_flow.sh

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-}"
PASS=0; FAIL=0
STAMP="$(date +%s)$RANDOM"
SUPP="supp${STAMP}@exd.test"
BUYER="mbuy${STAMP}@exd.test"
SELLER="msel${STAMP}@exd.test"
PW="Test1234pass"

SJAR="$(mktemp)"; AJAR="$(mktemp)"; BJAR="$(mktemp)"
trap 'rm -f "$SJAR" "$AJAR" "$BJAR"' EXIT

ok()  { PASS=$((PASS+1)); printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad() { FAIL=$((FAIL+1)); printf '  \033[31mFAIL\033[0m %s\n' "$1"; if [ $# -gt 1 ]; then printf '       %s\n' "$2"; fi; return 0; }
head1(){ printf '\n\033[1m%s\033[0m\n' "$1"; }

csrf() { curl -s -b "$1" -c "$1" "$2" | grep -oE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed 's/.*value="//;s/"//'; }
sql()  { php -r 'require "db_connect.php"; $r = $conn->query($argv[1])->fetch_row(); echo $r === null ? "" : (string) $r[0];' -- "$1"; }

register() {
  local jar="$1" email="$2" name="$3" type="$4"
  local token; token="$(csrf "$jar" "$BASE/register.php?type=$type")"
  curl -s -o /dev/null -b "$jar" -c "$jar" \
    -d "csrf_token=$token&name=$name&email=$email&phone=01000000020&password=$PW&confirm_password=$PW&agree=1&account_type=$type" \
    "$BASE/register.php"
}

sign_in() {
  local jar="$1" email="$2"
  local token; token="$(csrf "$jar" "$BASE/login.php")"
  curl -s -o /dev/null -b "$jar" -c "$jar" -d "csrf_token=$token&email=$email&password=$PW" "$BASE/login.php"
}

[ -n "$ADMIN_PASS" ] || { echo "ADMIN_PASS is required." >&2; exit 2; }
php tools/reset_throttle.php >/dev/null 2>&1 || true

head1 'A supplier that has not been approved'

register "$SJAR" "$SUPP" "Test Supplier" supplier
SUPP_ID="$(sql "SELECT id FROM platform_users WHERE email='$SUPP'")"
[ -n "$SUPP_ID" ] && ok "the supplier account exists (id $SUPP_ID)" || { bad 'the supplier account exists'; exit 1; }

STATUS="$(sql "SELECT status FROM platform_users WHERE id=$SUPP_ID")"
[ "$STATUS" = "pending" ] && ok 'and starts pending, not active' || bad 'starts pending' "got $STATUS"

PROFILE="$(sql "SELECT COUNT(*) FROM supplier_profiles WHERE user_id=$SUPP_ID")"
[ "$PROFILE" = "1" ] && ok 'a supplier profile is created with the account' || bad 'a supplier profile is created'

sign_in "$SJAR" "$SUPP"
TOKEN="$(csrf "$SJAR" "$BASE/supplier-dashboard.php?tab=offers")"
curl -s -o /dev/null -b "$SJAR" -c "$SJAR" \
  -d "csrf_token=$TOKEN&action=submit_offer&title=A service before approval&supplier_price=10" \
  "$BASE/supplier-dashboard.php"
OFFERS="$(sql "SELECT COUNT(*) FROM supplier_offers WHERE supplier_id=$SUPP_ID")"
[ "$OFFERS" = "0" ] && ok 'a pending supplier cannot offer a service' || bad 'a pending supplier cannot offer' "got $OFFERS"

head1 'Approval'

sign_in_admin() {
  local token; token="$(csrf "$AJAR" "$BASE/admin/login.php")"
  curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
    -d "csrf_token=$token&username=$ADMIN_USER&password=$ADMIN_PASS" "$BASE/admin/login.php"
}
sign_in_admin

TOKEN="$(csrf "$AJAR" "$BASE/admin/suppliers.php?status=pending")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=approve&supplier_id=$SUPP_ID&return=status=pending" \
  "$BASE/admin/suppliers.php"

STATUS="$(sql "SELECT status FROM platform_users WHERE id=$SUPP_ID")"
[ "$STATUS" = "active" ] && ok 'an administrator approves the supplier' || bad 'the supplier is approved' "got $STATUS"

APPROVED_BY="$(sql "SELECT approved_by FROM platform_users WHERE id=$SUPP_ID")"
[ -n "$APPROVED_BY" ] && ok 'the approval records who made it' || bad 'the approval records who made it'

NOTIFIED="$(sql "SELECT COUNT(*) FROM notifications WHERE user_id=$SUPP_ID")"
[ "$NOTIFIED" -ge 1 ] && ok 'the supplier is notified' || bad 'the supplier is notified' "got $NOTIFIED"

head1 'Offering a service'

TOKEN="$(csrf "$SJAR" "$BASE/supplier-dashboard.php?tab=offers")"
curl -s -o /dev/null -b "$SJAR" -c "$SJAR" \
  -d "csrf_token=$TOKEN&action=submit_offer&title=خدمة اختبار من مورد&supplier_price=40&execution_time=24 ساعة&description=وصف" \
  "$BASE/supplier-dashboard.php"

OFFER_ID="$(sql "SELECT id FROM supplier_offers WHERE supplier_id=$SUPP_ID ORDER BY id DESC LIMIT 1")"
[ -n "$OFFER_ID" ] && ok "an approved supplier can offer a service (offer $OFFER_ID)" || { bad 'an approved supplier can offer'; exit 1; }

REVIEW="$(sql "SELECT review_status FROM supplier_offers WHERE id=$OFFER_ID")"
[ "$REVIEW" = "pending_review" ] && ok 'the offer waits for review' || bad 'the offer waits for review' "got $REVIEW"

PUBLISHED="$(sql "SELECT COUNT(*) FROM store_services WHERE name='خدمة اختبار من مورد'")"
[ "$PUBLISHED" = "0" ] && ok 'and is not on the storefront yet' || bad 'the offer is not yet on the storefront'

head1 'Publishing it'

CAT_ID="$(sql "SELECT id FROM store_categories WHERE is_active=1 ORDER BY id LIMIT 1")"
TOKEN="$(csrf "$AJAR" "$BASE/admin/supplier-offers.php?status=pending_review")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=approve&offer_id=$OFFER_ID&category_id=$CAT_ID&sell_price=90&admin_notes=معتمدة&return_status=pending_review" \
  "$BASE/admin/supplier-offers.php"

SERVICE_ID="$(sql "SELECT published_service_id FROM supplier_offers WHERE id=$OFFER_ID")"
[ -n "$SERVICE_ID" ] && ok "approving publishes a real service (id $SERVICE_ID)" || bad 'approving publishes a service'

SELL="$(sql "SELECT price FROM store_services WHERE id=$SERVICE_ID")"
[ "$SELL" = "90.00" ] && ok "it carries the store's sell price, not the supplier's" || bad "it carries the sell price" "got $SELL"

COST="$(sql "SELECT supplier_sell_price FROM store_services WHERE id=$SERVICE_ID")"
[ "$COST" = "40.00" ] && ok "the supplier's own price is kept, out of the customer's reach" || bad "the supplier's price is kept" "got $COST"

VISIBLE="$(sql "SELECT supplier_visible FROM store_services WHERE id=$SERVICE_ID")"
[ "$VISIBLE" = "0" ] && ok 'the service does not reveal its supplier' || bad 'the service hides its supplier' "got $VISIBLE"

ACTIVE="$(sql "SELECT is_active FROM store_services WHERE id=$SERVICE_ID")"
[ "$ACTIVE" = "0" ] && ok 'and is published inactive, for staff to review before it sells' \
                    || bad 'it is published inactive' "got $ACTIVE"

# What the customer's own service page selects must not mention the supplier.
BODY="$(curl -s "$BASE/service.php?id=$SERVICE_ID")"
if grep -qE '40\.00|supplier_sell_price|Test Supplier' <<<"$BODY"; then
  bad "the service page never shows the supplier or what the store paid"
else
  ok "the service page never shows the supplier or what the store paid"
fi

head1 'Mediation: holding, releasing, refunding'

register "$BJAR" "$BUYER" "Mediation Buyer" user
BUYER_ID="$(sql "SELECT id FROM platform_users WHERE email='$BUYER'")"
register "$(mktemp)" "$SELLER" "Mediation Seller" user
SELLER_ID="$(sql "SELECT id FROM platform_users WHERE email='$SELLER'")"
[ -n "$BUYER_ID" ] && [ -n "$SELLER_ID" ] && ok "two parties exist ($BUYER_ID buyer, $SELLER_ID seller)" || bad 'two parties exist'

for uid in "$BUYER_ID"; do
  TOKEN="$(csrf "$AJAR" "$BASE/admin/wallets.php?user_id=$uid")"
  curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
    -d "csrf_token=$TOKEN&action=adjust&user_id=$uid&direction=credit&amount=500&note=رصيد وساطة" \
    "$BASE/admin/wallets.php"
done
BAL="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$BAL" = "500.00" ] && ok 'the buyer is funded with 500.00' || bad 'the buyer is funded' "got $BAL"

TOKEN="$(csrf "$AJAR" "$BASE/admin/mediation.php")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=open&subject=بيع قناة اختبار&deal_amount=300&fee_amount=20&safety_days=0&buyer_id=$BUYER_ID&seller_id=$SELLER_ID" \
  "$BASE/admin/mediation.php"

CASE_ID="$(sql "SELECT id FROM mediations ORDER BY id DESC LIMIT 1")"
[ -n "$CASE_ID" ] && ok "a case is opened (id $CASE_ID)" || { bad 'a case is opened'; exit 1; }

move() {
  local to="$1"
  local token; token="$(csrf "$AJAR" "$BASE/admin/mediation.php")"
  curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
    -d "csrf_token=$token&action=move&mediation_id=$CASE_ID&to_status=$to&note=اختبار" \
    "$BASE/admin/mediation.php"
}

# A jump the graph forbids must not happen.
move released
ST="$(sql "SELECT status FROM mediations WHERE id=$CASE_ID")"
[ "$ST" = "opened" ] && ok 'a jump straight to released is refused' || bad 'an illegal jump is refused' "got $ST"

move terms_accepted
move funds_held
ST="$(sql "SELECT status FROM mediations WHERE id=$CASE_ID")"
[ "$ST" = "funds_held" ] && ok 'the case reaches funds held along its allowed path' || bad 'the case reaches funds held' "got $ST"

BAL="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$BAL" = "180.00" ] && ok 'holding debits the buyer by deal plus fee (500 - 320)' || bad 'holding debits the buyer' "got $BAL"

HELD="$(sql "SELECT held_balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$HELD" = "320.00" ] && ok 'and the held amount is recorded, not lost' || bad 'the held amount is recorded' "got $HELD"

SELLER_BAL="$(sql "SELECT COALESCE(balance,0) FROM wallets WHERE user_id=$SELLER_ID")"
[ "$SELLER_BAL" = "0.00" ] && ok 'the seller has not been paid yet' || bad 'the seller is unpaid at this stage' "got $SELLER_BAL"

move in_delivery
move delivered
move released
ST="$(sql "SELECT status FROM mediations WHERE id=$CASE_ID")"
[ "$ST" = "released" ] && ok 'delivery then release completes the case' || bad 'the case is released' "got $ST"

SELLER_BAL="$(sql "SELECT balance FROM wallets WHERE user_id=$SELLER_ID")"
[ "$SELLER_BAL" = "300.00" ] && ok 'the seller receives the deal amount, and the fee stays with the platform' \
                             || bad 'the seller receives the deal amount' "got $SELLER_BAL"

HELD="$(sql "SELECT held_balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$HELD" = "0.00" ] && ok 'and the hold is cleared' || bad 'the hold is cleared' "got $HELD"

HIST="$(sql "SELECT COUNT(*) FROM mediation_status_history WHERE mediation_id=$CASE_ID")"
[ "$HIST" -ge 5 ] && ok "every move is recorded ($HIST rows)" || bad 'every move is recorded' "got $HIST"

head1 'Mediation: a refund returns everything'

TOKEN="$(csrf "$AJAR" "$BASE/admin/mediation.php")"
curl -s -o /dev/null -b "$AJAR" -c "$AJAR" \
  -d "csrf_token=$TOKEN&action=open&subject=صفقة تُسترد&deal_amount=100&fee_amount=10&safety_days=0&buyer_id=$BUYER_ID&seller_id=$SELLER_ID" \
  "$BASE/admin/mediation.php"
CASE_ID="$(sql "SELECT id FROM mediations ORDER BY id DESC LIMIT 1")"

move terms_accepted
move funds_held
BEFORE="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"
move refunded
AFTER="$(sql "SELECT balance FROM wallets WHERE user_id=$BUYER_ID")"

[ "$AFTER" = "180.00" ] && ok "a refund returns deal and fee in full ($BEFORE -> $AFTER)" \
                        || bad 'a refund returns deal and fee' "$BEFORE -> $AFTER"

HELD="$(sql "SELECT held_balance FROM wallets WHERE user_id=$BUYER_ID")"
[ "$HELD" = "0.00" ] && ok 'and clears the hold' || bad 'a refund clears the hold' "got $HELD"

# The ledger must still explain the balance exactly.
LEDGER="$(sql "SELECT ROUND(COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0),2) FROM wallet_transactions WHERE user_id=$BUYER_ID")"
[ "$LEDGER" = "$AFTER" ] && ok "the ledger sums to the balance ($LEDGER)" || bad 'the ledger sums to the balance' "ledger $LEDGER vs balance $AFTER"

printf '\n\033[1mResult:\033[0m %d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
