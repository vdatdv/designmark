<?php
// api/annotations.php - Annotations API

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$annotationId = $_GET['id'] ?? $_POST['id'] ?? null;
$versionId = $_GET['version_id'] ?? $_POST['version_id'] ?? $_GET['imageVersionId'] ?? null;

// GET
if ($method === 'GET') {
    if ($versionId) {
        $stmtVer = $pdo->prepare("SELECT * FROM image_versions WHERE id = :vid");
        $stmtVer->execute([':vid' => $versionId]);
        $version = $stmtVer->fetch();
        if (!$version) {
            json_error('Không tìm thấy phiên bản thiết kế.', 'NOT_FOUND', 404);
        }
        require_member($pdo, $version['project_id']);

        $isPaginated = isset($_GET['page']) || isset($_GET['paginated']) || isset($_GET['limit']) || isset($_GET['sort']) || isset($_GET['q']) || isset($_GET['status']) || isset($_GET['author']);

        if (!$isPaginated) {
            $stmt = $pdo->prepare("SELECT * FROM annotations WHERE image_version_id = :vid ORDER BY created_at ASC, id ASC");
            $stmt->execute([':vid' => $versionId]);
            $anns = $stmt->fetchAll();
            $result = [];
            foreach ($anns as $a) {
                $result[] = present_annotation($pdo, $a);
            }
            json_response($result);
        }

        // Server-side paginated & filtered list
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        $status = $_GET['status'] ?? 'all';
        $author = $_GET['author'] ?? 'all';
        $q = trim($_GET['q'] ?? '');
        $sort = $_GET['sort'] ?? 'newest';
        $targetAnnId = $_GET['target_ann_id'] ?? null;

        $whereClauses = ["a.image_version_id = :vid"];
        $params = [':vid' => $versionId];

        if ($status === 'open' || $status === 'resolved') {
            $whereClauses[] = "a.status = :status";
            $params[':status'] = $status;
        }

        if ($author && $author !== 'all') {
            $whereClauses[] = "a.author_id = :author";
            $params[':author'] = $author;
        }

        if ($q !== '') {
            $qCleanNum = ltrim($q, '#');
            if (is_numeric($qCleanNum)) {
                $whereClauses[] = "(LOWER(a.body) LIKE :qText OR LOWER(u.name) LIKE :qText OR LOWER(u.username) LIKE :qText OR a.number = :qNum)";
                $params[':qNum'] = (int)$qCleanNum;
            } else {
                $whereClauses[] = "(LOWER(a.body) LIKE :qText OR LOWER(u.name) LIKE :qText OR LOWER(u.username) LIKE :qText)";
            }
            $params[':qText'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Sorting
        $orderSql = "a.created_at DESC, a.id DESC";
        if ($sort === 'oldest') {
            $orderSql = "a.created_at ASC, a.id ASC";
        } elseif ($sort === 'most_replies') {
            $orderSql = "reply_count DESC, a.created_at DESC";
        }

        // Total matching count
        $countSql = "SELECT COUNT(*) FROM annotations a LEFT JOIN users u ON a.author_id = u.id WHERE {$whereSql}";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // Canvas Sync: Locate target annotation page if requested
        $targetPage = null;
        if ($targetAnnId) {
            $idSql = "
                SELECT a.id, (SELECT COUNT(*) FROM annotation_replies WHERE annotation_id = a.id) as reply_count 
                FROM annotations a 
                LEFT JOIN users u ON a.author_id = u.id 
                WHERE {$whereSql} 
                ORDER BY {$orderSql}
            ";
            $stmtIds = $pdo->prepare($idSql);
            $stmtIds->execute($params);
            $allIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
            $targetIdx = array_search($targetAnnId, $allIds);
            if ($targetIdx !== false) {
                $targetPage = (int)floor($targetIdx / $limit) + 1;
                if (!isset($_GET['page']) || isset($_GET['auto_jump'])) {
                    $page = $targetPage;
                }
            }
        }

        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages && $total > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        // Fetch page items with replyLimit = 2 for high performance
        $dataSql = "
            SELECT a.*, (SELECT COUNT(*) FROM annotation_replies WHERE annotation_id = a.id) as reply_count 
            FROM annotations a 
            LEFT JOIN users u ON a.author_id = u.id 
            WHERE {$whereSql} 
            ORDER BY {$orderSql} 
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmtData = $pdo->prepare($dataSql);
        $stmtData->execute($params);
        $rows = $stmtData->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = present_annotation($pdo, $row, null, 2);
        }

        // Lightweight markers for the entire canvas
        $markersSql = "
            SELECT a.id, a.number, a.type, a.x, a.y, a.w, a.h, a.original_width, a.original_height, a.drawing_data, a.status, a.author_id, a.body
            FROM annotations a
            WHERE a.image_version_id = :mvid
            ORDER BY a.created_at ASC, a.id ASC
        ";
        $stmtMarkers = $pdo->prepare($markersSql);
        $stmtMarkers->execute([':mvid' => $versionId]);
        $rawMarkers = $stmtMarkers->fetchAll();
        $markers = [];
        foreach ($rawMarkers as $m) {
            $markers[] = [
                'id' => $m['id'],
                'number' => (int)$m['number'],
                'type' => $m['type'],
                'x' => (float)$m['x'],
                'y' => (float)$m['y'],
                'w' => (float)$m['w'],
                'h' => (float)$m['h'],
                'originalWidth' => (int)($version ? $version['width'] : $m['original_width']),
                'originalHeight' => (int)($version ? $version['height'] : $m['original_height']),
                'drawingData' => $m['drawing_data'] ? (is_string($m['drawing_data']) ? json_decode($m['drawing_data'], true) : $m['drawing_data']) : null,
                'status' => $m['status'],
                'authorId' => $m['author_id'],
                'body' => $m['body']
            ];
        }

        $start = $total > 0 ? ($offset + 1) : 0;
        $end = min($total, $offset + count($items));

        json_response([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
                'start' => $start,
                'end' => $end
            ],
            'markers' => $markers,
            'targetPage' => $targetPage
        ]);
    }

    if ($annotationId) {
        $stmt = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmt->execute([':id' => $annotationId]);
        $ann = $stmt->fetch();
        if (!$ann) {
            json_error('Không tìm thấy góp ý.', 'NOT_FOUND', 404);
        }
        require_member($pdo, $ann['project_id']);

        if ($action === 'replies') {
            $allReplies = present_replies($pdo, $ann['id']);
            json_response([
                'annotationId' => $ann['id'],
                'replies' => $allReplies,
                'total' => count($allReplies)
            ]);
        }

        if ($action === 'history') {
            $presented = present_annotation($pdo, $ann);
            json_response($presented['statusHistory']);
        }

        json_response(present_annotation($pdo, $ann));
    }

    json_error('Thiếu mã phiên bản hoặc mã góp ý.', 'VALIDATION', 422);
}

