-- A payment instruction belongs to one existing order. No customer wallet is created.
CREATE TABLE IF NOT EXISTS pg_order_payment_requests (
 order_id BIGINT UNSIGNED PRIMARY KEY,
 client_id BIGINT UNSIGNED NOT NULL,
 cash_location_id VARCHAR(60) NOT NULL,
 requested_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 FOREIGN KEY(order_id) REFERENCES pg_orders(id),
 FOREIGN KEY(client_id) REFERENCES pm_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
