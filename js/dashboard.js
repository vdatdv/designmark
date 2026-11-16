import { boot } from './app.js';
import { api } from './api.js';
import {
  avatarEl,
  el,
  emptyState,
  formatDate,
  formatDay,
  icon,
  loadingBlock,
  openModal,
  setBusy,
  toast
} from './ui.js';

function statCard(label, value, iconName, href = null, highlight = '') {
  const card = el(href ? 'a' : 'article', {
    class: `stat-card${highlight ? ' is-' + highlight : ''}${href ? ' is-clickable' : ''}`,
    ...(href ? { href } : {})
  }, [
    el('div', { class: 'stat-header' }, [
      el('span', { class: 'stat-label', text: label }),
      iconName ? icon(iconName) : null
    ].filter(Boolean)),
    el('strong', { class: 'stat-value', text: String(value) })
  ]);
  return card;
}

function renderPendingApprovalCard(pendingUsers, refreshFn) {
  const rows = pendingUsers.map((u) => {
    const roleChoice = el('select', { class: 'field-input field-input-sm', style: 'padding: 4px 8px; font-size: 14px;' }, [
      el('option', { value: 'reviewer', text: 'Góp ý (Reviewer)', selected: 'true' }),
      el('option', { value: 'viewer', text: 'Chỉ xem (Viewer)' }),
      el('option', { value: 'manager', text: 'Quản lý (Admin)' })
    ]);

    const approveBtn = el('button', {
      type: 'button',
      class: 'btn btn-primary btn-sm',
      text: 'Phân cấp & Kích hoạt',
      onclick: async () => {
        approveBtn.disabled = true;
        try {
          const res = await api.approveUser(u.id, roleChoice.value);
          toast(res.message || `Đã phân cấp và kích hoạt cho ${u.name}.`, 'success');
          refreshFn();
        } catch (err) {
          approveBtn.disabled = false;
          toast(err.message || 'Lỗi phê duyệt tài khoản.', 'error');
        }
      }
    });

    const rejectBtn = el('button', {
      type: 'button',
      class: 'btn btn-ghost btn-sm',
      style: 'color: #ef4444; margin-left: 6px;',
      text: 'Từ chối',
      onclick: async () => {
        if (!confirm(`Bạn có chắc muốn từ chối yêu cầu đăng ký của ${u.name} (@${u.username})?`)) return;
        rejectBtn.disabled = true;
        try {
          await api.rejectUser(u.id);
          toast(`Đã từ chối tài khoản ${u.name}.`, 'info');
          refreshFn();
        } catch (err) {
          rejectBtn.disabled = false;
          toast(err.message || 'Lỗi từ chối tài khoản.', 'error');
        }
      }
    });

    return el('tr', {}, [
      el('td', {}, [
        el('div', { class: 'member-cell' }, [
          avatarEl(u, 28),
          el('div', {}, [
            el('strong', { text: u.name }),
            el('div', { class: 'help', text: `@${u.username}${u.title ? ' · ' + u.title : ''}` })
          ])
        ])
      ]),
      el('td', {}, [roleChoice]),
      el('td', {}, [approveBtn, rejectBtn])
    ]);
  });

  const table = el('table', { class: 'data-table' }, [
    el('thead', {}, [
      el('tr', {}, ['Tài khoản chờ duyệt', 'Phân cấp vai trò', 'Thao tác'].map((l) => el('th', { text: l })))
    ]),
    el('tbody', {}, rows)
  ]);

  return el('section', { class: 'panel panel-pad', style: 'margin-bottom: 24px; border-left: 4px solid var(--primary);' }, [
    el('div', { class: 'page-head', style: 'margin-bottom: 12px;' }, [
      el('div', {}, [
        el('h3', { text: `Tài khoản chờ Admin phân cấp vai trò (${pendingUsers.length})`, style: 'margin: 0 0 4px 0;' }),
        el('p', { class: 'help', text: 'Thành viên mới đã đăng ký và đang chờ phân cấp vai trò (Quản lý, Góp ý, Chỉ xem) để kích hoạt sử dụng.' })
      ]),
      el('a', { href: 'users.php', class: 'btn btn-secondary btn-sm', style: 'text-decoration: none;' }, [el('span', { text: 'Quản lý tài khoản &rarr;' })])
    ]),
    el('div', { class: 'feedback-table-wrap' }, [table])
  ]);
}

