import { boot } from './app.js';
import { api } from './api.js';
import {
  avatarEl,
  bindTabs,
  confirmDialog,
  el,
  emptyState,
  fieldError,
  formatDate,
  icon,
  openModal,
  renderPagination,
  roleBadge,
  roleLabel,
  setBusy,
  toast
} from './ui.js';

const params = new URLSearchParams(location.search);
const projectId = params.get('id');

function fileInput() {
  return el('input', { type: 'file', accept: 'image/png,image/jpeg,.png,.jpg,.jpeg', class: 'field-input' });
}

function fileDropzone(onFileSelected) {
  const hiddenInput = el('input', {
    type: 'file',
    accept: 'image/png,image/jpeg,.png,.jpg,.jpeg',
    style: 'display: none;'
  });

  const previewImg = el('img', {
    style: 'max-height: 120px; border-radius: 6px; object-fit: contain; display: none;'
  });
  const promptIcon = icon('upload') || el('span', { text: '📁' });
  const promptText = el('div', { class: 'dropzone-text' }, [
    el('strong', { text: 'Kéo và thả tệp ảnh vào đây' }),
    el('span', { class: 'help', text: 'hoặc nhấp để chọn tệp từ máy tính (PNG, JPEG tối đa 10 MB)' })
  ]);
  const fileInfo = el('div', {
    class: 'dropzone-file-info',
    style: 'display: none; font-size: 13px; color: var(--primary); font-weight: 500; margin-top: 6px;'
  });

  const zone = el('div', {
    class: 'file-dropzone-box',
    style: 'border: 2px dashed var(--border); border-radius: 10px; padding: 24px 16px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: var(--surface); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;'
  }, [
    hiddenInput,
    promptIcon,
    promptText,
    previewImg,
    fileInfo
  ]);

  zone.addEventListener('click', () => hiddenInput.click());

  function handleFile(file) {
    if (!file) return;
    fileInfo.textContent = `Đã chọn: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    fileInfo.style.display = 'block';
    if (file.type.startsWith('image/')) {
      previewImg.src = URL.createObjectURL(file);
      previewImg.style.display = 'block';
      promptIcon.style.display = 'none';
      promptText.style.display = 'none';
    }
    zone.style.borderColor = 'var(--primary)';
    zone.style.background = 'rgba(214, 36, 92, 0.04)';
    onFileSelected(file);
  }

  hiddenInput.addEventListener('change', () => {
    if (hiddenInput.files?.[0]) handleFile(hiddenInput.files[0]);
  });

  zone.addEventListener('dragover', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--primary)';
    zone.style.background = 'rgba(214, 36, 92, 0.08)';
  });

  zone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--border)';
    zone.style.background = 'var(--surface)';
  });

  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.style.borderColor = 'var(--border)';
    zone.style.background = 'var(--surface)';
    if (e.dataTransfer?.files?.[0]) {
      handleFile(e.dataTransfer.files[0]);
    }
  });

  return { zone, hiddenInput };
}

async function chooseAndValidate(file, errorNode) {
  errorNode.textContent = '';
  if (!file) {
    errorNode.textContent = 'Hãy chọn một tệp PNG hoặc JPEG.';
    return null;
  }
  if (!file.type.match(/^image\/(png|jpeg|jpg)$/) && !file.name.match(/\.(png|jpe?g)$/i)) {
    errorNode.textContent = 'Định dạng tệp không hợp lệ. Chỉ chấp nhận ảnh PNG hoặc JPEG.';
    return null;
  }
  if (file.size > 10 * 1024 * 1024) {
    errorNode.textContent = 'Dung lượng ảnh vượt quá giới hạn 10 MB.';
    return null;
  }
  try {
    return await api.validateImageFile(file);
  } catch (err) {
    errorNode.textContent = err.message;
    return null;
  }
}

boot({
  active: 'projects',
  title: 'Chi tiết dự án',
  async render({ setBreadcrumb, setPageTitle, session }) {
    if (!projectId) throw new Error('Thiếu mã dự án.');
    const project = await api.getProject(projectId);
    document.title = `${project.name} — DesignMark`;
    setPageTitle('Chi tiết dự án');
    setBreadcrumb([
      { label: 'Dự án', href: 'projects.php' },
      { label: project.name, title: project.name }
    ]);
    const page = document.getElementById('page');
    const panels = {
      screens: el('div', { id: 'panel-screens', role: 'tabpanel' }),
      members: el('div', { id: 'panel-members', role: 'tabpanel', hidden: true }),
      activity: el('div', { id: 'panel-activity', role: 'tabpanel', hidden: true })
    };
    const tabs = el('div', { class: 'tabs', role: 'tablist', 'aria-label': 'Mục dự án' }, [
      tabButton('screens', 'Màn hình thiết kế', true, 'panels', project.screenCount),
      tabButton('members', 'Thành viên', false, 'users', project.members?.length),
      tabButton('activity', 'Lịch sử', false, 'clock')
    ]);

    function switchTab(name) {
      tabs.querySelectorAll('[role="tab"]').forEach((button) => {
        const on = button.dataset.tab === name;
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-selected', on ? 'true' : 'false');
        button.tabIndex = on ? 0 : -1;
      });
      Object.entries(panels).forEach(([key, panel]) => {
        panel.hidden = key !== name;
      });
    }

    bindTabs(tabs, switchTab);

    // Header component
    const onUploadScreen = () => {
      switchTab('screens');
      openUpload(project, panels.screens);
    };
    const headerEl = renderProjectHeader(project, () => switchTab('members'), onUploadScreen);

    page.replaceChildren(
      headerEl,
      ...(project.myRole === 'admin_observer' ? [
        el('div', { class: 'observer-banner', style: 'background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; color: #3730a3; font-size: 14px; display: flex; align-items: center; gap: 10px;' }, [
          icon('users'),
          el('span', { text: 'Chế độ giám sát hệ thống: Bạn đang xem dữ liệu dự án với tư cách Quản trị viên (Chỉ xem dữ liệu, không có quyền chỉnh sửa dự án hoặc quản lý thành viên thay Manager).' })
        ])
      ] : []),
      tabs,
      panels.screens,
      panels.members,
      panels.activity
    );

    await renderScreens(panels.screens, project);
    await renderMembers(panels.members, project);
    await renderActivity(panels.activity, project);
  }
});

function tabButton(name, label, selected, iconName, countBadge) {
  return el('button', {
    type: 'button',
    class: `tab${selected ? ' is-active' : ''}`,
    role: 'tab',
    id: `tab-${name}`,
    'aria-selected': selected ? 'true' : 'false',
    'aria-controls': `panel-${name}`,
    tabindex: selected ? '0' : '-1',
    dataset: { tab: name }
  }, [
    iconName ? icon(iconName) : null,
    el('span', { class: 'tab-label-text', text: label }),
    (countBadge !== undefined && countBadge !== null) ? el('span', { class: 'tab-badge', text: String(countBadge) }) : null
  ].filter(Boolean));
}

/**
 * 1. PHẦN THÔNG TIN DỰ ÁN TINH GỌN (Section II.1 & III)
 * Giữ tên nổi bật (tối đa 2 dòng), trạng thái, vai trò, avatar stack thành viên, mô tả thu gọn
 * Primary: "+ Tải màn hình", Secondary: "Danh sách góp ý", Kebab menu: "Chuyển vào thùng rác"
 */
function renderProjectHeader(project, onOpenMembersTab, onUploadScreen) {
  // Lọc chỉ giữ lại mô tả dự án (mục đích, phạm vi, tính năng), không hiển thị chức vụ/phân quyền của các thành viên
  function cleanProjectDescription(desc) {
    if (!desc) return '';
    const lines = desc.split('\n').map((l) => l.trim()).filter(Boolean);
    const cleanLines = lines.filter((l) => {
      if (l.startsWith('•') || l.startsWith('-') || l.startsWith('*')) return false;
      if (l.includes('(@') && (l.includes('Vai trò') || l.includes('Chủ dự án') || l.includes('Admin') || l.includes('Reviewer') || l.includes('Viewer') || l.includes('Manager'))) return false;
      if (l.startsWith('Vai trò của bạn trong dự án:')) return false;
      return true;
    });
    let text = cleanLines.join(' ');
    text = text.replace(/Dự án do [^.]+phân định ranh giới chức năng rõ ràng cho từng thành viên:?/gi, '').trim();
    text = text.replace(/thiết lập phân quyền phân định ranh giới chức năng rõ ràng cho từng thành viên:?/gi, '').trim();
    text = text.replace(/cho từng thành viên:?/gi, '').trim();
    text = text.replace(/:\s*$/, '.').trim();
    if (text && !text.endsWith('.') && !text.endsWith('!') && !text.endsWith('?')) {
      text += '.';
    }
    return text;
  }

  let cleanDesc = cleanProjectDescription(project.description);
  const canManage = project.myRole === 'manager' || project.myRole === 'designer' || project.myRole === 'owner';

  const descTextEl = el('p', {
    class: 'project-desc-text is-clamped',
    text: cleanDesc
  });

  const toggleBtn = el('button', {
    type: 'button',
    class: 'desc-toggle-btn',
    style: 'display: none;',
    'aria-haspopup': 'dialog'
  }, [
    icon('chevron'),
    el('span', { text: 'Xem thêm' })
  ]);

  // Đề xuất nội dung mô tả tinh gọn (mục đích, phạm vi, tính năng, giá trị sản phẩm; loại bỏ danh sách thành viên trùng lặp)
  function getProposedDescription(currentDesc) {
    const cleaned = cleanProjectDescription(currentDesc);
    if (!cleaned) {
      return 'Nền tảng đánh giá và góp ý giao diện chuẩn pixel dành cho đội ngũ Product & Design, hỗ trợ tải lên bản vẽ màn hình, so sánh các phiên bản và thảo luận phản hồi trực quan theo thời gian thực.';
    }
    if (cleaned.length > 50) return cleaned;
    return cleaned + ' Nền tảng hỗ trợ tải lên các bản vẽ màn hình, so sánh các phiên bản giao diện (A/B testing), ghim tọa độ góp ý trực quan và thảo luận luồng trải nghiệm người dùng theo thời gian thực.';
  }

  function hasMemberDuplicates(desc) {
    return desc && (desc.includes('•') || desc.includes('(@') || desc.includes('thành viên:') || desc.includes('phân quyền'));
  }

  function openDescModal(startInEditMode = false) {
    let modalInstance = null;
    let isEditing = startInEditMode && canManage;
    const boxEl = el('div', { class: 'project-desc-modal-box' });

    function renderModalContent() {
      const currentCleanDesc = cleanProjectDescription(project.description);
      const hasDup = hasMemberDuplicates(project.description);
      const proposedDesc = getProposedDescription(project.description);

      const headerMeta = el('div', { class: 'project-desc-modal-header-meta' }, [
        el('span', { class: 'project-desc-modal-project-name', text: project.name }),
        project.category ? el('span', { class: 'badge badge-category', text: project.category }) : null
      ].filter(Boolean));

      if (!isEditing) {
        // CHẾ ĐỘ XEM
        const textScroll = el('div', { class: 'project-desc-modal-body-scroll', tabindex: '0' }, [
          el('p', { class: 'project-desc-modal-text', text: currentCleanDesc || 'Chưa có mô tả chi tiết cho dự án này.' })
        ]);

        const suggestNotice = (canManage && hasDup) ? el('div', { class: 'project-desc-suggest-box' }, [
          el('div', { class: 'project-desc-suggest-head' }, [
            icon('zap'),
            el('span', { text: 'Tối ưu nội dung mô tả dự án' })
          ]),
          el('p', { class: 'project-desc-suggest-text', text: 'Hệ thống đã tự động lọc bỏ danh sách thành viên và phân quyền khỏi phần mô tả để chỉ hiển thị mục đích và phạm vi dự án (thông tin thành viên đã có trong tab Thành viên).' })
        ]) : null;

        const editBtn = canManage ? el('button', {
          type: 'button',
          class: 'btn btn-secondary btn-edit-desc',
          style: 'display: inline-flex; align-items: center; gap: 6px;',
          onclick: () => {
            isEditing = true;
            renderModalContent();
          }
        }, [icon('edit'), el('span', { text: 'Chỉnh sửa mô tả' })]) : null;

        const closeBtn = el('button', {
          type: 'button',
          class: 'btn btn-secondary',
          onclick: () => {
            if (modalInstance && typeof modalInstance.close === 'function') {
              modalInstance.close();
            }
          }
        }, [el('span', { text: 'Đóng' })]);

        boxEl.replaceChildren(
          headerMeta,
          textScroll,
          ...(suggestNotice ? [suggestNotice] : []),
          el('div', { class: 'project-desc-modal-actions' }, [
            editBtn,
            closeBtn
          ].filter(Boolean))
        );
      } else {
        // CHẾ ĐỘ CHỈNH SỬA (Chỉ Quản lý)
        const textarea = el('textarea', {
          class: 'project-desc-textarea',
          rows: 5,
          placeholder: 'Nhập mục đích, phạm vi, tính năng và giá trị của dự án...'
        });
        textarea.value = currentCleanDesc;

        const proposalBox = el('div', { class: 'project-desc-suggest-box' }, [
          el('div', { class: 'project-desc-suggest-head' }, [
            icon('zap'),
            el('span', { text: 'Nội dung đề xuất chuẩn (chỉ nêu mục đích & tính năng):' })
          ]),
          el('p', { class: 'project-desc-suggest-preview', text: proposedDesc }),
          el('div', { style: 'margin-top: 4px;' }, [
            el('button', {
              type: 'button',
              class: 'btn btn-sm btn-secondary',
              style: 'color: var(--primary); font-weight: 600;',
              onclick: () => {
                textarea.value = proposedDesc;
                textarea.focus();
              }
            }, [icon('check'), el('span', { text: 'Áp dụng đề xuất này' })])
          ])
        ]);

        const cancelBtn = el('button', {
          type: 'button',
          class: 'btn btn-secondary',
          onclick: () => {
            isEditing = false;
            renderModalContent();
          }
        }, [el('span', { text: 'Hủy' })]);

        const saveBtn = el('button', {
          type: 'button',
          class: 'btn btn-primary',
          style: 'display: inline-flex; align-items: center; gap: 6px;',
          onclick: async () => {
            const newDesc = cleanProjectDescription(textarea.value.trim());
            const ok = await confirmDialog({
              title: 'Cập nhật mô tả dự án',
              message: 'Bạn có chắc chắn muốn lưu nội dung mô tả mới cho dự án này?',
              confirmText: 'Lưu thay đổi',
              cancelText: 'Xem lại'
            });
            if (!ok) return;

            setBusy(saveBtn, true, 'Đang lưu...');
            try {
              await api.updateProject(project.id, { description: newDesc });
              project.description = newDesc;
              cleanDesc = newDesc;
              descTextEl.textContent = newDesc;
              updateToggleState();
              toast('Đã cập nhật mô tả dự án thành công.', 'success');
              isEditing = false;
              renderModalContent();
            } catch (err) {
              setBusy(saveBtn, false);
              toast(err.message || 'Lỗi cập nhật mô tả.', 'error');
            }
          }
        }, [icon('check'), el('span', { text: 'Lưu mô tả mới' })]);

        boxEl.replaceChildren(
          headerMeta,
          el('div', { style: 'display: flex; flex-direction: column; gap: 6px;' }, [
            el('label', { class: 'field-label', text: 'Nội dung mô tả dự án (mục đích, phạm vi, tính năng)' }),
            textarea,
            el('p', { class: 'help', text: 'Mô tả dự án chỉ nêu mục đích và giá trị sản phẩm. Chức năng và vai trò của các thành viên được quản lý tập trung trong tab Thành viên.' })
          ]),
          proposalBox,
          el('div', { class: 'project-desc-modal-actions' }, [
            cancelBtn,
            saveBtn
          ])
        );

        setTimeout(() => textarea.focus(), 60);
      }
    }

    renderModalContent();

    modalInstance = openModal({
      title: 'Mô tả dự án',
      size: 'project-desc-modal-card',
      content: boxEl
    });
  }

  function updateToggleState() {
    if (!cleanDesc) {
      toggleBtn.hidden = true;
      toggleBtn.style.display = 'none';
      return;
    }

    // Nếu phần tử chưa được gắn vào DOM hoặc chưa render layout (clientHeight === 0), hoãn lại
    if (!descTextEl.isConnected || descTextEl.clientHeight === 0) {
      requestAnimationFrame(updateToggleState);
      return;
    }

    // Luôn giữ is-clamped (tối đa 2 dòng) để header luôn gọn gàng
    descTextEl.classList.add('is-clamped');

    // Kiểm tra trực tiếp bằng scrollHeight và clientHeight
    let overflows = descTextEl.scrollHeight > (descTextEl.clientHeight + 2);

    // Kiểm tra dự phòng bằng clone đo chiều cao thực tế khi không clamp để đảm bảo phát hiện chính xác khi vượt quá 2 dòng
    if (!overflows && descTextEl.clientHeight > 0 && descTextEl.clientWidth > 0) {
      const clone = descTextEl.cloneNode(true);
      clone.classList.remove('is-clamped');
      clone.style.cssText = 'position: absolute !important; visibility: hidden !important; pointer-events: none !important; display: block !important; -webkit-line-clamp: unset !important; width: ' + descTextEl.clientWidth + 'px !important; max-width: ' + descTextEl.clientWidth + 'px !important; margin: 0 !important; padding: 0 !important;';
      document.body.appendChild(clone);
      const fullHeight = clone.clientHeight || clone.offsetHeight;
      document.body.removeChild(clone);
      overflows = fullHeight > (descTextEl.clientHeight + 2);
    }

    toggleBtn.hidden = !overflows;
    toggleBtn.style.display = overflows ? 'inline-flex' : 'none';
  }

  toggleBtn.onclick = () => {
    openDescModal();
  };

  if (typeof ResizeObserver !== 'undefined') {
    const ro = new ResizeObserver(() => {
      updateToggleState();
    });
    ro.observe(descTextEl);
  }
  window.addEventListener('resize', updateToggleState);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(updateToggleState);
  }

  requestAnimationFrame(updateToggleState);
  setTimeout(updateToggleState, 60);
  setTimeout(updateToggleState, 300);

  const descContainer = cleanDesc ? el('div', { class: 'project-desc-collapsible' }, [
    descTextEl,
    toggleBtn
  ]) : null;

  const canUpload = project.myRole === 'manager' || project.myRole === 'designer' || project.myRole === 'owner';
  const uploadBtn = (canUpload && onUploadScreen) ? el('button', {
    type: 'button',
    class: 'btn btn-primary',
    'data-testid': 'upload-image',
    style: 'display: inline-flex; align-items: center; gap: 6px;',
    onclick: onUploadScreen
  }, [icon('plus'), el('span', { text: 'Tải màn hình' })]) : null;

  // Kebab menu (3 chấm) cho các hành động quản trị (Chỉnh sửa mô tả, Chuyển vào thùng rác)
  let kebabMenu = null;
  if (canManage) {
    const dropdownMenu = el('div', { class: 'project-dropdown-menu' }, [
      el('button', {
        type: 'button',
        class: 'project-dropdown-item',
        onclick: (e) => {
          e.stopPropagation();
          dropdownMenu.classList.remove('is-open');
          openDescModal(true);
        }
      }, [icon('edit'), el('span', { text: 'Chỉnh sửa mô tả' })]),
      el('button', {
        type: 'button',
        class: 'project-dropdown-item is-danger',
        onclick: async (e) => {
          e.stopPropagation();
          dropdownMenu.classList.remove('is-open');
          const ok = await confirmDialog({
            title: 'Chuyển dự án vào thùng rác',
            message: `Bạn có chắc chắn muốn chuyển dự án "${project.name}" vào thùng rác? Dự án sẽ tự động bị xóa vĩnh viễn sau 30 ngày nếu không khôi phục.`,
            confirmText: 'Chuyển vào thùng rác',
            cancelText: 'Hủy bỏ',
            danger: true
          });
          if (!ok) return;
          try {
            const res = await api.deleteProject(project.id);
            toast(res.message || 'Đã chuyển dự án vào thùng rác.', 'info');
            setTimeout(() => {
              location.href = 'projects.php';
            }, 400);
          } catch (err) {
            toast(err.message || 'Lỗi khi chuyển vào thùng rác.', 'error');
          }
        }
      }, [icon('trash'), el('span', { text: 'Chuyển vào thùng rác' })])
    ]);

    const moreBtn = el('button', {
      type: 'button',
      class: 'btn-icon-more project-kebab-btn',
      title: 'Tùy chọn khác',
      'aria-label': 'Tùy chọn khác',
      'aria-haspopup': 'true',
      'aria-expanded': 'false',
      onclick: (e) => {
        e.stopPropagation();
        const isOpen = dropdownMenu.classList.toggle('is-open');
        moreBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      }
    }, [icon('more')]);

    kebabMenu = el('div', { class: 'project-more-wrap' }, [
      moreBtn,
      dropdownMenu
    ]);

    document.addEventListener('click', (e) => {
      if (!kebabMenu.contains(e.target)) {
        dropdownMenu.classList.remove('is-open');
        moreBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Member avatar pile
  const members = project.members || [];
  const avatarStackEls = members.slice(0, 3).map((m) => {
    const av = avatarEl(m, 22);
    av.title = `${m.name} (@${m.username})`;
    return av;
  });

  return el('div', { class: 'project-head-card' }, [
    el('div', { class: 'project-head-title-row' }, [
      el('div', { class: 'project-head-main' }, [
        el('div', { class: 'project-title-badge-row' }, [
          el('h1', { class: 'project-page-title', text: project.name, title: project.name }),
          project.category ? el('span', { class: 'badge badge-category', text: project.category }) : null
        ].filter(Boolean)),
        el('div', { class: 'project-head-meta' }, [
          el('span', { class: 'status-chip is-live', title: 'Trạng thái dự án: Đang hoạt động' }, [
            el('span', { class: 'chip-pulse-dot' }),
            el('span', { text: 'Đang hoạt động' })
          ]),
          el('span', { class: 'tag tag-role', style: 'display: inline-flex; align-items: center; gap: 5px;' }, [
            el('span', { style: 'color: var(--muted); font-size: 12px;', text: 'Vai trò: ' }),
            roleBadge(project.myRole)
          ]),
          el('button', {
            type: 'button',
            class: 'member-count-pill',
            title: 'Xem danh sách và phân quyền thành viên',
            onclick: () => {
              if (typeof onOpenMembersTab === 'function') onOpenMembersTab();
            }
          }, [
            avatarStackEls.length ? el('span', { class: 'member-avatar-stack' }, avatarStackEls) : icon('users'),
            el('strong', { text: String(members.length || 1) }),
            el('span', { text: 'thành viên' })
          ]),
          project.updatedAt ? el('span', { class: 'project-updated-badge' }, [
            icon('clock'),
            el('span', { text: `Cập nhật ${formatDate(project.updatedAt)}` })
          ]) : null
        ].filter(Boolean))
      ]),
      el('div', { class: 'inline-actions' }, [
        uploadBtn,
        el('a', {
          class: 'btn btn-secondary',
          href: `feedback.php?project=${encodeURIComponent(project.id)}`,
          style: 'display: inline-flex; align-items: center; gap: 6px;'
        }, [
          icon('message'),
          el('span', { text: 'Danh sách góp ý' })
        ]),
        kebabMenu
      ].filter(Boolean))
    ]),
    descContainer
  ].filter(Boolean));
}

/**
 * 2. THỐNG KÊ DỰ ÁN NGẮN GỌN VÀ TƯƠNG TÁC (Section II.2)
 * Hiển thị số liệu thật từ DB, có thể click để lọc màn hình tương ứng
 */
function renderProjectStats(project, screens, activeFilter, onSelectFilter) {
  const totalScreens = screens.length;
  const totalComments = project.commentCount || 0;
  const openComments = project.openCount || 0;
  const resolvedComments = project.resolvedCount || 0;

  const cardScreens = el('div', {
    class: `project-stat-card stat-screens ${activeFilter === 'all' ? 'is-active' : ''}`,
    role: 'button',
    tabindex: '0',
    title: 'Nhấn để xem tất cả màn hình',
    onclick: () => onSelectFilter('all')
  }, [
    el('div', { class: 'project-stat-top' }, [
      el('div', { class: 'project-stat-icon-wrap icon-screens' }, [icon('panels')]),
      el('span', { class: 'project-stat-badge' }, [el('span', { text: 'Tất cả' })])
    ]),
    el('div', { class: 'project-stat-body' }, [
      el('div', { class: 'project-stat-value', text: String(totalScreens) }),
      el('div', { class: 'project-stat-label', text: 'Màn hình thiết kế' })
    ]),
    el('div', { class: 'project-stat-footer' }, [
      el('span', { class: 'project-stat-hint', text: 'Tất cả màn hình thiết kế' }),
      icon('chevron')
    ])
  ]);

  const isHasCommentsActive = activeFilter === 'has_comments';
  const cardTotal = el('div', {
    class: `project-stat-card stat-comments ${isHasCommentsActive ? 'is-active' : ''}`,
    role: 'button',
    tabindex: '0',
    title: 'Nhấn để lọc các màn hình có góp ý (lọc trực tiếp trong trang)',
    onclick: () => onSelectFilter('has_comments')
  }, [
    el('div', { class: 'project-stat-top' }, [
      el('div', { class: 'project-stat-icon-wrap icon-comments' }, [icon('message')]),
      el('span', { class: `project-stat-badge ${isHasCommentsActive ? 'badge-primary-pill' : 'badge-link'}` }, [
        el('span', { text: isHasCommentsActive ? 'Đang lọc' : 'Có góp ý' })
      ])
    ]),
    el('div', { class: 'project-stat-body' }, [
      el('div', { class: 'project-stat-value', text: String(totalComments) }),
      el('div', { class: 'project-stat-label', text: 'Tổng góp ý' })
    ]),
    el('div', { class: 'project-stat-footer' }, [
      el('span', { class: 'project-stat-hint', text: isHasCommentsActive ? 'Nhấn để hủy lọc' : 'Lọc màn hình có góp ý' }),
      icon('chevron')
    ])
  ]);

  const cardOpen = el('div', {
    class: `project-stat-card stat-warning ${activeFilter === 'open' ? 'is-active' : ''}`,
    role: 'button',
    tabindex: '0',
    title: 'Nhấn để lọc các màn hình có góp ý chưa xử lý',
    onclick: () => onSelectFilter('open')
  }, [
    el('div', { class: 'project-stat-top' }, [
      el('div', { class: 'project-stat-icon-wrap icon-warning' }, [icon('alert')]),
      el('span', { class: 'project-stat-badge badge-warning-pill' }, [
        openComments > 0 ? el('span', { class: 'stat-pulse-dot' }) : null,
        el('span', { text: openComments > 0 ? `${openComments} cần làm` : 'Đã sạch' })
      ].filter(Boolean))
    ]),
    el('div', { class: 'project-stat-body' }, [
      el('div', { class: 'project-stat-value', text: String(openComments) }),
      el('div', { class: 'project-stat-label', text: 'Chưa xử lý' })
    ]),
    el('div', { class: 'project-stat-footer' }, [
      el('span', { class: 'project-stat-hint', text: openComments > 0 ? 'Cần xử lý & chỉnh sửa' : 'Đã sạch góp ý tồn đọng' }),
      icon('chevron')
    ])
  ]);

  const cardResolved = el('div', {
    class: `project-stat-card stat-success ${activeFilter === 'resolved' ? 'is-active' : ''}`,
    role: 'button',
    tabindex: '0',
    title: 'Nhấn để lọc các màn hình đã giải quyết xong góp ý',
    onclick: () => onSelectFilter('resolved')
  }, [
    el('div', { class: 'project-stat-top' }, [
      el('div', { class: 'project-stat-icon-wrap icon-success' }, [icon('check')]),
      el('span', { class: 'project-stat-badge badge-success-pill' }, [el('span', { text: 'Hoàn tất' })])
    ]),
    el('div', { class: 'project-stat-body' }, [
      el('div', { class: 'project-stat-value', text: String(resolvedComments) }),
      el('div', { class: 'project-stat-label', text: 'Đã xử lý' })
    ]),
    el('div', { class: 'project-stat-footer' }, [
      el('span', { class: 'project-stat-hint', text: 'Góp ý hoàn tất' }),
      icon('chevron')
    ])
  ]);

  return el('div', { class: 'project-stats-grid' }, [
    cardScreens,
    cardTotal,
    cardOpen,
    cardResolved
  ]);
}

/**
 * 3. DANH SÁCH MÀN HÌNH THIẾT KẾ (Section III)
 * Hỗ trợ Grid View, List View, Tìm kiếm theo tên, Bộ lọc trạng thái, Sắp xếp
 */
async function renderScreens(panel, project) {
  const canUpload = project.myRole === 'manager' || project.myRole === 'owner';
  const canReorder = project.myRole === 'manager' || project.myRole === 'owner' || project.myRole === 'reviewer';

  let searchQuery = '';
  const urlParams = new URLSearchParams(location.search);
  let statusFilter = urlParams.get('status') || 'all'; // 'all' | 'has_comments' | 'open' | 'resolved' | 'multi_version'
  let sortOption = 'order'; // 'order' | 'updated' | 'name' | 'comments'
  let viewMode = localStorage.getItem('dm_screens_view_mode') || 'grid'; // 'grid' | 'list'

  let screensPage = parseInt(urlParams.get('page') || '1', 10);
  let defaultLimit = viewMode === 'grid' ? 9 : 10;
  let screensLimit = parseInt(urlParams.get('limit') || String(defaultLimit), 10);
  if (isNaN(screensPage) || screensPage < 1) screensPage = 1;
  if (isNaN(screensLimit) || screensLimit < 1) screensLimit = defaultLimit;

  function updateUrlState() {
    const url = new URL(location.href);
    if (screensPage > 1) {
      url.searchParams.set('page', screensPage);
    } else {
      url.searchParams.delete('page');
    }
    const defLimit = viewMode === 'grid' ? 9 : 10;
    if (screensLimit !== defLimit) {
      url.searchParams.set('limit', screensLimit);
    } else {
      url.searchParams.delete('limit');
    }
    if (statusFilter && statusFilter !== 'all') {
      url.searchParams.set('status', statusFilter);
    } else {
      url.searchParams.delete('status');
    }
    history.replaceState(null, '', url.toString());
  }

  const statsContainer = el('div', { id: 'project-stats-wrap' });
  const screensContainer = el('div', { id: 'screens-view-container' });

  // Clear filters button
  const clearFilterBtn = el('button', {
    type: 'button',
    class: 'btn-clear-filters',
    title: 'Xóa bộ lọc và tìm kiếm để xem tất cả',
    style: 'display: none;',
    onclick: () => {
      searchQuery = '';
      searchInput.value = '';
      statusFilter = 'all';
      screensPage = 1;
      updatePills();
      updateViews();
    }
  }, [
    icon('close'),
    el('span', { text: 'Xóa lọc' })
  ]);

  async function updateViews() {
    try {
      const hasActiveFilter = Boolean(searchQuery.trim() || statusFilter !== 'all');
      clearFilterBtn.style.display = hasActiveFilter ? 'inline-flex' : 'none';

      const res = await api.listScreens(project.id, {
        page: screensPage,
        limit: screensLimit,
        q: searchQuery,
        status: statusFilter,
        sort: sortOption
      });

      let pageScreens = [];
      let totalScreens = 0;
      let totalPages = 1;
      let stats = null;

      if (res && res.pagination) {
        pageScreens = res.screens || [];
        totalScreens = res.pagination.total || 0;
        totalPages = res.pagination.totalPages || 1;
        screensPage = res.pagination.page || screensPage;
        stats = res.stats || null;
      } else {
        const all = Array.isArray(res) ? res : [];
        totalScreens = all.length;
        totalPages = Math.ceil(totalScreens / screensLimit) || 1;
        if (screensPage > totalPages) screensPage = totalPages;
        const start = (screensPage - 1) * screensLimit;
        pageScreens = all.slice(start, start + screensLimit);
      }

      updateUrlState();

      // Cập nhật thanh thống kê
      statsContainer.replaceChildren(renderProjectStats(project, stats ? { length: stats.total } : pageScreens, statusFilter, (filterKey) => {
        statusFilter = (statusFilter === filterKey && filterKey !== 'all') ? 'all' : filterKey;
        updatePills(stats);
        screensPage = 1;
        updateViews();
      }));

      if (stats) {
        updatePills(stats);
        const screensTabBadge = document.querySelector('#tab-screens .tab-badge');
        if (screensTabBadge) {
          screensTabBadge.textContent = String(stats.total || 0);
        }
      }

      screensContainer.replaceChildren();

      if (!pageScreens.length) {
        screensContainer.append(emptyState({
          title: totalScreens ? 'Không tìm thấy màn hình khớp bộ lọc' : 'Chưa có màn hình thiết kế',
          text: totalScreens
            ? 'Hãy thử xóa từ khóa tìm kiếm hoặc chọn bộ lọc "Tất cả màn hình".'
            : 'Tải ảnh PNG hoặc JPEG để tạo màn hình thiết kế đầu tiên.',
          action: (!totalScreens && canUpload) ? el('button', {
            type: 'button',
            class: 'btn btn-primary',
            style: 'margin-top: 14px; display: inline-flex; align-items: center; gap: 6px;',
            onclick: () => openUpload(project, panel)
          }, [icon('plus'), el('span', { text: 'Tải màn hình' })]) : undefined
        }));
        return;
      }

      let contentEl;
      if (viewMode === 'list') {
        const listEl = el('div', { class: 'screen-list' });
        pageScreens.forEach((screen) => {
          listEl.append(screenListItem(screen, project, canUpload, () => updateViews()));
        });
        contentEl = listEl;
      } else {
        const gridEl = el('div', { class: 'screen-grid' });
        pageScreens.forEach((screen) => {
          gridEl.append(screenCard(screen, project, canUpload, () => updateViews()));
        });

        // Kéo thả sắp xếp thứ tự chỉ trên trang 1 khi dùng thứ tự tùy chỉnh
        if (canReorder && totalScreens > 1 && sortOption === 'order' && statusFilter === 'all' && !searchQuery.trim() && screensPage === 1) {
          setupDragAndDrop(gridEl, project);
        }

        contentEl = gridEl;
      }

      const paginationBar = renderPagination({
        currentPage: screensPage,
        pageSize: screensLimit,
        totalItems: totalScreens,
        showPageSizeSelect: true,
        pageSizeOptions: viewMode === 'grid' ? [9, 12, 24] : [10, 20, 50],
        showInfo: true,
        isCentered: true,
        itemLabel: 'màn hình',
        isStandalone: viewMode === 'grid',
        onPageChange: (newPage) => {
          screensPage = newPage;
          updateViews();
          screensContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },
        onPageSizeChange: (newLimit) => {
          screensLimit = newLimit;
          screensPage = 1;
          updateViews();
        }
      });

      screensContainer.replaceChildren(contentEl, ...(paginationBar ? [paginationBar] : []));
    } catch (err) {
      toast(err.message || 'Lỗi tải danh sách màn hình.', 'error');
    }
  }

  // Search input
  const searchInput = el('input', {
    type: 'search',
    class: 'field-input screens-search-input',
    placeholder: 'Tìm màn hình...',
    'aria-label': 'Tìm kiếm màn hình'
  });
  searchInput.addEventListener('input', () => {
    searchQuery = searchInput.value;
    screensPage = 1;
    updateViews();
  });

  const searchBox = el('div', { class: 'screens-search-box' }, [
    icon('search'),
    searchInput
  ]);

  // Filter pills
  const filterAllPill = el('button', {
    type: 'button',
    class: `screen-filter-pill ${statusFilter === 'all' ? 'is-active' : ''}`,
    text: 'Tất cả',
    onclick: () => {
      statusFilter = 'all';
      screensPage = 1;
      updatePills();
      updateViews();
    }
  });

  const filterHasCommentsPill = el('button', {
    type: 'button',
    class: `screen-filter-pill ${statusFilter === 'has_comments' ? 'is-active' : ''}`,
    text: 'Có góp ý',
    onclick: () => {
      statusFilter = (statusFilter === 'has_comments') ? 'all' : 'has_comments';
      screensPage = 1;
      updatePills();
      updateViews();
    }
  });

  const filterOpenPill = el('button', {
    type: 'button',
    class: `screen-filter-pill ${statusFilter === 'open' ? 'is-active' : ''}`,
    text: 'Chưa xử lý',
    onclick: () => {
      statusFilter = (statusFilter === 'open') ? 'all' : 'open';
      screensPage = 1;
      updatePills();
      updateViews();
    }
  });

  const filterResolvedPill = el('button', {
    type: 'button',
    class: `screen-filter-pill ${statusFilter === 'resolved' ? 'is-active' : ''}`,
    text: 'Đã xử lý',
    onclick: () => {
      statusFilter = (statusFilter === 'resolved') ? 'all' : 'resolved';
      screensPage = 1;
      updatePills();
      updateViews();
    }
  });

  const filterMultiVerPill = el('button', {
    type: 'button',
    class: `screen-filter-pill ${statusFilter === 'multi_version' ? 'is-active' : ''}`,
    text: 'Có bản mới',
    onclick: () => {
      statusFilter = (statusFilter === 'multi_version') ? 'all' : 'multi_version';
      screensPage = 1;
      updatePills();
      updateViews();
    }
  });

  function updatePills(stats = null) {
    filterAllPill.classList.toggle('is-active', statusFilter === 'all');
    filterHasCommentsPill.classList.toggle('is-active', statusFilter === 'has_comments');
    filterOpenPill.classList.toggle('is-active', statusFilter === 'open');
    filterResolvedPill.classList.toggle('is-active', statusFilter === 'resolved');
    filterMultiVerPill.classList.toggle('is-active', statusFilter === 'multi_version');
    if (stats) {
      filterAllPill.textContent = `Tất cả (${stats.total || 0})`;
      filterHasCommentsPill.textContent = `Có góp ý (${stats.hasComments || 0})`;
      filterOpenPill.textContent = `Chưa xử lý (${stats.open || 0})`;
      filterResolvedPill.textContent = `Đã xử lý (${stats.resolved || 0})`;
      filterMultiVerPill.textContent = `Có bản mới (${stats.multiVersion || 0})`;
    }
  }

  const filterPills = el('div', { class: 'screens-filter-pills' }, [
    filterAllPill,
    filterHasCommentsPill,
    filterOpenPill,
    filterResolvedPill,
    filterMultiVerPill
  ]);

  // Sort dropdown
  const sortSelect = el('select', {
    class: 'field-input screens-sort-select',
    'aria-label': 'Sắp xếp màn hình'
  }, [
    el('option', { value: 'order', text: 'Thứ tự tùy chỉnh' }),
    el('option', { value: 'updated', text: 'Mới cập nhật' }),
    el('option', { value: 'created', text: 'Ngày tạo' }),
    el('option', { value: 'name', text: 'Tên A–Z' }),
    el('option', { value: 'comments', text: 'Nhiều góp ý nhất' })
  ]);
  sortSelect.value = sortOption;
  sortSelect.addEventListener('change', () => {
    sortOption = sortSelect.value;
    screensPage = 1;
    updateViews();
  });

  // View mode switcher (Grid / List)
  const gridViewBtn = el('button', {
    type: 'button',
    class: `view-mode-btn ${viewMode === 'grid' ? 'is-active' : ''}`,
    title: 'Chế độ xem lưới (Grid View)',
    'aria-label': 'Chế độ xem lưới',
    onclick: () => {
      viewMode = 'grid';
      screensLimit = 9;
      screensPage = 1;
      localStorage.setItem('dm_screens_view_mode', 'grid');
      gridViewBtn.classList.add('is-active');
      listViewBtn.classList.remove('is-active');
      updateViews();
    }
  }, [icon('grid')]);

  const listViewBtn = el('button', {
    type: 'button',
    class: `view-mode-btn ${viewMode === 'list' ? 'is-active' : ''}`,
    title: 'Chế độ xem danh sách (List View)',
    'aria-label': 'Chế độ xem danh sách',
    onclick: () => {
      viewMode = 'list';
      screensLimit = 10;
      screensPage = 1;
      localStorage.setItem('dm_screens_view_mode', 'list');
      listViewBtn.classList.add('is-active');
      gridViewBtn.classList.remove('is-active');
      updateViews();
    }
  }, [icon('list')]);

  const viewModeGroup = el('div', { class: 'view-mode-group' }, [
    gridViewBtn,
    listViewBtn
  ]);

  const controlBar = el('div', { class: 'screens-control-bar' }, [
    el('div', { class: 'screens-control-left' }, [
      searchBox,
      filterPills,
      clearFilterBtn
    ]),
    el('div', { class: 'screens-control-right' }, [
      sortSelect,
      viewModeGroup
    ].filter(Boolean))
  ]);

  panel.replaceChildren(
    statsContainer,
    controlBar,
    screensContainer
  );

  updateViews();
}

/**
 * Setup HTML5 Drag-and-Drop Reordering for screens
 */
function setupDragAndDrop(grid, project) {
  let draggedCard = null;

  grid.addEventListener('dragstart', (e) => {
    const card = e.target.closest('.screen-card');
    if (!card) return;
    draggedCard = card;
    card.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.screenId || '');
  });

  grid.addEventListener('dragend', async () => {
    if (draggedCard) {
      draggedCard.classList.remove('is-dragging');
      draggedCard = null;
    }
    const cardElements = Array.from(grid.querySelectorAll('.screen-card'));
    const newOrder = cardElements.map((c) => c.dataset.screenId).filter(Boolean);
    if (newOrder.length > 1) {
      try {
        await api.reorderScreens(project.id, newOrder);
        toast('Đã lưu thứ tự màn hình mới.', 'success');
      } catch (err) {
        toast('Lỗi khi lưu thứ tự: ' + (err.message || 'Thất bại'), 'error');
      }
    }
  });

  grid.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const targetCard = e.target.closest('.screen-card');
    if (!targetCard || targetCard === draggedCard) return;

    const rect = targetCard.getBoundingClientRect();
    const midX = rect.left + rect.width / 2;
    if (e.clientX < midX) {
      grid.insertBefore(draggedCard, targetCard);
    } else {
      grid.insertBefore(draggedCard, targetCard.nextSibling);
    }
  });
}

/**
 * Screen Card in Grid View (Section III.1 & III.2)
 */
function screenCard(screen, project, canUpload, refreshScreens) {
  const latest = screen.latestVersion;
  const reviewUrl = latest
    ? `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}&version=${encodeURIComponent(latest.id)}`
    : `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}`;

  const canReorder = project.myRole === 'manager' || project.myRole === 'owner' || project.myRole === 'reviewer';

  const card = el('article', {
    class: 'card screen-card is-clickable',
    tabindex: '0',
    role: 'button',
    'aria-label': `Mở xem màn hình ${screen.name}`,
    title: `Nhấp để mở xem màn hình ${screen.name}`
  });
  card.dataset.screenId = screen.id;
  if (canReorder) {
    card.setAttribute('draggable', 'true');
  }

  // Click card to open review
  card.addEventListener('click', (e) => {
    if (e.target.closest('button') || e.target.closest('input') || e.target.closest('a') || e.target.closest('.screen-dropdown-menu')) {
      return;
    }
    location.href = reviewUrl;
  });

  card.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      if (e.target.closest('button') || e.target.closest('input') || e.target.closest('a')) return;
      e.preventDefault();
      location.href = reviewUrl;
    }
  });

  // Thumbnail with overlays
  const thumb = el('div', { class: 'screen-thumb' });
  if (latest) {
    const img = el('img', { alt: `Thumbnail ${screen.name}`, loading: 'lazy' });
    api.resolveImageUrl(latest.imageKey).then((url) => {
      img.src = url;
    }).catch(() => {
      img.alt = 'Không tải được thumbnail';
    });
    thumb.append(img);

    // Overlay badges
    const badgeWrap = el('div', { class: 'screen-thumb-badge-top' }, [
      el('span', { class: 'thumb-version-tag', text: latest.label || `v${latest.number}` }),
      (screen.openCount || 0) > 0
        ? el('span', { class: 'thumb-open-tag' }, [
            el('span', { class: 'stat-pulse-dot', style: 'background: #fff; width: 5px; height: 5px; margin-right: 4px;' }),
            el('span', { text: `${screen.openCount} chưa xử lý` })
          ])
        : el('span', { class: 'thumb-resolved-tag' }, [
            icon('check'),
            el('span', { text: 'Đã xử lý xong' })
          ])
    ]);
    const hoverOverlay = el('div', { class: 'screen-thumb-hover-overlay' }, [
      el('span', { class: 'thumb-hover-btn' }, [
        icon('pin'),
        el('span', { text: 'Mở góp ý' })
      ])
    ]);
    thumb.append(badgeWrap, hoverOverlay);
  }

  // Action Bar: Primary "Mở góp ý" + Secondary Menu
  const primaryReviewBtn = el('a', {
    class: 'btn btn-primary btn-sm btn-main-review',
    href: reviewUrl,
    onclick: (e) => e.stopPropagation()
  }, [
    icon('pin'),
    el('span', { text: 'Mở góp ý' })
  ]);

  const menuDropdown = createScreenActionMenu(screen, project, canUpload, refreshScreens);

  const actionBar = el('div', { class: 'screen-card-action-bar' }, [
    primaryReviewBtn,
    menuDropdown
  ]);

  card.append(
    thumb,
    el('div', { class: 'card-body' }, [
      el('h3', { class: 'screen-title', text: screen.name, title: screen.name }),
      el('p', { class: 'help screen-desc-clamp', text: screen.description || 'Chưa có mô tả chi tiết.' }),
      el('div', { class: 'screen-card-stats' }, [
        el('span', { class: 'stat-pill' }, [
          icon('grid'),
          el('span', { text: `${screen.versionCount || 1} phiên bản` })
        ]),
        el('span', { class: 'stat-pill' }, [
          icon('message'),
          el('span', { text: `${screen.commentCount || 0} góp ý` })
        ]),
        el('span', { class: `stat-pill${(screen.openCount || 0) > 0 ? ' is-open' : ' is-resolved'}` }, [
          icon((screen.openCount || 0) > 0 ? 'clock' : 'check'),
          el('span', { text: (screen.openCount || 0) > 0 ? `${screen.openCount} chưa xong` : 'Xong hết' })
        ]),
        el('span', { class: 'stat-pill' }, [
          icon('clock'),
          el('span', { text: latest ? `Bản ${latest.label}` : 'v1' })
        ])
      ]),
      actionBar
    ])
  );

  return card;
}

/**
 * Screen Item in List View (Section III.3)
 */
function screenListItem(screen, project, canUpload, refreshScreens) {
  const latest = screen.latestVersion;
  const reviewUrl = latest
    ? `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}&version=${encodeURIComponent(latest.id)}`
    : `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}`;

  const item = el('article', {
    class: 'screen-list-item is-clickable',
    tabindex: '0',
    role: 'button',
    onclick: (e) => {
      if (e.target.closest('button') || e.target.closest('input') || e.target.closest('a') || e.target.closest('.screen-dropdown-menu')) return;
      location.href = reviewUrl;
    }
  });

  const thumb = el('div', { class: 'screen-list-thumb' });
  if (latest) {
    const img = el('img', { alt: screen.name, loading: 'lazy' });
    api.resolveImageUrl(latest.imageKey).then((url) => { img.src = url; });
    thumb.append(img);
  }

  const titleRow = el('div', { class: 'screen-list-title-row' }, [
    el('h3', { class: 'screen-list-title', text: screen.name }),
    latest ? el('span', { class: 'badge badge-neutral', text: latest.label || `v${latest.number}` }) : null
  ].filter(Boolean));

  const main = el('div', { class: 'screen-list-main' }, [
    titleRow,
    el('p', { class: 'screen-list-desc', text: screen.description || 'Chưa có mô tả chi tiết.' })
  ]);

  const stats = el('div', { class: 'screen-list-stats' }, [
    el('span', { class: 'badge badge-neutral', text: `${screen.versionCount || 1} bản` }),
    el('span', { class: 'badge badge-neutral', text: `${screen.commentCount || 0} góp ý` }),
    (screen.openCount || 0) > 0
      ? el('span', { class: 'status-tag is-open', text: `${screen.openCount} chưa xử lý` })
      : el('span', { class: 'status-tag is-resolved', text: 'Đã xử lý xong' })
  ]);

  const primaryBtn = el('a', {
    class: 'btn btn-primary btn-sm',
    href: reviewUrl,
    onclick: (e) => e.stopPropagation()
  }, [icon('pin'), el('span', { text: 'Mở góp ý' })]);

  const menu = createScreenActionMenu(screen, project, canUpload, refreshScreens);

  const actions = el('div', { class: 'screen-list-actions' }, [
    primaryBtn,
    menu
  ]);

  item.append(thumb, main, stats, actions);
  return item;
}

/**
 * Secondary Action Dropdown Menu (Section III.1)
 * So sánh, Tải phiên bản mới, Đổi tên màn hình, Xóa màn hình
 */
function createScreenActionMenu(screen, project, canUpload, refreshScreens) {
  const wrap = el('div', { class: 'screen-menu-wrap' });

  const moreBtn = el('button', {
    type: 'button',
    class: 'btn-icon-more',
    title: 'Thao tác phụ trên màn hình',
    'aria-label': 'Thao tác phụ',
    onclick: (e) => {
      e.stopPropagation();
      const isOpen = dropdown.style.display === 'flex';
      // Close all other open dropdowns first
      document.querySelectorAll('.screen-dropdown-menu').forEach((d) => { d.style.display = 'none'; });
      document.querySelectorAll('.btn-icon-more').forEach((b) => { b.classList.remove('is-open'); });
      if (!isOpen) {
        dropdown.style.display = 'flex';
        moreBtn.classList.add('is-open');
      }
    }
  }, [icon('more')]);

  const compareItem = el('a', {
    class: 'screen-dropdown-item',
    href: `compare.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}`,
    onclick: (e) => e.stopPropagation()
  }, [icon('compare'), el('span', { text: 'So sánh phiên bản' })]);

  // Hidden file input for new version
  const fileVerInput = fileInput();
  fileVerInput.hidden = true;
  fileVerInput.addEventListener('change', async (e) => {
    e.stopPropagation();
    const file = fileVerInput.files?.[0];
    fileVerInput.value = '';
    if (!file) return;
    const error = el('p', { class: 'field-error' });
    const info = await chooseAndValidate(file, error);
    if (!info) {
      toast(error.textContent, 'error');
      return;
    }
    toast('Đang tải phiên bản mới...', 'info');
    try {
      const version = await api.addVersionFromFile(screen.id, file);
      toast(`Đã tải thành công ${version.label}. Góp ý cũ giữ nguyên tại phiên bản cũ.`, 'success');
      refreshScreens();
    } catch (err) {
      toast(err.message || 'Không thể lưu phiên bản mới.', 'error');
    }
  });

  const uploadVersionItem = canUpload ? el('button', {
    type: 'button',
    class: 'screen-dropdown-item',
    onclick: (e) => {
      e.stopPropagation();
      dropdown.style.display = 'none';
      moreBtn.classList.remove('is-open');
      fileVerInput.click();
    }
  }, [icon('upload'), el('span', { text: 'Tải phiên bản mới' })]) : null;

  const renameItem = canUpload ? el('button', {
    type: 'button',
    class: 'screen-dropdown-item',
    onclick: (e) => {
      e.stopPropagation();
      dropdown.style.display = 'none';
      moreBtn.classList.remove('is-open');
      openRenameModal(screen, refreshScreens);
    }
  }, [icon('edit'), el('span', { text: 'Đổi tên màn hình' })]) : null;

  const deleteItem = canUpload ? el('button', {
    type: 'button',
    class: 'screen-dropdown-item is-danger',
    onclick: async (e) => {
      e.stopPropagation();
      dropdown.style.display = 'none';
      moreBtn.classList.remove('is-open');
      const ok = await confirmDialog({
        title: 'Xóa màn hình thiết kế',
        message: `Bạn có chắc chắn muốn xóa vĩnh viễn màn hình "${screen.name}"? Toàn bộ các phiên bản ảnh và góp ý liên quan sẽ bị xóa sạch.`,
        confirmText: 'Xóa màn hình',
        cancelText: 'Hủy bỏ',
        danger: true
      });
      if (!ok) return;
      try {
        await api.deleteScreen(screen.id);
        toast(`Đã xóa màn hình "${screen.name}".`, 'success');
        refreshScreens();
      } catch (err) {
        toast(err.message || 'Không thể xóa màn hình.', 'error');
      }
    }
  }, [icon('trash'), el('span', { text: 'Xóa màn hình' })]) : null;

  const dropdown = el('div', {
    class: 'screen-dropdown-menu',
    style: 'display: none;'
  }, [
    compareItem,
    uploadVersionItem,
    renameItem,
    deleteItem
  ].filter(Boolean));

  // Close when clicking outside
  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) {
      dropdown.style.display = 'none';
      moreBtn.classList.remove('is-open');
    }
  });

  wrap.append(moreBtn, dropdown, fileVerInput);
  return wrap;
}

/**
 * Modal Đổi tên màn hình
 */
function openRenameModal(screen, onDone) {
  const nameInput = el('input', {
    type: 'text',
    class: 'field-input',
    id: 'rename-screen-input',
    value: screen.name,
    maxlength: '80'
  });
  const errorMsg = el('p', { class: 'field-error' });
  const submitBtn = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Lưu thay đổi' });

  const form = el('form', {}, [
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'rename-screen-input', text: 'Tên màn hình mới' }),
      nameInput,
      errorMsg
    ]),
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submitBtn
    ])
  ]);

  const modal = openModal({ title: 'Đổi tên màn hình', content: form });
  setTimeout(() => { nameInput.focus(); nameInput.select(); }, 50);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const val = nameInput.value.trim();
    if (!val) {
      errorMsg.textContent = 'Tên màn hình không được để trống.';
      return;
    }
    setBusy(submitBtn, true, 'Đang lưu...');
    try {
      await api.renameScreen(screen.id, val);
      modal.close();
      toast('Đã cập nhật tên màn hình.', 'success');
      onDone();
    } catch (err) {
      setBusy(submitBtn, false);
      errorMsg.textContent = err.message || 'Không đổi được tên màn hình.';
    }
  });
}

function openUpload(project, panel) {
  let selectedFile = null;
  const name = el('input', { class: 'field-input', id: 'screen-name', maxlength: '80', placeholder: 'Ví dụ: 01. Trang chủ Desktop' });
  const error = el('p', { class: 'field-error', id: 'screen-file-error' });
  const submit = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Tải lên' });

  const { zone, hiddenInput } = fileDropzone((file) => {
    selectedFile = file;
    error.textContent = '';
    if (!name.value.trim()) {
      const baseName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
      name.value = baseName.charAt(0).toUpperCase() + baseName.slice(1);
    }
  });

  const form = el('form', {}, [
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', for: 'screen-name', text: 'Tên màn hình' }),
      name
    ]),
    el('div', { class: 'field' }, [
      el('label', { class: 'field-label', text: 'Tệp thiết kế (PNG hoặc JPEG, tối đa 10 MB)' }),
      zone,
      error
    ]),
    el('p', { class: 'help', text: 'Ảnh được lưu trữ an toàn trên máy chủ. DesignMark giữ nguyên độ phân giải pixel gốc.' }),
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      submit
    ])
  ]);

  const modal = openModal({ title: 'Tải màn hình thiết kế mới', content: form });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    fieldError(name, '');
    error.textContent = '';
    if (!name.value.trim()) {
      fieldError(name, 'Tên màn hình không được để trống.');
      return;
    }
    const file = selectedFile || hiddenInput.files?.[0];
    const info = await chooseAndValidate(file, error);
    if (!info) return;
    setBusy(submit, true, 'Đang tải lên...');
    try {
      await api.createScreenFromFile(project.id, { name: name.value.trim(), file });
      modal.close();
      toast('Đã tạo màn hình và phiên bản v1.', 'success');
      const fresh = await api.getProject(project.id);
      Object.assign(project, fresh);
      await renderScreens(panel, fresh);
    } catch (err) {
      setBusy(submit, false);
      error.textContent = err.message || 'Không thể tải lên ảnh.';
      toast(err.message || 'Không lưu được ảnh.', 'error');
    }
  });
}

/**
 * 4. THÀNH VIÊN VÀ BẢNG HƯỚNG DẪN PHÂN QUYỀN (Section II.1 & IV)
 */
async function renderMembers(panel, project) {
  const canManage = project.myRole === 'manager' || project.myRole === 'owner';

  let memberPage = 1;
  let memberPageSize = 10;
  let memberSearchQuery = '';
  let searchDebounceTimer = null;

  const tableWrap = el('div', { class: 'table-wrap' });
  const headTitle = el('h2', { text: `Thành viên (${project.members?.length || ''})` });

  async function updateMemberTable() {
    try {
      const params = {
        page: memberPage,
        limit: memberPageSize,
        ...(memberSearchQuery ? { q: memberSearchQuery } : {})
      };
      const res = await api.listMembers(project.id, params);
      let pageMembers = [];
      let totalMembers = 0;

      if (res && res.pagination) {
        pageMembers = res.members || [];
        totalMembers = res.pagination.total || 0;
        memberPage = res.pagination.page || memberPage;
      } else {
        const all = Array.isArray(res) ? res : [];
        totalMembers = all.length;
        const totalPages = Math.ceil(totalMembers / memberPageSize) || 1;
        if (memberPage > totalPages) memberPage = totalPages;
        const start = (memberPage - 1) * memberPageSize;
        pageMembers = all.slice(start, start + memberPageSize);
      }

      headTitle.textContent = `Thành viên (${totalMembers})`;

      if (!totalMembers) {
        tableWrap.replaceChildren(emptyState({
          title: memberSearchQuery ? 'Không tìm thấy thành viên' : 'Chưa có thành viên',
          text: memberSearchQuery ? `Không có thành viên nào khớp với từ khóa "${memberSearchQuery}".` : 'Dự án hiện chưa có thành viên nào.'
        }));
        return;
      }

      const table = el('table', { class: 'data-table members-table' });
      const columns = ['Thành viên', 'Tài khoản', 'Email', 'Vai trò'];
      if (canManage) columns.push('Thao tác');

      table.append(el('thead', {}, [
        el('tr', {}, columns.map((label) => el('th', { scope: 'col', text: label })))
      ]));

      const body = el('tbody');
      pageMembers.forEach((member) => {
        const roleCell = canManage ? roleSelect(project, member, panel) : roleBadge(member.role);
        const isOwner = member.id === project.createdBy;
        const isSelf = window.DESIGNMARK_SESSION && window.DESIGNMARK_SESSION.userId === member.id;

        const actionCell = canManage ? el('td', {}, [
          (!isOwner && !isSelf) ? el('button', {
            type: 'button',
            class: 'btn-icon-more',
            title: `Xóa ${member.name} khỏi dự án`,
            'aria-label': `Xóa ${member.name} khỏi dự án`,
            style: 'color: var(--danger);',
            onclick: async () => {
              const ok = await confirmDialog({
                title: 'Xóa thành viên khỏi dự án',
                message: `Bạn có chắc chắn muốn xóa thành viên "${member.name}" (@${member.username}) khỏi dự án này?`,
                confirmText: 'Xóa thành viên',
                cancelText: 'Hủy',
                danger: true
              });
              if (!ok) return;
              try {
                await api.removeMember(project.id, member.id);
                toast(`Đã xóa ${member.name} khỏi dự án.`, 'info');
                const fresh = await api.getProject(project.id);
                await renderMembers(panel, fresh);
              } catch (err) {
                toast(err.message || 'Lỗi khi xóa thành viên.', 'error');
              }
            }
          }, [icon('trash')]) : el('span', { class: 'badge badge-neutral', text: isOwner ? 'Chủ dự án' : 'Bạn' })
        ]) : null;

        body.append(el('tr', {}, [
          el('td', {}, [el('div', { class: 'member-cell' }, [avatarEl(member, 32), el('span', { text: member.name })])]),
          el('td', { text: member.username }),
          el('td', { text: member.email || '—', style: 'color: var(--muted); font-size: 13.5px;' }),
          el('td', {}, [roleCell]),
          ...(actionCell ? [actionCell] : [])
        ]));
      });
      table.append(body);

      const paginationBar = renderPagination({
        currentPage: memberPage,
        pageSize: memberPageSize,
        totalItems: totalMembers,
        pageSizeOptions: [10, 20, 50, 100],
        itemLabel: 'thành viên',
        onPageChange: (newPage) => {
          memberPage = newPage;
          updateMemberTable();
        },
        onPageSizeChange: (newSize) => {
          memberPageSize = newSize;
          memberPage = 1;
          updateMemberTable();
        }
      });

      tableWrap.replaceChildren(table, ...(paginationBar ? [paginationBar] : []));
    } catch (err) {
      toast(err.message || 'Lỗi tải thành viên.', 'error');
    }
  }

  await updateMemberTable();

  // Search input for members
  const memberSearchInput = el('input', {
    type: 'search',
    class: 'field-input member-search-input',
    placeholder: 'Tìm theo tên, @username...',
    style: 'padding-left: 32px; height: 38px; width: 210px; font-size: 13.5px;'
  });
  memberSearchInput.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      memberSearchQuery = memberSearchInput.value.trim();
      memberPage = 1;
      updateMemberTable();
    }, 250);
  });
  const memberSearchWrap = el('div', { style: 'position: relative; display: inline-flex; align-items: center;' }, [
    el('span', { style: 'position: absolute; left: 10px; color: var(--muted); pointer-events: none; display: flex;' }, [icon('search')]),
    memberSearchInput
  ]);

  // Invite Form collapsible
  let invitePanel = null;
  let showInvite = false;
  let addMemberBtn = null;

  if (canManage) {
    const inviteInner = await inviteForm(project, panel);
    invitePanel = el('div', { id: 'project-invite-panel', class: 'member-invite-collapsible', hidden: true }, [inviteInner]);

    addMemberBtn = el('button', {
      type: 'button',
      class: 'btn btn-primary btn-add-member',
      style: 'display: inline-flex; align-items: center; gap: 6px;',
      onclick: () => {
        showInvite = !showInvite;
        invitePanel.hidden = !showInvite;
        addMemberBtn.classList.toggle('btn-secondary', showInvite);
        addMemberBtn.classList.toggle('btn-primary', !showInvite);
        addMemberBtn.replaceChildren(
          showInvite ? icon('close') : icon('plus'),
          el('span', { text: showInvite ? 'Đóng form thêm' : 'Thêm thành viên' })
        );
        if (showInvite) {
          const inp = invitePanel.querySelector('#invite-username-input');
          if (inp) inp.focus();
        }
      }
    }, [icon('plus'), el('span', { text: 'Thêm thành viên' })]);
  }

  const memberToolbar = el('div', {
    class: 'page-head members-header-toolbar',
    style: 'display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 16px; flex-wrap: wrap;'
  }, [
    el('div', {}, [
      headTitle,
      el('p', { class: 'help', text: 'Danh sách những người đang có quyền truy cập và cộng tác trong dự án này.' })
    ]),
    el('div', { style: 'display: flex; align-items: center; gap: 10px; flex-wrap: wrap;' }, [
      memberSearchWrap,
      addMemberBtn
    ].filter(Boolean))
  ]);

  // Accordion "Xem quyền của các vai trò" (Được đưa lên đầu tab Thành viên)
  const roleGuideAccordion = renderRolePermissionsAccordion();

  panel.replaceChildren(
    roleGuideAccordion,
    memberToolbar,
    ...(invitePanel ? [invitePanel] : []),
    tableWrap
  );
}

function renderRolePermissionsAccordion() {
  let isOpen = false;

  const toggleBtn = el('button', {
    type: 'button',
    class: 'role-guide-accordion-toggle',
    'aria-expanded': 'false',
    onclick: () => {
      isOpen = !isOpen;
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      toggleBtn.classList.toggle('is-open', isOpen);
      bodyWrap.hidden = !isOpen;
    }
  }, [
    el('div', { class: 'role-guide-toggle-left' }, [
      icon('key'),
      el('span', { class: 'role-guide-toggle-title', text: 'Xem quyền của các vai trò' }),
      el('span', { class: 'role-guide-toggle-subtitle', text: '— Hướng dẫn phân định trách nhiệm: Quản lý, Góp ý, Chỉ xem và Admin' })
    ]),
    el('span', { class: 'role-guide-toggle-icon' }, [icon('chevron')])
  ]);

  const bodyWrap = el('div', { class: 'role-guide-accordion-body', hidden: true }, [
    el('div', { class: 'role-guide-grid' }, [
      el('div', { class: 'role-guide-card' }, [
        el('div', { class: 'role-guide-card-top' }, [
          el('span', { class: 'status-pill-dot', style: 'background: #dc2626; width: 8px; height: 8px;' }),
          el('strong', { text: 'Quản lý (Manager)' })
        ]),
        el('p', { class: 'role-guide-card-desc', text: 'Toàn quyền điều hành dự án: tải ảnh thiết kế, tạo phiên bản mới, đổi tên, xóa màn hình, mời và phân quyền thành viên, quản lý và xử lý góp ý.' })
      ]),
      el('div', { class: 'role-guide-card' }, [
        el('div', { class: 'role-guide-card-top' }, [
          el('span', { class: 'status-pill-dot', style: 'background: #d97706; width: 8px; height: 8px;' }),
          el('strong', { text: 'Góp ý (Reviewer)' })
        ]),
        el('p', { class: 'role-guide-card-desc', text: 'Xem thiết kế, đặt điểm và khoanh vùng góp ý trực tiếp trên ảnh, viết nhận xét, trả lời trao đổi trong thread và đánh dấu giải quyết góp ý.' })
      ]),
      el('div', { class: 'role-guide-card' }, [
        el('div', { class: 'role-guide-card-top' }, [
          el('span', { class: 'status-pill-dot', style: 'background: #0284c7; width: 8px; height: 8px;' }),
          el('strong', { text: 'Chỉ xem (Viewer)' })
        ]),
        el('p', { class: 'role-guide-card-desc', text: 'Theo dõi dự án, xem ảnh thiết kế các phiên bản và đọc toàn bộ góp ý. Không có quyền sửa đổi hoặc tạo thêm góp ý mới.' })
      ]),
      el('div', { class: 'role-guide-card' }, [
        el('div', { class: 'role-guide-card-top' }, [
          el('span', { class: 'status-pill-dot', style: 'background: #6366f1; width: 8px; height: 8px;' }),
          el('strong', { text: 'Admin Hệ thống' })
        ]),
        el('p', { class: 'role-guide-card-desc', text: 'Quản lý tài khoản toàn hệ thống, giám sát vận hành và theo dõi thống kê. Không tự ý chỉnh sửa nội dung dự án nếu không được cấp quyền.' })
      ])
    ])
  ]);

  return el('div', { class: 'role-guide-accordion panel' }, [
    toggleBtn,
    bodyWrap
  ]);
}

function roleSelect(project, member, panel) {
  const select = el('select', { class: 'field-input', 'aria-label': `Vai trò của ${member.name}` }, [
    el('option', { value: 'viewer', text: 'Chỉ xem' }),
    el('option', { value: 'reviewer', text: 'Góp ý' }),
    el('option', { value: 'manager', text: 'Quản lý' })
  ]);
  const currentRole = (member.role === 'commenter' ? 'reviewer' : member.role);
  select.value = currentRole;
  select.addEventListener('change', async () => {
    const next = select.value;
    if (next === currentRole) return;
    const ok = await confirmDialog({
      title: 'Thay đổi vai trò thành viên',
      message: `Bạn có chắc muốn chuyển vai trò của "${member.name}" sang "${roleLabel(next)}"?`,
      confirmText: 'Xác nhận thay đổi',
      cancelText: 'Hủy'
    });
    if (!ok) {
      select.value = currentRole;
      return;
    }
    select.disabled = true;
    try {
      await api.updateMemberRole(project.id, member.id, next);
      toast(`Đã cập nhật vai trò của ${member.name} thành ${roleLabel(next)}.`, 'success');
      const fresh = await api.getProject(project.id);
      await renderMembers(panel, fresh);
      if (fresh.myRole !== 'manager') location.reload();
    } catch (err) {
      select.value = currentRole;
      select.disabled = false;
      toast(err.message || 'Không đổi được vai trò.', 'error');
    }
  });
  return select;
}

async function inviteForm(project, panel) {
  let selectedCandidate = null;
  let searchTimer = null;

  const searchInput = el('input', {
    type: 'text',
    class: 'field-input',
    id: 'invite-username-input',
    placeholder: 'Nhập tên đăng nhập hoặc email để tìm (ví dụ: an, binh@..., chi...)',
    autocomplete: 'off'
  });

  const suggestionsBox = el('div', {
    class: 'invite-suggestions',
    id: 'invite-suggestions',
    style: 'display: none;'
  });

  const selectedDisplay = el('div', {
    class: 'invite-selected-pill',
    id: 'invite-selected-pill',
    style: 'display: none;'
  });

  const roleSelect = el('select', { class: 'field-input', id: 'invite-role', style: 'min-width: 170px;' }, [
    el('option', { value: 'reviewer', text: 'Góp ý (Reviewer)', selected: 'true' }),
    el('option', { value: 'viewer', text: 'Chỉ xem (Viewer)' }),
    el('option', { value: 'manager', text: 'Quản lý (Manager)' })
  ]);

  const button = el('button', {
    type: 'submit',
    class: 'btn btn-primary',
    id: 'invite-submit-btn',
    style: 'white-space: nowrap;'
  }, [icon('plus'), el('span', { text: 'Thêm vào dự án' })]);

  function renderSelected(candidate) {
    selectedCandidate = candidate;
    if (!candidate) {
      selectedDisplay.style.display = 'none';
      selectedDisplay.replaceChildren();
      return;
    }
    selectedDisplay.style.display = 'inline-flex';
    selectedDisplay.replaceChildren(
      avatarEl(candidate, 22),
      el('span', { text: `${candidate.name} (@${candidate.username}${candidate.email ? ' · ' + candidate.email : ''})` }),
      el('button', {
        type: 'button',
        class: 'btn-clear',
        title: 'Bỏ chọn',
        onclick: (e) => {
          e.preventDefault();
          e.stopPropagation();
          renderSelected(null);
          searchInput.value = '';
          searchInput.focus();
        }
      }, [icon('close')])
    );
  }

  async function performSearch(q) {
    try {
      const candidates = await api.listInviteCandidates(project.id, q);
      if (!candidates || !candidates.length) {
        suggestionsBox.replaceChildren(
          el('div', {
            style: 'padding: 12px 14px; color: var(--muted); font-size: 14px; text-align: center;'
          }, [
            el('span', { text: q ? `Không tìm thấy tài khoản khả dụng nào khớp với "${q}".` : 'Không có tài khoản khả dụng nào để thêm.' })
          ])
        );
      } else {
        const items = candidates.map((c) => {
          const itemEl = el('div', {
            class: 'invite-candidate-item',
            tabindex: '0',
            role: 'button'
          }, [
            el('div', { class: 'invite-candidate-meta' }, [
              avatarEl(c, 28),
              el('div', { class: 'invite-candidate-info' }, [
                el('span', { class: 'invite-candidate-name', text: c.name }),
                el('span', { class: 'invite-candidate-username', text: `@${c.username}${c.email ? ' · ' + c.email : ''}${c.title ? ' · ' + c.title : ''}` })
              ])
            ]),
            el('span', { class: 'badge badge-neutral', style: 'font-size: 13px;', text: 'Chọn' })
          ]);

          const choose = () => {
            renderSelected(c);
            searchInput.value = c.username;
            suggestionsBox.style.display = 'none';
          };

          itemEl.addEventListener('click', choose);
          itemEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              choose();
            }
          });

          return itemEl;
        });

        suggestionsBox.replaceChildren(...items);
      }
      suggestionsBox.style.display = 'block';
    } catch (err) {
      suggestionsBox.style.display = 'none';
    }
  }

  searchInput.addEventListener('input', () => {
    const val = searchInput.value.trim();
    if (selectedCandidate && selectedCandidate.username.toLowerCase() !== val.toLowerCase()) {
      renderSelected(null);
    }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      performSearch(val);
    }, 200);
  });

  searchInput.addEventListener('focus', () => {
    performSearch(searchInput.value.trim());
  });

  document.addEventListener('click', (e) => {
    if (!form.contains(e.target)) {
      suggestionsBox.style.display = 'none';
    }
  });

  const searchWrap = el('div', { class: 'invite-search-wrap' }, [
    searchInput,
    suggestionsBox,
    selectedDisplay
  ]);

  const form = el('form', { class: 'panel panel-pad', style: 'margin-top: 0; margin-bottom: 24px;' }, [
    el('h3', { text: 'Thêm thành viên vào dự án' }),
    el('p', { class: 'help', text: 'Tìm kiếm theo tên đăng nhập (username) hoặc địa chỉ email của tài khoản để phân quyền và thêm vào dự án.' }),
    el('div', { class: 'toolbar', style: 'align-items: flex-start; gap: 12px; margin-top: 14px; flex-wrap: wrap;' }, [
      el('div', { class: 'field', style: 'flex: 1; min-width: 260px; margin-bottom: 0;' }, [
        el('label', { class: 'field-label', for: 'invite-username-input', text: 'Tên đăng nhập hoặc Email *' }),
        searchWrap
      ]),
      el('div', { class: 'field', style: 'width: auto; min-width: 180px; margin-bottom: 0;' }, [
        el('label', { class: 'field-label', for: 'invite-role', text: 'Vai trò trong dự án' }),
        roleSelect
      ]),
      el('div', { style: 'margin-top: 25px;' }, [button])
    ])
  ]);

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const queryVal = searchInput.value.trim();

    if (!selectedCandidate && !queryVal) {
      toast('Vui lòng nhập tên đăng nhập hoặc email của thành viên cần thêm.', 'error');
      searchInput.focus();
      return;
    }

    setBusy(button, true, 'Đang thêm...');
    try {
      const payload = {
        role: roleSelect.value,
        ...(selectedCandidate ? { userId: selectedCandidate.id, username: selectedCandidate.username, email: selectedCandidate.email } : { username: queryVal, email: queryVal })
      };
      await api.inviteMember(project.id, payload);
      toast(`Đã thêm thành viên ${selectedCandidate ? '@' + selectedCandidate.username : queryVal} vào dự án.`, 'success');
      renderSelected(null);
      searchInput.value = '';
      suggestionsBox.style.display = 'none';
      const fresh = await api.getProject(project.id);
      await renderMembers(panel, fresh);
    } catch (err) {
      setBusy(button, false);
      toast(err.message || 'Không thêm được thành viên.', 'error');
    }
  });

  return form;
}

/**
 * 5. LỊCH SỬ HOẠT ĐỘNG
 */
async function renderActivity(panel, project) {
  let activityPage = 1;
  let activityLimit = 10;

  const activityContainer = el('div', { class: 'panel panel-pad' });

  async function updateActivityView() {
    try {
      const res = await api.listActivities(project.id, { page: activityPage, limit: activityLimit });
      let pageItems = [];
      let totalItems = 0;

      if (res && res.pagination) {
        pageItems = res.activities || [];
        totalItems = res.pagination.total || 0;
        activityPage = res.pagination.page || activityPage;
      } else {
        const all = Array.isArray(res) ? res : [];
        totalItems = all.length;
        const totalPages = Math.ceil(totalItems / activityLimit) || 1;
        if (activityPage > totalPages) activityPage = totalPages;
        const start = (activityPage - 1) * activityLimit;
        pageItems = all.slice(start, start + activityLimit);
      }

      if (!totalItems) {
        activityContainer.replaceChildren(emptyState({ title: 'Chưa có hoạt động', text: 'Các thay đổi trong dự án sẽ xuất hiện tại đây.' }));
        return;
      }

      function getDayHeading(dateString) {
        if (!dateString) return 'Trước đây';
        const d = new Date(dateString);
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const itemDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const diffDays = Math.round((today - itemDate) / (1000 * 60 * 60 * 24));
        if (diffDays === 0) return 'Hôm nay';
        if (diffDays === 1) return 'Hôm qua';
        return `Ngày ${new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(d)}`;
      }

      const timelineList = el('div', { class: 'timeline' });
      let currentDay = '';
      pageItems.forEach((item) => {
        const day = getDayHeading(item.createdAt);
        if (day !== currentDay) {
          currentDay = day;
          timelineList.append(el('div', { class: 'timeline-date-heading' }, [
            icon('clock'),
            el('span', { text: day })
          ]));
        }
        timelineList.append(el('article', { class: 'timeline-item' }, [
          avatarEl(item.user, 32),
          el('div', {}, [
            el('p', {}, [
              el('strong', { text: item.user.name }),
              document.createTextNode(` ${item.message}`)
            ]),
            el('time', { datetime: item.createdAt, text: formatDate(item.createdAt) })
          ])
        ]));
      });

      const paginationBar = renderPagination({
        currentPage: activityPage,
        pageSize: activityLimit,
        totalItems: totalItems,
        pageSizeOptions: [10, 20, 50, 100],
        itemLabel: 'hoạt động',
        isStandalone: true,
        onPageChange: (newPage) => {
          activityPage = newPage;
          updateActivityView();
        },
        onPageSizeChange: (newSize) => {
          activityLimit = newSize;
          activityPage = 1;
          updateActivityView();
        }
      });

      activityContainer.replaceChildren(timelineList, ...(paginationBar ? [paginationBar] : []));
    } catch (err) {
      toast(err.message || 'Lỗi tải lịch sử hoạt động.', 'error');
    }
  }

  await updateActivityView();

  panel.replaceChildren(
    el('div', { class: 'page-head' }, [el('h2', { text: 'Lịch sử hoạt động' })]),
    activityContainer
  );
}
