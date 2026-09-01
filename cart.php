<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/pricing.php';
require_once __DIR__ . '/lib/checkout.php';
require_once __DIR__ . '/settings_helper.php';
auth_boot();

$page_title = 'سلة المشتريات | Elawaady XDigital';

$user   = auth_user();
$userId = $user !== null ? (int) $user['id'] : 0;
$key    = (string) ($_SESSION['cart_key'] ?? '');

$errors = [];
$notice = isset($_GET['added']) ? 'تمت إضافة الخدمة إلى السلة.' : '';

/** The open cart for this visitor, or null. */
function cart_current($conn, int $userId, string $key): ?array {
    if ($userId > 0) {
        $cart = fetch_one($conn, "SELECT id FROM carts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", 'i', $userId);
        if ($cart !== null) {
            return $cart;
        }
    }
    if ($key !== '') {
        return fetch_one($conn, "SELECT id FROM carts WHERE session_key = ? AND status = 'open' ORDER BY id DESC LIMIT 1", 's', $key);
    }
    return null;
}

$cart   = cart_current($conn, $userId, $key);
$cartId = $cart !== null ? (int) $cart['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');

    if ($cartId === 0) {
        header('Location: cart.php');
        exit;
    }

    if ($action === 'remove') {
        $itemId = max(0, (int) ($_POST['item_id'] ?? 0));
        $stmt   = $conn->prepare('DELETE FROM cart_items WHERE id = ? AND cart_id = ?');
        $stmt->bind_param('ii', $itemId, $cartId);
        $stmt->execute();
        header('Location: cart.php');
        exit;
    }

    if ($action === 'checkout') {
        if ($userId === 0) {
            header('Location: login.php?next=cart.php');
            exit;
        }

        $items = fetch_all(
            $conn,
            'SELECT ci.*, s.name, s.price, s.currency, s.price_mode, s.provider_base_price,
                    s.provider_price_per, s.profit_percent, s.min_quantity, s.max_quantity, s.quantity_step
               FROM cart_items ci
               JOIN store_services s ON s.id = ci.service_id
              WHERE ci.cart_id = ? AND s.is_active = 1',
            'i',
            $cartId
        );

        if (!$items) {
            $errors[] = 'السلة فارغة.';
        } else {
            // Each line is its own order, and each is charged through the same
            // atomic checkout. A line that fails leaves the ones before it
            // paid and stays in the cart, rather than silently vanishing.
            $placed = [];
            foreach ($items as $item) {
                $quote = service_quote($conn, $item, (int) $item['quantity']);
                try {
                    $result = checkout_with_wallet([
                        'user_id'       => $userId,
                        'service_id'    => (int) $item['service_id'],
                        'service_name'  => (string) $item['name'],
                        'quantity'      => (int) $item['quantity'],
                        'unit_price'    => $quote['unit_price'],
                        'options_total' => $quote['options_total'],
                        'currency'      => strtoupper((string) ($item['currency'] === 'ج.م' ? 'EGP' : $item['currency'])),
                        'target_url'    => (string) ($item['target_url'] ?? ''),
                    ]);
                    $placed[] = $result['order_code'];

                    $del = $conn->prepare('DELETE FROM cart_items WHERE id = ?');
                    $itemId = (int) $item['id'];
                    $del->bind_param('i', $itemId);
                    $del->execute();
                } catch (Throwable $e) {
                    $errors[] = (string) $item['name'] . ' — ' . $e->getMessage();
                }
            }

            if ($placed) {
                $remaining = fetch_one($conn, 'SELECT COUNT(*) AS n FROM cart_items WHERE cart_id = ?', 'i', $cartId);
                if ((int) ($remaining['n'] ?? 0) === 0) {
                    $close = $conn->prepare("UPDATE carts SET status = 'converted' WHERE id = ?");
                    $close->bind_param('i', $cartId);
                    $close->execute();
                }
                header('Location: order-success.php?code=' . urlencode((string) $placed[0]));
                exit;
            }
        }
    }
}

$items = $cartId > 0
    ? fetch_all(
        $conn,
        'SELECT ci.id, ci.service_id, ci.quantity, ci.unit_price, ci.options_json, ci.target_url,
                s.name, s.currency, s.image, s.main_image, s.price, s.price_mode,
                s.provider_base_price, s.provider_price_per, s.profit_percent
           FROM cart_items ci
           JOIN store_services s ON s.id = ci.service_id
          WHERE ci.cart_id = ? AND s.is_active = 1
          ORDER BY ci.id',
        'i',
        $cartId
    )
    : [];

$total    = 0.0;
$currency = setting('default_currency', 'EGP');
foreach ($items as $item) {
    $total += service_line_total($item, (int) $item['quantity']);
    $currency = (string) ($item['currency'] ?: $currency);
}

$balance = 0.0;
if ($userId > 0) {
    $wallet  = fetch_one($conn, 'SELECT balance FROM wallets WHERE user_id = ?', 'i', $userId);
    $balance = (float) ($wallet['balance'] ?? 0);
}

require_once __DIR__ . '/header.php';
?>

<section class="order-outcome">
    <div class="container narrow">
        <div class="outcome-card reveal">
            <h1>سلة المشتريات</h1>

            <?php if ($notice !== ''): ?>
                <div class="alert alert-success"><span>✅</span><div><?= e($notice) ?></div></div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><span>⚠️</span><div><?= e($error) ?></div></div>
            <?php endforeach; ?>

            <?php if ($items): ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $art  = trim((string) ($item['main_image'] ?: $item['image'] ?: ''));
                    $line = service_line_total($item, (int) $item['quantity']);
                    ?>
                    <div class="cart-line">
                        <div class="cart-line__art exd-media">
                            <?php if ($art !== ''): ?>
                                <img src="<?= e($art) ?>" alt="<?= e((string) $item['name']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="exd-media-fallback"><span><?= mb_substr(e((string) $item['name']), 0, 1) ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a class="cart-line__name" href="service.php?id=<?= (int) $item['service_id'] ?>">
                                <?= e((string) $item['name']) ?>
                            </a>
                            <div class="cart-line__meta">
                                الكمية: <?= (int) $item['quantity'] ?>
                                · <?= e(number_format($line, 2)) ?> <?= e((string) $item['currency']) ?>
                            </div>
                        </div>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button class="btn btn-ghost btn-card" type="submit">إزالة</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <div class="cart-summary">
                    <div class="cart-summary__row cart-summary__row--total">
                        <span>الإجمالي</span>
                        <span class="money"><?= e(number_format($total, 2)) ?> <?= e($currency) ?></span>
                    </div>
                    <?php if ($userId > 0): ?>
                        <div class="cart-summary__row">
                            <span>رصيد محفظتك</span>
                            <span class="money"><?= e(number_format($balance, 2)) ?> <?= e($currency) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" class="outcome-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="checkout">
                    <?php if ($userId === 0): ?>
                        <a class="btn btn-primary" href="login.php?next=cart.php">سجّل الدخول لإتمام الشراء</a>
                    <?php elseif ($balance < $total): ?>
                        <button class="btn btn-primary" type="submit" disabled>الرصيد غير كافٍ</button>
                        <a class="btn btn-secondary" href="contact.php">اشحن رصيدك</a>
                    <?php else: ?>
                        <button class="btn btn-primary" type="submit">إتمام الشراء من المحفظة</button>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="index.php">متابعة التسوق</a>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <p>سلتك فارغة.</p>
                    <a class="btn btn-primary" href="index.php">تصفح الخدمات</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
