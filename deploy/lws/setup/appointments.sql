CREATE TABLE IF NOT EXISTS vp_appointment_requests (
 request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 task_id VARCHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 source_reference VARCHAR(191) NOT NULL,
 service VARCHAR(120) NOT NULL,
 requested_date DATE NULL,
 requested_time_start TIME NULL,
 requested_time_end TIME NULL,
 status ENUM('needs_information','ready_to_check','proposed','confirmed','unavailable','human_required') NOT NULL DEFAULT 'needs_information',
 calendar_reference VARCHAR(255) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX vp_appointment_status_date(status,requested_date),
 CONSTRAINT vp_appointment_task FOREIGN KEY(task_id) REFERENCES vp_tasks(task_id)
) ENGINE=InnoDB;
