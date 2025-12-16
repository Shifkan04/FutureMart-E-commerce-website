<?php
/**
 * verify.php - Handle OTP verification
 */

session_start();
require_once 'config.php';
require_once 'EmailHelper.php';
require_once 'OTPManager.php';

header('Content-Type: application/json');

// Check if user is in verification process
if (!isset($_SESSION['verification_pending']) || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No verification process found. Please register again.'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

try {
    // Get and validate OTP
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        throw new Exception('Please enter the verification code.');
    }
    
    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new Exception('Verification code must be 6 digits.');
    }
    
    // Verify OTP
    $otpManager = new OTPManager($pdo);
    $result = $otpManager->verifyOTP($_SESSION['user_id'], $otp);
    
    if ($result['success']) {
        // Clear verification pending flag
        unset($_SESSION['verification_pending']);
        $_SESSION['user_verified'] = true;
        
        // Log successful verification
        error_log("User verification successful: " . $_SESSION['user_email']);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'redirect' => 'login.php?verified=1'
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
