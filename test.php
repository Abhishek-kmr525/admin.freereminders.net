<?php
// debug/check-linkedin-tokens.php - Debug script to check tokens
require_once 'config/database-config.php';

// Check if user is logged in
if (!isCustomerLoggedIn()) {
    echo "Please log in first";
    exit();
}

$customerId = $_SESSION['customer_id'];

echo "<h2>LinkedIn Token Debug for Customer ID: $customerId</h2>";

// Check if tokens exist
try {
    $stmt = $db->prepare("
        SELECT id, customer_id, 
               SUBSTRING(access_token, 1, 20) as token_preview,
               expires_at, linkedin_user_id, created_at, updated_at
        FROM customer_linkedin_tokens 
        WHERE customer_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$customerId]);
    $tokens = $stmt->fetchAll();
    
    if (empty($tokens)) {
        echo "<p style='color: red;'>❌ No LinkedIn tokens found in database for this customer.</p>";
        echo "<p>This means the OAuth callback is not saving tokens properly.</p>";
    } else {
        echo "<p style='color: green;'>✅ Found " . count($tokens) . " LinkedIn token(s):</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Token Preview</th><th>Expires At</th><th>LinkedIn User ID</th><th>Created</th></tr>";
        
        foreach ($tokens as $token) {
            echo "<tr>";
            echo "<td>{$token['id']}</td>";
            echo "<td>{$token['token_preview']}...</td>";
            echo "<td>{$token['expires_at']}</td>";
            echo "<td>{$token['linkedin_user_id']}</td>";
            echo "<td>{$token['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test the most recent token
        $latestToken = $tokens[0];
        echo "<h3>Testing Latest Token:</h3>";
        
        $stmt = $db->prepare("SELECT access_token FROM customer_linkedin_tokens WHERE id = ?");
        $stmt->execute([$latestToken['id']]);
        $fullToken = $stmt->fetch()['access_token'];
        
        // Test API call
        $ch = curl_init('https://api.linkedin.com/v2/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $fullToken,
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $userInfo = json_decode($response, true);
            echo "<p style='color: green;'>✅ Token is valid! User: " . $userInfo['name'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Token test failed. HTTP Code: $httpCode</p>";
            echo "<p>Response: " . htmlspecialchars($response) . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check customer activity logs for OAuth
echo "<h3>Recent OAuth Activity:</h3>";
try {
    $stmt = $db->prepare("
        SELECT action, details, created_at 
        FROM customer_activity_logs 
        WHERE customer_id = ? AND action LIKE '%linkedin%' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$customerId]);
    $activities = $stmt->fetchAll();
    
    if (empty($activities)) {
        echo "<p>No LinkedIn-related activity found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Action</th><th>Details</th><th>Time</th></tr>";
        foreach ($activities as $activity) {
            echo "<tr>";
            echo "<td>{$activity['action']}</td>";
            echo "<td>{$activity['details']}</td>";
            echo "<td>{$activity['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>Error checking activity: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='/customer/connect-linkedin.php'>Try LinkedIn Connection</a></p>";
echo "<p><a href='/customer/dashboard.php'>Back to Dashboard</a></p>";
?>