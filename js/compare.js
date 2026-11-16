import { boot } from './app.js';
import { api } from './api.js';
import { toStyle } from './coords.js';
import { el, emptyState, icon, setBusy, statusBadge, toast } from './ui.js';

const params = new URLSearchParams(location.search);
let role = 'viewer';
let versions = [];
let screen = null;
let project = null;

// View Mode: 'slider' (default) or 'split'
let viewMode = 'slider';

// Version selections
let versionBefore = null;
let versionAfter = null;
let annotationsBefore = [];
let annotationsAfter = [];

// Slider State
let sliderPercent = 50;
let zoom = 1;
let fitMode = true;
let isDragging = false;

// Split View State
let splitStageBefore = null;
let splitStageAfter = null;
let splitPaneBeforeEl = null;
let splitPaneAfterEl = null;

// DOM References
let stageEl = null;
let viewportEl = null;
let layerBeforeEl = null;
let layerAfterEl = null;
let imgBeforeEl = null;
let imgAfterEl = null;
let overlayBeforeEl = null;
let overlayAfterEl = null;
let dividerEl = null;
let handleEl = null;
let zoomLabelEl = null;
let pctLabelEl = null;
let rangeInputEl = null;
let selectBeforeEl = null;
let selectAfterEl = null;
let sliderViewContainer = null;
let splitViewContainer = null;
let commentsContainer = null;

