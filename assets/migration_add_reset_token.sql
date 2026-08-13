-- Bookora Platform - Database Migration
-- Add password reset columns to users table

ALTER TABLE users 
ADD COLUMN reset_token_hash VARCHAR(64) NULL AFTER password_hash,
ADD COLUMN reset_token_expires TIMESTAMP NULL AFTER reset_token_hash,
ADD INDEX idx_reset_token_hash (reset_token_hash);
