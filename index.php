<?php
// index.php - Login & Register Slider Page
require_once __DIR__ . '/api/db.php';

$session = get_current_user_session($pdo);
if ($session) {
    $next = $_GET['next'] ?? 'dashboard.php';
    if (!str_contains($next, '://') && !str_starts_with($next, '//')) {
        header('Location: ' . $next);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

$initialMode = 'login';
$pageTitle = 'Đăng nhập — DesignMark';
$pageDescription = 'Đăng nhập DesignMark để rà soát giao diện, ghim góp ý trực tiếp và theo dõi phiên bản thiết kế.';

include __DIR__ . '/includes/auth_slider.php';
