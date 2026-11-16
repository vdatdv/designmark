<?php
// scripts/seed_10_diverse_projects.php
// Tạo 10 dự án phong phú, đầy đủ màn hình, ảnh, góp ý đã/chưa xử lý và sở hữu bởi 10 người khác nhau

require_once __DIR__ . '/../api/db.php';

echo "=== DESIGNMARK: KHỞI TẠO 10 DỰ ÁN MẪU ĐẦY ĐỦ TIÊU CHÍ ===\n\n";

$sqliteDb = new PDO("sqlite:" . __DIR__ . '/../data/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// 1. Đảm bảo 10 tài khoản người dùng khác nhau tồn tại với đầy đủ thông tin
$demoHash = '$2y$12$KO2wwwn9yUq64yvTbVjgnOC1gY6AveC9Y1iH146bB7y9NgtuvTb4K'; // Mật khẩu: demo

$usersToSeed = [
    ['id' => 'user-an', 'username' => 'an', 'name' => 'An Nguyễn', 'title' => 'Product Lead', 'color' => '#4F46E5', 'role' => 'user'],
    ['id' => 'user-binh', 'username' => 'binh', 'name' => 'Bình Trần', 'title' => 'Senior UI Designer', 'color' => '#0F766E', 'role' => 'user'],
    ['id' => 'user-chi', 'username' => 'chi', 'name' => 'Chi Lê', 'title' => 'Frontend Architect', 'color' => '#B45309', 'role' => 'user'],
    ['id' => 'user-hongphuc', 'username' => 'hongphuc', 'name' => 'Hồng Phúc', 'title' => 'UX Researcher', 'color' => '#C2410C', 'role' => 'user'],
    ['id' => 'user-thanhnt', 'username' => 'thanhnt', 'name' => 'Thành Nguyễn', 'title' => 'Product Designer', 'color' => '#2563EB', 'role' => 'user'],
    ['id' => 'user-hoanghai', 'username' => 'hoanghai', 'name' => 'Hoàng Hải', 'title' => 'Mobile Lead Developer', 'color' => '#DB2777', 'role' => 'user'],
    ['id' => 'user-admin', 'username' => 'admin', 'name' => 'Quản trị viên Hệ thống', 'title' => 'System Administrator', 'color' => '#4338CA', 'role' => 'admin'],
    ['id' => 'user-minhtri', 'username' => 'minhtri', 'name' => 'Minh Trí', 'title' => 'Tech Lead & PM', 'color' => '#059669', 'role' => 'user'],
    ['id' => 'user-lananh', 'username' => 'lananh', 'name' => 'Lan Anh', 'title' => 'Creative Art Director', 'color' => '#7C3AED', 'role' => 'user'],
    ['id' => 'user-quocbao', 'username' => 'quocbao', 'name' => 'Quốc Bảo', 'title' => 'AI Solutions Lead', 'color' => '#D97706', 'role' => 'user'],
];

$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

foreach ($usersToSeed as $u) {
    // PostgreSQL
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
    $stmt->execute([':u' => $u['username']]);
    $exist = $stmt->fetch();
    if (!$exist) {
        $pdo->prepare("INSERT INTO users (id, username, name, role, title, color, system_role, password_hash, status, is_verified, created_at) VALUES (:id, :u, :name, 'manager', :title, :color, :role, :pass, 'active', 1, :now)")
            ->execute([
                ':id' => $u['id'],
                ':u' => $u['username'],
                ':name' => $u['name'],
                ':title' => $u['title'],
                ':color' => $u['color'],
                ':role' => $u['role'],
                ':pass' => $demoHash,
                ':now' => $now
            ]);
    }
    // SQLite
    $stmtSql = $sqliteDb->prepare("SELECT id FROM users WHERE username = :u");
    $stmtSql->execute([':u' => $u['username']]);
    if (!$stmtSql->fetch()) {
        $sqliteDb->prepare("INSERT INTO users (id, username, name, role, title, color, system_role, password_hash, status, is_verified, created_at) VALUES (:id, :u, :name, 'manager', :title, :color, :role, :pass, 'active', 1, :now)")
            ->execute([
                ':id' => $u['id'],
                ':u' => $u['username'],
                ':name' => $u['name'],
                ':title' => $u['title'],
                ':color' => $u['color'],
                ':role' => $u['role'],
                ':pass' => $demoHash,
                ':now' => $now
            ]);
    }
}
echo "✓ Đã chuẩn bị 10 tài khoản người dùng riêng biệt.\n";

// Map username -> user_id thực tế
$userMapPg = [];
$stmt = $pdo->query("SELECT username, id FROM users");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $userMapPg[$r['username']] = $r['id'];
}

$userMapSqlite = [];
$stmt = $sqliteDb->query("SELECT username, id FROM users");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $userMapSqlite[$r['username']] = $r['id'];
}

