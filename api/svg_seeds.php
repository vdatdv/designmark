<?php
// api/svg_seeds.php - SVG generators for demo seed designs

function svg_esc($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function svg_txt($x, $y, $value, $size = 14, $fill = '#0F172A', $weight = 500, $anchor = 'start') {
    $font = 'Inter, Segoe UI, sans-serif';
    return sprintf('<text x="%s" y="%s" fill="%s" font-size="%s" font-weight="%s" font-family="%s" text-anchor="%s">%s</text>',
        $x, $y, $fill, $size, $weight, $font, $anchor, svg_esc($value)
    );
}

function svg_rect($x, $y, $w, $h, $fill, $stroke = 'none', $radius = 0) {
    $strokeAttr = ($stroke && $stroke !== 'none') ? sprintf(' stroke="%s"', $stroke) : '';
    return sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"%s/>',
        $x, $y, $w, $h, $radius, $fill, $strokeAttr
    );
}

function svg_line($x1, $y1, $x2, $y2, $stroke = '#E2E8F0') {
    return sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s"/>',
        $x1, $y1, $x2, $y2, $stroke
    );
}

function svg_wrap($body) {
    return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="500" viewBox="0 0 1000 500">%s</svg>', $body);
}

function svg_chrome($brand, $links, $activeIndex = 0) {
    $nav = '';
    foreach ($links as $index => $label) {
        $color = ($index === $activeIndex) ? '#4F46E5' : '#64748B';
        $weight = ($index === $activeIndex) ? 700 : 500;
        $nav .= svg_txt(188 + $index * 108, 34, $label, 13, $color, $weight);
    }
    return implode('', [
        svg_rect(0, 0, 1000, 500, '#F1F5F9'),
        svg_rect(0, 0, 1000, 56, '#FFFFFF'),
        svg_line(0, 56, 1000, 56),
        svg_rect(16, 16, 24, 24, '#4F46E5', 'none', 6),
        svg_txt(28, 33, 'D', 12, '#FFFFFF', 700, 'middle'),
        svg_txt(48, 34, $brand, 15, '#0F172A', 700),
        $nav
    ]);
}

function svg_field($x, $y, $w, $label, $placeholder) {
    return implode('', [
        svg_txt($x, $y, $label, 13, '#334155', 600),
        svg_rect($x, $y + 8, $w, 36, '#FFFFFF', '#CBD5E1', 8),
        svg_txt($x + 12, $y + 31, $placeholder, 13, '#94A3B8', 400)
    ]);
}

function svg_cta($x, $y, $w, $h, $label, $fill = '#4F46E5') {
    return implode('', [
        svg_rect($x, $y, $w, $h, $fill, 'none', 8),
        svg_txt($x + $w / 2, $y + $h / 2 + 5, $label, 15, '#FFFFFF', 700, 'middle')
    ]);
}

function svg_chip($x, $y, $label) {
    $width = max(72, mb_strlen($label, 'UTF-8') * 7 + 20);
    return implode('', [
        svg_rect($x, $y, $width, 26, '#EEF2FF', 'none', 13),
        svg_txt($x + $width / 2, $y + 17, $label, 12, '#4338CA', 700, 'middle')
    ]);
}

function svg_card($x, $y, $w, $h) {
    return svg_rect($x, $y, $w, $h, '#FFFFFF', '#E2E8F0', 12);
}

function render_seed_signup_v1() {
    return svg_wrap(implode('', [
        svg_chrome('DM Shop', ['Sản phẩm', 'Giải pháp', 'Bảng giá'], 1),
        svg_txt(860, 34, 'Đăng nhập', 13, '#334155', 600),
        svg_card(40, 80, 580, 370),
        svg_txt(64, 118, 'Tạo tài khoản', 22, '#0F172A', 700),
        svg_txt(64, 144, 'Nhập thông tin để bắt đầu dùng thử 14 ngày.', 13, '#64748B', 400),
        svg_field(64, 172, 250, 'Họ và tên', 'Nguyễn An'),
        svg_field(334, 172, 250, 'Email công việc', 'an@congty.vn'),
        svg_field(64, 252, 250, 'Mật khẩu', '••••••••'),
        svg_field(334, 252, 250, 'Xác nhận mật khẩu', '••••••••'),
        svg_rect(64, 340, 18, 18, '#FFFFFF', '#CBD5E1', 4),
        svg_txt(92, 354, 'Tôi đồng ý với điều khoản sử dụng', 13, '#334155', 500),
        svg_card(640, 80, 320, 250),
        svg_txt(664, 112, 'Hoàn tất đăng ký', 14, '#0F172A', 700),
        svg_cta(668, 124, 164, 52, 'Đăng ký'),
        svg_txt(664, 204, 'Nút chính nằm ở thẻ bên phải.', 12, '#64748B', 400),
        svg_txt(664, 226, 'Góp ý trên nút chỉ thuộc phiên bản này.', 12, '#64748B', 400),
        svg_rect(664, 248, 272, 58, '#F8FAFC', '#E2E8F0', 8),
        svg_txt(680, 274, 'Đã có tài khoản? Đăng nhập', 13, '#334155', 500),
        svg_txt(680, 294, 'Hỗ trợ: hello@dmshop.vn', 12, '#64748B', 400),
        svg_chip(40, 460, 'Phiên bản 1')
    ]));
}

function render_seed_signup_v2() {
    return svg_wrap(implode('', [
        svg_chrome('DM Shop', ['Trang chủ', 'Đăng ký', 'Hỗ trợ'], 1),
        svg_rect(0, 64, 1000, 40, '#EEF2FF'),
        svg_txt(40, 89, 'Phiên bản 2 — bố cục một cột, nút Đăng ký đã chuyển xuống cuối form', 14, '#4338CA', 600),
        svg_card(250, 124, 500, 250),
        svg_txt(274, 160, 'Đăng ký tài khoản', 22, '#0F172A', 700),
        svg_field(274, 184, 452, 'Email', 'ban@congty.vn'),
        svg_field(274, 260, 452, 'Mật khẩu', 'Tối thiểu 8 ký tự'),
        svg_card(640, 124, 280, 78),
        svg_txt(656, 154, 'Nút Đăng ký không còn ở đây', 13, '#334155', 700),
        svg_txt(656, 176, 'Vị trí cũ của phiên bản 1', 12, '#64748B', 400),
        svg_cta(148, 392, 200, 52, 'Đăng ký'),
        svg_txt(364, 424, 'Tạo tài khoản', 13, '#64748B', 500),
        svg_chip(860, 460, 'Phiên bản 2')
    ]));
}

function render_seed_home_v1() {
    return svg_wrap(implode('', [
        svg_chrome('DM Shop', ['Sản phẩm', 'Bộ sưu tập', 'Giỏ hàng'], 1),
        svg_txt(860, 34, 'Tài khoản', 13, '#334155', 600),
        svg_card(40, 80, 920, 150),
        svg_txt(64, 124, 'Mùa mới', 13, '#4F46E5', 700),
        svg_txt(360, 172, 'Bộ sưu tập mùa mới', 28, '#0F172A', 700),
        svg_txt(64, 200, 'Tiêu đề đang lệch khỏi cột nội dung bên trái.', 14, '#64748B', 400),
        svg_card(48, 250, 280, 180),
        svg_rect(68, 270, 240, 78, '#EEF2FF', 'none', 8),
        svg_txt(68, 372, 'Áo linen', 16, '#0F172A', 700),
        svg_txt(68, 396, 'Thiếu giá và nút xem', 13, '#64748B', 400),
        svg_card(348, 250, 280, 180),
        svg_rect(368, 270, 240, 78, '#F8FAFC', 'none', 8),
        svg_txt(368, 372, 'Túi canvas', 16, '#0F172A', 700),
        svg_txt(368, 396, '890.000đ', 13, '#334155', 600),
        svg_card(648, 250, 280, 180),
        svg_rect(668, 270, 240, 78, '#F8FAFC', 'none', 8),
        svg_txt(668, 372, 'Giày thể thao', 16, '#0F172A', 700),
        svg_txt(668, 396, '1.250.000đ', 13, '#334155', 600),
        svg_chip(40, 460, 'Phiên bản 1')
    ]));
}

function render_seed_home_v2() {
    return svg_wrap(implode('', [
        svg_chrome('DM Shop', ['Sản phẩm', 'Bộ sưu tập', 'Giỏ hàng'], 0),
        svg_rect(0, 64, 1000, 44, '#4F46E5'),
        svg_txt(40, 91, 'Giảm 20% trong tuần này — mã NEW20', 15, '#FFFFFF', 700),
        svg_card(40, 128, 440, 200),
        svg_txt(64, 176, 'Bộ sưu tập đã căn lại', 26, '#0F172A', 700),
        svg_txt(64, 208, 'Tiêu đề nằm trong cột trái.', 14, '#64748B', 400),
        svg_cta(64, 236, 150, 42, 'Mua ngay'),
        svg_card(520, 240, 200, 160),
        svg_txt(540, 276, 'Áo linen', 15, '#0F172A', 700),
        svg_txt(540, 300, '890.000đ', 13, '#334155', 600),
        svg_card(740, 240, 200, 160),
        svg_txt(760, 276, 'Túi canvas', 15, '#0F172A', 700),
        svg_txt(760, 300, '640.000đ', 13, '#334155', 600),
        svg_chip(40, 460, 'Phiên bản 2')
    ]));
}

