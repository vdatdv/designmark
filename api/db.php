<?php
// api/db.php - Central Database connection & helpers (Supabase Postgres & SQLite)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// 1. Simple .env / .env.local loader
function load_env_file_custom($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
                putenv("$key=$val");
            }
        }
    }
}
load_env_file_custom(__DIR__ . '/../.env.local');
load_env_file_custom(__DIR__ . '/../.env');

// 2. Determine database configuration & Network Resiliency
$dbPath = $dataDir . '/database.sqlite';
$dbDriver = 'sqlite';
$pdo = null;

$databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? null);
$supabaseHost = getenv('SUPABASE_DB_HOST') ?: ($_ENV['SUPABASE_DB_HOST'] ?? 'aws-0-ap-southeast-1.pooler.supabase.com');
$supabaseUser = getenv('SUPABASE_DB_USER') ?: ($_ENV['SUPABASE_DB_USER'] ?? 'postgres.qpdsjaxilorovzboexrq');
$supabasePass = getenv('SUPABASE_DB_PASS') ?: ($_ENV['SUPABASE_DB_PASS'] ?? '@Vinhdat123');
$supabasePort = getenv('SUPABASE_DB_PORT') ?: ($_ENV['SUPABASE_DB_PORT'] ?? 5432);
$supabaseName = getenv('SUPABASE_DB_NAME') ?: ($_ENV['SUPABASE_DB_NAME'] ?? 'postgres');

// Check if user requested local database priority (Luôn ưu tiên CSDL Cục bộ)
$preferLocalHeader = $_SERVER['HTTP_X_PREFER_LOCAL'] ?? '';
$preferLocalParam = $_GET['prefer_local'] ?? '';
$preferLocalCookie = $_COOKIE['prefer_local'] ?? '';
$envPreferLocal = getenv('DB_PREFER_LOCAL') ?: ($_ENV['DB_PREFER_LOCAL'] ?? '0');
$preferLocal = ($preferLocalHeader === '1' || $preferLocalParam === '1' || $preferLocalCookie === '1' || $envPreferLocal === '1');

// Circuit Breaker: Avoid blocking requests if Supabase was recently unreachable
$breakerFile = $dataDir . '/.supabase_breaker';
$isBreakerActive = false;
if (file_exists($breakerFile)) {
    $lastFailed = (int)@file_get_contents($breakerFile);
    if ((time() - $lastFailed) < 60) {
        $isBreakerActive = true;
    } else {
        @unlink($breakerFile);
    }
}

// Kết nối Supabase PostgreSQL Cloud khi không ép buộc CSDL cục bộ và breaker không kích hoạt
if (!$preferLocal && !$isBreakerActive && $supabaseHost && $supabaseUser && in_array('pgsql', PDO::getAvailableDrivers())) {
    try {
        // 10-second timeout: nếu quá 10s thì tự động chuyển sang đọc SQLite cục bộ
        $pgDsn = "pgsql:host={$supabaseHost};port={$supabasePort};dbname={$supabaseName};sslmode=require";
        $pdo = new PDO($pgDsn, $supabaseUser, $supabasePass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10
        ]);
        $dbDriver = 'pgsql';
    } catch (Throwable $e) {
        // Tự động ngắt kết nối và đóng circuit breaker 60s để ưu tiên CSDL cục bộ SQLite siêu tốc
        @file_put_contents($breakerFile, (string)time());
        $isBreakerActive = true;
        error_log("Supabase connection timeout/error (>10s: " . $e->getMessage() . "). Tripped circuit breaker. Auto-switching to local SQLite.");
        $pdo = null;
    }
}

// Fallback to local SQLite when offline, circuit breaker tripped, or prefer_local is requested
if (!$pdo) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $dbDriver = 'sqlite';
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Không thể kết nối cơ sở dữ liệu.', 'code' => 'DB_ERROR']);
        exit;
    }
}

// Send diagnostic headers
if (!headers_sent()) {
    header("X-DB-Engine: " . ($dbDriver === 'sqlite' ? 'SQLite (Fast-Local / Poor Network Mode)' : 'PostgreSQL (Supabase Live)'));
    header("X-DB-Breaker: " . ($isBreakerActive ? 'tripped' : 'inactive'));
    header("X-DB-Prefer-Local: " . ($preferLocal ? 'true' : 'false'));
}

// Helper to mirror writes to local SQLite when operating in Supabase mode
function mirror_to_sqlite($sql, $params = []) {
    global $dataDir, $dbDriver;
    if ($dbDriver !== 'pgsql') return;
    try {
        static $localPdo = null;
        if ($localPdo === null) {
            $localPdo = new PDO('sqlite:' . $dataDir . '/database.sqlite');
            $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $localPdo->exec("PRAGMA foreign_keys = ON;");
        }
        $stmt = $localPdo->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable $e) {
        // Non-blocking mirror
    }
}

