<!DOCTYPE html>
<html lang="vi">
<head>
<?php include __DIR__ . "/head.php"; ?>
  <style>
    body.auth-slider-body {
      background-color: var(--bg);
      background-image: var(--grid-bg);
      background-size: 20px 20px;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 24px 16px;
      color: var(--text);
    }

    .auth-slider-container .password-match-hint[hidden] {
      display: none !important;
    }

    [data-theme="dark"] .locked-account-box {
      color: #fca5a5 !important;
    }

    .auth-slider-container {
      background-color: var(--surface-raised, #ffffff);
      border: 1px solid var(--border);
      border-radius: 26px;
      box-shadow: 0 22px 54px rgba(15, 17, 24, 0.16);
      position: relative;
      overflow: hidden;
      width: 940px;
      max-width: 100%;
      min-height: 620px;
      animation: authEntrance 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes authEntrance {
      0% {
        opacity: 0;
        transform: translateY(22px) scale(0.985);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    [data-theme="dark"] .auth-slider-container {
      background-color: #181920;
      border-color: #2a2c36;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
    }

    .auth-slider-container .form-container {
      position: absolute;
      top: 0;
      height: 100%;
      transition: all 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    }

    .auth-slider-container .sign-in-container {
      left: 0;
      width: 50%;
      z-index: 2;
    }

    .auth-slider-container:not(.active) .sign-in-container {
      animation: dmAuthShowSignIn 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    }

    @keyframes dmAuthShowSignIn {
      0%, 49.99% { opacity: 0; z-index: 1; }
      50%, 100% { opacity: 1; z-index: 2; }
    }

    .auth-slider-container .sign-up-container {
      left: 0;
      width: 50%;
      opacity: 0;
      z-index: 1;
      pointer-events: none;
    }

    .auth-slider-container.active .sign-in-container {
      transform: translateX(100%);
      opacity: 0;
      pointer-events: none;
    }

    .auth-slider-container.active .sign-up-container {
      transform: translateX(100%);
      opacity: 1;
      z-index: 5;
      pointer-events: auto;
      animation: dmAuthShow 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    }

    @keyframes dmAuthShow {
      0%, 49.99% { opacity: 0; z-index: 1; }
      50%, 100% { opacity: 1; z-index: 5; }
    }

    .auth-form-inner {
      background-color: var(--surface-raised, #ffffff);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 28px 42px;
      height: 100%;
      overflow-y: auto;
    }

    [data-theme="dark"] .auth-form-inner {
      background-color: #181920;
    }

    .auth-brand-pill {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 14px;
    }

    .auth-brand-pill .login-mark {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-strong) 100%);
      color: #ffffff;
      display: grid;
      place-items: center;
      font-family: var(--font-heading);
      font-weight: 800;
      font-size: 16px;
      box-shadow: 0 4px 12px rgba(214, 36, 92, 0.25);
    }

    .auth-brand-pill strong {
      font-family: var(--font-heading);
      font-size: 17px;
      color: var(--heading);
      letter-spacing: -0.02em;
    }

    .auth-form-title {
      font-family: var(--font-heading);
      font-size: 24px;
      font-weight: 700;
      color: var(--heading);
      margin: 0 0 4px 0;
      letter-spacing: -0.02em;
    }

    .auth-form-lede {
      font-size: 13.5px;
      color: var(--muted);
      margin: 0 0 18px 0;
      line-height: 1.45;
    }

    .auth-slider-container .dm-field {
      margin-bottom: 11px;
      text-align: left;
    }

    .auth-slider-container .dm-field-label {
      display: block;
      font-size: 12.5px;
      font-weight: 600;
      color: var(--heading);
      margin-bottom: 5px;
    }

    .auth-slider-container .dm-input {
      background-color: var(--surface-subtle, #f2f4f1);
      border: 1px solid var(--border);
      padding: 10px 14px;
      width: 100%;
      border-radius: 10px;
      color: var(--heading);
      font-size: 14px;
      outline: none;
      transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
    }

    [data-theme="dark"] .auth-slider-container .dm-input {
      background-color: #232530;
      border-color: #323544;
      color: #f1f3f5;
    }

    .auth-slider-container .dm-input:focus {
      border-color: var(--primary);
      background-color: var(--surface-raised, #ffffff);
      box-shadow: 0 0 0 3px var(--primary-ring);
    }

    [data-theme="dark"] .auth-slider-container .dm-input:focus {
      background-color: #282b38;
    }

    .auth-pw-wrap {
      position: relative;
    }

    .auth-pw-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--muted);
      padding: 4px 6px;
      font-size: 12.5px;
      font-weight: 600;
    }

    .auth-pw-toggle:hover {
      color: var(--primary);
    }

    .auth-two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .auth-main-btn {
      border-radius: 9999px;
      border: none;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-strong) 100%);
      color: #ffffff;
      font-family: var(--font-heading);
      font-size: 13.5px;
      font-weight: 700;
      padding: 12px 32px;
      letter-spacing: 0.4px;
      cursor: pointer;
      margin-top: 8px;
      width: 100%;
      min-height: 44px;
      box-shadow: 0 8px 20px rgba(214, 36, 92, 0.25);
      transition: transform 0.12s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    }

    .auth-main-btn:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(214, 36, 92, 0.34);
    }

    .auth-main-btn:active:not(:disabled) {
      transform: scale(0.97);
    }

    .auth-main-btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }

    .auth-slider-container .overlay-container {
      position: absolute;
      top: 0;
      left: 50%;
      width: 50%;
      height: 100%;
      overflow: hidden;
      transition: transform 0.6s cubic-bezier(0.65, 0, 0.35, 1);
      z-index: 100;
    }

    .auth-slider-container.active .overlay-container {
      transform: translateX(-100%);
    }

    .auth-slider-container .overlay {
      background: linear-gradient(135deg, #15161c 0%, #241422 48%, var(--primary-strong) 100%);
      color: #ffffff;
      position: relative;
      left: -100%;
      height: 100%;
      width: 200%;
      transform: translateX(0);
      transition: transform 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    }

    .auth-slider-container .overlay::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
      background-size: 18px 18px;
      pointer-events: none;
    }

    .auth-slider-container.active .overlay {
      transform: translateX(50%);
    }

    .auth-slider-container .overlay-panel {
      position: absolute;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      padding: 0 42px;
      text-align: center;
      top: 0;
      height: 100%;
      width: 50%;
      transition: transform 0.6s cubic-bezier(0.65, 0, 0.35, 1);
      z-index: 2;
    }

    .auth-slider-container .overlay-left {
      transform: translateX(-20%);
    }

    .auth-slider-container.active .overlay-left {
      transform: translateX(0);
    }

    .auth-slider-container .overlay-right {
      right: 0;
      transform: translateX(0);
    }

    .auth-slider-container.active .overlay-right {
      transform: translateX(20%);
    }

    .overlay-pin-badge {
      width: 52px;
      height: 52px;
      border-radius: 50% 50% 50% 6px;
      background: linear-gradient(135deg, var(--primary) 0%, #f43f5e 100%);
      color: #ffffff;
      display: grid;
      place-items: center;
      font-family: var(--font-heading);
      font-weight: 800;
      font-size: 20px;
      margin-bottom: 18px;
      box-shadow: 0 10px 26px rgba(214, 36, 92, 0.42);
      transform: rotate(-12deg);
      animation: floatPinBadge 3.6s ease-in-out infinite !important;
    }

    @keyframes floatPinBadge {
      0%, 100% {
        transform: translateY(0px) rotate(-12deg);
      }
      50% {
        transform: translateY(-8px) rotate(-7deg);
      }
    }

    .overlay-pin-badge span {
      transform: rotate(12deg);
    }

    .overlay-panel h2 {
      font-family: var(--font-heading);
      color: #ffffff;
      font-size: 26px;
      font-weight: 800;
      margin: 0 0 10px 0;
      letter-spacing: -0.02em;
    }

    .overlay-panel p {
      font-size: 14px;
      line-height: 1.6;
      color: rgba(255, 255, 255, 0.84);
      margin: 0 0 26px 0;
      max-width: 310px;
    }

    .ghost-btn {
      background-color: transparent;
      border: 1.5px solid rgba(255, 255, 255, 0.85);
      border-radius: 9999px;
      color: #ffffff;
      padding: 11px 36px;
      cursor: pointer;
      font-family: var(--font-heading);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.4px;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .ghost-btn:hover {
      background-color: rgba(255, 255, 255, 0.22) !important;
      transform: translateY(-2px) scale(1.03) !important;
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28) !important;
    }

    .ghost-btn:active {
      transform: scale(0.96) !important;
    }

    .mobile-auth-switch {
      display: none;
      margin-top: 14px;
      text-align: center;
      font-size: 13.5px;
      color: var(--muted);
    }

    .mobile-auth-switch button {
      background: none;
      border: none;
      color: var(--primary);
      font-weight: 700;
      padding: 0 4px;
      cursor: pointer;
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .auth-slider-container {
        min-height: 580px;
        border-radius: 20px;
      }

      .auth-slider-container .overlay-container {
        display: none;
      }

      .auth-slider-container .sign-in-container,
      .auth-slider-container .sign-up-container {
        width: 100%;
      }

      .auth-slider-container.active .sign-in-container {
        transform: translateX(-100%);
      }

      .auth-slider-container.active .sign-up-container {
        transform: translateX(0);
      }

      .auth-form-inner {
        padding: 28px 24px;
      }

      .auth-two-col {
        grid-template-columns: 1fr;
        gap: 0;
      }

      .mobile-auth-switch {
        display: block;
      }
    }
  </style>
</head>
<body class="auth-slider-body">
  <a class="skip-link" href="#login-form">Bỏ qua giới thiệu</a>

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

  <div class="auth-slider-container<?= ($initialMode ?? "login") === "register" ? " active" : "" ?>" id="container">
    <!-- SIGN UP FORM -->
    <div class="form-container sign-up-container">
      <div class="auth-form-inner">
        <div class="auth-brand-pill">
          <span class="login-mark" aria-hidden="true">D</span>
          <strong>DesignMark</strong>
        </div>

        <div id="register-success-card" class="registration-success-card" style="display: none; text-align: center; padding: 24px 16px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; margin-top: 8px;">
          <div style="font-weight: 700; color: #10B981; font-size: 0.95rem; letter-spacing: 0.5px; margin-bottom: 6px;">HOÀN TẤT</div>
          <h3 style="margin: 0 0 8px 0; color: var(--heading); font-size: 1.2rem;">Tài khoản đã sẵn sàng!</h3>
          <p id="success-message" style="color: var(--muted); font-size: 0.9rem; line-height: 1.55; margin-bottom: 18px;">
            Tài khoản của bạn đã được tạo thành công.<br>
            Bạn có thể vào bảng điều khiển để bắt đầu ngay.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="dashboard.php" class="btn btn-primary" style="text-decoration: none; padding: 10px 22px;">Vào bảng điều khiển</a>
            <button type="button" id="success-to-signin" class="btn btn-secondary" style="padding: 10px 18px;">Đăng nhập</button>
          </div>
        </div>

        <form id="register-form" novalidate>
          <h1 class="auth-form-title">Đăng ký tài khoản</h1>
          <p class="auth-form-lede">Điền thông tin cơ bản để mở không gian làm việc mới cho bạn.</p>

          <div class="auth-two-col">
            <div class="dm-field">
              <label class="dm-field-label" for="reg-name">Họ và tên</label>
              <input class="dm-input" id="reg-name" name="name" placeholder="Nguyễn Minh Anh" required autocomplete="name">
            </div>
            <div class="dm-field">
              <label class="dm-field-label" for="reg-username">Tên đăng nhập</label>
              <input class="dm-input" id="reg-username" name="username" autocomplete="username" placeholder="minhanh_ui" required>
            </div>
          </div>

          <div class="dm-field">
            <label class="dm-field-label" for="reg-email">Email công việc</label>
            <input class="dm-input" id="reg-email" name="email" type="email" autocomplete="email" placeholder="minhanh@studio.vn" required>
          </div>

          <div class="dm-field">
            <label class="dm-field-label" for="reg-password">Mật khẩu</label>
            <div class="auth-pw-wrap">
              <input class="dm-input" id="reg-password" name="password" type="password" autocomplete="new-password" placeholder="Ít nhất 4 ký tự" required style="padding-right: 52px;">
              <button type="button" id="toggle-reg-password" class="auth-pw-toggle" aria-label="Hiện hoặc ẩn mật khẩu">Hiện</button>
            </div>
          </div>

          <div class="dm-field">
            <label class="dm-field-label" for="reg-confirm">Xác nhận mật khẩu</label>
            <div class="auth-pw-wrap">
              <input class="dm-input" id="reg-confirm" name="confirm" type="password" autocomplete="new-password" placeholder="Nhập lại mật khẩu" required style="padding-right: 52px;">
              <button type="button" id="toggle-reg-confirm" class="auth-pw-toggle" aria-label="Hiện hoặc ẩn xác nhận mật khẩu">Hiện</button>
            </div>
            <div id="password-match-hint" class="password-match-hint" hidden></div>
          </div>

          <p id="register-error" class="field-error" role="alert" style="margin: 4px 0;"></p>
          <button id="register-button" class="auth-main-btn" type="submit">Hoàn tất đăng ký</button>

          <p class="mobile-auth-switch">
            Đã có tài khoản? <button type="button" id="mobile-to-signin">Đăng nhập</button>
          </p>
        </form>
      </div>
    </div>

    <!-- SIGN IN FORM -->
    <div class="form-container sign-in-container">
      <div class="auth-form-inner">
        <div class="auth-brand-pill">
          <span class="login-mark" aria-hidden="true">D</span>
          <strong>DesignMark</strong>
        </div>

        <form id="login-form" data-testid="login-form" novalidate>
          <h1 class="auth-form-title">Đăng nhập DesignMark</h1>
          <p class="auth-form-lede">Tiếp tục rà soát giao diện, trao đổi góp ý và theo dõi tiến độ dự án.</p>

          <div class="dm-field">
            <label class="dm-field-label" for="username">Tên đăng nhập hoặc Email</label>
            <input class="dm-input" id="username" name="username" autocomplete="username" placeholder="Nhập tên đăng nhập hoặc email" required>
          </div>

          <div class="dm-field">
            <label class="dm-field-label" for="password">Mật khẩu</label>
            <div class="auth-pw-wrap">
              <input class="dm-input" id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required style="padding-right: 52px;">
              <button type="button" id="toggle-password" class="auth-pw-toggle" aria-label="Hiện hoặc ẩn mật khẩu">Hiện</button>
            </div>
          </div>

          <label class="checkline" for="remember" style="margin: 6px 0 8px;">
            <input id="remember" type="checkbox" checked>
            Duy trì đăng nhập trên thiết bị này
          </label>

          <p id="login-error" class="field-error" role="alert" style="margin: 4px 0;"></p>
          <button id="login-button" data-testid="login-button" class="auth-main-btn" type="submit">Đăng nhập ngay</button>

          <div class="account-pills" aria-label="Tài khoản mẫu" style="display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0 6px; justify-content: center;">
            <button type="button" class="btn btn-secondary btn-sm" data-user="admin" data-pass="demo" style="font-size: 11.5px; padding: 4px 10px; border-radius: 999px;">Admin</button>
            <button type="button" class="btn btn-secondary btn-sm" data-user="an" data-pass="demo" style="font-size: 11.5px; padding: 4px 10px; border-radius: 999px;">An (Quản lý)</button>
            <button type="button" class="btn btn-secondary btn-sm" data-user="binh" data-pass="demo" style="font-size: 11.5px; padding: 4px 10px; border-radius: 999px;">Bình (Góp ý)</button>
            <button type="button" class="btn btn-secondary btn-sm" data-user="chi" data-pass="demo" style="font-size: 11.5px; padding: 4px 10px; border-radius: 999px;">Chi (Xem)</button>
          </div>

          <p class="mobile-auth-switch">
            Chưa có tài khoản? <button type="button" id="mobile-to-signup">Tạo tài khoản</button>
          </p>
        </form>
      </div>
    </div>

    <!-- SLIDING OVERLAY -->
    <div class="overlay-container">
      <div class="overlay">
        <div class="overlay-panel overlay-left">
          <div class="overlay-pin-badge" aria-hidden="true"><span>D</span></div>
          <h2>Đã là thành viên?</h2>
          <p>Đăng nhập để mở lại các bản thiết kế đang chờ duyệt và xem những bình luận mới nhất từ nhóm.</p>
          <button type="button" class="ghost-btn" id="signIn">Quay lại đăng nhập</button>
        </div>

        <div class="overlay-panel overlay-right">
          <div class="overlay-pin-badge" aria-hidden="true"><span>D</span></div>
          <h2>Chưa có tài khoản?</h2>
          <p>Tham gia cùng đội ngũ thiết kế và phát triển để chấm điểm, nhận xét và duyệt phiên bản nhanh hơn.</p>
          <button type="button" class="ghost-btn" id="signUp">Tạo tài khoản</button>
        </div>
      </div>
    </div>
  </div>

  <script type="module">
    import { clearSession, getSession, login, redirectAfterLogin, registerAccount } from './js/auth.js';
    import { toast } from './js/ui.js';

    // Theme toggle
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
        try { localStorage.setItem('dm_theme', next); } catch (e) {}
        updateAria();
        toast(
          next === 'dark' ? 'Đã bật giao diện tối' : 'Đã bật giao diện sáng',
          'info',
          { key: 'theme', icon: next === 'dark' ? 'moon' : 'sun', duration: 2200 }
        );
      });
    }

    // Session sync
    if (!window.DESIGNMARK_SESSION) {
      clearSession();
    } else if (getSession()) {
      redirectAfterLogin();
    }

    // Sliding panel animation controls
    const container = document.getElementById('container');
    const signUpBtn = document.getElementById('signUp');
    const signInBtn = document.getElementById('signIn');
    const mobileToSignup = document.getElementById('mobile-to-signup');
    const mobileToSignin = document.getElementById('mobile-to-signin');
    const successToSignin = document.getElementById('success-to-signin');

    function switchToSignUp() {
      container.classList.add('active');
      document.title = 'Đăng ký tài khoản — DesignMark';
      try { history.replaceState(null, '', 'register.php'); } catch (e) {}
    }

    function switchToSignIn() {
      container.classList.remove('active');
      document.title = 'Đăng nhập — DesignMark';
      try { history.replaceState(null, '', 'index.php'); } catch (e) {}
    }

    if (signUpBtn) signUpBtn.addEventListener('click', switchToSignUp);
    if (signInBtn) signInBtn.addEventListener('click', switchToSignIn);
    if (mobileToSignup) mobileToSignup.addEventListener('click', switchToSignUp);
    if (mobileToSignin) mobileToSignin.addEventListener('click', switchToSignIn);
    if (successToSignin) successToSignin.addEventListener('click', switchToSignIn);

    // Login logic
    const loginForm = document.getElementById('login-form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginError = document.getElementById('login-error');
    const togglePassBtn = document.getElementById('toggle-password');

    if (togglePassBtn) {
      togglePassBtn.addEventListener('click', () => {
        const isPass = passwordInput.type === 'password';
        passwordInput.type = isPass ? 'text' : 'password';
        togglePassBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
      });
    }

    document.querySelectorAll('.account-pills button').forEach((btn) => {
      btn.addEventListener('click', () => {
        usernameInput.value = btn.dataset.user;
        passwordInput.value = btn.dataset.pass;
        loginError.textContent = '';
      });
    });

    loginForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      loginError.textContent = '';
      const button = document.getElementById('login-button');
      button.disabled = true;
      try {
        const user = await login(usernameInput.value, passwordInput.value, document.getElementById('remember').checked);
        redirectAfterLogin(user);
      } catch (err) {
        if (err.code === 'ACCOUNT_LOCKED') {
          loginError.innerHTML = \`<div class="locked-account-box" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 10px 12px; color: #b91c1c; font-size: 0.85rem; line-height: 1.45; text-align: left; margin-top: 6px;">
            <strong style="display: block; margin-bottom: 3px; font-weight: 600;">Tài khoản đã bị khóa</strong>
            \${err.message || 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Quản trị viên để được hỗ trợ.'}
          </div>\`;
        } else {
          loginError.textContent = err.message || 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        }
        button.disabled = false;
        (err.message && err.message.includes('mật khẩu') ? passwordInput : usernameInput).focus();
      }
    });

    // Register logic
    const regForm = document.getElementById('register-form');
    const successCard = document.getElementById('register-success-card');
    const successMsg = document.getElementById('success-message');
    const regUsernameInput = document.getElementById('reg-username');
    const regNameInput = document.getElementById('reg-name');
    const regEmailInput = document.getElementById('reg-email');
    const regPasswordInput = document.getElementById('reg-password');
    const regConfirmInput = document.getElementById('reg-confirm');
    const toggleRegPassBtn = document.getElementById('toggle-reg-password');
    const toggleRegConfirmBtn = document.getElementById('toggle-reg-confirm');
    const matchHint = document.getElementById('password-match-hint');
    const regError = document.getElementById('register-error');
    const regSubmitBtn = document.getElementById('register-button');

    const RESERVED_USERNAMES = ['admin', 'administrator', 'system', 'root', 'support', 'help', 'api', 'guest'];

    if (toggleRegPassBtn && regPasswordInput) {
      toggleRegPassBtn.addEventListener('click', () => {
        const isPass = regPasswordInput.type === 'password';
        regPasswordInput.type = isPass ? 'text' : 'password';
        toggleRegPassBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
      });
    }

    if (toggleRegConfirmBtn && regConfirmInput) {
      toggleRegConfirmBtn.addEventListener('click', () => {
        const isPass = regConfirmInput.type === 'password';
        regConfirmInput.type = isPass ? 'text' : 'password';
        toggleRegConfirmBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
      });
    }

    function checkPasswordMatch() {
      const pass = regPasswordInput.value;
      const conf = regConfirmInput.value;
      if (!conf) {
        matchHint.hidden = true;
        matchHint.textContent = '';
        return;
      }
      matchHint.hidden = false;
      if (pass === conf) {
        matchHint.className = 'password-match-hint is-matched';
        matchHint.textContent = 'Mật khẩu trùng khớp';
      } else {
        matchHint.className = 'password-match-hint is-mismatched';
        matchHint.textContent = 'Mật khẩu xác nhận chưa khớp';
      }
    }

    regPasswordInput.addEventListener('input', checkPasswordMatch);
    regConfirmInput.addEventListener('input', checkPasswordMatch);

    regForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      regError.textContent = '';

      const username = regUsernameInput.value.trim();
      const name = regNameInput.value.trim();
      const email = regEmailInput ? regEmailInput.value.trim() : '';
      const password = regPasswordInput.value;
      const confirm = regConfirmInput.value;

      if (!name) {
        regError.textContent = 'Vui lòng nhập họ và tên.';
        regNameInput.focus();
        return;
      }
      if (!username) {
        regError.textContent = 'Vui lòng nhập tên đăng nhập.';
        regUsernameInput.focus();
        return;
      }
      if (!/^[a-zA-Z0-9_\\-.]{3,30}$/.test(username)) {
        regError.textContent = 'Tên đăng nhập từ 3 đến 30 ký tự, chỉ gồm chữ cái, số, gạch dưới hoặc gạch ngang.';
        regUsernameInput.focus();
        return;
      }
      if (RESERVED_USERNAMES.includes(username.toLowerCase())) {
        regError.textContent = 'Tên đăng nhập này thuộc danh sách dành riêng cho hệ thống. Vui lòng chọn tên khác.';
        regUsernameInput.focus();
        return;
      }
      if (!email) {
        regError.textContent = 'Vui lòng nhập địa chỉ email.';
        regEmailInput.focus();
        return;
      }
      if (!/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(email)) {
        regError.textContent = 'Địa chỉ email không đúng định dạng (ví dụ: user@example.com).';
        regEmailInput.focus();
        return;
      }
      if (password.length < 4) {
        regError.textContent = 'Mật khẩu phải có tối thiểu 4 ký tự.';
        regPasswordInput.focus();
        return;
      }
      if (password !== confirm) {
        regError.textContent = 'Mật khẩu xác nhận không khớp.';
        regConfirmInput.focus();
        return;
      }

      regSubmitBtn.disabled = true;
      regSubmitBtn.textContent = 'Đang khởi tạo tài khoản...';

      try {
        const data = await registerAccount({
          username,
          name,
          email,
          password,
          confirm_password: confirm
        });

        regForm.style.display = 'none';
        if (successMsg) {
          successMsg.innerHTML = \`
            Tài khoản <strong>\${data.username || username}</strong> (\${name}) đã được tạo thành công.<br>
            Bạn có thể vào bảng điều khiển để bắt đầu làm việc ngay.
          \`;
        }
        successCard.style.display = 'block';
        toast('Đăng ký tài khoản thành công!', 'success');
      } catch (err) {
        regError.textContent = err.message || 'Lỗi khi tạo tài khoản.';
        regSubmitBtn.disabled = false;
        regSubmitBtn.textContent = 'Hoàn tất đăng ký';
      }
    });
  </script>
</body>
</html>