function render_seed_pay_v1() {
    return svg_wrap(implode('', [
        svg_chrome('Thanh toán', ['Giỏ hàng', 'Thông tin', 'Thanh toán'], 2),
        svg_card(48, 88, 460, 280),
        svg_txt(72, 124, 'Tóm tắt đơn hàng', 18, '#0F172A', 700),
        svg_txt(72, 164, 'Áo linen × 1', 14, '#334155', 500),
        svg_txt(430, 164, '890.000đ', 14, '#334155', 600, 'end'),
        svg_line(72, 188, 484, 188),
        svg_txt(72, 220, 'Vận chuyển', 14, '#334155', 500),
        svg_txt(430, 220, '30.000đ', 14, '#334155', 600, 'end'),
        svg_txt(72, 260, 'Tổng', 16, '#0F172A', 700),
        svg_txt(430, 260, '920.000đ', 16, '#0F172A', 700, 'end'),
        svg_txt(72, 312, 'Mã giảm giá NEW20 đã áp dụng', 13, '#059669', 600),
        svg_card(540, 88, 420, 250),
        svg_txt(564, 124, 'Thẻ tín dụng', 16, '#0F172A', 700),
        svg_field(564, 148, 372, 'Số thẻ', '•••• 4242'),
        svg_field(564, 224, 176, 'Ngày', '12/28'),
        svg_field(760, 224, 176, 'CVC', '123'),
        svg_cta(740, 376, 180, 48, 'Thanh toán'),
        svg_chip(48, 460, 'Phiên bản 1')
    ]));
}

function render_seed_pay_v2() {
    return svg_wrap(implode('', [
        svg_chrome('Thanh toán', ['Giỏ hàng', 'Thông tin', 'Thanh toán'], 2),
        svg_txt(48, 96, 'Chọn phương thức', 20, '#0F172A', 700),
        svg_card(48, 116, 280, 150),
        svg_rect(48, 116, 6, 150, '#4F46E5', 'none', 0),
        svg_txt(72, 156, 'Ví điện tử', 16, '#0F172A', 700),
        svg_txt(72, 182, 'Đang được chọn', 13, '#4F46E5', 600),
        svg_card(348, 116, 280, 150),
        svg_txt(372, 156, 'Thẻ nội địa', 16, '#0F172A', 700),
        svg_txt(372, 182, 'Chưa chọn', 13, '#64748B', 500),
        svg_card(648, 116, 304, 150),
        svg_txt(672, 156, 'Chuyển khoản', 16, '#0F172A', 700),
        svg_txt(672, 182, 'Phí 0đ', 13, '#64748B', 500),
        svg_cta(48, 392, 200, 48, 'Thanh toán'),
        svg_txt(268, 420, 'Nút đã chuyển sang trái', 13, '#64748B', 500),
        svg_chip(860, 460, 'Phiên bản 2')
    ]));
}

function render_seed_dash_v1() {
    return svg_wrap(implode('', [
        svg_rect(0, 0, 1000, 500, '#F1F5F9'),
        svg_rect(0, 0, 180, 500, '#FFFFFF'),
        svg_line(180, 0, 180, 500),
        svg_rect(16, 16, 24, 24, '#4F46E5', 'none', 6),
        svg_txt(48, 34, 'Nội bộ', 14, '#0F172A', 700),
        svg_txt(24, 88, 'Tổng quan', 13, '#4F46E5', 700),
        svg_txt(24, 122, 'Đơn hàng', 13, '#64748B', 500),
        svg_txt(24, 156, 'Cài đặt', 13, '#64748B', 500),
        svg_card(200, 80, 180, 110),
        svg_txt(216, 108, 'Doanh thu', 13, '#64748B', 600),
        svg_txt(216, 146, '128', 28, '#0F172A', 700),
        svg_card(400, 80, 180, 110),
        svg_txt(416, 108, 'Đơn mới', 13, '#64748B', 600),
        svg_txt(416, 146, '46', 28, '#0F172A', 700),
        svg_card(520, 96, 440, 200),
        svg_txt(544, 132, 'Biểu đồ 7 ngày', 15, '#0F172A', 700),
        svg_line(560, 250, 620, 210, '#4F46E5'),
        svg_line(620, 210, 700, 230, '#4F46E5'),
        svg_line(700, 230, 790, 170, '#4F46E5'),
        svg_line(790, 170, 900, 190, '#4F46E5'),
        svg_chip(200, 460, 'Phiên bản 1')
    ]));
}

function render_seed_dash_v2() {
    return svg_wrap(implode('', [
        svg_rect(0, 0, 1000, 500, '#F8FAFC'),
        svg_rect(0, 0, 72, 500, '#FFFFFF'),
        svg_line(72, 0, 72, 500),
        svg_rect(24, 16, 24, 24, '#4F46E5', 'none', 6),
        svg_card(96, 24, 860, 56),
        svg_rect(96, 24, 6, 56, '#DC2626', 'none', 0),
        svg_txt(120, 48, 'Cảnh báo: đồng bộ kho chậm 12 phút', 15, '#0F172A', 700),
        svg_txt(120, 68, 'Cần có nút đóng và liên kết xem chi tiết', 12, '#64748B', 400),
        svg_txt(900, 56, '×', 22, '#64748B', 500, 'middle'),
        svg_card(96, 104, 200, 100),
        svg_txt(112, 136, 'Doanh thu', 13, '#64748B', 600),
        svg_txt(112, 172, '128 tr', 24, '#0F172A', 700),
        svg_card(312, 104, 200, 100),
        svg_txt(328, 136, 'Đơn mới', 13, '#64748B', 600),
        svg_txt(328, 172, '46', 24, '#0F172A', 700),
        svg_card(528, 104, 200, 100),
        svg_txt(544, 136, 'Hoàn', 13, '#64748B', 600),
        svg_txt(544, 172, '3', 24, '#0F172A', 700),
        svg_chip(96, 460, 'Phiên bản 2')
    ]));
}

function render_seed_orders_v1() {
    return svg_wrap(implode('', [
        svg_chrome('Đơn hàng', ['Tất cả', 'Đang xử lý', 'Hoàn tất'], 0),
        svg_cta(860, 16, 112, 28, 'Xuất', '#334155'),
        svg_rect(40, 96, 920, 48, '#FFFFFF', '#E2E8F0', 0),
        svg_txt(56, 126, 'Mã', 13, '#64748B', 700),
        svg_txt(180, 126, 'Khách', 13, '#64748B', 700),
        svg_txt(420, 126, 'Trạng thái', 13, '#64748B', 700),
        svg_txt(680, 126, 'Tổng', 13, '#64748B', 700),
        svg_rect(40, 144, 920, 52, '#FFFFFF', '#E2E8F0', 0),
        svg_txt(56, 176, 'DH-1042', 14, '#0F172A', 600),
        svg_txt(180, 176, 'Mai Phạm', 14, '#334155', 500),
        svg_txt(420, 176, 'Đang giao', 14, '#334155', 500),
        svg_txt(680, 176, '920.000đ', 14, '#334155', 600),
        svg_rect(40, 196, 920, 52, '#F8FAFC', '#E2E8F0', 0),
        svg_txt(56, 228, 'DH-1043', 14, '#0F172A', 600),
        svg_txt(180, 228, 'Hùng Lê', 14, '#334155', 500),
        svg_txt(420, 228, 'Chờ thanh toán', 14, '#334155', 500),
        svg_txt(680, 228, '450.000đ', 14, '#334155', 600),
        svg_chip(40, 460, 'Phiên bản 1')
    ]));
}

function render_seed_orders_v2() {
    return svg_wrap(implode('', [
        svg_chrome('Đơn hàng', ['Tất cả', 'Đang xử lý', 'Hoàn tất'], 0),
        svg_txt(700, 34, 'Bộ lọc', 13, '#334155', 600),
        svg_rect(40, 80, 140, 32, '#FFFFFF', '#CBD5E1', 8),
        svg_txt(52, 101, 'Trạng thái', 13, '#64748B', 500),
        svg_rect(192, 80, 140, 32, '#FFFFFF', '#CBD5E1', 8),
        svg_txt(204, 101, 'Khoản tiền', 13, '#64748B', 500),
        svg_rect(40, 132, 920, 44, '#EEF2FF', '#C7D2FE', 0),
        svg_txt(56, 160, 'Mã', 13, '#4338CA', 700),
        svg_txt(180, 160, 'Khách', 13, '#4338CA', 700),
        svg_txt(420, 160, 'Trạng thái', 13, '#4338CA', 700),
        svg_rect(40, 176, 920, 88, '#FFFFFF', '#4F46E5', 0),
        svg_txt(56, 214, 'DH-1048', 14, '#0F172A', 700),
        svg_txt(180, 214, 'An Nguyễn', 14, '#334155', 500),
        svg_txt(420, 214, 'Đã chọn — thiếu chú thích', 14, '#4F46E5', 700),
        svg_txt(56, 240, 'Hàng đang chọn chưa giải thích trạng thái sau khi mở lại.', 13, '#64748B', 400),
        svg_chip(860, 460, 'Phiên bản 2')
    ]));
}

