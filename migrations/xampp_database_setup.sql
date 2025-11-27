-- XAMPP Database Setup Script
-- Run this in phpMyAdmin or MySQL command line

-- Create database with UTF-8 support
CREATE DATABASE IF NOT EXISTS `khorasan_club` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `khorasan_club`;

-- Set session character set
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;

-- Now import the schema.sql file from migrations folder
-- Or run: SOURCE migrations/schema.sql;
