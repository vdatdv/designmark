<?php
// tests/test_account_standardization.php - Automated Verification for 17 Test Cases

require_once __DIR__ . '/../api/db.php';

$cookieJarDir = sys_get_temp_dir() . '/dm_test_cookies_' . uniqid();
@mkdir($cookieJarDir, 0777, true);

function test_curl($url, $method = 'GET', $data = null, $cookieFile = null) {
    $ch = curl_init('http://127.0.0.1:8000/' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    return ['code' => $httpCode, 'data' => json_decode($body, true), 'raw' => $body];
}

$results = [];

function record($testNum, $description, $passed, $details = '') {
    global $results;
    $results[] = [
        'num' => $testNum,
        'desc' => $description,
        'passed' => $passed,
        'details' => $details
    ];
    $status = $passed ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "Test $testNum: $status $description\n";
    if ($details) {
        echo "   -> $details\n";
    }
}

echo "=======================================================\n";
echo "DESIGNMARK ACCOUNT STANDARDIZATION — 17 TEST SUITE\n";
echo "=======================================================\n\n";

// TEST 1: Tạo Admin bằng script PHP CLI
$phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : '/usr/local/bin/php';
$cliOutput = shell_exec(escapeshellcmd($phpBin) . ' ' . escapeshellarg(__DIR__ . '/../scripts/create-admin.php') . ' 2>&1');
$t1Passed = stripos($cliOutput, 'admin đã tồn tại') !== false || stripos($cliOutput, 'tạo tài khoản admin thành công') !== false;
record(1, 'Tạo Admin bằng script PHP CLI', $t1Passed, trim($cliOutput));

// TEST 2: Đăng nhập admin/demo trên môi trường local
$adminCookie = $cookieJarDir . '/admin.cookie';
$adminLoginRes = test_curl('api/auth.php?action=login', 'POST', ['username' => 'admin', 'password' => 'demo'], $adminCookie);
$t2Passed = $adminLoginRes['code'] === 200 && ($adminLoginRes['data']['systemRole'] ?? '') === 'admin';
record(2, 'Đăng nhập admin/demo trên môi trường local', $t2Passed, "HTTP {$adminLoginRes['code']}, System Role: " . ($adminLoginRes['data']['systemRole'] ?? 'N/A'));

// TEST 3: Đăng ký người dùng bằng username, không cần email
$testUname = 'testuser_' . substr(md5(uniqid()), 0, 6);
$regRes = test_curl('api/auth.php?action=register', 'POST', [
    'username' => $testUname,
    'name' => 'Nguyễn Văn Test',
    'password' => 'test1234',
    'confirm_password' => 'test1234'
]);
$t3Passed = $regRes['code'] === 201 && !empty($regRes['data']['userId']) && $regRes['data']['status'] === 'active';
record(3, 'Đăng ký người dùng bằng username, không cần email', $t3Passed, "Registered username: $testUname, Status: " . ($regRes['data']['status'] ?? 'N/A'));

// TEST 4: Đăng ký username trùng (không phân biệt chữ hoa/thường)
$regDupRes = test_curl('api/auth.php?action=register', 'POST', [
    'username' => strtoupper($testUname),
    'name' => 'Trùng Tên',
    'password' => 'test1234',
    'confirm_password' => 'test1234'
]);
$t4Passed = $regDupRes['code'] === 422 && strpos($regDupRes['data']['error'] ?? '', 'đã được sử dụng') !== false;
record(4, 'Đăng ký username trùng (không phân biệt chữ hoa/thường)', $t4Passed, $regDupRes['data']['error'] ?? 'N/A');

// TEST 5: Đăng ký username "admin" hoặc từ khóa hệ thống
$regAdminRes = test_curl('api/auth.php?action=register', 'POST', [
    'username' => 'Admin',
    'name' => 'Admin Mạo Danh',
    'password' => 'test1234',
    'confirm_password' => 'test1234'
]);
$t5Passed = $regAdminRes['code'] === 422 && strpos($regAdminRes['data']['error'] ?? '', 'dành riêng cho hệ thống') !== false;
record(5, 'Đăng ký username "admin" hoặc từ khóa hệ thống', $t5Passed, $regAdminRes['data']['error'] ?? 'N/A');

// TEST 6: Đăng nhập đúng và sai mật khẩu
$loginWrong = test_curl('api/auth.php?action=login', 'POST', [
    'username' => $testUname,
    'password' => 'wrongpassword'
]);
$t6aPassed = $loginWrong['code'] === 401 && ($loginWrong['data']['error'] ?? '') === 'Tên đăng nhập hoặc mật khẩu không chính xác.';

$userCookie = $cookieJarDir . '/user.cookie';
$loginRight = test_curl('api/auth.php?action=login', 'POST', [
    'username' => $testUname,
    'password' => 'test1234'
], $userCookie);
$t6bPassed = $loginRight['code'] === 200 && ($loginRight['data']['username'] ?? '') === strtolower($testUname);
record(6, 'Đăng nhập đúng và sai mật khẩu', $t6aPassed && $t6bPassed, "Wrong: HTTP {$loginWrong['code']} ({$loginWrong['data']['error']}), Right: HTTP {$loginRight['code']}");

// TEST 15: Admin khóa và mở khóa tài khoản
$targetUserId = $regRes['data']['userId'];
$lockRes = test_curl('api/auth.php?action=lock_user', 'POST', [
    'userId' => $targetUserId,
    'reason' => 'Kiểm thử tính năng khóa'
], $adminCookie);
$t15aPassed = $lockRes['code'] === 200 && ($lockRes['data']['status'] ?? '') === 'locked';

// TEST 7: Tài khoản bị khóa không thể đăng nhập hoặc tiếp tục sử dụng hệ thống
$loginLocked = test_curl('api/auth.php?action=login', 'POST', [
    'username' => $testUname,
    'password' => 'test1234'
]);
$t7aPassed = $loginLocked['code'] === 403 && strpos($loginLocked['data']['error'] ?? '', 'đã bị khóa') !== false;

// Check existing session of locked user is immediately revoked
$sessionLocked = test_curl('api/auth.php?action=session', 'GET', null, $userCookie);
$t7bPassed = $sessionLocked['code'] === 200 && empty($sessionLocked['data']);
record(7, 'Tài khoản bị khóa bị từ chối đăng nhập và vô hiệu hóa session', $t7aPassed && $t7bPassed, "Login HTTP {$loginLocked['code']}: {$loginLocked['data']['error']}, Session revoked: " . ($t7bPassed ? 'YES' : 'NO'));

// Admin unlocks user
$unlockRes = test_curl('api/auth.php?action=unlock_user', 'POST', [
    'userId' => $targetUserId,
    'reason' => 'Kiểm thử mở khóa'
], $adminCookie);
$t15bPassed = $unlockRes['code'] === 200 && ($unlockRes['data']['status'] ?? '') === 'active';
record(15, 'Admin khóa và mở khóa tài khoản', $t15aPassed && $t15bPassed, "Lock: " . ($lockRes['data']['message'] ?? 'N/A') . ", Unlock: " . ($unlockRes['data']['message'] ?? 'N/A'));

// Re-login user after unlock
$loginAfterUnlock = test_curl('api/auth.php?action=login', 'POST', [
    'username' => $testUname,
    'password' => 'test1234'
], $userCookie);

// TEST 8: Người dùng thông thường tạo dự án và trở thành Manager
$createProjRes = test_curl('api/projects.php?action=create', 'POST', [
    'name' => 'Dự án Kiểm thử Phân quyền ' . rand(100, 999),
    'description' => 'Mô tả dự án kiểm thử'
], $userCookie);
$t8Passed = $createProjRes['code'] === 201 && ($createProjRes['data']['myRole'] ?? '') === 'manager';
$newProjectId = $createProjRes['data']['id'] ?? null;
record(8, 'Người dùng thông thường tạo dự án và trở thành Manager', $t8Passed, "Project ID: $newProjectId, Role in Project: " . ($createProjRes['data']['myRole'] ?? 'N/A'));

// Register Commenter & Viewer
$commenterUname = 'commenter_' . substr(md5(uniqid()), 0, 6);
$regCommenter = test_curl('api/auth.php?action=register', 'POST', [
    'username' => $commenterUname,
    'name' => 'Nguyễn Commenter',
    'password' => 'demo1234'
]);
$commenterId = $regCommenter['data']['userId'];

$viewerUname = 'viewer_' . substr(md5(uniqid()), 0, 6);
$regViewer = test_curl('api/auth.php?action=register', 'POST', [
    'username' => $viewerUname,
    'name' => 'Trần Viewer',
    'password' => 'demo1234'
]);
$viewerId = $regViewer['data']['userId'];

// TEST 9: Manager thêm Commenter
$addCommenterRes = test_curl("api/projects.php?id=$newProjectId&action=invite", 'POST', [
    'userId' => $commenterId,
    'role' => 'commenter'
], $userCookie);
$t9Passed = $addCommenterRes['code'] === 200;
record(9, 'Manager thêm thành viên vai trò Commenter', $t9Passed, "HTTP {$addCommenterRes['code']}");

// TEST 10: Manager thêm Viewer
$addViewerRes = test_curl("api/projects.php?id=$newProjectId&action=invite", 'POST', [
    'userId' => $viewerId,
    'role' => 'viewer'
], $userCookie);
$t10Passed = $addViewerRes['code'] === 200;
record(10, 'Manager thêm thành viên vai trò Viewer', $t10Passed, "HTTP {$addViewerRes['code']}");

// Login Commenter
$commenterCookie = $cookieJarDir . '/commenter.cookie';
test_curl('api/auth.php?action=login', 'POST', ['username' => $commenterUname, 'password' => 'demo1234'], $commenterCookie);

// Login Viewer
$viewerCookie = $cookieJarDir . '/viewer.cookie';
test_curl('api/auth.php?action=login', 'POST', ['username' => $viewerUname, 'password' => 'demo1234'], $viewerCookie);

// Create a screen & version in $newProjectId
$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
$screenId = generate_uuid('scr');
$versionId = generate_uuid('ver');
$pdo->prepare("INSERT INTO design_screens (id, project_id, name, created_by, created_at, updated_at) VALUES (:id, :pid, 'Màn hình kiểm thử', :cb, :now, :now)")->execute([
    ':id' => $screenId,
    ':pid' => $newProjectId,
    ':cb' => $targetUserId,
    ':now' => $now
]);
$pdo->prepare("INSERT INTO image_versions (id, project_id, screen_id, number, image_key, width, height, mime_type, source, created_by, created_at) VALUES (:id, :pid, :sid, 1, 'seed:dashboard', 1200, 800, 'image/png', 'seed', :cb, :now)")->execute([
    ':id' => $versionId,
    ':pid' => $newProjectId,
    ':sid' => $screenId,
    ':cb' => $targetUserId,
    ':now' => $now
]);

// TEST 11: Commenter gửi góp ý thành công
$annCommenter = test_curl('api/annotations.php?action=create', 'POST', [
    'version_id' => $versionId,
    'type' => 'point',
    'x' => 150,
    'y' => 200,
    'body' => 'Góp ý kiểm thử từ Commenter'
], $commenterCookie);
$t11Passed = $annCommenter['code'] === 201 && !empty($annCommenter['data']['id']);
record(11, 'Commenter gửi góp ý thành công', $t11Passed, "HTTP {$annCommenter['code']}, ID: " . ($annCommenter['data']['id'] ?? 'N/A'));

// TEST 12: Viewer không thể tạo góp ý (Backend chặn 403)
$annViewer = test_curl('api/annotations.php?action=create', 'POST', [
    'version_id' => $versionId,
    'type' => 'point',
    'x' => 250,
    'y' => 300,
    'body' => 'Góp ý bất hợp pháp từ Viewer'
], $viewerCookie);
$t12Passed = $annViewer['code'] === 403;
record(12, 'Viewer bị chặn 403 khi cố tạo góp ý', $t12Passed, "HTTP {$annViewer['code']}: " . ($annViewer['data']['error'] ?? ''));

// TEST 13: Người dùng không thể truy cập dự án chưa được chia sẻ
$unauthorizedUname = 'stranger_' . substr(md5(uniqid()), 0, 6);
$regStranger = test_curl('api/auth.php?action=register', 'POST', [
    'username' => $unauthorizedUname,
    'name' => 'Người Lạ',
    'password' => 'demo1234'
]);
$strangerCookie = $cookieJarDir . '/stranger.cookie';
test_curl('api/auth.php?action=login', 'POST', ['username' => $unauthorizedUname, 'password' => 'demo1234'], $strangerCookie);
$strangerAccess = test_curl("api/projects.php?id=$newProjectId", 'GET', null, $strangerCookie);
$t13Passed = $strangerAccess['code'] === 403;
record(13, 'Người dùng không thể truy cập dự án chưa được chia sẻ', $t13Passed, "HTTP {$strangerAccess['code']}: " . ($strangerAccess['data']['error'] ?? ''));

// TEST 14: Người dùng không thể tự nâng quyền Admin
$escalateRes = test_curl("api/projects.php?id=$newProjectId&action=invite", 'POST', [
    'userId' => $regStranger['data']['userId'],
    'role' => 'admin' // Attempting to grant admin
], $userCookie);
$t14Passed = $escalateRes['code'] === 422;
record(14, 'Không cho phép nâng quyền Admin qua API dự án', $t14Passed, "HTTP {$escalateRes['code']}: " . ($escalateRes['data']['error'] ?? ''));

// TEST 16: Tài khoản và dự án cũ vẫn hoạt động sau migration
$stmtAn = $pdo->query("SELECT id, username, system_role, status FROM users WHERE username = 'an'")->fetch();
$stmtProj = $pdo->query("SELECT id, name FROM projects WHERE id = 'proj-shop'")->fetch();
$stmtMemAn = $pdo->query("SELECT role FROM project_members WHERE project_id = 'proj-shop' AND user_id = 'user-an'")->fetch();
$t16Passed = $stmtAn && $stmtProj && $stmtMemAn && $stmtAn['status'] === 'active';
record(16, 'Tài khoản và dự án cũ vẫn hoạt động trọn vẹn sau migration', $t16Passed, "User: {$stmtAn['username']}, Status: {$stmtAn['status']}, Proj: {$stmtProj['name']}, Member Role: {$stmtMemAn['role']}");

// TEST 17: Các API hiện tại không bị lỗi do chuyển từ email sang username
$loginAn = test_curl('api/auth.php?action=login', 'POST', [
    'username' => 'an',
    'password' => 'demo'
]);
$t17Passed = $loginAn['code'] === 200 && ($loginAn['data']['username'] ?? '') === 'an';
record(17, 'Các API cốt lõi hoạt động trơn tru với username', $t17Passed, "Login 'an' with password 'demo': HTTP {$loginAn['code']}");

echo "\n=======================================================\n";
$allPassed = !in_array(false, array_column($results, 'passed'), true);
if ($allPassed) {
    echo "\033[32mALL 17 TESTS PASSED SUCCESSFULLY! (17/17)\033[0m\n";
} else {
    echo "\033[31mSOME TESTS FAILED!\033[0m\n";
}
echo "=======================================================\n";

// Cleanup test project so it does not clutter the project list
if (!empty($newProjectId)) {
    try {
        $pdo->prepare("DELETE FROM notifications WHERE project_id = :pid")->execute([':pid' => $newProjectId]);
        permanently_delete_project($pdo, $newProjectId);
    } catch (Throwable $e) {}
}

// Cleanup test users
$cleanupUsers = array_filter([$testUserId ?? null, $commenterId ?? null, $viewerId ?? null, $strangerId ?? null]);
foreach ($cleanupUsers as $cuid) {
    try {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = :uid OR actor_id = :uid")->execute([':uid' => $cuid]);
        $pdo->prepare("DELETE FROM project_members WHERE user_id = :uid")->execute([':uid' => $cuid]);
        $pdo->prepare("DELETE FROM activities WHERE user_id = :uid")->execute([':uid' => $cuid]);
        $pdo->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $cuid]);
        mirror_to_sqlite("DELETE FROM users WHERE id = :uid", [':uid' => $cuid]);
    } catch (Throwable $e) {}
}

// Cleanup cookies
@array_map('unlink', glob("$cookieJarDir/*.*"));
@rmdir($cookieJarDir);