function render_seed_settings_v1() {
    return svg_wrap(implode('', [
        svg_chrome('Cài đặt', ['Hồ sơ', 'Bảo mật', 'Thông báo'], 2),
        svg_card(180, 90, 640, 320),
        svg_txt(208, 130, 'Thông báo', 22, '#0F172A', 700),
        svg_txt(208, 176, 'Email đơn mới', 15, '#0F172A', 600),
        svg_rect(720, 156, 44, 24, '#4F46E5', 'none', 12),
        svg_rect(742, 160, 16, 16, '#FFFFFF', 'none', 8),
        svg_txt(208, 230, 'Báo cáo tuần', 15, '#0F172A', 600),
        svg_rect(720, 210, 44, 24, '#E2E8F0', 'none', 12),
        svg_rect(724, 214, 16, 16, '#FFFFFF', 'none', 8),
        svg_txt(208, 284, 'Cảnh báo kho', 15, '#0F172A', 600),
        svg_rect(720, 264, 44, 24, '#4F46E5', 'none', 12),
        svg_rect(742, 268, 16, 16, '#FFFFFF', 'none', 8),
        svg_chip(180, 430, 'Phiên bản 1')
    ]));
}

function render_seed_settings_v2() {
    return svg_wrap(implode('', [
        svg_chrome('Cài đặt', ['Hồ sơ', 'Bảo mật', 'Thông báo'], 0),
        svg_card(40, 80, 440, 300),
        svg_txt(64, 116, 'Hồ sơ cửa hàng', 18, '#0F172A', 700),
        svg_field(64, 140, 392, 'Tên hiển thị', 'DM Shop'),
        svg_field(64, 220, 392, 'Múi giờ', 'Asia/Ho_Chi_Minh'),
        svg_card(520, 80, 440, 220),
        svg_txt(544, 116, 'Bảo mật', 18, '#0F172A', 700),
        svg_txt(544, 156, 'Xác thực hai lớp đang tắt', 14, '#334155', 500),
        svg_cta(544, 188, 160, 40, 'Bật 2FA'),
        svg_txt(544, 260, 'Nút lưu đã tách khỏi nhóm thông báo.', 13, '#64748B', 400),
        svg_chip(40, 460, 'Phiên bản 2')
    ]));
}

function render_seed_foodhub_home_v1() {
    return svg_wrap(implode('', [
        svg_chrome('FoodHub', ['Gợi ý', 'Nhà hàng gần bạn', 'Ưu đãi', 'Đơn hàng'], 0),
        svg_card(40, 80, 920, 110),
        svg_rect(40, 80, 920, 110, '#FEF2F2', '#FCA5A5', 12),
        svg_txt(70, 120, 'ĐẠI TIỆC ẨM THỰC — GIẢM ĐẾN 50%', 22, '#DC2626', 700),
        svg_txt(70, 150, 'Miễn phí giao hàng cho đơn từ 99k. Nhập mã FOODHUB50', 14, '#7F1D1D', 500),
        svg_card(40, 210, 280, 220),
        svg_rect(60, 230, 240, 100, '#FEE2E2', 'none', 8),
        svg_txt(70, 290, 'Phở Bò Gia Truyền', 16, '#991B1B', 700),
        svg_txt(70, 360, 'Phở Thìn Lò Đúc', 16, '#0F172A', 700),
        svg_txt(70, 385, '55.000đ • Đánh giá 4.8★ (320+)', 13, '#64748B', 500),
        svg_cta(70, 400, 100, 24, 'Thêm món', '#DC2626'),
        svg_card(360, 210, 280, 220),
        svg_rect(380, 230, 240, 100, '#FEF3C7', 'none', 8),
        svg_txt(390, 290, 'Cơm Tấm Sườn Bì', 16, '#92400E', 700),
        svg_txt(390, 360, 'Cơm Tấm Sài Gòn', 16, '#0F172A', 700),
        svg_txt(390, 385, '45.000đ • Đánh giá 4.7★ (410+)', 13, '#64748B', 500),
        svg_cta(390, 400, 100, 24, 'Thêm món', '#DC2626'),
        svg_card(680, 210, 280, 220),
        svg_rect(700, 230, 240, 100, '#DCFCE7', 'none', 8),
        svg_txt(710, 290, 'Trà Sữa Trân Châu', 16, '#166534', 700),
        svg_txt(710, 360, 'Trà Sữa Koi Thé', 16, '#0F172A', 700),
        svg_txt(710, 385, '35.000đ • Đánh giá 4.9★ (850+)', 13, '#64748B', 500),
        svg_cta(710, 400, 100, 24, 'Thêm món', '#DC2626'),
        svg_chip(40, 460, 'FoodHub Trang chủ — Phiên bản 1')
    ]));
}

function render_seed_foodhub_home_v2() {
    return svg_wrap(implode('', [
        svg_chrome('FoodHub', ['Khám phá', 'Flash Sale', 'Quán quen', 'Ví Voucher'], 1),
        svg_card(40, 75, 920, 130),
        svg_rect(40, 75, 920, 130, '#DC2626', 'none', 12),
        svg_txt(70, 115, 'SIÊU HỘI ĂN UỐNG — GIẢM 50% TẤT CẢ QUÁN NGON', 24, '#FFFFFF', 800),
        svg_txt(70, 145, 'Bộ lọc mới theo thời gian giao nhanh (Dưới 20 phút) và khoảng cách', 15, '#FEE2E2', 500),
        svg_cta(70, 165, 140, 32, 'Lấy mã ngay', '#FFFFFF'),
        svg_txt(140, 185, 'LẤY MÃ NGAY', 12, '#DC2626', 700, 'middle'),
        svg_card(40, 225, 280, 220),
        svg_rect(60, 245, 240, 100, '#FEE2E2', 'none', 8),
        svg_txt(70, 305, 'Phở Thìn Lò Đúc (v2)', 16, '#991B1B', 700),
        svg_chip(200, 255, 'Giao 15p'),
        svg_txt(70, 375, 'Phở Đặc Biệt Tái Lăn', 16, '#0F172A', 700),
        svg_txt(70, 400, '60.000đ • 4.9★ (1.2k+)', 13, '#16A34A', 600),
        svg_cta(70, 415, 110, 24, 'Chọn món', '#DC2626'),
        svg_card(360, 225, 280, 220),
        svg_rect(380, 245, 240, 100, '#FEF3C7', 'none', 8),
        svg_txt(390, 305, 'Bún Chả Hà Nội', 16, '#92400E', 700),
        svg_chip(520, 255, 'Giao 20p'),
        svg_txt(390, 375, 'Bún Chả Que Tre', 16, '#0F172A', 700),
        svg_txt(390, 400, '50.000đ • 4.8★ (890+)', 13, '#16A34A', 600),
        svg_cta(390, 415, 110, 24, 'Chọn món', '#DC2626'),
        svg_card(680, 225, 280, 220),
        svg_rect(700, 245, 240, 100, '#DCFCE7', 'none', 8),
        svg_txt(710, 305, 'Trà Sữa MayCha', 16, '#166534', 700),
        svg_chip(840, 255, 'Freeship'),
        svg_txt(710, 375, 'Trà Sữa Kem Trứng', 16, '#0F172A', 700),
        svg_txt(710, 400, '32.000đ • 4.9★ (3.1k+)', 13, '#16A34A', 600),
        svg_cta(710, 415, 110, 24, 'Chọn món', '#DC2626'),
        svg_chip(40, 465, 'FoodHub Trang chủ — Phiên bản 2 (Cải tiến UI & Tag Giao Nhanh)')
    ]));
}

