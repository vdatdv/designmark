<?php
// scripts/seed_an_smartbank_50_annotations.php
// Tạo 1 dự án do user-an quản lý với 50 góp ý phù hợp và chân thực

require_once __DIR__ . '/../api/db.php';

echo "=== DESIGNMARK: KHỞI TẠO DỰ ÁN DO AN QUẢN LÝ VỚI 50 GÓP Ý ===\n\n";

$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

$projectId = 'proj-an-smartbank';
$projectName = 'Nền tảng Ngân hàng số & Quản lý Tài chính SmartBank';
$projectDesc = 'Hệ sinh thái tài chính và ngân hàng số thông minh: quản lý tài khoản thanh toán, chuyển tiền nhanh Napas 24/7, quét mã VietQR, quản lý thẻ tín dụng, tiết kiệm trực tuyến và phân tích chi tiêu cá nhân.';
$projectCategory = 'Tài chính / Fintech';
$createdBy = 'user-an';

// 1. Xóa dữ liệu cũ nếu đã tồn tại để tạo mới tinh
echo "1. Dọn dẹp dữ liệu cũ (nếu có)...\n";
$delSqls = [
    "DELETE FROM comment_reactions WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = '$projectId')",
    "DELETE FROM annotation_status_events WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = '$projectId')",
    "DELETE FROM annotation_replies WHERE annotation_id IN (SELECT id FROM annotations WHERE project_id = '$projectId')",
    "DELETE FROM annotations WHERE project_id = '$projectId'",
    "DELETE FROM image_versions WHERE project_id = '$projectId'",
    "DELETE FROM design_screens WHERE project_id = '$projectId'",
    "DELETE FROM project_members WHERE project_id = '$projectId'",
    "DELETE FROM activities WHERE project_id = '$projectId'",
    "DELETE FROM projects WHERE id = '$projectId'"
];

foreach ($delSqls as $sql) {
    try { $pdo->exec($sql); } catch (Throwable $e) {}
    try { mirror_to_sqlite($sql); } catch (Throwable $e) {}
}

// 2. Tạo Dự án mới
echo "2. Tạo Dự án mới do An Nguyễn quản lý...\n";
$stmt = $pdo->prepare("INSERT INTO projects (id, name, description, category, created_by, created_at, updated_at) VALUES (:id, :name, :desc, :cat, :cb, :ca, :ua)");
$stmt->execute([
    ':id' => $projectId,
    ':name' => $projectName,
    ':desc' => $projectDesc,
    ':cat' => $projectCategory,
    ':cb' => $createdBy,
    ':ca' => $now,
    ':ua' => $now
]);
mirror_to_sqlite("INSERT INTO projects (id, name, description, category, created_by, created_at, updated_at) VALUES ('$projectId', " . $pdo->quote($projectName) . ", " . $pdo->quote($projectDesc) . ", '$projectCategory', '$createdBy', '$now', '$now')");

// 3. Thêm thành viên vào dự án
echo "3. Thêm thành viên dự án (An là Manager, Bình & Quân là Reviewer, Chi là Viewer)...\n";
$members = [
    ['id' => 'pm-sb-an', 'user_id' => 'user-an', 'role' => 'manager'],
    ['id' => 'pm-sb-binh', 'user_id' => 'user-binh', 'role' => 'reviewer'],
    ['id' => 'pm-sb-quan', 'user_id' => 'user-quan', 'role' => 'reviewer'],
    ['id' => 'pm-sb-chi', 'user_id' => 'user-chi', 'role' => 'viewer']
];

foreach ($members as $m) {
    $stmt = $pdo->prepare("INSERT INTO project_members (id, project_id, user_id, role, created_at) VALUES (:id, :pid, :uid, :role, :ca)");
    $stmt->execute([
        ':id' => $m['id'],
        ':pid' => $projectId,
        ':uid' => $m['user_id'],
        ':role' => $m['role'],
        ':ca' => $now
    ]);
    mirror_to_sqlite("INSERT INTO project_members (id, project_id, user_id, role, created_at) VALUES ('{$m['id']}', '$projectId', '{$m['user_id']}', '{$m['role']}', '$now')");
}

