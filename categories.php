<?php
require_once "db_connect.php";
require_once "banner.php";
$page_title = "الأقسام الرئيسية | Elawaady XDigital Platform";
$categories = fetch_all($conn, "SELECT * FROM store_categories WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
require_once "header.php";
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">Store Categories</span>
        <h1>الأقسام الرئيسية</h1>
        <p>اختار القسم المناسب، وبعدها هتلاقي الأقسام الفرعية والخدمات التابعة له.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-banners">
            <?php foreach ($categories as $cat): ?>
                <?= exd_category_banner($conn, $cat) ?>
            <?php endforeach; ?>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <?php
                $category_href = "subcategories.php?category_id=" . (int)$cat['id'];
                $category_image = trim((string)($cat['image'] ?? ''));
                ?>
                <article class="category-card">
                    <?php if ($category_image !== ''): ?>
                        <div class="exd-media">
                            <img src="<?= e($category_image) ?>" alt="<?= e($cat['name']) ?>" loading="lazy" decoding="async">
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="category-icon"><?= e($cat['icon']) ?></div>
                        <h3><a class="card-link" href="<?= e($category_href) ?>"><?= e($cat['name']) ?></a></h3>
                        <p><?= e($cat['description']) ?></p>
                        <div class="card-actions">
                            <a class="btn btn-secondary btn-card btn-card--minor" href="<?= e($category_href) ?>">التفاصيل</a>
                            <a class="btn btn-primary btn-card" href="<?= e($category_href) ?>">اطلب الآن</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
