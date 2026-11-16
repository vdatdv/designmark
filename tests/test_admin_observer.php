<?php
// tests/test_admin_observer.php - Comprehensive Test Suite for System-Wide Observer Admin Model

require_once __DIR__ . '/../api/db.php';

$baseUrl = 'http://127.0.0.1:8000';
$passed = 0;
$failed = 0;

function run_test($name, $callback) {
    global $passed, $failed;
    echo "Testing: $name... ";
    try {
        $callback();
        echo "\033[32mPASSED\033[0m\n";
        $passed++;
    } catch (Throwable $e) {
        echo "\033[31mFAILED\033[0m: " . $e->getMessage() . "\n";
        $failed++;
    }
}

function http_req($method, $path, $data = null, &$cookieJar = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);
    }

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch); // invoke destructor to flush cookies

    return ['status' => $status, 'json' => json_decode($body, true), 'raw' => $body];
}

$adminCookie = tempnam(sys_get_temp_dir(), 'adm_cookie_');
$anCookie = tempnam(sys_get_temp_dir(), 'an_cookie_');
$binhCookie = tempnam(sys_get_temp_dir(), 'binh_cookie_');
$chiCookie = tempnam(sys_get_temp_dir(), 'chi_cookie_');

// 1. Verify user-admin is NOT in project_members
run_test("1. Admin is NOT in project_members table (Requirement 8)", function() {
    global $pdo;
    $stmt = $pdo->query("SELECT count(*) FROM project_members WHERE user_id = 'user-admin'");
    $cnt = (int)$stmt->fetchColumn();
    if ($cnt !== 0) throw new Exception("user-admin must NOT be in project_members, found $cnt rows");
});

// 2. Admin logs in successfully
run_test("2. Admin login via username-only auth (Requirement 3)", function() use (&$adminCookie) {
    $res = http_req('POST', '/api/auth.php?action=login', [
        'username' => 'admin',
        'password' => 'demo'
    ], $adminCookie);

    if ($res['status'] !== 200) throw new Exception("Login failed: " . $res['raw']);
    if (($res['json']['systemRole'] ?? '') !== 'admin') throw new Exception("Expected systemRole admin");
});

// 3. Admin views Dashboard stats across whole system
run_test("3. Admin Dashboard returns system-wide stats and projects (Requirements 1, 2)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/dashboard.php', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET dashboard failed: " . $res['raw']);
    $stats = $res['json']['stats'] ?? [];
    if (($stats['totalProjects'] ?? 0) < 2) throw new Exception("Expected at least 2 totalProjects, got " . ($stats['totalProjects'] ?? 0));
    if (($stats['totalUsers'] ?? 0) < 4) throw new Exception("Expected at least 4 totalUsers, got " . ($stats['totalUsers'] ?? 0));
    if (($stats['totalScreens'] ?? 0) < 2) throw new Exception("Expected at least 2 totalScreens, got " . ($stats['totalScreens'] ?? 0));

    $recentProj = $res['json']['recentProjects'] ?? [];
    if (empty($recentProj)) throw new Exception("Admin should see recent projects on dashboard");
    foreach ($recentProj as $p) {
        if ($p['id'] === 'proj-admin-designmark') {
            if ($p['myRole'] !== 'manager') throw new Exception("Expected myRole manager for owned project, got " . $p['myRole']);
        } else {
            if ($p['myRole'] !== 'admin_observer') throw new Exception("Expected myRole admin_observer for unjoined project, got " . $p['myRole']);
        }
    }
});

// 4. Admin lists all projects across system without membership
run_test("4. Admin lists all projects on system as observer (Requirement 1)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/projects.php', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET projects failed: " . $res['raw']);
    $projects = $res['json'];
    if (!is_array($projects) || count($projects) < 2) throw new Exception("Admin should see all projects, got " . count($projects));
    foreach ($projects as $p) {
        if ($p['id'] === 'proj-admin-designmark') {
            if ($p['myRole'] !== 'manager') throw new Exception("Expected myRole manager for owned project {$p['id']}, got {$p['myRole']}");
        } else {
            if ($p['myRole'] !== 'admin_observer') throw new Exception("Expected myRole admin_observer for project {$p['id']}, got {$p['myRole']}");
        }
    }
});

