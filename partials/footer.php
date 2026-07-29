<?php declare(strict_types=1); ?>
</main>
<nav class="mobile-quick-actions" aria-label="ทางลัด">
    <a class="btn btn-primary" href="/plates">เลือกชมทะเบียน</a>
    <a class="btn btn-glass" href="/#contact">ปรึกษาเรา</a>
</nav>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand brand-light" href="/">
                <img class="brand-logo" src="/image/logo-240.webp" width="240" height="160" loading="lazy" decoding="async" alt="">
                <span><strong>MY NAME IS TABIEN</strong><small>CO.,LTD. · PHUKET</small></span>
            </a>
            <p>คัดสรรทะเบียนสวยสำหรับรถคันพิเศษ พร้อมให้คำปรึกษาและดูแลเอกสารครบทุกขั้นตอน</p>
        </div>
        <div>
            <h3>ติดต่อเรา</h3>
            <p>โทร: <a href="tel:0898888888">089-888-8888</a><br>LINE: <a href="https://line.me/R/ti/p/@mynameistabien" target="_blank" rel="noopener">@mynameistabien</a><br>เปิดบริการทุกวัน 09:00–18:00 น.</p>
        </div>
        <div>
            <h3>เมนูลัด</h3>
            <a href="/plates">ทะเบียนทั้งหมด</a>
            <a href="/#services">บริการของเรา</a>
            <a href="/admin">เข้าสู่ระบบแอดมิน</a>
        </div>
    </div>
    <div class="container copyright">© <?= date('Y') ?> MY NAME IS TABIEN CO.,LTD.</div>
    <div class="impact-site-verification">Impact-Site-Verification: a9efd229-dfee-4ef7-850a-42ef4e0da638</div>
</footer>
<script src="/assets/app.js?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/app.js') ?>" defer></script>
</body>
</html>
