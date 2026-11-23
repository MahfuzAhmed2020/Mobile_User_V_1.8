CREATE TABLE delivery_quotes (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT NOT NULL, address_id BIGINT NOT NULL, fingerprint VARCHAR(64) NOT NULL,
 carrier VARCHAR(255), service VARCHAR(255), rate_id VARCHAR(255),
 amount DECIMAL(10,2) NOT NULL, days INT NOT NULL, expires_at DATETIME(6) NOT NULL,
 INDEX delivery_quotes_owner (user_id), INDEX delivery_quotes_expiry (expires_at),
 FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (address_id) REFERENCES saved_addresses(id)
);
ALTER TABLE orders ADD delivery_quote_id BIGINT NULL, ADD shipping_amount DECIMAL(10,2) NULL,
 ADD FOREIGN KEY (delivery_quote_id) REFERENCES delivery_quotes(id);
ALTER TABLE order_tracking ADD carrier VARCHAR(255) NULL, ADD carrier_number VARCHAR(255) NULL,
 ADD label_url VARCHAR(2000) NULL, ADD checked_at DATETIME(6) NULL;
