<?php
// scripts/standardize_creator_titles.php
// Chuẩn hóa họ tên, chức danh và vai trò cho các tài khoản tạo dự án và tài khoản chính.

require_once __DIR__ . '/../api/db.php';

$updates = [
    [
        'username' => 'an',
        'name' => 'An Nguyễn',
        'title' => 'Quản lý thiết kế',
        'role' => 'manager',
        'system_role' => 'user'
    ],
    [
        'username' => 'hoanghai',
        'name' => 'Hoàng Hải',
        'title' => 'Quản lý thiết kế',
        'role' => 'manager',
        'system_role' => 'user'
    ],
    [
        'username' => 'lananh',
        'name' => 'Lan Anh',
        'title' => 'Quản lý thiết kế',
        'role' => 'manager',
        'system_role' => 'user'
    ],
    [
        'username' => 'minhtri',
        'name' => 'Minh Trí',
        'title' => 'Quản lý thiết kế',
        'role' => 'manager',
        'system_role' => 'user'
    ],
    [
        'username' => 'quocbao',
        'name' => 'Quốc Bảo',
        'title' => 'Quản lý thiết kế',
        'role' => 'manager',
        'system_role' => 'user'
    ],
    [
        'username' => 'binh',
        'name' => 'Bình Trần',
        'title' => 'Người góp ý',
        'role' => 'reviewer',
        'system_role' => 'user'
    ],
    [
        'username' => 'thanhnt',
        'name' => 'Thành Nguyễn',
        'title' => 'Người góp ý',
        'role' => 'reviewer',
        'system_role' => 'user'
    ],
    [
        'username' => 'hongphuc',
        'name' => 'Hồng Phúc',
        'title' => 'Người góp ý',
        'role' => 'reviewer',
        'system_role' => 'user'
    ],
    [
        'username' => 'chi',
        'name' => 'Chi Lê',
        'title' => 'Thành viên (Chỉ xem)',
        'role' => 'viewer',
        'system_role' => 'user'
    ],
    [
        'username' => 'admin',
        'name' => 'Quản trị viên Hệ thống',
        'title' => 'System Administrator',
        'role' => 'manager',
        'system_role' => 'admin'
    ]
];

$sqlitePath = __DIR__ . '/../data/database.sqlite';
$sqlite = file_exists($sqlitePath) ? new PDO("sqlite:$sqlitePath") : null;
$dbs = ['supabase' => $pdo];
if ($sqlite && $sqlite !== $pdo) {
    $dbs['sqlite'] = $sqlite;
}

foreach ($dbs as $dbName => $db) {
    echo "=== ĐANG CẬP NHẬT TRÊN DATABASE: $dbName ===\n";
    foreach ($updates as $u) {
        $stmt = $db->prepare("UPDATE users SET name = :name, title = :title, role = :role, system_role = :sys WHERE username = :u");
        $stmt->execute([
            ':name' => $u['name'],
            ':title' => $u['title'],
            ':role' => $u['role'],
            ':sys' => $u['system_role'],
            ':u' => $u['username']
        ]);
        echo "  [OK] Đã chuẩn hóa tài khoản {$u['username']}: {$u['name']} — {$u['title']} ({$u['role']})\n";
    }
}

echo "\nHOÀN THÀNH CHUẨN HÓA DỮ LIỆU TÀI KHOẢN!\n";