// 5. Admin inspects specific project details without membership
run_test("5. Admin inspects proj-shop details as observer (Requirement 1)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/projects.php?id=proj-shop', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET proj-shop failed: " . $res['raw']);
    if ($res['json']['myRole'] !== 'admin_observer') throw new Exception("Expected myRole admin_observer, got " . $res['json']['myRole']);
    if (empty($res['json']['members'])) throw new Exception("Expected project members list to be visible");
});

// 6. Admin inspects screens and image versions without 403
run_test("6. Admin inspects screens and image versions without 403 (Requirement 1)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/screens.php?project_id=proj-shop', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET screens failed: " . $res['raw']);
    $screens = $res['json'];
    if (empty($screens)) throw new Exception("Expected screens in proj-shop");

    $screenId = $screens[0]['id'];
    $resScr = http_req('GET', '/api/screens.php?id=' . $screenId, null, $adminCookie);
    if ($resScr['status'] !== 200) throw new Exception("GET screen detail failed: " . $resScr['raw']);
});

// 7. Admin inspects annotations and replies of proj-shop
run_test("7. Admin inspects annotations of ver-signup-1 without 403 (Requirement 1)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/annotations.php?version_id=ver-signup-1', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET annotations failed: " . $res['raw']);
    $anns = $res['json'];
    if (empty($anns)) throw new Exception("Expected annotations in ver-signup-1");
    if (!isset($anns[0]['body'])) throw new Exception("Expected annotation body");
});

// 8. Admin inspects feedback overview across whole system
run_test("8. Admin inspects feedback across whole system (Requirement 1)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/feedback.php', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET feedback failed: " . $res['raw']);
    $projects = $res['json']['projects'] ?? [];
    if (count($projects) < 2) throw new Exception("Expected all projects in feedback API, got " . count($projects));
    foreach ($projects as $p) {
        if ($p['id'] === 'proj-admin-designmark') {
            if ($p['myRole'] !== 'manager') throw new Exception("Expected myRole manager in feedback for owned project, got " . ($p['myRole'] ?? 'null'));
        } else {
            if ($p['myRole'] !== 'admin_observer') throw new Exception("Expected myRole admin_observer in feedback, got " . ($p['myRole'] ?? 'null'));
        }
    }
});

// 9. Admin BLOCKED from creating annotation in proj-shop
run_test("9. Admin CANNOT create annotation without project role (Requirement 4, 6)", function() use (&$adminCookie) {
    $res = http_req('POST', '/api/annotations.php?action=create', [
        'imageVersionId' => 'ver-signup-1',
        'type' => 'point',
        'x' => 100,
        'y' => 100,
        'body' => 'Admin unauthorized test comment'
    ], $adminCookie);
    if ($res['status'] !== 403) throw new Exception("Expected HTTP 403 FORBIDDEN, got {$res['status']}: {$res['raw']}");
});

// 10. Admin BLOCKED from changing annotation status in proj-shop
run_test("10. Admin CANNOT change annotation status without project role (Requirement 4, 6)", function() use (&$adminCookie) {
    $res = http_req('POST', '/api/annotations.php?action=status', [
        'id' => 'ann-01',
        'status' => 'resolved'
    ], $adminCookie);
    if ($res['status'] !== 403) throw new Exception("Expected HTTP 403 FORBIDDEN, got {$res['status']}: {$res['raw']}");
});

// 11. Admin BLOCKED from deleting annotation in proj-shop
run_test("11. Admin CANNOT delete annotation without project role (Requirement 4, 6)", function() use (&$adminCookie) {
    $res = http_req('POST', '/api/annotations.php?action=delete', [
        'id' => 'ann-01'
    ], $adminCookie);
    if ($res['status'] !== 403) throw new Exception("Expected HTTP 403 FORBIDDEN, got {$res['status']}: {$res['raw']}");
});

