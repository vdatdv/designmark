/**
 * Quản lý tài khoản hệ thống dành cho Quản trị viên (Admin) - DesignMark.
 * Phân quyền hai cấp:
 *   1. Quyền hệ thống: Admin / User.
 *   2. Vai trò theo dự án: Owner, Designer, Reviewer, Viewer (lưu theo quan hệ dự án, không lưu chức danh cố định).
 * Hỗ trợ: Thống kê live, lọc trạng thái 3 tab, tìm kiếm debounce (name, username, email),
 * lọc quyền hệ thống, lọc phạm vi dự án, lọc vai trò dự án, sắp xếp, phân trang server-side,
 * menu thao tác ⋯, modal chi tiết người dùng, modal thêm tài khoản, đổi mật khẩu an toàn và lịch sử hoạt động.
 */
import { boot } from './app.js';
import { api } from './api.js';
import {
  avatarEl,
  bindTabs,
  confirmDialog,
  el,
  emptyState,
  formatDay,
  icon,
  openModal,
  renderPagination,
  setBusy,
  toast
} from './ui.js';

// Random password generator helper
function generateRandomPassword(length = 10) {
  const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%&*';
  let pass = '';
  for (let i = 0; i < length; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pass;
}

// Stat Card component
function statCard(label, value, isAlert = false, color = '') {
  return el('article', {
    class: 'stat-card',
    style: isAlert ? 'border-color: #ef4444; background: rgba(239, 68, 68, 0.05);' : ''
  }, [
    el('span', {
      text: label,
      style: color ? `color: ${color}; font-weight: 600;` : ''
    }),
    el('strong', {
      text: String(value),
      style: color ? `color: ${color};` : ''
    })
  ]);
}

// 1. MODAL: XEM CHI TIẾT NGƯỜI DÙNG (Kèm danh sách dự án và vai trò theo dự án)
async function openUserDetailModal(user) {
  const loadingEl = el('div', { style: 'padding: 36px; text-align: center; color: var(--muted);' }, [
    el('span', { text: 'Đang tải thông tin chi tiết người dùng...' })
  ]);
  const container = el('div', {}, [loadingEl]);

  const modal = openModal({
    title: `Chi tiết tài khoản — ${user.name} (@${user.username})`,
    content: container,
    size: 'wide'
  });

  try {
    const detail = await api.getUserDetail(user.id);
    const u = detail.user;
    const stats = detail.stats || { ownedCount: 0, joinedCount: 0, totalAccessible: 0 };
    const projects = detail.projects || [];

    const isSystemAdmin = (u.systemRole === 'admin' || u.username === 'admin');
    const isLocked = u.status === 'locked';

    // Status pill
    const statusPill = isLocked
      ? el('span', {
          class: 'status-pill',
          style: 'background: rgba(239, 68, 68, 0.12); color: #dc2626;'
        }, [
          el('span', { class: 'status-pill-dot', style: 'background: #dc2626;' }),
          el('span', { text: 'Đã khóa' })
        ])
      : el('span', { class: 'status-pill is-active' }, [
          el('span', { class: 'status-pill-dot' }),
          el('span', { text: 'Đang hoạt động' })
        ]);

    // Role badge
    const roleBadge = isSystemAdmin
      ? el('span', { class: 'badge badge-role role-manager', text: 'Quản trị viên (Admin)' })
      : el('span', { class: 'badge badge-role role-viewer', text: 'Người dùng (User)' });

    // Personal info header
    const profileHeader = el('div', { class: 'user-detail-profile' }, [
      avatarEl(u, 54),
      el('div', { class: 'user-detail-meta' }, [
        el('h3', {}, [
          el('span', { text: u.name }),
          roleBadge,
          statusPill
        ]),
        el('p', { text: `@${u.username} • Email: ${u.email || 'Chưa cập nhật'}` }),
        el('p', { class: 'help', text: `Ngày đăng ký: ${formatDay(u.createdAt)}` })
      ])
    ]);

    // Project stats cards
    const statsGrid = el('div', { class: 'user-detail-stats' }, [
      el('div', { class: 'user-detail-stat-card' }, [
        el('span', { text: 'Dự án sở hữu (Owner)' }),
        el('strong', { text: String(stats.ownedCount), style: 'color: #6366f1;' })
      ]),
      el('div', { class: 'user-detail-stat-card' }, [
        el('span', { text: 'Dự án được mời' }),
        el('strong', { text: String(stats.joinedCount), style: 'color: #0284c7;' })
      ]),
      el('div', { class: 'user-detail-stat-card' }, [
        el('span', { text: 'Tổng dự án có quyền' }),
        el('strong', { text: String(stats.totalAccessible), style: 'color: var(--heading);' })
      ])
    ]);

    // Projects list table
    let projectsContent;
    if (!projects.length) {
      projectsContent = emptyState({
        title: 'Chưa tham gia dự án nào',
        text: 'Người dùng này hiện chưa tạo hoặc được mời vào dự án nào.'
      });
    } else {
      const pRows = projects.map((p) => {
        let roleBadgeClass = 'project-role-viewer';
        let roleText = 'Viewer (Chỉ xem)';

        if (p.role === 'owner') {
          roleBadgeClass = 'project-role-owner';
          roleText = 'Owner (Chủ dự án)';
        } else if (p.role === 'designer') {
          roleBadgeClass = 'project-role-designer';
          roleText = 'Designer (Thiết kế)';
        } else if (p.role === 'reviewer') {
          roleBadgeClass = 'project-role-reviewer';
          roleText = 'Reviewer (Góp ý)';
        }

        const pStatusPill = p.status === 'trashed'
          ? el('span', { class: 'badge badge-trashed', text: 'Thùng rác' })
          : el('span', { class: 'badge badge-active', text: 'Đang hoạt động' });

        return el('tr', { class: 'user-detail-prow' }, [
          el('td', { class: 'user-detail-pcol-name' }, [
            el('div', { class: 'user-detail-pname', text: p.name }),
            el('div', { class: 'user-detail-pcat', text: p.category || 'Khác' })
          ]),
          el('td', { class: 'user-detail-pcol-role' }, [
            el('span', { class: `project-role-badge ${roleBadgeClass}`, text: roleText })
          ]),
          el('td', { class: 'user-detail-pcol-date' }, [
            el('span', { class: 'user-detail-pdate', text: formatDay(p.joinedAt) })
          ]),
          el('td', { class: 'user-detail-pcol-status' }, [pStatusPill])
        ]);
      });

      const pTable = el('table', { class: 'data-table user-detail-projects-table' }, [
        el('thead', {}, [
          el('tr', {}, [
            el('th', { text: 'TÊN DỰ ÁN', style: 'min-width: 260px;' }),
            el('th', { text: 'VAI TRÒ TRONG DỰ ÁN', style: 'min-width: 190px;' }),
            el('th', { text: 'NGÀY THAM GIA', style: 'min-width: 140px;' }),
            el('th', { text: 'TRẠNG THÁI', style: 'min-width: 130px;' })
          ])
        ]),
        el('tbody', {}, pRows)
      ]);

      projectsContent = el('div', { class: 'table-wrap user-detail-projects-wrap' }, [pTable]);
    }

    container.replaceChildren(
      profileHeader,
      statsGrid,
      el('h4', { text: 'Danh sách dự án tham gia', style: 'margin: 0 0 10px 0; font-size: 14px; color: var(--heading);' }),
      projectsContent,
      el('div', { class: 'modal-actions', style: 'margin-top: 20px;' }, [
        el('button', { type: 'button', class: 'btn btn-secondary', text: 'Đóng', onclick: () => modal.close() })
      ])
    );
  } catch (err) {
    container.replaceChildren(
      el('p', { class: 'field-error', text: err.message || 'Không thể tải thông tin chi tiết người dùng.' })
    );
  }
}

// 2. MODAL: TẠO TÀI KHOẢN MỚI (Admin only)
function openCreateUserModal(onSuccess) {
  const nameInput = el('input', { class: 'field-input', id: 'create-name', placeholder: 'Ví dụ: Nguyễn Văn An', required: 'true' });
  const usernameInput = el('input', { class: 'field-input', id: 'create-username', placeholder: 'Ví dụ: an_nguyen', required: 'true' });
  const emailInput = el('input', { class: 'field-input', id: 'create-email', type: 'email', placeholder: 'Ví dụ: an.nguyen@company.com' });

  const systemRoleSelect = el('select', { class: 'field-input', id: 'create-system-role' }, [
    el('option', { value: 'user', text: 'Người dùng (User) — Mặc định' }),
    el('option', { value: 'admin', text: 'Quản trị viên (Admin)' })
  ]);

  const passwordInput = el('input', {
    class: 'field-input',
    id: 'create-password',
    type: 'password',
    placeholder: 'Tối thiểu 4 ký tự',
    required: 'true'
  });

  const togglePassBtn = el('button', {
    type: 'button',
    class: 'btn btn-secondary btn-sm',
    title: 'Hiện/Ẩn mật khẩu',
    onclick: () => {
      const isPass = passwordInput.type === 'password';
      passwordInput.type = isPass ? 'text' : 'password';
      togglePassBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
    }
  }, ['Hiện']);

  const generatePassBtn = el('button', {
    type: 'button',
    class: 'btn btn-ghost btn-sm',
    title: 'Tự động sinh mật khẩu ngẫu nhiên an toàn',
    onclick: () => {
      const randomPass = generateRandomPassword(10);
      passwordInput.value = randomPass;
      passwordInput.type = 'text';
      togglePassBtn.textContent = 'Ẩn';
      toast(`Đã sinh mật khẩu: ${randomPass}`, 'info');
    }
  }, [icon('key'), el('span', { text: 'Tạo ngẫu nhiên' })]);

  const errBox = el('p', { class: 'field-error', id: 'create-error' });
  const submitBtn = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Tạo tài khoản' });

  const form = el('form', {}, [
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'create-name', text: 'Họ và tên *' }),
      nameInput
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'create-username', text: 'Tên đăng nhập *' }),
      usernameInput,
      el('p', { class: 'help', text: '3 đến 30 ký tự (chữ cái, số, gạch dưới hoặc gạch ngang).' })
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'create-email', text: 'Địa chỉ Email *' }),
      emailInput,
      el('p', { class: 'help', text: 'Dùng để nhận thông báo và tìm kiếm khi thêm vào dự án.' })
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'create-system-role', text: 'Quyền hệ thống' }),
      systemRoleSelect,
      el('p', { class: 'help', text: 'Mặc định là User. Người dùng có thể tự tạo dự án hoặc được mời vào các dự án khác.' })
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'create-password', text: 'Mật khẩu khởi tạo *' }),
      el('div', { class: 'field-with-btn' }, [
        passwordInput,
        togglePassBtn,
        generatePassBtn
      ])
    ]),
    errBox,
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submitBtn
    ])
  ]);

  const modal = openModal({ title: 'Thêm tài khoản người dùng', content: form });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.textContent = '';
    const uname = usernameInput.value.trim();
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const pass = passwordInput.value;

    if (!name) {
      errBox.textContent = 'Vui lòng nhập họ và tên.';
      nameInput.focus();
      return;
    }
    if (!uname) {
      errBox.textContent = 'Vui lòng nhập tên đăng nhập.';
      usernameInput.focus();
      return;
    }
    if (!/^[a-zA-Z0-9_\-\.]{3,30}$/.test(uname)) {
      errBox.textContent = 'Tên đăng nhập từ 3 đến 30 ký tự, chỉ gồm chữ cái, số, gạch dưới hoặc gạch ngang.';
      usernameInput.focus();
      return;
    }
    if (['admin', 'administrator', 'system', 'root', 'support', 'help'].includes(uname.toLowerCase())) {
      errBox.textContent = 'Tên đăng nhập này dành riêng cho hệ thống.';
      usernameInput.focus();
      return;
    }
    if (!email) {
      errBox.textContent = 'Vui lòng nhập địa chỉ email.';
      emailInput.focus();
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errBox.textContent = 'Địa chỉ email không đúng định dạng (ví dụ: user@example.com).';
      emailInput.focus();
      return;
    }
    if (!pass || pass.length < 4) {
      errBox.textContent = 'Mật khẩu phải từ 4 ký tự trở lên.';
      passwordInput.focus();
      return;
    }

    setBusy(submitBtn, true, 'Đang tạo...');
    try {
      const res = await api.adminCreateUser({
        username: uname,
        name: name,
        email: email || undefined,
        systemRole: systemRoleSelect.value,
        password: pass
      });
      modal.close();
      toast(res.message || 'Đã tạo tài khoản thành công! Tài khoản có thể đăng nhập ngay.', 'success');
      onSuccess();
    } catch (err) {
      setBusy(submitBtn, false);
      errBox.textContent = err.message || 'Không tạo được tài khoản.';
    }
  });
}

