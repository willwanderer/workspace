-- Status History Table for Task and Project tracking
-- Add this to your database

CREATE TABLE IF NOT EXISTS `status_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('task', 'project') NOT NULL COMMENT 'task or project',
  `entity_id` INT NOT NULL,
  `old_status` VARCHAR(50) DEFAULT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `note` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `entity_type` (`entity_type`),
  INDEX `entity_id` (`entity_id`),
  INDEX `user_id` (`user_id`),
  INDEX `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;