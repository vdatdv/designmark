<?php
// tests/test_large_scale_annotations.php - Test 100+ Annotations, Pagination, Filtering, Canvas Sync, and Lazy Replies

require_once __DIR__ . '/../api/db.php';

echo "=======================================================\n";
echo "TEST SUITE: LARGE-SCALE ANNOTATIONS (100 ANNS & 50 REPLIES)\n";
echo "=======================================================\n\n";

$baseUrl = 'http://localhost:8000';

function test_req($path, $method = 'GET', $data = null, $cookies = '') {
    global $baseUrl;
    $url = $baseUrl . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $headers = [];
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $headerSize = $info['header_size'];
    $headerStr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    $resCookies = '';
    if (preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headerStr, $m)) {
        $resCookies = implode('; ', $m[1]);
    }

    return [
        'status' => $info['http_code'],
        'json' => json_decode($body, true),
        'cookies' => $resCookies,
        'raw' => $body
    ];
}

// 1. Login as @an
$loginRes = test_req('api/auth.php?action=login', 'POST', [
    'username' => 'an',
    'password' => 'demo'
]);

if ($loginRes['status'] !== 200) {
    die("❌ Login failed: " . $loginRes['raw'] . "\n");
}
$anCookie = $loginRes['cookies'];
echo "[PASS] 1. Đăng nhập thành công với tài khoản Manager (@an)\n";

// 2. Prepare test dataset of 100 annotations
$testVersionId = 'ver-scale-test';
$testProjectId = 'proj-scale';
$testScreenId = 'scr-scale';

// Create test project and screen if needed directly in DB
$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

// Clean up any old test records first
$pdo->prepare("DELETE FROM annotations WHERE image_version_id = :vid")->execute([':vid' => $testVersionId]);
$pdo->prepare("DELETE FROM image_versions WHERE id = :vid")->execute([':vid' => $testVersionId]);
$pdo->prepare("DELETE FROM design_screens WHERE id = :sid")->execute([':sid' => $testScreenId]);
$pdo->prepare("DELETE FROM project_members WHERE project_id = :pid")->execute([':pid' => $testProjectId]);
$pdo->prepare("DELETE FROM projects WHERE id = :pid")->execute([':pid' => $testProjectId]);

$pdo->prepare("INSERT INTO projects (id, name, description, created_at, updated_at, created_by) VALUES (:id, 'Dự án Thử nghiệm Quy mô lớn', 'Test 100+ Annotations', :cat, :uat, 'user-an')")
    ->execute([':id' => $testProjectId, ':cat' => $now, ':uat' => $now]);

$pdo->prepare("INSERT INTO project_members (id, project_id, user_id, role, created_at) VALUES ('pm-scale-an', :pid, 'user-an', 'manager', :cat)")
    ->execute([':pid' => $testProjectId, ':cat' => $now]);

$pdo->prepare("INSERT INTO design_screens (id, project_id, name, description, created_at, updated_at, created_by) VALUES (:sid, :pid, 'Màn hình Scale Test', '', :cat, :uat, 'user-an')")
    ->execute([':sid' => $testScreenId, ':pid' => $testProjectId, ':cat' => $now, ':uat' => $now]);

$pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:vid, :sid, :pid, 1, 1440, 900, 'img-scale-1', 'image/png', 'upload', :cat, 'user-an')")
    ->execute([':vid' => $testVersionId, ':sid' => $testScreenId, ':pid' => $testProjectId, ':cat' => $now]);

echo "-> Đang tạo 100 góp ý giả lập với nhiều trạng thái và 50 phản hồi...\n";
$users = ['user-an', 'user-binh', 'user-chi'];
$createdAnnIds = [];
$targetSpecialAnnId = null;

$pdo->beginTransaction();

