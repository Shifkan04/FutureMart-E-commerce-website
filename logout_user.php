<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Log activity
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'user';

try {
    logUserActivity($userId, 'logout', ucfirst($userRole) . ' logged out');
} catch (Exception $e) {
    // Continue even if logging fails
}

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to home page
header('Location: index.php?logout=success');
exit();
?>