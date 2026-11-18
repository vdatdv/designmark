/**
 * Thành phần giao diện dùng chung. Không chứa nghiệp vụ dự án.
 * Phân quyền hiển thị chỉ là lớp demo; API vẫn kiểm tra lại.
 */
import { getSession, logout, roleLabel, switchAccount } from './auth.js';
import { api, isPreferLocal, setPreferLocal, checkDatabaseHealth } from './api.js';

const ICONS = {
  grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  folder: '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H9l2 2h7.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z"/>',
  message: '<path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v7A2.5 2.5 0 0 1 16.5 16H9l-4 3.5V6.5Z"/>',
  menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
  list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
  close: '<path d="M6 6l12 12M18 6 6 18"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  logout: '<path d="M10 7V5.5A1.5 1.5 0 0 1 11.5 4h7A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 10 18.5V17"/><path d="M4 12h10M11 8l4 4-4 4"/>',
  chevron: '<path d="m9 6 6 6-6 6"/>',
  search: '<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>',
  upload: '<path d="M12 16V6M8 9l4-4 4 4"/><path d="M5 19h14"/>',
  pin: '<path d="M12 21s6-5.1 6-10a6 6 0 1 0-12 0c0 4.9 6 10 6 10Z"/><circle cx="12" cy="11" r="1.6" fill="currentColor" stroke="none"/>',
  cursor: '<path d="m5 4 6.5 15 1.8-5.7L19 11.5 5 4Z"/>',
  square: '<rect x="5" y="5" width="14" height="14" rx="2"/>',
  zoomIn: '<circle cx="11" cy="11" r="6"/><path d="M11 8v6M8 11h6M20 20l-3.5-3.5"/>',
  zoomOut: '<circle cx="11" cy="11" r="6"/><path d="M8 11h6M20 20l-3.5-3.5"/>',
  fit: '<path d="M9 4H5v4M15 4h4v4M9 20H5v-4M15 20h4v-4"/>',
  compare: '<path d="M4 6h7v12H4zM13 6h7v12h-7z"/>',
  download: '<path d="M12 5v10M8 11l4 4 4-4M5 19h14"/>',
  check: '<path d="m5 12 5 5 9-10"/>',
  alert: '<path d="M12 4 3 19h18L12 4Z"/><path d="M12 10v4M12 16.5v.5"/>',
  users: '<path d="M8 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM16.5 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path d="M3.5 19c.6-2.4 2.4-3.5 4.5-3.5s3.9 1.1 4.5 3.5M14 15.6c1.4-.4 2.7-.1 3.8.8 1 .8 1.6 1.8 1.9 2.6"/>',
  clock: '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
  panels: '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M10 5v14"/>',
  database: '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/>',
  zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
  wifi: '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="19" r="1"/>',
  trash: '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
  key: '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3M18.5 4.5l3 3"/>',
  lock: '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  unlock: '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
  bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
  moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
  heart: '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
  smilePlus: '<path d="M22 11v1a10 10 0 1 1-9-10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/><path d="M16 5h6"/><path d="M19 2v6"/>',
  more: '<circle cx="12" cy="12" r="1.75" fill="currentColor"/><circle cx="12" cy="5" r="1.75" fill="currentColor"/><circle cx="12" cy="19" r="1.75" fill="currentColor"/>',
  reply: '<polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
  link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
  paperclip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
  image: '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>'
};


const escapeHandlers = [];

if (typeof document !== 'undefined') {
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const handler = escapeHandlers[escapeHandlers.length - 1];
    if (!handler) return;
    event.preventDefault();
    handler();
  });
}

export function pushEscape(handler) {
  escapeHandlers.push(handler);
  return () => {
    const index = escapeHandlers.indexOf(handler);
    if (index >= 0) escapeHandlers.splice(index, 1);
  };
}

export function icon(name) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '1.75');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.classList.add('icon');
  svg.innerHTML = ICONS[name] || ICONS.grid;
  return svg;
}

export function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  Object.entries(attrs).forEach(([key, value]) => {
    if (value == null || value === false) return;
    if (key === 'class') node.className = value;
    else if (key === 'text') node.textContent = String(value);
    else if (key === 'dataset') Object.entries(value).forEach(([name, data]) => { node.dataset[name] = data; });
    else if (key.startsWith('on') && typeof value === 'function') node.addEventListener(key.slice(2).toLowerCase(), value);
    else node.setAttribute(key, value === true ? '' : String(value));
  });
  const list = Array.isArray(children) ? children : [children];
  list.forEach((child) => {
    if (child == null || child === false) return;
    node.append(child.nodeType ? child : document.createTextNode(String(child)));
  });
  return node;
}

export function announce(message) {
  const region = document.getElementById('live-region');
  if (!region) return;
  region.textContent = '';
  window.setTimeout(() => {
    region.textContent = message;
  }, 20);
}

export function formatDate(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  }).format(date);
}

export function formatDay(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

export function formatRelativeTime(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const now = new Date();
  const diffSec = Math.max(0, Math.floor((now - date) / 1000));
  if (diffSec < 45) return 'Vừa xong';
  const diffMin = Math.floor(diffSec / 60);
  if (diffMin < 60) return `${diffMin} phút trước`;
  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours} giờ trước`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays === 1) return 'Hôm qua';
  if (diffDays < 7) return `${diffDays} ngày trước`;
  return formatDay(iso);
}

export function avatarEl(user, size = 28) {
  const node = el('span', {
    class: 'avatar',
    title: user?.name || '',
    style: `width:${size}px;height:${size}px;background:${user?.color || '#4F46E5'};color:#ffffff;font-weight:700;line-height:1;`
  }, (user?.name || '?').trim().charAt(0).toUpperCase());
  return node;
}

export function statusBadge(status) {
  const open = status !== 'resolved';
  return el('span', { class: open ? 'badge badge-open' : 'badge badge-resolved' }, [
    el('span', { class: 'badge-dot', 'aria-hidden': 'true' }),
    open ? 'Chưa xử lý' : 'Đã xử lý'
  ]);
}

export function roleBadge(role) {
  return el('span', { class: `badge badge-role role-${role}` }, roleLabel(role));
}

