<?php
// api/projects.php - Projects API

require_once __DIR__ . '/db.php';

// Purge projects in trash older than 30 days
purge_expired_trash($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$projectId = $_GET['id'] ?? $_POST['id'] ?? $_GET['project_id'] ?? null;

// GET single or list
if ($method === 'GET') {
    if ($projectId) {
        $memberInfo = require_member($pdo, $projectId);
        $session = $memberInfo['session'];

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->execute([':id' => $projectId]);
        $proj = $stmt->fetch();
        if (!$proj) {
            json_error('Không tìm thấy dự án.', 'NOT_FOUND', 404);
        }

        // If project is in trash, only manager or admin can access it
        if (!empty($proj['deleted_at'])) {
            if ($memberInfo['role'] !== 'manager' && ($session['systemRole'] ?? '') !== 'admin') {
                json_error('Dự án này đã bị chuyển vào thùng rác.', 'PROJECT_TRASHED', 404);
            }
        }

        if ($action === 'members') {
            $stmtOwner = $pdo->prepare("SELECT created_by FROM projects WHERE id = :pid");
            $stmtOwner->execute([':pid' => $projectId]);
            $ownerId = $stmtOwner->fetchColumn();

            $q = trim($_GET['q'] ?? '');
            if ($q !== '') {
                $qLower = '%' . mb_strtolower($q, 'UTF-8') . '%';
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.username, u.email, pm.role, u.color, u.title, pm.id as \"membershipId\", pm.created_at as \"createdAt\" FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid AND (LOWER(u.name) LIKE :q OR LOWER(u.username) LIKE :q OR LOWER(COALESCE(u.email, '')) LIKE :q) ORDER BY CASE WHEN u.id = :ownerId THEN 0 ELSE 1 END, pm.created_at ASC");
                $stmt->execute([':pid' => $projectId, ':q' => $qLower, ':ownerId' => $ownerId]);
            } else {
                $stmt = $pdo->prepare("SELECT u.id, u.name, u.username, u.email, pm.role, u.color, u.title, pm.id as \"membershipId\", pm.created_at as \"createdAt\" FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid ORDER BY CASE WHEN u.id = :ownerId THEN 0 ELSE 1 END, pm.created_at ASC");
                $stmt->execute([':pid' => $projectId, ':ownerId' => $ownerId]);
            }
            $allMembers = $stmt->fetchAll();

            foreach ($allMembers as &$m) {
                if ($m['id'] === $ownerId) {
                    $m['role'] = 'manager';
                }
            }
            unset($m);

            $isPaginated = isset($_GET['page']) || isset($_GET['paginate']) || isset($_GET['limit']);
            if (!$isPaginated) {
                json_response($allMembers);
            }

            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
            $total = count($allMembers);
            $totalPages = max(1, (int)ceil($total / $limit));
            if ($page > $totalPages) $page = $totalPages;
            $offset = ($page - 1) * $limit;
            $pageMembers = array_slice($allMembers, $offset, $limit);

            json_response([
                'members' => $pageMembers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => $totalPages
                ]
            ]);
        }

        if ($action === 'invite_candidates') {
            require_member($pdo, $projectId, ['manager']);
            $q = trim($_GET['q'] ?? '');
            if ($q !== '') {
                $stmt = $pdo->prepare("SELECT id, name, username, email, color, title, COALESCE(system_role, 'user') as \"systemRole\", COALESCE(status, 'active') as status FROM users WHERE COALESCE(status, 'active') = 'active' AND (lower(username) LIKE :q OR lower(name) LIKE :q OR lower(COALESCE(email, '')) LIKE :q) AND id NOT IN (SELECT user_id FROM project_members WHERE project_id = :pid) ORDER BY CASE WHEN lower(username) = :exact OR lower(COALESCE(email, '')) = :exact THEN 0 WHEN lower(username) LIKE :prefix OR lower(COALESCE(email, '')) LIKE :prefix THEN 1 ELSE 2 END, username ASC LIMIT 10");
                $stmt->execute([
                    ':q' => '%' . strtolower($q) . '%',
                    ':pid' => $projectId,
                    ':exact' => strtolower($q),
                    ':prefix' => strtolower($q) . '%'
                ]);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, username, email, color, title, COALESCE(system_role, 'user') as \"systemRole\", COALESCE(status, 'active') as status FROM users WHERE COALESCE(status, 'active') = 'active' AND id NOT IN (SELECT user_id FROM project_members WHERE project_id = :pid) ORDER BY username ASC LIMIT 15");
                $stmt->execute([':pid' => $projectId]);
            }
            json_response($stmt->fetchAll());
        }

        if ($action === 'activities') {
            $stmt = $pdo->prepare("SELECT id, project_id as \"projectId\", user_id as \"userId\", type, message, created_at as \"createdAt\" FROM activities WHERE project_id = :pid ORDER BY created_at DESC");
            $stmt->execute([':pid' => $projectId]);
            $acts = $stmt->fetchAll();
            foreach ($acts as &$act) {
                $act['user'] = public_user($pdo, $act['userId']);
            }

            $isPaginated = isset($_GET['page']) || isset($_GET['paginate']) || isset($_GET['limit']);
            if (!$isPaginated) {
                json_response($acts);
            }

            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
            $total = count($acts);
            $totalPages = max(1, (int)ceil($total / $limit));
            if ($page > $totalPages) $page = $totalPages;
            $offset = ($page - 1) * $limit;
            $pageActs = array_slice($acts, $offset, $limit);

            json_response([
                'activities' => $pageActs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => $totalPages
                ]
            ]);
        }

        if ($action === 'role') {
            json_response($memberInfo['role']);
        }

        json_response(summarize_project($pdo, $proj, $session['userId']));
    }

    // List all projects
    $session = require_auth($pdo);
    $isTrash = isset($_GET['trash']) || $action === 'trash';

    if ($isTrash) {
        // Trash bin: Admin sees all trashed projects, regular users only see trashed projects where they were manager
        if (($session['systemRole'] ?? '') === 'admin') {
            $stmt = $pdo->query("SELECT * FROM projects WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
            $projects = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = :uid AND pm.role = 'manager' AND p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC");
            $stmt->execute([':uid' => $session['userId']]);
            $projects = $stmt->fetchAll();
        }
    } else {
        // Active projects: deleted_at IS NULL
        if (($session['systemRole'] ?? '') === 'admin') {
            $stmt = $pdo->query("SELECT * FROM projects WHERE deleted_at IS NULL ORDER BY updated_at DESC, created_at DESC");
            $projects = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = :uid AND p.deleted_at IS NULL ORDER BY p.updated_at DESC, p.created_at DESC");
            $stmt->execute([':uid' => $session['userId']]);
            $projects = $stmt->fetchAll();
        }
    }

    $allSummaries = [];
    foreach ($projects as $p) {
        $allSummaries[] = summarize_project($pdo, $p, $session['userId']);
    }

    $isPaginated = isset($_GET['page']) || isset($_GET['paginate']) || isset($_GET['limit']);
    if (!$isPaginated) {
        json_response($allSummaries);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 12)));
    $tab = trim($_GET['tab'] ?? 'all');
    $q = trim($_GET['q'] ?? '');
    $sort = trim($_GET['sort'] ?? 'updated');

    $stats = [
        'total' => count($allSummaries),
        'mine' => 0,
        'shared' => 0
    ];

    $filtered = [];
    foreach ($allSummaries as $item) {
        if ($item['isOwner']) $stats['mine']++;
        else $stats['shared']++;

        if ($tab === 'mine' && !$item['isOwner']) continue;
        if ($tab === 'shared' && $item['isOwner']) continue;

        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $matchName = mb_stripos($item['name'] ?? '', $qLower) !== false;
            $matchDesc = mb_stripos($item['description'] ?? '', $qLower) !== false;
            if (!$matchName && !$matchDesc) continue;
        }

        $filtered[] = $item;
    }

    if ($sort === 'name') {
        usort($filtered, function($a, $b) {
            return strcoll($a['name'] ?? '', $b['name'] ?? '');
        });
    } elseif ($sort === 'comments') {
        usort($filtered, function($a, $b) {
            return ($b['openComments'] ?? 0) - ($a['openComments'] ?? 0);
        });
    } elseif ($sort === 'screens') {
        usort($filtered, function($a, $b) {
            return ($b['screenCount'] ?? 0) - ($a['screenCount'] ?? 0);
        });
    } elseif ($sort === 'updated') {
        usort($filtered, function($a, $b) {
            return strcmp($b['updatedAt'] ?? $b['createdAt'], $a['updatedAt'] ?? $a['createdAt']);
        });
    }

    $totalFiltered = count($filtered);
    $totalPages = max(1, (int)ceil($totalFiltered / $limit));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $limit;
    $pageProjects = array_slice($filtered, $offset, $limit);

    json_response([
        'projects' => $pageProjects,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalFiltered,
            'totalPages' => $totalPages
        ],
        'stats' => $stats
    ]);
}

