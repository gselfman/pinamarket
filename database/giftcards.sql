-- Additive migration. Never rewrite the legacy pm_* balances or operations.
-- Applied by deploy/giftcards-migrate.php under an advisory lock, after a verified backup.
CREATE TABLE IF NOT EXISTS pg_schema_migrations(version VARCHAR(60) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_issuers (
 id CHAR(36) PRIMARY KEY, name VARCHAR(180) NOT NULL, country CHAR(2) NOT NULL DEFAULT 'KG',
 company_json MEDIUMTEXT NOT NULL, program_json MEDIUMTEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft',
 vdar_site_id CHAR(36) NULL, recipient_login VARCHAR(140) NULL, contract_number VARCHAR(60) NULL UNIQUE,
 contract_version CHAR(64) NULL, contract_hash CHAR(64) NULL, contract_status VARCHAR(24) NOT NULL DEFAULT 'draft',
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_issuer_members (
 issuer_id CHAR(36) NOT NULL, client_id BIGINT UNSIGNED NOT NULL, role VARCHAR(20) NOT NULL,
 PRIMARY KEY(issuer_id,client_id), FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id), FOREIGN KEY(client_id) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_profiles (
 client_id BIGINT UNSIGNED PRIMARY KEY, email_verified_at DATETIME NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_login_links (
 token_hash CHAR(64) PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id), INDEX pg_links_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_orders (
 id BIGINT UNSIGNED PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL, issuer_id CHAR(36) NOT NULL,
 total_minor BIGINT NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'RUB', payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
 request_key CHAR(36) NOT NULL, request_hash CHAR(64) NOT NULL, offer_version VARCHAR(60) NOT NULL, offer_hash CHAR(64) NOT NULL,
 snapshot_json MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY pg_order_request(client_id,request_key), FOREIGN KEY(client_id) REFERENCES pm_clients(id), FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_bank_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL UNIQUE,
 reference VARCHAR(150) NOT NULL UNIQUE, amount_minor BIGINT NOT NULL, confirmed_by BIGINT UNSIGNED NOT NULL, confirmed_at DATETIME NOT NULL,
 FOREIGN KEY(order_id) REFERENCES pg_orders(id), FOREIGN KEY(confirmed_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_bank_references (
 reference VARCHAR(150) PRIMARY KEY, event_kind VARCHAR(30) NOT NULL, object_id VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO pg_bank_references(reference,event_kind,object_id,created_at)
 SELECT bank_reference,'legacy',CAST(number AS CHAR),updated_at FROM pm_operations WHERE bank_reference IS NOT NULL;
CREATE TABLE IF NOT EXISTS pg_cards (
 id CHAR(36) PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, nominal_minor BIGINT NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'RUB',
 issuance_status VARCHAR(20) NOT NULL DEFAULT 'not_issued', redemption_status VARCHAR(20) NOT NULL DEFAULT 'not_redeemed', refund_status VARCHAR(20) NOT NULL DEFAULT 'none',
 provider_id CHAR(36) NULL UNIQUE, code_ciphertext TEXT NULL, code_hash CHAR(64) NULL UNIQUE, payment_id VARCHAR(100) NOT NULL UNIQUE,
 expires_at DATETIME NULL, provided_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY(order_id) REFERENCES pg_orders(id), INDEX pg_card_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_activations (
 id CHAR(36) PRIMARY KEY, card_id CHAR(36) NOT NULL UNIQUE, issuer_id CHAR(36) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL,
 buyer_name VARCHAR(180) NOT NULL, contract_reference VARCHAR(200) NOT NULL, basis VARCHAR(20) NOT NULL DEFAULT 'advance',
 status VARCHAR(20) NOT NULL DEFAULT 'pending', nominal_minor BIGINT NOT NULL, provider_commission_minor BIGINT NULL,
 provider_operation_id CHAR(36) NULL UNIQUE, activated_at DATETIME NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(card_id) REFERENCES pg_cards(id), FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id), FOREIGN KEY(actor_id) REFERENCES pm_clients(id),
 INDEX pg_activation_issuer(issuer_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_commands (
 id CHAR(36) PRIMARY KEY, card_id CHAR(36) NOT NULL, activation_id CHAR(36) NULL, kind VARCHAR(20) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'queued', error_code VARCHAR(80) NULL, attempts INT UNSIGNED NOT NULL DEFAULT 0,
 claimed_at DATETIME NULL, completed_at DATETIME NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY pg_command_once(card_id,kind), FOREIGN KEY(card_id) REFERENCES pg_cards(id), FOREIGN KEY(activation_id) REFERENCES pg_activations(id),
 INDEX pg_queue(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_settlements (
 id CHAR(36) PRIMARY KEY, issuer_id CHAR(36) NOT NULL, request_key CHAR(36) NOT NULL UNIQUE,
 gross_minor BIGINT NOT NULL, agent_fee_minor BIGINT NOT NULL, control_fee_minor BIGINT NOT NULL, transfer_fee_minor BIGINT NOT NULL, net_minor BIGINT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'prepared', bank_reference VARCHAR(150) NULL UNIQUE, bank_credited_at DATETIME NULL,
 received_reference VARCHAR(150) NULL, received_minor BIGINT NULL, received_by BIGINT UNSIGNED NULL, received_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id), FOREIGN KEY(created_by) REFERENCES pm_clients(id), FOREIGN KEY(received_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_settlement_items (
 settlement_id CHAR(36) NOT NULL, activation_id CHAR(36) NOT NULL UNIQUE,
 PRIMARY KEY(settlement_id,activation_id), FOREIGN KEY(settlement_id) REFERENCES pg_settlements(id), FOREIGN KEY(activation_id) REFERENCES pg_activations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_balance_returns (
 id CHAR(36) PRIMARY KEY, settlement_id CHAR(36) NOT NULL UNIQUE, status VARCHAR(24) NOT NULL DEFAULT 'manual_review',
 amount_minor BIGINT NULL, provider_commission_minor BIGINT NULL, provider_id CHAR(36) NULL,
 reverse_code_ciphertext TEXT NULL, generation_state VARCHAR(20) NOT NULL DEFAULT 'not_started', activation_state VARCHAR(20) NOT NULL DEFAULT 'not_started',
 provider_operation_id CHAR(36) NULL UNIQUE, reconciliation_note TEXT NULL, provider_reference VARCHAR(200) NULL,
 verified_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL, completed_at DATETIME NULL,
 FOREIGN KEY(settlement_id) REFERENCES pg_settlements(id), FOREIGN KEY(verified_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_refunds (
 id CHAR(36) PRIMARY KEY, card_id CHAR(36) NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
 reason TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'requested', due_at DATETIME NOT NULL,
 amount_minor BIGINT NULL, bank_reference VARCHAR(150) NULL UNIQUE, resolution TEXT NULL, created_at DATETIME NOT NULL, paid_at DATETIME NULL,
 FOREIGN KEY(card_id) REFERENCES pg_cards(id), FOREIGN KEY(client_id) REFERENCES pm_clients(id), INDEX pg_refund_due(status,due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_documents (
 id CHAR(36) PRIMARY KEY, issuer_id CHAR(36) NOT NULL, kind VARCHAR(24) NOT NULL, version CHAR(64) NOT NULL,
 storage_name VARCHAR(100) NOT NULL UNIQUE, original_name VARCHAR(180) NOT NULL, mime VARCHAR(100) NOT NULL, size_bytes BIGINT NOT NULL, sha256 CHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'review', uploaded_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(issuer_id) REFERENCES pg_issuers(id), FOREIGN KEY(uploaded_by) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_enquiries (
 id CHAR(36) PRIMARY KEY, company_name VARCHAR(180) NOT NULL, contact_name VARCHAR(180) NOT NULL, email VARCHAR(254) NOT NULL,
 country VARCHAR(100) NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_consents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NULL,
 document_version VARCHAR(60) NOT NULL, document_hash CHAR(64) NOT NULL, accepted_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id), FOREIGN KEY(order_id) REFERENCES pg_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_legal_versions (
 hash CHAR(64) PRIMARY KEY, version VARCHAR(60) NOT NULL, document_json MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO pm_counters(name,value) VALUES('gift_order',0),('gift_contract',0);
