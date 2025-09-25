<?php
// admin/includes/admin-auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // If not logged in, redirect to login page
    header('Location: login.php');
    exit();
}

// Optional: You can add more checks here, like session timeout or IP validation
?>
