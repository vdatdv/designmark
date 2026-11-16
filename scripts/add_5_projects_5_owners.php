<?php
// scripts/add_5_projects_5_owners.php
// Thêm 5 dự án thuộc 5 người sở hữu khác nhau, add ngẫu nhiên thành viên và đồng bộ dữ liệu vào PostgreSQL & SQLite

require_once __DIR__ . '/../api/db.php';

echo "=== KHỞI TẠO 5 DỰ ÁN THUỘC 5 NGƯỜI SỞ HỮU KHÁC NHAU ===\n\n";

$sqlitePath = __DIR__ . '/../data/database.sqlite';
$sqliteDb = new PDO("sqlite:" . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$demoHash = '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y'; // Mật khẩu chung: demo / admin123
$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z');

// 1. Danh sách 5 người sở hữu + các thành viên hệ thống
$usersData = [
    [
        'id' => 'user-an',
        'username' => 'an',
        'name' => 'An Nguyễn',
        'title' => 'Trưởng nhóm UI/UX Designer',
        'color' => '#4F46E5',
        'email' => 'an.nguyen@designmark.vn',
        'system_role' => 'user'
    ],
    [
        'id' => 'user-binh',
        'username' => 'binh',
        'name' => 'Bình Trần',
        'title' => 'Quản lý Sản phẩm Y tế',
        'color' => '#0F766E',
        'email' => 'binh.tran@designmark.vn',
        'system_role' => 'user'
    ],
    [
        'id' => 'user-chi',
        'username' => 'chi',
        'name' => 'Chi Lê',
        'title' => 'Kiến trúc sư Trải nghiệm EduTech',
        'color' => '#B45309',
        'email' => 'chi.le@designmark.vn',
        'system_role' => 'user'
    ],
    [
        'id' => 'user-quan',
        'username' => 'quan',
        'name' => 'Đặng Minh Quân',
        'title' => 'Chuyên gia Thiết kế Fintech',
        'color' => '#059669',
        'email' => 'quan.dang@designmark.vn',
        'system_role' => 'user'
    ],
    [
        'id' => 'user-thao',
        'username' => 'thao',
        'name' => 'Hoàng Thu Thảo',
        'title' => 'Giám đốc Sáng tạo E-Commerce',
        'color' => '#DB2777',
        'email' => 'thao.hoang@designmark.vn',
        'system_role' => 'user'
    ],
    // Các thành viên khác để add ngẫu nhiên vào dự án
    [
        'id' => 'user-admin',
        'username' => 'admin',
        'name' => 'Quản trị viên Hệ thống',
        'title' => 'System Administrator',
        'color' => '#4338CA',
        'email' => 'admin@designmark.vn',
        'system_role' => 'admin'
    ],
    [
        'id' => 'user-huy',
        'username' => 'huy',
        'name' => 'Lê Quốc Huy',
        'title' => 'Kỹ sư Frontend Senior',
        'color' => '#D97706',
        'email' => 'huy.le@designmark.vn',
        'system_role' => 'user'
    ],
    [
        'id' => 'user-mai',
        'username' => 'maianh',
        'name' => 'Vũ Mai Anh',
        'title' => 'Product Owner',
        'color' => '#7C3AED',
        'email' => 'maianh.vu@designmark.vn',
        'system_role' => 'user'
    ],
    [
        'id' => 'user-thang',
        'username' => 'thang',
        'name' => 'Phạm Đức Thắng',
        'title' => 'Chuyên viên QA & Kiểm thử',
        'color' => '#2563EB',
        'email' => 'thang.pham@designmark.vn',
        'system_role' => 'user'
    ]
];

// Hàm upsert user vào cả hai database
function ensureUser($db, $u, $demoHash, $now) {
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id OR username = :u");
    $stmt->execute([':id' => $u['id'], ':u' => $u['username']]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $stmtInsert = $db->prepare("INSERT INTO users (id, username, name, role, title, color, system_role, password_hash, status, is_verified, email, created_at) VALUES (:id, :u, :name, 'manager', :title, :color, :srole, :phash, 'active', 1, :email, :created_at)");
        $stmtInsert->execute([
            ':id' => $u['id'],
            ':u' => $u['username'],
            ':name' => $u['name'],
            ':title' => $u['title'],
            ':color' => $u['color'],
            ':srole' => $u['system_role'],
            ':phash' => $demoHash,
            ':email' => $u['email'],
            ':created_at' => $now
        ]);
    } else {
        $stmtUpdate = $db->prepare("UPDATE users SET name = :name, title = :title, color = :color, email = :email WHERE id = :id");
        $stmtUpdate->execute([
            ':id' => $u['id'],
            ':name' => $u['name'],
            ':title' => $u['title'],
            ':color' => $u['color'],
            ':email' => $u['email']
        ]);
    }
}

echo "1. Đang đảm bảo tài khoản người dùng trên cả PostgreSQL và SQLite...\n";
foreach ($usersData as $u) {
    ensureUser($pdo, $u, $demoHash, $now);
    ensureUser($sqliteDb, $u, $demoHash, $now);
}
echo "   ✓ Đã cập nhật 9 tài khoản người dùng (gồm 5 chủ sở hữu riêng biệt).\n\n";

// 2. Định nghĩa chi tiết 5 dự án với 5 chủ sở hữu khác nhau
$projectsList = [
    [
        'id' => 'proj-foodhub',
        'name' => 'Ứng dụng Đặt món & Giao đồ ăn FoodHub',
        'category' => 'Di động / F&B',
        'owner_id' => 'user-an',
        'description' => 'Thiết kế ứng dụng giao đồ ăn nhanh đa nền tảng: tìm kiếm quán ăn thông minh, bộ lọc món theo khẩu vị, giỏ hàng gợi ý voucher và theo dõi tài xế thời gian thực.',
        'screens' => [
            [
                'id' => 'scr-foodhub-home',
                'name' => 'Trang chủ & Gợi ý món ngon',
                'desc' => 'Header tìm kiếm, banner ưu đãi flash sale, danh mục món ăn và danh sách quán ngon gần bạn.',
                'versions' => [
                    ['id' => 'ver-foodhub-home-1', 'num' => 1, 'key' => 'seed:foodhub-home-v1'],
                    ['id' => 'ver-foodhub-home-2', 'num' => 2, 'key' => 'seed:foodhub-home-v2']
                ]
            ],
            [
                'id' => 'scr-foodhub-cart',
                'name' => 'Giỏ hàng & Khuyến mãi voucher',
                'desc' => 'Chi tiết món đã chọn, tùy chọn topping, mã giảm giá Freeship và tổng tiền thanh toán.',
                'versions' => [
                    ['id' => 'ver-foodhub-cart-1', 'num' => 1, 'key' => 'seed:foodhub-cart-v1'],
                    ['id' => 'ver-foodhub-cart-2', 'num' => 2, 'key' => 'seed:foodhub-cart-v2']
                ]
            ],
            [
                'id' => 'scr-foodhub-tracking',
                'name' => 'Theo dõi tài xế di chuyển',
                'desc' => 'Bản đồ định vị GPS thời gian thực, thông tin phương tiện tài xế và thời gian dự kiến giao.',
                'versions' => [
                    ['id' => 'ver-foodhub-tracking-1', 'num' => 1, 'key' => 'seed:foodhub-tracking-v1']
                ]
            ]
        ],
        'annotations' => [
            [
                'screen_id' => 'scr-foodhub-home',
                'version_id' => 'ver-foodhub-home-1',
                'type' => 'point',
                'x' => 120, 'y' => 135,
                'author_user' => 'random',
                'body' => 'Banner ưu đãi 50% đang bị chìm chữ trên nền đỏ thẫm. Cần nâng tương phản WCAG AAA.',
                'status' => 'resolved',
                'reply' => 'Đã chuyển nền sang đỏ tươi và chữ trắng in hoa nổi bật trên v2.'
            ],
            [
                'screen_id' => 'scr-foodhub-home',
                'version_id' => 'ver-foodhub-home-1',
                'type' => 'rect',
                'x' => 40, 'y' => 210, 'w' => 280, 'h' => 220,
                'author_user' => 'random',
                'body' => 'Thẻ món ăn cần bổ sung thời gian giao dự kiến (ví dụ: 15-20 phút) và khoảng cách km.',
                'status' => 'resolved',
                'reply' => 'Đã thêm badge "Giao 15p" và "Giao 20p" trên mỗi card ở phiên bản v2.'
            ],
            [
                'screen_id' => 'scr-foodhub-cart',
                'version_id' => 'ver-foodhub-cart-1',
                'type' => 'point',
                'x' => 880, 'y' => 225,
                'author_user' => 'random',
                'body' => 'Mã giảm giá nên tự động chọn voucher tốt nhất cho người dùng thay vì bắt nhập tay.',
                'status' => 'resolved',
                'reply' => 'Đã cập nhật tính năng Auto-Apply Best Coupon tiết kiệm 25k.'
            ],
            [
                'screen_id' => 'scr-foodhub-cart',
                'version_id' => 'ver-foodhub-cart-2',
                'type' => 'rect',
                'x' => 65, 'y' => 260, 'w' => 480, 'h' => 60,
                'author_user' => 'random',
                'body' => 'Ô ghi chú cho tài xế nên có các tag nhanh: "Để trước cửa", "Gọi trước khi giao".',
                'status' => 'open',
                'reply' => null
            ],
            [
                'screen_id' => 'scr-foodhub-tracking',
                'version_id' => 'ver-foodhub-tracking-1',
                'type' => 'point',
                'x' => 780, 'y' => 250,
                'author_user' => 'random',
                'body' => 'Nút nhắn tin cho tài xế cần tích hợp sẵn các câu thoại mẫu để khách bấm nhanh khi bận.',
                'status' => 'open',
                'reply' => null
            ]
        ]
    ],

    [
        'id' => 'proj-medicare',
        'name' => 'Hệ thống Y tế & Đặt lịch khám MediCare',
        'category' => 'Y tế & Sức khỏe',
        'owner_id' => 'user-binh',
        'description' => 'Cổng thông tin khám chữa bệnh thông minh: đặt lịch khám trực tuyến theo chuyên khoa, tra cứu hồ sơ bệnh án điện tử và nhắc lịch uống thuốc hàng ngày.',
        'screens' => [
            [
                'id' => 'scr-medicare-booking',
                'name' => 'Đặt lịch khám theo bác sĩ & Khung giờ',
                'desc' => 'Chọn chuyên khoa, bác sĩ phụ trách, ngày hẹn và các khung giờ còn trống hoặc kín lịch.',
                'versions' => [
                    ['id' => 'ver-medicare-booking-1', 'num' => 1, 'key' => 'seed:medicare-booking-v1'],
                    ['id' => 'ver-medicare-booking-2', 'num' => 2, 'key' => 'seed:medicare-booking-v2']
                ]
            ],
            [
                'id' => 'scr-medicare-records',
                'name' => 'Hồ sơ bệnh án & Kết quả xét nghiệm',
                'desc' => 'Lịch sử khám bệnh, chỉ số sinh tồn, đơn thuốc điện tử và cảnh báo dị ứng thuốc nổi bật.',
                'versions' => [
                    ['id' => 'ver-medicare-records-1', 'num' => 1, 'key' => 'seed:medicare-records-v1'],
                    ['id' => 'ver-medicare-records-2', 'num' => 2, 'key' => 'seed:medicare-records-v2']
                ]
            ]
        ],
        'annotations' => [
            [
                'screen_id' => 'scr-medicare-booking',
                'version_id' => 'ver-medicare-booking-1',
                'type' => 'point',
                'x' => 685, 'y' => 140,
                'author_user' => 'random',
                'body' => 'Các khung giờ đã kín lịch (08:30 - 09:00) cần hiển thị chữ "ĐÃ KÍN CHỖ" màu đỏ mờ để bệnh nhân không ấn nhầm.',
                'status' => 'resolved',
                'reply' => 'Đã thay bằng badge đỏ cảnh báo ĐÃ KÍN CHỖ ở phiên bản v2.'
            ],
            [
                'screen_id' => 'scr-medicare-booking',
                'version_id' => 'ver-medicare-booking-1',
                'type' => 'rect',
                'x' => 65, 'y' => 135, 'w' => 400, 'h' => 150,
                'author_user' => 'random',
                'body' => 'Cần hiển thị thông tin học hàm/học vị (PGS.TS) và số năm kinh nghiệm khám chữa bệnh của bác sĩ.',
                'status' => 'resolved',
                'reply' => 'Đã thêm profile bác sĩ có avatar và số sao đánh giá.'
            ],
            [
                'screen_id' => 'scr-medicare-records',
                'version_id' => 'ver-medicare-records-1',
                'type' => 'rect',
                'x' => 70, 'y' => 275, 'w' => 860, 'h' => 120,
                'author_user' => 'random',
                'body' => 'Thông tin tiền sử dị ứng thuốc (Penicillin) cần được đưa lên đầu trang với màu đỏ cảnh báo khẩn.',
                'status' => 'resolved',
                'reply' => 'Đã đặt box Cảnh báo dị ứng thuốc tại vị trí ưu tiên góc trên phải v2.'
            ],
            [
                'screen_id' => 'scr-medicare-records',
                'version_id' => 'ver-medicare-records-2',
                'type' => 'point',
                'x' => 850, 'y' => 110,
                'author_user' => 'random',
                'body' => 'Nút Tải PDF bệnh án cần có xác thực mã OTP gửi về số điện thoại đăng ký.',
                'status' => 'open',
                'reply' => null
            ]
        ]
    ],

    [
        'id' => 'proj-edumaster',
        'name' => 'Nền tảng Học trực tuyến & Thi thử EduMaster',
        'category' => 'Giáo dục / EdTech',
        'owner_id' => 'user-chi',
        'description' => 'Hệ sinh thái đào tạo trực tuyến chuẩn quốc tế: bài giảng video tương tác, ghi chú bài học theo timestamp, làm bài thi trắc nghiệm bấm giờ và cấp chứng chỉ tự động.',
        'screens' => [
            [
                'id' => 'scr-edumaster-classroom',
                'name' => 'Phòng học trực tuyến & Bài giảng video',
                'desc' => 'Khung phát video chuẩn 4K, ghi chú theo thời gian thực timestamp, tài liệu đính kèm và khu vực hỏi đáp.',
                'versions' => [
                    ['id' => 'ver-edumaster-classroom-1', 'num' => 1, 'key' => 'seed:edumaster-classroom-v1'],
                    ['id' => 'ver-edumaster-classroom-2', 'num' => 2, 'key' => 'seed:edumaster-classroom-v2']
                ]
            ],
            [
                'id' => 'scr-edumaster-exam',
                'name' => 'Làm bài thi trắc nghiệm & Bấm giờ',
                'desc' => 'Giao diện làm bài thi trực tuyến, đồng hồ đếm ngược, ma trận chuyển câu hỏi và nộp bài.',
                'versions' => [
                    ['id' => 'ver-edumaster-exam-1', 'num' => 1, 'key' => 'seed:edumaster-exam-v1'],
                    ['id' => 'ver-edumaster-exam-2', 'num' => 2, 'key' => 'seed:edumaster-exam-v2']
                ]
            ]
        ],
        'annotations' => [
            [
                'screen_id' => 'scr-edumaster-classroom',
                'version_id' => 'ver-edumaster-classroom-1',
                'type' => 'point',
                'x' => 350, 'y' => 230,
                'author_user' => 'random',
                'body' => 'Trình phát video cần cho phép chọn tốc độ phát 1.25x và 1.5x để học viên tiết kiệm thời gian.',
                'status' => 'resolved',
                'reply' => 'Đã hỗ trợ bộ điều khiển tốc độ và phím tắt tua nhanh trong bản v2.'
            ],
            [
                'screen_id' => 'scr-edumaster-classroom',
                'version_id' => 'ver-edumaster-classroom-1',
                'type' => 'rect',
                'x' => 680, 'y' => 75, 'w' => 280, 'h' => 375,
                'author_user' => 'random',
                'body' => 'Bổ sung tính năng ghi chú cá nhân gắn kèm thời điểm giây trong video (Timestamp).',
                'status' => 'resolved',
                'reply' => 'Đã tích hợp tab Ghi chú theo thời gian (12:45, 28:10).'
            ],
            [
                'screen_id' => 'scr-edumaster-exam',
                'version_id' => 'ver-edumaster-exam-1',
                'type' => 'point',
                'x' => 735, 'y' => 150,
                'author_user' => 'random',
                'body' => 'Đồng hồ đếm ngược khi còn dưới 5 phút cần có thanh cảnh báo đỏ nổi bật để học sinh chú ý nộp bài.',
                'status' => 'resolved',
                'reply' => 'Đã thêm banner đỏ cảnh báo nhấp nháy khi còn dưới 5 phút ở v2.'
            ],
            [
                'screen_id' => 'scr-edumaster-exam',
                'version_id' => 'ver-edumaster-exam-2',
                'type' => 'rect',
                'x' => 65, 'y' => 165, 'w' => 600, 'h' => 190,
                'author_user' => 'random',
                'body' => 'Các đáp án trắc nghiệm cần hỗ trợ hiển thị công thức toán học KaTeX và code block.',
                'status' => 'open',
                'reply' => null
            ]
        ]
    ],

    [
        'id' => 'proj-finpay',
        'name' => 'Cổng Thanh toán & Ví điện tử FinPay',
        'category' => 'Tài chính / Fintech',
        'owner_id' => 'user-quan',
        'description' => 'Giải pháp ví điện tử thế hệ mới: quét mã VietQR đa năng 24/7, xác thực bảo mật sinh trắc học FaceID, quản lý hạn mức chi tiêu và đối soát giao dịch thời gian thực.',
        'screens' => [
            [
                'id' => 'scr-finpay-dashboard',
                'name' => 'Bảng điều khiển ví & Biểu đồ chi tiêu',
                'desc' => 'Số dư khả dụng, hạn mức trong ngày, đồ thị thu chi tự động phân loại bằng AI.',
                'versions' => [
                    ['id' => 'ver-finpay-dashboard-1', 'num' => 1, 'key' => 'seed:finpay-dashboard-v1'],
                    ['id' => 'ver-finpay-dashboard-2', 'num' => 2, 'key' => 'seed:finpay-dashboard-v2']
                ]
            ],
            [
                'id' => 'scr-finpay-qr',
                'name' => 'Quét mã QR & Xác nhận thanh toán',
                'desc' => 'Quét mã VietQR Napas 24/7, tự động điền tên chủ tài khoản thụ hưởng và xác thực FaceID.',
                'versions' => [
                    ['id' => 'ver-finpay-qr-1', 'num' => 1, 'key' => 'seed:finpay-qr-v1'],
                    ['id' => 'ver-finpay-qr-2', 'num' => 2, 'key' => 'seed:finpay-qr-v2']
                ]
            ]
        ],
        'annotations' => [
            [
                'screen_id' => 'scr-finpay-dashboard',
                'version_id' => 'ver-finpay-dashboard-1',
                'type' => 'point',
                'x' => 65, 'y' => 160,
                'author_user' => 'random',
                'body' => 'Số dư cần có nút con mắt để ẩn/hiện số tiền nhằm bảo vệ quyền riêng tư nơi công cộng.',
                'status' => 'resolved',
                'reply' => 'Đã bổ sung nút ẩn hiện số dư cùng chế độ bảo vệ FaceID ở bản v2.'
            ],
            [
                'screen_id' => 'scr-finpay-dashboard',
                'version_id' => 'ver-finpay-dashboard-1',
                'type' => 'rect',
                'x' => 430, 'y' => 75, 'w' => 530, 'h' => 220,
                'author_user' => 'random',
                'body' => 'Biểu đồ nên phân loại chi tiêu theo danh mục: Ăn uống, Hóa đơn, Mua sắm với thanh tiến độ ngân sách.',
                'status' => 'resolved',
                'reply' => 'Đã thêm biểu đồ AI phân loại danh mục % chi tiêu.'
            ],
            [
                'screen_id' => 'scr-finpay-qr',
                'version_id' => 'ver-finpay-qr-1',
                'type' => 'point',
                'x' => 450, 'y' => 245,
                'author_user' => 'random',
                'body' => 'Sau khi quét mã QR, cần hiển thị màn hình xác nhận tên chủ tài khoản và ngân hàng trước khi chuyển.',
                'status' => 'resolved',
                'reply' => 'Đã thêm màn hình xác nhận thông tin VietQR 24/7 ở v2.'
            ],
            [
                'screen_id' => 'scr-finpay-qr',
                'version_id' => 'ver-finpay-qr-2',
                'type' => 'point',
                'x' => 280, 'y' => 380,
                'author_user' => 'random',
                'body' => 'Nút xác thực FaceID cần hỗ trợ mã PIN 6 số dự phòng khi điều kiện ánh sáng yếu.',
                'status' => 'open',
                'reply' => null
            ]
        ]
    ],

    [
        'id' => 'proj-vtravel',
        'name' => 'Nền tảng Đặt tour & Khách sạn V-Travel',
        'category' => 'Du lịch / Khách sạn',
        'owner_id' => 'user-thao',
        'description' => 'Cổng dịch vụ du lịch và lữ hành trọn gói: tìm kiếm khách sạn resort cao cấp, đặt tour trải nghiệm văn hóa, đánh giá cộng đồng kèm hình ảnh thực tế và gợi ý lịch trình.',
        'screens' => [
            [
                'id' => 'scr-vtravel-explore',
                'name' => 'Khám phá điểm đến & Bộ lọc khách sạn',
                'desc' => 'Thanh tìm kiếm ngày nhận/trả phòng, bộ lọc tiện ích (Hồ bơi vô cực, Giáp biển) và danh sách phòng ưu đãi.',
                'versions' => [
                    ['id' => 'ver-vtravel-explore-1', 'num' => 1, 'key' => 'seed:vtravel-explore-v1'],
                    ['id' => 'ver-vtravel-explore-2', 'num' => 2, 'key' => 'seed:vtravel-explore-v2']
                ]
            ],
            [
                'id' => 'scr-vtravel-detail',
                'name' => 'Chi tiết phòng & Đặt chỗ thanh toán',
                'desc' => 'Xem ảnh phòng thực tế VR 360, chính sách hủy phòng miễn phí, gói bảo hiểm du lịch và đặt chỗ.',
                'versions' => [
                    ['id' => 'ver-vtravel-detail-1', 'num' => 1, 'key' => 'seed:vtravel-detail-v1'],
                    ['id' => 'ver-vtravel-detail-2', 'num' => 2, 'key' => 'seed:vtravel-detail-v2']
                ]
            ]
        ],
        'annotations' => [
            [
                'screen_id' => 'scr-vtravel-explore',
                'version_id' => 'ver-vtravel-explore-1',
                'type' => 'rect',
                'x' => 40, 'y' => 75, 'w' => 920, 'h' => 120,
                'author_user' => 'random',
                'body' => 'Thanh tìm kiếm cần có chọn ngày nhận/trả phòng và số lượng khách (người lớn, trẻ em) trực quan.',
                'status' => 'resolved',
                'reply' => 'Đã nâng cấp thanh tìm kiếm thông minh có chọn ngày và bộ lọc tiện ích ở v2.'
            ],
            [
                'screen_id' => 'scr-vtravel-explore',
                'version_id' => 'ver-vtravel-explore-1',
                'type' => 'point',
                'x' => 70, 'y' => 385,
                'author_user' => 'random',
                'body' => 'Giá phòng hiển thị cần ghi rõ đã bao gồm thuế VAT và phí dịch vụ chưa để tránh hiểu lầm.',
                'status' => 'resolved',
                'reply' => 'Đã thêm dòng ghi chú "(Đã gồm thuế & phí)" minh bạch trên v2.'
            ],
            [
                'screen_id' => 'scr-vtravel-detail',
                'version_id' => 'ver-vtravel-detail-1',
                'type' => 'point',
                'x' => 240, 'y' => 205,
                'author_user' => 'random',
                'body' => 'Thư viện ảnh nên có chế độ xem toàn cảnh thực tế ảo VR 360 độ cho các resort cao cấp.',
                'status' => 'resolved',
                'reply' => 'Đã thêm chế độ xem VR 360 độ trực quan tại bản v2.'
            ],
            [
                'screen_id' => 'scr-vtravel-detail',
                'version_id' => 'ver-vtravel-detail-2',
                'type' => 'rect',
                'x' => 670, 'y' => 265, 'w' => 265, 'h' => 40,
                'author_user' => 'random',
                'body' => 'Bảo hiểm du lịch nên cho phép tùy chọn nâng hạn mức quyền lợi (từ 500 triệu lên 1 tỷ đồng).',
                'status' => 'open',
                'reply' => null
            ]
        ]
    ]
];

// Danh sách user pool để add thành viên ngẫu nhiên
$allUserIds = array_column($usersData, 'id');

// Hàm thực thi cho từng database (PostgreSQL & SQLite)
function insertFullProject($db, $targetName, $p, $allUserIds, $now) {
    echo " -> Đang ghi dự án [{$p['name']}] vào {$targetName}...\n";

    $pid = $p['id'];
    $ownerId = $p['owner_id'];

    // Dọn sạch dự án nếu đã tồn tại để cập nhật mới tinh
    $tables = ['notifications', 'annotation_replies', 'annotation_status_events', 'annotations', 'image_versions', 'design_screens', 'activities', 'project_members', 'projects'];
    foreach ($tables as $t) {
        try {
            $db->prepare("DELETE FROM {$t} WHERE project_id = :pid")->execute([':pid' => $pid]);
        } catch (Throwable $e) {}
    }
    try {
        $db->prepare("DELETE FROM projects WHERE id = :pid")->execute([':pid' => $pid]);
    } catch (Throwable $e) {}

    // 1. Insert Project
    $stmtProj = $db->prepare("INSERT INTO projects (id, name, description, category, created_by, created_at, updated_at) VALUES (:id, :name, :desc, :cat, :owner, :now, :now)");
    $stmtProj->execute([
        ':id' => $pid,
        ':name' => $p['name'],
        ':desc' => $p['description'],
        ':cat' => $p['category'],
        ':owner' => $ownerId,
        ':now' => $now
    ]);

    // 2. Add ngẫu nhiên thành viên vào dự án
    // Chủ sở hữu luôn là manager
    $stmtMem = $db->prepare("INSERT INTO project_members (id, project_id, user_id, role, invited_by, created_at) VALUES (:id, :pid, :uid, :role, :inv, :now)");
    $stmtMem->execute([
        ':id' => "mem-{$pid}-{$ownerId}",
        ':pid' => $pid,
        ':uid' => $ownerId,
        ':role' => 'manager',
        ':inv' => null,
        ':now' => $now
    ]);

    // Chọn ngẫu nhiên 3 đến 5 thành viên khác từ user pool
    $candidateMembers = array_diff($allUserIds, [$ownerId]);
    shuffle($candidateMembers);
    $numMembers = rand(3, 5);
    $selectedMembers = array_slice($candidateMembers, 0, $numMembers);

    $projectMemberIds = [$ownerId];
    $rolesPool = ['reviewer', 'viewer', 'reviewer', 'manager'];

    foreach ($selectedMembers as $mIndex => $mUid) {
        $assignedRole = $rolesPool[$mIndex % count($rolesPool)];
        $stmtMem->execute([
            ':id' => "mem-{$pid}-{$mUid}",
            ':pid' => $pid,
            ':uid' => $mUid,
            ':role' => $assignedRole,
            ':inv' => $ownerId,
            ':now' => $now
        ]);
        $projectMemberIds[] = $mUid;
    }

    // 3. Insert Screens & Versions
    $stmtScreen = $db->prepare("INSERT INTO design_screens (id, project_id, name, description, created_by, created_at, updated_at) VALUES (:id, :pid, :name, :desc, :cb, :now, :now)");
    $stmtVer = $db->prepare("INSERT INTO image_versions (id, project_id, screen_id, number, image_key, width, height, mime_type, source, created_by, created_at) VALUES (:id, :pid, :sid, :num, :key, 1000, 500, 'image/svg+xml', 'seed', :cb, :now)");

    foreach ($p['screens'] as $s) {
        $stmtScreen->execute([
            ':id' => $s['id'],
            ':pid' => $pid,
            ':name' => $s['name'],
            ':desc' => $s['desc'],
            ':cb' => $ownerId,
            ':now' => $now
        ]);

        foreach ($s['versions'] as $v) {
            $stmtVer->execute([
                ':id' => $v['id'],
                ':pid' => $pid,
                ':sid' => $s['id'],
                ':num' => $v['num'],
                ':key' => $v['key'],
                ':cb' => $ownerId,
                ':now' => $now
            ]);
        }
    }

    // 4. Insert Annotations, Replies & Status Events
    $stmtAnn = $db->prepare("INSERT INTO annotations (id, screen_id, project_id, image_version_id, type, x, y, w, h, original_width, original_height, author_id, body, status, revision, check_note, number, created_at, updated_at) VALUES (:id, :sid, :pid, :vid, :type, :x, :y, :w, :h, 1000, 500, :author, :body, :status, 1, '', :num, :now, :now)");
    $stmtRep = $db->prepare("INSERT INTO annotation_replies (id, annotation_id, author_id, body, created_at) VALUES (:id, :aid, :author, :body, :now)");
    $stmtEvt = $db->prepare("INSERT INTO annotation_status_events (id, annotation_id, status, user_id, note, created_at) VALUES (:id, :aid, :st, :uid, :note, :now)");

    foreach ($p['annotations'] as $aIdx => $a) {
        $aid = "ann-{$pid}-" . ($aIdx + 1);

        // Chọn ngẫu nhiên một thành viên trong dự án làm author
        $author = $projectMemberIds[array_rand($projectMemberIds)];

        $stmtAnn->execute([
            ':id' => $aid,
            ':sid' => $a['screen_id'],
            ':pid' => $pid,
            ':vid' => $a['version_id'],
            ':type' => $a['type'],
            ':x' => $a['x'],
            ':y' => $a['y'],
            ':w' => $a['w'] ?? 0,
            ':h' => $a['h'] ?? 0,
            ':author' => $author,
            ':body' => $a['body'],
            ':status' => $a['status'],
            ':num' => $aIdx + 1,
            ':now' => $now
        ]);

        // Tạo sự kiện mở góp ý
        $stmtEvt->execute([
            ':id' => "evt-{$aid}-open",
            ':aid' => $aid,
            ':st' => 'open',
            ':uid' => $author,
            ':note' => 'Đã khởi tạo góp ý trên giao diện.',
            ':now' => $now
        ]);

        // Phản hồi từ chủ dự án nếu có
        if (!empty($a['reply'])) {
            $stmtRep->execute([
                ':id' => "rep-{$aid}-1",
                ':aid' => $aid,
                ':author' => $ownerId,
                ':body' => $a['reply'],
                ':now' => $now
            ]);
        }

        // Nếu trạng thái đã xử lý thì thêm event resolved
        if ($a['status'] === 'resolved') {
            $stmtEvt->execute([
                ':id' => "evt-{$aid}-res",
                ':aid' => $aid,
                ':st' => 'resolved',
                ':uid' => $ownerId,
                ':note' => 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.',
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
        ':msg' => "Dự án \"{$p['name']}\" đã được khởi tạo bởi chủ sở hữu.",
        ':now' => $now
    ]);
    $stmtAct->execute([
        ':id' => "act-{$pid}-2",
        ':pid' => $pid,
        ':uid' => $ownerId,
        ':type' => 'screen_added',
        ':msg' => "Đã tải lên " . count($p['screens']) . " màn hình thiết kế kèm phiên bản so sánh v1/v2.",
        ':now' => $now
    ]);
    $stmtAct->execute([
        ':id' => "act-{$pid}-3",
        ':pid' => $pid,
        ':uid' => $ownerId,
        ':type' => 'member_added',
        ':msg' => "Đã mời " . (count($projectMemberIds) - 1) . " thành viên tham gia cộng tác duyệt thiết kế.",
        ':now' => $now
    ]);
}

// Chạy khởi tạo 5 dự án trên cả PostgreSQL (Supabase) và SQLite
foreach ($projectsList as $index => $proj) {
    echo "\n[" . ($index + 1) . "/5] KHỞI TẠO DỰ ÁN: {$proj['name']}\n";
    echo "Chủ sở hữu: {$proj['owner_id']}\n";

    // 1. PostgreSQL (Supabase)
    insertFullProject($pdo, "Supabase PostgreSQL", $proj, $allUserIds, $now);

    // 2. SQLite (Cục bộ)
    insertFullProject($sqliteDb, "Local SQLite", $proj, $allUserIds, $now);
}

echo "\n=======================================================\n";
echo "✓ HOÀN TẤT CẬP NHẬT 5 DỰ ÁN & THÀNH VIÊN TRÊN TOÀN BỘ CSDL!\n";
echo "=======================================================\n\n";

// Kiểm tra danh sách dự án sau khi tạo
$stmt = $pdo->query("SELECT p.id, p.name, p.category, u.name as owner_name, u.username as owner_uname,
    (SELECT count(*) FROM project_members WHERE project_id = p.id) as members_count,
    (SELECT count(*) FROM design_screens WHERE project_id = p.id) as screens_count,
    (SELECT count(*) FROM annotations WHERE project_id = p.id) as ann_count,
    (SELECT count(*) FROM annotations WHERE project_id = p.id AND status = 'resolved') as resolved_count
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC");
$allProjects = $stmt->fetchAll();

echo "DANH SÁCH DỰ ÁN ĐANG HOẠT ĐỘNG:\n";
foreach ($allProjects as $i => $row) {
    echo sprintf(
        " %d. %-45s | Chủ: %-22s | DM: %-20s | %d TV | %d MH | %d góp ý (%d đã xử lý)\n",
        $i + 1,
        $row['name'],
        "{$row['owner_name']} (@{$row['owner_uname']})",
        $row['category'],
        $row['members_count'],
        $row['screens_count'],
        $row['ann_count'],
        $row['resolved_count']
    );
}

