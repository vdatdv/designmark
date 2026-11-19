import { boot } from './app.js';
import { api } from './api.js';
import { toOriginal, toStyle } from './coords.js';
import {
  avatarEl,
  el,
  emptyState,
  formatDate,
  icon,
  openModal,
  pushEscape,
  setBusy,
  statusBadge,
  toast,
  triggerNotificationRefresh
} from './ui.js';

const params = new URLSearchParams(location.search);
const projectId = params.get('project') || params.get('project_id');
const screenId = params.get('screen') || params.get('screen_id');

const ui = {};
const filters = { status: 'all', author: 'all', q: '', sort: 'newest', page: 1, limit: 10 };
let project = null;
let screen = null;
let versions = [];
let version = null;
let annotations = [];
let allMarkers = [];
let pagination = { page: 1, limit: 10, total: 0, totalPages: 1, start: 0, end: 0 };
let role = 'viewer';
let tool = 'select';
let zoom = 1;
let fitMode = true;
let selectedId = null;
let composer = null;
let drag = null;
let editing = null;
let simulateConflict = false;
let removeComposerEscape = null;
let keyboard = { x: 500, y: 250, anchor: null };
let imageReady = false;
const replyDrafts = new Map();

function getDraftStorageKey(annId) {
  const u = currentSession?.username || 'anonymous';
  const v = version?.id || 'default';
  return `dm_reply_draft:${u}:${projectId}:${screenId}:${v}:${annId}`;
}

function getDraft(annId) {
  if (replyDrafts.has(annId)) return replyDrafts.get(annId);
  try {
    const saved = localStorage.getItem(getDraftStorageKey(annId));
    if (saved) {
      replyDrafts.set(annId, saved);
      return saved;
    }
  } catch (e) {}
  return '';
}

function setDraft(annId, text) {
  if (text && text.trim()) {
    replyDrafts.set(annId, text);
    try {
      localStorage.setItem(getDraftStorageKey(annId), text);
    } catch (e) {}
  } else {
    removeDraft(annId);
  }
}

function removeDraft(annId) {
  replyDrafts.delete(annId);
  try {
    localStorage.removeItem(getDraftStorageKey(annId));
  } catch (e) {}
}
let currentSession = null;
const expandedBodySet = new Set();
let markersHidden = false;

function toggleMarkersVisibility() {
  markersHidden = !markersHidden;
  if (ui.overlay) {
    ui.overlay.classList.toggle('markers-hidden', markersHidden);
  }
  if (ui['toggle-markers-label']) {
    ui['toggle-markers-label'].textContent = markersHidden ? 'Hiện điểm (H)' : 'Ẩn điểm (H)';
  }
  if (ui['toggle-markers-btn']) {
    ui['toggle-markers-btn'].classList.toggle('is-active', markersHidden);
    ui['toggle-markers-btn'].title = markersHidden
      ? 'Đang ẩn điểm đánh dấu (Phím tắt: H để hiện lại)'
      : 'Ẩn/Hiện điểm đánh dấu (Phím tắt: H)';
  }
  toast(markersHidden ? 'Đã ẩn các điểm đánh dấu trên ảnh.' : 'Đã hiện các điểm đánh dấu trên ảnh.', 'info');
}
const expandedRepliesSet = new Set();
const openReplyBoxSet = new Set();
const fullRepliesMap = new Map();

function canDraw() {
  return role === 'reviewer' || role === 'commenter' || role === 'manager' || role === 'owner';
}

function coordTarget() {
  const img = ui.img;
  return {
    getBoundingClientRect: () => img.getBoundingClientRect(),
    naturalWidth: version?.width || img.naturalWidth,
    naturalHeight: version?.height || img.naturalHeight,
    dataset: img.dataset
  };
}

function waitForImage(img, url) {
  return new Promise((resolve, reject) => {
    const fail = () => reject(new Error('Không tải được ảnh của phiên bản này.'));
    const succeed = () => resolve();
    img.onload = succeed;
    img.onerror = fail;
    if (img.getAttribute('src') === url && img.complete) {
      if (img.naturalWidth) succeed();
      else fail();
      return;
    }
    img.src = url;
    if (img.complete && img.naturalWidth) succeed();
  });
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function fmt(value) {
  return String(Math.round(Number(value) * 10) / 10);
}

function matches(ann) {
  if (filters.status !== 'all' && ann.status !== filters.status) return false;
  if (filters.author !== 'all' && ann.authorId !== filters.author) return false;
  const q = (filters.q || '').trim().toLowerCase();
  if (!q) return true;
  const qClean = q.replace(/^#/, '');
  if (qClean && String(ann.number) === qClean) return true;
  const authorName = ann.author?.name || '';
  const authorUname = ann.author?.username || '';
  return `${ann.body || ''} ${authorName} ${authorUname}`.toLowerCase().includes(q);
}

function matchesMarker(m) {
  if (filters.status !== 'all' && m.status !== filters.status) return false;
  if (filters.author !== 'all' && m.authorId !== filters.author) return false;
  const q = (filters.q || '').trim().toLowerCase();
  if (!q) return true;
  const qClean = q.replace(/^#/, '');
  if (qClean && String(m.number) === qClean) return true;
  return (m.body || m.bodySnippet || '').toLowerCase().includes(q);
}

function cacheUi() {
  [
    'version-select', 'version-banner', 'permission-note', 'zoom-label', 'screen-name',
    'compare-link', 'back-link', 'canvas-viewport', 'image-stage', 'review-image', 'image-overlay',
    'comment-panel', 'tool-select', 'tool-point', 'tool-rectangle', 'tool-arrow', 'tool-freehand',
    'zoom-in', 'zoom-out', 'zoom-fit', 'coord-btn', 'tool-hint', 'migrate-btn', 'export-annotated-btn',
    'toggle-markers-btn', 'toggle-markers-label'
  ].forEach((id) => {
    ui[id] = document.getElementById(id);
  });
  ui.viewport = ui['canvas-viewport'];
  ui.stage = ui['image-stage'];
  ui.img = ui['review-image'];
  ui.overlay = ui['image-overlay'];
  ui.panel = ui['comment-panel'];
}

async function normalizeImage(url) {
  imageReady = false;
  ui.overlay.style.pointerEvents = 'none';
  ui.img.dataset.originalWidth = String(version.width);
  ui.img.dataset.originalHeight = String(version.height);
  ui.img.alt = `Ảnh thiết kế ${screen.name}, phiên bản v${version.number}`;
  await waitForImage(ui.img, url);
  const isSvg = url.includes('.svg') || url.includes('seed:') || (version.mimeType && version.mimeType.includes('svg'));
  if (!isSvg && ui.img.naturalWidth && ui.img.naturalHeight && (ui.img.naturalWidth !== version.width || ui.img.naturalHeight !== version.height)) {
    try {
      const canvas = document.createElement('canvas');
      canvas.width = version.width;
      canvas.height = version.height;
      canvas.getContext('2d').drawImage(ui.img, 0, 0, version.width, version.height);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
      if (blob) {
        const normalized = URL.createObjectURL(blob);
        await new Promise((resolve) => {
          ui.img.onload = () => resolve();
          ui.img.onerror = () => resolve();
          ui.img.src = normalized;
        });
      }
    } catch (err) {
      console.warn('Image normalization skipped:', err);
    }
  }
  if (!ui.img.naturalWidth) {
    toast('Trình duyệt không trả về kích thước gốc. Tọa độ sẽ dùng kích thước đã lưu của phiên bản.', 'error');
  }
  imageReady = true;
  ui.overlay.style.pointerEvents = '';
}

let lastZoomClustered = false;

function centerOnCoords(origX, origY) {
  if (!ui.viewport || !version) return;
  const scaledX = (origX / version.width) * (version.width * zoom);
  const scaledY = (origY / version.height) * (version.height * zoom);
  const scrollLeft = scaledX + 32 - ui.viewport.clientWidth / 2;
  const scrollTop = scaledY + 32 - ui.viewport.clientHeight / 2;
  ui.viewport.scrollTo({
    left: Math.max(0, scrollLeft),
    top: Math.max(0, scrollTop),
    behavior: 'smooth'
  });
}

function applyZoom() {
  if (!version) return;
  ui.stage.style.width = `${Math.max(1, Math.round(version.width * zoom))}px`;
  ui.stage.style.height = `${Math.max(1, Math.round(version.height * zoom))}px`;
  ui['zoom-label'].textContent = `${Math.round(zoom * 100)}%`;
  positionComposer();

  const isClustered = zoom < 0.65;
  if (isClustered || lastZoomClustered) {
    lastZoomClustered = isClustered;
    renderMarkers();
  }
}

function computeFit() {
  const pad = 48;
  const cw = Math.max(160, ui.viewport.clientWidth - pad);
  const ch = Math.max(160, ui.viewport.clientHeight - pad);
  return clamp(Math.min(cw / version.width, ch / version.height), 0.1, 4);
}

function applyFit() {
  fitMode = true;
  zoom = computeFit();
  applyZoom();
}

function zoomBy(factor) {
  fitMode = false;
  zoom = clamp(zoom * factor, 0.1, 4);
  applyZoom();
}

function updateBanner() {
  const latest = versions[versions.length - 1];
  const old = version.id !== latest?.id;
  ui['version-banner'].hidden = !old;
  ui['version-banner'].textContent = old
    ? `Bạn đang xem v${version.number}. Góp ý ở đây chỉ thuộc v${version.number}.`
    : '';
  ui['compare-link'].href = `compare.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}&left=${encodeURIComponent(versions[0]?.id || '')}&right=${encodeURIComponent(latest?.id || '')}`;
}

function setTool(next) {
  if (['point', 'rect', 'arrow', 'freehand'].includes(next) && !canDraw()) return;
  tool = next;
  keyboard.anchor = null;
  ui.overlay.classList.toggle('is-point', tool === 'point');
  ui.overlay.classList.toggle('is-rect', tool === 'rect');
  ui.overlay.classList.toggle('is-arrow', tool === 'arrow');
  ui.overlay.classList.toggle('is-freehand', tool === 'freehand');

  ['select', 'point', 'rectangle', 'arrow', 'freehand'].forEach((name) => {
    const btnId = name === 'rectangle' ? 'tool-rectangle' : `tool-${name}`;
    const button = document.getElementById(btnId);
    if (!button) return;
    const active = (name === 'rectangle' ? 'rect' : name) === tool;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
  const hints = {
    select: 'Công cụ chọn. Nhấp marker hoặc thread để xem góp ý.',
    point: 'Công cụ đặt điểm. Nhấp lên ảnh, hoặc dùng mũi tên và Enter khi vùng ảnh được chọn.',
    rect: 'Công cụ khoanh vùng. Kéo trên ảnh để tạo khung chữ nhật chú thích.',
    arrow: 'Công cụ mũi tên. Kéo từ điểm bắt đầu đến điểm kết thúc để vẽ mũi tên chỉ dẫn.',
    freehand: 'Công cụ vẽ tự do. Kéo chuột/ngón tay để vẽ nét viền tự do khoanh vùng chi tiết.'
  };
  ui['tool-hint'].textContent = hints[tool] || '';
  showCrosshair(document.activeElement === ui.stage);
}

function clearTemp() {
  ui.overlay.querySelectorAll('.temp-rect, .temp-point, .temp-svg').forEach((node) => node.remove());
}

function showTemp(draft) {
  clearTemp();
  if (draft.type === 'rect') {
    const style = toStyle(draft, version.width, version.height);
    const node = el('div', { class: 'temp-rect' });
    node.style.left = style.left;
    node.style.top = style.top;
    node.style.width = style.width;
    node.style.height = style.height;
    ui.overlay.append(node);
    return;
  }
  if (draft.type === 'arrow') {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'temp-svg');
    svg.setAttribute('viewBox', `0 0 ${version.width} ${version.height}`);
    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    defs.innerHTML = `<marker id="temp-arrowhead" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1 L 10 5 L 0 9 z" fill="#4F46E5" /></marker>`;
    svg.appendChild(defs);
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', draft.x1);
    line.setAttribute('y1', draft.y1);
    line.setAttribute('x2', draft.x2);
    line.setAttribute('y2', draft.y2);
    line.setAttribute('stroke', '#4F46E5');
    line.setAttribute('stroke-width', '4');
    line.setAttribute('stroke-dasharray', '6 4');
    line.setAttribute('marker-end', 'url(#temp-arrowhead)');
    svg.appendChild(line);
    ui.overlay.append(svg);
    return;
  }
  if (draft.type === 'freehand') {
    const pts = draft.points || [];
    if (pts.length < 2) return;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'temp-svg');
    svg.setAttribute('viewBox', `0 0 ${version.width} ${version.height}`);
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    const d = `M ${pts.map(p => `${p.x} ${p.y}`).join(' L ')}`;
    path.setAttribute('d', d);
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', '#EF4444');
    path.setAttribute('stroke-width', '4');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(path);
    ui.overlay.append(svg);
    return;
  }
  const style = toStyle(draft, version.width, version.height);
  const node = el('div', { class: 'marker marker-point status-open temp-point', 'aria-hidden': 'true', text: '+' });
  node.style.left = style.left;
  node.style.top = style.top;
  ui.overlay.append(node);
}

function showCrosshair(visible) {
  let node = ui.overlay.querySelector('.crosshair');
  if (!visible || !canDraw() || tool === 'select') {
    node?.remove();
    return;
  }
  if (!node) {
    node = el('div', { class: 'crosshair', 'aria-hidden': 'true' });
    ui.overlay.append(node);
  }
  const style = toStyle({ x: keyboard.x, y: keyboard.y, w: 0, h: 0 }, version.width, version.height);
  node.style.left = style.left;
  node.style.top = style.top;
}

function clientFromOriginal(x, y) {
  const rect = ui.img.getBoundingClientRect();
  const nw = ui.img.naturalWidth || version.width;
  const nh = ui.img.naturalHeight || version.height;
  return {
    clientX: rect.left + (x / nw) * rect.width,
    clientY: rect.top + (y / nh) * rect.height
  };
}

function positionComposer() {
  if (!composer?.pop) return;
  const point = clientFromOriginal(composer.x, composer.y);
  const pop = composer.pop;
  const margin = 8;
  const width = pop.offsetWidth || 320;
  const height = pop.offsetHeight || 200;
  let left = point.clientX + 12;
  let top = point.clientY + 12;
  if (left + width > window.innerWidth - margin) left = window.innerWidth - width - margin;
  if (top + height > window.innerHeight - margin) top = Math.max(margin, point.clientY - height - 12);
  pop.style.left = `${Math.max(margin, left)}px`;
  pop.style.top = `${Math.max(margin, top)}px`;
}

function closeComposer() {
  composer?.pop?.remove();
  composer = null;
  clearTemp();
  if (removeComposerEscape) {
    removeComposerEscape();
    removeComposerEscape = null;
  }
}

function openComposer(draft) {
  if (!canDraw()) {
    toast('Bạn chỉ có quyền xem.', 'error');
    return;
  }
  if (!imageReady) return;
  closeComposer();
  composer = { ...draft };
  showTemp(draft);
  const textarea = el('textarea', { class: 'field-input', id: 'comment-body', maxlength: '2000', placeholder: 'Viết góp ý, dùng @username để nhắc tên...' });
  const error = el('p', { class: 'field-error', id: 'comment-error' });
  const send = el('button', { type: 'button', class: 'btn btn-primary btn-sm', 'data-testid': 'send-comment', text: 'Gửi' });
  const cancel = el('button', { type: 'button', class: 'btn btn-secondary btn-sm', 'data-testid': 'cancel-comment', text: 'Hủy' });

  let labelText = 'Nhận xét cho điểm đánh dấu';
  let coordText = `X: ${fmt(draft.x)} px, Y: ${fmt(draft.y)} px`;
  if (draft.type === 'rect') {
    labelText = 'Nhận xét cho vùng khoanh';
    coordText = `X: ${fmt(draft.x)} px, Y: ${fmt(draft.y)} px, W: ${fmt(draft.w)} px, H: ${fmt(draft.h)} px`;
  } else if (draft.type === 'arrow') {
    labelText = 'Nhận xét cho mũi tên chỉ dẫn';
    const x2 = draft.drawingData?.x2 ?? (draft.x + draft.w);
    const y2 = draft.drawingData?.y2 ?? (draft.y + draft.h);
    coordText = `Từ (${fmt(draft.x)}, ${fmt(draft.y)}) px ➔ Đến (${fmt(x2)}, ${fmt(y2)}) px`;
  } else if (draft.type === 'freehand') {
    labelText = 'Nhận xét cho nét vẽ tự do';
    coordText = `Vùng vẽ tự do: ${draft.drawingData?.length || 0} điểm tọa độ`;
  }

  const pop = el('div', { class: 'popover', role: 'dialog', 'aria-label': 'Nhập nhận xét', 'data-testid': 'comment-popover' }, [
    el('label', { class: 'field-label', for: 'comment-body', text: labelText }),
    el('p', { class: 'coord', text: coordText }),
    textarea,
    error,
    el('div', { class: 'inline-actions' }, [send, cancel])
  ]);
  attachMentionAutocomplete(textarea, pop);
  document.body.append(pop);
  composer.pop = pop;
  positionComposer();
  textarea.focus();
  cancel.addEventListener('click', closeComposer);
  send.addEventListener('click', () => submitComposer(textarea, error, send));
  textarea.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) submitComposer(textarea, error, send);
  });
  removeComposerEscape = pushEscape(closeComposer);
}

