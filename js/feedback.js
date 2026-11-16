import { boot } from './app.js';
import { api } from './api.js';
import { getSession } from './auth.js';
import { el, emptyState, icon, formatRelativeTime, toast, renderPagination } from './ui.js';

let projects = [];
let annotations = [];
let activeProjectId = null;
let activeProjectFilter = null; // 'proj-id' khi lọc theo dự án hiện tại
let projectScope = 'all'; // 'all' | 'mine' | 'shared'
let projectSearch = '';
let statusFilter = 'all'; // 'all' | 'open' | 'in_progress' | 'resolved' | 'mine'
let innerSearch = '';
const screenVersionSelection = {}; // screenId -> versionId or 'all'
let projectPage = 1;
let projectPageSize = 10;
const screenFeedbackPage = {};
const screenFeedbackLimit = {};

export function sanitizeCsvCell(value) {
  let text = value == null ? '' : String(value);
  if (/^[\s\u0000-\u001F]*[=+\-@]/.test(text)) text = `'${text}`;
  if (/[",\r\n]/.test(text)) text = `"${text.replace(/"/g, '""')}"`;
  return text;
}

export function buildCsv(tableRows) {
  const lines = tableRows.map((row) => row.map(sanitizeCsvCell).join(','));
  return `\uFEFF${lines.join('\r\n')}`;
}

function statusText(status) {
  if (status === 'resolved') return 'Đã xử lý';
  if (status === 'rejected') return 'Đang xử lý';
  return 'Chưa xử lý';
}

function statusBadgeEl(status) {
  if (status === 'resolved') {
    return el('span', { class: 'status-tag is-resolved' }, [icon('check'), 'Đã xử lý']);
  }
  if (status === 'rejected') {
    return el('span', { class: 'status-tag is-in-progress' }, [icon('clock'), 'Đang xử lý']);
  }
  return el('span', { class: 'status-tag is-open' }, [icon('alert'), 'Chưa xử lý']);
}

function getFilteredProjects() {
  return projects.filter((p) => {
    if (activeProjectFilter && p.id !== activeProjectFilter) return false;
    if (projectScope === 'mine' && !p.isOwner) return false;
    if (projectScope === 'shared' && p.isOwner) return false;
    if (projectSearch.trim()) {
      const q = projectSearch.trim().toLowerCase();
      const matchName = (p.name || '').toLowerCase().includes(q);
      const matchDesc = (p.description || '').toLowerCase().includes(q);
      if (!matchName && !matchDesc) return false;
    }
    return true;
  });
}

function getFilteredAnnotationsForScreen(screenId, projectId, session) {
  const selectedVer = screenVersionSelection[screenId] || 'all';
  return annotations.filter((ann) => {
    if (ann.projectId !== projectId) return false;
    if (ann.screenId !== screenId) return false;
    if (selectedVer !== 'all' && ann.imageVersionId !== selectedVer) return false;

    // Filter status / author
    if (statusFilter === 'open' && ann.status !== 'open') return false;
    if (statusFilter === 'resolved' && ann.status !== 'resolved') return false;
    if (statusFilter === 'in_progress' && ann.status !== 'rejected') return false;
    if (statusFilter === 'mine' && session && ann.authorId !== session.userId) return false;
    if (statusFilter === 'relevant' && session) {
      const isAuthor = ann.authorId === session.userId;
      const hasReplied = (ann.replies || []).some((r) => r.userId === session.userId || r.author?.id === session.userId);
      if (!isAuthor && !hasReplied) return false;
    }
    if (statusFilter === 'new') {
      const diffHours = (Date.now() - new Date(ann.createdAt).getTime()) / (1000 * 3600);
      if (diffHours > 48) return false;
    }

    // Inner search
    if (innerSearch.trim()) {
      const q = innerSearch.trim().toLowerCase();
      const match = [ann.body, ann.author?.name || '', ann.versionLabel].join(' ').toLowerCase();
      if (!match.includes(q)) return false;
    }

    return true;
  });
}

function getAllCurrentFilteredAnnotations(session) {
  if (activeProjectId) {
    const p = projects.find((item) => item.id === activeProjectId);
    if (!p) return [];
    let list = [];
    (p.screens || []).forEach((s) => {
      list = list.concat(getFilteredAnnotationsForScreen(s.id, p.id, session));
    });
    return list;
  }
  // If no project is open, export all matching projects
  const visibleProjects = getFilteredProjects();
  const visiblePids = new Set(visibleProjects.map((p) => p.id));
  return annotations.filter((ann) => visiblePids.has(ann.projectId));
}

function downloadCsv(session) {
  const list = getAllCurrentFilteredAnnotations(session);
  if (!list.length) {
    toast('Không có góp ý nào khớp bộ lọc để xuất CSV.', 'error');
    return;
  }
  const header = ['STT', 'Dự án', 'Màn hình', 'Phiên bản', 'Nội dung', 'Tọa độ', 'Người viết', 'Trạng thái', 'Thời gian'];
  const body = list.map((ann, idx) => [
    idx + 1,
    ann.projectName,
    ann.screenName,
    ann.versionLabel,
    ann.body,
    `x=${Math.round(ann.x)}; y=${Math.round(ann.y)}; w=${Math.round(ann.w)}; h=${Math.round(ann.h)}`,
    ann.author?.name || 'Ẩn danh',
    statusText(ann.status),
    ann.createdAt
  ]);
  const csv = buildCsv([header, ...body]);
  const stamp = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  const filename = `designmark-gop-y-${stamp.getFullYear()}${pad(stamp.getMonth() + 1)}${pad(stamp.getDate())}-${pad(stamp.getHours())}${pad(stamp.getMinutes())}.csv`;
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = el('a', { href: url, download: filename });
  document.body.append(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
  toast(`Đã xuất ${list.length} góp ý sang tệp CSV.`, 'success');
}

export function renderFeedbackPage(pageContainer) {
  const session = getSession();
  const accordionContainer = el('div', { class: 'project-accordion-list', id: 'project-accordion-list' });

  // Top stats summary
  const totalComments = annotations.length;
  const totalOpen = annotations.filter((a) => a.status === 'open').length;

  const targetProj = activeProjectFilter ? projects.find((p) => p.id === activeProjectFilter) : null;
  const pageTitleText = targetProj ? `Quản lý góp ý — ${targetProj.name}` : 'Quản lý góp ý theo dự án';
  const pageLedeText = targetProj
    ? `Đang lọc danh sách góp ý của dự án "${targetProj.name}" (${targetProj.totalComments || 0} góp ý, ${targetProj.openComments || 0} chưa xử lý).`
    : `Tổng cộng ${totalComments} góp ý trên ${projects.length} dự án (${totalOpen} góp ý chưa xử lý). Nhấn vào từng dự án để xem chi tiết theo màn hình và phiên bản.`;

  // 1. Page Header
  const exportBtn = el('button', {
    type: 'button',
    class: 'btn btn-primary',
    'data-testid': 'export-csv',
    onclick: () => downloadCsv(session)
  }, [icon('download'), 'Xuất CSV']);

  const pageHead = el('div', { class: 'feedback-page-head' }, [
    el('div', {}, [
      el('h2', { class: 'feedback-title', text: pageTitleText }),
      el('p', { class: 'lede', text: pageLedeText })
    ]),
    el('div', { class: 'inline-actions' }, [exportBtn])
  ]);

  // Project Filter Banner khi truy cập từ trang chi tiết dự án
  const projectFilterBanner = (activeProjectFilter && targetProj) ? el('div', {
    class: 'active-project-filter-banner',
    style: 'display: flex; align-items: center; justify-content: space-between; gap: 14px; background: rgba(225, 29, 72, 0.08); border: 1px solid rgba(225, 29, 72, 0.25); border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; flex-wrap: wrap;'
  }, [
    el('div', { style: 'display: flex; align-items: center; gap: 10px; font-size: 14.5px;' }, [
      icon('folder'),
      el('span', { style: 'color: var(--muted);', text: 'Đang lọc theo dự án hiện tại:' }),
      el('strong', { style: 'color: var(--primary, #e11d48); font-weight: 700;', text: targetProj.name })
    ]),
    el('div', { style: 'display: flex; align-items: center; gap: 8px;' }, [
      el('a', {
        class: 'btn btn-secondary btn-sm',
        href: `project.php?id=${encodeURIComponent(targetProj.id)}`,
        style: 'display: inline-flex; align-items: center; gap: 6px;'
      }, [icon('panels'), el('span', { text: 'Về trang dự án' })]),
      el('button', {
        type: 'button',
        class: 'btn btn-secondary btn-sm',
        style: 'display: inline-flex; align-items: center; gap: 6px;',
        onclick: () => {
          activeProjectFilter = null;
          activeProjectId = null;
          const url = new URL(location.href);
          url.searchParams.delete('project');
          url.searchParams.delete('project_id');
          history.replaceState(null, '', url.toString());
          renderFeedbackPage(pageContainer);
        }
      }, [icon('close'), el('span', { text: 'Xem tất cả dự án' })])
    ])
  ]) : null;

  // 2. Project Scope & Search Filter Bar
  const scopeAll = el('button', {
    type: 'button',
    class: `filter-pill ${projectScope === 'all' ? 'is-active' : ''}`,
    onclick: () => { projectScope = 'all'; updateTopFilters(); }
  }, [
    'Tất cả dự án',
    el('span', { class: 'pill-badge', text: String(projects.length) })
  ]);

  const scopeMineCount = projects.filter((p) => p.isOwner).length;
  const scopeMine = el('button', {
    type: 'button',
    class: `filter-pill ${projectScope === 'mine' ? 'is-active' : ''}`,
    onclick: () => { projectScope = 'mine'; updateTopFilters(); }
  }, [
    'Dự án tôi tạo',
    el('span', { class: 'pill-badge', text: String(scopeMineCount) })
  ]);

  const scopeSharedCount = projects.filter((p) => !p.isOwner).length;
  const scopeShared = el('button', {
    type: 'button',
    class: `filter-pill ${projectScope === 'shared' ? 'is-active' : ''}`,
    onclick: () => { projectScope = 'shared'; updateTopFilters(); }
  }, [
    'Dự án được chia sẻ',
    el('span', { class: 'pill-badge', text: String(scopeSharedCount) })
  ]);

  const searchInput = el('input', {
    type: 'search',
    class: 'field-input search-input project-search-input',
    placeholder: 'Tìm kiếm dự án theo tên...',
    value: projectSearch,
    'aria-label': 'Tìm kiếm dự án'
  });
  searchInput.addEventListener('input', () => {
    projectSearch = searchInput.value;
    projectPage = 1;
    renderAccordionItems();
  });

  const filterBar = el('div', { class: 'feedback-filter-bar' }, [
    el('div', { class: 'scope-pill-group' }, [scopeAll, scopeMine, scopeShared]),
    el('div', { class: 'search-wrap' }, [
      el('span', { class: 'search-icon-wrap' }, [icon('search')]),
      searchInput
    ])
  ]);

  function updateTopFilters() {
    scopeAll.classList.toggle('is-active', projectScope === 'all');
    scopeMine.classList.toggle('is-active', projectScope === 'mine');
    scopeShared.classList.toggle('is-active', projectScope === 'shared');
    projectPage = 1;
    renderAccordionItems();
  }

  // 3. Render Accordion Items
  function renderAccordionItems() {
    const visibleProjects = getFilteredProjects();

    if (!visibleProjects.length) {
      accordionContainer.replaceChildren(
        emptyState({
          title: 'Không tìm thấy dự án',
          text: projects.length ? 'Không có dự án nào khớp với điều kiện lọc hiện tại.' : 'Bạn chưa tham gia dự án nào.'
        })
      );
      return;
    }

    const totalProjects = visibleProjects.length;
    const totalPages = Math.ceil(totalProjects / projectPageSize) || 1;
    if (projectPage > totalPages) projectPage = totalPages;
    if (projectPage < 1) projectPage = 1;

    // If an active project is selected, ensure the active project's page is displayed
    if (activeProjectId) {
      const activeIdx = visibleProjects.findIndex((p) => p.id === activeProjectId);
      if (activeIdx !== -1) {
        const targetPage = Math.floor(activeIdx / projectPageSize) + 1;
        if (targetPage !== projectPage) {
          projectPage = targetPage;
        }
      }
    }

    const startIdx = (projectPage - 1) * projectPageSize;
    const pageProjects = visibleProjects.slice(startIdx, startIdx + projectPageSize);

    const items = pageProjects.map((proj) => {
      const isOpen = (proj.id === activeProjectId);
      const accordionItem = el('div', {
        class: `accordion-item ${isOpen ? 'is-open' : ''}`,
        id: `accordion-project-${proj.id}`
      });

      // Accordion Header
      const chevronIcon = el('span', { class: 'accordion-chevron' }, [icon('chevron')]);
      const headerTitle = el('strong', { class: 'accordion-project-name', text: proj.name });

      const roleBadge = proj.isOwner
        ? el('span', { class: 'tag tag-owner', text: 'Tôi tạo' })
        : el('span', { class: 'tag tag-shared', text: 'Được chia sẻ' });

      const totalBadge = el('span', { class: 'badge-pill badge-neutral' }, [
        el('strong', { text: String(proj.totalComments) }),
        ' góp ý'
      ]);

      const openBadge = el('span', {
        class: `badge-pill ${proj.openComments > 0 ? 'badge-warning' : 'badge-subtle'}`
      }, [
        el('strong', { text: String(proj.openComments) }),
        ' chưa xử lý'
      ]);

      const viewProjectLink = el('a', {
        class: 'btn btn-ghost btn-sm accordion-link-btn',
        href: `project.php?id=${encodeURIComponent(proj.id)}`,
        title: 'Xem trang chi tiết dự án',
        onclick: (e) => e.stopPropagation()
      }, [icon('folder'), 'Chi tiết']);

      const headerBtn = el('button', {
        type: 'button',
        class: 'accordion-header',
        'aria-expanded': isOpen ? 'true' : 'false',
        'aria-controls': `accordion-body-${proj.id}`,
        onclick: () => toggleProject(proj.id)
      }, [
        el('div', { class: 'accordion-header-left' }, [
          chevronIcon,
          headerTitle,
          roleBadge
        ]),
        el('div', { class: 'accordion-header-right' }, [
          totalBadge,
          openBadge,
          viewProjectLink
        ])
      ]);

      accordionItem.append(headerBtn);

      // Accordion Body
      if (isOpen) {
        const bodyContent = renderProjectBody(proj, session);
        accordionItem.append(bodyContent);
      }

      return accordionItem;
    });

    const paginationBar = renderPagination({
      currentPage: projectPage,
      pageSize: projectPageSize,
      totalItems: totalProjects,
      pageSizeOptions: [10, 20, 50, 100],
      itemLabel: 'dự án',
      isStandalone: true,
      onPageChange: (newPage) => {
        projectPage = newPage;
        renderAccordionItems();
        accordionContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      },
      onPageSizeChange: (newSize) => {
        projectPageSize = newSize;
        projectPage = 1;
        renderAccordionItems();
      }
    });

    accordionContainer.replaceChildren(...items, ...(paginationBar ? [paginationBar] : []));
  }

  function toggleProject(projectId) {
    // RULE: At any time, at most ONE project is open.
    // If clicked on the currently open project -> collapse it.
    // If clicked on another project -> collapse current and open new.
    if (activeProjectId === projectId) {
      activeProjectId = null;
    } else {
      activeProjectId = projectId;
      // Reset inner filters for clean experience
      statusFilter = 'all';
      innerSearch = '';
    }
    renderAccordionItems();
  }

  // 4. Render Inside Open Project
  function renderProjectBody(project, session) {
    const body = el('div', {
      class: 'accordion-body',
      id: `accordion-body-${project.id}`,
      role: 'region'
    });

    // Inner Filter Bar: Tất cả góp ý / Chưa xử lý / Đang xử lý / Đã xử lý / Góp ý của tôi
    function resetScreenFeedbackPages() {
      Object.keys(screenFeedbackPage).forEach((k) => delete screenFeedbackPage[k]);
    }

    const pillAll = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'all' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'all'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, 'Tất cả góp ý');

    const pillOpen = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'open' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'open'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, [icon('alert'), 'Chưa xử lý']);

    const pillInProgress = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'in_progress' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'in_progress'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, [icon('clock'), 'Đang xử lý']);

    const pillResolved = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'resolved' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'resolved'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, [icon('check'), 'Đã xử lý']);

    const pillMine = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'mine' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'mine'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, [icon('users'), 'Góp ý của tôi']);

    const pillRelevant = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'relevant' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'relevant'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, [icon('message'), 'Liên quan đến tôi']);

    const pillNew = el('button', {
      type: 'button',
      class: `inner-pill ${statusFilter === 'new' ? 'is-active' : ''}`,
      onclick: () => { statusFilter = 'new'; resetScreenFeedbackPages(); renderProjectScreens(); }
    }, [icon('clock'), 'Góp ý mới']);

    const innerSearchInput = el('input', {
      type: 'search',
      class: 'field-input search-input inner-search-input',
      placeholder: 'Tìm nội dung góp ý, người viết...',
      value: innerSearch,
      'aria-label': 'Tìm trong dự án'
    });
    innerSearchInput.addEventListener('input', () => {
      innerSearch = innerSearchInput.value;
      resetScreenFeedbackPages();
      renderProjectScreens();
    });

    const innerFilterRow = el('div', { class: 'inner-filter-row' }, [
      el('div', { class: 'inner-pill-group' }, [pillAll, pillOpen, pillInProgress, pillResolved, pillMine, pillRelevant, pillNew]),
      el('div', { class: 'inner-search-box' }, [
        el('span', { class: 'search-icon-wrap' }, [icon('search')]),
        innerSearchInput
      ])
    ]);

    const screensContainer = el('div', { class: 'screens-list-container' });

    function renderProjectScreens() {
      // Update active pills
      pillAll.classList.toggle('is-active', statusFilter === 'all');
      pillOpen.classList.toggle('is-active', statusFilter === 'open');
      pillInProgress.classList.toggle('is-active', statusFilter === 'in_progress');
      pillResolved.classList.toggle('is-active', statusFilter === 'resolved');
      pillMine.classList.toggle('is-active', statusFilter === 'mine');
      pillRelevant.classList.toggle('is-active', statusFilter === 'relevant');
      pillNew.classList.toggle('is-active', statusFilter === 'new');

      const screens = project.screens || [];
      if (!screens.length) {
        screensContainer.replaceChildren(
          el('div', { class: 'subtle-empty' }, [
            icon('panels'),
            el('p', { text: 'Dự án này chưa có màn hình thiết kế nào.' })
          ])
        );
        return;
      }

      const screenEls = screens.map((screen) => {
        const screenEl = el('div', { class: 'screen-group-card', id: `screen-group-${screen.id}` });

        // Version Selector for Screen
        const versions = screen.versions || [];
        const currentSelectedVersion = screenVersionSelection[screen.id] || 'all';

        const verTabs = [
          el('button', {
            type: 'button',
            class: `ver-tab ${currentSelectedVersion === 'all' ? 'is-active' : ''}`,
            onclick: () => {
              screenVersionSelection[screen.id] = 'all';
              screenFeedbackPage[screen.id] = 1;
              renderProjectScreens();
            }
          }, `Tất cả phiên bản (${screen.totalComments})`)
        ];

        versions.forEach((v) => {
          verTabs.push(
            el('button', {
              type: 'button',
              class: `ver-tab ${currentSelectedVersion === v.id ? 'is-active' : ''}`,
              onclick: () => {
                screenVersionSelection[screen.id] = v.id;
                screenFeedbackPage[screen.id] = 1;
                renderProjectScreens();
              }
            }, `${v.label} (${v.totalComments})`)
          );
        });

        // Screen Card Header
        const screenHeader = el('div', { class: 'screen-group-header' }, [
          el('div', { class: 'screen-title-area' }, [
            el('span', { class: 'screen-icon-wrap' }, [icon('panels')]),
            el('strong', { class: 'screen-title', text: screen.name }),
            el('span', { class: 'badge-pill badge-neutral' }, `${screen.totalComments} góp ý`),
            screen.openComments > 0
              ? el('span', { class: 'badge-pill badge-warning' }, `${screen.openComments} chưa xử lý`)
              : el('span', { class: 'badge-pill badge-subtle' }, '0 chưa xử lý')
          ]),
          el('div', { class: 'screen-ver-tabs-wrap' }, verTabs)
        ]);

        // Feedback Items for this Screen
        const screenFeedbackList = getFilteredAnnotationsForScreen(screen.id, project.id, session);
        const feedbackCardsContainer = el('div', { class: 'feedback-cards-container' });

        const totalFeedback = screenFeedbackList.length;
        const currentLimit = screenFeedbackLimit[screen.id] || 10;
        let currentPage = screenFeedbackPage[screen.id] || 1;
        const totalFeedbackPages = Math.ceil(totalFeedback / currentLimit) || 1;
        if (currentPage > totalFeedbackPages) currentPage = totalFeedbackPages;
        if (currentPage < 1) currentPage = 1;
        screenFeedbackPage[screen.id] = currentPage;

        if (!totalFeedback) {
          feedbackCardsContainer.append(
            el('div', { class: 'screen-empty-feedback' }, [
              el('p', { text: 'Không có góp ý nào khớp với bộ lọc hiện tại trên màn hình này.' })
            ])
          );
        } else {
          const startIdx = (currentPage - 1) * currentLimit;
          const pageItems = screenFeedbackList.slice(startIdx, startIdx + currentLimit);

          pageItems.forEach((ann) => {
            const replyCount = (ann.replies || []).length;
            const targetUrl = `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}&version=${encodeURIComponent(ann.imageVersionId)}&annotation=${encodeURIComponent(ann.id)}`;

            const card = el('div', {
              class: 'feedback-card is-clickable',
              role: 'button',
              tabindex: '0',
              title: `Nhấp để mở góp ý #${ann.number} trực tiếp trên thiết kế`,
              onclick: (e) => {
                if (e.target.closest('a') || e.target.closest('button')) return;
                location.href = targetUrl;
              }
            }, [
              el('div', { class: 'feedback-card-top' }, [
                el('div', { class: 'feedback-card-author' }, [
                  el('span', { class: 'feedback-marker-badge' }, `#${ann.number}`),
                  el('span', {
                    class: 'avatar avatar-xs',
                    style: `background: ${ann.author?.color || '#4F46E5'}`
                  }, (ann.author?.name || '?').trim().charAt(0).toUpperCase()),
                  el('strong', { text: ann.author?.name || 'Ẩn danh' }),
                  el('span', { class: 'feedback-dot' }, '·'),
                  el('span', { class: 'feedback-time', text: formatRelativeTime(ann.createdAt) }),
                  el('span', { class: 'tag tag-version', text: ann.versionLabel || 'v1' })
                ]),
                el('div', { class: 'feedback-card-badges' }, [
                  statusBadgeEl(ann.status)
                ])
              ]),
              el('div', { class: 'feedback-card-body' }, [
                el('p', { class: 'feedback-text', text: ann.body })
              ]),
              el('div', { class: 'feedback-card-bottom' }, [
                el('div', { class: 'feedback-meta-left' }, [
                  replyCount > 0
                    ? el('span', { class: 'feedback-reply-meta' }, [icon('message'), `${replyCount} phản hồi`])
                    : null,
                  el('span', { class: 'feedback-coords' }, `Vị trí: x=${Math.round(ann.x)}, y=${Math.round(ann.y)}`)
                ]),
                el('div', { class: 'feedback-meta-right' }, [
                  el('a', {
                    href: targetUrl,
                    class: 'btn btn-sm btn-primary-outline review-pin-link',
                    title: 'Xem điểm góp ý trực tiếp trên bản thiết kế'
                  }, [icon('pin'), 'Xem trên ảnh'])
                ])
              ])
            ]);

            feedbackCardsContainer.append(card);
          });

          const screenPaginationBar = renderPagination({
            currentPage: currentPage,
            pageSize: currentLimit,
            totalItems: totalFeedback,
            pageSizeOptions: [10, 20, 50, 100],
            itemLabel: 'góp ý',
            isStandalone: true,
            onPageChange: (newPage) => {
              screenFeedbackPage[screen.id] = newPage;
              renderProjectScreens();
            },
            onPageSizeChange: (newLimit) => {
              screenFeedbackLimit[screen.id] = newLimit;
              screenFeedbackPage[screen.id] = 1;
              renderProjectScreens();
            }
          });

          if (screenPaginationBar) {
            feedbackCardsContainer.append(screenPaginationBar);
          }
        }

        screenEl.append(screenHeader, feedbackCardsContainer);
        return screenEl;
      });

      screensContainer.replaceChildren(...screenEls);
    }

    renderProjectScreens();

    body.append(innerFilterRow, screensContainer);
    return body;
  }

  // Mount entire page structure
  pageContainer.replaceChildren(
    pageHead,
    ...(projectFilterBanner ? [projectFilterBanner] : []),
    filterBar,
    accordionContainer
  );

  renderAccordionItems();
}