// Danh sách 10 dự án đa dạng
$projectsData = [
    [
        'id' => 'proj-fintech',
        'name' => 'Ví điện tử SmartPay',
        'category' => 'Fintech',
        'owner' => 'an',
        'description' => 'Ứng dụng thanh toán không tiền mặt, chuyển tiền tức thì qua mã QR, quản lý thẻ tín dụng và biểu đồ chi tiêu.',
        'screens' => [
            ['name' => 'Trang chủ ví & Số dư', 'image' => 'seed:dash-v2', 'order' => 1],
            ['name' => 'Quét mã QR & Chuyển tiền', 'image' => 'seed:pay-v2', 'order' => 2],
            ['name' => 'Lịch sử giao dịch & Báo cáo', 'image' => 'seed:orders-v2', 'order' => 3],
        ],
        'members' => ['an', 'binh', 'chi', 'admin', 'thanhnt'],
        'annotations' => [
            ['type' => 'point', 'x' => 120, 'y' => 80, 'body' => 'Nút quét mã QR cần to hơn trên màn hình di động 6.1 inch.', 'status' => 'resolved', 'author' => 'binh', 'reply' => 'Đã tăng kích thước lên 56px và bổ sung rung xúc giác.', 'reply_by' => 'an'],
            ['type' => 'area', 'x' => 250, 'y' => 140, 'w' => 300, 'h' => 120, 'body' => 'Biểu đồ chi tiêu theo tháng nên dùng dải màu tím gradient dịu mắt.', 'status' => 'resolved', 'author' => 'thanhnt', 'reply' => 'Đã cập nhật bảng màu pastel theo design system.', 'reply_by' => 'binh'],
            ['type' => 'point', 'x' => 520, 'y' => 210, 'body' => 'Bổ sung bước xác thực sinh trắc học FaceID trước khi chuyển tiền.', 'status' => 'resolved', 'author' => 'chi', 'reply' => 'Đã tích hợp SDK sinh trắc học hoàn tất.', 'reply_by' => 'an'],
            ['type' => 'area', 'x' => 60, 'y' => 320, 'w' => 450, 'h' => 110, 'body' => 'Cần cảnh báo người dùng khi chuyển tiền cho tài khoản lạ ngoài danh bạ.', 'status' => 'open', 'author' => 'admin'],
            ['type' => 'point', 'x' => 410, 'y' => 390, 'body' => 'Nút chia sẻ biên lai giao dịch qua Zalo/Telegram chưa rõ icon.', 'status' => 'open', 'author' => 'binh'],
        ]
    ],
    [
        'id' => 'proj-edtech',
        'name' => 'Hệ thống E-Learning EduHub',
        'category' => 'Giáo dục / EdTech',
        'owner' => 'binh',
        'description' => 'Nền tảng học trực tuyến video chuẩn HD, làm bài trắc nghiệm tương tác và cấp chứng chỉ blockchain.',
        'screens' => [
            ['name' => 'Cổng khóa học & Lộ trình', 'image' => 'seed:home-v2', 'order' => 1],
            ['name' => 'Giao diện phát bài giảng Video', 'image' => 'seed:dash-v1', 'order' => 2],
            ['name' => 'Làm bài kiểm tra & Chứng chỉ', 'image' => 'seed:settings-v2', 'order' => 3],
        ],
        'members' => ['binh', 'an', 'chi', 'hongphuc', 'admin'],
        'annotations' => [
            ['type' => 'point', 'x' => 90, 'y' => 110, 'body' => 'Thanh tiến độ học tập (Progress bar) nên có tỷ lệ phần trăm số học viên hoàn thành.', 'status' => 'resolved', 'author' => 'hongphuc', 'reply' => 'Đã thêm badge tỷ lệ % động.', 'reply_by' => 'binh'],
            ['type' => 'area', 'x' => 200, 'y' => 150, 'w' => 420, 'h' => 180, 'body' => 'Khung video player cần hỗ trợ phím tắt tua nhanh 10 giây (J/L).', 'status' => 'resolved', 'author' => 'chi', 'reply' => 'Đã cài đặt hotkey keyboard listener.', 'reply_by' => 'binh'],
            ['type' => 'point', 'x' => 650, 'y' => 90, 'body' => 'Nút tải slide tài liệu đính kèm PDF bị chìm màu so với nền tối.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã đổi viền nổi màu xanh cyan.', 'reply_by' => 'binh'],
            ['type' => 'point', 'x' => 310, 'y' => 360, 'body' => 'Đồng hồ đếm ngược bài thi trắc nghiệm nên nhấp nháy đỏ khi còn 5 phút.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã bổ sung animation cảnh báo đỏ.', 'reply_by' => 'binh'],
            ['type' => 'area', 'x' => 100, 'y' => 280, 'w' => 350, 'h' => 100, 'body' => 'Mẫu chứng chỉ hoàn thành khóa học cần độ phân giải cao để in ấn A4.', 'status' => 'open', 'author' => 'hongphuc'],
            ['type' => 'point', 'x' => 480, 'y' => 410, 'body' => 'Bổ sung mục câu hỏi thường gặp FAQ ngay dưới video bài học.', 'status' => 'open', 'author' => 'an'],
            ['type' => 'point', 'x' => 700, 'y' => 320, 'body' => 'Hệ thống gợi ý khóa học tiếp theo cần dựa trên kết quả bài thi vừa làm.', 'status' => 'open', 'author' => 'chi'],
        ]
    ],
    [
        'id' => 'proj-med',
        'name' => 'Cổng Y tế Sức Khỏe Vàng',
        'category' => 'Y tế / Sức khỏe',
        'owner' => 'chi',
        'description' => 'Hệ thống đặt lịch khám bệnh chuyên khoa, tra cứu kết quả xét nghiệm và sổ tiêm chủng điện tử toàn dân.',
        'screens' => [
            ['name' => 'Đặt lịch khám theo bác sĩ', 'image' => 'seed:orders-v1', 'order' => 1],
            ['name' => 'Hồ sơ sức khỏe & Kết quả xét nghiệm', 'image' => 'seed:settings-v1', 'order' => 2],
        ],
        'members' => ['chi', 'admin', 'binh', 'hongphuc'],
        'annotations' => [
            ['type' => 'point', 'x' => 150, 'y' => 95, 'body' => 'Các khung giờ khám (Time slot) đã kín lịch cần đổi sang màu xám mờ.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã disabled các khung giờ kín chỗ.', 'reply_by' => 'chi'],
            ['type' => 'area', 'x' => 180, 'y' => 160, 'w' => 380, 'h' => 130, 'body' => 'Bảo mật thông tin xét nghiệm: Yêu cầu nhập mã OTP khi tải file PDF.', 'status' => 'resolved', 'author' => 'hongphuc', 'reply' => 'Đã thêm modal xác thực OTP SMS.', 'reply_by' => 'chi'],
            ['type' => 'point', 'x' => 420, 'y' => 310, 'body' => 'Cần hiển thị thông tin bằng cấp và số năm kinh nghiệm của bác sĩ.', 'status' => 'resolved', 'author' => 'binh', 'reply' => 'Đã bổ sung thẻ bác sĩ chi tiết.', 'reply_by' => 'chi'],
            ['type' => 'point', 'x' => 600, 'y' => 220, 'body' => 'Thêm tính năng nhắc lịch uống thuốc định kỳ hàng ngày.', 'status' => 'open', 'author' => 'hongphuc'],
            ['type' => 'area', 'x' => 90, 'y' => 320, 'w' => 400, 'h' => 100, 'body' => 'Mục tiền sử dị ứng thuốc cần đặt ở vị trí dễ nhìn nhất với màu đỏ cảnh báo.', 'status' => 'open', 'author' => 'admin'],
        ]
    ],
    [
        'id' => 'proj-realestate',
        'name' => 'Sàn Bất động sản PrimeHome',
        'category' => 'Bất động sản',
        'owner' => 'hongphuc',
        'description' => 'Sàn giao dịch căn hộ cao cấp, biệt thự nghỉ dưỡng kèm công cụ tính toán lãi suất vay mua nhà trực quan.',
        'screens' => [
            ['name' => 'Trang khám phá bất động sản & Bản đồ', 'image' => 'seed:home-v1', 'order' => 1],
            ['name' => 'Chi tiết căn hộ & Ảnh 360 độ', 'image' => 'seed:dash-v2', 'order' => 2],
            ['name' => 'Bảng tính vay vốn trả góp ngân hàng', 'image' => 'seed:pay-v1', 'order' => 3],
        ],
        'members' => ['hongphuc', 'an', 'thanhnt', 'admin', 'minhtri'],
        'annotations' => [
            ['type' => 'point', 'x' => 110, 'y' => 120, 'body' => 'Bộ lọc khoảng giá (Price range slider) cần hiển thị đơn vị Tỷ VNĐ rõ ràng.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã chỉnh bước nhảy 0.5 tỷ/nấc.', 'reply_by' => 'hongphuc'],
            ['type' => 'area', 'x' => 220, 'y' => 180, 'w' => 360, 'h' => 140, 'body' => 'Chế độ xem thực tế ảo VR 360 cần có nút phóng to toàn màn hình.', 'status' => 'resolved', 'author' => 'thanhnt', 'reply' => 'Đã kích hoạt fullscreen API.', 'reply_by' => 'hongphuc'],
            ['type' => 'point', 'x' => 490, 'y' => 290, 'body' => 'Công cụ tính vay: Cần so sánh lãi suất giữa các ngân hàng BIDV, Vietcombank, Techcombank.', 'status' => 'resolved', 'author' => 'minhtri', 'reply' => 'Đã bổ sung bảng đối chiếu lãi suất.', 'reply_by' => 'hongphuc'],
            ['type' => 'point', 'x' => 620, 'y' => 160, 'body' => 'Tag hiển thị pháp lý (Sổ hồng riêng, Đang chờ bàn giao) cần đồng bộ màu xanh lá.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã chuẩn hóa badge pháp lý.', 'reply_by' => 'hongphuc'],
            ['type' => 'point', 'x' => 340, 'y' => 390, 'body' => 'Nút đặt lịch xem nhà trực tiếp cần tích hợp Google Calendar.', 'status' => 'open', 'author' => 'an'],
            ['type' => 'area', 'x' => 80, 'y' => 300, 'w' => 420, 'h' => 110, 'body' => 'Bản đồ tiện ích xung quanh (Trường học, Bệnh viện, Siêu thị) trong bán kính 2km.', 'status' => 'open', 'author' => 'thanhnt'],
        ]
    ],
    [
        'id' => 'proj-delivery',
        'name' => 'Ứng dụng Giao đồ ăn FastBite',
        'category' => 'F&B / Đặt món',
        'owner' => 'thanhnt',
        'description' => 'Ứng dụng tìm kiếm quán ăn ngon, chọn topping món ăn theo yêu cầu và theo dõi shipper di chuyển trên bản đồ.',
        'screens' => [
            ['name' => 'Khám phá quán ăn & Flash Sale', 'image' => 'seed:home-v2', 'order' => 1],
            ['name' => 'Tùy chọn Topping & Giỏ hàng', 'image' => 'seed:orders-v2', 'order' => 2],
            ['name' => 'Theo dõi lộ trình giao nhận hàng', 'image' => 'seed:dash-v1', 'order' => 3],
        ],
        'members' => ['thanhnt', 'hoanghai', 'an', 'binh', 'admin'],
        'annotations' => [
            ['type' => 'point', 'x' => 80, 'y' => 140, 'body' => 'Mã giảm giá FreeShip cần tự động áp dụng mã tốt nhất cho khách.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã cài đặt thuật toán auto-apply best coupon.', 'reply_by' => 'thanhnt'],
            ['type' => 'area', 'x' => 240, 'y' => 120, 'w' => 320, 'h' => 160, 'body' => 'Bổ sung danh sách chọn mức độ ngọt (0%, 30%, 50%, 100%) và lượng đá cho trà sữa.', 'status' => 'resolved', 'author' => 'binh', 'reply' => 'Đã thêm radio group tùy chọn.', 'reply_by' => 'thanhnt'],
            ['type' => 'point', 'x' => 520, 'y' => 230, 'body' => 'Icon xe máy của shipper trên bản đồ cần xoay đầu theo hướng di chuyển thực tế.', 'status' => 'resolved', 'author' => 'hoanghai', 'reply' => 'Đã cập nhật tính góc bearing trên GPS.', 'reply_by' => 'thanhnt'],
            ['type' => 'point', 'x' => 380, 'y' => 320, 'body' => 'Hiển thị số sao đánh giá thực tế của quán ăn kèm số lượng review.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã đồng bộ rating 4.8★ (1.2k+).', 'reply_by' => 'thanhnt'],
            ['type' => 'area', 'x' => 110, 'y' => 290, 'w' => 440, 'h' => 120, 'body' => 'Cho phép khách nhắn tin dặn dò shipper trực tiếp trong màn hình theo dõi đơn.', 'status' => 'open', 'author' => 'hoanghai'],
            ['type' => 'point', 'x' => 610, 'y' => 370, 'body' => 'Nút hủy đơn chỉ được phép hiển thị trong 2 phút đầu trước khi quán làm món.', 'status' => 'open', 'author' => 'an'],
        ]
    ],
    [
        'id' => 'proj-logistics',
        'name' => 'Quản lý Vận tải LogiChain',
        'category' => 'Logistics / Kho bãi',
        'owner' => 'hoanghai',
        'description' => 'Hệ thống quản lý chuỗi cung ứng, điều phối container, xếp xe thông minh và theo dõi chỉ số nhiệt độ kho lạnh.',
        'screens' => [
            ['name' => 'Bảng điều phối container liên tỉnh', 'image' => 'seed:dash-v2', 'order' => 1],
            ['name' => 'Kiểm kê kho bãi & Cảnh báo tồn', 'image' => 'seed:settings-v1', 'order' => 2],
        ],
        'members' => ['hoanghai', 'thanhnt', 'chi', 'admin'],
        'annotations' => [
            ['type' => 'point', 'x' => 140, 'y' => 100, 'body' => 'Mã vận đơn barcode cần quét được ngay bằng camera điện thoại của tài xế.', 'status' => 'resolved', 'author' => 'thanhnt', 'reply' => 'Đã kiểm thử thư viện scan ZXing nhận diện nhanh.', 'reply_by' => 'hoanghai'],
            ['type' => 'area', 'x' => 210, 'y' => 170, 'w' => 390, 'h' => 130, 'body' => 'Cảnh báo nhiệt độ kho lạnh vượt ngưỡng -18 độ C cần gửi thông báo đẩy khẩn cấp.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã kích hoạt còi cảnh báo & Webhook IoT.', 'reply_by' => 'hoanghai'],
            ['type' => 'point', 'x' => 480, 'y' => 260, 'body' => 'Nút xuất phiếu giao nhận hàng điện tử (e-POD) sang PDF có chữ ký số.', 'status' => 'resolved', 'author' => 'chi', 'reply' => 'Đã tích hợp module ký số VNPT.', 'reply_by' => 'hoanghai'],
            ['type' => 'point', 'x' => 630, 'y' => 340, 'body' => 'Cột tải trọng xe container cần có thanh cảnh báo quá tải trọng quy định.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã thêm chỉ số tải trọng an toàn.', 'reply_by' => 'hoanghai'],
        ]
    ],
    [
        'id' => 'proj-social',
        'name' => 'Mạng xã hội nội bộ Workplace',
        'category' => 'Truyền thông / Nội bộ',
        'owner' => 'admin',
        'description' => 'Môi trường làm việc số: Bảng tin công ty, vinh danh nhân viên xuất sắc, nhóm thảo luận dự án và khảo sát kín.',
        'screens' => [
            ['name' => 'Bảng tin công ty & Bài viết ghim', 'image' => 'seed:home-v1', 'order' => 1],
            ['name' => 'Không gian nhóm dự án & Thảo luận', 'image' => 'seed:dash-v1', 'order' => 2],
            ['name' => 'Khảo sát nhân viên & Biểu quyết', 'image' => 'seed:settings-v2', 'order' => 3],
        ],
        'members' => ['admin', 'an', 'binh', 'chi', 'hongphuc', 'thanhnt'],
        'annotations' => [
            ['type' => 'point', 'x' => 105, 'y' => 85, 'body' => 'Bài viết thông báo từ Ban Giám đốc cần có huy hiệu xác thực Official màu xanh.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã thêm verified badge màu xanh dương.', 'reply_by' => 'admin'],
            ['type' => 'area', 'x' => 220, 'y' => 140, 'w' => 340, 'h' => 150, 'body' => 'Chức năng thả cảm xúc (Like, Tim, Vỗ tay, Sáng tạo) cần icon động mượt.', 'status' => 'resolved', 'author' => 'binh', 'reply' => 'Đã thay bằng Lottie micro-animation.', 'reply_by' => 'admin'],
            ['type' => 'point', 'x' => 490, 'y' => 270, 'body' => 'Thăm dò ý kiến (Poll): Ẩn danh hoàn toàn thông tin người bình chọn.', 'status' => 'open', 'author' => 'chi'],
            ['type' => 'area', 'x' => 80, 'y' => 310, 'w' => 460, 'h' => 100, 'body' => 'Mục chúc mừng sinh nhật đồng nghiệp trong ngày đặt tại sidebar phải.', 'status' => 'open', 'author' => 'hongphuc'],
            ['type' => 'point', 'x' => 610, 'y' => 360, 'body' => 'Tính năng gắn thẻ @mention đồng nghiệp cần gợi ý thông minh theo phòng ban.', 'status' => 'open', 'author' => 'thanhnt'],
        ]
    ],
    [
        'id' => 'proj-travel',
        'name' => 'Cổng Du lịch & Đặt phòng Wander',
        'category' => 'Du lịch / Khách sạn',
        'owner' => 'minhtri',
        'description' => 'Trải nghiệm du lịch trọn gói: Đặt vé máy bay, phòng resort 5 sao, tour khám phá văn hóa và cẩm nang ẩm thực địa phương.',
        'screens' => [
            ['name' => 'Tìm kiếm điểm đến & Gói Tour', 'image' => 'seed:home-v2', 'order' => 1],
            ['name' => 'Chi tiết phòng khách sạn & Tiện ích', 'image' => 'seed:orders-v1', 'order' => 2],
            ['name' => 'Trang thanh toán & Bảo hiểm du lịch', 'image' => 'seed:pay-v2', 'order' => 3],
        ],
        'members' => ['minhtri', 'an', 'lananh', 'quocbao', 'admin'],
        'annotations' => [
            ['type' => 'point', 'x' => 130, 'y' => 110, 'body' => 'Lịch chọn ngày nhận/trả phòng cần tô sáng các ngày cuối tuần để khách dễ chọn.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã đánh dấu T7-CN nổi bật.', 'reply_by' => 'minhtri'],
            ['type' => 'area', 'x' => 230, 'y' => 150, 'w' => 380, 'h' => 140, 'body' => 'Danh sách tiện ích (Bể bơi vô cực, Buffet sáng, Xe đưa đón sân bay) cần icon rõ nét.', 'status' => 'resolved', 'author' => 'lananh', 'reply' => 'Đã vẽ bộ icon du lịch cao cấp.', 'reply_by' => 'minhtri'],
            ['type' => 'point', 'x' => 540, 'y' => 250, 'body' => 'Chính sách hoàn/hủy phòng miễn phí trước 24h cần được highlight màu xanh.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã thêm banner Free Cancellation.', 'reply_by' => 'minhtri'],
            ['type' => 'point', 'x' => 320, 'y' => 350, 'body' => 'Bổ sung gói bảo hiểm du lịch tự chọn với mức phí linh hoạt.', 'status' => 'open', 'author' => 'quocbao'],
            ['type' => 'area', 'x' => 90, 'y' => 290, 'w' => 450, 'h' => 110, 'body' => 'Mục đánh giá từ khách thực tế kèm ảnh chụp check-in nguyên bản.', 'status' => 'open', 'author' => 'lananh'],
            ['type' => 'point', 'x' => 640, 'y' => 380, 'body' => 'Tự động tính tiền quy đổi tỷ giá ngoại tệ USD/EUR/JPY theo thời gian thực.', 'status' => 'open', 'author' => 'an'],
        ]
    ],
    [
        'id' => 'proj-hr',
        'name' => 'Hệ sinh thái Tuyển dụng TalentPro',
        'category' => 'Tuyển dụng / HR',
        'owner' => 'lananh',
        'description' => 'Nền tảng săn đầu người và quản trị ứng viên ATS: Lọc hồ sơ CV bằng AI, xếp lịch phỏng vấn online qua Google Meet.',
        'screens' => [
            ['name' => 'Pipeline quản lý ứng viên ATS', 'image' => 'seed:dash-v1', 'order' => 1],
            ['name' => 'Chi tiết hồ sơ ứng viên & Đánh giá', 'image' => 'seed:settings-v1', 'order' => 2],
        ],
        'members' => ['lananh', 'minhtri', 'quocbao', 'admin'],
        'annotations' => [
            ['type' => 'point', 'x' => 125, 'y' => 90, 'body' => 'Cột trạng thái ứng viên (Ứng tuyển, Phỏng vấn, Đề nghị lương, Nhận việc) dạng Kanban kéo thả.', 'status' => 'resolved', 'author' => 'minhtri', 'reply' => 'Đã hỗ trợ drag & drop mượt mà.', 'reply_by' => 'lananh'],
            ['type' => 'area', 'x' => 200, 'y' => 160, 'w' => 360, 'h' => 130, 'body' => 'Khung chấm điểm kỹ năng chuyên môn thang điểm 10 kèm nhận xét từ Tech Lead.', 'status' => 'resolved', 'author' => 'quocbao', 'reply' => 'Đã tích hợp form đánh giá phỏng vấn.', 'reply_by' => 'lananh'],
            ['type' => 'point', 'x' => 510, 'y' => 280, 'body' => 'Nút gửi thư mời làm việc (Offer letter) tự điền mức lương thỏa thuận theo mẫu.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã bổ sung template generator.', 'reply_by' => 'lananh'],
            ['type' => 'point', 'x' => 360, 'y' => 370, 'body' => 'Chức năng chấm điểm độ tương thích CV với Job Description bằng AI Matching.', 'status' => 'open', 'author' => 'quocbao'],
        ]
    ],
    [
        'id' => 'proj-ai',
        'name' => 'Trợ lý Trí tuệ Nhân tạo NovaAI',
        'category' => 'Công nghệ / AI',
        'owner' => 'quocbao',
        'description' => 'Trợ lý AI đa năng thế hệ mới: Đọc hiểu tài liệu PDF, phân tích dữ liệu bảng tính Excel và sinh code lập trình đa ngôn ngữ.',
        'screens' => [
            ['name' => 'Giao diện Chat & Lời nhắc Prompt', 'image' => 'seed:home-v1', 'order' => 1],
            ['name' => 'Phân tích tài liệu & Biểu đồ', 'image' => 'seed:dash-v2', 'order' => 2],
            ['name' => 'Trình biên tập Code & Chạy thử', 'image' => 'seed:settings-v2', 'order' => 3],
        ],
        'members' => ['quocbao', 'an', 'binh', 'minhtri', 'admin'],
        'annotations' => [
            ['type' => 'point', 'x' => 95, 'y' => 115, 'body' => 'Khung trả lời của AI cần có nút Copy nhanh toàn bộ đoạn văn bản hoặc code.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã thêm nút Copy kèm thông báo toast.', 'reply_by' => 'quocbao'],
            ['type' => 'area', 'x' => 210, 'y' => 130, 'w' => 410, 'h' => 170, 'body' => 'Trình highlight code lập trình (Syntax highlighter) cần hỗ trợ đầy đủ Python, JS, PHP, SQL.', 'status' => 'resolved', 'author' => 'minhtri', 'reply' => 'Đã nhúng prism.js nhận diện đa ngôn ngữ.', 'reply_by' => 'quocbao'],
            ['type' => 'point', 'x' => 660, 'y' => 85, 'body' => 'Hiệu ứng chữ gõ trực tiếp (Streaming response) cần mượt mà, không bị giật khung hình.', 'status' => 'resolved', 'author' => 'binh', 'reply' => 'Đã tối ưu buffer rendering bằng requestAnimationFrame.', 'reply_by' => 'quocbao'],
            ['type' => 'point', 'x' => 330, 'y' => 320, 'body' => 'Thanh trượt điều chỉnh nhiệt độ sáng tạo (Temperature 0.0 - 1.0) cho kết quả chính xác hơn.', 'status' => 'resolved', 'author' => 'admin', 'reply' => 'Đã thêm slider điều chỉnh thông số mô hình.', 'reply_by' => 'quocbao'],
            ['type' => 'point', 'x' => 490, 'y' => 260, 'body' => 'Cho phép tải ảnh chụp bảng dữ liệu viết tay để AI bóc tách sang file Excel.', 'status' => 'resolved', 'author' => 'an', 'reply' => 'Đã tích hợp OCR Vision model.', 'reply_by' => 'quocbao'],
            ['type' => 'point', 'x' => 600, 'y' => 380, 'body' => 'Chế độ tối (Dark Mode) chuyên biệt cho coder với màu nền xám than #0F172A.', 'status' => 'resolved', 'author' => 'binh', 'reply' => 'Đã hoàn thiện bảng màu OLED Dark.', 'reply_by' => 'quocbao'],
            ['type' => 'area', 'x' => 110, 'y' => 280, 'w' => 360, 'h' => 120, 'body' => 'Tính năng lưu lịch sử hội thoại thành các session có thể tìm kiếm lại theo từ khóa.', 'status' => 'open', 'author' => 'minhtri'],
            ['type' => 'point', 'x' => 520, 'y' => 390, 'body' => 'Bổ sung danh mục Prompt mẫu dành riêng cho Marketing, Lập trình và Viết lách.', 'status' => 'open', 'author' => 'an'],
        ]
    ]
];

