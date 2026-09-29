CREATE TABLE IF NOT EXISTS vp_actions (
    action_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    agent VARCHAR(32) NOT NULL,
    action_type VARCHAR(64) NOT NULL,
    status ENUM('pending','running','waiting','retry','done','failed','blocked') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    run_after TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at TIMESTAMP NULL DEFAULT NULL,
    last_error VARCHAR(255) NULL,
    result_reference VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY vp_action_once (task_id, agent, action_type),
    INDEX vp_actions_queue (status, run_after, created_at),
    CONSTRAINT vp_actions_task FOREIGN KEY (task_id) REFERENCES vp_tasks(task_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vp_action_log (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(16) NULL,
    to_status VARCHAR(16) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX vp_action_log_action (action_id, created_at),
    CONSTRAINT vp_action_log_action_fk FOREIGN KEY (action_id) REFERENCES vp_actions(action_id)
) ENGINE=InnoDB;
