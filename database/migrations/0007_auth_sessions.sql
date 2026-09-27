CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    csrf_token CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sessions_user_active (user_id, revoked_at, expires_at),
    INDEX idx_sessions_expiry (expires_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (name, title) VALUES
('super_admin', 'مدیر سامانه'),
('agency_admin', 'مدیر سازمان'),
('operator', 'کاربر عملیاتی');

INSERT IGNORE INTO permissions (name, title) VALUES
('dashboard.view', 'مشاهده داشبورد'),
('users.view', 'مشاهده کاربران'),
('users.manage', 'مدیریت کاربران'),
('schools.view', 'مشاهده مدارس'),
('schools.manage', 'مدیریت مدارس'),
('students.view', 'مشاهده دانش‌آموزان'),
('students.manage', 'مدیریت دانش‌آموزان'),
('drivers.view', 'مشاهده رانندگان'),
('drivers.manage', 'مدیریت رانندگان'),
('services.view', 'مشاهده سرویس‌ها'),
('services.manage', 'مدیریت سرویس‌ها'),
('reports.view', 'مشاهده گزارش‌ها');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name='super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name='agency_admin' AND p.name IN (
'dashboard.view','users.view','schools.view','schools.manage','students.view','students.manage',
'drivers.view','drivers.manage','services.view','services.manage','reports.view'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name='operator' AND p.name IN (
'dashboard.view','schools.view','students.view','drivers.view','services.view'
);
