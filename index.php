<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$pdo = db();
$requestPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$page = $_GET['page'] ?? 'home';

if ($requestPath === 'plates') {
    $page = 'plates';
} elseif (preg_match('#^plate/(\d+)$#', $requestPath, $routeMatch)) {
    $page = 'detail';
    $_GET['id'] = $routeMatch[1];
}

if ($requestPath === 'sitemap.xml') {
    $platesForSitemap = $pdo->query(
        'SELECT id, updated_at FROM plates WHERE status != "hidden" ORDER BY id'
    )->fetchAll();
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    echo '<url><loc>https://tabiensvip.com/</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>';
    echo '<url><loc>https://tabiensvip.com/plates</loc><changefreq>daily</changefreq><priority>0.9</priority></url>';
    foreach ($platesForSitemap as $sitemapPlate) {
        echo '<url><loc>https://tabiensvip.com/plate/' . (int) $sitemapPlate['id'] . '</loc>';
        echo '<lastmod>' . e(date('Y-m-d', strtotime($sitemapPlate['updated_at']))) . '</lastmod>';
        echo '<changefreq>weekly</changefreq><priority>0.7</priority></url>';
    }
    echo '</urlset>';
    exit;
}

if ($requestPath === 'index.php' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $legacyPage = $_GET['page'] ?? 'home';
    $destination = match ($legacyPage) {
        'plates' => '/plates',
        'detail' => '/plate/' . (int) ($_GET['id'] ?? 0),
        default => '/',
    };
    $query = $_GET;
    unset($query['page'], $query['id']);
    redirect($destination . ($query ? '?' . http_build_query($query) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserve') {
    verify_csrf();
    $plateId = filter_var($_POST['plate_id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string) ($_POST['customer_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $lineId = trim((string) ($_POST['line_id'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));

    $stmt = $pdo->prepare('SELECT id FROM plates WHERE id = ? AND status = "available"');
    $stmt->execute([$plateId]);
    if (!$stmt->fetch() || $name === '' || $phone === '') {
        $_SESSION['flash_error'] = 'กรุณากรอกชื่อและเบอร์โทร หรือรายการนี้อาจไม่พร้อมขายแล้ว';
        redirect('/plate/' . (int) $plateId . '#reserve');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO reservations (plate_id, customer_name, phone, line_id, note) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$plateId, $name, $phone, $lineId, $note]);
    $_SESSION['flash_success'] = 'รับคำขอของคุณแล้ว เจ้าหน้าที่จะติดต่อกลับโดยเร็วที่สุด';
    redirect('/plate/' . (int) $plateId . '#reserve');
}

if ($page === 'detail') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('SELECT * FROM plates WHERE id = ? AND status != "hidden"');
    $stmt->execute([$id]);
    $plate = $stmt->fetch();
    if (!$plate) {
        http_response_code(404);
        $pageTitle = 'ไม่พบทะเบียน | ' . APP_NAME;
        $pageDescription = 'ไม่พบรายการป้ายทะเบียนที่ต้องการ กรุณาเลือกชมป้ายทะเบียนประมูลภูเก็ตและทะเบียนเลขสวยรายการอื่น';
        $robotsContent = 'noindex,follow';
        require __DIR__ . '/partials/header.php';
        echo '<section class="empty-state"><div class="container"><h1>ไม่พบทะเบียนที่ต้องการ</h1><a class="btn btn-primary" href="/plates">ดูทะเบียนทั้งหมด</a></div></section>';
        require __DIR__ . '/partials/footer.php';
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM plates WHERE id != ? AND status = "available" AND category = ? ORDER BY featured DESC, id DESC LIMIT 3');
    $stmt->execute([$id, $plate['category']]);
    $similar = $stmt->fetchAll();

    $plateName = $plate['prefix'] . ' ' . $plate['number'];
    $detailImage = (string) ($plate['image'] ?? '');
    if ($detailImage !== '' && preg_match('/\.jpe?g$/i', $detailImage)) {
        $detailWebp = preg_replace('/\.jpe?g$/i', '.webp', $detailImage);
        if ($detailWebp && is_file(__DIR__ . '/' . $detailWebp)) {
            $detailImage = $detailWebp;
        }
    }
    $detailImageSize = $detailImage !== '' && is_file(__DIR__ . '/' . $detailImage)
        ? @getimagesize(__DIR__ . '/' . $detailImage)
        : false;
    $pageTitle = 'ป้ายทะเบียน ' . $plateName . ' ' . $plate['province'] . ' ราคา ' . number_format((float) $plate['price']) . ' บาท';
    $pageDescription = 'ซื้อป้ายทะเบียน ' . $plateName . ' จังหวัด' . $plate['province'] . ' ราคา ' . number_format((float) $plate['price']) . ' บาท พร้อมบริการด้านทะเบียนและดูแลเอกสารครบขั้นตอน';
    $canonicalPath = '/plate/' . (int) $plate['id'];
    $pageOgType = 'product';
    $ogImage = !empty($plate['image']) ? 'https://tabiensvip.com/' . $plate['image'] : 'https://tabiensvip.com/image/1.jpg';
    $availability = $plate['status'] === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    $structuredData = [[
        '@type' => 'Product',
        '@id' => 'https://tabiensvip.com/plate/' . (int) $plate['id'] . '#product',
        'name' => 'ป้ายทะเบียน ' . $plateName . ' ' . $plate['province'],
        'image' => [$ogImage],
        'description' => $pageDescription,
        'category' => $plate['category'],
        'offers' => [
            '@type' => 'Offer',
            'url' => 'https://tabiensvip.com/plate/' . (int) $plate['id'],
            'priceCurrency' => 'THB',
            'price' => (float) $plate['price'],
            'availability' => $availability,
            'seller' => ['@id' => 'https://tabiensvip.com/#organization'],
        ],
    ]];
    $activePage = 'plates';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="page-hero page-hero-small">
        <div class="container">
            <div class="breadcrumbs"><a href="/">หน้าแรก</a> / <a href="/plates">ทะเบียนทั้งหมด</a> / <?= e($plate['prefix'] . ' ' . $plate['number']) ?></div>
            <h1>ทะเบียน <?= e($plate['prefix'] . ' ' . $plate['number']) ?></h1>
        </div>
    </section>
    <section class="section">
        <div class="container detail-grid">
            <div class="detail-visual">
                <?php if (!empty($plate['image'])): ?>
                    <img src="/<?= e($detailImage) ?>" width="<?= (int) ($detailImageSize[0] ?? 720) ?>" height="<?= (int) ($detailImageSize[1] ?? 614) ?>" fetchpriority="high" decoding="async" alt="ทะเบียน <?= e($plate['prefix'] . ' ' . $plate['number']) ?>">
                <?php else: ?>
                    <div class="license-plate license-plate-large">
                        <div><span><?= e($plate['prefix']) ?></span> <strong><?= e($plate['number']) ?></strong></div>
                        <small><?= e($plate['province']) ?></small>
                    </div>
                <?php endif; ?>
            </div>
            <div class="detail-info">
                <div class="plate-meta"><?= e($plate['category']) ?> · <?= e($plate['province']) ?></div>
                <h2><?= e($plate['prefix'] . ' ' . $plate['number']) ?></h2>
                <div class="detail-price"><?= baht($plate['price']) ?></div>
                <div class="detail-status status-<?= e($plate['status']) ?>"><?= e(status_label($plate['status'])) ?></div>
                <p><?= e($plate['description']) ?></p>
                <?php if ($plate['meaning'] !== ''): ?>
                    <div class="meaning-box"><strong>ความหมายของเลข</strong><p><?= e($plate['meaning']) ?></p></div>
                <?php endif; ?>
                <div class="detail-actions">
                    <a class="btn btn-primary" href="#reserve">ขอจองทะเบียน</a>
                    <a class="btn btn-line" href="https://line.me/R/ti/p/@mynameistabien" target="_blank" rel="noopener">สอบถามทาง LINE</a>
                </div>
                <p class="fine-print">การส่งคำขอจองยังไม่ถือเป็นการยืนยันการซื้อ เจ้าหน้าที่จะติดต่อกลับเพื่อตรวจสอบสถานะและแจ้งขั้นตอนอีกครั้ง</p>
            </div>
        </div>
    </section>
    <section class="section section-soft" id="reserve">
        <div class="container reserve-wrap">
            <div>
                <span class="eyebrow">สำรองทะเบียนที่คุณชอบ</span>
                <h2>ส่งคำขอจอง <?= e($plate['prefix'] . ' ' . $plate['number']) ?></h2>
                <p>กรอกข้อมูลติดต่อ เจ้าหน้าที่จะโทรกลับเพื่อยืนยันสถานะ ราคา และขั้นตอนเอกสาร</p>
            </div>
            <form class="reserve-form" method="post">
                <?php if (!empty($_SESSION['flash_success'])): ?><div class="flash flash-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
                <?php if (!empty($_SESSION['flash_error'])): ?><div class="flash flash-error"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="plate_id" value="<?= (int) $plate['id'] ?>">
                <label>ชื่อ–นามสกุล <span>*</span><input type="text" name="customer_name" required autocomplete="name"></label>
                <label>เบอร์โทรศัพท์ <span>*</span><input type="tel" name="phone" required autocomplete="tel"></label>
                <label>LINE ID<input type="text" name="line_id"></label>
                <label>ข้อความเพิ่มเติม<textarea name="note" rows="3" placeholder="ช่วงเวลาที่สะดวกให้ติดต่อกลับ"></textarea></label>
                <button class="btn btn-primary btn-block" type="submit" <?= $plate['status'] !== 'available' ? 'disabled' : '' ?>>
                    <?= $plate['status'] === 'available' ? 'ส่งคำขอจอง' : 'ทะเบียนนี้ยังไม่พร้อมจอง' ?>
                </button>
            </form>
        </div>
    </section>
    <?php if ($similar): ?>
    <section class="section">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">คุณอาจสนใจ</span><h2>ทะเบียนใกล้เคียง</h2></div></div>
            <div class="plate-grid">
                <?php foreach ($similar as $plate): require __DIR__ . '/partials/plate-card.php'; endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif;
    require __DIR__ . '/partials/footer.php';
    exit;
}

if ($page === 'plates') {
    $keyword = trim((string) ($_GET['q'] ?? ''));
    $category = trim((string) ($_GET['category'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $featured = ($_GET['featured'] ?? '') === '1';
    $sort = $_GET['sort'] ?? 'newest';

    $where = ['status != "hidden"'];
    $params = [];
    if ($keyword !== '') {
        $joinedPlate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'CONCAT(prefix, number)'
            : '(prefix || number)';
        $where[] = '(prefix LIKE ? OR number LIKE ? OR province LIKE ? OR ' . $joinedPlate . ' LIKE ?)';
        $term = '%' . $keyword . '%';
        array_push($params, $term, $term, $term, $term);
    }
    if ($category !== '') {
        $where[] = 'category = ?';
        $params[] = $category;
    }
    if (in_array($status, ['available', 'reserved', 'sold'], true)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if ($featured) {
        $where[] = 'featured = 1';
    }
    $order = match ($sort) {
        'price_asc' => 'price ASC',
        'price_desc' => 'price DESC',
        default => 'display_order ASC, featured DESC, id DESC',
    };
    $stmt = $pdo->prepare('SELECT * FROM plates WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order);
    $stmt->execute($params);
    $plates = $stmt->fetchAll();
    $categories = $pdo->query('SELECT DISTINCT category FROM plates ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

    $pageTitle = 'ป้ายทะเบียนประมูลภูเก็ต ราคาคุ้มค่า | MY NAME IS TABIEN';
    $pageDescription = 'รวมป้ายทะเบียนประมูลภูเก็ตและทะเบียนเลขสวย พร้อมราคา สถานะ และบริการด้านทะเบียนครบวงจร ค้นหาตามเลข หมวดหมู่ และงบประมาณได้ทันที';
    $canonicalPath = '/plates';
    if ($keyword !== '' || $category !== '' || $status !== '' || $featured || $sort !== 'newest') {
        $robotsContent = 'noindex,follow';
    }
    $activePage = 'plates';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow">ค้นหาเลขที่ใช่สำหรับคุณ</span>
            <h1><?= $featured ? 'ทะเบียนแนะนำ' : 'ทะเบียนสวยทั้งหมด' ?></h1>
            <p>คัดสรรทะเบียนเลขสวย เลขมงคล และเลขยอดนิยม พร้อมราคาและสถานะล่าสุด</p>
        </div>
    </section>
    <section class="section catalog-section">
        <div class="container">
            <form class="filter-bar" action="/plates" method="get">
                <?php if ($featured): ?><input type="hidden" name="featured" value="1"><?php endif; ?>
                <label class="search-field"><span>ค้นหาทะเบียน</span><input type="search" name="q" value="<?= e($keyword) ?>" placeholder="เช่น 88, ขล 88 หรือ ภูเก็ต"></label>
                <label><span>หมวดหมู่</span><select name="category"><option value="">ทั้งหมด</option><?php foreach ($categories as $item): ?><option value="<?= e($item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
                <label><span>สถานะ</span><select name="status"><option value="">ทุกสถานะ</option><option value="available" <?= $status === 'available' ? 'selected' : '' ?>>พร้อมขาย</option><option value="reserved" <?= $status === 'reserved' ? 'selected' : '' ?>>ติดจอง</option><option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>ขายแล้ว</option></select></label>
                <label><span>เรียงตาม</span><select name="sort"><option value="newest">แนะนำ / มาใหม่</option><option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ราคาน้อยไปมาก</option><option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>ราคามากไปน้อย</option></select></label>
                <button class="btn btn-primary" type="submit">ค้นหา</button>
            </form>
            <div class="result-heading"><strong>พบ <?= count($plates) ?> รายการ</strong><?php if ($keyword || $category || $status): ?><a href="/plates">ล้างตัวกรอง</a><?php endif; ?></div>
            <?php if ($plates): ?>
                <div class="plate-grid"><?php foreach ($plates as $plate): require __DIR__ . '/partials/plate-card.php'; endforeach; ?></div>
            <?php else: ?>
                <div class="empty-state"><h2>ยังไม่พบทเบียนที่ตรงกับการค้นหา</h2><p>ลองเปลี่ยนเลข ช่วงราคา หรือหมวดหมู่ดูอีกครั้ง</p></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$homeProducts = $pdo->query(
    'SELECT * FROM plates
     WHERE status != "hidden" AND image LIKE "image/435%_0.jpg"
     ORDER BY display_order ASC'
)->fetchAll();
$pageTitle = 'ป้ายทะเบียนประมูลภูเก็ตราคาคุ้มค่า | MY NAME IS TABIEN';
$pageDescription = 'ป้ายทะเบียนประมูลภูเก็ตราคาถูกและคุ้มค่า พร้อมบริการด้านทะเบียน ซื้อขาย ฝากขาย วิเคราะห์เลขมงคล และดูแลเอกสารโดยทีมงานมืออาชีพ';
$canonicalPath = '/';
$activePage = 'home';
$homeFaq = [
    ['question' => 'ป้ายทะเบียนประมูลภูเก็ตราคาเริ่มต้นเท่าไร?', 'answer' => 'ราคาขึ้นอยู่กับหมวดอักษร รูปแบบตัวเลข และความนิยม โดยหน้าเว็บไซต์แสดงราคาของแต่ละรายการอย่างชัดเจนเพื่อให้เปรียบเทียบได้ง่าย'],
    ['question' => 'มีบริการด้านทะเบียนอะไรบ้าง?', 'answer' => 'เราดูแลการซื้อขาย ฝากขาย จัดหาเลข วิเคราะห์เลขมงคล และให้คำปรึกษาเรื่องเอกสารทะเบียนครบทุกขั้นตอน'],
    ['question' => 'เลือกป้ายทะเบียนราคาถูกและคุ้มค่าอย่างไร?', 'answer' => 'กำหนดงบประมาณ เลือกรูปแบบเลขที่ต้องการ แล้วใช้ตัวกรองราคาและหมวดหมู่เพื่อเปรียบเทียบรายการที่พร้อมขาย'],
];
$structuredData = [[
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn(array $item): array => [
        '@type' => 'Question',
        'name' => $item['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $item['answer'],
        ],
    ], $homeFaq),
]];
require __DIR__ . '/partials/header.php';
?>
<section class="visual-hero" id="welcome">
    <picture>
        <source media="(max-width: 768px)" srcset="/image/hero-768.webp" type="image/webp">
        <source srcset="/image/hero-1280.webp" type="image/webp">
        <img src="/image/1.jpg" width="1536" height="1024" fetchpriority="high" decoding="async" alt="ป้ายทะเบียนประมูลภูเก็ต เมืองเก่าภูเก็ต และรถสปอร์ต">
    </picture>
</section>
<section class="welcome-strip" id="intro">
    <div class="container welcome-inner reveal">
        <div>
            <span class="eyebrow">MY NAME IS TABIEN CO.,LTD.</span>
            <h1>ป้ายทะเบียนประมูลภูเก็ต<br>ที่สะท้อนตัวตนของคุณ</h1>
        </div>
        <p>ซื้อ–ขายทะเบียนประมูลภูเก็ต วิเคราะห์เลขมงคล ฝากขาย และดูแลเอกสารครบทุกขั้นตอน</p>
        <div class="hero-cta">
            <a class="btn btn-primary" href="#products">เลือกชมทะเบียน</a>
            <a class="btn btn-glass" href="#contact">ปรึกษาเรา</a>
        </div>
    </div>
</section>

<section class="section services-section" id="services">
    <div class="container">
        <div class="section-heading center reveal"><div><span class="eyebrow">OUR SERVICES</span><h2>บริการครบ จบในที่เดียว</h2><p>ทุกขั้นตอนดูแลโดยทีมงานที่เข้าใจทะเบียนประมูลภูเก็ต</p></div></div>
        <div class="service-grid">
            <article class="service-card pastel-blue reveal"><span>01</span><h3>ซื้อ–ขายทะเบียน</h3><p>คัดสรรเลขสวย พร้อมราคาและสถานะชัดเจน</p></article>
            <article class="service-card pastel-pink reveal"><span>02</span><h3>จัดหาเลขเฉพาะ</h3><p>ช่วยค้นหาเลขที่เหมาะกับบุคลิกและรถของคุณ</p></article>
            <article class="service-card pastel-purple reveal"><span>03</span><h3>วิเคราะห์เลขมงคล</h3><p>แนะนำความหมายและพลังของตัวเลขอย่างเหมาะสม</p></article>
            <article class="service-card pastel-mint reveal"><span>04</span><h3>ดูแลเอกสาร</h3><p>ให้คำปรึกษาและประสานงานจนเสร็จสมบูรณ์</p></article>
            <article class="service-card pastel-gold reveal"><span>05</span><h3>ฝากขายทะเบียน</h3><p>ประเมินราคาและนำเสนอทะเบียนอย่างมืออาชีพ</p></article>
        </div>
    </div>
</section>

<section class="section seo-guide-section" aria-labelledby="seo-guide-title">
    <div class="container seo-guide-wrap">
        <div class="seo-guide-intro reveal">
            <span class="eyebrow">PHUKET REGISTRATION GUIDE</span>
            <h2 id="seo-guide-title">ป้ายทะเบียนประมูลภูเก็ตราคาถูกและคุ้มค่า เลือกอย่างไร?</h2>
            <p>เราแสดงราคาและสถานะของทะเบียนแต่ละรายการอย่างชัดเจน ช่วยให้คุณเปรียบเทียบเลขสวยตามงบประมาณได้ง่าย พร้อมบริการด้านทะเบียนครบตั้งแต่เลือกเลข ฝากขาย วิเคราะห์ความหมาย ไปจนถึงคำแนะนำเรื่องเอกสาร</p>
            <a class="text-link" href="/plates?sort=price_asc">ดูทะเบียนเรียงจากราคาน้อยไปมาก →</a>
        </div>
        <div class="faq-list reveal">
            <?php foreach ($homeFaq as $index => $faq): ?>
                <details <?= $index === 0 ? 'open' : '' ?>>
                    <summary><?= e($faq['question']) ?></summary>
                    <p><?= e($faq['answer']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="editorial-section" id="why">
    <div class="container editorial-card editorial-dark reveal">
        <div class="editorial-image">
            <img src="/image/3.webp" width="960" height="640" loading="lazy" decoding="async" alt="ทิวทัศน์แหลมพรหมเทพ จังหวัดภูเก็ต">
        </div>
        <div class="editorial-copy">
            <span class="editorial-kicker">WHY PREMIUM PLATES</span>
            <h2>มากกว่าตัวเลข<br>คือเอกลักษณ์</h2>
            <p>ทะเบียนที่ดีทำให้รถเป็นที่จดจำ สะท้อนรสนิยม และมีคุณค่าได้ในระยะยาว</p>
            <div class="editorial-points">
                <span>ถูกต้องตามกฎหมาย</span>
                <span>สะท้อนตัวตน</span>
                <span>เพิ่มเอกลักษณ์ให้รถ</span>
            </div>
        </div>
    </div>
</section>

<section class="editorial-section editorial-section-alt" id="lucky">
    <div class="container editorial-card editorial-light reveal">
        <div class="editorial-copy">
            <span class="editorial-kicker">LUCKY NUMBER</span>
            <h2>เลขที่ใช่<br>ในทุกเส้นทาง</h2>
            <p>คัดเลือกจากความหมายของตัวเลข บุคลิก และภาพลักษณ์ที่คุณต้องการ</p>
            <div class="editorial-points">
                <span>โชคลาภ</span>
                <span>ธุรกิจ</span>
                <span>ความมั่งคั่ง</span>
            </div>
        </div>
        <div class="editorial-image">
            <img src="/image/5.webp" width="960" height="639" loading="lazy" decoding="async" alt="พระพุทธมิ่งมงคลเอกนาคคีรี จังหวัดภูเก็ต">
        </div>
    </div>
</section>

<section class="section products-section" id="products">
    <div class="container">
        <div class="section-heading reveal">
            <div><span class="eyebrow">AVAILABLE NOW</span><h2>ทะเบียนพร้อมขาย</h2><p>เรียงลำดับและราคาอ้างอิงตามรายการที่ได้รับ</p></div>
            <a class="text-link" href="/plates">ค้นหาทั้งหมด →</a>
        </div>
        <div class="plate-grid product-grid">
            <?php foreach ($homeProducts as $plate): require __DIR__ . '/partials/plate-card.php'; endforeach; ?>
        </div>
    </div>
</section>

<section class="section reviews-section" id="reviews">
    <div class="container">
        <div class="section-heading center reveal"><div><span class="eyebrow">CLIENT STORIES</span><h2>ความประทับใจจากลูกค้า</h2></div></div>
        <div class="review-grid">
            <article class="review-card reveal"><div class="stars">★★★★★</div><p>“ทีมงานแนะนำดีมาก ได้เลขที่ชอบและช่วยดูแลเรื่องเอกสารครบทุกขั้นตอน”</p><strong>คุณเอ · เจ้าของธุรกิจ</strong></article>
            <article class="review-card featured-review reveal"><div class="stars">★★★★★</div><p>“บริการเป็นมืออาชีพ ราคาแจ้งชัดเจน ติดต่อสะดวก และส่งมอบตามที่ตกลง”</p><strong>คุณพี · ภูเก็ต</strong></article>
            <article class="review-card reveal"><div class="stars">★★★★★</div><p>“ชอบการออกแบบและการนำเสนอทะเบียนมาก เลือกดูง่ายและได้เลขมงคลตรงใจ”</p><strong>คุณเอ็ม · กรุงเทพมหานคร</strong></article>
        </div>
    </div>
</section>

<section class="contact-section" id="contact">
    <div class="container contact-card reveal">
        <div>
            <span class="eyebrow">LET’S FIND YOUR NUMBER</span>
            <h2>ให้เราช่วยค้นหา<br>ทะเบียนที่เป็นคุณ</h2>
            <p>MY NAME IS TABIEN CO.,LTD.<br>พร้อมให้คำปรึกษาทุกวัน 09:00–18:00 น.</p>
        </div>
        <div class="contact-actions">
            <a href="tel:0898888888"><small>โทรศัพท์</small><strong>089-888-8888</strong></a>
            <a href="https://line.me/R/ti/p/@mynameistabien" target="_blank" rel="noopener"><small>LINE Official</small><strong>@mynameistabien</strong></a>
            <a href="mailto:hello@mynameistabien.com"><small>อีเมล</small><strong>hello@mynameistabien.com</strong></a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
