<?php
// api/screens.php - Screens API

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$screenId = $_GET['id'] ?? $_POST['id'] ?? $_GET['screen_id'] ?? $_POST['screen_id'] ?? null;
$projectId = $_GET['project_id'] ?? $_POST['project_id'] ?? null;

function process_uploaded_image($fileKey = 'file') {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        json_error('Vui lòng chọn tệp ảnh hợp lệ.', 'VALIDATION', 422);
    }
    $file = $_FILES[$fileKey];
    if ($file['size'] > 10 * 1024 * 1024) {
        json_error('Ảnh vượt quá dung lượng tối đa 10 MB.', 'VALIDATION', 422);
    }
    $tmpPath = $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);

    if (str_ends_with(strtolower($file['name']), '.svg')) {
        $mime = 'image/svg+xml';
    }

    $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        json_error('Định dạng ảnh không được hỗ trợ. Chỉ chấp nhận PNG, JPEG, SVG hoặc WebP.', 'VALIDATION', 422);
    }

    $width = 0;
    $height = 0;
    if ($mime === 'image/svg+xml') {
        $content = file_get_contents($tmpPath);
        if (preg_match('/viewBox\s*=\s*["\']\s*[\d\.\-]+\s+[\d\.\-]+\s+([\d\.]+)\s+([\d\.]+)/i', $content, $m)) {
            $width = (int)$m[1];
            $height = (int)$m[2];
        } elseif (preg_match('/width\s*=\s*["\']([\d\.]+)["\']/i', $content, $mw) && preg_match('/height\s*=\s*["\']([\d\.]+)["\']/i', $content, $mh)) {
            $width = (int)$mw[1];
            $height = (int)$mh[1];
        }
        if ($width <= 0) $width = 1000;
        if ($height <= 0) $height = 500;
    } else {
        $info = @getimagesize($tmpPath);
        if (!$info) {
            json_error('Không thể đọc kích thước tệp ảnh.', 'VALIDATION', 422);
        }
        $width = $info[0];
        $height = $info[1];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
    $newFileName = generate_uuid('img') . '.' . strtolower($ext);
    $destPath = __DIR__ . '/../uploads/' . $newFileName;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        json_error('Không thể lưu ảnh lên máy chủ.', 'SAVE_FAILED', 500);
    }

    return [
        'width' => $width,
        'height' => $height,
        'mime' => $mime,
        'imageKey' => 'upload:' . $newFileName
    ];
}

