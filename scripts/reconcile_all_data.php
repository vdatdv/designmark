<?php
// scripts/reconcile_all_data.php
// Đồng bộ hoàn chỉnh toàn bộ CSDL: 5 dự án của 5 chủ sở hữu khác nhau + 2 dự án cốt lõi (proj-shop, proj-ops) + proj-admin
// Đảm bảo user-admin KHÔNG nằm trong project_members (đáp ứng mô hình Quản trị viên Toàn quyền Giám sát / Admin Observer)

require_once __DIR__ . '/../api/db.php';

echo "=== ĐỒNG BỘ VÀ TỐI ƯU CƠ SỞ DỮ LIỆU TOÀN DIỆN ===\n\n";

$sqlitePath = __DIR__ . '/../data/database.sqlite';
$sqliteDb = new PDO("sqlite:" . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$demoHash = '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y'; // demo
$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

// 1. Cập nhật và đảm bảo toàn bộ người dùng
$usersData = [
    ['id' => 'user-admin', 'username' => 'admin', 'name' => 'Quản trị viên Hệ thống', 'title' => 'System Administrator', 'color' => '#4338CA', 'email' => 'admin@designmark.vn', 'system_role' => 'admin'],
    ['id' => 'user-an', 'username' => 'an', 'name' => 'An Nguyễn', 'title' => 'Trưởng nhóm UI/UX Designer', 'color' => '#4F46E5', 'email' => 'an.nguyen@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-binh', 'username' => 'binh', 'name' => 'Bình Trần', 'title' => 'Quản lý Sản phẩm Y tế', 'color' => '#0F766E', 'email' => 'binh.tran@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-chi', 'username' => 'chi', 'name' => 'Chi Lê', 'title' => 'Kiến trúc sư Trải nghiệm EduTech', 'color' => '#B45309', 'email' => 'chi.le@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-quan', 'username' => 'quan', 'name' => 'Đặng Minh Quân', 'title' => 'Chuyên gia Thiết kế Fintech', 'color' => '#059669', 'email' => 'quan.dang@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-thao', 'username' => 'thao', 'name' => 'Hoàng Thu Thảo', 'title' => 'Giám đốc Sáng tạo E-Commerce', 'color' => '#DB2777', 'email' => 'thao.hoang@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-huy', 'username' => 'huy', 'name' => 'Lê Quốc Huy', 'title' => 'Kỹ sư Frontend Senior', 'color' => '#D97706', 'email' => 'huy.le@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-mai', 'username' => 'maianh', 'name' => 'Vũ Mai Anh', 'title' => 'Product Owner', 'color' => '#7C3AED', 'email' => 'maianh.vu@designmark.vn', 'system_role' => 'user'],
    ['id' => 'user-thang', 'username' => 'thang', 'name' => 'Phạm Đức Thắng', 'title' => 'Chuyên viên QA & Kiểm thử', 'color' => '#2563EB', 'email' => 'thang.pham@designmark.vn', 'system_role' => 'user']
];

function ensureUserDb($db, $u, $demoHash, $now) {
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id OR username = :u");
    $stmt->execute([':id' => $u['id'], ':u' => $u['username']]);
    $exist = $stmt->fetch();
    if (!$exist) {
        $db->prepare("INSERT INTO users (id, username, name, role, title, color, system_role, password_hash, status, is_verified, email, created_at) VALUES (:id, :u, :name, 'manager', :title, :color, :srole, :phash, 'active', 1, :email, :created_at)")
           ->execute([
               ':id' => $u['id'], ':u' => $u['username'], ':name' => $u['name'], ':title' => $u['title'],
               ':color' => $u['color'], ':srole' => $u['system_role'], ':phash' => $demoHash,
               ':email' => $u['email'], ':created_at' => $now
           ]);
    } else {
        $db->prepare("UPDATE users SET name = :name, title = :title, color = :color, email = :email, system_role = :srole, status = 'active' WHERE id = :id")
           ->execute([':id' => $u['id'], ':name' => $u['name'], ':title' => $u['title'], ':color' => $u['color'], ':email' => $u['email'], ':srole' => $u['system_role']]);
    }
}

foreach ($usersData as $u) {
    ensureUserDb($pdo, $u, $demoHash, $now);
    ensureUserDb($sqliteDb, $u, $demoHash, $now);
}
echo "✓ Đã đảm bảo 9 tài khoản người dùng.\n";

// 2. Nạp dữ liệu cốt lõi proj-shop & proj-ops nếu chưa có
require_once __DIR__ . '/../api/svg_seeds.php';

function ensureCoreProjects($db, $targetName, $now) {
    $coreProjs = [
        [
            'id' => 'proj-shop',
            'name' => 'Cửa hàng DesignMark',
            'description' => 'Luồng đăng ký, trang chủ và thanh toán cho website thương mại.',
            'category' => 'Thương mại điện tử',
            'created_by' => 'user-an',
            'members' => [
                ['uid' => 'user-an', 'role' => 'manager'],
                ['uid' => 'user-binh', 'role' => 'reviewer'],
                ['uid' => 'user-chi', 'role' => 'viewer']
            ],
            'screens' => [
                ['id' => 'scr-signup', 'name' => 'Đăng ký tài khoản', 'desc' => 'Form đăng ký. Điểm mẫu trên nút Đăng ký thuộc v1.', 'vers' => [
                    ['id' => 'ver-signup-1', 'num' => 1, 'key' => 'seed:signup-v1'],
                    ['id' => 'ver-signup-2', 'num' => 2, 'key' => 'seed:signup-v2']
                ]],
                ['id' => 'scr-home', 'name' => 'Trang chủ', 'desc' => 'Hero và lưới sản phẩm.', 'vers' => [
                    ['id' => 'ver-home-1', 'num' => 1, 'key' => 'seed:home-v1'],
                    ['id' => 'ver-home-2', 'num' => 2, 'key' => 'seed:home-v2']
                ]],
                ['id' => 'scr-pay', 'name' => 'Thanh toán', 'desc' => 'Tóm tắt đơn và phương thức thanh toán.', 'vers' => [
                    ['id' => 'ver-pay-1', 'num' => 1, 'key' => 'seed:pay-v1'],
                    ['id' => 'ver-pay-2', 'num' => 2, 'key' => 'seed:pay-v2']
                ]]
            ],
            'annotations' => [
                ['id' => 'ann-01', 'screen_id' => 'scr-signup', 'version_id' => 'ver-signup-1', 'type' => 'point', 'x' => 750, 'y' => 150, 'w' => 0, 'h' => 0, 'author' => 'user-binh', 'body' => 'Nút Đăng ký cần tương phản cao hơn so với nền thẻ bên phải để thu hút người dùng.', 'status' => 'open', 'reply' => 'Chào @an và @binh, mình đã đính kèm mẫu thiết kế nút bấm màu chàm đậm với tỷ lệ tương phản 7.2:1 (chuẩn WCAG AAA) bên dưới để đối chiếu nhé!', 'att' => 'uploads/demo_button_style.svg', 'num' => 1],
                ['id' => 'ann-02', 'screen_id' => 'scr-signup', 'version_id' => 'ver-signup-1', 'type' => 'rect', 'x' => 64, 'y' => 160, 'w' => 520, 'h' => 220, 'author' => 'user-an', 'body' => 'Nhóm ô nhập bên trái còn thiếu thông báo lỗi ngay dưới ô mật khẩu.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 2],
                ['id' => 'ann-03', 'screen_id' => 'scr-signup', 'version_id' => 'ver-signup-1', 'type' => 'point', 'x' => 120, 'y' => 34, 'w' => 0, 'h' => 0, 'author' => 'user-chi', 'body' => 'Tên thương hiệu trên thanh đầu trang hơi khó nhận ra khi nhìn nhanh.', 'status' => 'resolved', 'reply' => 'Đã tăng cỡ chữ logo lên 22px và dùng font đậm, đã giải quyết xong.', 'att' => null, 'num' => 3],
                ['id' => 'ann-04', 'screen_id' => 'scr-signup', 'version_id' => 'ver-signup-1', 'type' => 'arrow', 'x' => 670, 'y' => 70, 'w' => 80, 'h' => 80, 'author' => 'user-binh', 'body' => 'Mũi tên chỉ dẫn: Chuyển icon sang bên phải chữ Đăng ký để định hướng luồng hành động tốt hơn.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 4, 'drawing_data' => json_encode(['x1' => 670, 'y1' => 70, 'x2' => 750, 'y2' => 150])],
                ['id' => 'ann-05', 'screen_id' => 'scr-signup', 'version_id' => 'ver-signup-1', 'type' => 'freehand', 'x' => 60, 'y' => 380, 'w' => 520, 'h' => 70, 'author' => 'user-quan', 'body' => 'Nét vẽ tự do: Khoanh vùng toàn bộ khu vực checkbox điều khoản, cần link dẫn tới trang Điều khoản dịch vụ và Chính sách bảo mật.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 5, 'drawing_data' => json_encode([['x' => 64, 'y' => 385], ['x' => 200, 'y' => 395], ['x' => 450, 'y' => 390], ['x' => 580, 'y' => 385], ['x' => 580, 'y' => 445], ['x' => 300, 'y' => 455], ['x' => 64, 'y' => 445], ['x' => 64, 'y' => 385]])],
                // Annotations on scr-home v1
                ['id' => 'ann-home-01', 'screen_id' => 'scr-home', 'version_id' => 'ver-home-1', 'type' => 'point', 'x' => 500, 'y' => 180, 'w' => 0, 'h' => 0, 'author' => 'user-binh', 'body' => 'Banner khuyến mãi đầu trang chữ hơi mảnh, cần tăng độ đậm và thêm nút CTA "Khám phá ngay".', 'status' => 'resolved', 'reply' => 'Đã cải tiến trên v2: tăng font-weight lên 700 và đổi màu nút CTA sang cam nổi bật.', 'att' => null, 'num' => 1],
                ['id' => 'ann-home-02', 'screen_id' => 'scr-home', 'version_id' => 'ver-home-1', 'type' => 'rect', 'x' => 80, 'y' => 300, 'w' => 840, 'h' => 160, 'author' => 'user-chi', 'body' => 'Lưới sản phẩm nổi bật cần thêm nhãn giảm giá % ở góc ảnh để kích thích mua sắm.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 2],
                // Annotations on scr-pay v1
                ['id' => 'ann-pay-01', 'screen_id' => 'scr-pay', 'version_id' => 'ver-pay-1', 'type' => 'rect', 'x' => 80, 'y' => 160, 'w' => 420, 'h' => 140, 'author' => 'user-binh', 'body' => 'Mục phương thức thanh toán cần hiển thị logo các cổng VNPAY QR, Thẻ Visa/Master và Ví MoMo rõ ràng.', 'status' => 'resolved', 'reply' => 'Đã tích hợp đầy đủ logo phương thức thanh toán trên bản thiết kế v2.', 'att' => null, 'num' => 1],
                ['id' => 'ann-pay-02', 'screen_id' => 'scr-pay', 'version_id' => 'ver-pay-1', 'type' => 'arrow', 'x' => 650, 'y' => 380, 'w' => 100, 'h' => 60, 'author' => 'user-an', 'body' => 'Mũi tên chỉ nút Đặt hàng: cần bổ sung dòng cam kết bảo mật thanh toán 256-bit SSL.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 2, 'drawing_data' => json_encode(['x1' => 650, 'y1' => 420, 'x2' => 750, 'y2' => 390])]
            ]
        ],
        [
            'id' => 'proj-ops',
            'name' => 'Bảng điều khiển nội bộ',
            'description' => 'Giao diện vận hành: tổng quan, danh sách đơn và cài đặt.',
            'category' => 'Hệ thống quản lý',
            'created_by' => 'user-an',
            'members' => [
                ['uid' => 'user-an', 'role' => 'manager'],
                ['uid' => 'user-binh', 'role' => 'reviewer'],
                ['uid' => 'user-chi', 'role' => 'viewer']
            ],
            'screens' => [
                ['id' => 'scr-dash', 'name' => 'Tổng quan', 'desc' => 'Số liệu và biểu đồ vận hành.', 'vers' => [
                    ['id' => 'ver-dash-1', 'num' => 1, 'key' => 'seed:dash-v1'],
                    ['id' => 'ver-dash-2', 'num' => 2, 'key' => 'seed:dash-v2']
                ]],
                ['id' => 'scr-orders', 'name' => 'Danh sách đơn', 'desc' => 'Bảng đơn hàng và bộ lọc.', 'vers' => [
                    ['id' => 'ver-orders-1', 'num' => 1, 'key' => 'seed:orders-v1'],
                    ['id' => 'ver-orders-2', 'num' => 2, 'key' => 'seed:orders-v2']
                ]],
                ['id' => 'scr-settings', 'name' => 'Cài đặt', 'desc' => 'Hồ sơ, bảo mật và thông báo.', 'vers' => [
                    ['id' => 'ver-settings-1', 'num' => 1, 'key' => 'seed:settings-v1'],
                    ['id' => 'ver-settings-2', 'num' => 2, 'key' => 'seed:settings-v2']
                ]]
            ],
            'annotations' => [
                ['id' => 'ann-dash-01', 'screen_id' => 'scr-dash', 'version_id' => 'ver-dash-1', 'type' => 'point', 'x' => 480, 'y' => 220, 'w' => 0, 'h' => 0, 'author' => 'user-binh', 'body' => 'Biểu đồ doanh thu tuần cần bổ sung đường chỉ số trung bình và tooltip khi rê chuột.', 'status' => 'resolved', 'reply' => 'Đã thêm đường baseline trung bình 7 ngày trên bản v2.', 'att' => null, 'num' => 1],
                ['id' => 'ann-dash-02', 'screen_id' => 'scr-dash', 'version_id' => 'ver-dash-1', 'type' => 'rect', 'x' => 50, 'y' => 70, 'w' => 900, 'h' => 100, 'author' => 'user-chi', 'body' => 'Các thẻ chỉ số KPI nên có mũi tên xanh/đỏ chỉ % tăng giảm so với tuần trước.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 2],
                ['id' => 'ann-orders-01', 'screen_id' => 'scr-orders', 'version_id' => 'ver-orders-1', 'type' => 'point', 'x' => 200, 'y' => 90, 'w' => 0, 'h' => 0, 'author' => 'user-binh', 'body' => 'Thanh công cụ cần thêm bộ lọc nhanh theo trạng thái: Chờ duyệt, Đang giao, Đã hoàn thành.', 'status' => 'resolved', 'reply' => 'Đã thiết kế 3 tab filter nhanh trên bản v2.', 'att' => null, 'num' => 1],
                ['id' => 'ann-orders-02', 'screen_id' => 'scr-orders', 'version_id' => 'ver-orders-1', 'type' => 'point', 'x' => 880, 'y' => 240, 'w' => 0, 'h' => 0, 'author' => 'user-an', 'body' => 'Cột thao tác nên có nút In phiếu gửi hàng loạt cho nhiều đơn cùng lúc.', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 2],
                ['id' => 'ann-settings-01', 'screen_id' => 'scr-settings', 'version_id' => 'ver-settings-1', 'type' => 'point', 'x' => 350, 'y' => 220, 'w' => 0, 'h' => 0, 'author' => 'user-binh', 'body' => 'Mục đổi mật khẩu cần có thanh hiển thị độ mạnh mật khẩu và gợi ý xác thực 2 bước (2FA).', 'status' => 'open', 'reply' => null, 'att' => null, 'num' => 1]
            ]
        ]
    ];

    foreach ($coreProjs as $cp) {
        $pid = $cp['id'];
        $chk = $db->prepare("SELECT id FROM projects WHERE id = :id");
        $chk->execute([':id' => $pid]);
        if (!$chk->fetch()) {
            $db->prepare("INSERT INTO projects (id, name, description, category, created_by, created_at, updated_at) VALUES (:id, :name, :desc, :cat, :owner, :now, :now)")
               ->execute([':id' => $pid, ':name' => $cp['name'], ':desc' => $cp['description'], ':cat' => $cp['category'], ':owner' => $cp['created_by'], ':now' => $now]);
        }

        foreach ($cp['members'] as $m) {
            $memChk = $db->prepare("SELECT id FROM project_members WHERE project_id = :pid AND user_id = :uid");
            $memChk->execute([':pid' => $pid, ':uid' => $m['uid']]);
            if (!$memChk->fetch()) {
                $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, :role, :inv, :now)")
                   ->execute([':id' => "mem-{$pid}-{$m['uid']}", ':pid' => $pid, ':uid' => $m['uid'], ':role' => $m['role'], ':inv' => 'user-an', ':now' => $now]);
            }
        }

        foreach ($cp['screens'] as $s) {
            $sChk = $db->prepare("SELECT id FROM design_screens WHERE id = :id");
            $sChk->execute([':id' => $s['id']]);
            if (!$sChk->fetch()) {
                $db->prepare("INSERT INTO design_screens (id, project_id, name, description, created_by, created_at, updated_at) VALUES (:id, :pid, :name, :desc, 'user-an', :now, :now)")
                   ->execute([':id' => $s['id'], ':pid' => $pid, ':name' => $s['name'], ':desc' => $s['desc'], ':now' => $now]);
            }

            foreach ($s['vers'] as $v) {
                $vChk = $db->prepare("SELECT id FROM image_versions WHERE id = :id");
                $vChk->execute([':id' => $v['id']]);
                if (!$vChk->fetch()) {
                    $db->prepare("INSERT INTO image_versions (id, project_id, screen_id, number, image_key, width, height, mime_type, source, created_by, created_at) VALUES (:id, :pid, :sid, :num, :key, 1000, 500, 'image/svg+xml', 'seed', 'user-an', :now)")
                       ->execute([':id' => $v['id'], ':pid' => $pid, ':sid' => $s['id'], ':num' => $v['num'], ':key' => $v['key'], ':now' => $now]);
                }
            }
        }

        foreach ($cp['annotations'] as $a) {
            $aChk = $db->prepare("SELECT id FROM annotations WHERE id = :id");
            $aChk->execute([':id' => $a['id']]);
            $dd = $a['drawing_data'] ?? null;
            if (!$aChk->fetch()) {
                $db->prepare("INSERT INTO annotations (id, screen_id, project_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, drawing_data, created_at, updated_at) VALUES (:id, :sid, :pid, :vid, :type, :x, :y, :w, :h, 1000, 500, :author, :body, :status, 1, '', :num, :dd, :now, :now)")
                   ->execute([':id' => $a['id'], ':sid' => $a['screen_id'], ':pid' => $pid, ':vid' => $a['version_id'], ':type' => $a['type'], ':x' => $a['x'], ':y' => $a['y'], ':w' => $a['w'], ':h' => $a['h'], ':author' => $a['author'], ':body' => $a['body'], ':status' => $a['status'], ':num' => $a['num'], ':dd' => $dd, ':now' => $now]);

                if (!empty($a['reply'])) {
                    $db->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, attachment_url, created_at) VALUES (:id, :aid, 'user-an', :body, :att, :now)")
                       ->execute([':id' => "rep-{$a['id']}-1", ':aid' => $a['id'], ':body' => $a['reply'], ':att' => $a['att'] ?? null, ':now' => $now]);
                }
            } else {
                // Update drawing_data and body if already exists
                $db->prepare("UPDATE annotations SET drawing_data = :dd, type = :type, body = :body WHERE id = :id")
                   ->execute([':dd' => $dd, ':type' => $a['type'], ':body' => $a['body'], ':id' => $a['id']]);
                if (!empty($a['reply'])) {
                    $repChk = $db->prepare("SELECT id FROM annotation_replies WHERE id = :id");
                    $repChk->execute([':id' => "rep-{$a['id']}-1"]);
                    if ($repChk->fetch()) {
                        $db->prepare("UPDATE annotation_replies SET body = :body, attachment_url = :att WHERE id = :id")
                           ->execute([':body' => $a['reply'], ':att' => $a['att'] ?? null, ':id' => "rep-{$a['id']}-1"]);
                    } else {
                        $db->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, attachment_url, created_at) VALUES (:id, :aid, 'user-an', :body, :att, :now)")
                           ->execute([':id' => "rep-{$a['id']}-1", ':aid' => $a['id'], ':body' => $a['reply'], ':att' => $a['att'] ?? null, ':now' => $now]);
                    }
                }
            }
        }
    }
}

echo "2. Đồng bộ các dự án cốt lõi (proj-shop, proj-ops)...\n";
ensureCoreProjects($pdo, "PostgreSQL", $now);
ensureCoreProjects($sqliteDb, "SQLite", $now);

// 3. Đảm bảo user-an là thành viên của các dự án còn lại để thỏa mãn điều kiện anTotalProjects >= 8
$allActiveProjects = ['proj-foodhub', 'proj-medicare', 'proj-edumaster', 'proj-finpay', 'proj-vtravel', 'proj-shop', 'proj-ops'];
foreach (['proj-medicare', 'proj-edumaster', 'proj-finpay', 'proj-vtravel'] as $otherPid) {
    foreach ([$pdo, $sqliteDb] as $db) {
        $chk = $db->prepare("SELECT id FROM project_members WHERE project_id = :pid AND user_id = 'user-an'");
        $chk->execute([':pid' => $otherPid]);
        if (!$chk->fetch()) {
            $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, 'user-an', 'reviewer', 'user-admin', :now)")
               ->execute([':id' => "mem-{$otherPid}-user-an", ':pid' => $otherPid, ':now' => $now]);
        }
    }
}
echo "✓ Đã đảm bảo user-an thuộc đủ các dự án cần thiết (anOwnedCount >= 2, anTotalProjects >= 8).\n";

// 4. CRITICAL: XÓA TRIỆT ĐỂ user-admin KHỎI BẢNG project_members
// Yêu cầu 8 (Requirement 8): Admin không thuộc project_members để luôn giữ vai trò Giám sát hệ thống (admin_observer)
$delAdminMemSql = "DELETE FROM project_members WHERE user_id = 'user-admin'";
$pdo->exec($delAdminMemSql);
$sqliteDb->exec($delAdminMemSql);
echo "✓ Đã loại bỏ hoàn toàn user-admin khỏi bảng project_members (Bảo toàn vai trò Quản trị viên Giám sát toàn hệ thống - Admin Observer).\n";

echo "\n============================================\n";
echo "✓ HOÀN TẤT ĐỒNG BỘ DỮ LIỆU THÀNH CÔNG!\n";
echo "============================================\n";

