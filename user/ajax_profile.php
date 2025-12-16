<?php
/**
 * AJAX Profile Handler
 * Location: user/ajax_profile.php
 */

require_once '../config_user.php';
require_once '../User.php';

startSecureSession();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Please login to continue');
}

$userId = $_SESSION['user_id'];
$user = new User();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'upload_avatar':
            // Check if file was uploaded
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(false, 'No file uploaded or upload error occurred');
            }
            
            $file = $_FILES['avatar'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                jsonResponse(false, 'Invalid file type. Only JPG, PNG, and GIF are allowed');
            }
            
            // Validate file size (5MB)
            $maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if ($file['size'] > $maxSize) {
                jsonResponse(false, 'File size too large. Maximum 5MB allowed');
            }
            
            // Create upload directory if it doesn't exist
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = $userId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $fileName;
            
            // Delete old avatar if exists
            $userData = $user->getUserById($userId);
            if (!empty($userData['avatar'])) {
                $oldFile = $uploadDir . $userData['avatar'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Update database
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                
                if ($stmt->execute([$fileName, $userId])) {
                    // Log activity
                    logUserActivity($userId, 'avatar_update', 'Profile picture updated');
                    
                    jsonResponse(true, 'Profile picture updated successfully', [
                        'avatar' => $fileName,
                        'avatar_url' => '../uploads/avatars/' . $fileName
                    ]);
                } else {
                    // Delete uploaded file if database update fails
                    unlink($uploadPath);
                    jsonResponse(false, 'Failed to update database');
                }
            } else {
                jsonResponse(false, 'Failed to upload file');
            }
            break;
            
        case 'remove_avatar':
            // Get current user data
            $userData = $user->getUserById($userId);
            
            if (!empty($userData['avatar'])) {
                // Delete file
                $avatarPath = '../uploads/avatars/' . $userData['avatar'];
                if (file_exists($avatarPath)) {
                    unlink($avatarPath);
                }
                
                // Update database
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
                
                if ($stmt->execute([$userId])) {
                    // Log activity
                    logUserActivity($userId, 'avatar_remove', 'Profile picture removed');
                    
                    jsonResponse(true, 'Profile picture removed successfully');
                } else {
                    jsonResponse(false, 'Failed to remove avatar from database');
                }
            } else {
                jsonResponse(false, 'No profile picture to remove');
            }
            break;
            
        case 'update_personal_info':
            $data = [
                'first_name' => sanitizeInput($_POST['first_name']),
                'last_name' => sanitizeInput($_POST['last_name']),
                'email' => sanitizeInput($_POST['email']),
                'phone' => sanitizeInput($_POST['phone'] ?? ''),
                'date_of_birth' => $_POST['date_of_birth'] ?: null,
                'gender' => $_POST['gender'] ?: null,
                'bio' => sanitizeInput($_POST['bio'] ?? '')
            ];
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, 'Invalid email address');
            }
            
            // Check if email is already taken by another user
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $userId]);
            if ($stmt->fetch()) {
                jsonResponse(false, 'Email address is already in use');
            }
            
            // Update user
            $updateStmt = $db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                    date_of_birth = ?, gender = ?, bio = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($updateStmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['date_of_birth'],
                $data['gender'],
                $data['bio'],
                $userId
            ])) {
                // Update session
                $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
                $_SESSION['user_email'] = $data['email'];
                
                // Log activity
                logUserActivity($userId, 'profile_update', 'Profile information updated');
                
                jsonResponse(true, 'Profile information updated successfully', $data);
            } else {
                jsonResponse(false, 'Failed to update profile');
            }
            break;
            
        case 'update_preferences':
            $preferences = [
                'language' => sanitizeInput($_POST['language'] ?? 'en_US'),
                'currency_preference' => sanitizeInput($_POST['currency_preference'] ?? 'USD'),
                'date_format' => sanitizeInput($_POST['date_format'] ?? 'MM/DD/YYYY'),
                'time_format' => sanitizeInput($_POST['time_format'] ?? '12h')
            ];
            
            // Update user preferences
            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE users 
                SET language = ?, currency_preference = ?, date_format = ?, time_format = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $preferences['language'],
                $preferences['currency_preference'],
                $preferences['date_format'],
                $preferences['time_format'],
                $userId
            ])) {
                // Also save to user_preferences table if exists
                $checkStmt = $db->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
                $checkStmt->execute([$userId]);
                
                if ($checkStmt->fetch()) {
                    // Update existing
                    $updatePrefStmt = $db->prepare("
                        UPDATE user_preferences 
                        SET language = ?, currency = ?, date_format = ?, time_format = ?
                        WHERE user_id = ?
                    ");
                    $updatePrefStmt->execute([
                        $preferences['language'],
                        $preferences['currency_preference'],
                        $preferences['date_format'],
                        $preferences['time_format'],
                        $userId
                    ]);
                } else {
                    // Insert new
                    $insertPrefStmt = $db->prepare("
                        INSERT INTO user_preferences (user_id, language, currency, date_format, time_format)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insertPrefStmt->execute([
                        $userId,
                        $preferences['language'],
                        $preferences['currency_preference'],
                        $preferences['date_format'],
                        $preferences['time_format']
                    ]);
                }
                
                // Log activity
                logUserActivity($userId, 'preferences_update', 'Account preferences updated');
                
                jsonResponse(true, 'Preferences updated successfully', $preferences);
            } else {
                jsonResponse(false, 'Failed to update preferences');
            }
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Profile AJAX Error: " . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again.');
}