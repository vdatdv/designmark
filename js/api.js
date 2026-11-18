/**
 * Tầng truy cập dữ liệu DesignMark kết nối PHP Backend API & SQLite.
 */
import { getSession } from './auth.js';
import { seedImageSrc } from './mock-data.js';

const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const urlCache = new Map();

function fail(message, code, extra = {}) {
  const error = new Error(message);
  error.code = code;
  Object.assign(error, extra);
  return error;
}

function sessionUser() {
  const session = getSession();
  if (!session) {
    throw fail('Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.', 'UNAUTHORIZED');
  }
  return session;
}

export function isPreferLocal() {
  try {
    return localStorage.getItem('dm_prefer_local') === '1';
  } catch (e) {
    return false;
  }
}

export function setPreferLocal(enabled) {
  try {
    if (enabled) {
      localStorage.setItem('dm_prefer_local', '1');
      document.cookie = 'prefer_local=1; path=/; max-age=31536000; SameSite=Lax';
    } else {
      localStorage.removeItem('dm_prefer_local');
      document.cookie = 'prefer_local=0; path=/; max-age=0; SameSite=Lax';
    }
  } catch (e) {}
  window.dispatchEvent(new CustomEvent('dm:prefer-local-changed', { detail: { preferLocal: !!enabled } }));
}

export async function checkDatabaseHealth() {
  const start = performance.now();
  try {
    const headers = new Headers();
    if (isPreferLocal()) {
      headers.set('X-Prefer-Local', '1');
    }
    const res = await fetch('api/health.php', {
      headers,
      credentials: 'same-origin',
      cache: 'no-store'
    });
    const data = await res.json();
    data.client_ping_ms = Math.round(performance.now() - start);
    return data;
  } catch (e) {
    return {
      status: 'offline',
      engine: 'none',
      engine_name: 'Mất kết nối máy chủ',
      breaker_active: false,
      prefer_local: isPreferLocal(),
      latency_ms: null,
      client_ping_ms: Math.round(performance.now() - start),
      error: e.message
    };
  }
}

function broadcastDbStatus(res, fallback = false, reason = '') {
  try {
    const engineHeader = res ? (res.headers.get('x-db-engine') || '') : '';
    const breaker = res ? (res.headers.get('x-db-breaker') === 'tripped') : false;
    const isSqlite = fallback || engineHeader.toLowerCase().includes('sqlite');
    window.dispatchEvent(new CustomEvent('dm:db-mode', {
      detail: {
        engine: isSqlite ? 'sqlite' : 'pgsql',
        engineHeader: engineHeader || (fallback ? 'SQLite (Dự phòng mạng yếu)' : 'PostgreSQL Live'),
        isBreaker: breaker,
        fallback,
        reason,
        preferLocal: isPreferLocal()
      }
    }));
  } catch (e) {}
}

