CREATE TABLE IF NOT EXISTS vp_tasks (
    task_id VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    event_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    agent VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX vp_tasks_status_created (status, created_at),
    CONSTRAINT vp_tasks_event FOREIGN KEY (event_id) REFERENCES vp_events(event_id)
) ENGINE=InnoDB;
