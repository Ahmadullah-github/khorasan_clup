-- =====================================================
-- Sports Camp Management System - Complete Database Schema
-- Consolidated from all migrations
-- Generated: 1404-09-08 (2025-11-28)
-- =====================================================
-- This file creates the complete database from scratch.
-- For existing databases, use individual migration files.
-- =====================================================

-- Create database with UTF-8 support
CREATE DATABASE IF NOT EXISTS `khorasan_club` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `khorasan_club`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Set character set for the session
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;
SET character_set_connection=utf8mb4;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at_jalali` varchar(10) NOT NULL,
  `last_login_jalali` varchar(10) DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STUDENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at_jalali` varchar(10) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`first_name`,`last_name`),
  FULLTEXT KEY `idx_search` (`first_name`,`last_name`,`father_name`,`contact_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- COACHES TABLE (with contract management)
-- =====================================================
CREATE TABLE IF NOT EXISTS `coaches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `contract_type` enum('percentage','salary','hybrid') NOT NULL DEFAULT 'percentage',
  `percentage_rate` decimal(5,2) NOT NULL DEFAULT 50.00,
  `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fee_calculation_slots` enum('morning_evening','all','custom') NOT NULL DEFAULT 'morning_evening',
  `contract_start_jalali` varchar(10) DEFAULT NULL,
  `contract_end_jalali` varchar(10) DEFAULT NULL,
  `created_at_jalali` varchar(10) NOT NULL,
  `notes` text DEFAULT NULL,
  `default_fee` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`first_name`,`last_name`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted_at` (`deleted_at`),
  FULLTEXT KEY `idx_search` (`first_name`,`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TIME SLOTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `time_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COACH-TIME SLOT RELATIONSHIP
-- =====================================================
CREATE TABLE IF NOT EXISTS `coach_time_slot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coach_id` int(11) NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `counts_for_fee` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_coach_slot` (`coach_id`,`time_slot_id`),
  KEY `fk_coach` (`coach_id`),
  KEY `fk_time_slot` (`time_slot_id`),
  CONSTRAINT `fk_coach_time_slot_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coach_time_slot_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REGISTRATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `coach_id` int(11) NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL,
  `registration_date_jalali` varchar(10) NOT NULL,
  `start_date_jalali` varchar(10) NOT NULL,
  `end_date_jalali` varchar(10) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at_jalali` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reg_student` (`student_id`),
  KEY `fk_reg_coach` (`coach_id`),
  KEY `fk_reg_time_slot` (`time_slot_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date_jalali`,`end_date_jalali`),
  CONSTRAINT `fk_registrations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registrations_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registrations_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date_jalali` varchar(10) NOT NULL,
  `method` enum('cash','bank_transfer','card') DEFAULT 'cash',
  `receipt_no` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_payment_registration` (`registration_id`),
  CONSTRAINT `fk_payments_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EXPENSES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date_jalali` varchar(10) NOT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at_jalali` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_expense_user` (`created_by`),
  KEY `idx_category` (`category`),
  KEY `idx_date` (`expense_date_jalali`),
  FULLTEXT KEY `idx_search` (`title`,`details`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- RENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `rents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `month_jalali` int(2) NOT NULL,
  `year_jalali` int(4) NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT 1,
  `created_at_jalali` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_month_year` (`month_jalali`,`year_jalali`),
  KEY `fk_rent_expense` (`expense_id`),
  CONSTRAINT `fk_rents_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INVOICES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `issued_date_jalali` varchar(10) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_invoice_registration` (`registration_id`),
  CONSTRAINT `fk_invoices_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AUDIT LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `timestamp_jalali` varchar(10) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_timestamp` (`timestamp_jalali`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COACH CONTRACT HISTORY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `coach_contract_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coach_id` int(11) NOT NULL,
  `contract_type` enum('percentage','salary','hybrid') NOT NULL,
  `percentage_rate` decimal(5,2) DEFAULT NULL,
  `monthly_salary` decimal(12,2) DEFAULT NULL,
  `start_date_jalali` varchar(10) NOT NULL,
  `end_date_jalali` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at_jalali` varchar(10) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_contract_history_coach` (`coach_id`),
  KEY `fk_contract_history_user` (`created_by`),
  KEY `idx_coach_dates` (`coach_id`, `start_date_jalali`),
  CONSTRAINT `fk_coach_contract_history_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coach_contract_history_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADD FOREIGN KEY FOR INVOICE_ID IN REGISTRATIONS
-- =====================================================
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_registrations_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

-- =====================================================
-- SCHEDULED EVENT: AUTO-CLEANUP DELETED COACHES
-- Deletes coaches inactive for more than 60 days
-- =====================================================
DROP EVENT IF EXISTS `cleanup_deleted_coaches`;

DELIMITER //
CREATE EVENT IF NOT EXISTS `cleanup_deleted_coaches`
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 2 HOUR)
DO
BEGIN
    DELETE FROM coaches 
    WHERE status = 'inactive' 
    AND deleted_at IS NOT NULL 
    AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY);
END//
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =====================================================
-- DEFAULT TIME SLOTS (Optional - uncomment to use)
-- =====================================================

INSERT INTO `time_slots` (`name`, `start_time`, `end_time`, `description`) VALUES
('صبح', '06:00:00', '09:00:00', 'وقت صبح'),
('چاشت', '09:00:00', '12:00:00', 'وقت چاشت'),
('عصر', '15:00:00', '18:00:00', 'وقت عصر');


-- =====================================================
-- DEFAULT ADMIN USER (Optional - uncomment to use)
-- Password: admin123 (change immediately after setup!)
-- =====================================================

INSERT INTO `users` (`username`, `password_hash`, `role`, `created_at_jalali`) VALUES
('admin', '$2y$10$nPRP6T6fLjwoWUAt/Nv0FOiQ/u57hZzXXjVQFzU7YJv5M20.8uUvm', 'admin', '1404-09-08');