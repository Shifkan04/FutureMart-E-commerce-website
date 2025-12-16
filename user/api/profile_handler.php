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
    case 'get_profile':
        $userData = $user->getUserById($userId);
        if ($userData) {
            // Remove sensitive data
            unset($userData['password']);
            jsonResponse(true, 'Profile data retrieved', $userData);
        } else {
            jsonResponse(false, 'Failed to retrieve profile data');
        }
        break;

    case 'update_profile':
        try {
            $data = [
                'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
                'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
                'email' => sanitizeInput($_POST['email'] ?? ''),
                'phone' => sanitizeInput($_POST['phone'] ?? ''),
                'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
                'gender' => sanitizeInput($_POST['gender'] ?? ''),
                'bio' => sanitizeInput($_POST['bio'] ?? '')
            ];

            // Validate required fields
            if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
                jsonResponse(false, 'Please fill all required fields');
            }

            if (!isValidEmail($data['email'])) {
                jsonResponse(false, 'Invalid email address');
            }

            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE users SET 
                    first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    date_of_birth = ?,
                    gender = ?,
                    bio = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            if ($stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['date_of_birth'],
                $data['gender'],
                $data['bio'],
                $userId
            ])) {
                logUserActivity($userId, 'profile_update', 'Profile information updated');
                jsonResponse(true, 'Profile updated successfully', $data);
            } else {
                jsonResponse(false, 'Failed to update profile');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'upload_avatar':
        try {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(false, 'No file uploaded or upload error');
            }

            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowedTypes)) {
                jsonResponse(false, 'Invalid file type. Only JPG, PNG, GIF, and WEBP allowed');
            }

            if ($file['size'] > $maxSize) {
                jsonResponse(false, 'File too large. Maximum size is 5MB');
            }

            // Create avatar directory if not exists
            $avatarDir = '../uploads/avatars/';
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $userId . '_' . time() . '.' . $extension;
            $targetPath = $avatarDir . $filename;

            // Delete old avatar if exists
            $currentUser = $user->getUserById($userId);
            if (!empty($currentUser['avatar'])) {
                $oldFile = $avatarDir . $currentUser['avatar'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                
                if ($stmt->execute([$filename, $userId])) {
                    logUserActivity($userId, 'avatar_update', 'Profile picture updated');
                    jsonResponse(true, 'Avatar uploaded successfully', [
                        'avatar' => $filename,
                        'avatar_url' => '../uploads/avatars/' . $filename
                    ]);
                } else {
                    unlink($targetPath);
                    jsonResponse(false, 'Failed to update database');
                }
            } else {
                jsonResponse(false, 'Failed to move uploaded file');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'update_preferences':
        try {
            $theme = sanitizeInput($_POST['theme'] ?? 'dark');
            $language = sanitizeInput($_POST['language'] ?? 'en');
            $currency = sanitizeInput($_POST['currency'] ?? 'USD');
            $dateFormat = sanitizeInput($_POST['date_format'] ?? 'MM/DD/YYYY');
            $timeFormat = sanitizeInput($_POST['time_format'] ?? '12h');

            $db = Database::getInstance();
            
            // Update user preferences
            $stmt = $db->prepare("
                UPDATE users SET 
                    theme_preference = ?,
                    language = ?,
                    currency_preference = ?,
                    date_format = ?,
                    time_format = ?
                WHERE id = ?
            ");

            if ($stmt->execute([$theme, $language, $currency, $dateFormat, $timeFormat, $userId])) {
                // Also update user_preferences table
                $stmt2 = $db->prepare("
                    INSERT INTO user_preferences (user_id, language, currency, date_format, time_format)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        language = VALUES(language),
                        currency = VALUES(currency),
                        date_format = VALUES(date_format),
                        time_format = VALUES(time_format)
                ");
                $stmt2->execute([$userId, $language, $currency, $dateFormat, $timeFormat]);

                logUserActivity($userId, 'preferences_update', 'Updated account preferences');
                jsonResponse(true, 'Preferences updated successfully');
            } else {
                jsonResponse(false, 'Failed to update preferences');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'update_notifications':
        try {
            $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
            $smsNotif = isset($_POST['sms_notifications']) ? 1 : 0;
            $orderUpdates = isset($_POST['order_updates']) ? 1 : 0;
            $promotional = isset($_POST['promotional_emails']) ? 1 : 0;
            $productAlerts = isset($_POST['new_product_alerts']) ? 1 : 0;
            $priceDrops = isset($_POST['price_drop_alerts']) ? 1 : 0;
            $newsletter = isset($_POST['newsletter']) ? 1 : 0;

            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO user_notifications 
                (user_id, order_updates, promotional_emails, sms_notifications, 
                 new_product_alerts, price_drop_alerts, newsletter)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    order_updates = VALUES(order_updates),
                    promotional_emails = VALUES(promotional_emails),
                    sms_notifications = VALUES(sms_notifications),
                    new_product_alerts = VALUES(new_product_alerts),
                    price_drop_alerts = VALUES(price_drop_alerts),
                    newsletter = VALUES(newsletter)
            ");

            if ($stmt->execute([$userId, $orderUpdates, $promotional, $smsNotif, $productAlerts, $priceDrops, $newsletter])) {
                logUserActivity($userId, 'preferences_update', 'Notification preferences updated');
                jsonResponse(true, 'Notification preferences updated successfully');
            } else {
                jsonResponse(false, 'Failed to update notification preferences');
            }
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    case 'get_notifications':
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT * FROM user_notifications WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetch();

            if (!$notifications) {
                // Create default notifications settings
                $stmt = $db->prepare("
                    INSERT INTO user_notifications (user_id) VALUES (?)
                ");
                $stmt->execute([$userId]);
                
                $stmt = $db->prepare("SELECT * FROM user_notifications WHERE user_id = ?");
                $stmt->execute([$userId]);
                $notifications = $stmt->fetch();
            }

            jsonResponse(true, 'Notifications retrieved', $notifications);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage());
        }
        break;

    default:
        jsonResponse(false, 'Invalid action');
}
?>