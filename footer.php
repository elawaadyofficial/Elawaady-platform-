</main>

<?php
/**
 * The footer's links and contact details come from the database.
 *
 * A support number that lives in markup has to be changed in every file that
 * shows it, and one of them is always missed. These read the same
 * system_settings rows the dashboard writes, and the page list is whatever is
 * published and marked for the footer.
 */
require_once __DIR__ . '/settings_helper.php';

$exd_footer_pages = [];
try {
    $exd_footer_pages = fetch_all(
        $conn,
        'SELECT slug, title FROM static_pages
          WHERE is_published = 1 AND show_in_footer = 1
          ORDER BY sort_order, title LIMIT 12'
    );
} catch (Throwable $e) {
    $exd_footer_pages = [];
}

$exd_footer_policies = [];
try {
    $exd_footer_policies = fetch_all(
        $conn,
        'SELECT policy_key, title FROM policies WHERE current_version_id IS NOT NULL ORDER BY id LIMIT 8'
    );
} catch (Throwable $e) {
    $exd_footer_policies = [];
}

$exd_wa      = preg_replace('/\D/', '', setting('support_whatsapp', ''));
$exd_wa_alt  = preg_replace('/\D/', '', setting('support_whatsapp_alt', ''));
$exd_tg      = trim(setting('support_telegram', ''));
$exd_mail    = trim(setting('support_email', ''));
$exd_hours   = trim(setting('support_hours', ''));
$exd_licence = setting('licence_number', '767-766-857');
$exd_brand   = setting('brand_name_en', 'Elawaady XDigital');
?>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h3><?= e($exd_brand) ?></h3>
            <p>متجر خدمات رقمية منظم: توثيق، بيع، شراء، وساطة آمنة، سوشيال ميديا، ذكاء اصطناعي، اشتراكات، وبرمجة.</p>
            <p class="footer-licence">
                الوسيط لخدمات السوشيال ميديا — ترخيص رقم <span dir="ltr"><?= e($exd_licence) ?></span>
            </p>
        </div>

        <?php if ($exd_footer_pages): ?>
            <div>
                <h4>عن المتجر</h4>
                <?php foreach ($exd_footer_pages as $exd_page): ?>
                    <a href="page.php?slug=<?= e((string) $exd_page['slug']) ?>"><?= e((string) $exd_page['title']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($exd_footer_policies): ?>
            <div>
                <h4>الشروط والسياسات</h4>
                <?php foreach ($exd_footer_policies as $exd_policy): ?>
                    <a href="page.php?slug=<?= e((string) $exd_policy['policy_key']) ?>"><?= e((string) $exd_policy['title']) ?></a>
                <?php endforeach; ?>
                <a href="mediation.php">الوساطة الآمنة</a>
            </div>
        <?php endif; ?>

        <div>
            <h4>الدعم</h4>
            <?php if ($exd_wa !== ''): ?>
                <a href="https://wa.me/<?= e($exd_wa) ?>" target="_blank" rel="noopener">واتساب <span dir="ltr"><?= e($exd_wa) ?></span></a>
            <?php endif; ?>
            <?php if ($exd_wa_alt !== ''): ?>
                <a href="https://wa.me/<?= e($exd_wa_alt) ?>" target="_blank" rel="noopener">واتساب إضافي</a>
            <?php endif; ?>
            <?php if ($exd_tg !== ''): ?>
                <a href="<?= e(str_starts_with($exd_tg, 'http') ? $exd_tg : 'https://t.me/' . ltrim($exd_tg, '@')) ?>"
                   target="_blank" rel="noopener">تيليجرام</a>
            <?php endif; ?>
            <?php if ($exd_mail !== ''): ?>
                <a href="mailto:<?= e($exd_mail) ?>"><?= e($exd_mail) ?></a>
            <?php endif; ?>
            <a href="contact.php">صفحة التواصل</a>
            <a href="order-track.php">تتبّع طلب</a>
            <?php if ($exd_hours !== ''): ?>
                <p class="footer-hours"><?= e($exd_hours) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-bottom">
        © <?= date('Y') ?> <?= e($exd_brand) ?> — شغلك بنظام… وحقوقك محفوظة
    </div>
</footer>

<script src="main.js"></script>
</body>
</html>
