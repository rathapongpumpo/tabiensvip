<?php
declare(strict_types=1);
$plate = $plate ?? [];
$plateImage = (string) ($plate['image'] ?? '');
if ($plateImage !== '' && preg_match('/\.jpe?g$/i', $plateImage)) {
    $webpCandidate = preg_replace('/\.jpe?g$/i', '.webp', $plateImage);
    if ($webpCandidate && is_file(dirname(__DIR__) . '/' . $webpCandidate)) {
        $plateImage = $webpCandidate;
    }
}
$plateImageWidth = 720;
$plateImageHeight = 614;
if ($plateImage !== '' && is_file(dirname(__DIR__) . '/' . $plateImage)) {
    $plateImageSize = @getimagesize(dirname(__DIR__) . '/' . $plateImage);
    if ($plateImageSize) {
        [$plateImageWidth, $plateImageHeight] = $plateImageSize;
    }
}
?>
<article class="plate-card">
    <div class="plate-card-top">
        <?php if (!empty($plate['featured'])): ?><span class="featured-badge">แนะนำ</span><?php endif; ?>
        <span class="status-badge status-<?= e($plate['status']) ?>"><?= e(status_label($plate['status'])) ?></span>
        <?php if (!empty($plate['image'])): ?>
            <img class="plate-upload" src="/<?= e($plateImage) ?>" width="<?= (int) $plateImageWidth ?>" height="<?= (int) $plateImageHeight ?>" loading="lazy" decoding="async" alt="ทะเบียน <?= e($plate['prefix'] . ' ' . $plate['number']) ?>">
        <?php else: ?>
            <div class="license-plate" aria-label="ทะเบียน <?= e($plate['prefix'] . ' ' . $plate['number'] . ' ' . $plate['province']) ?>">
                <div><span><?= e($plate['prefix']) ?></span> <strong><?= e($plate['number']) ?></strong></div>
                <small><?= e($plate['province']) ?></small>
            </div>
        <?php endif; ?>
    </div>
    <div class="plate-card-body">
        <div class="plate-meta"><?= e($plate['category']) ?> · <?= e($plate['province']) ?></div>
        <h3><?= e($plate['prefix'] . ' ' . $plate['number']) ?></h3>
        <div class="price"><?= baht($plate['price']) ?></div>
        <a class="btn btn-outline btn-block" href="/plate/<?= (int) $plate['id'] ?>">ดูรายละเอียด</a>
    </div>
</article>
