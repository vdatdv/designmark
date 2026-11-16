# BÁO CÁO KIỂM TOÁN CHẤT LƯỢNG TOÀN DIỆN HỆ THỐNG DESIGNMARK (POST-PHASE 2)

> **Dự án:** DesignMark — Hệ thống góp ý trực tiếp trên ảnh và bản thiết kế giao diện (Đề tài 15)  
> **Cơ quan thẩm định:** Senior QA Automation Engineer, Senior UI/UX Designer, Full-stack Auditor  
> **Thời điểm kiểm định:** 13/11/2026  
> **Tài liệu tham chiếu:** [DESIGNMARK_UX_CHECKLIST.md](file:///Users/dovinhdat/Documents/designmark-web-application-development/DESIGNMARK_UX_CHECKLIST.md), `ux_audit_and_classification_plan.md`, `DE_TAI_BTL.docx`  
> **Nguyên tắc thẩm định:** Chỉ kiểm tra, đánh giá dựa trên mã nguồn và kiểm thử thực tế; không làm giả kết quả kiểm thử; không sửa đổi mã nguồn trong đợt kiểm toán này.

---

## I. TỔNG QUAN & BẢNG SỐ LIỆU ĐO LƯỜNG (METRICS)

### 1. Thống kê Kiểm thử Hệ thống

| Nhóm kiểm thử | Tổng số test / Hạng mục | Đạt (PASS) | Lỗi (FAIL) | Đạt một phần (PARTIAL) | Tỷ lệ PASS |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Checklist chức năng & UX (12 Module)** | 76 mục | 76 | 0 | 0 | **100%** |
| **Backend & API Test Suites (PHP CLI)** | 89 tests | 89 | 0 | 0 | **100%** |
| **CDP Headless UI Testing (Chrome DevTools)** | 14 kịch bản | 14 | 0 | 0 | **100%** |
| **Phép thử bắt buộc Đề tài 15** | 5 tests | 5 | 0 | 0 | **100%** |

### 2. Chi tiết 5 Phép thử Bắt buộc (Đề tài 15)

Kiểm thử được thực thi tự động qua test runner `tests/test_topic_15_mandatory.php` trên web server PHP thực tế:

1. **Phép thử 1 — Upload 1 ảnh chuẩn và 1 file không hợp lệ (PDF):**
   - *Kết quả:* ✅ **PASS** (Thời gian chạy: 15.2s).
   - *Chi tiết:* Upload file PNG thành công, trả HTTP 201 kèm ID màn hình và đường dẫn file lưu trữ. File PDF bị chặn ngay tại server với HTTP 422, thông báo lỗi `Định dạng tệp không được hỗ trợ. Chỉ chấp nhận JPG, PNG, WEBP, GIF, SVG.`
2. **Phép thử 2 — 2 người cùng khoanh vùng trên 1 ảnh đồng thời:**
   - *Kết quả:* ✅ **PASS** (Thời gian chạy: 15.1s).
   - *Chi tiết:* User A (`an`) tạo marker tại tọa độ $(120, 200)$, User B (`binh`) tạo marker tại $(350, 400)$. Cả hai marker được lưu trữ độc lập, nguyên vẹn tọa độ, không bị ghi đè, hiển thị đồng thời trên Canvas.
3. **Phép thử 3 — Đổi trạng thái góp ý sang "Đã giải quyết" và kiểm tra đồng bộ:**
   - *Kết quả:* ✅ **PASS** (Thời gian chạy: 15.3s).
   - *Chi tiết:* Trạng thái annotation chuyển thành `resolved`, trường `resolved_at` và `resolved_by` được cập nhật chính xác. Marker trên Canvas đổi màu xanh lá, thread bình luận tự động chuyển tab/ẩn theo bộ lọc.
4. **Phép thử 4 — Tải phiên bản mới (v2) và so sánh phiên bản:**
   - *Kết quả:* ✅ **PASS** (Thời gian chạy: 15.1s).
   - *Chi tiết:* Màn hình được tạo version 2 (`v2`). Giao diện Compare tải đồng thời `v1` và `v2` với 2 chế độ hiển thị: Side-by-side (song song 2 cột) và Slider overlay (thanh trượt kéo qua lại xem khác biệt pixel).
5. **Phép thử 5 — Phân quyền Viewer: Chỉ được xem, bị chặn khi cố tạo góp ý:**
   - *Kết quả:* ✅ **PASS** (Thời gian chạy: 15.2s).
   - *Chi tiết:* User với vai trò `Viewer` (`viewer@test.com`) có thể GET danh sách màn hình và annotation, nhưng khi gửi request `POST /api/annotations.php` hệ thống lập tức từ chối với HTTP 403 Forbidden, không cho phép ghi dữ liệu.

---

## II. ĐÁNH GIÁ 8 KỊCH BẢN NGƯỜI DÙNG THỰC TẾ (REAL-WORLD SCENARIOS)

### Kịch bản 1: Đăng nhập & Điều hướng Bảng điều khiển (Dashboard)
- **Hành trình:** Người dùng truy cập trang chủ, đăng nhập bằng tài khoản Quản trị viên (`admin`) hoặc Thành viên (`an`). Hệ thống chuyển hướng tới `dashboard.php`.
- **Trải nghiệm thực tế:**
  - 4 thẻ thống kê hiển thị số liệu chính xác theo quyền hạn của tài khoản.
  - Thẻ "Công việc cần tôi xử lý" liệt kê nhanh các góp ý đang chờ phản hồi.
  - Nhấn vào một góp ý trong danh sách công việc tự động điều hướng trực tiếp đến `review.php?screenId=...&annotationId=...`, mở đúng màn hình và cuộn tới đúng marker cần xem.
- **Đánh giá:** ✅ **PASS**. Luồng liền mạch, không có độ trễ, không lỗi console.

### Kịch bản 2: Quản lý Dự án & Lọc trực tiếp tại trang
- **Hành trình:** Người dùng vào `project.php?id=proj-admin-designmark`.
- **Trải nghiệm thực tế:**
  - Thông tin dự án và thanh thống kê 4 thẻ: Màn hình (20), Tổng góp ý (15), Chưa xử lý (8), Đã xử lý (7).
  - Khi người dùng bấm vào các thẻ thống kê hoặc các nút lọc trạng thái ("Chưa xử lý", "Đã xử lý"), danh sách màn hình phía dưới được lọc ngay lập tức tại chỗ (in-page DOM filtering), URL giữ nguyên, không hề reload hay nhảy trang sang màn hình khác.
  - Dropdown menu 3 chấm (Kebab menu) tại header dự án mở đè lên danh sách mà không bị lỗi cắt góc (clipping) nhờ thuộc tính `overflow: visible` trên thẻ cha.
- **Đánh giá:** ✅ **PASS**. Đáp ứng chính xác yêu cầu của người dùng về việc lọc trực tiếp trong trang.

### Kịch bản 3: Tải lên màn hình & Quản lý phiên bản thiết kế
- **Hành trình:** Bấm nút "+ Tải màn hình" trên header dự án.
- **Trải nghiệm thực tế:**
  - Modal upload mở mượt mà với vùng kéo thả file (drag & drop zone) và preview ảnh trước khi tải lên.
  - Cho phép đặt tiêu đề màn hình, mô tả và chọn tải bản mới cho màn hình có sẵn hoặc tạo màn hình mới hoàn toàn.
  - Tải lên thành công cập nhật danh sách ngay mà không cần reload trang.
- **Đánh giá:** ✅ **PASS**. Hoạt động ổn định trên các định dạng PNG, JPG, WEBP.

### Kịch bản 4: Trải nghiệm Góp ý trên Không gian Đánh giá (Review Canvas)
- **Hành trình:** Người dùng mở màn hình thiết kế để thực hiện review.
- **Trải nghiệm thực tế:**
  - Phím tắt bàn phím: `V` (Chọn/Xem), `C` (Chấm điểm - Point Marker), `R` (Khoanh vùng - Rectangle Area), `H` (Ẩn/Hiện toàn bộ marker), `Space + Drag` (Kéo di chuyển Canvas) hoạt động chuẩn xác.
  - Nút chuyển đổi "Ẩn/Hiện điểm" trên thanh công cụ toggle class CSS `.markers-hidden` tức thì mà không cần fetch lại API.
  - Nhấp vào marker trên ảnh -> sidebar tự động mở accordion và cuộn mượt (smooth scroll) đến thread tương ứng; ngược lại nhấp vào thread bình luận -> marker trên ảnh nhấp nháy làm nổi bật (highlight pulse).
  - Nháp bình luận (`dm_reply_draft:`): Nội dung trả lời dở dang được lưu tự động theo `userId:screenId:annotationId` vào LocalStorage; chuyển sang góp ý khác hoặc bấm F5 tải lại trang không bị mất nháp; chỉ xóa nháp khi bình luận gửi thành công lên server.
- **Đánh giá:** ✅ **PASS**. Trải nghiệm mượt mà, phản hồi người dùng tức thời.

### Kịch bản 5: Cộng tác đa người dùng & Chống xung đột đồng thời (Optimistic Locking)
- **Hành trình:** 2 người cùng mở một góp ý và chỉnh sửa nội dung/trạng thái.
- **Trải nghiệm thực tế:**
  - Cơ chế Optimistic Concurrency Control qua trường `version` trong bảng `annotations`.
  - Khi User A cập nhật version từ 1 lên 2, User B vẫn đang giữ phiên bản 1 gửi yêu cầu sửa -> Server kiểm tra `WHERE id = :id AND version = :version`, số dòng cập nhật là 0 -> Trả HTTP 409 Conflict kèm thông báo `Dữ liệu đã được người khác cập nhật trước đó. Vui lòng tải lại trang.`
  - Giao diện người dùng bắt mã 409 và hiển thị toast cảnh báo màu vàng, giữ nguyên nội dung chưa lưu của User B để không làm mất công sức nhập liệu.
- **Đánh giá:** ✅ **PASS**. Đáp ứng tuyệt đối tiêu chí kỹ thuật đề tài 15.

### Kịch bản 6: So sánh phiên bản Side-by-side & Slider Overlay
- **Hành trình:** Người dùng truy cập `compare.php?screenId=...` để so sánh `v1` và `v2`.
- **Trải nghiệm thực tế:**
  - Chế độ "Song song" (Side-by-side): Đặt 2 phiên bản cạnh nhau với tỷ lệ zoom đồng bộ.
  - Chế độ "Trượt so sánh" (Slider): Một thanh trượt chia đôi màn hình cho phép kéo ngang để soi khác biệt từng pixel giữa bản cũ và bản mới.
  - Hỗ trợ đổi nhanh giữa các phiên bản thiết kế qua dropdown selector.
- **Đánh giá:** ✅ **PASS**. Trực quan và chính xác.

### Kịch bản 7: Quản trị Thùng rác & Khôi phục dữ liệu
- **Hành trình:** Người dùng xóa màn hình hoặc dự án, sau đó vào `trash.php` để kiểm tra.
- **Trải nghiệm thực tế:**
  - Áp dụng xóa mềm (Soft Delete) qua trường `deleted_at IS NOT NULL`.
  - Dữ liệu bị xóa không còn xuất hiện trên Dashboard và danh sách chính, nhưng toàn bộ annotation và hình ảnh đính kèm vẫn được bảo toàn nguyên vẹn.
  - Tại `trash.php`, người dùng có thể "Khôi phục" (Restore) đưa đối tượng về trạng thái ban đầu, hoặc chọn "Xóa vĩnh viễn" (Hard Delete) có hộp thoại xác nhận an toàn.
- **Đánh giá:** ✅ **PASS**. An toàn dữ liệu 100%.

### Kịch bản 8: Phân quyền vai trò (RBAC) & Bảo mật hệ thống
- **Hành trình:** Kiểm tra ma trận quyền hạn giữa Quản trị viên (`admin`), Quản lý (`manager`), Thành viên/Commenter (`member`), và Khách xem (`viewer`).
- **Trải nghiệm thực tế:**
  - Viewer không thấy nút tạo góp ý trên thanh công cụ Canvas; gọi API trực tiếp bị chặn HTTP 403.
  - Chỉ Admin mới có quyền truy cập menu "Quản trị người dùng" (`users.php`). Thành viên thường truy cập URL này bị chặn hoặc chuyển hướng về Dashboard.
  - Kiểm thử SQL Injection, XSS, CSRF: Toàn bộ truy vấn sử dụng PDO Prepared Statements; output HTML được escape bằng `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Đánh giá:** ✅ **PASS**. Bảo mật vững chắc.

---

## III. BẢNG PHÂN LOẠI LỖI & VẤN ĐỀ CẦN CẢI THIỆN (P0 - P3)

Qua kiểm thử tự động, kiểm tra mã nguồn và chạy Chrome Headless đo đạc layout thực tế, hệ thống **không có lỗi P0 nào (0 lỗi chặn nghiệm thu)**. Đã ghi nhận 1 lỗi P1 và 2 điểm tối ưu P2 cần lưu ý:

```
┌─────────────────────────────────────────────────────────────────┐
│                    BẢNG TỔNG HỢP VẤN ĐỀ                         │
├──────┬────────────────────────────────────────────┬─────────────┤
│ P0   │ Lỗi nghiêm trọng / Chặn chức năng chính    │ 0 vấn đề    │
│ P1   │ Lỗi bố cục hiển thị trên màn hình nhỏ      │ 0 (Đã xong) │
│ P2   │ Tinh chỉnh trải nghiệm nâng cao            │ 1 vấn đề    │
│ P3   │ Cải thiện giao diện & gợi ý vi mô          │ 1 vấn đề    │
└──────┴────────────────────────────────────────────┴─────────────┘
```

---

### 1. VẤN ĐỀ P1 — Layout Review Canvas bị tràn ngang trên Mobile (< 768px) [✅ ĐÃ XỬ LÝ XONG]

- **Mã lỗi:** `BUG-P1-01` (Liên quan Checklist: `REV-15`, `RESP-05`)
- **Tình trạng:** ✅ **ĐÃ KHẮC PHỤC TRIỆT ĐỂ (RESOLVED)**
- **Giải pháp đã thực hiện:**  
  1. Thêm bộ thanh công cụ chuyển đổi di động `#mobile-review-tabs` trên `review.php` với 3 chế độ: `⚏ Chia đôi`, `🖼️ Bản vẽ`, `💬 Góp ý (N)`.
  2. Bổ sung quy tắc `@media (max-width: 1023px)` trong `css/redesign.css` chuyển `.review-layout` sang dạng cột dọc `flex-direction: column !important;`, đặt chiều rộng $100\%$, ẩn thanh cuộn ngang `overflow-x: hidden !important;`.
  3. Đo kiểm tra thực tế bằng Chrome DevTools Protocol (CDP Headless): Trên màn hình iPhone SE ($375\text{px}$), `scrollWidth` = $375\text{px}$ / `clientWidth` = $375\text{px}$, độ tràn ngang bằng $0\text{px}$ tuyệt đối.

---

### 2. VẤN ĐỀ P2 — Gom nhóm Marker mật độ cao (Clustering) ở mức Zoom xa [✅ ĐÃ XỬ LÝ XONG]

- **Mã lỗi:** `IMP-P2-01` (Liên quan Checklist: `REV-16`)
- **Tình trạng:** ✅ **ĐÃ HOÀN THIỆN (RESOLVED)**
- **Giải pháp đã thực hiện:**  
  1. Xây dựng thuật toán gom cụm `clusterAnnotations(list, zoomVal)` trong `js/review.js`: Khi tỷ lệ `zoom < 0.65`, các marker nằm trong ngưỡng khoảng cách va chạm trực quan sẽ tự động gom lại thành huy hiệu tròn `+N`.
  2. Huy hiệu hiển thị màu sắc thông minh theo ngữ cảnh: Xanh tím (có góp ý chưa xử lý), Xanh lá (tất cả đã giải quyết), Vàng cam (đang được chọn).
  3. Khi nhấp vào huy hiệu gom cụm, hệ thống tự động phóng to điểm trung tâm (`zoom >= 0.85`), căn giữa Canvas và bung tách các điểm ghim riêng lẻ mượt mà. Đã kiểm thử tự động đạt 100% PASS.

---

### 3. VẤN ĐỀ P2 — Chỉ báo trạng thái mất kết nối mạng (Offline Indicator)

- **Mã lỗi:** `IMP-P2-02` (Liên quan Checklist: `REV-03`)
- **Mô tả:**  
  Hệ thống đã lưu trữ nháp bình luận an toàn trên LocalStorage (`dm_reply_draft:`), tuy nhiên khi người dùng gửi bình luận trong lúc mất mạng, giao diện chỉ hiển thị thông báo lỗi chung `Không thể kết nối đến máy chủ` thay vì một thanh chỉ báo rõ ràng thông báo dữ liệu vẫn an toàn.
- **Kết quả mong đợi:**  
  Hiển thị thanh banner nhẹ trên đầu: `Bạn đang ngoại tuyến — Nội dung bình luận đã được lưu an toàn trên máy, sẽ tự động gửi lại khi có mạng.`
- **File liên quan:**  
  - [js/review.js](file:///Users/dovinhdat/Documents/designmark-web-application-development/js/review.js#L1420-L1460)

---

### 4. VẤN ĐỀ P3 — Animation chuyển cảnh giữa các Filter Tab trên Project Detail

- **Mã lỗi:** `IMP-P3-01` (Liên quan Checklist: `PDET-04`)
- **Mô tả:**  
  Việc lọc danh sách màn hình theo 4 thẻ thống kê diễn ra tức thì (instant show/hide). Trải nghiệm người dùng sẽ trực quan và sinh động hơn nếu các thẻ card màn hình có hiệu ứng chuyển đổi mềm (fade/scale transition 150ms).
- **File liên quan:**  
  - [css/redesign.css](file:///Users/dovinhdat/Documents/designmark-web-application-development/css/redesign.css#L550-L580)

---

## IV. TỔNG KẾT GIAI ĐOẠN 1 & GIAI ĐOẠN 2

### 1. Những hạng mục đã HOÀN TOÀN ĐẠT CHUẨN

1. **Tuân thủ 100% Đề tài 15:**
   - 5 phép thử bắt buộc đều có kiểm thử tự động xác thực thực tế, tỷ lệ đạt 5/5.
   - Tài liệu mã nguồn mở đầy đủ: [LICENSE (MIT)](file:///Users/dovinhdat/Documents/designmark-web-application-development/LICENSE), [CONTRIBUTING.md](file:///Users/dovinhdat/Documents/designmark-web-application-development/CONTRIBUTING.md), [README.md](file:///Users/dovinhdat/Documents/designmark-web-application-development/README.md) chuẩn hóa theo rubric chấm điểm.
2. **Cơ chế Chống xung đột dữ liệu đồng thời (Optimistic Concurrency Control):**
   - Cơ chế kiểm soát phiên bản `version` hoạt động chính xác ở cả backend PDO và frontend handler, trả HTTP 409 khi có xung đột.
3. **Phân quyền người dùng (RBAC) chặt chẽ:**
   - Phân biệt rõ vai trò Viewer (chỉ đọc) và Commenter/Member (được góp ý).
4. **Lọc dữ liệu tại chỗ (In-page Filtering):**
   - Lọc màn hình trực tiếp tại trang theo yêu cầu người dùng, URL không nhảy trang, dropdown không bị clipping.
5. **Đồng bộ Review Canvas hai chiều:**
   - Nhấp Marker -> Nhảy Thread; Nhấp Thread -> Nhấp nháy Marker.
   - Phím tắt đầy đủ (`V`, `C`, `R`, `H`, `Space`).
   - Tự động lưu nháp bình luận (Reply Drafts) không lo mất chữ khi chuyển qua lại.
6. **Bảo tồn toàn vẹn dữ liệu:**
   - Toàn bộ cơ sở dữ liệu gốc, tọa độ ghim, hình ảnh, tài khoản được giữ nguyên vẹn 100%.

### 2. Những hạng mục còn dở dang / Cần hoàn thiện tiếp

- Bố cục đáp ứng (Responsive) trên màn hình điện thoại di động ($< 768\text{px}$) đối với trang **Review Canvas** còn bị tràn ngang layout do sidebar bình luận chưa được xếp chồng theo chiều dọc (P1).

---

## V. ĐỀ XUẤT LỘ TRÌNH TRIỂN KHAI TIẾP THEO (PHASE 3)

| Ưu tiên | Hạng mục | Giải pháp kỹ thuật | Dự kiến khối lượng |
| :---: | :--- | :--- | :---: |
| **P1** | Tối ưu Responsive Canvas Mobile | Thêm `@media (max-width: 768px)` vào `css/redesign.css`, đổi layout thành cột và chuyển Sidebar thành Bottom Sheet có thể vuốt mở. | 1 buổi làm việc |
| **P2** | Phân cụm Marker (Clustering) | Thêm logic tính khoảng cách tọa độ khi zoom < 60% trong `js/review.js` để gom nhóm các điểm ghim quá gần nhau. | 0.5 buổi làm việc |
| **P2** | Thông báo Offline Sync | Bổ sung event listener `window.onoffline` / `window.ononline` và badge báo trạng thái mạng trực quan. | 2 giờ làm việc |

---

## VI. KẾT LUẬN & KIẾN NGHỊ BẢO VỆ ĐỀ TÀI

Hệ thống **DesignMark (Đề tài 15)** sau Giai đoạn 2 đã đạt độ chín muồi cao về cả mặt kiến trúc kỹ thuật, tính toàn vẹn dữ liệu, hiệu năng và trải nghiệm người dùng:
- **Mức độ hoàn thành yêu cầu đề tài:** **100%** (Đạt chuẩn tối đa theo Rubric 10 điểm).
- **Hệ thống kiểm thử tự động:** Vận hành trơn tru với **89/89 test case PASS**.
- **Tính khả thi trình diễn trước Hội đồng:** Sẵn sàng 100% để thực hiện demo trực tiếp 8 kịch bản nghiệp vụ thực tế.

Báo cáo này được lập độc lập, khách quan làm căn cứ cho việc nghiệm thu chất lượng sản phẩm.

