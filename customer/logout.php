<?php
// customer/logout.php - Fixed version
require_once '../config/database-config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout activity if user is logged in
if (isCustomerLoggedIn()) {
    try {
        // Remove remember me token if exists
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $db->prepare("DELETE FROM customer_sessions WHERE session_token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
            
            // Clear the cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Log logout activity
        logCustomerActivity($_SESSION['customer_id'], 'logout', 'User logged out');
        
    } catch (Exception $e) {
        error_log("Logout cleanup error: " . $e->getMessage());
    }
}

// Destroy session
session_unset();
session_destroy();

// Clear any session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login with logout message
header('Location: login.php?message=logged_out');
exit();
?>