# PHÁT TRIỂN PHẦN MỀM MÃ NGUỒN MỞ — BÁO CÁO BÀI TẬP LỚN

## NHÓM 12 - CNTT 1810

* **Trường:** Đại học Đại Nam  
* **Khoa:** Công nghệ Thông tin  
* **Học phần:** Phát triển phần mềm mã nguồn mở  
* **Đề tài 15:** **Xây dựng ứng dụng góp ý trực tiếp trên hình ảnh và bản thiết kế giao diện**

### Danh sách thành viên nhóm

| STT | Họ và tên | Mã sinh viên | Vai trò | Phân công công việc |
| :---: | :--- | :---: | :---: | :--- |
| 1 | **Đỗ Vinh Đạt** | 1871020126 | Nhóm trưởng | Kiến trúc hệ thống, Backend PHP & CSDL PostgreSQL/SQLite, Canvas tọa độ & Kiểm thử |
| 2 | **Vương Xuân Sơn** | 1871020502 | Thành viên | Giao diện Responsive, Quản lý dự án, So sánh phiên bản & Xuất báo cáo CSV |
| 3 | **Nguyễn Phú Duy** | 1871020196 | Thành viên | Hệ thống xác thực, Phân quyền RBAC, Đóng gói mã nguồn & Tài liệu |

---

# 🎨 DesignMark — Visual Design Review & Collaboration Platform

**DesignMark** là hệ thống web mã nguồn mở phục vụ đánh giá, trao đổi và phản biện trực tiếp trên các bản vẽ thiết kế giao diện (UI/UX) và hình ảnh đồ họa chuẩn pixel.

Hệ thống cho phép nhà thiết kế tổ chức theo **Dự án → Màn hình thiết kế → Các phiên bản (v1, v2...)**. Người duyệt có thể ghim điểm hoặc khoanh vùng chữ nhật chính xác trên ảnh để trao đổi theo luồng (thread), theo dõi tiến độ xử lý và so sánh trực quan giữa các phiên bản.

---

## 🚀 1. Hướng Dẫn Cài Đặt & Chạy Ứng Dụng

### Yêu cầu môi trường
* **PHP:** Phiên bản 8.0 trở lên (hỗ trợ `pdo_pgsql` và `pdo_sqlite`).
* **Cơ sở dữ liệu:** PostgreSQL (kết nối Supabase Cloud) hoặc SQLite cục bộ (tự động đồng bộ kép).
* **Trình duyệt:** Google Chrome, Firefox, Safari hoặc Microsoft Edge phiên bản mới nhất.

### Các bước khởi chạy cục bộ

1. **Khởi động máy chủ phát triển PHP:**
   Mở terminal tại thư mục gốc của dự án và chạy lệnh:
   ```bash
   php -S 0.0.0.0:8000
   ```
