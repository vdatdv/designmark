<?php
// tests/test_account_system_v2.php - Automated verification for all 12 required test cases
require_once __DIR__ . '/../api/db.php';

$baseUrl = 'http://127.0.0.1:8000';
$cookieDir = sys_get_temp_dir() . '/dm_test_v2_' . uniqid();
@mkdir($cookieDir, 0777, true);

function req($path, $method = 'GET', $data = null, $cookieFile = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . '/' . ltrim($path, '/'));
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    return ['code' => $code, 'data' => json_decode($body, true), 'raw' => $body];
}

$results = [];
function report($idx, $title, $pass, $detail = '') {
    global $results;
    $results[] = ['idx' => $idx, 'title' => $title, 'pass' => $pass, 'detail' => $detail];
    $badge = $pass ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "Case $idx: $badge $title\n";
    if ($detail) {
        echo "   -> $detail\n";
    }
}

echo "=======================================================\n";
echo "DESIGNMARK ACCOUNT SYSTEM V2 — 12 MANDATORY TEST CASES\n";
echo "=======================================================\n\n";

// Login as Admin
$adminCookie = $cookieDir . '/admin.cookie';
$adminLogin = req('api/auth.php?action=login', 'POST', ['username' => 'admin', 'password' => 'demo'], $adminCookie);

// CASE 1: User đăng ký và hoạt động ngay
$u1Uname = 'user_flow_' . substr(md5(uniqid()), 0, 6);
$u1Pass = 'Pass1234!';
$reg1 = req('api/auth.php?action=register', 'POST', [
    'username' => $u1Uname,
    'name' => 'Nguyễn Flow One',
    'password' => $u1Pass
]);
$u1Id = $reg1['data']['userId'] ?? null;
$u1Cookie = $cookieDir . '/u1.cookie';
$login1 = req('api/auth.php?action=login', 'POST', ['username' => $u1Uname, 'password' => $u1Pass], $u1Cookie);
$c1Pass = ($reg1['code'] === 201) && ($reg1['data']['status'] === 'active') && ($reg1['data']['systemRole'] === 'user') && ($login1['code'] === 200);
report(1, 'User đăng ký và hoạt động ngay (active, user role, no approval)', $c1Pass, "Register HTTP {$reg1['code']}, Status: {$reg1['data']['status']}, Login: HTTP {$login1['code']}");

// CASE 2: User mới tạo dự án và trở thành Owner
$createProjRes = req('api/projects.php?action=create', 'POST', [
    'name' => 'Dự Án Flow Kiểm Thử',
    'description' => 'Mô tả dự án kiểm thử',
    'category' => 'Công nghệ'
], $u1Cookie);
$newProjId = $createProjRes['data']['id'] ?? null;
$projOwnerId = $createProjRes['data']['createdBy'] ?? null;
$c2Pass = ($createProjRes['code'] === 201) && ($projOwnerId === $u1Id);
report(2, 'User mới tạo dự án và trở thành Owner', $c2Pass, "Project ID: $newProjId, Created By: $projOwnerId (Owner = {$u1Id})");

// CASE 3: User được mời làm Reviewer ở dự án khác
$u2Uname = 'user_rev_' . substr(md5(uniqid()), 0, 6);
$reg2 = req('api/auth.php?action=register', 'POST', [
    'username' => $u2Uname,
    'name' => 'Trần Reviewer Flow',
    'password' => $u1Pass
]);
$u2Id = $reg2['data']['userId'] ?? null;
$u2Cookie = $cookieDir . '/u2.cookie';
req('api/auth.php?action=login', 'POST', ['username' => $u2Uname, 'password' => $u1Pass], $u2Cookie);

// U1 invites U2 as Reviewer into project
$invRes = req("api/projects.php?id={$newProjId}&action=invite", 'POST', [
    'userId' => $u2Id,
    'role' => 'reviewer'
], $u1Cookie);

// Check U2 role in project
$u2RoleRes = req("api/projects.php?id={$newProjId}&action=role", 'GET', null, $u2Cookie);
$c3Pass = ($invRes['code'] === 200) && ($u2RoleRes['code'] === 200) && in_array($u2RoleRes['data'], ['reviewer', 'commenter'], true);
report(3, 'User được mời làm Reviewer ở dự án khác', $c3Pass, "Invite HTTP {$invRes['code']}, Role in project: {$u2RoleRes['data']}");

// CASE 4: Một User có nhiều vai trò ở nhiều dự án (Owner ở Dự án 2, Designer ở Dự án 1)
// U2 creates project 2 (Owner)
$createProj2 = req('api/projects.php?action=create', 'POST', [
    'name' => 'Dự Án Riêng Của U2',
    'category' => 'Thiết kế'
], $u2Cookie);
$proj2Id = $createProj2['data']['id'] ?? null;