// 3. MODAL: CHỈNH SỬA THÔNG TIN TÀI KHOẢN (Họ tên, email, quyền hệ thống)
function openEditUserModal(user, onSuccess) {
  const nameInput = el('input', {
    class: 'field-input',
    id: 'edit-name',
    value: user.name || '',
    required: 'true',
    placeholder: 'Ví dụ: Hoàng Nguyễn'
  });

  const emailInput = el('input', {
    class: 'field-input',
    id: 'edit-email',
    type: 'email',
    value: user.email || '',
    placeholder: 'Ví dụ: hoang@company.com'
  });

  const systemRoleSelect = el('select', { class: 'field-input', id: 'edit-system-role' }, [
    el('option', { value: 'user', text: 'Người dùng (User)', selected: user.systemRole !== 'admin' }),
    el('option', { value: 'admin', text: 'Quản trị viên (Admin)', selected: user.systemRole === 'admin' })
  ]);

  const errBox = el('p', { class: 'field-error', id: 'edit-error' });
  const submitBtn = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Lưu thay đổi' });

  const form = el('form', {}, [
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', text: 'Tên đăng nhập' }),
      el('input', { class: 'field-input', value: `@${user.username}`, disabled: true, style: 'background: var(--bg); opacity: 0.8;' })
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'edit-name', text: 'Họ và tên *' }),
      nameInput
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'edit-email', text: 'Địa chỉ Email' }),
      emailInput
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'edit-system-role', text: 'Quyền hệ thống' }),
      systemRoleSelect,
      el('p', { class: 'help', text: 'Admin có quyền quản trị người dùng. User có quyền tạo và tham gia dự án theo vai trò cụ thể.' })
    ]),
    errBox,
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submitBtn
    ])
  ]);

  const modal = openModal({ title: `Chỉnh sửa tài khoản — @${user.username}`, content: form });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.textContent = '';
    const newName = nameInput.value.trim();
    const newEmail = emailInput.value.trim();

    if (!newName) {
      errBox.textContent = 'Họ và tên không được để trống.';
      nameInput.focus();
      return;
    }
    if (newEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
      errBox.textContent = 'Địa chỉ email không đúng định dạng.';
      emailInput.focus();
      return;
    }

    setBusy(submitBtn, true, 'Đang lưu...');
    try {
      const res = await api.updateUser(user.id, {
        name: newName,
        email: newEmail,
        systemRole: systemRoleSelect.value
      });
      modal.close();
      toast(res.message || 'Đã cập nhật thông tin tài khoản.', 'success');
      onSuccess();
    } catch (err) {
      setBusy(submitBtn, false);
      errBox.textContent = err.message || 'Không thể cập nhật tài khoản.';
    }
  });
}