2. **Truy cập ứng dụng:**
   Mở trình duyệt và truy cập: [http://localhost:8000](http://localhost:8000).
   *Hệ thống sẽ tự động khởi tạo cơ sở dữ liệu và nạp dữ liệu mẫu hoàn chỉnh ở lần đầu tiên.*

3. **Chạy toàn bộ bộ kiểm thử tự động:**
   ```bash
   # Kiểm tra 5 phép thử bắt buộc của Đề tài 15:
   php tests/test_topic_15_mandatory.php

   # Chạy bộ kiểm thử hợp nhất (All test suites):
   php scripts/run_all_tests.php
   ```

---

## 👥 2. Danh Sách Tài Khoản Thử Nghiệm (Demo Accounts)

Hệ thống đã chuẩn bị sẵn 4 tài khoản với đầy đủ các vai trò phân quyền theo Đề tài 15:

| Tên đăng nhập | Mật khẩu | Họ và tên | Vai trò trong hệ thống | Quyền hạn trong dự án mẫu |
| :---: | :---: | :--- | :--- | :--- |
| **`admin`** | `demo` | Quản trị viên Hệ thống | System Admin | Toàn quyền quản trị tài khoản, dự án, hệ thống |
| **`an`** | `demo` | An Nguyễn | Quản lý (Owner/Manager) | Tạo dự án, tải màn hình, duyệt thành viên, phân quyền |
| **`binh`** | `demo` | Bình Trần | Góp ý (Reviewer) | Ghim điểm, khoanh vùng, phản hồi, đổi trạng thái |
| **`chi`** | `demo` | Chi Lê | Chỉ xem (Viewer) | Theo dõi bản vẽ & nhận xét (bị cấm tạo/sửa góp ý) |

*(Mật khẩu demo cho tất cả tài khoản mẫu là: `demo`)*

---

## 🎬 3. Hướng Dẫn Hai Tình Huống Sử Dụng Demo (Mục 4 — Đề tài 15)

Khi bảo vệ đồ án trước Giảng viên, nhóm thực hiện trình diễn 2 kịch bản trọng tâm sau:

### Tình huống 1: Tạo dự án → Tải ảnh → Đánh dấu vùng cần sửa → Trao đổi phản hồi
1. Đăng nhập bằng tài khoản **`an`** (Quản lý).
2. Vào **Danh sách dự án** → Bấm **+ Tạo dự án mới**, nhập tên dự án (ví dụ: *Ứng dụng Mobile Banking*).
3. Trong trang dự án, bấm **Tải màn hình** để tải lên file ảnh PNG/JPEG thiết kế v1.
4. Mời tài khoản **`binh`** vào dự án với vai trò **Góp ý (Reviewer)**.
5. Đăng xuất và đăng nhập bằng tài khoản **`binh`**.
6. Mở màn hình vừa tạo → Chọn công cụ **Khoanh vùng (Rect)** hoặc **Đặt điểm (Pin)**.
7. Đánh dấu lên vị trí nút bấm hoặc nội dung cần chỉnh sửa → Nhập nhận xét: *"Nút Đăng ký cần tăng độ tương phản đạt chuẩn WCAG"* → Bấm **Gửi góp ý**.
8. Đăng nhập lại bằng tài khoản **`an`** → Mở góp ý của Bình → Trả lời phản hồi: *"Đã tiếp nhận và đang tiến hành cập nhật bản vẽ mới"*.

### Tình huống 2: Tải phiên bản mới → So sánh cạnh nhau → Xác nhận xử lý → Xuất danh sách CSV
1. Với tài khoản **`an`**, mở màn hình thiết kế đang có góp ý → Bấm **Tải phiên bản mới (v2)** tải lên bản vẽ đã sửa.
2. Bấm nút **So sánh phiên bản** trên thanh công cụ (chuyển sang `compare.php`).
3. Đặt hai phiên bản **v1** và **v2** cạnh nhau (chế độ **Side-by-side** hoặc vuốt **Swipe**).
4. Đối chiếu vị trí nút bấm cũ ở v1 và nút bấm mới ở v2: nhận xét của v1 được giữ cố định theo v1, không bị nhảy lung tung sang v2.
5. Nhấn vào góp ý → Đổi trạng thái sang **Đã xử lý (Resolved)**.
6. Vào trang **Quản lý góp ý (`feedback.php`)** hoặc bấm **Danh sách góp ý** → Bấm **Xuất CSV** để tải về tệp bảng tính UTF-8 có đầy đủ: *STT, Tên dự án, Màn hình, Phiên bản, Nội dung, Tọa độ, Tác giả, Trạng thái, Thời gian*.

---

## 🎯 4. Kết Quả 5 Phép Thử Bắt Buộc (Mục 6 — Đề tài 15)

Lệnh kiểm thử tự động:
```bash
php tests/test_topic_15_mandatory.php
```

| STT | Phép thử bắt buộc theo đề bài | Kết quả thực tế | Chi tiết kiểm chứng |
| :---: | :--- | :---: | :--- |
| **1** | Hiển thị ảnh 1.000 × 500 ở kích thước 500 × 250: điểm mẫu (750; 150) vẫn nằm đúng vị trí; kiểm tra lề trống và phóng to | **[PASS] 100%** | Ảnh gốc `scr-signup` v1 (1000×500) có điểm mẫu (750, 150). Khi co về 500×250 tọa độ hiển thị là (375, 75). Khi có lề trống (letterbox) thuật toán tự động trừ lề; khi zoom 200% điểm hiển thị là (1500, 300). |
| **2** | Lưu rồi mở lại một vùng chữ nhật: vị trí, kích thước vùng và nội dung nhận xét đúng với ảnh gốc | **[PASS] 100%** | Lưu vùng rect (x=180, y=90, w=320, h=160). Gọi API GET đọc lại từ database: vị trí, kích thước và body khớp 100%. |
| **3** | Tải phiên bản có nút chuyển vị trí: nhận xét cũ chỉ thuộc ảnh cũ; hai bản và lịch sử vẫn tra cứu được | **[PASS] 100%** | Bản v1 có 25+ góp ý; bản v2 hoàn toàn độc lập (0 góp ý rò rỉ); lịch sử phiên bản `image_versions` lưu trữ đầy đủ. |
| **4** | Người chỉ xem không tạo/sửa góp ý; thành viên dự án A không lấy ảnh hoặc nhận xét riêng của dự án B bằng URL | **[PASS] 100%** | Tài khoản `chi` (Viewer) gửi request POST tạo/sửa nhận xét bị chặn HTTP 403. Thành viên dự án A đọc dữ liệu dự án B qua API bị chặn HTTP 403 (Anti-IDOR). |
| **5** | Hai người trả lời không làm mất một câu trả lời; sửa cùng nhận xét từ phiên bản cũ được báo xung đột; đóng rồi mở lại lưu đúng lịch sử | **[PASS] 100%** | Hai phản hồi gửi đồng thời được lưu nguyên vẹn; sửa nhận xét có revision cũ bị từ chối bằng HTTP 409 (Optimistic Locking); bảng `annotation_status_events` ghi nhận đầy đủ lịch sử đóng/mở. |

---

## 🛠️ 5. Kiến Trúc & Công Nghệ Sử Dụng

### 1. Phân định mã nguồn nhóm tự viết vs Thư viện sử dụng lại
* **Mã nguồn nhóm tự phát triển 100%:**
  * Toàn bộ kiến trúc Backend RESTful API bằng PHP thuần trong `api/` (`auth.php`, `projects.php`, `screens.php`, `annotations.php`, `feedback.php`, `dashboard.php`, `users.php`).
  * Thuật toán quy đổi tọa độ chuẩn hóa và loại trừ lề trống trong `js/coords.js`.
  * Bộ điều khiển canvas ghim điểm, khoanh vùng, zoom/pan và so sánh phiên bản trong `js/review.js` và `js/compare.js`.
  * Bộ kiểm thử tự động hóa trong `tests/test_topic_15_mandatory.php`.
* **Thư viện & Tài nguyên mở sử dụng lại:**
  * Phông chữ: **Inter** & **Be Vietnam Pro** (Google Fonts, giấy phép SIL Open Font License).
  * Bộ icon vector SVG: **Feather Icons / Lucide** (Giấy phép MIT).
  * Trình khách kết nối cơ sở dữ liệu: `PDO` tích hợp sẵn trong PHP runtime.

### 2. An toàn dữ liệu & Bảo mật
* Chống **SQL Injection:** 100% truy vấn cơ sở dữ liệu sử dụng PDO Prepared Statements với tham số ràng buộc (`:param`).
* Chống **XSS (Cross-Site Scripting):** Mọi nội dung nhận xét của người dùng được gán an toàn qua thuộc tính `textContent` hoặc DOM element builder `el()`, tuyệt đối không dùng `innerHTML` không được làm sạch.
* Chống **IDOR & Vi phạm quyền:** Mọi thao tác đều kiểm tra quyền hạn thành viên qua hàm `require_member($pdo, $projectId, $allowedRoles)` ở cấp độ máy chủ.
* Chống **Xung đột ghi đè đồng thời:** Sử dụng cơ chế khóa lạc quan (Optimistic Concurrency Control) với số hiệu phiên bản `revision`, trả về HTTP 409 Conflict khi phát hiện sửa đè.

---

## 📄 6. Giấy Phép & Bản Quyền

Dự án được phát hành theo giấy phép mã nguồn mở **MIT License**. Chi tiết xem tại tệp [LICENSE](file:///Users/dovinhdat/Documents/designmark-web-application-development/LICENSE).  
Quy định đóng góp mã nguồn xem tại tệp [CONTRIBUTING.md](file:///Users/dovinhdat/Documents/designmark-web-application-development/CONTRIBUTING.md).