if (typeof document !== 'undefined' && document.body?.dataset.page === 'feedback') {
  boot({
    active: 'feedback',
    title: 'Quản lý góp ý',
    async render({ setBreadcrumb }) {
      const page = document.getElementById('page');
      page.replaceChildren(
        el('div', { class: 'page-loading-wrap' }, [
          el('div', { class: 'spinner' }),
          el('p', { text: 'Đang tải dữ liệu góp ý...' })
        ])
      );

      const params = new URLSearchParams(location.search);
      const requestedProject = params.get('project') || params.get('project_id') || '';
      const requestedStatus = params.get('status') || '';
      if (['open', 'resolved', 'in_progress', 'mine'].includes(requestedStatus)) {
        statusFilter = requestedStatus;
      }

      if (requestedProject) {
        activeProjectFilter = requestedProject;
        activeProjectId = requestedProject;
      }

      try {
        const data = await api.listFeedback();
        projects = Array.isArray(data) ? [] : (data.projects || []);
        annotations = Array.isArray(data) ? data : (data.annotations || []);

        // Default: all collapsed. If URL specifies ?project=..., filter & open that project.
        if (requestedProject && projects.some((p) => p.id === requestedProject)) {
          activeProjectFilter = requestedProject;
          activeProjectId = requestedProject;
          const targetProj = projects.find((p) => p.id === requestedProject);
          if (targetProj) {
            setBreadcrumb([
              { label: 'Dự án', href: 'projects.php' },
              { label: targetProj.name, href: `project.php?id=${encodeURIComponent(targetProj.id)}` },
              { label: 'Góp ý' }
            ]);
          }
        } else {
          activeProjectFilter = null;
          activeProjectId = null;
          setBreadcrumb([
            { label: 'Dự án', href: 'projects.php' },
            { label: 'Quản lý góp ý' }
          ]);
        }

        renderFeedbackPage(page);
      } catch (err) {
        page.replaceChildren(
          emptyState({
            title: 'Lỗi tải dữ liệu',
            text: err.message || 'Không thể tải danh sách góp ý.'
          })
        );
      }
    }
  });
}
