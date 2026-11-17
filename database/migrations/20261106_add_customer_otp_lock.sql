-- Add persistent OTP lockout fields without dropping or rewriting user data.
-- Run with the target schema selected (for example, mysql --database=kopi_mahasiswa_restore_check).
-- The allowlist prevents accidental execution against an unrelated schema.

SET @otp_lock_schema = DATABASE();
SET @otp_lock_users_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @otp_lock_schema
      AND TABLE_NAME = 'users'
      AND TABLE_TYPE = 'BASE TABLE'
);

SET @otp_lock_has_failed_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @otp_lock_schema
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'failed_otp_attempts'
);
SET @otp_lock_sql = IF(
    @otp_lock_schema IN ('kopi_mahasiswa', 'kopi_mahasiswa_restore_check', 'kopi_mahasiswa_otp_test')
    AND @otp_lock_users_exists = 1,
    IF(@otp_lock_has_failed_column = 0,
       'ALTER TABLE users ADD COLUMN failed_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0',
       'SELECT ''failed_otp_attempts already exists'' AS migration_status'),
    'ALTER TABLE __otp_lock_migration_schema_guard_failed__ ADD COLUMN failed_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE otp_lock_stmt FROM @otp_lock_sql;
EXECUTE otp_lock_stmt;
DEALLOCATE PREPARE otp_lock_stmt;

SET @otp_lock_has_until_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @otp_lock_schema
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'otp_locked_until'
);
SET @otp_lock_sql = IF(
    @otp_lock_schema IN ('kopi_mahasiswa', 'kopi_mahasiswa_restore_check', 'kopi_mahasiswa_otp_test')
    AND @otp_lock_users_exists = 1,
    IF(@otp_lock_has_until_column = 0,
       'ALTER TABLE users ADD COLUMN otp_locked_until DATETIME NULL DEFAULT NULL',
       'SELECT ''otp_locked_until already exists'' AS migration_status'),
    'ALTER TABLE __otp_lock_migration_schema_guard_failed__ ADD COLUMN otp_locked_until DATETIME NULL DEFAULT NULL'
);
PREPARE otp_lock_stmt FROM @otp_lock_sql;
EXECUTE otp_lock_stmt;
DEALLOCATE PREPARE otp_lock_stmt;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME IN ('failed_otp_attempts', 'otp_locked_until')
ORDER BY COLUMN_NAME;
