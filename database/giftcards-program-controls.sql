-- New sales approvals do not rewrite existing orders or signed documents.
CREATE TABLE IF NOT EXISTS pg_bank_approvals (
 issuer_id CHAR(36) PRIMARY KEY,
 terms_hash CHAR(64) NOT NULL,
 reference VARCHAR(250) NOT NULL,
 approval_date DATE NOT NULL,
 evidence_id CHAR(36) NOT NULL,
 screening_note TEXT NOT NULL,
 reviewed_at DATETIME NOT NULL,
 reviewed_by BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'approved',
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id),
 FOREIGN KEY(evidence_id) REFERENCES pg_files(id),
 FOREIGN KEY(reviewed_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