// 4. Tạo 3 màn hình thiết kế
echo "4. Tạo 3 màn hình thiết kế với phiên bản ảnh SVG...\n";
$screens = [
    [
        'id' => 'scr-sb-dash',
        'name' => '01. Bảng điều khiển Tài khoản & Số dư (Dashboard)',
        'desc' => 'Giao diện tổng quan tài khoản thanh toán, biến động số dư và truy cập nhanh các dịch vụ.',
        'order' => 1,
        'v1' => ['id' => 'ver-sb-dash-1', 'key' => 'seed:finpay-dashboard-v1'],
        'v2' => ['id' => 'ver-sb-dash-2', 'key' => 'seed:finpay-dashboard-v2']
    ],
    [
        'id' => 'scr-sb-qr',
        'name' => '02. Cổng Chuyển tiền & Quét mã VietQR',
        'desc' => 'Tính năng chuyển khoản liên ngân hàng 24/7, tạo mã VietQR động và danh bạ thụ hưởng.',
        'order' => 2,
        'v1' => ['id' => 'ver-sb-qr-1', 'key' => 'seed:finpay-qr-v1'],
        'v2' => ['id' => 'ver-sb-qr-2', 'key' => 'seed:finpay-qr-v2']
    ],
    [
        'id' => 'scr-sb-card',
        'name' => '03. Quản lý Thẻ tín dụng & Cài đặt Bảo mật',
        'desc' => 'Trung tâm kiểm soát thẻ thanh toán, thẻ tín dụng ảo, khóa/mở thẻ và giới hạn chi tiêu.',
        'order' => 3,
        'v1' => ['id' => 'ver-sb-card-1', 'key' => 'seed:pay-v1'],
        'v2' => ['id' => 'ver-sb-card-2', 'key' => 'seed:pay-v2']
    ]
];

foreach ($screens as $s) {
    $stmt = $pdo->prepare("INSERT INTO design_screens (id, project_id, name, description, sort_order, created_by, created_at, updated_at) VALUES (:id, :pid, :name, :desc, :ord, :cb, :ca, :ua)");
    $stmt->execute([
        ':id' => $s['id'],
        ':pid' => $projectId,
        ':name' => $s['name'],
        ':desc' => $s['desc'],
        ':ord' => $s['order'],
        ':cb' => $createdBy,
        ':ca' => $now,
        ':ua' => $now
    ]);
    mirror_to_sqlite("INSERT INTO design_screens (id, project_id, name, description, sort_order, created_by, created_at, updated_at) VALUES ('{$s['id']}', '$projectId', " . $pdo->quote($s['name']) . ", " . $pdo->quote($s['desc']) . ", {$s['order']}, '$createdBy', '$now', '$now')");

    // Thêm bản v1
    $stmt = $pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_by, created_at) VALUES (:id, :sid, :pid, 1, 1000, 500, :key, 'image/svg+xml', 'seed', :cb, :ca)");
    $stmt->execute([
        ':id' => $s['v1']['id'],
        ':sid' => $s['id'],
        ':pid' => $projectId,
        ':key' => $s['v1']['key'],
        ':cb' => $createdBy,
        ':ca' => $now
    ]);
    mirror_to_sqlite("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_by, created_at) VALUES ('{$s['v1']['id']}', '{$s['id']}', '$projectId', 1, 1000, 500, '{$s['v1']['key']}', 'image/svg+xml', 'seed', '$createdBy', '$now')");

    // Thêm bản v2
    $stmt = $pdo->prepare("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_by, created_at) VALUES (:id, :sid, :pid, 2, 1000, 500, :key, 'image/svg+xml', 'seed', :cb, :ca)");
    $stmt->execute([
        ':id' => $s['v2']['id'],
        ':sid' => $s['id'],
        ':pid' => $projectId,
        ':key' => $s['v2']['key'],
        ':cb' => $createdBy,
        ':ca' => $now
    ]);
    mirror_to_sqlite("INSERT INTO image_versions (id, screen_id, project_id, number, width, height, image_key, mime_type, source, created_by, created_at) VALUES ('{$s['v2']['id']}', '{$s['id']}', '$projectId', 2, 1000, 500, '{$s['v2']['key']}', 'image/svg+xml', 'seed', '$createdBy', '$now')");
}

// 5. Danh sách 50 góp ý phù hợp chi tiết
echo "5. Tạo 50 góp ý chi tiết trải đều trên 3 màn hình...\n";