function renderPendingFeedbackSection(pendingFeedback) {
  const items = (pendingFeedback || []).slice(0, 3);

  const container = el('section', { class: 'dashboard-card' }, [
    el('div', { class: 'dashboard-card-head' }, [
      el('div', {}, [
        el('div', { class: 'dashboard-title-row' }, [
          icon('message'),
          el('h2', { text: 'Công việc cần tôi xử lý' }),
          el('span', { class: 'badge badge-warning', text: `${items.length} chưa xong` })
        ]),
        el('p', { class: 'dashboard-subtitle', text: 'Các góp ý mới nhất hoặc đang mở cần đối chiếu và xử lý trực tiếp trên bản thiết kế.' })
      ]),
      el('a', { href: 'feedback.php?status=open', class: 'btn btn-secondary btn-sm' }, [
        el('span', { text: 'Xem tất cả' }),
        icon('chevron')
      ])
    ])
  ]);

  if (!items.length) {
    container.append(emptyState({
      title: 'Không có góp ý tồn đọng',
      text: 'Mọi nhận xét và góp ý trong các dự án của bạn đều đã được giải quyết.'
    }));
    return container;
  }

  // Desktop Table View
  const rows = items.map((item) => {
    return el('tr', { class: 'feedback-row' }, [
      el('td', { class: 'feedback-project-col' }, [
        el('div', { class: 'feedback-project-info' }, [
          el('a', {
            href: `feedback.php?project=${encodeURIComponent(item.projectId)}`,
            class: 'project-name project-link-title',
            text: item.projectName,
            title: 'Mở nhóm góp ý của dự án này'
          }),
          el('span', { class: 'screen-name', text: `${item.screenName} (v${item.versionNumber})` })
        ])
      ]),
      el('td', { class: 'feedback-body-col' }, [
        el('p', { class: 'feedback-snippet', text: item.body, title: item.body })
      ]),
      el('td', { class: 'feedback-author-col' }, [
        el('div', { class: 'member-cell' }, [
          avatarEl(item.author, 24),
          el('span', { text: item.author.name })
        ])
      ]),
      el('td', { class: 'feedback-time-col' }, [
        el('time', { datetime: item.createdAt, text: formatDay(item.createdAt) })
      ]),
      el('td', { class: 'feedback-status-col' }, [
        el('div', { style: 'display: flex; flex-direction: column; gap: 4px; align-items: flex-start;' }, [
          el('span', { class: `badge badge-status status-${item.status}`, text: item.status === 'open' ? 'Chưa xử lý' : 'Đang xử lý' }),
          item.isMyFeedback ? el('span', { class: 'badge badge-info', text: 'Của tôi', style: 'font-size: 11px; padding: 1px 6px;' }) : null
        ].filter(Boolean))
      ]),
      el('td', { class: 'feedback-action-col' }, [
        el('a', {
          href: item.reviewUrl,
          class: 'btn btn-secondary btn-sm',
          title: 'Mở vị trí góp ý trên ảnh thiết kế'
        }, [
          icon('pin'),
          el('span', { text: 'Mở trên ảnh' })
        ])
      ])
    ]);
  });

  const table = el('table', { class: 'data-table feedback-table' }, [
    el('thead', {}, [
      el('tr', {}, [
        el('th', { text: 'Dự án & Màn hình' }),
        el('th', { text: 'Nội dung góp ý' }),
        el('th', { text: 'Người gửi' }),
        el('th', { text: 'Ngày tạo' }),
        el('th', { text: 'Trạng thái' }),
        el('th', { text: 'Thao tác' })
      ])
    ]),
    el('tbody', {}, rows)
  ]);

  const tableWrap = el('div', { class: 'feedback-table-wrap' }, [table]);

  // Mobile Card List View
  const mobileList = el('div', { class: 'feedback-mobile-list' }, items.map((item) => {
    return el('div', { class: 'feedback-mobile-card' }, [
      el('div', { class: 'feedback-mobile-card-top' }, [
        el('div', { class: 'feedback-mobile-header-meta' }, [
          el('a', {
            href: `feedback.php?project=${encodeURIComponent(item.projectId)}`,
            class: 'feedback-mobile-project-name',
            text: item.projectName
          }),
          el('span', { class: 'feedback-dot' }, '·'),
          el('span', { class: 'feedback-mobile-screen', text: `${item.screenName} (v${item.versionNumber})` })
        ]),
        el('div', { style: 'display: flex; gap: 4px; align-items: center;' }, [
          el('span', {
            class: `badge badge-status status-${item.status}`,
            text: item.status === 'open' ? 'Chưa xử lý' : 'Đang xử lý'
          }),
          item.isMyFeedback ? el('span', { class: 'badge badge-info', text: 'Của tôi', style: 'font-size: 10px; padding: 1px 5px;' }) : null
        ].filter(Boolean))
      ]),
      el('p', { class: 'feedback-mobile-body', text: item.body }),
      el('div', { class: 'feedback-mobile-card-bottom' }, [
        el('div', { class: 'member-cell member-cell-sm' }, [
          avatarEl(item.author, 22),
          el('span', { class: 'feedback-mobile-author', text: item.author.name }),
          el('span', { class: 'feedback-dot' }, '·'),
          el('time', { datetime: item.createdAt, class: 'feedback-mobile-time', text: formatDay(item.createdAt) })
        ]),
        el('a', {
          href: item.reviewUrl,
          class: 'btn btn-secondary btn-xs',
          title: 'Mở vị trí góp ý trên ảnh thiết kế'
        }, [
          icon('pin'),
          el('span', { text: 'Mở trên ảnh' })
        ])
      ])
    ]);
  }));

  container.append(tableWrap, mobileList);
  return container;
}

