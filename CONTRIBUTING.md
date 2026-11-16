# Hướng Dẫn Đóng Góp — DesignMark

Dự án **DesignMark** là sản phẩm mã nguồn mở phục vụ môn học *Phát triển phần mềm mã nguồn mở* (Đề tài 15: *Xây dựng ứng dụng góp ý trực tiếp trên hình ảnh và bản thiết kế giao diện*).

Tài liệu này quy định quy trình cộng tác, tiêu chuẩn lập trình và quy ước đóng góp mã nguồn giữa các thành viên trong nhóm phát triển.

---

## 1. Quy Trình Làm Việc (Git Flow & Branching)

* **Nhánh chính (`main` hoặc `lab5`):** Chứa mã nguồn ổn định, đã qua kiểm thử và sẵn sàng phát hành.
* **Nhánh tính năng (`feature/<ten-tinh-nang>`):** Mỗi chức năng hoặc sửa lỗi tạo nhánh riêng trước khi gộp.
* **Quy ước Commit:**
  * `feat:` Bổ sung tính năng mới (ví dụ: `feat: export-csv-annotations`).
  * `fix:` Sửa lỗi (ví dụ: `fix: pin-coordinate-letterbox-clipping`).
  * `test:` Thêm hoặc cập nhật ca kiểm thử (ví dụ: `test: add-mandatory-topic-15-suite`).
  * `docs:` Cập nhật tài liệu (ví dụ: `docs: update-readme-with-demo-scenarios`).
  * `refactor:` Tối ưu cấu trúc mã nguồn không làm đổi hành vi.

---

## 2. Tiêu Chuẩn Kỹ Thuật & Kiến Trúc Mã Nguồn

* **Backend:**
  * Ngôn ngữ: **PHP 8.x** (thuần, không dùng framework nặng để kiểm soát toàn bộ cơ chế xử lý).
  * Cơ sở dữ liệu: **PostgreSQL** kết hợp cơ chế đồng bộ tự động **SQLite fallback** khi chạy ngoại tuyến.
  * Tách biệt tầng xử lý: API đặt trong thư mục `api/`, không nhúng mã HTML vào API; dữ liệu trả về theo định dạng JSON chuẩn `json_response()`.
  * An toàn dữ liệu: Bắt buộc dùng **Prepared Statements** (`PDO::prepare`) để ngăn SQL Injection; kiểm tra quyền truy cập ở phía máy chủ bằng hàm `require_member()` và `require_admin()`.

* **Frontend:**
  * Công nghệ: **Vanilla JavaScript (ES Modules)**, CSS3 (CSS Variables, Flexbox, Grid), không phụ thuộc jQuery hoặc build tools cồng kềnh.
  * Trình diễn dữ liệu an toàn: Sử dụng `el()` hoặc gán thuộc tính `textContent` để ngăn ngừa lỗi bảo mật XSS (Cross-Site Scripting).

---

## 3. Quy Định Về Kiểm Thử & Chấp Nhận Mã Nguồn (Pull Request / Review)

Mỗi thay đổi mã nguồn trước khi tạo bản phát hành phải thỏa mãn:
1. Chạy thành công bộ kiểm thử hợp nhất:
   ```bash
   php scripts/run_all_tests.php
   php tests/test_topic_15_mandatory.php
   ```
2. Không làm phát sinh lỗi hồi quy (regression) trên 12 ca kiểm thử phân quyền tài khoản và 5 phép thử bắt buộc của Đề tài 15.
3. Không đẩy thông tin nhạy cảm (mật khẩu thật, file cấu hình `.local`, file `.env` chứa bí mật thật) lên hệ thống quản lý phiên bản.

---

## 4. Quản Lý Vấn Đề (Issues & Code Review)

* Mọi lỗi phát hiện hoặc yêu cầu cải tiến đều được lập thành **Issue** trên GitHub với mô tả các bước tái hiện (Steps to Reproduce) và kết quả mong đợi.
* Mỗi Pull Request cần ít nhất một thành viên khác trong nhóm xem xét (Review) và chấp thuận trước khi gộp vào nhánh chính.

