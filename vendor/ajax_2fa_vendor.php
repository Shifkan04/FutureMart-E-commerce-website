<?php
require_once '../config.php';
require_once '../vendor/autoload.php'; // Make sure you have Google Authenticator library

use PragmaRX\Google2FA\Google2FA;

// Check if user is logged in as vendor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $google2fa = new Google2FA();
    
    switch ($action) {
        case 'generate_2fa_secret':
            // Generate new secret
            $secret = $google2fa->generateSecretKey();
            
            // Get user info for QR code
            $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $companyName = APP_NAME ?? 'FutureMart';
            $userEmail = $user['email'];
            
            // Generate QR code URL
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                $companyName,
                $userEmail,
                $secret
            );
            
            // Store secret in session temporarily
            $_SESSION['temp_2fa_secret'] = $secret;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'secret' => $secret,
                    'qr_code_url' => $qrCodeUrl
                ]
            ]);
            break;
            
        case 'verify_2fa':
            // Get the temporary secret from session
            $secret = $_SESSION['temp_2fa_secret'] ?? null;
            $code = $_POST['verification_code'] ?? '';
            
            if (!$secret) {
                echo json_encode(['success' => false, 'message' => 'No secret found. Please regenerate QR code.']);
                exit();
            }
            
            // Verify the code
            $valid = $google2fa->verifyKey($secret, $code);
            
            if ($valid) {
                // Generate backup codes
                $backupCodes = [];
                for ($i = 0; $i < 10; $i++) {
                    $backupCode = strtoupper(bin2hex(random_bytes(4)));
                    $backupCodes[] = $backupCode;
                }
                
                // Hash backup codes for storage
                $hashedBackupCodes = array_map(function($code) {
                    return password_hash($code, PASSWORD_DEFAULT);
                }, $backupCodes);
                
                // Save to database
                $stmt = $pdo->prepare("
                    UPDATE security_settings 
                    SET two_factor_enabled = 1, 
                        two_factor_secret = ?, 
                        backup_codes = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$secret, json_encode($hashedBackupCodes), $user_id]);
                
                // Clear temporary secret
                unset($_SESSION['temp_2fa_secret']);
                
                // Log activity
                logUserActivity($user_id, '2fa_enabled', 'Two-factor authentication enabled', $_SERVER['REMOTE_ADDR']);
                
                echo json_encode([
                    'success' => true,
                    'message' => '2FA enabled successfully!',
                    'data' => [
                        'backup_codes' => $backupCodes
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
            }
            break;
            
        case 'disable_2fa':
            // Disable 2FA
            $stmt = $pdo->prepare("
                UPDATE security_settings 
                SET two_factor_enabled = 0, 
                    two_factor_secret = NULL, 
                    backup_codes = NULL,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            
            // Log activity
            logUserActivity($user_id, '2fa_disabled', 'Two-factor authentication disabled', $_SERVER['REMOTE_ADDR']);
            
            echo json_encode([
                'success' => true,
                'message' => '2FA has been disabled successfully.'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log('2FA Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>