<?php
require_once __DIR__ . '/../api/db.php';


$pdo->exec("UPDATE users SET title = 'Thành viên (Chỉ xem)' WHERE title = 'Thành viên' OR title = 'Bên liên quan' OR title = 'DSADSA' OR title IS NULL OR title = ''");
mirror_to_sqlite("UPDATE users SET title = 'Thành viên (Chỉ xem)' WHERE title = 'Thành viên' OR title = 'Bên liên quan' OR title = 'DSADSA' OR title IS NULL OR title = ''");

$stmt = $pdo->query("SELECT DISTINCT title, count(*) as cnt FROM users GROUP BY title ORDER BY title ASC");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