// POST
if ($method === 'POST') {
    $input = get_json_input();

    // Upload attachment for comments / replies
    if ($action === 'upload_attachment') {
        $session = require_auth($pdo);
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            json_error('Vui lòng chọn tệp ảnh hợp lệ.', 'VALIDATION', 422);
        }
        $file = $_FILES['file'];
        if ($file['size'] > 10 * 1024 * 1024) {
            json_error('Ảnh vượt quá dung lượng tối đa 10 MB.', 'VALIDATION', 422);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (str_ends_with(strtolower($file['name']), '.svg')) {
            $mime = 'image/svg+xml';
        }
        $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes, true)) {
            json_error('Chỉ chấp nhận định dạng ảnh PNG, JPEG, SVG, WebP hoặc GIF.', 'VALIDATION', 422);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $newFileName = 'att_' . generate_uuid() . '.' . strtolower($ext);
        $destPath = __DIR__ . '/../uploads/' . $newFileName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            json_error('Không thể lưu ảnh đính kèm lên máy chủ.', 'SAVE_FAILED', 500);
        }
        json_response([
            'url' => 'uploads/' . $newFileName,
            'fileName' => $newFileName,
            'originalName' => $file['name']
        ], 201);
    }

    // Migrate unresolved annotations from previous version
    if ($action === 'migrate_unresolved') {
        $targetVid = $input['targetVersionId'] ?? $input['target_version_id'] ?? null;
        $sourceVid = $input['sourceVersionId'] ?? $input['source_version_id'] ?? null;
        if (!$targetVid) {
            json_error('Thiếu mã phiên bản đích.', 'VALIDATION', 422);
        }

        $tStmt = $pdo->prepare("SELECT * FROM image_versions WHERE id = :id");
        $tStmt->execute([':id' => $targetVid]);
        $targetVer = $tStmt->fetch();
        if (!$targetVer) {
            json_error('Không tìm thấy phiên bản đích.', 'NOT_FOUND', 404);
        }

        $memberInfo = require_member($pdo, $targetVer['project_id'], ['reviewer', 'manager']);
        $session = $memberInfo['session'];

        if (!$sourceVid) {
            // Find previous version of the same screen
            $sStmt = $pdo->prepare("SELECT * FROM image_versions WHERE screen_id = :sid AND number < :num ORDER BY number DESC LIMIT 1");
            $sStmt->execute([':sid' => $targetVer['screen_id'], ':num' => $targetVer['number']]);
            $sourceVer = $sStmt->fetch();
        } else {
            $sStmt = $pdo->prepare("SELECT * FROM image_versions WHERE id = :id");
            $sStmt->execute([':id' => $sourceVid]);
            $sourceVer = $sStmt->fetch();
        }

        if (!$sourceVer) {
            json_error('Không tìm thấy phiên bản cũ để sao chép góp ý.', 'NOT_FOUND', 404);
        }

        // Query unresolved annotations from source version
        $annStmt = $pdo->prepare("SELECT * FROM annotations WHERE image_version_id = :vid AND status = 'open' ORDER BY COALESCE(number, 0) ASC, created_at ASC");
        $annStmt->execute([':vid' => $sourceVer['id']]);
        $unresolved = $annStmt->fetchAll();

        if (empty($unresolved)) {
            json_response([
                'count' => 0,
                'message' => "Không có góp ý nào chưa xử lý ở phiên bản v{$sourceVer['number']}.",
                'annotations' => []
            ]);
        }

        // Get max number on target version
        $maxNumStmt = $pdo->prepare("SELECT COALESCE(MAX(number), 0) FROM annotations WHERE image_version_id = :vid");
        $maxNumStmt->execute([':vid' => $targetVer['id']]);
        $curNumber = (int)$maxNumStmt->fetchColumn();

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $migrated = [];

        // Scale factor if dimensions changed
        $scaleX = $targetVer['width'] / max(1, $sourceVer['width']);
        $scaleY = $targetVer['height'] / max(1, $sourceVer['height']);

        $stmtIns = $pdo->prepare("INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, drawing_data, created_at, updated_at) VALUES (:id, :pid, :sid, :vid, :type, :x, :y, :w, :h, :ow, :oh, :aid, :body, 'open', 1, :cn, :num, :dd, :cat, :uat)");
        $stmtSe = $pdo->prepare("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, 'open', :uid, :note, :cat)");

        $inTx = false;
        try {
            $pdo->beginTransaction();
            $inTx = true;
        } catch (Throwable $e) {}

        foreach ($unresolved as $orig) {
            $curNumber++;
            $newId = generate_uuid('ann');
            $newX = round((float)$orig['x'] * $scaleX, 2);
            $newY = round((float)$orig['y'] * $scaleY, 2);
            $newW = round((float)$orig['w'] * $scaleX, 2);
            $newH = round((float)$orig['h'] * $scaleY, 2);
            $checkNote = "Chuyển từ phiên bản v{$sourceVer['number']} (Góp ý ban đầu #" . ($orig['number'] ?: $orig['id']) . ")";

            $params = [
                ':id' => $newId,
                ':pid' => $targetVer['project_id'],
                ':sid' => $targetVer['screen_id'],
                ':vid' => $targetVer['id'],
                ':type' => $orig['type'],
                ':x' => $newX,
                ':y' => $newY,
                ':w' => $newW,
                ':h' => $newH,
                ':ow' => $targetVer['width'],
                ':oh' => $targetVer['height'],
                ':aid' => $orig['author_id'],
                ':body' => $orig['body'],
                ':cn' => $checkNote,
                ':num' => $curNumber,
                ':dd' => $orig['drawing_data'] ?? null,
                ':cat' => $now,
                ':uat' => $now
            ];
            $stmtIns->execute($params);
            mirror_to_sqlite("INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, drawing_data, created_at, updated_at) VALUES (:id, :pid, :sid, :vid, :type, :x, :y, :w, :h, :ow, :oh, :aid, :body, 'open', 1, :cn, :num, :dd, :cat, :uat)", $params);

            // Status event
            $seParams = [
                ':id' => generate_uuid('sh'),
                ':aid' => $newId,
                ':uid' => $session['userId'],
                ':note' => "Chuyển tiếp từ phiên bản v{$sourceVer['number']}",
                ':cat' => $now
            ];
            $stmtSe->execute($seParams);
            mirror_to_sqlite("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, 'open', :uid, :note, :cat)", $seParams);

            $migrated[] = $newId;
        }

        if ($inTx && $pdo->inTransaction()) {
            $pdo->commit();
        }

        touch_project($pdo, $targetVer['project_id'], $now);
        push_activity($pdo, $targetVer['project_id'], $session['userId'], 'annotations.migrated', "đã chuyển " . count($migrated) . " góp ý chưa xử lý từ v{$sourceVer['number']} sang v{$targetVer['number']}");

        json_response([
            'count' => count($migrated),
            'message' => "Đã chuyển thành công " . count($migrated) . " góp ý chưa xử lý từ v{$sourceVer['number']} sang v{$targetVer['number']}.",
            'migratedIds' => $migrated
        ]);
    }

    // Create annotation
    if ($action === 'create') {
        $vid = $versionId ?: ($input['imageVersionId'] ?? $input['version_id'] ?? '');
        if (!$vid) {
            json_error('Thiếu mã phiên bản ảnh.', 'VALIDATION', 422);
        }
        $stmtVer = $pdo->prepare("SELECT * FROM image_versions WHERE id = :vid");
        $stmtVer->execute([':vid' => $vid]);
        $version = $stmtVer->fetch();
        if (!$version) {
            json_error('Không tìm thấy phiên bản ảnh.', 'NOT_FOUND', 404);
        }

        $memberInfo = require_member($pdo, $version['project_id'], ['reviewer', 'manager']);
        $session = $memberInfo['session'];

        $body = trim($input['body'] ?? '');
        if (!$body) {
            json_error('Nhận xét không được để trống.', 'VALIDATION', 422);
        }
        if (mb_strlen($body, 'UTF-8') > 2000) {
            json_error('Nhận xét tối đa 2000 ký tự.', 'VALIDATION', 422);
        }

        $rawType = $input['type'] ?? '';
        if ($rawType === 'area') $rawType = 'rect';
        $allowedTypes = ['point', 'rect', 'arrow', 'freehand'];
        if (!in_array($rawType, $allowedTypes, true)) {
            json_error('Loại đánh dấu không hợp lệ.', 'VALIDATION', 422);
        }
        $type = $rawType;

        $x = (float)($input['x'] ?? 0);
        $y = (float)($input['y'] ?? 0);
        $w = in_array($type, ['rect', 'arrow', 'freehand']) ? (float)($input['w'] ?? 0) : 0;
        $h = in_array($type, ['rect', 'arrow', 'freehand']) ? (float)($input['h'] ?? 0) : 0;

        $drawingData = null;
        if (!empty($input['drawingData'])) {
            $drawingData = is_string($input['drawingData']) ? $input['drawingData'] : json_encode($input['drawingData']);
        }

        if ($x < 0 || $y < 0 || $x > $version['width'] || $y > $version['height']) {
            json_error('Tọa độ nằm ngoài ảnh gốc.', 'VALIDATION', 422);
        }
        if ($type === 'rect' && ($w <= 0 || $h <= 0 || $x + $w > $version['width'] + 0.01 || $y + $h > $version['height'] + 0.01)) {
            json_error('Vùng khoanh không hợp lệ trên ảnh gốc.', 'VALIDATION', 422);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $annId = generate_uuid('ann');

        // Next sequential number for this image version
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(number), 0) FROM annotations WHERE image_version_id = :vid");
        $stmtMax->execute([':vid' => $version['id']]);
        $nextNumber = ((int)$stmtMax->fetchColumn()) + 1;

        $stmtInsert = $pdo->prepare("INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, drawing_data, created_at, updated_at) VALUES (:id, :pid, :sid, :vid, :type, :x, :y, :w, :h, :ow, :oh, :aid, :body, 'open', 1, '', :num, :dd, :cat, :uat)");
        $insertParams = [
            ':id' => $annId,
            ':pid' => $version['project_id'],
            ':sid' => $version['screen_id'],
            ':vid' => $version['id'],
            ':type' => $type,
            ':x' => $x,
            ':y' => $y,
            ':w' => max(0, $w),
            ':h' => max(0, $h),
            ':ow' => $version['width'],
            ':oh' => $version['height'],
            ':aid' => $session['userId'],
            ':body' => $body,
            ':num' => $nextNumber,
            ':dd' => $drawingData,
            ':cat' => $now,
            ':uat' => $now
        ];
        $stmtInsert->execute($insertParams);
        mirror_to_sqlite("INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, drawing_data, created_at, updated_at) VALUES (:id, :pid, :sid, :vid, :type, :x, :y, :w, :h, :ow, :oh, :aid, :body, 'open', 1, '', :num, :dd, :cat, :uat)", $insertParams);

        $stmtSh = $pdo->prepare("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, 'open', :uid, 'Tạo góp ý', :cat)");
        $shParams = [
            ':id' => generate_uuid('sh'),
            ':aid' => $annId,
            ':uid' => $session['userId'],
            ':cat' => $now
        ];
        $stmtSh->execute($shParams);
        mirror_to_sqlite("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, 'open', :uid, 'Tạo góp ý', :cat)", $shParams);

        $stmtScr = $pdo->prepare("SELECT name FROM design_screens WHERE id = :sid");
        $stmtScr->execute([':sid' => $version['screen_id']]);
        $screenName = $stmtScr->fetchColumn() ?: 'màn hình';

        touch_project($pdo, $version['project_id'], $now);
        $annReviewLink = "review.php?project={$version['project_id']}&screen={$version['screen_id']}&version={$version['id']}&annotation={$annId}";
        $annSnippet = mb_strlen($body, 'UTF-8') > 80 ? (mb_substr($body, 0, 77, 'UTF-8') . '...') : $body;
        push_activity($pdo, $version['project_id'], $session['userId'], 'comment.created', "đã thêm nhận xét trên {$screenName} v{$version['number']}: \"{$annSnippet}\"", $annReviewLink, $annId);

        $stmtProj = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $stmtProj->execute([':pid' => $version['project_id']]);
        $projName = $stmtProj->fetchColumn() ?: 'Dự án';

        notify_project_members(
            $pdo,
            $version['project_id'],
            'new_annotation',
            'Góp ý mới',
            "{$session['name']} đã góp ý: \"{$annSnippet}\" trên {$screenName} (v{$version['number']}). Dự án: {$projName}.",
            $version['screen_id'],
            $version['id'],
            $annId,
            $annReviewLink,
            $session['userId']
        );

        // Check for @mentions in annotation body
        preg_match_all('/@([a-zA-Z0-9_\-\.]+)/u', $body, $mentionMatches);
        if (!empty($mentionMatches[1])) {
            $uniqueUnames = array_unique($mentionMatches[1]);
            foreach ($uniqueUnames as $uname) {
                $uStmt = $pdo->prepare("SELECT id, name FROM users WHERE LOWER(username) = LOWER(:u)");
                $uStmt->execute([':u' => $uname]);
                $targetUser = $uStmt->fetch();
                if ($targetUser && $targetUser['id'] !== $session['userId']) {
                    create_notification(
                        $pdo,
                        $targetUser['id'],
                        'mention',
                        'Nhắc tên trong góp ý',
                        "{$session['name']} đã nhắc tên bạn trong một góp ý tại {$screenName}: \"{$annSnippet}\"",
                        $version['project_id'],
                        $version['screen_id'],
                        $version['id'],
                        $annId,
                        $annReviewLink,
                        $session['userId']
                    );
                }
            }
        }

        $stmtFetch = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtFetch->execute([':id' => $annId]);
        json_response(present_annotation($pdo, $stmtFetch->fetch()), 201);
    }

    if (!$annotationId) {
        $annotationId = $input['id'] ?? null;
    }
    if (!$annotationId) {
        json_error('Thiếu mã góp ý.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
    $stmt->execute([':id' => $annotationId]);
    $ann = $stmt->fetch();
    if (!$ann) {
        json_error('Không tìm thấy góp ý.', 'NOT_FOUND', 404);
    }

    if ($action === 'update_body') {
        $memberInfo = require_member($pdo, $ann['project_id'], ['reviewer', 'manager']);
        $session = $memberInfo['session'];

        if ($ann['author_id'] !== $session['userId']) {
            json_error('Bạn chỉ sửa được nhận xét của chính mình.', 'FORBIDDEN', 403);
        }

        $body = trim($input['body'] ?? '');
        if (!$body) {
            json_error('Nhận xét không được để trống.', 'VALIDATION', 422);
        }
        if (mb_strlen($body, 'UTF-8') > 2000) {
            json_error('Nhận xét tối đa 2000 ký tự.', 'VALIDATION', 422);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $reqRevision = isset($input['revision']) ? (int)$input['revision'] : null;
        $simulateConflict = !empty($input['simulateConflict']);

        if ($simulateConflict) {
            $conflictBody = "Nội dung mới nhất được ghi bởi một phiên khác lúc " . (new DateTime())->format('H:i:s') . ".";
            $newRev = ((int)$ann['revision']) + 1;
            $stmtUp = $pdo->prepare("UPDATE annotations SET body = :b, revision = :rev, updated_at = :uat WHERE id = :id");
            $stmtUp->execute([':b' => $conflictBody, ':rev' => $newRev, ':uat' => $now, ':id' => $ann['id']]);

            push_activity($pdo, $ann['project_id'], $session['userId'], 'comment.updated', 'đã mô phỏng xung đột khi sửa nhận xét');
            json_error('Nội dung đã được thay đổi ở một phiên khác.', 'CONFLICT', 409, [
                'latest' => ['body' => $conflictBody, 'revision' => $newRev],
                'mine' => $body
            ]);
        }

        if ($reqRevision !== null && $reqRevision !== (int)$ann['revision']) {
            json_error('Nội dung đã được thay đổi ở một phiên khác.', 'CONFLICT', 409, [
                'latest' => ['body' => $ann['body'], 'revision' => (int)$ann['revision']],
                'mine' => $body
            ]);
        }

        $newRev = ((int)$ann['revision']) + 1;
        $stmtUp = $pdo->prepare("UPDATE annotations SET body = :b, revision = :rev, updated_at = :uat WHERE id = :id");
        $stmtUp->execute([':b' => $body, ':rev' => $newRev, ':uat' => $now, ':id' => $ann['id']]);
        mirror_to_sqlite("UPDATE annotations SET body = :b, revision = :rev, updated_at = :uat WHERE id = :id", [':b' => $body, ':rev' => $newRev, ':uat' => $now, ':id' => $ann['id']]);

        // Cập nhật nội dung thông báo cũ liên quan đến góp ý này
        $stmtProj = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $stmtProj->execute([':pid' => $ann['project_id']]);
        $projName = $stmtProj->fetchColumn() ?: 'Dự án';
        $snippet = mb_strlen($body, 'UTF-8') > 80 ? (mb_substr($body, 0, 77, 'UTF-8') . '...') : $body;
        $updatedNotifContent = "{$session['name']} đã thêm góp ý: \"{$snippet}\". Dự án: {$projName}. (Đã chỉnh sửa)";
        $stmtNotifUp = $pdo->prepare("UPDATE notifications SET content = :c WHERE annotation_id = :aid AND type = 'new_annotation'");
        $stmtNotifUp->execute([':c' => $updatedNotifContent, ':aid' => $ann['id']]);
        mirror_to_sqlite("UPDATE notifications SET content = :c WHERE annotation_id = :aid AND type = 'new_annotation'", [':c' => $updatedNotifContent, ':aid' => $ann['id']]);

        push_activity($pdo, $ann['project_id'], $session['userId'], 'comment.updated', 'đã sửa một nhận xét');

        $stmtFetch = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtFetch->execute([':id' => $ann['id']]);
        json_response(present_annotation($pdo, $stmtFetch->fetch(), $session['userId']));
    }

    if ($action === 'react') {
        $memberInfo = require_member($pdo, $ann['project_id']);
        $session = $memberInfo['session'];

        $emoji = trim($input['emoji'] ?? '');
        if (!$emoji) {
            json_error('Vui lòng chọn biểu tượng cảm xúc.', 'VALIDATION', 422);
        }
        if (mb_strlen($emoji, 'UTF-8') > 16) {
            json_error('Biểu tượng cảm xúc không hợp lệ.', 'VALIDATION', 422);
        }

        $replyId = !empty($input['replyId']) ? trim($input['replyId']) : null;
        $replyRow = null;

        if ($replyId) {
            $stmtRep = $pdo->prepare("SELECT * FROM annotation_replies WHERE id = :rid AND annotation_id = :aid");
            $stmtRep->execute([':rid' => $replyId, ':aid' => $ann['id']]);
            $replyRow = $stmtRep->fetch();
            if (!$replyRow) {
                json_error('Không tìm thấy phản hồi này.', 'NOT_FOUND', 404);
            }
        }

        // Kiểm tra xem đã thả cảm xúc này chưa
        if ($replyId) {
            $stmtFind = $pdo->prepare("SELECT id FROM comment_reactions WHERE annotation_id = :aid AND reply_id = :rid AND user_id = :uid AND emoji = :e");
            $stmtFind->execute([':aid' => $ann['id'], ':rid' => $replyId, ':uid' => $session['userId'], ':e' => $emoji]);
        } else {
            $stmtFind = $pdo->prepare("SELECT id FROM comment_reactions WHERE annotation_id = :aid AND (reply_id IS NULL OR reply_id = '') AND user_id = :uid AND emoji = :e");
            $stmtFind->execute([':aid' => $ann['id'], ':uid' => $session['userId'], ':e' => $emoji]);
        }
        $existingReaction = $stmtFind->fetch();

        if ($existingReaction) {
            // Đã thả -> Bỏ cảm xúc (toggle off)
            $delStmt = $pdo->prepare("DELETE FROM comment_reactions WHERE id = :id");
            $delStmt->execute([':id' => $existingReaction['id']]);
            mirror_to_sqlite("DELETE FROM comment_reactions WHERE id = :id", [':id' => $existingReaction['id']]);
            $reactionAction = 'removed';
        } else {
            // Chưa thả -> Thêm cảm xúc (toggle on)
            $rxId = generate_uuid('rx');
            $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
            $insStmt = $pdo->prepare("INSERT INTO comment_reactions (id, annotation_id, reply_id, user_id, emoji, created_at) VALUES (:id, :aid, :rid, :uid, :e, :cat)");
            $insParams = [
                ':id' => $rxId,
                ':aid' => $ann['id'],
                ':rid' => $replyId ?: null,
                ':uid' => $session['userId'],
                ':e' => $emoji,
                ':cat' => $now
            ];
            $insStmt->execute($insParams);
            mirror_to_sqlite("INSERT INTO comment_reactions (id, annotation_id, reply_id, user_id, emoji, created_at) VALUES (:id, :aid, :rid, :uid, :e, :cat)", $insParams);
            $reactionAction = 'added';

            // Gửi thông báo đến người viết nhận xét / phản hồi nếu không phải chính mình
            $targetAuthorId = $replyRow ? $replyRow['author_id'] : $ann['author_id'];
            if ($targetAuthorId && $targetAuthorId !== $session['userId']) {
                $reviewLink = "review.php?project={$ann['project_id']}&screen={$ann['screen_id']}&version={$ann['image_version_id']}&annotation={$ann['id']}";
                $targetDesc = $replyRow ? "phản hồi của bạn" : "nhận xét của bạn";
                create_notification(
                    $pdo,
                    $targetAuthorId,
                    'reaction',
                    'Cảm xúc mới',
                    "{$session['name']} đã thả cảm xúc {$emoji} vào {$targetDesc}.",
                    $ann['project_id'],
                    $ann['screen_id'],
                    $ann['image_version_id'],
                    $ann['id'],
                    $reviewLink,
                    $session['userId']
                );
            }
        }

        $stmtFetch = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtFetch->execute([':id' => $ann['id']]);
        $updatedAnn = present_annotation($pdo, $stmtFetch->fetch(), $session['userId']);
        $updatedAnn['reactionAction'] = $reactionAction;
        json_response($updatedAnn);
    }

    if ($action === 'reply') {
        $memberInfo = require_member($pdo, $ann['project_id'], ['reviewer', 'manager']);
        $session = $memberInfo['session'];

        $body = trim($input['body'] ?? '');
        if (!$body) {
            json_error('Trả lời không được để trống.', 'VALIDATION', 422);
        }
        if (mb_strlen($body, 'UTF-8') > 2000) {
            json_error('Trả lời tối đa 2000 ký tự.', 'VALIDATION', 422);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $attachmentUrl = !empty($input['attachmentUrl']) ? trim($input['attachmentUrl']) : null;
        $repId = generate_uuid('rep');

        $stmtRep = $pdo->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, attachment_url, created_at) VALUES (:id, :aid, :author, :body, :att, :cat)");
        $repParams = [
            ':id' => $repId,
            ':aid' => $ann['id'],
            ':author' => $session['userId'],
            ':body' => $body,
            ':att' => $attachmentUrl,
            ':cat' => $now
        ];
        $stmtRep->execute($repParams);
        mirror_to_sqlite("INSERT INTO annotation_replies (id, annotation_id, author_id, body, attachment_url, created_at) VALUES (:id, :aid, :author, :body, :att, :cat)", $repParams);

        $stmtUp = $pdo->prepare("UPDATE annotations SET updated_at = :uat WHERE id = :id");
        $stmtUp->execute([':uat' => $now, ':id' => $ann['id']]);
        mirror_to_sqlite("UPDATE annotations SET updated_at = :uat WHERE id = :id", [':uat' => $now, ':id' => $ann['id']]);

        touch_project($pdo, $ann['project_id'], $now);
        $replyReviewLink = "review.php?project={$ann['project_id']}&screen={$ann['screen_id']}&version={$ann['image_version_id']}&annotation={$ann['id']}";
        $replySnippet = mb_strlen($body, 'UTF-8') > 80 ? (mb_substr($body, 0, 77, 'UTF-8') . '...') : $body;
        push_activity($pdo, $ann['project_id'], $session['userId'], 'comment.replied', "đã trả lời một góp ý: \"{$replySnippet}\"", $replyReviewLink, $ann['id']);

        $stmtProj = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $stmtProj->execute([':pid' => $ann['project_id']]);
        $projName = $stmtProj->fetchColumn() ?: 'Dự án';

        // Thông báo cho tất cả thành viên trong dự án về phản hồi góp ý mới
        notify_project_members(
            $pdo,
            $ann['project_id'],
            'new_reply',
            'Phản hồi mới',
            "{$session['name']} đã trả lời trong cuộc thảo luận: \"{$replySnippet}\". Dự án: {$projName}.",
            $ann['screen_id'],
            $ann['image_version_id'],
            $ann['id'],
            $replyReviewLink,
            $session['userId']
        );

        // Check for @mentions in reply
        preg_match_all('/@([a-zA-Z0-9_\-\.]+)/u', $body, $mentionMatches);
        if (!empty($mentionMatches[1])) {
            $uniqueUnames = array_unique($mentionMatches[1]);
            foreach ($uniqueUnames as $uname) {
                $uStmt = $pdo->prepare("SELECT id, name FROM users WHERE LOWER(username) = LOWER(:u)");
                $uStmt->execute([':u' => $uname]);
                $targetUser = $uStmt->fetch();
                if ($targetUser && $targetUser['id'] !== $session['userId']) {
                    create_notification(
                        $pdo,
                        $targetUser['id'],
                        'mention',
                        'Nhắc tên trong bình luận',
                        "{$session['name']} đã nhắc tên bạn trong phản hồi tại dự án {$projName}: \"{$replySnippet}\"",
                        $ann['project_id'],
                        $ann['screen_id'],
                        $ann['image_version_id'],
                        $ann['id'],
                        $replyReviewLink,
                        $session['userId']
                    );
                }
            }
        }

        $stmtFetch = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtFetch->execute([':id' => $ann['id']]);
        json_response(present_annotation($pdo, $stmtFetch->fetch()));
    }

    if ($action === 'status') {
        $memberInfo = require_member($pdo, $ann['project_id'], ['reviewer', 'manager']);
        $session = $memberInfo['session'];

        $status = trim($input['status'] ?? '');
        if ($status !== 'open' && $status !== 'resolved') {
            json_error('Trạng thái không hợp lệ.', 'VALIDATION', 422);
        }

        if ($ann['status'] === $status) {
            json_response(present_annotation($pdo, $ann));
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $newRev = ((int)$ann['revision']) + 1;

        $stmtUp = $pdo->prepare("UPDATE annotations SET status = :st, revision = :rev, updated_at = :uat WHERE id = :id");
        $stmtUp->execute([':st' => $status, ':rev' => $newRev, ':uat' => $now, ':id' => $ann['id']]);

        $stmtSh = $pdo->prepare("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, :st, :uid, :note, :cat)");
        $stmtSh->execute([
            ':id' => generate_uuid('sh'),
            ':aid' => $ann['id'],
            ':st' => $status,
            ':uid' => $session['userId'],
            ':note' => ($status === 'resolved' ? 'Đã xử lý' : 'Mở lại'),
            ':cat' => $now
        ]);

        touch_project($pdo, $ann['project_id'], $now);
        $statusMsg = ($status === 'resolved') ? 'đã đánh dấu một góp ý là đã xử lý' : 'đã mở lại một góp ý';
        $statusReviewLink = "review.php?project={$ann['project_id']}&screen={$ann['screen_id']}&version={$ann['image_version_id']}&annotation={$ann['id']}";
        push_activity($pdo, $ann['project_id'], $session['userId'], 'comment.status', $statusMsg, $statusReviewLink, $ann['id']);

        $stmtProjName = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $stmtProjName->execute([':pid' => $ann['project_id']]);
        $projName = (string)($stmtProjName->fetchColumn() ?: 'dự án');

        $statusLabel = ($status === 'resolved') ? 'Đã xử lý' : 'Mở lại';
        notify_project_members(
            $pdo,
            $ann['project_id'],
            'status_change',
            'Trạng thái góp ý thay đổi',
            "{$session['name']} đã chuyển góp ý sang \"{$statusLabel}\". Dự án: {$projName}.",
            $ann['screen_id'],
            $ann['image_version_id'],
            $ann['id'],
            $statusReviewLink,
            $session['userId']
        );

        $stmtFetch = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtFetch->execute([':id' => $ann['id']]);
        json_response(present_annotation($pdo, $stmtFetch->fetch()));
    }

    if ($action === 'check_note') {
        require_member($pdo, $ann['project_id'], ['reviewer', 'manager']);
        $note = trim($input['note'] ?? '');
        if (mb_strlen($note, 'UTF-8') > 500) {
            json_error('Ghi chú kiểm tra tối đa 500 ký tự.', 'VALIDATION', 422);
        }
        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

        $stmtUp = $pdo->prepare("UPDATE annotations SET check_note = :note, updated_at = :uat WHERE id = :id");
        $stmtUp->execute([':note' => $note, ':uat' => $now, ':id' => $ann['id']]);

        $stmtFetch = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtFetch->execute([':id' => $ann['id']]);
        json_response(present_annotation($pdo, $stmtFetch->fetch()));
    }

    if ($action === 'delete') {
        $aid = $annotationId ?: ($input['id'] ?? $input['annotationId'] ?? '');
        if (!$aid) {
            json_error('Thiếu mã góp ý cần xóa.', 'VALIDATION', 422);
        }

        $stmtAnn = $pdo->prepare("SELECT * FROM annotations WHERE id = :id");
        $stmtAnn->execute([':id' => $aid]);
        $ann = $stmtAnn->fetch();
        if (!$ann) {
            json_error('Không tìm thấy góp ý hoặc góp ý đã bị xóa.', 'NOT_FOUND', 404);
        }

        // Check project membership and permissions (Section 6)
        $memberInfo = require_member($pdo, $ann['project_id']);
        $session = $memberInfo['session'];
        $userRole = $memberInfo['role'];
        $isAuthor = ($ann['author_id'] === $session['userId']);
        $isManager = ($userRole === 'manager');

        if ($userRole === 'viewer' || $userRole === 'admin_observer') {
            json_error('Bạn không có quyền xóa góp ý trong dự án này.', 'FORBIDDEN', 403);
        }

        if (!$isAuthor && !$isManager) {
            json_error('Bạn không có quyền xóa góp ý của người khác.', 'FORBIDDEN', 403);
        }

        // Delete cascade safely on both PostgreSQL ($pdo) and SQLite
        $delParams = [':id' => $aid];
        $pdo->prepare("DELETE FROM comment_reactions WHERE annotation_id = :id")->execute($delParams);
        $pdo->prepare("DELETE FROM notifications WHERE annotation_id = :id")->execute($delParams);
        $pdo->prepare("DELETE FROM annotation_replies WHERE annotation_id = :id")->execute($delParams);
        $pdo->prepare("DELETE FROM annotation_status_events WHERE annotation_id = :id")->execute($delParams);
        $pdo->prepare("DELETE FROM annotations WHERE id = :id")->execute($delParams);

        mirror_to_sqlite("DELETE FROM comment_reactions WHERE annotation_id = :id", $delParams);
        mirror_to_sqlite("DELETE FROM notifications WHERE annotation_id = :id", $delParams);
        mirror_to_sqlite("DELETE FROM annotation_replies WHERE annotation_id = :id", $delParams);
        mirror_to_sqlite("DELETE FROM annotation_status_events WHERE annotation_id = :id", $delParams);
        mirror_to_sqlite("DELETE FROM annotations WHERE id = :id", $delParams);

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        touch_project($pdo, $ann['project_id'], $now);
        push_activity($pdo, $ann['project_id'], $session['userId'], 'comment.deleted', "đã xóa góp ý #{$ann['number']}");

        json_response([
            'success' => true,
            'id' => $aid,
            'number' => (int)($ann['number'] ?? 0),
            'message' => 'Đã xóa góp ý thành công.'
        ]);
    }
}

json_error('Thao tác không hợp lệ.', 'NOT_FOUND', 404);
