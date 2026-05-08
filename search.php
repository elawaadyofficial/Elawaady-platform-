<?php
require_once "db_connect.php";
$q = trim($_GET['q'] ?? "");
$page_title = "بحث | Elawaady XDigital Platform";

$results = [];
if ($q !== "") {
    $like = "%" . $q . "%";
    $results = fetch_all($conn, "
        SELECT s.*, c.name AS category_name
        FROM store_services s
        LEFT JOIN store_categories c ON c.id=s.category_id
        WHERE s.is_active=1 AND (s.name LIKE ? OR s.description LIKE ? OR c.name LIKE ?)
        ORDER BY s.sort_order ASC, s.id DESC
        LIMIT 50
    ", "sss", $like, $like, $like);
}

require_once "header.php";
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">Search</span>
        <h1>ابحث داخل المتجر</h1>
        <form class="search-form" method="get" action="search.php">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="اكتب اسم الخدمة أو القسم">
            <button class="btn btn-primary" type="submit">بحث</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($q === ""): ?>
            <div class="empty-box">اكتب كلمة للبحث عن الخدمات.</div>
        <?php elseif (!$results): ?>
            <div class="empty-box">لا توجد نتائج مطابقة لـ: <?= e($q) ?></div>
        <?php else: ?>
            <div class="section-head">
                <h2>نتائج البحث عن: <?= e($q) ?></h2>
            </div>

            <div class="service-grid">
                <?php foreach ($results as $service): ?>
                    <a class="service-card" href="service.php?id=<?= e($service['id']) ?>">
                        <div class="service-top">
                            <span><?= e($service['category_name']) ?></span>
                            <small><?= e($service['status']) ?></small>
                        </div>
                        <h3><?= e($service['name']) ?></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="price"><?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once "footer.php"; ?>