export function emptyState({ title, text, action, iconName = 'folder' }) {
  return el('div', {
    class: 'empty-state',
    style: 'grid-column: 1 / -1; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; margin: 48px auto; padding: 24px 16px;'
  }, [
    el('div', { class: 'empty-mark', 'aria-hidden': 'true', style: 'margin-bottom: 12px;' }, [icon(iconName)]),
    el('h2', { text: title, style: 'margin-bottom: 8px;' }),
    text ? el('p', { text, style: 'max-width: 480px; margin: 0 auto; line-height: 1.6; color: var(--muted);' }) : null,
    action || null
  ]);
}


export function setBusy(button, busy, busyLabel) {
  if (!button) return;
  if (busy) {
    if (!button.dataset.label) button.dataset.label = button.textContent;
    button.disabled = true;
    button.dataset.loading = 'true';
    button.replaceChildren(el('span', { class: 'spinner', 'aria-hidden': 'true' }), document.createTextNode(busyLabel || 'Đang lưu...'));
  } else {
    button.disabled = false;
    delete button.dataset.loading;
    button.textContent = button.dataset.label || button.textContent;
  }
}

export function toast(message, type = 'info', options = {}) {
  const root = document.getElementById('toast-root');
  if (!root) return;
  const { key = '', icon: customIcon = '', duration = 3000 } = options || {};
  if (key) {
    root.querySelectorAll(`[data-toast-key="${key}"]`).forEach((existing) => existing.remove());
  }
  const iconName = customIcon || (type === 'success' ? 'check' : type === 'error' ? 'alert' : 'message');
  const dismiss = (node) => {
    if (!node || node.dataset.closing === '1') return;
    node.dataset.closing = '1';
    node.classList.remove('is-in');
    window.setTimeout(() => node.remove(), 220);
  };
  const closeBtn = el('button', {
    type: 'button',
    class: 'toast-close',
    'aria-label': 'Đóng thông báo',
    onclick: () => dismiss(node)
  }, [icon('close')]);
  const node = el('div', {
    class: `toast toast-${type}`,
    role: type === 'error' ? 'alert' : 'status',
    dataset: key ? { toastKey: key } : undefined
  }, [
    el('span', { class: 'toast-icon', 'aria-hidden': 'true' }, [icon(iconName)]),
    el('span', { class: 'toast-text', text: message }),
    closeBtn
  ]);
  root.append(node);
  requestAnimationFrame(() => node.classList.add('is-in'));
  window.setTimeout(() => dismiss(node), duration);
  announce(message);
}

function focusable(container) {
  return [...container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
    .filter((node) => !node.disabled && node.offsetParent !== null);
}

export function openModal({ title, content, size = '' }) {
  const root = document.getElementById('modal-root');
  const previous = document.activeElement;
  const titleId = `modal-title-${Date.now()}`;
  const card = el('div', { class: `modal-card ${size}`, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
    el('div', { class: 'modal-head' }, [
      el('h2', { id: titleId, text: title }),
      el('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Đóng hộp thoại', onclick: () => close() }, [icon('close')])
    ]),
    el('div', { class: 'modal-body' }, [content])
  ]);
  const backdrop = el('div', { class: 'modal-backdrop', onclick: (event) => { if (event.target === backdrop) close(); } }, [card]);
  root.append(backdrop);
  document.body.classList.add('modal-open');
  requestAnimationFrame(() => backdrop.classList.add('is-in'));
  const removeEscape = pushEscape(() => close());
  const onKey = (event) => {
    if (event.key !== 'Tab') return;
    const items = focusable(card);
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };
  card.addEventListener('keydown', onKey);
  window.setTimeout(() => {
    const items = focusable(card);
    (items.find((item) => item !== card.querySelector('.icon-btn')) || items[0] || card).focus();
  }, 30);
  let closed = false;
  function close() {
    if (closed) return;
    closed = true;
    removeEscape();
    backdrop.classList.remove('is-in');
    window.setTimeout(() => backdrop.remove(), 180);
    if (!root.querySelector('.modal-backdrop')) document.body.classList.remove('modal-open');
    if (previous && typeof previous.focus === 'function') previous.focus();
  }
  return { close, card, backdrop };
}

export function confirmDialog({ title, message, confirmText = 'Xác nhận', cancelText = 'Hủy', danger = false }) {
  return new Promise((resolve) => {
    const content = el('div', {}, [
      el('p', { class: 'modal-copy', text: message }),
      el('div', { class: 'modal-actions' }, [
        el('button', { type: 'button', class: 'btn btn-secondary', text: cancelText, onclick: () => done(false) }),
        el('button', { type: 'button', class: danger ? 'btn btn-danger' : 'btn btn-primary', text: confirmText, onclick: () => done(true) })
      ])
    ]);
    const modal = openModal({ title, content });
    function done(value) {
      modal.close();
      resolve(value);
    }
  });
}

export function fieldError(input, message) {
  const id = `${input.id || 'field'}-error`;
  let node = input.parentElement.querySelector('.field-error');
  if (!node) {
    node = el('p', { class: 'field-error', id });
    input.insertAdjacentElement('afterend', node);
  }
  node.textContent = message || '';
  input.setAttribute('aria-invalid', message ? 'true' : 'false');
  if (message) input.setAttribute('aria-describedby', node.id);
}

export function setBreadcrumb(items) {
  const nav = document.getElementById('breadcrumb');
  const backBtn = document.getElementById('topbar-back-btn');
  const isDashboard = location.pathname.endsWith('dashboard.php') || location.pathname.endsWith('/');

  if (backBtn) {
    if (!isDashboard && items && items.length > 1) {
      let prevHref = null;
      let prevLabel = 'trang trước';
      for (let i = items.length - 2; i >= 0; i--) {
        if (items[i].href) {
          prevHref = items[i].href;
          prevLabel = items[i].label || 'trang trước';
          break;
        }
      }
      window.__dmBreadcrumbPrevHref = prevHref;
      backBtn.style.display = 'inline-flex';
      const tooltip = `Quay lại: ${prevLabel}`;
      backBtn.title = tooltip;
      backBtn.setAttribute('aria-label', tooltip);
    } else {
      window.__dmBreadcrumbPrevHref = null;
      backBtn.style.display = 'none';
    }
  }

  if (!nav) return;
  nav.replaceChildren();
  items.forEach((item, index) => {
    if (index) nav.append(el('span', { class: 'crumb-sep', 'aria-hidden': 'true' }, '/'));
    if (item.href && index < items.length - 1) {
      nav.append(el('a', { href: item.href, text: item.label }));
    } else {
      nav.append(el('span', { text: item.label, 'aria-current': index === items.length - 1 ? 'page' : null }));
    }
  });
}

