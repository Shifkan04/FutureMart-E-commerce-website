<?php
/**
 * resend.php - Handle OTP resend requests
 */

session_start();
require_once 'config.php';
require_once 'EmailHelper.php';
require_once 'OTPManager.php';

header('Content-Type: application/json');

// Check verification process
if (!isset($_SESSION['verification_pending']) || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No verification process found.'
    ]);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

try {
    // Resend OTP
    $otpManager = new OTPManager($pdo);
    $result = $otpManager->generateAndSendOTP(
        $_SESSION['user_id'], 
        $_SESSION['user_email'], 
        $_SESSION['user_name']
    );
    
    if ($result['success']) {
        error_log("OTP resent to: " . $_SESSION['user_email']);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Resend OTP error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend verification code. Please try again.'
    ]);
}
?>
