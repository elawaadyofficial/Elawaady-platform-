<?php
require_once "db_connect.php";
$page_title = "Elawaady XDigital Platform | المتجر الرسمي";
$featured_categories = fetch_all($conn, "SELECT * FROM store_categories WHERE is_active=1 ORDER BY sort_order ASC LIMIT 12");
$featured_services = fetch_all($conn, "SELECT s.*, c.name AS category_name FROM store_services s LEFT JOIN store_categories c ON c.id=s.category_id WHERE s.is_active=1 ORDER BY s.sort_order ASC, s.id DESC LIMIT 8");
require_once "header.php";
?>

<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="pill">Official Digital Store</span>
            <h1>متجر رقمي شامل لإدارة خدماتك باحتراف</h1>
            <p>
                خدمات السوشيال ميديا، التوثيق، الحسابات الجاهزة، الذكاء الاصطناعي،
                Microsoft 365، الاشتراكات، البرمجة، الوساطة الرقمية، والماركت بليس.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="categories.php">تصفح الأقسام</a>
                <a class="btn btn-secondary" href="contact.php">اطلب استشارة</a>
            </div>
        </div>

        <div class="hero-card">
            <div class="orb orb-one"></div>
            <div class="orb orb-two"></div>
            <h2>Elawaady XDigital</h2>
            <p>توثيق — بيع — شراء — وساطة آمنة — متجر خدمات رقمية</p>
            <div class="stats">
                <span><b>43+</b> قسم</span>
                <span><b>VIP</b> خدمة</span>
                <span><b>Safe</b> System</span>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="pill">Main Categories</span>
            <h2>الأقسام الرئيسية</h2>
            <p>هيكل منظم من أقسام رئيسية وفرعية وخدمات قابلة للتوسع.</p>
        </div>

        <div class="category-grid">
            <?php foreach ($featured_categories as $cat): ?>
                <a class="category-card" href="subcategories.php?category_id=<?= e($cat['id']) ?>">
                    <div class="category-icon"><?= e($cat['icon']) ?></div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p><?= e($cat['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-dark">
    <div class="container">
        <div class="section-head">
            <span class="pill">Featured Services</span>
            <h2>خدمات مختارة</h2>
        </div>

        <div class="service-grid">
            <?php foreach ($featured_services as $service): ?>
                <a class="service-card" href="service.php?id=<?= e($service['id']) ?>">
                    <div class="service-top">
                        <span><?= e($service['category_name']) ?></span>
                        <small><?= e($service['status']) ?></small>
                    </div>
                    <h3><?= e($service['name']) ?></h3>
                    <p><?= e($service['description']) ?></p>
                    <div class="price">
                        <?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
