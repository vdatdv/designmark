<?php
// scripts/cleanup_keep_core_accounts.php
// Cleans all projects and all user accounts, keeping ONLY: admin, an, binh, chi

require_once __DIR__ . '/../api/db.php';

echo "=== DesignMark: Dọn dẹp hệ thống, chỉ giữ lại admin, an, binh, chi ===\n\n";

$coreUsernames = ['admin', 'an', 'binh', 'chi'];

// 1. Prepare connections (Supabase Postgres if active + local SQLite)
$dbs = [];
$dbs['active_pdo'] = $pdo;

$sqlitePath = __DIR__ . '/../data/database.sqlite';
if (file_exists($sqlitePath)) {
    $sqliteDb = new PDO('sqlite:' . $sqlitePath);
    $sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbs['sqlite'] = $sqliteDb;
}

foreach ($dbs as $name => $db) {
    echo "Dang xu ly co so du lieu: $name...\n";

    // Delete all project-related tables
    $db->exec("DELETE FROM annotation_replies");
    $db->exec("DELETE FROM annotation_status_events");
    $db->exec("DELETE FROM annotations");
    $db->exec("DELETE FROM image_versions");
    $db->exec("DELETE FROM design_screens");
    $db->exec("DELETE FROM project_members");
    $db->exec("DELETE FROM activities");
    $db->exec("DELETE FROM notifications");
    $db->exec("DELETE FROM projects");

    // Delete user status history for deleted users
    $db->exec("DELETE FROM user_status_history WHERE user_id NOT IN (SELECT id FROM users WHERE username IN ('admin', 'an', 'binh', 'chi'))");

    // Delete all users except admin, an, binh, chi
    $db->exec("DELETE FROM users WHERE username NOT IN ('admin', 'an', 'binh', 'chi')");

    // Reset standard demo password ('demo') for the 4 core accounts
    $passHash = password_hash('demo', PASSWORD_DEFAULT);
    $stmtUp = $db->prepare("UPDATE users SET status = 'active', is_verified = 1, password_hash = :phash WHERE username IN ('admin', 'an', 'binh', 'chi')");
    $stmtUp->execute([':phash' => $passHash]);

    // Ensure admin has system_role = 'admin'
    $db->exec("UPDATE users SET system_role = 'admin', role = 'admin' WHERE username = 'admin'");

    // Ensure an, binh, chi have system_role = 'user'
    $db->exec("UPDATE users SET system_role = 'user', role = 'user' WHERE username IN ('an', 'binh', 'chi')");

    // Count remaining
    $userCount = (int)$db->query("SELECT count(*) FROM users")->fetchColumn();
    $projCount = (int)$db->query("SELECT count(*) FROM projects")->fetchColumn();

    echo " -> $name xong: Con lai $userCount nguoi dung, $projCount du an.\n\n";
}

// Vacuum SQLite
if (isset($dbs['sqlite'])) {
    $dbs['sqlite']->exec("VACUUM");
}

echo "=== DANH SACH TAI KHOAN CON LAI ===\n";
$stmtUsers = $pdo->query("SELECT id, username, name, email, system_role, status FROM users ORDER BY CASE WHEN username = 'admin' THEN 0 ELSE 1 END, username ASC");
while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
    echo " - @{$row['username']}: {$row['name']} | Quyen: {$row['system_role']} | Email: {$row['email']} | Mat khau: demo\n";
}

echo "\nHOAN TAT: Da xoa het du an va cac tai khoan nguoi dung khac. Chi con lai 4 tai khoan cot loi!\n";
