<?php
// scripts/create_admin_project.php
// Tạo 1 dự án hoàn chỉnh thuộc quyền sở hữu của Admin với đầy đủ phân quyền thành viên, màn hình, phiên bản và góp ý.

require_once __DIR__ . '/../api/db.php';

echo "=== KHỞI TẠO DỰ ÁN MẪU CHUẨN: QUYỀN SỞ HỮU ADMIN & PHÂN QUYỀN THÀNH VIÊN ===\n\n";

$sqlitePath = __DIR__ . '/../data/database.sqlite';
$sqliteDb = new PDO("sqlite:" . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// 1. Kiểm tra 4 tài khoản cốt lõi
$users = [
    'admin' => 'user-admin',
    'an'    => 'user-an',
    'binh'  => 'user-binh',
    'chi'   => 'user-chi'
];

foreach ($users as $uname => $uid) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
    $stmt->execute([':u' => $uname]);
    if (!$stmt->fetch()) {
        die("Lỗi: Tài khoản @{$uname} chưa tồn tại trong cơ sở dữ liệu!\n");
    }
}
echo "✓ Đã xác thực 4 tài khoản: @admin (Owner), @an (Reviewer), @binh (Reviewer), @chi (Viewer).\n";

$projectId = 'proj-admin-designmark';
$projectName = 'Hệ thống DesignMark Portal & Mobile App';
$projectCat = 'Nền tảng Thiết kế';
$projectDesc = 'Hệ thống đánh giá và góp ý giao diện chuẩn pixel dành cho đội ngũ Product & Design, hỗ trợ tải lên bản vẽ màn hình, so sánh các phiên bản và thảo luận phản hồi trực quan theo thời gian thực.';

$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