// 3. Ensure database schema tables exist (Compatible with PostgreSQL and SQLite)
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL,
    title TEXT,
    color TEXT,
    password_hash TEXT,
    is_verified INTEGER DEFAULT 1,
    verification_code TEXT,
    verified_at TEXT,
    created_at TEXT
);

CREATE TABLE IF NOT EXISTS projects (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    category TEXT DEFAULT 'Khác',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_members (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role TEXT NOT NULL,
    invited_by TEXT,
    created_at TEXT NOT NULL,
    UNIQUE(project_id, user_id)
);

CREATE TABLE IF NOT EXISTS design_screens (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS image_versions (
    id TEXT PRIMARY KEY,
    screen_id TEXT NOT NULL REFERENCES design_screens(id) ON DELETE CASCADE,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    number INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    image_key TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    source TEXT NOT NULL,
    created_at TEXT NOT NULL,
    created_by TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS annotations (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    screen_id TEXT NOT NULL REFERENCES design_screens(id) ON DELETE CASCADE,
    image_version_id TEXT NOT NULL REFERENCES image_versions(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    x REAL NOT NULL,
    y REAL NOT NULL,
    w REAL NOT NULL,
    h REAL NOT NULL,
    original_width INTEGER NOT NULL,
    original_height INTEGER NOT NULL,
    author_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    revision INTEGER NOT NULL DEFAULT 1,
    check_note TEXT DEFAULT '',
    drawing_data TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS annotation_replies (
    id TEXT PRIMARY KEY,
    annotation_id TEXT NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    author_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    attachment_url TEXT DEFAULT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS annotation_status_events (
    id TEXT PRIMARY KEY,
    annotation_id TEXT NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    status TEXT NOT NULL,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    note TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS activities (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    actor_id TEXT REFERENCES users(id) ON DELETE SET NULL,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    project_id TEXT REFERENCES projects(id) ON DELETE CASCADE,
    screen_id TEXT REFERENCES design_screens(id) ON DELETE SET NULL,
    image_version_id TEXT REFERENCES image_versions(id) ON DELETE SET NULL,
    annotation_id TEXT REFERENCES annotations(id) ON DELETE CASCADE,
    link TEXT,
    is_read INTEGER DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_created ON notifications(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notifications_unread ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_project ON notifications(project_id);

CREATE TABLE IF NOT EXISTS comment_reactions (
    id TEXT PRIMARY KEY,
    annotation_id TEXT NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    reply_id TEXT DEFAULT NULL,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    emoji VARCHAR(32) NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_reactions_ann ON comment_reactions(annotation_id);
CREATE INDEX IF NOT EXISTS idx_reactions_reply ON comment_reactions(reply_id);
CREATE INDEX IF NOT EXISTS idx_reactions_user ON comment_reactions(user_id);
");

// Schema migration checks for SQLite if running SQLite driver
if ($dbDriver === 'sqlite') {
    $userCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('password_hash', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_hash TEXT");
    }
    if (!in_array('is_verified', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified INTEGER DEFAULT 1");
    }
    if (!in_array('verification_code', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verification_code TEXT");
    }
    if (!in_array('verified_at', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verified_at TEXT");
    }
    if (!in_array('created_at', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN created_at TEXT");
    }
    $annCols = $pdo->query("PRAGMA table_info(annotations)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('number', $annCols, true)) {
        $pdo->exec("ALTER TABLE annotations ADD COLUMN number INTEGER");
    }
}

// Ensure trash bin and category columns exist in projects table
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN deleted_at TEXT DEFAULT NULL;");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN deleted_by TEXT DEFAULT NULL;");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN category TEXT DEFAULT 'Khác';");
} catch (Throwable $e) {}
try {
    mirror_to_sqlite("ALTER TABLE projects ADD COLUMN deleted_at TEXT DEFAULT NULL;");
    mirror_to_sqlite("ALTER TABLE projects ADD COLUMN deleted_by TEXT DEFAULT NULL;");
    mirror_to_sqlite("ALTER TABLE projects ADD COLUMN category TEXT DEFAULT 'Khác';");
} catch (Throwable $e) {}

// 4. Auto-seed if database is empty
$count = (int)$pdo->query("SELECT count(*) FROM users")->fetchColumn();
if ($count === 0) {
    require_once __DIR__ . '/seed.php';
    seed_database($pdo);
}

// Utilities & Helper functions
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error($message, $code = 'ERROR', $statusCode = 400, $extra = []) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $res = array_merge(['error' => $message, 'code' => $code], $extra);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_json_input() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function generate_uuid($prefix = '') {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    return $prefix ? ($prefix . '-' . $uuid) : $uuid;
}

function get_current_user_session(PDO $pdo) {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        $authHeader = $_SERVER['HTTP_X_USER_ID'] ?? null;
        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['X-User-Id'] ?? $headers['x-user-id'] ?? null;
        }
        if ($authHeader) {
            $userId = $authHeader;
        }
    }
    if (!$userId) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$auth && function_exists('getallheaders')) {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (str_starts_with($auth, 'Bearer ')) {
            $token = trim(substr($auth, 7));
            if ($token && $token === session_id() && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
        }
    }
    if (!$userId) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id OR username = :uname");
    $stmt->execute([':id' => $userId, ':uname' => $userId]);
    $user = $stmt->fetch();
    if (!$user) return null;

    // Check account status: locked accounts are denied immediately and session is invalidated
    $status = $user['status'] ?? 'active';
    if ($status === 'locked') {
        if (isset($_SESSION['user_id'])) {
            unset($_SESSION['user_id']);
        }
        return null;
    }

    if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) return null;
    if ($user['role'] === 'pending' && $status === 'pending') return null;

    $systemRole = $user['system_role'] ?? ($user['role'] === 'admin' ? 'admin' : 'user');

    return [
        'userId' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'systemRole' => $systemRole,
        'status' => $status,
        'role' => ($systemRole === 'admin') ? 'admin' : ($user['role'] ?? 'user'),
        'title' => $user['title'],
        'color' => $user['color'],
        'isVerified' => (int)($user['is_verified'] ?? 1),
        'remember' => true,
        'sessionToken' => session_id() ?: ('tok_' . md5($user['id'] . microtime()))
    ];
}

function require_auth(PDO $pdo) {
    $user = get_current_user_session($pdo);
    if (!$user) {
        json_error('Phiên đăng nhập đã hết hạn hoặc tài khoản đã bị khóa. Hãy đăng nhập lại.', 'UNAUTHORIZED', 401);
    }
    return $user;
}

function require_admin(PDO $pdo) {
    $session = require_auth($pdo);
    if (($session['systemRole'] ?? '') !== 'admin') {
        json_error('Chỉ Quản trị viên hệ thống mới có quyền thực hiện thao tác này.', 'FORBIDDEN', 403);
    }
    return $session;
}

function require_member(PDO $pdo, $projectId, $allowedRoles = null) {
    $session = require_auth($pdo);

    // Check project creator
    $stmtOwner = $pdo->prepare("SELECT created_by FROM projects WHERE id = :pid");
    $stmtOwner->execute([':pid' => $projectId]);
    $createdBy = $stmtOwner->fetchColumn();
    $isOwner = ($createdBy && $createdBy === $session['userId']);

    $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = :pid AND user_id = :uid");
    $stmt->execute([':pid' => $projectId, ':uid' => $session['userId']]);
    $role = $stmt->fetchColumn();

    if (!$role) {
        if ($isOwner) {
            $role = 'manager';
        } else if (($session['systemRole'] ?? '') === 'admin' && $allowedRoles === null) {
            return ['session' => $session, 'role' => 'admin_observer'];
        } else {
            json_error('Bạn không thuộc dự án này.', 'FORBIDDEN', 403);
        }
    }

    if ($allowedRoles) {
        $effectiveAllowed = [];
        foreach ($allowedRoles as $r) {
            $effectiveAllowed[] = $r;
            if ($r === 'commenter') $effectiveAllowed[] = 'reviewer';
            if ($r === 'reviewer') $effectiveAllowed[] = 'commenter';
            if ($r === 'manager') { $effectiveAllowed[] = 'designer'; $effectiveAllowed[] = 'owner'; }
            if ($r === 'designer') { $effectiveAllowed[] = 'manager'; $effectiveAllowed[] = 'owner'; }
            if ($r === 'owner') { $effectiveAllowed[] = 'manager'; $effectiveAllowed[] = 'designer'; }
        }

        $userRoles = [$role];
        if ($isOwner) {
            $userRoles[] = 'owner';
            $userRoles[] = 'manager';
        }

        $hasPermission = false;
        foreach ($userRoles as $ur) {
            if (in_array($ur, $effectiveAllowed, true)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            json_error('Bạn không có quyền thực hiện thao tác này.', 'FORBIDDEN', 403);
        }
    }

    return ['session' => $session, 'role' => $role];
}

function public_user(PDO $pdo, $userId) {
    static $userCache = [];
    if (!$userId) {
        return ['id' => '', 'name' => 'Hệ thống', 'username' => 'system', 'role' => '', 'color' => '#64748B', 'title' => '', 'isVerified' => 1];
    }
    if (isset($userCache[$userId])) {
        return $userCache[$userId];
    }
    $stmt = $pdo->prepare("SELECT id, name, username, email, system_role, status, role, color, title, is_verified FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        $res = ['id' => $userId, 'name' => 'Người dùng đã xóa', 'username' => '', 'email' => '', 'role' => '', 'color' => '#64748B', 'title' => '', 'isVerified' => 1];
    } else {
        $user['isVerified'] = (int)($user['is_verified'] ?? 1);
        $user['systemRole'] = $user['system_role'] ?? 'user';
        $user['status'] = $user['status'] ?? 'active';
        $user['email'] = $user['email'] ?? '';
        unset($user['is_verified']);
        $res = $user;
    }
    $userCache[$userId] = $res;
    return $res;
}

function touch_project(PDO $pdo, $projectId, $iso = null) {
    $now = $iso ?: (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $stmt = $pdo->prepare("UPDATE projects SET updated_at = :now WHERE id = :id");
    $stmt->execute([':now' => $now, ':id' => $projectId]);
}

function push_activity(PDO $pdo, $projectId, $userId, $type, $message, $link = null, $annotationId = null) {
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $id = generate_uuid('act');
    $stmt = $pdo->prepare("INSERT INTO activities (id, project_id, user_id, type, message, created_at, link, annotation_id) VALUES (:id, :project_id, :user_id, :type, :message, :created_at, :link, :annotation_id)");
    $stmt->execute([
        ':id' => $id,
        ':project_id' => $projectId,
        ':user_id' => $userId,
        ':type' => $type,
        ':message' => $message,
        ':created_at' => $now,
        ':link' => $link,
        ':annotation_id' => $annotationId
    ]);
    mirror_to_sqlite("INSERT INTO activities (id, project_id, user_id, type, message, created_at, link, annotation_id) VALUES (:id, :project_id, :user_id, :type, :message, :created_at, :link, :annotation_id)", [
        ':id' => $id,
        ':project_id' => $projectId,
        ':user_id' => $userId,
        ':type' => $type,
        ':message' => $message,
        ':created_at' => $now,
        ':link' => $link,
        ':annotation_id' => $annotationId
    ]);
}

function create_notification(
    PDO $pdo,
    $userId,
    $type,
    $title,
    $content,
    $projectId = null,
    $screenId = null,
    $imageVersionId = null,
    $annotationId = null,
    $link = null,
    $actorId = null
) {
    if (!$userId) return null;
    if ($actorId && $userId === $actorId) return null;

    // Prevent rapid duplicate spam (within last 10 seconds with exact same content)
    $tenSecsAgo = (new DateTime('-10 seconds', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = :uid AND type = :t AND COALESCE(annotation_id, '') = :aid AND COALESCE(project_id, '') = :pid AND content = :content AND created_at >= :recent LIMIT 1");
    $checkStmt->execute([
        ':uid' => $userId,
        ':t' => $type,
        ':aid' => $annotationId ?: '',
        ':pid' => $projectId ?: '',
        ':content' => $content,
        ':recent' => $tenSecsAgo
    ]);
    if ($checkStmt->fetch()) {
        return null;
    }

    $id = generate_uuid('notif');
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

    $stmt = $pdo->prepare("INSERT INTO notifications (id, user_id, actor_id, type, title, content, project_id, screen_id, image_version_id, annotation_id, link, is_read, created_at) VALUES (:id, :uid, :actor, :type, :title, :content, :pid, :sid, :vid, :aid, :link, 0, :cat)");
    $params = [
        ':id' => $id,
        ':uid' => $userId,
        ':actor' => $actorId,
        ':type' => $type,
        ':title' => $title,
        ':content' => $content,
        ':pid' => $projectId,
        ':sid' => $screenId,
        ':vid' => $imageVersionId,
        ':aid' => $annotationId,
        ':link' => $link,
        ':cat' => $now
    ];
    $stmt->execute($params);

    mirror_to_sqlite(
        "INSERT INTO notifications (id, user_id, actor_id, type, title, content, project_id, screen_id, image_version_id, annotation_id, link, is_read, created_at) VALUES (:id, :uid, :actor, :type, :title, :content, :pid, :sid, :vid, :aid, :link, 0, :cat)",
        $params
    );

    return $id;
}

function notify_project_members(
    PDO $pdo,
    $projectId,
    $type,
    $title,
    $content,
    $screenId = null,
    $imageVersionId = null,
    $annotationId = null,
    $link = null,
    $actorId = null
) {
    if (!$projectId) return;
    $stmt = $pdo->prepare("
        SELECT DISTINCT u_id FROM (
            SELECT created_by as u_id FROM projects WHERE id = :pid
            UNION
            SELECT user_id as u_id FROM project_members WHERE project_id = :pid
        ) t WHERE u_id IS NOT NULL AND u_id != ''
    ");
    $stmt->execute([':pid' => $projectId]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($members as $uid) {
        if ($actorId && $uid === $actorId) continue;
        create_notification($pdo, $uid, $type, $title, $content, $projectId, $screenId, $imageVersionId, $annotationId, $link, $actorId);
    }
}

function notify_project_stakeholders(
    PDO $pdo,
    $projectId,
    $type,
    $title,
    $content,
    $screenId = null,
    $imageVersionId = null,
    $annotationId = null,
    $link = null,
    $actorId = null
) {
    if (!$projectId) return;

    // Lấy chủ dự án (created_by) và quản trị viên dự án (role = 'manager')
    $stmt = $pdo->prepare("
        SELECT DISTINCT u_id FROM (
            SELECT created_by as u_id FROM projects WHERE id = :pid
            UNION
            SELECT user_id as u_id FROM project_members WHERE project_id = :pid AND role = 'manager'
        ) t WHERE u_id IS NOT NULL AND u_id != ''
    ");
    $stmt->execute([':pid' => $projectId]);
    $stakeholders = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($stakeholders as $uid) {
        if ($actorId && $uid === $actorId) continue;
        create_notification($pdo, $uid, $type, $title, $content, $projectId, $screenId, $imageVersionId, $annotationId, $link, $actorId);
    }
}

function present_version(PDO $pdo, $ver) {
    if (!$ver) return null;
    return [
        'id' => $ver['id'],
        'screenId' => $ver['screen_id'],
        'projectId' => $ver['project_id'],
        'number' => (int)$ver['number'],
        'label' => 'v' . $ver['number'],
        'width' => (int)$ver['width'],
        'height' => (int)$ver['height'],
        'imageKey' => $ver['image_key'],
        'mimeType' => $ver['mime_type'],
        'source' => $ver['source'],
        'createdAt' => $ver['created_at'],
        'createdBy' => $ver['created_by'],
        'author' => public_user($pdo, $ver['created_by'])
    ];
}

function present_annotation(PDO $pdo, $ann, $currentUserId = null, $replyLimit = null) {
    if ($currentUserId === null) {
        $sess = get_current_user_session($pdo);
        if ($sess && isset($sess['userId'])) {
            $currentUserId = $sess['userId'];
        }
    }

    static $versionCache = [];
    static $screenCache = [];
    static $projectCache = [];

    // Persistent sequential number on version
    $number = (isset($ann['number']) && $ann['number'] !== null && (int)$ann['number'] > 0)
        ? (int)$ann['number']
        : null;

    if ($number === null) {
        $stmtSib = $pdo->prepare("SELECT id FROM annotations WHERE image_version_id = :vid ORDER BY created_at ASC, id ASC");
        $stmtSib->execute([':vid' => $ann['image_version_id']]);
        $siblings = $stmtSib->fetchAll(PDO::FETCH_COLUMN);
        $idx = array_search($ann['id'], $siblings);
        $number = ($idx !== false) ? ($idx + 1) : 1;
    }

    // Fetch version details (cached)
    $vid = $ann['image_version_id'];
    if (!isset($versionCache[$vid])) {
        $stmtVer = $pdo->prepare("SELECT * FROM image_versions WHERE id = :vid");
        $stmtVer->execute([':vid' => $vid]);
        $versionCache[$vid] = $stmtVer->fetch() ?: null;
    }
    $version = $versionCache[$vid];

    // Fetch screen details (cached)
    $sid = $ann['screen_id'];
    if (!isset($screenCache[$sid])) {
        $stmtScr = $pdo->prepare("SELECT name FROM design_screens WHERE id = :sid");
        $stmtScr->execute([':sid' => $sid]);
        $screenCache[$sid] = $stmtScr->fetchColumn() ?: '';
    }
    $screenName = $screenCache[$sid];

    // Fetch project details (cached)
    $pid = $ann['project_id'];
    if (!isset($projectCache[$pid])) {
        $stmtProj = $pdo->prepare("SELECT name FROM projects WHERE id = :pid");
        $stmtProj->execute([':pid' => $pid]);
        $projectCache[$pid] = $stmtProj->fetchColumn() ?: '';
    }
    $projectName = $projectCache[$pid];

    // Fetch reactions for this annotation and its replies
    $stmtRx = $pdo->prepare("
        SELECT r.id, r.annotation_id, r.reply_id, r.user_id, r.emoji, r.created_at, u.name as user_name
        FROM comment_reactions r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.annotation_id = :aid
        ORDER BY r.created_at ASC
    ");
    $stmtRx->execute([':aid' => $ann['id']]);
    $rawRx = $stmtRx->fetchAll();

    $annReactionsMap = [];
    $replyReactionsMap = [];

    foreach ($rawRx as $rx) {
        $e = $rx['emoji'];
        $uName = $rx['user_name'] ?: 'Người dùng';
        $isMe = ($currentUserId && $rx['user_id'] === $currentUserId);
        $rid = $rx['reply_id'];

        if (empty($rid)) {
            if (!isset($annReactionsMap[$e])) {
                $annReactionsMap[$e] = [
                    'emoji' => $e,
                    'count' => 0,
                    'users' => [],
                    'hasReacted' => false
                ];
            }
            $annReactionsMap[$e]['count']++;
            $annReactionsMap[$e]['users'][] = $uName;
            if ($isMe) {
                $annReactionsMap[$e]['hasReacted'] = true;
            }
        } else {
            if (!isset($replyReactionsMap[$rid])) {
                $replyReactionsMap[$rid] = [];
            }
            if (!isset($replyReactionsMap[$rid][$e])) {
                $replyReactionsMap[$rid][$e] = [
                    'emoji' => $e,
                    'count' => 0,
                    'users' => [],
                    'hasReacted' => false
                ];
            }
            $replyReactionsMap[$rid][$e]['count']++;
            $replyReactionsMap[$rid][$e]['users'][] = $uName;
            if ($isMe) {
                $replyReactionsMap[$rid][$e]['hasReacted'] = true;
            }
        }
    }

    $annReactions = array_values($annReactionsMap);

    // Fetch replies
    $stmtRep = $pdo->prepare("SELECT * FROM annotation_replies WHERE annotation_id = :aid ORDER BY created_at ASC");
    $stmtRep->execute([':aid' => $ann['id']]);
    $allRawReplies = $stmtRep->fetchAll();
    $totalRepliesCount = count($allRawReplies);

    if ($replyLimit !== null && (int)$replyLimit >= 0 && $totalRepliesCount > (int)$replyLimit) {
        $rawReplies = array_slice($allRawReplies, -(int)$replyLimit);
        $hasMoreReplies = true;
    } else {
        $rawReplies = $allRawReplies;
        $hasMoreReplies = false;
    }

    $replies = [];
    foreach ($rawReplies as $r) {
        $rReactions = isset($replyReactionsMap[$r['id']]) ? array_values($replyReactionsMap[$r['id']]) : [];
        $replies[] = [
            'id' => $r['id'],
            'annotationId' => $r['annotation_id'],
            'authorId' => $r['author_id'],
            'body' => $r['body'],
            'attachmentUrl' => $r['attachment_url'] ?? null,
            'createdAt' => $r['created_at'],
            'author' => public_user($pdo, $r['author_id']),
            'reactions' => $rReactions
        ];
    }

    // Fetch status history only if needed (for full presentation, not paginated list summary)
    $statusHistory = [];
    if ($replyLimit === null) {
        $stmtHis = $pdo->prepare("SELECT * FROM annotation_status_events WHERE annotation_id = :aid ORDER BY created_at ASC");
        $stmtHis->execute([':aid' => $ann['id']]);
        $rawHis = $stmtHis->fetchAll();
        foreach ($rawHis as $h) {
            $statusHistory[] = [
                'id' => $h['id'],
                'annotationId' => $h['annotation_id'],
                'status' => $h['status'],
                'userId' => $h['user_id'],
                'at' => $h['created_at'],
                'note' => $h['note'],
                'user' => public_user($pdo, $h['user_id'])
            ];
        }
    }

    return [
        'id' => $ann['id'],
        'projectId' => $ann['project_id'],
        'screenId' => $ann['screen_id'],
        'imageVersionId' => $ann['image_version_id'],
        'type' => $ann['type'],
        'x' => (float)$ann['x'],
        'y' => (float)$ann['y'],
        'w' => (float)$ann['w'],
        'h' => (float)$ann['h'],
        'originalWidth' => (int)($version ? $version['width'] : $ann['original_width']),
        'originalHeight' => (int)($version ? $version['height'] : $ann['original_height']),
        'authorId' => $ann['author_id'],
        'body' => $ann['body'],
        'status' => $ann['status'],
        'revision' => (int)$ann['revision'],
        'checkNote' => $ann['check_note'] ?: '',
        'createdAt' => $ann['created_at'],
        'updatedAt' => $ann['updated_at'],
        'number' => $number,
        'drawingData' => $ann['drawing_data'] ?? null,
        'author' => public_user($pdo, $ann['author_id']),
        'reactions' => $annReactions,
        'replies' => $replies,
        'replyCount' => $totalRepliesCount,
        'hasMoreReplies' => $hasMoreReplies,
        'statusHistory' => $statusHistory,
        'versionNumber' => $version ? (int)$version['number'] : null,
        'versionLabel' => $version ? ('v' . $version['number']) : '',
        'screenName' => $screenName,
        'projectName' => $projectName,
        'imageWidth' => (int)($version ? $version['width'] : $ann['original_width']),
        'imageHeight' => (int)($version ? $version['height'] : $ann['original_height'])
    ];
}

function present_replies(PDO $pdo, $annotationId, $currentUserId = null) {
    if ($currentUserId === null) {
        $sess = get_current_user_session($pdo);
        if ($sess && isset($sess['userId'])) {
            $currentUserId = $sess['userId'];
        }
    }

    $stmtRx = $pdo->prepare("
        SELECT r.id, r.annotation_id, r.reply_id, r.user_id, r.emoji, r.created_at, u.name as user_name
        FROM comment_reactions r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.annotation_id = :aid AND r.reply_id IS NOT NULL AND r.reply_id != ''
        ORDER BY r.created_at ASC
    ");
    $stmtRx->execute([':aid' => $annotationId]);
    $rawRx = $stmtRx->fetchAll();

    $replyReactionsMap = [];
    foreach ($rawRx as $rx) {
        $e = $rx['emoji'];
        $uName = $rx['user_name'] ?: 'Người dùng';
        $isMe = ($currentUserId && $rx['user_id'] === $currentUserId);
        $rid = $rx['reply_id'];

        if (!isset($replyReactionsMap[$rid])) {
            $replyReactionsMap[$rid] = [];
        }
        if (!isset($replyReactionsMap[$rid][$e])) {
            $replyReactionsMap[$rid][$e] = [
                'emoji' => $e,
                'count' => 0,
                'users' => [],
                'hasReacted' => false
            ];
        }
        $replyReactionsMap[$rid][$e]['count']++;
        $replyReactionsMap[$rid][$e]['users'][] = $uName;
        if ($isMe) {
            $replyReactionsMap[$rid][$e]['hasReacted'] = true;
        }
    }

    $stmtRep = $pdo->prepare("SELECT * FROM annotation_replies WHERE annotation_id = :aid ORDER BY created_at ASC");
    $stmtRep->execute([':aid' => $annotationId]);
    $rawReplies = $stmtRep->fetchAll();
    $replies = [];
    foreach ($rawReplies as $r) {
        $rReactions = isset($replyReactionsMap[$r['id']]) ? array_values($replyReactionsMap[$r['id']]) : [];
        $replies[] = [
            'id' => $r['id'],
            'annotationId' => $r['annotation_id'],
            'authorId' => $r['author_id'],
            'body' => $r['body'],
            'attachmentUrl' => $r['attachment_url'] ?? null,
            'createdAt' => $r['created_at'],
            'author' => public_user($pdo, $r['author_id']),
            'reactions' => $rReactions
        ];
    }
    return $replies;
}

function summarize_screen(PDO $pdo, $scr) {
    $stmtVer = $pdo->prepare("SELECT * FROM image_versions WHERE screen_id = :sid ORDER BY number ASC, created_at ASC");
    $stmtVer->execute([':sid' => $scr['id']]);
    $versions = $stmtVer->fetchAll();
    $latest = !empty($versions) ? $versions[count($versions) - 1] : null;

    $stmtAnn = $pdo->prepare("SELECT count(*) as total, sum(case when status = 'open' then 1 else 0 end) as open_cnt FROM annotations WHERE screen_id = :sid");
    $stmtAnn->execute([':sid' => $scr['id']]);
    $annStats = $stmtAnn->fetch();

    return [
        'id' => $scr['id'],
        'projectId' => $scr['project_id'],
        'name' => $scr['name'],
        'description' => $scr['description'] ?: '',
        'createdAt' => $scr['created_at'],
        'updatedAt' => $scr['updated_at'],
        'createdBy' => $scr['created_by'],
        'sortOrder' => (int)($scr['sort_order'] ?? 0),
        'versionCount' => count($versions),
        'latestVersion' => present_version($pdo, $latest),
        'commentCount' => (int)($annStats['total'] ?? 0),
        'openCount' => (int)($annStats['open_cnt'] ?? 0)
    ];
}

function summarize_project(PDO $pdo, $proj, $userId) {
    // Screen count
    $stmtScr = $pdo->prepare("SELECT count(*) FROM design_screens WHERE project_id = :pid");
    $stmtScr->execute([':pid' => $proj['id']]);
    $screenCount = (int)$stmtScr->fetchColumn();

    // Cover image from first screen
    $stmtCover = $pdo->prepare("SELECT iv.image_key FROM design_screens ds JOIN image_versions iv ON ds.id = iv.screen_id WHERE ds.project_id = :pid ORDER BY COALESCE(ds.sort_order, 0) ASC, ds.created_at ASC, iv.number DESC LIMIT 1");
    $stmtCover->execute([':pid' => $proj['id']]);
    $coverImageKey = $stmtCover->fetchColumn() ?: null;

    // Annotation stats
    $stmtAnn = $pdo->prepare("SELECT count(*) as total, sum(case when status = 'open' then 1 else 0 end) as open_cnt, sum(case when status = 'resolved' then 1 else 0 end) as res_cnt FROM annotations WHERE project_id = :pid");
    $stmtAnn->execute([':pid' => $proj['id']]);
    $annStats = $stmtAnn->fetch();

    // Members
    $stmtMem = $pdo->prepare("SELECT u.id, u.name, u.username, u.email, pm.role, u.color, u.title, pm.id as membership_id, pm.created_at FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = :pid");
    $stmtMem->execute([':pid' => $proj['id']]);
    $members = $stmtMem->fetchAll();

    $myRole = null;
    foreach ($members as $m) {
        if ($m['id'] === $userId) {
            $myRole = $m['role'];
            break;
        }
    }

    if ($myRole === null) {
        if ($proj['created_by'] === $userId) {
            $myRole = 'manager';
        } else {
            $u = public_user($pdo, $userId);
            if (($u['systemRole'] ?? '') === 'admin') {
                $myRole = 'admin_observer';
            }
        }
    }

    return [
        'id' => $proj['id'],
        'name' => $proj['name'],
        'description' => $proj['description'] ?: '',
        'category' => !empty($proj['category']) ? $proj['category'] : 'Khác',
        'createdAt' => $proj['created_at'],
        'updatedAt' => $proj['updated_at'],
        'createdBy' => $proj['created_by'],
        'creator' => public_user($pdo, $proj['created_by']),
        'isOwner' => ($proj['created_by'] === $userId),
        'screenCount' => $screenCount,
        'coverImageKey' => $coverImageKey,

        'commentCount' => (int)($annStats['total'] ?? 0),
        'openCount' => (int)($annStats['open_cnt'] ?? 0),
        'resolvedCount' => (int)($annStats['res_cnt'] ?? 0),
        'members' => array_map(function($m) {
            return [
                'id' => $m['id'],
                'name' => $m['name'],
                'username' => $m['username'],
                'email' => $m['email'] ?? '',
                'role' => $m['role'],
                'color' => $m['color'],
                'title' => $m['title'],
                'membershipId' => $m['membership_id'],
                'createdAt' => $m['created_at']
            ];
        }, $members),
        'myRole' => $myRole,
        'deletedAt' => $proj['deleted_at'] ?? null,
        'deletedBy' => $proj['deleted_by'] ?? null,
        'daysRemaining' => !empty($proj['deleted_at']) ? max(0, 30 - (int)floor((time() - strtotime($proj['deleted_at'])) / 86400)) : null
    ];
}

function permanently_delete_project(PDO $pdo, $projectId) {
    // Clean up uploaded image files
    try {
        $stmtImgs = $pdo->prepare("SELECT image_key FROM image_versions WHERE project_id = :pid");
        $stmtImgs->execute([':pid' => $projectId]);
        $imgs = $stmtImgs->fetchAll();
        foreach ($imgs as $img) {
            $key = $img['image_key'];
            if ($key && !str_starts_with($key, 'seed:')) {
                $filename = basename($key);
                $filePath = __DIR__ . '/../uploads/' . $filename;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }
    } catch (Throwable $e) {}

    // Cascade delete in database
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM annotation_replies WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :pid)")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM annotation_status_events WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :pid)")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM annotations WHERE project_id = :pid")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM image_versions WHERE project_id = :pid")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM design_screens WHERE project_id = :pid")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM activities WHERE project_id = :pid")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM project_members WHERE project_id = :pid")->execute([':pid' => $projectId]);
        $pdo->prepare("DELETE FROM projects WHERE id = :pid")->execute([':pid' => $projectId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Mirror to SQLite if running pgsql
    mirror_to_sqlite("DELETE FROM annotation_replies WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :pid)", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM annotation_status_events WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :pid)", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM annotations WHERE project_id = :pid", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM image_versions WHERE project_id = :pid", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM design_screens WHERE project_id = :pid", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM activities WHERE project_id = :pid", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM project_members WHERE project_id = :pid", [':pid' => $projectId]);
    mirror_to_sqlite("DELETE FROM projects WHERE id = :pid", [':pid' => $projectId]);
}

function purge_expired_trash(PDO $pdo): int {
    $count = 0;
    try {
        // Find projects deleted more than 30 days ago
        $threshold = (new DateTime('-30 days', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE deleted_at IS NOT NULL AND deleted_at <= :threshold");
        $stmt->execute([':threshold' => $threshold]);
        $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expiredIds as $pid) {
            permanently_delete_project($pdo, $pid);
            $count++;
        }
    } catch (Throwable $e) {
        // Non-blocking purge
    }
    return $count;
}

