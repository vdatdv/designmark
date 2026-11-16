<?php
// api/auth.php - Authentication & Account Management API

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper to pick random avatar color
function pick_avatar_color() {
    $colors = ['#4F46E5', '#0F766E', '#9A3412', '#1D4ED8', '#6D28D9', '#BE185D', '#065F46', '#4338CA'];
    return $colors[array_rand($colors)];
}

// 1. REGISTER
if ($action === 'register' && $method === 'POST') {
    $input = get_json_input();
    $username = trim($input['username'] ?? $_POST['username'] ?? '');
    $name = trim($input['name'] ?? $_POST['name'] ?? '');
    $email = trim($input['email'] ?? $_POST['email'] ?? '');
    $password = trim($input['password'] ?? $_POST['password'] ?? '');
    $confirmPassword = trim($input['confirm_password'] ?? $input['confirmPassword'] ?? $_POST['confirm_password'] ?? $_POST['confirmPassword'] ?? '');
    $title = trim($input['title'] ?? $_POST['title'] ?? '');
    if (!$title) {
        $title = 'Thành viên';
    }

    // Validations
    if (!$username) {
        json_error('Vui lòng nhập tên đăng nhập.', 'VALIDATION', 422);
    }
    if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,30}$/', $username)) {
        json_error('Tên đăng nhập từ 3 đến 30 ký tự, chỉ chứa chữ cái, số, gạch dưới hoặc gạch ngang.', 'VALIDATION', 422);
    }

    // Reserved usernames check
    $reservedUsernames = ['admin', 'administrator', 'system', 'root', 'support', 'help', 'api', 'guest', 'null', 'undefined', 'mod', 'moderator'];
    if (in_array(strtolower($username), $reservedUsernames, true)) {
        json_error('Tên đăng nhập này thuộc danh sách dành riêng cho hệ thống. Vui lòng chọn tên đăng nhập khác.', 'VALIDATION', 422);
    }

    if (!$name) {
        json_error('Vui lòng nhập họ và tên.', 'VALIDATION', 422);
    }
    if (mb_strlen($name, 'UTF-8') > 80) {
        json_error('Họ và tên tối đa 80 ký tự.', 'VALIDATION', 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Địa chỉ email không đúng định dạng.', 'VALIDATION', 422);
    }

    if (!$password || strlen($password) < 4) {
        json_error('Mật khẩu tối thiểu 4 ký tự.', 'VALIDATION', 422);
    }
    if ($confirmPassword !== '' && $password !== $confirmPassword) {
        json_error('Mật khẩu xác nhận không khớp.', 'VALIDATION', 422);
    }

    // Check if username already exists (case-insensitive)
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE lower(username) = lower(:uname)");
    $stmtCheck->execute([':uname' => $username]);
    if ($stmtCheck->fetch()) {
        json_error('Tên đăng nhập đã được sử dụng. Vui lòng chọn tên khác.', 'VALIDATION', 422);
    }

    if ($email !== '') {
        $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE lower(email) = lower(:email)");
        $stmtEmail->execute([':email' => $email]);
        if ($stmtEmail->fetch()) {
            json_error('Địa chỉ email này đã được sử dụng cho một tài khoản khác.', 'VALIDATION', 422);
        }
    }

    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $userId = generate_uuid('user');
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $color = pick_avatar_color();

    // Standard new user defaults:
    // system_role = 'user', status = 'active', is_verified = 1
    $systemRole = 'user';
    $status = 'active';
    $role = 'user';
    $isVerified = 1;

    $sqlIns = "INSERT INTO users (id, username, name, email, role, system_role, status, title, color, password_hash, is_verified, created_at) VALUES (:id, :uname, :name, :email, :role, :srole, :status, :title, :color, :phash, :is_ver, :cat)";
    $paramsIns = [
        ':id' => $userId,
        ':uname' => strtolower($username),
        ':name' => $name,
        ':email' => $email ?: null,
        ':role' => $role,
        ':srole' => $systemRole,
        ':status' => $status,
        ':title' => $title,
        ':color' => $color,
        ':phash' => $passwordHash,
        ':is_ver' => $isVerified,
        ':cat' => $now
    ];

    $stmtIns = $pdo->prepare($sqlIns);
    $stmtIns->execute($paramsIns);
    mirror_to_sqlite($sqlIns, $paramsIns);

    // Auto-login session upon successful registration
    $_SESSION['user_id'] = $userId;

    json_response([
        'success' => true,
        'userId' => $userId,
        'username' => strtolower($username),
        'name' => $name,
        'systemRole' => $systemRole,
        'status' => $status,
        'role' => $role,
        'message' => 'Đăng ký tài khoản thành công! Bạn có thể sử dụng DesignMark ngay bây giờ.'
    ], 201);
}

