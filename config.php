<?php
declare(strict_types=1);

const APP_NAME = 'MY NAME IS TABIEN CO.,LTD.';
const DB_PATH = __DIR__ . '/storage/tabian.sqlite';
const UPLOAD_DIR = __DIR__ . '/uploads';
const UPLOAD_WEB_PATH = 'uploads';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0775, true);
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    initialize_database($pdo);

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS plates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prefix TEXT NOT NULL,
            number TEXT NOT NULL,
            province TEXT NOT NULL DEFAULT "ภูเก็ต",
            price REAL NOT NULL DEFAULT 0,
            category TEXT NOT NULL DEFAULT "เลขสวย",
            description TEXT NOT NULL DEFAULT "",
            meaning TEXT NOT NULL DEFAULT "",
            image TEXT,
            status TEXT NOT NULL DEFAULT "available",
            featured INTEGER NOT NULL DEFAULT 0,
            display_order INTEGER NOT NULL DEFAULT 999,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plate_id INTEGER NOT NULL,
            customer_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            line_id TEXT NOT NULL DEFAULT "",
            note TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "new",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (plate_id) REFERENCES plates(id) ON DELETE CASCADE
        );'
    );

    $plateColumns = $pdo->query('PRAGMA table_info(plates)')->fetchAll();
    if (!in_array('display_order', array_column($plateColumns, 'name'), true)) {
        $pdo->exec('ALTER TABLE plates ADD COLUMN display_order INTEGER NOT NULL DEFAULT 999');
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM plates')->fetchColumn() === 0) {
        $plates = [
            ['ขล', '88', 'ภูเก็ต', 890000, 'เลขคู่', 'ทะเบียนเด่น จดจำง่าย เหมาะกับรถระดับพรีเมียม', 'เลข 88 สื่อถึงความมั่งคั่ง ความสำเร็จ และความก้าวหน้า', 'available', 1],
            ['กข', '999', 'ภูเก็ต', 1290000, 'เลขตอง', 'เลขตองยอดนิยม โดดเด่นทุกเส้นทาง', 'เลข 9 หมายถึงความก้าวหน้าและความเจริญรุ่งเรือง', 'available', 1],
            ['ขก', '168', 'ภูเก็ต', 650000, 'เลขมงคล', 'ชุดเลขมงคลที่ได้รับความนิยมสูง', '168 อ่านในความหมายจีนว่า รวยตลอดทาง', 'reserved', 1],
            ['ขจ', '1234', 'ภูเก็ต', 420000, 'เลขเรียง', 'เลขเรียงสวย อ่านง่ายและจำง่าย', 'เลขเรียงแสดงถึงการเติบโตอย่างเป็นขั้นเป็นตอน', 'available', 0],
            ['กม', '5555', 'กรุงเทพมหานคร', 980000, 'เลขสี่ตัวซ้ำ', 'เลขสี่ตัวซ้ำ เหมาะกับผู้ที่ชอบความโดดเด่น', 'เลข 5 สื่อถึงสติปัญญา ความมั่นคง และเหตุผล', 'available', 1],
            ['ขว', '789', 'ภูเก็ต', 590000, 'เลขเรียง', 'เลขสวยช่วงปลาย จังหวะตัวเลขลงตัว', '789 สื่อถึงการเติบโตและก้าวสู่ความสำเร็จ', 'sold', 0],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO plates (prefix, number, province, price, category, description, meaning, status, featured)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($plates as $plate) {
            $stmt->execute($plate);
        }
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS app_meta (meta_key TEXT PRIMARY KEY, meta_value TEXT NOT NULL)');
    $migration = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ?');
    $migration->execute(['product_images_v1']);
    if (!$migration->fetchColumn()) {
        $products = [
            ['ขจ', '88', 485000, 'เลขคู่', 'image/43504_0.jpg', 1],
            ['ขต', '567', 155000, 'เลขเรียง', 'image/43505_0.jpg', 2],
            ['ขต', '789', 330000, 'เลขเรียง', 'image/43506_0.jpg', 3],
            ['ขจ', '1234', 199000, 'เลขเรียง', 'image/43513_0.jpg', 4],
            ['ขจ', '2772', 68500, 'เลขกระจก', 'image/43507_0.jpg', 5],
            ['ขจ', '2882', 62500, 'เลขกระจก', 'image/43508_0.jpg', 6],
            ['ขจ', '2992', 68500, 'เลขกระจก', 'image/43509_0.jpg', 7],
            ['ขต', '3434', 58500, 'เลขคู่สลับ', 'image/43510_0.jpg', 8],
            ['ขจ', '6622', 65000, 'เลขคู่', 'image/43515_0.jpg', 9],
            ['ขต', '3377', 55000, 'เลขคู่', 'image/43511_0.jpg', 10],
            ['ขต', '3737', 55000, 'เลขคู่สลับ', 'image/43512_0.jpg', 11],
            ['ขจ', '4433', 55000, 'เลขคู่', 'image/43514_0.jpg', 12],
            ['ขต', '6363', 69000, 'เลขคู่สลับ', 'image/43516_0.jpg', 13],
            ['ขจ', '7722', 65000, 'เลขคู่', 'image/43517_0.jpg', 14],
            ['ขต', '8000', 95000, 'เลขหลักพัน', 'image/43518_0.jpg', 15],
        ];
        $pdo->beginTransaction();
        $pdo->exec(
            'UPDATE plates SET status = "hidden"
             WHERE image IS NULL AND number IN ("88","999","168","1234","5555","789")'
        );
        $stmt = $pdo->prepare(
            'INSERT INTO plates
                (prefix,number,province,price,category,description,meaning,image,status,featured,display_order)
             VALUES (?,?,"ภูเก็ต",?,?,"ทะเบียนประมูลภูเก็ต พร้อมป้ายคู่","เลขสวย คัดสรรเพื่อความโดดเด่นและความหมายที่ดี",?,"available",?,?)
             ON CONFLICT DO NOTHING'
        );
        foreach ($products as [$prefix, $number, $price, $category, $image, $order]) {
            $stmt->execute([$prefix, $number, $price, $category, $image, $order <= 4 ? 1 : 0, $order]);
        }
        $pdo->prepare('INSERT INTO app_meta (meta_key, meta_value) VALUES (?, ?)')->execute(['product_images_v1', '1']);
        $pdo->commit();
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function baht(float|int|string $amount): string
{
    return number_format((float) $amount, 0) . ' บาท';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals(csrf_token(), (string) $_POST['csrf_token'])) {
        http_response_code(419);
        exit('คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
    }
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function status_label(string $status): string
{
    return match ($status) {
        'available' => 'พร้อมขาย',
        'reserved' => 'ติดจอง',
        'sold' => 'ขายแล้ว',
        'hidden' => 'ซ่อนรายการ',
        default => $status,
    };
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}
