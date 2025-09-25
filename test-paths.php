<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Script directory (__DIR__): " . __DIR__ . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Full path to config: " . __DIR__ . '/config/database-config.php' . "\n";
echo "File exists? " . (file_exists(__DIR__ . '/config/database-config.php') ? 'Yes' : 'No') . "\n";

// List contents of config directory
if (is_dir(__DIR__ . '/config')) {
    echo "\nContents of config directory:\n";
    $files = scandir(__DIR__ . '/config');
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo $file . "\n";
        }
    }
} else {
    echo "\nConfig directory not found!\n";
}
?>