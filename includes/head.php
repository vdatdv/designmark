<?php
// includes/head.php - Common HTML head tags
if (!isset($pageTitle)) {
    $pageTitle = 'DesignMark';
}
if (!isset($pageDescription)) {
    $pageDescription = 'DesignMark — góp ý trực tiếp trên ảnh và bản thiết kế giao diện.';
}
$currentUserSession = isset($pdo) ? get_current_user_session($pdo) : null;
?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M16 3a11 11 0 0 0-11 11c0 8.5 11 15 11 15s11-6.5 11-15c0-6.075-4.925-11-11-11z' fill='%23d6245c'/%3E%3Cpath d='M16 8a6 6 0 0 0-6 6c0 4.5 6 9 6 9V8z' fill='%23ffffff'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/app.css?v=<?= filemtime(__DIR__ . '/../css/app.css') ?>">
  <link rel="stylesheet" href="css/redesign.css?v=<?= filemtime(__DIR__ . '/../css/redesign.css') ?>">
  <script>
    (function() {
      try {
        var savedTheme = localStorage.getItem('dm_theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
      } catch (e) {}
    })();
    window.DESIGNMARK_SESSION = <?= json_encode($currentUserSession) ?>;
  </script>

