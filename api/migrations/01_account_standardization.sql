-- Migration 01: Account Standardization & 4 Roles
-- Applicable to both PostgreSQL (Supabase) and SQLite

-- 1. Bổ sung cột system_role cho bảng users nếu chưa có
ALTER TABLE users ADD COLUMN IF NOT EXISTS system_role TEXT DEFAULT 'user';

-- 2. Bổ sung cột status cho bảng users nếu chưa có
ALTER TABLE users ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';

-- 3. Cập nhật dữ liệu người dùng hiện có an toàn
UPDATE users SET status = 'active' WHERE (is_verified = 1 OR is_verified IS NULL) AND (role != 'pending' OR role IS NULL);
UPDATE users SET status = 'pending' WHERE role = 'pending' OR is_verified = 0;
UPDATE users SET system_role = 'user' WHERE system_role IS NULL OR system_role = '';

-- 4. Bảng lưu vết lịch sử khóa / mở khóa tài khoản
CREATE TABLE IF NOT EXISTS user_status_history (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    admin_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    reason TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_user_status_history_user ON user_status_history(user_id, created_at DESC);
