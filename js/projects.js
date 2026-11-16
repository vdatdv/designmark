import { boot } from './app.js';
import { api } from './api.js';
import {
  avatarEl,
  confirmDialog,
  el,
  emptyState,
  fieldError,
  formatDay,
  icon,
  loadingBlock,
  openModal,
  renderPagination,
  setBusy,
  toast
} from './ui.js';

function roleBadge(role) {
  const map = {
    manager: { label: 'Quản lý', class: 'badge-role-manager' },
    reviewer: { label: 'Góp ý', class: 'badge-role-reviewer' },
    commenter: { label: 'Góp ý', class: 'badge-role-reviewer' },
    viewer: { label: 'Chỉ xem', class: 'badge-neutral badge-role-viewer' },
    admin_observer: { label: 'Giám sát hệ thống', class: 'badge-role role-admin_observer' }
  };
  const item = map[role] || { label: role || 'Thành viên', class: 'badge-neutral badge-role-viewer' };
  return el('span', { class: `badge ${item.class}` }, [el('span', { text: item.label })]);
}

function getProjectBadges(project, isOwner) {
  const categoryBadge = project.category
    ? el('span', { class: 'badge badge-category', text: project.category })
    : null;

  let roleBadges = [];
  if (!isOwner) {
    if (project.myRole === 'admin_observer') {
      roleBadges.push(roleBadge('admin_observer'));
    } else {
      roleBadges.push(el('span', { class: 'badge badge-invited', text: 'Được mời' }));
      if (project.myRole) {
        roleBadges.push(el('span', { class: 'badge-arrow', 'aria-hidden': 'true', text: '→' }));
        roleBadges.push(roleBadge(project.myRole));
      }
    }
  } else {
    roleBadges.push(roleBadge(project.myRole || 'manager'));
  }

  return { categoryBadge, roleBadges };
}

function openCreate(onCreated, existingCategories = []) {
  const name = el('input', { class: 'field-input', id: 'project-name', maxlength: '120', required: 'true', placeholder: 'Ví dụ: Ứng dụng Di động, Cửa hàng trực tuyến...' });
  
  // Tập hợp danh mục chuẩn + tự động cập nhật danh mục tùy chỉnh mà các dự án khác đã tạo
  const categorySet = new Set([
    'E-commerce',
    'Vận hành / Nội bộ',
    'Mobile App',
    'Website / Landing Page'
  ]);
  if (Array.isArray(existingCategories)) {
    existingCategories.forEach((cat) => {
      if (cat && typeof cat === 'string' && cat.trim() && cat.trim() !== 'Khác') {
        categorySet.add(cat.trim());
      }
    });
  }

  const categoryOptions = Array.from(categorySet).map((cat) => el('option', { value: cat, text: cat }));
  categoryOptions.push(el('option', { value: 'Khác', text: 'Khác (Tùy chỉnh danh mục...)' }));

  const categorySelect = el('select', { class: 'field-input field-select', id: 'project-category' }, categoryOptions);
  const categoryField = el('div', { class: 'field' }, [
    el('label', { class: 'field-label', for: 'project-category', text: 'Danh mục dự án *' }),
    categorySelect
  ]);

  const customCategory = el('input', {
    class: 'field-input',
    id: 'project-category-custom',
    placeholder: 'Nhập tên danh mục tùy chỉnh (ví dụ: Fintech, Giáo dục, CRM...)',
    maxlength: '50',
    style: 'margin-top: 8px;'
  });

  categorySelect.addEventListener('change', () => {
    if (categorySelect.value === 'Khác') {
      if (!categoryField.contains(customCategory)) {
        categoryField.append(customCategory);
      }
      customCategory.focus();
    } else {
      if (categoryField.contains(customCategory)) {
        customCategory.remove();
      }
      customCategory.value = '';
    }
  });

  const desc = el('textarea', { class: 'field-input', id: 'project-desc', maxlength: '500', rows: '3', placeholder: 'Mô tả ngắn gọn về mục tiêu và phạm vi thiết kế của dự án...' });
  const nameError = el('p', { class: 'field-error', id: 'project-name-error' });
  const submit = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Tạo dự án' });
  const form = el('form', {}, [
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'project-name', text: 'Tên dự án *' }),
      name,
      nameError
    ]),
    categoryField,
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'project-desc', text: 'Mô tả dự án' }),
      desc
    ]),
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submit
    ])
  ]);
  const modal = openModal({ title: 'Tạo dự án mới', content: form });
  name.focus();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    fieldError(name, '');
    if (!name.value.trim()) {
      fieldError(name, 'Tên dự án không được để trống.');
      name.focus();
      return;
    }
    if (categorySelect.value === 'Khác' && !customCategory.value.trim()) {
      fieldError(name, 'Vui lòng nhập tên danh mục tùy chỉnh khi chọn mục "Khác".');
      customCategory.focus();
      return;
    }
    setBusy(submit, true, 'Đang tạo...');
    try {
      const chosenCategory = (categorySelect.value === 'Khác' && customCategory.value.trim())
        ? customCategory.value.trim()
        : categorySelect.value;
      const project = await api.createProject({
        name: name.value.trim(),
        description: desc.value.trim(),
        category: chosenCategory
      });
      modal.close();
      toast('Đã tạo dự án thành công.', 'success');
      if (typeof onCreated === 'function') {
        onCreated(project);
      } else {
        location.href = `project.php?id=${encodeURIComponent(project.id)}`;
      }
    } catch (err) {
      setBusy(submit, false);
      fieldError(name, err.message);
      toast(err.message || 'Không tạo được dự án.', 'error');
    }
  });
}

