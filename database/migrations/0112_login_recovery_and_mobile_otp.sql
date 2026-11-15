-- Extend one-time challenges for account recovery. Safe to re-run on databases
-- where SmsLogin::prepareSchema() already created the purpose column.
SET @has_sms_purpose = (
 SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sms_login_challenges' AND COLUMN_NAME='purpose'
);
SET @sql = IF(@has_sms_purpose=0,
 'ALTER TABLE sms_login_challenges ADD COLUMN purpose ENUM(''login'',''password_reset'') NOT NULL DEFAULT ''login'' AFTER user_id',
 'SELECT 1');
PREPARE serviceyar_stmt FROM @sql;
EXECUTE serviceyar_stmt;
DEALLOCATE PREPARE serviceyar_stmt;

-- Bale already distinguishes link and login challenges; add password reset.
ALTER TABLE bale_auth_challenges
 MODIFY COLUMN purpose ENUM('link','login','password_reset') NOT NULL;

-- The mobile OTP method is controlled by platform_settings.login_method_mobile.
-- No new user data is required: it uses the existing users.mobile profile field.
