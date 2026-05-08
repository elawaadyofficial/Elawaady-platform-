<?php
require_once "db_connect.php";
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
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a class="category-card" href="subcategories.php?category_id=<?= e($cat['id']) ?>">
                    <div class="category-icon"><?= e($cat['icon']) ?></div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p><?= e($cat['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