for ($i = 1; $i <= 100; $i++) {
    $annId = "ann-scale-" . sprintf('%03d', $i);
    $status = ($i % 3 === 0) ? 'resolved' : 'open'; // 67 open, 33 resolved
    $author = $users[$i % count($users)];
    $type = ($i % 4 === 0) ? 'rect' : (($i % 4 === 1) ? 'point' : (($i % 4 === 2) ? 'arrow' : 'freehand'));
    $body = "Góp ý kiểm thử quy mô lớn #{$i}: Tối ưu hóa UI/UX và xử lý dữ liệu cho thẻ thứ {$i}.";

    $x = ($i * 13) % 1300 + 50;
    $y = ($i * 17) % 800 + 50;
    $time = date('Y-m-d\TH:i:s.000\Z', strtotime("-".(101 - $i)." hours"));

    $stmt = $pdo->prepare("
        INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, number, created_at, updated_at)
        VALUES (:id, :pid, :sid, :vid, :type, :x, :y, 80, 40, 1440, 900, :author, :body, :status, 1, :num, :cat, :uat)
    ");
    $stmt->execute([
        ':id' => $annId,
        ':pid' => $testProjectId,
        ':sid' => $testScreenId,
        ':vid' => $testVersionId,
        ':type' => $type,
        ':x' => $x,
        ':y' => $y,
        ':author' => $author,
        ':body' => $body,
        ':status' => $status,
        ':num' => $i,
        ':cat' => $time,
        ':uat' => $time
    ]);

    $createdAnnIds[] = $annId;

    // Seed 50 replies on annotation #1
    if ($i === 1) {
        $targetSpecialAnnId = $annId;
        for ($r = 1; $r <= 50; $r++) {
            $repId = "rep-scale-1-{$r}";
            $repAuthor = $users[$r % count($users)];
            $repBody = "Phản hồi thứ {$r} trên góp ý #1: cập nhật phương án xử lý số {$r}.";
            $repTime = date('Y-m-d\TH:i:s.000\Z', strtotime("+{$r} minutes", strtotime($time)));
            $pdo->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES (:id, :aid, :author, :body, :cat)")
                ->execute([':id' => $repId, ':aid' => $annId, ':author' => $repAuthor, ':body' => $repBody, ':cat' => $repTime]);
        }
    } elseif ($i % 10 === 0) {
        // Seed 10 replies on every 10th annotation
        for ($r = 1; $r <= 10; $r++) {
            $repId = "rep-scale-{$i}-{$r}";
            $repAuthor = $users[$r % count($users)];
            $repBody = "Phản hồi mẫu {$r} cho thẻ #{$i}.";
            $repTime = date('Y-m-d\TH:i:s.000\Z', strtotime("+{$r} minutes", strtotime($time)));
            $pdo->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES (:id, :aid, :author, :body, :cat)")
                ->execute([':id' => $repId, ':aid' => $annId, ':author' => $repAuthor, ':body' => $repBody, ':cat' => $repTime]);
        }
    }
}

$pdo->commit();
echo "[PASS] 2. Khởi tạo thành công 100 góp ý và 50 phản hồi trên góp ý #1\n";

// ==========================================
// TEST 1: Server-side pagination (Page 1)
// ==========================================
$p1Res = test_req("api/annotations.php?version_id={$testVersionId}&page=1&limit=10", 'GET', null, $anCookie);
if ($p1Res['status'] !== 200 || !isset($p1Res['json']['pagination'])) {
    die("❌ Test 1 FAILED: " . $p1Res['raw'] . "\n");
}
$p1Data = $p1Res['json'];
assert($p1Data['pagination']['total'] === 100, 'Total should be 100');
assert($p1Data['pagination']['totalPages'] === 10, 'Total pages should be 10');
assert(count($p1Data['items']) === 10, 'Items count should be 10');
assert($p1Data['pagination']['start'] === 1, 'Start should be 1');
assert($p1Data['pagination']['end'] === 10, 'End should be 10');
assert(count($p1Data['markers']) === 100, 'All 100 markers returned for canvas');
echo "[PASS] 3. Phân trang trang 1 (10 items/trang, tổng 100, trả về đủ 100 markers cho canvas)\n";

// ==========================================
// TEST 2: Pagination Page 5
// ==========================================
$p5Res = test_req("api/annotations.php?version_id={$testVersionId}&page=5&limit=10", 'GET', null, $anCookie);
$p5Data = $p5Res['json'];
assert($p5Data['pagination']['page'] === 5, 'Page should be 5');
assert($p5Data['pagination']['start'] === 41, 'Start should be 41');
assert($p5Data['pagination']['end'] === 50, 'End should be 50');
assert(count($p5Data['items']) === 10, 'Items count should be 10');
echo "[PASS] 4. Phân trang trang 5 (phạm vi kết quả 41-50, dữ liệu chính xác)\n";

// ==========================================
// TEST 3: Lazy Loading / Hiệu năng tải phản hồi
// ==========================================
// In list view, annotation #1 should only return 2 recent replies and replyCount=50
$ann1ListRes = test_req("api/annotations.php?version_id={$testVersionId}&sort=oldest&page=1&limit=10", 'GET', null, $anCookie);
$ann1Item = $ann1ListRes['json']['items'][0];
assert($ann1Item['number'] === 1, 'First item should be #1');
assert($ann1Item['replyCount'] === 50, 'replyCount should be 50');
assert(count($ann1Item['replies']) === 2, 'Default replies in card should be limited to 2');
assert($ann1Item['hasMoreReplies'] === true, 'hasMoreReplies should be true');

