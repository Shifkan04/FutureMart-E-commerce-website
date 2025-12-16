<?php
/**
 * Notification Helper Functions
 * Add this to your config.php or create a separate NotificationHelper.php file
 */

/**
 * Send notification to user
 * This function creates entries in admin_messages table so they appear in user's notifications.php
 * 
 * @param int $userId - User ID to send notification to
 * @param string $subject - Notification subject
 * @param string $message - Notification message content
 * @param string $priority - Priority level: 'normal', 'high', 'urgent'
 * @param string $messageType - Type: 'general', 'support', 'order_issue', 'vendor_application'
 * @param int|null $senderId - Admin ID sending the notification (null for system notifications)
 * @return bool - Success status
 */
function sendUserNotification($userId, $subject, $message, $priority = 'normal', $messageType = 'general', $senderId = null) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get user role to determine recipient_type
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $recipientType = $user['role'] ?? 'user';
        $senderType = $senderId ? 'admin' : 'system';
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO admin_messages 
            (sender_id, recipient_id, sender_type, recipient_type, subject, message, priority, message_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $senderId,
            $userId,
            $senderType,
            $recipientType,
            $subject,
            $message,
            $priority,
            $messageType
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to multiple users
 * 
 * @param array $userIds - Array of user IDs
 * @param string $subject - Notification subject
 * @param string $message - Notification message
 * @param string $priority - Priority level
 * @param string $messageType - Message type
 * @param int|null $senderId - Admin ID (null for system)
 * @return array - ['success' => count, 'failed' => count]
 */
function sendBulkNotifications($userIds, $subject, $message, $priority = 'normal', $messageType = 'general', $senderId = null) {
    $success = 0;
    $failed = 0;
    
    foreach ($userIds as $userId) {
        if (sendUserNotification($userId, $subject, $message, $priority, $messageType, $senderId)) {
            $success++;
        } else {
            $failed++;
        }
    }
    
    return ['success' => $success, 'failed' => $failed];
}

/**
 * Send price drop alert to users who wishlisted a product
 * 
 * @param int $productId - Product ID
 * @param float $oldPrice - Old price
 * @param float $newPrice - New price
 * @return array - Result statistics
 */
function sendPriceDropAlert($productId, $oldPrice, $newPrice) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get product details
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => 0, 'failed' => 0];
        }
        
        // Get users who have this product in wishlist
        $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM wishlist WHERE product_id = ?");
        $stmt->execute([$productId]);
        $wishlistUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($wishlistUsers)) {
            return ['success' => 0, 'failed' => 0];
        }
        
        $discount = round((($oldPrice - $newPrice) / $oldPrice) * 100);
        
        $subject = "🎉 Price Drop Alert: {$product['name']}";
        $message = "Great news! The price of \"{$product['name']}\" has dropped from \${$oldPrice} to \${$newPrice} ({$discount}% off)!\n\n";
        $message .= "This item is in your wishlist. Get it now before the price goes back up!";
        
        return sendBulkNotifications($wishlistUsers, $subject, $message, 'normal', 'general', null);
        
    } catch (Exception $e) {
        error_log("Price drop alert error: " . $e->getMessage());
        return ['success' => 0, 'failed' => 0];
    }
}

/**
 * Send order status update notification
 * 
 * @param int $orderId - Order ID
 * @param string $status - New order status
 * @return bool - Success status
 */
