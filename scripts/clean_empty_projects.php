<?php
// scripts/clean_empty_projects.php - Dọn dẹp các dự án trống và dự án test
require_once __DIR__ . '/../api/db.php';

echo "=== DESIGNMARK: DỌN DẸP DỰ ÁN TRỐNG & DỰ ÁN TEST ===\n\n";

$sqliteDb = new PDO("sqlite:" . __DIR__ . '/../data/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// 1. Dọn dẹp trên PostgreSQL Supabase
$stmt = $pdo->query("SELECT id, name, created_at, 
        (SELECT count(*) FROM design_screens WHERE project_id = projects.id) as screen_cnt,
        (SELECT count(*) FROM annotations WHERE project_id = projects.id) as ann_cnt
        FROM projects 
        WHERE id NOT IN ('proj-ops', 'proj-shop')");
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[Supabase PostgreSQL] Tìm thấy " . count($targets) . " dự án cần xóa:\n";
foreach ($targets as $t) {
    $id = $t['id'];
    echo " - Xóa vĩnh viễn: [{$id}] \"{$t['name']}\" (Màn hình: {$t['screen_cnt']}, Góp ý: {$t['ann_cnt']})\n";
    try {
        $pdo->prepare("DELETE FROM notifications WHERE project_id = :pid")->execute([':pid' => $id]);
    } catch (Throwable $e) {}
    permanently_delete_project($pdo, $id);
}

// 2. Dọn dẹp các dự án rác còn sót trong SQLite nếu có
$stmtSqlite = $sqliteDb->query("SELECT id, name, 
        (SELECT count(*) FROM design_screens WHERE project_id = projects.id) as screen_cnt
        FROM projects 
        WHERE id NOT IN ('proj-ops', 'proj-shop')");
$sqliteTargets = $stmtSqlite->fetchAll(PDO::FETCH_ASSOC);
echo "\n[Local SQLite] Tìm thấy " . count($sqliteTargets) . " dự án còn sót:\n";
foreach ($sqliteTargets as $st) {
    $id = $st['id'];
    echo " - Xóa SQLite: [{$id}] \"{$st['name']}\"\n";
    try {
        $sqliteDb->prepare("DELETE FROM notifications WHERE project_id = :pid")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM annotation_replies WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :pid)")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM annotation_status_events WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :pid)")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM annotations WHERE project_id = :pid")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM image_versions WHERE project_id = :pid")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM design_screens WHERE project_id = :pid")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM activities WHERE project_id = :pid")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM project_members WHERE project_id = :pid")->execute([':pid' => $id]);
        $sqliteDb->prepare("DELETE FROM projects WHERE id = :pid")->execute([':pid' => $id]);
    } catch (Throwable $e) {
        echo "   Lỗi: " . $e->getMessage() . "\n";
    }
}

echo "\n=== KẾT QUẢ SAU KHI DỌN DẸP ===\n";
$stmt = $pdo->query("SELECT id, name, category, (SELECT count(*) FROM design_screens WHERE project_id = projects.id) as screens, (SELECT count(*) FROM annotations WHERE project_id = projects.id) as comments FROM projects ORDER BY created_at DESC");
$remaining = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "PostgreSQL (Supabase) còn " . count($remaining) . " dự án:\n";
foreach ($remaining as $r) {
    echo " + [{$r['id']}] {$r['name']} ({$r['category']}) - {$r['screens']} màn hình, {$r['comments']} góp ý\n";
}

$stmt2 = $sqliteDb->query("SELECT id, name, category, (SELECT count(*) FROM design_screens WHERE project_id = projects.id) as screens, (SELECT count(*) FROM annotations WHERE project_id = projects.id) as comments FROM projects ORDER BY created_at DESC");
$remaining2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "SQLite (Local Mirror) còn " . count($remaining2) . " dự án:\n";
foreach ($remaining2 as $r) {
    echo " + [{$r['id']}] {$r['name']} ({$r['category']}) - {$r['screens']} màn hình, {$r['comments']} góp ý\n";
}
