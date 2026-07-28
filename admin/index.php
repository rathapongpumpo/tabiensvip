<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';
$pdo = db();
$requestPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH), '/');
$adminRoute = preg_replace('#^admin/?#', '', $requestPath);

if ($requestPath === 'admin/index.php' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $legacySection = $_GET['section'] ?? 'dashboard';
    $destination = match ($legacySection) {
        'plates' => '/admin/plates',
        'plate-form' => !empty($_GET['id']) ? '/admin/plate/' . (int) $_GET['id'] : '/admin/plate/new',
        'reservations' => '/admin/reservations',
        'settings' => '/admin/settings',
        default => '/admin',
    };
    redirect($destination);
}

if ($adminRoute === 'logout' || isset($_GET['logout'])) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username']);
    redirect('/admin');
}

if (!is_admin()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            redirect('/admin');
        }
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
    ?>
    <!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>เข้าสู่ระบบแอดมิน</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/admin/admin.css"></head>
    <body class="login-body">
    <div class="login-card">
        <a class="admin-logo" href="/"><img src="/image/logo_บริษัท.jpg" alt="MY NAME IS TABIEN"><strong>MY NAME IS TABIEN<small>ADMINISTRATION</small></strong></a>
        <h1>เข้าสู่ระบบแอดมิน</h1><p>จัดการทะเบียน ราคา และคำขอจากลูกค้า</p>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>ชื่อผู้ใช้<input type="text" name="username" required autofocus autocomplete="username"></label>
            <label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="admin-btn" type="submit">เข้าสู่ระบบ</button>
        </form>
    </div></body></html>
    <?php
    exit;
}

$section = $_GET['section'] ?? match (true) {
    $adminRoute === 'plates' => 'plates',
    $adminRoute === 'reservations' => 'reservations',
    $adminRoute === 'settings' => 'settings',
    $adminRoute === 'plate/new' => 'plate-form',
    preg_match('#^plate/(\d+)$#', $adminRoute, $adminMatch) === 1 => 'plate-form',
    default => 'dashboard',
};
if (isset($adminMatch[1])) {
    $_GET['id'] = $adminMatch[1];
}

function handle_upload(?array $file, ?string $currentImage = null): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentImage;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ หรือไฟล์มีขนาดเกิน 5 MB');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('รองรับเฉพาะไฟล์ JPG, PNG และ WEBP');
    }
    $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        throw new RuntimeException('ไม่สามารถบันทึกรูปได้');
    }
    return UPLOAD_WEB_PATH . '/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_plate') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $current = null;
            if ($id) {
                $stmt = $pdo->prepare('SELECT image FROM plates WHERE id = ?');
                $stmt->execute([$id]);
                $current = $stmt->fetchColumn() ?: null;
            }
            $image = handle_upload($_FILES['image'] ?? null, $current);
            $data = [
                trim((string) $_POST['prefix']),
                trim((string) $_POST['number']),
                trim((string) $_POST['province']),
                max(0, (float) $_POST['price']),
                trim((string) $_POST['category']),
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['meaning'] ?? '')),
                $image,
                in_array($_POST['status'], ['available', 'reserved', 'sold', 'hidden'], true) ? $_POST['status'] : 'available',
                isset($_POST['featured']) ? 1 : 0,
            ];
            if ($data[0] === '' || $data[1] === '' || $data[2] === '') {
                throw new RuntimeException('กรุณากรอกหมวดอักษร เลขทะเบียน และจังหวัด');
            }
            if ($id) {
                $stmt = $pdo->prepare('UPDATE plates SET prefix=?, number=?, province=?, price=?, category=?, description=?, meaning=?, image=?, status=?, featured=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $data[] = $id;
                $stmt->execute($data);
                $_SESSION['admin_flash'] = 'บันทึกข้อมูลทะเบียนเรียบร้อยแล้ว';
            } else {
                $stmt = $pdo->prepare('INSERT INTO plates (prefix,number,province,price,category,description,meaning,image,status,featured) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute($data);
                $_SESSION['admin_flash'] = 'เพิ่มทะเบียนใหม่เรียบร้อยแล้ว';
            }
            redirect('/admin/plates');
        }
        if ($action === 'delete_plate') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare('DELETE FROM plates WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['admin_flash'] = 'ลบรายการทะเบียนแล้ว';
            redirect('/admin/plates');
        }
        if ($action === 'reservation_status') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $status = in_array($_POST['status'] ?? '', ['new', 'contacted', 'closed'], true) ? $_POST['status'] : 'new';
            $stmt = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            $_SESSION['admin_flash'] = 'อัปเดตสถานะคำขอแล้ว';
            redirect('/admin/reservations');
        }
        if ($action === 'change_password') {
            $password = (string) ($_POST['new_password'] ?? '');
            if (strlen($password) < 8) {
                throw new RuntimeException('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
            }
            $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            $_SESSION['admin_flash'] = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
            redirect('/admin/settings');
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = $e->getMessage();
        $errorDestination = match ($section) {
            'plates' => '/admin/plates',
            'plate-form' => !empty($_POST['id']) ? '/admin/plate/' . (int) $_POST['id'] : '/admin/plate/new',
            'reservations' => '/admin/reservations',
            'settings' => '/admin/settings',
            default => '/admin',
        };
        redirect($errorDestination);
    }
}