export function setPageTitle(title) {
  document.title = `${title} — DesignMark`;
  const node = document.getElementById('topbar-title');
  if (node) node.textContent = title;
}

function closeDrawer() {
  document.querySelector('.app-shell')?.classList.remove('is-drawer-open');
  const backdrop = document.getElementById('sidebar-backdrop');
  if (backdrop) backdrop.hidden = true;
  document.getElementById('menu-toggle')?.setAttribute('aria-expanded', 'false');
}

let drawerEscape = null;

export function renderNotificationMenu(session) {
  const badge = el('span', { class: 'notif-badge', id: 'notif-badge', style: 'display: none;' }, '0');
  const bellBtn = el('button', {
    type: 'button',
    class: 'icon-btn notif-btn',
    id: 'notif-btn',
    'aria-label': 'Thông báo',
    'aria-expanded': 'false',
    'aria-haspopup': 'true'
  }, [icon('bell'), badge]);

  const markAllBtn = el('button', {
    type: 'button',
    class: 'btn-link notif-mark-all-btn',
    text: 'Đánh dấu tất cả đã đọc'
  });

  const tabAllBadge = el('span', { class: 'notif-tab-badge' }, '0');
  const tabUnreadBadge = el('span', { class: 'notif-tab-badge' }, '0');

  const tabAllBtn = el('button', {
    type: 'button',
    class: 'notif-tab is-active',
    id: 'notif-tab-all'
  }, [
    document.createTextNode('Tất cả '),
    tabAllBadge
  ]);

  const tabUnreadBtn = el('button', {
    type: 'button',
    class: 'notif-tab',
    id: 'notif-tab-unread'
  }, [
    document.createTextNode('Chưa đọc '),
    tabUnreadBadge
  ]);

  const tabsBar = el('div', { class: 'notif-tabs' }, [tabAllBtn, tabUnreadBtn]);
  const listContainer = el('div', { class: 'notif-list', id: 'notif-list' });

  const dropdown = el('div', { class: 'notif-dropdown', id: 'notif-dropdown', hidden: true }, [
    el('div', { class: 'notif-header' }, [
      el('div', { class: 'notif-header-title' }, [
        el('strong', { text: 'Thông báo' })
      ]),
      markAllBtn
    ]),
    tabsBar,
    listContainer
  ]);

  const wrapper = el('div', { class: 'notif-wrapper', id: 'notif-wrapper' }, [bellBtn, dropdown]);

  let currentTab = 'all'; // 'all' | 'unread'
  let notifications = [];
  let unreadCount = 0;
  let allCount = 0;
  let currentOffset = 0;
  const PAGE_LIMIT = 10;
  let hasMore = false;
  let isLoading = false;
  let isError = false;
  let escapeCleaner = null;

  function updateBadge(count) {
    unreadCount = Math.max(0, parseInt(count, 10) || 0);
    tabUnreadBadge.textContent = String(unreadCount);

    if (unreadCount > 0) {
      badge.removeAttribute('hidden');
      badge.style.display = 'inline-flex';
      badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
      badge.setAttribute('aria-label', `${unreadCount} thông báo chưa đọc`);
      bellBtn.classList.add('has-unread');
      bellBtn.setAttribute('title', `Bạn có ${unreadCount} thông báo mới`);
      markAllBtn.removeAttribute('hidden');
      markAllBtn.style.display = '';
    } else {
      badge.setAttribute('hidden', '');
      badge.style.display = 'none';
      badge.textContent = '0';
      badge.removeAttribute('aria-label');
      bellBtn.classList.remove('has-unread');
      bellBtn.setAttribute('title', 'Thông báo');
      markAllBtn.setAttribute('hidden', '');
      markAllBtn.style.display = 'none';
    }
  }

  function updateTabs() {
    tabAllBadge.textContent = String(allCount);
    tabUnreadBadge.textContent = String(unreadCount);
    if (currentTab === 'all') {
      tabAllBtn.classList.add('is-active');
      tabUnreadBtn.classList.remove('is-active');
    } else {
      tabUnreadBtn.classList.add('is-active');
      tabAllBtn.classList.remove('is-active');
    }
  }

  tabAllBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (currentTab === 'all') return;
    currentTab = 'all';
    currentOffset = 0;
    updateTabs();
    loadNotifications(false);
  });

  tabUnreadBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (currentTab === 'unread') return;
    currentTab = 'unread';
    currentOffset = 0;
    updateTabs();
    loadNotifications(false);
  });

  function renderList() {
    if (isLoading && notifications.length === 0) {
      listContainer.replaceChildren(
        el('div', { class: 'notif-loading' }, [
          el('div', { class: 'spinner', style: 'width: 22px; height: 22px; border-width: 2px;' }),
          el('span', { text: 'Đang tải thông báo...' })
        ])
      );
      return;
    }

    if (isError && notifications.length === 0) {
      const retryBtn = el('button', { type: 'button', class: 'notif-error-btn', text: 'Thử lại' });
      retryBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        loadNotifications(false);
      });
      listContainer.replaceChildren(
        el('div', { class: 'notif-error' }, [
          icon('alert'),
          el('span', { text: 'Không thể tải thông báo. Vui lòng thử lại.' }),
          retryBtn
        ])
      );
      return;
    }

    if (!notifications.length) {
      listContainer.replaceChildren(
        el('div', { class: 'notif-empty' }, [
          icon('bell'),
          el('p', { text: currentTab === 'unread' ? 'Không có thông báo chưa đọc nào.' : 'Chưa có thông báo nào.' })
        ])
      );
      return;
    }

    const items = notifications.map((notif) => {
      // Notification types with distinctive icons & colors
      let iconName = 'message';
      let iconColor = '#D6245C';

      if (notif.type === 'new_annotation') {
        iconName = 'pin';
        iconColor = '#D6245C';
      } else if (notif.type === 'new_reply') {
        iconName = 'message';
        iconColor = '#0284C7';
      } else if (notif.type === 'member_added') {
        iconName = 'users';
        iconColor = '#10B981';
      } else if (notif.type === 'member_role_changed') {
        iconName = 'key';
        iconColor = '#8B5CF6';
      } else if (notif.type === 'member_removed') {
        iconName = 'trash';
        iconColor = '#EF4444';
      } else if (notif.type === 'status_change') {
        iconName = 'check';
        iconColor = '#D97706';
      } else if (notif.type === 'new_version') {
        iconName = 'upload';
        iconColor = '#6366F1';
      } else if (notif.type === 'account_approved') {
        iconName = 'check';
        iconColor = '#059669';
      } else if (notif.type === 'mention') {
        iconName = 'message';
        iconColor = '#EC4899';
      } else if (notif.type === 'reaction') {
        iconName = 'heart';
        iconColor = '#EC4899';
      }

      // Quick mark as read button on item
      const markSingleBtn = !notif.isRead ? el('button', {
        type: 'button',
        class: 'notif-mark-single-btn',
        title: 'Đánh dấu đã đọc',
        'aria-label': 'Đánh dấu thông báo này là đã đọc'
      }, [icon('check')]) : null;

      if (markSingleBtn) {
        markSingleBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          try {
            await api.markNotificationRead(notif.id);
            notif.isRead = true;
            updateBadge(Math.max(0, unreadCount - 1));
            if (currentTab === 'unread') {
              notifications = notifications.filter((n) => n.id !== notif.id);
            }
            renderList();
          } catch (err) {
            // non-blocking
          }
        });
      }

      const itemEl = el('div', {
        class: `notif-item ${notif.isRead ? 'is-read' : 'is-unread'}`,
        role: 'button',
        tabindex: '0'
      }, [
        el('div', {
          class: 'notif-item-icon',
          style: `background: ${iconColor}18; color: ${iconColor};`
        }, [icon(iconName)]),
        el('div', { class: 'notif-item-content' }, [
          el('div', { class: 'notif-item-top' }, [
            el('strong', { class: 'notif-item-title', text: notif.title }),
            el('span', { class: 'notif-item-time', text: formatRelativeTime(notif.createdAt) })
          ]),
          el('div', { class: 'notif-item-desc', text: notif.content }),
          el('div', { class: 'notif-item-meta' }, [
            el('span', { class: 'notif-project-tag', text: notif.projectName || 'Dự án' })
          ])
        ]),
        el('div', { class: 'notif-item-indicator' }, [
          markSingleBtn,
          !notif.isRead ? el('span', { class: 'notif-unread-dot', title: 'Chưa đọc' }) : null
        ])
      ]);

      const handleClick = async () => {
        if (!notif.isRead) {
          try {
            await api.markNotificationRead(notif.id);
            notif.isRead = true;
            updateBadge(Math.max(0, unreadCount - 1));
          } catch (e) {
            // non-blocking
          }
        }
        closeDropdown();
        if (notif.link) {
          if (notif.projectId) {
            try {
              await api.getProject(notif.projectId);
              window.location.href = notif.link;
            } catch (err) {
              toast('Dự án hoặc nội dung này không còn tồn tại hoặc bạn không có quyền truy cập.', 'error');
            }
          } else {
            window.location.href = notif.link;
          }
        }
      };

      itemEl.addEventListener('click', handleClick);
      itemEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          handleClick();
        }
      });

      return itemEl;
    });

    const children = [...items];

    // Load More Button
    if (hasMore) {
      const loadMoreBtn = el('button', {
        type: 'button',
        class: 'notif-load-more-btn',
        text: isLoading ? 'Đang tải...' : 'Tải thêm thông báo'
      });
      loadMoreBtn.disabled = isLoading;
      loadMoreBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        currentOffset += PAGE_LIMIT;
        loadNotifications(true);
      });
      children.push(el('div', { class: 'notif-load-more-wrap' }, [loadMoreBtn]));
    }

    listContainer.replaceChildren(...children);
  }

  async function loadNotifications(append = false) {
    if (isLoading && append) return;
    isLoading = true;
    isError = false;
    if (!append) {
      renderList();
    }

    try {
      const params = {
        limit: PAGE_LIMIT,
        offset: currentOffset
      };
      if (currentTab === 'unread') {
        params.tab = 'unread';
      }

      const res = await api.listNotifications(params);
      const fetched = res.notifications || [];
      if (append) {
        notifications = notifications.concat(fetched);
      } else {
        notifications = fetched;
      }
      unreadCount = res.unreadCount || 0;
      allCount = res.allCount || 0;
      hasMore = !!res.hasMore;
      updateBadge(unreadCount);
      updateTabs();
    } catch (e) {
      console.warn('Could not load notifications', e);
      isError = true;
    } finally {
      isLoading = false;
      renderList();
    }
  }

  function toggleDropdown() {
    if (dropdown.hidden) {
      dropdown.hidden = false;
      bellBtn.setAttribute('aria-expanded', 'true');
      escapeCleaner = pushEscape(closeDropdown);
      currentOffset = 0;
      loadNotifications(false);
    } else {
      closeDropdown();
    }
  }

  function closeDropdown() {
    if (dropdown.hidden) return;
    dropdown.hidden = true;
    bellBtn.setAttribute('aria-expanded', 'false');
    if (escapeCleaner) {
      escapeCleaner();
      escapeCleaner = null;
    }
  }

  bellBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleDropdown();
  });

  markAllBtn.addEventListener('click', async (e) => {
    e.stopPropagation();
    try {
      await api.markAllNotificationsRead();
      notifications.forEach((n) => { n.isRead = true; });
      unreadCount = 0;
      updateBadge(0);
      updateTabs();
      if (currentTab === 'unread') {
        notifications = [];
      }
      renderList();
      toast('Đã đánh dấu tất cả thông báo là đã đọc.', 'success');
    } catch (err) {
      toast('Không thể đánh dấu đã đọc.', 'error');
    }
  });

  document.addEventListener('click', (e) => {
    if (!wrapper.contains(e.target)) {
      closeDropdown();
    }
  });

  // Initial fetch immediately
  loadNotifications(false);

  // Polling every 45s when tab is active
  const pollTimer = window.setInterval(() => {
    if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
      if (dropdown.hidden) {
        currentOffset = 0;
        loadNotifications(false);
      }
    }
  }, 45000);

  // Instant refresh on tab focus or visibility change
  window.addEventListener('focus', () => {
    if (dropdown.hidden) {
      currentOffset = 0;
      loadNotifications(false);
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && dropdown.hidden) {
      currentOffset = 0;
      loadNotifications(false);
    }
  });

  // Cross-tab and in-page instant notification triggers
  window.addEventListener('dm:notifications-changed', () => {
    currentOffset = 0;
    loadNotifications(false);
  });

  window.addEventListener('storage', (e) => {
    if (e.key === 'dm_notif_ping') {
      currentOffset = 0;
      loadNotifications(false);
    }
  });

  return wrapper;
}

