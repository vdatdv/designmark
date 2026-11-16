<?php
// review.php - Review and annotate designs
require_once __DIR__ . '/api/db.php';

$session = get_current_user_session($pdo);
if (!$session) {
    header('Location: index.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'review.php'));
    exit;
}

$pageTitle = 'Góp ý — DesignMark';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body data-page="review">
  <a class="skip-link" href="#review-toolbar">Bỏ qua điều hướng</a>
  <div class="app-shell">
    <aside id="sidebar" class="sidebar" aria-label="Điều hướng chính"></aside>
    <div id="sidebar-backdrop" class="sidebar-backdrop" hidden></div>
    <div class="app-main">
      <header id="topbar" class="topbar"></header>
      <main id="page" class="page page-review" tabindex="-1" data-preserve="true">
        <div class="review-toolbar" id="review-toolbar" data-testid="review-toolbar">
          <div class="toolbar-group">
            <a id="back-link" class="btn btn-secondary btn-sm" href="projects.php" title="Quay lại dự án" style="display: none;">← Quay lại dự án</a>
            <strong id="screen-name" class="screen-title-badge">Đang tải màn hình</strong>
            <label class="field-label sr-only" for="version-select">Phiên bản</label>
            <div class="version-select-wrap">
              <span class="version-select-prefix">Bản</span>
              <select id="version-select" data-testid="version-select" aria-label="Chọn phiên bản ảnh"></select>
            </div>
            <a id="compare-link" class="btn btn-secondary btn-sm" href="compare.php" title="So sánh các phiên bản">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h7v12H4zM13 6h7v12h-7z"/></svg>
              <span>So sánh</span>
            </a>
            <button type="button" id="migrate-btn" class="btn btn-secondary btn-sm" title="Chuyển góp ý chưa xong từ bản cũ">Chuyển góp ý cũ</button>
            <button type="button" id="export-annotated-btn" class="btn btn-secondary btn-sm" title="Tải ảnh kèm các ghim và vùng chú thích">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v10M8 11l4 4 4-4M5 19h14"/></svg>
              <span>Xuất ảnh</span>
            </button>
          </div>
          <div class="toolbar-group" role="toolbar" aria-label="Phóng to và công cụ góp ý">
            <div class="zoom-pill-group">
              <button type="button" id="zoom-out" class="zoom-btn" data-testid="zoom-out" aria-label="Thu nhỏ" title="Thu nhỏ">−</button>
              <span id="zoom-label" class="zoom-label">100%</span>
              <button type="button" id="zoom-in" class="zoom-btn" data-testid="zoom-in" aria-label="Phóng to" title="Phóng to">+</button>
              <button type="button" id="zoom-fit" class="zoom-btn zoom-btn-fit" data-testid="zoom-fit" title="Vừa khung hình">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H5v4M15 4h4v4M9 20H5v-4M15 20h4v-4"/></svg>
                <span>Vừa khung</span>
              </button>
            </div>
            <div class="tool-segmented-group">
              <button type="button" id="tool-select" class="tool-btn is-active" data-testid="tool-select" aria-pressed="true" title="Chọn và xem góp ý (Phím V hoặc Esc)">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 4 6.5 15 1.8-5.7L19 11.5 5 4Z"/></svg>
                <span>Chọn (V)</span>
              </button>
              <button type="button" id="tool-point" class="tool-btn" data-testid="tool-point" aria-pressed="false" title="Đặt điểm đánh dấu (Phím C hoặc P)">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s6-5.1 6-10a6 6 0 1 0-12 0c0 4.9 6 10 6 10Z"/><circle cx="12" cy="11" r="1.6" fill="currentColor"/></svg>
                <span>Đặt điểm (C)</span>
              </button>
              <button type="button" id="tool-rectangle" class="tool-btn" data-testid="tool-rectangle" aria-pressed="false" title="Khoanh vùng chữ nhật (Phím R)">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2"/></svg>
                <span>Khoanh vùng (R)</span>
              </button>
              <button type="button" id="tool-arrow" class="tool-btn" data-testid="tool-arrow" aria-pressed="false" title="Vẽ mũi tên chỉ dẫn">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="19" x2="19" y2="5"/><polyline points="9 5 19 5 19 15"/></svg>
                <span>Mũi tên</span>
              </button>
              <button type="button" id="tool-freehand" class="tool-btn" data-testid="tool-freehand" aria-pressed="false" title="Vẽ nét tự do">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                <span>Vẽ tự do</span>
              </button>
            </div>
            <button type="button" id="coord-btn" class="btn btn-secondary btn-sm" title="Nhập tọa độ chính xác">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
              <span>Tọa độ</span>
            </button>
            <button type="button" id="toggle-markers-btn" class="btn btn-secondary btn-sm" title="Ẩn/Hiện điểm đánh dấu (Phím tắt: H)">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              <span id="toggle-markers-label">Ẩn/Hiện điểm</span>
            </button>
          </div>
        </div>
        <p id="tool-hint" class="sr-only" aria-live="polite"></p>
        <p id="permission-note" class="permission-note" hidden>Bạn chỉ có quyền xem.</p>
        <p id="version-banner" class="version-banner" role="status" hidden></p>
        <div class="mobile-review-tabs" id="mobile-review-tabs" role="tablist" aria-label="Chuyển chế độ xem di động">
          <button type="button" class="mobile-tab-btn is-active" data-tab="split" role="tab" aria-selected="true" title="Xem song song bản vẽ và danh sách góp ý">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18"/></svg>
            <span>Chia đôi</span>
          </button>
          <button type="button" class="mobile-tab-btn" data-tab="canvas" role="tab" aria-selected="false" title="Mở rộng toàn màn hình bản vẽ thiết kế">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            <span>Bản vẽ</span>
          </button>
          <button type="button" class="mobile-tab-btn" data-tab="comments" role="tab" aria-selected="false" title="Xem danh sách góp ý và bình luận">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Góp ý (<span id="mobile-comments-count">0</span>)</span>
          </button>
        </div>
        <div class="review-layout" id="review-layout">
          <div class="canvas-column">
            <div id="canvas-viewport" class="canvas-viewport">
              <div class="canvas-pad">
                <div id="image-stage" class="image-stage" data-testid="image-stage" tabindex="0" aria-label="Vùng ảnh thiết kế. Dùng phím mũi tên và Enter để đặt điểm khi công cụ tương ứng đang bật.">
                  <img id="review-image" alt="Ảnh thiết kế đang tải">
                  <div id="image-overlay" class="overlay" data-testid="image-overlay"></div>
                </div>
              </div>
            </div>
          </div>
          <aside id="comment-panel" class="comment-panel" data-testid="comment-panel" aria-label="Danh sách góp ý"></aside>
        </div>
      </main>
    </div>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script type="module" src="js/review.js"></script>
</body>
</html>
