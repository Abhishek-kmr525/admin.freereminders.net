<?php
// customer/oauth/google-callback.php - Fixed version
// Start output buffering to prevent header issues
ob_start();

// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 3600,
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Show errors for debugging only in development - disable in production
if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/../../config/database-config.php';
require_once __DIR__ . '/../../config/oauth-config.php';

try {
    // If Google returned an error
    if (isset($_GET['error'])) {
        throw new Exception('OAuth authorization denied: ' . htmlspecialchars($_GET['error']));
    }

    // Validate state parameter
    $returnedState = $_GET['state'] ?? '';
    $storedState = $_SESSION['oauth_state'] ?? '';
    $cookieState = $_COOKIE['oauth_state'] ?? '';
    
    // Enhanced logging
    error_log("=== Google OAuth Callback Debug ===");
    error_log("Session ID: " . session_id());
    error_log("Returned State: " . $returnedState);
    error_log("Session State: " . $storedState);
    error_log("Cookie State: " . $cookieState);
    error_log("Session oauth_timestamp: " . ($_SESSION['oauth_timestamp'] ?? 'NOT SET'));
    error_log("All session data: " . print_r($_SESSION, true));
    error_log("All cookies: " . print_r($_COOKIE, true));
    
    if (empty($returnedState)) {
        throw new Exception('Missing state parameter from Google.');
    }

    // Validate state - accept from either session or cookie
    $stateValid = false;
    
    if (!empty($storedState) && hash_equals($storedState, $returnedState)) {
        error_log("State validated from SESSION");
        $stateValid = true;
    } elseif (!empty($cookieState) && hash_equals($cookieState, $returnedState)) {
        error_log("State validated from COOKIE (fallback)");
        $_SESSION['oauth_state'] = $cookieState;
        $stateValid = true;
    }
    
    if (!$stateValid) {
        error_log("State validation FAILED - clearing and throwing error");
        clearOAuthState();
        throw new Exception('Invalid state parameter. Please try logging in again.');
    }

    // Check state timestamp (if exists)
    $timestamp = $_SESSION['oauth_timestamp'] ?? 0;
    if ($timestamp > 0 && (time() - $timestamp) > 3600) {
        clearOAuthState();
        throw new Exception('Login session expired. Please try again.');
    }

    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        clearOAuthState();
        throw new Exception('Authorization code not returned by Google.');
    }

    // Clear state immediately to prevent reuse
    clearOAuthState();

    // Exchange authorization code for access token
    error_log("Exchanging code for token...");
    $tokenData = exchangeGoogleCodeForToken($code);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('No access token returned by Google: ' . json_encode($tokenData));
    }

    $accessToken = $tokenData['access_token'];
    error_log("Access token received successfully");

    // Retrieve user info from Google
    error_log("Fetching user info from Google...");
    $userInfo = getGoogleUserInfo($accessToken);
    
    if (empty($userInfo['email'])) {
        throw new Exception('Google did not return an email address.');
    }

    error_log("User info received: " . $userInfo['email']);

    // Create or update local customer
    $customer = createOrUpdateOAuthCustomer('google', $userInfo);

    // Set success message
    if (!empty($customer['existing'])) {
        $_SESSION['success_message'] = "Welcome back, " . htmlspecialchars($customer['name']) . "!";
    } else {
        $_SESSION['success_message'] = "Welcome, " . htmlspecialchars($customer['name']) . "! Your trial has started.";
    }

    error_log("OAuth login successful for: " . $userInfo['email']);

    // Redirect to dashboard
    header('Location: ' . (defined('SITE_URL') ? SITE_URL . '/customer/dashboard.php' : '../dashboard.php'));
    exit;

} catch (Exception $e) {
    // Log full error details
    error_log("=== Google OAuth Error ===");
    error_log("Error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Clear any oauth leftovers
    clearOAuthState();
    
    // Set user-friendly error message
    $_SESSION['error_message'] = 'Google login failed: ' . $e->getMessage();
    
    // Redirect back to login
    header('Location: ' . (defined('SITE_URL') ? SITE_URL . '/customer/login.php' : '../login.php'));
    exit;
}

ob_end_flush();