#!/bin/bash
# Script to fix encoding issues by re-importing data with correct encoding

echo "Dropping existing data..."
mysql -u root -prootpassword --default-character-set=utf8mb4 khorasan_club <<EOF
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE invoices;
TRUNCATE TABLE payments;
TRUNCATE TABLE registrations;
TRUNCATE TABLE rents;
TRUNCATE TABLE expenses;
TRUNCATE TABLE coach_time_slot;
TRUNCATE TABLE students;
TRUNCATE TABLE coaches;
TRUNCATE TABLE time_slots;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;
EOF

echo "Re-importing sample data with UTF-8 encoding..."
mysql -u root -prootpassword --default-character-set=utf8mb4 khorasan_club < seed/sample_data.sql

echo "Done! Data re-imported with correct encoding."