// Render Grid View Mode
function renderGrid(projects, onDeleteProject, session) {
  const grid = el('div', { class: 'project-grid', id: 'projects-grid', 'data-testid': 'project-grid' });

  projects.forEach((project) => {
    const isOwner = project.isOwner || (project.createdBy === session?.userId);
    const creatorName = project.creator?.name || '';

    const stack = el('div', { class: 'avatar-stack', 'aria-label': `Thành viên: ${(project.members || []).map((m) => m.name).join(', ')}` });
    (project.members || []).slice(0, 4).forEach((member) => stack.append(avatarEl(member, 24)));

    const canDelete = project.myRole === 'manager';
    const deleteBtn = canDelete && onDeleteProject ? el('button', {
      type: 'button',
      class: 'btn-card-action',
      title: 'Chuyển vào thùng rác',
      'aria-label': `Chuyển dự án ${project.name} vào thùng rác`,
      onclick: (e) => {
        e.preventDefault();
        e.stopPropagation();
        onDeleteProject(project);
      }
    }, [icon('trash')]) : null;

    const { categoryBadge, roleBadges } = getProjectBadges(project, isOwner);

    const tagsGroup = el('div', { class: 'project-card-tags-group' }, [
      categoryBadge ? el('div', { class: 'project-card-category-row' }, [categoryBadge]) : null,
      roleBadges.length ? el('div', { class: 'project-card-roles-row' }, roleBadges) : null
    ].filter(Boolean));

    const footerMeta = el('div', { class: 'project-card-footer-info' }, [
      isOwner
        ? el('div', { class: 'project-card-footer-meta' }, [
            el('span', { class: 'project-card-creator-label', text: 'Dự án của bạn' }),
            el('span', { class: 'project-card-footer-date', text: `Cập nhật ${formatDay(project.updatedAt)}` })
          ])
        : el('div', { class: 'project-card-footer-meta' }, [
            el('div', { class: 'project-card-creator', title: `Dự án được tạo bởi ${creatorName}` }, [
              el('span', { class: 'project-card-creator-label', text: 'Tạo bởi: ' }),
              el('strong', { class: 'project-card-creator-name', text: creatorName || 'Đồng nghiệp' })
            ]),
            el('span', { class: 'project-card-footer-date', text: formatDay(project.updatedAt) })
          ])
    ]);

    let thumbNode;
    if (project.coverImageKey) {
      const coverUrl = `api/image.php?key=${encodeURIComponent(project.coverImageKey)}`;
      thumbNode = el('div', { class: 'project-cover-wrap' }, [
        el('img', {
          src: coverUrl,
          alt: `Ảnh thiết kế ${project.name}`,
          class: 'project-cover-img',
          loading: 'lazy'
        }),
        el('span', { class: 'project-cover-badge' }, [
          icon('grid'),
          el('span', { text: `${project.screenCount || 0} màn hình` })
        ])
      ]);
    } else {
      thumbNode = el('div', { class: 'project-thumb-artboard' }, [
        el('span', { class: 'thumb-pin' }, [el('span', { text: '📍' })]),
        icon('grid'),
        el('span', { text: `${project.screenCount || 0} màn hình thiết kế` })
      ]);
    }
    const thumbWrap = el('div', { class: 'project-card-thumb' }, [thumbNode]);

    grid.append(el('a', {
      class: 'card project-card',
      href: `project.php?id=${encodeURIComponent(project.id)}`,
      'aria-label': `Mở xem dự án ${project.name}`,
      title: `Nhấp hoặc chạm để mở dự án ${project.name}`
    }, [
      thumbWrap,
      el('div', { class: 'card-body' }, [
        el('div', { class: 'project-card-header' }, [
          el('div', { class: 'project-card-title-row' }, [
            el('h2', { text: project.name }),
            deleteBtn
          ].filter(Boolean)),
          (categoryBadge || roleBadges.length) ? tagsGroup : null
        ]),
        el('p', { text: project.description || 'Chưa có mô tả chi tiết.', class: 'project-desc-clamp' }),
        el('div', { class: 'project-card-stats' }, [
          el('span', { class: 'stat-pill' }, [
            icon('grid'),
            el('span', { text: `${project.screenCount || 0} màn hình` })
          ]),
          el('span', { class: 'stat-pill' }, [
            icon('message'),
            el('span', { text: `${project.commentCount || 0} góp ý` })
          ]),
          el('span', { class: `stat-pill${(project.resolvedCount || 0) > 0 ? ' is-resolved' : ''}` }, [
            icon('check'),
            el('span', { text: `${project.resolvedCount || 0} đã xử lý` })
          ]),
          el('span', { class: `stat-pill${project.openCount > 0 ? ' is-open' : ''}` }, [
            icon('clock'),
            el('span', { text: `${project.openCount || 0} chưa xử lý` })
          ])
        ]),
        el('div', { class: 'project-card-footer' }, [
          footerMeta,
          stack
        ])
      ])
    ]));
  });

  return grid;
}

// Render List View Mode
function renderListView(projects, onDeleteProject, session) {
  const container = el('div', { class: 'project-list-view', id: 'projects-list', 'data-testid': 'project-list' });

  projects.forEach((project) => {
    const isOwner = project.isOwner || (project.createdBy === session?.userId);
    const creatorName = project.creator?.name || '';

    const stack = el('div', {
      class: 'avatar-stack',
      'aria-label': `Thành viên: ${(project.members || []).map((m) => m.name).join(', ')}`
    });
    (project.members || []).slice(0, 4).forEach((member) => stack.append(avatarEl(member, 26)));

    const canDelete = project.myRole === 'manager';
    const deleteBtn = canDelete && onDeleteProject ? el('button', {
      type: 'button',
      class: 'btn-card-action',
      title: 'Chuyển vào thùng rác',
      'aria-label': `Chuyển dự án ${project.name} vào thùng rác`,
      onclick: (e) => {
        e.preventDefault();
        e.stopPropagation();
        onDeleteProject(project);
      }
    }, [icon('trash')]) : null;

    const { categoryBadge, roleBadges } = getProjectBadges(project, isOwner);

    const openCount = project.openCount || 0;
    const itemEl = el('a', {
      class: 'project-list-item',
      href: `project.php?id=${encodeURIComponent(project.id)}`,
      'aria-label': `Mở xem dự án ${project.name}`,
      title: `Nhấp để mở dự án ${project.name}`
    }, [
      el('div', { class: 'project-list-main' }, [
        el('div', { class: 'project-list-icon' }, [icon('folder')]),
        el('div', { class: 'project-list-info' }, [
          el('div', { class: 'project-list-title-row' }, [
            el('h2', { text: project.name }),
            categoryBadge,
            ...roleBadges
          ].filter(Boolean)),
          el('p', { class: 'project-list-desc', text: project.description || 'Chưa có mô tả chi tiết.' })
        ])
      ]),
      el('div', { class: 'project-list-meta' }, [
        el('div', { class: 'project-list-stats' }, [
          el('div', { class: 'project-list-stats-row' }, [
            el('span', { class: 'stat-pill' }, [icon('grid'), el('span', { text: `${project.screenCount || 0} màn hình` })]),
            el('span', { class: 'stat-pill' }, [icon('message'), el('span', { text: `${project.commentCount || 0} góp ý` })])
          ]),
          el('div', { class: 'project-list-stats-row' }, [
            el('span', { class: `stat-pill${(project.resolvedCount || 0) > 0 ? ' is-resolved' : ''}` }, [
              icon('check'),
              el('span', { text: `${project.resolvedCount || 0} đã xử lý` })
            ]),
            el('span', { class: `stat-pill${openCount > 0 ? ' is-open' : ''}` }, [
              icon('clock'),
              el('span', { text: `${openCount} chưa xử lý` })
            ])
          ])
        ]),
        stack,
        el('div', { class: 'project-list-date' }, [
          el('span', { text: isOwner ? formatDay(project.updatedAt) : (creatorName || formatDay(project.updatedAt)) })
        ]),
        deleteBtn,
        el('span', { class: 'icon-btn', style: 'border: none; background: transparent; color: var(--muted); padding: 0;' }, [icon('chevron')])
      ].filter(Boolean))
    ]);

    container.append(itemEl);
  });

  return container;
}