$rawAnnotations = [
    // --- MÀN HÌNH 1: DASHBOARD (20 góp ý) ---
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'rect', 'x' => 60, 'y' => 50, 'w' => 380, 'h' => 120,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Thẻ số dư tài khoản cần bo góc 16px và tăng độ tương phản của chữ số dư để đạt chuẩn tiếp cận WCAG AA.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 410, 'y' => 75, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'resolved',
        'body' => 'Thêm icon con mắt ẩn/hiện số dư để bảo vệ quyền riêng tư cho người dùng khi mở app nơi đông người.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 120, 'y' => 200, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Nút Chuyển tiền nhanh cần đổi sang màu chủ đạo #E11D48 và thêm hiệu ứng nhấp nháy nhẹ khi có ưu đãi.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'rect', 'x' => 60, 'y' => 180, 'w' => 880, 'h' => 90,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Khoảng cách giữa các nút Quick Actions (Chuyển tiền, Nạp tiền, Thanh toán hóa đơn, Tiết kiệm) nên tăng lên 16px để dễ chạm.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 850, 'y' => 65, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Icon chuông thông báo cần hiển thị chấm đỏ badge khi có tin biến động số dư chưa đọc.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'rect', 'x' => 60, 'y' => 290, 'w' => 580, 'h' => 180,
        'author' => 'user-quan', 'status' => 'resolved',
        'body' => 'Biểu đồ chi tiêu tuần cần hiển thị tooltip số tiền cụ thể khi người dùng hover hoặc chạm ngón tay.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'arrow', 'x' => 680, 'y' => 310, 'w' => 260, 'h' => 60,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Mũi tên xem tất cả lịch sử giao dịch nên làm nổi bật màu xanh dương và có nhãn "Xem chi tiết".'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 720, 'y' => 360, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Các khoản tiền chi ra cần có dấu trừ màu đỏ (-500.000 đ), tiền nhận vào có dấu cộng màu xanh lá (+2.000.000 đ).'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 200, 'y' => 90, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'resolved',
        'body' => 'Font chữ số tài khoản nên chọn font monospace để các con số thẳng hàng, dễ sao chép.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'rect', 'x' => 500, 'y' => 50, 'w' => 320, 'h' => 120,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Thẻ quà tặng / Điểm thưởng SmartPoint bên cạnh cần có thanh tiến trình đổi voucher tự động.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 300, 'y' => 240, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Nút "Gửi tiết kiệm online" nên có tag nhỏ "Lãi suất 7.2%/năm" để kích thích người dùng.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 920, 'y' => 240, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Thêm nút "Xem thêm dịch vụ" ở cuối hàng Quick Actions để mở rộng lưới tính năng tiện ích.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'freehand', 'x' => 660, 'y' => 400, 'w' => 260, 'h' => 70,
        'author' => 'user-an', 'status' => 'resolved',
        'body' => 'Khu vực này cần thêm nút lọc giao dịch nhanh theo Tuần này / Tháng này.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 80, 'y' => 140, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Số tiền hiển thị cần làm tròn định dạng phân cách dấu chấm: 158.420.000 đ thay vì viết liền.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'rect', 'x' => 60, 'y' => 470, 'w' => 880, 'h' => 25,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Banner khuyến mại trượt ngang ở chân trang nên có chức năng tự động chuyển sau mỗi 4 giây.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 90, 'y' => 25, 'w' => 0, 'h' => 0,
        'author' => 'user-an', 'status' => 'resolved',
        'body' => 'Thêm câu chào cá nhân hóa: "Xin chào, An Nguyễn! Chúc bạn ngày mới tốt lành."'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 890, 'y' => 25, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Nút chuyển đổi chế độ Sáng / Tối (Dark/Light mode) nên đặt ở thanh tiêu đề trên cùng bên phải.'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'rect', 'x' => 680, 'y' => 380, 'w' => 240, 'h' => 45,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Tên người gửi tiền nên hiển thị tên ngân hàng chuyển khoản (VD: Vietcombank, Techcombank).'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'arrow', 'x' => 350, 'y' => 140, 'w' => 120, 'h' => 30,
        'author' => 'user-an', 'status' => 'resolved',
        'body' => 'Nút sao chép STK cần hiển thị popup thông báo nhẹ "Đã sao chép vào bộ nhớ tạm".'
    ],
    [
        'screen_id' => 'scr-sb-dash',
        'ver_id' => 'ver-sb-dash-1',
        'type' => 'point', 'x' => 500, 'y' => 240, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Nút Thanh toán hóa đơn cần có icon đồng hồ nhắc nợ hóa đơn Điện / Nước sắp đến hạn.'
    ],

    // --- MÀN HÌNH 2: CHUYỂN TIỀN & VIETQR (16 góp ý) ---
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'rect', 'x' => 80, 'y' => 50, 'w' => 400, 'h' => 380,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Khung viền camera quét mã VietQR cần có 4 góc bo sáng định vị và đường quét laser animation chuyển động.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 280, 'y' => 440, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'resolved',
        'body' => 'Thêm nút bật/tắt đèn Flash để hỗ trợ người dùng quét mã QR trong điều kiện trời tối.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 150, 'y' => 440, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Thêm nút "Tải ảnh QR từ thư viện ảnh" để quét mã khi người dùng nhận ảnh QR qua Zalo.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'rect', 'x' => 520, 'y' => 50, 'w' => 420, 'h' => 60,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Ô tìm kiếm danh bạ thụ hưởng nên hỗ trợ tìm theo cả Tên người nhận, Số tài khoản và Tên ngân hàng.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 900, 'y' => 80, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'resolved',
        'body' => 'Nút xóa tìm kiếm (X) nên xuất hiện ngay khi người dùng bắt đầu nhập ký tự.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'rect', 'x' => 520, 'y' => 130, 'w' => 420, 'h' => 90,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Danh sách người nhận gần đây nên hiển thị logo ngân hàng nhỏ ở góc avatar để dễ phân biệt.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 600, 'y' => 250, 'w' => 0, 'h' => 0,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Ô nhập số tiền cần có gợi ý nhanh các mốc: 100k, 200k, 500k, 1 triệu, 2 triệu, 5 triệu.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 880, 'y' => 250, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Hiển thị số tiền bằng chữ (Ví dụ: Một triệu năm trăm nghìn đồng) để tránh chuyển khoản nhầm số 0.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'rect', 'x' => 520, 'y' => 310, 'w' => 420, 'h' => 50,
        'author' => 'user-quan', 'status' => 'resolved',
        'body' => 'Ô nội dung chuyển tiền nên mặc định điền cú pháp chuẩn hoặc để trống, giới hạn tối đa 140 ký tự không dấu.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'arrow', 'x' => 520, 'y' => 380, 'w' => 420, 'h' => 45,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Nút "Tiếp tục chuyển tiền" cần cố định ở cạnh dưới và chỉ kích hoạt khi đã nhập đủ thông tin.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 700, 'y' => 440, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'resolved',
        'body' => 'Thêm checkbox "Lưu người thụ hưởng vào danh bạ" để người dùng tiện chuyển khoản lần sau.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 840, 'y' => 440, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Phí giao dịch chuyển khoản cần ghi rõ "0 đ (Miễn phí Napas 24/7)" màu xanh lá.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'freehand', 'x' => 120, 'y' => 120, 'w' => 320, 'h' => 200,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Vùng quét mã cần thu nhỏ viền đen và tăng độ mờ background ngoài vùng quét.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 350, 'y' => 30, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'resolved',
        'body' => 'Thêm tab chuyển đổi: [Quét mã QR] và [Mã QR của tôi] ở đầu trang.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'point', 'x' => 750, 'y' => 180, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Khi chuyển nội bộ cùng ngân hàng SmartBank, cần tự động điền tên người nhận tức thì.'
    ],
    [
        'screen_id' => 'scr-sb-qr',
        'ver_id' => 'ver-sb-qr-1',
        'type' => 'rect', 'x' => 520, 'y' => 270, 'w' => 200, 'h' => 30,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Cần hiển thị số dư khả dụng hiện tại ngay phía trên ô nhập số tiền để người dùng đối chiếu.'
    ],

    // --- MÀN HÌNH 3: QUẢN LÝ THẺ TÍN DỤNG & CÀI ĐẶT (14 góp ý) ---
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'rect', 'x' => 80, 'y' => 60, 'w' => 450, 'h' => 260,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Mặt trước thẻ tín dụng ảo cần làm hiệu ứng ánh kim dập nổi kèm logo chip EMV và logo Visa Signature.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 460, 'y' => 90, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'resolved',
        'body' => 'Thêm nút "Lật thẻ" (Flip Card) để xem mã bảo mật CVV và ngày hết hạn ở mặt sau.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 120, 'y' => 240, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'open',
        'body' => 'Số thẻ tín dụng nên mặc định che dạng **** **** **** 8899, chạm vào để hiển thị đầy đủ trong 30 giây.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'rect', 'x' => 560, 'y' => 60, 'w' => 380, 'h' => 90,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Thanh tiến trình hạn mức tín dụng (Đã dùng 18.500.000 / 50.000.000 đ) cần dùng màu gradient tím sang hồng.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 880, 'y' => 90, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Ngày đến hạn thanh toán sao kê (25/11/2026) cần làm nổi bật màu đỏ cam để nhắc nhở tránh nợ quá hạn.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'rect', 'x' => 560, 'y' => 170, 'w' => 380, 'h' => 45,
        'author' => 'user-quan', 'status' => 'resolved',
        'body' => 'Công tắc bật/tắt "Khóa thẻ tạm thời" cần hiển thị hộp thoại xác thực khuôn mặt FaceID trước khi thực thi.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'rect', 'x' => 560, 'y' => 230, 'w' => 380, 'h' => 45,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Nút "Cài đặt hạn mức chi tiêu online" nên cho phép thiết lập số tiền tối đa cho 1 giao dịch và 1 ngày.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 200, 'y' => 360, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Thêm nút "Đổi mã PIN thẻ" trực tiếp trên ứng dụng mà không cần phải ra cây ATM vật lý.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 400, 'y' => 360, 'w' => 0, 'h' => 0,
        'author' => 'user-quan', 'status' => 'resolved',
        'body' => 'Nút "Thanh toán dư nợ sao kê" nên cho phép chọn thanh toán toàn bộ hoặc thanh toán số tiền tối thiểu.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'rect', 'x' => 80, 'y' => 410, 'w' => 860, 'h' => 70,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Danh sách giao dịch thẻ gần đây cần phân loại icon rõ ràng: Ăn uống (Cam), Siêu thị (Xanh dương), Du lịch (Xanh lá).'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 900, 'y' => 440, 'w' => 0, 'h' => 0,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Thêm tính năng "Chuyển đổi trả góp 0%" ngay bên cạnh các giao dịch có giá trị từ 3 triệu đồng trở lên.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'arrow', 'x' => 750, 'y' => 110, 'w' => 160, 'h' => 30,
        'author' => 'user-quan', 'status' => 'resolved',
        'body' => 'Mũi tên xem chi tiết sao kê tháng cần có liên kết tải tệp sao kê PDF có đóng dấu điện tử.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'point', 'x' => 150, 'y' => 100, 'w' => 0, 'h' => 0,
        'author' => 'user-an', 'status' => 'open',
        'body' => 'Tên chủ thẻ "AN NGUYEN" nên dùng font chữ dập nổi chuẩn thẻ ngân hàng quốc tế.'
    ],
    [
        'screen_id' => 'scr-sb-card',
        'ver_id' => 'ver-sb-card-1',
        'type' => 'freehand', 'x' => 560, 'y' => 290, 'w' => 380, 'h' => 50,
        'author' => 'user-binh', 'status' => 'open',
        'body' => 'Vùng cài đặt thanh toán quốc tế cần bổ sung dòng giải thích: "Kích hoạt để mua sắm trên Shopee, Netflix, Spotify, App Store".'
    ]
];

