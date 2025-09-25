<?php
// config/init-database.php
// Run this script once to initialize your database with default data

require_once 'database-config.php';

echo "Initializing LinkedIn Automation Database...\n";

try {
    // Create default admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT); // Change this password!
    
    $stmt = $db->prepare("
        INSERT INTO admin_users (username, email, password, created_at) 
        VALUES ('admin', 'admin@example.com', ?, NOW())
        ON DUPLICATE KEY UPDATE password = VALUES(password)
    ");
    $stmt->execute([$adminPassword]);
    echo "✅ Default admin user created (username: admin, password: admin123)\n";
    
    // Initialize API settings table
    $stmt = $db->prepare("
        INSERT INTO api_settings (id, updated_at) 
        VALUES (1, NOW())
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    $stmt->execute();
    echo "✅ API settings table initialized\n";
    
    // Create a test customer (using the existing one from your SQL dump)
    $customerPassword = password_hash('password123', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        UPDATE customers 
        SET password = ?, subscription_status = 'trial', trial_ends_at = ? 
        WHERE email = 'mr.abhishek525@gmail.coim'
    ");
    $trialEnd = date('Y-m-d H:i:s', strtotime('+14 days'));
    $stmt->execute([$customerPassword, $trialEnd]);
    echo "✅ Test customer updated with password: password123\n";
    
    // Create sample pricing plans if they don't exist
    $plans = [
        ['us', 'Basic', 19.00, 'USD', '50 posts per month,AI Generation,Email Support', 50, 2],
        ['us', 'Pro', 49.00, 'USD', '200 posts per month,Advanced AI,Priority Support', 200, 5],
        ['us', 'Enterprise', 99.00, 'USD', 'Unlimited posts,All Features,24/7 Support', -1, -1],
        ['in', 'Basic', 1499.00, 'INR', '50 posts per month,AI Generation,Email Support', 50, 2],
        ['in', 'Pro', 3999.00, 'INR', '200 posts per month,Advanced AI,Priority Support', 200, 5],
        ['in', 'Enterprise', 7999.00, 'INR', 'Unlimited posts,All Features,24/7 Support', -1, -1]
    ];
    
    foreach ($plans as $plan) {
        $stmt = $db->prepare("
            INSERT INTO pricing_plans (
                country, plan_name, plan_price, currency, features, 
                max_posts_per_month, max_automations, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                plan_price = VALUES(plan_price),
                features = VALUES(features),
                max_posts_per_month = VALUES(max_posts_per_month),
                max_automations = VALUES(max_automations)
        ");
        $stmt->execute($plan);
    }
    echo "✅ Pricing plans created/updated\n";
    
    // Add password_resets table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `customer_id` int(11) NOT NULL,
            `token` varchar(255) NOT NULL,
            `expires_at` timestamp NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_customer` (`customer_id`),
            KEY `token_index` (`token`),
            FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ Password resets table created\n";
    
    echo "\n🎉 Database initialization completed!\n";
    echo "\n📝 Next Steps:\n";
    echo "1. Admin Panel: /admin/login.php (username: admin, password: admin123)\n";
    echo "2. Customer Login: /customer/login.php (email: mr.abhishek525@gmail.coim, password: password123)\n";
    echo "3. Configure API keys in Admin Panel > API Settings\n";
    echo "4. Change default admin password!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>