// GET
if ($method === 'GET') {
    if ($screenId) {
        $stmt = $pdo->prepare("SELECT * FROM design_screens WHERE id = :id");
        $stmt->execute([':id' => $screenId]);
        $screen = $stmt->fetch();
        if (!$screen) {
            json_error('Không tìm thấy màn hình thiết kế.', 'NOT_FOUND', 404);
        }
        require_member($pdo, $screen['project_id']);
        json_response(summarize_screen($pdo, $screen));
    }

    if ($projectId) {
        require_member($pdo, $projectId);

        $isPaginated = isset($_GET['page']) || isset($_GET['paginate']) || isset($_GET['limit']);

        if (!$isPaginated) {
            $stmt = $pdo->prepare("SELECT * FROM design_screens WHERE project_id = :pid ORDER BY COALESCE(sort_order, 0) ASC, created_at ASC");
            $stmt->execute([':pid' => $projectId]);
            $screens = $stmt->fetchAll();
            $result = [];
            foreach ($screens as $s) {
                $result[] = summarize_screen($pdo, $s);
            }
            json_response($result);
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 6)));
        $q = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? 'all');
        $sort = trim($_GET['sort'] ?? 'order');

        $stmtAll = $pdo->prepare("SELECT * FROM design_screens WHERE project_id = :pid ORDER BY COALESCE(sort_order, 0) ASC, created_at ASC");
        $stmtAll->execute([':pid' => $projectId]);
        $allScreens = $stmtAll->fetchAll();

        $stats = [
            'total' => count($allScreens),
            'open' => 0,
            'resolved' => 0,
            'multiVersion' => 0,
            'hasComments' => 0
        ];

        $filtered = [];
        foreach ($allScreens as $s) {
            $sum = summarize_screen($pdo, $s);
            if ($sum['openCount'] > 0) $stats['open']++;
            else $stats['resolved']++;
            if ($sum['versionCount'] > 1) $stats['multiVersion']++;
            if (($sum['commentCount'] ?? 0) > 0) $stats['hasComments']++;

            if ($q !== '') {
                $qLower = mb_strtolower($q, 'UTF-8');
                $matchName = mb_stripos($sum['name'] ?? '', $qLower) !== false;
                $matchDesc = mb_stripos($sum['description'] ?? '', $qLower) !== false;
                if (!$matchName && !$matchDesc) continue;
            }

            if ($status === 'open' && $sum['openCount'] <= 0) continue;
            if ($status === 'resolved' && $sum['openCount'] > 0) continue;
            if ($status === 'multi_version' && $sum['versionCount'] <= 1) continue;
            if ($status === 'has_comments' && ($sum['commentCount'] ?? 0) <= 0) continue;

            $filtered[] = $sum;
        }

        if ($sort === 'updated') {
            usort($filtered, function($a, $b) {
                return strcmp($b['updatedAt'] ?? $b['createdAt'], $a['updatedAt'] ?? $a['createdAt']);
            });
        } elseif ($sort === 'created') {
            usort($filtered, function($a, $b) {
                return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
            });
        } elseif ($sort === 'name') {
            usort($filtered, function($a, $b) {
                return strcoll($a['name'] ?? '', $b['name'] ?? '');
            });
        } elseif ($sort === 'comments') {
            usort($filtered, function($a, $b) {
                return ($b['commentCount'] ?? 0) - ($a['commentCount'] ?? 0);
            });
        }

        $totalFiltered = count($filtered);
        $totalPages = max(1, (int)ceil($totalFiltered / $limit));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $limit;
        $pageScreens = array_slice($filtered, $offset, $limit);

        json_response([
            'screens' => $pageScreens,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFiltered,
                'totalPages' => $totalPages
            ],
            'stats' => $stats
        ]);
    }

    json_error('Thiếu mã dự án hoặc màn hình.', 'VALIDATION', 422);
}