async function submitComposer(textarea, error, send) {
  error.textContent = '';
  if (!textarea.value.trim()) {
    error.textContent = 'Nhận xét không được để trống.';
    textarea.focus();
    return;
  }
  setBusy(send, true, 'Đang gửi...');
  try {
    const created = await api.createAnnotation({
      imageVersionId: version.id,
      type: composer.type,
      x: composer.x,
      y: composer.y,
      w: composer.w,
      h: composer.h,
      drawingData: composer.drawingData || null,
      body: textarea.value
    });
    closeComposer();
    filters.status = 'all';
    filters.q = '';
    syncFilterControls();
    await reloadAnnotations();
    selectAnnotation(created.id, 'create');
    toast('Đã lưu góp ý.', 'success');
    triggerNotificationRefresh();
  } catch (err) {
    setBusy(send, false);
    error.textContent = err.message || 'Không lưu được góp ý.';
    toast(err.message || 'Không lưu được góp ý.', 'error');
  }
}

function clusterAnnotations(list, zoomVal) {
  if (zoomVal >= 0.65 || list.length < 2) {
    return { clusters: [], singles: list };
  }
  const threshold = Math.max(30, Math.round(38 / zoomVal));
  const clusters = [];
  const singles = [];
  const candidates = [];
  const nonClustered = [];

  list.forEach((ann) => {
    if (ann.type === 'arrow' || ann.type === 'freehand') {
      nonClustered.push(ann);
    } else {
      candidates.push(ann);
    }
  });

  const assigned = new Set();
  for (let i = 0; i < candidates.length; i++) {
    if (assigned.has(i)) continue;
    const a = candidates[i];
    const ax = a.type === 'rect' ? a.x + (a.w || 0) / 2 : a.x;
    const ay = a.type === 'rect' ? a.y + (a.h || 0) / 2 : a.y;
    const group = [a];
    assigned.add(i);

    for (let j = i + 1; j < candidates.length; j++) {
      if (assigned.has(j)) continue;
      const b = candidates[j];
      const bx = b.type === 'rect' ? b.x + (b.w || 0) / 2 : b.x;
      const by = b.type === 'rect' ? b.y + (b.h || 0) / 2 : b.y;
      const dist = Math.hypot(ax - bx, ay - by);
      if (dist <= threshold) {
        group.push(b);
        assigned.add(j);
      }
    }

    if (group.length > 1) {
      let sumX = 0, sumY = 0;
      group.forEach((item) => {
        sumX += item.type === 'rect' ? item.x + (item.w || 0) / 2 : item.x;
        sumY += item.type === 'rect' ? item.y + (item.h || 0) / 2 : item.y;
      });
      clusters.push({
        id: `cluster-${group.map(g => g.id).join('-')}`,
        x: Math.round(sumX / group.length),
        y: Math.round(sumY / group.length),
        items: group,
        hasOpen: group.some(g => g.status !== 'resolved'),
        allResolved: group.every(g => g.status === 'resolved'),
        hasActive: group.some(g => g.id === selectedId)
      });
    } else {
      singles.push(a);
    }
  }

  return { clusters, singles: singles.concat(nonClustered) };
}

function renderMarkers() {
  const extras = [...ui.overlay.querySelectorAll('.temp-rect, .temp-point, .temp-svg, .crosshair')];
  ui.overlay.replaceChildren();

  // Create SVG layer for arrows and freehand strokes
  const svgLayer = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svgLayer.setAttribute('class', 'annotation-svg-layer');
  svgLayer.setAttribute('viewBox', `0 0 ${version.width} ${version.height}`);
  const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
  defs.innerHTML = `
    <marker id="dm-arr-open" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 1 L 10 5 L 0 9 z" fill="#4F46E5" />
    </marker>
    <marker id="dm-arr-resolved" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 1 L 10 5 L 0 9 z" fill="#10B981" />
    </marker>
    <marker id="dm-arr-active" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 1 L 10 5 L 0 9 z" fill="#F59E0B" />
    </marker>
  `;
  svgLayer.appendChild(defs);
  ui.overlay.append(svgLayer);

  const visible = (allMarkers.length ? allMarkers : annotations).filter(matchesMarker);
  const { clusters, singles } = clusterAnnotations(visible, zoom);

  // Render clusters at low zoom (< 65%)
  clusters.forEach((cl) => {
    const clusterStyle = toStyle({ x: cl.x, y: cl.y, w: 0, h: 0 }, version.width, version.height);
    const clusterBtn = el('button', {
      type: 'button',
      class: `marker marker-cluster ${cl.allResolved ? 'all-resolved' : 'has-open'}${cl.hasActive ? ' is-active' : ''}`,
      'data-testid': 'annotation-cluster',
      'data-cluster-ids': cl.items.map(i => i.id).join(','),
      'aria-label': `Cụm ${cl.items.length} góp ý`,
      title: `Cụm ${cl.items.length} góp ý (#${cl.items.map(i => i.number).join(', #')}) — Nhấn để phóng to`
    }, [
      el('span', { class: 'cluster-count', text: `+${cl.items.length}` })
    ]);
    clusterBtn.style.left = clusterStyle.left;
    clusterBtn.style.top = clusterStyle.top;

    clusterBtn.addEventListener('pointerdown', (e) => e.stopPropagation());
    clusterBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const targetZoom = Math.min(1.5, Math.max(0.85, zoom * 1.8));
      zoom = targetZoom;
      fitMode = false;
      applyZoom();
      centerOnCoords(cl.x, cl.y);
      const targetItem = cl.items.find(i => i.id === selectedId) || cl.items.find(i => i.status !== 'resolved') || cl.items[0];
      if (targetItem) {
        selectAnnotation(targetItem.id, 'marker');
      }
    });
    ui.overlay.append(clusterBtn);
  });

  singles.forEach((ann) => {
    const isResolved = ann.status === 'resolved';
    const isActive = ann.id === selectedId;

    if (ann.type === 'arrow') {
      let d = { x1: ann.x, y1: ann.y, x2: ann.x + (ann.w || 80), y2: ann.y + (ann.h || 40) };
      if (ann.drawingData) {
        try { d = typeof ann.drawingData === 'string' ? JSON.parse(ann.drawingData) : ann.drawingData; } catch (e) {}
      }
      const strokeColor = isActive ? '#F59E0B' : (isResolved ? '#10B981' : '#4F46E5');
      const markerId = isActive ? 'url(#dm-arr-active)' : (isResolved ? 'url(#dm-arr-resolved)' : 'url(#dm-arr-open)');

      const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1', d.x1);
      line.setAttribute('y1', d.y1);
      line.setAttribute('x2', d.x2);
      line.setAttribute('y2', d.y2);
      line.setAttribute('stroke', strokeColor);
      line.setAttribute('stroke-width', isActive ? '5' : '3.5');
      line.setAttribute('marker-end', markerId);
      line.setAttribute('class', 'svg-annotation-interactive');
      line.addEventListener('click', (e) => {
        e.stopPropagation();
        selectAnnotation(ann.id, 'marker');
      });
      svgLayer.appendChild(line);

      // Badge at start/tail (x1, y1)
      const badgeStyle = toStyle({ x: d.x1, y: d.y1, w: 0, h: 0 }, version.width, version.height);
      const badge = el('button', {
        type: 'button',
        class: `marker-path-badge status-${ann.status}${isActive ? ' is-active' : ''}`,
        'data-id': ann.id,
        'aria-label': `Mũi tên #${ann.number}: ${ann.body}`,
        title: `Mũi tên #${ann.number}: ${ann.body.slice(0, 80)}`,
        text: String(ann.number)
      });
      badge.style.left = badgeStyle.left;
      badge.style.top = badgeStyle.top;
      badge.addEventListener('pointerdown', (e) => e.stopPropagation());
      badge.addEventListener('click', (e) => {
        e.stopPropagation();
        selectAnnotation(ann.id, 'marker');
      });
      ui.overlay.append(badge);
      return;
    }

    if (ann.type === 'freehand') {
      let pts = [];
      if (ann.drawingData) {
        try { pts = typeof ann.drawingData === 'string' ? JSON.parse(ann.drawingData) : ann.drawingData; } catch (e) {}
      }
      if (pts.length > 1) {
        const strokeColor = isActive ? '#F59E0B' : (isResolved ? '#10B981' : '#EF4444');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const dStr = `M ${pts.map(p => `${p.x} ${p.y}`).join(' L ')}`;
        path.setAttribute('d', dStr);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', strokeColor);
        path.setAttribute('stroke-width', isActive ? '5' : '3.5');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        path.setAttribute('class', 'svg-annotation-interactive');
        path.addEventListener('click', (e) => {
          e.stopPropagation();
          selectAnnotation(ann.id, 'marker');
        });
        svgLayer.appendChild(path);

        // Badge at first point
        const badgeStyle = toStyle({ x: pts[0].x, y: pts[0].y, w: 0, h: 0 }, version.width, version.height);
        const badge = el('button', {
          type: 'button',
          class: `marker-path-badge status-${ann.status}${isActive ? ' is-active' : ''}`,
          'data-id': ann.id,
          'aria-label': `Vẽ tự do #${ann.number}: ${ann.body}`,
          title: `Vẽ tự do #${ann.number}: ${ann.body.slice(0, 80)}`,
          text: String(ann.number)
        });
        badge.style.left = badgeStyle.left;
        badge.style.top = badgeStyle.top;
        badge.addEventListener('pointerdown', (e) => e.stopPropagation());
        badge.addEventListener('click', (e) => {
          e.stopPropagation();
          selectAnnotation(ann.id, 'marker');
        });
        ui.overlay.append(badge);
        return;
      }
    }

    const style = toStyle(ann, ann.originalWidth || version.width, ann.originalHeight || version.height);
    const marker = el('button', {
      type: 'button',
      class: `marker marker-${ann.type === 'rect' ? 'rect' : 'point'} status-${ann.status}${isActive ? ' is-active' : ''}`,
      'data-testid': 'annotation-marker',
      'data-id': ann.id,
      'aria-label': `Góp ý #${ann.number}: ${ann.body}`,
      title: `Góp ý #${ann.number}: ${ann.body.slice(0, 80)}`
    });
    marker.dataset.id = ann.id;
    marker.style.left = style.left;
    marker.style.top = style.top;
    if (ann.type === 'rect') {
      marker.style.width = style.width;
      marker.style.height = style.height;
      marker.append(el('span', { class: `marker-label status-${ann.status}`, text: String(ann.number) }));
    } else {
      marker.replaceChildren(el('span', { text: String(ann.number) }));
    }
    marker.addEventListener('pointerdown', (event) => event.stopPropagation());
    marker.addEventListener('click', (event) => {
      event.stopPropagation();
      selectAnnotation(ann.id, 'marker');
    });
    ui.overlay.append(marker);
  });
  extras.forEach((node) => ui.overlay.append(node));
}

