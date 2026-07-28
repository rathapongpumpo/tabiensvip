<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$pageDescription = $pageDescription ?? 'ป้ายทะเบียนประมูลภูเก็ต ราคาคุ้มค่า พร้อมบริการซื้อขาย ฝากขาย วิเคราะห์เลขมงคล และดูแลเอกสารทะเบียนครบทุกขั้นตอน';
$canonicalPath = $canonicalPath ?? (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$canonicalPath = $canonicalPath === '/' ? '/' : rtrim($canonicalPath, '/');
$canonicalUrl = 'https://tabiensvip.com' . $canonicalPath;
$robotsContent = $robotsContent ?? 'index,follow,max-image-preview:large';
$pageOgType = $pageOgType ?? 'website';
$ogImage = $ogImage ?? 'https://tabiensvip.com/image/1.jpg';
$structuredData = $structuredData ?? [];
$structuredData[] = [
    '@type' => 'Organization',
    '@id' => 'https://tabiensvip.com/#organization',
    'name' => 'MY NAME IS TABIEN CO.,LTD.',
    'alternateName' => 'มายเนมอิสทะเบียน',
    'url' => 'https://tabiensvip.com/',
    'logo' => 'https://tabiensvip.com/image/logo_บริษัท.jpg',
];
if ($activePage === 'home') {
    $structuredData[] = [
        '@type' => 'WebSite',
        '@id' => 'https://tabiensvip.com/#website',
        'url' => 'https://tabiensvip.com/',
        'name' => 'MY NAME IS TABIEN',
        'alternateName' => 'ป้ายทะเบียนประมูลภูเก็ต',
        'publisher' => ['@id' => 'https://tabiensvip.com/#organization'],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => 'https://tabiensvip.com/plates?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="<?= e($robotsContent) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:locale" content="th_TH">
    <meta property="og:type" content="<?= e($pageOgType) ?>">
    <meta property="og:site_name" content="MY NAME IS TABIEN">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="/image/logo_บริษัท.jpg" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&family=Pridi:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/style.css') ?>">
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@graph' => $structuredData,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
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