function render_seed_foodhub_cart_v1() {
    return svg_wrap(implode('', [
        svg_chrome('FoodHub', ['Trang chủ', 'Giỏ hàng', 'Khuyến mãi'], 1),
        svg_card(40, 80, 560, 340),
        svg_txt(65, 115, 'Món đã chọn (2 món)', 18, '#0F172A', 700),
        svg_line(65, 130, 575, 130),
        svg_txt(65, 160, '1x Phở Bò Tái Nạm (Ít hành, thêm ớt)', 14, '#1E293B', 600),
        svg_txt(500, 160, '65.000đ', 14, '#DC2626', 700),
        svg_txt(65, 210, '1x Trà Đào Cam Sả (50% đường, 70% đá)', 14, '#1E293B', 600),
        svg_txt(500, 210, '35.000đ', 14, '#DC2626', 700),
        svg_field(65, 260, 480, 'Ghi chú cho quán ăn', 'Giao nước chấm riêng...'),
        svg_card(630, 80, 330, 340),
        svg_txt(655, 115, 'Tóm tắt thanh toán', 18, '#0F172A', 700),
        svg_txt(655, 155, 'Tạm tính:', 14, '#64748B', 500),
        svg_txt(880, 155, '100.000đ', 14, '#0F172A', 600),
        svg_txt(655, 190, 'Phí giao hàng (2.4 km):', 14, '#64748B', 500),
        svg_txt(880, 190, '18.000đ', 14, '#0F172A', 600),
        svg_txt(655, 225, 'Mã giảm giá:', 14, '#64748B', 500),
        svg_txt(880, 225, '-15.000đ', 14, '#16A34A', 600),
        svg_line(655, 250, 930, 250),
        svg_txt(655, 280, 'Tổng thanh toán:', 16, '#0F172A', 700),
        svg_txt(860, 280, '103.000đ', 20, '#DC2626', 800),
        svg_cta(655, 320, 280, 48, 'Đặt đơn hàng ngay', '#DC2626'),
        svg_chip(40, 455, 'FoodHub Giỏ hàng — Phiên bản 1')
    ]));
}

function render_seed_foodhub_cart_v2() {
    return svg_wrap(implode('', [
        svg_chrome('FoodHub', ['Trang chủ', 'Giỏ hàng', 'Khuyến mãi'], 1),
        svg_card(40, 80, 560, 360),
        svg_txt(65, 115, 'Món đã chọn & Tùy biến Topping', 18, '#0F172A', 700),
        svg_chip(470, 100, 'Tiết kiệm 25k'),
        svg_line(65, 135, 575, 135),
        svg_txt(65, 165, '1x Phở Bò Tái Nạm + Quẩy giòn + Trứng chần', 14, '#1E293B', 700),
        svg_txt(500, 165, '75.000đ', 14, '#DC2626', 700),
        svg_txt(65, 215, '1x Trà Đào Cam Sả + Thạch đào giòn', 14, '#1E293B', 700),
        svg_txt(500, 215, '40.000đ', 14, '#DC2626', 700),
        svg_field(65, 260, 480, 'Ghi chú cho tài xế & quán', 'Bấm chuông cửa nhà số 12...'),
        svg_txt(65, 360, 'Phương thức thanh toán: Ví MoMo / Thẻ ATM / Tiền mặt', 13, '#4B5563', 500),
        svg_card(630, 80, 330, 360),
        svg_rect(630, 80, 330, 40, '#DC2626', 'none', 10),
        svg_txt(655, 106, 'Ưu đãi thẻ VISA: Giảm 20k', 13, '#FFFFFF', 700),
        svg_txt(655, 155, 'Tạm tính:', 14, '#64748B', 500),
        svg_txt(880, 155, '115.000đ', 14, '#0F172A', 600),
        svg_txt(655, 190, 'Phí giao hàng:', 14, '#64748B', 500),
        svg_txt(880, 190, '0đ (Free)', 14, '#16A34A', 700),
        svg_txt(655, 225, 'Voucher giảm giá:', 14, '#64748B', 500),
        svg_txt(880, 225, '-25.000đ', 14, '#16A34A', 700),
        svg_line(655, 255, 930, 255),
        svg_txt(655, 285, 'Tổng thanh toán:', 16, '#0F172A', 700),
        svg_txt(860, 285, '90.000đ', 22, '#DC2626', 800),
        svg_cta(655, 330, 280, 48, 'Xác nhận & Đặt món (v2)', '#DC2626'),
        svg_chip(40, 465, 'FoodHub Giỏ hàng — Phiên bản 2 (Topping rõ ràng, Freeship tự áp dụng)')
    ]));
}

function render_seed_foodhub_tracking_v1() {
    return svg_wrap(implode('', [
        svg_chrome('FoodHub', ['Trang chủ', 'Theo dõi đơn', 'Lịch sử'], 1),
        svg_card(40, 75, 920, 380),
        svg_rect(60, 95, 520, 330, '#E2E8F0', 'none', 12),
        svg_line(80, 250, 500, 180, '#DC2626'),
        svg_rect(240, 200, 36, 36, '#DC2626', 'none', 18),
        svg_txt(258, 223, '🛵', 18, '#FFFFFF', 700, 'middle'),
        svg_txt(80, 130, 'Bản đồ định vị GPS thời gian thực', 16, '#0F172A', 700),
        svg_txt(80, 155, 'Tài xế đang di chuyển đến nhà hàng — Dự kiến giao: 11:45 (12 phút nữa)', 13, '#334155', 500),
        svg_card(600, 95, 340, 330),
        svg_txt(625, 130, 'Thông tin đơn hàng #FH-8921', 18, '#0F172A', 700),
        svg_txt(625, 170, 'Tài xế: Nguyễn Văn Hùng', 15, '#1E293B', 600),
        svg_txt(625, 195, 'Biển số: 29E1-884.21 (Honda Wave)', 13, '#64748B', 500),
        svg_txt(625, 220, 'Đánh giá: 4.9★ (1.420 chuyến)', 13, '#F59E0B', 600),
        svg_cta(625, 250, 140, 36, 'Gọi tài xế', '#16A34A'),
        svg_cta(780, 250, 140, 36, 'Nhắn tin', '#3B82F6'),
        svg_txt(625, 320, 'Trạng thái: Quán đang chuẩn bị món', 14, '#DC2626', 700),
        svg_chip(40, 465, 'FoodHub Theo dõi đơn hàng — Phiên bản 1')
    ]));
}

function render_seed_medicare_booking_v1() {
    return svg_wrap(implode('', [
        svg_chrome('MediCare', ['Trang chủ', 'Đặt lịch khám', 'Bác sĩ', 'Hồ sơ bệnh án'], 1),
        svg_card(40, 75, 450, 370),
        svg_txt(65, 110, 'Chọn chuyên khoa & Bác sĩ', 18, '#0F766E', 700),
        svg_field(65, 135, 400, 'Chuyên khoa', 'Tim Mạch / Nội Tổng Quát'),
        svg_field(65, 210, 400, 'Bác sĩ phụ trách', 'PGS.TS Trần Minh Tuấn'),
        svg_field(65, 285, 400, 'Ngày hẹn khám', '15/11/2026'),
        svg_card(520, 75, 440, 370),
        svg_txt(545, 110, 'Khung giờ khám còn trống', 18, '#0F766E', 700),
        svg_rect(545, 140, 120, 45, '#0F766E', 'none', 8),
        svg_txt(605, 168, '08:00 - 08:30', 13, '#FFFFFF', 600, 'middle'),
        svg_rect(685, 140, 120, 45, '#E2E8F0', 'none', 8),
        svg_txt(745, 168, '08:30 - 09:00', 13, '#64748B', 500, 'middle'),
        svg_rect(825, 140, 120, 45, '#0F766E', 'none', 8),
        svg_txt(885, 168, '09:00 - 09:30', 13, '#FFFFFF', 600, 'middle'),
        svg_rect(545, 205, 120, 45, '#E2E8F0', 'none', 8),
        svg_txt(605, 233, '14:00 - 14:30', 13, '#64748B', 500, 'middle'),
        svg_rect(685, 205, 120, 45, '#0F766E', 'none', 8),
        svg_txt(745, 233, '14:30 - 15:00', 13, '#FFFFFF', 600, 'middle'),
        svg_rect(825, 205, 120, 45, '#E2E8F0', 'none', 8),
        svg_txt(885, 233, '15:00 - 15:30', 13, '#64748B', 500, 'middle'),
        svg_field(545, 275, 390, 'Lý do khám / Triệu chứng', 'Khó thở khi gắng sức...'),
        svg_cta(545, 360, 390, 48, 'Xác nhận Đặt lịch hẹn', '#0F766E'),
        svg_chip(40, 465, 'MediCare Đặt lịch — Phiên bản 1')
    ]));
}