function seedProject($db, $targetName, $projectId, $projectName, $projectDesc, $projectCat, $users, $now) {
    echo " -> Đang khởi tạo trên [{$targetName}]...\n";

    // Xóa dữ liệu cũ nếu có
    $tables = ['notifications', 'annotation_replies', 'annotation_status_events', 'annotations', 'image_versions', 'design_screens', 'activities', 'project_members', 'projects'];
    foreach ($tables as $tbl) {
        try {
            $db->prepare("DELETE FROM {$tbl} WHERE project_id = :id")->execute([':id' => $projectId]);
        } catch (Throwable $e) {}
    }
    try {
        $db->prepare("DELETE FROM projects WHERE id = :id")->execute([':id' => $projectId]);
    } catch (Throwable $e) {}

    // 1. Thêm dự án
    $stmtProj = $db->prepare("INSERT INTO projects (id, name, description, category, created_by, created_at, updated_at) VALUES (:id, :name, :desc, :cat, :owner, :now, :now)");
    $stmtProj->execute([
        ':id' => $projectId,
        ':name' => $projectName,
        ':desc' => $projectDesc,
        ':cat' => $projectCat,
        ':owner' => $users['admin'],
        ':now' => $now
    ]);

    // 2. Thêm thành viên với phân quyền & cấm quyền rõ ràng
    // Admin: manager (Chủ sở hữu, toàn quyền)
    // An: reviewer (Được quyền góp ý, cấm quyền quản trị)
    // Bình: reviewer (Được quyền góp ý, cấm quyền quản trị)
    // Chi: viewer (Chỉ xem, cấm tạo góp ý, cấm chỉnh sửa)
    $membersData = [
        ['uid' => $users['admin'], 'role' => 'manager', 'inv' => null],
        ['uid' => $users['an'],    'role' => 'reviewer', 'inv' => $users['admin']],
        ['uid' => $users['binh'],  'role' => 'reviewer', 'inv' => $users['admin']],
        ['uid' => $users['chi'],   'role' => 'viewer',   'inv' => $users['admin']]
    ];

    $stmtMem = $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, :role, :inv, :now)");
    foreach ($membersData as $m) {
        $stmtMem->execute([
            ':id' => "mem-{$projectId}-" . substr($m['uid'], 5),
            ':pid' => $projectId,
            ':uid' => $m['uid'],
            ':role' => $m['role'],
            ':inv' => $m['inv'],
            ':now' => $now
        ]);
    }

    // 3. Tạo 4 màn hình thiết kế, mỗi màn hình có 2 phiên bản (v1 và v2)
    $screensData = [
        [
            'id' => "scr-{$projectId}-1",
            'name' => '01. Bảng điều khiển Tổng quan (Dashboard)',
            'desc' => 'Hiển thị tóm tắt tiến độ phản hồi, tỷ lệ góp ý đã giải quyết, biểu đồ hoạt động và danh sách các màn hình đang chờ đánh giá.',
            'v1_img' => 'seed:dash-v1',
            'v2_img' => 'seed:dash-v2'
        ],
        [
            'id' => "scr-{$projectId}-2",
            'name' => '02. Không gian Đánh giá & Góp ý (Review Canvas)',
            'desc' => 'Hỗ trợ tương tác ghim tọa độ tuyệt đối, khoanh vùng chi tiết, xem luồng thảo luận real-time và công cụ so sánh 2 phiên bản trước/sau.',
            'v1_img' => 'seed:home-v1',
            'v2_img' => 'seed:home-v2'
        ],
        [
            'id' => "scr-{$projectId}-3",
            'name' => '03. Cổng Đăng nhập & Xác thực Tài khoản (Authentication)',
            'desc' => 'Xác thực đa yếu tố, giao diện thích ứng Dark/Light Mode, xử lý khóa tài khoản tự động khi đăng nhập sai nhiều lần.',
            'v1_img' => 'seed:signup-v1',
            'v2_img' => 'seed:signup-v2'
        ],
        [
            'id' => "scr-{$projectId}-4",
            'name' => '04. Quản lý Thành viên & Phân quyền Dự án (Permissions & Roles)',
            'desc' => 'Bảng ma trận phân quyền theo vai trò (Owner, Designer, Reviewer, Viewer). Kiểm soát chặt chẽ quyền tạo góp ý và quản lý dự án.',
            'v1_img' => 'seed:settings-v1',
            'v2_img' => 'seed:settings-v2'
        ]
    ];

    $stmtScr = $db->prepare("INSERT INTO design_screens (id, project_id, name, description, created_by, created_at, updated_at) VALUES (:id, :pid, :name, :desc, :owner, :now, :now)");
    $stmtVer = $db->prepare("INSERT INTO image_versions (id, project_id, screen_id, number, image_key, width, height, mime_type, source, created_by, created_at) VALUES (:id, :pid, :sid, :num, :key, 1000, 500, 'image/svg+xml', 'seed', :owner, :now)");

    foreach ($screensData as $s) {
        $stmtScr->execute([
            ':id' => $s['id'],
            ':pid' => $projectId,
            ':name' => $s['name'],
            ':desc' => $s['desc'],
            ':owner' => $users['admin'],
            ':now' => $now
        ]);

        // Version 1
        $stmtVer->execute([
            ':id' => "ver-{$s['id']}-1",
            ':pid' => $projectId,
            ':sid' => $s['id'],
            ':num' => 1,
            ':key' => $s['v1_img'],
            ':owner' => $users['admin'],
            ':now' => $now
        ]);

        // Version 2 (phiên bản hiện tại có góp ý và cải tiến)
        $stmtVer->execute([
            ':id' => "ver-{$s['id']}-2",
            ':pid' => $projectId,
            ':sid' => $s['id'],
            ':num' => 2,
            ':key' => $s['v2_img'],
            ':owner' => $users['admin'],
            ':now' => $now
        ]);
    }

    // 4. Tạo các Góp ý (Annotations) đa dạng giữa v1 và v2:
    // - Phiên bản v1: Có các góp ý chỉ ra lỗi/thiếu sót của bản thiết kế ban đầu (đều đã được giải quyết 'resolved' trong v2).
    // - Phiên bản v2: Đã sửa theo góp ý của v1, và tiếp tục có các góp ý phản biện mới ('resolved' và 'open').
    $annotationsData = [
        // --- GÓP Ý TRÊN PHIÊN BẢN V1 (Đã được khắc phục trong v2) ---
        [
            'id' => "ann-{$projectId}-v1-1",
            'screen_id' => "scr-{$projectId}-1",
            'version_id' => "ver-scr-{$projectId}-1-1",
            'type' => 'point',
            'x' => 216,
            'y' => 146,
            'w' => 0,
            'h' => 0,
            'author_id' => $users['an'],
            'body' => '[UI/UX] Thẻ doanh thu "128" thiếu đơn vị tính cụ thể (triệu hay tỷ) và thanh biểu đồ cần có cảnh báo trạng thái đồng bộ kho khi bị chậm trễ.',
            'status' => 'resolved',
            'number' => 1,
            'reply' => 'Rất chính xác. Tôi đã thêm đơn vị "128 tr" và tích hợp banner thông báo cảnh báo đồng bộ kho chậm 12 phút trong phiên bản v2.',
            'reply_by' => $users['admin'],
            'resolved_note' => 'Đã hoàn thành điều chỉnh trong phiên bản v2.'
        ],
        [
            'id' => "ann-{$projectId}-v1-2",
            'screen_id' => "scr-{$projectId}-2",
            'version_id' => "ver-scr-{$projectId}-2-1",
            'type' => 'area',
            'x' => 40,
            'y' => 80,
            'w' => 920,
            'h' => 150,
            'author_id' => $users['binh'],
            'body' => '[Giao diện] Tiêu đề "Bộ sưu tập mùa mới" bị lệch khỏi lề trái nội dung. Thẻ sản phẩm Áo linen phía dưới đang bị thiếu giá tiền niêm yết và nút Mua ngay.',
            'status' => 'resolved',
            'number' => 2,
            'reply' => 'Đồng ý. Tôi đã căn chỉnh tiêu đề thẳng lề trái, bổ sung giá 890.000đ và nút "Mua ngay" trong bản v2.',
            'reply_by' => $users['admin'],
            'resolved_note' => 'Đã căn lề trái và bổ sung nút Mua ngay trong v2.'
        ],
        [
            'id' => "ann-{$projectId}-v1-3",
            'screen_id' => "scr-{$projectId}-3",
            'version_id' => "ver-scr-{$projectId}-3-1",
            'type' => 'area',
            'x' => 640,
            'y' => 80,
            'w' => 320,
            'h' => 250,
            'author_id' => $users['an'],
            'body' => '[UX/Layout] Bố cục 2 thẻ tách rời khiến nút "Đăng ký" bị đặt cô lập ở thẻ phụ bên phải. Người dùng rất dễ bỏ sót. Cần chuyển về bố cục 1 cột và đưa nút Đăng ký xuống dưới cùng form.',
            'status' => 'resolved',
            'number' => 3,
            'reply' => 'Đã tiếp thu. Phiên bản v2 đã chuyển toàn bộ form về bố cục 1 cột tối giản và nút Đăng ký đặt ngay dưới mật khẩu.',
            'reply_by' => $users['admin'],
            'resolved_note' => 'Đã chuyển bố cục 1 cột và đưa nút xuống form v2.'
        ],
        [
            'id' => "ann-{$projectId}-v1-4",
            'screen_id' => "scr-{$projectId}-4",
            'version_id' => "ver-scr-{$projectId}-4-1",
            'type' => 'area',
            'x' => 180,
            'y' => 90,
            'w' => 640,
            'h' => 320,
            'author_id' => $users['an'],
            'body' => '[Thiết kế] Màn hình cài đặt hiện chỉ có danh sách bật/tắt thông báo, thiếu các khu vực quản lý thông tin hồ sơ cửa hàng và cài đặt xác thực bảo mật tài khoản (2FA).',
            'status' => 'resolved',
            'number' => 4,
            'reply' => 'Đã bổ sung panel "Hồ sơ cửa hàng" và panel "Bảo mật 2FA" riêng biệt trong phiên bản v2.',
            'reply_by' => $users['admin'],
            'resolved_note' => 'Đã bổ sung Hồ sơ và Bảo mật 2FA trong v2.'
        ],

        // --- GÓP Ý TRÊN PHIÊN BẢN V2 (Các góp ý mới trong giai đoạn review hiện tại) ---
        [
            'id' => "ann-{$projectId}-v2-1",
            'screen_id' => "scr-{$projectId}-1",
            'version_id' => "ver-scr-{$projectId}-1-2",
            'type' => 'point',
            'x' => 120,
            'y' => 48,
            'w' => 0,
            'h' => 0,
            'author_id' => $users['binh'],
            'body' => '[UX/Tương tác] Banner cảnh báo đồng bộ kho đã hiển thị rất rõ ràng, nhưng nút đóng (×) cần bổ sung tooltip giải thích và liên kết "Xem chi tiết".',
            'status' => 'resolved',
            'number' => 5,
            'reply' => 'Đã cập nhật tooltip giải thích và dẫn liên kết đến trang đối chiếu log hệ thống.',
            'reply_by' => $users['admin'],
            'resolved_note' => 'Đã bổ sung tooltip và liên kết xem chi tiết.'
        ],
        [
            'id' => "ann-{$projectId}-v2-2",
            'screen_id' => "scr-{$projectId}-1",
            'version_id' => "ver-scr-{$projectId}-1-2",
            'type' => 'point',
            'x' => 680,
            'y' => 340,
            'w' => 0,
            'h' => 0,
            'author_id' => $users['an'],
            'body' => '[Responsive] Kiểm tra kích thước chữ và khoảng cách padding của các thẻ thống kê khi xem trên màn hình iPad 10.2 inch.',
            'status' => 'open',
            'number' => 6,
            'reply' => 'Chờ QA test lại trên thiết bị thực tế trước khi phát hành chính thức.',
            'reply_by' => $users['admin']
        ],
        [
            'id' => "ann-{$projectId}-v2-3",
            'screen_id' => "scr-{$projectId}-2",
            'version_id' => "ver-scr-{$projectId}-2-2",
            'type' => 'area',
            'x' => 180,
            'y' => 140,
            'w' => 420,
            'h' => 130,
            'author_id' => $users['binh'],
            'body' => '[Chức năng] Vùng thanh công cụ so sánh cần có thanh trượt chia đôi màn hình (Split Slider) để đối chiếu nhanh giữa bản cũ v1 và bản mới v2.',
            'status' => 'resolved',
            'number' => 7,
            'reply' => 'Ý kiến rất chuẩn xác! Đã tích hợp chế độ so sánh trượt Split Slider mượt mà cho toàn bộ thành viên.',
            'reply_by' => $users['admin'],
            'resolved_note' => 'Đã tích hợp Split Slider hoàn tất.'
        ],
        [
            'id' => "ann-{$projectId}-v2-4",
            'screen_id' => "scr-{$projectId}-3",
            'version_id' => "ver-scr-{$projectId}-3-2",
            'type' => 'point',
            'x' => 480,
            'y' => 220,
            'w' => 0,
            'h' => 0,
            'author_id' => $users['admin'],
            'body' => '[Bảo mật] Kiểm tra kỹ thời gian timeout của phiên làm việc (Session) sau 14 ngày ghi nhớ và hiển thị thông báo trước 5 phút khi hết hạn.',
            'status' => 'open',
            'number' => 8,
            'reply' => 'Đã ghi nhận yêu cầu bảo mật. Em đang thiết kế popup cảnh báo timeout đếm ngược.',
            'reply_by' => $users['an']
        ],
        [
            'id' => "ann-{$projectId}-v2-5",
            'screen_id' => "scr-{$projectId}-4",
            'version_id' => "ver-scr-{$projectId}-4-2",
            'type' => 'area',
            'x' => 80,
            'y' => 260,
            'w' => 520,
            'h' => 140,
            'author_id' => $users['binh'],
            'body' => '[Phân quyền] Cần hiển thị rõ ràng nhãn cấm quyền của tài khoản Viewer: "Tài khoản Viewer chỉ có quyền xem, không được phép tạo ghim hay bình luận" để người dùng không thắc mắc khi thanh công cụ vẽ bị vô hiệu hóa.',
            'status' => 'open',
            'number' => 9,
            'reply' => 'Đồng ý, phân quyền cần cực kỳ minh bạch để thành viên hiểu rõ vai trò của mình trong dự án.',
            'reply_by' => $users['admin']
        ]
    ];

    $stmtAnn = $db->prepare("INSERT INTO annotations (id, project_id, screen_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, created_at, updated_at) VALUES (:id, :pid, :sid, :vid, :type, :x, :y, :w, :h, 1000, 500, :author, :body, :status, 1, '', :num, :now, :now)");
    $stmtRep = $db->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES (:id, :aid, :author, :body, :now)");
    $stmtEvt = $db->prepare("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, :st, :uid, :note, :now)");

    foreach ($annotationsData as $a) {
        $stmtAnn->execute([
            ':id' => $a['id'],
            ':pid' => $projectId,
            ':sid' => $a['screen_id'],
            ':vid' => $a['version_id'],
            ':type' => $a['type'],
            ':x' => $a['x'],
            ':y' => $a['y'],
            ':w' => $a['w'],
            ':h' => $a['h'],
            ':author' => $a['author_id'],
            ':body' => $a['body'],
            ':status' => $a['status'],
            ':num' => $a['number'],
            ':now' => $now
        ]);

        if (!empty($a['reply'])) {
            $stmtRep->execute([
                ':id' => "rep-{$a['id']}-1",
                ':aid' => $a['id'],
                ':author' => $a['reply_by'],
                ':body' => $a['reply'],
                ':now' => $now
            ]);
        }

        if ($a['status'] === 'resolved') {
            $stmtEvt->execute([
                ':id' => "evt-{$a['id']}-1",
                ':aid' => $a['id'],
                ':st' => 'resolved',
                ':uid' => $a['reply_by'],
                ':note' => $a['resolved_note'] ?? 'Đã giải quyết góp ý hoàn tất.',
                ':now' => $now
            ]);
        }
    }

    // 5. Nhật ký hoạt động (Activities)
    $activitiesData = [
        ['type' => 'project_created', 'uid' => $users['admin'], 'msg' => 'Quản trị viên đã khởi tạo dự án "Hệ thống DesignMark Portal & Mobile App".'],
        ['type' => 'member.invited',  'uid' => $users['admin'], 'msg' => 'đã thêm An Nguyễn vào dự án với vai trò Góp ý (Reviewer - cấm quản trị dự án).'],
        ['type' => 'member.invited',  'uid' => $users['admin'], 'msg' => 'đã thêm Bình Trần vào dự án với vai trò Góp ý (Reviewer - cấm quản trị dự án).'],
        ['type' => 'member.invited',  'uid' => $users['admin'], 'msg' => 'đã thêm Chi Lê vào dự án với vai trò Chỉ xem (Viewer - cấm tạo góp ý & cấm chỉnh sửa).'],
        ['type' => 'screen_added',    'uid' => $users['admin'], 'msg' => 'đã tải lên 4 màn hình thiết kế mới vào dự án.'],
        ['type' => 'version_added',   'uid' => $users['admin'], 'msg' => 'đã phát hành phiên bản thiết kế v2 cho tất cả các màn hình.'],
        ['type' => 'comment_added',   'uid' => $users['an'],    'msg' => 'đã đặt ghim góp ý #1 trên màn hình Bảng điều khiển Tổng quan.'],
        ['type' => 'comment_added',   'uid' => $users['binh'],  'msg' => 'đã khoanh vùng góp ý #2 trên màn hình Không gian Đánh giá.'],
        ['type' => 'status_resolved', 'uid' => $users['admin'], 'msg' => 'đã xác nhận xử lý và hoàn tất góp ý #1 và #2.']
    ];

    $stmtAct = $db->prepare("INSERT INTO activities (id, project_id, user_id, type, message, created_at) VALUES (:id, :pid, :uid, :type, :msg, :now)");
    foreach ($activitiesData as $idx => $act) {
        $stmtAct->execute([
            ':id' => "act-{$projectId}-" . ($idx + 1),
            ':pid' => $projectId,
            ':uid' => $act['uid'],
            ':type' => $act['type'],
            ':msg' => $act['msg'],
            ':now' => $now
        ]);
    }

    echo " ✓ Đã thêm 1 dự án, 4 thành viên (với phân quyền & cấm quyền), 4 màn hình (8 phiên bản), 5 góp ý (Resolved & Open) và 9 nhật ký hoạt động trên [{$targetName}].\n";
}

seedProject($pdo, "Supabase PostgreSQL", $projectId, $projectName, $projectDesc, $projectCat, $users, $now);
seedProject($sqliteDb, "Local SQLite", $projectId, $projectName, $projectDesc, $projectCat, $users, $now);

echo "\n=== HOÀN TẤT KHỞI TẠO DỰ ÁN THÀNH CÔNG! ===\n";
