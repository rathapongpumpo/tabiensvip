<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ค้นหาและเลือกซื้อทะเบียนรถสวยภูเก็ต ทะเบียนเลขมงคล เลขคู่ เลขตอง และเลขเรียง">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&family=Pridi:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<a class="skip-link" href="#main-content">ข้ามไปยังเนื้อหา</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="/" aria-label="หน้าแรก">
            <img class="brand-logo" src="/image/logo_บริษัท.jpg" alt="">
            <span><strong>MY NAME IS TABIEN</strong><small>CO.,LTD. · PHUKET</small></span>
        </a>
        <button class="nav-toggle" type="button" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="main-navigation">☰</button>
        <nav class="main-nav" id="main-navigation" aria-label="เมนูหลัก">
            <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="/">หน้าแรก</a>
            <a href="/#services">บริการ</a>
            <a href="/#why">เรื่องทะเบียน</a>
            <a class="<?= $activePage === 'plates' ? 'active' : '' ?>" href="/plates">ทะเบียนทั้งหมด</a>
            <a href="/#reviews">รีวิว</a>
            <a href="/#contact">ติดต่อ</a>
        </nav>
    </div>
</header>
<main id="main-content">
