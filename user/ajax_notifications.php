<?php
require_once '../config_user.php';
require_once '../User.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Unauthorized access');
}

$user = new User();
$userId = $_SESSION['user_id'];
$userData = $user->getUserById($userId);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'mark_as_read':
        markAsRead($userId, $userData);
        break;
        
    case 'mark_all_as_read':
        markAllAsRead($userId, $userData);
        break;
        
    case 'delete_notification':
        deleteNotification($userId, $userData);
        break;
        
    case 'clear_all_notifications':
        clearAllNotifications($userId, $userData);
        break;
        
    case 'get_message_details':
        getMessageDetails($userId, $userData);
        break;
        
    case 'send_reply':
        sendReply($userId, $userData);
        break;
        
    default:
        jsonResponse(false, 'Invalid action');
}

/**
 * Mark Single Notification as Read
 */
function markAsRead($userId, $userData) {
    $notificationId = intval($_POST['notification_id'] ?? 0);
    $notificationType = $_POST['notification_type'] ?? '';
    
    if ($notificationId <= 0) {
        jsonResponse(false, 'Invalid notification ID');
    }
    
    $db = Database::getInstance();
    
    try {
        if ($notificationType === 'message') {
            // Update admin_messages table
            $stmt = $db->prepare("
                UPDATE admin_messages 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND recipient_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            
        } elseif ($notificationType === 'contact' && $userData['role'] === 'admin') {
            // Update contact_messages table
            $stmt = $db->prepare("
                UPDATE contact_messages 
                SET status = 'in_progress' 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$notificationId]);
        }
        
        jsonResponse(true, 'Notification marked as read');
        
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        jsonResponse(false, 'Error marking notification as read');
    }
}

/**
 * Mark All Notifications as Read
 */
