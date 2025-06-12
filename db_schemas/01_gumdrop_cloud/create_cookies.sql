CREATE TABLE `cookies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cookie` CHAR(32) COLLATE utf8mb4_bin NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_access` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` VARBINARY(16) DEFAULT NULL,
  `user_agent_md5` CHAR(32) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cookie` (`cookie`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_cookies_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
