<?php
// register.php - Register & Login Slider Page
require_once __DIR__ . '/api/db.php';

$session = get_current_user_session($pdo);
if ($session) {
    header('Location: dashboard.php');
    exit;
}

$initialMode = 'register';
$pageTitle = 'Đăng ký tài khoản — DesignMark';
$pageDescription = 'Tạo tài khoản DesignMark để quản lý dự án, ghim nhận xét và rà soát giao diện cùng đội ngũ.';

include __DIR__ . '/includes/auth_slider.php';