$stats = [
    'all' => (int) $pdo->query('SELECT COUNT(*) FROM plates')->fetchColumn(),
    'available' => (int) $pdo->query('SELECT COUNT(*) FROM plates WHERE status="available"')->fetchColumn(),
    'reserved' => (int) $pdo->query('SELECT COUNT(*) FROM plates WHERE status="reserved"')->fetchColumn(),
    'leads' => (int) $pdo->query('SELECT COUNT(*) FROM reservations WHERE status="new"')->fetchColumn(),
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ระบบจัดการ | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="admin-body">
<aside class="sidebar">
    <a class="admin-logo" href="/admin"><img src="/image/logo_บริษัท.jpg" alt="MY NAME IS TABIEN"><strong>MY NAME IS TABIEN<small>ADMINISTRATION</small></strong></a>
    <nav>
        <a class="<?= $section === 'dashboard' ? 'active' : '' ?>" href="/admin">ภาพรวม</a>
        <a class="<?= in_array($section, ['plates','plate-form'], true) ? 'active' : '' ?>" href="/admin/plates">จัดการทะเบียน</a>
        <a class="<?= $section === 'reservations' ? 'active' : '' ?>" href="/admin/reservations">คำขอจอง <?php if ($stats['leads']): ?><b><?= $stats['leads'] ?></b><?php endif; ?></a>
        <a class="<?= $section === 'settings' ? 'active' : '' ?>" href="/admin/settings">ตั้งค่าบัญชี</a>
    </nav>
    <div class="sidebar-bottom"><a href="/" target="_blank">เปิดหน้าร้าน ↗</a><a href="/admin/logout">ออกจากระบบ</a></div>
</aside>
<main class="admin-main">
    <header class="admin-topbar"><button class="menu-button" type="button" aria-label="เปิดเมนู" aria-expanded="false">☰</button><div class="topbar-title"><strong>ระบบจัดการหลังบ้าน</strong><span>MY NAME IS TABIEN CO., LTD.</span></div><div class="user-meta"><span class="user-avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['admin_username'], 0, 1))) ?></span><span><strong><?= e($_SESSION['admin_username']) ?></strong><small>ผู้ดูแลระบบ</small></span></div></header>
    <div class="admin-content">
        <?php if (!empty($_SESSION['admin_flash'])): ?><div class="alert success"><?= e($_SESSION['admin_flash']); unset($_SESSION['admin_flash']); ?></div><?php endif; ?>
        <?php if (!empty($_SESSION['admin_error'])): ?><div class="alert error"><?= e($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div><?php endif; ?>

        <?php if ($section === 'plates'): 
            $plates = $pdo->query('SELECT * FROM plates ORDER BY id DESC')->fetchAll(); ?>
            <div class="content-heading"><div><h1>จัดการทะเบียน</h1><p>เพิ่ม แก้ไขราคา รูปภาพ และสถานะการขาย</p></div><a class="admin-btn" href="/admin/plate/new">+ เพิ่มทะเบียน</a></div>
            <div class="panel table-panel"><table><thead><tr><th>ทะเบียน</th><th>หมวดหมู่</th><th>ราคา</th><th>สถานะ</th><th>แนะนำ</th><th></th></tr></thead><tbody>
            <?php foreach ($plates as $plate): ?><tr>
                <td><div class="plate-cell"><?php if ($plate['image']): ?><img class="plate-thumb" src="/<?= e($plate['image']) ?>" alt=""><?php else: ?><span class="plate-thumb plate-thumb-empty">—</span><?php endif; ?><span><strong><?= e($plate['prefix'].' '.$plate['number']) ?></strong><small><?= e($plate['province']) ?></small></span></div></td>
                <td><?= e($plate['category']) ?></td><td><?= baht($plate['price']) ?></td>
                <td><span class="table-status <?= e($plate['status']) ?>"><?= e(status_label($plate['status'])) ?></span></td>
                <td><?= $plate['featured'] ? '★' : '—' ?></td>
                <td class="row-actions"><a href="/admin/plate/<?= (int)$plate['id'] ?>">แก้ไข</a><form method="post" onsubmit="return confirm('ยืนยันการลบทะเบียนนี้?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_plate"><input type="hidden" name="id" value="<?= (int)$plate['id'] ?>"><button type="submit">ลบ</button></form></td>
            </tr><?php endforeach; ?></tbody></table></div>

        <?php elseif ($section === 'plate-form'):
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $plate = ['id'=>'','prefix'=>'','number'=>'','province'=>'ภูเก็ต','price'=>'','category'=>'เลขสวย','description'=>'','meaning'=>'','image'=>'','status'=>'available','featured'=>0];
            if ($id) { $stmt=$pdo->prepare('SELECT * FROM plates WHERE id=?'); $stmt->execute([$id]); $plate=$stmt->fetch() ?: $plate; }
            ?>
            <div class="content-heading"><div><h1><?= $id ? 'แก้ไขทะเบียน' : 'เพิ่มทะเบียนใหม่' ?></h1><p>กรอกข้อมูลที่จะแสดงในหน้าร้าน</p></div><a class="secondary-btn" href="/admin/plates">← กลับ</a></div>
            <form class="panel admin-form plate-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_plate"><input type="hidden" name="id" value="<?= (int)$plate['id'] ?>">
                <div class="form-grid">
                    <label>หมวดอักษร *<input name="prefix" value="<?= e($plate['prefix']) ?>" required placeholder="เช่น ขล"></label>
                    <label>เลขทะเบียน *<input name="number" value="<?= e($plate['number']) ?>" required placeholder="เช่น 88"></label>
                    <label>จังหวัด *<input name="province" value="<?= e($plate['province']) ?>" required></label>
                    <label>ราคา (บาท) *<input type="number" min="0" step="1" name="price" value="<?= e((string)$plate['price']) ?>" required></label>
                    <label>หมวดหมู่<input name="category" value="<?= e($plate['category']) ?>" list="category-list"><datalist id="category-list"><option value="เลขคู่"><option value="เลขตอง"><option value="เลขเรียง"><option value="เลขมงคล"><option value="เลขสี่ตัวซ้ำ"></datalist></label>
                    <label>สถานะ<select name="status"><option value="available" <?= $plate['status']==='available'?'selected':'' ?>>พร้อมขาย</option><option value="reserved" <?= $plate['status']==='reserved'?'selected':'' ?>>ติดจอง</option><option value="sold" <?= $plate['status']==='sold'?'selected':'' ?>>ขายแล้ว</option><option value="hidden" <?= $plate['status']==='hidden'?'selected':'' ?>>ซ่อนรายการ</option></select></label>
                    <label class="full">คำอธิบาย<textarea name="description" rows="3"><?= e($plate['description']) ?></textarea></label>
                    <label class="full">ความหมายของเลข<textarea name="meaning" rows="3"><?= e($plate['meaning']) ?></textarea></label>
                    <label class="full">รูปทะเบียน <small>JPG, PNG หรือ WEBP ไม่เกิน 5 MB</small><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><?php if($plate['image']): ?><img class="current-image" src="/<?= e($plate['image']) ?>" alt="รูปปัจจุบัน"><?php endif; ?></label>
                    <label class="checkbox full"><input type="checkbox" name="featured" value="1" <?= $plate['featured']?'checked':'' ?>> แสดงในส่วนทะเบียนแนะนำ</label>
                </div>
                <div class="form-actions"><a class="secondary-btn" href="/admin/plates">ยกเลิก</a><button class="admin-btn" type="submit">บันทึกข้อมูล</button></div>
            </form>

        <?php elseif ($section === 'reservations'):
            $reservations=$pdo->query('SELECT r.*,p.prefix,p.number,p.province,p.price FROM reservations r JOIN plates p ON p.id=r.plate_id ORDER BY r.id DESC')->fetchAll(); ?>
            <div class="content-heading"><div><h1>คำขอจอง</h1><p>ติดตามและอัปเดตการติดต่อกับลูกค้า</p></div></div>
            <div class="panel table-panel"><table><thead><tr><th>ลูกค้า</th><th>ทะเบียน</th><th>ติดต่อ</th><th>ข้อความ</th><th>วันที่</th><th>สถานะ</th></tr></thead><tbody>
            <?php if(!$reservations): ?><tr><td colspan="6" class="empty-cell">ยังไม่มีคำขอจอง</td></tr><?php endif; ?>
            <?php foreach($reservations as $item): ?><tr><td><strong><?= e($item['customer_name']) ?></strong></td><td><strong><?= e($item['prefix'].' '.$item['number']) ?></strong><small><?= baht($item['price']) ?></small></td><td><a href="tel:<?= e($item['phone']) ?>"><?= e($item['phone']) ?></a><small><?= $item['line_id']?'LINE: '.e($item['line_id']):'' ?></small></td><td class="note-cell"><?= e($item['note'] ?: '—') ?></td><td><?= e(date('d/m/Y H:i',strtotime($item['created_at']))) ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reservation_status"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><select name="status" onchange="this.form.submit()"><option value="new" <?= $item['status']==='new'?'selected':'' ?>>คำขอใหม่</option><option value="contacted" <?= $item['status']==='contacted'?'selected':'' ?>>ติดต่อแล้ว</option><option value="closed" <?= $item['status']==='closed'?'selected':'' ?>>ปิดรายการ</option></select></form></td></tr><?php endforeach; ?>
            </tbody></table></div>

        <?php elseif ($section === 'settings'): ?>
            <div class="content-heading"><div><h1>ตั้งค่าบัญชี</h1><p>เปลี่ยนรหัสผ่านสำหรับผู้ดูแลระบบ</p></div></div>
            <form class="panel admin-form settings-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="change_password"><label>รหัสผ่านใหม่<input type="password" name="new_password" minlength="8" required><small>อย่างน้อย 8 ตัวอักษร</small></label><button class="admin-btn" type="submit">เปลี่ยนรหัสผ่าน</button></form>

        <?php else:
            $latest=$pdo->query('SELECT r.*,p.prefix,p.number FROM reservations r JOIN plates p ON p.id=r.plate_id ORDER BY r.id DESC LIMIT 5')->fetchAll(); ?>
            <div class="content-heading"><div><h1>ภาพรวม</h1><p>ยินดีต้อนรับกลับ นี่คือสถานะล่าสุดของร้าน</p></div><a class="admin-btn" href="/admin/plate/new">+ เพิ่มทะเบียน</a></div>
            <div class="stats-grid"><div class="tone-blue"><span>ทะเบียนทั้งหมด</span><strong><?= $stats['all'] ?></strong><small>รายการในระบบ</small></div><div class="tone-mint"><span>พร้อมขาย</span><strong><?= $stats['available'] ?></strong><small>พร้อมแสดงหน้าร้าน</small></div><div class="tone-gold"><span>ติดจอง</span><strong><?= $stats['reserved'] ?></strong><small>อยู่ระหว่างดำเนินการ</small></div><div class="tone-pink"><span>คำขอใหม่</span><strong><?= $stats['leads'] ?></strong><small>รอการติดต่อกลับ</small></div></div>
            <div class="panel"><div class="panel-heading"><h2>คำขอล่าสุด</h2><a href="/admin/reservations">ดูทั้งหมด</a></div>
                <div class="lead-list"><?php if(!$latest): ?><div class="empty-cell">ยังไม่มีคำขอจากลูกค้า</div><?php endif; ?><?php foreach($latest as $item): ?><div><span class="lead-avatar"><?= e(mb_substr($item['customer_name'],0,1)) ?></span><p><strong><?= e($item['customer_name']) ?></strong><small>สนใจ <?= e($item['prefix'].' '.$item['number']) ?> · <?= e($item['phone']) ?></small></p><time><?= e(date('d/m/Y',strtotime($item['created_at']))) ?></time></div><?php endforeach; ?></div>
            </div>
        <?php endif; ?>
    </div>
</main>
<script>
const sidebar=document.querySelector('.sidebar'),menuButton=document.querySelector('.menu-button');
const closeMenu=()=>{sidebar?.classList.remove('open');menuButton?.setAttribute('aria-expanded','false')};
menuButton?.addEventListener('click',()=>{const open=sidebar?.classList.toggle('open');menuButton.setAttribute('aria-expanded',open?'true':'false')});
sidebar?.querySelectorAll('a').forEach(link=>link.addEventListener('click',closeMenu));
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMenu()});
</script>
</body></html>
