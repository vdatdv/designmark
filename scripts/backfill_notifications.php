<?php
// scripts/backfill_notifications.php
require_once __DIR__ . '/../api/db.php';

echo "=== DESIGNMARK HISTORICAL NOTIFICATION BACKFILL ===" . PHP_EOL;

// 1. Backfill project_members notifications
$members = $pdo->query("
    SELECT pm.project_id, pm.user_id, pm.role, p.name as project_name, p.created_by as project_owner
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
")->fetchAll();

$memberNotifCount = 0;
foreach ($members as $m) {
    // Check if notif exists
    $stmtCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id = :uid AND project_id = :pid AND type = 'member_added'");
    $stmtCheck->execute([':uid' => $m['user_id'], ':pid' => $m['project_id']]);
    if (!$stmtCheck->fetch()) {
        $roleName = ($m['role'] === 'manager') ? 'Quản lý (Manager)' : (($m['role'] === 'reviewer') ? 'Góp ý (Reviewer)' : 'Chỉ xem (Viewer)');
        $content = "Bạn đã được thêm vào dự án {$m['project_name']} với vai trò {$roleName}.";
        create_notification(
            $pdo,
            $m['user_id'],
            'member_added',
            'Thành viên dự án',
            $content,
            $m['project_id'],
            null,
            null,
            null,
            "project.php?id={$m['project_id']}",
            $m['project_owner']
        );
        $memberNotifCount++;
    }
}
echo "Backfilled {$memberNotifCount} member_added notifications." . PHP_EOL;

// 2. Backfill annotations notifications
$annotations = $pdo->query("
    SELECT a.*, p.name as project_name, u.name as author_name
    FROM annotations a
    JOIN projects p ON a.project_id = p.id
    LEFT JOIN users u ON a.author_id = u.id
    ORDER BY a.created_at ASC
")->fetchAll();

$annNotifCount = 0;
foreach ($annotations as $a) {
    $authorName = $a['author_name'] ?: 'Thành viên';
    $snippet = mb_strlen($a['body'], 'UTF-8') > 80 ? (mb_substr($a['body'], 0, 77, 'UTF-8') . '...') : $a['body'];
    $content = "{$authorName} đã thêm góp ý: \"{$snippet}\". Dự án: {$a['project_name']}.";
    $link = "review.php?project={$a['project_id']}&screen={$a['screen_id']}&version={$a['image_version_id']}&annotation={$a['id']}";

    // Get project members
    $stmtM = $pdo->prepare("
        SELECT DISTINCT u_id FROM (
            SELECT created_by as u_id FROM projects WHERE id = :pid
            UNION
            SELECT user_id as u_id FROM project_members WHERE project_id = :pid
        ) t WHERE u_id IS NOT NULL AND u_id != ''
    ");
    $stmtM->execute([':pid' => $a['project_id']]);
    $uids = $stmtM->fetchAll(PDO::FETCH_COLUMN);

    foreach ($uids as $uid) {
        if ($uid === $a['author_id']) continue;
        $stmtCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id = :uid AND annotation_id = :aid AND type = 'new_annotation'");
        $stmtCheck->execute([':uid' => $uid, ':aid' => $a['id']]);
        if (!$stmtCheck->fetch()) {
            create_notification(
                $pdo,
                $uid,
                'new_annotation',
                'Góp ý mới',
                $content,
                $a['project_id'],
                $a['screen_id'],
                $a['image_version_id'],
                $a['id'],
                $link,
                $a['author_id']
            );
            $annNotifCount++;
        }
    }
}
echo "Backfilled {$annNotifCount} new_annotation notifications." . PHP_EOL;

// 3. Backfill replies notifications
$replies = $pdo->query("
    SELECT r.*, a.project_id, a.screen_id, a.image_version_id, a.author_id as annotation_author_id, p.name as project_name, u.name as reply_author_name
    FROM annotation_replies r
    JOIN annotations a ON r.annotation_id = a.id
    JOIN projects p ON a.project_id = p.id
    LEFT JOIN users u ON r.author_id = u.id
    ORDER BY r.created_at ASC
")->fetchAll();

$repNotifCount = 0;
foreach ($replies as $r) {
    $authorName = $r['reply_author_name'] ?: 'Thành viên';
    $snippet = mb_strlen($r['body'], 'UTF-8') > 80 ? (mb_substr($r['body'], 0, 77, 'UTF-8') . '...') : $r['body'];
    $content = "{$authorName} đã trả lời trong cuộc thảo luận: \"{$snippet}\". Dự án: {$r['project_name']}.";
    $link = "review.php?project={$r['project_id']}&screen={$r['screen_id']}&version={$r['image_version_id']}&annotation={$r['annotation_id']}";

    // Target users: Annotation author + Project owner
    $stmtM = $pdo->prepare("
        SELECT DISTINCT u_id FROM (
            SELECT created_by as u_id FROM projects WHERE id = :pid
            UNION
            SELECT :aid_author as u_id
        ) t WHERE u_id IS NOT NULL AND u_id != ''
    ");
    $stmtM->execute([':pid' => $r['project_id'], ':aid_author' => $r['annotation_author_id']]);
    $uids = $stmtM->fetchAll(PDO::FETCH_COLUMN);

    foreach ($uids as $uid) {
        if ($uid === $r['author_id']) continue;
        $stmtCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id = :uid AND annotation_id = :aid AND type = 'new_reply' AND actor_id = :actor");
        $stmtCheck->execute([':uid' => $uid, ':aid' => $r['annotation_id'], ':actor' => $r['author_id']]);
        if (!$stmtCheck->fetch()) {
            create_notification(
                $pdo,
                $uid,
                'new_reply',
                'Phản hồi mới',
                $content,
                $r['project_id'],
                $r['screen_id'],
                $r['image_version_id'],
                $r['annotation_id'],
                $link,
                $r['author_id']
            );
            $repNotifCount++;
        }
    }
}
echo "Backfilled {$repNotifCount} new_reply notifications." . PHP_EOL;

echo "=== BACKFILL COMPLETE ===" . PHP_EOL;

