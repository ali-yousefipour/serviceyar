-- Agency managers may administer users belonging to their own company.
INSERT IGNORE INTO permissions(name,title) VALUES
 ('users.view','مشاهده کاربران شرکت'),
 ('users.manage','مدیریت کاربران شرکت');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r JOIN permissions p
 WHERE r.name='agency_admin' AND p.name IN ('users.view','users.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r JOIN permissions p WHERE r.name='super_admin' AND p.name IN ('users.view','users.manage');
