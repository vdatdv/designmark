<?php
// scripts/seed_admin_20_screens.php - Ensure Admin's Project has exactly 20 screens

require_once __DIR__ . '/../api/db.php';

$projectId = 'proj-admin-designmark';
$adminId = 'user-admin';

// Check that project exists
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) {
    die("Error: Project $projectId not found.\n");
}

echo "Found project: " . $project['name'] . " (ID: $projectId)\n";

// Target 20 screens definition
$screenDefs = [
    [
        'id' => 'scr-proj-admin-designmark-1',
        'sort_order' => 1,
        'name' => '01. Bảng điều khiển Tổng quan (Dashboard)',
        'description' => 'Hiển thị tóm tắt tiến độ phản hồi, tỷ lệ góp ý đã giải quyết, biểu đồ hoạt động và danh sách các màn hình đang chờ đánh giá.',
        'v1' => 'seed:dash-v1',
        'v2' => 'seed:dash-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-2',
        'sort_order' => 2,
        'name' => '02. Không gian Đánh giá & Góp ý (Review Canvas)',
        'description' => 'Hỗ trợ tương tác ghim tọa độ tuyệt đối, khoanh vùng chi tiết, xem luồng thảo luận real-time và công cụ so sánh 2 phiên bản trước/sau.',
        'v1' => 'seed:home-v1',
        'v2' => 'seed:home-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-3',
        'sort_order' => 3,
        'name' => '03. Cổng Đăng nhập & Xác thực Tài khoản (Authentication)',
        'description' => 'Xác thực đa yếu tố, giao diện thích ứng Dark/Light Mode, xử lý khóa tài khoản tự động khi đăng nhập sai nhiều lần.',
        'v1' => 'seed:signup-v1',
        'v2' => 'seed:signup-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-4',
        'sort_order' => 4,
        'name' => '04. Quản lý Thành viên & Phân quyền Dự án (Permissions & Roles)',
        'description' => 'Bảng ma trận phân quyền theo vai trò (Owner, Designer, Reviewer, Viewer). Kiểm soát chặt chẽ quyền tạo góp ý và quản lý dự án.',
        'v1' => 'seed:settings-v1',
        'v2' => 'seed:settings-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-5',
        'sort_order' => 5,
        'name' => '05. Danh sách Màn hình & Phiên bản (Screen & Version Explorer)',
        'description' => 'Bộ sưu tập màn hình dạng lưới Grid và danh sách List view, tích hợp phân trang số, bộ lọc theo tiến độ và công cụ tìm kiếm tức thì.',
        'v1' => 'seed:dash-v1',
        'v2' => 'seed:dash-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-6',
        'sort_order' => 6,
        'name' => '06. So sánh Phiên bản Song song (Side-by-Side Compare)',
        'description' => 'Chế độ xem so sánh 2 phiên bản cạnh nhau (Split View) và thanh trượt trực quan (Slider) để rà soát thay đổi chi tiết từng pixel.',
        'v1' => 'seed:home-v1',
        'v2' => 'seed:home-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-7',
        'sort_order' => 7,
        'name' => '07. Trung tâm Thông báo Hoạt động (Activity & Notifications)',
        'description' => 'Thông báo tức thì khi có thành viên mới, góp ý được ghim hoặc trạng thái phản hồi chuyển sang Đã giải quyết.',
        'v1' => 'seed:orders-v1',
        'v2' => 'seed:orders-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-8',
        'sort_order' => 8,
        'name' => '08. Quản lý Thùng rác Dự án (Trash Bin & Soft Delete)',
        'description' => 'Lưu trữ các dự án đã xóa tạm, hỗ trợ khôi phục dự án trong vòng 30 ngày hoặc xóa vĩnh viễn với cơ chế xác thực an toàn.',
        'v1' => 'seed:settings-v1',
        'v2' => 'seed:settings-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-9',
        'sort_order' => 9,
        'name' => '09. Cài đặt Hồ sơ Cá nhân & Mật khẩu (Profile & Security)',
        'description' => 'Thay đổi thông tin người dùng, cập nhật họ tên, đổi mật khẩu bảo mật và kiểm tra nhật ký các phiên đăng nhập gần nhất.',
        'v1' => 'seed:signup-v1',
        'v2' => 'seed:signup-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-10',
        'sort_order' => 10,
        'name' => '10. Báo cáo & Thống kê Góp ý (Feedback Analytics)',
        'description' => 'Biểu đồ trực quan tỷ lệ phản hồi tồn đọng, thời gian xử lý trung bình theo từng màn hình và hiệu suất của người đánh giá.',
        'v1' => 'seed:finpay-dashboard-v1',
        'v2' => 'seed:finpay-dashboard-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-11',
        'sort_order' => 11,
        'name' => '11. Mobile App: Màn hình Luồng Tin (Mobile Design Stream)',
        'description' => 'Trải nghiệm duyệt thiết kế tối ưu cho thiết bị di động, thao tác vuốt chạm để xem nhanh các màn hình và phiên bản mới cập nhật.',
        'v1' => 'seed:foodhub-home-v1',
        'v2' => 'seed:foodhub-home-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-12',
        'sort_order' => 12,
        'name' => '12. Mobile App: Luồng Thảo luận & Góp ý (Mobile Feedback Thread)',
        'description' => 'Giao diện trao đổi luồng góp ý dạng tin nhắn trên điện thoại, hỗ trợ phản hồi nhanh, gửi đính kèm và nhắc tên thành viên.',
        'v1' => 'seed:foodhub-cart-v1',
        'v2' => 'seed:foodhub-cart-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-13',
        'sort_order' => 13,
        'name' => '13. Mobile App: Chế độ Xem Toàn màn hình (Full Screen Preview)',
        'description' => 'Chế độ xem không viền (Full Screen), thu phóng mượt mà chuẩn tỉ lệ hiển thị trên màn hình cảm ứng di động.',
        'v1' => 'seed:foodhub-tracking-v1',
        'v2' => 'seed:foodhub-tracking-v1',
    ],
    [
        'id' => 'scr-proj-admin-designmark-14',
        'sort_order' => 14,
        'name' => '14. Cấu hình Tích hợp Webhook & Slack (Integrations & Webhooks)',
        'description' => 'Đồng bộ thông báo góp ý mới sang kênh Slack/Discord của đội ngũ phát triển và xuất dữ liệu tự động theo sự kiện.',
        'v1' => 'seed:edumaster-classroom-v1',
        'v2' => 'seed:edumaster-classroom-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-15',
        'sort_order' => 15,
        'name' => '15. Xuất Biên bản Nghiệm thu PDF & CSV (Export & Audit Logs)',
        'description' => 'Xuất biên bản nghiệm thu thiết kế, báo cáo toàn bộ danh sách góp ý cùng tọa độ, lịch sử trạng thái và ảnh chụp giao diện.',
        'v1' => 'seed:edumaster-exam-v1',
        'v2' => 'seed:edumaster-exam-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-16',
        'sort_order' => 16,
        'name' => '16. Quản trị Tài khoản Hệ thống (User Management Console)',
        'description' => 'Khu vực quản trị dành cho Quản trị viên: phân quyền hệ thống, theo dõi tài khoản, khóa và mở khóa tài khoản an toàn.',
        'v1' => 'seed:settings-v1',
        'v2' => 'seed:settings-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-17',
        'sort_order' => 17,
        'name' => '17. Thư viện Thành phần & Design Tokens (UI Component Library)',
        'description' => 'Quản lý bảng mã màu, kiểu chữ, quy cách khoảng cách và các thành phần giao diện tái sử dụng trong hệ sinh thái DesignMark.',
        'v1' => 'seed:vtravel-explore-v1',
        'v2' => 'seed:vtravel-explore-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-18',
        'sort_order' => 18,
        'name' => '18. Cổng Thanh toán & Gói Thuê bao (Billing & Subscription)',
        'description' => 'Giao diện quản lý gói dung lượng lưu trữ, nâng cấp tính năng dành cho doanh nghiệp và lịch sử hóa đơn dịch vụ.',
        'v1' => 'seed:pay-v1',
        'v2' => 'seed:pay-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-19',
        'sort_order' => 19,
        'name' => '19. Trung tâm Trợ giúp & Hướng dẫn Sử dụng (Help Center & Onboarding)',
        'description' => 'Tài liệu tương tác hướng dẫn quy trình tạo dự án, cách ghim tọa độ, khoanh vùng giao diện và quy chuẩn phản biện thiết kế.',
        'v1' => 'seed:medicare-booking-v1',
        'v2' => 'seed:medicare-booking-v2',
    ],
    [
        'id' => 'scr-proj-admin-designmark-20',
        'sort_order' => 20,
        'name' => '20. Thiết lập API & Khóa Truy cập Nhà phát triển (Developer API Keys)',
        'description' => 'Khởi tạo API token cá nhân, phân quyền truy cập theo phạm vi (read/write) và tài liệu Swagger tích hợp.',
        'v1' => 'seed:medicare-records-v1',
        'v2' => 'seed:medicare-records-v2',
    ]
];