export function triggerNotificationRefresh() {
  try {
    localStorage.setItem('dm_notif_ping', String(Date.now()));
  } catch (e) {}
  window.dispatchEvent(new CustomEvent('dm:notifications-changed'));
}

export function renderThemeToggle() {
  const getTheme = () => document.documentElement.getAttribute('data-theme') || localStorage.getItem('dm_theme') || 'light';

  const btn = el('button', {
    type: 'button',
    id: 'theme-toggle-btn',
    class: 'icon-btn theme-toggle-btn',
    'aria-label': getTheme() === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối',
    title: getTheme() === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối',
    onclick: () => {
      const current = getTheme();
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try {
        localStorage.setItem('dm_theme', next);
      } catch (e) {
        console.warn('Cannot persist theme', e);
      }
      updateState(next);
      toast(
        next === 'dark' ? 'Đã bật giao diện tối' : 'Đã bật giao diện sáng',
        'info',
        { key: 'theme', icon: next === 'dark' ? 'moon' : 'sun', duration: 2200 }
      );
      window.dispatchEvent(new CustomEvent('dm:theme-changed', { detail: { theme: next } }));
    }
  });

  function updateState(theme) {
    const isDark = theme === 'dark';
    btn.setAttribute('aria-label', isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối');
    btn.setAttribute('title', isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối');
    btn.replaceChildren(icon(isDark ? 'sun' : 'moon'));
  }

  updateState(getTheme());
  return btn;
}

export function mountChrome({ active }) {
  const session = getSession();
  const sidebar = document.getElementById('sidebar');
  const topbar = document.getElementById('topbar');
  if (!sidebar || !topbar || !session) return;
  const collapsed = localStorage.getItem('designmark.sidebar') === '1';
  document.querySelector('.app-shell')?.classList.toggle('is-collapsed', collapsed);

  const brand = el('a', { class: 'brand', href: 'dashboard.php' }, [
    el('span', { class: 'brand-mark', 'aria-hidden': 'true' }, [el('span', { text: 'D' })]),
    el('span', { class: 'brand-text' }, [
      el('strong', { text: 'DesignMark' }),
      el('small', { text: 'Góp ý thiết kế' })
    ])
  ]);
  const collapseBtn = el('button', {
    type: 'button',
    class: 'icon-btn collapse-btn',
    'aria-label': collapsed ? 'Mở rộng thanh bên' : 'Thu gọn thanh bên',
    'data-tooltip': collapsed ? 'Mở rộng' : 'Thu gọn',
    onclick: () => {
      const shell = document.querySelector('.app-shell');
      const next = !shell.classList.contains('is-collapsed');
      shell.classList.toggle('is-collapsed', next);
      localStorage.setItem('designmark.sidebar', next ? '1' : '0');
      collapseBtn.setAttribute('aria-label', next ? 'Mở rộng thanh bên' : 'Thu gọn thanh bên');
      collapseBtn.setAttribute('data-tooltip', next ? 'Mở rộng' : 'Thu gọn');
    }
  }, [icon('panels')]);

  const navLinks = [
    navLink('grid', 'Tổng quan dự án', 'dashboard.php', active === 'overview' || active === 'dashboard'),
    navLink('folder', 'Danh sách dự án', 'projects.php', active === 'projects'),
    navLink('message', 'Quản lý góp ý', 'feedback.php', active === 'feedback')
  ];

  if (session.systemRole === 'admin') {
    navLinks.push(navLink('users', 'Quản lý tài khoản', 'users.php', active === 'users'));
  }

  const nav = el('nav', { class: 'side-nav', 'aria-label': 'Mục chính' }, navLinks);

  sidebar.replaceChildren(
    el('div', { class: 'side-top' }, [brand, collapseBtn]),
    nav,
    el('div', { class: 'side-account' }, [
      el('div', { class: 'account-row' }, [
        avatarEl(session, 36),
        el('div', { class: 'account-meta' }, [
          el('strong', { text: session.name }),
          el('span', { text: session.systemRole === 'admin' ? 'Quản trị viên (Admin)' : (session.title || 'Thành viên') })
        ])
      ]),
      el('div', { class: 'account-actions', style: 'margin-top: 10px; display: flex; flex-direction: column; gap: 6px;' }, [
        el('button', {
          type: 'button',
          class: 'btn btn-secondary btn-sm',
          style: 'width: 100%; justify-content: center;',
          onclick: () => openChangePasswordModal()
        }, [icon('key'), 'Đổi mật khẩu']),
        el('button', {
          type: 'button',
          class: 'btn btn-secondary btn-sm',
          style: 'width: 100%; justify-content: center;',
          onclick: () => logout()
        }, [icon('logout'), 'Đăng xuất'])
      ])
    ])
  );


  topbar.replaceChildren(
    el('div', { class: 'topbar-left' }, [
      el('button', {
        type: 'button',
        class: 'icon-btn menu-toggle',
        id: 'menu-toggle',
        'aria-label': 'Mở menu',
        'aria-expanded': 'false',
        onclick: () => {
          const shell = document.querySelector('.app-shell');
          const open = !shell.classList.contains('is-drawer-open');
          shell.classList.toggle('is-drawer-open', open);
          const backdrop = document.getElementById('sidebar-backdrop');
          if (backdrop) backdrop.hidden = !open;
          document.getElementById('menu-toggle').setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) {
            drawerEscape = pushEscape(closeDrawer);
            sidebar.querySelector('a, button')?.focus();
          } else if (drawerEscape) {
            drawerEscape();
            drawerEscape = null;
          }
        }
      }, [icon('menu')]),
      el('button', {
        type: 'button',
        class: 'topbar-back-btn',
        id: 'topbar-back-btn',
        'aria-label': 'Quay lại trang trước',
        title: 'Quay lại trang trước',
        style: 'display: none;',
        onclick: () => {
          const prev = window.__dmBreadcrumbPrevHref;
          if (prev) {
            location.href = prev;
          } else if (window.history.length > 1) {
            window.history.back();
          } else {
            location.href = 'dashboard.php';
          }
        }
      }, ['← Quay lại']),
      el('div', { class: 'topbar-titles' }, [
        el('nav', { id: 'breadcrumb', class: 'breadcrumb', 'aria-label': 'Breadcrumb' }),
        el('h1', { id: 'topbar-title', text: 'DesignMark' })
      ])
    ]),
    el('div', { class: 'topbar-right' }, [
      el('button', {
        type: 'button',
        id: 'db-network-badge',
        class: 'db-network-badge is-remote',
        title: 'Cài đặt kết nối cơ sở dữ liệu & Tối ưu mạng yếu',
        onclick: () => openNetworkSettingsModal()
      }, [
        icon('wifi'),
        el('span', { class: 'db-badge-text', text: 'Trực tuyến (Supabase)' })
      ]),
      renderThemeToggle(),
      renderNotificationMenu(session),
      el('div', { class: 'topbar-user' }, [
        avatarEl(session, 32),
        el('div', { class: 'topbar-user-meta' }, [
          el('strong', { text: session.name }),
          el('span', { class: 'topbar-user-role', text: `@${session.username} · ${session.systemRole === 'admin' ? 'Quản trị viên' : (session.title || 'Thành viên')}` })
        ])
      ])
    ])
  );

  setupNetworkBadgeListeners();

  const backdrop = document.getElementById('sidebar-backdrop');
  if (backdrop) {
    backdrop.onclick = () => {
      closeDrawer();
      if (drawerEscape) {
        drawerEscape();
        drawerEscape = null;
      }
    };
  }
}

function updateTopBadge(detail = {}) {
  const badgeBtn = document.getElementById('db-network-badge');
  if (!badgeBtn) return;
  const preferLocal = isPreferLocal();
  const isOffline = typeof navigator !== 'undefined' && !navigator.onLine;

  let modeClass = 'is-remote';
  let iconName = 'wifi';
  let label = 'Trực tuyến (Supabase)';

  if (isOffline) {
    modeClass = 'is-offline';
    iconName = 'alert';
    label = 'Mất mạng (CSDL Cục bộ)';
  } else if (preferLocal) {
    modeClass = 'is-local';
    iconName = 'zap';
    label = 'CSDL Cục bộ (Mạng yếu)';
  } else if (detail.engine === 'sqlite' || detail.fallback) {
    modeClass = 'is-fallback';
    iconName = 'zap';
    label = 'CSDL Cục bộ (Tự động)';
  }

  badgeBtn.className = `db-network-badge ${modeClass}`;
  badgeBtn.title = 'Trạng thái kết nối CSDL';
  badgeBtn.replaceChildren(
    icon(iconName),
    el('span', { class: 'db-badge-text', text: label })
  );
}

let networkListenersSetup = false;
function setupNetworkBadgeListeners() {
  if (networkListenersSetup) {
    updateTopBadge();
    return;
  }
  networkListenersSetup = true;

  window.addEventListener('dm:db-mode', (e) => updateTopBadge(e.detail));
  window.addEventListener('dm:prefer-local-changed', () => updateTopBadge());
  window.addEventListener('online', () => updateTopBadge());
  window.addEventListener('offline', () => updateTopBadge());
  updateTopBadge();
}

export function openNetworkSettingsModal() {
  const preferLocal = isPreferLocal();
  const modalContainer = el('div', { class: 'network-modal-content' });

  // Status card
  const statusBox = el('div', { class: 'network-status-box' }, [
    el('div', { class: 'network-status-header' }, [
      icon('database'),
      el('strong', { text: 'Trạng thái Kết nối & Động cơ CSDL' })
    ]),
    el('div', { id: 'network-ping-info', class: 'network-ping-info' }, [
      el('span', { class: 'spinner spinner-dark', style: 'display:inline-block; margin-right:6px;' }),
      'Đang kiểm tra kết nối...'
    ])
  ]);

  function updateSelection(local) {
    setPreferLocal(local);
    modalContainer.querySelectorAll('.network-mode-card').forEach(c => c.classList.remove('is-selected'));
    if (local) {
      modalContainer.querySelectorAll('.network-mode-card')[1]?.classList.add('is-selected');
    } else {
      modalContainer.querySelectorAll('.network-mode-card')[0]?.classList.add('is-selected');
    }
    runPingTest();
  }

  // Options radio group
  const modeGroup = el('div', { class: 'network-mode-options' }, [
    el('label', { class: `network-mode-card ${!preferLocal ? 'is-selected' : ''}` }, [
      el('input', {
        type: 'radio',
        name: 'dm_net_mode',
        value: 'auto',
        checked: !preferLocal,
        onchange: () => updateSelection(false)
      }),
      el('div', { class: 'mode-card-body' }, [
        el('div', { class: 'mode-card-title' }, [
          icon('wifi'),
          el('strong', { text: 'Chế độ Tự động (Mặc định)' })
        ]),
        el('p', { class: 'mode-card-desc', text: 'Ưu tiên Supabase PostgreSQL Cloud. Khi mạng yếu, rớt mạng hoặc phản hồi quá 10s, hệ thống tự động ngắt và chuyển sang đọc từ CSDL cục bộ SQLite ngay lập tức.' })
      ])
    ]),
    el('label', { class: `network-mode-card ${preferLocal ? 'is-selected' : ''}` }, [
      el('input', {
        type: 'radio',
        name: 'dm_net_mode',
        value: 'local',
        checked: preferLocal,
        onchange: () => updateSelection(true)
      }),
      el('div', { class: 'mode-card-body' }, [
        el('div', { class: 'mode-card-title' }, [
          icon('zap'),
          el('strong', { text: 'Luôn ưu tiên CSDL Cục bộ (Mạng yếu / Tốc độ cao)' })
        ]),
        el('p', { class: 'mode-card-desc', text: 'Bỏ qua hoàn toàn độ trễ mạng internet. Đọc dữ liệu trực tiếp từ CSDL nội bộ SQLite với tốc độ 0.01ms, không bị ảnh hưởng bởi mạng chập chờn hay đứt cáp quốc tế.' })
      ])
    ])
  ]);

  async function runPingTest() {
    const pingEl = modalContainer.querySelector('#network-ping-info');
    if (!pingEl) return;
    pingEl.innerHTML = '<span class="spinner spinner-dark" style="display:inline-block; margin-right:6px;"></span> Đang đo tốc độ phản hồi...';
    const data = await checkDatabaseHealth();

    const isSqlite = data.engine === 'sqlite';
    const latency = isSqlite ? (data.latency_ms || 0.01) : (data.latency_ms || data.client_ping_ms);
    const badgeHtml = isSqlite
      ? `<span class="badge badge-db-sqlite" style="font-weight:600;">⚡ SQLite Cục bộ (0.01ms - Siêu tốc)</span>`
      : `<span class="badge badge-db-supabase" style="font-weight:600;">☁️ Supabase Cloud (${latency}ms)</span>`;

    pingEl.innerHTML = `
      <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--muted)">Động cơ CSDL đang dùng:</span>
          ${badgeHtml}
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--muted)">Thời gian phản hồi CSDL:</span>
          <strong>${latency} ms</strong>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--muted)">Cơ chế Circuit Breaker:</span>
          <span>${data.breaker_active ? '<b style="color:var(--danger)">Đang kích hoạt (Tự động chuyển SQLite do mạng yếu)</b>' : '<b style="color:var(--success)">Bình thường</b>'}</span>
        </div>
      </div>
    `;
  }

  const actions = el('div', { class: 'modal-actions', style: 'justify-content: space-between; margin-top: 20px;' }, [
    el('button', {
      type: 'button',
      class: 'btn btn-secondary btn-sm',
      onclick: () => {
        try {
          Object.keys(localStorage).forEach(k => {
            if (k.startsWith('dm_cache_')) localStorage.removeItem(k);
          });
          announce('Đã xóa bộ nhớ đệm cache.');
          runPingTest();
        } catch (e) {}
      }
    }, [icon('check'), 'Làm mới bộ nhớ đệm']),
    el('div', { style: 'display:flex; gap:8px;' }, [
      el('button', {
        type: 'button',
        class: 'btn btn-secondary',
        onclick: () => runPingTest()
      }, 'Kiểm tra lại'),
      el('button', {
        type: 'button',
        class: 'btn btn-primary',
        onclick: () => modal.close()
      }, 'Hoàn tất')
    ])
  ]);

  modalContainer.append(statusBox, modeGroup, actions);

  const modal = openModal({
    title: 'Tối ưu Mạng & Ưu tiên Đọc Cơ sở Dữ liệu',
    content: modalContainer
  });

  runPingTest();
}

function navLink(iconName, label, href, active) {
  return el('a', { class: `nav-link${active ? ' is-active' : ''}`, href, 'aria-current': active ? 'page' : null }, [
    icon(iconName),
    el('span', { class: 'nav-label', text: label })
  ]);
}

export function loadingBlock(label = 'Đang tải dữ liệu...') {
  return el('div', { class: 'loading-block', role: 'status' }, [
    el('span', { class: 'spinner spinner-dark', 'aria-hidden': 'true' }),
    el('span', { text: label })
  ]);
}

export function bindTabs(tablist, onChange) {
  const tabs = [...tablist.querySelectorAll('[role="tab"]')];
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => onChange(tab.dataset.tab));
    tab.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
      event.preventDefault();
      const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : (index - 1 + tabs.length) % tabs.length;
      tabs[next].focus();
      onChange(tabs[next].dataset.tab);
    });
  });
}