async function selectAnnotation(id, source) {
  if (!id) {
    selectedId = null;
    renderMarkers();
    renderThreads();
    return;
  }

  // Check if this annotation exists in current page items
  const existsOnPage = annotations.some((item) => item.id === id);
  if (!existsOnPage) {
    try {
      await reloadAnnotations({ targetAnnId: id });
    } catch (err) {
      console.error('Error loading target annotation page:', err);
    }
  }

  const finalExists = annotations.some((item) => item.id === id) || allMarkers.some((item) => item.id === id);
  if (!finalExists) {
    selectedId = null;
    renderMarkers();
    renderThreads();
    if (source === 'url' || source === 'notification') {
      toast('Góp ý này không tồn tại trên phiên bản hiện tại hoặc đã bị xóa.', 'info');
    }
    return;
  }

  selectedId = id;
  renderMarkers();
  renderThreads();

  // Scroll active thread into view in sidebar
  setTimeout(() => {
    const activeThread = ui.panel.querySelector(`[data-testid="comment-thread"][data-id="${id}"]`);
    if (activeThread) {
      activeThread.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }, 60);

  // Scroll active marker into view on canvas if clicked from sidebar or URL
  if (source !== 'marker') {
    setTimeout(() => {
      ui.overlay.querySelector(`.marker[data-id="${id}"], .marker[data-cluster-ids*="${id}"], .marker-path-badge[data-id="${id}"]`)?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }, 60);
  }

  const reviewLayout = document.getElementById('review-layout');
  if (source === 'marker' && reviewLayout && reviewLayout.classList.contains('is-canvas-only')) {
    showMobileCanvasPreview(id);
  } else {
    document.querySelector('.mobile-canvas-preview-pill')?.remove();
  }
}

function showMobileCanvasPreview(id) {
  const target = (allMarkers.length ? allMarkers : annotations).find(a => a.id === id);
  if (!target) return;
  let pill = document.querySelector('.mobile-canvas-preview-pill');
  if (!pill) {
    pill = el('div', { class: 'mobile-canvas-preview-pill' });
    ui.viewport.parentElement?.append(pill);
  }
  const snippet = (target.body || target.bodySnippet || '').slice(0, 60);
  pill.replaceChildren(
    el('div', { class: 'mobile-preview-text', style: 'flex: 1; min-width: 0;' }, [
      el('strong', { text: `#${target.number}: `, style: 'color: var(--heading);' }),
      el('span', { text: snippet || 'Góp ý', style: 'color: var(--text);' })
    ]),
    el('button', {
      type: 'button',
      class: 'btn btn-primary btn-sm',
      text: 'Xem góp ý →',
      onclick: () => {
        const mobileTabs = document.getElementById('mobile-review-tabs');
        const commentsTab = mobileTabs?.querySelector('[data-tab="comments"]');
        if (commentsTab) commentsTab.click();
        pill.remove();
      }
    })
  );
}

function toggleAnnotation(id) {
  if (selectedId === id) {
    selectedId = null;
    renderMarkers();
    renderThreads();
  } else {
    selectAnnotation(id, 'thread');
  }
}

function syncFilterControls() {
  const status = ui.panel.querySelector('#filter-status');
  const author = ui.panel.querySelector('#filter-author');
  const sort = ui.panel.querySelector('#filter-sort');
  const search = ui.panel.querySelector('#filter-search');
  if (status) status.value = filters.status;
  if (author) author.value = filters.author;
  if (sort) sort.value = filters.sort;
  if (search && document.activeElement !== search) search.value = filters.q;
}

function renderPaginationControls() {
  if (pagination.totalPages <= 1) {
    return null;
  }

  const container = el('div', { class: 'pagination-container' });
  const nav = el('nav', { class: 'pagination-nav', 'aria-label': 'Phân trang góp ý' });

  // Prev button
  const prevBtn = el('button', {
    type: 'button',
    class: 'pagination-btn pagination-prev',
    disabled: pagination.page <= 1,
    title: 'Trang trước',
    'aria-label': 'Trang trước',
    onclick: (e) => {
      e.stopPropagation();
      if (pagination.page > 1) {
        filters.page = pagination.page - 1;
        reloadAnnotations();
      }
    }
  }, [el('span', { text: 'Trước' })]);
  nav.append(prevBtn);

  // Page numbers calculation with ellipsis
  const total = pagination.totalPages;
  const current = pagination.page;
  const pages = [];

  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i);
  } else {
    pages.push(1);
    if (current > 3) pages.push('...');
    
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) pages.push(i);

    if (current < total - 2) pages.push('...');
    pages.push(total);
  }

  pages.forEach((p) => {
    if (p === '...') {
      nav.append(el('span', { class: 'pagination-ellipsis', text: '…' }));
    } else {
      const pageNumBtn = el('button', {
        type: 'button',
        class: `pagination-btn pagination-num ${p === current ? 'is-active' : ''}`,
        'aria-current': p === current ? 'page' : null,
        title: `Trang ${p}`,
        'aria-label': `Trang ${p}`,
        text: String(p),
        onclick: (e) => {
          e.stopPropagation();
          if (p !== current) {
            filters.page = p;
            reloadAnnotations();
          }
        }
      });
      nav.append(pageNumBtn);
    }
  });

  // Next button
  const nextBtn = el('button', {
    type: 'button',
    class: 'pagination-btn pagination-next',
    disabled: pagination.page >= pagination.totalPages,
    title: 'Trang sau',
    'aria-label': 'Trang sau',
    onclick: (e) => {
      e.stopPropagation();
      if (pagination.page < pagination.totalPages) {
        filters.page = pagination.page + 1;
        reloadAnnotations();
      }
    }
  }, [el('span', { text: 'Sau' })]);
  nav.append(nextBtn);

  container.append(nav);
  return container;
}

function renderThreads() {
  const list = ui.panel.querySelector('#thread-list');
  const paginationBox = ui.panel.querySelector('#thread-pagination');
  if (!list) return;

  list.replaceChildren();

  if (!annotations.length) {
    const hasFilter = (filters.status !== 'all' || filters.author !== 'all' || filters.q !== '');
    list.append(emptyState({
      title: hasFilter ? 'Không tìm thấy góp ý nào' : 'Chưa có góp ý trên phiên bản này',
      text: hasFilter ? 'Thử xóa bớt bộ lọc hoặc thay đổi từ khóa tìm kiếm.' : 'Đặt điểm hoặc khoanh vùng trên ảnh để bắt đầu.'
    }));
    if (paginationBox) {
      paginationBox.replaceChildren();
      paginationBox.hidden = true;
    }
    return;
  }

  annotations.forEach((ann) => list.append(renderThread(ann)));

  if (paginationBox) {
    const controls = renderPaginationControls();
    if (controls) {
      paginationBox.replaceChildren(controls);
      paginationBox.hidden = false;
    } else {
      paginationBox.replaceChildren();
      paginationBox.hidden = true;
    }
  }
}

function formatMentions(text) {
  if (!text) return '';
  const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return escaped.replace(/@([a-zA-Z0-9_\-\.]+)/g, '<span class="mention-pill">@$1</span>');
}

function attachMentionAutocomplete(textarea, wrapper) {
  const menu = el('div', { class: 'mention-autocomplete-menu', hidden: true });
  if (wrapper) {
    if (getComputedStyle(wrapper).position === 'static') {
      wrapper.style.position = 'relative';
    }
    wrapper.append(menu);
  } else {
    document.body.append(menu);
  }

  let activeIndex = 0;
  let matches = [];
  let atIndex = -1;

  function closeMenu() {
    menu.setAttribute('hidden', '');
    menu.innerHTML = '';
    matches = [];
    activeIndex = 0;
    atIndex = -1;
  }

  function renderMenu() {
    menu.innerHTML = '';
    if (!matches.length) {
      closeMenu();
      return;
    }
    menu.removeAttribute('hidden');
    matches.forEach((m, idx) => {
      const item = el('button', {
        type: 'button',
        class: `mention-autocomplete-item ${idx === activeIndex ? 'is-active' : ''}`,
        onclick: (e) => {
          e.stopPropagation();
          insertMention(m);
        }
      }, [
        avatarEl(m, 22),
        el('span', { class: 'mention-item-name', text: m.name || m.username }),
        el('span', { class: 'mention-item-handle', text: `@${m.username}` })
      ]);
      menu.append(item);
    });
  }

  function insertMention(m) {
    if (atIndex < 0) return;
    const val = textarea.value;
    const cursorPos = textarea.selectionStart;
    const beforeAt = val.slice(0, atIndex);
    const afterCursor = val.slice(cursorPos);
    const insertText = `@${m.username} `;
    textarea.value = beforeAt + insertText + afterCursor;
    const newPos = beforeAt.length + insertText.length;
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    closeMenu();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  textarea.addEventListener('input', () => {
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.slice(0, cursorPos);
    const match = textBefore.match(/(?:^|\s)@([a-zA-Z0-9_\-\.]*)$/);
    if (!match) {
      closeMenu();
      return;
    }
    const query = match[1].toLowerCase();
    atIndex = cursorPos - match[1].length - 1;
    const members = project?.members || [];
    matches = members.filter(m => {
      const uname = (m.username || '').toLowerCase();
      const name = (m.name || '').toLowerCase();
      return uname.includes(query) || name.includes(query);
    });
    activeIndex = 0;
    if (matches.length > 0) {
      renderMenu();
    } else {
      closeMenu();
    }
  });

  textarea.addEventListener('keydown', (e) => {
    if (menu.hasAttribute('hidden') || !matches.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % matches.length;
      renderMenu();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + matches.length) % matches.length;
      renderMenu();
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      insertMention(matches[activeIndex]);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      closeMenu();
    }
  });

  textarea.addEventListener('blur', () => {
    setTimeout(closeMenu, 220);
  });
}

function copyAnnotationLink(ann) {
  const url = new URL(window.location.href);
  url.searchParams.set('ann', ann.id);
  const link = url.toString();
  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(link).then(() => {
      toast('Đã sao chép liên kết góp ý vào bộ nhớ tạm.', 'success');
    }).catch(() => {
      prompt('Liên kết góp ý:', link);
    });
  } else {
    prompt('Liên kết góp ý:', link);
  }
}

function renderThreadMoreMenu(ann) {
  const isAuthor = ann.author.username === document.body.dataset.user;
  const canDelete = (role === 'manager') || (isAuthor && role !== 'viewer' && role !== 'admin_observer');
  const canEdit = isAuthor && canDraw();

  const wrap = el('div', { class: 'thread-more-wrap' });
  const triggerBtn = el('button', {
    type: 'button',
    class: 'thread-more-btn',
    title: 'Thao tác khác',
    'aria-label': `Thao tác khác cho góp ý #${ann.number}`,
    onclick: (e) => {
      e.stopPropagation();
      toggleMenu();
    }
  }, [icon('more')]);

  const menu = el('div', { class: 'thread-more-menu', hidden: true });

  if (canEdit) {
    const editItem = el('button', {
      type: 'button',
      class: 'thread-more-item',
      onclick: (e) => {
        e.stopPropagation();
        closeMenu();
        startEdit(ann);
      }
    }, [
      icon('edit'),
      el('span', { text: 'Sửa nội dung' })
    ]);
    menu.append(editItem);
  }

  const copyLinkItem = el('button', {
    type: 'button',
    class: 'thread-more-item',
    onclick: (e) => {
      e.stopPropagation();
      closeMenu();
      copyAnnotationLink(ann);
    }
  }, [
    icon('link'),
    el('span', { text: 'Sao chép liên kết' })
  ]);
  menu.append(copyLinkItem);

  if (canDelete) {
    const deleteItem = el('button', {
      type: 'button',
      class: 'thread-more-item is-danger',
      onclick: (e) => {
        e.stopPropagation();
        closeMenu();
        promptDelete(ann);
      }
    }, [
      icon('trash'),
      el('span', { text: 'Xóa góp ý' })
    ]);
    menu.append(deleteItem);
  }

  let removeEscape = null;
  function openMenu() {
    menu.removeAttribute('hidden');
    removeEscape = pushEscape(closeMenu);
    document.addEventListener('click', onDocClick);
  }

  function closeMenu() {
    menu.setAttribute('hidden', '');
    if (removeEscape) {
      removeEscape();
      removeEscape = null;
    }
    document.removeEventListener('click', onDocClick);
  }

  function toggleMenu() {
    if (menu.hasAttribute('hidden')) openMenu();
    else closeMenu();
  }

  function onDocClick(e) {
    if (!wrap.contains(e.target)) closeMenu();
  }

  wrap.append(triggerBtn, menu);
  return wrap;
}

