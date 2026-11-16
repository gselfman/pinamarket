-- Actual issuer declarations are retained separately from the platform result.
-- Historical activations deliberately have no invented purchase declaration.
CREATE TABLE IF NOT EXISTS pg_activation_purchases (
 activation_id CHAR(36) PRIMARY KEY,
 subject VARCHAR(500) NOT NULL,
 country VARCHAR(200) NOT NULL,
 place VARCHAR(500) NOT NULL,
 performance VARCHAR(30) NOT NULL,
 document_reference VARCHAR(500) NOT NULL,
 holder_name VARCHAR(180) NOT NULL,
 recorded_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(activation_id) REFERENCES pg_activations(id),
 FOREIGN KEY(recorded_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
