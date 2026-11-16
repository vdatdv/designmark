<?php
// api/dashboard.php - Dashboard API (Aggregated stats, recent activities, pending feedback, recent projects)
require_once __DIR__ . '/db.php';

// Purge projects in trash older than 30 days
purge_expired_trash($pdo);

$session = require_auth($pdo);
$userId = $session['userId'];

// Get project IDs user has access to (or all projects if Admin observer), excluding projects in trash
if (($session['systemRole'] ?? '') === 'admin') {
    $stmtP = $pdo->query("SELECT id FROM projects WHERE deleted_at IS NULL");
    $projectIds = $stmtP->fetchAll(PDO::FETCH_COLUMN);
} else {
    $stmtM = $pdo->prepare("SELECT DISTINCT p.id FROM projects p LEFT JOIN project_members pm ON pm.project_id = p.id WHERE (pm.user_id = :uid OR p.created_by = :uid) AND p.deleted_at IS NULL");
    $stmtM->execute([':uid' => $userId]);
    $projectIds = $stmtM->fetchAll(PDO::FETCH_COLUMN);
}


$totalUsers = (int)$pdo->query("SELECT count(*) FROM users WHERE COALESCE(status, 'active') != 'deleted'")->fetchColumn();

$emptyDashboard = [
    'stats' => [
        'totalProjects' => 0,
        'totalScreens' => 0,
        'openFeedback' => 0,
        'resolvedFeedback' => 0,
        'totalUsers' => $totalUsers
    ],
    'recentActivities' => [],
    'pendingFeedback' => [],
    'recentProjects' => [],
    'pendingUsers' => []
];

// Pending approval workflow is removed from active dashboard
$pendingUsers = [];

if (empty($projectIds)) {
    $emptyDashboard['pendingUsers'] = $pendingUsers;
    json_response($emptyDashboard);
}

$placeholders = implode(',', array_fill(0, count($projectIds), '?'));

// 1. Stats
// Total Screens
$stmtScr = $pdo->prepare("SELECT count(*) FROM design_screens WHERE project_id IN ($placeholders)");
$stmtScr->execute($projectIds);
$totalScreens = (int)$stmtScr->fetchColumn();

