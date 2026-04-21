-- ==========================================
-- 🚗 SMARTDRIVE X - MASTER DATABASE SETUP
-- ==========================================
-- Run this entire script in phpMyAdmin to build the complete architecture.

-- 1. Create and Use the Database
CREATE DATABASE IF NOT EXISTS smartdrive_x;
USE smartdrive_x;

-- ==========================================
-- 🏗️ TABLE CREATION (In Dependency Order)
-- ==========================================

-- 2. Locations Table
CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Users Table (Customers & Admins)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role_id INT DEFAULT 2 COMMENT '1 = Admin, 2 = Customer',
    loyalty_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Cars (Fleet Inventory)
CREATE TABLE IF NOT EXISTS cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    location_id INT NOT NULL,
    status ENUM('available', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
);

-- 5. Promotional Coupons
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Bookings (The Core Transaction Table)
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    car_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    final_price DECIMAL(10,2) NOT NULL,
    booking_status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);

-- 7. Invoices (Generated automatically on payment)
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    gst_amount DECIMAL(10,2) NOT NULL,
    total_with_tax DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- 8. Payments Log
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('PAID', 'FAILED', 'PENDING') DEFAULT 'PAID',
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- 9. Maintenance Records (Fleet Health)
CREATE TABLE IF NOT EXISTS maintenance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    service_date DATE NOT NULL,
    description TEXT NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);

-- ==========================================
-- 🌱 SEED DATA (Pre-populating the platform)
-- ==========================================

-- Insert Default Hub Locations
INSERT INTO locations (city_name) VALUES 
('Ahmedabad'), 
('Mumbai'), 
('Delhi'), 
('Bangalore');

-- Insert Initial Premium Fleet
INSERT INTO cars (brand, name, model, base_price, location_id, status) VALUES 
('Porsche', '911 Carrera', '2023 Sports', 8500.00, 1, 'available'),
('BMW', 'X5', '2024 Luxury SUV', 6200.00, 1, 'available'),
('Honda', 'City', '2023 Executive', 2100.00, 1, 'available'),
('Range Rover', 'Evoque', '2024 Premium', 7500.00, 2, 'available'),
('Audi', 'A6', '2023 Sedan', 5000.00, 3, 'available');

-- Insert a Welcome Promo Code
INSERT INTO coupons (code, discount_type, discount_value, expiry_date) VALUES 
('SMART10', 'percentage', 10.00, '2026-12-31'),
('DIWALI500', 'fixed', 500.00, '2026-11-01');

-- ==========================================
-- 🎉 SETUP COMPLETE
-- ==========================================