-- =====================================================
-- Migration: Coach Contract Management Enhancement
-- Date: 1404-09-07 (2025-11-27)
-- Description: Adds contract management, payment types,
--              soft delete, and contract history tracking
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Set character set
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================
-- STEP 1: Add new columns to coaches table
-- =====================================================

-- Add phone number
ALTER TABLE `coaches` 
ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `last_name`;

-- Add contract type: percentage, salary, or hybrid (both)
ALTER TABLE `coaches` 
ADD COLUMN `contract_type` enum('percentage','salary','hybrid') NOT NULL DEFAULT 'percentage' AFTER `photo_path`;

-- Add percentage rate (for percentage and hybrid types)
ALTER TABLE `coaches` 
ADD COLUMN `percentage_rate` decimal(5,2) NOT NULL DEFAULT 50.00 AFTER `contract_type`;

-- Add monthly salary (for salary and hybrid types)
ALTER TABLE `coaches` 
ADD COLUMN `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `percentage_rate`;

-- Add contract period dates
ALTER TABLE `coaches` 
ADD COLUMN `contract_start_jalali` varchar(10) DEFAULT NULL AFTER `monthly_salary`;

ALTER TABLE `coaches` 
ADD COLUMN `contract_end_jalali` varchar(10) DEFAULT NULL AFTER `contract_start_jalali`;

-- Add status for soft delete functionality
ALTER TABLE `coaches` 
ADD COLUMN `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active' AFTER `notes`;

-- Add deleted_at for 60-day soft delete tracking
ALTER TABLE `coaches` 
ADD COLUMN `deleted_at` datetime DEFAULT NULL AFTER `status`;

-- Add updated_at timestamp
ALTER TABLE `coaches` 
ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Add index for status filtering
ALTER TABLE `coaches` 
ADD INDEX `idx_status` (`status`);

-- Add index for soft delete cleanup
ALTER TABLE `coaches` 
ADD INDEX `idx_deleted_at` (`deleted_at`);

-- =====================================================
-- STEP 2: Create coach_contract_history table
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
-- STEP 3: Create directory marker for coach photos
-- Note: Actual directory creation must be done manually
-- Path: public/assets/uploads/coaches/
-- =====================================================

-- =====================================================
-- STEP 4: Create scheduled event for auto-cleanup
-- Deletes coaches inactive for more than 60 days
-- =====================================================

-- Drop existing event if exists
DROP EVENT IF EXISTS `cleanup_deleted_coaches`;

-- Create cleanup event (runs daily at 2 AM)
DELIMITER //
CREATE EVENT IF NOT EXISTS `cleanup_deleted_coaches`
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 2 HOUR)
DO
BEGIN
    -- Delete coaches that have been soft-deleted for more than 60 days
    DELETE FROM coaches 
    WHERE status = 'inactive' 
    AND deleted_at IS NOT NULL 
    AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY);
END//
DELIMITER ;

-- =====================================================
-- STEP 5: Update existing coaches with default values
-- =====================================================

-- Set default contract values for existing coaches
UPDATE `coaches` 
SET 
    `contract_type` = 'percentage',
    `percentage_rate` = 50.00,
    `monthly_salary` = 0.00,
    `status` = 'active'
WHERE `contract_type` IS NULL OR `status` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =====================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================
/*
-- To rollback this migration, run:

DROP EVENT IF EXISTS `cleanup_deleted_coaches`;
DROP TABLE IF EXISTS `coach_contract_history`;

ALTER TABLE `coaches` 
DROP COLUMN `phone`,
DROP COLUMN `contract_type`,
DROP COLUMN `percentage_rate`,
DROP COLUMN `monthly_salary`,
DROP COLUMN `contract_start_jalali`,
DROP COLUMN `contract_end_jalali`,
DROP COLUMN `status`,
DROP COLUMN `deleted_at`,
DROP COLUMN `updated_at`,
DROP INDEX `idx_status`,
DROP INDEX `idx_deleted_at`;
*/

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================
/*
-- Check coaches table structure:
DESCRIBE coaches;

-- Check contract history table:
DESCRIBE coach_contract_history;

-- Check event status:
SHOW EVENTS LIKE 'cleanup_deleted_coaches';

-- Verify indexes:
SHOW INDEX FROM coaches;
*/