async function request(url, options = {}) {
  const session = sessionUser();
  const method = (options.method || 'GET').toUpperCase();
  const isGet = method === 'GET';
  const cacheKey = `dm_cache_${url}`;
  const preferLocal = isPreferLocal();

  // 1. Offline immediate response for GET if browser is completely disconnected
  if (isGet && typeof navigator !== 'undefined' && !navigator.onLine) {
    try {
      const cached = localStorage.getItem(cacheKey);
      if (cached) {
        broadcastDbStatus(null, true, 'offline_cache');
        return JSON.parse(cached);
      }
    } catch (e) {}
  }

  const makeHeaders = (forceLocal = false) => {
    const h = new Headers(options.headers || {});
    if (session.userId) {
      h.set('X-User-Id', session.userId);
    }
    if (session.sessionToken) {
      h.set('Authorization', `Bearer ${session.sessionToken}`);
    }
    if (preferLocal || forceLocal) {
      h.set('X-Prefer-Local', '1');
    }
    if (options.body && !(options.body instanceof FormData) && !h.has('Content-Type')) {
      h.set('Content-Type', 'application/json');
    }
    return h;
  };

  // 2. Timeout 10s: nếu quá 10s thì tự động chuyển sang đọc CSDL cục bộ (SQLite)
  const timeoutMs = isGet && !preferLocal ? 10000 : 15000;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  let res;
  try {
    res = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: makeHeaders(false),
      signal: options.signal || controller.signal
    });
  } catch (err) {
    const isTimeout = err.name === 'AbortError';

    // Nếu quá 10s hoặc lỗi mạng: Tự động chuyển sang đọc CSDL cục bộ SQLite ngay lập tức!
    if (isGet && !preferLocal) {
      try {
        const localRes = await fetch(url, {
          credentials: 'same-origin',
          ...options,
          headers: makeHeaders(true)
        });
        if (localRes.ok) {
          const isJson = (localRes.headers.get('content-type') || '').includes('application/json');
          const data = isJson ? await localRes.json() : await localRes.text();
          if (isJson) {
            try { localStorage.setItem(cacheKey, JSON.stringify(data)); } catch (e) {}
          }
          // Quá 10s: Tự động ghi nhớ chuyển sang CSDL cục bộ để các lần đọc tiếp theo không phải đợi nữa
          if (isTimeout) {
            setPreferLocal(true);
          }
          broadcastDbStatus(localRes, true, isTimeout ? 'timeout_10s_auto_switched' : 'network_timeout_failover');
          return data;
        }
      } catch (fallbackErr) {
        // Local retry also failed (e.g. server completely unreachable)
      }
    }

    // Try localStorage SWR cache if available
    if (isGet) {
      try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
          if (isTimeout) setPreferLocal(true);
          broadcastDbStatus(null, true, 'network_error_cache');
          return JSON.parse(cached);
        }
      } catch (e) {}
    }

    throw fail(
      isTimeout ? 'Kết nối mạng quá 10s — đã tự động chuyển sang đọc CSDL cục bộ.' : 'Lỗi kết nối máy chủ.',
      isTimeout ? 'NETWORK_TIMEOUT' : 'NETWORK_ERROR'
    );
  } finally {
    clearTimeout(timer);
  }

  const isJson = (res.headers.get('content-type') || '').includes('application/json');
  const data = isJson ? await res.json() : await res.text();

  if (!res.ok) {
    const errorMsg = (typeof data === 'object' && data.error) ? data.error : 'Lỗi kết nối máy chủ.';
    const errorCode = (typeof data === 'object' && data.code) ? data.code : 'HTTP_ERROR';
    const extra = typeof data === 'object' ? data : {};
    throw fail(errorMsg, errorCode, extra);
  }

  // Cache successful GET data
  if (isGet && isJson) {
    try {
      localStorage.setItem(cacheKey, JSON.stringify(data));
    } catch (e) {}
  }

  broadcastDbStatus(res);
  return data;
}

export async function validateImageFile(file) {
  if (!file) throw fail('Vui lòng chọn một tệp ảnh.', 'VALIDATION');
  if (file.size > MAX_IMAGE_BYTES) {
    throw fail('Ảnh vượt quá dung lượng tối đa 10 MB.', 'VALIDATION');
  }

  const allowedTypes = new Set(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp']);
  const mime = file.type;
  const isSvgByName = file.name.toLowerCase().endsWith('.svg');

  if (!allowedTypes.has(mime) && !isSvgByName) {
    throw fail('Định dạng ảnh không được hỗ trợ. Chỉ chấp nhận PNG, JPEG, SVG hoặc WebP.', 'VALIDATION');
  }

  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(fail('Không đọc được tệp ảnh.', 'FILE_READ'));
    reader.onload = () => {
      const img = new Image();
      img.onerror = () => reject(fail('Tệp tải lên không phải ảnh hợp lệ.', 'DECODE_FAILED'));
      img.onload = () => {
        resolve({
          name: file.name,
          mime: mime || 'image/png',
          size: file.size,
          width: img.naturalWidth || 1000,
          height: img.naturalHeight || 500
        });
      };
      img.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
}

async function getDashboard(params = {}) {
  const query = new URLSearchParams(params).toString();
  return request(`api/dashboard.php${query ? '?' + query : ''}`);
}

async function listProjects(params = null) {
  if (!params) {
    return request('api/projects.php');
  }
  const query = new URLSearchParams(params).toString();
  return request(`api/projects.php${query ? '?' + query : ''}`);
}

async function getProject(projectId) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}`);
}

async function createProject({ name, description, category }) {
  return request('api/projects.php?action=create', {
    method: 'POST',
    body: JSON.stringify({ name, description, category })
  });
}

async function updateProject(projectId, data) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=update`, {
    method: 'POST',
    body: JSON.stringify(data)
  });
}