function canEdit() {
  return role === 'reviewer' || role === 'manager';
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function setSliderPercent(pct) {
  sliderPercent = clamp(pct, 0, 100);
  if (dividerEl) {
    dividerEl.style.left = `${sliderPercent}%`;
  }
  if (handleEl) {
    handleEl.setAttribute('aria-valuenow', String(Math.round(sliderPercent)));
  }
  if (layerBeforeEl) {
    layerBeforeEl.style.clipPath = `polygon(0 0, ${sliderPercent}% 0, ${sliderPercent}% 100%, 0 100%)`;
  }
  if (rangeInputEl) {
    rangeInputEl.value = Math.round(sliderPercent);
  }
  if (pctLabelEl) {
    pctLabelEl.textContent = `${Math.round(sliderPercent)}%`;
  }
}

function computeFit() {
  const pad = 48;
  if (viewMode === 'split') {
    const vPort1 = splitPaneBeforeEl?.querySelector('.canvas-viewport');
    const vPort2 = splitPaneAfterEl?.querySelector('.canvas-viewport');
    const baseW1 = versionBefore?.width || 800;
    const baseH1 = versionBefore?.height || 600;
    const baseW2 = versionAfter?.width || 800;
    const baseH2 = versionAfter?.height || 600;

    const vw1 = vPort1 ? Math.max(120, vPort1.clientWidth - pad) : 400;
    const vh1 = vPort1 ? Math.max(120, vPort1.clientHeight - pad) : 400;
    const vw2 = vPort2 ? Math.max(120, vPort2.clientWidth - pad) : 400;
    const vh2 = vPort2 ? Math.max(120, vPort2.clientHeight - pad) : 400;

    const fit1 = Math.min(vw1 / baseW1, vh1 / baseH1);
    const fit2 = Math.min(vw2 / baseW2, vh2 / baseH2);
    return clamp(Math.min(fit1, fit2), 0.05, 3);
  } else {
    if (!viewportEl || !versionBefore) return 1;
    const baseW = Math.max(versionBefore.width || 800, versionAfter?.width || 800);
    const baseH = Math.max(versionBefore.height || 600, versionAfter?.height || 600);
    const vw = Math.max(120, viewportEl.clientWidth - pad);
    const vh = Math.max(120, viewportEl.clientHeight - pad);
    return clamp(Math.min(vw / baseW, vh / baseH), 0.1, 4);
  }
}

function applyZoom() {
  if (!stageEl || !versionBefore) return;
  const baseW = Math.max(versionBefore.width || 800, versionAfter?.width || 800);
  const baseH = Math.max(versionBefore.height || 600, versionAfter?.height || 600);
  stageEl.style.width = `${Math.max(1, Math.round(baseW * zoom))}px`;
  stageEl.style.height = `${Math.max(1, Math.round(baseH * zoom))}px`;
  if (zoomLabelEl) {
    zoomLabelEl.textContent = `${Math.round(zoom * 100)}%`;
  }
}

function applySplitZoom() {
  if (!splitViewContainer) return;
  const baseW1 = versionBefore?.width || 800;
  const baseH1 = versionBefore?.height || 600;
  const baseW2 = versionAfter?.width || 800;
  const baseH2 = versionAfter?.height || 600;

  if (splitStageBefore) {
    splitStageBefore.style.width = `${Math.max(1, Math.round(baseW1 * zoom))}px`;
    splitStageBefore.style.height = `${Math.max(1, Math.round(baseH1 * zoom))}px`;
  }
  if (splitStageAfter) {
    splitStageAfter.style.width = `${Math.max(1, Math.round(baseW2 * zoom))}px`;
    splitStageAfter.style.height = `${Math.max(1, Math.round(baseH2 * zoom))}px`;
  }

  if (zoomLabelEl) {
    zoomLabelEl.textContent = `${Math.round(zoom * 100)}%`;
  }
}

function changeZoom(factor) {
  fitMode = false;
  zoom = clamp(zoom * factor, 0.05, 4);
  if (viewMode === 'split') {
    applySplitZoom();
  } else {
    applyZoom();
  }
}

function fitToScreen() {
  fitMode = true;
  zoom = computeFit();
  if (viewMode === 'split') {
    applySplitZoom();
  } else {
    applyZoom();
  }
}

function fitSplitPanes() {
  fitToScreen();
}

function renderMarkers(overlay, annotations, version) {
  overlay.replaceChildren();
  const baseW = version.width || 800;
  const baseH = version.height || 600;
  annotations.forEach((ann) => {
    const style = toStyle(ann, ann.originalWidth || baseW, ann.originalHeight || baseH);
    const marker = el('div', {
      class: `marker marker-${ann.type === 'rect' ? 'rect' : 'point'} status-${ann.status}`,
      'data-testid': 'annotation-marker',
      'aria-hidden': 'true',
      title: `#${ann.number}: ${ann.body}`
    });
    marker.style.left = style.left;
    marker.style.top = style.top;
    if (ann.type === 'rect') {
      marker.style.width = style.width;
      marker.style.height = style.height;
      marker.append(el('span', { class: `marker-label status-${ann.status}`, text: String(ann.number) }));
    } else {
      marker.append(el('span', { text: String(ann.number) }));
    }
    overlay.append(marker);
  });
}

function commentCard(ann, versionLabel, onStatusChanged) {
  const note = el('textarea', {
    class: 'field-input note-input',
    'aria-label': `Kiểm tra sau sửa cho góp ý ${ann.number}`,
    placeholder: 'Ghi chú kiểm tra sau sửa...'
  });
  note.value = ann.checkNote || '';
  note.disabled = !canEdit();
  let timer = 0;
  const saveNote = async () => {
    if (!canEdit() || note.value.trim() === (ann.checkNote || '')) return;
    try {
      const updated = await api.setCheckNote(ann.id, note.value);
      ann.checkNote = updated.checkNote;
      toast('Đã lưu ghi chú kiểm tra.', 'success');
    } catch (err) {
      toast(err.message || 'Không lưu được ghi chú.', 'error');
    }
  };
  note.addEventListener('input', () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(saveNote, 500);
  });
  note.addEventListener('blur', saveNote);

  const body = el('p', { class: 'thread-body', text: ann.body });
  const statusBtn = el('button', {
    type: 'button',
    class: 'btn btn-secondary btn-sm',
    text: ann.status === 'open' ? 'Đánh dấu đã xử lý' : 'Mở lại góp ý',
    disabled: !canEdit()
  });

  statusBtn.addEventListener('click', async () => {
    setBusy(statusBtn, true, 'Đang lưu...');
    try {
      const nextStatus = (ann.status === 'open') ? 'resolved' : 'open';
      await api.setAnnotationStatus(ann.id, nextStatus);
      ann.status = nextStatus;
      toast('Đã cập nhật trạng thái góp ý.', 'success');
      if (onStatusChanged) onStatusChanged();
    } catch (err) {
      setBusy(statusBtn, false);
      toast(err.message || 'Không đổi được trạng thái.', 'error');
    }
  });

  return el('article', { class: 'thread compare-thread-card', 'data-testid': 'comment-thread' }, [
    el('div', { class: 'thread-top' }, [
      el('div', { class: 'thread-author-wrap' }, [
        el('span', { class: 'compare-ann-badge' }, `#${ann.number}`),
        el('strong', { text: ann.author?.name || 'Ẩn danh' }),
        el('span', { class: 'tag tag-version', text: versionLabel })
      ]),
      statusBadge(ann.status)
    ]),
    body,
    el('p', { class: 'coord', text: `Tọa độ: x=${Math.round(ann.x)}, y=${Math.round(ann.y)}, w=${Math.round(ann.w)}, h=${Math.round(ann.h)}` }),
    el('label', { class: 'field-label' }, 'Kiểm tra sau sửa'),
    note,
    el('div', { class: 'thread-footer', style: 'margin-top: 8px;' }, [statusBtn])
  ]);
}

