Database migrations for Post Automator

How to run migrations

1. Backup your database
   - Always take a full backup before running migrations.

2. Run migration(s)
   - From the project root run:
     php config/migrations/001_create_customer_linkedin_tokens.php

3. Verify
   - Check that the `customer_linkedin_tokens` table exists and contains columns:
     id, customer_id, access_token, refresh_token, token_type, expires_at, linkedin_user_id, created_at, updated_at
   - Ensure `customer_id` has a foreign key to `customers(id)`.

Notes
- The migration script attempts to be idempotent.
- If your MySQL version does not support `ADD COLUMN IF NOT EXISTS`, the script will detect and add the column safely.
- If you use a managed database or need a specific migration tool, convert this script into your tool's format (Flyway/Liquibase/Phinx/etc.).
