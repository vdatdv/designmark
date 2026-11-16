<?php
// api/feedback.php - High-Performance Feedback list and hierarchy API

require_once __DIR__ . '/db.php';

// Purge projects in trash older than 30 days
purge_expired_trash($pdo);

$session = require_auth($pdo);
$projectId = $_GET['project'] ?? $_GET['project_id'] ?? null;
$isFlat = isset($_GET['flat']) || isset($_GET['raw']);

// 1. Get projects user is member of (or all projects if Admin observer), excluding trash
if (($session['systemRole'] ?? '') === 'admin') {
    $stmtM = $pdo->query("
        SELECT p.id, p.name, p.description, p.created_by, p.created_at, p.updated_at,
               CASE
                   WHEN pm.role IS NOT NULL THEN pm.role
                   WHEN p.created_by = " . $pdo->quote($session['userId']) . " THEN 'manager'
                   ELSE 'admin_observer'
               END as my_role
        FROM projects p
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = " . $pdo->quote($session['userId']) . "
        WHERE p.deleted_at IS NULL
        ORDER BY p.name ASC
    ");
    $userProjects = $stmtM->fetchAll();
} else {
    $stmtM = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.created_by, p.created_at, p.updated_at, pm.role as my_role 
        FROM projects p 
        JOIN project_members pm ON p.id = pm.project_id 
        WHERE pm.user_id = :uid AND p.deleted_at IS NULL
        ORDER BY p.name ASC
    ");
    $stmtM->execute([':uid' => $session['userId']]);
    $userProjects = $stmtM->fetchAll();
}


if (empty($userProjects)) {
    json_response($isFlat ? [] : ['projects' => [], 'annotations' => []]);
}

$userProjectMap = [];
foreach ($userProjects as $up) {
    $userProjectMap[$up['id']] = $up;
}

if ($projectId) {
    if (!isset($userProjectMap[$projectId])) {
        json_error('Bạn không thuộc dự án này.', 'FORBIDDEN', 403);
    }
    $targetProjects = [$projectId];
} else {
    $targetProjects = array_keys($userProjectMap);
}

// 2. Batch fetch screens for target projects
$placeholders = implode(',', array_fill(0, count($targetProjects), '?'));
$stmtScr = $pdo->prepare("SELECT id, project_id, name, description, created_at, updated_at FROM design_screens WHERE project_id IN ($placeholders) ORDER BY created_at ASC");
$stmtScr->execute($targetProjects);
$rawScreens = $stmtScr->fetchAll();
$screenMap = [];
foreach ($rawScreens as $s) {
    $screenMap[$s['id']] = $s;
}

// 3. Batch fetch versions for target projects
$stmtVer = $pdo->prepare("SELECT id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at FROM image_versions WHERE project_id IN ($placeholders) ORDER BY number ASC");
$stmtVer->execute($targetProjects);
$rawVersions = $stmtVer->fetchAll();
$versionMap = [];
foreach ($rawVersions as $v) {
    $versionMap[$v['id']] = $v;
}

// 4. Batch fetch annotations for target projects
$stmtAnn = $pdo->prepare("SELECT * FROM annotations WHERE project_id IN ($placeholders) ORDER BY created_at DESC, id ASC");
$stmtAnn->execute($targetProjects);
$rawAnns = $stmtAnn->fetchAll();

$annIds = array_column($rawAnns, 'id');
$repliesByAnn = [];
$statusEventsByAnn = [];

if (!empty($annIds)) {
    // 5. Batch fetch replies
    $annPlaceholders = implode(',', array_fill(0, count($annIds), '?'));
    $stmtRep = $pdo->prepare("SELECT * FROM annotation_replies WHERE annotation_id IN ($annPlaceholders) ORDER BY created_at ASC");
    $stmtRep->execute($annIds);
    $allReplies = $stmtRep->fetchAll();
    foreach ($allReplies as $r) {
        $aid = $r['annotation_id'];
        if (!isset($repliesByAnn[$aid])) $repliesByAnn[$aid] = [];
        $repliesByAnn[$aid][] = [
            'id' => $r['id'],
            'annotationId' => $r['annotation_id'],
            'authorId' => $r['author_id'],
            'body' => $r['body'],
            'createdAt' => $r['created_at'],
            'author' => public_user($pdo, $r['author_id'])
        ];
    }

    // 6. Batch fetch status events
    $stmtHis = $pdo->prepare("SELECT * FROM annotation_status_events WHERE annotation_id IN ($annPlaceholders) ORDER BY created_at ASC");
    $stmtHis->execute($annIds);
    $allEvents = $stmtHis->fetchAll();
    foreach ($allEvents as $h) {
        $aid = $h['annotation_id'];
        if (!isset($statusEventsByAnn[$aid])) $statusEventsByAnn[$aid] = [];
        $statusEventsByAnn[$aid][] = [
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

// 7. Calculate sibling numbers per version (sorted created_at ASC, id ASC)
$siblingsByVersion = [];
foreach ($rawAnns as $a) {
    $vid = $a['image_version_id'];
    if (!isset($siblingsByVersion[$vid])) {
        $siblingsByVersion[$vid] = [];
    }
    $siblingsByVersion[$vid][] = $a;
}
foreach ($siblingsByVersion as $vid => &$list) {
    usort($list, function($x, $y) {
        $cmp = strcmp($x['created_at'], $y['created_at']);
        return $cmp !== 0 ? $cmp : strcmp($x['id'], $y['id']);
    });
}
unset($list);

$numberMap = [];
foreach ($siblingsByVersion as $vid => $list) {
    foreach ($list as $idx => $item) {
        $numberMap[$item['id']] = $idx + 1;
    }
}

// 8. Present all annotations
$annotations = [];
foreach ($rawAnns as $a) {
    $ver = $versionMap[$a['image_version_id']] ?? null;
    $scr = $screenMap[$a['screen_id']] ?? null;
    $prj = $userProjectMap[$a['project_id']] ?? null;

    $annotations[] = [
        'id' => $a['id'],
        'projectId' => $a['project_id'],
        'screenId' => $a['screen_id'],
        'imageVersionId' => $a['image_version_id'],
        'type' => $a['type'],
        'x' => (float)$a['x'],
        'y' => (float)$a['y'],
        'w' => (float)$a['w'],
        'h' => (float)$a['h'],
        'originalWidth' => (int)($ver ? $ver['width'] : $a['original_width']),
        'originalHeight' => (int)($ver ? $ver['height'] : $a['original_height']),
        'authorId' => $a['author_id'],
        'body' => $a['body'],
        'status' => $a['status'],
        'revision' => (int)$a['revision'],
        'checkNote' => $a['check_note'] ?: '',
        'createdAt' => $a['created_at'],
        'updatedAt' => $a['updated_at'],
        'number' => $numberMap[$a['id']] ?? 1,
        'author' => public_user($pdo, $a['author_id']),
        'replies' => $repliesByAnn[$a['id']] ?? [],
        'statusHistory' => $statusEventsByAnn[$a['id']] ?? [],
        'versionNumber' => $ver ? (int)$ver['number'] : null,
        'versionLabel' => $ver ? ('v' . $ver['number']) : '',
        'screenName' => $scr ? $scr['name'] : '',
        'projectName' => $prj ? $prj['name'] : '',
        'imageWidth' => (int)($ver ? $ver['width'] : $a['original_width']),
        'imageHeight' => (int)($ver ? $ver['height'] : $a['original_height'])
    ];
}

if ($isFlat) {
    json_response($annotations);
}

// 9. Build version and screen hierarchy
$versionsByScreen = [];
foreach ($rawVersions as $v) {
    $sid = $v['screen_id'];
    if (!isset($versionsByScreen[$sid])) {
        $versionsByScreen[$sid] = [];
    }
    $versionsByScreen[$sid][] = [
        'id' => $v['id'],
        'screenId' => $v['screen_id'],
        'projectId' => $v['project_id'],
        'number' => (int)$v['number'],
        'label' => 'v' . $v['number'],
        'createdAt' => $v['created_at'],
        'totalComments' => 0,
        'openComments' => 0,
        'resolvedComments' => 0
    ];
}

$screensByProject = [];
foreach ($rawScreens as $s) {
    $pid = $s['project_id'];
    if (!isset($screensByProject[$pid])) {
        $screensByProject[$pid] = [];
    }
    $screensByProject[$pid][$s['id']] = [
        'id' => $s['id'],
        'projectId' => $s['project_id'],
        'name' => $s['name'],
        'description' => $s['description'] ?: '',
        'totalComments' => 0,
        'openComments' => 0,
        'resolvedComments' => 0,
        'versions' => $versionsByScreen[$s['id']] ?? []
    ];
}

// 10. Compute aggregated stats
$projectStats = [];
foreach ($targetProjects as $pid) {
    $projectStats[$pid] = [
        'totalComments' => 0,
        'openComments' => 0,
        'resolvedComments' => 0
    ];
}

foreach ($annotations as $ann) {
    $pid = $ann['projectId'];
    $sid = $ann['screenId'];
    $vid = $ann['imageVersionId'];
    $st = $ann['status'];

    if (isset($projectStats[$pid])) {
        $projectStats[$pid]['totalComments']++;
        if ($st === 'open') $projectStats[$pid]['openComments']++;
        if ($st === 'resolved') $projectStats[$pid]['resolvedComments']++;
    }

    if (isset($screensByProject[$pid][$sid])) {
        $screensByProject[$pid][$sid]['totalComments']++;
        if ($st === 'open') $screensByProject[$pid][$sid]['openComments']++;
        if ($st === 'resolved') $screensByProject[$pid][$sid]['resolvedComments']++;

        foreach ($screensByProject[$pid][$sid]['versions'] as &$verObj) {
            if ($verObj['id'] === $vid) {
                $verObj['totalComments']++;
                if ($st === 'open') $verObj['openComments']++;
                if ($st === 'resolved') $verObj['resolvedComments']++;
                break;
            }
        }
        unset($verObj);
    }
}

// 11. Format final projects list
$projectsResult = [];
foreach ($targetProjects as $pid) {
    $p = $userProjectMap[$pid];
    $screensList = array_values($screensByProject[$pid] ?? []);
    $projectsResult[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'description' => $p['description'] ?: '',
        'createdBy' => $p['created_by'],
        'isOwner' => ($p['created_by'] === $session['userId']),
        'myRole' => $p['my_role'],
        'createdAt' => $p['created_at'],
        'updatedAt' => $p['updated_at'],
        'totalComments' => $projectStats[$pid]['totalComments'] ?? 0,
        'openComments' => $projectStats[$pid]['openComments'] ?? 0,
        'resolvedComments' => $projectStats[$pid]['resolvedComments'] ?? 0,
        'screens' => $screensList
    ];
}

json_response([
    'projects' => $projectsResult,
    'annotations' => $annotations
]);