function renderCommentsPanel() {
  if (!commentsContainer) return;

  const leftCards = annotationsBefore.length
    ? annotationsBefore.map((a) => commentCard(a, versionBefore.label, reloadData))
    : [el('div', { class: 'compare-empty-side' }, [el('p', { text: `Phiên bản ${versionBefore?.label || 'trước'} chưa có góp ý nào.` })])];

  const rightCards = annotationsAfter.length
    ? annotationsAfter.map((a) => commentCard(a, versionAfter.label, reloadData))
    : [el('div', { class: 'compare-empty-side' }, [el('p', { text: `Phiên bản ${versionAfter?.label || 'sau'} chưa có góp ý nào.` })])];

  const colBefore = el('div', { class: 'compare-comment-col' }, [
    el('div', { class: 'compare-col-header' }, [
      el('span', { class: 'badge-pill badge-neutral' }, `${versionBefore?.label || 'v1'}`),
      el('strong', { text: `Góp ý phiên bản trước (${annotationsBefore.length})` })
    ]),
    el('div', { class: 'compare-col-list' }, leftCards)
  ]);

  const colAfter = el('div', { class: 'compare-comment-col' }, [
    el('div', { class: 'compare-col-header' }, [
      el('span', { class: 'badge-pill badge-neutral' }, `${versionAfter?.label || 'v2'}`),
      el('strong', { text: `Góp ý phiên bản sau (${annotationsAfter.length})` })
    ]),
    el('div', { class: 'compare-col-list' }, rightCards)
  ]);

  commentsContainer.replaceChildren(colBefore, colAfter);
}

async function loadVersionImage(imgNode, ver) {
  const src = await api.resolveImageUrl(ver.imageKey);
  return new Promise((resolve, reject) => {
    imgNode.alt = `${screen.name} ${ver.label}`;
    imgNode.dataset.originalWidth = String(ver.width);
    imgNode.dataset.originalHeight = String(ver.height);
    const onOk = () => resolve(src);
    const onErr = () => reject(new Error(`Không tải được ảnh ${ver.label}.`));
    imgNode.onload = onOk;
    imgNode.onerror = onErr;
    if (imgNode.getAttribute('src') === src && imgNode.complete && imgNode.naturalWidth) {
      resolve(src);
      return;
    }
    imgNode.src = src;
    if (imgNode.complete && imgNode.naturalWidth) {
      resolve(src);
    }
  });
}

