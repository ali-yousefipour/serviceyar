CREATE TABLE IF NOT EXISTS agency_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(150) NOT NULL,
    setting_value TEXT NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_agency_setting (agency_id, setting_key),
    INDEX idx_agency_settings_agency (agency_id),
    CONSTRAINT fk_agency_settings_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    date_jalali VARCHAR(10) NOT NULL,
    date_gregorian DATE NULL,
    holiday_type VARCHAR(30) NOT NULL DEFAULT 'holiday',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_holidays_agency_date (agency_id, date_gregorian),
    CONSTRAINT fk_holidays_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name,title) VALUES
('agencies.view','مشاهده سازمان'),('agencies.manage','مدیریت سازمان'),
('settings.view','مشاهده تنظیمات'),('settings.manage','مدیریت تنظیمات'),
('holidays.view','مشاهده تعطیلات'),('holidays.manage','مدیریت تعطیلات'),
('messages.manage','مدیریت پیام‌ها'),('finance.view','مشاهده مالی'),('finance.manage','مدیریت مالی'),
('monitoring.view','مشاهده پایش'),('audit.view','مشاهده رویدادها');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='super_admin';
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.name='agency_admin' AND p.name IN ('agencies.view','agencies.manage','settings.view','settings.manage','holidays.view','holidays.manage','messages.manage','finance.view','finance.manage','monitoring.view');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.name='operator' AND p.name IN ('agencies.view','settings.view','holidays.view','messages.manage','monitoring.view');