function sendOrderStatusNotification($orderId, $status) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.*, o.user_id, o.order_number
            FROM orders o
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return false;
        }
        
        $statusMessages = [
            'pending' => 'Your order has been received and is being processed.',
            'processing' => 'Your order is currently being prepared.',
            'shipped' => 'Your order has been shipped! Track it using the tracking number provided.',
            'delivered' => 'Your order has been delivered successfully. Thank you for shopping with us!',
            'cancelled' => 'Your order has been cancelled. If you have any questions, please contact support.',
            'refunded' => 'Your order has been refunded. The amount will be credited to your account within 5-7 business days.'
        ];
        
        $subject = "Order Update: {$order['order_number']}";
        $message = $statusMessages[$status] ?? "Your order status has been updated to: " . ucfirst($status);
        $message .= "\n\nOrder Number: {$order['order_number']}\nTotal: \${$order['total_amount']}";
        
        if ($status === 'shipped' && $order['tracking_number']) {
            $message .= "\nTracking Number: {$order['tracking_number']}";
        }
        
        return sendUserNotification($order['user_id'], $subject, $message, 'high', 'order_issue', null);
        
    } catch (Exception $e) {
        error_log("Order notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send discount/promotion notification to all users
 * 
 * @param string $promoTitle - Promotion title
 * @param string $promoDetails - Promotion details
 * @param string $couponCode - Optional coupon code
 * @return array - Result statistics
 */
function sendPromotionNotification($promoTitle, $promoDetails, $couponCode = null) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get all active users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active' AND role = 'user'");
        $stmt->execute();
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $subject = "🎁 Special Offer: {$promoTitle}";
        $message = $promoDetails;
        
        if ($couponCode) {
            $message .= "\n\nUse coupon code: {$couponCode}";
        }
        
        return sendBulkNotifications($userIds, $subject, $message, 'normal', 'general', null);
        
    } catch (Exception $e) {
        error_log("Promotion notification error: " . $e->getMessage());
        return ['success' => 0, 'failed' => 0];
    }
}

/**
 * Send low stock alert to admins
 * 
 * @param int $productId - Product ID
 * @param int $currentStock - Current stock level
 * @return bool - Success status
 */
function sendLowStockAlert($productId, $currentStock) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get product details
        $stmt = $pdo->prepare("SELECT name, vendor_id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return false;
        }
        
        // Send to admin
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin) {
            $subject = "⚠️ Low Stock Alert";
            $message = "Product \"{$product['name']}\" has only {$currentStock} units left in stock. Consider restocking soon.";
            
            sendUserNotification($admin['id'], $subject, $message, 'high', 'general', null);
        }
        
        // Send to vendor if applicable
        if ($product['vendor_id']) {
            $subject = "⚠️ Low Stock Alert: {$product['name']}";
            $message = "Your product \"{$product['name']}\" has only {$currentStock} units left in stock. Please restock soon.";
            
            sendUserNotification($product['vendor_id'], $subject, $message, 'high', 'general', null);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Low stock alert error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome notification to new users
 * 
 * @param int $userId - New user ID
 * @return bool - Success status
 */
function sendWelcomeNotification($userId) {
    $subject = "🎉 Welcome to FutureMart!";
    $message = "Thank you for joining FutureMart! We're excited to have you with us.\n\n";
    $message .= "Start exploring our wide range of products and enjoy exclusive deals.\n\n";
    $message .= "If you have any questions, our support team is here to help 24/7!";
    
    return sendUserNotification($userId, $subject, $message, 'normal', 'general', null);
}

/**
 * Send new product notification to users
 * 
 * @param int $productId - New product ID
 * @param string $categoryId - Product category
 * @return array - Result statistics
 */
function sendNewProductNotification($productId, $categoryId = null) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get product details
        $stmt = $pdo->prepare("SELECT name, price, category_id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => 0, 'failed' => 0];
        }
        
        // Get users who want new product alerts
        $stmt = $pdo->prepare("
            SELECT u.id 
            FROM users u
            JOIN user_notifications un ON u.id = un.user_id
            WHERE un.new_product_alerts = 1 AND u.status = 'active' AND u.role = 'user'
        ");
        $stmt->execute();
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($userIds)) {
            return ['success' => 0, 'failed' => 0];
        }
        
        $subject = "🆕 New Product Alert: {$product['name']}";
        $message = "Check out our latest product: \"{$product['name']}\" now available for \${$product['price']}!\n\n";
        $message .= "Visit our store to see more details and exclusive launch offers!";
        
        return sendBulkNotifications($userIds, $subject, $message, 'normal', 'general', null);
        
    } catch (Exception $e) {
        error_log("New product notification error: " . $e->getMessage());
        return ['success' => 0, 'failed' => 0];
    }
}

/**
 * Send account security alert
 * 
 * @param int $userId - User ID
 * @param string $alertType - Type of security alert
 * @param string $details - Alert details
 * @return bool - Success status
 */
function sendSecurityAlert($userId, $alertType, $details) {
    $subject = "🔒 Security Alert: {$alertType}";
    $message = "We detected important activity on your account:\n\n{$details}\n\n";
    $message .= "If this wasn't you, please change your password immediately and contact support.";
    
    return sendUserNotification($userId, $subject, $message, 'urgent', 'general', null);
}