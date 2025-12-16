<?php
// logout.php - Secure Logout Handler
require_once 'config.php';

// Check if user is logged in
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Log the logout activity
    try {
        logUserActivity($user_id, 'logout', ucfirst($user_role) . ' logged out');
    } catch (Exception $e) {
        error_log("Logout activity log error: " . $e->getMessage());
    }
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        // Clear token from database
        try {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Remember token clear error: " . $e->getMessage());
        }
        
        // Delete the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// Destroy all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with logout message
header('Location: login.php?logout=success');
exit();
?>