const QUICK_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

function renderReactionsBar(reactions = [], onToggle, isCompact = false) {
  const container = el('div', { class: `reactions-bar ${isCompact ? 'is-compact' : ''}` });
  const validReactions = (reactions || []).filter((r) => r.count > 0);
  const hasReactions = validReactions.length > 0;

  // 1. Render existing reaction pills if any
  validReactions.forEach((r) => {
    const tooltipText = (r.users && r.users.length) ? r.users.join(', ') : '';
    const pill = el('button', {
      type: 'button',
      class: `reaction-pill ${isCompact ? 'is-compact-pill' : ''} ${r.hasReacted ? 'is-reacted' : ''}`,
      title: tooltipText ? `${r.emoji} · ${tooltipText}` : r.emoji,
      'aria-label': `${r.count} người đã thả cảm xúc ${r.emoji}`,
      onclick: (e) => {
        e.stopPropagation();
        onToggle(r.emoji);
      }
    }, [
      el('span', { class: 'reaction-emoji', text: r.emoji }),
      el('span', { class: 'reaction-count', text: String(r.count) })
    ]);
    container.append(pill);
  });

  // 2. Reaction picker trigger & popover
  const pickerWrapper = el('div', { class: 'reaction-picker-wrapper' });

  const isIconOnly = isCompact || hasReactions;
  const triggerBtn = el('button', {
    type: 'button',
    class: `reaction-add-btn ${isCompact ? 'is-compact-btn' : ''} ${isIconOnly ? 'is-icon-only' : 'is-empty-state'}`,
    title: hasReactions ? 'Thêm cảm xúc' : 'Thả cảm xúc',
    'aria-label': hasReactions ? 'Thêm cảm xúc' : 'Thả cảm xúc',
    onclick: (e) => {
      e.stopPropagation();
      togglePicker();
    }
  }, [
    icon('smilePlus'),
    (!isCompact && !hasReactions) ? el('span', { class: 'reaction-btn-text', text: 'Thả cảm xúc' }) : null
  ].filter(Boolean));

  const pickerPopover = el('div', { class: 'reaction-picker-popover', hidden: true });
  QUICK_EMOJIS.forEach((em) => {
    const currentRx = validReactions.find((r) => r.emoji === em);
    const hasMyReaction = currentRx ? !!currentRx.hasReacted : false;
    const emBtn = el('button', {
      type: 'button',
      class: `reaction-emoji-btn ${hasMyReaction ? 'is-reacted-option' : ''}`,
      title: hasMyReaction ? `${em} (Nhấn để bỏ cảm xúc)` : em,
      'aria-label': hasMyReaction ? `Bỏ cảm xúc ${em}` : `Thả cảm xúc ${em}`,
      text: em,
      onclick: (e) => {
        e.stopPropagation();
        closePicker();
        onToggle(em);
      }
    });
    pickerPopover.append(emBtn);
  });

  let removeEscape = null;
  function openPicker() {
    pickerPopover.removeAttribute('hidden');
    requestAnimationFrame(() => {
      const rect = pickerPopover.getBoundingClientRect();
      if (rect.right > (window.innerWidth - 12)) {
        pickerPopover.style.left = 'auto';
        pickerPopover.style.right = '0';
      } else {
        pickerPopover.style.left = '0';
        pickerPopover.style.right = 'auto';
      }
    });
    removeEscape = pushEscape(closePicker);
    document.addEventListener('click', onDocClick);
  }

  function closePicker() {
    pickerPopover.setAttribute('hidden', '');
    if (removeEscape) {
      removeEscape();
      removeEscape = null;
    }
    document.removeEventListener('click', onDocClick);
  }

  function togglePicker() {
    if (pickerPopover.hasAttribute('hidden')) {
      openPicker();
    } else {
      closePicker();
    }
  }

  function onDocClick(e) {
    if (!pickerWrapper.contains(e.target)) {
      closePicker();
    }
  }

  pickerWrapper.append(triggerBtn, pickerPopover);
  container.append(pickerWrapper);

  return container;
}

function renderCollapsedReactions(reactions = []) {
  if (!reactions || !reactions.length) return null;
  const valid = reactions.filter((r) => r.count > 0);
  if (!valid.length) return null;

  const wrap = el('div', { class: 'thread-collapsed-reactions' });
  valid.forEach((r) => {
    const tooltipText = (r.users && r.users.length) ? r.users.join(', ') : '';
    wrap.append(el('span', {
      class: `collapsed-reaction-badge ${r.hasReacted ? 'is-reacted' : ''}`,
      title: tooltipText ? `${r.emoji} · ${tooltipText}` : r.emoji,
      text: `${r.emoji} ${r.count}`
    }));
  });
  return wrap;
}

async function handleAnnotationReaction(ann, emoji) {
  // Optimistic update
  if (!ann.reactions) ann.reactions = [];
  let existing = ann.reactions.find((r) => r.emoji === emoji);
  const myName = currentSession?.name || 'Bạn';
  if (existing) {
    if (existing.hasReacted) {
      existing.hasReacted = false;
      existing.count = Math.max(0, existing.count - 1);
      existing.users = (existing.users || []).filter((u) => u !== myName && u !== 'Bạn');
    } else {
      existing.hasReacted = true;
      existing.count = (existing.count || 0) + 1;
      if (!existing.users) existing.users = [];
      existing.users.push(myName);
    }
  } else {
    ann.reactions.push({
      emoji,
      count: 1,
      users: [myName],
      hasReacted: true
    });
  }
  ann.reactions = ann.reactions.filter((r) => r.count > 0);
  renderThreads();

  try {
    const updated = await api.toggleReaction(ann.id, null, emoji);
    const idx = annotations.findIndex((a) => a.id === ann.id);
    if (idx >= 0) {
      annotations[idx] = updated;
    }
    triggerNotificationRefresh();
    renderThreads();
  } catch (err) {
    toast('Không thể thả cảm xúc: ' + (err.message || ''), 'error');
    renderThreads();
  }
}

async function handleReplyReaction(ann, replyId, emoji) {
  // Optimistic update
  const rep = (ann.replies || []).find((r) => r.id === replyId);
  if (rep) {
    if (!rep.reactions) rep.reactions = [];
    let existing = rep.reactions.find((r) => r.emoji === emoji);
    const myName = currentSession?.name || 'Bạn';
    if (existing) {
      if (existing.hasReacted) {
        existing.hasReacted = false;
        existing.count = Math.max(0, existing.count - 1);
        existing.users = (existing.users || []).filter((u) => u !== myName && u !== 'Bạn');
      } else {
        existing.hasReacted = true;
        existing.count = (existing.count || 0) + 1;
        if (!existing.users) existing.users = [];
        existing.users.push(myName);
      }
    } else {
      rep.reactions.push({
        emoji,
        count: 1,
        users: [myName],
        hasReacted: true
      });
    }
    rep.reactions = rep.reactions.filter((r) => r.count > 0);
    renderThreads();
  }

  try {
    const updated = await api.toggleReaction(ann.id, replyId, emoji);
    const idx = annotations.findIndex((a) => a.id === ann.id);
    if (idx >= 0) {
      annotations[idx] = updated;
    }
    triggerNotificationRefresh();
    renderThreads();
  } catch (err) {
    toast('Không thể thả cảm xúc: ' + (err.message || ''), 'error');
    renderThreads();
  }
}

