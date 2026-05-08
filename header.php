<?php
if (!isset($page_title)) {
    $page_title = "Elawaady XDigital Platform";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="Elawaady XDigital Platform - متجر خدمات رقمية، سوشيال ميديا، توثيق، حسابات جاهزة، AI، Microsoft 365، اشتراكات، وساطة آمنة وماركت بليس.">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php">
            <span class="brand-mark">XD</span>
            <span>
                <strong>Elawaady</strong>
                <small>XDigital Platform</small>
            </span>
        </a>

        <button class="menu-toggle" aria-label="فتح القائمة">☰</button>

        <nav class="main-nav">
            <a href="index.php">الرئيسية</a>
            <a href="categories.php">الأقسام</a>
            <a href="search.php">بحث</a>
            <a href="contact.php">تواصل</a>
        </nav>
    </div>
</header>

<main>
