CREATE TABLE IF NOT EXISTS vp_product_intake (
    intake_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    source VARCHAR(32) NOT NULL,
    source_reference VARCHAR(255) NOT NULL,
    reference_text VARCHAR(255) NULL,
    notes MEDIUMTEXT NULL,
    media_json MEDIUMTEXT NULL,
    processing_status ENUM('new','candidate_ready','needs_information','rejected','draft_created') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX vp_product_intake_status (processing_status, created_at),
    CONSTRAINT vp_product_intake_task_fk FOREIGN KEY (task_id) REFERENCES vp_tasks(task_id)
) ENGINE=InnoDB;
