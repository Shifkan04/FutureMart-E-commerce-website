<?php
require_once '../config_user.php';
require_once '../User.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Log logout activity
    logUserActivity($userId, 'logout', 'User logged out');
    
    // Destroy session
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

// Redirect to login page
header('Location: ../login.php?message=logged_out');
exit();
?>