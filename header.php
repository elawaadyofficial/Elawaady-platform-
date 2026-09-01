<?php
require_once __DIR__ . '/lib/auth.php';
auth_boot();

if (!isset($page_title)) {
    $page_title = "Elawaady XDigital Platform";
}

$exd_account = auth_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#09010f">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="Elawaady XDigital Platform - متجر خدمات ومنتجات رقمية، اشتراكات، ذكاء اصطناعي، سوشيال ميديا، توثيق، بطاقات وألعاب.">
    <link rel="preload" href="assets/fonts/TheYearofHandicrafts-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/TheYearofHandicrafts-Black.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/brand/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/brand/favicon-180.png">
    <script>document.documentElement.classList.add("exd-js");</script>
    <link rel="stylesheet" href="exd-tokens.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="storefront.css">
    <link rel="stylesheet" href="exd-media.css">
    <link rel="stylesheet" href="exd-sections.css">
    <link rel="stylesheet" href="exd-layouts.css">
    <link rel="stylesheet" href="exd-banner.css">
    <link rel="stylesheet" href="exd-account.css">
    <link rel="stylesheet" href="exd-commerce.css">
    <link rel="stylesheet" href="motion.css">
    <link rel="stylesheet" href="exd-interaction.css">
</head>
<body>
<div class="announcement-bar">
    <div class="container announcement-inner">
        <span>⚡ خدمات رقمية مختارة بعناية ودعم سريع</span>
        <span class="desktop-only">Elawaady XDigital — كل احتياجاتك الرقمية في مكان واحد</span>
    </div>
</div>

<header class="site-header">
    <div class="container header-main">
        <a class="brand" href="index.php" aria-label="Elawaady XDigital">
            <span class="brand-mark">
                <img src="assets/brand/exd-logo-official.webp" alt="EXD" width="822" height="860" fetchpriority="high" decoding="async">
            </span>
            <span class="brand-copy">
                <strong>Elawaady XDigital</strong>
                <small>Digital Store & Services</small>
            </span>
        </a>

        <form class="header-search" action="search.php" method="get" role="search">
            <input type="search" name="q" placeholder="ابحث عن خدمة، اشتراك، منصة..." aria-label="بحث">
            <button type="submit" aria-label="بحث">⌕</button>
        </form>

        <div class="header-actions">
            <a class="header-action" href="contact.php"><span>◉</span><b>الدعم</b></a>
            <a class="header-action" href="search.php"><span>⌕</span><b>بحث</b></a>
<?php if ($exd_account !== null): ?>
            <a class="header-action header-action--account" href="<?= $exd_account['account_type'] === 'supplier' ? 'supplier-dashboard.php' : 'account.php' ?>">
                <span>◆</span><b><?= e(mb_strimwidth((string) $exd_account['name'], 0, 14, '…')) ?></b>
            </a>
<?php else: ?>
            <a class="header-action header-action--account" href="login.php"><span>◆</span><b>حسابي</b></a>
<?php endif; ?>
            <button class="menu-toggle" type="button" aria-label="فتح القائمة">☰</button>
        </div>
    </div>

    <div class="nav-shell">
        <div class="container nav-wrap">
            <nav class="main-nav" aria-label="القائمة الرئيسية">
                <a class="nav-home" href="index.php">الرئيسية</a>
                <a href="categories.php">كل الأقسام</a>
                <a href="categories.php">الاشتراكات الرقمية</a>
                <a href="categories.php">الذكاء الاصطناعي</a>
                <a href="categories.php">السوشيال ميديا</a>
                <a href="categories.php">الألعاب والبطاقات</a>
                <a href="contact.php">تواصل معنا</a>
            </nav>
        </div>
    </div>
</header>

<main>
