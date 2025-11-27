-- =====================================================
-- Migration: Add fee_calculation_slots to coaches table
-- Date: 1404-09-08 (2025-11-28)
-- Description: Adds field to control which time slots
--              count toward percentage fee calculation
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================
-- Add fee_calculation_slots column to coaches table
-- =====================================================

-- Controls which student fees count for percentage calculation:
-- 'morning_evening' = Only morning/evening student fees (default, legacy behavior)
-- 'all' = All student fees regardless of time slot
-- 'custom' = Use coach_time_slot table to mark which slots count

ALTER TABLE `coaches` 
ADD COLUMN `fee_calculation_slots` enum('morning_evening','all','custom') NOT NULL DEFAULT 'morning_evening' 
AFTER `monthly_salary`;

-- =====================================================
-- Add counts_for_fee column to coach_time_slot table
-- Used when fee_calculation_slots = 'custom'
-- =====================================================

ALTER TABLE `coach_time_slot`
ADD COLUMN `counts_for_fee` tinyint(1) NOT NULL DEFAULT 1
AFTER `fee_amount`;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =====================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================
/*
ALTER TABLE `coaches` DROP COLUMN `fee_calculation_slots`;
ALTER TABLE `coach_time_slot` DROP COLUMN `counts_for_fee`;
*/

-- =====================================================
-- VERIFICATION
-- =====================================================
/*
DESCRIBE coaches;
DESCRIBE coach_time_slot;
*/
