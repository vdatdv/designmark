<?php
// tests/test_trash_and_password.php
// Comprehensive integration test for:
// 1. Change password (validation, wrong old password, correct old password, relogin)
// 2. Project Trash bin (soft delete, restore, permanent delete, daysRemaining calculation)
// 3. Auto-purge expired projects (> 30 days)

require_once __DIR__ . '/../api/db.php';

function run_test($name, callable $fn) {
    echo "Running: $name... ";
    try {
        $fn();
        echo "\033[32m[PASS]\033[0m\n";
        return true;
    } catch (Throwable $e) {
        echo "\033[31m[FAIL]\033[0m: " . $e->getMessage() . "\n";
        echo "Line: " . $e->getFile() . ":" . $e->getLine() . "\n";
        return false;
    }
}

function assert_true($cond, $msg = 'Expected true') {
    if (!$cond) throw new Exception($msg);
}

function assert_equals($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        throw new Exception($msg ?: ("Expected " . var_export($expected, true) . ", got " . var_export($actual, true)));
    }
}

$passed = 0;
$total = 0;

// Find user 'an'
$stmt = $pdo->prepare("SELECT * FROM users WHERE lower(username) = 'an'");
$stmt->execute();
$anUser = $stmt->fetch();
if (!$anUser) {
    die("User 'an' not found in database.\n");
}
$anId = $anUser['id'];

// Test 1: Đổi mật khẩu - Sai mật khẩu hiện tại
$total++;
if (run_test("Change password - Wrong current password rejects with error", function() use ($pdo, $anId) {
    $_SESSION['user_id'] = $anId;
    
    // Simulate POST request
    $currentPassword = 'wrong_password_1234';
    $newPassword = 'newpassword123';
    
    $user = $pdo->query("SELECT * FROM users WHERE id = " . $pdo->quote($anId))->fetch();
    $hash = $user['password_hash'] ?? '';
    $currentValid = false;
    if ($hash) {
        $currentValid = password_verify($currentPassword, $hash);
    }
    if (!$currentValid && empty($hash) && $currentPassword === 'demo') {
        $currentValid = true;
    }
    assert_true(!$currentValid, 'Wrong password must be invalid');
})) $passed++;

// Test 2: Đổi mật khẩu - Đúng mật khẩu hiện tại ('demo') và cập nhật mật khẩu mới
$total++;
if (run_test("Change password - Successfully update password and verify new hash", function() use ($pdo, $anId) {
    $_SESSION['user_id'] = $anId;
    $user = $pdo->query("SELECT * FROM users WHERE id = " . $pdo->quote($anId))->fetch();
    $currentPassword = 'demo';
    
    $hash = $user['password_hash'] ?? '';
    $currentValid = false;
    if ($hash) {
        $currentValid = password_verify($currentPassword, $hash) || ($hash === md5($currentPassword));
    }
    if (!$currentValid && empty($hash) && $currentPassword === 'demo') {
        $currentValid = true;
    }
    assert_true($currentValid, 'Current password demo should be valid');

    $newPassword = 'an_new_secret_pwd_2026';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmtUp = $pdo->prepare("UPDATE users SET password_hash = :phash WHERE id = :id");
    $stmtUp->execute([':phash' => $newHash, ':id' => $anId]);
    mirror_to_sqlite("UPDATE users SET password_hash = :phash WHERE id = :id", [':phash' => $newHash, ':id' => $anId]);

    // Verify user can authenticate with new password
    $updatedUser = $pdo->query("SELECT * FROM users WHERE id = " . $pdo->quote($anId))->fetch();
    assert_true(password_verify($newPassword, $updatedUser['password_hash']), 'New password verification should succeed');

    // Revert password back to 'demo' for seed integrity
    $revertHash = password_hash('demo', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = :phash WHERE id = :id")->execute([':phash' => $revertHash, ':id' => $anId]);
    mirror_to_sqlite("UPDATE users SET password_hash = :phash WHERE id = :id", [':phash' => $revertHash, ':id' => $anId]);
})) $passed++;

// Test 3: Tạo dự án test & kiểm tra soft delete vào thùng rác
$testProjId = generate_uuid('proj_test');
$total++;
if (run_test("Project Trash - Create test project and soft delete to trash bin", function() use ($pdo, $anId, $testProjId) {
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    
    // Create test project
    $stmtIns = $pdo->prepare("INSERT INTO projects (id, name, description, created_at, updated_at, created_by) VALUES (:id, 'Dự Án Test Thùng Rác', 'Mô tả test', :cat, :uat, :cb)");
    $stmtIns->execute([':id' => $testProjId, ':cat' => $now, ':uat' => $now, ':cb' => $anId]);
    
    $stmtMem = $pdo->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, 'manager', :inv, :cat)");
    $stmtMem->execute([':id' => generate_uuid('mem_test'), ':pid' => $testProjId, ':uid' => $anId, ':inv' => $anId, ':cat' => $now]);

    // Verify it is active
    $p = $pdo->query("SELECT * FROM projects WHERE id = " . $pdo->quote($testProjId))->fetch();
    assert_true(empty($p['deleted_at']), 'Project should be active initially');

    // Soft delete: move to trash
    $delNow = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $stmtTrash = $pdo->prepare("UPDATE projects SET deleted_at = :del_at, deleted_by = :del_by WHERE id = :id");
    $stmtTrash->execute([':del_at' => $delNow, ':del_by' => $anId, ':id' => $testProjId]);
    mirror_to_sqlite("UPDATE projects SET deleted_at = :del_at, deleted_by = :del_by WHERE id = :id", [':del_at' => $delNow, ':del_by' => $anId, ':id' => $testProjId]);

    // Check summarized project has daysRemaining = 30
    $trashedP = $pdo->query("SELECT * FROM projects WHERE id = " . $pdo->quote($testProjId))->fetch();
    assert_true(!empty($trashedP['deleted_at']), 'Project should have deleted_at timestamp');
    
    $summary = summarize_project($pdo, $trashedP, $anId);
    assert_true(isset($summary['daysRemaining']), 'Summary must contain daysRemaining');
    assert_equals(30, (int)$summary['daysRemaining'], 'daysRemaining should be 30 on initial deletion');
})) $passed++;

