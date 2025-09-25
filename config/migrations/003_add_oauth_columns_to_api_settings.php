<?php
// config/migrations/003_add_oauth_columns_to_api_settings.php
// Run: php config/migrations/003_add_oauth_columns_to_api_settings.php
require_once __DIR__ . '/../database-config.php';

try {
    $cols = [
        "google_oauth_client_id VARCHAR(255) DEFAULT NULL",
        "google_oauth_client_secret TEXT DEFAULT NULL",
        "linkedin_client_id VARCHAR(255) DEFAULT NULL",
        "linkedin_client_secret TEXT DEFAULT NULL"
    ];

    foreach ($cols as $colDef) {
        // Extract column name
        preg_match('/^([a-z0-9_]+)/', $colDef, $m);
        $colName = $m[1] ?? null;
        if (!$colName) continue;

        $exists = $db->query("SHOW COLUMNS FROM api_settings LIKE '" . $colName . "'")->fetchAll();
        if (empty($exists)) {
            $db->exec("ALTER TABLE api_settings ADD COLUMN " . $colDef);
            echo "Added column: $colName\n";
        } else {
            echo "Column already exists: $colName\n";
        }
    }

    echo "Migration complete.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    error_log('Migration 003 failed: ' . $e->getMessage());
    exit(1);
}

?>
