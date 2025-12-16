<?php
require_once '../config_user.php';
require_once '../User.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Unauthorized access');
}

$user = new User();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'change_password':
        try {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                jsonResponse(false, 'All fields are required');
            }

            if ($newPassword !== $confirmPassword) {
                jsonResponse(false, 'New passwords do not match');
            }

            if (strlen($newPassword) < 8) {
                jsonResponse(false, 'Password must be at least 8 characters long');
            }

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $newPassword)) {
                jsonResponse(false, 'Password must contain uppercase, lowercase, and numbers');
            }

            // Verify current password
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();

            if (!verifyPassword($currentPassword, $userData['password'])) {
                jsonResponse(false, 'Current password is incorrect');
            }

            // Update password
            $hashedPassword = hashPassword($newPassword);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            
            if ($stmt->execute([$hashedPassword, $userId])) {
                // Update security settings
                $stmt = $db->prepare("
                    INSERT INTO security_settings (user_id, last_password_change)
                    VALUES (?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE last_password_change = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$userId]);

                logUserActivity($userId, 'password_change', 'Password changed successfully');
                jsonResponse(true, 'Password changed successfully');
            } else {
                jsonResponse(false, 'Failed to update password');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'get_security_settings':
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT * FROM security_settings WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $settings = $stmt->fetch();

            if (!$settings) {
                // Create default settings
                $stmt = $db->prepare("
                    INSERT INTO security_settings (user_id) VALUES (?)
                ");
                $stmt->execute([$userId]);
                
                $stmt = $db->prepare("SELECT * FROM security_settings WHERE user_id = ?");
                $stmt->execute([$userId]);
                $settings = $stmt->fetch();
            }

            jsonResponse(true, 'Security settings retrieved', $settings);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'update_2fa':
        try {
            $twoFactorEnabled = isset($_POST['two_factor_enabled']) ? 1 : 0;

            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE security_settings 
                SET two_factor_enabled = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");

            if ($stmt->execute([$twoFactorEnabled, $userId])) {
                $action = $twoFactorEnabled ? 'enabled' : 'disabled';
                logUserActivity($userId, '2fa_update', "Two-factor authentication $action");
                jsonResponse(true, "Two-factor authentication $action successfully");
            } else {
                jsonResponse(false, 'Failed to update 2FA settings');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'update_login_alerts':
        try {
            $loginAlerts = isset($_POST['login_alerts']) ? 1 : 0;

            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE security_settings 
                SET login_alerts = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");

            if ($stmt->execute([$loginAlerts, $userId])) {
                logUserActivity($userId, 'security_update', 'Login alerts updated');
                jsonResponse(true, 'Login alerts updated successfully');
            } else {
                jsonResponse(false, 'Failed to update login alerts');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'get_login_sessions':
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT * FROM login_sessions 
                WHERE user_id = ? AND is_active = 1
                ORDER BY login_time DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $sessions = $stmt->fetchAll();

            jsonResponse(true, 'Login sessions retrieved', $sessions);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'revoke_session':
        try {
            $sessionId = intval($_POST['session_id'] ?? 0);

            if (!$sessionId) {
                jsonResponse(false, 'Invalid session ID');
            }

            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE login_sessions 
                SET is_active = 0, logout_time = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
            ");

            if ($stmt->execute([$sessionId, $userId])) {
                logUserActivity($userId, 'session_revoke', "Session #$sessionId revoked");
                jsonResponse(true, 'Session revoked successfully');
            } else {
                jsonResponse(false, 'Failed to revoke session');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'revoke_all_sessions':
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE login_sessions 
                SET is_active = 0, logout_time = CURRENT_TIMESTAMP
                WHERE user_id = ? AND is_active = 1
            ");

            if ($stmt->execute([$userId])) {
                logUserActivity($userId, 'all_sessions_revoke', 'All sessions revoked');
                jsonResponse(true, 'All sessions revoked successfully');
            } else {
                jsonResponse(false, 'Failed to revoke sessions');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'get_security_events':
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT activity_type, activity_description, ip_address, created_at
                FROM user_activity_log 
                WHERE user_id = ? 
                AND activity_type IN ('login', 'logout', 'password_change', '2fa_update', 'security_update')
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$userId]);
            $events = $stmt->fetchAll();

            jsonResponse(true, 'Security events retrieved', $events);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'generate_backup_codes':
        try {
            $codes = [];
            for ($i = 0; $i < 10; $i++) {
                $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            }

            // Store hashed codes in database
            $db = Database::getInstance();
            $hashedCodes = array_map(function($code) {
                return password_hash($code, PASSWORD_DEFAULT);
            }, $codes);

            $stmt = $db->prepare("
                UPDATE security_settings 
                SET backup_codes = ?
                WHERE user_id = ?
            ");

            if ($stmt->execute([json_encode($hashedCodes), $userId])) {
                logUserActivity($userId, 'backup_codes_generated', 'Backup codes generated');
                jsonResponse(true, 'Backup codes generated successfully', ['codes' => $codes]);
            } else {
                jsonResponse(false, 'Failed to generate backup codes');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    default:
        jsonResponse(false, 'Invalid action');
}
?>