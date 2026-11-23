-- Public branding can change without revising contracts or approved programme terms.
CREATE TABLE IF NOT EXISTS pg_issuer_presentation (
 issuer_id CHAR(36) PRIMARY KEY,
 brand_name VARCHAR(80) NOT NULL,
 updated_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id),
 FOREIGN KEY(updated_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