// POST
if ($method === 'POST') {
    $input = get_json_input();
    if (!$action) {
        $action = $input['action'] ?? '';
    }
    if ($action === 'create') {
        $pid = $projectId ?: ($_POST['projectId'] ?? $_POST['project_id'] ?? '');
        if (!$pid) {
            json_error('Thiếu mã dự án.', 'VALIDATION', 422);
        }
        $memberInfo = require_member($pdo, $pid, ['manager']);
        $session = $memberInfo['session'];

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            json_error('Tên màn hình không được để trống.', 'VALIDATION', 422);
        }
        if (mb_strlen($name, 'UTF-8') > 80) {
            json_error('Tên màn hình tối đa 80 ký tự.', 'VALIDATION', 422);
        }

        $uploaded = process_uploaded_image('file');
        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

        $scrId = generate_uuid('scr');
        $verId = generate_uuid('ver');

        $stmtMaxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM design_screens WHERE project_id = :pid");
        $stmtMaxOrder->execute([':pid' => $pid]);
        $nextOrder = ((int)$stmtMaxOrder->fetchColumn()) + 1;

        $stmtScrParams = [
            ':id' => $scrId,
            ':pid' => $pid,
            ':name' => $name,
            ':ord' => $nextOrder,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':uid' => $session['userId']
        ];
        $stmtScr = $pdo->prepare("INSERT INTO design_screens (id, project_id, name, description, sort_order, created_at, updated_at, created_by) VALUES (:id, :pid, :name, '', :ord, :created_at, :updated_at, :uid)");
        $stmtScr->execute($stmtScrParams);
        mirror_to_sqlite("INSERT INTO design_screens (id, project_id, name, description, sort_order, created_at, updated_at, created_by) VALUES (:id, :pid, :name, '', :ord, :created_at, :updated_at, :uid)", $stmtScrParams);

        $stmtVerParams = [
            ':id' => $verId,
            ':sid' => $scrId,
            ':pid' => $pid,
            ':w' => $uploaded['width'],
            ':h' => $uploaded['height'],
            ':ikey' => $uploaded['imageKey'],
            ':mime' => $uploaded['mime'],
            ':cat' => $now,
            ':uid' => $session['userId']
        ];
        $stmtVer = $pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:id, :sid, :pid, 1, :w, :h, :ikey, :mime, 'upload', :cat, :uid)");
        $stmtVer->execute($stmtVerParams);
        mirror_to_sqlite("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:id, :sid, :pid, 1, :w, :h, :ikey, :mime, 'upload', :cat, :uid)", $stmtVerParams);

        touch_project($pdo, $pid, $now);
        push_activity($pdo, $pid, $session['userId'], 'screen.created', "đã tải màn hình $name, phiên bản v1");

        $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $projStmt->execute([':pid' => $pid]);
        $projName = $projStmt->fetchColumn() ?: 'dự án';

        notify_project_members(
            $pdo,
            $pid,
            'new_version',
            'Màn hình mới',
            "Dự án {$projName} đã có màn hình mới \"{$name}\" (v1)",
            $scrId,
            $verId,
            null,
            "review.php?project={$pid}&screen={$scrId}&version={$verId}",
            $session['userId']
        );

        $stmtFetch = $pdo->prepare("SELECT * FROM design_screens WHERE id = :id");
        $stmtFetch->execute([':id' => $scrId]);
        json_response(summarize_screen($pdo, $stmtFetch->fetch()), 201);
    }

    if ($action === 'add_version' || $action === 'create_version') {
        $sid = $screenId ?: ($_POST['screenId'] ?? $_POST['screen_id'] ?? '');
        if (!$sid) {
            json_error('Thiếu mã màn hình.', 'VALIDATION', 422);
        }
        $stmtScr = $pdo->prepare("SELECT * FROM design_screens WHERE id = :id");
        $stmtScr->execute([':id' => $sid]);
        $screen = $stmtScr->fetch();
        if (!$screen) {
            json_error('Không tìm thấy màn hình thiết kế.', 'NOT_FOUND', 404);
        }

        $memberInfo = require_member($pdo, $screen['project_id'], ['manager']);
        $session = $memberInfo['session'];

        $uploaded = process_uploaded_image('file');
        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

        $stmtMax = $pdo->prepare("SELECT max(number) FROM image_versions WHERE screen_id = :sid");
        $stmtMax->execute([':sid' => $sid]);
        $nextNumber = ((int)$stmtMax->fetchColumn()) + 1;

        $verId = generate_uuid('ver');
        $verParams = [
            ':id' => $verId,
            ':sid' => $sid,
            ':pid' => $screen['project_id'],
            ':num' => $nextNumber,
            ':w' => $uploaded['width'],
            ':h' => $uploaded['height'],
            ':ikey' => $uploaded['imageKey'],
            ':mime' => $uploaded['mime'],
            ':cat' => $now,
            ':uid' => $session['userId']
        ];
        $stmtVer = $pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:id, :sid, :pid, :num, :w, :h, :ikey, :mime, 'upload', :cat, :uid)");
        $stmtVer->execute($verParams);
        mirror_to_sqlite("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:id, :sid, :pid, :num, :w, :h, :ikey, :mime, 'upload', :cat, :uid)", $verParams);

        $stmtUpScr = $pdo->prepare("UPDATE design_screens SET updated_at = :now WHERE id = :sid");
        $stmtUpScr->execute([':now' => $now, ':sid' => $sid]);
        mirror_to_sqlite("UPDATE design_screens SET updated_at = :now WHERE id = :sid", [':now' => $now, ':sid' => $sid]);

        touch_project($pdo, $screen['project_id'], $now);
        push_activity($pdo, $screen['project_id'], $session['userId'], 'version.created', "đã tải phiên bản v$nextNumber cho màn hình {$screen['name']}");

        $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $projStmt->execute([':pid' => $screen['project_id']]);
        $projName = $projStmt->fetchColumn() ?: 'dự án';

        notify_project_members(
            $pdo,
            $screen['project_id'],
            'new_version',
            'Phiên bản mới',
            "Dự án {$projName} đã có phiên bản v{$nextNumber} cho \"{$screen['name']}\"",
            $screen['id'],
            $verId,
            null,
            "review.php?project={$screen['project_id']}&screen={$screen['id']}&version={$verId}",
            $session['userId']
        );

        $stmtFetch = $pdo->prepare("SELECT * FROM image_versions WHERE id = :id");
        $stmtFetch->execute([':id' => $verId]);
        json_response(present_version($pdo, $stmtFetch->fetch()), 201);
    }

    if ($action === 'reorder') {
        $input = get_json_input();
        $pid = $projectId ?: ($input['projectId'] ?? $input['project_id'] ?? '');
        if (!$pid) {
            json_error('Thiếu mã dự án.', 'VALIDATION', 422);
        }
        $memberInfo = require_member($pdo, $pid, ['manager', 'reviewer']);
        $session = $memberInfo['session'];

        $order = $input['order'] ?? [];
        if (!is_array($order) || empty($order)) {
            json_error('Danh sách thứ tự màn hình không hợp lệ.', 'VALIDATION', 422);
        }

        $stmtUp = $pdo->prepare("UPDATE design_screens SET sort_order = :ord WHERE id = :id AND project_id = :pid");
        foreach ($order as $idx => $sId) {
            $sortNum = $idx + 1;
            $stmtUp->execute([':ord' => $sortNum, ':id' => $sId, ':pid' => $pid]);
            mirror_to_sqlite("UPDATE design_screens SET sort_order = :ord WHERE id = :id AND project_id = :pid", [':ord' => $sortNum, ':id' => $sId, ':pid' => $pid]);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        touch_project($pdo, $pid, $now);
        push_activity($pdo, $pid, $session['userId'], 'screens.reordered', "đã sắp xếp lại thứ tự các màn hình");

        json_response(['success' => true]);
    }

    if ($action === 'rename' || $action === 'update') {
        $sid = $screenId ?: ($input['id'] ?? $input['screenId'] ?? $_POST['screenId'] ?? '');
        if (!$sid) {
            json_error('Thiếu mã màn hình.', 'VALIDATION', 422);
        }
        $stmtScr = $pdo->prepare("SELECT * FROM design_screens WHERE id = :id");
        $stmtScr->execute([':id' => $sid]);
        $screen = $stmtScr->fetch();
        if (!$screen) {
            json_error('Không tìm thấy màn hình thiết kế.', 'NOT_FOUND', 404);
        }
        $memberInfo = require_member($pdo, $screen['project_id'], ['manager']);
        $session = $memberInfo['session'];

        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        if (!$name) {
            json_error('Tên màn hình không được để trống.', 'VALIDATION', 422);
        }
        if (mb_strlen($name, 'UTF-8') > 80) {
            json_error('Tên màn hình tối đa 80 ký tự.', 'VALIDATION', 422);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $oldName = $screen['name'];
        $stmtUp = $pdo->prepare("UPDATE design_screens SET name = :name, updated_at = :uat WHERE id = :id");
        $stmtUp->execute([':name' => $name, ':uat' => $now, ':id' => $sid]);
        mirror_to_sqlite("UPDATE design_screens SET name = :name, updated_at = :uat WHERE id = :id", [':name' => $name, ':uat' => $now, ':id' => $sid]);

        touch_project($pdo, $screen['project_id'], $now);
        push_activity($pdo, $screen['project_id'], $session['userId'], 'screen.renamed', "đã đổi tên màn hình từ \"$oldName\" thành \"$name\"");

        $stmtFetch = $pdo->prepare("SELECT * FROM design_screens WHERE id = :id");
        $stmtFetch->execute([':id' => $sid]);
        json_response(summarize_screen($pdo, $stmtFetch->fetch()));
    }

    if ($action === 'delete') {
        $sid = $screenId ?: ($input['id'] ?? $input['screenId'] ?? $_POST['screenId'] ?? '');
        if (!$sid) {
            json_error('Thiếu mã màn hình cần xóa.', 'VALIDATION', 422);
        }
        $stmtScr = $pdo->prepare("SELECT * FROM design_screens WHERE id = :id");
        $stmtScr->execute([':id' => $sid]);
        $screen = $stmtScr->fetch();
        if (!$screen) {
            json_error('Không tìm thấy màn hình thiết kế hoặc đã bị xóa.', 'NOT_FOUND', 404);
        }
        $memberInfo = require_member($pdo, $screen['project_id'], ['manager']);
        $session = $memberInfo['session'];

        // Get all version IDs for this screen
        $stmtVerIds = $pdo->prepare("SELECT id FROM image_versions WHERE screen_id = :sid");
        $stmtVerIds->execute([':sid' => $sid]);
        $verIds = $stmtVerIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($verIds)) {
            foreach ($verIds as $vId) {
                $stmtAnns = $pdo->prepare("SELECT id FROM annotations WHERE image_version_id = :vid");
                $stmtAnns->execute([':vid' => $vId]);
                $annIds = $stmtAnns->fetchAll(PDO::FETCH_COLUMN);
                foreach ($annIds as $aId) {
                    $delP = [':id' => $aId];
                    $pdo->prepare("DELETE FROM annotation_replies WHERE annotation_id = :id")->execute($delP);
                    mirror_to_sqlite("DELETE FROM annotation_replies WHERE annotation_id = :id", $delP);
                    $pdo->prepare("DELETE FROM annotation_status_events WHERE annotation_id = :id")->execute($delP);
                    mirror_to_sqlite("DELETE FROM annotation_status_events WHERE annotation_id = :id", $delP);
                    $pdo->prepare("DELETE FROM notifications WHERE annotation_id = :id")->execute($delP);
                    mirror_to_sqlite("DELETE FROM notifications WHERE annotation_id = :id", $delP);
                    $pdo->prepare("DELETE FROM annotations WHERE id = :id")->execute($delP);
                    mirror_to_sqlite("DELETE FROM annotations WHERE id = :id", $delP);
                }
                $delVerP = [':id' => $vId];
                $pdo->prepare("DELETE FROM notifications WHERE image_version_id = :id")->execute($delVerP);
                mirror_to_sqlite("DELETE FROM notifications WHERE image_version_id = :id", $delVerP);
                $pdo->prepare("DELETE FROM image_versions WHERE id = :id")->execute($delVerP);
                mirror_to_sqlite("DELETE FROM image_versions WHERE id = :id", $delVerP);
            }
        }

        $delScrP = [':id' => $sid];
        $pdo->prepare("DELETE FROM notifications WHERE screen_id = :id")->execute($delScrP);
        mirror_to_sqlite("DELETE FROM notifications WHERE screen_id = :id", $delScrP);
        $pdo->prepare("DELETE FROM design_screens WHERE id = :id")->execute($delScrP);
        mirror_to_sqlite("DELETE FROM design_screens WHERE id = :id", $delScrP);

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        touch_project($pdo, $screen['project_id'], $now);
        push_activity($pdo, $screen['project_id'], $session['userId'], 'screen.deleted', "đã xóa màn hình \"{$screen['name']}\"");

        json_response(['success' => true, 'id' => $sid, 'message' => "Đã xóa màn hình \"{$screen['name']}\"."]);
    }
}

json_error('Thao tác không hợp lệ.', 'NOT_FOUND', 404);