function render_seed_medicare_booking_v2() {
    return svg_wrap(implode('', [
        svg_chrome('MediCare', ['Trang chủ', 'Đặt lịch khám', 'Bác sĩ', 'Hồ sơ bệnh án'], 1),
        svg_card(40, 75, 450, 375),
        svg_txt(65, 110, 'Thông tin bác sĩ & Đánh giá', 18, '#0F766E', 700),
        svg_rect(65, 130, 70, 70, '#CCFBF1', 'none', 35),
        svg_txt(100, 172, '👨‍⚕️', 30, '#0F766E', 700, 'middle'),
        svg_txt(155, 155, 'PGS.TS Trần Minh Tuấn', 16, '#0F172A', 700),
        svg_txt(155, 180, '25 năm kinh nghiệm BV Bạch Mai', 13, '#64748B', 500),
        svg_txt(155, 200, 'Đánh giá: 4.95★ (1.500+ lượt khám)', 13, '#0F766E', 600),
        svg_field(65, 220, 400, 'Phòng khám & Cơ sở', 'Cơ sở 1: 180 Giải Phóng, Hà Nội'),
        svg_field(65, 290, 400, 'Số thẻ BHYT (nếu có)', 'DN 4 01 01 23456789'),
        svg_card(520, 75, 440, 375),
        svg_txt(545, 110, 'Khung giờ hẹn có bảo hiểm y tế', 18, '#0F766E', 700),
        svg_rect(545, 135, 120, 42, '#0F766E', 'none', 8),
        svg_txt(605, 161, '08:00 - 08:30', 13, '#FFFFFF', 600, 'middle'),
        svg_rect(685, 135, 120, 42, '#FEE2E2', 'none', 8),
        svg_txt(745, 161, 'ĐÃ KÍN CHỖ', 12, '#991B1B', 700, 'middle'),
        svg_rect(825, 135, 120, 42, '#0F766E', 'none', 8),
        svg_txt(885, 161, '09:00 - 09:30', 13, '#FFFFFF', 600, 'middle'),
        svg_txt(545, 205, 'Chi phí khám có BHYT: 45.000đ (Gốc 250.000đ)', 14, '#15803D', 700),
        svg_field(545, 235, 390, 'Triệu chứng & tiền sử bệnh lý', 'Đau tức ngực, huyết áp cao...'),
        svg_cta(545, 335, 390, 48, 'Xác nhận Đặt lịch & Giữ chỗ (v2)', '#0F766E'),
        svg_chip(40, 465, 'MediCare Đặt lịch — Phiên bản 2 (Hiện rõ BHYT, Kín chỗ mờ đỏ)')
    ]));
}

function render_seed_medicare_records_v1() {
    return svg_wrap(implode('', [
        svg_chrome('MediCare', ['Hồ sơ', 'Lịch sử khám', 'Đơn thuốc', 'Chỉ số'], 0),
        svg_card(40, 75, 920, 380),
        svg_txt(70, 115, 'Hồ sơ bệnh án điện tử — Bệnh nhân: Nguyễn Văn An (Mã: BN-2026-88)', 20, '#0F766E', 700),
        svg_line(70, 135, 930, 135),
        svg_txt(70, 170, 'Lần khám gần nhất: 12/10/2026 — Chẩn đoán: Viêm họng cấp, Huyết áp ổn định', 15, '#1E293B', 600),
        svg_rect(70, 195, 860, 60, '#F0FDFA', '#99F6E4', 8),
        svg_txt(90, 230, 'Đơn thuốc: Augmentin 1g (x14 viên), Panadol Extra (x10 viên), Vitamin C 500mg', 14, '#134E4A', 600),
        svg_rect(70, 275, 410, 120, '#FFFFFF', '#E2E8F0', 8),
        svg_txt(90, 310, 'Kết quả xét nghiệm máu (12/10/2026)', 15, '#0F172A', 700),
        svg_txt(90, 340, 'Đường huyết (Glucose): 5.4 mmol/L (Bình thường)', 13, '#16A34A', 600),
        svg_txt(90, 365, 'Cholesterol toàn phần: 4.8 mmol/L (Bình thường)', 13, '#16A34A', 600),
        svg_rect(520, 275, 410, 120, '#FFFFFF', '#E2E8F0', 8),
        svg_txt(540, 310, 'Chỉ số sinh tồn', 15, '#0F172A', 700),
        svg_txt(540, 340, 'Huyết áp: 120/80 mmHg | Nhịp tim: 76 bpm', 13, '#0F172A', 500),
        svg_txt(540, 365, 'Chiều cao: 172cm | Cân nặng: 68kg | BMI: 23.0', 13, '#0F172A', 500),
        svg_chip(40, 465, 'MediCare Bệnh án — Phiên bản 1')
    ]));
}

function render_seed_medicare_records_v2() {
    return svg_wrap(implode('', [
        svg_chrome('MediCare', ['Hồ sơ', 'Lịch sử khám', 'Đơn thuốc', 'Chỉ số'], 0),
        svg_card(40, 75, 920, 380),
        svg_txt(70, 115, 'Hồ sơ bệnh án điện tử — Tích hợp Mã QR BHYT & File PDF có chữ ký số', 20, '#0F766E', 700),
        svg_cta(780, 95, 150, 36, 'Tải PDF bệnh án', '#0F766E'),
        svg_line(70, 140, 930, 140),
        svg_rect(70, 160, 260, 90, '#F0FDFA', '#99F6E4', 8),
        svg_txt(90, 195, 'Huyết áp trung bình', 13, '#134E4A', 500),
        svg_txt(90, 230, '118/78 mmHg', 22, '#0F766E', 800),
        svg_rect(360, 160, 260, 90, '#FEF3C7', '#FDE68A', 8),
        svg_txt(380, 195, 'Đường huyết HbA1c', 13, '#92400E', 500),
        svg_txt(380, 230, '5.2 %', 22, '#B45309', 800),
        svg_rect(650, 160, 280, 90, '#DCFCE7', '#BBF7D0', 8),
        svg_txt(670, 195, 'Cảnh báo dị ứng thuốc', 13, '#166534', 500),
        svg_txt(670, 230, 'Dị ứng: Penicillin G', 18, '#DC2626', 800),
        svg_card(70, 270, 860, 130),
        svg_txt(95, 305, 'Lịch sử tiêm chủng Vắc xin: Cúm mùa (09/2026), Viêm gan B (Đủ 3 mũi)', 14, '#1E293B', 600),
        svg_txt(95, 340, 'Nhắc lịch tái khám định kỳ: Khám mắt & Tai mũi họng ngày 15/12/2026', 14, '#0F766E', 700),
        svg_chip(40, 465, 'MediCare Bệnh án — Phiên bản 2 (Chỉ số đồ thị & Cảnh báo dị ứng nổi bật)')
    ]));
}

function render_seed_edumaster_classroom_v1() {
    return svg_wrap(implode('', [
        svg_chrome('EduMaster', ['Khóa học của tôi', 'Lộ trình', 'Thư viện', 'Hỏi đáp'], 0),
        svg_card(40, 75, 620, 375),
        svg_rect(55, 90, 590, 260, '#0F172A', 'none', 10),
        svg_txt(350, 230, '▶ VIDEO BÀI GIẢNG: THIẾT KẾ UI/UX CHUẨN FIGMA', 16, '#FFFFFF', 700, 'middle'),
        svg_txt(65, 385, 'Bài 08: Xây dựng Auto Layout và Component Set linh hoạt', 16, '#0F172A', 700),
        svg_txt(65, 410, 'Giảng viên: Nguyễn Hoàng Minh • Thời lượng: 42 phút', 13, '#64748B', 500),
        svg_card(680, 75, 280, 375),
        svg_txt(705, 110, 'Danh mục bài học', 16, '#4338CA', 700),
        svg_line(705, 125, 935, 125),
        svg_txt(705, 155, '01. Giới thiệu khóa học', 13, '#16A34A', 600),
        svg_txt(705, 190, '02. Tư duy thiết kế Design System', 13, '#16A34A', 600),
        svg_txt(705, 225, '03. Màu sắc & Typography', 13, '#16A34A', 600),
        svg_rect(695, 245, 250, 35, '#EEF2FF', 'none', 6),
        svg_txt(705, 268, '▶ 08. Auto Layout nâng cao', 13, '#4338CA', 700),
        svg_txt(705, 310, '09. Thiết kế Responsive Mobile', 13, '#64748B', 500),
        svg_txt(705, 345, '10. Bài kiểm tra cuối kỳ', 13, '#64748B', 500),
        svg_cta(705, 380, 230, 42, 'Tải tài liệu thực hành (.fig)', '#4338CA'),
        svg_chip(40, 465, 'EduMaster Phòng học — Phiên bản 1')
    ]));
}

