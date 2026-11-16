<?php
// verify.php - Admin Account Verification Page
require_once __DIR__ . '/api/db.php';

$presetUsername = $_GET['username'] ?? '';
$presetCode = $_GET['code'] ?? '';

$pageTitle = 'Xác minh tài khoản — DesignMark';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
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
        <h1>Xác minh quyền Quản trị viên (Admin).</h1>
        <p>Bảo mật hệ thống yêu cầu xác nhận danh tính trước khi cấp quyền tạo dự án, quản lý thành viên và phê duyệt phiên bản thiết kế.</p>
        <div class="hero-points">
          <div>Nhập mã xác minh OTP 6 số được cấp khi đăng ký.</div>
          <div>Hoặc sử dụng mã xác minh Quản trị viên cấp cao.</div>
          <div>Tài khoản sẽ được kích hoạt tức thì ngay sau khi xác nhận.</div>
        </div>
      </div>
      <p style="font-size: 13px; color: #626572; margin: 0;">Cơ chế xác minh an toàn bảo vệ quyền Quản trị trên hệ thống.</p>
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
        <a href="index.php" class="auth-back-btn" title="Quay lại trang đăng nhập">← Quay lại đăng nhập</a>
        <h2>Xác minh tài khoản Admin</h2>
        <p class="lede">Nhập mã xác minh để kích hoạt quyền Quản trị viên.</p>

        <div class="admin-badge-notice" style="margin-bottom: 20px; background: rgba(214, 36, 92, 0.08); border: 1px solid rgba(214, 36, 92, 0.25); border-radius: 8px; padding: 12px 14px;">
          <strong style="color: var(--primary); display: block; margin-bottom: 4px;">Hướng dẫn xác minh:</strong>
          <span style="display: block; font-size: 13px; color: var(--muted);">• Nhập mã OTP 6 số hoặc mã Quản trị: <span class="code-display" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">ADMIN2026</span></span>
        </div>

        <form id="verify-form" novalidate>
          <div class="field">
            <label class="field-label" for="verify-username">Tên đăng nhập Admin</label>
            <input class="field-input" id="verify-username" name="username" value="<?= htmlspecialchars($presetUsername, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nhập tên đăng nhập" required>
          </div>

          <div class="field">
            <label class="field-label">Mã xác minh OTP (6 chữ số)</label>
            <div class="otp-container" style="display: flex; gap: 8px; justify-content: space-between; margin: 6px 0 12px 0;">
              <input type="text" maxlength="1" inputmode="numeric" class="field-input otp-digit" data-index="0" style="text-align: center; font-size: 20px; font-weight: 700; padding: 6px 0;" aria-label="Số thứ 1">
              <input type="text" maxlength="1" inputmode="numeric" class="field-input otp-digit" data-index="1" style="text-align: center; font-size: 20px; font-weight: 700; padding: 6px 0;" aria-label="Số thứ 2">
              <input type="text" maxlength="1" inputmode="numeric" class="field-input otp-digit" data-index="2" style="text-align: center; font-size: 20px; font-weight: 700; padding: 6px 0;" aria-label="Số thứ 3">
              <input type="text" maxlength="1" inputmode="numeric" class="field-input otp-digit" data-index="3" style="text-align: center; font-size: 20px; font-weight: 700; padding: 6px 0;" aria-label="Số thứ 4">
              <input type="text" maxlength="1" inputmode="numeric" class="field-input otp-digit" data-index="4" style="text-align: center; font-size: 20px; font-weight: 700; padding: 6px 0;" aria-label="Số thứ 5">
              <input type="text" maxlength="1" inputmode="numeric" class="field-input otp-digit" data-index="5" style="text-align: center; font-size: 20px; font-weight: 700; padding: 6px 0;" aria-label="Số thứ 6">
            </div>
            <input class="field-input" id="verify-code" name="code" value="<?= htmlspecialchars($presetCode, ENT_QUOTES, 'UTF-8') ?>" placeholder="Hoặc nhập mã ADMIN2026" style="font-family: monospace; font-size: 14px; letter-spacing: 1px;">
          </div>

          <p id="verify-error" class="field-error" role="alert"></p>
          <button id="verify-button" class="btn btn-primary" type="submit" style="width: 100%;">Kích hoạt tài khoản Admin</button>
        </form>

        <p class="auth-switch-link">
          <a href="register.php">Tạo tài khoản khác</a>
        </p>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script type="module">
    import { setSession } from './js/auth.js';
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

    const form = document.getElementById('verify-form');
    const usernameInput = document.getElementById('verify-username');
    const codeInput = document.getElementById('verify-code');
    const errorEl = document.getElementById('verify-error');
    const submitBtn = document.getElementById('verify-button');

    const otpDigits = document.querySelectorAll('.otp-digit');

    // Auto-advance and backspace handling for OTP digits
    otpDigits.forEach((digit, idx) => {
      digit.addEventListener('input', (e) => {
        const val = e.target.value.replace(/\D/g, '');
        digit.value = val.slice(-1);
        if (digit.value && idx < otpDigits.length - 1) {
          otpDigits[idx + 1].focus();
        }
        syncOtpToCode();
      });

      digit.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !digit.value && idx > 0) {
          otpDigits[idx - 1].focus();
        }
      });

      digit.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').trim().replace(/\D/g, '');
        if (pasted.length >= otpDigits.length) {
          otpDigits.forEach((d, i) => { d.value = pasted[i] || ''; });
          otpDigits[otpDigits.length - 1].focus();
          syncOtpToCode();
        }
      });
    });

    function syncOtpToCode() {
      const full = Array.from(otpDigits).map((d) => d.value).join('');
      if (full.length === otpDigits.length) {
        codeInput.value = full;
      }
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorEl.textContent = '';

      const username = usernameInput.value.trim();
      const code = codeInput.value.trim();

      if (!username) {
        errorEl.textContent = 'Vui lòng nhập tên đăng nhập.';
        usernameInput.focus();
        return;
      }
      if (!code) {
        errorEl.textContent = 'Vui lòng nhập mã xác minh.';
        codeInput.focus();
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Đang xác minh...';

      try {
        const res = await fetch('api/auth.php?action=verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, code })
        });

        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.error || 'Xác minh không thành công.');
        }

        setSession({
          userId: data.userId,
          username: data.username,
          name: data.name,
          role: data.role,
          title: data.title || 'Quản lý thiết kế',
          color: data.color || '#4F46E5',
          remember: true
        }, true);

        toast('Xác minh tài khoản Admin thành công! Đang chuyển hướng...', 'success');
        setTimeout(() => {
          location.href = 'projects.php';
        }, 800);
      } catch (err) {
        errorEl.textContent = err.message || 'Lỗi xác minh tài khoản.';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Kích hoạt tài khoản Admin';
      }
    });
  </script>
</body>
</html>

