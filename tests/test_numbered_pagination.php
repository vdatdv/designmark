<?php
// tests/test_numbered_pagination.php - Comprehensive test for Numbered Pagination & Server-side APIs

$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : '/usr/local/bin/php';

echo "=======================================================\n";
echo "DESIGNMARK NUMBERED PAGINATION & SERVER API TEST SUITE\n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function report($name, $ok, $detail = '') {
    global $passed, $total;
    $total++;
    if ($ok) $passed++;
    $status = $ok ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "Test $total: $status $name\n";
    if ($detail) echo "   -> $detail\n";
}

function run_sub_test($scriptCode) {
    global $php;
    $cmd = escapeshellcmd($php) . ' -r ' . escapeshellarg($scriptCode);
    $out = shell_exec($cmd);
    return json_decode(trim($out), true);
}

// 1. Screens Server-side Pagination Page 1 with limit 2
$data1 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['project_id'] = 'proj-shop';
\$_GET['page'] = '1';
\$_GET['limit'] = '2';
ob_start();
include 'api/screens.php';
echo ob_get_clean();
");

$ok1 = isset($data1['screens']) && count($data1['screens']) === 2 && 
       isset($data1['pagination']) && $data1['pagination']['page'] === 1 && 
       $data1['pagination']['limit'] === 2 && $data1['pagination']['totalPages'] >= 2;
report('Screens API: Phân trang phía server với limit & offset', $ok1, 
    "Screens: " . count($data1['screens'] ?? []) . ", Page: " . ($data1['pagination']['page'] ?? 0) . "/" . ($data1['pagination']['totalPages'] ?? 0));

// 2. Screens API Page 2
$data2 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['project_id'] = 'proj-shop';
\$_GET['page'] = '2';
\$_GET['limit'] = '2';
ob_start();
include 'api/screens.php';
echo ob_get_clean();
");

$ok2 = isset($data2['screens']) && count($data2['screens']) === 1 && 
       $data2['pagination']['page'] === 2;
report('Screens API: Chuyển sang trang 2 trả về đúng dữ liệu trang 2', $ok2,
    "Screens in page 2: " . count($data2['screens'] ?? []) . ", Screen ID: " . ($data2['screens'][0]['id'] ?? 'none'));

// 3. Screens API Search & Filter on server
$data3 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['project_id'] = 'proj-shop';
\$_GET['page'] = '1';
\$_GET['limit'] = '12';
\$_GET['q'] = 'Đăng ký';
ob_start();
include 'api/screens.php';
echo ob_get_clean();
");

$ok3 = isset($data3['screens']) && count($data3['screens']) === 1 && 
       strpos($data3['screens'][0]['name'], 'Đăng ký') !== false;
report('Screens API: Tìm kiếm kết hợp phân trang trả về chính xác', $ok3,
    "Matched: " . ($data3['screens'][0]['name'] ?? 'none'));

// 4. Screens API Backward Compatibility (flat array when no page requested)
$dataLegacy = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['project_id'] = 'proj-shop';
ob_start();
include 'api/screens.php';
echo ob_get_clean();
");

$ok4 = is_array($dataLegacy) && isset($dataLegacy[0]['id']) && !isset($dataLegacy['pagination']);
report('Screens API: Tương thích ngược trả về mảng trực tiếp khi không yêu cầu paginate', $ok4,
    "Total legacy screens: " . count($dataLegacy));

// 5. Projects Server-side Pagination
$dataP1 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['page'] = '1';
\$_GET['limit'] = '3';
ob_start();
include 'api/projects.php';
echo ob_get_clean();
");

$ok5 = isset($dataP1['projects']) && count($dataP1['projects']) === 3 &&
       isset($dataP1['pagination']) && $dataP1['pagination']['page'] === 1 &&
       $dataP1['pagination']['totalPages'] >= 2;
report('Projects API: Phân trang phía server với limit=3', $ok5,
    "Projects in page 1: " . count($dataP1['projects'] ?? []) . ", Total: " . ($dataP1['pagination']['total'] ?? 0));

// 6. Members API Server-side Pagination
$dataM1 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['id'] = 'proj-shop';
\$_GET['action'] = 'members';
\$_GET['page'] = '1';
\$_GET['limit'] = '2';
ob_start();
include 'api/projects.php';
echo ob_get_clean();
");

$ok6 = isset($dataM1['members']) && count($dataM1['members']) === 2 &&
       isset($dataM1['pagination']) && $dataM1['pagination']['page'] === 1 &&
       $dataM1['pagination']['total'] === 3;
report('Project Members API: Phân trang danh sách thành viên phía server', $ok6,
    "Members in page: " . count($dataM1['members'] ?? []) . ", Total: " . ($dataM1['pagination']['total'] ?? 0));

// 7. Activities API Server-side Pagination
$dataA1 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-an';
\$_GET['id'] = 'proj-shop';
\$_GET['action'] = 'activities';
\$_GET['page'] = '1';
\$_GET['limit'] = '5';
ob_start();
include 'api/projects.php';
echo ob_get_clean();
");

$ok7 = isset($dataA1['activities']) && count($dataA1['activities']) === 5 &&
       isset($dataA1['pagination']) && $dataA1['pagination']['page'] === 1 &&
       $dataA1['pagination']['total'] > 5;
report('Project Activities API: Phân trang timeline lịch sử phía server', $ok7,
    "Activities in page: " . count($dataA1['activities'] ?? []) . ", Total: " . ($dataA1['pagination']['total'] ?? 0));

// 8. Users API Server-side Pagination (Admin only)
$dataU1 = run_sub_test("
session_start();
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SESSION['user_id'] = 'user-admin';
\$_GET['action'] = 'list_all_users';
\$_GET['page'] = '1';
\$_GET['limit'] = '5';
ob_start();
include 'api/auth.php';
echo ob_get_clean();
");

$ok8 = isset($dataU1['users']) && count($dataU1['users']) === 5 &&
       isset($dataU1['pagination']) && $dataU1['pagination']['page'] === 1 &&
       $dataU1['pagination']['total'] >= 5;
report('Users API: Phân trang danh sách tài khoản phía server', $ok8,
    "Users in page: " . count($dataU1['users'] ?? []) . ", Total: " . ($dataU1['pagination']['total'] ?? 0));

echo "\n=======================================================\n";
echo "KẾT QUẢ: $passed / $total TESTS ĐẠT\n";
if ($passed === $total) {
    echo "🎉 TẤT CẢ CÁC TÍNH NĂNG PHÂN TRANG PHÍA SERVER HOẠT ĐỘNG HOÀN HẢO!\n";
} else {
    echo "⚠️ MỘT SỐ TEST CHƯA ĐẠT.\n";
}
echo "=======================================================\n";