function render_seed_edumaster_classroom_v2() {
    return svg_wrap(implode('', [
        svg_chrome('EduMaster', ['Khóa học', 'Video', 'Ghi chú Timestamp', 'Thảo luận'], 2),
        svg_card(40, 75, 620, 375),
        svg_rect(55, 90, 590, 260, '#020617', 'none', 10),
        svg_txt(350, 220, '▶ KHUNG PHÁT VIDEO CHUẨN 4K — HỖ TRỢ TỐC ĐỘ 1.25x / 1.5x', 15, '#38BDF8', 700, 'middle'),
        svg_rect(55, 320, 590, 30, '#1E293B', 'none', 0),
        svg_line(55, 320, 320, 320, '#6366F1'),
        svg_txt(65, 380, 'Bài 08: Auto Layout nâng cao (Đã tích hợp ghi chú phím tắt J/K/L)', 16, '#0F172A', 700),
        svg_chip(520, 370, 'Đã hoàn thành 68%'),
        svg_card(680, 75, 280, 375),
        svg_txt(705, 110, 'Ghi chú theo thời gian', 16, '#4338CA', 700),
        svg_rect(695, 130, 250, 50, '#F8FAFC', '#E2E8F0', 6),
        svg_txt(705, 152, '⏱ 12:45 — Lưu ý về Hug contents', 12, '#4338CA', 700),
        svg_txt(705, 170, 'Không lồng quá 3 frame Auto Layout.', 11, '#64748B', 500),
        svg_rect(695, 190, 250, 50, '#F8FAFC', '#E2E8F0', 6),
        svg_txt(705, 212, '⏱ 28:10 — Phím tắt biến Frame sang Group', 12, '#4338CA', 700),
        svg_txt(705, 230, 'Dùng Cmd+Opt+G trên macOS.', 11, '#64748B', 500),
        svg_field(695, 255, 250, 'Thêm ghi chú cá nhân', 'Ghi chép tại giây hiện tại...'),
        svg_cta(695, 375, 250, 42, 'Hỏi đáp với giảng viên (12 bình luận)', '#4338CA'),
        svg_chip(40, 465, 'EduMaster Phòng học — Phiên bản 2 (Tích hợp Timestamp & Tiến độ %)')
    ]));
}

function render_seed_edumaster_exam_v1() {
    return svg_wrap(implode('', [
        svg_chrome('EduMaster', ['Bài thi', 'Đồng hồ', 'Bảng câu hỏi', 'Nộp bài'], 0),
        svg_card(40, 75, 650, 375),
        svg_txt(65, 110, 'Câu hỏi 05 / 30: Thuộc tính CSS Flexbox nào dùng căn chỉnh trục phụ?', 16, '#0F172A', 700),
        svg_rect(65, 135, 600, 48, '#F8FAFC', '#E2E8F0', 8),
        svg_txt(85, 165, 'A. justify-content', 14, '#334155', 600),
        svg_rect(65, 195, 600, 48, '#EEF2FF', '#6366F1', 8),
        svg_txt(85, 225, 'B. align-items (Đáp án đã chọn)', 14, '#4338CA', 700),
        svg_rect(65, 255, 600, 48, '#F8FAFC', '#E2E8F0', 8),
        svg_txt(85, 285, 'C. flex-direction', 14, '#334155', 600),
        svg_rect(65, 315, 600, 48, '#F8FAFC', '#E2E8F0', 8),
        svg_txt(85, 345, 'D. align-content', 14, '#334155', 600),
        svg_cta(65, 385, 140, 40, '◀ Câu trước', '#64748B'),
        svg_cta(525, 385, 140, 40, 'Câu tiếp ▶', '#4338CA'),
        svg_card(710, 75, 250, 375),
        svg_txt(735, 110, 'Thời gian làm bài', 16, '#DC2626', 700),
        svg_txt(735, 150, '34 : 20', 32, '#DC2626', 800),
        svg_txt(735, 190, 'Ma trận 30 câu hỏi:', 13, '#64748B', 600),
        svg_rect(735, 210, 35, 35, '#16A34A', 'none', 6),
        svg_txt(752, 233, '1', 12, '#FFFFFF', 700, 'middle'),
        svg_rect(780, 210, 35, 35, '#16A34A', 'none', 6),
        svg_txt(797, 233, '2', 12, '#FFFFFF', 700, 'middle'),
        svg_rect(825, 210, 35, 35, '#16A34A', 'none', 6),
        svg_txt(842, 233, '3', 12, '#FFFFFF', 700, 'middle'),
        svg_rect(870, 210, 35, 35, '#6366F1', 'none', 6),
        svg_txt(887, 233, '4', 12, '#FFFFFF', 700, 'middle'),
        svg_cta(735, 380, 200, 45, 'Nộp bài thi ngay', '#DC2626'),
        svg_chip(40, 465, 'EduMaster Thi thử — Phiên bản 1')
    ]));
}

function render_seed_edumaster_exam_v2() {
    return svg_wrap(implode('', [
        svg_chrome('EduMaster', ['Bài thi', 'Đồng hồ', 'Bảng câu hỏi', 'Nộp bài'], 0),
        svg_card(40, 75, 650, 375),
        svg_rect(40, 75, 650, 35, '#DC2626', 'none', 8),
        svg_txt(65, 98, 'CẢNH BÁO: Còn lại 4 phút 59 giây! Hãy kiểm tra lại các câu chưa làm', 13, '#FFFFFF', 700),
        svg_txt(65, 140, 'Câu hỏi 28 / 30: Phương thức bảo mật nào ngăn chặn tấn công CSRF?', 16, '#0F172A', 700),
        svg_rect(65, 165, 600, 48, '#EEF2FF', '#6366F1', 8),
        svg_txt(85, 195, 'A. Sử dụng Anti-CSRF Token ngẫu nhiên cho mỗi session', 14, '#4338CA', 700),
        svg_rect(65, 225, 600, 48, '#F8FAFC', '#E2E8F0', 8),
        svg_txt(85, 255, 'B. Mã hóa Base64 toàn bộ chuỗi query', 14, '#334155', 600),
        svg_rect(65, 285, 600, 48, '#F8FAFC', '#E2E8F0', 8),
        svg_txt(85, 315, 'C. Chỉ cho phép phương thức HTTP GET', 14, '#334155', 600),
        svg_cta(65, 375, 140, 40, '◀ Câu trước', '#64748B'),
        svg_cta(525, 375, 140, 40, 'Câu tiếp ▶', '#4338CA'),
        svg_card(710, 75, 250, 375),
        svg_txt(735, 110, 'Thời gian đếm ngược', 16, '#DC2626', 700),
        svg_txt(735, 150, '04 : 59', 32, '#DC2626', 900),
        svg_chip(735, 175, 'Đã làm 28/30 câu'),
        svg_cta(735, 370, 200, 50, 'NỘP BÀI CHÍNH THỨC', '#DC2626'),
        svg_chip(40, 465, 'EduMaster Thi thử — Phiên bản 2 (Cảnh báo nhấp nháy đỏ khi sắp hết giờ)')
    ]));
}

function render_seed_finpay_dashboard_v1() {
    return svg_wrap(implode('', [
        svg_chrome('FinPay', ['Tổng quan ví', 'Chuyển tiền', 'Thanh toán hóa đơn', 'Lịch sử'], 0),
        svg_card(40, 75, 360, 220),
        svg_rect(40, 75, 360, 220, '#065F46', 'none', 12),
        svg_txt(65, 115, 'SỐ DƯ KHẢ DỤNG', 13, '#A7F3D0', 700),
        svg_txt(65, 160, '148.520.000 đ', 28, '#FFFFFF', 800),
        svg_txt(65, 195, 'Số tài khoản ví: 0988.123.456', 13, '#D1FAE5', 500),
        svg_cta(65, 225, 95, 35, 'Nạp tiền', '#10B981'),
        svg_cta(170, 225, 95, 35, 'Rút tiền', '#047857'),
        svg_cta(275, 225, 105, 35, 'Chuyển tiền', '#059669'),
        svg_card(430, 75, 530, 220),
        svg_txt(455, 110, 'Biến động số dư & Thu chi tháng 10/2026', 16, '#0F172A', 700),
        svg_txt(455, 135, 'Tổng thu: +45.200.000đ | Tổng chi: -18.600.000đ', 13, '#16A34A', 600),
        svg_line(460, 240, 530, 200, '#10B981'),
        svg_line(530, 200, 610, 220, '#10B981'),
        svg_line(610, 220, 700, 160, '#10B981'),
        svg_line(700, 160, 800, 180, '#10B981'),
        svg_line(800, 180, 920, 140, '#10B981'),
        svg_card(40, 315, 920, 135),
        svg_txt(65, 345, 'Giao dịch gần đây', 16, '#0F172A', 700),
        svg_txt(65, 380, 'Chuyển tiền đến Tran Van Binh (Vietcombank) • -2.500.000đ', 14, '#DC2626', 600),
        svg_txt(65, 410, 'Nhận tiền từ Công ty Cổ phần Công nghệ • +25.000.000đ', 14, '#16A34A', 600),
        svg_chip(40, 465, 'FinPay Bảng điều khiển — Phiên bản 1')
    ]));
}

