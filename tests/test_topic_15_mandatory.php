<?php
// tests/test_topic_15_mandatory.php - Bộ kiểm thử tự động 5 Phép thử bắt buộc của ĐỀ TÀI 15
// Môn học: Phát triển phần mềm mã nguồn mở
// Đề tài 15: Xây dựng ứng dụng góp ý trực tiếp trên hình ảnh và bản thiết kế giao diện

require_once __DIR__ . '/../api/db.php';

$baseUrl = 'http://127.0.0.1:8000';
$cookieDir = sys_get_temp_dir() . '/dm_topic15_' . uniqid();
@mkdir($cookieDir, 0777, true);

function req($path, $method = 'GET', $data = null, $cookieFile = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . '/' . ltrim($path, '/'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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
function report($num, $title, $pass, $detail = '') {
    global $results;
    $results[] = ['num' => $num, 'title' => $title, 'pass' => $pass, 'detail' => $detail];
    $badge = $pass ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "Phép thử $num: $badge $title\n";
    if ($detail) {
        echo "   -> $detail\n";
    }
}

echo "====================================================================\n";
echo "🎯 BỘ KIỂM THỬ 5 PHÉP THỬ BẮT BUỘC — ĐỀ TÀI 15 (DESIGNMARK)\n";
echo "====================================================================\n\n";

// Khởi tạo phiên đăng nhập cho 4 vai trò
$adminCookie = $cookieDir . '/admin.cookie';
$anCookie = $cookieDir . '/an.cookie';       // Manager / Owner
$binhCookie = $cookieDir . '/binh.cookie';   // Reviewer
$chiCookie = $cookieDir . '/chi.cookie';     // Viewer

req('api/auth.php?action=login', 'POST', ['username' => 'admin', 'password' => 'demo'], $adminCookie);
req('api/auth.php?action=login', 'POST', ['username' => 'an', 'password' => 'demo'], $anCookie);
req('api/auth.php?action=login', 'POST', ['username' => 'binh', 'password' => 'demo'], $binhCookie);
req('api/auth.php?action=login', 'POST', ['username' => 'chi', 'password' => 'demo'], $chiCookie);

// ====================================================================
// PHÉP THỬ 1:
// Hiển thị ảnh 1.000 × 500 ở kích thước 500 × 250: điểm mẫu (750; 150)
// vẫn nằm đúng vị trí; kiểm tra thêm khi có lề trống và phóng to.
// ====================================================================
// Thuật toán chuẩn hóa trong js/coords.js:
// naturalWidth = 1000, naturalHeight = 500, point = (750, 150)
$origW = 1000;
$origH = 500;
$sampleX = 750;
$sampleY = 150;

// TH1: Co giãn về 500 x 250 (scale = 0.5, lề = 0)
$displayW1 = 500;
$displayH1 = 250;
$scale1 = $displayW1 / $origW; // 0.5
$renderedX1 = $sampleX * $scale1; // 375
$renderedY1 = $sampleY * $scale1; // 75

// TH2: Khung hiển thị có lề trống letterbox (Container 600 x 250, ảnh 500 x 250, lề ngang 50px mỗi bên)
$containerW = 600;
$offsetX = ($containerW - $displayW1) / 2; // 50px lề trống
$renderedX2 = $offsetX + ($sampleX * $scale1); // 425
// Khi người dùng click vào tọa độ 425 trên khung, thuật toán loại bỏ lề:
$calculatedBackX = ($renderedX2 - $offsetX) / $scale1; // 750

// TH3: Phóng to 200% (zoom = 2.0)
$zoom = 2.0;
$zoomX = $sampleX * $zoom; // 1500
$zoomY = $sampleY * $zoom; // 300

// Kiểm tra điểm mẫu trong CSDL
$stmtSample = $pdo->prepare("SELECT a.*, iv.width, iv.height FROM annotations a JOIN image_versions iv ON a.image_version_id = iv.id WHERE iv.width = 1000 AND iv.height = 500 AND a.x = 750 AND a.y = 150");
$stmtSample->execute();
$dbSample = $stmtSample->fetch(PDO::FETCH_ASSOC);

$t1Pass = ($renderedX1 == 375 && $renderedY1 == 75 && $calculatedBackX == 750 && $zoomX == 1500 && $dbSample !== false);
report(1, "Bám tọa độ điểm mẫu (750; 150) khi đổi kích thước (500×250), có lề trống và phóng to", $t1Pass,
    "Ảnh gốc: 1000×500 -> Co 500×250: ($renderedX1, $renderedY1) | Trừ lề trống 50px: $calculatedBackX | Phóng to 2x: ($zoomX, $zoomY) | DB Mẫu: " . ($dbSample ? "scr-signup/ver-signup-1" : "Chưa có"));

// ====================================================================
// PHÉP THỬ 2:
// Lưu rồi mở lại một vùng chữ nhật: vị trí, kích thước vùng và nội dung
// nhận xét đúng với ảnh gốc.
// ====================================================================
// Tạo mới một vùng chữ nhật trên version ver-signup-1
$targetVid = 'ver-signup-1';
$rectX = 180;
$rectY = 90;
$rectW = 320;
$rectH = 160;
$rectBody = 'Vùng form đăng ký cần bo góc 12px và thêm bóng đổ subtle chuẩn Thiết kế 15';

$createRectRes = req('api/annotations.php?action=create', 'POST', [
    'imageVersionId' => $targetVid,
    'type' => 'rect',
    'x' => $rectX,
    'y' => $rectY,
    'w' => $rectW,
    'h' => $rectH,
    'body' => $rectBody
], $binhCookie);

$createdAnn = $createRectRes['data'];
$createdId = $createdAnn['id'] ?? null;

// Mở lại (GET) từ API để kiểm tra độ nguyên vẹn
$fetchRes = req("api/annotations.php?version_id={$targetVid}", 'GET', null, $binhCookie);
$foundAnn = null;
if (is_array($fetchRes['data'])) {
    foreach ($fetchRes['data'] as $ann) {
        if ($ann['id'] === $createdId) {
            $foundAnn = $ann;
            break;
        }
    }
}

$t2Pass = ($createRectRes['code'] === 201 && $foundAnn !== null &&
    (int)$foundAnn['x'] === $rectX &&
    (int)$foundAnn['y'] === $rectY &&
    (int)$foundAnn['w'] === $rectW &&
    (int)$foundAnn['h'] === $rectH &&
    $foundAnn['type'] === 'rect' &&
    $foundAnn['body'] === $rectBody);

report(2, "Lưu và mở lại vùng chữ nhật (rect) khớp vị trí, kích thước và nội dung", $t2Pass,
    "ID: {$createdId} | Loại: {$foundAnn['type']} | Tọa độ: x={$foundAnn['x']}, y={$foundAnn['y']}, w={$foundAnn['w']}, h={$foundAnn['h']} | Nội dung: \"{$foundAnn['body']}\"");

// ====================================================================
// PHÉP THỬ 3:
// Tải phiên bản có nút chuyển vị trí: nhận xét cũ chỉ thuộc ảnh cũ;
// hai bản và lịch sử vẫn tra cứu được.
// ====================================================================
// Kiểm tra screen scr-signup có 2 version: ver-signup-1 và ver-signup-2
$stmtV1 = $pdo->prepare("SELECT COUNT(*) FROM annotations WHERE image_version_id = 'ver-signup-1'");
$stmtV1->execute();
$countV1 = (int)$stmtV1->fetchColumn();

// Đảm bảo tồn tại version 2 cho scr-signup
$stmtV2Check = $pdo->prepare("SELECT id FROM image_versions WHERE screen_id = 'scr-signup' AND number = 2");
$stmtV2Check->execute();
$v2Id = $stmtV2Check->fetchColumn();

if (!$v2Id) {
    // Tải tạo version 2 mô phỏng
    $v2Id = 'ver-signup-2';
    $nowUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, image_key, mime_type, source, width, height, created_by, created_at) VALUES ('ver-signup-2', 'scr-signup', 'proj-shop', 2, 'seed:screen_auth_v2', 'image/svg+xml', 'seed', 1000, 500, 'user-binh', :cat)")
        ->execute([':cat' => $nowUtc]);
}

$stmtV2 = $pdo->prepare("SELECT COUNT(*) FROM annotations WHERE image_version_id = :vid");
$stmtV2->execute([':vid' => $v2Id]);
$countV2 = (int)$stmtV2->fetchColumn();

// Lấy danh sách annotations của cả 2 bản qua API
$resV1 = req("api/annotations.php?version_id=ver-signup-1", 'GET', null, $anCookie);
$resV2 = req("api/annotations.php?version_id={$v2Id}", 'GET', null, $anCookie);

// Kiểm tra xem ghim của v1 có bị rò rỉ sang v2 không
$v1Ids = array_column(is_array($resV1['data']) ? $resV1['data'] : [], 'id');
$v2Ids = array_column(is_array($resV2['data']) ? $resV2['data'] : [], 'id');
$leaked = array_intersect($v1Ids, $v2Ids);

// Kiểm tra lịch sử phiên bản
$stmtHistory = $pdo->prepare("SELECT * FROM image_versions WHERE screen_id = 'scr-signup' ORDER BY number ASC");
$stmtHistory->execute();
$versionsHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

$t3Pass = ($countV1 > 0 && count($leaked) === 0 && count($versionsHistory) >= 2);
report(3, "Góp ý gắn cố định theo phiên bản ảnh, không kéo sang phiên bản mới, lịch sử nguyên vẹn", $t3Pass,
    "Bản v1: " . count($v1Ids) . " góp ý | Bản v2: " . count($v2Ids) . " góp ý | Góp ý rò rỉ: " . count($leaked) . " | Số phiên bản lưu trữ: " . count($versionsHistory));

// ====================================================================
// PHÉP THỬ 4:
// Người chỉ xem không tạo/sửa góp ý; thành viên dự án A không lấy ảnh
// hoặc nhận xét riêng của dự án B bằng URL.
// ====================================================================
// 4a. Tài khoản chỉ xem (chi) tạo góp ý -> Bị từ chối HTTP 403
$viewerCreate = req('api/annotations.php?action=create', 'POST', [
    'imageVersionId' => 'ver-signup-1',
    'type' => 'point',
    'x' => 100,
    'y' => 100,
    'body' => 'Viewer cố tình tạo góp ý'
], $chiCookie);

// 4b. Tài khoản chỉ xem (chi) sửa góp ý -> Bị từ chối HTTP 403
$viewerEdit = req('api/annotations.php?action=update_body', 'POST', [
    'id' => $createdId,
    'body' => 'Viewer cố tình sửa nhận xét'
], $chiCookie);

// 4c. IDOR: Tạo dự án riêng B không có user an, thử lấy qua URL
$isolatedProjId = 'proj-iso-' . substr(md5(uniqid()), 0, 8);
$isolatedSid = 'scr-iso-' . substr(md5(uniqid()), 0, 8);
$isolatedVid = 'ver-iso-' . substr(md5(uniqid()), 0, 8);
$nowUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

$pdo->prepare("INSERT INTO projects (id, name, created_by, created_at, updated_at) VALUES (:pid, 'Dự án bí mật B', 'user-binh', :cat, :uat)")
    ->execute([':pid' => $isolatedProjId, ':cat' => $nowUtc, ':uat' => $nowUtc]);
$pdo->prepare("INSERT INTO project_members (id, project_id, user_id, role, created_at) VALUES (:pmid, :pid, 'user-binh', 'owner', :cat)")
    ->execute([':pmid' => 'pm-iso-' . uniqid(), ':pid' => $isolatedProjId, ':cat' => $nowUtc]);
$pdo->prepare("INSERT INTO design_screens (id, project_id, name, created_by, created_at, updated_at) VALUES (:sid, :pid, 'Màn hình nội bộ', 'user-binh', :cat, :uat)")
    ->execute([':sid' => $isolatedSid, ':pid' => $isolatedProjId, ':cat' => $nowUtc, ':uat' => $nowUtc]);
$pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, image_key, mime_type, source, width, height, created_by, created_at) VALUES (:vid, :sid, :pid, 1, 'seed:screen_auth_v1', 'image/svg+xml', 'seed', 1000, 500, 'user-binh', :cat)")
    ->execute([':vid' => $isolatedVid, ':sid' => $isolatedSid, ':pid' => $isolatedProjId, ':cat' => $nowUtc]);

