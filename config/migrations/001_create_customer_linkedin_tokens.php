<?php
// config/migrations/001_create_customer_linkedin_tokens.php
// Run this script once to create or update the customer_linkedin_tokens table.
// Usage: php config/migrations/001_create_customer_linkedin_tokens.php

require_once __DIR__ . '/../database-config.php';

try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `customer_linkedin_tokens` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `customer_id` INT(11) NOT NULL,
        `access_token` TEXT NOT NULL,
        `refresh_token` TEXT DEFAULT NULL,
        `token_type` VARCHAR(64) DEFAULT NULL,
        `expires_at` DATETIME DEFAULT NULL,
        `linkedin_user_id` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_customer` (`customer_id`),
        CONSTRAINT `fk_clt_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL;

    $db->exec($sql);
    echo "✅ customer_linkedin_tokens table created or already exists\n";

    // Optional: add token_type column if missing (for older schemas)
    try {
        $db->exec("ALTER TABLE customer_linkedin_tokens ADD COLUMN IF NOT EXISTS token_type VARCHAR(64) DEFAULT NULL");
    } catch (Exception $e) {
        // Some MySQL versions don't support IF NOT EXISTS for ADD COLUMN; handle gracefully
        $columns = $db->query("SHOW COLUMNS FROM customer_linkedin_tokens LIKE 'token_type'")->fetchAll();
        if (empty($columns)) {
            $db->exec("ALTER TABLE customer_linkedin_tokens ADD COLUMN token_type VARCHAR(64) DEFAULT NULL");
            echo "✅ token_type column added\n";
        }
    }

    echo "Migration complete. Please verify the table and run application tests.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    error_log("Migration 001 failed: " . $e->getMessage());
    exit(1);
}

?>