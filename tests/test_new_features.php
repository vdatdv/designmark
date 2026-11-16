<?php
// tests/test_new_features.php - Test new feature upgrades:
// 1. Arrow & Freehand annotations
// 2. Mention @username & comment attachments
// 3. Screen drag-and-drop reordering
// 4. Migration of unresolved annotations between versions

require_once __DIR__ . '/../api/db.php';

echo "=======================================================\n";
echo "DESIGNMARK UPGRADE FEATURE TEST SUITE\n";
echo "=======================================================\n\n";

$baseUrl = 'http://localhost:8000';
$cookieDir = sys_get_temp_dir() . '/dm_test_feat_' . uniqid();
mkdir($cookieDir, 0777, true);

function req($endpoint, $method = 'GET', $data = null, $cookieFile = null) {
    global $baseUrl;
    $url = $baseUrl . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    $decoded = json_decode($raw, true);
    return ['code' => $code, 'data' => $decoded, 'raw' => $raw];
}

$passed = 0;
$total = 0;

function report($num, $desc, $ok, $details = '') {
    global $passed, $total;
    $total++;
    if ($ok) $passed++;
    $status = $ok ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "Feature $num: $status $desc\n";
    if ($details) echo "   -> $details\n";
}

// Login as user-an (Manager on proj-shop)
$anCookie = $cookieDir . '/an.cookie';
$loginAn = req('api/auth.php?action=login', 'POST', ['username' => 'an', 'password' => 'demo'], $anCookie);
report(1, 'Đăng nhập tài khoản Manager (@an)', $loginAn['code'] === 200, "HTTP {$loginAn['code']}");

// TEST 2: Arrow Annotation Creation
$arrowRes = req('api/annotations.php?action=create', 'POST', [
    'imageVersionId' => 'ver-signup-1',
    'type' => 'arrow',
    'x' => 100,
    'y' => 100,
    'w' => 120,
    'h' => 80,
    'drawingData' => ['x1' => 100, 'y1' => 100, 'x2' => 220, 'y2' => 180],
    'body' => 'Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký'
], $anCookie);

$arrowAnnId = $arrowRes['data']['id'] ?? null;
$t2Ok = ($arrowRes['code'] === 201) && (($arrowRes['data']['type'] ?? '') === 'arrow') && !empty($arrowRes['data']['drawingData']);
report(2, 'Tạo góp ý dạng Mũi tên (Arrow)', $t2Ok, "HTTP {$arrowRes['code']}, ID: {$arrowAnnId}, Raw: " . substr($arrowRes['raw'] ?? '', 0, 100));

// TEST 3: Freehand Annotation Creation
$freehandRes = req('api/annotations.php?action=create', 'POST', [
    'imageVersionId' => 'ver-signup-1',
    'type' => 'freehand',
    'x' => 50,
    'y' => 50,
    'w' => 100,
    'h' => 100,
    'drawingData' => [['x' => 50, 'y' => 50], ['x' => 80, 'y' => 60], ['x' => 120, 'y' => 90], ['x' => 150, 'y' => 150]],
    'body' => 'Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này'
], $anCookie);

$freehandAnnId = $freehandRes['data']['id'] ?? null;
$t3Ok = ($freehandRes['code'] === 201) && ($freehandRes['data']['type'] === 'freehand') && !empty($freehandRes['data']['drawingData']);
report(3, 'Tạo góp ý dạng Vẽ tự do (Freehand)', $t3Ok, "HTTP {$freehandRes['code']}, ID: {$freehandAnnId}, Type: " . ($freehandRes['data']['type'] ?? ''));

// TEST 4: Mention Notification verification
// Login as user-binh
$binhCookie = $cookieDir . '/binh.cookie';
req('api/auth.php?action=login', 'POST', ['username' => 'binh', 'password' => 'demo'], $binhCookie);
$notifsBinh = req('api/notifications.php?action=list', 'GET', null, $binhCookie);
$hasMention = false;
$notifList = $notifsBinh['data']['notifications'] ?? [];
if (is_array($notifList)) {
    foreach ($notifList as $notif) {
        if (($notif['type'] ?? '') === 'mention') {
            $hasMention = true;
            break;
        }
    }
}
report(4, 'Bắn thông báo khi được nhắc tên (@username)', $hasMention, "Mention notification found for user @binh: " . ($hasMention ? 'YES' : 'NO'));

// TEST 5: Comment Reply with Image Attachment & Mention
$replyRes = req("api/annotations.php?id={$arrowAnnId}&action=reply", 'POST', [
    'body' => 'Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất.',
    'attachmentUrl' => 'uploads/demo_button_style.svg'
], $binhCookie);

