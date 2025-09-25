<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$basePath = dirname(__DIR__);
echo "Base path: " . $basePath . "\n";
echo "Looking for config files in:\n";
echo "Database config: " . $basePath . "/config/database-config.php\n";
echo "OAuth config: " . $basePath . "/config/oauth-config.php\n";

echo "\nChecking file existence:\n";
echo "Database config exists: " . (file_exists($basePath . "/config/database-config.php") ? "Yes" : "No") . "\n";
echo "OAuth config exists: " . (file_exists($basePath . "/config/oauth-config.php") ? "Yes" : "No") . "\n";

echo "\nDirectory contents:\n";
$configDir = $basePath . "/config";
if (is_dir($configDir)) {
    $files = scandir($configDir);
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            echo $file . "\n";
        }
    }
} else {
    echo "Config directory not found!\n";
}

echo "\nFull server path: " . __DIR__ . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
?>