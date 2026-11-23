-- Actual access verification and complete operational audit. No synthetic customer activity.
CREATE TABLE IF NOT EXISTS pg_staff_2fa (
 client_id BIGINT UNSIGNED PRIMARY KEY, secret_ciphertext TEXT NULL, pending_ciphertext TEXT NULL,
 pending_expires_at DATETIME NULL, pending_auth_version INT NULL, pending_attempts INT NOT NULL DEFAULT 0,
 factor_version INT NOT NULL DEFAULT 0, last_counter BIGINT NOT NULL DEFAULT -1, updated_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_staff_recovery_codes (
 id CHAR(36) PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL, factor_version INT NOT NULL,
 code_hash CHAR(64) NOT NULL UNIQUE, used_at DATETIME NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(client_id) REFERENCES pm_clients(id), INDEX pg_recovery_owner(client_id,factor_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_audit_context (
 audit_id BIGINT UNSIGNED PRIMARY KEY, actor_kind VARCHAR(20) NOT NULL, source VARCHAR(20) NOT NULL,
 target_client_id BIGINT UNSIGNED NULL, issuer_id CHAR(36) NULL,
 user_agent VARCHAR(500) NOT NULL, ip_hash CHAR(64) NULL,
 INDEX pg_audit_actor(actor_kind,audit_id), INDEX pg_audit_target(target_client_id,audit_id), INDEX pg_audit_issuer(issuer_id,audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_audit_archive (
 id BIGINT UNSIGNED PRIMARY KEY, actor_id BIGINT UNSIGNED NULL, action VARCHAR(100) NOT NULL,
 object_ref VARCHAR(120) NOT NULL, details_json MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
 archived_at DATETIME NOT NULL, INDEX pg_archived_date(created_at,id), INDEX pg_archived_actor(actor_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pg_audit_archive_context (
 audit_id BIGINT UNSIGNED PRIMARY KEY, actor_kind VARCHAR(20) NOT NULL, source VARCHAR(20) NOT NULL,
 target_client_id BIGINT UNSIGNED NULL, issuer_id CHAR(36) NULL,
 user_agent VARCHAR(500) NOT NULL, ip_hash CHAR(64) NULL,
 INDEX pg_archived_context_target(target_client_id,audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
