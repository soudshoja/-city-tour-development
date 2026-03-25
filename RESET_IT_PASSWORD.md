# IT Admin Password Reset Instructions

## Quick Method (Direct SQL)
Run this SQL on your production database:

```sql
UPDATE users
SET password = '$2y$10$W6mdgC925NvdbkgwkTnZeOMIcEMEywxsecgwehTDTqC950GbxlaKe'
WHERE email LIKE '%it@alphia%';
```

**Password:** `City@998000`

## Alternative Method (Laravel Command)
If you can run Laravel commands on production:

```bash
php artisan user:reset-it-password
```

Or with custom parameters:

```bash
php artisan user:reset-it-password --email=it@alphia.net --password=City@998000
```

## What Changed
- **Email:** it@alphia.net (or similar IT email)
- **New Password:** City@998000
- **Role:** Super Admin (role_id = 1)

## Verification
After running, verify with:
```sql
SELECT id, name, email, role_id, updated_at
FROM users
WHERE email LIKE '%it@alphia%';
```