function renderThread(ann) {
  const isExpanded = ann.id === selectedId;
  const replyCount = ann.replies?.length || 0;
  let typeLabel = 'Điểm đánh dấu';
  if (ann.type === 'rect') typeLabel = 'Khoanh vùng';
  else if (ann.type === 'arrow') typeLabel = 'Mũi tên chỉ dẫn';
  else if (ann.type === 'freehand') typeLabel = 'Nét vẽ tự do';

  const verNum = ann.versionNumber || (version && version.number) || 1;
  const friendlyLocation = `${typeLabel} #${ann.number} · Phiên bản v${verNum}`;

  const thread = el('article', {
    class: `thread thread-accordion${isExpanded ? ' is-active' : ' is-collapsed'}`,
    'data-testid': 'comment-thread',
    'data-id': ann.id,
    'aria-expanded': isExpanded ? 'true' : 'false'
  });
  thread.dataset.id = ann.id;

  // Header of accordion card
  const head = el('div', {
    class: 'thread-top thread-head',
    role: 'button',
    tabindex: '0',
    'aria-label': `Góp ý #${ann.number} của ${ann.author.name}, ${isExpanded ? 'nhấn để thu gọn' : 'nhấn để mở rộng'}`
  }, [
    avatarEl(ann.author, 32),
    el('div', { class: 'thread-author-wrap' }, [
      el('div', { class: 'thread-title-row' }, [
        el('strong', { class: 'thread-author-name', text: ann.author.name }),
        el('span', { class: 'thread-num-badge', text: `#${ann.number}` })
      ]),
      el('div', { class: 'thread-sub-row' }, [
        el('time', { datetime: ann.createdAt, text: formatDate(ann.createdAt) })
      ])
    ]),
    el('div', { class: 'thread-head-right' }, [
      statusBadge(ann.status),
      renderThreadMoreMenu(ann),
      el('span', { class: `thread-chevron${isExpanded ? ' is-open' : ''}`, 'aria-hidden': 'true' }, [icon('chevron')])
    ])
  ]);

  // Click & keyboard handlers on header
  head.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleAnnotation(ann.id);
  });
  head.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      e.stopPropagation();
      toggleAnnotation(ann.id);
    }
  });

  if (!isExpanded) {
    const locationBadge = el('div', { class: 'thread-location-row' }, [
      el('span', { class: 'thread-location-tag', text: friendlyLocation })
    ]);

    const bodyPreview = el('p', { class: 'thread-body is-preview' });
    bodyPreview.innerHTML = formatMentions(ann.body);

    const collapsedRx = renderCollapsedReactions(ann.reactions);
    const meta = el('div', { class: 'thread-collapsed-meta' }, [
      el('div', { style: 'display: flex; align-items: center; gap: 8px;' }, [
        replyCount > 0 ? el('span', { class: 'thread-reply-pill', text: `${replyCount} phản hồi` }) : null,
        collapsedRx
      ].filter(Boolean)),
      el('span', { class: 'thread-expand-hint', text: 'Nhấn để mở chi tiết' })
    ]);

    thread.append(head, locationBadge, bodyPreview, meta);

    // Clicking anywhere on collapsed thread expands it
    thread.addEventListener('click', () => {
      selectAnnotation(ann.id, 'thread');
    });

    return thread;
  }

  // When expanded:
  // 1. Sleek location target chip
  const locationChip = el('div', { class: 'thread-target-chip' }, [
    el('span', { class: 'chip-pin-icon', text: '📍' }),
    el('span', { class: 'chip-text', text: friendlyLocation })
  ]);

  // 2. Collapsible location details ("Chi tiết vị trí")
  let coordText = '';
  if (ann.type === 'rect') {
    coordText = `X: ${fmt(ann.x)} px · Y: ${fmt(ann.y)} px · Rộng: ${fmt(ann.w)} px · Cao: ${fmt(ann.h)} px`;
  } else if (ann.type === 'arrow') {
    let d = { x1: ann.x, y1: ann.y, x2: ann.x + ann.w, y2: ann.y + ann.h };
    if (ann.drawingData) {
      try { d = typeof ann.drawingData === 'string' ? JSON.parse(ann.drawingData) : ann.drawingData; } catch (e) {}
    }
    coordText = `Bắt đầu: (${fmt(d.x1)}, ${fmt(d.y1)}) px ➔ Đến: (${fmt(d.x2)}, ${fmt(d.y2)}) px`;
  } else if (ann.type === 'freehand') {
    let pts = [];
    if (ann.drawingData) {
      try { pts = typeof ann.drawingData === 'string' ? JSON.parse(ann.drawingData) : ann.drawingData; } catch (e) {}
    }
    coordText = `Nét vẽ tự do gồm ${pts.length || 0} điểm tọa độ`;
  } else {
    coordText = `X: ${fmt(ann.x)} px · Y: ${fmt(ann.y)} px`;
  }

  if (ann.checkNote) {
    coordText += ` · Ghi chú: ${ann.checkNote}`;
  }

  const coordDetails = el('details', { class: 'coord-details' }, [
    el('summary', { text: 'Chi tiết vị trí' }),
    el('div', { class: 'coord-details-body' }, [
      el('span', { class: 'coord-value', text: coordText })
    ])
  ]);
  coordDetails.addEventListener('click', (e) => e.stopPropagation());

  // 3. Body with "Xem thêm" / "Thu gọn" if long
  const isBodyExpanded = expandedBodySet.has(ann.id);
  const isLongBody = (ann.body || '').length > 200 || (ann.body || '').split('\n').length > 3;

  const bodyWrap = el('div', { class: 'thread-body-wrap' });
  const body = el('p', { class: `thread-body ${isLongBody && !isBodyExpanded ? 'is-clamped' : ''}` });
  body.innerHTML = formatMentions(ann.body);
  bodyWrap.append(body);

  if (isLongBody) {
    const toggleBtn = el('button', {
      type: 'button',
      class: 'btn-text-toggle',
      onclick: (e) => {
        e.stopPropagation();
        if (isBodyExpanded) expandedBodySet.delete(ann.id);
        else expandedBodySet.add(ann.id);
        renderThreads();
      }
    }, [
      el('span', { text: isBodyExpanded ? 'Thu gọn' : 'Xem thêm' })
    ]);
    bodyWrap.append(toggleBtn);
  }

  // 3b. Action buttons & reactions row (all on the same line)
  const actions = actionRow(ann);

  thread.append(head, locationChip, coordDetails, bodyWrap, actions);

  if (editing?.id === ann.id) {
    thread.append(editBox(ann));
  }

  // 4. Replies list
  const totalReplies = ann.replyCount !== undefined ? ann.replyCount : (ann.replies?.length || 0);
  const replies = ann.replies || [];
  if (totalReplies > 0 || replies.length > 0) {
    const repliesContainer = el('div', { class: 'replies-container' });
    const isRepliesExpanded = expandedRepliesSet.has(ann.id);

    if (totalReplies > 2 && !isRepliesExpanded) {
      const expandBtn = el('button', {
        type: 'button',
        class: 'btn-replies-toggle',
        onclick: async (e) => {
          e.stopPropagation();
          if (ann.hasMoreReplies && !fullRepliesMap.has(ann.id)) {
            expandBtn.disabled = true;
            expandBtn.textContent = 'Đang tải phản hồi...';
            try {
              const res = await api.getReplies(ann.id);
              fullRepliesMap.set(ann.id, res.replies);
              ann.replies = res.replies;
              ann.replyCount = res.total;
              ann.hasMoreReplies = false;
            } catch (err) {
              toast('Không tải được danh sách phản hồi.', 'error');
            }
          } else if (fullRepliesMap.has(ann.id)) {
            ann.replies = fullRepliesMap.get(ann.id);
          }
          expandedRepliesSet.add(ann.id);
          renderThreads();
        }
      }, [
        icon('reply'),
        el('span', { text: `Xem tất cả (${totalReplies}) phản hồi` })
      ]);
      repliesContainer.append(expandBtn);
    }

    const repliesToShow = (!isRepliesExpanded && replies.length > 2)
      ? replies.slice(-2)
      : replies;

    repliesToShow.forEach((reply) => {
      const copy = el('div', { class: 'reply-copy' });
      const replyBody = el('p', { class: 'reply-body' });
      replyBody.innerHTML = formatMentions(reply.body);

      // Compact reactions bar for replies (small icon only, no long text)
      const replyRxBar = renderReactionsBar(reply.reactions, (emoji) => {
        handleReplyReaction(ann, reply.id, emoji);
      }, true);

      copy.append(
        el('div', { class: 'reply-meta' }, [
          el('strong', { class: 'reply-author-name', text: reply.author.name }),
          el('time', { datetime: reply.createdAt, text: formatDate(reply.createdAt) })
        ]),
        replyBody
      );
      if (reply.attachmentUrl) {
        const imgAtt = el('img', {
          class: 'reply-attachment-thumb',
          src: reply.attachmentUrl,
          alt: 'Ảnh đính kèm',
          title: 'Nhấp để xem ảnh lớn'
        });
        imgAtt.addEventListener('click', (e) => {
          e.stopPropagation();
          openModal({
            title: 'Ảnh đính kèm trong phản hồi',
            content: el('div', { style: 'text-align: center; padding: 10px;' }, [
              el('img', { src: reply.attachmentUrl, style: 'max-width: 100%; max-height: 80vh; border-radius: 8px;' })
            ]),
            size: 'wide'
          });
        });
        copy.append(imgAtt);
      }
      copy.append(replyRxBar);
      repliesContainer.append(el('div', { class: 'reply' }, [avatarEl(reply.author, 24), copy]));
    });

    if (totalReplies > 2 && isRepliesExpanded) {
      const collapseBtn = el('button', {
        type: 'button',
        class: 'btn-replies-toggle is-collapse',
        onclick: (e) => {
          e.stopPropagation();
          expandedRepliesSet.delete(ann.id);
          renderThreads();
        }
      }, [
        el('span', { text: 'Thu gọn phản hồi' })
      ]);
      repliesContainer.append(collapseBtn);
    }

    thread.append(repliesContainer);
  }

  // 5. Reply composer (only rendered if user clicked "Trả lời" or has a draft)
  const hasDraft = Boolean(getDraft(ann.id));
  if (canDraw() && (openReplyBoxSet.has(ann.id) || hasDraft)) {
    thread.append(replyBox(ann));
  }

  // 6. Status history
  if (ann.statusHistory?.length) {
    const history = el('div', { class: 'help' });
    ann.statusHistory.forEach((item) => {
      const line = el('div', {
        text: `${formatDate(item.at)} · ${item.user?.name || ''} · ${item.note || (item.status === 'resolved' ? 'Đã xử lý' : 'Mở lại')}`
      });
      history.append(line);
    });
    const historyDetails = el('details', { class: 'status-history-details' }, [
      el('summary', { text: 'Lịch sử trạng thái' }),
      history
    ]);
    historyDetails.addEventListener('click', (e) => e.stopPropagation());
    thread.append(historyDetails);
  }

  return thread;
}

function promptDelete(ann) {
  let isDeleting = false;
  const num = ann.number || '';
  const confirmBtn = el('button', {
    type: 'button',
    class: 'btn btn-danger',
    text: 'Xóa góp ý'
  });
  const cancelBtn = el('button', {
    type: 'button',
    class: 'btn btn-secondary',
    text: 'Hủy'
  });

  const content = el('div', {}, [
    el('p', {
      class: 'modal-copy',
      text: 'Bạn có chắc chắn muốn xóa góp ý này không? Góp ý và các phản hồi liên quan có thể bị xóa theo chính sách dữ liệu hiện tại.'
    }),
    el('div', { class: 'modal-actions' }, [
      cancelBtn,
      confirmBtn
    ])
  ]);

  const modal = openModal({
    title: `Xóa góp ý #${num}?`,
    content
  });

  cancelBtn.addEventListener('click', () => {
    if (isDeleting) return;
    modal.close();
  });

  confirmBtn.addEventListener('click', async () => {
    if (isDeleting) return;
    isDeleting = true;
    confirmBtn.disabled = true;
    cancelBtn.disabled = true;
    confirmBtn.textContent = 'Đang xóa...';

    try {
      await api.deleteAnnotation(ann.id);
      modal.close();
      if (selectedId === ann.id) {
        selectedId = null;
      }
      if (editing?.id === ann.id) {
        editing = null;
      }
      removeDraft(ann.id);
      toast('Đã xóa góp ý thành công.', 'success');
      await reloadAnnotations();
      triggerNotificationRefresh();
    } catch (err) {
      toast(err.message || 'Không thể xóa góp ý.', 'error');
      isDeleting = false;
      confirmBtn.disabled = false;
      cancelBtn.disabled = false;
      confirmBtn.textContent = 'Xóa góp ý';
    }
  });
}

function actionRow(ann) {
  const bar = el('div', { class: 'thread-actions-bar' });

  // 1. Emoji reactions bar
  const annReactionsBar = renderReactionsBar(ann.reactions, (emoji) => {
    handleAnnotationReaction(ann, emoji);
  }, false);
  bar.append(annReactionsBar);

  if (canDraw()) {
    // 2. Reply toggle button
    const isReplyOpen = openReplyBoxSet.has(ann.id);
    const replyBtn = el('button', {
      type: 'button',
      class: `btn-reply-toggle ${isReplyOpen ? 'is-active' : ''}`,
      title: isReplyOpen ? 'Đóng ô trả lời' : 'Viết phản hồi',
      'aria-label': 'Trả lời góp ý này',
      onclick: (event) => {
        event.stopPropagation();
        if (openReplyBoxSet.has(ann.id)) {
          openReplyBoxSet.delete(ann.id);
          renderThreads();
        } else {
          openReplyBoxSet.add(ann.id);
          renderThreads();
          setTimeout(() => {
            const input = ui.panel.querySelector(`#reply-${ann.id}`);
            if (input) {
              input.focus();
              input.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
          }, 50);
        }
      }
    }, [
      icon('reply'),
      el('span', { text: 'Trả lời' })
    ]);
    bar.append(replyBtn);

    // 3. Status quick toggle
    const isResolved = ann.status === 'resolved';
    const statusBtn = el('button', {
      type: 'button',
      class: `btn-status-toggle ${isResolved ? 'is-reopen' : 'is-resolve'}`,
      title: isResolved ? 'Mở lại góp ý này' : 'Đánh dấu góp ý đã xử lý',
      'aria-label': isResolved ? 'Mở lại góp ý' : 'Đánh dấu góp ý đã xử lý',
      onclick: (event) => {
        event.stopPropagation();
        changeStatus(ann, isResolved ? 'open' : 'resolved');
      }
    }, [
      isResolved ? icon('refresh') : icon('check'),
      el('span', { text: isResolved ? 'Mở lại' : 'Đã xử lý' })
    ]);
    bar.append(statusBtn);
  }

  return bar;
}

function startEdit(ann) {
  editing = { id: ann.id, body: ann.body, revision: ann.revision };
  renderThreads();
  ui.panel.querySelector(`#edit-${ann.id}`)?.focus();
}

function editBox(ann) {
  const textarea = el('textarea', { class: 'field-input', id: `edit-${ann.id}` });
  textarea.value = editing.body;
  const error = el('p', { class: 'field-error' });
  const save = el('button', { type: 'button', class: 'btn btn-primary btn-sm', text: 'Lưu' });
  const cancel = el('button', { type: 'button', class: 'btn btn-secondary btn-sm', text: 'Hủy', onclick: (event) => { event.stopPropagation(); editing = null; renderThreads(); } });
  save.addEventListener('click', async (event) => {
    event.stopPropagation();
    if (!textarea.value.trim()) {
      error.textContent = 'Nhận xét không được để trống.';
      return;
    }
    setBusy(save, true, 'Đang lưu...');
    try {
      await api.updateAnnotationBody(ann.id, {
        body: textarea.value,
        revision: editing.revision,
        simulateConflict
      });
      simulateConflict = false;
      syncConflictButton();
      editing = null;
      await reloadAnnotations();
      toast('Đã cập nhật nhận xét.', 'success');
    } catch (err) {
      setBusy(save, false);
      if (err.code === 'CONFLICT') {
        simulateConflict = false;
        syncConflictButton();
        openConflict(ann.id, err);
        return;
      }
      error.textContent = err.message || 'Không lưu được.';
      toast(err.message || 'Không lưu được nhận xét.', 'error');
    }
  });
  return el('div', { onclick: (event) => event.stopPropagation() }, [
    el('label', { class: 'field-label', for: `edit-${ann.id}`, text: 'Sửa nhận xét' }),
    textarea,
    error,
    el('div', { class: 'inline-actions' }, [save, cancel])
  ]);
}

function openConflict(id, err) {
  const mine = el('div', { class: 'conflict-box' });
  mine.textContent = err.mine || '';
  const latest = el('div', { class: 'conflict-box' });
  latest.textContent = err.latest?.body || '';
  const content = el('div', {}, [
    el('p', { class: 'modal-copy', text: 'Nhận xét đã thay đổi trước khi bạn lưu. Không tự ghi đè.' }),
    el('div', { class: 'conflict-grid' }, [
      el('div', {}, [el('h3', { text: 'Nội dung của tôi' }), mine]),
      el('div', {}, [el('h3', { text: 'Nội dung mới nhất' }), latest])
    ]),
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Tải bản mới nhất', onclick: async () => { modal.close(); editing = null; await reloadAnnotations(); } }),
      el('button', { type: 'button', class: 'btn btn-primary', text: 'Giữ nội dung của tôi để sửa lại', onclick: async () => {
        modal.close();
        editing = { id, body: err.mine, revision: err.latest.revision };
        await reloadAnnotations();
        toast('Bản trên máy chủ đã đổi. Hãy lưu lại nếu bạn muốn dùng nội dung của mình.', 'info');
      } })
    ])
  ]);
  const modal = openModal({ title: 'Xung đột nội dung', content, size: 'wide' });
}