function seedProjectToDb(PDO $db, array $pData, string $dbName, array $userMap) {
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');
    $pid = $pData['id'];
    $ownerId = $userMap[$pData['owner']] ?? $pData['owner'];

    // Kiểm tra dự án đã tồn tại chưa
    $chk = $db->prepare("SELECT id FROM projects WHERE id = :id");
    $chk->execute([':id' => $pid]);
    if ($chk->fetch()) {
        // Xóa sạch để tạo lại mới tinh
        try {
            $db->prepare("DELETE FROM notifications WHERE project_id = :id")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM annotation_replies WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :id)")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM annotation_status_events WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = :id)")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM annotations WHERE project_id = :id")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM image_versions WHERE project_id = :id")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM design_screens WHERE project_id = :id")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM activities WHERE project_id = :id")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM project_members WHERE project_id = :id")->execute([':id' => $pid]);
            $db->prepare("DELETE FROM projects WHERE id = :id")->execute([':id' => $pid]);
        } catch (Throwable $e) {}
    }

    // 1. Tạo Project
    $stmtProj = $db->prepare("INSERT INTO projects (id, name, description, category, created_by, created_at, updated_at) VALUES (:id, :name, :desc, :cat, :owner, :now, :now)");
    $stmtProj->execute([
        ':id' => $pid,
        ':name' => $pData['name'],
        ':desc' => $pData['description'],
        ':cat' => $pData['category'],
        ':owner' => $ownerId,
        ':now' => $now
    ]);

    // 2. Thêm Members
    $stmtMem = $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, :role, :inv, :now)");
    foreach ($pData['members'] as $idx => $uname) {
        $uid = $userMap[$uname] ?? $uname;
        $role = ($uname === $pData['owner']) ? 'manager' : ($idx === 1 ? 'reviewer' : ($idx === 2 ? 'commenter' : 'viewer'));
        $stmtMem->execute([
            ':id' => "mem-{$pid}-{$uname}",
            ':pid' => $pid,
            ':uid' => $uid,
            ':role' => $role,
            ':inv' => $ownerId,
            ':now' => $now
        ]);
    }

    // 3. Thêm Screens & Image Versions
    $createdScreens = [];
    $stmtScreen = $db->prepare("INSERT INTO design_screens (id, project_id, name, description, created_by, created_at, updated_at) VALUES (:id, :pid, :name, '', :cb, :now, :now)");
    $stmtVersion = $db->prepare("INSERT INTO image_versions (id, project_id, screen_id, number, image_key, width, height, mime_type, source, created_by, created_at) VALUES (:id, :pid, :sid, :num, :key, 1000, 500, 'image/svg+xml', 'seed', :cb, :now)");

    foreach ($pData['screens'] as $sIdx => $s) {
        $sid = "scr-{$pid}-" . ($sIdx + 1);
        $vid = "ver-{$pid}-" . ($sIdx + 1) . "-1";
        $stmtScreen->execute([
            ':id' => $sid,
            ':pid' => $pid,
            ':name' => $s['name'],
            ':cb' => $ownerId,
            ':now' => $now
        ]);
        $stmtVersion->execute([
            ':id' => $vid,
            ':pid' => $pid,
            ':sid' => $sid,
            ':num' => 1,
            ':key' => $s['image'],
            ':cb' => $ownerId,
            ':now' => $now
        ]);
        $createdScreens[] = ['screen_id' => $sid, 'version_id' => $vid];
    }

    // 4. Thêm Annotations (Góp ý), Replies & Status Events
    $stmtAnn = $db->prepare("INSERT INTO annotations (id, screen_id, project_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, created_at, updated_at) VALUES (:id, :sid, :pid, :vid, :type, :x, :y, :w, :h, 1000, 500, :author, :body, :status, 1, '', :num, :now, :now)");
    $stmtRep = $db->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES (:id, :aid, :author, :body, :now)");
    $stmtEvt = $db->prepare("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, :st, :uid, :note, :now)");

    foreach ($pData['annotations'] as $aIdx => $a) {
        $aid = "ann-{$pid}-" . ($aIdx + 1);
        $target = $createdScreens[$aIdx % count($createdScreens)];
        $authorId = $userMap[$a['author']] ?? $ownerId;
        $replyById = !empty($a['reply_by']) ? ($userMap[$a['reply_by']] ?? $ownerId) : $ownerId;

        $stmtAnn->execute([
            ':id' => $aid,
            ':sid' => $target['screen_id'],
            ':pid' => $pid,
            ':vid' => $target['version_id'],
            ':type' => $a['type'],
            ':x' => $a['x'],
            ':y' => $a['y'],
            ':w' => $a['w'] ?? 0,
            ':h' => $a['h'] ?? 0,
            ':author' => $authorId,
            ':body' => $a['body'],
            ':status' => $a['status'],
            ':num' => ($aIdx + 1),
            ':now' => $now
        ]);

        if (!empty($a['reply'])) {
            $stmtRep->execute([
                ':id' => "rep-{$aid}-1",
                ':aid' => $aid,
                ':author' => $replyById,
                ':body' => $a['reply'],
                ':now' => $now
            ]);
        }

        if ($a['status'] === 'resolved') {
            $stmtEvt->execute([
                ':id' => "evt-{$aid}-1",
                ':aid' => $aid,
                ':st' => 'resolved',
                ':uid' => $replyById,
                ':note' => 'Đã xử lý hoàn tất theo góp ý.',
                ':now' => $now
            ]);
        }
    }

    // 5. Nhật ký hoạt động (Activities)
    $stmtAct = $db->prepare("INSERT INTO activities (id, project_id, user_id, type, message, created_at) VALUES (:id, :pid, :uid, :type, :msg, :now)");
    $stmtAct->execute([
        ':id' => "act-{$pid}-1",
        ':pid' => $pid,
        ':uid' => $ownerId,
        ':type' => 'project_created',
        ':msg' => "Dự án \"{$pData['name']}\" đã được khởi tạo.",
        ':now' => $now
    ]);
    $stmtAct->execute([
        ':id' => "act-{$pid}-2",
        ':pid' => $pid,
        ':uid' => $ownerId,
        ':type' => 'screen_added',
        ':msg' => "Đã thêm " . count($pData['screens']) . " màn hình thiết kế mới vào dự án.",
        ':now' => $now
    ]);
}

