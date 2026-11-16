<?php
// api/notifications.php - Notifications API for DesignMark
require_once __DIR__ . '/db.php';

$session = require_auth($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET') {
    $unreadOnly = (isset($_GET['unread_only']) && ($_GET['unread_only'] === '1' || $_GET['unread_only'] === 'true'))
        || (isset($_GET['tab']) && $_GET['tab'] === 'unread');

    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    // Purge any orphan notifications whose annotation or project was deleted
    try {
        $pdo->exec("DELETE FROM notifications WHERE annotation_id IS NOT NULL AND annotation_id != '' AND annotation_id NOT IN (SELECT id FROM annotations)");
        mirror_to_sqlite("DELETE FROM notifications WHERE annotation_id IS NOT NULL AND annotation_id != '' AND annotation_id NOT IN (SELECT id FROM annotations)");
    } catch (Throwable $e) {}

    $where = "WHERE n.user_id = :uid";
    $params = [':uid' => $session['userId']];

    if ($unreadOnly) {
        $where .= " AND (n.is_read = 0 OR n.is_read IS NULL)";
    }

    // Total count for current filtered tab
    $stmtCountFiltered = $pdo->prepare("SELECT COUNT(*) FROM notifications n $where");
    $stmtCountFiltered->execute($params);
    $totalFiltered = (int)$stmtCountFiltered->fetchColumn();

    // Fetch paginated notifications
    $stmt = $pdo->prepare("
        SELECT n.*, COALESCE(p.name, '') as project_name 
        FROM notifications n 
        LEFT JOIN projects p ON n.project_id = p.id 
        $where 
        ORDER BY n.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Count unread for badge
    $stmtUnread = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE user_id = :uid AND (is_read = 0 OR is_read IS NULL)
    ");
    $stmtUnread->execute([':uid' => $session['userId']]);
    $unreadCount = (int)$stmtUnread->fetchColumn();

    // Count all notifications for tab count
    $stmtAll = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid");
    $stmtAll->execute([':uid' => $session['userId']]);
    $allCount = (int)$stmtAll->fetchColumn();

    $result = [];
    foreach ($rows as $n) {
        $result[] = [
            'id' => $n['id'],
            'userId' => $n['user_id'],
            'actorId' => $n['actor_id'],
            'actor' => $n['actor_id'] ? public_user($pdo, $n['actor_id']) : null,
            'type' => $n['type'],
            'title' => $n['title'],
            'content' => $n['content'],
            'projectId' => $n['project_id'],
            'projectName' => $n['project_name'] ?: 'Dự án',
            'screenId' => $n['screen_id'],
            'imageVersionId' => $n['image_version_id'],
            'annotationId' => $n['annotation_id'],
            'link' => $n['link'],
            'isRead' => (int)($n['is_read'] ?? 0) === 1,
            'createdAt' => $n['created_at']
        ];
    }

    $hasMore = ($offset + count($rows)) < $totalFiltered;

    json_response([
        'notifications' => $result,
        'unreadCount' => $unreadCount,
        'allCount' => $allCount,
        'totalFiltered' => $totalFiltered,
        'hasMore' => $hasMore,
        'offset' => $offset,
        'limit' => $limit
    ]);
}

if ($method === 'POST') {
    $input = get_json_input();
    if (!$action) {
        $action = $input['action'] ?? '';
    }

    if ($action === 'mark_read') {
        $id = trim($input['id'] ?? $_POST['id'] ?? '');
        if (!$id) {
            json_error('Thiếu mã thông báo.', 'VALIDATION', 422);
        }

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $session['userId']]);
        mirror_to_sqlite("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid", [':id' => $id, ':uid' => $session['userId']]);

        // Get updated unread count
        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND (is_read = 0 OR is_read IS NULL)");
        $stmtUnread->execute([':uid' => $session['userId']]);
        $unreadCount = (int)$stmtUnread->fetchColumn();

        json_response([
            'success' => true,
            'id' => $id,
            'unreadCount' => $unreadCount
        ]);
    }

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND (is_read = 0 OR is_read IS NULL)");
        $stmt->execute([':uid' => $session['userId']]);
        mirror_to_sqlite("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND (is_read = 0 OR is_read IS NULL)", [':uid' => $session['userId']]);

        json_response([
            'success' => true,
            'unreadCount' => 0
        ]);
    }

    if ($action === 'delete') {
        $id = trim($input['id'] ?? $_POST['id'] ?? '');
        if (!$id) {
            json_error('Thiếu mã thông báo.', 'VALIDATION', 422);
        }

        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $session['userId']]);
        mirror_to_sqlite("DELETE FROM notifications WHERE id = :id AND user_id = :uid", [':id' => $id, ':uid' => $session['userId']]);

        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND (is_read = 0 OR is_read IS NULL)");
        $stmtUnread->execute([':uid' => $session['userId']]);
        $unreadCount = (int)$stmtUnread->fetchColumn();

        json_response([
            'success' => true,
            'unreadCount' => $unreadCount
        ]);
    }

    json_error('Thao tác không hợp lệ.', 'BAD_REQUEST', 400);
}