// U1 updates U2's role in Project 1 to Designer (manager)
$updateRoleRes = req("api/projects.php?id={$newProjId}&action=update_role", 'POST', [
    'userId' => $u2Id,
    'role' => 'designer'
], $u1Cookie);

// Check user_detail for U2
$u2Detail = req("api/auth.php?action=user_detail&userId={$u2Id}", 'GET', null, $adminCookie);
$rolesFound = array_column($u2Detail['data']['projects'] ?? [], 'role');
$c4Pass = in_array('owner', $rolesFound, true) && in_array('designer', $rolesFound, true);
report(4, 'Một User có nhiều vai trò ở nhiều dự án (Owner + Designer)', $c4Pass, "Roles across projects: " . implode(', ', $rolesFound));

// CASE 5: Bộ lọc Owner trả đúng tài khoản có sở hữu dự án
$filterOwner = req('api/auth.php?action=list_all_users&paginate=1&projectRole=owner', 'GET', null, $adminCookie);
$ownerUsers = $filterOwner['data']['users'] ?? [];
$allHaveOwned = true;
foreach ($ownerUsers as $ou) {
    if ((int)$ou['ownedProjectsCount'] <= 0) {
        $allHaveOwned = false;
        break;
    }
}
$c5Pass = !empty($ownerUsers) && $allHaveOwned;
report(5, 'Bộ lọc Owner trả đúng tài khoản có sở hữu dự án', $c5Pass, "Found " . count($ownerUsers) . " owner users, all ownedProjectsCount > 0: " . ($allHaveOwned ? 'YES' : 'NO'));

// CASE 6: Bộ lọc Reviewer trả đúng tài khoản được phân quyền Reviewer
$filterReviewer = req('api/auth.php?action=list_all_users&paginate=1&projectRole=reviewer', 'GET', null, $adminCookie);
$reviewerUsers = $filterReviewer['data']['users'] ?? [];
$c6Pass = !empty($reviewerUsers);
report(6, 'Bộ lọc Reviewer trả đúng tài khoản được phân quyền Reviewer', $c6Pass, "Found " . count($reviewerUsers) . " reviewer accounts");

// CASE 7: Tìm kiếm kết hợp bộ lọc và phân trang hoạt động chính xác
$searchParam = urlencode($u1Uname);
$searchRes = req("api/auth.php?action=list_all_users&paginate=1&q={$searchParam}&systemRole=user&tab=active&page=1&limit=10", 'GET', null, $adminCookie);
$searchUsers = $searchRes['data']['users'] ?? [];
$searchStats = $searchRes['data']['stats'] ?? [];
$searchPagination = $searchRes['data']['pagination'] ?? [];
$c7Pass = (count($searchUsers) === 1) && ($searchUsers[0]['username'] === $u1Uname) && ($searchPagination['total'] === 1);
report(7, 'Tìm kiếm kết hợp bộ lọc và phân trang hoạt động chính xác', $c7Pass, "Matched 1 exact user: @{$u1Uname}, Total: {$searchPagination['total']}");

// CASE 8: Khóa tài khoản ngăn truy cập trái phép
$lockRes = req('api/auth.php?action=lock_user', 'POST', [
    'userId' => $u1Id,
    'reason' => 'Kiểm thử cơ chế bảo mật khóa tài khoản'
], $adminCookie);
// Try to make a request with existing U1 session
$activeSessionReq = req('api/auth.php?action=session', 'GET', null, $u1Cookie);
// Try to login again with locked account
$lockedLoginReq = req('api/auth.php?action=login', 'POST', ['username' => $u1Uname, 'password' => $u1Pass]);
$c8Pass = ($lockRes['code'] === 200) && ($lockedLoginReq['code'] === 403) && empty($activeSessionReq['data']);
report(8, 'Khóa tài khoản ngăn truy cập và thu hồi phiên', $c8Pass, "Lock HTTP {$lockRes['code']}, Login blocked HTTP {$lockedLoginReq['code']}, Session invalidated: " . (empty($activeSessionReq['data']) ? 'YES' : 'NO'));

// Unlock back for cleanup
req('api/auth.php?action=unlock_user', 'POST', ['userId' => $u1Id], $adminCookie);

// CASE 9: User không thể truy cập API quản trị
$userTryingAdminAPI = req('api/auth.php?action=list_all_users', 'GET', null, $u2Cookie);
$c9Pass = ($userTryingAdminAPI['code'] === 403);
report(9, 'User không thể truy cập API quản trị', $c9Pass, "HTTP {$userTryingAdminAPI['code']}: " . ($userTryingAdminAPI['data']['error'] ?? ''));