// 2. LOGIN
if ($action === 'login' && $method === 'POST') {
    $input = get_json_input();
    $username = trim($input['username'] ?? $_POST['username'] ?? '');
    $password = trim($input['password'] ?? $_POST['password'] ?? '');
    $remember = !empty($input['remember']) || !empty($_POST['remember']);

    if (!$username) {
        json_error('Vui lòng nhập tên đăng nhập.', 'VALIDATION', 422);
    }
    if (!$password) {
        json_error('Vui lòng nhập mật khẩu.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE lower(username) = lower(:uname) OR (email IS NOT NULL AND lower(email) = lower(:uname))");
    $stmt->execute([':uname' => $username]);
    $user = $stmt->fetch();

    $validPassword = false;
    if ($user && !empty($user['password_hash'])) {
        $validPassword = password_verify($password, $user['password_hash']);
    }

    // Generic error on invalid credentials: do not reveal whether the username exists
    if (!$user || !$validPassword) {
        json_error('Tên đăng nhập hoặc mật khẩu không chính xác.', 'AUTH', 401);
    }

    // Check account status
    $status = $user['status'] ?? 'active';
    if ($status === 'locked') {
        json_error('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Quản trị viên để được hỗ trợ.', 'ACCOUNT_LOCKED', 403);
    }

    // Secure session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    $systemRole = $user['system_role'] ?? ($user['username'] === 'admin' ? 'admin' : 'user');

    json_response([
        'userId' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'systemRole' => $systemRole,
        'status' => $status,
        'role' => $user['role'] ?? 'user',
        'title' => $user['title'] ?? 'Thành viên (Chỉ xem)',
        'color' => $user['color'] ?? '#4F46E5',
        'isVerified' => (int)($user['is_verified'] ?? 1),
        'remember' => $remember
    ]);
}

// 3. LIST ALL USERS (Admin only)
if ($action === 'list_all_users' && $method === 'GET') {
    $session = require_admin($pdo);

    $isRawLegacy = empty($_GET['paginate'])
        && !isset($_GET['page'])
        && !isset($_GET['limit'])
        && !isset($_GET['tab'])
        && !isset($_GET['q'])
        && !isset($_GET['systemRole'])
        && !isset($_GET['system_role'])
        && !isset($_GET['projectScope'])
        && !isset($_GET['project_scope'])
        && !isset($_GET['projectRole'])
        && !isset($_GET['project_role'])
        && !isset($_GET['sort']);

    if ($isRawLegacy) {
        $stmt = $pdo->query("SELECT id, username, name, email, COALESCE(system_role, CASE WHEN username = 'admin' THEN 'admin' ELSE 'user' END) as \"systemRole\", COALESCE(status, 'active') as status, role, title, color, is_verified as \"isVerified\", created_at as \"createdAt\" FROM users ORDER BY CASE WHEN (system_role = 'admin' OR username = 'admin') THEN 0 ELSE 1 END, created_at DESC");
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(5, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $tab = trim($_GET['tab'] ?? 'all');
    $q = trim($_GET['q'] ?? '');
    $systemRole = trim($_GET['systemRole'] ?? $_GET['system_role'] ?? '');
    $projectScope = trim($_GET['projectScope'] ?? $_GET['project_scope'] ?? '');
    $projectRole = trim($_GET['projectRole'] ?? $_GET['project_role'] ?? '');
    $sort = trim($_GET['sort'] ?? 'newest');

    // Live counts for the 4 stat cards
    $totalCount = (int)$pdo->query("SELECT count(*) FROM users")->fetchColumn();
    $activeCount = (int)$pdo->query("SELECT count(*) FROM users WHERE COALESCE(status, 'active') = 'active'")->fetchColumn();
    $lockedCount = (int)$pdo->query("SELECT count(*) FROM users WHERE status = 'locked'")->fetchColumn();
    $adminCount = (int)$pdo->query("SELECT count(*) FROM users WHERE (system_role = 'admin' OR username = 'admin')")->fetchColumn();

    $where = [];
    $params = [];

    // Tab filter
    if ($tab === 'active') {
        $where[] = "COALESCE(u.status, 'active') = 'active'";
    } elseif ($tab === 'locked') {
        $where[] = "u.status = 'locked'";
    }

    // Search filter (name, username, email)
    if ($q !== '') {
        $where[] = "(lower(u.name) LIKE :q OR lower(u.username) LIKE :q OR lower(COALESCE(u.email, '')) LIKE :q)";
        $params[':q'] = '%' . strtolower($q) . '%';
    }

    // System Role filter
    if ($systemRole === 'admin') {
        $where[] = "(u.system_role = 'admin' OR u.username = 'admin')";
    } elseif ($systemRole === 'user') {
        $where[] = "(COALESCE(u.system_role, 'user') = 'user' AND u.username != 'admin')";
    }

    // Project Scope filter
    if ($projectScope === 'owned') {
        $where[] = "(SELECT count(*) FROM projects p WHERE p.created_by = u.id AND p.deleted_at IS NULL) > 0";
    } elseif ($projectScope === 'joined') {
        $where[] = "(SELECT count(DISTINCT pm.project_id) FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND p.created_by != u.id AND p.deleted_at IS NULL) > 0";
    } elseif ($projectScope === 'none') {
        $where[] = "(SELECT count(*) FROM projects p WHERE p.created_by = u.id AND p.deleted_at IS NULL) = 0 AND (SELECT count(*) FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND p.deleted_at IS NULL) = 0";
    }

    // Project Role filter
    if ($projectRole === 'owner') {
        $where[] = "((SELECT count(*) FROM projects p WHERE p.created_by = u.id AND p.deleted_at IS NULL) > 0 OR EXISTS (SELECT 1 FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND pm.role = 'owner' AND p.deleted_at IS NULL))";
    } elseif ($projectRole === 'designer') {
        $where[] = "EXISTS (SELECT 1 FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND pm.role IN ('designer', 'manager') AND p.deleted_at IS NULL)";
    } elseif ($projectRole === 'reviewer') {
        $where[] = "EXISTS (SELECT 1 FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND pm.role IN ('reviewer', 'commenter') AND p.deleted_at IS NULL)";
    } elseif ($projectRole === 'viewer') {
        $where[] = "EXISTS (SELECT 1 FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND pm.role = 'viewer' AND p.deleted_at IS NULL)";
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count filtered records
    $stmtCount = $pdo->prepare("SELECT count(*) FROM users u $whereSql");
    $stmtCount->execute($params);
    $totalFiltered = (int)$stmtCount->fetchColumn();

    // Sorting (Always prioritize Admin at the top)
    $adminPrioritySql = "CASE WHEN (u.system_role = 'admin' OR u.username = 'admin') THEN 0 ELSE 1 END";
    $orderSql = "ORDER BY $adminPrioritySql, u.created_at DESC";
    if ($sort === 'oldest') {
        $orderSql = "ORDER BY $adminPrioritySql, u.created_at ASC";
    } elseif ($sort === 'name_asc') {
        $orderSql = "ORDER BY $adminPrioritySql, u.name ASC";
    } elseif ($sort === 'name_desc') {
        $orderSql = "ORDER BY $adminPrioritySql, u.name DESC";
    }

    // Query paginated users with project counts
    $sql = "
        SELECT 
            u.id, 
            u.username, 
            u.name, 
            u.email,
            COALESCE(u.system_role, CASE WHEN u.username = 'admin' THEN 'admin' ELSE 'user' END) as \"systemRole\", 
            COALESCE(u.status, 'active') as status, 
            u.role, 
            u.title, 
            u.color, 
            u.is_verified as \"isVerified\", 
            u.created_at as \"createdAt\",
            (SELECT count(*) FROM projects p WHERE p.created_by = u.id AND p.deleted_at IS NULL) as \"ownedProjectsCount\",
            (SELECT count(DISTINCT pm.project_id) FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE pm.user_id = u.id AND p.created_by != u.id AND p.deleted_at IS NULL) as \"joinedProjectsCount\"
        FROM users u
        $whereSql
        $orderSql
        LIMIT :limit OFFSET :offset
    ";

    $stmtUsers = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmtUsers->bindValue($k, $v);
    }
    $stmtUsers->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtUsers->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtUsers->execute();
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = (int)ceil($totalFiltered / $limit);
    if ($totalPages < 1) $totalPages = 1;

    json_response([
        'users' => $users,
        'stats' => [
            'total' => $totalCount,
            'active' => $activeCount,
            'locked' => $lockedCount,
            'admins' => $adminCount
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalFiltered,
            'totalPages' => $totalPages
        ]
    ]);
}

// 4. LOCK USER (Admin only)
if ($action === 'lock_user' && $method === 'POST') {
    $session = require_admin($pdo);
    $input = get_json_input();
    $targetId = trim($input['userId'] ?? $input['id'] ?? $_POST['userId'] ?? '');
    $reason = trim($input['reason'] ?? $_POST['reason'] ?? 'Bị khóa bởi Quản trị viên');

    if (!$targetId) {
        json_error('Vui lòng cung cấp ID tài khoản cần khóa.', 'VALIDATION', 422);
    }

    // Cannot lock self
    if ($targetId === $session['userId']) {
        json_error('Bạn không thể tự khóa tài khoản của chính mình.', 'VALIDATION', 422);
    }

    $stmtCheck = $pdo->prepare("SELECT id, username, name, system_role, status FROM users WHERE id = :id");
    $stmtCheck->execute([':id' => $targetId]);
    $target = $stmtCheck->fetch();

    if (!$target) {
        json_error('Không tìm thấy tài khoản người dùng.', 'NOT_FOUND', 404);
    }

    // Cannot lock the last active Admin
    $targetSystemRole = $target['system_role'] ?? ($target['username'] === 'admin' ? 'admin' : 'user');
    if ($targetSystemRole === 'admin') {
        $stmtCount = $pdo->query("SELECT count(*) FROM users WHERE (system_role = 'admin' OR username = 'admin') AND COALESCE(status, 'active') = 'active'");
        $activeAdmins = (int)$stmtCount->fetchColumn();
        if ($activeAdmins <= 1 && ($target['status'] ?? 'active') === 'active') {
            json_error('Không thể khóa Quản trị viên cuối cùng còn hoạt động trong hệ thống.', 'VALIDATION', 422);
        }
    }

    $stmtUp = $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = :id");
    $stmtUp->execute([':id' => $target['id']]);
    mirror_to_sqlite("UPDATE users SET status = 'locked' WHERE id = :id", [':id' => $target['id']]);

    // Audit log
    $historyId = generate_uuid('ush');
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $sqlHist = "INSERT INTO user_status_history (id, user_id, action, reason, admin_id, created_at) VALUES (:id, :uid, 'lock', :reason, :by, :cat)";
    $paramsHist = [
        ':id' => $historyId,
        ':uid' => $target['id'],
        ':reason' => $reason,
        ':by' => $session['userId'],
        ':cat' => $now
    ];
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute($paramsHist);
    mirror_to_sqlite($sqlHist, $paramsHist);

    json_response([
        'success' => true,
        'message' => "Đã khóa tài khoản {$target['name']} (@{$target['username']}) thành công.",
        'userId' => $target['id'],
        'status' => 'locked'
    ]);
}

// 5. UNLOCK USER (Admin only)
if ($action === 'unlock_user' && $method === 'POST') {
    $session = require_admin($pdo);
    $input = get_json_input();
    $targetId = trim($input['userId'] ?? $input['id'] ?? $_POST['userId'] ?? '');
    $reason = trim($input['reason'] ?? $_POST['reason'] ?? 'Mở khóa bởi Quản trị viên');

    if (!$targetId) {
        json_error('Vui lòng cung cấp ID tài khoản cần mở khóa.', 'VALIDATION', 422);
    }

    $stmtCheck = $pdo->prepare("SELECT id, username, name, status FROM users WHERE id = :id");
    $stmtCheck->execute([':id' => $targetId]);
    $target = $stmtCheck->fetch();

    if (!$target) {
        json_error('Không tìm thấy tài khoản người dùng.', 'NOT_FOUND', 404);
    }

    $stmtUp = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id");
    $stmtUp->execute([':id' => $target['id']]);
    mirror_to_sqlite("UPDATE users SET status = 'active' WHERE id = :id", [':id' => $target['id']]);

    // Audit log
    $historyId = generate_uuid('ush');
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $sqlHist = "INSERT INTO user_status_history (id, user_id, action, reason, admin_id, created_at) VALUES (:id, :uid, 'unlock', :reason, :by, :cat)";
    $paramsHist = [
        ':id' => $historyId,
        ':uid' => $target['id'],
        ':reason' => $reason,
        ':by' => $session['userId'],
        ':cat' => $now
    ];
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute($paramsHist);
    mirror_to_sqlite($sqlHist, $paramsHist);

    json_response([
        'success' => true,
        'message' => "Đã mở khóa tài khoản {$target['name']} (@{$target['username']}) thành công.",
        'userId' => $target['id'],
        'status' => 'active'
    ]);
}

// 6. USER STATUS HISTORY (Admin only)
if ($action === 'user_status_history' && $method === 'GET') {
    $session = require_admin($pdo);
    $targetId = trim($_GET['userId'] ?? $_GET['id'] ?? '');

    if (!$targetId) {
        json_error('Vui lòng cung cấp ID tài khoản để xem lịch sử.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT h.id, h.user_id as \"userId\", h.action, h.reason, h.admin_id as \"performedBy\", h.created_at as \"createdAt\", u.name as \"performedByName\", u.username as \"performedByUsername\" FROM user_status_history h LEFT JOIN users u ON u.id = h.admin_id WHERE h.user_id = :uid ORDER BY h.created_at DESC");
    $stmt->execute([':uid' => $targetId]);
    json_response($stmt->fetchAll());
}

// 7. RESET USER PASSWORD (Admin only)
if ($action === 'reset_user_password' && $method === 'POST') {
    $session = require_admin($pdo);
    $input = get_json_input();
    $targetId = trim($input['userId'] ?? $input['id'] ?? '');
    $newPassword = trim($input['newPassword'] ?? $input['password'] ?? '');

    if (!$targetId) {
        json_error('Vui lòng cung cấp ID tài khoản.', 'VALIDATION', 422);
    }

    if (!$newPassword || strlen($newPassword) < 4) {
        json_error('Mật khẩu mới tối thiểu 4 ký tự.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT id, name, username FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        json_error('Không tìm thấy tài khoản người dùng.', 'NOT_FOUND', 404);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmtUp = $pdo->prepare("UPDATE users SET password_hash = :phash WHERE id = :id");
    $stmtUp->execute([':phash' => $passwordHash, ':id' => $targetId]);
    mirror_to_sqlite("UPDATE users SET password_hash = :phash WHERE id = :id", [':phash' => $passwordHash, ':id' => $targetId]);

    // Audit log
    $historyId = generate_uuid('ush');
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $sqlHist = "INSERT INTO user_status_history (id, user_id, action, reason, admin_id, created_at) VALUES (:id, :uid, 'reset_password', :reason, :by, :cat)";
    $paramsHist = [
        ':id' => $historyId,
        ':uid' => $targetId,
        ':reason' => 'Quản trị viên đặt lại mật khẩu',
        ':by' => $session['userId'],
        ':cat' => $now
    ];
    $pdo->prepare($sqlHist)->execute($paramsHist);
    mirror_to_sqlite($sqlHist, $paramsHist);

    json_response([
        'success' => true,
        'message' => "Đã đặt lại mật khẩu cho tài khoản {$target['name']} (@{$target['username']}) thành công."
    ]);
}

// 7b. UPDATE USER PROFILE & ROLE (Admin only)
if ($action === 'update_user' && $method === 'POST') {
    $session = require_admin($pdo);
    $input = get_json_input();
    $targetId = trim($input['userId'] ?? $input['id'] ?? '');
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $title = trim($input['title'] ?? '');
    $role = trim($input['role'] ?? '');
    $systemRole = trim($input['systemRole'] ?? $input['system_role'] ?? '');

    if (!$targetId) {
        json_error('Thiếu mã tài khoản cần cập nhật.', 'VALIDATION', 422);
    }
    if (!$name) {
        json_error('Họ và tên không được để trống.', 'VALIDATION', 422);
    }
    if (mb_strlen($name, 'UTF-8') > 80) {
        json_error('Họ và tên tối đa 80 ký tự.', 'VALIDATION', 422);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Địa chỉ email không đúng định dạng.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT id, name, username, email, system_role, role, title, status FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        json_error('Không tìm thấy tài khoản người dùng.', 'NOT_FOUND', 404);
    }

    $currentSystemRole = $target['system_role'] ?? ($target['username'] === 'admin' ? 'admin' : 'user');

    // Safety: Prevent removing admin role from the last active admin
    if ($currentSystemRole === 'admin' && $systemRole !== '' && $systemRole !== 'admin') {
        $countStmt = $pdo->query("SELECT count(*) FROM users WHERE (system_role = 'admin' OR username = 'admin') AND COALESCE(status, 'active') = 'active'");
        $activeAdminCount = (int)$countStmt->fetchColumn();
        if ($activeAdminCount <= 1) {
            json_error('Không thể giáng cấp Quản trị viên cuối cùng còn hoạt động trong hệ thống.', 'OPERATION_DENIED', 403);
        }
    }

    if (!$role) {
        if ($title === 'Quản lý thiết kế') $role = 'manager';
        else if ($title === 'Người góp ý') $role = 'reviewer';
        else if ($title === 'Thành viên (Chỉ xem)') $role = 'viewer';
        else $role = $target['role'] ?: 'user';
    }

    if (!$systemRole) {
        $systemRole = $currentSystemRole;
    }

    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

    // Audit log if systemRole changed
    if ($systemRole !== $currentSystemRole) {
        $histId = generate_uuid('ush');
        $actionType = ($systemRole === 'admin') ? 'grant_admin' : 'revoke_admin';
        $reasonText = ($systemRole === 'admin') ? 'Cấp quyền Quản trị viên (Admin)' : 'Chuyển về quyền Người dùng (User)';
        $sqlHist = "INSERT INTO user_status_history (id, user_id, action, reason, admin_id, created_at) VALUES (:id, :uid, :act, :reason, :by, :cat)";
        $paramsHist = [
            ':id' => $histId,
            ':uid' => $targetId,
            ':act' => $actionType,
            ':reason' => $reasonText,
            ':by' => $session['userId'],
            ':cat' => $now
        ];
        $pdo->prepare($sqlHist)->execute($paramsHist);
        mirror_to_sqlite($sqlHist, $paramsHist);
    }

    $params = [
        ':name' => $name,
        ':email' => $email ?: null,
        ':title' => $title,
        ':role' => $role,
        ':sys' => $systemRole,
        ':id' => $targetId
    ];

    $sql = "UPDATE users SET name = :name, email = :email, title = :title, role = :role, system_role = :sys WHERE id = :id";
    $pdo->prepare($sql)->execute($params);
    mirror_to_sqlite($sql, $params);

    json_response([
        'success' => true,
        'message' => "Đã cập nhật thông tin cho tài khoản {$name} (@{$target['username']}) thành công.",
        'user' => [
            'id' => $targetId,
            'username' => $target['username'],
            'name' => $name,
            'email' => $email,
            'title' => $title,
            'role' => $role,
            'systemRole' => $systemRole
        ]
    ]);
}

// 7c. ADMIN CREATE USER (Admin only)
if ($action === 'admin_create_user' && $method === 'POST') {
    $session = require_admin($pdo);
    $input = get_json_input();
    $username = trim($input['username'] ?? $_POST['username'] ?? '');
    $name = trim($input['name'] ?? $_POST['name'] ?? '');
    $email = trim($input['email'] ?? $_POST['email'] ?? '');
    $password = trim($input['password'] ?? $_POST['password'] ?? '');
    $systemRole = trim($input['systemRole'] ?? $input['system_role'] ?? 'user');

    if (!$username) {
        json_error('Vui lòng nhập tên đăng nhập.', 'VALIDATION', 422);
    }
    if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,30}$/', $username)) {
        json_error('Tên đăng nhập từ 3 đến 30 ký tự, chỉ chứa chữ cái, số, gạch dưới hoặc gạch ngang.', 'VALIDATION', 422);
    }

    $reservedUsernames = ['admin', 'administrator', 'system', 'root', 'support', 'help', 'api', 'guest', 'null', 'undefined', 'mod', 'moderator'];
    if (in_array(strtolower($username), $reservedUsernames, true)) {
        json_error('Tên đăng nhập này thuộc danh sách dành riêng cho hệ thống. Vui lòng chọn tên đăng nhập khác.', 'VALIDATION', 422);
    }

    if (!$name) {
        json_error('Vui lòng nhập họ và tên.', 'VALIDATION', 422);
    }
    if (mb_strlen($name, 'UTF-8') > 80) {
        json_error('Họ và tên tối đa 80 ký tự.', 'VALIDATION', 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Địa chỉ email không đúng định dạng.', 'VALIDATION', 422);
    }

    if (!$password || strlen($password) < 4) {
        json_error('Mật khẩu tối thiểu 4 ký tự.', 'VALIDATION', 422);
    }

    if (!in_array($systemRole, ['admin', 'user'], true)) {
        $systemRole = 'user';
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE lower(username) = lower(:uname)");
    $stmtCheck->execute([':uname' => $username]);
    if ($stmtCheck->fetch()) {
        json_error('Tên đăng nhập đã được sử dụng. Vui lòng chọn tên khác.', 'VALIDATION', 422);
    }

    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $userId = generate_uuid('user');
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $color = pick_avatar_color();
    $status = 'active';
    $isVerified = 1;
    $role = $systemRole;
    $title = ($systemRole === 'admin') ? 'Quản trị viên hệ thống' : 'Thành viên';

    $sqlIns = "INSERT INTO users (id, username, name, email, role, system_role, status, title, color, password_hash, is_verified, created_at) VALUES (:id, :uname, :name, :email, :role, :srole, :status, :title, :color, :phash, :is_ver, :cat)";
    $paramsIns = [
        ':id' => $userId,
        ':uname' => strtolower($username),
        ':name' => $name,
        ':email' => $email ?: null,
        ':role' => $role,
        ':srole' => $systemRole,
        ':status' => $status,
        ':title' => $title,
        ':color' => $color,
        ':phash' => $passwordHash,
        ':is_ver' => $isVerified,
        ':cat' => $now
    ];

    $stmtIns = $pdo->prepare($sqlIns);
    $stmtIns->execute($paramsIns);
    mirror_to_sqlite($sqlIns, $paramsIns);

    // Audit log
    $historyId = generate_uuid('ush');
    $sqlHist = "INSERT INTO user_status_history (id, user_id, action, reason, admin_id, created_at) VALUES (:id, :uid, 'create', :reason, :by, :cat)";
    $paramsHist = [
        ':id' => $historyId,
        ':uid' => $userId,
        ':reason' => 'Tài khoản được tạo bởi Quản trị viên',
        ':by' => $session['userId'],
        ':cat' => $now
    ];
    $pdo->prepare($sqlHist)->execute($paramsHist);
    mirror_to_sqlite($sqlHist, $paramsHist);

    json_response([
        'success' => true,
        'message' => "Đã tạo tài khoản {$name} (@{$username}) thành công.",
        'user' => [
            'id' => $userId,
            'username' => strtolower($username),
            'name' => $name,
            'email' => $email,
            'systemRole' => $systemRole,
            'status' => $status,
            'createdAt' => $now
        ]
    ], 201);
}

// 7d. USER DETAIL (Admin only)
if ($action === 'user_detail' && $method === 'GET') {
    $session = require_admin($pdo);
    $targetId = trim($_GET['userId'] ?? $_GET['id'] ?? '');
    if (!$targetId) {
        json_error('Vui lòng cung cấp ID tài khoản.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT id, username, name, email, COALESCE(system_role, CASE WHEN username = 'admin' THEN 'admin' ELSE 'user' END) as \"systemRole\", COALESCE(status, 'active') as status, color, is_verified as \"isVerified\", created_at as \"createdAt\" FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_error('Không tìm thấy tài khoản người dùng.', 'NOT_FOUND', 404);
    }

    // Projects list with roles
    $stmtProj = $pdo->prepare("
        SELECT 
            p.id as \"projectId\",
            p.name as \"projectName\",
            p.category as \"category\",
            p.created_by as \"createdBy\",
            p.created_at as \"projectCreatedAt\",
            p.deleted_at as \"deletedAt\",
            pm.role as \"memberRole\",
            COALESCE(pm.created_at, p.created_at) as \"joinedAt\"
        FROM projects p
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = :uid
        WHERE p.created_by = :uid OR pm.user_id = :uid
        ORDER BY COALESCE(pm.created_at, p.created_at) DESC
    ");
    $stmtProj->execute([':uid' => $targetId]);
    $rawProjects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

    $projects = [];
    $ownedCount = 0;
    $joinedCount = 0;

    foreach ($rawProjects as $rp) {
        $isOwner = ($rp['createdBy'] === $targetId);
        $roleKey = '';
        $roleLabel = '';

        if ($isOwner) {
            $roleKey = 'owner';
            $roleLabel = 'Owner';
            if (empty($rp['deletedAt'])) {
                $ownedCount++;
            }
        } else {
            $mRole = strtolower($rp['memberRole'] ?? 'viewer');
            if (in_array($mRole, ['designer', 'manager'], true)) {
                $roleKey = 'designer';
                $roleLabel = 'Designer';
            } elseif (in_array($mRole, ['reviewer', 'commenter'], true)) {
                $roleKey = 'reviewer';
                $roleLabel = 'Reviewer';
            } else {
                $roleKey = 'viewer';
                $roleLabel = 'Viewer';
            }
            if (empty($rp['deletedAt'])) {
                $joinedCount++;
            }
        }

        $projects[] = [
            'id' => $rp['projectId'],
            'name' => $rp['projectName'],
            'category' => $rp['category'] ?: 'Khác',
            'role' => $roleKey,
            'roleLabel' => $roleLabel,
            'isOwner' => $isOwner,
            'joinedAt' => $rp['joinedAt'],
            'status' => !empty($rp['deletedAt']) ? 'trashed' : 'active',
            'statusLabel' => !empty($rp['deletedAt']) ? 'Thùng rác' : 'Đang hoạt động'
        ];
    }

    json_response([
        'user' => $user,
        'stats' => [
            'ownedCount' => $ownedCount,
            'joinedCount' => $joinedCount,
            'totalAccessible' => $ownedCount + $joinedCount
        ],
        'projects' => $projects
    ]);
}

// 8. DISALLOW PERMANENT DELETION (Requirement: Không xây dựng chức năng xóa vĩnh viễn tài khoản)
if ($action === 'delete_user' && $method === 'POST') {
    json_error('Chức năng xóa vĩnh viễn tài khoản đã được thay thế bằng tính năng Khóa tài khoản an toàn.', 'FORBIDDEN', 403);
}

// 8b. CHANGE PASSWORD (Người dùng tự đổi mật khẩu của mình)
if ($action === 'change_password' && $method === 'POST') {
    $session = require_auth($pdo);
    $userId = $session['userId'];

    $input = get_json_input();
    $currentPassword = trim($input['currentPassword'] ?? $input['current_password'] ?? $_POST['currentPassword'] ?? $_POST['current_password'] ?? '');
    $newPassword = trim($input['newPassword'] ?? $input['new_password'] ?? $_POST['newPassword'] ?? $_POST['new_password'] ?? '');
    $confirmPassword = trim($input['confirmPassword'] ?? $input['confirm_password'] ?? $_POST['confirmPassword'] ?? $_POST['confirm_password'] ?? '');

    if (!$currentPassword) {
        json_error('Vui lòng nhập mật khẩu hiện tại.', 'VALIDATION', 422);
    }
    if (!$newPassword || strlen($newPassword) < 4) {
        json_error('Mật khẩu mới phải có tối thiểu 4 ký tự.', 'VALIDATION', 422);
    }
    if ($confirmPassword !== '' && $newPassword !== $confirmPassword) {
        json_error('Mật khẩu xác nhận không khớp.', 'VALIDATION', 422);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        json_error('Không tìm thấy tài khoản người dùng.', 'NOT_FOUND', 404);
    }

    // Verify current password
    $hash = $user['password_hash'] ?? '';
    $currentValid = false;
    if ($hash) {
        $currentValid = password_verify($currentPassword, $hash);
        if (!$currentValid && $hash === md5($currentPassword)) {
            $currentValid = true;
        }
    }
    // Fallback default password 'demo' for seed accounts
    if (!$currentValid && empty($hash) && $currentPassword === 'demo') {
        $currentValid = true;
    }

    if (!$currentValid) {
        json_error('Mật khẩu hiện tại không chính xác.', 'VALIDATION', 422);
    }

    if ($currentPassword === $newPassword) {
        json_error('Mật khẩu mới không được trùng với mật khẩu hiện tại.', 'VALIDATION', 422);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmtUp = $pdo->prepare("UPDATE users SET password_hash = :phash WHERE id = :id");
    $stmtUp->execute([':phash' => $newHash, ':id' => $userId]);
    mirror_to_sqlite("UPDATE users SET password_hash = :phash WHERE id = :id", [':phash' => $newHash, ':id' => $userId]);

    json_response([
        'success' => true,
        'message' => 'Đổi mật khẩu thành công!'
    ]);
}


// 9. SESSION
if ($action === 'session' || ($method === 'GET' && empty($action))) {
    $session = get_current_user_session($pdo);
    if (!$session) {
        json_response(null);
    }
    json_response($session);
}

// 10. LOGOUT
if ($action === 'logout' && $method === 'POST') {
    unset($_SESSION['user_id']);
    session_destroy();
    json_response(['success' => true]);
}

// 11. SWITCH ACCOUNT (Demo helper)
if ($action === 'switch' && $method === 'POST') {
    $input = get_json_input();
    $username = trim($input['username'] ?? $_POST['username'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM users WHERE lower(username) = lower(:uname) OR id = :id");
    $stmt->execute([':uname' => $username, ':id' => $username]);
    $user = $stmt->fetch();
    if ($user) {
        if (($user['status'] ?? 'active') === 'locked') {
            json_error('Tài khoản này đang bị khóa, không thể chuyển đổi.', 'ACCOUNT_LOCKED', 403);
        }
        $_SESSION['user_id'] = $user['id'];
        $systemRole = $user['system_role'] ?? ($user['username'] === 'admin' ? 'admin' : 'user');
        json_response([
            'userId' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'systemRole' => $systemRole,
            'status' => $user['status'] ?? 'active',
            'role' => $user['role'] ?? 'user',
            'title' => $user['title'] ?? 'Thành viên (Chỉ xem)',
            'color' => $user['color'] ?? '#4F46E5',
            'isVerified' => (int)($user['is_verified'] ?? 1)
        ]);
    }
    json_error('Không tìm thấy người dùng để chuyển đổi.', 'NOT_FOUND', 404);
}

json_error('Thao tác không hợp lệ.', 'NOT_FOUND', 404);