function replyBox(ann) {
  if (!canDraw()) {
    return el('div', { class: 'permission-note', style: 'padding: 6px 0; font-size: 13px; color: var(--muted);' }, [
      el('span', { text: role === 'admin_observer' ? 'Chế độ giám sát hệ thống (Chỉ xem, không gửi phản hồi).' : 'Bạn chỉ có quyền xem, không gửi phản hồi trong dự án này.' })
    ]);
  }

  const box = el('div', { class: 'reply-composer-card', onclick: (event) => event.stopPropagation() });
  const textarea = el('textarea', {
    class: 'reply-composer-textarea',
    id: `reply-${ann.id}`,
    rows: '2',
    placeholder: 'Viết phản hồi, dùng @username để nhắc tên...'
  });

  const draft = getDraft(ann.id);
  if (draft) textarea.value = draft;

  textarea.addEventListener('input', () => {
    setDraft(ann.id, textarea.value);
  });

  // Attach mention autocomplete
  attachMentionAutocomplete(textarea, box);

  // Attachment state
  let selectedFile = null;
  const attachPreview = el('div', { class: 'composer-attachment-preview', hidden: true });
  const attachThumb = el('img', { class: 'composer-attachment-thumb', src: '', alt: 'Preview' });
  const attachName = el('span', { class: 'composer-attachment-name', text: '' });
  const attachRemove = el('button', {
    type: 'button',
    class: 'composer-attachment-remove',
    title: 'Xóa ảnh này',
    'aria-label': 'Xóa ảnh đính kèm',
    onclick: () => {
      selectedFile = null;
      fileInput.value = '';
      attachPreview.setAttribute('hidden', '');
    }
  }, [icon('close') || el('span', { text: '✕' })]);

  attachPreview.append(attachThumb, attachName, attachRemove);

  const fileInput = el('input', { type: 'file', accept: 'image/*', style: 'display:none;' });
  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files[0]) {
      selectedFile = fileInput.files[0];
      attachThumb.src = URL.createObjectURL(selectedFile);
      attachName.textContent = selectedFile.name;
      attachPreview.removeAttribute('hidden');
    }
  });

  // Bottom toolbar
  const toolbar = el('div', { class: 'reply-composer-toolbar' });

  const attachBtn = el('button', {
    type: 'button',
    class: 'composer-tool-btn',
    title: 'Đính kèm ảnh',
    'aria-label': 'Đính kèm ảnh',
    onclick: () => fileInput.click()
  }, [icon('paperclip')]);

  const actionsRight = el('div', { class: 'reply-composer-actions' });

  const cancelBtn = el('button', {
    type: 'button',
    class: 'btn btn-secondary btn-sm',
    text: 'Hủy',
    onclick: () => {
      removeDraft(ann.id);
      openReplyBoxSet.delete(ann.id);
      renderThreads();
      toast('Đã hủy bản nháp phản hồi.', 'info');
    }
  });

  const sendBtn = el('button', {
    type: 'button',
    class: 'btn btn-primary btn-sm',
    text: 'Gửi trả lời'
  });

  sendBtn.addEventListener('click', async (event) => {
    event.stopPropagation();
    if (!textarea.value.trim() && !selectedFile) {
      toast('Nội dung phản hồi không được để trống.', 'error');
      return;
    }
    setBusy(sendBtn, true, 'Đang gửi...');
    try {
      let attachmentUrl = null;
      if (selectedFile) {
        const uploadRes = await api.uploadAttachment(selectedFile);
        attachmentUrl = uploadRes.url;
      }
      await api.addReply(ann.id, textarea.value, attachmentUrl);
      removeDraft(ann.id);
      openReplyBoxSet.delete(ann.id);
      fullRepliesMap.delete(ann.id);
      await reloadAnnotations();
      toast('Đã gửi phản hồi thành công.', 'success');
      triggerNotificationRefresh();
    } catch (err) {
      setBusy(sendBtn, false);
      toast(err.message || 'Không gửi được trả lời.', 'error');
    }
  });

  actionsRight.append(cancelBtn, sendBtn);
  toolbar.append(attachBtn, actionsRight);

  box.append(textarea, attachPreview, fileInput, toolbar);
  return box;
}

async function changeStatus(ann, status) {
  try {
    await api.setAnnotationStatus(ann.id, status);
    await reloadAnnotations();
    toast(status === 'resolved' ? 'Đã đánh dấu đã xử lý.' : 'Đã mở lại góp ý.', 'success');
    triggerNotificationRefresh();
  } catch (err) {
    toast(err.message || 'Không đổi được trạng thái.', 'error');
  }
}

let searchDebounceTimer = null;

function ensurePanel() {
  const members = project?.members || [];
  const authorsMap = new Map();
  members.forEach((m) => authorsMap.set(m.id, m.name));
  allMarkers.forEach((m) => {
    if (m.authorId && !authorsMap.has(m.authorId)) {
      authorsMap.set(m.authorId, m.author?.name || 'Thành viên');
    }
  });

  // Calculate status counts
  const totalCount = allMarkers.length || pagination.total || 0;
  const openCount = allMarkers.filter((m) => m.status === 'open').length;
  const resolvedCount = allMarkers.filter((m) => m.status === 'resolved').length;

  // Segmented status pills
  const statusSegments = el('div', { class: 'filter-segmented-status', role: 'tablist', 'aria-label': 'Lọc trạng thái' });
  const statusTabs = [
    { value: 'all', label: 'Tất cả', count: totalCount },
    { value: 'open', label: 'Chưa xử lý', count: openCount, dot: 'open' },
    { value: 'resolved', label: 'Đã xử lý', count: resolvedCount, dot: 'resolved' }
  ];

  statusTabs.forEach((tab) => {
    const isActive = filters.status === tab.value;
    const btn = el('button', {
      type: 'button',
      class: `status-segment-btn ${isActive ? 'is-active' : ''}`,
      role: 'tab',
      'aria-selected': isActive ? 'true' : 'false',
      onclick: () => {
        if (filters.status === tab.value) return;
        filters.status = tab.value;
        filters.page = 1;
        if (statusSelect) statusSelect.value = tab.value;
        reloadAnnotations();
      }
    }, [
      tab.dot ? el('span', { class: `status-tab-dot dot-${tab.dot}` }) : null,
      el('span', { class: 'status-tab-label', text: tab.label }),
      el('span', { class: 'status-tab-count', text: String(tab.count) })
    ].filter(Boolean));
    statusSegments.append(btn);
  });

  // Status select (kept in DOM with sr-only for test/compatibility)
  const statusSelect = el('select', { class: 'field-input filter-select sr-only', id: 'filter-status', 'aria-label': 'Lọc trạng thái' }, [
    el('option', { value: 'all', text: 'Tất cả trạng thái' }),
    el('option', { value: 'open', text: 'Chưa xử lý' }),
    el('option', { value: 'resolved', text: 'Đã xử lý' })
  ]);
  statusSelect.value = filters.status;
  statusSelect.addEventListener('change', () => {
    filters.status = statusSelect.value;
    filters.page = 1;
    reloadAnnotations();
  });

  // Search input with icon and inline clear
  const search = el('input', {
    class: 'field-input search-input',
    id: 'filter-search',
    type: 'search',
    placeholder: 'Tìm theo #mã, nội dung, người gửi...',
    'aria-label': 'Tìm theo mã, nội dung, người gửi'
  });
  search.value = filters.q;
  search.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      filters.q = search.value;
      filters.page = 1;
      reloadAnnotations();
    }, 280);
  });

  const searchBox = el('div', { class: 'filter-search-box' }, [
    icon('search'),
    search,
    filters.q ? el('button', {
      type: 'button',
      class: 'search-clear-inline',
      title: 'Xóa tìm kiếm',
      onclick: () => {
        search.value = '';
        filters.q = '';
        filters.page = 1;
        reloadAnnotations();
      }
    }, [icon('close')]) : null
  ].filter(Boolean));

  // Author select
  const author = el('select', { class: 'field-input filter-select filter-compact-select', id: 'filter-author', 'aria-label': 'Lọc người gửi' }, [
    el('option', { value: 'all', text: 'Tất cả người gửi' }),
    ...[...authorsMap.entries()].map(([id, name]) => el('option', { value: id, text: name }))
  ]);
  author.value = filters.author;
  author.addEventListener('change', () => {
    filters.author = author.value;
    filters.page = 1;
    reloadAnnotations();
  });

  // Sort select
  const sort = el('select', { class: 'field-input filter-select filter-compact-select', id: 'filter-sort', 'aria-label': 'Sắp xếp góp ý' }, [
    el('option', { value: 'newest', text: 'Mới nhất' }),
    el('option', { value: 'oldest', text: 'Cũ nhất' }),
    el('option', { value: 'most_replies', text: 'Nhiều phản hồi nhất' })
  ]);
  sort.value = filters.sort;
  sort.addEventListener('change', () => {
    filters.sort = sort.value;
    filters.page = 1;
    reloadAnnotations();
  });

  // Clear filters button if any filter is active
  const hasActiveFilter = (filters.status !== 'all' || filters.author !== 'all' || filters.q !== '' || filters.sort !== 'newest');
  const clearFilterBtn = hasActiveFilter ? el('button', {
    type: 'button',
    class: 'btn-clear-filters',
    title: 'Xóa toàn bộ bộ lọc và đặt lại tìm kiếm',
    'aria-label': 'Xóa toàn bộ bộ lọc',
    onclick: () => {
      filters.status = 'all';
      filters.author = 'all';
      filters.q = '';
      filters.sort = 'newest';
      filters.page = 1;
      search.value = '';
      reloadAnnotations();
    }
  }, [
    icon('close') || el('span', { text: '✕' }),
    el('span', { text: 'Xóa bộ lọc' })
  ]) : null;

  const conflict = el('button', {
    type: 'button',
    class: 'btn btn-secondary btn-sm',
    id: 'conflict-toggle',
    text: 'Giả lập xung đột',
    hidden: !canDraw()
  });
  conflict.addEventListener('click', () => {
    simulateConflict = !simulateConflict;
    syncConflictButton();
    toast(simulateConflict ? 'Lần sửa nhận xét tiếp theo sẽ mô phỏng xung đột.' : 'Đã tắt giả lập xung đột.', 'info');
  });

  ui.panel.replaceChildren(
    el('div', { class: 'panel-head' }, [
      el('div', { class: 'panel-head-titles' }, [
        el('h2', { text: 'Góp ý' }),
        el('span', { class: 'panel-count-badge', id: 'comment-count', text: `${pagination.total || 0}` })
      ]),
      conflict
    ]),
    el('div', { class: 'panel-pad' }, [
      canDraw() ? null : el('p', { class: 'permission-note', text: (role === 'admin_observer' ? 'Chế độ giám sát hệ thống (Chỉ xem dữ liệu).' : 'Bạn chỉ có quyền xem.') }),
      statusSegments,
      statusSelect,
      searchBox,
      el('div', { class: 'filter-grid-bar' }, [author, sort]),
      clearFilterBtn ? el('div', { class: 'filter-actions-bar' }, [clearFilterBtn]) : null
    ].filter(Boolean)),
    el('div', { id: 'thread-list' }),
    el('div', { id: 'thread-pagination' })
  );
  syncConflictButton();
}

function syncConflictButton() {
  const button = document.getElementById('conflict-toggle');
  if (!button) return;
  button.classList.toggle('is-active', simulateConflict);
  button.setAttribute('aria-pressed', simulateConflict ? 'true' : 'false');
  button.textContent = simulateConflict ? 'Giả lập xung đột: bật' : 'Giả lập xung đột';
}

function updateCount() {
  const total = pagination.total || 0;
  const node = document.getElementById('comment-count');
  if (node) {
    node.textContent = `${total}`;
  }
  const mobileNode = document.getElementById('mobile-comments-count');
  if (mobileNode) {
    mobileNode.textContent = `${total}`;
  }
}

async function reloadAnnotations(options = {}) {
  const params = {
    page: filters.page,
    limit: filters.limit,
    status: filters.status,
    author: filters.author,
    q: filters.q,
    sort: filters.sort
  };

  if (options.targetAnnId) {
    params.target_ann_id = options.targetAnnId;
    params.auto_jump = 1;
  }

  const res = await api.listAnnotations(version.id, params);

  if (res && res.items && res.pagination) {
    annotations = res.items;
    pagination = res.pagination;
    filters.page = pagination.page;
    if (res.markers) {
      allMarkers = res.markers;
    }
    if (options.targetAnnId) {
      selectedId = options.targetAnnId;
    }
  } else if (Array.isArray(res)) {
    annotations = res;
    allMarkers = res;
    pagination = {
      page: 1,
      limit: annotations.length,
      total: annotations.length,
      totalPages: 1,
      start: annotations.length ? 1 : 0,
      end: annotations.length
    };
  }

  ensurePanel();
  updateCount();
  renderMarkers();
  renderThreads();
}

function finishDrag(event) {
  if (!drag || event.pointerId !== drag.pointerId) return;
  const dx = event.clientX - drag.startClientX;
  const dy = event.clientY - drag.startClientY;
  const dist = Math.hypot(dx, dy);
  const end = toOriginal(event, coordTarget());
  const start = drag.start;
  const used = drag.tool;
  const points = drag.points || [start];
  drag = null;

  if (used === 'arrow') {
    if (dist < 6) {
      clearTemp();
      return;
    }
    openComposer({
      type: 'arrow',
      x: start.x,
      y: start.y,
      w: end.x - start.x,
      h: end.y - start.y,
      drawingData: { x1: start.x, y1: start.y, x2: end.x, y2: end.y }
    });
    return;
  }

  if (used === 'freehand') {
    if (points.length < 3) {
      clearTemp();
      return;
    }
    const xs = points.map(p => p.x);
    const ys = points.map(p => p.y);
    const minX = Math.min(...xs);
    const minY = Math.min(...ys);
    const maxX = Math.max(...xs);
    const maxY = Math.max(...ys);
    openComposer({
      type: 'freehand',
      x: minX,
      y: minY,
      w: Math.max(10, maxX - minX),
      h: Math.max(10, maxY - minY),
      drawingData: points
    });
    return;
  }

  if (used === 'point' || dist < 4) {
    openComposer({ type: 'point', x: start.x, y: start.y, w: 0, h: 0 });
    return;
  }
  const x = Math.min(start.x, end.x);
  const y = Math.min(start.y, end.y);
  const w = Math.abs(end.x - start.x);
  const h = Math.abs(end.y - start.y);
  if (w <= 0 || h <= 0) {
    clearTemp();
    return;
  }
  openComposer({ type: 'rect', x, y, w, h });
}