async function reloadData() {
  if (!versionBefore || !versionAfter) return;

  // 1. Load images in parallel
  await Promise.all([
    loadVersionImage(imgBeforeEl, versionBefore),
    loadVersionImage(imgAfterEl, versionAfter)
  ]);

  // 2. Load annotations in parallel
  const [annsBefore, annsAfter] = await Promise.all([
    api.listAnnotations(versionBefore.id),
    api.listAnnotations(versionAfter.id)
  ]);

  annotationsBefore = annsBefore || [];
  annotationsAfter = annsAfter || [];

  // 3. Render markers
  renderMarkers(overlayBeforeEl, annotationsBefore, versionBefore);
  renderMarkers(overlayAfterEl, annotationsAfter, versionAfter);

  // 4. Update badge labels
  const badgeBefore = stageEl.querySelector('.slider-badge-before');
  const badgeAfter = stageEl.querySelector('.slider-badge-after');
  if (badgeBefore) badgeBefore.textContent = `${versionBefore.label} (Trước)`;
  if (badgeAfter) badgeAfter.textContent = `${versionAfter.label} (Sau)`;

  // 5. Update slider percent & zoom
  setSliderPercent(sliderPercent);
  if (viewMode === 'split') {
    renderSplitMode();
  } else if (fitMode) {
    fitToScreen();
  } else {
    applyZoom();
  }

  // 6. Render comments panel
  renderCommentsPanel();
}

