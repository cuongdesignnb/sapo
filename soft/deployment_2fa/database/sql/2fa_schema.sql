-- 2FA Database Schema Updates 
-- Run these commands on production database 
 
ALTER TABLE users ADD COLUMN two_factor_secret TEXT NULL; 
ALTER TABLE users ADD COLUMN two_factor_enabled_at TIMESTAMP NULL; 
ALTER TABLE users ADD COLUMN two_factor_recovery_codes TEXT NULL; 
 
-- Purchase Orders schema fix 
ALTER TABLE purchase_orders ADD COLUMN is_order_only BOOLEAN DEFAULT FALSE; 
