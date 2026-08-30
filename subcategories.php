<?php
require_once "db_connect.php";

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category = fetch_one($conn, "SELECT * FROM store_categories WHERE id=? AND is_active=1", "i", $category_id);

if (!$category) {
    http_response_code(404);
    $page_title = "القسم غير موجود";
    require_once "header.php";
    echo '<section class="page-hero"><div class="container"><h1>القسم غير موجود</h1><p>راجع رابط القسم أو ارجع للأقسام الرئيسية.</p><a class="btn btn-primary" href="categories.php">الأقسام الرئيسية</a></div></section>';
    require_once "footer.php";
    exit;
}

$page_title = $category['name'] . " | Elawaady XDigital Platform";
$subcategories = fetch_all($conn, "SELECT * FROM store_subcategories WHERE category_id=? AND is_active=1 ORDER BY sort_order ASC, id ASC", "i", $category_id);
$services = fetch_all($conn, "SELECT * FROM store_services WHERE category_id=? AND is_active=1 ORDER BY sort_order ASC, id DESC LIMIT 12", "i", $category_id);

require_once "header.php";
?>

<section class="page-hero">
    <div class="container">
        <span class="pill"><?= e($category['icon']) ?> <?= e($category['name']) ?></span>
        <h1><?= e($category['name']) ?></h1>
        <p><?= e($category['description']) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>الأقسام الفرعية</h2>
        </div>

        <?php if (!$subcategories): ?>
            <div class="empty-box">لا توجد أقسام فرعية حاليًا داخل هذا القسم.</div>
        <?php else: ?>
            <div class="category-grid">
                <?php foreach ($subcategories as $sub): ?>
                    <a class="category-card" href="subcategories.php?category_id=<?= e($category_id) ?>&subcategory_id=<?= e($sub['id']) ?>">
                        <div class="category-icon"><?= e($sub['icon']) ?></div>
                        <h3><?= e($sub['name']) ?></h3>
                        <p><?= e($sub['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-dark">
    <div class="container">
        <div class="section-head">
            <h2>خدمات من هذا القسم</h2>
        </div>

        <div class="service-grid">
            <?php foreach ($services as $service): ?>
                <?php
                $service_media = trim((string)($service['image'] ?? ''));
                $service_media_path = parse_url($service_media, PHP_URL_PATH) ?: '';
                $service_media_ext = strtolower(pathinfo($service_media_path, PATHINFO_EXTENSION));
                $service_media_is_video = in_array($service_media_ext, ['mp4', 'webm'], true);
                ?>
                <a class="service-card" href="service.php?id=<?= e($service['id']) ?>">
                    <div class="exd-media">
                        <?php if ($service_media !== '' && $service_media_is_video): ?>
                            <video src="<?= e($service_media) ?>" muted loop playsinline preload="metadata" aria-label="<?= e($service['name']) ?>"></video>
                        <?php elseif ($service_media !== ''): ?>
                            <img src="<?= e($service_media) ?>" alt="<?= e($service['name']) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="exd-media-fallback"><span><?= mb_substr(e($service['name']), 0, 1) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="service-top">
                        <span><?= e($category['name']) ?></span>
                        <small><?= e($service['status']) ?></small>
                    </div>
                    <h3><?= e($service['name']) ?></h3>
                    <p><?= e($service['description']) ?></p>
                    <div class="price"><?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