async function deleteProject(projectId, permanent = false) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=${permanent ? 'permanent_delete' : 'delete'}`, {
    method: 'POST',
    body: JSON.stringify({ permanent })
  });
}

async function listTrashProjects() {
  return request('api/projects.php?trash=1');
}

async function restoreProject(projectId) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=restore`, {
    method: 'POST'
  });
}


async function listScreens(projectId, params = null) {
  if (!params) {
    return request(`api/screens.php?project_id=${encodeURIComponent(projectId)}`);
  }
  const query = new URLSearchParams({ project_id: projectId, ...params }).toString();
  return request(`api/screens.php?${query}`);
}

async function getScreen(screenId) {
  return request(`api/screens.php?id=${encodeURIComponent(screenId)}`);
}

async function listVersions(screenId) {
  return request(`api/versions.php?screen_id=${encodeURIComponent(screenId)}`);
}

async function getVersion(versionId) {
  return request(`api/versions.php?id=${encodeURIComponent(versionId)}`);
}

async function createScreenFromFile(projectId, { name, file }) {
  await validateImageFile(file);
  const formData = new FormData();
  formData.append('projectId', projectId);
  formData.append('name', name);
  formData.append('file', file);

  return request('api/screens.php?action=create', {
    method: 'POST',
    body: formData
  });
}

async function addVersionFromFile(screenId, file) {
  await validateImageFile(file);
  const formData = new FormData();
  formData.append('screenId', screenId);
  formData.append('file', file);

  return request('api/screens.php?action=add_version', {
    method: 'POST',
    body: formData
  });
}

async function resolveImageUrl(imageKey) {
  if (!imageKey) throw fail('Phiên bản chưa có ảnh.', 'NOT_FOUND');
  if (urlCache.has(imageKey)) return urlCache.get(imageKey);

  if (String(imageKey).startsWith('seed:')) {
    // Có thể dùng trực tiếp API PHP hoặc data URI nội bộ
    const url = `api/image.php?key=${encodeURIComponent(imageKey)}`;
    urlCache.set(imageKey, url);
    return url;
  }

  const url = `api/image.php?key=${encodeURIComponent(imageKey)}`;
  urlCache.set(imageKey, url);
  return url;
}

async function listAnnotations(versionId, params = null) {
  const query = new URLSearchParams({ version_id: versionId });
  if (params && typeof params === 'object') {
    query.set('paginated', '1');
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== null && v !== '') {
        query.set(k, String(v));
      }
    }
  }
  return request(`api/annotations.php?${query.toString()}`);
}

async function getReplies(annotationId) {
  return request(`api/annotations.php?id=${encodeURIComponent(annotationId)}&action=replies`);
}

async function createAnnotation(input) {
  return request('api/annotations.php?action=create', {
    method: 'POST',
    body: JSON.stringify(input)
  });
}

async function deleteAnnotation(id) {
  return request(`api/annotations.php?action=delete&id=${encodeURIComponent(id)}`, {
    method: 'POST',
    body: JSON.stringify({ id })
  });
}

async function updateAnnotationBody(id, { body, revision, simulateConflict = false }) {
  return request(`api/annotations.php?id=${encodeURIComponent(id)}&action=update_body`, {
    method: 'POST',
    body: JSON.stringify({ body, revision, simulateConflict })
  });
}

