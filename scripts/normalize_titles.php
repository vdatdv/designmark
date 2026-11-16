<?php
require_once __DIR__ . '/../api/db.php';


// Normalize titles
$pdo->exec("UPDATE users SET title = 'Thành viên' WHERE title IN ('DSADSA', 'Product Designer', 'UX Researcher', 'Bên liên quan') OR title IS NULL OR title = ''");
$pdo->exec("UPDATE users SET title = 'Người góp ý' WHERE lower(title) LIKE '%góp ý%'");
$pdo->exec("UPDATE users SET title = 'Quản lý thiết kế' WHERE lower(title) LIKE '%quản lý%'");
$pdo->exec("UPDATE users SET title = 'System Administrator' WHERE username = 'admin' OR lower(title) LIKE '%system%' OR lower(title) LIKE '%administrator%'");

mirror_to_sqlite("UPDATE users SET title = 'Thành viên' WHERE title IN ('DSADSA', 'Product Designer', 'UX Researcher', 'Bên liên quan') OR title IS NULL OR title = ''");
mirror_to_sqlite("UPDATE users SET title = 'Người góp ý' WHERE lower(title) LIKE '%góp ý%'");
mirror_to_sqlite("UPDATE users SET title = 'Quản lý thiết kế' WHERE lower(title) LIKE '%quản lý%'");
mirror_to_sqlite("UPDATE users SET title = 'System Administrator' WHERE username = 'admin' OR lower(title) LIKE '%system%' OR lower(title) LIKE '%administrator%'");

$stmt = $pdo->query("SELECT DISTINCT title, count(*) as cnt FROM users GROUP BY title ORDER BY title ASC");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
