-- ==========================================
-- 🚗 SMARTDRIVE X — V2.0 DATABASE MIGRATION
-- Purpose: Late Returns, Enhanced Booking Flow, Admin Settings
-- ==========================================
-- Run this AFTER the original db_setup.sql
-- Safe to run multiple times (idempotent)

USE smartdrive_db;

-- ==========================================
-- 1️⃣ EXPAND BOOKING STATUS ENUM
-- ==========================================
-- Old: 'pending', 'confirmed', 'cancelled'
-- New: + 'approved', 'active', 'completed'
ALTER TABLE bookings MODIFY COLUMN booking_status 
    ENUM('pending','approved','confirmed','active','completed','cancelled') DEFAULT 'pending';

-- ==========================================
-- 2️⃣ ADD LATE RETURN & SETTLEMENT COLUMNS TO BOOKINGS
-- ==========================================

-- Time fields for precise scheduling
ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT '10:00:00' AFTER start_date;
ALTER TABLE bookings ADD COLUMN end_time TIME DEFAULT '10:00:00' AFTER end_date;

-- Actual return tracking
ALTER TABLE bookings ADD COLUMN return_time DATETIME DEFAULT NULL AFTER end_time;

-- Late charge breakdown stored on the booking itself
ALTER TABLE bookings ADD COLUMN late_hours INT DEFAULT 0;
ALTER TABLE bookings ADD COLUMN extra_charges DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE bookings ADD COLUMN gst_on_extra DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE bookings ADD COLUMN final_settlement DECIMAL(10,2) DEFAULT 0.00;

-- Payment tracking
ALTER TABLE bookings ADD COLUMN payment_status 
    ENUM('unpaid','pending','paid','settled') DEFAULT 'unpaid';


-- ==========================================
-- 3️⃣ ADMIN-CONTROLLED SYSTEM SETTINGS
-- ==========================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(500) DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default values (won't overwrite if already set)
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
    ('late_hourly_rate', '300', 'Per-hour charge for late vehicle returns (in ₹)'),
    ('gst_percentage', '18', 'GST tax percentage applied on extra/late charges'),
    ('grace_period_minutes', '60', 'Minutes of grace before late charges kick in')
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ==========================================
-- 4️⃣ LATE RETURNS LEDGER (Detailed Audit Trail)
-- ==========================================
CREATE TABLE IF NOT EXISTS late_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    due_datetime DATETIME NOT NULL,
    return_datetime DATETIME NOT NULL,
    late_minutes INT DEFAULT 0,
    chargeable_hours INT DEFAULT 0,
    hourly_rate DECIMAL(10,2) NOT NULL,
    grace_minutes INT DEFAULT 60,
    base_extra_charge DECIMAL(10,2) DEFAULT 0.00,
    gst_percentage DECIMAL(5,2) DEFAULT 18.00,
    gst_amount DECIMAL(10,2) DEFAULT 0.00,
    total_penalty DECIMAL(10,2) DEFAULT 0.00,
    admin_override TINYINT(1) DEFAULT 0,
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 5️⃣ MIGRATE OLD DATA (Retroactive cleanup)
-- ==========================================
-- Mark overdue confirmed bookings where end_date has passed as 'completed'
UPDATE bookings 
SET booking_status = 'completed', 
    payment_status = 'paid',
    final_settlement = final_price
WHERE booking_status = 'confirmed' 
  AND end_date < CURDATE();

-- Mark currently active confirmed bookings  
UPDATE bookings 
SET booking_status = 'active',
    payment_status = 'paid'
WHERE booking_status = 'confirmed' 
  AND start_date <= CURDATE() 
  AND end_date >= CURDATE();

-- Set payment_status for existing confirmed/active bookings
UPDATE bookings SET payment_status = 'paid' 
WHERE booking_status IN ('confirmed', 'active', 'completed') AND payment_status = 'unpaid';

-- Set payment_status for pending bookings
UPDATE bookings SET payment_status = 'unpaid' 
WHERE booking_status IN ('pending', 'approved');


-- ==========================================
-- 🎉 MIGRATION V2.0 COMPLETE
-- ==========================================