boot({
  active: 'projects',
  title: 'So sánh phiên bản',
  async render({ setBreadcrumb, setPageTitle }) {
    const projectId = params.get('project');
    const screenId = params.get('screen');
    if (!projectId || !screenId) throw new Error('Thiếu thông tin màn hình cần so sánh.');

    project = await api.getProject(projectId);
    screen = await api.getScreen(screenId);
    role = project.myRole || 'viewer';
    versions = await api.listVersions(screenId);

    if (versions.length < 1) throw new Error('Chưa có phiên bản ảnh để so sánh.');

    setPageTitle(`So sánh — ${screen.name}`);
    setBreadcrumb([
      { label: 'DesignMark', href: 'projects.php' },
      { label: project.name, href: `project.php?id=${encodeURIComponent(project.id)}` },
      { label: screen.name, href: `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}` },
      { label: 'So sánh phiên bản' }
    ]);

    // Choose default versions: before = first version or param; after = latest version or param
    const reqLeft = params.get('left');
    const reqRight = params.get('right');
    versionBefore = versions.find((v) => v.id === reqLeft) || versions[0];
    versionAfter = versions.find((v) => v.id === reqRight) || versions[versions.length - 1];

    if (versionBefore.id === versionAfter.id && versions.length > 1) {
      versionBefore = versions[0];
      versionAfter = versions[versions.length - 1];
    }

    const page = document.getElementById('page');

    // 1. Toolbar Elements
    selectBeforeEl = el('select', { class: 'field-input compare-select', 'aria-label': 'Phiên bản trước' });
    selectAfterEl = el('select', { class: 'field-input compare-select', 'aria-label': 'Phiên bản sau' });

    versions.forEach((v) => {
      const optText = (v.label && v.label.toLowerCase() === `v${v.number}`) ? v.label : `${v.label} (v${v.number})`;
      selectBeforeEl.append(el('option', { value: v.id, text: optText }));
      selectAfterEl.append(el('option', { value: v.id, text: optText }));
    });

    selectBeforeEl.value = versionBefore.id;
    selectAfterEl.value = versionAfter.id;

    selectBeforeEl.addEventListener('change', () => {
      versionBefore = versions.find((v) => v.id === selectBeforeEl.value);
      reloadData().catch((err) => toast(err.message, 'error'));
    });

    selectAfterEl.addEventListener('change', () => {
      versionAfter = versions.find((v) => v.id === selectAfterEl.value);
      reloadData().catch((err) => toast(err.message, 'error'));
    });

    // Mode Toggle Buttons
    const btnModeSlider = el('button', {
      type: 'button',
      class: 'mode-toggle-btn is-active',
      onclick: () => switchViewMode('slider')
    }, [icon('compare'), 'Thanh trượt (Slider)']);

    const btnModeSplit = el('button', {
      type: 'button',
      class: 'mode-toggle-btn',
      onclick: () => switchViewMode('split')
    }, [icon('panels'), 'Song song (2 bên)']);

    const modeToggle = el('div', { class: 'mode-toggle-group' }, [btnModeSlider, btnModeSplit]);

    // Zoom controls
    zoomLabelEl = el('strong', { class: 'zoom-label', text: '100%' });
    const zoomOutBtn = el('button', {
      type: 'button',
      class: 'btn btn-secondary btn-sm',
      title: 'Thu nhỏ',
      onclick: () => changeZoom(1 / 1.25)
    }, [icon('zoomOut')]);

    const zoomInBtn = el('button', {
      type: 'button',
      class: 'btn btn-secondary btn-sm',
      title: 'Phóng to',
      onclick: () => changeZoom(1.25)
    }, [icon('zoomIn')]);

    const fitBtn = el('button', {
      type: 'button',
      class: 'btn btn-secondary btn-sm',
      title: 'Vừa màn hình',
      onclick: () => fitToScreen()
    }, [icon('fit'), 'Vừa khung']);

    // Slider range input and percentage buttons
    pctLabelEl = el('span', { class: 'slider-pct-label', text: '50%' });
    rangeInputEl = el('input', {
      type: 'range',
      min: '0',
      max: '100',
      value: '50',
      class: 'slider-range-input',
      'aria-label': 'Vị trí thanh trượt',
      oninput: () => {
        setSliderPercent(Number(rangeInputEl.value));
      }
    });

    const btnPct25 = el('button', {
      type: 'button',
      class: 'quick-pct-btn',
      text: '25%',
      onclick: () => setSliderPercent(25)
    });
    const btnPct50 = el('button', {
      type: 'button',
      class: 'quick-pct-btn',
      text: '50%',
      onclick: () => setSliderPercent(50)
    });
    const btnPct75 = el('button', {
      type: 'button',
      class: 'quick-pct-btn',
      text: '75%',
      onclick: () => setSliderPercent(75)
    });

    const rowTop = el('div', { class: 'compare-toolbar-row compare-toolbar-row-top' }, [
      el('div', { class: 'toolbar-group-versions' }, [
        el('label', { class: 'field-label-inline' }, 'Trước:'),
        selectBeforeEl,
        el('span', { class: 'compare-arrow-icon' }, '→'),
        el('label', { class: 'field-label-inline' }, 'Sau:'),
        selectAfterEl
      ]),
      el('div', { class: 'toolbar-group-zoom' }, [
        zoomOutBtn,
        zoomLabelEl,
        zoomInBtn,
        fitBtn
      ])
    ]);

    const rowBottom = el('div', { class: 'compare-toolbar-row compare-toolbar-row-bottom' }, [
      modeToggle,
      el('div', { class: 'toolbar-group-slider' }, [
        el('label', { class: 'field-label-inline' }, 'Vị trí trượt:'),
        rangeInputEl,
        pctLabelEl,
        el('div', { class: 'quick-pct-group' }, [btnPct25, btnPct50, btnPct75])
      ])
    ]);

    const toolbar = el('div', { class: 'compare-toolbar' }, [
      rowTop,
      rowBottom
    ]);

    // 2. Build Interactive Slider Stage
    imgBeforeEl = el('img', { class: 'slider-img' });
    overlayBeforeEl = el('div', { class: 'overlay' });
    layerBeforeEl = el('div', { class: 'slider-layer slider-layer-before' }, [
      imgBeforeEl,
      overlayBeforeEl,
      el('span', { class: 'slider-badge slider-badge-before', text: `${versionBefore.label} (Trước)` })
    ]);

    imgAfterEl = el('img', { class: 'slider-img' });
    overlayAfterEl = el('div', { class: 'overlay' });
    layerAfterEl = el('div', { class: 'slider-layer slider-layer-after' }, [
      imgAfterEl,
      overlayAfterEl,
      el('span', { class: 'slider-badge slider-badge-after', text: `${versionAfter.label} (Sau)` })
    ]);

    handleEl = el('div', {
      class: 'slider-handle',
      tabindex: '0',
      role: 'slider',
      'aria-label': 'Thanh trượt so sánh',
      'aria-valuenow': '50',
      'aria-valuemin': '0',
      'aria-valuemax': '100',
      title: 'Kéo sang trái hoặc phải để so sánh (phím mũi tên ← →)'
    }, [
      el('span', { class: 'slider-handle-icon', text: '◀ ▶' })
    ]);

    dividerEl = el('div', { class: 'slider-divider', id: 'slider-divider' }, [handleEl]);

    stageEl = el('div', { class: 'compare-slider-stage', id: 'compare-slider-stage' }, [
      layerAfterEl,
      layerBeforeEl,
      dividerEl
    ]);

    viewportEl = el('div', { class: 'compare-slider-viewport canvas-viewport' }, [
      el('div', { class: 'canvas-pad' }, [stageEl])
    ]);

    sliderViewContainer = el('div', { class: 'compare-slider-view-wrapper' }, [viewportEl]);

    // Slider Drag Event Handlers
    function handleDrag(clientX) {
      if (!stageEl) return;
      const rect = stageEl.getBoundingClientRect();
      if (rect.width <= 0) return;
      const offsetX = clientX - rect.left;
      const pct = (offsetX / rect.width) * 100;
      setSliderPercent(pct);
    }

    dividerEl.addEventListener('mousedown', (e) => {
      e.preventDefault();
      isDragging = true;
      document.body.classList.add('is-sliding-active');
    });

    window.addEventListener('mousemove', (e) => {
      if (isDragging) {
        handleDrag(e.clientX);
      }
    });

    window.addEventListener('mouseup', () => {
      if (isDragging) {
        isDragging = false;
        document.body.classList.remove('is-sliding-active');
      }
    });

    dividerEl.addEventListener('touchstart', () => {
      isDragging = true;
    }, { passive: true });

    window.addEventListener('touchmove', (e) => {
      if (isDragging && e.touches[0]) {
        handleDrag(e.touches[0].clientX);
      }
    }, { passive: true });

    window.addEventListener('touchend', () => {
      isDragging = false;
    });

    // Click on canvas stage directly jumps slider to position
    stageEl.addEventListener('click', (e) => {
      if (e.target.closest('.marker') || e.target.closest('.slider-handle') || e.target.closest('button')) return;
      handleDrag(e.clientX);
    });

    // Keyboard controls on handle
    handleEl.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
        e.preventDefault();
        setSliderPercent(sliderPercent - (e.shiftKey ? 10 : 2));
      } else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
        e.preventDefault();
        setSliderPercent(sliderPercent + (e.shiftKey ? 10 : 2));
      } else if (e.key === 'Home') {
        e.preventDefault();
        setSliderPercent(0);
      } else if (e.key === 'End') {
        e.preventDefault();
        setSliderPercent(100);
      }
    });

    // 3. Side-by-Side (Split) View Container
    splitViewContainer = el('div', { class: 'compare-view compare-split-view', hidden: true });

    function switchViewMode(mode) {
      viewMode = mode;
      btnModeSlider.classList.toggle('is-active', mode === 'slider');
      btnModeSplit.classList.toggle('is-active', mode === 'split');

      if (mode === 'slider') {
        sliderViewContainer.hidden = false;
        splitViewContainer.hidden = true;
        document.querySelector('.toolbar-group-slider')?.removeAttribute('hidden');
        fitToScreen();
      } else {
        sliderViewContainer.hidden = true;
        splitViewContainer.hidden = false;
        document.querySelector('.toolbar-group-slider')?.setAttribute('hidden', 'true');
        renderSplitMode();
      }
    }

    function renderSplitMode() {
      // Re-create two panes for side-by-side mode
      const paneBefore = createSidePane('left', versionBefore, annotationsBefore);
      const paneAfter = createSidePane('right', versionAfter, annotationsAfter);
      splitViewContainer.replaceChildren(paneBefore, paneAfter);
      requestAnimationFrame(() => {
        fitToScreen();
      });
    }

    function createSidePane(side, version, anns) {
      const isLeft = side === 'left';
      const baseW = version.width || 800;
      const baseH = version.height || 600;
      const vImg = el('img', {
        alt: `${screen.name} ${version.label}`,
        src: (isLeft ? imgBeforeEl.src : imgAfterEl.src)
      });
      vImg.style.width = '100%';
      vImg.style.height = '100%';
      vImg.style.display = 'block';
      vImg.style.objectFit = 'fill';
      vImg.dataset.originalWidth = String(baseW);
      vImg.dataset.originalHeight = String(baseH);

      const vOverlay = el('div', { class: 'overlay' });
      renderMarkers(vOverlay, anns, version);

      const vStage = el('div', { class: 'image-stage' }, [vImg, vOverlay]);
      if (isLeft) splitStageBefore = vStage;
      else splitStageAfter = vStage;

      vStage.style.width = `${Math.max(1, Math.round(baseW * zoom))}px`;
      vStage.style.height = `${Math.max(1, Math.round(baseH * zoom))}px`;

      let isSyncingScroll = false;
      const vPort = el('div', { class: 'canvas-viewport' }, [
        el('div', { class: 'canvas-pad' }, [vStage])
      ]);

      // Synchronize scroll between left and right pane
      vPort.addEventListener('scroll', () => {
        if (isSyncingScroll) return;
        const otherPort = isLeft ? splitPaneAfterEl?.querySelector('.canvas-viewport') : splitPaneBeforeEl?.querySelector('.canvas-viewport');
        if (!otherPort) return;
        isSyncingScroll = true;
        otherPort.scrollLeft = vPort.scrollLeft;
        otherPort.scrollTop = vPort.scrollTop;
        requestAnimationFrame(() => {
          isSyncingScroll = false;
        });
      });

      vPort.addEventListener('wheel', (e) => {
        if (e.ctrlKey || e.metaKey) {
          e.preventDefault();
          const factor = e.deltaY < 0 ? 1.15 : 1 / 1.15;
          changeZoom(factor);
        }
      }, { passive: false });

      const header = el('div', { class: 'pane-title-badge' }, [
        el('strong', { text: `${version.label} (${anns.length} góp ý)` })
      ]);

      const pane = el('div', {
        class: 'compare-pane',
        tabindex: '0',
        'aria-label': `Khung so sánh ${version.label}`
      }, [
        header,
        vPort
      ]);

      if (isLeft) splitPaneBeforeEl = pane;
      else splitPaneAfterEl = pane;

      return pane;
    }

    // 4. Comments Layout
    commentsContainer = el('div', { class: 'compare-comments-layout' });

    // 5. Mount page
    const pageHeader = el('div', { class: 'page-head' }, [
      el('div', {}, [
        el('h2', { class: 'feedback-title', text: `So sánh các phiên bản — ${screen.name}` }),
        el('p', { class: 'lede', text: 'Kéo thanh trượt trái/phải để đối chiếu trực tiếp sự thay đổi giữa hai phiên bản thiết kế.' }),
        canEdit() ? null : el('p', { class: 'permission-note', text: 'Bạn đang xem ở chế độ chỉ đọc.' })
      ])
    ]);

    page.replaceChildren(
      pageHeader,
      toolbar,
      sliderViewContainer,
      splitViewContainer,
      commentsContainer
    );

    // Initial load
    await reloadData();

    // Auto-fit on window resize
    const onWindowResize = () => {
      if (fitMode) {
        fitToScreen();
      }
    };
    window.addEventListener('resize', onWindowResize);
    if (typeof ResizeObserver !== 'undefined') {
      if (viewportEl) {
        const ro1 = new ResizeObserver(onWindowResize);
        ro1.observe(viewportEl);
      }
      if (splitViewContainer) {
        const ro2 = new ResizeObserver(() => {
          if (viewMode === 'split' && fitMode) {
            fitSplitPanes();
          }
        });
        ro2.observe(splitViewContainer);
      }
    }
  }
});
