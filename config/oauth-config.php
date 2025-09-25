<?php
// config/oauth-config.php - Enhanced OAuth Configuration with Database Integration

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define SITE_URL if not already defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://postautomator.com');
}

// Skip database include if already included
if (!isset($db)) {
    require_once __DIR__ . '/database-config.php';
}

// OAuth Configuration - fetch from api_settings table
$oauthSettings = [];
try {
    if (isset($db) && $db) {
        // First, ensure the table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'api_settings'");
        if ($tableCheck->rowCount() === 0) {
            throw new Exception('api_settings table does not exist');
        }
        
        $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
        $stmt->execute();
        $oauthSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        if (empty($oauthSettings)) {
            error_log('Warning: No OAuth settings found in database.');
        }
    }
} catch (Exception $e) {
    error_log('OAuth settings error: ' . $e->getMessage());
    die('ERROR: Unable to load OAuth settings from database. Please contact administrator.');
}

// Google OAuth Configuration - Fetch from database only
if (!defined('GOOGLE_CLIENT_ID')) {
    if (empty($oauthSettings['google_client_id'])) {
        die('ERROR: Google Client ID not configured. Please add it in Admin Panel → API Settings.');
    }
    define('GOOGLE_CLIENT_ID', $oauthSettings['google_client_id']);
}

if (!defined('GOOGLE_CLIENT_SECRET')) {
    if (empty($oauthSettings['google_client_secret'])) {
        die('ERROR: Google Client Secret not configured. Please add it in Admin Panel → API Settings.');
    }
    define('GOOGLE_CLIENT_SECRET', $oauthSettings['google_client_secret']);
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', SITE_URL . '/customer/oauth/google-callback.php');
}

// LinkedIn OAuth Configuration - Fetch from database only
if (!defined('LINKEDIN_CLIENT_ID')) {
    if (empty($oauthSettings['linkedin_client_id'])) {
        die('ERROR: LinkedIn Client ID not configured. Please add it in Admin Panel → API Settings.');
    }
    define('LINKEDIN_CLIENT_ID', $oauthSettings['linkedin_client_id']);
}

if (!defined('LINKEDIN_CLIENT_SECRET')) {
    if (empty($oauthSettings['linkedin_client_secret'])) {
        die('ERROR: LinkedIn Client Secret not configured. Please add it in Admin Panel → API Settings.');
    }
    define('LINKEDIN_CLIENT_SECRET', $oauthSettings['linkedin_client_secret']);
}

if (!defined('LINKEDIN_REDIRECT_URI')) {
    define('LINKEDIN_REDIRECT_URI', SITE_URL . '/customer/oauth/linkedin-callback.php');
}

// Session management
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 3600,
            'cookie_secure' => true,  // Changed to true for HTTPS
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax'
        ]);
    }
}

function generateSecureState() {
    return bin2hex(random_bytes(32));
}

function validateState($receivedState) {
    ensureSession();
    
    // Check if state exists
    if (empty($receivedState)) {
        error_log("OAuth Error: No state parameter received");
        return false;
    }
    
    // Check session state
    $sessionState = $_SESSION['oauth_state'] ?? null;
    $sessionTimestamp = $_SESSION['oauth_timestamp'] ?? 0;
    
    // Check cookie state as fallback
    $cookieState = $_COOKIE['oauth_state'] ?? null;
    
    // Debug logging
    error_log("Received state: " . substr($receivedState, 0, 20) . "...");
    error_log("Session state: " . ($sessionState ? substr($sessionState, 0, 20) . "..." : "NONE"));
    error_log("Cookie state: " . ($cookieState ? substr($cookieState, 0, 20) . "..." : "NONE"));
    
    // State must match either session or cookie
    $stateValid = ($receivedState === $sessionState) || ($receivedState === $cookieState);
    
    // Check timestamp (max 1 hour) - only if session state exists
    $timestampValid = true;
    if ($sessionState && $sessionTimestamp > 0) {
        $timestampValid = (time() - $sessionTimestamp) < 3600;
        if (!$timestampValid) {
            error_log("OAuth Error: State timestamp expired. Age: " . (time() - $sessionTimestamp) . " seconds");
        }
    }
    
    if (!$stateValid) {
        error_log("OAuth Error: State mismatch");
    }
    
    return $stateValid && $timestampValid;
}
function clearOAuthState() {
    ensureSession();
    unset($_SESSION['oauth_state']);
    unset($_SESSION['oauth_timestamp']);
    setcookie('oauth_state', '', time() - 3600, '/', '', false, true);
}

function getGoogleLoginUrl() {
    ensureSession();
    
    $state = generateSecureState();
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_timestamp'] = time();
    
    // Set cookie as fallback with matching settings
    setcookie('oauth_state', $state, [
        'expires' => time() + 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,  // HTTPS only
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Debug logging
    error_log("=== Google Login URL Generated ===");
    error_log("Session ID: " . session_id());
    error_log("Generated State: " . $state);
    error_log("Timestamp: " . time());
    
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => 'openid profile email',
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state
    ];
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function getLinkedInLoginUrl() {
    ensureSession();
    
    $state = generateSecureState();
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_timestamp'] = time();
    
    // Set cookie as fallback
    setcookie('oauth_state', $state, time() + 3600, '/', '', true, true);
    
    $params = [
        'response_type' => 'code',
        'client_id' => LINKEDIN_CLIENT_ID,
        'redirect_uri' => LINKEDIN_REDIRECT_URI,
        'scope' => 'openid profile email',  // REMOVED w_member_social - it requires special approval
        'state' => $state
    ];
    
    return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query($params);
}
function exchangeGoogleCodeForToken($code) {
    $data = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI
    ];
    
    return makeTokenRequest('https://oauth2.googleapis.com/token', $data);
}

function exchangeLinkedInCodeForToken($code) {
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => LINKEDIN_REDIRECT_URI,
        'client_id' => LINKEDIN_CLIENT_ID,
        'client_secret' => LINKEDIN_CLIENT_SECRET
    ];
    
    return makeTokenRequest('https://www.linkedin.com/oauth/v2/accessToken', $data);
}

function makeTokenRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL error: $error");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Token exchange failed with HTTP $httpCode: $response");
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . json_last_error_msg());
    }
    
    if (isset($result['error'])) {
        throw new Exception("OAuth error: " . $result['error'] . " - " . ($result['error_description'] ?? ''));
    }
    
    return $result;
}

function getGoogleUserInfo($accessToken) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Google user info request failed with HTTP $httpCode: $response");
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from Google userinfo: " . json_last_error_msg());
    }
    
    return $result;
}

function getLinkedInUserInfo($accessToken) {
    // Use OpenID Connect userinfo endpoint instead of deprecated v2 API
    $url = 'https://api.linkedin.com/v2/userinfo';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("LinkedIn userinfo failed: HTTP $httpCode - $response");
        throw new Exception("LinkedIn user info request failed with HTTP $httpCode: $response");
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from LinkedIn userinfo: " . json_last_error_msg());
    }
    
    // Return normalized structure
    return [
        'id' => $result['sub'] ?? '',
        'email' => $result['email'] ?? '',
        'name' => $result['name'] ?? '',
        'given_name' => $result['given_name'] ?? '',
        'family_name' => $result['family_name'] ?? '',
        'picture' => $result['picture'] ?? ''
    ];
}

function makeLinkedInApiRequest($url, $accessToken) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("LinkedIn API request failed with HTTP $httpCode: $response");
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from LinkedIn: " . json_last_error_msg());
    }
    
    return $result;
}

// Create or update OAuth customer
function createOrUpdateOAuthCustomer($provider, $userData) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $email = '';
        $name = '';
        $providerId = '';
        $profilePicture = '';
        
       if ($provider === 'google') {
    $email = $userData['email'] ?? '';
    $name = $userData['name'] ?? '';
    $providerId = $userData['id'] ?? '';
    $profilePicture = $userData['picture'] ?? '';
} elseif ($provider === 'linkedin') {
    // NEW: Handle OpenID Connect format
    $email = $userData['email'] ?? '';
    $name = $userData['name'] ?? '';
    $providerId = $userData['id'] ?? '';
    $profilePicture = $userData['picture'] ?? '';
}
        if (empty($email)) {
            throw new Exception('Email not provided by OAuth provider');
        }
        
        // Check if customer exists
        $stmt = $db->prepare("SELECT * FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing customer
            $stmt = $db->prepare("
                UPDATE customers 
                SET oauth_provider = ?, oauth_id = ?, profile_picture = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$provider, $providerId, $profilePicture, $existing['id']]);
            
            $customerId = $existing['id'];
            $isNew = false;
        } else {
            // Create new customer
            $trialEndsAt = date('Y-m-d H:i:s', strtotime('+14 days'));
            
            $stmt = $db->prepare("
                INSERT INTO customers (name, email, oauth_provider, oauth_id, profile_picture, trial_ends_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $email, $provider, $providerId, $profilePicture, $trialEndsAt]);
            
            $customerId = $db->lastInsertId();
            $isNew = true;
        }
        
        // Set session
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['customer_email'] = $email;
        $_SESSION['customer_name'] = $name;
        
        // Log activity
        logCustomerActivity($customerId, $isNew ? 'oauth_signup' : 'oauth_login', 
                           "Account accessed via $provider", $_SERVER['REMOTE_ADDR'] ?? '', 
                           $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $db->commit();
        
        return [
            'id' => $customerId,
            'email' => $email,
            'name' => $name,
            'existing' => !$isNew
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('Failed to create/update customer: ' . $e->getMessage());
    }
}

if (!function_exists('logCustomerActivity')) {
    function logCustomerActivity($customerId, $action, $details = '') {
        global $db;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO customer_activity_logs (customer_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $customerId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Error logging customer activity: " . $e->getMessage());
        }
    }
}

// LinkedIn posting functions
function postToLinkedIn($accessToken, $content, $linkedinUserId) {
    $data = [
        'author' => 'urn:li:person:' . $linkedinUserId,
        'lifecycleState' => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary' => [
                    'text' => $content
                ],
                'shareMediaCategory' => 'NONE'
            ]
        ],
        'visibility' => [
            'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
        ]
    ];
    
    $ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201) {
        throw new Exception("LinkedIn post failed with HTTP $httpCode: $response");
    }
    
    $result = json_decode($response, true);
    return $result['id'] ?? null;
}

// Helper function to get current OAuth credentials source
function getOAuthCredentialsSource() {
    global $oauthSettings;
    
    $sources = [];
    
    // Check Google credentials
    if (!empty($oauthSettings['google_client_id'])) {
        $sources['google'] = 'database';
    } else {
        $sources['google'] = 'not_configured';
    }
    
    // Check LinkedIn credentials
    if (!empty($oauthSettings['linkedin_client_id'])) {
        $sources['linkedin'] = 'database';
    } else {
        $sources['linkedin'] = 'not_configured';
    }
    
    return $sources;
}

// Clean expired states
function cleanExpiredOAuthStates() {
    ensureSession();
    
    $currentTime = time();
    $sessionTimestamp = $_SESSION['oauth_timestamp'] ?? 0;
    
    if (($currentTime - $sessionTimestamp) > 3600) {
        clearOAuthState();
    }
}

cleanExpiredOAuthStates();
?>
