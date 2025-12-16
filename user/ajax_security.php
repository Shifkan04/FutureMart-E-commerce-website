<?php
require_once '../config_user.php';
require_once '../User.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Unauthorized access');
}

$user = new User();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'change_password':
        changePassword($user, $userId);
        break;
        
    case 'generate_2fa_secret':
        generate2FASecret($userId);
        break;
        
    case 'verify_2fa':
        verify2FA($userId);
        break;
        
    case 'disable_2fa':
        disable2FA($userId);
        break;
        
    case 'update_security_preferences':
        updateSecurityPreferences($userId);
        break;
        
    case 'terminate_all_sessions':
        terminateAllSessions($userId);
        break;
        
    default:
        jsonResponse(false, 'Invalid action');
}

/**
 * Change Password
 */
function changePassword($user, $userId) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonResponse(false, 'Please fill in all fields');
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, 'New passwords do not match');
    }
    
    if (strlen($newPassword) < 8) {
        jsonResponse(false, 'Password must be at least 8 characters long');
    }
    
    // Get user data
    $userData = $user->getUserById($userId);
    
    // Verify current password
    if (!verifyPassword($currentPassword, $userData['password'])) {
        jsonResponse(false, 'Current password is incorrect');
    }
    
    // Hash new password
    $hashedPassword = hashPassword($newPassword);
    
    // Update password in database
    $db = Database::getInstance();
    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    
    if ($stmt->execute([$hashedPassword, $userId])) {
        // Update security settings
        $stmt = $db->prepare("
            UPDATE security_settings 
            SET last_password_change = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        // If no security settings exist, create them
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare("
                INSERT INTO security_settings (user_id, last_password_change) 
                VALUES (?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$userId]);
        }
        
        // Log activity
        logUserActivity($userId, 'password_change', 'Password changed successfully', getUserIP());
        
        jsonResponse(true, 'Password changed successfully');
    } else {
        jsonResponse(false, 'Failed to change password');
    }
}

/**
 * Generate 2FA Secret - FIXED VERSION
 */
function generate2FASecret($userId) {
    try {
        $db = Database::getInstance();
        
        // Get user data
        $stmt = $db->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found');
        }
        
        // Generate cryptographically secure secret (32 characters, base32)
        $secret = generateBase32Secret(32);
        
        // Store secret temporarily in session
        $_SESSION['temp_2fa_secret'] = $secret;
        $_SESSION['temp_2fa_user_id'] = $userId;
        
        // Create proper OTP Auth URL
        $appName = 'FutureMart';
        $userIdentifier = $user['email'];
        $issuer = 'FutureMart';
        
        // Proper OTP Auth format
        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($appName),
            rawurlencode($userIdentifier),
            $secret,
            rawurlencode($issuer)
        );
        
        jsonResponse(true, '2FA secret generated successfully', [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'user_email' => $user['email']
        ]);
        
    } catch (Exception $e) {
        error_log("2FA Generation Error: " . $e->getMessage());
        jsonResponse(false, 'Error generating 2FA secret: ' . $e->getMessage());
    }
}

/**
 * Verify 2FA Code - FIXED VERSION
 */
