-- Idempotent grant for the school-service companies' actual manager role.
-- Authorization::requireAgencyAccess remains mandatory for tenant-scoped actions.
INSERT IGNORE INTO permissions(name,title) VALUES
 ('users.view','مشاهده کاربران شرکت'),
 ('users.manage','مدیریت کاربران شرکت');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id
 FROM roles r CROSS JOIN permissions p
 WHERE r.name IN ('school_company_manager','agency_admin','super_admin')
   AND p.name IN ('users.view','users.manage');