$count = 0;
$stmtAnn = $pdo->prepare("
    INSERT INTO annotations (id, image_version_id, screen_id, project_id, author_id, type, x, y, w, h, original_width, original_height, body, status, revision, number, created_at, updated_at)
    VALUES (:id, :vid, :sid, :pid, :aid, :type, :x, :y, :w, :h, 1000, 500, :body, :status, 1, :num, :ca, :ua)
");

foreach ($rawAnnotations as $idx => $a) {
    $count++;
    $annId = 'ann-sb-' . sprintf('%03d', $count);
    $time = date('Y-m-d\TH:i:s.000\Z', strtotime("-" . (60 - $count) . " hours"));
    
    $stmtAnn->execute([
        ':id' => $annId,
        ':vid' => $a['ver_id'],
        ':sid' => $a['screen_id'],
        ':pid' => $projectId,
        ':aid' => $a['author'],
        ':type' => $a['type'],
        ':x' => $a['x'],
        ':y' => $a['y'],
        ':w' => $a['w'],
        ':h' => $a['h'],
        ':body' => $a['body'],
        ':status' => $a['status'],
        ':num' => $count,
        ':ca' => $time,
        ':ua' => $time
    ]);

    $sqliteBody = $pdo->quote($a['body']);
    mirror_to_sqlite("INSERT INTO annotations (id, image_version_id, screen_id, project_id, author_id, type, x, y, w, h, original_width, original_height, body, status, revision, number, created_at, updated_at) VALUES ('$annId', '{$a['ver_id']}', '{$a['screen_id']}', '$projectId', '{$a['author']}', '{$a['type']}', {$a['x']}, {$a['y']}, {$a['w']}, {$a['h']}, 1000, 500, $sqliteBody, '{$a['status']}', 1, $count, '$time', '$time')");

    // Thêm phản hồi thảo luận cho 15 góp ý đầu tiên
    if ($count <= 15) {
        $repId = 'rep-sb-' . sprintf('%03d', $count);
        $repUser = ($a['author'] === 'user-an') ? 'user-binh' : 'user-an';
        $repBody = "Đồng ý với ý kiến của @" . ($a['author'] === 'user-an' ? 'an' : 'binh') . ", đội ngũ UI Designer sẽ cập nhật vào bản vẽ v2.";
        $repTime = date('Y-m-d\TH:i:s.000\Z', strtotime("-" . (59 - $count) . " hours"));
        
        $pdo->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES (:id, :aid, :uid, :body, :ca)")
            ->execute([':id' => $repId, ':aid' => $annId, ':uid' => $repUser, ':body' => $repBody, ':ca' => $repTime]);
        mirror_to_sqlite("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES ('$repId', '$annId', '$repUser', " . $pdo->quote($repBody) . ", '$repTime')");

        // Thêm cảm xúc Emoji cho phản hồi
        $reactId = 'react-sb-' . sprintf('%03d', $count);
        $pdo->prepare("INSERT INTO comment_reactions (id, annotation_id, reply_id, user_id, emoji, created_at) VALUES (:id, :aid, :rid, :uid, '👍', :ca)")
            ->execute([':id' => $reactId, ':aid' => $annId, ':rid' => $repId, ':uid' => 'user-an', ':ca' => $repTime]);
        mirror_to_sqlite("INSERT INTO comment_reactions (id, annotation_id, reply_id, user_id, emoji, created_at) VALUES ('$reactId', '$annId', '$repId', 'user-an', '👍', '$repTime')");
    }
}

// 6. Thêm hoạt động vào bảng activities
echo "6. Tạo dòng thời gian hoạt động (Activities)...\n";
$pdo->prepare("INSERT INTO activities (id, project_id, user_id, type, message, created_at) VALUES (:id, :pid, :uid, :type, :msg, :ca)")
    ->execute([
        ':id' => 'act-sb-create',
        ':pid' => $projectId,
        ':uid' => 'user-an',
        ':type' => 'project_created',
        ':msg' => 'Đã khởi tạo dự án Nền tảng Ngân hàng số & Quản lý Tài chính SmartBank',
        ':ca' => $now
    ]);
mirror_to_sqlite("INSERT INTO activities (id, project_id, user_id, type, message, created_at) VALUES ('act-sb-create', '$projectId', 'user-an', 'project_created', 'Đã khởi tạo dự án Nền tảng Ngân hàng số & Quản lý Tài chính SmartBank', '$now')");

echo "\n🎉 HOÀN TẤT THÀNH CÔNG!\n";
echo " - Dự án: $projectName (ID: $projectId)\n";
echo " - Người quản lý: An Nguyễn (user-an)\n";
echo " - Số màn hình: 3 màn hình\n";
echo " - Tổng số góp ý đã tạo: $count góp ý (Trải đều Point, Rect, Arrow, Freehand)\n";
echo " - Đã đồng bộ hoàn hảo trên cả Supabase PostgreSQL và SQLite cục bộ!\n";
