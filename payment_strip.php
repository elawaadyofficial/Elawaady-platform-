<?php
// Payment strip partial — include anywhere: require __DIR__ . '/payment_strip.php';
// Accepts optional $context = 'service' | 'homepage_mid' | 'homepage_bottom'
$_ps_context = $context ?? 'default';
$_ps_img_src = '';
$_ps_candidates = [
    'uploads/payment/payment-methods.png',
    'uploads/payment/payment-methods.jpg',
    'uploads/payment/payment-methods.svg',
    'uploads/payment/payment-methods.webp',
    'uploads/brand/payment-methods.png',
    'uploads/brand/payment-methods.svg',
    'uploads/brand/payment-methods.jpg',
    'uploads/brand/payment-methods.webp',
];
foreach ($_ps_candidates as $_c) {
    if (file_exists(__DIR__ . '/' . $_c)) { $_ps_img_src = $_c; break; }
}
?>
<section class="payment-strip-section<?= $_ps_context === 'service' ? ' payment-strip-inline' : '' ?>">
  <div class="container">
    <div class="payment-strip-head">
      <h3 class="payment-strip-title">طرق الدفع المتاحة</h3>
      <p class="payment-strip-sub">واختار الطريقة اللي تناسبك — Available Payment Methods</p>
    </div>
    <?php if ($_ps_img_src): ?>
      <div class="payment-strip-img-wrap">
        <img src="<?= htmlspecialchars($_ps_img_src, ENT_QUOTES) ?>"
             alt="طرق الدفع المتاحة"
             class="payment-strip-img"
             loading="lazy">
      </div>
    <?php else: ?>
      <!-- CSS-only fallback badges -->
      <div class="payment-badges-row">
        <span class="pay-badge pay-visa">VISA</span>
        <span class="pay-badge pay-mc">Mastercard</span>
        <span class="pay-badge pay-mada">mada</span>
        <span class="pay-badge pay-apple">Apple Pay</span>
        <span class="pay-badge pay-google">Google Pay</span>
        <span class="pay-badge pay-paypal">PayPal</span>
        <span class="pay-badge pay-bank">🏦 تحويل بنكي</span>
      </div>
    <?php endif; ?>
  </div>
</section>