function bindPointer() {
  ui.overlay.addEventListener('pointerdown', (event) => {
    if (event.button !== 0 || composer || !imageReady) return;
    if (event.target.closest('.marker') || event.target.closest('.marker-path-badge')) return;
    if (tool === 'select') {
      selectedId = null;
      renderMarkers();
      renderThreads();
      return;
    }
    if (!canDraw()) return;
    event.preventDefault();
    try { ui.overlay.setPointerCapture(event.pointerId); } catch (err) { /* một số trình duyệt từ chối capture */ }
    const startCoord = toOriginal(event, coordTarget());
    drag = {
      pointerId: event.pointerId,
      startClientX: event.clientX,
      startClientY: event.clientY,
      start: startCoord,
      points: [startCoord],
      tool
    };
    if (tool === 'rect') showTemp({ type: 'rect', ...rectFrom(drag.start, drag.start) });
    else if (tool === 'arrow') showTemp({ type: 'arrow', x1: drag.start.x, y1: drag.start.y, x2: drag.start.x, y2: drag.start.y });
  });

  ui.overlay.addEventListener('pointermove', (event) => {
    if (!drag || event.pointerId !== drag.pointerId) return;
    const cur = toOriginal(event, coordTarget());
    if (drag.tool === 'rect') {
      showTemp({ type: 'rect', ...rectFrom(drag.start, cur) });
    } else if (drag.tool === 'arrow') {
      showTemp({ type: 'arrow', x1: drag.start.x, y1: drag.start.y, x2: cur.x, y2: cur.y });
    } else if (drag.tool === 'freehand') {
      drag.points.push(cur);
      showTemp({ type: 'freehand', points: drag.points });
    }
  });
  ui.overlay.addEventListener('pointerup', finishDrag);
  ui.overlay.addEventListener('pointercancel', () => {
    drag = null;
    clearTemp();
  });
  window.addEventListener('pointerup', finishDrag);
  window.addEventListener('pointercancel', () => {
    if (!drag) return;
    drag = null;
    clearTemp();
  });
}

function rectFrom(start, end) {
  return {
    x: Math.min(start.x, end.x),
    y: Math.min(start.y, end.y),
    w: Math.abs(end.x - start.x),
    h: Math.abs(end.y - start.y)
  };
}

function bindKeyboard() {
  ui.stage.addEventListener('keydown', (event) => {
    if (!canDraw() || tool === 'select') return;
    const step = event.shiftKey ? 40 : 10;
    if (event.key === 'ArrowLeft') keyboard.x -= step;
    else if (event.key === 'ArrowRight') keyboard.x += step;
    else if (event.key === 'ArrowUp') keyboard.y -= step;
    else if (event.key === 'ArrowDown') keyboard.y += step;
    else if (event.key === 'Escape' && keyboard.anchor) {
      keyboard.anchor = null;
      toast('Đã hủy vùng đang tạo bằng bàn phím.', 'info');
      return;
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      placeFromKeyboard();
      return;
    } else return;
    event.preventDefault();
    keyboard.x = clamp(keyboard.x, 0, version.width);
    keyboard.y = clamp(keyboard.y, 0, version.height);
    showCrosshair(true);
    ui['tool-hint'].textContent = `Tọa độ bàn phím: x ${fmt(keyboard.x)}, y ${fmt(keyboard.y)}`;
  });
  ui.stage.addEventListener('focus', () => showCrosshair(true));
  ui.stage.addEventListener('blur', () => showCrosshair(false));
}

function placeFromKeyboard() {
  if (tool === 'rect') {
    if (!keyboard.anchor) {
      keyboard.anchor = { x: keyboard.x, y: keyboard.y };
      toast('Đã chọn điểm bắt đầu. Di chuyển rồi nhấn Enter để hoàn tất.', 'info');
      return;
    }
    const draft = { type: 'rect', ...rectFrom(keyboard.anchor, keyboard) };
    keyboard.anchor = null;
    if (draft.w < 1 && draft.h < 1) {
      openComposer({ type: 'point', x: keyboard.x, y: keyboard.y, w: 0, h: 0 });
      return;
    }
    openComposer(draft);
    return;
  }
  openComposer({ type: 'point', x: keyboard.x, y: keyboard.y, w: 0, h: 0 });
}

function openCoordDialog() {
  if (!canDraw()) return;
  const x = el('input', { class: 'field-input', id: 'coord-x', type: 'number', min: '0', step: '1', value: '100' });
  const y = el('input', { class: 'field-input', id: 'coord-y', type: 'number', min: '0', step: '1', value: '100' });
  const w = el('input', { class: 'field-input', id: 'coord-w', type: 'number', min: '0', step: '1', value: '0' });
  const h = el('input', { class: 'field-input', id: 'coord-h', type: 'number', min: '0', step: '1', value: '0' });
  const body = el('textarea', { class: 'field-input', id: 'coord-body' });
  const error = el('p', { class: 'field-error' });
  const form = el('form', {}, [
    el('p', { class: 'help', text: 'Nhập tọa độ pixel của ảnh gốc. Để w và h bằng 0 nếu muốn đặt điểm.' }),
    el('div', { class: 'filter-bar' }, [
      labeled('coord-x', 'X', x), labeled('coord-y', 'Y', y), labeled('coord-w', 'W', w), labeled('coord-h', 'H', h)
    ]),
    el('label', { class: 'field-label', for: 'coord-body', text: 'Nhận xét' }),
    body,
    error,
    el('div', { class: 'modal-actions' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      el('button', { type: 'submit', class: 'btn btn-primary', text: 'Lưu góp ý' })
    ])
  ]);
  const modal = openModal({ title: 'Nhập tọa độ', content: form });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const draft = {
      type: Number(w.value) > 0 && Number(h.value) > 0 ? 'rect' : 'point',
      x: Number(x.value),
      y: Number(y.value),
      w: Number(w.value) > 0 && Number(h.value) > 0 ? Number(w.value) : 0,
      h: Number(w.value) > 0 && Number(h.value) > 0 ? Number(h.value) : 0,
      body: body.value
    };
    try {
      const created = await api.createAnnotation({ ...draft, imageVersionId: version.id });
      modal.close();
      await reloadAnnotations();
      selectAnnotation(created.id, 'create');
      toast('Đã lưu góp ý theo tọa độ.', 'success');
    } catch (err) {
      error.textContent = err.message;
      toast(err.message || 'Không lưu được góp ý.', 'error');
    }
  });
}

function labeled(id, text, control) {
  return el('label', { class: 'field', for: id }, [el('span', { class: 'field-label', text }), control]);
}

async function showVersion(nextId, pushUrl = true) {
  closeComposer();
  selectedId = null;
  editing = null;
  filters.author = 'all';
  filters.status = 'all';
  filters.q = '';
  version = versions.find((item) => item.id === nextId) || versions[versions.length - 1];
  keyboard = { x: version.width / 2, y: version.height / 2, anchor: null };
  ui['version-select'].value = version.id;
  ui['screen-name'].textContent = screen.name;
  updateBanner();
  if (ui['migrate-btn']) ui['migrate-btn'].hidden = !canDraw() || !version || (version.number <= 1);
  if (pushUrl) {
    const url = `review.php?project=${encodeURIComponent(project.id)}&screen=${encodeURIComponent(screen.id)}&version=${encodeURIComponent(version.id)}`;
    history.replaceState(null, '', url);
  }
  const src = await api.resolveImageUrl(version.imageKey);
  await normalizeImage(src);
  if (fitMode) applyFit();
  else applyZoom();
  await reloadAnnotations();
}

async function exportAnnotatedImage() {
  if (!version || !ui.img || !ui.img.naturalWidth) {
    toast('Ảnh chưa sẵn sàng để xuất.', 'error');
    return;
  }
  toast('Đang chuẩn bị xuất ảnh thiết kế kèm chú thích...', 'info');

  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  const nw = ui.img.naturalWidth || version.width;
  const nh = ui.img.naturalHeight || version.height;

  const visibleAnns = annotations.filter(matches);
  const legendItemHeight = 44;
  const legendPadding = 24;
  const legendHeight = visibleAnns.length > 0 ? (legendPadding * 2 + visibleAnns.length * legendItemHeight + 60) : 0;

  canvas.width = nw;
  canvas.height = nh + legendHeight;

  // Background
  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  // Draw base image
  try {
    ctx.drawImage(ui.img, 0, 0, nw, nh);
  } catch (e) {
    console.warn('Canvas drawImage issue:', e);
  }

  // Draw annotations
  visibleAnns.forEach((ann) => {
    const isResolved = ann.status === 'resolved';
    const mainColor = isResolved ? '#10B981' : '#4F46E5';

    if (ann.type === 'point') {
      ctx.save();
      ctx.shadowColor = 'rgba(0, 0, 0, 0.4)';
      ctx.shadowBlur = 8;
      ctx.beginPath();
      ctx.arc(ann.x, ann.y, 16, 0, Math.PI * 2);
      ctx.fillStyle = mainColor;
      ctx.fill();
      ctx.lineWidth = 3;
      ctx.strokeStyle = '#FFFFFF';
      ctx.stroke();

      ctx.shadowBlur = 0;
      ctx.fillStyle = '#FFFFFF';
      ctx.font = 'bold 13px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(ann.number), ann.x, ann.y);
      ctx.restore();
    } else if (ann.type === 'rect') {
      ctx.save();
      ctx.fillStyle = isResolved ? 'rgba(16, 185, 129, 0.18)' : 'rgba(79, 70, 229, 0.18)';
      ctx.fillRect(ann.x, ann.y, ann.w, ann.h);

      ctx.lineWidth = 3;
      ctx.setLineDash([8, 6]);
      ctx.strokeStyle = mainColor;
      ctx.strokeRect(ann.x, ann.y, ann.w, ann.h);

      ctx.setLineDash([]);
      ctx.beginPath();
      ctx.arc(ann.x + 14, ann.y + 14, 14, 0, Math.PI * 2);
      ctx.fillStyle = mainColor;
      ctx.fill();
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#FFFFFF';
      ctx.stroke();

      ctx.fillStyle = '#FFFFFF';
      ctx.font = 'bold 12px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(ann.number), ann.x + 14, ann.y + 14);
      ctx.restore();
    } else if (ann.type === 'arrow') {
      let d = { x1: ann.x, y1: ann.y, x2: ann.x + (ann.w || 100), y2: ann.y + (ann.h || 50) };
      if (ann.drawingData) {
        try { d = typeof ann.drawingData === 'string' ? JSON.parse(ann.drawingData) : ann.drawingData; } catch (e) {}
      }
      ctx.save();
      ctx.lineWidth = 4;
      ctx.strokeStyle = mainColor;
      ctx.fillStyle = mainColor;

      ctx.beginPath();
      ctx.moveTo(d.x1, d.y1);
      ctx.lineTo(d.x2, d.y2);
      ctx.stroke();

      const angle = Math.atan2(d.y2 - d.y1, d.x2 - d.x1);
      const headLen = 18;
      ctx.beginPath();
      ctx.moveTo(d.x2, d.y2);
      ctx.lineTo(d.x2 - headLen * Math.cos(angle - Math.PI / 6), d.y2 - headLen * Math.sin(angle - Math.PI / 6));
      ctx.lineTo(d.x2 - headLen * Math.cos(angle + Math.PI / 6), d.y2 - headLen * Math.sin(angle + Math.PI / 6));
      ctx.closePath();
      ctx.fill();

      // Number badge at tail
      ctx.beginPath();
      ctx.arc(d.x1, d.y1, 14, 0, Math.PI * 2);
      ctx.fillStyle = mainColor;
      ctx.fill();
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#FFFFFF';
      ctx.stroke();

      ctx.fillStyle = '#FFFFFF';
      ctx.font = 'bold 12px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(ann.number), d.x1, d.y1);
      ctx.restore();
    } else if (ann.type === 'freehand') {
      let pts = [];
      if (ann.drawingData) {
        try { pts = typeof ann.drawingData === 'string' ? JSON.parse(ann.drawingData) : ann.drawingData; } catch (e) {}
      }
      if (pts.length > 1) {
        ctx.save();
        ctx.lineWidth = 4;
        ctx.strokeStyle = isResolved ? '#10B981' : '#EF4444';
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        for (let i = 1; i < pts.length; i++) {
          ctx.lineTo(pts[i].x, pts[i].y);
        }
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(pts[0].x, pts[0].y, 14, 0, Math.PI * 2);
        ctx.fillStyle = isResolved ? '#10B981' : '#EF4444';
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#FFFFFF';
        ctx.stroke();

        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 12px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(ann.number), pts[0].x, pts[0].y);
        ctx.restore();
      }
    }
  });

  // Draw legend bar at bottom
  if (visibleAnns.length > 0) {
    ctx.save();
    ctx.fillStyle = '#F8FAFC';
    ctx.fillRect(0, nh, nw, legendHeight);
    ctx.strokeStyle = '#E2E8F0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, nh);
    ctx.lineTo(nw, nh);
    ctx.stroke();

    ctx.fillStyle = '#0F172A';
    ctx.font = 'bold 18px Inter, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`DANH SÁCH GÓP Ý & CHÚ THÍCH THIẾT KẾ (${screen.name} · v${version.number})`, 30, nh + 38);

    let currentY = nh + 75;
    visibleAnns.forEach((ann) => {
      const isResolved = ann.status === 'resolved';
      const badgeColor = isResolved ? '#10B981' : '#4F46E5';
      const statusText = isResolved ? '[ĐÃ XỬ LÝ]' : '[CHỜ XỬ LÝ]';

      ctx.beginPath();
      ctx.arc(45, currentY, 12, 0, Math.PI * 2);
      ctx.fillStyle = badgeColor;
      ctx.fill();

      ctx.fillStyle = '#FFFFFF';
      ctx.font = 'bold 11px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(ann.number), 45, currentY);

      ctx.textAlign = 'left';
      ctx.font = 'bold 13px Inter, sans-serif';
      ctx.fillStyle = badgeColor;
      ctx.fillText(statusText, 70, currentY);

      ctx.font = 'bold 13px Inter, sans-serif';
      ctx.fillStyle = '#1E293B';
      const authorText = `${ann.author?.name || 'Thành viên'}:`;
      ctx.fillText(authorText, 175, currentY);

      ctx.font = 'normal 13px Inter, sans-serif';
      ctx.fillStyle = '#475569';
      let snippet = ann.body.replace(/\s+/g, ' ');
      if (snippet.length > 90) snippet = snippet.slice(0, 87) + '...';
      ctx.fillText(snippet, 300, currentY);

      currentY += legendItemHeight;
    });
    ctx.restore();
  }

  try {
    canvas.toBlob((blob) => {
      if (!blob) {
        toast('Không tạo được tệp ảnh.', 'error');
        return;
      }
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      const cleanName = (screen.name || 'screen').toLowerCase().replace(/[^a-z0-9]+/g, '_');
      a.download = `${cleanName}_v${version.number}_annotated.png`;
      document.body.appendChild(a);
      a.click();
      setTimeout(() => { a.remove(); URL.revokeObjectURL(url); }, 1000);
      toast('Đã xuất ảnh thiết kế kèm chú thích thành công!', 'success');
    }, 'image/png');
  } catch (err) {
    console.error('Export error:', err);
    toast('Không thể xuất ảnh từ canvas: ' + (err.message || 'Lỗi bảo mật trình duyệt'), 'error');
  }
}