export function openChangePasswordModal() {
  const curInput = el('input', { id: 'change-cur-pass', type: 'password', class: 'field-input', placeholder: 'Nhập mật khẩu hiện tại', required: true, autocomplete: 'current-password' });
  const newInput = el('input', { id: 'change-new-pass', type: 'password', class: 'field-input', placeholder: 'Tối thiểu 4 ký tự', required: true, autocomplete: 'new-password' });
  const confInput = el('input', { id: 'change-conf-pass', type: 'password', class: 'field-input', placeholder: 'Nhập lại mật khẩu mới', required: true, autocomplete: 'new-password' });

  const errorBox = el('p', { class: 'field-error', style: 'margin-bottom: 12px;' });
  function showErr(msg) {
    errorBox.textContent = msg;
    errorBox.style.display = 'block';
  }

  const saveBtn = el('button', { type: 'submit', class: 'btn btn-primary' }, [icon('check'), 'Lưu mật khẩu mới']);
  const cancelBtn = el('button', { type: 'button', class: 'btn btn-secondary', onclick: () => modal.close() }, 'Hủy');

  const form = el('form', { onsubmit: async (e) => {
    e.preventDefault();
    errorBox.textContent = '';
    errorBox.style.display = 'none';

    const currentPassword = curInput.value.trim();
    const newPassword = newInput.value.trim();
    const confirmPassword = confInput.value.trim();

    if (!currentPassword) {
      showErr('Vui lòng nhập mật khẩu hiện tại.');
      curInput.focus();
      return;
    }
    if (!newPassword || newPassword.length < 4) {
      showErr('Mật khẩu mới phải có tối thiểu 4 ký tự.');
      newInput.focus();
      return;
    }
    if (newPassword !== confirmPassword) {
      showErr('Mật khẩu xác nhận không khớp.');
      confInput.focus();
      return;
    }

    setBusy(saveBtn, true, 'Đang cập nhật...');
    try {
      const res = await api.changePassword({ currentPassword, newPassword, confirmPassword });
      toast(res.message || 'Đổi mật khẩu thành công!', 'success');
      modal.close();
    } catch (err) {
      showErr(err.message || 'Không thể đổi mật khẩu. Vui lòng kiểm tra lại.');
    } finally {
      setBusy(saveBtn, false);
    }
  }}, [
    errorBox,
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'change-cur-pass', text: 'Mật khẩu hiện tại *' }),
      curInput
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'change-new-pass', text: 'Mật khẩu mới *' }),
      newInput,
      el('p', { class: 'help', text: 'Mật khẩu an toàn từ 4 ký tự trở lên' })
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'change-conf-pass', text: 'Xác nhận mật khẩu mới *' }),
      confInput
    ]),
    el('div', { class: 'modal-actions', style: 'margin-top: 20px;' }, [
      cancelBtn,
      saveBtn
    ])
  ]);

  const modal = openModal({
    title: 'Đổi mật khẩu tài khoản',
    content: form
  });

  setTimeout(() => curInput.focus(), 50);
}

