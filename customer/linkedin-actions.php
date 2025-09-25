<?php
// customer/oauth/linkedin-callback.php - Fixed to save tokens
require_once '../../config/database-config.php';
require_once '../../config/oauth-config.php';

try {
    // Start session if not already started
    ensureSession();
    
    // Check for errors
    if (isset($_GET['error'])) {
        throw new Exception('OAuth authorization was denied: ' . $_GET['error']);
    }
    
    // Verify state parameter
    $receivedState = $_GET['state'] ?? '';
    $sessionState = $_SESSION['oauth_state'] ?? '';
    
    if (empty($receivedState) || empty($sessionState) || $receivedState !== $sessionState) {
        throw new Exception('Invalid state parameter. Session may have expired.');
    }
    
    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        throw new Exception('Authorization code not received');
    }
    
    // Exchange code for access token
    $tokenResponse = exchangeLinkedInCodeForToken($code);
    
    if (!isset($tokenResponse['access_token'])) {
        throw new Exception('Failed to obtain access token: ' . json_encode($tokenResponse));
    }
    
    // Get user information
    $userInfo = getLinkedInUserInfo($tokenResponse['access_token']);
    
    if (!isset($userInfo['email']) || !isset($userInfo['name'])) {
        throw new Exception('Failed to retrieve user information');
    }
    
    // Create or update customer
    $customer = createOrUpdateOAuthCustomer('linkedin', $userInfo, getCustomerCountry());
    
    // **CRITICAL: Save LinkedIn token to database**
    try {
        // Delete any existing tokens for this customer
        $stmt = $db->prepare("DELETE FROM customer_linkedin_tokens WHERE customer_id = ?");
        $stmt->execute([$customer['id']]);
        
        // Insert new token
        $stmt = $db->prepare("
            INSERT INTO customer_linkedin_tokens (
                customer_id, 
                access_token, 
                refresh_token,
                expires_at,
                linkedin_user_id,
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        // Calculate expiry time (default 60 days for LinkedIn)
        $expiresIn = $tokenResponse['expires_in'] ?? 5184000; // 60 days default
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        
        $stmt->execute([
            $customer['id'],
            $tokenResponse['access_token'],
            $tokenResponse['refresh_token'] ?? null,
            $expiresAt,
            $userInfo['sub'] ?? null // LinkedIn user ID
        ]);
        
        error_log("LinkedIn token saved for customer ID: " . $customer['id']);
        
    } catch (Exception $e) {
        error_log("Failed to save LinkedIn token: " . $e->getMessage());
        // Don't fail the entire flow if token save fails
    }
    
    // Clean up session
    unset($_SESSION['oauth_state']);
    unset($_SESSION['oauth_timestamp']);
    setcookie('oauth_state', '', time() - 3600, '/');
    
    // Set success message
    $_SESSION['success_message'] = $customer['existing'] ? 
        "Welcome back! LinkedIn connected successfully." : 
        "Account created! LinkedIn connected successfully.";
    
    // Redirect to dashboard
    header('Location: ../dashboard.php');
    exit();
    
} catch (Exception $e) {
    error_log("LinkedIn OAuth error: " . $e->getMessage());
    
    // Clean up session
    unset($_SESSION['oauth_state']);
    unset($_SESSION['oauth_timestamp']);
    setcookie('oauth_state', '', time() - 3600, '/');
    
    $_SESSION['error_message'] = 'LinkedIn connection failed: ' . $e->getMessage();
    header('Location: ../connect-linkedin.php');
    exit();
}
?>