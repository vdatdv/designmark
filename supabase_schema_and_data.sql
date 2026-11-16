-- ====================================================================
-- DesignMark - Supabase PostgreSQL Database Schema & Seed Data
-- Backup Date: 2026-10-29 03:12:59 UTC
-- Compatible with: Supabase PostgreSQL (SQL Editor & psql)
-- ====================================================================

-- 1. CẤU TRÚC BẢNG (TABLE SCHEMAS)

CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('manager', 'reviewer', 'viewer', 'pending')),
    title TEXT,
    color TEXT,
    password_hash TEXT,
    is_verified INTEGER DEFAULT 1,
    verification_code TEXT,
    verified_at TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS projects (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_members (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role TEXT NOT NULL CHECK (role IN ('manager', 'reviewer', 'viewer')),
    invited_by TEXT,
    created_at TEXT NOT NULL,
    UNIQUE(project_id, user_id)
);

CREATE TABLE IF NOT EXISTS design_screens (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS image_versions (
    id TEXT PRIMARY KEY,
    screen_id TEXT NOT NULL REFERENCES design_screens(id) ON DELETE CASCADE,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    number INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    image_key TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    source TEXT NOT NULL,
    created_at TEXT NOT NULL,
    created_by TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS annotations (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    screen_id TEXT NOT NULL REFERENCES design_screens(id) ON DELETE CASCADE,
    image_version_id TEXT NOT NULL REFERENCES image_versions(id) ON DELETE CASCADE,
    type TEXT NOT NULL CHECK (type IN ('point', 'rect', 'area')),
    x REAL NOT NULL,
    y REAL NOT NULL,
    w REAL NOT NULL,
    h REAL NOT NULL,
    original_width INTEGER NOT NULL,
    original_height INTEGER NOT NULL,
    author_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'resolved', 'rejected')),
    revision INTEGER NOT NULL DEFAULT 1,
    check_note TEXT DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS annotation_replies (
    id TEXT PRIMARY KEY,
    annotation_id TEXT NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    author_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS annotation_status_events (
    id TEXT PRIMARY KEY,
    annotation_id TEXT NOT NULL REFERENCES annotations(id) ON DELETE CASCADE,
    status TEXT NOT NULL,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    note TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS activities (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_projects_created_by ON projects(created_by);
CREATE INDEX IF NOT EXISTS idx_project_members_project ON project_members(project_id);
CREATE INDEX IF NOT EXISTS idx_project_members_user ON project_members(user_id);
CREATE INDEX IF NOT EXISTS idx_design_screens_project ON design_screens(project_id);
CREATE INDEX IF NOT EXISTS idx_image_versions_screen ON image_versions(screen_id);
CREATE INDEX IF NOT EXISTS idx_annotations_version ON annotations(image_version_id);
CREATE INDEX IF NOT EXISTS idx_annotations_screen ON annotations(screen_id);
CREATE INDEX IF NOT EXISTS idx_annotation_replies_ann ON annotation_replies(annotation_id);
CREATE INDEX IF NOT EXISTS idx_activities_project ON activities(project_id);

-- ====================================================================
-- 2. DỮ LIỆU BAN ĐẦU (DATA INSERTS)
-- ====================================================================

-- Dữ liệu bảng `users` (11 bản ghi)
INSERT INTO users ("id", "username", "name", "role", "title", "color", "password_hash", "is_verified", "verification_code", "verified_at", "created_at", "system_role", "status", "email") VALUES
  ('user-an', 'an', 'An Nguyễn', 'user', 'Trưởng nhóm UI/UX Designer', '#4F46E5', '$2y$12$zoTiunndfWjYnHhJWVL6H.Xg4VLi9z5Hk4PyRZISS9KKVZXKTGkxK', 1, NULL, NULL, '2026-02-12T08:00:00.000Z', 'user', 'active', 'an.nguyen@designmark.vn'),
  ('user-binh', 'binh', 'Bình Trần', 'user', 'Quản lý Sản phẩm Y tế', '#0F766E', '$2y$12$lQDoMhLtebjyItIMQY.53utp9CQ13cZRHIIBVboEKzrYfNhb42OJ2', 1, NULL, NULL, '2026-02-12T08:00:00.000Z', 'user', 'active', 'binh.tran@designmark.vn'),
  ('user-chi', 'chi', 'Chi Lê', 'user', 'Kiến trúc sư Trải nghiệm EduTech', '#B45309', '$2y$12$lQDoMhLtebjyItIMQY.53utp9CQ13cZRHIIBVboEKzrYfNhb42OJ2', 1, NULL, NULL, '2026-02-12T08:00:00.000Z', 'user', 'active', 'chi.le@designmark.vn'),
  ('user-admin', 'admin', 'Quản trị viên Hệ thống', 'admin', 'System Administrator', '#4338CA', '$2y$12$lQDoMhLtebjyItIMQY.53utp9CQ13cZRHIIBVboEKzrYfNhb42OJ2', 1, NULL, NULL, '2026-10-26T18:54:07.000Z', 'admin', 'active', 'admin@designmark.vn'),
  ('user-huy', 'huy', 'Lê Quốc Huy', 'manager', 'Kỹ sư Frontend Senior', '#D97706', '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y', 1, NULL, NULL, '2026-10-29T00:40:03.000Z', 'user', 'active', 'huy.le@designmark.vn'),
  ('user-mai', 'maianh', 'Vũ Mai Anh', 'manager', 'Product Owner', '#7C3AED', '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y', 1, NULL, NULL, '2026-10-29T00:40:03.000Z', 'user', 'active', 'maianh.vu@designmark.vn'),
  ('user-quan', 'quan', 'Đặng Minh Quân', 'manager', 'Chuyên gia Thiết kế Fintech', '#059669', '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y', 1, NULL, NULL, '2026-10-29T00:40:03.000Z', 'user', 'active', 'quan.dang@designmark.vn'),
  ('user-thang', 'thang', 'Phạm Đức Thắng', 'manager', 'Chuyên viên QA & Kiểm thử', '#2563EB', '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y', 1, NULL, NULL, '2026-10-29T00:40:03.000Z', 'user', 'active', 'thang.pham@designmark.vn'),
  ('user-thao', 'thao', 'Hoàng Thu Thảo', 'manager', 'Giám đốc Sáng tạo E-Commerce', '#DB2777', '$2y$12$ruqN9G/XXvDrsTl0MP7LWeYgu0oOskrCWCG8ob9sAORKtUpoPgR0y', 1, NULL, NULL, '2026-10-29T00:40:03.000Z', 'user', 'active', 'thao.hoang@designmark.vn'),
  ('user-86ed26d4-1c5f-43f2-8916-44ba515feae5', 'testuser_ca9e80', 'Nguyễn Văn Test', 'user', 'Thành viên', '#065F46', '$2y$12$9HgcdnXahyiINn.eBUvr0.UFAJy7XyHdAdZJ3fJZmfJPH9qIrQzk2', 1, NULL, NULL, '2026-10-29T02:19:52.000Z', 'user', 'active', NULL),
  ('user-58cc1b1e-1b5d-428c-beda-0cff49976606', 'stranger_af5229', 'Người Lạ', 'user', 'Thành viên', '#6D28D9', '$2y$12$cljPhmt0YqMd8o8fKUzp0eiAWgRH5fO4458zDn6JR86zWVers6YFW', 1, NULL, NULL, '2026-10-29T02:20:43.000Z', 'user', 'active', NULL)
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `projects` (8 bản ghi)
INSERT INTO projects ("id", "name", "description", "created_at", "updated_at", "created_by", "deleted_at", "deleted_by", "category") VALUES
  ('proj-admin-designmark', 'Hệ thống DesignMark Portal & Mobile App', 'Hệ thống đánh giá và góp ý giao diện chuẩn pixel dành cho đội ngũ Product & Design, hỗ trợ tải lên bản vẽ màn hình, so sánh các phiên bản và thảo luận phản hồi trực quan theo thời gian thực.', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 'user-admin', NULL, NULL, 'Nền tảng Thiết kế'),
  ('proj-edumaster', 'Nền tảng Học trực tuyến & Thi thử EduMaster', 'Hệ sinh thái đào tạo trực tuyến chuẩn quốc tế: bài giảng video tương tác, ghi chú bài học theo timestamp, làm bài thi trắc nghiệm bấm giờ và cấp chứng chỉ tự động.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-chi', NULL, NULL, 'Giáo dục / EdTech'),
  ('proj-finpay', 'Cổng Thanh toán & Ví điện tử FinPay', 'Giải pháp ví điện tử thế hệ mới: quét mã VietQR đa năng 24/7, xác thực bảo mật sinh trắc học FaceID, quản lý hạn mức chi tiêu và đối soát giao dịch thời gian thực.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-quan', NULL, NULL, 'Tài chính / Fintech'),
  ('proj-foodhub', 'Ứng dụng Đặt món & Giao đồ ăn FoodHub', 'Thiết kế ứng dụng giao đồ ăn nhanh đa nền tảng: tìm kiếm quán ăn thông minh, bộ lọc món theo khẩu vị, giỏ hàng gợi ý voucher và theo dõi tài xế thời gian thực.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-an', NULL, NULL, 'Di động / F&B'),
  ('proj-medicare', 'Hệ thống Y tế & Đặt lịch khám MediCare', 'Cổng thông tin khám chữa bệnh thông minh: đặt lịch khám trực tuyến theo chuyên khoa, tra cứu hồ sơ bệnh án điện tử và nhắc lịch uống thuốc hàng ngày.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-binh', NULL, NULL, 'Y tế & Sức khỏe'),
  ('proj-vtravel', 'Nền tảng Đặt tour & Khách sạn V-Travel', 'Cổng dịch vụ du lịch và lữ hành trọn gói: tìm kiếm khách sạn resort cao cấp, đặt tour trải nghiệm văn hóa, đánh giá cộng đồng kèm hình ảnh thực tế và gợi ý lịch trình.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-thao', NULL, NULL, 'Du lịch / Khách sạn'),
  ('proj-ops', 'Bảng điều khiển nội bộ', 'Giao diện vận hành: tổng quan, danh sách đơn và cài đặt.', '2026-10-29T01:01:28.000Z', '2026-10-29T02:27:24.000Z', 'user-an', NULL, NULL, 'Hệ thống quản lý'),
  ('proj-shop', 'Cửa hàng DesignMark', 'Luồng đăng ký, trang chủ và thanh toán cho website thương mại.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', NULL, NULL, 'Thương mại điện tử')
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `project_members` (35 bản ghi)
INSERT INTO project_members ("id", "project_id", "user_id", "role", "invited_by", "created_at") VALUES
  ('mem-proj-admin-designmark-an', 'proj-admin-designmark', 'user-an', 'reviewer', 'user-admin', '2026-10-28T12:09:20.000Z'),
  ('mem-proj-admin-designmark-binh', 'proj-admin-designmark', 'user-binh', 'reviewer', 'user-admin', '2026-10-28T12:09:20.000Z'),
  ('mem-proj-admin-designmark-chi', 'proj-admin-designmark', 'user-chi', 'viewer', 'user-admin', '2026-10-28T12:09:20.000Z'),
  ('mem-proj-edumaster-user-chi', 'proj-edumaster', 'user-chi', 'manager', NULL, '2026-10-29T00:40:03.000Z'),
  ('mem-proj-edumaster-user-huy', 'proj-edumaster', 'user-huy', 'reviewer', 'user-chi', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-edumaster-user-mai', 'proj-edumaster', 'user-mai', 'viewer', 'user-chi', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-edumaster-user-quan', 'proj-edumaster', 'user-quan', 'reviewer', 'user-chi', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-edumaster-user-thang', 'proj-edumaster', 'user-thang', 'reviewer', 'user-chi', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-finpay-user-an', 'proj-finpay', 'user-an', 'reviewer', 'user-quan', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-finpay-user-binh', 'proj-finpay', 'user-binh', 'viewer', 'user-quan', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-finpay-user-chi', 'proj-finpay', 'user-chi', 'reviewer', 'user-quan', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-finpay-user-mai', 'proj-finpay', 'user-mai', 'manager', 'user-quan', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-finpay-user-quan', 'proj-finpay', 'user-quan', 'manager', NULL, '2026-10-29T00:40:03.000Z'),
  ('mem-proj-finpay-user-thang', 'proj-finpay', 'user-thang', 'reviewer', 'user-quan', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-foodhub-user-an', 'proj-foodhub', 'user-an', 'manager', NULL, '2026-10-29T00:40:03.000Z'),
  ('mem-proj-foodhub-user-chi', 'proj-foodhub', 'user-chi', 'viewer', 'user-an', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-foodhub-user-huy', 'proj-foodhub', 'user-huy', 'reviewer', 'user-an', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-foodhub-user-quan', 'proj-foodhub', 'user-quan', 'manager', 'user-an', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-foodhub-user-thang', 'proj-foodhub', 'user-thang', 'reviewer', 'user-an', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-medicare-user-binh', 'proj-medicare', 'user-binh', 'manager', NULL, '2026-10-29T00:40:03.000Z'),
  ('mem-proj-medicare-user-quan', 'proj-medicare', 'user-quan', 'viewer', 'user-binh', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-medicare-user-thao', 'proj-medicare', 'user-thao', 'reviewer', 'user-binh', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-vtravel-user-an', 'proj-vtravel', 'user-an', 'reviewer', 'user-thao', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-vtravel-user-binh', 'proj-vtravel', 'user-binh', 'reviewer', 'user-thao', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-vtravel-user-huy', 'proj-vtravel', 'user-huy', 'reviewer', 'user-thao', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-vtravel-user-mai', 'proj-vtravel', 'user-mai', 'manager', 'user-thao', '2026-10-29T00:40:03.000Z'),
  ('mem-proj-vtravel-user-thao', 'proj-vtravel', 'user-thao', 'manager', NULL, '2026-10-29T00:40:03.000Z'),
  ('mem-proj-edumaster-user-an', 'proj-edumaster', 'user-an', 'reviewer', 'user-admin', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-medicare-user-an', 'proj-medicare', 'user-an', 'reviewer', 'user-admin', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-ops-user-an', 'proj-ops', 'user-an', 'manager', 'user-an', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-ops-user-binh', 'proj-ops', 'user-binh', 'reviewer', 'user-an', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-ops-user-chi', 'proj-ops', 'user-chi', 'viewer', 'user-an', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-shop-user-an', 'proj-shop', 'user-an', 'manager', 'user-an', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-shop-user-binh', 'proj-shop', 'user-binh', 'reviewer', 'user-an', '2026-10-29T01:01:28.000Z'),
  ('mem-proj-shop-user-chi', 'proj-shop', 'user-chi', 'viewer', 'user-an', '2026-10-29T01:01:28.000Z')
ON CONFLICT (project_id, user_id) DO NOTHING;

-- Dữ liệu bảng `design_screens` (21 bản ghi)
INSERT INTO design_screens ("id", "project_id", "name", "description", "created_at", "updated_at", "created_by", "sort_order") VALUES
  ('scr-proj-admin-designmark-1', 'proj-admin-designmark', '01. Bảng điều khiển Tổng quan (Dashboard)', 'Hiển thị tóm tắt tiến độ phản hồi, tỷ lệ góp ý đã giải quyết, biểu đồ hoạt động và danh sách các màn hình đang chờ đánh giá.', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 'user-admin', 0),
  ('scr-proj-admin-designmark-2', 'proj-admin-designmark', '02. Không gian Đánh giá & Góp ý (Review Canvas)', 'Hỗ trợ tương tác ghim tọa độ tuyệt đối, khoanh vùng chi tiết, xem luồng thảo luận real-time và công cụ so sánh 2 phiên bản trước/sau.', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 'user-admin', 0),
  ('scr-proj-admin-designmark-3', 'proj-admin-designmark', '03. Cổng Đăng nhập & Xác thực Tài khoản (Authentication)', 'Xác thực đa yếu tố, giao diện thích ứng Dark/Light Mode, xử lý khóa tài khoản tự động khi đăng nhập sai nhiều lần.', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 'user-admin', 0),
  ('scr-proj-admin-designmark-4', 'proj-admin-designmark', '04. Quản lý Thành viên & Phân quyền Dự án (Permissions & Roles)', 'Bảng ma trận phân quyền theo vai trò (Owner, Designer, Reviewer, Viewer). Kiểm soát chặt chẽ quyền tạo góp ý và quản lý dự án.', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 'user-admin', 0),
  ('scr-edumaster-classroom', 'proj-edumaster', 'Phòng học trực tuyến & Bài giảng video', 'Khung phát video chuẩn 4K, ghi chú theo thời gian thực timestamp, tài liệu đính kèm và khu vực hỏi đáp.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-chi', 0),
  ('scr-edumaster-exam', 'proj-edumaster', 'Làm bài thi trắc nghiệm & Bấm giờ', 'Giao diện làm bài thi trực tuyến, đồng hồ đếm ngược, ma trận chuyển câu hỏi và nộp bài.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-chi', 0),
  ('scr-finpay-dashboard', 'proj-finpay', 'Bảng điều khiển ví & Biểu đồ chi tiêu', 'Số dư khả dụng, hạn mức trong ngày, đồ thị thu chi tự động phân loại bằng AI.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-quan', 0),
  ('scr-finpay-qr', 'proj-finpay', 'Quét mã QR & Xác nhận thanh toán', 'Quét mã VietQR Napas 24/7, tự động điền tên chủ tài khoản thụ hưởng và xác thực FaceID.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-quan', 0),
  ('scr-foodhub-cart', 'proj-foodhub', 'Giỏ hàng & Khuyến mãi voucher', 'Chi tiết món đã chọn, tùy chọn topping, mã giảm giá Freeship và tổng tiền thanh toán.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-an', 0),
  ('scr-foodhub-home', 'proj-foodhub', 'Trang chủ & Gợi ý món ngon', 'Header tìm kiếm, banner ưu đãi flash sale, danh mục món ăn và danh sách quán ngon gần bạn.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-an', 0),
  ('scr-foodhub-tracking', 'proj-foodhub', 'Theo dõi tài xế di chuyển', 'Bản đồ định vị GPS thời gian thực, thông tin phương tiện tài xế và thời gian dự kiến giao.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-an', 0),
  ('scr-medicare-booking', 'proj-medicare', 'Đặt lịch khám theo bác sĩ & Khung giờ', 'Chọn chuyên khoa, bác sĩ phụ trách, ngày hẹn và các khung giờ còn trống hoặc kín lịch.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-binh', 0),
  ('scr-medicare-records', 'proj-medicare', 'Hồ sơ bệnh án & Kết quả xét nghiệm', 'Lịch sử khám bệnh, chỉ số sinh tồn, đơn thuốc điện tử và cảnh báo dị ứng thuốc nổi bật.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-binh', 0),
  ('scr-vtravel-detail', 'proj-vtravel', 'Chi tiết phòng & Đặt chỗ thanh toán', 'Xem ảnh phòng thực tế VR 360, chính sách hủy phòng miễn phí, gói bảo hiểm du lịch và đặt chỗ.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-thao', 0),
  ('scr-vtravel-explore', 'proj-vtravel', 'Khám phá điểm đến & Bộ lọc khách sạn', 'Thanh tìm kiếm ngày nhận/trả phòng, bộ lọc tiện ích (Hồ bơi vô cực, Giáp biển) và danh sách phòng ưu đãi.', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 'user-thao', 0),
  ('scr-dash', 'proj-ops', 'Tổng quan', 'Số liệu và biểu đồ vận hành.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', 1),
  ('scr-home', 'proj-shop', 'Trang chủ', 'Hero và lưới sản phẩm.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', 2),
  ('scr-orders', 'proj-ops', 'Danh sách đơn', 'Bảng đơn hàng và bộ lọc.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', 2),
  ('scr-pay', 'proj-shop', 'Thanh toán', 'Tóm tắt đơn và phương thức thanh toán.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', 3),
  ('scr-settings', 'proj-ops', 'Cài đặt', 'Hồ sơ, bảo mật và thông báo.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', 3),
  ('scr-signup', 'proj-shop', 'Đăng ký tài khoản', 'Form đăng ký. Điểm mẫu trên nút Đăng ký thuộc v1.', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 'user-an', 1)
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `image_versions` (41 bản ghi)
INSERT INTO image_versions ("id", "screen_id", "project_id", "number", "width", "height", "image_key", "mime_type", "source", "created_at", "created_by") VALUES
  ('ver-scr-proj-admin-designmark-1-1', 'scr-proj-admin-designmark-1', 'proj-admin-designmark', 1, 1000, 500, 'seed:dash-v1', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-1-2', 'scr-proj-admin-designmark-1', 'proj-admin-designmark', 2, 1000, 500, 'seed:dash-v2', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-2-1', 'scr-proj-admin-designmark-2', 'proj-admin-designmark', 1, 1000, 500, 'seed:home-v1', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-2-2', 'scr-proj-admin-designmark-2', 'proj-admin-designmark', 2, 1000, 500, 'seed:home-v2', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-3-1', 'scr-proj-admin-designmark-3', 'proj-admin-designmark', 1, 1000, 500, 'seed:signup-v1', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-3-2', 'scr-proj-admin-designmark-3', 'proj-admin-designmark', 2, 1000, 500, 'seed:signup-v2', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-4-1', 'scr-proj-admin-designmark-4', 'proj-admin-designmark', 1, 1000, 500, 'seed:settings-v1', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-scr-proj-admin-designmark-4-2', 'scr-proj-admin-designmark-4', 'proj-admin-designmark', 2, 1000, 500, 'seed:settings-v2', 'image/svg+xml', 'seed', '2026-10-28T12:09:20.000Z', 'user-admin'),
  ('ver-edumaster-classroom-1', 'scr-edumaster-classroom', 'proj-edumaster', 1, 1000, 500, 'seed:edumaster-classroom-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-chi'),
  ('ver-edumaster-classroom-2', 'scr-edumaster-classroom', 'proj-edumaster', 2, 1000, 500, 'seed:edumaster-classroom-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-chi'),
  ('ver-edumaster-exam-1', 'scr-edumaster-exam', 'proj-edumaster', 1, 1000, 500, 'seed:edumaster-exam-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-chi'),
  ('ver-edumaster-exam-2', 'scr-edumaster-exam', 'proj-edumaster', 2, 1000, 500, 'seed:edumaster-exam-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-chi'),
  ('ver-finpay-dashboard-1', 'scr-finpay-dashboard', 'proj-finpay', 1, 1000, 500, 'seed:finpay-dashboard-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-quan'),
  ('ver-finpay-dashboard-2', 'scr-finpay-dashboard', 'proj-finpay', 2, 1000, 500, 'seed:finpay-dashboard-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-quan'),
  ('ver-finpay-qr-1', 'scr-finpay-qr', 'proj-finpay', 1, 1000, 500, 'seed:finpay-qr-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-quan'),
  ('ver-finpay-qr-2', 'scr-finpay-qr', 'proj-finpay', 2, 1000, 500, 'seed:finpay-qr-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-quan'),
  ('ver-foodhub-cart-1', 'scr-foodhub-cart', 'proj-foodhub', 1, 1000, 500, 'seed:foodhub-cart-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-an'),
  ('ver-foodhub-cart-2', 'scr-foodhub-cart', 'proj-foodhub', 2, 1000, 500, 'seed:foodhub-cart-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-an'),
  ('ver-foodhub-home-1', 'scr-foodhub-home', 'proj-foodhub', 1, 1000, 500, 'seed:foodhub-home-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-an'),
  ('ver-foodhub-home-2', 'scr-foodhub-home', 'proj-foodhub', 2, 1000, 500, 'seed:foodhub-home-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-an'),
  ('ver-foodhub-tracking-1', 'scr-foodhub-tracking', 'proj-foodhub', 1, 1000, 500, 'seed:foodhub-tracking-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-an'),
  ('ver-medicare-booking-1', 'scr-medicare-booking', 'proj-medicare', 1, 1000, 500, 'seed:medicare-booking-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-binh'),
  ('ver-medicare-booking-2', 'scr-medicare-booking', 'proj-medicare', 2, 1000, 500, 'seed:medicare-booking-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-binh'),
  ('ver-medicare-records-1', 'scr-medicare-records', 'proj-medicare', 1, 1000, 500, 'seed:medicare-records-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-binh'),
  ('ver-medicare-records-2', 'scr-medicare-records', 'proj-medicare', 2, 1000, 500, 'seed:medicare-records-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-binh'),
  ('ver-vtravel-detail-1', 'scr-vtravel-detail', 'proj-vtravel', 1, 1000, 500, 'seed:vtravel-detail-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-thao'),
  ('ver-vtravel-detail-2', 'scr-vtravel-detail', 'proj-vtravel', 2, 1000, 500, 'seed:vtravel-detail-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-thao'),
  ('ver-vtravel-explore-1', 'scr-vtravel-explore', 'proj-vtravel', 1, 1000, 500, 'seed:vtravel-explore-v1', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-thao'),
  ('ver-vtravel-explore-2', 'scr-vtravel-explore', 'proj-vtravel', 2, 1000, 500, 'seed:vtravel-explore-v2', 'image/svg+xml', 'seed', '2026-10-29T00:40:03.000Z', 'user-thao'),
  ('ver-dash-1', 'scr-dash', 'proj-ops', 1, 1000, 500, 'seed:dash-v1', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-dash-2', 'scr-dash', 'proj-ops', 2, 1000, 500, 'seed:dash-v2', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-home-1', 'scr-home', 'proj-shop', 1, 1000, 500, 'seed:home-v1', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-home-2', 'scr-home', 'proj-shop', 2, 1000, 500, 'seed:home-v2', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-orders-1', 'scr-orders', 'proj-ops', 1, 1000, 500, 'seed:orders-v1', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-orders-2', 'scr-orders', 'proj-ops', 2, 1000, 500, 'seed:orders-v2', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-pay-1', 'scr-pay', 'proj-shop', 1, 1000, 500, 'seed:pay-v1', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-pay-2', 'scr-pay', 'proj-shop', 2, 1000, 500, 'seed:pay-v2', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-settings-1', 'scr-settings', 'proj-ops', 1, 1000, 500, 'seed:settings-v1', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-settings-2', 'scr-settings', 'proj-ops', 2, 1000, 500, 'seed:settings-v2', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-signup-1', 'scr-signup', 'proj-shop', 1, 1000, 500, 'seed:signup-v1', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an'),
  ('ver-signup-2', 'scr-signup', 'proj-shop', 2, 1000, 500, 'seed:signup-v2', 'image/svg+xml', 'seed', '2026-10-29T01:01:28.000Z', 'user-an')
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `annotations` (44 bản ghi)
INSERT INTO annotations ("id", "project_id", "screen_id", "image_version_id", "type", "x", "y", "w", "h", "original_width", "original_height", "author_id", "body", "status", "revision", "check_note", "created_at", "updated_at", "number", "drawing_data") VALUES
  ('ann-proj-admin-designmark-v1-1', 'proj-admin-designmark', 'scr-proj-admin-designmark-1', 'ver-scr-proj-admin-designmark-1-1', 'point', 216, 146, 0, 0, 1000, 500, 'user-an', '[UI/UX] Thẻ doanh thu "128" thiếu đơn vị tính cụ thể (triệu hay tỷ) và thanh biểu đồ cần có cảnh báo trạng thái đồng bộ kho khi bị chậm trễ.', 'resolved', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 1, NULL),
  ('ann-proj-admin-designmark-v1-2', 'proj-admin-designmark', 'scr-proj-admin-designmark-2', 'ver-scr-proj-admin-designmark-2-1', 'area', 40, 80, 920, 150, 1000, 500, 'user-binh', '[Giao diện] Tiêu đề "Bộ sưu tập mùa mới" bị lệch khỏi lề trái nội dung. Thẻ sản phẩm Áo linen phía dưới đang bị thiếu giá tiền niêm yết và nút Mua ngay.', 'resolved', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 2, NULL),
  ('ann-proj-admin-designmark-v1-3', 'proj-admin-designmark', 'scr-proj-admin-designmark-3', 'ver-scr-proj-admin-designmark-3-1', 'area', 640, 80, 320, 250, 1000, 500, 'user-an', '[UX/Layout] Bố cục 2 thẻ tách rời khiến nút "Đăng ký" bị đặt cô lập ở thẻ phụ bên phải. Người dùng rất dễ bỏ sót. Cần chuyển về bố cục 1 cột và đưa nút Đăng ký xuống dưới cùng form.', 'resolved', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 3, NULL),
  ('ann-proj-admin-designmark-v1-4', 'proj-admin-designmark', 'scr-proj-admin-designmark-4', 'ver-scr-proj-admin-designmark-4-1', 'area', 180, 90, 640, 320, 1000, 500, 'user-an', '[Thiết kế] Màn hình cài đặt hiện chỉ có danh sách bật/tắt thông báo, thiếu các khu vực quản lý thông tin hồ sơ cửa hàng và cài đặt xác thực bảo mật tài khoản (2FA).', 'resolved', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 4, NULL),
  ('ann-proj-admin-designmark-v2-1', 'proj-admin-designmark', 'scr-proj-admin-designmark-1', 'ver-scr-proj-admin-designmark-1-2', 'point', 120, 48, 0, 0, 1000, 500, 'user-binh', '[UX/Tương tác] Banner cảnh báo đồng bộ kho đã hiển thị rất rõ ràng, nhưng nút đóng (×) cần bổ sung tooltip giải thích và liên kết "Xem chi tiết".', 'resolved', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 5, NULL),
  ('ann-proj-admin-designmark-v2-2', 'proj-admin-designmark', 'scr-proj-admin-designmark-1', 'ver-scr-proj-admin-designmark-1-2', 'point', 680, 340, 0, 0, 1000, 500, 'user-an', '[Responsive] Kiểm tra kích thước chữ và khoảng cách padding của các thẻ thống kê khi xem trên màn hình iPad 10.2 inch.', 'open', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 6, NULL),
  ('ann-proj-admin-designmark-v2-3', 'proj-admin-designmark', 'scr-proj-admin-designmark-2', 'ver-scr-proj-admin-designmark-2-2', 'area', 180, 140, 420, 130, 1000, 500, 'user-binh', '[Chức năng] Vùng thanh công cụ so sánh cần có thanh trượt chia đôi màn hình (Split Slider) để đối chiếu nhanh giữa bản cũ v1 và bản mới v2.', 'resolved', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 7, NULL),
  ('ann-proj-admin-designmark-v2-4', 'proj-admin-designmark', 'scr-proj-admin-designmark-3', 'ver-scr-proj-admin-designmark-3-2', 'point', 480, 220, 0, 0, 1000, 500, 'user-admin', '[Bảo mật] Kiểm tra kỹ thời gian timeout của phiên làm việc (Session) sau 14 ngày ghi nhớ và hiển thị thông báo trước 5 phút khi hết hạn.', 'open', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 8, NULL),
  ('ann-proj-admin-designmark-v2-5', 'proj-admin-designmark', 'scr-proj-admin-designmark-4', 'ver-scr-proj-admin-designmark-4-2', 'area', 80, 260, 520, 140, 1000, 500, 'user-binh', '[Phân quyền] Cần hiển thị rõ ràng nhãn cấm quyền của tài khoản Viewer: "Tài khoản Viewer chỉ có quyền xem, không được phép tạo ghim hay bình luận" để người dùng không thắc mắc khi thanh công cụ vẽ bị vô hiệu hóa.', 'open', 1, '', '2026-10-28T12:09:20.000Z', '2026-10-28T12:09:20.000Z', 9, NULL),
  ('ann-proj-edumaster-1', 'proj-edumaster', 'scr-edumaster-classroom', 'ver-edumaster-classroom-1', 'point', 350, 230, 0, 0, 1000, 500, 'user-huy', 'Trình phát video cần cho phép chọn tốc độ phát 1.25x và 1.5x để học viên tiết kiệm thời gian.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 1, NULL),
  ('ann-proj-edumaster-2', 'proj-edumaster', 'scr-edumaster-classroom', 'ver-edumaster-classroom-1', 'rect', 680, 75, 280, 375, 1000, 500, 'user-huy', 'Bổ sung tính năng ghi chú cá nhân gắn kèm thời điểm giây trong video (Timestamp).', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 2, NULL),
  ('ann-proj-edumaster-3', 'proj-edumaster', 'scr-edumaster-exam', 'ver-edumaster-exam-1', 'point', 735, 150, 0, 0, 1000, 500, 'user-chi', 'Đồng hồ đếm ngược khi còn dưới 5 phút cần có thanh cảnh báo đỏ nổi bật để học sinh chú ý nộp bài.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 3, NULL),
  ('ann-proj-edumaster-4', 'proj-edumaster', 'scr-edumaster-exam', 'ver-edumaster-exam-2', 'rect', 65, 165, 600, 190, 1000, 500, 'user-quan', 'Các đáp án trắc nghiệm cần hỗ trợ hiển thị công thức toán học KaTeX và code block.', 'open', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 4, NULL),
  ('ann-proj-finpay-1', 'proj-finpay', 'scr-finpay-dashboard', 'ver-finpay-dashboard-1', 'point', 65, 160, 0, 0, 1000, 500, 'user-quan', 'Số dư cần có nút con mắt để ẩn/hiện số tiền nhằm bảo vệ quyền riêng tư nơi công cộng.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 1, NULL),
  ('ann-proj-finpay-2', 'proj-finpay', 'scr-finpay-dashboard', 'ver-finpay-dashboard-1', 'rect', 430, 75, 530, 220, 1000, 500, 'user-quan', 'Biểu đồ nên phân loại chi tiêu theo danh mục: Ăn uống, Hóa đơn, Mua sắm với thanh tiến độ ngân sách.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 2, NULL),
  ('ann-proj-finpay-3', 'proj-finpay', 'scr-finpay-qr', 'ver-finpay-qr-1', 'point', 450, 245, 0, 0, 1000, 500, 'user-quan', 'Sau khi quét mã QR, cần hiển thị màn hình xác nhận tên chủ tài khoản và ngân hàng trước khi chuyển.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 3, NULL),
  ('ann-proj-finpay-4', 'proj-finpay', 'scr-finpay-qr', 'ver-finpay-qr-2', 'point', 280, 380, 0, 0, 1000, 500, 'user-binh', 'Nút xác thực FaceID cần hỗ trợ mã PIN 6 số dự phòng khi điều kiện ánh sáng yếu.', 'open', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 4, NULL),
  ('ann-proj-foodhub-1', 'proj-foodhub', 'scr-foodhub-home', 'ver-foodhub-home-1', 'point', 120, 135, 0, 0, 1000, 500, 'user-thang', 'Banner ưu đãi 50% đang bị chìm chữ trên nền đỏ thẫm. Cần nâng tương phản WCAG AAA.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 1, NULL),
  ('ann-proj-foodhub-2', 'proj-foodhub', 'scr-foodhub-home', 'ver-foodhub-home-1', 'rect', 40, 210, 280, 220, 1000, 500, 'user-chi', 'Thẻ món ăn cần bổ sung thời gian giao dự kiến (ví dụ: 15-20 phút) và khoảng cách km.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 2, NULL),
  ('ann-proj-foodhub-3', 'proj-foodhub', 'scr-foodhub-cart', 'ver-foodhub-cart-1', 'point', 880, 225, 0, 0, 1000, 500, 'user-thang', 'Mã giảm giá nên tự động chọn voucher tốt nhất cho người dùng thay vì bắt nhập tay.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 3, NULL),
  ('ann-proj-foodhub-4', 'proj-foodhub', 'scr-foodhub-cart', 'ver-foodhub-cart-2', 'rect', 65, 260, 480, 60, 1000, 500, 'user-chi', 'Ô ghi chú cho tài xế nên có các tag nhanh: "Để trước cửa", "Gọi trước khi giao".', 'open', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 4, NULL),
  ('ann-proj-foodhub-5', 'proj-foodhub', 'scr-foodhub-tracking', 'ver-foodhub-tracking-1', 'point', 780, 250, 0, 0, 1000, 500, 'user-quan', 'Nút nhắn tin cho tài xế cần tích hợp sẵn các câu thoại mẫu để khách bấm nhanh khi bận.', 'open', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 5, NULL),
  ('ann-proj-medicare-1', 'proj-medicare', 'scr-medicare-booking', 'ver-medicare-booking-1', 'point', 685, 140, 0, 0, 1000, 500, 'user-admin', 'Các khung giờ đã kín lịch (08:30 - 09:00) cần hiển thị chữ "ĐÃ KÍN CHỖ" màu đỏ mờ để bệnh nhân không ấn nhầm.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 1, NULL),
  ('ann-proj-medicare-2', 'proj-medicare', 'scr-medicare-booking', 'ver-medicare-booking-1', 'rect', 65, 135, 400, 150, 1000, 500, 'user-admin', 'Cần hiển thị thông tin học hàm/học vị (PGS.TS) và số năm kinh nghiệm khám chữa bệnh của bác sĩ.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 2, NULL),
  ('ann-proj-medicare-3', 'proj-medicare', 'scr-medicare-records', 'ver-medicare-records-1', 'rect', 70, 275, 860, 120, 1000, 500, 'user-binh', 'Thông tin tiền sử dị ứng thuốc (Penicillin) cần được đưa lên đầu trang với màu đỏ cảnh báo khẩn.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 3, NULL),
  ('ann-proj-medicare-4', 'proj-medicare', 'scr-medicare-records', 'ver-medicare-records-2', 'point', 850, 110, 0, 0, 1000, 500, 'user-binh', 'Nút Tải PDF bệnh án cần có xác thực mã OTP gửi về số điện thoại đăng ký.', 'open', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 4, NULL),
  ('ann-proj-vtravel-1', 'proj-vtravel', 'scr-vtravel-explore', 'ver-vtravel-explore-1', 'rect', 40, 75, 920, 120, 1000, 500, 'user-an', 'Thanh tìm kiếm cần có chọn ngày nhận/trả phòng và số lượng khách (người lớn, trẻ em) trực quan.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 1, NULL),
  ('ann-proj-vtravel-2', 'proj-vtravel', 'scr-vtravel-explore', 'ver-vtravel-explore-1', 'point', 70, 385, 0, 0, 1000, 500, 'user-binh', 'Giá phòng hiển thị cần ghi rõ đã bao gồm thuế VAT và phí dịch vụ chưa để tránh hiểu lầm.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 2, NULL),
  ('ann-proj-vtravel-3', 'proj-vtravel', 'scr-vtravel-detail', 'ver-vtravel-detail-1', 'point', 240, 205, 0, 0, 1000, 500, 'user-binh', 'Thư viện ảnh nên có chế độ xem toàn cảnh thực tế ảo VR 360 độ cho các resort cao cấp.', 'resolved', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 3, NULL),
  ('ann-proj-vtravel-4', 'proj-vtravel', 'scr-vtravel-detail', 'ver-vtravel-detail-2', 'rect', 670, 265, 265, 40, 1000, 500, 'user-an', 'Bảo hiểm du lịch nên cho phép tùy chọn nâng hạn mức quyền lợi (từ 500 triệu lên 1 tỷ đồng).', 'open', 1, '', '2026-10-29T00:40:03.000Z', '2026-10-29T00:40:03.000Z', 4, NULL),
  ('ann-01', 'proj-shop', 'scr-signup', 'ver-signup-1', 'point', 750, 150, 0, 0, 1000, 500, 'user-binh', 'Nút Đăng ký cần tương phản cao hơn so với nền thẻ bên phải để thu hút người dùng.', 'open', 1, '', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 1, NULL),
  ('ann-02', 'proj-shop', 'scr-signup', 'ver-signup-1', 'rect', 64, 160, 520, 220, 1000, 500, 'user-an', 'Nhóm ô nhập bên trái còn thiếu thông báo lỗi ngay dưới ô mật khẩu.', 'open', 1, '', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 2, NULL),
  ('ann-03', 'proj-shop', 'scr-signup', 'ver-signup-1', 'point', 120, 34, 0, 0, 1000, 500, 'user-chi', 'Tên thương hiệu trên thanh đầu trang hơi khó nhận ra khi nhìn nhanh.', 'resolved', 1, '', '2026-10-29T01:01:28.000Z', '2026-10-29T01:01:28.000Z', 3, NULL),
  ('ann-04', 'proj-shop', 'scr-signup', 'ver-signup-1', 'arrow', 670, 70, 80, 80, 1000, 500, 'user-binh', 'Mũi tên chỉ dẫn: Chuyển icon sang bên phải chữ Đăng ký để định hướng luồng hành động tốt hơn.', 'open', 1, '', '2026-10-29T02:01:14.000Z', '2026-10-29T02:01:14.000Z', 4, '{"x1":670,"y1":70,"x2":750,"y2":150}'),
  ('ann-05', 'proj-shop', 'scr-signup', 'ver-signup-1', 'freehand', 60, 380, 520, 70, 1000, 500, 'user-quan', 'Nét vẽ tự do: Khoanh vùng toàn bộ khu vực checkbox điều khoản, cần link dẫn tới trang Điều khoản dịch vụ và Chính sách bảo mật.', 'open', 1, '', '2026-10-29T02:01:14.000Z', '2026-10-29T02:01:14.000Z', 5, '[{"x":64,"y":385},{"x":200,"y":395},{"x":450,"y":390},{"x":580,"y":385},{"x":580,"y":445},{"x":300,"y":455},{"x":64,"y":445},{"x":64,"y":385}]'),
  ('ann-dash-01', 'proj-ops', 'scr-dash', 'ver-dash-1', 'point', 480, 220, 0, 0, 1000, 500, 'user-binh', 'Biểu đồ doanh thu tuần cần bổ sung đường chỉ số trung bình và tooltip khi rê chuột.', 'resolved', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 1, NULL),
  ('ann-dash-02', 'proj-ops', 'scr-dash', 'ver-dash-1', 'rect', 50, 70, 900, 100, 1000, 500, 'user-chi', 'Các thẻ chỉ số KPI nên có mũi tên xanh/đỏ chỉ % tăng giảm so với tuần trước.', 'open', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 2, NULL),
  ('ann-home-01', 'proj-shop', 'scr-home', 'ver-home-1', 'point', 500, 180, 0, 0, 1000, 500, 'user-binh', 'Banner khuyến mãi đầu trang chữ hơi mảnh, cần tăng độ đậm và thêm nút CTA "Khám phá ngay".', 'resolved', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 1, NULL),
  ('ann-home-02', 'proj-shop', 'scr-home', 'ver-home-1', 'rect', 80, 300, 840, 160, 1000, 500, 'user-chi', 'Lưới sản phẩm nổi bật cần thêm nhãn giảm giá % ở góc ảnh để kích thích mua sắm.', 'open', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 2, NULL),
  ('ann-orders-01', 'proj-ops', 'scr-orders', 'ver-orders-1', 'point', 200, 90, 0, 0, 1000, 500, 'user-binh', 'Thanh công cụ cần thêm bộ lọc nhanh theo trạng thái: Chờ duyệt, Đang giao, Đã hoàn thành.', 'resolved', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 1, NULL),
  ('ann-orders-02', 'proj-ops', 'scr-orders', 'ver-orders-1', 'point', 880, 240, 0, 0, 1000, 500, 'user-an', 'Cột thao tác nên có nút In phiếu gửi hàng loạt cho nhiều đơn cùng lúc.', 'open', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 2, NULL),
  ('ann-pay-01', 'proj-shop', 'scr-pay', 'ver-pay-1', 'rect', 80, 160, 420, 140, 1000, 500, 'user-binh', 'Mục phương thức thanh toán cần hiển thị logo các cổng VNPAY QR, Thẻ Visa/Master và Ví MoMo rõ ràng.', 'resolved', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 1, NULL),
  ('ann-pay-02', 'proj-shop', 'scr-pay', 'ver-pay-1', 'arrow', 650, 380, 100, 60, 1000, 500, 'user-an', 'Mũi tên chỉ nút Đặt hàng: cần bổ sung dòng cam kết bảo mật thanh toán 256-bit SSL.', 'open', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 2, '{"x1":650,"y1":420,"x2":750,"y2":390}'),
  ('ann-settings-01', 'proj-ops', 'scr-settings', 'ver-settings-1', 'point', 350, 220, 0, 0, 1000, 500, 'user-binh', 'Mục đổi mật khẩu cần có thanh hiển thị độ mạnh mật khẩu và gợi ý xác thực 2 bước (2FA).', 'open', 1, '', '2026-10-29T02:49:10.000Z', '2026-10-29T02:49:10.000Z', 1, NULL)
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `annotation_replies` (34 bản ghi)
INSERT INTO annotation_replies ("id", "annotation_id", "author_id", "body", "created_at", "attachment_url") VALUES
  ('rep-ann-proj-admin-designmark-1-1', 'ann-proj-admin-designmark-1', 'user-admin', 'Đã phê duyệt và cập nhật hiển thị số liệu phần trăm trực tiếp trong phiên bản v2.', '2026-10-28T08:13:00.000Z', NULL),
  ('rep-ann-proj-admin-designmark-2-1', 'ann-proj-admin-designmark-2', 'user-admin', 'Ý kiến rất chuẩn xác! Đã tích hợp chế độ so sánh trượt mượt mà cho toàn bộ thành viên.', '2026-10-28T08:13:00.000Z', NULL),
  ('rep-ann-proj-admin-designmark-3-1', 'ann-proj-admin-designmark-3', 'user-an', 'Đã ghi nhận yêu cầu bảo mật. Em đang thiết kế popup cảnh báo timeout đếm ngược.', '2026-10-28T08:13:00.000Z', NULL),
  ('rep-ann-proj-admin-designmark-4-1', 'ann-proj-admin-designmark-4', 'user-admin', 'Đồng ý, phân quyền cần cực kỳ minh bạch để thành viên hiểu rõ vai trò của mình trong dự án.', '2026-10-28T08:13:00.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v1-1-1', 'ann-proj-admin-designmark-v1-1', 'user-admin', 'Rất chính xác. Tôi đã thêm đơn vị "128 tr" và tích hợp banner thông báo cảnh báo đồng bộ kho chậm 12 phút trong phiên bản v2.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v1-2-1', 'ann-proj-admin-designmark-v1-2', 'user-admin', 'Đồng ý. Tôi đã căn chỉnh tiêu đề thẳng lề trái, bổ sung giá 890.000đ và nút "Mua ngay" trong bản v2.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v1-3-1', 'ann-proj-admin-designmark-v1-3', 'user-admin', 'Đã tiếp thu. Phiên bản v2 đã chuyển toàn bộ form về bố cục 1 cột tối giản và nút Đăng ký đặt ngay dưới mật khẩu.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v1-4-1', 'ann-proj-admin-designmark-v1-4', 'user-admin', 'Đã bổ sung panel "Hồ sơ cửa hàng" và panel "Bảo mật 2FA" riêng biệt trong phiên bản v2.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v2-1-1', 'ann-proj-admin-designmark-v2-1', 'user-admin', 'Đã cập nhật tooltip giải thích và dẫn liên kết đến trang đối chiếu log hệ thống.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v2-2-1', 'ann-proj-admin-designmark-v2-2', 'user-admin', 'Chờ QA test lại trên thiết bị thực tế trước khi phát hành chính thức.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v2-3-1', 'ann-proj-admin-designmark-v2-3', 'user-admin', 'Ý kiến rất chuẩn xác! Đã tích hợp chế độ so sánh trượt Split Slider mượt mà cho toàn bộ thành viên.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v2-4-1', 'ann-proj-admin-designmark-v2-4', 'user-an', 'Đã ghi nhận yêu cầu bảo mật. Em đang thiết kế popup cảnh báo timeout đếm ngược.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-admin-designmark-v2-5-1', 'ann-proj-admin-designmark-v2-5', 'user-admin', 'Đồng ý, phân quyền cần cực kỳ minh bạch để thành viên hiểu rõ vai trò của mình trong dự án.', '2026-10-28T12:09:20.000Z', NULL),
  ('rep-ann-proj-edumaster-1-1', 'ann-proj-edumaster-1', 'user-chi', 'Đã hỗ trợ bộ điều khiển tốc độ và phím tắt tua nhanh trong bản v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-edumaster-2-1', 'ann-proj-edumaster-2', 'user-chi', 'Đã tích hợp tab Ghi chú theo thời gian (12:45, 28:10).', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-edumaster-3-1', 'ann-proj-edumaster-3', 'user-chi', 'Đã thêm banner đỏ cảnh báo nhấp nháy khi còn dưới 5 phút ở v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-finpay-1-1', 'ann-proj-finpay-1', 'user-quan', 'Đã bổ sung nút ẩn hiện số dư cùng chế độ bảo vệ FaceID ở bản v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-finpay-2-1', 'ann-proj-finpay-2', 'user-quan', 'Đã thêm biểu đồ AI phân loại danh mục % chi tiêu.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-finpay-3-1', 'ann-proj-finpay-3', 'user-quan', 'Đã thêm màn hình xác nhận thông tin VietQR 24/7 ở v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-foodhub-1-1', 'ann-proj-foodhub-1', 'user-an', 'Đã chuyển nền sang đỏ tươi và chữ trắng in hoa nổi bật trên v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-foodhub-2-1', 'ann-proj-foodhub-2', 'user-an', 'Đã thêm badge "Giao 15p" và "Giao 20p" trên mỗi card ở phiên bản v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-foodhub-3-1', 'ann-proj-foodhub-3', 'user-an', 'Đã cập nhật tính năng Auto-Apply Best Coupon tiết kiệm 25k.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-medicare-1-1', 'ann-proj-medicare-1', 'user-binh', 'Đã thay bằng badge đỏ cảnh báo ĐÃ KÍN CHỖ ở phiên bản v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-medicare-2-1', 'ann-proj-medicare-2', 'user-binh', 'Đã thêm profile bác sĩ có avatar và số sao đánh giá.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-medicare-3-1', 'ann-proj-medicare-3', 'user-binh', 'Đã đặt box Cảnh báo dị ứng thuốc tại vị trí ưu tiên góc trên phải v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-vtravel-1-1', 'ann-proj-vtravel-1', 'user-thao', 'Đã nâng cấp thanh tìm kiếm thông minh có chọn ngày và bộ lọc tiện ích ở v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-vtravel-2-1', 'ann-proj-vtravel-2', 'user-thao', 'Đã thêm dòng ghi chú "(Đã gồm thuế & phí)" minh bạch trên v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-proj-vtravel-3-1', 'ann-proj-vtravel-3', 'user-thao', 'Đã thêm chế độ xem VR 360 độ trực quan tại bản v2.', '2026-10-29T00:40:03.000Z', NULL),
  ('rep-ann-01-1', 'ann-01', 'user-an', 'Chào @an và @binh, mình đã đính kèm mẫu thiết kế nút bấm màu chàm đậm với tỷ lệ tương phản 7.2:1 (chuẩn WCAG AAA) bên dưới để đối chiếu nhé!', '2026-10-29T01:01:28.000Z', 'uploads/demo_button_style.svg'),
  ('rep-ann-03-1', 'ann-03', 'user-an', 'Đã tăng cỡ chữ logo lên 22px và dùng font đậm, đã giải quyết xong.', '2026-10-29T01:01:28.000Z', NULL),
  ('rep-ann-dash-01-1', 'ann-dash-01', 'user-an', 'Đã thêm đường baseline trung bình 7 ngày trên bản v2.', '2026-10-29T02:49:10.000Z', NULL),
  ('rep-ann-home-01-1', 'ann-home-01', 'user-an', 'Đã cải tiến trên v2: tăng font-weight lên 700 và đổi màu nút CTA sang cam nổi bật.', '2026-10-29T02:49:10.000Z', NULL),
  ('rep-ann-orders-01-1', 'ann-orders-01', 'user-an', 'Đã thiết kế 3 tab filter nhanh trên bản v2.', '2026-10-29T02:49:10.000Z', NULL),
  ('rep-ann-pay-01-1', 'ann-pay-01', 'user-an', 'Đã tích hợp đầy đủ logo phương thức thanh toán trên bản thiết kế v2.', '2026-10-29T02:49:10.000Z', NULL)
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `annotation_status_events` (44 bản ghi)
INSERT INTO annotation_status_events ("id", "annotation_id", "status", "user_id", "note", "created_at") VALUES
  ('evt-ann-proj-admin-designmark-1-1', 'ann-proj-admin-designmark-1', 'resolved', 'user-admin', 'Đã hoàn thành điều chỉnh trong phiên bản v2.', '2026-10-28T08:13:00.000Z'),
  ('evt-ann-proj-admin-designmark-2-1', 'ann-proj-admin-designmark-2', 'resolved', 'user-admin', 'Đã tích hợp Split Slider hoàn tất.', '2026-10-28T08:13:00.000Z'),
  ('evt-ann-proj-admin-designmark-v1-1-1', 'ann-proj-admin-designmark-v1-1', 'resolved', 'user-admin', 'Đã hoàn thành điều chỉnh trong phiên bản v2.', '2026-10-28T12:09:20.000Z'),
  ('evt-ann-proj-admin-designmark-v1-2-1', 'ann-proj-admin-designmark-v1-2', 'resolved', 'user-admin', 'Đã căn lề trái và bổ sung nút Mua ngay trong v2.', '2026-10-28T12:09:20.000Z'),
  ('evt-ann-proj-admin-designmark-v1-3-1', 'ann-proj-admin-designmark-v1-3', 'resolved', 'user-admin', 'Đã chuyển bố cục 1 cột và đưa nút xuống form v2.', '2026-10-28T12:09:20.000Z'),
  ('evt-ann-proj-admin-designmark-v1-4-1', 'ann-proj-admin-designmark-v1-4', 'resolved', 'user-admin', 'Đã bổ sung Hồ sơ và Bảo mật 2FA trong v2.', '2026-10-28T12:09:20.000Z'),
  ('evt-ann-proj-admin-designmark-v2-1-1', 'ann-proj-admin-designmark-v2-1', 'resolved', 'user-admin', 'Đã bổ sung tooltip và liên kết xem chi tiết.', '2026-10-28T12:09:20.000Z'),
  ('evt-ann-proj-admin-designmark-v2-3-1', 'ann-proj-admin-designmark-v2-3', 'resolved', 'user-admin', 'Đã tích hợp Split Slider hoàn tất.', '2026-10-28T12:09:20.000Z'),
  ('evt-ann-proj-edumaster-1-open', 'ann-proj-edumaster-1', 'open', 'user-huy', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-edumaster-1-res', 'ann-proj-edumaster-1', 'resolved', 'user-chi', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-edumaster-2-open', 'ann-proj-edumaster-2', 'open', 'user-huy', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-edumaster-2-res', 'ann-proj-edumaster-2', 'resolved', 'user-chi', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-edumaster-3-open', 'ann-proj-edumaster-3', 'open', 'user-chi', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-edumaster-3-res', 'ann-proj-edumaster-3', 'resolved', 'user-chi', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-edumaster-4-open', 'ann-proj-edumaster-4', 'open', 'user-quan', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-1-open', 'ann-proj-finpay-1', 'open', 'user-quan', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-1-res', 'ann-proj-finpay-1', 'resolved', 'user-quan', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-2-open', 'ann-proj-finpay-2', 'open', 'user-quan', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-2-res', 'ann-proj-finpay-2', 'resolved', 'user-quan', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-3-open', 'ann-proj-finpay-3', 'open', 'user-quan', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-3-res', 'ann-proj-finpay-3', 'resolved', 'user-quan', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-finpay-4-open', 'ann-proj-finpay-4', 'open', 'user-binh', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-1-open', 'ann-proj-foodhub-1', 'open', 'user-thang', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-1-res', 'ann-proj-foodhub-1', 'resolved', 'user-an', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-2-open', 'ann-proj-foodhub-2', 'open', 'user-chi', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-2-res', 'ann-proj-foodhub-2', 'resolved', 'user-an', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-3-open', 'ann-proj-foodhub-3', 'open', 'user-thang', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-3-res', 'ann-proj-foodhub-3', 'resolved', 'user-an', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-4-open', 'ann-proj-foodhub-4', 'open', 'user-chi', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-foodhub-5-open', 'ann-proj-foodhub-5', 'open', 'user-quan', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-1-open', 'ann-proj-medicare-1', 'open', 'user-admin', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-1-res', 'ann-proj-medicare-1', 'resolved', 'user-binh', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-2-open', 'ann-proj-medicare-2', 'open', 'user-admin', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-2-res', 'ann-proj-medicare-2', 'resolved', 'user-binh', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-3-open', 'ann-proj-medicare-3', 'open', 'user-binh', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-3-res', 'ann-proj-medicare-3', 'resolved', 'user-binh', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-medicare-4-open', 'ann-proj-medicare-4', 'open', 'user-binh', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-1-open', 'ann-proj-vtravel-1', 'open', 'user-an', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-1-res', 'ann-proj-vtravel-1', 'resolved', 'user-thao', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-2-open', 'ann-proj-vtravel-2', 'open', 'user-binh', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-2-res', 'ann-proj-vtravel-2', 'resolved', 'user-thao', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-3-open', 'ann-proj-vtravel-3', 'open', 'user-binh', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-3-res', 'ann-proj-vtravel-3', 'resolved', 'user-thao', 'Đã xử lý hoàn tất trong bản thiết kế cập nhật.', '2026-10-29T00:40:03.000Z'),
  ('evt-ann-proj-vtravel-4-open', 'ann-proj-vtravel-4', 'open', 'user-an', 'Đã khởi tạo góp ý trên giao diện.', '2026-10-29T00:40:03.000Z')
ON CONFLICT (id) DO NOTHING;

-- Dữ liệu bảng `activities` (98 bản ghi)
INSERT INTO activities ("id", "project_id", "user_id", "type", "message", "created_at", "link", "annotation_id") VALUES
  ('act-proj-admin-designmark-1', 'proj-admin-designmark', 'user-admin', 'project_created', 'Quản trị viên đã khởi tạo dự án "Hệ thống DesignMark Portal & Mobile App".', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-2', 'proj-admin-designmark', 'user-admin', 'member.invited', 'đã thêm An Nguyễn vào dự án với vai trò Góp ý (Reviewer - cấm quản trị dự án).', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-3', 'proj-admin-designmark', 'user-admin', 'member.invited', 'đã thêm Bình Trần vào dự án với vai trò Góp ý (Reviewer - cấm quản trị dự án).', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-4', 'proj-admin-designmark', 'user-admin', 'member.invited', 'đã thêm Chi Lê vào dự án với vai trò Chỉ xem (Viewer - cấm tạo góp ý & cấm chỉnh sửa).', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-5', 'proj-admin-designmark', 'user-admin', 'screen_added', 'đã tải lên 4 màn hình thiết kế mới vào dự án.', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-6', 'proj-admin-designmark', 'user-admin', 'version_added', 'đã phát hành phiên bản thiết kế v2 cho tất cả các màn hình.', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-7', 'proj-admin-designmark', 'user-an', 'comment_added', 'đã đặt ghim góp ý #1 trên màn hình Bảng điều khiển Tổng quan.', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-8', 'proj-admin-designmark', 'user-binh', 'comment_added', 'đã khoanh vùng góp ý #2 trên màn hình Không gian Đánh giá.', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-proj-admin-designmark-9', 'proj-admin-designmark', 'user-admin', 'status_resolved', 'đã xác nhận xử lý và hoàn tất góp ý #1 và #2.', '2026-10-28T12:09:20.000Z', NULL, NULL),
  ('act-ac614bfa-dcf4-4bf7-bc90-a7c30311f8e0', 'proj-admin-designmark', 'user-admin', 'member.invited', 'đã thêm Nguyen Van 0CFBC2 vào dự án với vai trò Góp ý', '2026-10-28T12:54:01.000Z', NULL, NULL),
  ('act-proj-edumaster-1', 'proj-edumaster', 'user-chi', 'project_created', 'Dự án "Nền tảng Học trực tuyến & Thi thử EduMaster" đã được khởi tạo bởi chủ sở hữu.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-edumaster-2', 'proj-edumaster', 'user-chi', 'screen_added', 'Đã tải lên 2 màn hình thiết kế kèm phiên bản so sánh v1/v2.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-edumaster-3', 'proj-edumaster', 'user-chi', 'member_added', 'Đã mời 5 thành viên tham gia cộng tác duyệt thiết kế.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-finpay-1', 'proj-finpay', 'user-quan', 'project_created', 'Dự án "Cổng Thanh toán & Ví điện tử FinPay" đã được khởi tạo bởi chủ sở hữu.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-finpay-2', 'proj-finpay', 'user-quan', 'screen_added', 'Đã tải lên 2 màn hình thiết kế kèm phiên bản so sánh v1/v2.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-finpay-3', 'proj-finpay', 'user-quan', 'member_added', 'Đã mời 5 thành viên tham gia cộng tác duyệt thiết kế.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-foodhub-1', 'proj-foodhub', 'user-an', 'project_created', 'Dự án "Ứng dụng Đặt món & Giao đồ ăn FoodHub" đã được khởi tạo bởi chủ sở hữu.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-foodhub-2', 'proj-foodhub', 'user-an', 'screen_added', 'Đã tải lên 3 màn hình thiết kế kèm phiên bản so sánh v1/v2.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-foodhub-3', 'proj-foodhub', 'user-an', 'member_added', 'Đã mời 5 thành viên tham gia cộng tác duyệt thiết kế.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-medicare-1', 'proj-medicare', 'user-binh', 'project_created', 'Dự án "Hệ thống Y tế & Đặt lịch khám MediCare" đã được khởi tạo bởi chủ sở hữu.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-medicare-2', 'proj-medicare', 'user-binh', 'screen_added', 'Đã tải lên 2 màn hình thiết kế kèm phiên bản so sánh v1/v2.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-medicare-3', 'proj-medicare', 'user-binh', 'member_added', 'Đã mời 3 thành viên tham gia cộng tác duyệt thiết kế.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-vtravel-1', 'proj-vtravel', 'user-thao', 'project_created', 'Dự án "Nền tảng Đặt tour & Khách sạn V-Travel" đã được khởi tạo bởi chủ sở hữu.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-vtravel-2', 'proj-vtravel', 'user-thao', 'screen_added', 'Đã tải lên 2 màn hình thiết kế kèm phiên bản so sánh v1/v2.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-proj-vtravel-3', 'proj-vtravel', 'user-thao', 'member_added', 'Đã mời 5 thành viên tham gia cộng tác duyệt thiết kế.', '2026-10-29T00:40:03.000Z', NULL, NULL),
  ('act-71e67b1d-c642-40ea-9697-7daaaede805e', 'proj-shop', 'user-binh', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Góp ý kiểm thử xác nhận hoạt động bình thường"', '2026-10-29T01:04:59.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-3c5f6164-0503-4636-80ce-9816ee41f31e', 'ann-3c5f6164-0503-4636-80ce-9816ee41f31e'),
  ('act-9526b301-0817-41e7-97e5-d20394cbcd47', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Phản hồi kiểm thử"', '2026-10-29T01:05:59.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-3c5f6164-0503-4636-80ce-9816ee41f31e', 'ann-3c5f6164-0503-4636-80ce-9816ee41f31e'),
  ('act-460eec90-2d1c-45d9-a8d5-7d9c7841be26', 'proj-shop', 'user-binh', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Test from curl"', '2026-10-29T01:06:05.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-e1088b51-d2b1-4b71-b8d0-c10a840b1782', 'ann-e1088b51-d2b1-4b71-b8d0-c10a840b1782'),
  ('act-aa51f2cc-e1eb-4ee1-81c0-48f41557f206', 'proj-shop', 'user-binh', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Hello test"', '2026-10-29T01:06:31.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-b3c4c049-229a-4cac-8cfb-22b5e2b74a7f', 'ann-b3c4c049-229a-4cac-8cfb-22b5e2b74a7f'),
  ('act-e1d2d9c8-2448-4fe9-9d6a-f4cca61e55ee', 'proj-shop', 'user-binh', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Góp ý kiểm thử xác nhận hoạt động bình thường"', '2026-10-29T01:09:27.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-cfa2a6f7-3402-4e46-871e-efba4ad511e0', 'ann-cfa2a6f7-3402-4e46-871e-efba4ad511e0'),
  ('act-f4470f6a-a3e6-46c8-9f3a-b325c72a855b', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Phản hồi kiểm thử"', '2026-10-29T01:09:35.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-cfa2a6f7-3402-4e46-871e-efba4ad511e0', 'ann-cfa2a6f7-3402-4e46-871e-efba4ad511e0'),
  ('act-65f5ed28-3fac-4a78-87cd-08730546e347', 'proj-shop', 'user-binh', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Góp ý kiểm thử xác nhận hoạt động bình thường"', '2026-10-29T01:15:35.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-acbf24e4-77a8-4aa1-835f-2abd9e292a44', 'ann-acbf24e4-77a8-4aa1-835f-2abd9e292a44'),
  ('act-e123655a-cbad-41ef-9a63-43d7e44abe98', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Phản hồi kiểm thử"', '2026-10-29T01:15:43.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-acbf24e4-77a8-4aa1-835f-2abd9e292a44', 'ann-acbf24e4-77a8-4aa1-835f-2abd9e292a44'),
  ('act-a1602d09-6b9a-4f12-973e-0fa7f9608f81', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:02:09.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-5ebd4b91-cd9d-4f64-8158-fea1e4065660', 'ann-5ebd4b91-cd9d-4f64-8158-fea1e4065660'),
  ('act-dd90901f-e1ae-420b-85a9-8b66ae774ca9', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:02:14.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-25acf638-c365-4e6f-b026-428ae4bb3dfe', 'ann-25acf638-c365-4e6f-b026-428ae4bb3dfe'),
  ('act-72165f17-e7a4-4baf-813e-1d4d0fd2b2f0', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:02:49.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-3bf06088-0838-4445-b6b3-07185ffd7f9b', 'ann-3bf06088-0838-4445-b6b3-07185ffd7f9b'),
  ('act-f57cc678-f540-43f0-86b3-43cd0b1b828e', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:02:55.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-a91487cb-ea11-4671-960e-ba42ffb3013c', 'ann-a91487cb-ea11-4671-960e-ba42ffb3013c'),
  ('act-ff0288b5-bbe7-4da8-b9f7-52902824be63', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Test arrow"', '2026-10-29T02:03:55.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-0576ad9f-c609-4b2b-9d65-012ba4a998f3', 'ann-0576ad9f-c609-4b2b-9d65-012ba4a998f3'),
  ('act-3085d3e0-67a9-495b-a3e8-b7eafa7a556e', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:04:52.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-05634f47-4196-4138-a3f4-02d5ef27d30a', 'ann-05634f47-4196-4138-a3f4-02d5ef27d30a'),
  ('act-d28c379d-bcf5-4a67-9fd3-d1255e8b26e7', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:04:57.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-b16c0a1f-8843-4c1d-810e-e0a63152cb57', 'ann-b16c0a1f-8843-4c1d-810e-e0a63152cb57'),
  ('act-f08f1f02-c472-41ce-a4e3-d0ffe0a4d4a4', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:05:22.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-2ef367e9-5a42-4f24-95ca-b1533a1d966e', 'ann-2ef367e9-5a42-4f24-95ca-b1533a1d966e'),
  ('act-64d245c5-f313-465f-b65a-4e8346f83426', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:05:42.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-28061188-d608-4acc-8a5a-7e59d70e2cc3', 'ann-28061188-d608-4acc-8a5a-7e59d70e2cc3'),
  ('act-bd75f3fb-52e2-4891-ac9f-fe29a8b24bb5', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Test arrow"', '2026-10-29T02:06:03.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-693aa290-78a6-4c42-8b10-dcf3a9eac0dd', 'ann-693aa290-78a6-4c42-8b10-dcf3a9eac0dd'),
  ('act-a7beba0b-30c0-4076-afac-b1745ea011e5', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:06:56.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-663f8cc8-48ad-4754-97bb-399a9b7e09cc', 'ann-663f8cc8-48ad-4754-97bb-399a9b7e09cc'),
  ('act-a564ea6e-c8c7-44f1-ad07-ff9c86a2cd56', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:07:10.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-7c83a8dc-0854-4b88-a159-8f316fab288e', 'ann-7c83a8dc-0854-4b88-a159-8f316fab288e'),
  ('act-d01acf54-1a86-4421-bf02-15b078245bbe', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:09:43.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-f6a7f4d3-1e90-48bd-853e-fd1643cffa0a', 'ann-f6a7f4d3-1e90-48bd-853e-fd1643cffa0a'),
  ('act-c29485da-6047-4972-be34-5ea8120577b0', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:09:56.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-fd51ec5e-f10b-4141-a2b7-f7a280c4e079', 'ann-fd51ec5e-f10b-4141-a2b7-f7a280c4e079'),
  ('act-4b36adf3-fbb6-42ae-b3ef-1571f6775dbd', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:10:12.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-f6a7f4d3-1e90-48bd-853e-fd1643cffa0a', 'ann-f6a7f4d3-1e90-48bd-853e-fd1643cffa0a'),
  ('act-196c02d6-39a7-424c-9cd9-73f3c2648281', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:11:26.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-7608eaa2-7512-422e-b375-81a4352f819f', 'ann-7608eaa2-7512-422e-b375-81a4352f819f'),
  ('act-275f3124-cce0-4280-a510-3ed07d7ebd77', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:11:38.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-9894f457-0d9a-42dd-a391-54a27af81072', 'ann-9894f457-0d9a-42dd-a391-54a27af81072'),
  ('act-9c0904d6-edd1-490d-8f16-41e93e8bd366', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:12:11.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-7608eaa2-7512-422e-b375-81a4352f819f', 'ann-7608eaa2-7512-422e-b375-81a4352f819f'),
  ('act-a1e45607-e396-47ca-95e1-621d2924a358', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:12:20.000Z', NULL, NULL),
  ('act-84e32267-439d-469f-9b94-b67620fda956', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:12:29.000Z', NULL, NULL),
  ('act-ece20880-1d8d-45a8-8c37-f70bdbe3869e', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 13 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:12:50.000Z', NULL, NULL),
  ('act-902d0a00-b411-4c13-9580-015c2e3435ef', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:13:36.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-59ec4ec1-779c-47e8-8958-42358393192c', 'ann-59ec4ec1-779c-47e8-8958-42358393192c'),
  ('act-9589ff41-ec10-4ad2-975a-190b587102b0', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:13:42.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-481962d7-41d2-4a68-936b-e7b4212db030', 'ann-481962d7-41d2-4a68-936b-e7b4212db030'),
  ('act-f8f21800-5b44-48d3-aef1-d9d471b221ab', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 13 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:14:18.000Z', NULL, NULL),
  ('act-a7e00345-e025-40f1-82fd-d815dfdf237e', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:15:46.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-7223aa9d-800a-4494-aaf2-3f2f7ca24fa5', 'ann-7223aa9d-800a-4494-aaf2-3f2f7ca24fa5'),
  ('act-a032db05-91d3-4ef3-b63b-2c7c9bf15a2c', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:15:56.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-c877354e-0b15-49da-b356-2dcadd82e859', 'ann-c877354e-0b15-49da-b356-2dcadd82e859'),
  ('act-f37882c3-4eb4-4716-adf4-9f0b05efc327', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:16:23.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-7223aa9d-800a-4494-aaf2-3f2f7ca24fa5', 'ann-7223aa9d-800a-4494-aaf2-3f2f7ca24fa5'),
  ('act-2d6aa8aa-f0bc-4576-8691-51d74a717832', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:16:35.000Z', NULL, NULL),
  ('act-6c03e50a-c82e-4a8b-9160-86e0aed11407', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:16:59.000Z', NULL, NULL),
  ('act-df5cadf9-3995-46c9-92b0-71405d1285ad', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 15 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:17:24.000Z', NULL, NULL),
  ('act-3bedc503-e302-4474-9be7-23644224d1a2', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:18:55.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-f1b02d34-a4e1-408f-9c3e-861095fd9c26', 'ann-f1b02d34-a4e1-408f-9c3e-861095fd9c26'),
  ('act-ac3d0851-5c74-4189-9466-e2db1829ff83', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:19:01.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-702e07d4-bae4-4afb-9f0d-a9ad422ac8f4', 'ann-702e07d4-bae4-4afb-9f0d-a9ad422ac8f4'),
  ('act-6d6f8ea0-718d-487c-826f-7b1bdcec1a95', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:19:11.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-f1b02d34-a4e1-408f-9c3e-861095fd9c26', 'ann-f1b02d34-a4e1-408f-9c3e-861095fd9c26'),
  ('act-6cd79625-5233-41fe-bd3e-abc6b6607b99', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:19:18.000Z', NULL, NULL),
  ('act-9a09d30d-96e3-4173-9b91-62baaa5f421d', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:19:29.000Z', NULL, NULL),
  ('act-807b79d1-265a-410e-afc1-1bbe6e476afd', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 15 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:19:39.000Z', NULL, NULL),
  ('act-772d6cd2-1023-4f42-9241-02fd5588c8cd', 'proj-shop', 'user-binh', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Góp ý kiểm thử xác nhận hoạt động bình thường"', '2026-10-29T02:23:17.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-51020ffd-5807-4303-8cc8-d4bf9980d81f', 'ann-51020ffd-5807-4303-8cc8-d4bf9980d81f'),
  ('act-78a85628-5ea5-4670-82bc-828e9234a661', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Phản hồi kiểm thử"', '2026-10-29T02:23:23.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-51020ffd-5807-4303-8cc8-d4bf9980d81f', 'ann-51020ffd-5807-4303-8cc8-d4bf9980d81f'),
  ('act-e05d4e49-3c07-4999-8f4b-172b5b832ce8', 'proj-ops', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:27:20.000Z', NULL, NULL),
  ('act-34279780-6c41-4546-b766-9636ce2863f7', 'proj-ops', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:27:24.000Z', NULL, NULL),
  ('act-0bcaa3e5-5641-4a4d-8ae3-8a0201f5cc86', 'proj-shop', 'user-an', 'screen.created', 'đã tải màn hình Màn hình kiểm thử ảnh, phiên bản v1', '2026-10-29T02:33:45.000Z', NULL, NULL),
  ('act-19c28785-46dd-4a07-872f-d66be2344eff', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:37:13.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-a3f7ab7b-2623-4c58-b2ec-f689fd7ab1d0', 'ann-a3f7ab7b-2623-4c58-b2ec-f689fd7ab1d0'),
  ('act-287bd382-d326-4e78-92f4-25637e2fd1d1', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:37:21.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-ea880c0a-2023-4e29-b03f-83297e1124c9', 'ann-ea880c0a-2023-4e29-b03f-83297e1124c9'),
  ('act-f550a632-4d5d-4ef5-a173-a3e386577566', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:37:39.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-a3f7ab7b-2623-4c58-b2ec-f689fd7ab1d0', 'ann-a3f7ab7b-2623-4c58-b2ec-f689fd7ab1d0'),
  ('act-28bfa2c4-3209-411a-b790-673b9c415ef4', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:37:49.000Z', NULL, NULL),
  ('act-6df1f486-7f15-4360-b01d-8cacbf6efc3a', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:38:04.000Z', NULL, NULL),
  ('act-d014b572-f0f6-45b2-930f-28c693d1df82', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 16 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:38:13.000Z', NULL, NULL),
  ('act-ff545424-0493-4424-8779-547c35082e84', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:42:26.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-fb29a1a0-fe0e-4dbf-b69b-346af4c7cdaf', 'ann-fb29a1a0-fe0e-4dbf-b69b-346af4c7cdaf'),
  ('act-ae9e00b1-1f8b-4e83-9f5b-e28be99514aa', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:42:33.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-68db33df-740a-41f6-b676-c11648555394', 'ann-68db33df-740a-41f6-b676-c11648555394'),
  ('act-6929b9fc-d87d-486e-9491-d1529d5309fc', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:42:44.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-fb29a1a0-fe0e-4dbf-b69b-346af4c7cdaf', 'ann-fb29a1a0-fe0e-4dbf-b69b-346af4c7cdaf'),
  ('act-d428f2f8-20f1-4739-b382-9e0caaf0feab', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:42:58.000Z', NULL, NULL),
  ('act-e8d9d6fd-5911-4303-81aa-15dd27232edc', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:43:11.000Z', NULL, NULL),
  ('act-94c6ed33-3f5e-4da3-946f-1e5f6b0f88d5', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 16 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:43:21.000Z', NULL, NULL),
  ('act-45ff114d-ff82-48f1-83c3-ac01c197c058', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T02:50:13.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-08ae685c-1cb4-4b9c-ae31-f038057823b2', 'ann-08ae685c-1cb4-4b9c-ae31-f038057823b2'),
  ('act-e2414747-4c1d-4e41-aec1-ce432ad6551f', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T02:50:18.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-322e9a4b-faac-4761-aea8-1551b2f8f15d', 'ann-322e9a4b-faac-4761-aea8-1551b2f8f15d'),
  ('act-b5131961-f090-4e31-bad0-00813c5adbd3', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T02:50:39.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-08ae685c-1cb4-4b9c-ae31-f038057823b2', 'ann-08ae685c-1cb4-4b9c-ae31-f038057823b2'),
  ('act-1aad2962-ad8d-4462-bde4-12c4764e37cb', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:50:51.000Z', NULL, NULL),
  ('act-015e25e2-527e-459b-ba95-e446c1d04ae5', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T02:50:58.000Z', NULL, NULL),
  ('act-9d2e3be7-bc81-47b5-a565-6c078f033327', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 6 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T02:51:03.000Z', NULL, NULL),
  ('act-abe57b00-5a85-44be-8ec4-e52aaad4e62e', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử mũi tên chỉ dẫn: @binh vui lòng kiểm tra luồng đăng ký"', '2026-10-29T03:07:29.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-830a29fd-039a-4e70-af13-e60f584e33db', 'ann-830a29fd-039a-4e70-af13-e60f584e33db'),
  ('act-fa365737-94c5-49b2-b209-5b12513ec435', 'proj-shop', 'user-an', 'comment.created', 'đã thêm nhận xét trên Đăng ký tài khoản v1: "Kiểm thử vẽ tự do: @chi xem vùng nét vẽ này"', '2026-10-29T03:07:35.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-333918de-3163-4a9f-85f1-d98056ef0a21', 'ann-333918de-3163-4a9f-85f1-d98056ef0a21'),
  ('act-29fb9683-7769-4832-9a96-fbb7301084a4', 'proj-shop', 'user-binh', 'comment.replied', 'đã trả lời một góp ý: "Cảm ơn @an, mình gửi đính kèm phương án thiết kế đề xuất."', '2026-10-29T03:07:47.000Z', 'review.php?project=proj-shop&screen=scr-signup&version=ver-signup-1&annotation=ann-830a29fd-039a-4e70-af13-e60f584e33db', 'ann-830a29fd-039a-4e70-af13-e60f584e33db'),
  ('act-938de2d4-324b-4887-a079-856d5f3f3b94', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T03:08:11.000Z', NULL, NULL),
  ('act-0952d6b8-4fc2-4744-a4f8-c58add5fd8a1', 'proj-shop', 'user-an', 'screens.reordered', 'đã sắp xếp lại thứ tự các màn hình', '2026-10-29T03:08:28.000Z', NULL, NULL),
  ('act-23667c7c-1696-453a-b77b-e5c6cc9396d9', 'proj-shop', 'user-an', 'annotations.migrated', 'đã chuyển 6 góp ý chưa xử lý từ v1 sang v2', '2026-10-29T03:08:32.000Z', NULL, NULL)
ON CONFLICT (id) DO NOTHING;