function render_seed_finpay_dashboard_v2() {
    return svg_wrap(implode('', [
        svg_chrome('FinPay', ['Tổng quan ví', 'Chuyển tiền', 'Báo cáo chi tiêu', 'Bảo mật FaceID'], 0),
        svg_card(40, 75, 380, 230),
        svg_rect(40, 75, 380, 230, '#047857', 'none', 12),
        svg_txt(65, 110, 'SỐ DƯ KHẢ DỤNG (ĐÃ BẬT BẢO VỆ 2 LỚP)', 12, '#A7F3D0', 700),
        svg_txt(65, 155, '148.520.000 đ', 30, '#FFFFFF', 900),
        svg_txt(65, 190, 'Hạn mức ngày: 500.000.000 đ (Đã dùng 3.5%)', 13, '#D1FAE5', 500),
        svg_cta(65, 225, 95, 38, '+ Nạp tiền', '#10B981'),
        svg_cta(170, 225, 95, 38, 'Rút về NH', '#065F46'),
        svg_cta(275, 225, 120, 38, 'Quét VietQR', '#059669'),
        svg_card(445, 75, 515, 230),
        svg_txt(470, 110, 'Phân loại chi tiêu tự động bằng AI', 16, '#0F172A', 700),
        svg_chip(470, 125, 'Ăn uống: 35%'),
        svg_chip(580, 125, 'Hóa đơn: 25%'),
        svg_chip(690, 125, 'Mua sắm: 20%'),
        svg_rect(470, 175, 460, 24, '#E2E8F0', 'none', 12),
        svg_rect(470, 175, 280, 24, '#10B981', 'none', 12),
        svg_txt(470, 225, 'Cảnh báo: Bạn đã chi tiêu 65% ngân sách đề ra cho tháng này', 13, '#B45309', 600),
        svg_card(40, 325, 920, 125),
        svg_txt(65, 355, 'Giao dịch bảo mật sinh trắc học FaceID hoàn tất trong 0.8 giây', 15, '#0F172A', 700),
        svg_txt(65, 385, 'Giao dịch mã số #FP-2026-9921: Chuyển khoản VietQR đến Chi Le (-500.000đ)', 14, '#1E293B', 500),
        svg_chip(40, 465, 'FinPay Bảng điều khiển — Phiên bản 2 (Biểu đồ phân loại AI & Báo cáo hạn mức)')
    ]));
}

function render_seed_finpay_qr_v1() {
    return svg_wrap(implode('', [
        svg_chrome('FinPay', ['Ví điện tử', 'Quét mã VietQR', 'Xác thực', 'Biên lai'], 1),
        svg_card(250, 75, 500, 375),
        svg_txt(330, 110, 'Quét mã QR để chuyển tiền nhanh', 18, '#047857', 700),
        svg_rect(350, 130, 200, 200, '#F0FDF4', '#86EFAC', 12),
        svg_rect(370, 150, 50, 50, '#047857', 'none', 4),
        svg_rect(480, 150, 50, 50, '#047857', 'none', 4),
        svg_rect(370, 260, 50, 50, '#047857', 'none', 4),
        svg_txt(450, 245, 'VIETQR', 18, '#047857', 900, 'middle'),
        svg_txt(310, 355, 'Hỗ trợ quét tất cả các ngân hàng tại Việt Nam (Napas 247)', 13, '#64748B', 500),
        svg_cta(300, 380, 400, 45, 'Chọn ảnh QR từ thư viện máy ảnh', '#047857'),
        svg_chip(40, 465, 'FinPay Quét QR — Phiên bản 1')
    ]));
}

function render_seed_finpay_qr_v2() {
    return svg_wrap(implode('', [
        svg_chrome('FinPay', ['Ví điện tử', 'Quét VietQR', 'Xác thực sinh trắc', 'Biên lai'], 1),
        svg_card(250, 75, 500, 375),
        svg_txt(310, 110, 'Xác nhận chuyển khoản VietQR Napas 24/7', 18, '#047857', 700),
        svg_rect(280, 130, 440, 130, '#F0FDF4', '#86EFAC', 12),
        svg_txt(305, 160, 'Người nhận: NGUYEN VAN AN', 16, '#0F172A', 700),
        svg_txt(305, 185, 'Ngân hàng: Techcombank (TCB) — 1903.888.999', 14, '#1E293B', 500),
        svg_txt(305, 215, 'Số tiền chuyển:', 14, '#64748B', 500),
        svg_txt(420, 215, '2.500.000 đ', 22, '#047857', 800),
        svg_txt(305, 245, 'Miễn phí chuyển khoản 100% 24/7', 12, '#16A34A', 600),
        svg_field(280, 275, 440, 'Nội dung chuyển tiền', 'Thanh toan tien hop dong thiet ke UI'),
        svg_cta(280, 380, 440, 48, 'Xác thực FaceID để chuyển ngay', '#047857'),
        svg_chip(40, 465, 'FinPay Quét QR — Phiên bản 2 (Xem trước tên chủ tài khoản và xác thực sinh trắc)')
    ]));
}

function render_seed_vtravel_explore_v1() {
    return svg_wrap(implode('', [
        svg_chrome('V-Travel', ['Điểm đến', 'Khách sạn', 'Tour trọn gói', 'Cẩm nang'], 0),
        svg_card(40, 75, 920, 120),
        svg_rect(40, 75, 920, 120, '#0284C7', 'none', 12),
        svg_txt(70, 115, 'KHÁM PHÁ VIỆT NAM — TOUR DU LỊCH ĐẲNG CẤP', 24, '#FFFFFF', 800),
        svg_txt(70, 150, 'Đặt phòng khách sạn 5 sao và combo vé máy bay giá tốt nhất mùa thu 2026', 15, '#E0F2FE', 500),
        svg_card(40, 215, 280, 220),
        svg_rect(60, 235, 240, 100, '#BAE6FD', 'none', 8),
        svg_txt(70, 295, 'Đà Nẵng — Hội An', 16, '#0369A1', 700),
        svg_txt(70, 360, 'Furama Resort Đà Nẵng', 16, '#0F172A', 700),
        svg_txt(70, 385, '2.850.000đ/đêm • 5.0★ (890+)', 13, '#0284C7', 600),
        svg_cta(70, 400, 120, 25, 'Xem phòng', '#0284C7'),
        svg_card(360, 215, 280, 220),
        svg_rect(380, 235, 240, 100, '#FEF08A', 'none', 8),
        svg_txt(390, 295, 'Phú Quốc — Kiên Giang', 16, '#854D0E', 700),
        svg_txt(390, 360, 'Vinpearl Resort & Spa', 16, '#0F172A', 700),
        svg_txt(390, 385, '3.200.000đ/đêm • 4.9★ (1.2k+)', 13, '#0284C7', 600),
        svg_cta(390, 400, 120, 25, 'Xem phòng', '#0284C7'),
        svg_card(680, 215, 280, 220),
        svg_rect(700, 235, 240, 100, '#BBF7D0', 'none', 8),
        svg_txt(710, 295, 'Sapa — Lào Cai', 16, '#15803D', 700),
        svg_txt(710, 360, 'Hotel de la Coupole', 16, '#0F172A', 700),
        svg_txt(710, 385, '2.450.000đ/đêm • 4.8★ (650+)', 13, '#0284C7', 600),
        svg_cta(710, 400, 120, 25, 'Xem phòng', '#0284C7'),
        svg_chip(40, 465, 'V-Travel Khám phá — Phiên bản 1')
    ]));
}

function render_seed_vtravel_explore_v2() {
    return svg_wrap(implode('', [
        svg_chrome('V-Travel', ['Điểm đến', 'Khách sạn', 'Tour trọn gói', 'Cẩm nang'], 0),
        svg_card(40, 75, 920, 135),
        svg_txt(65, 105, 'Tìm kiếm chuyến đi mơ ước của bạn', 16, '#0F172A', 700),
        svg_rect(65, 120, 200, 42, '#F8FAFC', '#CBD5E1', 8),
        svg_txt(80, 146, '📍 Đà Nẵng, Việt Nam', 13, '#334155', 600),
        svg_rect(280, 120, 200, 42, '#F8FAFC', '#CBD5E1', 8),
        svg_txt(295, 146, '📅 10/11 - 14/11 (4 đêm)', 13, '#334155', 600),
        svg_rect(495, 120, 200, 42, '#F8FAFC', '#CBD5E1', 8),
        svg_txt(510, 146, '👥 2 người lớn, 1 phòng', 13, '#334155', 600),
        svg_cta(715, 120, 220, 42, 'Tìm kiếm phòng ngay', '#0284C7'),
        svg_chip(65, 180, 'Hủy miễn phí'),
        svg_chip(180, 180, 'Bao gồm bữa sáng'),
        svg_chip(330, 180, 'Bể bơi vô cực'),
        svg_chip(460, 180, 'Giáp bãi biển'),
        svg_card(40, 230, 920, 210),
        svg_rect(60, 245, 260, 180, '#BAE6FD', 'none', 10),
        svg_txt(80, 345, 'ẢNH THỰC TẾ RESORT', 16, '#0369A1', 700),
        svg_txt(345, 275, 'InterContinental Danang Sun Peninsula Resort', 18, '#0F172A', 700),
        svg_txt(345, 305, 'Bán đảo Sơn Trà, Đà Nẵng • Cách bãi biển 50m • Đánh giá 5.0★ Tuyệt hảo (2.4k review)', 13, '#64748B', 500),
        svg_txt(345, 345, 'Tiện ích: Spa cao cấp, Bữa sáng buffet quốc tế, Xe đưa đón sân bay miễn phí', 13, '#0284C7', 600),
        svg_txt(345, 395, 'Giá từ 5.400.000đ/đêm (Đã gồm thuế & phí)', 18, '#DC2626', 800),
        svg_cta(740, 375, 200, 45, 'Chọn phòng & Đặt ngay', '#0284C7'),
        svg_chip(40, 465, 'V-Travel Khám phá — Phiên bản 2 (Thanh tìm kiếm ngày thông minh & Bộ lọc tiện ích)')
    ]));
}

