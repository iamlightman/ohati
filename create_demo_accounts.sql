-- ======================================================================
-- Ohati Event Marketplace — App Store Review Demo Accounts Generator
-- Safe SQL Script: Creates/Verifies Demo Accounts Without Deleting Data
-- ======================================================================

-- 1. Create Pre-Verified Demo Customer Account
INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, is_active)
SELECT 'App Review Customer', 'demo.customer@ohati.com', '+233240649883', '$2y$10$Wp2BwM0N0Xw9GkG3vB.xX.e7o9z0123456789abcdefghijk', 'customer', 1, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'demo.customer@ohati.com');

UPDATE users 
SET email_verified = 1, phone_verified = 1, is_active = 1 
WHERE email = 'demo.customer@ohati.com';

-- 2. Create Pre-Verified Demo Vendor Account
INSERT INTO users (name, email, phone, password_hash, role, email_verified, phone_verified, is_active)
SELECT 'App Review Vendor', 'demo.vendor@ohati.com', '+233200000002', '$2y$10$Wp2BwM0N0Xw9GkG3vB.xX.e7o9z0123456789abcdefghijk', 'vendor', 1, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'demo.vendor@ohati.com');

UPDATE users 
SET email_verified = 1, phone_verified = 1, is_active = 1 
WHERE email = 'demo.vendor@ohati.com';

-- 3. Link Demo Vendor Profile
INSERT INTO vendors (user_id, name, category, location, rating, reviews_count, verified, verification_badge, is_active)
SELECT u.id, 'App Review Event Services', 'Photography', 'Accra, Ghana', 5.0, 12, 1, 'gold', 1
FROM users u
WHERE u.email = 'demo.vendor@ohati.com'
  AND NOT EXISTS (SELECT 1 FROM vendors v WHERE v.user_id = u.id);

UPDATE vendors v
JOIN users u ON v.user_id = u.id
SET v.verified = 1, v.verification_badge = 'gold', v.is_active = 1
WHERE u.email = 'demo.vendor@ohati.com';
