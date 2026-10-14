-- Repair access for the pre-provisioned administrator in databases imported from SQL dumps.
-- Safe to execute repeatedly; does not create users or grant roles to arbitrary accounts.
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
JOIN roles r ON r.name = 'super_admin'
WHERE u.username = 'admin' AND u.is_active = 1;