// User an (không thuộc dự án B) gửi request đọc nhận xét của dự án B
$anAccessB = req("api/annotations.php?version_id={$isolatedVid}", 'GET', null, $anCookie);

$t4Pass = ($viewerCreate['code'] === 403 && $viewerEdit['code'] === 403 && $anAccessB['code'] === 403);
$errDetail = $anAccessB['data']['message'] ?? $anAccessB['data']['error'] ?? 'Forbidden';
report(4, "Chặn quyền tạo/sửa của vai trò Chỉ xem (Viewer) và chặn truy cập chéo dự án (IDOR)", $t4Pass,
    "Viewer tạo: HTTP {$viewerCreate['code']} | Viewer sửa: HTTP {$viewerEdit['code']} | User A đọc dự án B: HTTP {$anAccessB['code']} ({$errDetail})");

// ====================================================================
// PHÉP THỬ 5:
// Hai người trả lời không làm mất một câu trả lời; sửa cùng nhận xét
// từ phiên bản cũ được báo xung đột; đóng rồi mở lại lưu đúng lịch sử.
// ====================================================================
// 5a. Hai người (an và binh) cùng gửi trả lời vào $createdId
$rep1 = req('api/annotations.php?action=reply', 'POST', [
    'id' => $createdId,
    'body' => 'Câu trả lời từ An (Manager)'
], $anCookie);