// 4. MODAL: ĐỔI MẬT KHẨU TÀI KHOẢN (Admin only)
function openResetPasswordModal(user) {
  const newPass = el('input', {
    class: 'field-input',
    id: 'reset-new-pass',
    type: 'password',
    placeholder: 'Tối thiểu 4 ký tự',
    required: 'true'
  });

  const toggleBtn = el('button', {
    type: 'button',
    class: 'btn btn-secondary btn-sm',
    title: 'Hiện/Ẩn mật khẩu',
    onclick: () => {
      const isPass = newPass.type === 'password';
      newPass.type = isPass ? 'text' : 'password';
      toggleBtn.textContent = isPass ? 'Ẩn' : 'Hiện';
    }
  }, ['Hiện']);

  const generateBtn = el('button', {
    type: 'button',
    class: 'btn btn-ghost btn-sm',
    title: 'Tự sinh mật khẩu ngẫu nhiên',
    onclick: () => {
      const p = generateRandomPassword(10);
      newPass.value = p;
      newPass.type = 'text';
      toggleBtn.textContent = 'Ẩn';
      toast(`Mật khẩu mới: ${p}`, 'info');
    }
  }, [icon('key'), el('span', { text: 'Tạo ngẫu nhiên' })]);

  const errBox = el('p', { class: 'field-error' });
  const submitBtn = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Lưu mật khẩu mới' });

  const form = el('form', {}, [
    el('p', { class: 'help', text: `Thiết lập mật khẩu mới cho tài khoản ${user.name} (@${user.username}):` }),
    el('div', { class: 'field', style: 'margin-top: 12px;' }, [
      el('label', { class: 'field-label', for: 'reset-new-pass', text: 'Mật khẩu mới *' }),
      el('div', { class: 'field-with-btn' }, [
        newPass,
        toggleBtn,
        generateBtn
      ])
    ]),
    errBox,
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submitBtn
    ])
  ]);

  const modal = openModal({ title: 'Đổi mật khẩu người dùng', content: form });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.textContent = '';
    if (!newPass.value || newPass.value.length < 4) {
      errBox.textContent = 'Mật khẩu mới phải từ 4 ký tự trở lên.';
      newPass.focus();
      return;
    }
    setBusy(submitBtn, true, 'Đang lưu...');
    try {
      const res = await api.resetUserPassword(user.id, newPass.value);
      modal.close();
      toast(res.message || 'Đã đặt lại mật khẩu thành công.', 'success');
    } catch (err) {
      setBusy(submitBtn, false);
      errBox.textContent = err.message || 'Không đổi được mật khẩu.';
    }
  });
}

