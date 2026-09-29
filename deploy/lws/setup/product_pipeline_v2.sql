ALTER TABLE vp_product_drafts
    ADD COLUMN description MEDIUMTEXT NULL AFTER title,
    ADD COLUMN payload_json MEDIUMTEXT NULL AFTER price,
    ADD COLUMN proof_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER shopify_handle,
    ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER proof_verified;

CREATE TABLE IF NOT EXISTS vp_dead_letters (
    dead_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action_id BIGINT UNSIGNED NOT NULL,
    task_id VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY vp_dead_action (action_id),
    INDEX vp_dead_task (task_id),
    CONSTRAINT vp_dead_action_fk FOREIGN KEY (action_id) REFERENCES vp_actions(action_id),
    CONSTRAINT vp_dead_task_fk FOREIGN KEY (task_id) REFERENCES vp_tasks(task_id)
) ENGINE=InnoDB;
