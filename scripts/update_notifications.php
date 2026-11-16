<?php
// scripts/update_notifications.php
// Cập nhật và chuẩn hóa danh sách thông báo phong phú cho admin và các thành viên trong DesignMark

require_once __DIR__ . '/../api/db.php';

function update_notifications() {
    global $pdo;

    $sqlitePath = __DIR__ . '/../data/database.sqlite';
    $sqlite = file_exists($sqlitePath) ? new PDO("sqlite:$sqlitePath") : null;
    if ($sqlite) {
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $dbs = ['supabase' => $pdo];
    if ($sqlite && $sqlite !== $pdo) {
        $dbs['sqlite'] = $sqlite;
    }

    foreach ($dbs as $dbName => $db) {
        echo "=== CẬP NHẬT THÔNG BÁO TRÊN: $dbName ===\n";

        // Lấy danh sách ID của các thành viên chính
        $stmtUsers = $db->query("SELECT username, id FROM users");
        $userMap = [];
        while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
            $userMap[$u['username']] = $u['id'];
        }

        $adminId = $userMap['admin'] ?? 'user-admin';
        $anId = $userMap['an'] ?? 'user-an';
        $hoanghaiId = $userMap['hoanghai'] ?? 'user-f9bdccff-d7cb-447a-a3a8-4a760ac11562';
        $binhId = $userMap['binh'] ?? 'user-binh';
        $chiId = $userMap['chi'] ?? 'user-chi';

        // Xóa các thông báo mẫu cũ của admin để thay thế bằng bộ thông báo chuẩn hóa, phong phú
        $db->prepare("DELETE FROM notifications WHERE user_id = :uid")->execute([':uid' => $adminId]);
        echo " -> Đã làm mới bảng thông báo cho tài khoản admin\n";

        $baseTime = time();

        $notifications = [
            [
                'id' => 'notif-admin-01',
                'user_id' => $adminId,
                'actor_id' => $binhId,
                'type' => 'new_annotation',
                'title' => 'Góp ý mới',
                'content' => 'Bình Trần đã thêm góp ý trên màn hình "Đăng ký tài khoản"',
                'project_id' => 'proj-shop',
                'screen_id' => 'scr-signup',
                'image_version_id' => 'ver-signup-2',
                'annotation_id' => 'ann-05',
                'link' => 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-2&annotation=ann-05',
                'is_read' => 0,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 15 * 60) // 15 phút trước
            ],
            [
                'id' => 'notif-admin-02',
                'user_id' => $adminId,
                'actor_id' => $anId,
                'type' => 'new_reply',
                'title' => 'Phản hồi mới',
                'content' => 'An Nguyễn đã trả lời góp ý #2: "Nhóm trường thông tin ở bố cục một cột đã gọn hơn..."',
                'project_id' => 'proj-shop',
                'screen_id' => 'scr-signup',
                'image_version_id' => 'ver-signup-2',
                'annotation_id' => 'ann-06',
                'link' => 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-2&annotation=ann-06',
                'is_read' => 0,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 45 * 60) // 45 phút trước
            ],
            [
                'id' => 'notif-admin-03',
                'user_id' => $adminId,
                'actor_id' => $hoanghaiId,
                'type' => 'new_version',
                'title' => 'Phiên bản mới',
                'content' => 'Dự án "Bảng điều khiển Vận hành & Phân tích" đã tải lên phiên bản v2 cho màn hình "Tổng quan"',
                'project_id' => 'proj-ops',
                'screen_id' => 'scr-dash',
                'image_version_id' => 'ver-dash-2',
                'annotation_id' => null,
                'link' => 'review.php?project=proj-ops&screen=scr-dash&version=ver-dash-2',
                'is_read' => 0,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 2 * 3600) // 2 giờ trước
            ],
            [
                'id' => 'notif-admin-04',
                'user_id' => $adminId,
                'actor_id' => $chiId,
                'type' => 'status_change',
                'title' => 'Trạng thái góp ý thay đổi',
                'content' => 'Chi Lê đã đánh dấu "Đã xử lý" cho góp ý #1 tại "Danh sách đơn"',
                'project_id' => 'proj-ops',
                'screen_id' => 'scr-orders',
                'image_version_id' => 'ver-orders-1',
                'annotation_id' => 'ann-18',
                'link' => 'review.php?project=proj-ops&screen=scr-orders&version=ver-orders-1&annotation=ann-18',
                'is_read' => 0,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 4 * 3600) // 4 giờ trước
            ],
            [
                'id' => 'notif-admin-05',
                'user_id' => $adminId,
                'actor_id' => $hoanghaiId,
                'type' => 'member_added',
                'title' => 'Được thêm vào dự án',
                'content' => 'Hoàng Hải đã thêm bạn vào dự án "Bảng điều khiển Vận hành & Phân tích" với vai trò Quản lý',
                'project_id' => 'proj-ops',
                'screen_id' => null,
                'image_version_id' => null,
                'annotation_id' => null,
                'link' => 'project.php?id=proj-ops',
                'is_read' => 1,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 24 * 3600) // 1 ngày trước
            ],
            [
                'id' => 'notif-admin-06',
                'user_id' => $adminId,
                'actor_id' => $anId,
                'type' => 'member_added',
                'title' => 'Được thêm vào dự án',
                'content' => 'An Nguyễn đã thêm bạn vào dự án "Cửa hàng DesignMark" với vai trò Quản lý',
                'project_id' => 'proj-shop',
                'screen_id' => null,
                'image_version_id' => null,
                'annotation_id' => null,
                'link' => 'project.php?id=proj-shop',
                'is_read' => 1,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 48 * 3600) // 2 ngày trước
            ],
            [
                'id' => 'notif-admin-07',
                'user_id' => $adminId,
                'actor_id' => $binhId,
                'type' => 'status_change',
                'title' => 'Trạng thái góp ý thay đổi',
                'content' => 'Bình Trần đã đánh dấu "Đã xử lý" cho góp ý #2 tại "Danh sách đơn"',
                'project_id' => 'proj-ops',
                'screen_id' => 'scr-orders',
                'image_version_id' => 'ver-orders-1',
                'annotation_id' => 'ann-19',
                'link' => 'review.php?project=proj-ops&screen=scr-orders&version=ver-orders-1&annotation=ann-19',
                'is_read' => 1,
                'created_at' => date('Y-m-d\TH:i:s.000\Z', $baseTime - 72 * 3600) // 3 ngày trước
            ]
        ];

        $stmtInsert = $db->prepare("
            INSERT INTO notifications (id, user_id, actor_id, type, title, content, project_id, screen_id, image_version_id, annotation_id, link, is_read, created_at)
            VALUES (:id, :uid, :actor, :type, :title, :content, :pid, :sid, :vid, :aid, :link, :read, :cat)
        ");

        foreach ($notifications as $n) {
            $stmtInsert->execute([
                ':id' => $n['id'],
                ':uid' => $n['user_id'],
                ':actor' => $n['actor_id'],
                ':type' => $n['type'],
                ':title' => $n['title'],
                ':content' => $n['content'],
                ':pid' => $n['project_id'],
                ':sid' => $n['screen_id'],
                ':vid' => $n['image_version_id'],
                ':aid' => $n['annotation_id'],
                ':link' => $n['link'],
                ':read' => $n['is_read'],
                ':cat' => $n['created_at']
            ]);
        }

        echo " -> Đã thêm thành công " . count($notifications) . " thông báo phong phú cho admin (4 chưa đọc, 3 đã đọc)\n";
    }

    echo "\nHOÀN TẤT CẬP NHẬT THÔNG BÁO THÀNH CÔNG!\n";
}

update_notifications();