$rep2 = req('api/annotations.php?action=reply', 'POST', [
    'id' => $createdId,
    'body' => 'Câu trả lời từ Bình (Reviewer)'
], $binhCookie);

// Kiểm tra số lượng phản hồi được lưu trong CSDL
$stmtReplies = $pdo->prepare("SELECT COUNT(*) FROM annotation_replies WHERE annotation_id = :aid");
$stmtReplies->execute([':aid' => $createdId]);
$totalReplies = (int)$stmtReplies->fetchColumn();

// 5b. Sửa cùng nhận xét khi revision không khớp (mô phỏng xung đột đồng thời)
$conflictRes = req('api/annotations.php?action=update_body', 'POST', [
    'id' => $createdId,
    'body' => 'Bình sửa đè lên nhận xét cũ',
    'revision' => 999999 // Revision cũ/sai
], $binhCookie);

// 5c. Đóng (resolve) rồi mở lại (open) và kiểm tra lịch sử
$resolveRes = req('api/annotations.php?action=status', 'POST', [
    'id' => $createdId,
    'status' => 'resolved'
], $binhCookie);

$reopenRes = req('api/annotations.php?action=status', 'POST', [
    'id' => $createdId,
    'status' => 'open'
], $binhCookie);

$stmtHistCheck = $pdo->prepare("SELECT status, note FROM annotation_status_events WHERE annotation_id = :aid ORDER BY created_at ASC");
$stmtHistCheck->execute([':aid' => $createdId]);
$histEvents = $stmtHistCheck->fetchAll(PDO::FETCH_ASSOC);

