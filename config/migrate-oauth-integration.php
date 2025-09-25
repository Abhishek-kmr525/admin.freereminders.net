<?php
// config/migrate-oauth-integration.php
// Run this script once to update your database for OAuth integration

require_once 'database-config.php';

echo "Starting OAuth Integration Database Migration...\n";

try {
    $db->beginTransaction();
    
    // Check and add Google OAuth columns to api_settings
    $stmt = $db->query("SHOW COLUMNS FROM api_settings LIKE 'google_oauth_client_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE api_settings ADD COLUMN google_oauth_client_id TEXT DEFAULT NULL AFTER linkedin_client_secret");
        echo "✅ Added google_oauth_client_id column to api_settings\n";
    } else {
        echo "ℹ️ google_oauth_client_id column already exists\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM api_settings LIKE 'google_oauth_client_secret'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE api_settings ADD COLUMN google_oauth_client_secret TEXT DEFAULT NULL AFTER google_oauth_client_id");
        echo "✅ Added google_oauth_client_secret column to api_settings\n";
    } else {
        echo "ℹ️ google_oauth_client_secret column already exists\n";
    }
    
    // Check and add OAuth columns to customers table
    $stmt = $db->query("SHOW COLUMNS FROM customers LIKE 'oauth_provider'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customers ADD COLUMN oauth_provider VARCHAR(20) DEFAULT NULL AFTER password");
        echo "✅ Added oauth_provider column to customers\n";
    } else {
        echo "ℹ️ oauth_provider column already exists\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM customers LIKE 'oauth_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customers ADD COLUMN oauth_id VARCHAR(100) DEFAULT NULL AFTER oauth_provider");
        echo "✅ Added oauth_id column to customers\n";
    } else {
        echo "ℹ️ oauth_id column already exists\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM customers LIKE 'profile_picture'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customers ADD COLUMN profile_picture TEXT DEFAULT NULL AFTER oauth_id");
        echo "✅ Added profile_picture column to customers\n";
    } else {
        echo "ℹ️ profile_picture column already exists\n";
    }
    
    // Check and create customer_activity_logs table
    $stmt = $db->query("SHOW TABLES LIKE 'customer_activity_logs'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE `customer_activity_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `customer_id` int(11) NOT NULL,
                `action` varchar(50) NOT NULL,
                `details` text DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `customer_id` (`customer_id`),
                KEY `action` (`action`),
                KEY `created_at` (`created_at`),
                FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "✅ Created customer_activity_logs table\n";
    } else {
        echo "ℹ️ customer_activity_logs table already exists\n";
    }
    
    // Check and create admin_activity_log table
    $stmt = $db->query("SHOW TABLES LIKE 'admin_activity_log'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE `admin_activity_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `admin_id` int(11) NOT NULL,
                `action` varchar(50) NOT NULL,
                `description` text DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `admin_id` (`admin_id`),
                KEY `action` (`action`),
                KEY `created_at` (`created_at`),
                FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "✅ Created admin_activity_log table\n";
    } else {
        echo "ℹ️ admin_activity_log table already exists\n";
    }
    
    // Update api_settings with proper columns if needed
    $stmt = $db->query("SHOW COLUMNS FROM api_settings LIKE 'webhook_secret'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE api_settings ADD COLUMN webhook_secret TEXT DEFAULT NULL");
        echo "✅ Added webhook_secret column to api_settings\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM api_settings LIKE 'smtp_host'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE api_settings ADD COLUMN smtp_host VARCHAR(255) DEFAULT NULL");
        $db->exec("ALTER TABLE api_settings ADD COLUMN smtp_port INT DEFAULT 587");
        $db->exec("ALTER TABLE api_settings ADD COLUMN smtp_username VARCHAR(255) DEFAULT NULL");
        $db->exec("ALTER TABLE api_settings ADD COLUMN smtp_password TEXT DEFAULT NULL");
        echo "✅ Added SMTP configuration columns to api_settings\n";
    }
    
    // Create indexes for better performance
    try {
        $db->exec("CREATE INDEX idx_customers_email ON customers (email)");
        echo "✅ Added email index to customers table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "ℹ️ Email index already exists on customers table\n";
    }
    
    try {
        $db->exec("CREATE INDEX idx_customers_oauth ON customers (oauth_provider, oauth_id)");
        echo "✅ Added OAuth index to customers table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "ℹ️ OAuth index already exists on customers table\n";
    }
    
    // Initialize api_settings record if it doesn't exist
    $stmt = $db->query("SELECT COUNT(*) as count FROM api_settings WHERE id = 1");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $db->exec("
            INSERT INTO api_settings (
                id, gemini_api_key, chatgpt_api_key, linkedin_client_id, 
                linkedin_client_secret, google_oauth_client_id, google_oauth_client_secret,
                razorpay_key_id, razorpay_key_secret, webhook_secret,
                smtp_host, smtp_port, smtp_username, smtp_password, created_at, updated_at
            ) VALUES (
                1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                NULL, 587, NULL, NULL, NOW(), NOW()
            )
        ");
        echo "✅ Initialized api_settings record\n";
    } else {
        echo "ℹ️ api_settings record already exists\n";
    }
    
    // Update customer_automations table for better integration
    $stmt = $db->query("SHOW COLUMNS FROM customer_automations LIKE 'post_frequency'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customer_automations ADD COLUMN post_frequency ENUM('daily', 'weekdays', 'custom') DEFAULT 'custom' AFTER days_of_week");
        echo "✅ Added post_frequency column to customer_automations\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM customer_automations LIKE 'content_style'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customer_automations ADD COLUMN content_style ENUM('professional', 'casual', 'educational', 'motivational', 'storytelling') DEFAULT 'professional' AFTER content_template");
        echo "✅ Added content_style column to customer_automations\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM customer_automations LIKE 'include_images'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customer_automations ADD COLUMN include_images TINYINT(1) DEFAULT 0 AFTER content_style");
        echo "✅ Added include_images column to customer_automations\n";
    }
    
    $stmt = $db->query("SHOW COLUMNS FROM customer_automations LIKE 'auto_hashtags'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE customer_automations ADD COLUMN auto_hashtags TINYINT(1) DEFAULT 0 AFTER include_images");
        echo "✅ Added auto_hashtags column to customer_automations\n";
    }
    
    // Create customer_linkedin_tokens table for storing LinkedIn access tokens
    $stmt = $db->query("SHOW TABLES LIKE 'customer_linkedin_tokens'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE `customer_linkedin_tokens` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `customer_id` int(11) NOT NULL,
                `access_token` text NOT NULL,
                `refresh_token` text DEFAULT NULL,
                `linkedin_user_id` varchar(100) DEFAULT NULL,
                `expires_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `customer_id` (`customer_id`),
                FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "✅ Created customer_linkedin_tokens table\n";
    } else {
        echo "ℹ️ customer_linkedin_tokens table already exists\n";
    }
    
    $db->commit();
    
    echo "\n🎉 OAuth Integration Database Migration completed successfully!\n";
    echo "\n📋 Next Steps:\n";
    echo "1. Configure OAuth credentials in Admin Panel > API Settings\n";
    echo "2. Set up LinkedIn and Google OAuth applications\n";
    echo "3. Configure redirect URIs in your OAuth apps\n";
    echo "4. Test the OAuth integration using admin/oauth-integration-test.php\n";
    echo "\n🔗 Redirect URIs to configure:\n";
    echo "   LinkedIn: " . SITE_URL . "/customer/oauth/linkedin-callback.php\n";
    echo "   Google: " . SITE_URL . "/customer/oauth/google-callback.php\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>