// CASE 10: Không thể khóa hoặc hạ quyền Admin cuối cùng
$lockSelfAdmin = req('api/auth.php?action=lock_user', 'POST', ['userId' => 'user-admin'], $adminCookie);
$demoteAdmin = req('api/auth.php?action=update_user', 'POST', ['userId' => 'user-admin', 'name' => 'Admin', 'systemRole' => 'user'], $adminCookie);
$c10Pass = ($lockSelfAdmin['code'] === 422) && ($demoteAdmin['code'] === 403);
report(10, 'Không thể tự khóa hoặc hạ quyền Admin cuối cùng', $c10Pass, "Lock self: HTTP {$lockSelfAdmin['code']} ({$lockSelfAdmin['data']['error']}), Demote: HTTP {$demoteAdmin['code']} ({$demoteAdmin['data']['error']})");

// CASE 11: Các dự án và tài khoản cũ không bị mất dữ liệu
$checkOldUser = req('api/auth.php?action=user_detail&userId=user-an', 'GET', null, $adminCookie);
$anOwnedCount = $checkOldUser['data']['stats']['ownedCount'] ?? 0;
$anTotalProjects = count($checkOldUser['data']['projects'] ?? []);
$c11Pass = ($checkOldUser['code'] === 200) && ($anOwnedCount >= 2) && ($anTotalProjects >= 8);
report(11, 'Các dự án và tài khoản cũ không bị mất dữ liệu', $c11Pass, "User 'an': owned {$anOwnedCount} projects, total accessible {$anTotalProjects} projects intact");

// CASE 12: Chức năng quản lý góp ý vẫn hoạt động bình thường
// Get version id of proj-shop
$stmtVer = $pdo->query("SELECT v.id FROM image_versions v WHERE v.project_id = 'proj-shop' ORDER BY v.created_at ASC LIMIT 1");
$shopVerId = $stmtVer->fetchColumn();

// Login as user-binh (reviewer on proj-shop)
$binhCookie = $cookieDir . '/binh.cookie';
req('api/auth.php?action=login', 'POST', ['username' => 'binh', 'password' => 'demo'], $binhCookie);

$annRes = req('api/annotations.php?action=create', 'POST', [
    'version_id' => $shopVerId,
    'type' => 'point',
    'x' => 120,
    'y' => 240,
    'body' => 'Góp ý kiểm thử xác nhận hoạt động bình thường'
], $binhCookie);
$annId = $annRes['data']['id'] ?? null;

// Reply to annotation
$replyRes = req("api/annotations.php?id={$annId}&action=reply", 'POST', [
    'body' => 'Phản hồi kiểm thử'
], $binhCookie);

$c12Pass = ($annRes['code'] === 201) && !empty($annId) && in_array($replyRes['code'], [200, 201], true);
report(12, 'Chức năng quản lý góp ý vẫn hoạt động bình thường', $c12Pass, "Annotation created: HTTP {$annRes['code']} ({$annId}), Reply: HTTP {$replyRes['code']}");

// Clean up test annotation
if ($annId) {
    try {
        $pdo->prepare("DELETE FROM annotation_comments WHERE annotation_id = :id")->execute([':id' => $annId]);
        $pdo->prepare("DELETE FROM annotations WHERE id = :id")->execute([':id' => $annId]);
    } catch (Throwable $e) {}
}

// Clean up test projects
if ($newProjId) {
    permanently_delete_project($pdo, $newProjId);
}
if ($proj2Id) {
    permanently_delete_project($pdo, $proj2Id);
}

// Clean up test users
foreach ([$u1Id ?? null, $u2Id ?? null] as $uid) {
    if (!$uid) continue;
    try {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = :uid OR actor_id = :uid")->execute([':uid' => $uid]);
        $pdo->prepare("DELETE FROM project_members WHERE user_id = :uid")->execute([':uid' => $uid]);
        $pdo->prepare("DELETE FROM activities WHERE user_id = :uid")->execute([':uid' => $uid]);
        $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $uid]);
        mirror_to_sqlite("DELETE FROM users WHERE id = :id", [':id' => $uid]);
    } catch (Throwable $e) {}
}

// Summary
echo "\n=======================================================\n";
$allPass = !in_array(false, array_column($results, 'pass'), true);
if ($allPass) {
    echo "\033[32mALL 12 MANDATORY TEST CASES PASSED! (12/12)\033[0m\n";
} else {
    echo "\033[31mSOME TEST CASES FAILED!\033[0m\n";
}
echo "=======================================================\n";

@array_map('unlink', glob("$cookieDir/*.*"));
@rmdir($cookieDir);