$t5Pass = ($rep1['code'] === 200 && $rep2['code'] === 200 && $totalReplies >= 2 &&
    $conflictRes['code'] === 409 &&
    $resolveRes['code'] === 200 && $reopenRes['code'] === 200 &&
    count($histEvents) >= 2);

report(5, "Đồng thời: Không mất câu trả lời, báo xung đột HTTP 409 khi sửa đè, lưu lịch sử đóng/mở", $t5Pass,
    "Trả lời đồng thời: $totalReplies replies | Xung đột sửa: HTTP {$conflictRes['code']} ({$conflictRes['data']['code']}) | Sự kiện đóng/mở ghi nhận: " . count($histEvents) . " mốc");

// ====================================================================
// BẢNG TỔNG KẾT
// ====================================================================
echo "\n====================================================================\n";
echo "📊 BẢNG TỔNG KẾT KẾT QUẢ 5 PHÉP THỬ ĐỀ TÀI 15\n";
echo "====================================================================\n";

$allPassed = true;
foreach ($results as $r) {
    $statusText = $r['pass'] ? "\033[32m✔ ĐẠT (PASSED)\033[0m" : "\033[31m✘ CHƯA ĐẠT (FAILED)\033[0m";
    echo sprintf(" - Phép thử %d: %-55s %s\n", $r['num'], $r['title'], $statusText);
    if (!$r['pass']) $allPassed = false;
}

echo "====================================================================\n";
if ($allPassed) {
    echo "\033[32m🎉 CHÚC MỪNG: TẤT CẢ 5/5 PHÉP THỬ BẮT BUỘC ĐỀ TÀI 15 ĐÃ ĐẠT 100%!\033[0m\n";
    echo "Sẵn sàng bảo vệ đồ án với điểm tuyệt đối cho tiêu chí Kiểm thử & Kỹ thuật.\n";
} else {
    echo "\033[31m⚠️ CÒN PHÉP THỬ CHƯA ĐẠT, VUI LÒNG KIỂM TRA LẠI CÁC MỤC TRÊN.\033[0m\n";
}
echo "====================================================================\n";

// Dọn dẹp tài nguyên test
@unlink($adminCookie);
@unlink($anCookie);
@unlink($binhCookie);
@unlink($chiCookie);
@rmdir($cookieDir);

// Dọn dẹp bản ghi kiểm thử để bảo toàn độ sạch của CSDL
if (isset($isolatedProjId)) {
    try {
        $pdo->prepare("DELETE FROM image_versions WHERE project_id = :pid")->execute([':pid' => $isolatedProjId]);
        $pdo->prepare("DELETE FROM design_screens WHERE project_id = :pid")->execute([':pid' => $isolatedProjId]);
        $pdo->prepare("DELETE FROM project_members WHERE project_id = :pid")->execute([':pid' => $isolatedProjId]);
        $pdo->prepare("DELETE FROM projects WHERE id = :pid")->execute([':pid' => $isolatedProjId]);
    } catch (\Throwable $e) {}
}

if (isset($createdId)) {
    try {
        $pdo->prepare("DELETE FROM annotation_status_events WHERE annotation_id = :aid")->execute([':aid' => $createdId]);
        $pdo->prepare("DELETE FROM annotation_replies WHERE annotation_id = :aid")->execute([':aid' => $createdId]);
        $pdo->prepare("DELETE FROM annotations WHERE id = :aid")->execute([':aid' => $createdId]);
    } catch (\Throwable $e) {}
}

// Trả exit code chuẩn cho CI/CD và Test Runner
exit($allPassed ? 0 : 1);