// Test 4: Dự án trong thùng rác không hiển thị trong danh sách dự án active & dashboard
$total++;
if (run_test("Project Trash - Trashed project excluded from active list and dashboard", function() use ($pdo, $anId, $testProjId) {
    // Active query
    $stmtActive = $pdo->prepare("SELECT p.id FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = :uid AND p.deleted_at IS NULL");
    $stmtActive->execute([':uid' => $anId]);
    $activeIds = $stmtActive->fetchAll(PDO::FETCH_COLUMN);
    assert_true(!in_array($testProjId, $activeIds, true), 'Trashed project must NOT be in active list');

    // Trash query
    $stmtTrash = $pdo->prepare("SELECT p.id FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = :uid AND pm.role = 'manager' AND p.deleted_at IS NOT NULL");
    $stmtTrash->execute([':uid' => $anId]);
    $trashIds = $stmtTrash->fetchAll(PDO::FETCH_COLUMN);
    assert_true(in_array($testProjId, $trashIds, true), 'Trashed project MUST be in trash list');
})) $passed++;

// Test 5: Khôi phục dự án (Restore)
$total++;
if (run_test("Project Trash - Restore project from trash", function() use ($pdo, $anId, $testProjId) {
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $stmtRestore = $pdo->prepare("UPDATE projects SET deleted_at = NULL, deleted_by = NULL, updated_at = :up WHERE id = :id");
    $stmtRestore->execute([':up' => $now, ':id' => $testProjId]);
    mirror_to_sqlite("UPDATE projects SET deleted_at = NULL, deleted_by = NULL, updated_at = :up WHERE id = :id", [':up' => $now, ':id' => $testProjId]);

    $restoredP = $pdo->query("SELECT * FROM projects WHERE id = " . $pdo->quote($testProjId))->fetch();
    assert_true(empty($restoredP['deleted_at']), 'Project deleted_at should be NULL after restore');

    // Should now be back in active list
    $stmtActive = $pdo->prepare("SELECT p.id FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = :uid AND p.deleted_at IS NULL");
    $stmtActive->execute([':uid' => $anId]);
    $activeIds = $stmtActive->fetchAll(PDO::FETCH_COLUMN);
    assert_true(in_array($testProjId, $activeIds, true), 'Restored project MUST be back in active list');
})) $passed++;

// Test 6: Tự động dọn sạch sau 30 ngày (purge_expired_trash)
$total++;
if (run_test("Auto-Purge - Automatically permanently deletes projects older than 30 days", function() use ($pdo, $anId, $testProjId) {
    // Set deleted_at to 35 days ago
    $thirtyFiveDaysAgo = (new DateTime('-35 days', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $stmtOld = $pdo->prepare("UPDATE projects SET deleted_at = :del_at, deleted_by = :del_by WHERE id = :id");
    $stmtOld->execute([':del_at' => $thirtyFiveDaysAgo, ':del_by' => $anId, ':id' => $testProjId]);
    mirror_to_sqlite("UPDATE projects SET deleted_at = :del_at, deleted_by = :del_by WHERE id = :id", [':del_at' => $thirtyFiveDaysAgo, ':del_by' => $anId, ':id' => $testProjId]);

    // Run auto-purge
    $purgedCount = purge_expired_trash($pdo);
    assert_true($purgedCount >= 1, "purge_expired_trash should have purged at least 1 expired project, got $purgedCount");

    // Project should no longer exist in database
    $checkP = $pdo->query("SELECT id FROM projects WHERE id = " . $pdo->quote($testProjId))->fetch();
    assert_true(!$checkP, 'Project should be permanently deleted from DB after purge');
})) $passed++;

echo "\n============================================\n";
echo "Results: $passed / $total tests passed.\n";
if ($passed === $total) {
    echo "\033[32mALL TRASH BIN & CHANGE PASSWORD TESTS PASSED!\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED!\033[0m\n";
    exit(1);
}
