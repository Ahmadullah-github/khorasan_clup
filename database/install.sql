-- =====================================================
-- سیستم مدیریت باشگاه ورزشی خراسان
-- Khorasan Sports Club Management System
-- =====================================================
-- 
-- نصب آسان برای XAMPP / phpMyAdmin
-- Easy Installation for XAMPP / phpMyAdmin
--
-- دستورالعمل / Instructions:
-- 1. XAMPP را باز کنید و Apache + MySQL را روشن کنید
--    Open XAMPP and start Apache + MySQL
-- 2. به phpMyAdmin بروید: http://localhost/phpmyadmin
--    Go to phpMyAdmin: http://localhost/phpmyadmin
-- 3. روی تب "Import" کلیک کنید
--    Click on "Import" tab
-- 4. این فایل را انتخاب و اجرا کنید
--    Select this file and run it
--
-- یوزر پیش‌فرض / Default Login:
--   Username: admin
--   Password: admin123
--   (بعد از ورود رمز را عوض کنید!)
--   (Change password after first login!)
--
-- =====================================================

-- ایجاد دیتابیس / Create Database
CREATE DATABASE IF NOT EXISTS `khorasan_club` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `khorasan_club`;

-- تنظیمات اولیه / Initial Settings
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================
-- جدول کاربران / USERS TABLE
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
-- جدول شاگردان / STUDENTS TABLE
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
-- جدول مربیان / COACHES TABLE
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
-- جدول وقت‌ها / TIME SLOTS TABLE
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
-- جدول ارتباط مربی-وقت / COACH-TIME SLOT TABLE
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
  KEY `fk_time_slot` (`time_slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- جدول ثبت‌نام‌ها / REGISTRATIONS TABLE
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
  KEY `idx_dates` (`start_date_jalali`,`end_date_jalali`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول پرداخت‌ها / PAYMENTS TABLE
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
  KEY `fk_payment_registration` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول مصارف / EXPENSES TABLE
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
  FULLTEXT KEY `idx_search` (`title`,`details`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول کرایه / RENTS TABLE
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
  KEY `fk_rent_expense` (`expense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول فاکتورها / INVOICES TABLE
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
  KEY `fk_invoice_registration` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول لاگ‌ها / AUDIT LOGS TABLE
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
  KEY `idx_timestamp` (`timestamp_jalali`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول تاریخچه قرارداد مربیان / COACH CONTRACT HISTORY
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
  KEY `idx_coach_dates` (`coach_id`, `start_date_jalali`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_group (setting_group),
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- اضافه کردن کلیدهای خارجی / ADD FOREIGN KEYS
-- =====================================================

-- Coach Time Slot
ALTER TABLE `coach_time_slot`
  ADD CONSTRAINT `fk_coach_time_slot_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_coach_time_slot_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE;

-- Registrations
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_registrations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_registrations_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_registrations_time_slot` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE;

-- Payments
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

-- Expenses
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

-- Rents
ALTER TABLE `rents`
  ADD CONSTRAINT `fk_rents_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE;

-- Invoices
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

-- Registrations -> Invoice (after invoices table exists)
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_registrations_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

-- Audit Logs
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

-- Coach Contract History
ALTER TABLE `coach_contract_history`
  ADD CONSTRAINT `fk_coach_contract_history_coach` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_coach_contract_history_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

-- فعال کردن کلیدهای خارجی / Enable Foreign Keys
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- داده‌های پیش‌فرض / DEFAULT DATA
-- =====================================================

-- Insert default values (these will be overwritten by setup wizard)
INSERT INTO app_settings (setting_key, setting_value, setting_group) VALUES
-- Organization settings
('org_name_fa', 'کمپ خراسان', 'organization'),
('org_name_en', 'Khorasan Sports Camp', 'organization'),
('org_slogan', 'سیستم مدیریت باشگاه ورزشی', 'organization'),
('org_phone', '', 'organization'),
('org_city', '', 'organization'),
('org_address', '', 'organization'),

-- Manager settings
('manager_name_fa', 'کامران منصوری', 'manager'),
('manager_name_en', 'Kamran Mansoori', 'manager'),
('manager_title', 'مدیر باشگاه', 'manager'),
('manager_phone', '', 'manager'),
('manager_email', '', 'manager'),

-- Financial settings
('currency', 'AFN', 'financial'),
('currency_label', 'افغانی', 'financial'),
('default_percentage', '50', 'financial'),
('default_hybrid_percentage', '25', 'financial'),
('fiscal_year_start', '1404', 'financial'),

-- Categories (JSON array)
('expense_categories', '[{"key":"Rent","label":"اجاره"},{"key":"Equipment","label":"تجهیزات"},{"key":"Taxes","label":"مالیات"},{"key":"Services","label":"خدمات"},{"key":"Other","label":"سایر"}]', 'categories'),

-- System settings
('setup_complete', 'false', 'system')

ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- وقت‌های پیش‌فرض / Default Time Slots
INSERT INTO `time_slots` (`name`, `start_time`, `end_time`, `description`) VALUES
('صبح', '06:00:00', '09:00:00', 'وقت صبح'),
('چاشت', '09:00:00', '12:00:00', 'وقت چاشت'),
('عصر', '15:00:00', '18:00:00', 'وقت عصر');

-- کاربر ادمین پیش‌فرض / Default Admin User
-- Username: admin | Password: admin123
-- Note: created_at_jalali should be updated to current Jalali date when installing
INSERT INTO `users` (`username`, `password_hash`, `role`, `created_at_jalali`) VALUES
('admin', '$2y$10$nPRP6T6fLjwoWUAt/Nv0FOiQ/u57hZzXXjVQFzU7YJv5M20.8uUvm', 'admin', '1404-10-06');

-- =====================================================
-- نصب کامل شد! / INSTALLATION COMPLETE!
-- =====================================================
-- 
-- حالا می‌توانید وارد سیستم شوید:
-- You can now login with:
--   Username: admin
--   Password: admin123
--
-- مهم: رمز عبور را فوراً تغییر دهید!
-- IMPORTANT: Change the password immediately!
--
-- =====================================================