/**
 * Component phân trang chuẩn hóa cho toàn bộ DesignMark
 */
export function renderPagination({
  currentPage = 1,
  pageSize = 10,
  totalItems = 0,
  pageSizeOptions = [10, 20, 50, 100],
  showPageSizeSelect = true,
  showInfo = true,
  isCentered = false,
  itemLabel = 'mục',
  isStandalone = false,
  alwaysShow = false,
  onPageChange,
  onPageSizeChange
}) {
  if (totalItems <= 0 && !alwaysShow) return null;
  const totalPages = Math.ceil(totalItems / pageSize) || 1;

  // Không hiển thị phân trang dư thừa khi chỉ có một trang trừ khi có alwaysShow
  if (!alwaysShow && totalPages <= 1 && (!showPageSizeSelect || totalItems <= (pageSizeOptions[0] || pageSize))) {
    return null;
  }

  const startIdx = (currentPage - 1) * pageSize + 1;
  const endIdx = Math.min(currentPage * pageSize, totalItems);

  // Khối thông tin và chọn số lượng mỗi trang bên trái (nếu có bật showInfo)
  let infoEl = null;
  if (showInfo) {
    infoEl = el('div', { class: 'pagination-info' }, [
      el('span', { text: `Hiển thị ${startIdx} – ${endIdx} trên ` }),
      el('strong', { text: String(totalItems) }),
      el('span', { text: ` ${itemLabel}` })
    ]);

    if (showPageSizeSelect && pageSizeOptions && pageSizeOptions.length > 1 && typeof onPageSizeChange === 'function') {
      const sizeSelect = el('select', {
        class: 'pagination-size-select',
        'aria-label': `Số lượng ${itemLabel} mỗi trang`
      }, pageSizeOptions.map((opt) => el('option', {
        value: String(opt),
        text: `${opt} / trang`,
        selected: opt === pageSize ? 'true' : null
      })));

      sizeSelect.value = String(pageSize);
      sizeSelect.addEventListener('change', () => {
        const next = parseInt(sizeSelect.value, 10);
        if (next > 0 && next !== pageSize) {
          onPageSizeChange(next);
        }
      });

      infoEl.append(sizeSelect);
    }
  }

  // Khối nút điều khiển bên phải (Trước, các nút số trang 1, 2, 3... 10, Sau)
  const prevBtn = el('button', {
    type: 'button',
    class: 'pagination-btn pagination-prev',
    disabled: currentPage <= 1,
    'aria-label': 'Trang trước',
    onclick: () => {
      if (currentPage > 1 && typeof onPageChange === 'function') {
        onPageChange(currentPage - 1);
      }
    }
  }, ['« Trước']);

  const nextBtn = el('button', {
    type: 'button',
    class: 'pagination-btn pagination-next',
    disabled: currentPage >= totalPages,
    'aria-label': 'Trang sau',
    onclick: () => {
      if (currentPage < totalPages && typeof onPageChange === 'function') {
        onPageChange(currentPage + 1);
      }
    }
  }, ['Sau »']);

  // Thuật toán đánh số trang rút gọn dạng: 1 2 3 ... 10
  const pageItems = [];
  if (totalPages <= 7) {
    for (let i = 1; i <= totalPages; i++) {
      pageItems.push(i);
    }
  } else {
    if (currentPage <= 3) {
      pageItems.push(1, 2, 3, 4, '...', totalPages);
    } else if (currentPage >= totalPages - 2) {
      pageItems.push(1, '...', totalPages - 3, totalPages - 2, totalPages - 1, totalPages);
    } else {
      pageItems.push(1, '...', currentPage - 1, currentPage, currentPage + 1, '...', totalPages);
    }
  }

  const pageButtons = pageItems.map((item) => {
    if (item === '...') {
      return el('span', { class: 'pagination-ellipsis', 'aria-hidden': 'true' }, ['…']);
    }
    const p = Number(item);
    const isCur = p === currentPage;
    return el('button', {
      type: 'button',
      class: `pagination-btn ${isCur ? 'is-active' : ''}`,
      'aria-current': isCur ? 'page' : null,
      title: `Trang ${p}`,
      onclick: () => {
        if (!isCur && typeof onPageChange === 'function') {
          onPageChange(p);
        }
      }
    }, [String(p)]);
  });

  const controlsEl = el('div', { class: 'pagination-controls' }, [
    prevBtn,
    ...pageButtons,
    nextBtn
  ]);

  const shouldCenter = isCentered || !showInfo;

  return el('div', { class: `pagination-bar ${isStandalone ? 'is-standalone' : ''} ${shouldCenter ? 'is-centered' : ''}`.trim() }, [
    ...(infoEl ? [infoEl] : []),
    controlsEl
  ]);
}

export { roleLabel };