// Feedback counts
$stmtFb = $pdo->prepare("SELECT 
    sum(case when status != 'resolved' then 1 else 0 end) as open_cnt,
    sum(case when status = 'resolved' then 1 else 0 end) as res_cnt
    FROM annotations WHERE project_id IN ($placeholders)");
$stmtFb->execute($projectIds);
$fbStats = $stmtFb->fetch();
$openFeedback = (int)($fbStats['open_cnt'] ?? 0);
$resolvedFeedback = (int)($fbStats['res_cnt'] ?? 0);

$stats = [
    'totalProjects' => count($projectIds),
    'totalScreens' => $totalScreens,
    'openFeedback' => $openFeedback,
    'resolvedFeedback' => $resolvedFeedback,
    'totalFeedback' => ($openFeedback + $resolvedFeedback),
    'totalUsers' => $totalUsers
];

// 2. Recent Activities (limit 5 by default, or up to 100 if all_activities requested)
$isAllActivities = isset($_GET['all_activities']) && in_array($_GET['all_activities'], ['1', 'true', 'all'], true);
$limitClause = $isAllActivities ? "LIMIT 100" : "LIMIT 5";

$stmtAct = $pdo->prepare("SELECT a.id, a.project_id, a.user_id, a.type, a.message, a.created_at, 
    COALESCE(a.link, '') as act_link, COALESCE(a.annotation_id, '') as act_annotation_id,
    p.name as project_name 
    FROM activities a 
    JOIN projects p ON a.project_id = p.id 
    WHERE a.project_id IN ($placeholders) 
    ORDER BY a.created_at DESC 
    $limitClause");
$stmtAct->execute($projectIds);
$rawActivities = $stmtAct->fetchAll();

$stmtCountAct = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE project_id IN ($placeholders)");
$stmtCountAct->execute($projectIds);
$totalActivities = (int)$stmtCountAct->fetchColumn();

// Chuẩn bị các câu truy vấn hỗ trợ tìm link góp ý cho các activity cũ chưa có link trực tiếp
$stmtNotifLink = $pdo->prepare("SELECT link, annotation_id, screen_id, image_version_id 
    FROM notifications 
    WHERE project_id = :pid AND annotation_id IS NOT NULL AND annotation_id != ''
    ORDER BY created_at DESC LIMIT 1");

$stmtScreens = $pdo->prepare("SELECT id, name FROM design_screens WHERE project_id = :pid");
$stmtScreenAnn = $pdo->prepare("SELECT a.id, a.screen_id, a.image_version_id 
    FROM annotations a 
    WHERE a.screen_id = :sid 
    ORDER BY a.updated_at DESC, a.created_at DESC LIMIT 1");

$stmtLatestProjAnn = $pdo->prepare("SELECT a.id, a.screen_id, a.image_version_id 
    FROM annotations a 
    WHERE a.project_id = :pid 
    ORDER BY a.updated_at DESC, a.created_at DESC LIMIT 1");

$recentActivities = [];
foreach ($rawActivities as $act) {
    $actLink = $act['act_link'];
    $isCommentAct = (strpos($act['type'], 'comment.') === 0 || strpos($act['message'], 'góp ý') !== false || strpos($act['message'], 'nhận xét') !== false);
    $targetAnnotationId = $act['act_annotation_id'];

    if (!$actLink && $isCommentAct) {
        // 1. Thử tìm màn hình được nhắc đến trong message
        $stmtScreens->execute([':pid' => $act['project_id']]);
        $screens = $stmtScreens->fetchAll();
        $matchedScreenId = null;
        foreach ($screens as $scr) {
            if (mb_stripos($act['message'], $scr['name']) !== false) {
                $matchedScreenId = $scr['id'];
                break;
            }
        }

        if ($matchedScreenId) {
            $stmtScreenAnn->execute([':sid' => $matchedScreenId]);
            $foundAnn = $stmtScreenAnn->fetch();
            if ($foundAnn) {
                $actLink = "review.php?project=" . urlencode($act['project_id']) . "&screen=" . urlencode($foundAnn['screen_id']) . "&version=" . urlencode($foundAnn['image_version_id']) . "&annotation=" . urlencode($foundAnn['id']);
                $targetAnnotationId = $foundAnn['id'];
            }
        }

        // 2. Nếu chưa có, thử tìm từ notifications liên quan
        if (!$actLink) {
            $stmtNotifLink->execute([':pid' => $act['project_id']]);
            $notif = $stmtNotifLink->fetch();
            if ($notif && !empty($notif['link'])) {
                $actLink = $notif['link'];
                $targetAnnotationId = $notif['annotation_id'];
            }
        }

        // 3. Nếu vẫn chưa có, lấy annotation mới nhất của dự án
        if (!$actLink) {
            $stmtLatestProjAnn->execute([':pid' => $act['project_id']]);
            $latestAnn = $stmtLatestProjAnn->fetch();
            if ($latestAnn) {
                $actLink = "review.php?project=" . urlencode($act['project_id']) . "&screen=" . urlencode($latestAnn['screen_id']) . "&version=" . urlencode($latestAnn['image_version_id']) . "&annotation=" . urlencode($latestAnn['id']);
                $targetAnnotationId = $latestAnn['id'];
            }
        }
    }

    if (!$actLink) {
        $actLink = "project.php?id=" . urlencode($act['project_id']);
    }

    $recentActivities[] = [
        'id' => $act['id'],
        'projectId' => $act['project_id'],
        'projectName' => $act['project_name'],
        'userId' => $act['user_id'],
        'user' => public_user($pdo, $act['user_id']),
        'type' => $act['type'],
        'message' => $act['message'],
        'createdAt' => $act['created_at'],
        'link' => $actLink,
        'annotationId' => $targetAnnotationId,
        'isComment' => $isCommentAct
    ];
}

// 3. Pending Feedback (Góp ý cần xử lý - limit 5)
$stmtAnn = $pdo->prepare("SELECT a.id, a.project_id, a.screen_id, a.image_version_id, a.author_id, a.body, a.status, a.created_at, a.updated_at,
    p.name as project_name, s.name as screen_name, v.number as version_number
    FROM annotations a
    JOIN projects p ON a.project_id = p.id
    JOIN design_screens s ON a.screen_id = s.id
    JOIN image_versions v ON a.image_version_id = v.id
    WHERE a.project_id IN ($placeholders) AND a.status != 'resolved'
    ORDER BY a.updated_at DESC, a.created_at DESC
    LIMIT 5");
$stmtAnn->execute($projectIds);
$rawPending = $stmtAnn->fetchAll();

$pendingFeedback = [];
foreach ($rawPending as $pAnn) {
    $pendingFeedback[] = [
        'id' => $pAnn['id'],
        'projectId' => $pAnn['project_id'],
        'projectName' => $pAnn['project_name'],
        'screenId' => $pAnn['screen_id'],
        'screenName' => $pAnn['screen_name'],
        'versionNumber' => (int)$pAnn['version_number'],
        'body' => $pAnn['body'],
        'status' => $pAnn['status'],
        'author' => public_user($pdo, $pAnn['author_id']),
        'isMyFeedback' => ($pAnn['author_id'] === $session['userId']),
        'createdAt' => $pAnn['created_at'],
        'reviewUrl' => "review.php?project=" . urlencode($pAnn['project_id']) . "&screen=" . urlencode($pAnn['screen_id']) . "&version=" . urlencode($pAnn['image_version_id']) . "&annotation=" . urlencode($pAnn['id'])
    ];
}

// 4. Recent Projects (Top 3 most recently updated)
$stmtProj = $pdo->prepare("SELECT * FROM projects WHERE id IN ($placeholders) ORDER BY updated_at DESC, created_at DESC LIMIT 3");
$stmtProj->execute($projectIds);
$rawRecentProj = $stmtProj->fetchAll();

$recentProjects = [];
foreach ($rawRecentProj as $proj) {
    $recentProjects[] = summarize_project($pdo, $proj, $userId);
}

json_response([
    'stats' => $stats,
    'recentActivities' => $recentActivities,
    'totalActivities' => $totalActivities,
    'pendingFeedback' => $pendingFeedback,
    'recentProjects' => $recentProjects,
    'pendingUsers' => $pendingUsers
]);
