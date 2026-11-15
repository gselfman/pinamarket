-- Additive operational administration. Existing financial records are preserved.
CREATE TABLE IF NOT EXISTS pg_runtime_settings (
 name VARCHAR(60) PRIMARY KEY, value_json MEDIUMTEXT NOT NULL, updated_by BIGINT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(updated_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_staff_roles (
 client_id BIGINT UNSIGNED PRIMARY KEY, role VARCHAR(20) NOT NULL, updated_by BIGINT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id), FOREIGN KEY(updated_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_client_controls (
 client_id BIGINT UNSIGNED PRIMARY KEY, sales_blocked TINYINT NOT NULL DEFAULT 0, reason TEXT NOT NULL, updated_by BIGINT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id), FOREIGN KEY(updated_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_admin_notes (
 id CHAR(36) PRIMARY KEY, object_kind VARCHAR(30) NOT NULL, object_ref VARCHAR(100) NOT NULL, body TEXT NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(actor_id) REFERENCES pm_clients(id), INDEX pg_notes_object(object_kind,object_ref,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_bank_events (
 id CHAR(36) PRIMARY KEY, order_id BIGINT UNSIGNED NULL, reference VARCHAR(150) NOT NULL UNIQUE, amount_minor BIGINT NOT NULL,
 bank_date DATE NOT NULL, comment TEXT NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(order_id) REFERENCES pg_orders(id), FOREIGN KEY(actor_id) REFERENCES pm_clients(id), INDEX pg_bank_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_settlement_events (
 id CHAR(36) PRIMARY KEY, settlement_id CHAR(36) NOT NULL, kind VARCHAR(30) NOT NULL, amount_minor BIGINT NOT NULL DEFAULT 0,
 reference VARCHAR(200) NOT NULL, body TEXT NOT NULL, request_key CHAR(36) NOT NULL UNIQUE, actor_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(settlement_id) REFERENCES pg_settlements(id), FOREIGN KEY(actor_id) REFERENCES pm_clients(id), INDEX pg_settlement_history(settlement_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_support_cases (
 id CHAR(36) PRIMARY KEY, issuer_id CHAR(36) NULL, client_id BIGINT UNSIGNED NULL, object_ref VARCHAR(100) NOT NULL, topic VARCHAR(200) NOT NULL,
 body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'open', due_at DATETIME NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id), FOREIGN KEY(client_id) REFERENCES pm_clients(id), FOREIGN KEY(created_by) REFERENCES pm_clients(id), INDEX pg_cases_due(status,due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_case_files (
 case_id CHAR(36) NOT NULL, document_id CHAR(36) NOT NULL UNIQUE, PRIMARY KEY(case_id,document_id),
 FOREIGN KEY(case_id) REFERENCES pg_support_cases(id), FOREIGN KEY(document_id) REFERENCES pg_documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_settlement_locks (
 activation_id CHAR(36) PRIMARY KEY, settlement_id CHAR(36) NOT NULL,
 FOREIGN KEY(activation_id) REFERENCES pg_activations(id), FOREIGN KEY(settlement_id) REFERENCES pg_settlements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO pg_settlement_locks(activation_id,settlement_id)
 SELECT si.activation_id,si.settlement_id FROM pg_settlement_items si JOIN pg_settlements s ON s.id=si.settlement_id WHERE s.status<>'cancelled';
CREATE TABLE IF NOT EXISTS pg_files (
 id CHAR(36) PRIMARY KEY, object_kind VARCHAR(24) NOT NULL, object_id VARCHAR(100) NOT NULL,
 issuer_id CHAR(36) NULL, client_id BIGINT UNSIGNED NULL, storage_name VARCHAR(100) NOT NULL UNIQUE,
 original_name VARCHAR(180) NOT NULL, mime VARCHAR(100) NOT NULL, size_bytes BIGINT NOT NULL, sha256 CHAR(64) NOT NULL,
 uploaded_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id), FOREIGN KEY(client_id) REFERENCES pm_clients(id), FOREIGN KEY(uploaded_by) REFERENCES pm_clients(id),
 INDEX pg_files_object(object_kind,object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_case_messages (
 id CHAR(36) PRIMARY KEY, case_id CHAR(36) NOT NULL, body TEXT NOT NULL, author_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(case_id) REFERENCES pg_support_cases(id), FOREIGN KEY(author_id) REFERENCES pm_clients(id), INDEX pg_case_message_order(case_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_contacts (
 client_id BIGINT UNSIGNED PRIMARY KEY, full_name VARCHAR(180) NOT NULL, phone VARCHAR(60) NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
