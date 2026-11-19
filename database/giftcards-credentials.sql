-- Separate, additive migration: keep 001-giftcards immutable.
CREATE TABLE IF NOT EXISTS pg_merchant_credentials (
 scope_id VARCHAR(36) PRIMARY KEY,
 merchant_id CHAR(36) NOT NULL UNIQUE, site_id CHAR(36) NOT NULL UNIQUE,
 recipient_login VARCHAR(140) NOT NULL UNIQUE, api_key_ciphertext TEXT NOT NULL,
 verified_at DATETIME NOT NULL, updated_by BIGINT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(updated_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
