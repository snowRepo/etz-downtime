-- Adminer 5.4.1 MySQL 8.0.44 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `company_id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `companies` (`company_id`, `company_name`, `category`, `created_at`, `updated_at`) VALUES
(1,	'Abii National',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(2,	'AirtelTigo',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(3,	'All',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(4,	'Atwima',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(5,	'Bestpoint',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(6,	'BOA',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(7,	'ECG',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(8,	'eTranzanct',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(9,	'MTN',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(10,	'Multi Choice',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(11,	'NIB',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(12,	'NLA',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(13,	'PBL',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(14,	'SISL',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(15,	'STCCU',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(16,	'Telecel',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23'),
(17,	'VisionFund',	NULL,	'2025-12-10 13:09:23',	'2025-12-10 13:09:23');

DROP TABLE IF EXISTS `downtime_incidents`;
CREATE TABLE `downtime_incidents` (
  `downtime_id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `actual_start_time` datetime NOT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `downtime_minutes` int DEFAULT NULL,
  `is_planned` tinyint(1) DEFAULT '0',
  `downtime_category` enum('Network','Server','Maintenance','Third-party','Other') DEFAULT 'Other',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`downtime_id`),
  KEY `incident_id` (`incident_id`),
  CONSTRAINT `fk_downtime_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `downtime_incidents` (`downtime_id`, `incident_id`, `actual_start_time`, `actual_end_time`, `downtime_minutes`, `is_planned`, `downtime_category`, `created_at`, `updated_at`) VALUES
(2,	2,	'2026-01-20 23:18:18',	NULL,	NULL,	0,	'Other',	'2026-01-20 23:18:18',	'2026-01-20 23:18:18');

DELIMITER ;;

CREATE TRIGGER `calculate_downtime_minutes` BEFORE UPDATE ON `downtime_incidents` FOR EACH ROW
BEGIN
        IF NEW.actual_end_time IS NOT NULL AND (OLD.actual_end_time IS NULL OR NEW.actual_end_time != OLD.actual_end_time) THEN
            SET NEW.downtime_minutes = TIMESTAMPDIFF(MINUTE, NEW.actual_start_time, NEW.actual_end_time);
        END IF;
    END;;

DELIMITER ;

DROP TABLE IF EXISTS `incident_affected_companies`;
CREATE TABLE `incident_affected_companies` (
  `incident_id` int NOT NULL,
  `company_id` int NOT NULL,
  PRIMARY KEY (`incident_id`,`company_id`),
  KEY `fk_iac_company` (`company_id`),
  CONSTRAINT `fk_iac_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_iac_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



DROP TABLE IF EXISTS `incident_types`;
CREATE TABLE `incident_types` (
  `type_id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`type_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_type_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



DROP TABLE IF EXISTS `incident_updates`;
CREATE TABLE `incident_updates` (
  `update_id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) NOT NULL,
  `update_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `incident_id` (`incident_id`),
  CONSTRAINT `fk_updates_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `incidents`;
CREATE TABLE `incidents` (
  `incident_id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `component_id` int DEFAULT NULL,
  `incident_type_id` int DEFAULT NULL,
  `impact_level` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
  `root_cause` text,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `reported_by` int NOT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`incident_id`),
  KEY `service_id` (`service_id`),
  KEY `component_id` (`component_id`),
  KEY `incident_type_id` (`incident_type_id`),
  KEY `reported_by` (`reported_by`),
  CONSTRAINT `fk_incident_component` FOREIGN KEY (`component_id`) REFERENCES `service_components` (`component_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_incident_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_incident_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_incident_type` FOREIGN KEY (`incident_type_id`) REFERENCES `incident_types` (`type_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `service_components`;
CREATE TABLE `service_components` (
  `component_id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`component_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_component_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `services` (`service_id`, `service_name`, `created_at`, `updated_at`) VALUES
(1,	'Mobile Money',	'2025-12-10 13:33:46',	'2025-12-10 13:33:46');

DROP TABLE IF EXISTS `sla_targets`;
CREATE TABLE `sla_targets` (
  `target_id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `service_id` int DEFAULT NULL,
  `target_uptime` decimal(5,2) NOT NULL DEFAULT '99.99',
  `business_hours_start` time DEFAULT '09:00:00',
  `business_hours_end` time DEFAULT '17:00:00',
  `business_days` set('Mon','Tue','Wed','Thu','Fri','Sat','Sun') DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `unique_company_service` (`company_id`,`service_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_sla_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sla_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `changed_password` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2026-01-22 08:18:15 UTC