async function promptMigrateUnresolved() {
  if (!version || version.number <= 1) {
    toast('Đây là phiên bản v1, không có phiên bản cũ hơn để chuyển góp ý.', 'info');
    return;
  }
  const prevNum = version.number - 1;
  const content = el('div', {}, [
    el('p', { class: 'modal-copy', text: `Bạn có muốn chuyển tất cả góp ý chưa xử lý (trạng thái Chờ xử lý) từ phiên bản v${prevNum} sang phiên bản v${version.number} này không?` }),
    el('p', { class: 'help', style: 'margin-top: 8px;', text: '• Góp ý trên phiên bản cũ sẽ được giữ nguyên vẹn.\n• Tọa độ điểm, vùng khoanh, mũi tên và nét vẽ sẽ được sao chép sang phiên bản này.' }),
    el('div', { class: 'modal-actions', style: 'margin-top: 20px;' }, [
      el('button', { type: 'button', class: 'btn btn-secondary', text: 'Hủy', onclick: () => modal.close() }),
      el('button', {
        type: 'button',
        class: 'btn btn-primary',
        text: 'Xác nhận chuyển',
        onclick: async (e) => {
          setBusy(e.target, true, 'Đang chuyển...');
          try {
            const res = await api.migrateUnresolvedAnnotations(version.id);
            modal.close();
            await reloadAnnotations();
            toast(res.message || 'Đã chuyển thành công.', 'success');
          } catch (err) {
            setBusy(e.target, false);
            toast(err.message || 'Chuyển góp ý thất bại.', 'error');
          }
        }
      })
    ])
  ]);
  const modal = openModal({ title: `Chuyển góp ý từ v${prevNum} sang v${version.number}`, content });
}

function bindChrome() {
  ui['back-link'].href = `project.php?id=${encodeURIComponent(project.id)}`;
  ui['back-link'].textContent = '← Quay lại dự án';
  ui['back-link'].title = `Quay lại dự án: ${project.name}`;
  ui['version-select'].replaceChildren(...versions.map((item) => el('option', { value: item.id, text: item.label })));
  ui['version-select'].addEventListener('change', () => {
    showVersion(ui['version-select'].value).catch((err) => toast(err.message, 'error'));
  });
  ui['zoom-in'].addEventListener('click', () => zoomBy(1.2));
  ui['zoom-out'].addEventListener('click', () => zoomBy(1 / 1.2));
  ui['zoom-fit'].addEventListener('click', applyFit);
  ui['tool-select'].addEventListener('click', () => setTool('select'));
  ui['tool-point'].addEventListener('click', () => setTool('point'));
  ui['tool-rectangle'].addEventListener('click', () => setTool('rect'));
  ui['tool-arrow']?.addEventListener('click', () => setTool('arrow'));
  ui['tool-freehand']?.addEventListener('click', () => setTool('freehand'));
  ui['export-annotated-btn']?.addEventListener('click', exportAnnotatedImage);
  ui['migrate-btn']?.addEventListener('click', promptMigrateUnresolved);
  ui['coord-btn'].addEventListener('click', openCoordDialog);
  ui['toggle-markers-btn']?.addEventListener('click', toggleMarkersVisibility);

  // Mobile Review Tabs (Split / Canvas / Comments)
  const mobileTabs = document.getElementById('mobile-review-tabs');
  const reviewLayout = document.getElementById('review-layout');
  if (mobileTabs && reviewLayout) {
    mobileTabs.querySelectorAll('.mobile-tab-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        mobileTabs.querySelectorAll('.mobile-tab-btn').forEach((b) => {
          const isActive = b === btn;
          b.classList.toggle('is-active', isActive);
          b.setAttribute('aria-selected', String(isActive));
        });
        reviewLayout.classList.remove('is-split', 'is-canvas-only', 'is-comments-only');
        if (tab === 'canvas') {
          reviewLayout.classList.add('is-canvas-only');
        } else if (tab === 'comments') {
          reviewLayout.classList.add('is-comments-only');
        } else {
          reviewLayout.classList.add('is-split');
        }
        document.querySelector('.mobile-canvas-preview-pill')?.remove();
        if (tab === 'canvas' || tab === 'split') {
          setTimeout(() => applyFit(), 100);
        }
      });
    });
  }
  ui['tool-point'].hidden = !canDraw();
  ui['tool-rectangle'].hidden = !canDraw();
  if (ui['tool-arrow']) ui['tool-arrow'].hidden = !canDraw();
  if (ui['tool-freehand']) ui['tool-freehand'].hidden = !canDraw();
  if (ui['migrate-btn']) ui['migrate-btn'].hidden = true;
  ui['coord-btn'].hidden = !canDraw();
  ui['permission-note'].textContent = (role === 'admin_observer' ? 'Chế độ giám sát hệ thống (Chỉ xem dữ liệu).' : 'Bạn chỉ có quyền xem.');
  ui['permission-note'].hidden = canDraw();
  setTool('select');
  bindPointer();
  bindKeyboard();

  // Bàn phím toàn cục cho Review Canvas (Phím tắt thiết kế chuyên nghiệp)
  let isSpaceDown = false;
  let isSpacePanning = false;
  let spacePanStart = { x: 0, y: 0, scrollLeft: 0, scrollTop: 0 };

  ui.viewport.addEventListener('pointerdown', (e) => {
    if (isSpaceDown && e.button === 0) {
      e.preventDefault();
      isSpacePanning = true;
      spacePanStart = {
        x: e.clientX,
        y: e.clientY,
        scrollLeft: ui.viewport.scrollLeft,
        scrollTop: ui.viewport.scrollTop
      };
      ui.viewport.style.cursor = 'grabbing';
      try { ui.viewport.setPointerCapture(e.pointerId); } catch (_) {}
    }
  });

  ui.viewport.addEventListener('pointermove', (e) => {
    if (isSpacePanning) {
      const dx = e.clientX - spacePanStart.x;
      const dy = e.clientY - spacePanStart.y;
      ui.viewport.scrollLeft = spacePanStart.scrollLeft - dx;
      ui.viewport.scrollTop = spacePanStart.scrollTop - dy;
    }
  });

  ui.viewport.addEventListener('pointerup', (e) => {
    if (isSpacePanning) {
      isSpacePanning = false;
      ui.viewport.style.cursor = isSpaceDown ? 'grab' : '';
      try { ui.viewport.releasePointerCapture(e.pointerId); } catch (_) {}
    }
  });

  window.addEventListener('keydown', (e) => {
    // 1. Chặn phím tắt khi đang gõ IME (tiếng Việt), input, textarea hoặc modal
    if (e.isComposing || e.keyCode === 229) return;

    const active = document.activeElement;
    const isTyping = active && (
      ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) ||
      active.isContentEditable ||
      active.closest?.('.reply-composer-card') ||
      active.closest?.('.popover') ||
      active.closest?.('.modal-dialog')
    );

    // Xử lý phím Escape ưu tiên theo thứ bậc an toàn
    if (e.key === 'Escape') {
      if (composer) {
        e.preventDefault();
        closeComposer();
        return;
      }
      if (document.querySelector('.modal-backdrop, .modal, dialog[open]')) {
        return; // Modal tự xử lý
      }
      if (selectedId) {
        e.preventDefault();
        selectAnnotation(null, 'esc');
        return;
      }
      if (tool !== 'select') {
        e.preventDefault();
        setTool('select');
        return;
      }
      return;
    }

    if (isTyping) return;
    if (e.ctrlKey || e.metaKey || e.altKey) return;

    // Giữ phím Space để tạm thời kéo rê ảnh (Pan)
    if (e.code === 'Space') {
      e.preventDefault();
      if (!isSpaceDown) {
        isSpaceDown = true;
        ui.viewport.style.cursor = 'grab';
      }
      return;
    }

    if (e.key === 'c' || e.key === 'C' || e.key === 'p' || e.key === 'P') {
      if (canDraw()) {
        e.preventDefault();
        setTool('point');
        toast('Công cụ: Đặt điểm góp ý (Pin)', 'info');
      }
    } else if (e.key === 'r' || e.key === 'R') {
      if (canDraw()) {
        e.preventDefault();
        setTool('rect');
        toast('Công cụ: Khoanh vùng chữ nhật (Rectangle)', 'info');
      }
    } else if (e.key === 'v' || e.key === 'V') {
      e.preventDefault();
      setTool('select');
      if (selectedId) {
        selectAnnotation(null, 'key');
      }
    } else if (e.key === 'h' || e.key === 'H') {
      e.preventDefault();
      toggleMarkersVisibility();
    } else if (e.key === '0') {
      e.preventDefault();
      applyFit();
    } else if (e.key === '1') {
      e.preventDefault();
      applyZoom(1);
    }
  });

  window.addEventListener('keyup', (e) => {
    if (e.code === 'Space' && isSpaceDown) {
      isSpaceDown = false;
      isSpacePanning = false;
      ui.viewport.style.cursor = '';
    }
  });
  ui.viewport.addEventListener('scroll', positionComposer);
  window.addEventListener('resize', () => {
    if (fitMode) applyFit();
    else positionComposer();
  });
  if (typeof ResizeObserver !== 'undefined') {
    const observer = new ResizeObserver(() => { if (fitMode && version) applyFit(); });
    observer.observe(ui.viewport);
  }
}

boot({
  active: 'projects',
  title: 'Góp ý trên ảnh',
  async render({ session, setBreadcrumb, setPageTitle }) {
    currentSession = session;
    cacheUi();
    if (!projectId || !screenId) throw new Error('Thiếu màn hình thiết kế cần góp ý.');
    document.body.dataset.user = session.username;
    project = await api.getProject(projectId);
    screen = await api.getScreen(screenId);
    role = project.myRole || 'viewer';
    versions = await api.listVersions(screenId);
    if (!versions.length) throw new Error('Màn hình này chưa có phiên bản ảnh.');
    setPageTitle(screen.name);
    setBreadcrumb([
      { label: 'DesignMark', href: 'projects.php' },
      { label: project.name, href: `project.php?id=${encodeURIComponent(project.id)}` },
      { label: screen.name }
    ]);
    bindChrome();
    const requested = params.get('version') || params.get('version_id');
    await showVersion(requested || versions[versions.length - 1].id, false);
    const targetAnnId = params.get('annotation') || params.get('annotation_id') || params.get('ann') || (location.hash ? location.hash.replace('#ann-', '').replace('#', '') : null);
    if (targetAnnId) {
      setTimeout(() => {
        selectAnnotation(targetAnnId, 'url');
      }, 250);
    }
  }
});