// Open SQLite database directly as well to sync both
$sqlitePdo = new PDO('sqlite:' . __DIR__ . '/../data/database.sqlite');
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dbs = ['primary' => $pdo, 'sqlite' => $sqlitePdo];

$now = '2026-10-31T12:00:00.000Z';

foreach ($screenDefs as $def) {
    $sid = $def['id'];
    $sname = $def['name'];
    $sdesc = $def['description'];
    $sort = $def['sort_order'];
    $v1Key = $def['v1'];
    $v2Key = $def['v2'];

    echo "Processing screen $sort/20: $sname ($sid)...\n";

    foreach ($dbs as $dbName => $db) {
        // 1. Check or Upsert Screen
        $checkStmt = $db->prepare("SELECT id FROM design_screens WHERE id = :id");
        $checkStmt->execute([':id' => $sid]);
        $exists = $checkStmt->fetch();

        if ($exists) {
            $upStmt = $db->prepare("UPDATE design_screens SET name = :name, description = :desc, sort_order = :sort, updated_at = :now WHERE id = :id");
            $upStmt->execute([
                ':name' => $sname,
                ':desc' => $sdesc,
                ':sort' => $sort,
                ':now' => $now,
                ':id' => $sid
            ]);
        } else {
            $inStmt = $db->prepare("INSERT INTO design_screens (id, project_id, name, description, created_at, updated_at, created_by, sort_order) VALUES (:id, :project_id, :name, :desc, :created_at, :updated_at, :created_by, :sort)");
            $inStmt->execute([
                ':id' => $sid,
                ':project_id' => $projectId,
                ':name' => $sname,
                ':desc' => $sdesc,
                ':created_at' => $now,
                ':updated_at' => $now,
                ':created_by' => $adminId,
                ':sort' => $sort
            ]);
        }

        // 2. Ensure Version 1
        $ver1Id = "ver-{$sid}-1";
        $v1Check = $db->prepare("SELECT id FROM image_versions WHERE id = :id");
        $v1Check->execute([':id' => $ver1Id]);
        if (!$v1Check->fetch()) {
            $v1In = $db->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:id, :screen_id, :project_id, :number, :w, :h, :key, :mime, :source, :created_at, :created_by)");
            $v1In->execute([
                ':id' => $ver1Id,
                ':screen_id' => $sid,
                ':project_id' => $projectId,
                ':number' => 1,
                ':w' => 1000,
                ':h' => 500,
                ':key' => $v1Key,
                ':mime' => 'image/svg+xml',
                ':source' => 'seed',
                ':created_at' => '2026-10-28T12:00:00.000Z',
                ':created_by' => $adminId
            ]);
        }

        // 3. Ensure Version 2
        $ver2Id = "ver-{$sid}-2";
        $v2Check = $db->prepare("SELECT id FROM image_versions WHERE id = :id");
        $v2Check->execute([':id' => $ver2Id]);
        if (!$v2Check->fetch()) {
            $v2In = $db->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_at, created_by) VALUES (:id, :screen_id, :project_id, :number, :w, :h, :key, :mime, :source, :created_at, :created_by)");
            $v2In->execute([
                ':id' => $ver2Id,
                ':screen_id' => $sid,
                ':project_id' => $projectId,
                ':number' => 2,
                ':w' => 1000,
                ':h' => 500,
                ':key' => $v2Key,
                ':mime' => 'image/svg+xml',
                ':source' => 'seed',
                ':created_at' => '2026-10-29T14:30:00.000Z',
                ':created_by' => $adminId
            ]);
        }
    }
}

