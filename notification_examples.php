<?php
/**
 * Examples of how to use the notification system
 * Add these examples where needed in your application
 */

// Include the notification helper functions
require_once 'config_user.php';
require_once 'NotificationHelper.php'; // Or wherever you placed the helper functions

// ============================================
// EXAMPLE 1: Send a simple notification to a user
// ============================================
$userId = 19; // Example user ID
$subject = "Welcome to Our Store!";
$message = "Thank you for shopping with us. Enjoy your experience!";

sendUserNotification($userId, $subject, $message, 'normal', 'general', null);


// ============================================
// EXAMPLE 2: Admin replying to contact message
// This should be triggered when an admin replies to a contact message
// ============================================
$adminId = 1; // Example admin ID
$contactId = 42; // Example contact message ID
$subject = "Re: Contact Message #{$contactId}";
$message = "Thanks for your message. We'll get back to you soon!";

sendUserNotification($adminId, $subject, $message, 'normal', 'general', $contactId);    
