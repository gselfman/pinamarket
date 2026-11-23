-- Supplier companies use separate points within the common Pina merchant.
-- Historical credentials remain intact for previously created operations.
CREATE TABLE IF NOT EXISTS pg_vdar_supplier_points (
 issuer_id CHAR(36) PRIMARY KEY,
 site_id CHAR(36) NOT NULL UNIQUE,
 site_login VARCHAR(64) NOT NULL UNIQUE,
 recipient_login VARCHAR(140) NOT NULL UNIQUE,
 verified_at DATETIME NOT NULL,
 updated_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id),
 FOREIGN KEY(updated_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