// Render Trash Grid Mode
function renderTrashGrid(projects, onRestoreProject, onPermanentDeleteProject) {
  const grid = el('div', { class: 'project-grid', id: 'trash-grid', 'data-testid': 'trash-grid' });

  projects.forEach((project) => {
    const daysLeft = typeof project.daysRemaining === 'number' ? project.daysRemaining : 30;
    const isUrgent = daysLeft <= 3;
    const daysBadge = el('span', {
      class: 'badge',
      style: isUrgent
        ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600;'
        : 'background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight: 600;'
    }, [
      icon('clock'),
      el('span', { text: daysLeft <= 1 ? 'Xóa vĩnh viễn hôm nay' : `Tự động xóa sau ${daysLeft} ngày` })
    ]);

    const restoreBtn = el('button', {
      type: 'button',
      class: 'btn btn-secondary btn-sm',
      style: 'display: inline-flex; align-items: center; gap: 5px;',
      title: 'Khôi phục dự án này về danh sách hoạt động',
      onclick: (e) => {
        e.preventDefault();
        onRestoreProject(project);
      }
    }, [icon('check'), 'Khôi phục']);

    const permanentDeleteBtn = el('button', {
      type: 'button',
      class: 'btn btn-danger-soft btn-sm',
      style: 'display: inline-flex; align-items: center; gap: 5px; color: #b91c1c;',
      title: 'Xóa vĩnh viễn ngay lập tức (không thể hoàn tác)',
      onclick: (e) => {
        e.preventDefault();
        onPermanentDeleteProject(project);
      }
    }, [icon('trash'), 'Xóa vĩnh viễn']);

    grid.append(el('div', {
      class: 'card project-card project-card-trashed',
      style: 'border: 1px dashed #cbd5e1; background: #fafafa;'
    }, [
      el('div', { class: 'card-body' }, [
        el('div', { class: 'project-card-header', style: 'display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;' }, [
          el('h2', { text: project.name, style: 'margin: 0; font-size: 1.15rem; color: var(--heading);' }),
          daysBadge
        ]),
        el('p', { text: project.description || 'Chưa có mô tả chi tiết.', class: 'project-desc-clamp' }),
        el('div', { class: 'meta-row' }, [
          el('span', { class: 'badge-meta', text: `${project.screenCount || 0} màn hình` }),
          el('span', { class: 'badge-meta', text: `${project.commentCount || 0} góp ý` }),
          el('span', { class: 'badge-meta', style: 'color: var(--muted);', text: `Đã xóa: ${formatDay(project.deletedAt)}` })
        ]),
        el('div', {
          class: 'meta-row project-card-footer',
          style: 'justify-content: flex-end; gap: 8px; margin-top: 14px; border-top: 1px solid var(--border); padding-top: 10px;'
        }, [
          restoreBtn,
          permanentDeleteBtn
        ])
      ])
    ]));
  });

  return grid;
}

// Render Trash List Mode
function renderTrashListView(projects, onRestoreProject, onPermanentDeleteProject) {
  const container = el('div', { class: 'project-list-view', id: 'trash-list', 'data-testid': 'trash-list' });

  projects.forEach((project) => {
    const daysLeft = typeof project.daysRemaining === 'number' ? project.daysRemaining : 30;
    const isUrgent = daysLeft <= 3;
    const daysBadge = el('span', {
      class: 'badge',
      style: isUrgent
        ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600;'
        : 'background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight: 600;'
    }, [
      icon('clock'),
      el('span', { text: daysLeft <= 1 ? 'Xóa vĩnh viễn hôm nay' : `Tự động xóa sau ${daysLeft} ngày` })
    ]);

    const restoreBtn = el('button', {
      type: 'button',
      class: 'btn btn-secondary btn-sm',
      style: 'display: inline-flex; align-items: center; gap: 5px;',
      title: 'Khôi phục dự án này về danh sách hoạt động',
      onclick: (e) => {
        e.preventDefault();
        onRestoreProject(project);
      }
    }, [icon('check'), 'Khôi phục']);

    const permanentDeleteBtn = el('button', {
      type: 'button',
      class: 'btn btn-danger-soft btn-sm',
      style: 'display: inline-flex; align-items: center; gap: 5px; color: #b91c1c;',
      title: 'Xóa vĩnh viễn ngay lập tức (không thể hoàn tác)',
      onclick: (e) => {
        e.preventDefault();
        onPermanentDeleteProject(project);
      }
    }, [icon('trash'), 'Xóa vĩnh viễn']);

    const itemEl = el('div', {
      class: 'project-list-item'
    }, [
      el('div', { class: 'project-list-main' }, [
        el('div', { class: 'project-list-icon', style: 'background: rgba(239, 68, 68, 0.1); color: #ef4444;' }, [icon('trash')]),
        el('div', { class: 'project-list-info' }, [
          el('div', { class: 'project-list-title-row' }, [
            el('h2', { text: project.name, style: 'text-decoration: line-through; opacity: 0.75;' }),
            daysBadge
          ]),
          el('p', { class: 'project-list-desc', text: project.description || 'Chưa có mô tả chi tiết.' })
        ])
      ]),
      el('div', { class: 'project-list-meta' }, [
        el('div', { class: 'project-list-actions', style: 'display: flex; gap: 8px;' }, [
          restoreBtn,
          permanentDeleteBtn
        ])
      ])
    ]);

    container.append(itemEl);
  });

  return container;
}