async function addReply(id, body, attachmentUrl = null) {
  return request(`api/annotations.php?id=${encodeURIComponent(id)}&action=reply`, {
    method: 'POST',
    body: JSON.stringify({ body, attachmentUrl })
  });
}

async function toggleReaction(annotationId, replyId = null, emoji = '👍') {
  return request(`api/annotations.php?id=${encodeURIComponent(annotationId)}&action=react`, {
    method: 'POST',
    body: JSON.stringify({ replyId, emoji })
  });
}

async function uploadAttachment(file) {
  const data = new FormData();
  data.append('file', file);
  return request('api/annotations.php?action=upload_attachment', {
    method: 'POST',
    body: data
  });
}

async function migrateUnresolvedAnnotations(targetVersionId, sourceVersionId = null) {
  return request('api/annotations.php?action=migrate_unresolved', {
    method: 'POST',
    body: JSON.stringify({ targetVersionId, sourceVersionId })
  });
}

async function reorderScreens(projectId, order) {
  return request('api/screens.php?action=reorder', {
    method: 'POST',
    body: JSON.stringify({ projectId, order })
  });
}

async function renameScreen(screenId, name) {
  return request('api/screens.php?action=rename', {
    method: 'POST',
    body: JSON.stringify({ id: screenId, name })
  });
}

async function deleteScreen(screenId) {
  return request('api/screens.php?action=delete', {
    method: 'POST',
    body: JSON.stringify({ id: screenId })
  });
}

async function setAnnotationStatus(id, status) {
  return request(`api/annotations.php?id=${encodeURIComponent(id)}&action=status`, {
    method: 'POST',
    body: JSON.stringify({ status })
  });
}

async function getStatusHistory(id) {
  return request(`api/annotations.php?id=${encodeURIComponent(id)}&action=history`);
}

async function setCheckNote(id, note) {
  return request(`api/annotations.php?id=${encodeURIComponent(id)}&action=check_note`, {
    method: 'POST',
    body: JSON.stringify({ note })
  });
}

async function listMembers(projectId, params = null) {
  if (!params) {
    return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=members`);
  }
  const query = new URLSearchParams({ id: projectId, action: 'members', ...params }).toString();
  return request(`api/projects.php?${query}`);
}

async function listInviteCandidates(projectId, query = '') {
  const q = query ? `&q=${encodeURIComponent(query)}` : '';
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=invite_candidates${q}`);
}

async function inviteMember(projectId, { userId, username, role }) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=invite`, {
    method: 'POST',
    body: JSON.stringify({ userId, username, role })
  });
}

async function updateMemberRole(projectId, userId, role) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=update_role`, {
    method: 'POST',
    body: JSON.stringify({ userId, role })
  });
}

async function removeMember(projectId, userId) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=remove_member`, {
    method: 'POST',
    body: JSON.stringify({ userId })
  });
}

async function listActivities(projectId, params = null) {
  if (!params) {
    return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=activities`);
  }
  const query = new URLSearchParams({ id: projectId, action: 'activities', ...params }).toString();
  return request(`api/projects.php?${query}`);
}

async function listFeedback(projectId) {
  const query = projectId ? `?project_id=${encodeURIComponent(projectId)}` : '';
  return request(`api/feedback.php${query}`);
}

async function listNotifications(params = {}) {
  const query = new URLSearchParams(params).toString();
  return request('api/notifications.php' + (query ? '?' + query : ''));
}

async function markNotificationRead(id) {
  return request('api/notifications.php?action=mark_read', {
    method: 'POST',
    body: JSON.stringify({ id })
  });
}

async function markAllNotificationsRead() {
  return request('api/notifications.php?action=mark_all_read', {
    method: 'POST',
    body: JSON.stringify({})
  });
}

async function getMyRole(projectId) {
  return request(`api/projects.php?id=${encodeURIComponent(projectId)}&action=role`);
}

async function resetDemo() {
  urlCache.clear();
  return request('api/reset.php', { method: 'POST' });
}

async function getUnverifiedUsers() {
  return request('api/auth.php?action=unverified_users');
}