function verify2FA($userId) {
    try {
        $verificationCode = trim($_POST['verification_code'] ?? '');
        
        // Validate code format
        if (empty($verificationCode)) {
            jsonResponse(false, 'Please enter a verification code');
        }
        
        if (!preg_match('/^\d{6}$/', $verificationCode)) {
            jsonResponse(false, 'Invalid code format. Please enter a 6-digit code.');
        }
        
        // Get temporary secret from session
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        $sessionUserId = $_SESSION['temp_2fa_user_id'] ?? null;
        
        if (empty($secret) || $sessionUserId != $userId) {
            jsonResponse(false, 'Session expired. Please restart the 2FA setup process.');
        }
        
        // Verify the code with wider time window for better reliability
        if (!verifyTOTP($secret, $verificationCode, 2)) {
            jsonResponse(false, 'Invalid verification code. Please try again or scan the QR code again.');
        }
        
        // Code is valid, save to database
        $db = Database::getInstance();
        
        // Generate backup codes
        $backupCodes = generateBackupCodes(10);
        $hashedBackupCodes = array_map(function($code) {
            return password_hash($code, PASSWORD_DEFAULT);
        }, $backupCodes);
        
        // Update or insert security settings
        $stmt = $db->prepare("SELECT id FROM security_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE security_settings 
                SET two_factor_enabled = 1,
                    two_factor_secret = ?,
                    backup_codes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            $stmt->execute([$secret, json_encode($hashedBackupCodes), $userId]);
        } else {
            // Insert new
            $stmt = $db->prepare("
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
        logUserActivity($userId, '2fa_enabled', 'Two-factor authentication enabled', getUserIP());
        
        jsonResponse(true, 'Two-factor authentication enabled successfully!', [
            'backup_codes' => $backupCodes
        ]);
        
    } catch (Exception $e) {
        error_log("2FA Verification Error: " . $e->getMessage());
        jsonResponse(false, 'Error verifying 2FA: ' . $e->getMessage());
    }
}

/**
 * Disable 2FA
 */
function disable2FA($userId) {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        UPDATE security_settings 
        SET two_factor_enabled = 0,
            two_factor_secret = NULL,
            backup_codes = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ?
    ");
    
    if ($stmt->execute([$userId])) {
        // Log activity
        logUserActivity($userId, '2fa_disabled', 'Two-factor authentication disabled', getUserIP());
        
        jsonResponse(true, 'Two-factor authentication disabled successfully');
    } else {
        jsonResponse(false, 'Failed to disable two-factor authentication');
    }
}

/**
 * Update Security Preferences
 */
function updateSecurityPreferences($userId) {
    $loginAlerts = isset($_POST['login_alerts']) && $_POST['login_alerts'] == '1' ? 1 : 0;
    $sessionTimeout = intval($_POST['session_timeout'] ?? 3600);
    
    // Validate session timeout
    $validTimeouts = [900, 1800, 3600, 7200];
    if (!in_array($sessionTimeout, $validTimeouts)) {
        $sessionTimeout = 3600;
    }
    
    $db = Database::getInstance();
    
    // Check if settings exist
    $stmt = $db->prepare("SELECT id FROM security_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    if ($stmt->fetch()) {
        // Update existing
        $stmt = $db->prepare("
            UPDATE security_settings 
            SET login_alerts = ?,
                session_timeout = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $result = $stmt->execute([$loginAlerts, $sessionTimeout, $userId]);
    } else {
        // Insert new
        $stmt = $db->prepare("
            INSERT INTO security_settings (user_id, login_alerts, session_timeout)
            VALUES (?, ?, ?)
        ");
        $result = $stmt->execute([$userId, $loginAlerts, $sessionTimeout]);
    }
    
    if ($result) {
        // Log activity
        logUserActivity($userId, 'security_preferences_update', 'Security preferences updated', getUserIP());
        
        jsonResponse(true, 'Security preferences updated successfully');
    } else {
        jsonResponse(false, 'Failed to update security preferences');
    }
}

/**
 * Terminate All Sessions
 */
function terminateAllSessions($userId) {
    $db = Database::getInstance();
    
    // Update all active sessions to inactive
    $stmt = $db->prepare("
        UPDATE login_sessions 
        SET is_active = 0,
            logout_time = CURRENT_TIMESTAMP
        WHERE user_id = ? AND is_active = 1
    ");
    
    if ($stmt->execute([$userId])) {
        // Log activity
        logUserActivity($userId, 'all_sessions_terminated', 'All sessions terminated', getUserIP());
        
        // Destroy current session
        session_destroy();
        
        jsonResponse(true, 'All sessions terminated successfully. You will be logged out.');
    } else {
        jsonResponse(false, 'Failed to terminate sessions');
    }
}

/**
 * Helper Functions - IMPROVED VERSIONS
 */

// Generate Base32 Secret - IMPROVED
function generateBase32Secret($length = 32) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $charsLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, $charsLength - 1)];
    }
    
    return $secret;
}

// Generate Backup Codes - IMPROVED
function generateBackupCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
    }
    return $codes;
}

// Verify TOTP Code - FIXED VERSION
function verifyTOTP($secret, $code, $window = 2) {
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    $timeSlice = floor(time() / 30);
    
    // Check current time and time windows before and after
    for ($i = -$window; $i <= $window; $i++) {
        $calculatedCode = getTOTPCode($secret, $timeSlice + $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

// Get TOTP Code - IMPROVED VERSION
function getTOTPCode($secret, $timeSlice) {
    try {
        $key = base32Decode($secret);
        if ($key === false) {
            throw new Exception('Invalid secret key');
        }
        
        // Pack time as 64-bit big-endian
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        
        // Generate HMAC
        $hash = hash_hmac('sha1', $time, $key, true);
        
        // Dynamic truncation
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

// Base32 Decode - IMPROVED VERSION
function base32Decode($secret) {
    if (empty($secret)) {
        return false;
    }
    
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32charsFlipped = array_flip(str_split($base32chars));
    
    $secret = strtoupper($secret);
    $secret = str_replace('=', '', $secret); // Remove padding
    
    $binaryString = '';
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        
        if (!isset($base32charsFlipped[$char])) {
            return false; // Invalid character
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