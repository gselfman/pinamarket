-- Programme approval is independent of document signing and never approves a file.
CREATE TABLE IF NOT EXISTS pg_program_confirmations (
 issuer_id CHAR(36) PRIMARY KEY,
 content_hash CHAR(64) NOT NULL,
 confirmed_by BIGINT UNSIGNED NOT NULL,
 confirmed_at DATETIME NOT NULL,
 contract_warning TEXT NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id),
 FOREIGN KEY(confirmed_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