foreach ($projectsData as $idx => $p) {
    echo " [" . ($idx + 1) . "/10] Khởi tạo dự án \"{$p['name']}\" (Chủ sở hữu: @{$p['owner']})...\n";
    seedProjectToDb($pdo, $p, "Supabase PostgreSQL", $userMapPg);
    seedProjectToDb($sqliteDb, $p, "Local SQLite", $userMapSqlite);
}

echo "\n=== HOÀN TẤT! KIỂM TRA THỐNG KÊ TOÀN BỘ DỰ ÁN TRÊN HỆ THỐNG ===\n";
$stmt = $pdo->query("SELECT p.id, p.name, p.category, u.name as owner_name, u.username as owner_username,
    (SELECT count(*) FROM design_screens WHERE project_id = p.id) as screens,
    (SELECT count(*) FROM annotations WHERE project_id = p.id) as total_comments,
    (SELECT count(*) FROM annotations WHERE project_id = p.id AND status = 'resolved') as resolved_comments,
    (SELECT count(*) FROM annotations WHERE project_id = p.id AND status = 'open') as open_comments
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC");
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Tổng cộng " . count($list) . " dự án đang hoạt động:\n";
foreach ($list as $i => $item) {
    echo sprintf(
        " %2d. %-35s | Chủ: %-25s | Danh mục: %-20s | %d màn hình | %d góp ý (%d đã xử lý, %d chưa xử lý)\n",
        $i + 1,
        $item['name'],
        "{$item['owner_name']} (@{$item['owner_username']})",
        $item['category'],
        $item['screens'],
        $item['total_comments'],
        $item['resolved_comments'],
        $item['open_comments']
    );
}