function render_seed_vtravel_detail_v1() {
    return svg_wrap(implode('', [
        svg_chrome('V-Travel', ['Khách sạn', 'Chi tiết phòng', 'Đánh giá', 'Thanh toán'], 1),
        svg_card(40, 75, 580, 375),
        svg_rect(60, 95, 540, 200, '#BAE6FD', 'none', 10),
        svg_txt(240, 205, 'HÌNH ẢNH PHÒNG OCEAN VIEW SUITE', 16, '#0369A1', 700),
        svg_txt(65, 330, 'Phòng Ocean View Suite — Hướng biển ban công riêng', 18, '#0F172A', 700),
        svg_txt(65, 360, 'Diện tích: 65m² • 1 Giường King • Tối đa 2 người lớn + 1 trẻ em', 13, '#64748B', 500),
        svg_txt(65, 390, 'Chính sách: Miễn phí hủy trước 48 giờ. Không cần đặt cọc.', 13, '#16A34A', 600),
        svg_card(645, 75, 315, 375),
        svg_txt(670, 110, 'Tóm tắt đặt phòng', 18, '#0F172A', 700),
        svg_txt(670, 145, 'Nhận phòng: 14:00 (10/11)', 13, '#334155', 500),
        svg_txt(670, 175, 'Trả phòng: 12:00 (14/11)', 13, '#334155', 500),
        svg_line(670, 195, 935, 195),
        svg_txt(670, 225, 'Giá phòng (4 đêm):', 13, '#64748B', 500),
        svg_txt(850, 225, '11.400.000đ', 13, '#0F172A', 600),
        svg_txt(670, 255, 'Thuế GTGT & phí dịch vụ:', 13, '#64748B', 500),
        svg_txt(870, 255, '1.140.000đ', 13, '#0F172A', 600),
        svg_txt(670, 285, 'Ưu đãi thẻ thành viên:', 13, '#64748B', 500),
        svg_txt(870, 285, '-800.000đ', 13, '#16A34A', 600),
        svg_line(670, 310, 935, 310),
        svg_txt(670, 335, 'Tổng thanh toán:', 15, '#0F172A', 700),
        svg_txt(810, 335, '11.740.000đ', 18, '#DC2626', 800),
        svg_cta(670, 370, 265, 48, 'Tiến hành Thanh toán', '#0284C7'),
        svg_chip(40, 465, 'V-Travel Chi tiết phòng — Phiên bản 1')
    ]));
}

function render_seed_vtravel_detail_v2() {
    return svg_wrap(implode('', [
        svg_chrome('V-Travel', ['Khách sạn', 'Chi tiết phòng', 'Đánh giá 360', 'Thanh toán'], 1),
        svg_card(40, 75, 580, 375),
        svg_rect(60, 95, 540, 210, '#0284C7', 'none', 10),
        svg_txt(210, 205, '🔄 CHẾ ĐỘ XEM TOÀN CẢNH VR 360 ĐỘ TRỰC QUAN', 16, '#FFFFFF', 800),
        svg_txt(65, 335, 'Ocean View Suite — Đã thêm lựa chọn Bữa tối lãng mạn bên bãi biển', 16, '#0F172A', 700),
        svg_txt(65, 365, 'Bao gồm: Vé cáp treo Bà Nà Hills + Buffet trưa cho 2 người', 13, '#0284C7', 600),
        svg_txt(65, 395, 'Cam kết giá tốt nhất — Hoàn tiền 100% nếu tìm thấy giá rẻ hơn', 13, '#16A34A', 600),
        svg_card(645, 75, 315, 375),
        svg_rect(645, 75, 315, 40, '#0284C7', 'none', 8),
        svg_txt(670, 100, 'Tích điểm V-Point: +1.200 điểm', 13, '#FFFFFF', 700),
        svg_txt(670, 145, 'Thời gian: 4 đêm (10/11 - 14/11)', 13, '#334155', 500),
        svg_field(670, 170, 265, 'Yêu cầu đặc biệt', 'Phòng tầng cao, view biển...'),
        svg_txt(670, 265, 'Bảo hiểm du lịch trọn gói:', 13, '#16A34A', 700),
        svg_txt(670, 285, 'Được bảo vệ quyền lợi đến 500 triệu', 12, '#64748B', 500),
        svg_line(670, 310, 935, 310),
        svg_txt(670, 335, 'Tổng thanh toán:', 15, '#0F172A', 700),
        svg_txt(810, 335, '11.740.000đ', 18, '#DC2626', 800),
        svg_cta(670, 370, 265, 48, 'Xác nhận & Giữ phòng (v2)', '#0284C7'),
        svg_chip(40, 465, 'V-Travel Chi tiết phòng — Phiên bản 2 (VR 360 và Bảo hiểm du lịch)')
    ]));
}

function render_seed_svg($key) {
    switch ($key) {
        case 'seed:signup-v1': return render_seed_signup_v1();
        case 'seed:signup-v2': return render_seed_signup_v2();
        case 'seed:home-v1': return render_seed_home_v1();
        case 'seed:home-v2': return render_seed_home_v2();
        case 'seed:pay-v1': return render_seed_pay_v1();
        case 'seed:pay-v2': return render_seed_pay_v2();
        case 'seed:dash-v1': return render_seed_dash_v1();
        case 'seed:dash-v2': return render_seed_dash_v2();
        case 'seed:orders-v1': return render_seed_orders_v1();
        case 'seed:orders-v2': return render_seed_orders_v2();
        case 'seed:settings-v1': return render_seed_settings_v1();
        case 'seed:settings-v2': return render_seed_settings_v2();

        // 5 Dự án mới — FoodHub
        case 'seed:foodhub-home-v1': return render_seed_foodhub_home_v1();
        case 'seed:foodhub-home-v2': return render_seed_foodhub_home_v2();
        case 'seed:foodhub-cart-v1': return render_seed_foodhub_cart_v1();
        case 'seed:foodhub-cart-v2': return render_seed_foodhub_cart_v2();
        case 'seed:foodhub-tracking-v1': return render_seed_foodhub_tracking_v1();

        // MediCare
        case 'seed:medicare-booking-v1': return render_seed_medicare_booking_v1();
        case 'seed:medicare-booking-v2': return render_seed_medicare_booking_v2();
        case 'seed:medicare-records-v1': return render_seed_medicare_records_v1();
        case 'seed:medicare-records-v2': return render_seed_medicare_records_v2();

        // EduMaster
        case 'seed:edumaster-classroom-v1': return render_seed_edumaster_classroom_v1();
        case 'seed:edumaster-classroom-v2': return render_seed_edumaster_classroom_v2();
        case 'seed:edumaster-exam-v1': return render_seed_edumaster_exam_v1();
        case 'seed:edumaster-exam-v2': return render_seed_edumaster_exam_v2();

        // FinPay
        case 'seed:finpay-dashboard-v1': return render_seed_finpay_dashboard_v1();
        case 'seed:finpay-dashboard-v2': return render_seed_finpay_dashboard_v2();
        case 'seed:finpay-qr-v1': return render_seed_finpay_qr_v1();
        case 'seed:finpay-qr-v2': return render_seed_finpay_qr_v2();

        // V-Travel
        case 'seed:vtravel-explore-v1': return render_seed_vtravel_explore_v1();
        case 'seed:vtravel-explore-v2': return render_seed_vtravel_explore_v2();
        case 'seed:vtravel-detail-v1': return render_seed_vtravel_detail_v1();
        case 'seed:vtravel-detail-v2': return render_seed_vtravel_detail_v2();

        default:
            return svg_wrap(implode('', [
                svg_chrome('DesignMark Mockup', ['Màn hình', 'Phiên bản', 'Góp ý'], 0),
                svg_card(40, 80, 920, 360),
                svg_txt(70, 130, 'Bản thiết kế giao diện mẫu', 22, '#0F172A', 700),
                svg_txt(70, 165, 'Khóa ảnh: ' . svg_esc($key), 14, '#64748B', 500),
                svg_rect(70, 195, 860, 200, '#F8FAFC', '#E2E8F0', 8),
                svg_txt(500, 305, 'Bản thiết kế mẫu DesignMark', 18, '#94A3B8', 600, 'middle'),
                svg_chip(40, 460, 'DesignMark Seed Image')
            ]));
    }
}