// 5. MODAL: KHÓA TÀI KHOẢN (Admin only)
function openLockUserModal(user, onSuccess) {
  const reasonInput = el('textarea', {
    class: 'field-input',
    rows: 3,
    placeholder: 'Nhập lý do khóa tài khoản (ví dụ: Vi phạm quy định, yêu cầu từ ban quản trị...)'
  });
  const errBox = el('p', { class: 'field-error' });
  const submitBtn = el('button', { type: 'submit', class: 'btn btn-danger', text: 'Khóa tài khoản' });

  const form = el('form', {}, [
    el('div', {
      style: 'background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; color: #b91c1c; font-size: 14px; line-height: 1.5;'
    }, [
      el('strong', { text: `Khóa tài khoản ${user.name} (@${user.username})`, style: 'display: block; margin-bottom: 4px;' }),
      el('span', { text: 'Tài khoản sau khi bị khóa sẽ không thể đăng nhập hoặc tiếp tục thao tác. Mọi phiên đăng nhập hiện có sẽ bị thu hồi và chấm dứt ngay lập tức.' })
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', text: 'Lý do khóa tài khoản' }),
      reasonInput
    ]),
    errBox,
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submitBtn
    ])
  ]);

  const modal = openModal({ title: 'Khóa tài khoản người dùng', content: form });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.textContent = '';
    const reason = reasonInput.value.trim() || 'Bị khóa bởi Quản trị viên';
    setBusy(submitBtn, true, 'Đang khóa...');
    try {
      const res = await api.lockUser(user.id, reason);
      modal.close();
      toast(res.message || `Đã khóa tài khoản ${user.name}.`, 'success');
      onSuccess();
    } catch (err) {
      setBusy(submitBtn, false);
      errBox.textContent = err.message || 'Không thể khóa tài khoản.';
    }
  });
}

// 6. MODAL: LỊCH SỬ TRẠNG THÁI & HOẠT ĐỘNG QUẢN TRỊ
async function openStatusHistoryModal(user) {
  const loadingEl = el('div', { style: 'padding: 24px; text-align: center; color: var(--muted);' }, [
    el('span', { text: 'Đang tải lịch sử hoạt động...' })
  ]);
  const container = el('div', {}, [loadingEl]);
  const modal = openModal({
    title: `Lịch sử quản trị — ${user.name} (@${user.username})`,
    content: container
  });

  try {
    const history = await api.getUserStatusHistory(user.id);
    if (!history || !history.length) {
      container.replaceChildren(
        emptyState({
          title: 'Chưa có lịch sử quản trị',
          text: 'Tài khoản này chưa từng có sự kiện Khóa, Mở khóa, Đổi quyền hay Đặt lại mật khẩu.'
        })
      );
      return;
    }

    const rows = history.map((h) => {
      let badgeStyle = 'background: rgba(16, 185, 129, 0.15); color: #059669;';
      let actionLabel = 'Mở khóa';

      if (h.action === 'lock') {
        badgeStyle = 'background: rgba(239, 68, 68, 0.15); color: #dc2626;';
        actionLabel = 'Khóa tài khoản';
      } else if (h.action === 'reset_password') {
        badgeStyle = 'background: rgba(245, 158, 11, 0.15); color: #d97706;';
        actionLabel = 'Đổi mật khẩu';
      } else if (h.action === 'grant_admin') {
        badgeStyle = 'background: rgba(99, 102, 241, 0.15); color: #4f46e5;';
        actionLabel = 'Cấp quyền Admin';
      } else if (h.action === 'revoke_admin') {
        badgeStyle = 'background: rgba(100, 116, 139, 0.15); color: #64748b;';
        actionLabel = 'Chuyển quyền User';
      } else if (h.action === 'create') {
        badgeStyle = 'background: rgba(16, 185, 129, 0.15); color: #059669;';
        actionLabel = 'Khởi tạo tài khoản';
      }

      const actionPill = el('span', {
        class: 'status-pill',
        style: badgeStyle
      }, [
        el('span', { text: actionLabel })
      ]);

      return el('tr', {}, [
        el('td', {}, [actionPill]),
        el('td', {}, [el('span', { text: h.reason || '—' })]),
        el('td', {}, [
          el('span', { text: h.performedByName ? `${h.performedByName} (@${h.performedByUsername})` : 'Quản trị viên' })
        ]),
        el('td', {}, [el('span', { class: 'help', text: formatDay(h.createdAt) })])
      ]);
    });

    const table = el('table', { class: 'data-table is-aligned' }, [
      el('thead', {}, [
        el('tr', {}, ['Hành động', 'Chi tiết / Lý do', 'Người thực hiện', 'Thời gian'].map((t) => el('th', { text: t })))
      ]),
      el('tbody', {}, rows)
    ]);

    container.replaceChildren(el('div', { class: 'table-wrap' }, [table]));
  } catch (err) {
    container.replaceChildren(
      el('p', { class: 'field-error', text: err.message || 'Không thể tải lịch sử.' })
    );
  }
}

