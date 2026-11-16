<?php
// index.php - Login page
require_once __DIR__ . '/api/db.php';

$session = get_current_user_session($pdo);
if ($session) {
    $next = $_GET['next'] ?? 'dashboard.php';
    if (!str_contains($next, '://') && !str_starts_with($next, '//')) {
        header('Location: ' . $next);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Đăng nhập — DesignMark';
$pageDescription = 'DesignMark — góp ý trực tiếp trên ảnh và bản thiết kế giao diện.';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
  <a class="skip-link" href="#login-form">Bỏ qua giới thiệu</a>
  <div class="login-shell">
    <section class="login-hero">
      <div>
        <div class="brand">
          <span class="login-mark" aria-hidden="true"><span>D</span></span>
          <span class="brand-text">
            <strong>DesignMark</strong>
            <small>Góp ý trên thiết kế</small>
          </span>
        </div>
        <h1>Góp ý đúng vị trí trên từng phiên bản giao diện.</h1>
        <p>Đặt điểm, khoanh vùng và trao đổi trực tiếp trên ảnh thiết kế với độ chuẩn xác từng pixel.</p>

        <!-- Bản thiết kế mẫu mô phỏng "Ghim trên giấy can" -->
        <div class="login-hero-preview" aria-hidden="true">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid #282a36; padding-bottom: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444; display: inline-block;"></span>
              <span style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span>
              <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
              <span style="font-family: var(--font-mono); font-size: 12px; color: #a1a5b5; margin-left: 8px;">Artboard #01 — Trang chủ (1440 × 900)</span>
            </div>
            <span class="badge" style="background: rgba(225, 29, 72, 0.2); color: #fda4af; border: 1px solid rgba(253, 164, 175, 0.35);">2 góp ý</span>
          </div>

          <div style="position: relative; padding: 12px 8px; min-height: 140px;">
            <!-- Wireframe mẫu -->
            <div style="display: flex; gap: 10px; margin-bottom: 14px;">
              <div style="height: 20px; width: 100px; background: #323648; border-radius: 4px;"></div>
              <div style="height: 20px; flex: 1; background: #262938; border-radius: 4px;"></div>
            </div>

            <!-- Vùng khoanh đỏ minh họa -->
            <div style="border: 2px dashed #e11d48; background: rgba(225, 29, 72, 0.08); border-radius: 6px; padding: 14px 16px; margin-bottom: 14px; position: relative;">
              <span style="position: absolute; top: -10px; left: 10px; background: #e11d48; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; letter-spacing: 0.2px;">Vùng góp ý #1</span>
              <div style="height: 12px; width: 55%; background: #717894; border-radius: 3px; margin-bottom: 8px;"></div>
              <div style="height: 8px; width: 85%; background: #4d536c; border-radius: 3px; margin-bottom: 6px;"></div>
              <div style="height: 8px; width: 65%; background: #4d536c; border-radius: 3px;"></div>
            </div>

            <div style="display: flex; gap: 16px; align-items: flex-start;">
              <!-- Ghim 1: Đỏ giọt nước với animation thả xuống -->
              <div class="hero-pin" title="Điểm góp ý số 1"><span>1</span></div>
              <div style="background: #242735; border: 1px solid #363949; border-radius: 8px; padding: 10px 14px; font-size: 13px; max-width: 340px;">
                <strong style="color: #fda4af; font-size: 12px; display: block; margin-bottom: 2px;">Điểm #1 · Chưa xử lý</strong>
                <span style="color: #e2e4e9;">"Cần tăng độ tương phản của tiêu đề và khoảng cách dòng (line-height) của đoạn mô tả để dễ đọc hơn trên mobile."</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hero-points">
          <div>Quản lý màn hình và nhiều phiên bản ảnh.</div>
          <div>Góp ý theo tọa độ ảnh gốc, không lệch khi phóng to.</div>
          <div>So sánh hai phiên bản và xuất danh sách góp ý.</div>
        </div>
      </div>
      <p style="font-size: 13px; color: #8b8f9e; margin: 0;">DesignMark — Công cụ đánh giá giao diện chuyên nghiệp</p>
    </section>
    <main class="login-panel">
      <div class="auth-theme-toggle-wrap">
        <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" aria-label="Chuyển sang giao diện tối" title="Chuyển sang giao diện tối">
          <svg class="icon icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="icon icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
          </svg>
        </button>
      </div>
      <section class="login-card">
        <h2>Chào mừng trở lại</h2>
        <p class="lede">Đăng nhập để xem dự án và góp ý thiết kế.</p>
        <form id="login-form" data-testid="login-form" novalidate>
          <div class="field">
            <label class="field-label" for="username">Tên đăng nhập</label>
            <input class="field-input" id="username" name="username" autocomplete="username" placeholder="Ví dụ: an hoặc admin" required>
          </div>
          <div class="field">
            <label class="field-label" for="password">Mật khẩu</label>
            <div style="position: relative;">
              <input class="field-input" id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required style="padding-right: 56px;">
              <button type="button" id="toggle-password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px 6px; font-size: 13px; font-weight: 500;" aria-label="Hiện hoặc ẩn mật khẩu">
                Hiện
              </button>
            </div>
          </div>
          <label class="checkline" for="remember">
            <input id="remember" type="checkbox" checked>
            Ghi nhớ phiên trên trình duyệt này
          </label>
          <p id="login-error" class="field-error" role="alert"></p>
          <button id="login-button" data-testid="login-button" class="btn btn-primary" type="submit" style="width: 100%; padding: 10px 16px; min-height: 42px;">Đăng nhập</button>
        </form>

        <p class="auth-switch-link">
          Chưa có tài khoản? <a href="register.php">Tạo tài khoản mới</a>
        </p>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script type="module">
    import { clearSession, getSession, login, redirectAfterLogin } from './js/auth.js';
    import { toast } from './js/ui.js';

    // Theme Toggle Handler
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    if (themeToggleBtn) {
      const updateAria = () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        themeToggleBtn.setAttribute('aria-label', isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối');
        themeToggleBtn.setAttribute('title', isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối');
      };
      updateAria();
      themeToggleBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try {
          localStorage.setItem('dm_theme', next);
        } catch (e) {}
        updateAria();
        toast(next === 'dark' ? 'Đã bật giao diện tối (Dark Mode)' : 'Đã bật giao diện sáng (Light Mode)', 'info');
      });
    }

    // If PHP session is not active, clear any stale client storage
    if (!window.DESIGNMARK_SESSION) {
      clearSession();
    } else if (getSession()) {
      redirectAfterLogin();
    }

    const form = document.getElementById('login-form');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const error = document.getElementById('login-error');
    const togglePassBtn = document.getElementById('toggle-password');

    if (togglePassBtn) {
      togglePassBtn.addEventListener('click', () => {
        const isPass = password.type === 'password';
        password.type = isPass ? 'text' : 'password';
        togglePassBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
      });
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      error.textContent = '';
      const button = document.getElementById('login-button');
      button.disabled = true;
      try {
        const user = await login(username.value, password.value, document.getElementById('remember').checked);
        redirectAfterLogin(user);
      } catch (err) {
        if (err.code === 'ACCOUNT_LOCKED') {
          error.innerHTML = `<div class="locked-account-box" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 12px 14px; color: #b91c1c; font-size: 0.875rem; line-height: 1.5; text-align: left; margin-top: 8px;">
            <strong style="display: block; margin-bottom: 4px; font-weight: 600;">Tài khoản đã bị khóa</strong>
            ${err.message || 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Quản trị viên để được hỗ trợ.'}
          </div>`;
        } else {
          error.textContent = err.message || 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        }
        button.disabled = false;
        (err.message && err.message.includes('mật khẩu') ? password : username).focus();
      }
    });
  </script>
</body>
</html>