// Helper trích xuất danh mục và tag nghiệp vụ từ dữ liệu dự án
function getProjectTags(project) {
  const tags = [];
  // 1. Tag nghiệp vụ đặc biệt cho Quản trị viên
  if (project.myRole === 'admin_observer') {
    tags.push('Giám sát hệ thống');
  }
  // 2. Danh mục chính thức của dự án
  if (project.category && project.category.trim()) {
    tags.push(project.category.trim());
  }
  // 3. Tags mảng nếu có sẵn
  if (Array.isArray(project.tags)) {
    project.tags.forEach((t) => {
      if (t && !tags.includes(t)) tags.push(t);
    });
  }
  // 4. Hashtag #tag hoặc [tag] trong tên và mô tả
  const text = `${project.name || ''} ${project.description || ''}`;
  const matches = text.match(/#([\w\p{L}-]+)|\[([\w\p{L}\s-]+)\]/gu);
  if (matches) {
    matches.forEach((m) => {
      const clean = m.replace(/[#\[\]]/g, '').trim();
      if (clean && !tags.includes(clean)) tags.push(clean);
    });
  }
  // 5. Phân loại theo từ khóa nghiệp vụ thực tế (nếu chưa có tag tương ứng)
  const lower = text.toLowerCase();
  if (lower.includes('thương mại') || lower.includes('shop') || lower.includes('cửa hàng') || lower.includes('e-commerce') || lower.includes('bán hàng')) {
    if (!tags.includes('E-commerce')) tags.push('E-commerce');
  }
  if (lower.includes('bảng điều khiển') || lower.includes('dashboard') || lower.includes('vận hành') || lower.includes('nội bộ')) {
    if (!tags.includes('Vận hành / Nội bộ')) tags.push('Vận hành / Nội bộ');
  }
  if (lower.includes('mobile') || lower.includes('di động') || lower.includes('app') || lower.includes('ứng dụng')) {
    if (!tags.includes('Mobile App')) tags.push('Mobile App');
  }
  if (lower.includes('landing') || lower.includes('website') || lower.includes('web')) {
    if (!tags.includes('Website / Landing Page') && !tags.includes('Mobile App')) tags.push('Website / Landing Page');
  }
  return tags;
}

// Active Filter Chips Builder
function renderFilterChips(searchQuery, filterTag, filterStatus, filterRole, onClearSearch, onClearTag, onClearStatus, onClearRole, onClearAll) {
  const chips = [];

  if (searchQuery) {
    chips.push(el('div', { class: 'filter-chip' }, [
      el('span', { text: `Từ khóa: "${searchQuery}"` }),
      el('button', {
        type: 'button',
        class: 'filter-chip-remove',
        title: 'Xóa từ khóa',
        'aria-label': 'Xóa từ khóa tìm kiếm',
        onclick: onClearSearch
      }, [icon('close')])
    ]));
  }

  if (filterTag && filterTag !== 'all') {
    chips.push(el('div', { class: 'filter-chip' }, [
      el('span', { text: `Danh mục: ${filterTag}` }),
      el('button', {
        type: 'button',
        class: 'filter-chip-remove',
        title: 'Bỏ lọc danh mục',
        'aria-label': 'Bỏ lọc danh mục',
        onclick: onClearTag
      }, [icon('close')])
    ]));
  }

  if (filterStatus && filterStatus !== 'all') {
    const statusLabels = {
      open: 'Còn góp ý chưa xử lý',
      resolved: 'Đã xử lý xong'
    };
    chips.push(el('div', { class: 'filter-chip' }, [
      el('span', { text: `Trạng thái: ${statusLabels[filterStatus] || filterStatus}` }),
      el('button', {
        type: 'button',
        class: 'filter-chip-remove',
        title: 'Bỏ lọc trạng thái việc',
        'aria-label': 'Bỏ lọc trạng thái việc',
        onclick: onClearStatus
      }, [icon('close')])
    ]));
  }

  if (filterRole && filterRole !== 'all') {
    const roleLabels = {
      manager: 'Quản lý',
      commenter: 'Góp ý',
      viewer: 'Chỉ xem',
      admin_observer: 'Giám sát hệ thống'
    };
    chips.push(el('div', { class: 'filter-chip' }, [
      el('span', { text: `Quyền: ${roleLabels[filterRole] || filterRole}` }),
      el('button', {
        type: 'button',
        class: 'filter-chip-remove',
        title: 'Bỏ lọc theo quyền',
        'aria-label': 'Bỏ lọc theo quyền',
        onclick: onClearRole
      }, [icon('close')])
    ]));
  }

  if (!chips.length) {
    return el('div', { class: 'filter-chips-row', style: 'display: none;' });
  }

  return el('div', { class: 'filter-chips-row' }, [
    el('span', { class: 'filter-chips-label', text: 'Đang lọc:' }),
    ...chips,
    el('button', {
      type: 'button',
      class: 'btn-clear-all-filters',
      text: 'Xóa tất cả bộ lọc',
      onclick: onClearAll
    })
  ]);
}

boot({
  active: 'projects',
  title: 'Danh sách dự án',
  async render({ session, setBreadcrumb }) {
    setBreadcrumb([
      { label: 'DesignMark', href: 'dashboard.php' },
      { label: 'Danh sách dự án' }
    ]);
    const page = document.getElementById('page');
    page.replaceChildren(loadingBlock('Đang tải danh sách dự án...'));

    // State Variables (Restore filters and query from URL for back/forward retention)
    const urlParams = new URLSearchParams(location.search);
    let currentTab = urlParams.get('tab') || 'all'; // 'all' | 'mine' | 'invited' | 'trash'
    let activeProjects = [];
    let trashProjects = [];
    let searchQuery = urlParams.get('q') || '';
    let filterTag = urlParams.get('tag') || 'all';
    let filterStatus = urlParams.get('status') || 'all';
    let filterRole = urlParams.get('role') || 'all';
    let sortMode = urlParams.get('sort') || 'updated-desc';
    let viewMode = localStorage.getItem('designmark.projects_view_mode') || 'grid';
    let currentPage = parseInt(urlParams.get('page') || '1', 10);
    let currentLimit = parseInt(urlParams.get('limit') || (viewMode === 'grid' ? '12' : '10'), 10);

    function updateUrlState() {
      const url = new URL(location.href);
      if (currentPage > 1) {
        url.searchParams.set('page', String(currentPage));
      } else {
        url.searchParams.delete('page');
      }
      if (currentTab && currentTab !== 'all') {
        url.searchParams.set('tab', currentTab);
      } else {
        url.searchParams.delete('tab');
      }
      if (searchQuery) {
        url.searchParams.set('q', searchQuery);
      } else {
        url.searchParams.delete('q');
      }
      if (filterRole && filterRole !== 'all') {
        url.searchParams.set('role', filterRole);
      } else {
        url.searchParams.delete('role');
      }
      if (filterStatus && filterStatus !== 'all') {
        url.searchParams.set('status', filterStatus);
      } else {
        url.searchParams.delete('status');
      }
      if (filterTag && filterTag !== 'all') {
        url.searchParams.set('tag', filterTag);
      } else {
        url.searchParams.delete('tag');
      }
      if (sortMode && sortMode !== 'updated-desc') {
        url.searchParams.set('sort', sortMode);
      } else {
        url.searchParams.delete('sort');
      }
      const defaultLimit = viewMode === 'grid' ? 12 : 10;
      if (currentLimit !== defaultLimit) {
        url.searchParams.set('limit', String(currentLimit));
      } else {
        url.searchParams.delete('limit');
      }
      history.replaceState(null, '', url.toString());
    }

    async function loadData() {
      try {
        const [activeList, trashList] = await Promise.all([
          api.listProjects(),
          api.listTrashProjects().catch(() => [])
        ]);
        activeProjects = activeList || [];
        trashProjects = trashList || [];
      } catch (err) {
        throw err;
      }
    }

    // Logic xử lý lọc đồng thời và sắp xếp dữ liệu
    function getFilteredAndSorted() {
      let source = [];
      if (currentTab === 'all') {
        source = activeProjects;
      } else if (currentTab === 'mine') {
        source = activeProjects.filter((p) => p.isOwner || (p.createdBy === session.userId));
      } else if (currentTab === 'invited') {
        source = activeProjects.filter((p) => !p.isOwner && (p.createdBy !== session.userId));
      } else if (currentTab === 'trash') {
        source = trashProjects;
      }

      let filtered = source.filter((p) => {
        // 1. Lọc theo từ khóa tìm kiếm (tên hoặc mô tả)
        if (searchQuery) {
          const q = searchQuery.toLowerCase();
          const matchName = (p.name || '').toLowerCase().includes(q);
          const matchDesc = (p.description || '').toLowerCase().includes(q);
          if (!matchName && !matchDesc) return false;
        }

        // 2. Lọc theo Danh mục / Tag nghiệp vụ (không lọc theo vai trò)
        if (filterTag && filterTag !== 'all') {
          const pTags = getProjectTags(p);
          if (!pTags.includes(filterTag)) return false;
        }

        // 3. Lọc theo Trạng thái việc (Góp ý chưa xử lý)
        if (filterStatus && filterStatus !== 'all') {
          const openCount = p.openCount || 0;
          if (filterStatus === 'open' && openCount <= 0) return false;
          if (filterStatus === 'resolved' && openCount > 0) return false;
        }

        // 4. Lọc theo Quyền trong dự án (Quản lý, Góp ý, Chỉ xem)
        if (filterRole && filterRole !== 'all') {
          const role = p.myRole || (p.isOwner || (p.createdBy === session.userId) ? 'manager' : 'viewer');
          if (filterRole === 'manager') {
            if (role !== 'manager' && role !== 'owner') return false;
          } else if (filterRole === 'commenter') {
            if (role !== 'commenter' && role !== 'reviewer') return false;
          } else if (filterRole === 'viewer') {
            if (role !== 'viewer' && role !== 'admin_observer') return false;
          } else if (filterRole === 'admin_observer') {
            if (role !== 'admin_observer') return false;
          }
        }

        return true;
      });

      // Sắp xếp dữ liệu theo lựa chọn
      filtered.sort((a, b) => {
        if (currentTab === 'trash') {
          return (a.daysRemaining || 30) - (b.daysRemaining || 30);
        }
        if (sortMode === 'updated-desc') return new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime();
        if (sortMode === 'open-desc') return (b.openCount || 0) - (a.openCount || 0);
        if (sortMode === 'name-asc') return (a.name || '').localeCompare(b.name || '');
        if (sortMode === 'name-desc') return (b.name || '').localeCompare(a.name || '');
        if (sortMode === 'comments-desc') return (b.commentCount || 0) - (a.commentCount || 0);
        if (sortMode === 'screens-desc') return (b.screenCount || 0) - (a.screenCount || 0);
        if (sortMode === 'updated-asc') return new Date(a.updatedAt).getTime() - new Date(b.updatedAt).getTime();
        return 0;
      });

      return filtered;
    }

    function renderView() {
      const items = getFilteredAndSorted();

      // Counts cho các tab pills
      const allCount = activeProjects.length;
      const mineCount = activeProjects.filter((p) => p.isOwner || (p.createdBy === session.userId)).length;
      const invitedCount = activeProjects.filter((p) => !p.isOwner && (p.createdBy !== session.userId)).length;
      const trashCount = trashProjects.length;

      // Top Header
      const header = el('div', { class: 'page-head' }, [
        el('div', {}, [
          el('h1', { text: 'Danh sách dự án', style: 'font-size: 1.5rem; margin-bottom: 4px;' }),
          el('p', { class: 'lede', text: 'Tìm kiếm, truy cập và quản lý các dự án thiết kế bạn được cấp quyền.' })
        ]),
        currentTab !== 'trash' ? el('button', {
          type: 'button',
          class: 'btn btn-primary',
          'data-testid': 'create-project',
          onclick: () => openCreate(() => {
            loadData().then(renderView);
          }, activeProjects.map((p) => p.category))
        }, [icon('plus'), el('span', { text: 'Tạo dự án mới' })]) : null
      ].filter(Boolean));

      // Tab Pills (Tất cả, Của tôi, Được mời, Thùng rác)
      function createTabBtn(key, label, iconName, count, isDangerBadge = false) {
        const isActive = currentTab === key;
        const badge = el('span', {
          class: 'badge badge-meta',
          style: `margin-left: 6px; ${isDangerBadge && count > 0 ? 'background: #fee2e2; color: #b91c1c;' : ''}`
        }, `${count}`);

        return el('button', {
          type: 'button',
          class: `btn ${isActive ? 'btn-primary' : 'btn-secondary'} btn-sm`,
          style: 'display: inline-flex; align-items: center; border-radius: 20px; padding: 6px 14px; white-space: nowrap;',
          onclick: () => {
            if (currentTab === key) return;
            currentTab = key;
            searchQuery = '';
            filterTag = 'all';
            filterStatus = 'all';
            filterRole = 'all';
            currentPage = 1;
            updateUrlState();
            renderView();
          }
        }, [icon(iconName), el('span', { text: label }), badge]);
      }

      const tabsBar = el('div', {
        class: 'projects-tabs-bar',
        style: 'display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;'
      }, [
        createTabBtn('all', 'Tất cả dự án', 'folder', allCount),
        createTabBtn('mine', 'Dự án của tôi', 'square', mineCount),
        createTabBtn('invited', 'Được mời tham gia', 'users', invitedCount),
        createTabBtn('trash', 'Thùng rác', 'trash', trashCount, true)
      ]);

      // Upgraded Toolbar Components — Single Row Layout
      // 1. Ô tìm kiếm vừa phải (w-64 đến w-72 ~270px) có kính lúp và nút X
      const searchInput = el('input', {
        type: 'text',
        class: 'field-input',
        placeholder: currentTab === 'trash' ? 'Tìm trong thùng rác...' : 'Tìm tên hoặc mô tả...',
        value: searchQuery,
        oninput: (e) => {
          searchQuery = e.target.value.trim();
          clearSearchBtn.style.display = searchQuery ? 'flex' : 'none';
          currentPage = 1;
          updateUrlState();
          renderGridContainer();
        }
      });

      const clearSearchBtn = el('button', {
        type: 'button',
        class: 'search-clear-btn',
        title: 'Xóa từ khóa',
        style: searchQuery ? 'display: flex;' : 'display: none;',
        onclick: () => {
          searchQuery = '';
          searchInput.value = '';
          clearSearchBtn.style.display = 'none';
          searchInput.focus();
          currentPage = 1;
          updateUrlState();
          renderGridContainer();
        }
      }, [icon('close')]);

      const searchWrap = el('div', { class: 'projects-search-wrap' }, [
        icon('search'),
        searchInput,
        clearSearchBtn
      ]);

      // 2. Dropdown lọc theo Danh mục / Tag nghiệp vụ (Placeholder: Tất cả danh mục)
      const availableTags = new Set();
      activeProjects.forEach((p) => {
        getProjectTags(p).forEach((t) => availableTags.add(t));
      });
      if (activeProjects.some((p) => p.myRole === 'admin_observer')) {
        availableTags.add('Giám sát hệ thống');
      }

      const tagOptions = [
        el('option', { value: 'all', text: 'Tất cả danh mục', selected: filterTag === 'all' })
      ];
      Array.from(availableTags).sort().forEach((t) => {
        tagOptions.push(el('option', { value: t, text: t, selected: filterTag === t }));
      });

      const tagSelect = currentTab !== 'trash' ? el('select', {
        class: 'field-input field-select projects-toolbar-select select-category',
        onchange: (e) => {
          filterTag = e.target.value;
          currentPage = 1;
          updateUrlState();
          renderGridContainer();
        }
      }, tagOptions) : null;

      // 3. Dropdown lọc theo Trạng thái việc
      const statusSelect = currentTab !== 'trash' ? el('select', {
        class: 'field-input field-select projects-toolbar-select select-status',
        onchange: (e) => {
          filterStatus = e.target.value;
          currentPage = 1;
          updateUrlState();
          renderGridContainer();
        }
      }, [
        el('option', { value: 'all', text: 'Tất cả trạng thái', selected: filterStatus === 'all' }),
        el('option', { value: 'open', text: 'Còn góp ý chưa xử lý', selected: filterStatus === 'open' }),
        el('option', { value: 'resolved', text: 'Đã xử lý xong', selected: filterStatus === 'resolved' })
      ]) : null;

      // 4. Dropdown lọc theo Quyền trong dự án
      const roleOptions = [
        el('option', { value: 'all', text: 'Tất cả quyền', selected: filterRole === 'all' }),
        el('option', { value: 'manager', text: 'Quản lý', selected: filterRole === 'manager' }),
        el('option', { value: 'commenter', text: 'Góp ý', selected: filterRole === 'commenter' }),
        el('option', { value: 'viewer', text: 'Chỉ xem', selected: filterRole === 'viewer' })
      ];
      if (activeProjects.some((p) => p.myRole === 'admin_observer')) {
        roleOptions.push(el('option', { value: 'admin_observer', text: 'Giám sát hệ thống', selected: filterRole === 'admin_observer' }));
      }

      const roleSelect = currentTab !== 'trash' ? el('select', {
        class: 'field-input field-select projects-toolbar-select select-role',
        onchange: (e) => {
          filterRole = e.target.value;
          currentPage = 1;
          updateUrlState();
          renderGridContainer();
        }
      }, roleOptions) : null;

      const toolbarLeft = el('div', { class: 'projects-toolbar-left' }, [
        searchWrap,
        tagSelect,
        statusSelect,
        roleSelect
      ].filter(Boolean));

      // 4. Dropdown Sắp xếp
      const sortSelect = currentTab !== 'trash' ? el('select', {
        class: 'field-input field-select projects-toolbar-select select-sort',
        onchange: (e) => {
          sortMode = e.target.value;
          currentPage = 1;
          updateUrlState();
          renderGridContainer();
        }
      }, [
        el('option', { value: 'updated-desc', text: 'Mới cập nhật nhất', selected: sortMode === 'updated-desc' }),
        el('option', { value: 'open-desc', text: 'Nhiều việc tồn đọng nhất', selected: sortMode === 'open-desc' }),
        el('option', { value: 'name-asc', text: 'Tên A-Z', selected: sortMode === 'name-asc' }),
        el('option', { value: 'name-desc', text: 'Tên Z-A', selected: sortMode === 'name-desc' }),
        el('option', { value: 'comments-desc', text: 'Nhiều góp ý nhất', selected: sortMode === 'comments-desc' }),
        el('option', { value: 'screens-desc', text: 'Nhiều màn hình nhất', selected: sortMode === 'screens-desc' })
      ]) : null;

      // 5. Nút chuyển chế độ xem View Mode (Grid / List)
      const gridBtn = el('button', {
        type: 'button',
        class: `view-mode-btn ${viewMode === 'grid' ? 'is-active' : ''}`,
        title: 'Chế độ xem lưới (Grid)',
        'aria-label': 'Chế độ xem lưới',
        onclick: () => {
          if (viewMode === 'grid') return;
          viewMode = 'grid';
          currentLimit = 12;
          currentPage = 1;
          localStorage.setItem('designmark.projects_view_mode', 'grid');
          gridBtn.classList.add('is-active');
          listBtn.classList.remove('is-active');
          updateUrlState();
          renderGridContainer();
        }
      }, [icon('grid')]);

      const listBtn = el('button', {
        type: 'button',
        class: `view-mode-btn ${viewMode === 'list' ? 'is-active' : ''}`,
        title: 'Chế độ xem danh sách (List)',
        'aria-label': 'Chế độ xem danh sách',
        onclick: () => {
          if (viewMode === 'list') return;
          viewMode = 'list';
          currentLimit = 10;
          currentPage = 1;
          localStorage.setItem('designmark.projects_view_mode', 'list');
          listBtn.classList.add('is-active');
          gridBtn.classList.remove('is-active');
          updateUrlState();
          renderGridContainer();
        }
      }, [icon('list')]);

      const viewModeToggle = el('div', { class: 'view-mode-toggle', role: 'group', 'aria-label': 'Chế độ hiển thị' }, [
        gridBtn,
        listBtn
      ]);

      // 6. Số lượng dự án dạng text phụ tách rời
      const countLabel = el('span', {
        id: 'projects-count-label',
        class: 'projects-count-pill'
      }, `${items.length} dự án`);

      const toolbarRight = el('div', { class: 'projects-toolbar-right' }, [
        sortSelect,
        viewModeToggle,
        countLabel
      ].filter(Boolean));

      // Thanh công cụ 1 hàng ngang duy nhất (Single Row)
      const toolbar = el('div', { class: 'projects-toolbar' }, [
        toolbarLeft,
        toolbarRight
      ]);

      // Hàng Filter Chips
      const chipsContainer = el('div', { id: 'filter-chips-container' });

      // Thông báo thùng rác
      const trashNotice = currentTab === 'trash' ? el('div', {
        class: 'trash-notice-banner',
        style: 'background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; display: flex; align-items: center; gap: 10px;'
      }, [
        icon('alert'),
        el('span', { text: 'Các dự án trong thùng rác sẽ được lưu trữ trong 30 ngày. Sau 30 ngày hệ thống sẽ tự động dọn sạch và xóa vĩnh viễn.' })
      ]) : null;

      const gridContainer = el('div', { id: 'grid-container' });

      // Move project to trash
      async function confirmMoveToTrash(project) {
        const confirmed = await confirmDialog({
          title: 'Chuyển dự án vào thùng rác',
          message: `Bạn có chắc chắn muốn chuyển dự án "${project.name}" vào thùng rác? Dự án sẽ tự động bị xóa vĩnh viễn sau 30 ngày nếu không khôi phục.`,
          confirmText: 'Chuyển vào thùng rác',
          cancelText: 'Hủy bỏ',
          danger: true
        });
        if (!confirmed) return;

        try {
          const res = await api.deleteProject(project.id, false);
          toast(res.message || 'Đã chuyển dự án vào thùng rác.', 'info');
          await loadData();
          renderView();
        } catch (err) {
          toast(err.message || 'Lỗi khi chuyển vào thùng rác.', 'error');
        }
      }

      // Restore project from trash
      async function handleRestoreProject(project) {
        try {
          const res = await api.restoreProject(project.id);
          toast(res.message || 'Đã khôi phục dự án thành công.', 'success');
          await loadData();
          renderView();
        } catch (err) {
          toast(err.message || 'Không thể khôi phục dự án.', 'error');
        }
      }

      // Permanent delete project
      async function handlePermanentDelete(project) {
        const confirmed = await confirmDialog({
          title: 'Xóa vĩnh viễn dự án',
          message: `Hành động này KHÔNG THỂ HOÀN TÁC. Bạn có chắc chắn muốn xóa vĩnh viễn dự án "${project.name}" cùng tất cả màn hình, ảnh và góp ý ngay bây giờ?`,
          confirmText: 'Xóa vĩnh viễn ngay',
          cancelText: 'Hủy bỏ',
          danger: true
        });
        if (!confirmed) return;

        try {
          const res = await api.deleteProject(project.id, true);
          toast(res.message || 'Đã xóa vĩnh viễn dự án.', 'info');
          await loadData();
          renderView();
        } catch (err) {
          toast(err.message || 'Lỗi khi xóa vĩnh viễn dự án.', 'error');
        }
      }

      // Hàm reset tất cả bộ lọc
      function resetAllFilters() {
        searchQuery = '';
        filterTag = 'all';
        filterStatus = 'all';
        filterRole = 'all';
        searchInput.value = '';
        clearSearchBtn.style.display = 'none';
        if (tagSelect) tagSelect.value = 'all';
        if (statusSelect) statusSelect.value = 'all';
        if (roleSelect) roleSelect.value = 'all';
        currentPage = 1;
        updateUrlState();
        renderGridContainer();
      }

      // Hàm điều phối hiển thị Grid/List và Empty State
      function renderGridContainer() {
        const filteredItems = getFilteredAndSorted();
        countLabel.textContent = `${filteredItems.length} dự án`;

        const hasActiveFilters = Boolean(
          searchQuery ||
          (filterTag && filterTag !== 'all') ||
          (filterStatus && filterStatus !== 'all') ||
          (filterRole && filterRole !== 'all')
        );

        // Cập nhật hàng Filter Chips
        chipsContainer.replaceChildren(renderFilterChips(
          searchQuery,
          filterTag,
          filterStatus,
          filterRole,
          () => {
            searchQuery = '';
            searchInput.value = '';
            clearSearchBtn.style.display = 'none';
            renderGridContainer();
          },
          () => {
            filterTag = 'all';
            if (tagSelect) tagSelect.value = 'all';
            renderGridContainer();
          },
          () => {
            filterStatus = 'all';
            if (statusSelect) statusSelect.value = 'all';
            renderGridContainer();
          },
          () => {
            filterRole = 'all';
            if (roleSelect) roleSelect.value = 'all';
            renderGridContainer();
          },
          resetAllFilters
        ));

        // Xử lý Empty State khi không có kết quả
        if (!filteredItems.length) {
          if (hasActiveFilters) {
            const resetBtn = el('button', {
              type: 'button',
              class: 'btn btn-secondary btn-sm',
              style: 'margin-top: 14px;',
              onclick: resetAllFilters
            }, [icon('close'), 'Đặt lại bộ lọc']);

            gridContainer.replaceChildren(emptyState({
              iconName: 'search',
              title: 'Không tìm thấy dự án phù hợp',
              text: 'Không có dự án nào khớp với các tiêu chí tìm kiếm hoặc bộ lọc hiện tại. Hãy thử chọn lại hoặc đặt lại bộ lọc.',
              action: resetBtn
            }));
            return;
          }

          if (currentTab === 'trash') {
            gridContainer.replaceChildren(emptyState({
              iconName: 'trash',
              title: 'Thùng rác trống',
              text: 'Không có dự án nào trong thùng rác. Khi bạn xóa dự án, dự án sẽ được giữ lại ở đây trong 30 ngày trước khi tự động xóa vĩnh viễn.'
            }));
            return;
          }

          if (currentTab === 'mine') {
            const createBtn = el('button', {
              type: 'button',
              class: 'btn btn-primary',
              style: 'margin-top: 14px;',
              onclick: () => openCreate(() => {
                loadData().then(renderView);
              }, activeProjects.map((p) => p.category))
            }, [icon('plus'), el('span', { text: 'Tạo dự án mới' })]);

            gridContainer.replaceChildren(emptyState({
              title: 'Bạn chưa tạo dự án nào',
              text: 'Hãy tạo dự án mới để tải ảnh giao diện thiết kế và mời các thành viên cùng tham gia góp ý.',
              action: createBtn
            }));
            return;
          }

          if (currentTab === 'invited') {
            gridContainer.replaceChildren(emptyState({
              iconName: 'users',
              title: 'Chưa có dự án nào được chia sẻ với bạn',
              text: 'Khi quản lý của các dự án khác thêm bạn vào dự án của họ, dự án sẽ tự động xuất hiện tại đây.'
            }));
            return;
          }

          const createBtn = el('button', {
            type: 'button',
            class: 'btn btn-primary',
            style: 'margin-top: 14px;',
            onclick: () => openCreate(() => {
              loadData().then(renderView);
            }, activeProjects.map((p) => p.category))
          }, [icon('plus'), el('span', { text: 'Tạo dự án mới' })]);

          gridContainer.replaceChildren(emptyState({
            title: 'Bạn chưa có dự án nào',
            text: 'Tạo dự án mới để tải ảnh giao diện và bắt đầu lấy góp ý, hoặc chờ được thêm vào dự án của nhóm.',
            action: createBtn
          }));
          return;
        }

        // Phân trang dữ liệu
        const totalItems = filteredItems.length;
        const totalPages = Math.ceil(totalItems / currentLimit) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * currentLimit;
        const pageItems = filteredItems.slice(startIdx, startIdx + currentLimit);

        // Render dữ liệu theo View Mode (Grid hoặc List)
        let contentEl;
        if (currentTab !== 'trash') {
          if (viewMode === 'grid') {
            contentEl = renderGrid(pageItems, confirmMoveToTrash, session);
          } else {
            contentEl = renderListView(pageItems, confirmMoveToTrash, session);
          }
        } else {
          if (viewMode === 'grid') {
            contentEl = renderTrashGrid(pageItems, handleRestoreProject, handlePermanentDelete);
          } else {
            contentEl = renderTrashListView(pageItems, handleRestoreProject, handlePermanentDelete);
          }
        }

        const paginationBar = renderPagination({
          currentPage,
          pageSize: currentLimit,
          totalItems,
          pageSizeOptions: viewMode === 'grid' ? [12, 24, 48] : [10, 20, 50, 100],
          itemLabel: 'dự án',
          isStandalone: viewMode === 'grid',
          onPageChange: (newPage) => {
            currentPage = newPage;
            updateUrlState();
            renderGridContainer();
            window.scrollTo({ top: 0, behavior: 'smooth' });
          },
          onPageSizeChange: (newLimit) => {
            currentLimit = newLimit;
            currentPage = 1;
            updateUrlState();
            renderGridContainer();
          }
        });

        gridContainer.replaceChildren(contentEl, ...(paginationBar ? [paginationBar] : []));
      }

      renderGridContainer();
      page.replaceChildren(...[header, tabsBar, trashNotice, toolbar, chipsContainer, gridContainer].filter(Boolean));
    }

    try {
      await loadData();
      renderView();

      // If URL hash is #create, open modal automatically
      if (location.hash === '#create') {
        openCreate(() => {
          loadData().then(renderView);
        }, activeProjects.map((p) => p.category));
        history.replaceState(null, '', 'projects.php');
      }
    } catch (err) {
      page.replaceChildren(emptyState({
        title: 'Lỗi tải dự án',
        text: err.message || 'Không thể lấy danh sách dự án. Vui lòng kiểm tra lại kết nối.'
      }));
    }
  }
});
