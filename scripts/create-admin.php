<?php
// scripts/create-admin.php - Create the first System Admin account via CLI
// Usage: php scripts/create-admin.php [optional_password]

// 1. Strict security check: MUST be executed via CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: Access Denied. This script can only be executed via the PHP CLI terminal.\n";
    exit(1);
}

// 2. Load database connection
require_once dirname(__DIR__) . '/api/db.php';

echo "=== DesignMark: Tạo tài khoản Quản trị hệ thống (Admin) ===" . PHP_EOL;

// 3. Check if Admin account already exists (case-insensitive check)
$stmtCheck = $pdo->prepare("SELECT id, username, name, system_role, status, created_at FROM users WHERE lower(username) = 'admin' OR system_role = 'admin' LIMIT 1");
$stmtCheck->execute();
$existingAdmin = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($existingAdmin) {
    echo "THÔNG BÁO: Tài khoản Admin đã tồn tại trên hệ thống!" . PHP_EOL;
    echo " - ID: " . $existingAdmin['id'] . PHP_EOL;
    echo " - Username: " . $existingAdmin['username'] . PHP_EOL;
    echo " - Tên: " . $existingAdmin['name'] . PHP_EOL;
    echo " - Trạng thái: " . ($existingAdmin['status'] ?? 'active') . PHP_EOL;
    echo " - Quyền hệ thống: " . ($existingAdmin['system_role'] ?? 'admin') . PHP_EOL;
    echo "Script KHÔNG ghi đè mật khẩu hay quyền của Admin đã tồn tại." . PHP_EOL;
    exit(0);
}

// 4. Determine password securely
// Can be provided via CLI argument, environment variable, or defaults to 'demo' for local
$password = $argv[1] ?? getenv('ADMIN_INITIAL_PASSWORD') ?: 'demo';

if (strlen($password) < 4) {
    echo "LỖI: Mật khẩu phải có độ dài tối thiểu 4 ký tự." . PHP_EOL;
    exit(1);
}

$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
$adminId = 'user-admin';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$username = 'admin';
$name = 'Quản trị viên Hệ thống';
$systemRole = 'admin';
$status = 'active';
$color = '#4F46E5';
$title = 'System Administrator';

// 5. Insert Admin into main database
$stmtInsert = $pdo->prepare("
    INSERT INTO users (id, username, name, system_role, status, role, title, color, password_hash, is_verified, created_at)
    VALUES (:id, :uname, :name, :srole, :status, :role, :title, :color, :phash, 1, :cat)
");

$stmtInsert->execute([
    ':id' => $adminId,
    ':uname' => $username,
    ':name' => $name,
    ':srole' => $systemRole,
    ':status' => $status,
    ':role' => 'manager', // Compatible with legacy queries
    ':title' => $title,
    ':color' => $color,
    ':phash' => $passwordHash,
    ':cat' => $now
]);

// Also mirror to local SQLite if exists
if (file_exists(dirname(__DIR__) . '/data/database.sqlite')) {
    try {
        $localPdo = new PDO('sqlite:' . dirname(__DIR__) . '/data/database.sqlite');
        $stmtLocal = $localPdo->prepare("
            INSERT OR IGNORE INTO users (id, username, name, system_role, status, role, title, color, password_hash, is_verified, created_at)
            VALUES (:id, :uname, :name, :srole, :status, :role, :title, :color, :phash, 1, :cat)
        ");
        $stmtLocal->execute([
            ':id' => $adminId,
            ':uname' => $username,
            ':name' => $name,
            ':srole' => $systemRole,
            ':status' => $status,
            ':role' => 'manager',
            ':title' => $title,
            ':color' => $color,
            ':phash' => $passwordHash,
            ':cat' => $now
        ]);
    } catch (Throwable $e) {}
}

echo "THÀNH CÔNG: Đã tạo tài khoản Admin đầu tiên!" . PHP_EOL;
echo " - Tên đăng nhập: " . $username . PHP_EOL;
echo " - System Role: " . $systemRole . PHP_EOL;
echo " - Trạng thái: " . $status . PHP_EOL;
echo "Lưu ý: Mật khẩu đã được mã hóa an toàn bằng password_hash()." . PHP_EOL;
exit(0);
