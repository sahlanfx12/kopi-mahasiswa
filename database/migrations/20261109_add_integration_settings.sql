-- Kopi Mahasiswa — Konfigurasi Integrasi (xKiro & Fonnte)
-- Migrasi ADDITIVE: hanya membuat dua tabel baru. Tidak mengubah, menghapus,
-- atau membaca tabel bisnis, OTP (customer_phone_otps, customer_otp_rate_limits), users, atau RBAC.
-- Aman dijalankan berulang (CREATE TABLE IF NOT EXISTS).
--
-- Jalankan dengan schema target terpilih, contoh:
--   mysql -u root kopi_mahasiswa_qa < database/migrations/20261109_add_integration_settings.sql
--
-- Rahasia (API Key xKiro, Device Token Fonnte) disimpan sebagai ciphertext AES-256-GCM
-- dengan format "v1.<base64(nonce|tag|ciphertext)>". Kunci enkripsi TIDAK disimpan di database;
-- kunci berada di environment server INTEGRATION_ENCRYPTION_KEY.

CREATE TABLE IF NOT EXISTS integration_settings (
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    is_secret     TINYINT(1) NOT NULL DEFAULT 0,
    updated_by    INT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_fonnte_devices (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_name        VARCHAR(100) NOT NULL,
    sender_number      VARCHAR(20) NOT NULL,
    token_ciphertext   TEXT NULL,
    token_hint         VARCHAR(8) NULL,
    is_enabled         TINYINT(1) NOT NULL DEFAULT 1,
    last_check_status  VARCHAR(20) NULL,
    last_check_device  VARCHAR(20) NULL,
    last_check_package VARCHAR(50) NULL,
    last_check_quota   VARCHAR(30) NULL,
    last_check_expired VARCHAR(50) NULL,
    last_check_at      DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_fonnte_sender (sender_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('integration_settings', 'integration_fonnte_devices')
ORDER BY TABLE_NAME;
