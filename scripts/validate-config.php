<?php
// scripts/validate-config.php
// Run from the project root to validate the database configuration.

// Try environment variables first (safer for CI or production use)
$envHost = getenv('DB_HOST');
$envName = getenv('DB_NAME');
$envUser = getenv('DB_USER');
$envPass = getenv('DB_PASS');

if ($envHost && $envName && $envUser) {
    echo "Using DB credentials from environment variables\n";
    $dsn = "mysql:host={$envHost};dbname={$envName};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $envUser, $envPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "Connection successful (env credentials)\n";
        exit(0);
    } catch (Exception $e) {
        echo "Connection failed with environment credentials: " . $e->getMessage() . "\n";
        exit(2);
    }
}

$configPath = __DIR__ . '/../config/database-config.php';
if (!file_exists($configPath)) {
    echo "Config file not found: $configPath\n";
    echo "Create it by copying config/database-config.php.example and filling in values:\n";
    echo "cp config/database-config.php.example config/database-config.php\n";
    exit(1);
}

// Try including the project's config and getting a PDO
require_once $configPath;

if (!isset($database)) {
    echo "The included config did not create \$database variable. Please ensure config/database-config.php sets up \$database = new DatabaseConfig();\n";
    exit(3);
}

try {
    $db = $database->getConnection();
    echo "Connection successful (config file)\n";
    exit(0);
} catch (Exception $e) {
    echo "Connection failed using config/database-config.php: " . $e->getMessage() . "\n";
    exit(4);
}