async function approveUser(userId, role = 'reviewer') {
  return request('api/auth.php?action=approve_user', {
    method: 'POST',
    body: JSON.stringify({ userId, role })
  });
}

async function rejectUser(userId) {
  return request('api/auth.php?action=reject_user', {
    method: 'POST',
    body: JSON.stringify({ userId })
  });
}

async function listAllUsers(params = null) {
  if (!params) {
    return request('api/auth.php?action=list_all_users');
  }
  const searchParams = new URLSearchParams();
  searchParams.set('action', 'list_all_users');
  searchParams.set('paginate', '1');
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') {
      searchParams.set(k, String(v));
    }
  }
  return request(`api/auth.php?${searchParams.toString()}`);
}

async function getUserDetail(userId) {
  return request(`api/auth.php?action=user_detail&userId=${encodeURIComponent(userId)}`);
}

async function updateSystemUserRole(userId, role) {
  return request('api/auth.php?action=update_user_role', {
    method: 'POST',
    body: JSON.stringify({ userId, role })
  });
}

async function deleteSystemUser(userId) {
  return request('api/auth.php?action=delete_user', {
    method: 'POST',
    body: JSON.stringify({ userId })
  });
}

async function adminCreateUser({ username, name, email, password, systemRole, title, role }) {
  return request('api/auth.php?action=admin_create_user', {
    method: 'POST',
    body: JSON.stringify({ username, name, email, password, systemRole, title, role })
  });
}

async function resetUserPassword(userId, newPassword) {
  return request('api/auth.php?action=reset_user_password', {
    method: 'POST',
    body: JSON.stringify({ userId, newPassword })
  });
}

async function lockUser(userId, reason = '') {
  return request('api/auth.php?action=lock_user', {
    method: 'POST',
    body: JSON.stringify({ userId, reason })
  });
}

async function unlockUser(userId, reason = '') {
  return request('api/auth.php?action=unlock_user', {
    method: 'POST',
    body: JSON.stringify({ userId, reason })
  });
}

async function getUserStatusHistory(userId) {
  return request(`api/auth.php?action=user_status_history&userId=${encodeURIComponent(userId)}`);
}

async function updateUser(userId, { name, email, title, role, systemRole }) {
  return request('api/auth.php?action=update_user', {
    method: 'POST',
    body: JSON.stringify({ userId, name, email, title, role, systemRole })
  });
}

async function changePassword({ currentPassword, newPassword, confirmPassword }) {
  return request('api/auth.php?action=change_password', {
    method: 'POST',
    body: JSON.stringify({ currentPassword, newPassword, confirmPassword })
  });
}

export const api = {
  getDashboard,
  listProjects,
  listTrashProjects,
  restoreProject,
  getProject,
  createProject,
  updateProject,
  deleteProject,
  changePassword,

  listScreens,
  getScreen,
  listVersions,
  getVersion,
  createScreenFromFile,
  addVersionFromFile,
  resolveImageUrl,
  validateImageFile,
  listAnnotations,
  getReplies,
  createAnnotation,
  deleteAnnotation,
  updateAnnotationBody,
  toggleReaction,
  addReply,
  uploadAttachment,
  migrateUnresolvedAnnotations,
  reorderScreens,
  renameScreen,
  deleteScreen,
  setAnnotationStatus,
  getStatusHistory,
  setCheckNote,
  listMembers,
  listInviteCandidates,
  inviteMember,
  updateMemberRole,
  removeMember,
  listActivities,
  listFeedback,
  listNotifications,
  markNotificationRead,
  markAllNotificationsRead,
  getMyRole,
  resetDemo,
  getUnverifiedUsers,
  approveUser,
  rejectUser,
  listAllUsers,
  getUserDetail,
  updateSystemUserRole,
  deleteSystemUser,
  adminCreateUser,
  updateUser,
  resetUserPassword,
  lockUser,
  unlockUser,
  getUserStatusHistory,
  isPreferLocal,
  setPreferLocal,
  checkDatabaseHealth
};
