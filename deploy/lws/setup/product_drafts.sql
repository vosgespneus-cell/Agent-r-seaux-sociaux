CREATE TABLE IF NOT EXISTS vp_product_drafts (
    draft_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_reference VARCHAR(255) NOT NULL,
    fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(180) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('prepared','validated','created','rejected','failed') NOT NULL DEFAULT 'prepared',
    shopify_product_id VARCHAR(128) NULL,
    shopify_handle VARCHAR(255) NULL,
    last_error VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY vp_product_draft_fingerprint (fingerprint),
    INDEX vp_product_draft_task (task_id),
    CONSTRAINT vp_product_draft_task_fk FOREIGN KEY (task_id) REFERENCES vp_tasks(task_id)
) ENGINE=InnoDB;