// POST or DELETE actions
if ($method === 'POST' || $method === 'DELETE') {
    $session = require_auth($pdo);
    $input = get_json_input();

    // Create project: Any authenticated active user can create a project and become Manager
    if ($method === 'POST' && ($action === 'create' || (!$action && !$projectId))) {
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $description = trim($input['description'] ?? $_POST['description'] ?? '');
        $category = trim($input['category'] ?? $_POST['category'] ?? 'Khác');
        if (!$category) {
            $category = 'Khác';
        }

        if (!$name) {
            json_error('Tên dự án không được để trống.', 'VALIDATION', 422);
        }
        if (mb_strlen($name, 'UTF-8') > 120) {
            json_error('Tên dự án tối đa 120 ký tự.', 'VALIDATION', 422);
        }
        if (mb_strlen($description, 'UTF-8') > 500) {
            json_error('Mô tả tối đa 500 ký tự.', 'VALIDATION', 422);
        }
        if (mb_strlen($category, 'UTF-8') > 50) {
            $category = mb_substr($category, 0, 50, 'UTF-8');
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $id = generate_uuid('proj');

        $stmt = $pdo->prepare("INSERT INTO projects (id, name, description, category, created_at, updated_at, created_by) VALUES (:id, :name, :description, :category, :created_at, :updated_at, :created_by)");
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':description' => $description,
            ':category' => $category,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':created_by' => $session['userId']
        ]);
        mirror_to_sqlite("INSERT INTO projects (id, name, description, category, created_at, updated_at, created_by) VALUES (:id, :name, :description, :category, :created_at, :updated_at, :created_by)", [
            ':id' => $id,
            ':name' => $name,
            ':description' => $description,
            ':category' => $category,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':created_by' => $session['userId']
        ]);

        $stmtMem = $pdo->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, 'manager', :inv, :created_at)");
        $stmtMem->execute([
            ':id' => generate_uuid('mem'),
            ':pid' => $id,
            ':uid' => $session['userId'],
            ':inv' => $session['userId'],
            ':created_at' => $now
        ]);

        push_activity($pdo, $id, $session['userId'], 'project.created', "đã tạo dự án $name");

        $stmtFetch = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmtFetch->execute([':id' => $id]);
        $newProj = $stmtFetch->fetch();
        json_response(summarize_project($pdo, $newProj, $session['userId']), 201);
    }

    // Project sub-actions require projectId
    if (!$projectId) {
        json_error('Thiếu mã dự án.', 'VALIDATION', 422);
    }

    // Restore project from trash
    if ($action === 'restore' && $method === 'POST') {
        $stmtOwner = $pdo->prepare("SELECT created_by FROM projects WHERE id = :pid");
        $stmtOwner->execute([':pid' => $projectId]);
        $isOwner = ($stmtOwner->fetchColumn() === $session['userId']);

        $stmtRole = $pdo->prepare("SELECT role FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtRole->execute([':pid' => $projectId, ':uid' => $session['userId']]);
        $myRole = $stmtRole->fetchColumn();

        if ($myRole !== 'manager' && !$isOwner) {
            json_error('Chỉ Quản lý dự án mới có quyền khôi phục dự án.', 'FORBIDDEN', 403);
        }

        $stmtProj = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmtProj->execute([':id' => $projectId]);
        $proj = $stmtProj->fetch();
        if (!$proj) {
            json_error('Không tìm thấy dự án.', 'NOT_FOUND', 404);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $stmtRestore = $pdo->prepare("UPDATE projects SET deleted_at = NULL, deleted_by = NULL, updated_at = :up WHERE id = :id");
        $stmtRestore->execute([':up' => $now, ':id' => $projectId]);
        mirror_to_sqlite("UPDATE projects SET deleted_at = NULL, deleted_by = NULL, updated_at = :up WHERE id = :id", [':up' => $now, ':id' => $projectId]);

        push_activity($pdo, $projectId, $session['userId'], 'project.restored', "đã khôi phục dự án từ thùng rác");

        json_response([
            'success' => true,
            'restored' => true,
            'message' => 'Đã khôi phục dự án "' . $proj['name'] . '" thành công.',
            'projectId' => $projectId
        ]);
    }

    // Delete project (Soft delete into Trash or Permanent delete)
    if ($action === 'delete' || $action === 'permanent_delete' || $method === 'DELETE') {
        // Must be manager in the project or project creator (Admin observer cannot delete other people's projects)
        $stmtOwner = $pdo->prepare("SELECT created_by FROM projects WHERE id = :pid");
        $stmtOwner->execute([':pid' => $projectId]);
        $isOwner = ($stmtOwner->fetchColumn() === $session['userId']);

        $stmtRole = $pdo->prepare("SELECT role FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtRole->execute([':pid' => $projectId, ':uid' => $session['userId']]);
        $myRole = $stmtRole->fetchColumn();

        if ($myRole !== 'manager' && !$isOwner) {
            json_error('Chỉ Quản lý dự án mới có quyền xóa dự án.', 'FORBIDDEN', 403);
        }

        $stmtProj = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmtProj->execute([':id' => $projectId]);
        $proj = $stmtProj->fetch();
        if (!$proj) {
            json_error('Không tìm thấy dự án.', 'NOT_FOUND', 404);
        }

        $isPermanent = ($action === 'permanent_delete') || (!empty($input['permanent']));

        if ($isPermanent) {
            // Permanent deletion: clean up files and cascade delete
            permanently_delete_project($pdo, $projectId);
            json_response([
                'success' => true,
                'permanent' => true,
                'message' => 'Đã xóa vĩnh viễn dự án "' . $proj['name'] . '" và toàn bộ dữ liệu liên quan.',
                'projectId' => $projectId
            ]);
        } else {
            // Soft delete: move to trash bin
            $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
            $stmtTrash = $pdo->prepare("UPDATE projects SET deleted_at = :del_at, deleted_by = :del_by WHERE id = :id");
            $stmtTrash->execute([':del_at' => $now, ':del_by' => $session['userId'], ':id' => $projectId]);
            mirror_to_sqlite("UPDATE projects SET deleted_at = :del_at, deleted_by = :del_by WHERE id = :id", [':del_at' => $now, ':del_by' => $session['userId'], ':id' => $projectId]);

            push_activity($pdo, $projectId, $session['userId'], 'project.trashed', "đã chuyển dự án vào thùng rác");

            json_response([
                'success' => true,
                'trashed' => true,
                'message' => 'Đã chuyển dự án "' . $proj['name'] . '" vào thùng rác. Dự án sẽ tự động bị xóa vĩnh viễn sau 30 ngày nếu không khôi phục.',
                'projectId' => $projectId
            ]);
        }
    }

    // Update project information (description, name, category)
    if ($action === 'update' && $method === 'POST') {
        require_member($pdo, $projectId, ['manager']);
        $description = isset($input['description']) ? trim($input['description']) : null;
        $name = isset($input['name']) ? trim($input['name']) : null;
        $category = isset($input['category']) ? trim($input['category']) : null;

        $updates = [];
        $params = [':id' => $projectId];

        if ($description !== null) {
            if (mb_strlen($description, 'UTF-8') > 1000) {
                json_error('Mô tả tối đa 1000 ký tự.', 'VALIDATION', 422);
            }
            $updates[] = 'description = :description';
            $params[':description'] = $description;
        }

        if ($name !== null) {
            if ($name === '') {
                json_error('Tên dự án không được để trống.', 'VALIDATION', 422);
            }
            if (mb_strlen($name, 'UTF-8') > 120) {
                json_error('Tên dự án tối đa 120 ký tự.', 'VALIDATION', 422);
            }
            $updates[] = 'name = :name';
            $params[':name'] = $name;
        }

        if ($category !== null) {
            $updates[] = 'category = :category';
            $params[':category'] = mb_substr($category, 0, 50, 'UTF-8');
        }

        if (empty($updates)) {
            json_error('Không có thông tin nào được cập nhật.', 'VALIDATION', 422);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $updates[] = 'updated_at = :up';
        $params[':up'] = $now;

        $sql = "UPDATE projects SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        mirror_to_sqlite($sql, $params);

        push_activity($pdo, $projectId, $session['userId'], 'project.updated', "đã cập nhật thông tin dự án");
        touch_project($pdo, $projectId);

        $stmtFetch = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
        $stmtFetch->execute([':id' => $projectId]);
        $updatedProj = $stmtFetch->fetch();
        json_response([
            'success' => true,
            'message' => 'Cập nhật thông tin dự án thành công.',
            'project' => summarize_project($pdo, $updatedProj, $session['userId'])
        ]);
    }

    if ($action === 'invite') {
        require_member($pdo, $projectId, ['manager']);
        $userId = trim($input['userId'] ?? $_POST['userId'] ?? '');
        $username = trim($input['username'] ?? $_POST['username'] ?? '');
        $email = trim($input['email'] ?? $_POST['email'] ?? '');
        $role = trim($input['role'] ?? $_POST['role'] ?? '');

        $target = $username ?: $email;
        if (!$userId && $target) {
            $stmtFind = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = :u OR (email IS NOT NULL AND LOWER(email) = :u) LIMIT 1");
            $stmtFind->execute([':u' => strtolower($target)]);
            $foundId = $stmtFind->fetchColumn();
            if (!$foundId) {
                json_error("Không tìm thấy tài khoản với tên đăng nhập hoặc email \"{$target}\".", 'NOT_FOUND', 404);
            }
            $userId = (string)$foundId;
        } elseif ($userId && !str_starts_with($userId, 'user-')) {
            $stmtFind = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = :u OR (email IS NOT NULL AND LOWER(email) = :u) LIMIT 1");
            $stmtFind->execute([':u' => strtolower($userId)]);
            $foundId = $stmtFind->fetchColumn();
            if ($foundId) {
                $userId = (string)$foundId;
            }
        }

        if (!$userId) {
            json_error('Vui lòng nhập tên đăng nhập hoặc email của thành viên cần thêm.', 'VALIDATION', 422);
        }

        if ($role === 'commenter') {
            $role = 'reviewer';
        }
        if ($role === 'designer') {
            $role = 'manager';
        }

        if (!in_array($role, ['viewer', 'reviewer', 'manager'], true)) {
            json_error('Vai trò không hợp lệ. Vui lòng chọn Designer, Reviewer hoặc Viewer.', 'VALIDATION', 422);
        }

        $user = public_user($pdo, $userId);
        if (!$user || empty($user['username'])) {
            json_error('Không tìm thấy tài khoản người dùng.', 'VALIDATION', 422);
        }

        if (($user['status'] ?? 'active') === 'locked') {
            json_error('Tài khoản này đang bị khóa, không thể thêm vào dự án.', 'VALIDATION', 422);
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtCheck->execute([':pid' => $projectId, ':uid' => $userId]);
        if ($stmtCheck->fetch()) {
            json_error('Tài khoản này đã ở trong dự án.', 'VALIDATION', 422);
        }

        $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $memParams = [
            ':id' => generate_uuid('mem'),
            ':pid' => $projectId,
            ':uid' => $userId,
            ':role' => $role,
            ':inv' => $session['userId'],
            ':cat' => $now
        ];
        $stmtInsert = $pdo->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, :role, :inv, :cat)");
        $stmtInsert->execute($memParams);
        mirror_to_sqlite("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, :role, :inv, :cat)", $memParams);

        $roleLabel = ($role === 'manager') ? 'Quản lý' : (($role === 'reviewer') ? 'Góp ý' : 'Chỉ xem');
        push_activity($pdo, $projectId, $session['userId'], 'member.invited', "đã thêm {$user['name']} vào dự án với vai trò $roleLabel");
        touch_project($pdo, $projectId, $now);

        $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $projStmt->execute([':pid' => $projectId]);
        $projName = $projStmt->fetchColumn() ?: 'dự án';

        create_notification(
            $pdo,
            $userId,
            'member_added',
            'Được thêm vào dự án',
            "Bạn đã được thêm vào dự án \"{$projName}\" với vai trò {$roleLabel}.",
            $projectId,
            null,
            null,
            null,
            "project.php?id={$projectId}",
            $session['userId']
        );

        $stmtMems = $pdo->prepare("SELECT u.id, u.name, u.username, pm.role, u.color, u.title, pm.id as \"membershipId\", pm.created_at as \"createdAt\" FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid");
        $stmtMems->execute([':pid' => $projectId]);
        json_response($stmtMems->fetchAll());
    }

    if ($action === 'update_role') {
        require_member($pdo, $projectId, ['manager']);
        $userId = trim($input['userId'] ?? $_POST['userId'] ?? '');
        $role = trim($input['role'] ?? $_POST['role'] ?? '');

        if ($role === 'commenter') {
            $role = 'reviewer';
        }
        if ($role === 'designer') {
            $role = 'manager';
        }

        if (!in_array($role, ['viewer', 'reviewer', 'manager'], true)) {
            json_error('Vai trò không hợp lệ. Vui lòng chọn Designer, Reviewer hoặc Viewer.', 'VALIDATION', 422);
        }

        $stmtCheck = $pdo->prepare("SELECT * FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtCheck->execute([':pid' => $projectId, ':uid' => $userId]);
        $member = $stmtCheck->fetch();
        if (!$member) {
            json_error('Không tìm thấy thành viên trong dự án.', 'NOT_FOUND', 404);
        }

        $stmtProj = $pdo->prepare("SELECT created_by FROM projects WHERE id = :pid");
        $stmtProj->execute([':pid' => $projectId]);
        $ownerId = $stmtProj->fetchColumn();
        if ($userId === $ownerId) {
            json_error('Không thể thay đổi vai trò của Chủ dự án. Chủ dự án mặc định luôn giữ vai trò Quản lý.', 'VALIDATION', 422);
        }

        if ($member['role'] === 'manager' && $role !== 'manager') {
            $stmtMgrs = $pdo->prepare("SELECT count(*) FROM project_members WHERE project_id = :pid AND role = 'manager'");
            $stmtMgrs->execute([':pid' => $projectId]);
            if ((int)$stmtMgrs->fetchColumn() <= 1) {
                json_error('Dự án cần ít nhất một Quản lý.', 'VALIDATION', 422);
            }
        }

        $updateParams = [':role' => $role, ':pid' => $projectId, ':uid' => $userId];
        $stmtUp = $pdo->prepare("UPDATE project_members SET role = :role WHERE project_id = :pid AND user_id = :uid");
        $stmtUp->execute($updateParams);
        mirror_to_sqlite("UPDATE project_members SET role = :role WHERE project_id = :pid AND user_id = :uid", $updateParams);

        $user = public_user($pdo, $userId);
        $roleLabel = ($role === 'manager') ? 'Quản lý' : (($role === 'reviewer') ? 'Góp ý' : 'Chỉ xem');
        push_activity($pdo, $projectId, $session['userId'], 'member.role', "đã đổi vai trò của {$user['name']} thành $roleLabel");
        touch_project($pdo, $projectId);

        $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $projStmt->execute([':pid' => $projectId]);
        $projName = $projStmt->fetchColumn() ?: 'dự án';

        // Gửi thông báo cho thành viên khi quyền bị chỉnh sửa
        create_notification(
            $pdo,
            $userId,
            'member_role_changed',
            'Thay đổi vai trò trong dự án',
            "Vai trò của bạn trong dự án \"{$projName}\" đã được chuyển thành: {$roleLabel}.",
            $projectId,
            null,
            null,
            null,
            "project.php?id={$projectId}",
            $session['userId']
        );

        $stmtMems = $pdo->prepare("SELECT u.id, u.name, u.username, pm.role, u.color, u.title, pm.id as \"membershipId\", pm.created_at as \"createdAt\" FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid");
        $stmtMems->execute([':pid' => $projectId]);
        json_response($stmtMems->fetchAll());
    }

    if ($action === 'remove_member') {
        require_member($pdo, $projectId, ['manager']);
        $userId = trim($input['userId'] ?? $_POST['userId'] ?? '');

        if (!$userId) {
            json_error('Vui lòng chọn thành viên cần xóa.', 'VALIDATION', 422);
        }

        $stmtCheck = $pdo->prepare("SELECT * FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtCheck->execute([':pid' => $projectId, ':uid' => $userId]);
        $member = $stmtCheck->fetch();
        if (!$member) {
            json_error('Không tìm thấy thành viên trong dự án.', 'NOT_FOUND', 404);
        }

        $stmtProj = $pdo->prepare("SELECT created_by FROM projects WHERE id = :pid");
        $stmtProj->execute([':pid' => $projectId]);
        $ownerId = $stmtProj->fetchColumn();
        if ($userId === $ownerId) {
            json_error('Không thể xóa Chủ dự án khỏi dự án.', 'VALIDATION', 422);
        }

        if ($member['role'] === 'manager') {
            $stmtMgrs = $pdo->prepare("SELECT count(*) FROM project_members WHERE project_id = :pid AND role = 'manager'");
            $stmtMgrs->execute([':pid' => $projectId]);
            if ((int)$stmtMgrs->fetchColumn() <= 1) {
                json_error('Không thể xóa Quản lý duy nhất của dự án.', 'VALIDATION', 422);
            }
        }

        $delParams = [':pid' => $projectId, ':uid' => $userId];
        $stmtDel = $pdo->prepare("DELETE FROM project_members WHERE project_id = :pid AND user_id = :uid");
        $stmtDel->execute($delParams);
        mirror_to_sqlite("DELETE FROM project_members WHERE project_id = :pid AND user_id = :uid", $delParams);

        $user = public_user($pdo, $userId);
        push_activity($pdo, $projectId, $session['userId'], 'member.removed', "đã xóa {$user['name']} khỏi dự án");
        touch_project($pdo, $projectId);

        $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $projStmt->execute([':pid' => $projectId]);
        $projName = $projStmt->fetchColumn() ?: 'dự án';

        // Gửi thông báo cho thành viên khi bị loại khỏi dự án
        create_notification(
            $pdo,
            $userId,
            'member_removed',
            'Đã rời khỏi dự án',
            "Bạn đã được quản trị viên đưa ra khỏi dự án \"{$projName}\".",
            $projectId,
            null,
            null,
            null,
            'projects.php',
            $session['userId']
        );

        $stmtMems = $pdo->prepare("SELECT u.id, u.name, u.username, pm.role, u.color, u.title, pm.id as \"membershipId\", pm.created_at as \"createdAt\" FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid");
        $stmtMems->execute([':pid' => $projectId]);
        json_response($stmtMems->fetchAll());
    }
}

json_error('Thao tác không hợp lệ.', 'NOT_FOUND', 404);
