-- ============================================================
-- SQL QUERIES FOR TODAY'S UPDATES (2026-08-20)
-- Execute these queries on your Live Server Database phpMyAdmin / MySQL
-- ============================================================

-- 1. Add TDS Deduction columns to sales table (if not exists)
ALTER TABLE sales 
ADD COLUMN IF NOT EXISTS tds_percent DECIMAL(5,2) DEFAULT 0.00, 
ADD COLUMN IF NOT EXISTS tds_amount DECIMAL(10,2) DEFAULT 0.00;

-- 2. Add TDS Deduction columns to invoices table (if not exists)
ALTER TABLE invoices 
ADD COLUMN IF NOT EXISTS tds_percent DECIMAL(5,2) DEFAULT 0.00, 
ADD COLUMN IF NOT EXISTS tds_amount DECIMAL(10,2) DEFAULT 0.00;

-- 3. Add Current Balance column to customers table (if not exists)
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS current_balance DECIMAL(10,2) DEFAULT 0.00;

-- 4. Add Quantity Alert column to products table (if not exists)
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS quantity_alert INT DEFAULT 5;
