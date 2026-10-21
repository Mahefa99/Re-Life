-- Appliquer avant de déployer la branche de sécurité sur une base existante.
USE rewear;
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1;
CREATE TABLE IF NOT EXISTS auth_throttles (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_auth_expiry (expires_at)
) ENGINE=InnoDB;
