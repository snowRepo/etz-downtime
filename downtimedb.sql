-- Downtime Database Schema
-- Created: 2025-12-10
-- Updated: 2025-12-11 - Reorganized for proper foreign key constraints

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Drop database if exists (comment out if you don't want to drop existing database)
-- DROP DATABASE IF EXISTS `downtimedb`;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `downtimedb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `downtimedb`;

-- Table structure for `companies`
CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `services`
CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `issues_reported`
CREATE TABLE `issues_reported` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) NOT NULL,
  `service_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `root_cause` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `impact_level` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
  PRIMARY KEY (`issue_id`),
  KEY `service_id` (`service_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `incident_updates`
CREATE TABLE `incident_updates` (
  `update_id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `update_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`update_id`),
  KEY `issue_id` (`issue_id`),
  CONSTRAINT `fk_incident_updates_issue` 
  FOREIGN KEY (`issue_id`) REFERENCES `issues_reported` (`issue_id`) 
  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Root cause is now stored directly in issues_reported table
-- Add foreign key constraints after all tables are created
ALTER TABLE `issues_reported`
  ADD CONSTRAINT `fk_issues_reported_service` 
  FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) 
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `issues_reported`
  ADD CONSTRAINT `fk_issues_reported_company` 
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) 
  ON DELETE CASCADE ON UPDATE CASCADE;

-- Add resolved_by and resolved_at columns to track who resolved the issue and when
ALTER TABLE `issues_reported` 
ADD COLUMN `resolved_by` varchar(255) DEFAULT NULL AFTER `status`,
ADD COLUMN `resolved_at` timestamp NULL DEFAULT NULL AFTER `resolved_by`;

-- Insert initial data after tables and constraints are set up
-- Data for `companies`
INSERT INTO `companies` (`company_id`, `company_name`, `category`, `created_at`, `updated_at`) VALUES
(1, 'Abii National', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(2, 'AirtelTigo', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(3, 'All', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(4, 'Atwima', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(5, 'Bestpoint', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(6, 'BOA', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(7, 'ECG', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(8, 'eTranzanct', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(9, 'MTN', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(10, 'Multi Choice', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(11, 'NIB', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(12, 'NLA', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(13, 'PBL', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(14, 'SISL', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(15, 'STCCU', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(16, 'Telecel', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23'),
(17, 'VisionFund', NULL, '2025-12-10 13:09:23', '2025-12-10 13:09:23');

-- Data for `services`
INSERT INTO `services` (`service_id`, `service_name`, `created_at`, `updated_at`) VALUES
(1, 'Mobile Money', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(2, 'Fundgate', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(3, 'Vasgate Top-up', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(4, 'Vasgate Bill', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(5, 'GIP (Funds transfer)', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(6, 'GHQR Transactions', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(7, 'Paygate / Payfluid', '2025-12-10 13:33:46', '2025-12-10 13:33:46'),
(8, 'Xcel', '2025-12-10 13:33:46', '2025-12-10 13:33:46');

-- Insert sample issue (optional)
-- Note: This will only work if the service with ID 2 exists
-- INSERT INTO `issues_reported` (`issue_id`, `user_name`, `service_id`, `root_cause`, `status`, `impact_level`) 
-- VALUES (1, 'Ataankpa', 2, 'Root cause unknown', 'pending', 'Low');

-- Insert sample incident_company relationship (optional)
-- Note: This will only work if both the issue and company exist
-- INSERT INTO `incident_companies` (`incident_id`, `company_id`) VALUES (1, 5);

-- Reset auto-increment values
ALTER TABLE `companies` AUTO_INCREMENT=18;
ALTER TABLE `services` AUTO_INCREMENT=9;
ALTER TABLE `issues_reported` AUTO_INCREMENT=1;

-- Table for tracking detailed downtime incidents
CREATE TABLE `downtime_incidents` (
  `incident_id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `actual_start_time` datetime NOT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `downtime_minutes` int(11) DEFAULT NULL,
  `is_planned` tinyint(1) DEFAULT 0,
  `downtime_category` enum('Network','Server','Maintenance','Third-party','Other') DEFAULT 'Other',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`incident_id`),
  KEY `issue_id` (`issue_id`),
  CONSTRAINT `fk_downtime_incident_issue` 
    FOREIGN KEY (`issue_id`) REFERENCES `issues_reported` (`issue_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for SLA/Uptime targets
CREATE TABLE `sla_targets` (
  `target_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `target_uptime` decimal(5,2) NOT NULL DEFAULT 99.99,
  `business_hours_start` time DEFAULT '09:00:00',
  `business_hours_end` time DEFAULT '17:00:00',
  `business_days` set('Mon','Tue','Wed','Thu','Fri','Sat','Sun') DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `unique_company_service` (`company_id`, `service_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `fk_sla_company` 
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) 
    ON DELETE CASCADE,
  CONSTRAINT `fk_sla_service` 
    FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger to calculate downtime in minutes when actual_end_time is updated
DELIMITER //
CREATE TRIGGER calculate_downtime_minutes
BEFORE UPDATE ON `downtime_incidents`
FOR EACH ROW
BEGIN
    IF NEW.actual_end_time IS NOT NULL AND (OLD.actual_end_time IS NULL OR NEW.actual_end_time != OLD.actual_end_time) THEN
        SET NEW.downtime_minutes = TIMESTAMPDIFF(MINUTE, NEW.actual_start_time, NEW.actual_end_time);
    END IF;
END//
DELIMITER ;

-- Insert default SLA targets for all companies and services
-- This sets default 99.99% uptime target for all combinations
-- You can customize these as needed
INSERT INTO `sla_targets` (`company_id`, `service_id`, `target_uptime`)
SELECT c.company_id, s.service_id, 99.99
FROM `companies` c
CROSS JOIN `services` s
WHERE c.company_name != 'All';

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;