// Call action=replies to lazy-load all 50 replies
$repliesRes = test_req("api/annotations.php?id={$targetSpecialAnnId}&action=replies", 'GET', null, $anCookie);
assert($repliesRes['status'] === 200, 'Status should be 200');
assert($repliesRes['json']['total'] === 50, 'Total replies should be 50');
assert(count($repliesRes['json']['replies']) === 50, 'Full 50 replies returned on demand');
echo "[PASS] 5. Tối ưu hiệu năng: Thẻ góp ý chỉ tải 2 phản hồi gần nhất, tải toàn bộ 50 phản hồi theo yêu cầu (Lazy loading)\n";

// ==========================================
// TEST 4: Lọc theo trạng thái (Filter status=open)
// ==========================================
$openRes = test_req("api/annotations.php?version_id={$testVersionId}&status=open&limit=100", 'GET', null, $anCookie);
$openData = $openRes['json'];
assert($openData['pagination']['total'] === 67, 'Expected 67 open annotations');
foreach ($openData['items'] as $item) {
    assert($item['status'] === 'open', 'All items must be open');
}
echo "[PASS] 6. Lọc trạng thái 'Chưa xử lý' (67/100 góp ý open)\n";

// ==========================================
// TEST 5: Lọc theo trạng thái đã xử lý (Filter status=resolved)
// ==========================================
$resRes = test_req("api/annotations.php?version_id={$testVersionId}&status=resolved&limit=100", 'GET', null, $anCookie);
$resData = $resRes['json'];
assert($resData['pagination']['total'] === 33, 'Expected 33 resolved annotations');
foreach ($resData['items'] as $item) {
    assert($item['status'] === 'resolved', 'All items must be resolved');
}
echo "[PASS] 7. Lọc trạng thái 'Đã xử lý' (33/100 góp ý resolved)\n";

// ==========================================
// TEST 6: Tìm kiếm theo mã góp ý (Search #50)
// ==========================================
$qRes = test_req("api/annotations.php?version_id={$testVersionId}&q=%2350", 'GET', null, $anCookie);
$qData = $qRes['json'];
assert($qData['pagination']['total'] === 1, 'Expected 1 item for #50');
assert($qData['items'][0]['number'] === 50, 'Found annotation #50');
echo "[PASS] 8. Tìm kiếm theo mã góp ý '#50' (chính xác 1 kết quả)\n";

// ==========================================
// TEST 7: Sắp xếp theo nhiều phản hồi nhất (sort=most_replies)
// ==========================================
$sortRes = test_req("api/annotations.php?version_id={$testVersionId}&sort=most_replies&page=1&limit=10", 'GET', null, $anCookie);
$topItem = $sortRes['json']['items'][0];
assert($topItem['id'] === $targetSpecialAnnId, 'Góp ý #1 với 50 phản hồi phải đứng đầu khi xếp theo most_replies');
assert($topItem['replyCount'] === 50, 'Top item has 50 replies');
echo "[PASS] 9. Sắp xếp theo 'Nhiều phản hồi nhất' (góp ý có 50 phản hồi đứng đầu trang 1)\n";

// ==========================================
// TEST 8: Đồng bộ Canvas (Canvas Sync - locate target annotation page)
// ==========================================
// Annotation ann-scale-077 under 'newest' sort is on page 3 or 8 depending on sort
$targetAnn = "ann-scale-077";
$syncRes = test_req("api/annotations.php?version_id={$testVersionId}&sort=newest&limit=10&target_ann_id={$targetAnn}&auto_jump=1", 'GET', null, $anCookie);
$syncData = $syncRes['json'];
$foundOnPage = false;
foreach ($syncData['items'] as $item) {
    if ($item['id'] === $targetAnn) {
        $foundOnPage = true;
        break;
    }
}
assert($foundOnPage === true, "Canvas sync: Target annotation {$targetAnn} automatically located on page {$syncData['pagination']['page']}");
echo "[PASS] 10. Đồng bộ với Canvas: Bấm marker trên ảnh tự động tìm và chuyển đúng trang chứa góp ý (#{$targetAnn} trên trang {$syncData['pagination']['page']})\n";

// Clean up
$pdo->prepare("DELETE FROM annotations WHERE image_version_id = :vid")->execute([':vid' => $testVersionId]);
$pdo->prepare("DELETE FROM image_versions WHERE id = :vid")->execute([':vid' => $testVersionId]);
$pdo->prepare("DELETE FROM design_screens WHERE id = :sid")->execute([':sid' => $testScreenId]);
$pdo->prepare("DELETE FROM projects WHERE id = :pid")->execute([':pid' => $testProjectId]);

echo "\n=======================================================\n";
echo "TEST RESULTS: 10 / 10 PASSED\n";
echo "🎉 HỆ THỐNG QUẢN LÝ GÓP Ý QUY MÔ LỚN (50-500 GÓP Ý, 50 PHẢN HỒI) HOẠT ĐỘNG HOÀN HẢO!\n";
echo "=======================================================\n";
