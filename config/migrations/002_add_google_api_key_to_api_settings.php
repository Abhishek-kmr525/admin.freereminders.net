<?php
// config/migrations/002_add_google_api_key_to_api_settings.php
// Run: php config/migrations/002_add_google_api_key_to_api_settings.php
require_once __DIR__ . '/../database-config.php';

try {
    // Add column if not exists (best-effort)
    $columns = $db->query("SHOW COLUMNS FROM api_settings LIKE 'google_api_key'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE api_settings ADD COLUMN google_api_key TEXT DEFAULT NULL");
        echo "✅ google_api_key column added to api_settings\n";
    } else {
        echo "ℹ️ google_api_key column already exists\n";
    }
    echo "Migration complete.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    error_log('Migration 002 failed: ' . $e->getMessage());
    exit(1);
}

?>
