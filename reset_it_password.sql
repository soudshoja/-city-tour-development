-- SQL script to reset IT@ALPHIA.NET password to City@998000
-- Run this on your production database

-- Find the user email (case-insensitive search)
-- SELECT id, name, email, role_id FROM users WHERE email LIKE '%it@alphia%';

-- Update password for the IT admin user
-- Password: City@998000
-- Hashed password: $2y$10$W6mdgC925NvdbkgwkTnZeOMIcEMEywxsecgwehTDTqC950GbxlaKe

UPDATE users
SET password = '$2y$10$W6mdgC925NvdbkgwkTnZeOMIcEMEywxsecgwehTDTqC950GbxlaKe'
WHERE email LIKE '%it@alphia%';

-- Verify the update
-- SELECT id, name, email, role_id,
--        CASE WHEN password LIKE '$2y$10$%' THEN 'Password Set' ELSE 'No Password' END as password_status
-- FROM users
-- WHERE email LIKE '%it@alphia%';