function renderRecentActivitiesSection(activities, totalActivities = 0) {
  let isExpanded = false;
  let items = (activities || []).slice(0, 5);
  items.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));

  const total = totalActivities || items.length;

  const titleRow = el('div', { class: 'dashboard-title-row' }, [
    icon('clock'),
    el('h2', { text: 'Hoạt động gần đây' }),
    total > 0 ? el('span', { class: 'badge badge-subtle', text: `${total} hoạt động` }) : null
  ].filter(Boolean));

  const subtitle = el('p', {
    class: 'dashboard-subtitle',
    text: 'Các hoạt động mới nhất trong các dự án được cấp quyền truy cập.'
  });

  const container = el('section', { class: 'dashboard-card' }, [
    el('div', { class: 'dashboard-card-head' }, [
      el('div', {}, [titleRow, subtitle])
    ])
  ]);

  if (!items.length) {
    container.append(emptyState({
      title: 'Chưa có hoạt động',
      text: 'Các hoạt động đóng góp, cập nhật ảnh và nhận xét sẽ hiển thị tại đây.'
    }));
    return container;
  }

  const list = el('div', { class: 'timeline' });

  function renderTimelineItems(listItems) {
    list.replaceChildren();
    listItems.forEach((act) => {
      const isComment = act.isComment || (act.link && act.link.includes('review.php'));

      const projectLink = el('a', {
        class: 'timeline-project-link',
        href: `project.php?id=${encodeURIComponent(act.projectId)}`,
        title: `Xem dự án ${act.projectName}`,
        onclick: (e) => e.stopPropagation()
      }, [
        el('span', { text: `[${act.projectName}]` })
      ]);

      const actionTextEl = act.link
        ? el('a', {
            class: 'timeline-action-link',
            href: act.link,
            title: isComment ? 'Mở trực tiếp góp ý này trên bản thiết kế' : 'Xem chi tiết'
          }, [
            el('span', { text: act.message })
          ])
        : el('span', { text: act.message });

      const metaRow = el('div', { class: 'timeline-meta-row' }, [
        el('time', { datetime: act.createdAt, text: formatDate(act.createdAt) }),
        isComment && act.link
          ? el('a', {
              class: 'timeline-view-comment-pill',
              href: act.link,
              title: 'Mở vị trí góp ý trên ảnh thiết kế',
              onclick: (e) => e.stopPropagation()
            }, [
              icon('pin'),
              el('span', { text: 'Mở góp ý' })
            ])
          : null
      ].filter(Boolean));

      const itemEl = el('article', {
        class: `timeline-item ${act.link ? 'is-clickable' : ''}`,
        title: isComment ? 'Nhấn để chuyển đến vị trí góp ý trên bản thiết kế' : 'Nhấn để xem chi tiết',
        onclick: (e) => {
          if (e.target.closest('a')) return;
          if (act.link) window.location.href = act.link;
        }
      }, [
        avatarEl(act.user, 32),
        el('div', { class: 'timeline-content' }, [
          el('p', { class: 'timeline-text' }, [
            el('strong', { text: act.user?.name || 'Ẩn danh' }),
            document.createTextNode(' '),
            actionTextEl,
            document.createTextNode(' '),
            projectLink
          ]),
          metaRow
        ])
      ]);

      list.append(itemEl);
    });
  }

  renderTimelineItems(items);

  // Nút "Xem tất cả" ở bên dưới danh sách hoạt động
  const bottomViewAllBtn = el('button', {
    type: 'button',
    class: 'btn btn-secondary btn-sm',
    title: 'Xem tất cả hoạt động',
    onclick: () => toggleExpand()
  }, [
    icon('clock'),
    el('span', { text: 'Xem tất cả hoạt động' }),
    icon('chevron')
  ]);

  const footerWrap = el('div', { class: 'dashboard-card-footer dashboard-card-footer-center' }, [
    bottomViewAllBtn
  ]);

  async function toggleExpand() {
    if (!isExpanded) {
      bottomViewAllBtn.disabled = true;
      const originalText = bottomViewAllBtn.querySelector('span')?.textContent;
      if (bottomViewAllBtn.querySelector('span')) bottomViewAllBtn.querySelector('span').textContent = 'Đang tải...';

      try {
        const fullData = await api.getDashboard({ all_activities: 1 });
        const allItems = (fullData.recentActivities || []).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        renderTimelineItems(allItems);
        isExpanded = true;
        subtitle.textContent = `Đang hiển thị tất cả ${allItems.length} hoạt động trong các dự án được cấp quyền.`;

        bottomViewAllBtn.replaceChildren(icon('clock'), el('span', { text: 'Thu gọn hoạt động' }));
      } catch (err) {
        toast(err.message || 'Không thể tải toàn bộ hoạt động.', 'error');
        if (bottomViewAllBtn.querySelector('span')) bottomViewAllBtn.querySelector('span').textContent = originalText;
      } finally {
        bottomViewAllBtn.disabled = false;
      }
    } else {
      renderTimelineItems(items);
      isExpanded = false;
      subtitle.textContent = 'Các hoạt động mới nhất trong các dự án được cấp quyền truy cập.';
      bottomViewAllBtn.replaceChildren(icon('clock'), el('span', { text: 'Xem tất cả hoạt động' }), icon('chevron'));
      container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  container.append(list, footerWrap);
  return container;
}

function renderRecentProjectsSection(projects, session) {
  const items = (projects || []).slice(0, 3);

  const container = el('section', { class: 'dashboard-card' }, [
    el('div', { class: 'dashboard-card-head' }, [
      el('div', {}, [
        el('div', { class: 'dashboard-title-row' }, [
          icon('folder'),
          el('h2', { text: 'Dự án gần đây' })
        ]),
        el('p', { class: 'dashboard-subtitle', text: 'Truy cập nhanh các dự án có tương tác mới nhất.' })
      ]),
      el('a', { href: 'projects.php', class: 'btn btn-secondary btn-sm' }, [
        el('span', { text: 'Xem tất cả dự án' }),
        icon('chevron')
      ])
    ])
  ]);

  if (!items.length) {
    container.append(emptyState({
      title: 'Bạn chưa có dự án nào.',
      text: 'Tạo dự án mới để tải ảnh giao diện và bắt đầu lấy góp ý, hoặc chờ được thêm vào dự án của nhóm.',
      action: {
        label: '+ Tạo dự án mới',
        href: 'projects.php#create'
      }
    }));
    return container;
  }

  const countClass = `grid-count-${Math.min(items.length, 4)}`;
  const grid = el('div', { class: `recent-projects-grid ${countClass}` });

  items.forEach((proj) => {
    const stack = el('div', { class: 'avatar-stack', 'aria-label': `Thành viên: ${(proj.members || []).map((m) => m.name).join(', ')}` });
    (proj.members || []).slice(0, 4).forEach((m) => stack.append(avatarEl(m, 26)));

    const isOwner = !!proj.isOwner;
    const categoryBadge = proj.category ? el('span', { class: 'badge badge-category', text: proj.category }) : null;
    let roleBadges = [];
    if (!isOwner) {
      if (proj.myRole === 'admin_observer') {
        roleBadges.push(el('span', { class: 'badge badge-role role-admin_observer' }, [el('span', { text: 'Giám sát hệ thống' })]));
      } else {
        roleBadges.push(el('span', { class: 'badge badge-invited', text: 'Được mời' }));
        if (proj.myRole) {
          const roleLabel = proj.myRole === 'manager' ? 'Quản lý' : (proj.myRole === 'reviewer' || proj.myRole === 'commenter' ? 'Góp ý' : 'Chỉ xem');
          const roleClass = proj.myRole === 'manager' ? 'badge-role-manager' : (proj.myRole === 'reviewer' || proj.myRole === 'commenter' ? 'badge-role-reviewer' : 'badge-neutral badge-role-viewer');
          roleBadges.push(el('span', { class: 'badge-arrow', 'aria-hidden': 'true', text: '→' }));
          roleBadges.push(el('span', { class: `badge ${roleClass}` }, [el('span', { text: roleLabel })]));
        }
      }
    } else {
      roleBadges.push(el('span', { class: 'badge badge-role-manager' }, [el('span', { text: 'Quản lý' })]));
    }

    const tagsGroup = el('div', { class: 'project-card-tags-group', style: 'display: flex; flex-direction: column; gap: 5px; margin-top: 4px;' }, [
      categoryBadge ? el('div', { class: 'project-card-category-row' }, [categoryBadge]) : null,
      roleBadges.length ? el('div', { class: 'project-card-roles-row' }, roleBadges) : null
    ].filter(Boolean));

    grid.append(el('a', {
      class: 'card recent-project-card',
      href: `project.php?id=${encodeURIComponent(proj.id)}`,
      title: `Mở dự án: ${proj.name}`
    }, [
      el('div', { class: 'recent-project-top', style: 'display: flex; flex-direction: column; gap: 6px; align-items: flex-start;' }, [
        el('h3', { text: proj.name, style: 'margin: 0; font-size: 1.1rem; line-height: 1.35;' }),
        (categoryBadge || roleBadges.length) ? tagsGroup : null
      ].filter(Boolean)),
      el('p', { class: 'recent-project-desc', text: proj.description || 'Chưa có mô tả chi tiết.' }),
      el('div', { class: 'recent-project-meta' }, [
        el('span', { class: 'stat-pill' }, [
          icon('grid'),
          el('span', { text: `${proj.screenCount || 0} màn hình` })
        ]),
        el('span', { class: `stat-pill${proj.openCount > 0 ? ' is-open' : ''}` }, [
          icon('clock'),
          el('span', { text: `${proj.openCount || 0} chưa xử lý` })
        ]),
        el('span', { class: `stat-pill${(proj.resolvedCount || 0) > 0 ? ' is-resolved' : ''}` }, [
          icon('check'),
          el('span', { text: `${proj.resolvedCount || 0} hoàn thành` })
        ])
      ]),
      el('div', { class: 'recent-project-footer' }, [
        stack,
        el('span', { class: 'recent-project-date', text: `Cập nhật ${formatDay(proj.updatedAt)}` })
      ])
    ]));
  });

  container.append(grid);
  return container;
}

boot({
  active: 'overview',
  title: 'Tổng quan dự án',
  async render({ session, setBreadcrumb }) {
    setBreadcrumb([
      { label: 'DesignMark', href: 'dashboard.php' },
      { label: 'Tổng quan dự án' }
    ]);
    const page = document.getElementById('page');
    page.replaceChildren(loadingBlock('Đang tải dữ liệu tổng quan...'));

    async function loadDashboard() {
      try {
        const data = await api.getDashboard();
        const { stats, recentActivities, totalActivities, pendingFeedback, recentProjects, pendingUsers } = data;

        const isAdmin = session && session.systemRole === 'admin';
        const statCards = [
          statCard(isAdmin ? 'Tổng số dự án' : 'Dự án được cấp quyền', stats.totalProjects, 'folder', 'projects.php'),
          ...(isAdmin && stats.totalUsers !== undefined ? [
            statCard('Tổng số người dùng', stats.totalUsers, 'users', 'users.php')
          ] : []),
          statCard('Màn hình thiết kế', stats.totalScreens, 'grid', 'projects.php'),
          statCard('Góp ý chưa xử lý', stats.openFeedback, 'message', 'feedback.php?status=open', stats.openFeedback > 0 ? 'warning' : ''),
          statCard('Góp ý đã hoàn thành', stats.resolvedFeedback, 'check', 'feedback.php?status=resolved', 'success')
        ];
        const statGrid = el('div', { class: `stat-grid ${statCards.length === 5 ? 'stat-grid-5' : ''}` }, statCards);

        const headerActions = el('div', { class: 'page-head' }, [
          el('div', {}, [
            el('p', { class: 'lede', text: 'Theo dõi tình hình hoạt động chung, tiến độ góp ý và các bản thiết kế mới nhất.' })
          ]),
          el('div', { class: 'inline-actions' }, [
            el('a', { href: 'projects.php', class: 'btn btn-secondary' }, [
              icon('folder'),
              el('span', { text: 'Danh sách dự án' })
            ]),
            el('a', { href: 'projects.php#create', class: 'btn btn-primary' }, [
              icon('plus'),
              el('span', { text: 'Tạo dự án mới' })
            ])
          ].filter(Boolean))
        ]);

        const pendingApprovalBox = (pendingUsers && pendingUsers.length > 0)
          ? renderPendingApprovalCard(pendingUsers, loadDashboard)
          : null;

        const recentProjectsSection = renderRecentProjectsSection(recentProjects || [], session);
        const feedbackSection = renderPendingFeedbackSection(pendingFeedback || []);
        const activitiesSection = renderRecentActivitiesSection(recentActivities || [], totalActivities || 0);

        page.replaceChildren(
          headerActions,
          ...(pendingApprovalBox ? [pendingApprovalBox] : []),
          statGrid,
          recentProjectsSection,
          feedbackSection,
          activitiesSection
        );
      } catch (err) {
        page.replaceChildren(emptyState({
          title: 'Lỗi tải tổng quan',
          text: err.message || 'Không thể lấy dữ liệu tổng quan từ máy chủ. Vui lòng thử lại sau.'
        }));
      }
    }

    await loadDashboard();
  }
});