// 7. TRANG QUẢN LÝ TÀI KHOẢN (BOOT)
boot({
  active: 'users',
  title: 'Quản lý tài khoản',
  async render({ session, setBreadcrumb }) {
    setBreadcrumb([
      { label: 'DesignMark', href: 'dashboard.php' },
      { label: 'Quản lý tài khoản' }
    ]);

    const page = document.getElementById('page');

    // State
    let currentTab = 'all'; // 'all' | 'active' | 'locked'
    let searchQuery = '';
    let systemRoleFilter = ''; // '' | 'admin' | 'user'
    let projectScopeFilter = ''; // '' | 'owned' | 'joined' | 'none'
    let projectRoleFilter = ''; // '' | 'owner' | 'designer' | 'reviewer' | 'viewer'
    let sortFilter = 'newest'; // 'newest' | 'oldest' | 'name_asc' | 'name_desc'

    const urlParams = new URLSearchParams(location.search);
    let currentPage = parseInt(urlParams.get('page') || '1', 10);
    let limit = parseInt(urlParams.get('limit') || '10', 10);
    if (isNaN(currentPage) || currentPage < 1) currentPage = 1;
    if (isNaN(limit) || limit < 1) limit = 10;

    function updateUrlState() {
      const url = new URL(location.href);
      if (currentPage > 1) {
        url.searchParams.set('page', currentPage);
      } else {
        url.searchParams.delete('page');
      }
      if (limit !== 10) {
        url.searchParams.set('limit', limit);
      } else {
        url.searchParams.delete('limit');
      }
      history.replaceState(null, '', url.toString());
    }

    // Active dropdown ID tracker
    let openDropdownId = null;

    // Close any open dropdown menu on click outside or escape
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.action-menu-wrap')) {
        closeAllDropdowns();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeAllDropdowns();
      }
    });

    function closeAllDropdowns() {
      openDropdownId = null;
      document.querySelectorAll('.action-menu-dropdown').forEach((d) => (d.style.display = 'none'));
      document.querySelectorAll('.btn-action-menu').forEach((b) => b.classList.remove('is-active'));
    }

    // Debounce timer
    let searchDebounceTimeout = null;

    async function loadData() {
      closeAllDropdowns();

      // Show temporary loading indicator in table if area exists
      const contentArea = document.getElementById('users-content-area');
      if (contentArea && contentArea.querySelector('tbody')) {
        const tbody = contentArea.querySelector('tbody');
        tbody.style.opacity = '0.5';
      }

      try {
        const data = await api.listAllUsers({
          page: currentPage,
          limit: limit,
          tab: currentTab,
          q: searchQuery,
          systemRole: systemRoleFilter,
          projectScope: projectScopeFilter,
          projectRole: projectRoleFilter,
          sort: sortFilter
        });

        renderView(data);
      } catch (err) {
        toast(err.message || 'Không thể tải danh sách tài khoản.', 'error');
      }
    }

    function renderView(data) {
      const stats = data.stats || { total: 0, active: 0, locked: 0, admins: 0 };
      const pagination = data.pagination || { page: 1, limit: 10, total: 0, totalPages: 1 };
      if (currentPage > pagination.totalPages && pagination.totalPages >= 1) {
        currentPage = pagination.totalPages;
        loadData();
        return;
      }
      updateUrlState();
      const users = data.users || [];

      // 1. Header Actions
      const createBtn = el('button', {
        type: 'button',
        class: 'btn btn-primary',
        onclick: () => openCreateUserModal(loadData)
      }, [
        icon('plus'),
        el('span', { text: '+ Thêm người dùng' })
      ]);

      const header = el('div', { class: 'page-head' }, [
        el('div', {}, [
          el('h1', { text: 'Quản lý tài khoản' }),
          el('p', { class: 'lede', text: 'Quản lý danh sách tài khoản, vai trò theo dự án, trạng thái hoạt động và bảo mật hệ thống.' })
        ]),
        createBtn
      ]);

      // 2. Stat Cards (4 live indicators with theme-aware contrast)
      const statsGrid = el('div', { class: 'stat-grid-4', style: 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;' }, [
        statCard('Tổng tài khoản', stats.total),
        statCard('Đang hoạt động', stats.active, false, 'var(--success)'),
        statCard('Đã khóa', stats.locked, stats.locked > 0, stats.locked > 0 ? 'var(--danger)' : ''),
        statCard('Quản trị viên', stats.admins, false, 'var(--primary)')
      ]);

      // 3. Segmented Tabs (3 tabs: all, active, locked)
      const tabAll = el('button', {
        type: 'button',
        role: 'tab',
        class: `segmented-tab ${currentTab === 'all' ? 'is-active' : ''}`,
        'data-tab': 'all',
        'aria-selected': currentTab === 'all' ? 'true' : 'false'
      }, [
        icon('users'),
        el('span', { text: 'Tất cả' }),
        el('span', { class: 'tab-badge tab-badge-neutral', text: String(stats.total) })
      ]);

      const tabActive = el('button', {
        type: 'button',
        role: 'tab',
        class: `segmented-tab ${currentTab === 'active' ? 'is-active' : ''}`,
        'data-tab': 'active',
        'aria-selected': currentTab === 'active' ? 'true' : 'false'
      }, [
        icon('check'),
        el('span', { text: 'Đang hoạt động' }),
        el('span', { class: 'tab-badge tab-badge-active', text: String(stats.active) })
      ]);

      const tabLocked = el('button', {
        type: 'button',
        role: 'tab',
        class: `segmented-tab ${currentTab === 'locked' ? 'is-active' : ''}`,
        'data-tab': 'locked',
        'aria-selected': currentTab === 'locked' ? 'true' : 'false'
      }, [
        icon('alert'),
        el('span', { text: 'Đã khóa' }),
        stats.locked > 0
          ? el('span', { class: 'tab-badge tab-badge-locked', text: String(stats.locked) })
          : el('span', { class: 'tab-badge tab-badge-neutral', text: '0' })
      ]);

      const tabNav = el('div', { class: 'segmented-nav', role: 'tablist' }, [tabAll, tabActive, tabLocked]);

      bindTabs(tabNav, (nextTab) => {
        if (currentTab !== nextTab) {
          currentTab = nextTab;
          currentPage = 1;
          loadData();
        }
      });

      // 4. Filters Toolbar (Single unified horizontal bar)
      const searchInput = el('input', {
        class: 'field-input field-input-sm',
        placeholder: 'Tìm họ tên, username, email...',
        value: searchQuery
      });

      searchInput.addEventListener('input', () => {
        clearTimeout(searchDebounceTimeout);
        searchDebounceTimeout = setTimeout(() => {
          searchQuery = searchInput.value.trim();
          currentPage = 1;
          loadData();
        }, 300);
      });

      const systemRoleSelect = el('select', {
        class: 'field-input field-input-sm',
        'aria-label': 'Lọc quyền hệ thống',
        onchange: (e) => {
          systemRoleFilter = e.target.value;
          currentPage = 1;
          loadData();
        }
      }, [
        el('option', { value: '', text: 'Tất cả quyền', selected: systemRoleFilter === '' }),
        el('option', { value: 'admin', text: 'Admin', selected: systemRoleFilter === 'admin' }),
        el('option', { value: 'user', text: 'User', selected: systemRoleFilter === 'user' })
      ]);

      const projectScopeSelect = el('select', {
        class: 'field-input field-input-sm',
        'aria-label': 'Lọc theo dự án',
        onchange: (e) => {
          projectScopeFilter = e.target.value;
          currentPage = 1;
          loadData();
        }
      }, [
        el('option', { value: '', text: 'Tất cả tài khoản', selected: projectScopeFilter === '' }),
        el('option', { value: 'owned', text: 'Có dự án sở hữu', selected: projectScopeFilter === 'owned' }),
        el('option', { value: 'joined', text: 'Có tham gia dự án', selected: projectScopeFilter === 'joined' }),
        el('option', { value: 'none', text: 'Chưa tham gia dự án', selected: projectScopeFilter === 'none' })
      ]);

      const projectRoleSelect = el('select', {
        class: 'field-input field-input-sm',
        'aria-label': 'Lọc vai trò dự án',
        onchange: (e) => {
          projectRoleFilter = e.target.value;
          currentPage = 1;
          loadData();
        }
      }, [
        el('option', { value: '', text: 'Tất cả vai trò', selected: projectRoleFilter === '' }),
        el('option', { value: 'owner', text: 'Owner', selected: projectRoleFilter === 'owner' }),
        el('option', { value: 'designer', text: 'Designer', selected: projectRoleFilter === 'designer' }),
        el('option', { value: 'reviewer', text: 'Reviewer', selected: projectRoleFilter === 'reviewer' }),
        el('option', { value: 'viewer', text: 'Viewer', selected: projectRoleFilter === 'viewer' })
      ]);

      const sortSelect = el('select', {
        class: 'field-input field-input-sm',
        'aria-label': 'Sắp xếp danh sách',
        onchange: (e) => {
          sortFilter = e.target.value;
          currentPage = 1;
          loadData();
        }
      }, [
        el('option', { value: 'newest', text: 'Mới nhất', selected: sortFilter === 'newest' }),
        el('option', { value: 'oldest', text: 'Cũ nhất', selected: sortFilter === 'oldest' }),
        el('option', { value: 'name_asc', text: 'Tên A–Z', selected: sortFilter === 'name_asc' }),
        el('option', { value: 'name_desc', text: 'Tên Z–A', selected: sortFilter === 'name_desc' })
      ]);

      const isFiltering = searchQuery !== '' || systemRoleFilter !== '' || projectScopeFilter !== '' || projectRoleFilter !== '' || sortFilter !== 'newest';
      const clearFilterBtn = isFiltering ? el('button', {
        type: 'button',
        class: 'btn-clear-filter',
        title: 'Đặt lại bộ lọc về mặc định',
        onclick: () => {
          searchQuery = '';
          systemRoleFilter = '';
          projectScopeFilter = '';
          projectRoleFilter = '';
          sortFilter = 'newest';
          currentPage = 1;
          loadData();
        }
      }, [icon('close'), el('span', { text: 'Đặt lại' })]) : null;

      const startIdx = pagination.total === 0 ? 0 : (pagination.page - 1) * pagination.limit + 1;
      const endIdx = Math.min(pagination.page * pagination.limit, pagination.total);

      const toolbar = el('div', { class: 'users-toolbar' }, [
        el('div', { class: 'users-toolbar-left' }, [
          el('div', { class: 'search-box-wrap' }, [icon('search'), searchInput]),
          systemRoleSelect,
          projectScopeSelect,
          projectRoleSelect,
          sortSelect,
          ...(clearFilterBtn ? [clearFilterBtn] : [])
        ]),
        el('div', { class: 'users-toolbar-right' }, [
          el('span', { text: `${startIdx} - ${endIdx} / ` }),
          el('strong', { text: String(pagination.total) }),
          el('span', { text: ' tài khoản' })
        ])
      ]);

      // 5. Table Rows
      let tableContent;
      if (!users.length) {
        tableContent = emptyState({
          title: currentTab === 'locked' ? 'Không có tài khoản nào bị khóa' : 'Không tìm thấy tài khoản',
          text: searchQuery.trim() ? 'Thử tìm kiếm với từ khóa khác hoặc xóa bộ lọc.' : (currentTab === 'locked' ? 'Hệ thống hiện không có tài khoản nào trong trạng thái bị khóa.' : 'Chưa có tài khoản nào phù hợp điều kiện lọc.')
        });
      } else {
        const rows = users.map((u) => {
          const isMe = (u.id === session.userId);
          const isSystemAdmin = (u.systemRole === 'admin' || u.username === 'admin');
          const isLocked = (u.status === 'locked');
          const isLastActiveAdmin = isSystemAdmin && !isLocked && stats.admins <= 1;

          // Status Badge
          const statusBadge = isLocked
            ? el('span', {
                class: 'status-pill',
                style: 'background: rgba(239, 68, 68, 0.12); color: #dc2626;'
              }, [
                el('span', { class: 'status-pill-dot', style: 'background: #dc2626;' }),
                el('span', { text: 'Đã khóa' })
              ])
            : el('span', { class: 'status-pill is-active' }, [
                el('span', { class: 'status-pill-dot' }),
                el('span', { text: 'Hoạt động' })
              ]);

          // System Role Badge
          const roleBadge = isSystemAdmin
            ? el('span', { class: 'badge badge-role role-manager', text: 'Admin' })
            : el('span', { class: 'badge badge-role role-viewer', text: 'User' });

          // Project counts
          const ownedCountBadge = el('span', {
            class: `project-count-badge ${Number(u.ownedProjectsCount) === 0 ? 'is-zero' : ''}`,
            text: `${u.ownedProjectsCount || 0} dự án`
          });

          const joinedCountBadge = el('span', {
            class: `project-count-badge ${Number(u.joinedProjectsCount) === 0 ? 'is-zero' : ''}`,
            text: `${u.joinedProjectsCount || 0} dự án`
          });

          // Action dropdown items
          const dropdownItems = [];

          // View details
          dropdownItems.push(el('button', {
            type: 'button',
            class: 'action-menu-item',
            onclick: (e) => {
              e.stopPropagation();
              closeAllDropdowns();
              openUserDetailModal(u);
            }
          }, [icon('eye'), el('span', { text: 'Xem chi tiết' })]));

          // Edit info
          dropdownItems.push(el('button', {
            type: 'button',
            class: 'action-menu-item',
            onclick: (e) => {
              e.stopPropagation();
              closeAllDropdowns();
              openEditUserModal(u, loadData);
            }
          }, [icon('edit'), el('span', { text: 'Chỉnh sửa thông tin' })]));

          // Reset password
          dropdownItems.push(el('button', {
            type: 'button',
            class: 'action-menu-item',
            onclick: (e) => {
              e.stopPropagation();
              closeAllDropdowns();
              openResetPasswordModal(u);
            }
          }, [icon('key'), el('span', { text: 'Đổi mật khẩu' })]));

          // Status history
          dropdownItems.push(el('button', {
            type: 'button',
            class: 'action-menu-item',
            onclick: (e) => {
              e.stopPropagation();
              closeAllDropdowns();
              openStatusHistoryModal(u);
            }
          }, [icon('clock'), el('span', { text: 'Xem lịch sử' })]));

          dropdownItems.push(el('div', { class: 'action-menu-divider' }));

          // Lock / Unlock actions
          if (isLocked) {
            dropdownItems.push(el('button', {
              type: 'button',
              class: 'action-menu-item',
              onclick: async (e) => {
                e.stopPropagation();
                closeAllDropdowns();
                const ok = await confirmDialog({
                  title: 'Mở khóa tài khoản?',
                  message: `Bạn có chắc muốn mở khóa cho tài khoản ${u.name} (@${u.username})? Người dùng sẽ có thể đăng nhập bình thường.`,
                  confirmText: 'Mở khóa'
                });
                if (!ok) return;
                try {
                  const res = await api.unlockUser(u.id, 'Quản trị viên mở khóa');
                  toast(res.message || `Đã mở khóa cho ${u.name}.`, 'success');
                  loadData();
                } catch (err) {
                  toast(err.message || 'Lỗi khi mở khóa.', 'error');
                }
              }
            }, [icon('unlock'), el('span', { text: 'Mở khóa tài khoản' })]));
          } else {
            if (isMe) {
              dropdownItems.push(el('div', {
                class: 'action-menu-item',
                style: 'opacity: 0.6; cursor: not-allowed; font-size: 12px; color: var(--muted);'
              }, [icon('lock'), el('span', { text: 'Tài khoản của bạn' })]));
            } else if (isLastActiveAdmin) {
              dropdownItems.push(el('div', {
                class: 'action-menu-item',
                style: 'opacity: 0.6; cursor: not-allowed; font-size: 12px; color: var(--muted);'
              }, [icon('lock'), el('span', { text: 'Không thể khóa Admin cuối' })]));
            } else {
              dropdownItems.push(el('button', {
                type: 'button',
                class: 'action-menu-item is-danger',
                onclick: (e) => {
                  e.stopPropagation();
                  closeAllDropdowns();
                  openLockUserModal(u, loadData);
                }
              }, [icon('lock'), el('span', { text: 'Khóa tài khoản' })]));
            }
          }

          const dropdownMenu = el('div', {
            class: 'action-menu-dropdown',
            id: `dropdown-${u.id}`,
            style: 'display: none;'
          }, dropdownItems);

          const moreBtn = el('button', {
            type: 'button',
            class: 'btn-action-menu',
            title: 'Thao tác tài khoản',
            onclick: (e) => {
              e.stopPropagation();
              const isCurrentlyOpen = openDropdownId === u.id;
              closeAllDropdowns();
              if (!isCurrentlyOpen) {
                openDropdownId = u.id;
                dropdownMenu.style.display = 'flex';
                moreBtn.classList.add('is-active');
              }
            }
          }, ['⋯']);

          const actionWrap = el('div', { class: 'action-menu-wrap' }, [moreBtn, dropdownMenu]);

          return el('tr', {
            class: 'clickable-row',
            onclick: (e) => {
              if (e.target.closest('.action-menu-wrap')) return;
              openUserDetailModal(u);
            }
          }, [
            el('td', {}, [
              el('div', { class: 'user-member-cell' }, [
                avatarEl(u, 38),
                el('div', {}, [
                  el('span', { class: 'user-name-title', text: u.name }),
                  el('div', { class: 'user-username-subtle', text: `@${u.username}` }),
                  u.email ? el('div', { class: 'user-email-subtle', text: u.email }) : null
                ].filter(Boolean))
              ])
            ]),
            el('td', {}, [roleBadge]),
            el('td', {}, [ownedCountBadge]),
            el('td', {}, [joinedCountBadge]),
            el('td', {}, [statusBadge]),
            el('td', {}, [
              el('span', { class: 'help', text: formatDay(u.createdAt) })
            ]),
            el('td', { style: 'text-align: right; padding-right: 18px;' }, [actionWrap])
          ]);
        });

        const columns = [
          { label: 'Tài khoản', style: 'min-width: 240px;' },
          { label: 'Quyền hệ thống', style: 'min-width: 140px;' },
          { label: 'Dự án sở hữu', style: 'min-width: 130px;' },
          { label: 'Dự án tham gia', style: 'min-width: 140px;' },
          { label: 'Trạng thái', style: 'min-width: 120px;' },
          { label: 'Ngày tạo', style: 'min-width: 110px;' },
          { label: 'Thao tác', style: 'min-width: 90px; text-align: right; padding-right: 18px;' }
        ];

        const table = el('table', { class: 'data-table is-aligned' }, [
          el('thead', {}, [
            el('tr', {}, columns.map((col) => el('th', { text: col.label, style: col.style })))
          ]),
          el('tbody', {}, rows)
        ]);

        // 6. Pagination Bar Controls
        const paginationBar = renderPagination({
          currentPage: pagination.page,
          pageSize: pagination.limit,
          totalItems: pagination.total,
          pageSizeOptions: [10, 20, 50, 100],
          itemLabel: 'tài khoản',
          onPageChange: (newPage) => {
            currentPage = newPage;
            loadData();
          },
          onPageSizeChange: (newLimit) => {
            limit = newLimit;
            currentPage = 1;
            loadData();
          }
        });

        tableContent = el('div', { class: 'table-card' }, [
          el('div', { class: 'table-wrap' }, [table]),
          ...(paginationBar ? [paginationBar] : [])
        ]);
      }

      // Assemble content area
      const contentArea = el('div', { id: 'users-content-area', style: 'margin-top: 20px;' }, [
        toolbar,
        tableContent
      ]);

      page.replaceChildren(
        el('section', { id: 'users-overview' }, [
          header,
          statsGrid
        ]),
        el('section', { style: 'margin-top: 24px;' }, [
          tabNav,
          contentArea
        ])
      );
    }

    await loadData();
  }
});
