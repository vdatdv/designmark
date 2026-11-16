<?php
// scripts/update_old_projects.php
// Cập nhật 2 dự án cũ (proj-shop và proj-ops) với đầy đủ thành viên, danh mục, phân quyền, và trạng thái góp ý chuẩn hóa.

require_once __DIR__ . '/../api/db.php';

function update_databases() {
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

    foreach ($dbs as $name => $db) {
        echo "=== CẬP NHẬT TRÊN DATABASE: $name ===\n";

        // Lấy danh sách ID của các thành viên chính
        $stmtUsers = $db->query("SELECT username, id FROM users");
        $userMap = [];
        while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
            $userMap[$u['username']] = $u['id'];
        }

        $anId = $userMap['an'] ?? 'user-an';
        $hoanghaiId = $userMap['hoanghai'] ?? 'user-f9bdccff-d7cb-447a-a3a8-4a760ac11562';
        $binhId = $userMap['binh'] ?? 'user-binh';
        $chiId = $userMap['chi'] ?? 'user-chi';
        $thanhntId = $userMap['thanhnt'] ?? 'user-7e08216f-1fc1-4bcd-86e8-c995452b6c8a';
        $hongphucId = $userMap['hongphuc'] ?? 'user-022a2b97-ff04-491e-8e0e-bcd9848bb03c';
        $minhtriId = $userMap['minhtri'] ?? 'user-minhtri';
        $lananhId = $userMap['lananh'] ?? 'user-lananh';
        $quocbaoId = $userMap['quocbao'] ?? 'user-quocbao';
        $adminId = $userMap['admin'] ?? 'user-admin';

        $now = date('Y-m-d\TH:i:s.000\Z');

        // 1. CẬP NHẬT PROJ-SHOP (Cửa hàng DesignMark)
        $stmtShop = $db->prepare("UPDATE projects SET name = :name, category = :cat, description = :desc, updated_at = :up WHERE id = 'proj-shop'");
        $stmtShop->execute([
            ':name' => 'Cửa hàng DesignMark',
            ':cat' => 'E-commerce',
            ':desc' => 'Nền tảng thương mại điện tử đa kênh: Đăng ký tài khoản, danh mục sản phẩm, giỏ hàng & cổng thanh toán trực tuyến bảo mật.',
            ':up' => $now
        ]);
        echo " -> Đã cập nhật thông tin dự án proj-shop\n";

        // Thêm/cập nhật đầy đủ thành viên cho proj-shop
        $shopMembers = [
            ['uid' => $anId, 'role' => 'manager'],
            ['uid' => $hoanghaiId, 'role' => 'manager'],
            ['uid' => $binhId, 'role' => 'reviewer'],
            ['uid' => $thanhntId, 'role' => 'reviewer'],
            ['uid' => $hongphucId, 'role' => 'reviewer'],
            ['uid' => $minhtriId, 'role' => 'reviewer'],
            ['uid' => $lananhId, 'role' => 'reviewer'],
            ['uid' => $chiId, 'role' => 'viewer'],
            ['uid' => $quocbaoId, 'role' => 'viewer'],
            ['uid' => $adminId, 'role' => 'viewer']
        ];

        foreach ($shopMembers as $m) {
            $memId = 'pm-shop-' . substr(md5($m['uid']), 0, 8);
            $check = $db->prepare("SELECT id FROM project_members WHERE project_id = 'proj-shop' AND user_id = :uid");
            $check->execute([':uid' => $m['uid']]);
            if ($check->fetch()) {
                $db->prepare("UPDATE project_members SET role = :role WHERE project_id = 'proj-shop' AND user_id = :uid")
                   ->execute([':role' => $m['role'], ':uid' => $m['uid']]);
            } else {
                $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, 'proj-shop', :uid, :role, :inv, :now)")
                   ->execute([':id' => $memId, ':uid' => $m['uid'], ':role' => $m['role'], ':inv' => $anId, ':now' => $now]);
            }
        }
        echo " -> Đã chuẩn hóa danh sách thành viên cho proj-shop\n";

        // Cập nhật phân bố trạng thái góp ý cho proj-shop (8 resolved, 6 open)
        $shopAnns = $db->query("SELECT id FROM annotations WHERE project_id = 'proj-shop' ORDER BY created_at ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (count($shopAnns) >= 10) {
            foreach ($shopAnns as $idx => $annId) {
                $st = ($idx < 8) ? 'resolved' : 'open';
                $db->prepare("UPDATE annotations SET status = :st WHERE id = :id")->execute([':st' => $st, ':id' => $annId]);
            }
            echo " -> Đã cân bằng trạng thái góp ý cho proj-shop: 8 đã xử lý, 6 chưa xử lý\n";
        }


        // 2. CẬP NHẬT PROJ-OPS (Bảng điều khiển nội bộ)
        // Đổi người sở hữu sang Hoàng Hải (user-hoanghai) để đa dạng hóa quyền sở hữu dự án cũ
        $stmtOps = $db->prepare("UPDATE projects SET name = :name, category = :cat, description = :desc, created_by = :cb, updated_at = :up WHERE id = 'proj-ops'");
        $stmtOps->execute([
            ':name' => 'Bảng điều khiển Vận hành & Phân tích',
            ':cat' => 'Vận hành / Nội bộ',
            ':desc' => 'Hệ thống quản trị và vận hành tập trung: Giám sát doanh thu real-time, xử lý đơn hàng, điều phối vận chuyển và cấu hình hệ thống.',
            ':cb' => $hoanghaiId,
            ':up' => $now
        ]);
        echo " -> Đã cập nhật thông tin và chủ sở hữu dự án proj-ops (Chủ sở hữu: Hoàng Hải)\n";

        // Thêm/cập nhật đầy đủ thành viên cho proj-ops
        $opsMembers = [
            ['uid' => $hoanghaiId, 'role' => 'manager'],
            ['uid' => $anId, 'role' => 'manager'],
            ['uid' => $binhId, 'role' => 'reviewer'],
            ['uid' => $thanhntId, 'role' => 'reviewer'],
            ['uid' => $hongphucId, 'role' => 'reviewer'],
            ['uid' => $lananhId, 'role' => 'reviewer'],
            ['uid' => $minhtriId, 'role' => 'reviewer'],
            ['uid' => $chiId, 'role' => 'viewer'],
            ['uid' => $quocbaoId, 'role' => 'viewer'],
            ['uid' => $adminId, 'role' => 'viewer']
        ];

        foreach ($opsMembers as $m) {
            $memId = 'pm-ops-' . substr(md5($m['uid']), 0, 8);
            $check = $db->prepare("SELECT id FROM project_members WHERE project_id = 'proj-ops' AND user_id = :uid");
            $check->execute([':uid' => $m['uid']]);
            if ($check->fetch()) {
                $db->prepare("UPDATE project_members SET role = :role WHERE project_id = 'proj-ops' AND user_id = :uid")
                   ->execute([':role' => $m['role'], ':uid' => $m['uid']]);
            } else {
                $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, 'proj-ops', :uid, :role, :inv, :now)")
                   ->execute([':id' => $memId, ':uid' => $m['uid'], ':role' => $m['role'], ':inv' => $hoanghaiId, ':now' => $now]);
            }
        }
        echo " -> Đã chuẩn hóa danh sách thành viên cho proj-ops\n";

        // Cập nhật phân bố trạng thái góp ý cho proj-ops (4 resolved, 2 open)
        $opsAnns = $db->query("SELECT id FROM annotations WHERE project_id = 'proj-ops' ORDER BY created_at ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (count($opsAnns) >= 4) {
            foreach ($opsAnns as $idx => $annId) {
                $st = ($idx < 4) ? 'resolved' : 'open';
                $db->prepare("UPDATE annotations SET status = :st WHERE id = :id")->execute([':st' => $st, ':id' => $annId]);
            }
            echo " -> Đã cân bằng trạng thái góp ý cho proj-ops: 4 đã xử lý, 2 chưa xử lý\n";
        }
    }

    // Cập nhật hệ thống thông báo chuẩn hóa
    if (file_exists(__DIR__ . '/update_notifications.php')) {
        require_once __DIR__ . '/update_notifications.php';
    }

    echo "\nHOÀN TẤT CẬP NHẬT CÁC DỰ ÁN CŨ VÀ THÔNG BÁO THÀNH CÔNG!\n";
}

update_databases();

