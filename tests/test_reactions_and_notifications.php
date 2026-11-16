<?php
// tests/test_reactions_and_notifications.php
require_once __DIR__ . '/../api/db.php';

echo "=======================================================\n";
echo "TEST SUITE: EMOJI REACTIONS & NOTIFICATION UPDATES\n";
echo "=======================================================\n\n";

$baseUrl = 'http://localhost:8000';
$cookieDir = sys_get_temp_dir() . '/dm_test_rx_' . uniqid();
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
    echo "Test $num: $status $desc\n";
    if ($details) echo "   -> $details\n";
}

// 1. Check historical notifications for admin
$adminCookie = $cookieDir . '/admin.cookie';
$loginAdmin = req('api/auth.php?action=login', 'POST', ['username' => 'admin', 'password' => 'demo'], $adminCookie);
$notifsAdmin = req('api/notifications.php?action=list', 'GET', null, $adminCookie);
$adminNotifCount = count($notifsAdmin['data']['notifications'] ?? []);
report(1, 'Admin có danh sách thông báo lịch sử (Historical backfill)', $adminNotifCount > 0, "Total notifications for admin: {$adminNotifCount}");

// 2. Login as user-an (Reviewer/Manager)
$anCookie = $cookieDir . '/an.cookie';
$loginAn = req('api/auth.php?action=login', 'POST', ['username' => 'an', 'password' => 'demo'], $anCookie);

// Create an annotation by @an
$createAnn = req('api/annotations.php?action=create', 'POST', [
    'imageVersionId' => 'ver-signup-1',
    'type' => 'point',
    'x' => 150,
    'y' => 150,
    'w' => 0,
    'h' => 0,
    'body' => 'Góp ý kiểm thử cảm xúc và cập nhật thông báo cũ'
], $anCookie);
$annId = $createAnn['data']['id'] ?? null;
report(2, 'Tạo góp ý bởi @an', $createAnn['code'] === 201 && !empty($annId), "ID: {$annId}");

// 3. Update annotation body and verify old notification content is updated
$updateBody = req("api/annotations.php?id={$annId}&action=update_body", 'POST', [
    'body' => 'Góp ý kiểm thử cảm xúc và cập nhật thông báo cũ (Nội dung đã chỉnh sửa hoàn tất)'
], $anCookie);
$stmtCheckNotif = $pdo->prepare("SELECT content FROM notifications WHERE annotation_id = :aid AND type = 'new_annotation' LIMIT 1");
$stmtCheckNotif->execute([':aid' => $annId]);
$notifContent = $stmtCheckNotif->fetchColumn() ?: '';
$hasUpdatedNotice = str_contains($notifContent, 'Đã chỉnh sửa');
report(3, 'Cập nhật nội dung góp ý đồng thời cập nhật nội dung thông báo cũ', $updateBody['code'] === 200 && $hasUpdatedNotice, "Notification content: {$notifContent}");

// 4. Reaction on annotation by @binh
$binhCookie = $cookieDir . '/binh.cookie';
req('api/auth.php?action=login', 'POST', ['username' => 'binh', 'password' => 'demo'], $binhCookie);

$reactRes = req("api/annotations.php?id={$annId}&action=react", 'POST', [
    'emoji' => '👍'
], $binhCookie);

$reactions = $reactRes['data']['reactions'] ?? [];
$hasThumb = false;
foreach ($reactions as $rx) {
    if ($rx['emoji'] === '👍' && $rx['count'] === 1 && $rx['hasReacted'] === true) {
        $hasThumb = true;
        break;
    }
}
report(4, 'Thả cảm xúc 👍 vào góp ý bởi @binh', $reactRes['code'] === 200 && $hasThumb, "Action: " . ($reactRes['data']['reactionAction'] ?? ''));

// 5. Check reaction notification delivered to author (@an)
$notifsAn = req('api/notifications.php?action=list', 'GET', null, $anCookie);
$hasReactionNotif = false;
foreach ($notifsAn['data']['notifications'] ?? [] as $n) {
    if ($n['type'] === 'reaction' && str_contains($n['content'], '👍')) {
        $hasReactionNotif = true;
        break;
    }
}
report(5, 'Tác giả (@an) nhận được thông báo khi có người thả cảm xúc', $hasReactionNotif, "Reaction notification found in @an feed");

// 6. Toggle off reaction by @binh
$unreactRes = req("api/annotations.php?id={$annId}&action=react", 'POST', [
    'emoji' => '👍'
], $binhCookie);
$rxAfterUnreact = $unreactRes['data']['reactions'] ?? [];
$unreactOk = ($unreactRes['code'] === 200) && ($unreactRes['data']['reactionAction'] === 'removed') && empty($rxAfterUnreact);
report(6, 'Nhấn lại cảm xúc 👍 để bỏ thả cảm xúc (Toggle off)', $unreactOk, "Action: " . ($unreactRes['data']['reactionAction'] ?? ''));

// 7. Add reply by @binh and react to reply by @an
$replyRes = req("api/annotations.php?id={$annId}&action=reply", 'POST', [
    'body' => 'Phản hồi thử nghiệm thả cảm xúc vào reply'
], $binhCookie);
$replyId = end($replyRes['data']['replies'])['id'] ?? null;

$reactReplyRes = req("api/annotations.php?id={$annId}&action=react", 'POST', [
    'replyId' => $replyId,
    'emoji' => '❤️'
], $anCookie);

$repList = $reactReplyRes['data']['replies'] ?? [];
$repRxOk = false;
foreach ($repList as $rep) {
    if ($rep['id'] === $replyId && !empty($rep['reactions'])) {
        foreach ($rep['reactions'] as $r) {
            if ($r['emoji'] === '❤️' && $r['count'] === 1) {
                $repRxOk = true;
                break;
            }
        }
    }
}
report(7, 'Thả cảm xúc ❤️ vào phản hồi (Reply)', $reactReplyRes['code'] === 200 && $repRxOk, "Reply ID: {$replyId}");

// 8. Delete annotation and verify reactions & notifications cascade deletion
$delRes = req("api/annotations.php?id={$annId}&action=delete", 'POST', [], $anCookie);
$rxRemaining = (int)$pdo->query("SELECT count(*) FROM comment_reactions WHERE annotation_id = '{$annId}'")->fetchColumn();
$notifRemaining = (int)$pdo->query("SELECT count(*) FROM notifications WHERE annotation_id = '{$annId}'")->fetchColumn();
report(8, 'Xóa góp ý tự động dọn sạch cảm xúc và thông báo liên quan', $delRes['code'] === 200 && $rxRemaining === 0 && $notifRemaining === 0, "Remaining reactions: {$rxRemaining}, notifs: {$notifRemaining}");

// Cleanup cookies
@array_map('unlink', glob("$cookieDir/*.*"));
@rmdir($cookieDir);

echo "\n=======================================================\n";
echo "TEST RESULTS: {$passed} / {$total} PASSED\n";
if ($passed === $total) {
    echo "\033[32m🎉 TẤT CẢ CÁC KIỂM THỬ CẢM XÚC VÀ THÔNG BÁO ĐỀU THÀNH CÔNG!\033[0m\n";
} else {
    echo "\033[31m⚠️ CÓ KIỂM THỬ THẤT BẠI.\033[0m\n";
}
echo "=======================================================\n";
