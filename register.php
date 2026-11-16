<?php
// register.php - Register new account based on Account Management System
require_once __DIR__ . '/api/db.php';

$session = get_current_user_session($pdo);
if ($session) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Đăng ký tài khoản — DesignMark';
$pageDescription = 'Tạo tài khoản mới trên hệ thống DesignMark. Hoạt động tức thì, hỗ trợ quản lý dự án và phân quyền chuyên nghiệp.';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
  <a class="skip-link" href="#register-form">Bỏ qua giới thiệu</a>
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
        <h1>Khởi tạo tài khoản làm việc cùng đội ngũ.</h1>
        <p>Đăng ký nhanh chóng, kích hoạt ngay lập tức để tạo dự án và tham gia phản hồi giao diện chuẩn xác từng pixel.</p>

        <!-- Thẻ mô phỏng tài khoản chuẩn hệ thống Quản lý tài khoản -->
        <div class="hero-profile-preview" aria-hidden="true">
          <div class="hero-profile-header">
            <div class="hero-profile-avatar">HN</div>
            <div class="hero-profile-info">
              <strong>Hoàng Nguyễn</strong>
              <span>@hoang_design</span>
            </div>
            <span class="status-pill is-active" style="margin-left: auto;">
              <span class="status-pill-dot"></span>
              <span>Người dùng (User)</span>
            </span>
          </div>

          <!-- Thống kê dự án theo mô hình mới -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
            <div style="background: #252833; border: 1px solid #323544; border-radius: 8px; padding: 10px 12px;">
              <span style="font-size: 11.5px; color: #8b8f9e; display: block;">Dự án sở hữu</span>
              <strong style="font-size: 17px; color: #ffffff;">01</strong>
              <small style="color: #60a5fa; font-size: 11px; margin-left: 4px;">Owner</small>
            </div>
            <div style="background: #252833; border: 1px solid #323544; border-radius: 8px; padding: 10px 12px;">
              <span style="font-size: 11.5px; color: #8b8f9e; display: block;">Dự án tham gia</span>
              <strong style="font-size: 17px; color: #ffffff;">03</strong>
              <small style="color: #34d399; font-size: 11px; margin-left: 4px;">Thành viên</small>
            </div>
          </div>

          <!-- 4 vai trò dự án linh hoạt -->
          <div style="font-size: 11.5px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
            4 vai trò phân quyền linh hoạt theo dự án:
          </div>
          <div class="hero-role-grid">
            <div class="hero-role-item">
              <strong>Owner</strong>
              <span>Toàn quyền quản trị dự án</span>
            </div>
            <div class="hero-role-item">
              <strong>Designer</strong>
              <span>Tải ảnh & quản lý phiên bản</span>
            </div>
            <div class="hero-role-item">
              <strong>Reviewer</strong>
              <span>Đặt ghim, khoanh vùng & góp ý</span>
            </div>
            <div class="hero-role-item">
              <strong>Viewer</strong>
              <span>Theo dõi tiến độ & so sánh ảnh</span>
            </div>
          </div>
        </div>

        <div class="hero-points">
          <div>Tài khoản hoạt động tức thì ngay sau khi đăng ký, không cần xét duyệt.</div>
          <div>Tự do tạo dự án cá nhân hoặc tham gia dự án được mời.</div>
          <div>Bảo mật dữ liệu tối ưu với chuẩn mã hóa mật khẩu hiện đại.</div>
        </div>
      </div>
      <p style="font-size: 13px; color: #8b8f9e; margin: 0;">DesignMark — Nền tảng đánh giá và góp ý giao diện chuyên nghiệp</p>
    </section>

    <main class="login-panel">
      <!-- Nút đổi giao diện sáng/tối -->
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

      <section class="login-card" style="max-width: 460px;">
        <a href="index.php" class="auth-back-btn" title="Quay lại trang đăng nhập">← Quay lại đăng nhập</a>
        <h2>Đăng ký tài khoản</h2>
        <p class="lede">Khởi tạo tài khoản cá nhân để tạo dự án và tham gia góp ý thiết kế.</p>

        <!-- Thẻ thông báo đăng ký thành công -->
        <div id="register-success-card" class="registration-success-card" style="display: none; text-align: center; padding: 28px 20px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; margin-top: 12px;">
          <div style="font-weight: 700; color: #10B981; font-size: 1.1rem; margin-bottom: 8px;">THÀNH CÔNG</div>
          <h3 style="margin: 0 0 8px 0; color: var(--heading); font-size: 1.25rem;">Đăng ký tài khoản hoàn tất!</h3>
          <p id="success-message" style="color: var(--muted); font-size: 0.925rem; line-height: 1.6; margin-bottom: 22px;">
            Tài khoản của bạn đã được kích hoạt tự động.<br>
            Bạn có thể bắt đầu sử dụng DesignMark ngay bây giờ.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="dashboard.php" class="btn btn-primary" style="text-decoration: none; padding: 10px 24px;">Bắt đầu sử dụng ngay</a>
            <a href="index.php" class="btn btn-secondary" style="text-decoration: none; padding: 10px 20px;">Trang Đăng nhập</a>
          </div>
        </div>

        <!-- Form Đăng ký -->
        <form id="register-form" novalidate>
          <div class="field">
            <label class="field-label" for="reg-name">Họ và tên <span style="color: var(--primary);">*</span></label>
            <input class="field-input" id="reg-name" name="name" placeholder="Ví dụ: Hoàng Nguyễn" required autocomplete="name">
          </div>

          <div class="field">
            <label class="field-label" for="reg-username">Tên đăng nhập <span style="color: var(--primary);">*</span></label>
            <input class="field-input" id="reg-username" name="username" autocomplete="username" placeholder="Ví dụ: hoang_design" required>
            <p class="help">3 đến 30 ký tự (chữ cái, số, gạch dưới hoặc gạch ngang).</p>
          </div>

          <div class="field">
            <label class="field-label" for="reg-email">Địa chỉ Email <span style="color: var(--primary);">*</span></label>
            <input class="field-input" id="reg-email" name="email" type="email" autocomplete="email" placeholder="Ví dụ: hoang.nguyen@company.com" required>
            <p class="help">Dùng để nhận thông báo góp ý dự án và đăng nhập.</p>
          </div>

          <div class="field">
            <label class="field-label" for="reg-password">Mật khẩu <span style="color: var(--primary);">*</span></label>
            <div style="position: relative;">
              <input class="field-input" id="reg-password" name="password" type="password" autocomplete="new-password" placeholder="Tối thiểu 4 ký tự" required style="padding-right: 56px;">
              <button type="button" id="toggle-password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px 6px; font-size: 13px; font-weight: 500;" aria-label="Hiện hoặc ẩn mật khẩu">
                Hiện
              </button>
            </div>
          </div>

          <div class="field">
            <label class="field-label" for="reg-confirm">Xác nhận mật khẩu <span style="color: var(--primary);">*</span></label>
            <div style="position: relative;">
              <input class="field-input" id="reg-confirm" name="confirm" type="password" autocomplete="new-password" placeholder="Nhập lại mật khẩu" required style="padding-right: 56px;">
              <button type="button" id="toggle-confirm" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px 6px; font-size: 13px; font-weight: 500;" aria-label="Hiện hoặc ẩn xác nhận mật khẩu">
                Hiện
              </button>
            </div>
            <div id="password-match-hint" class="password-match-hint" style="display: none;"></div>
          </div>

          <p id="register-error" class="field-error" role="alert"></p>
          <button id="register-button" class="btn btn-primary" type="submit" style="width: 100%; margin-top: 8px; padding: 10px 16px; min-height: 42px;">Tạo tài khoản & Bắt đầu ngay</button>
        </form>

        <p id="login-link-container" class="auth-switch-link" style="margin-top: 20px;">
          Đã có tài khoản? <a href="index.php">Đăng nhập tại đây</a>
        </p>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script type="module">
    import { registerAccount } from './js/auth.js';
    import { toast } from './js/ui.js';

    // 1. Theme Toggle Handler
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

    // 2. Elements & Controls
    const form = document.getElementById('register-form');
    const successCard = document.getElementById('register-success-card');
    const successMsg = document.getElementById('success-message');
    const loginLinkContainer = document.getElementById('login-link-container');
    const usernameInput = document.getElementById('reg-username');
    const nameInput = document.getElementById('reg-name');
    const emailInput = document.getElementById('reg-email');
    const passwordInput = document.getElementById('reg-password');
    const confirmInput = document.getElementById('reg-confirm');
    const togglePassBtn = document.getElementById('toggle-password');
    const toggleConfirmBtn = document.getElementById('toggle-confirm');
    const matchHint = document.getElementById('password-match-hint');
    const errorEl = document.getElementById('register-error');
    const submitBtn = document.getElementById('register-button');

    const RESERVED_USERNAMES = ['admin', 'administrator', 'system', 'root', 'support', 'help', 'api', 'guest'];

    // 3. Toggle Password Visibility (Text button Hiện/Ẩn, no icon)
    if (togglePassBtn && passwordInput) {
      togglePassBtn.addEventListener('click', () => {
        const isPass = passwordInput.type === 'password';
        passwordInput.type = isPass ? 'text' : 'password';
        togglePassBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
      });
    }

    if (toggleConfirmBtn && confirmInput) {
      toggleConfirmBtn.addEventListener('click', () => {
        const isPass = confirmInput.type === 'password';
        confirmInput.type = isPass ? 'text' : 'password';
        toggleConfirmBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
      });
    }

    // 4. Realtime Password Match Validation
    function checkPasswordMatch() {
      const pass = passwordInput.value;
      const conf = confirmInput.value;
      if (!conf) {
        matchHint.style.display = 'none';
        return;
      }
      matchHint.style.display = 'flex';
      if (pass === conf) {
        matchHint.className = 'password-match-hint is-matched';
        matchHint.textContent = 'Mật khẩu trùng khớp';
      } else {
        matchHint.className = 'password-match-hint is-mismatched';
        matchHint.textContent = 'Mật khẩu xác nhận chưa khớp';
      }
    }

    passwordInput.addEventListener('input', checkPasswordMatch);
    confirmInput.addEventListener('input', checkPasswordMatch);

    // 7. Form Submission
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorEl.textContent = '';

      const username = usernameInput.value.trim();
      const name = nameInput.value.trim();
      const email = emailInput ? emailInput.value.trim() : '';
      const password = passwordInput.value;
      const confirm = confirmInput.value;

      // Client-side validations
      if (!name) {
        errorEl.textContent = 'Vui lòng nhập họ và tên.';
        nameInput.focus();
        return;
      }
      if (!username) {
        errorEl.textContent = 'Vui lòng nhập tên đăng nhập.';
        usernameInput.focus();
        return;
      }
      if (!/^[a-zA-Z0-9_\-\.]{3,30}$/.test(username)) {
        errorEl.textContent = 'Tên đăng nhập từ 3 đến 30 ký tự, chỉ gồm chữ cái, số, gạch dưới hoặc gạch ngang.';
        usernameInput.focus();
        return;
      }
      if (RESERVED_USERNAMES.includes(username.toLowerCase())) {
        errorEl.textContent = 'Tên đăng nhập này thuộc danh sách dành riêng cho hệ thống. Vui lòng chọn tên khác.';
        usernameInput.focus();
        return;
      }
      if (!email) {
        errorEl.textContent = 'Vui lòng nhập địa chỉ email.';
        emailInput.focus();
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errorEl.textContent = 'Địa chỉ email không đúng định dạng (ví dụ: user@example.com).';
        emailInput.focus();
        return;
      }
      if (password.length < 4) {
        errorEl.textContent = 'Mật khẩu phải có tối thiểu 4 ký tự.';
        passwordInput.focus();
        return;
      }
      if (password !== confirm) {
        errorEl.textContent = 'Mật khẩu xác nhận không khớp.';
        confirmInput.focus();
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Đang khởi tạo tài khoản...';

      try {
        const data = await registerAccount({
          username,
          name,
          email,
          password,
          confirm_password: confirm
        });

        // Hide form and show auto-activated success card
        form.style.display = 'none';
        if (loginLinkContainer) loginLinkContainer.style.display = 'none';
        if (successMsg) {
          successMsg.innerHTML = `
            Tài khoản <strong>${data.username || username}</strong> (${name}) đã được tạo và kích hoạt tự động.<br>
            Bạn có thể bắt đầu sử dụng DesignMark ngay bây giờ mà không cần chờ phê duyệt.
          `;
        }
        successCard.style.display = 'block';
        toast('Đăng ký tài khoản thành công!', 'success');
      } catch (err) {
        errorEl.textContent = err.message || 'Lỗi khi tạo tài khoản.';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Tạo tài khoản & Bắt đầu ngay';
      }
    });
  </script>
</body>
</html>