$t5Ok = ($replyRes['code'] === 200) && !empty($replyRes['data']['replies']);
$latestReply = end($replyRes['data']['replies']);
$hasAttachment = ($latestReply['attachmentUrl'] ?? '') === 'uploads/demo_button_style.svg';
report(5, 'Phản hồi bình luận có đính kèm ảnh và nhắc tên @an', $t5Ok && $hasAttachment, "HTTP {$replyRes['code']}, Attachment URL: " . ($latestReply['attachmentUrl'] ?? 'none'));

// TEST 6: Screen Drag-and-Drop Reordering
// Reorder screens of proj-shop: ['scr-pay', 'scr-signup', 'scr-home']
$reorderRes = req('api/screens.php?action=reorder', 'POST', [
    'projectId' => 'proj-shop',
    'order' => ['scr-pay', 'scr-signup', 'scr-home']
], $anCookie);

// Verify new order in GET screens
$screensRes = req('api/screens.php?project_id=proj-shop', 'GET', null, $anCookie);
$screenIds = array_column($screensRes['data'] ?? [], 'id');
$t6Ok = ($reorderRes['code'] === 200) && ($screenIds[0] === 'scr-pay') && ($screenIds[1] === 'scr-signup');
report(6, 'Kéo thả thay đổi thứ tự màn hình (Screen Reorder)', $t6Ok, "New screens order: " . implode(' -> ', $screenIds));

// Reorder back to original order: ['scr-signup', 'scr-home', 'scr-pay']
req('api/screens.php?action=reorder', 'POST', [
    'projectId' => 'proj-shop',
    'order' => ['scr-signup', 'scr-home', 'scr-pay']
], $anCookie);

// TEST 7: Migration of unresolved annotations from previous version
// Migrate from ver-signup-1 to ver-signup-2
$migrateRes = req('api/annotations.php?action=migrate_unresolved', 'POST', [
    'targetVersionId' => 'ver-signup-2',
    'sourceVersionId' => 'ver-signup-1'
], $anCookie);

$migratedCount = $migrateRes['data']['count'] ?? 0;
// Check that target version now has copied annotations with note "Chuyển từ phiên bản v1"
$t7Ok = ($migrateRes['code'] === 200) && ($migratedCount > 0);
report(7, 'Chuyển góp ý chưa xử lý từ phiên bản cũ sang phiên bản mới', $t7Ok, "Migrated count: {$migratedCount}, Message: " . ($migrateRes['data']['message'] ?? ''));

// Clean up test annotations created by this test
global $pdo;
if ($arrowAnnId) {
    $pdo->prepare("DELETE FROM annotation_replies WHERE annotation_id = :id")->execute([':id' => $arrowAnnId]);
    $pdo->prepare("DELETE FROM annotation_status_events WHERE annotation_id = :id")->execute([':id' => $arrowAnnId]);
    $pdo->prepare("DELETE FROM annotations WHERE id = :id")->execute([':id' => $arrowAnnId]);
    mirror_to_sqlite("DELETE FROM annotations WHERE id = :id", [':id' => $arrowAnnId]);
}
if ($freehandAnnId) {
    $pdo->prepare("DELETE FROM annotation_replies WHERE annotation_id = :id")->execute([':id' => $freehandAnnId]);
    $pdo->prepare("DELETE FROM annotation_status_events WHERE annotation_id = :id")->execute([':id' => $freehandAnnId]);
    $pdo->prepare("DELETE FROM annotations WHERE id = :id")->execute([':id' => $freehandAnnId]);
    mirror_to_sqlite("DELETE FROM annotations WHERE id = :id", [':id' => $freehandAnnId]);
}
// Clean up migrated annotations on ver-signup-2
$pdo->prepare("DELETE FROM annotations WHERE image_version_id = 'ver-signup-2' AND check_note LIKE 'Chuyển từ phiên bản%'")->execute();
mirror_to_sqlite("DELETE FROM annotations WHERE image_version_id = 'ver-signup-2' AND check_note LIKE 'Chuyển từ phiên bản%'");

// Cleanup cookies
@array_map('unlink', glob("$cookieDir/*.*"));
@rmdir($cookieDir);

echo "\n=======================================================\n";
echo "TEST RESULTS: {$passed} / {$total} PASSED\n";
if ($passed === $total) {
    echo "\033[32m🎉 TẤT CẢ CÁC TÍNH NĂNG MỚI ĐỀU HOẠT ĐỘNG HOÀN HẢO!\033[0m\n";
} else {
    echo "\033[31m⚠️ CÒN TÍNH NĂNG CHƯA ĐẠT.\033[0m\n";
}
echo "=======================================================\n";

