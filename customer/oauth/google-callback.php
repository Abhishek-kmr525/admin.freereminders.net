<?php
// customer/oauth/google-callback.php - Updated
require_once __DIR__ . '/../../config/database-config.php';
require_once __DIR__ . '/../../config/oauth-config.php';

// Show errors for debugging only in development - disable in production
if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

ensureSession();

try {
    // If Google returned an error
    if (isset($_GET['error'])) {
        throw new Exception('OAuth authorization denied: ' . htmlspecialchars($_GET['error']));
    }

    // Validate state parameter
    $returnedState = $_GET['state'] ?? '';
    $storedState = $_SESSION['oauth_state'] ?? '';
    $cookieState = $_COOKIE['oauth_state'] ?? '';

    error_log("Google OAuth - returnedState={$returnedState}, storedState={$storedState}, cookieState={$cookieState}");

    if (empty($returnedState)) {
        throw new Exception('Missing state parameter.');
    }

    // Accept state from session or cookie fallback
    if (!empty($storedState) && hash_equals($storedState, $returnedState)) {
        // ok
    } elseif (!empty($cookieState) && hash_equals($cookieState, $returnedState)) {
        // cookie fallback accepted
        $_SESSION['oauth_state'] = $cookieState;
    } else {
        clearOAuthState();
        throw new Exception('Invalid state parameter. Session may have expired or state mismatch.');
    }

    // Get code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        clearOAuthState();
        throw new Exception('Authorization code not returned by Google.');
    }

    // Clear state immediately to prevent reuse
    clearOAuthState();

    // Exchange authorization code for access token
    $tokenData = exchangeGoogleCodeForToken($code);
    if (!isset($tokenData['access_token'])) {
        throw new Exception('No access token returned by Google: ' . json_encode($tokenData));
    }
    $accessToken = $tokenData['access_token'];

    // Retrieve user info
    $userInfo = getGoogleUserInfo($accessToken);

    if (empty($userInfo['email'])) {
        throw new Exception('Google did not return an email address.');
    }

    // Create or update local customer
    $customer = createOrUpdateOAuthCustomer('google', $userInfo, function_exists('getCustomerCountry') ? getCustomerCountry() : null);

    // Set friendly messages
    if (!empty($customer['existing'])) {
        $_SESSION['success_message'] = "Welcome back, " . htmlspecialchars($customer['name']) . "!";
    } else {
        $_SESSION['success_message'] = "Welcome, " . htmlspecialchars($customer['name']) . "! Your trial has started.";
    }

    // Redirect to dashboard (adjust path as needed)
    header('Location: ' . (defined('SITE_URL') ? SITE_URL . '/customer/dashboard.php' : '../dashboard.php'));
    exit;
} catch (Exception $e) {
    // Log full detail for debugging (server logs)
    error_log("Google OAuth callback error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    // Clear any oauth leftovers
    clearOAuthState();

    // Provide user-friendly error in session and redirect to login
    $_SESSION['error_message'] = 'Google login failed: ' . $e->getMessage();
    header('Location: ' . (defined('SITE_URL') ? SITE_URL . '/customer/login.php' : '../login.php'));
    exit;
}