function markAllAsRead($userId, $userData) {
    $db = Database::getInstance();
    
    try {
        // Mark all admin_messages as read
        $stmt = $db->prepare("
            UPDATE admin_messages 
            SET is_read = 1, read_at = NOW() 
            WHERE recipient_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        
        // If admin, update contact messages
        if ($userData['role'] === 'admin') {
            $stmt = $db->prepare("
                UPDATE contact_messages 
                SET status = 'in_progress' 
                WHERE status = 'pending'
            ");
            $stmt->execute();
        }
        
        jsonResponse(true, 'All notifications marked as read');
        
    } catch (Exception $e) {
        error_log("Error marking all as read: " . $e->getMessage());
        jsonResponse(false, 'Error marking notifications as read');
    }
}

/**
 * Delete Single Notification
 */
function deleteNotification($userId, $userData) {
    $notificationId = intval($_POST['notification_id'] ?? 0);
    $notificationType = $_POST['notification_type'] ?? '';
    
    if ($notificationId <= 0) {
        jsonResponse(false, 'Invalid notification ID');
    }
    
    $db = Database::getInstance();
    
    try {
        if ($notificationType === 'message') {
            // Verify and delete from admin_messages
            $stmt = $db->prepare("
                DELETE FROM admin_messages 
                WHERE id = ? AND recipient_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            
        } elseif ($notificationType === 'contact' && $userData['role'] === 'admin') {
            // Update contact_messages status to closed
            $stmt = $db->prepare("
                UPDATE contact_messages 
                SET status = 'closed' 
                WHERE id = ?
            ");
            $stmt->execute([$notificationId]);
            
        } elseif ($notificationType === 'activity') {
            // Delete from user_activity_log
            $stmt = $db->prepare("
                DELETE FROM user_activity_log 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
        }
        
        jsonResponse(true, 'Notification deleted successfully');
        
    } catch (Exception $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        jsonResponse(false, 'Error deleting notification');
    }
}

/**
 * Clear All Notifications
 */
function clearAllNotifications($userId, $userData) {
    $db = Database::getInstance();
    
    try {
        // Delete all admin_messages for user
        $stmt = $db->prepare("DELETE FROM admin_messages WHERE recipient_id = ?");
        $stmt->execute([$userId]);
        
        // Delete all user_activity_log for user
        $stmt = $db->prepare("DELETE FROM user_activity_log WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // If admin, close all pending contact messages
        if ($userData['role'] === 'admin') {
            $stmt = $db->prepare("
                UPDATE contact_messages 
                SET status = 'closed' 
                WHERE status IN ('pending', 'in_progress')
            ");
            $stmt->execute();
        }
        
        jsonResponse(true, 'All notifications cleared successfully');
        
    } catch (Exception $e) {
        error_log("Error clearing notifications: " . $e->getMessage());
        jsonResponse(false, 'Error clearing notifications');
    }
}

/**
 * Get Message Details for Reply Modal
 */
function getMessageDetails($userId, $userData) {
    $notificationId = intval($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
    $notificationType = $_POST['notification_type'] ?? $_GET['notification_type'] ?? '';
    
    if ($notificationId <= 0) {
        jsonResponse(false, 'Invalid notification ID');
    }
    
    $db = Database::getInstance();
    
    try {
        $messageData = [];
        
        if ($notificationType === 'message') {
            // Get from admin_messages
            $stmt = $db->prepare("
                SELECT 
                    am.*,
                    u.first_name as sender_first_name,
                    u.last_name as sender_last_name,
                    u.email as sender_email
                FROM admin_messages am
                LEFT JOIN users u ON am.sender_id = u.id
                WHERE am.id = ?
            ");
            $stmt->execute([$notificationId]);
            $message = $stmt->fetch();
            
            if (!$message) {
                jsonResponse(false, 'Message not found');
            }
            
            $messageData = [
                'id' => $message['id'],
                'sender_name' => ($message['sender_first_name'] ?? 'System') . ' ' . ($message['sender_last_name'] ?? ''),
                'sender_email' => $message['sender_email'] ?? '',
                'subject' => $message['subject'],
                'message' => $message['message'],
                'priority' => $message['priority'],
                'created_at' => $message['created_at']
            ];
            
            // Get thread if exists
            $parentId = $message['parent_message_id'] ?? $message['id'];
            $stmt = $db->prepare("
                SELECT 
                    am.*,
                    u.first_name as sender_first_name,
                    u.last_name as sender_last_name
                FROM admin_messages am
                LEFT JOIN users u ON am.sender_id = u.id
                WHERE (am.id = ? OR am.parent_message_id = ?)
                ORDER BY am.created_at ASC
            ");
            $stmt->execute([$parentId, $parentId]);
            $thread = $stmt->fetchAll();
            
            $messageData['thread'] = array_map(function($msg) {
                return [
                    'sender_name' => ($msg['sender_first_name'] ?? 'System') . ' ' . ($msg['sender_last_name'] ?? ''),
                    'sender_type' => $msg['sender_type'],
                    'message' => $msg['message'],
                    'created_at' => $msg['created_at']
                ];
            }, $thread);
            
        } elseif ($notificationType === 'contact') {
            // Get from contact_messages
            $stmt = $db->prepare("
                SELECT * FROM contact_messages WHERE id = ?
            ");
            $stmt->execute([$notificationId]);
            $message = $stmt->fetch();
            
            if (!$message) {
                jsonResponse(false, 'Message not found');
            }
            
            $messageData = [
                'id' => $message['id'],
                'sender_name' => $message['sender_name'],
                'sender_email' => $message['sender_email'],
                'subject' => $message['subject'],
                'message' => $message['message'],
                'priority' => $message['priority'],
                'created_at' => $message['created_at']
            ];
            
            // Get notes/replies
            $stmt = $db->prepare("
                SELECT 
                    cn.*,
                    u.first_name,
                    u.last_name
                FROM contact_notes cn
                LEFT JOIN users u ON cn.admin_id = u.id
                WHERE cn.contact_message_id = ?
                ORDER BY cn.created_at ASC
            ");
            $stmt->execute([$notificationId]);
            $notes = $stmt->fetchAll();
            
            $messageData['thread'] = array_map(function($note) {
                return [
                    'sender_name' => $note['first_name'] . ' ' . $note['last_name'],
                    'sender_type' => 'admin',
                    'message' => $note['note'],
                    'created_at' => $note['created_at']
                ];
            }, $notes);
        }
        
        jsonResponse(true, 'Message details retrieved', $messageData);
        
    } catch (Exception $e) {
        error_log("Error getting message details: " . $e->getMessage());
        jsonResponse(false, 'Error loading message details');
    }
}

/**
 * Send Reply to Message
 */
function sendReply($userId, $userData) {
    $notificationId = intval($_POST['message_id'] ?? 0);
    $notificationType = $_POST['notification_type'] ?? '';
    $reply = trim($_POST['reply'] ?? '');
    
    if ($notificationId <= 0) {
        jsonResponse(false, 'Invalid message ID');
    }
    
    if (empty($reply)) {
        jsonResponse(false, 'Reply message is required');
    }
    
    $db = Database::getInstance();
    
    try {
        if ($notificationType === 'message') {
            // Get original message
            $stmt = $db->prepare("SELECT * FROM admin_messages WHERE id = ?");
            $stmt->execute([$notificationId]);
            $originalMessage = $stmt->fetch();
            
            if (!$originalMessage) {
                jsonResponse(false, 'Original message not found');
            }
            
            // Determine recipient
            $recipientId = $originalMessage['sender_id'];
            $recipientType = $originalMessage['sender_type'];
            
            // Insert reply
            $stmt = $db->prepare("
                INSERT INTO admin_messages (
                    sender_id, 
                    recipient_id, 
                    sender_type, 
                    recipient_type, 
                    subject, 
                    message, 
                    priority, 
                    message_type, 
                    parent_message_id,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $recipientId,
                $userData['role'],
                $recipientType,
                'Re: ' . $originalMessage['subject'],
                $reply,
                $originalMessage['priority'],
                $originalMessage['message_type'],
                $originalMessage['parent_message_id'] ?? $originalMessage['id']
            ]);
            
            // Mark original as read
            $stmt = $db->prepare("
                UPDATE admin_messages 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$notificationId]);
            
        } elseif ($notificationType === 'contact') {
            // Add note to contact_messages
            $stmt = $db->prepare("
                INSERT INTO contact_notes (
                    contact_message_id, 
                    admin_id, 
                    note, 
                    is_internal,
                    created_at
                ) VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$notificationId, $userId, $reply]);
            
            // Update contact message status and replied info
            $stmt = $db->prepare("
                UPDATE contact_messages 
                SET status = 'resolved', 
                    reply = ?, 
                    replied_at = NOW(), 
                    replied_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$reply, $userId, $notificationId]);
            
            // Send reply notification to user if they have an account
            $stmt = $db->prepare("SELECT sender_id, sender_type FROM contact_messages WHERE id = ?");
            $stmt->execute([$notificationId]);
            $contact = $stmt->fetch();
            
            if ($contact && $contact['sender_id'] && $contact['sender_type'] !== 'guest') {
                $stmt = $db->prepare("
                    INSERT INTO admin_messages (
                        sender_id, 
                        recipient_id, 
                        sender_type, 
                        recipient_type, 
                        subject, 
                        message, 
                        priority, 
                        message_type,
                        created_at
                    ) VALUES (?, ?, 'admin', ?, ?, ?, 'normal', 'support', NOW())
                ");
                
                $stmt->execute([
                    $userId,
                    $contact['sender_id'],
                    $contact['sender_type'],
                    'Re: Your Contact Request',
                    $reply
                ]);
            }
        }
        
        // Log activity
        logUserActivity($userId, 'notification_reply', 'Replied to notification', getUserIP());
        
        jsonResponse(true, 'Reply sent successfully');
        
    } catch (Exception $e) {
        error_log("Error sending reply: " . $e->getMessage());
        jsonResponse(false, 'Error sending reply');
    }
}
?>