// Add sample annotations on some screens to ensure rich badge stats
$annotationsSeed = [
    [
        'id' => 'ann-proj-admin-scr5-1',
        'screen_id' => 'scr-proj-admin-designmark-5',
        'ver_id' => 'ver-scr-proj-admin-designmark-5-2',
        'body' => 'Bổ sung lựa chọn hiển thị 12, 24, 48 mục trên mỗi trang khi duyệt ở chế độ Grid.',
        'status' => 'open',
        'x' => 25.5,
        'y' => 18.2
    ],
    [
        'id' => 'ann-proj-admin-scr5-2',
        'screen_id' => 'scr-proj-admin-designmark-5',
        'ver_id' => 'ver-scr-proj-admin-designmark-5-2',
        'body' => 'Đã tối ưu thuật toán phân trang số gọn gàng dạng 1 2 3 ... 10.',
        'status' => 'resolved',
        'x' => 62.0,
        'y' => 74.0
    ],
    [
        'id' => 'ann-proj-admin-scr7-1',
        'screen_id' => 'scr-proj-admin-designmark-7',
        'ver_id' => 'ver-scr-proj-admin-designmark-7-2',
        'body' => 'Cần âm thanh thông báo nhẹ hoặc pop-up toast khi có người bình luận mới.',
        'status' => 'open',
        'x' => 45.0,
        'y' => 30.0
    ],
    [
        'id' => 'ann-proj-admin-scr10-1',
        'screen_id' => 'scr-proj-admin-designmark-10',
        'ver_id' => 'ver-scr-proj-admin-designmark-10-2',
        'body' => 'Biểu đồ tròn tỷ lệ góp ý cần chú thích rõ các màu đại diện cho trạng thái.',
        'status' => 'resolved',
        'x' => 50.0,
        'y' => 40.0
    ],
    [
        'id' => 'ann-proj-admin-scr11-1',
        'screen_id' => 'scr-proj-admin-designmark-11',
        'ver_id' => 'ver-scr-proj-admin-designmark-11-2',
        'body' => 'Tăng khoảng cách padding cạnh màn hình điện thoại để tránh bị chạm nhầm vào mép tai thỏ.',
        'status' => 'open',
        'x' => 30.0,
        'y' => 50.0
    ]
];

foreach ($annotationsSeed as $ann) {
    foreach ($dbs as $db) {
        $c = $db->prepare("SELECT id FROM annotations WHERE id = :id");
        $c->execute([':id' => $ann['id']]);
        if (!$c->fetch()) {
            $ins = $db->prepare("INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, created_at, updated_at) VALUES (:id, :project_id, :screen_id, :ver_id, 'pin', :x, :y, 0, 0, 1000, 500, :author_id, :body, :status, 1, :now, :now)");
            $ins->execute([
                ':id' => $ann['id'],
                ':project_id' => $projectId,
                ':screen_id' => $ann['screen_id'],
                ':ver_id' => $ann['ver_id'],
                ':x' => $ann['x'],
                ':y' => $ann['y'],
                ':author_id' => 'user-admin',
                ':body' => $ann['body'],
                ':status' => $ann['status'],
                ':now' => $now
            ]);
        }
    }
}

echo "\nDone! Project '$projectId' now has exactly 20 screens.\n";