// 12. Admin BLOCKED from deleting project proj-shop
run_test("12. Admin CANNOT delete project unless project Manager (Requirement 4, 6)", function() use (&$adminCookie) {
    $res = http_req('POST', '/api/projects.php?id=proj-shop&action=delete', [], $adminCookie);
    if ($res['status'] !== 403) throw new Exception("Expected HTTP 403 FORBIDDEN, got {$res['status']}: {$res['raw']}");
});

// 13. Admin BLOCKED from inviting members to proj-shop
run_test("13. Admin CANNOT invite members without project Manager role (Requirement 4, 6)", function() use (&$adminCookie) {
    $res = http_req('POST', '/api/projects.php?id=proj-shop&action=invite', [
        'userId' => 'user-chi',
        'role' => 'reviewer'
    ], $adminCookie);
    if ($res['status'] !== 403) throw new Exception("Expected HTTP 403 FORBIDDEN, got {$res['status']}: {$res['raw']}");
});

// 14. Admin still retains user management functions
run_test("14. Admin retains full user management and lock/unlock (Requirement 3)", function() use (&$adminCookie) {
    $res = http_req('GET', '/api/auth.php?action=list_all_users', null, $adminCookie);
    if ($res['status'] !== 200) throw new Exception("GET list_all_users failed: " . $res['raw']);
    $users = $res['json'];
    if (!is_array($users) || count($users) < 4) throw new Exception("Expected all users in users list");
});

// 15. Regular user 'an' retains Manager rights on proj-shop
run_test("15. User 'an' retains full Manager rights on proj-shop (Requirement 5)", function() use (&$anCookie) {
    $login = http_req('POST', '/api/auth.php?action=login', [
        'username' => 'an',
        'password' => 'demo'
    ], $anCookie);
    if ($login['status'] !== 200) throw new Exception("Login 'an' failed: " . $login['raw']);

    $res = http_req('GET', '/api/projects.php?id=proj-shop', null, $anCookie);
    if ($res['status'] !== 200) throw new Exception("GET proj-shop failed: " . $res['raw']);
    if ($res['json']['myRole'] !== 'manager') throw new Exception("User 'an' should have myRole manager, got " . $res['json']['myRole']);
});

// 16. Regular user 'chi' retains Viewer restrictions on proj-shop
run_test("16. User 'chi' retains Viewer restrictions on proj-shop (Requirement 5)", function() use (&$chiCookie) {
    $login = http_req('POST', '/api/auth.php?action=login', [
        'username' => 'chi',
        'password' => 'demo'
    ], $chiCookie);
    if ($login['status'] !== 200) throw new Exception("Login 'chi' failed: " . $login['raw']);

    $res = http_req('GET', '/api/projects.php?id=proj-shop', null, $chiCookie);
    if ($res['status'] !== 200) throw new Exception("GET proj-shop failed: " . $res['raw']);
    if ($res['json']['myRole'] !== 'viewer') throw new Exception("User 'chi' should have myRole viewer, got " . $res['json']['myRole']);

    $writeRes = http_req('POST', '/api/annotations.php?action=create', [
        'imageVersionId' => 'ver-signup-1',
        'type' => 'point',
        'x' => 50,
        'y' => 50,
        'body' => 'Viewer test'
    ], $chiCookie);
    if ($writeRes['status'] !== 403) throw new Exception("Viewer should be blocked from creating annotation, got {$writeRes['status']}");
});

// Cleanup temp cookie jars
@unlink($adminCookie);
@unlink($anCookie);
@unlink($binhCookie);
@unlink($chiCookie);

echo "\n============================================\n";
echo "SUMMARY: Passed: $passed, Failed: $failed\n";
echo "============================================\n";

if ($failed > 0) exit(1);
exit(0);
