-- Explicit evidence of administrator review for both signing methods.
-- Existing signed documents and their approval history are not rewritten.
CREATE TABLE IF NOT EXISTS pg_contract_reviews (
 document_id CHAR(36) PRIMARY KEY,
 signing_method VARCHAR(20) NOT NULL,
 content_hash CHAR(64) NOT NULL,
 reviewed_by BIGINT UNSIGNED NOT NULL,
 reviewed_at DATETIME NOT NULL,
 FOREIGN KEY(document_id) REFERENCES pg_documents(id),
 FOREIGN KEY(reviewed_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
