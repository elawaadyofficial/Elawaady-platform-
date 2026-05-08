<?php
require_once "db_connect.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$service = fetch_one($conn, "
    SELECT s.*, c.name AS category_name, sc.name AS subcategory_name
    FROM store_services s
    LEFT JOIN store_categories c ON c.id=s.category_id
    LEFT JOIN store_subcategories sc ON sc.id=s.subcategory_id
    WHERE s.id=? AND s.is_active=1
", "i", $id);

if (!$service) {
    http_response_code(404);
    $page_title = "الخدمة غير موجودة";
    require_once "header.php";
    echo '<section class="page-hero"><div class="container"><h1>الخدمة غير موجودة</h1><p>راجع رابط الخدمة أو ابحث عن خدمة أخرى.</p><a class="btn btn-primary" href="categories.php">تصفح الأقسام</a></div></section>';
    require_once "footer.php";
    exit;
}

$page_title = $service['name'] . " | Elawaady XDigital Platform";
require_once "header.php";
?>

<section class="page-hero">
    <div class="container">
        <span class="pill"><?= e($service['category_name']) ?> <?= $service['subcategory_name'] ? " / " . e($service['subcategory_name']) : "" ?></span>
        <h1><?= e($service['name']) ?></h1>
        <p><?= e($service['description']) ?></p>

        <div class="service-meta">
            <span>الحالة: <?= e($service['status']) ?></span>
            <span>السعر: <?= $service['price'] > 0 ? e(number_format($service['price'], 2)) . " ج.م" : "حسب الطلب" ?></span>
        </div>

        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= e($service['service_link'] ?: 'contact.php') ?>" target="<?= $service['service_link'] ? '_blank' : '_self' ?>">طلب الخدمة</a>
            <a class="btn btn-secondary" href="contact.php">استشارة قبل الطلب</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <div class="vip-panel">
            <h2>تفاصيل مهمة قبل الطلب</h2>
            <ul class="clean-list">
                <li>يتم توضيح حالة الخدمة قبل التنفيذ.</li>
                <li>يتم تحديد المتطلبات والمدة المتوقعة حسب نوع الخدمة.</li>
                <li>الخدمات الحساسة مثل التوثيق والحسابات الجاهزة تحتاج مراجعة قبل الدفع.</li>
                <li>يتم تنظيم خطوات التسليم وحفظ حقوق الطرفين قدر الإمكان.</li>
            </ul>
        </div>
    </div>
</section>

<?php require_once "footer.php"; ?>
