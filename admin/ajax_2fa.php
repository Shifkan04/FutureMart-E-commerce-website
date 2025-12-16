<?php
require_once '../config.php';

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'generate_2fa_secret':
        generate2FASecret($userId, $pdo);
        break;
        
    case 'verify_2fa':
        verify2FA($userId, $pdo);
        break;
        
    case 'disable_2fa':
        disable2FA($userId, $pdo);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Generate 2FA Secret
 */
function generate2FASecret($userId, $pdo) {
    try {
        // Get user data
        $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        // Generate cryptographically secure secret
        $secret = generateBase32Secret(32);
        
        // Store secret temporarily in session
        $_SESSION['temp_2fa_secret'] = $secret;
        $_SESSION['temp_2fa_user_id'] = $userId;
        
        // Create proper OTP Auth URL
        $appName = APP_NAME;
        $userIdentifier = $user['email'];
        $issuer = APP_NAME;
        
        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($appName),
            rawurlencode($userIdentifier),
            $secret,
            rawurlencode($issuer)
        );
        
        echo json_encode([
            'success' => true,
            'message' => '2FA secret generated successfully',
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'user_email' => $user['email']
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("2FA Generation Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error generating 2FA secret']);
    }
}

/**
 * Verify 2FA Code
 */
function verify2FA($userId, $pdo) {
    try {
        $verificationCode = trim($_POST['verification_code'] ?? '');
        
        if (empty($verificationCode) || !preg_match('/^\d{6}$/', $verificationCode)) {
            echo json_encode(['success' => false, 'message' => 'Invalid code format']);
            exit();
        }
        
        // Get temporary secret from session
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        $sessionUserId = $_SESSION['temp_2fa_user_id'] ?? null;
        
        if (empty($secret) || $sessionUserId != $userId) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please restart setup.']);
            exit();
        }
        
        // Verify the code
        if (!verifyTOTP($secret, $verificationCode, 2)) {
            echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
            exit();
        }
        
        // Generate backup codes
        $backupCodes = generateBackupCodes(10);
        $hashedBackupCodes = array_map(function($code) {
            return password_hash($code, PASSWORD_DEFAULT);
        }, $backupCodes);
        
        // Update or insert security settings
        $stmt = $pdo->prepare("SELECT id FROM security_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE security_settings 
                SET two_factor_enabled = 1,
                    two_factor_secret = ?,
                    backup_codes = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$secret, json_encode($hashedBackupCodes), $userId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO security_settings 
                (user_id, two_factor_enabled, two_factor_secret, backup_codes)
                VALUES (?, 1, ?, ?)
            ");
            $stmt->execute([$userId, $secret, json_encode($hashedBackupCodes)]);
        }
        
        // Clear temporary session data
        unset($_SESSION['temp_2fa_secret']);
        unset($_SESSION['temp_2fa_user_id']);
        
        // Log activity
        logUserActivity($userId, '2fa_enabled', 'Two-factor authentication enabled');
        
        echo json_encode([
            'success' => true,
            'message' => '2FA enabled successfully!',
            'data' => ['backup_codes' => $backupCodes]
        ]);
        
    } catch (Exception $e) {
        error_log("2FA Verification Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error verifying 2FA']);
    }
}

/**
 * Disable 2FA
 */
function disable2FA($userId, $pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE security_settings 
            SET two_factor_enabled = 0,
                two_factor_secret = NULL,
                backup_codes = NULL,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        
        if ($stmt->execute([$userId])) {
            logUserActivity($userId, '2fa_disabled', 'Two-factor authentication disabled');
            echo json_encode(['success' => true, 'message' => '2FA disabled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to disable 2FA']);
        }
        
    } catch (Exception $e) {
        error_log("2FA Disable Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error disabling 2FA']);
    }
}

/**
 * Helper Functions
 */

function generateBase32Secret($length = 32) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $charsLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, $charsLength - 1)];
    }
    
    return $secret;
}

function generateBackupCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
    }
    return $codes;
}

function verifyTOTP($secret, $code, $window = 2) {
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    $timeSlice = floor(time() / 30);
    
    for ($i = -$window; $i <= $window; $i++) {
        $calculatedCode = getTOTPCode($secret, $timeSlice + $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

function getTOTPCode($secret, $timeSlice) {
    try {
        $key = base32Decode($secret);
        if ($key === false) {
            throw new Exception('Invalid secret key');
        }
        
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        error_log("TOTP Generation Error: " . $e->getMessage());
        return '000000';
    }
}

function base32Decode($secret) {
    if (empty($secret)) {
        return false;
    }
    
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32charsFlipped = array_flip(str_split($base32chars));
    
    $secret = strtoupper($secret);
    $secret = str_replace('=', '', $secret);
    
    $binaryString = '';
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        
        if (!isset($base32charsFlipped[$char])) {
            return false;
        }
        
        $binaryString .= str_pad(decbin($base32charsFlipped[$char]), 5, '0', STR_PAD_LEFT);
    }
    
    $output = '';
    
    for ($i = 0; $i < strlen($binaryString); $i += 8) {
        $chunk = substr($binaryString, $i, 8);
        
        if (strlen($chunk) == 8) {
            $output .= chr(bindec($chunk));
        }
    }
    
    return $output;
}