<?php
require_once 'config_user.php';

/**
 * User Management Class
 * Handles all user-related database operations
 */
class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, ur.points_balance, ur.loyalty_tier 
            FROM users u 
            LEFT JOIN user_rewards ur ON u.id = ur.user_id 
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    date_of_birth = ?, 
                    gender = ?, 
                    bio = ?,
                    theme_preference = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['date_of_birth'] ?: null,
                $data['gender'] ?: null,
                $data['bio'] ?: null,
                $data['theme_preference'] ?? 'dark',
                $userId
            ]);

            if ($result) {
                logUserActivity($userId, 'profile_update', 'Profile information updated');
                $this->db->commit();
                return true;
            }

            $this->db->rollback();
            return false;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Update avatar
     */
    public function updateAvatar($userId, $avatarPath)
    {
        $stmt = $this->db->prepare("UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$avatarPath, $userId]);

        if ($result) {
            logUserActivity($userId, 'avatar_update', 'Profile picture updated');
        }

        return $result;
    }

    /**
     * Get user addresses
     */
    public function getUserAddresses($userId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM user_addresses 
            WHERE user_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Add user address
     */
    public function addAddress($userId, $addressData)
    {
        try {
            $this->db->beginTransaction();

            // If this is the default address, unset other defaults
            if (isset($addressData['is_default']) && $addressData['is_default']) {
                $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$userId]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO user_addresses 
                (user_id, title, address_line_1, address_line_2, city, state, postal_code, country, phone, is_default) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $userId,
                $addressData['title'],
                $addressData['address_line_1'],
                $addressData['address_line_2'] ?? null,
                $addressData['city'],
                $addressData['state'],
                $addressData['postal_code'],
                $addressData['country'] ?? 'United States',
                $addressData['phone'] ?? null,
                isset($addressData['is_default']) && $addressData['is_default'] ? 1 : 0
            ]);

            if ($result) {
                logUserActivity($userId, 'address_add', 'New address added: ' . $addressData['title']);
                $this->db->commit();
                return $this->db->lastInsertId();
            }

            $this->db->rollback();
            return false;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Update user address
     */
    public function updateAddress($userId, $addressId, $addressData)
    {
        try {
            $this->db->beginTransaction();

            // Check if address belongs to user
            $stmt = $this->db->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$addressId, $userId]);
            if (!$stmt->fetch()) {
                $this->db->rollback();
                return false;
            }

            // If this is the default address, unset other defaults
            if (isset($addressData['is_default']) && $addressData['is_default']) {
                $stmt = $this->db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$userId]);
            }

            $stmt = $this->db->prepare("
                UPDATE user_addresses SET 
                    title = ?, address_line_1 = ?, address_line_2 = ?, 
                    city = ?, state = ?, postal_code = ?, country = ?, 
                    phone = ?, is_default = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
            ");

            $result = $stmt->execute([
                $addressData['title'],
                $addressData['address_line_1'],
                $addressData['address_line_2'] ?? null,
                $addressData['city'],
                $addressData['state'],
                $addressData['postal_code'],
                $addressData['country'] ?? 'United States',
                $addressData['phone'] ?? null,
                isset($addressData['is_default']) && $addressData['is_default'] ? 1 : 0,
                $addressId,
                $userId
            ]);

            if ($result) {
                logUserActivity($userId, 'address_update', 'Address updated: ' . $addressData['title']);
                $this->db->commit();
                return true;
            }

            $this->db->rollback();
            return false;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Delete user address
     */
    public function deleteAddress($userId, $addressId)
    {
        $stmt = $this->db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$addressId, $userId]);

        if ($result) {
            logUserActivity($userId, 'address_delete', 'Address deleted');
        }

        return $result;
    }

    /**
     * Get user orders with pagination
     */
    public function getUserOrders($userId, $limit = 10, $offset = 0)
    {
        try {
            // Fetch main order info
            $stmt = $this->db->prepare("
                SELECT 
                    o.id,
                    o.order_number,
                    o.user_id,
                    o.total_amount,
                    o.status,
                    o.tracking_number,
                    o.created_at
                FROM orders o
                WHERE o.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, (int)$limit, (int)$offset]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process each order to include items + tracking
            foreach ($orders as &$order) {
                // Fetch all items for this order
                $itemStmt = $this->db->prepare("
                    SELECT 
                        oi.product_id,
                        p.name AS product_name,
                        p.image AS product_image,
                        p.short_description AS details,
                        oi.quantity,
                        oi.unit_price AS price
                    FROM order_items oi
                    LEFT JOIN products p ON p.id = oi.product_id
                    WHERE oi.order_id = ?
                ");
                $itemStmt->execute([$order['id']]);
                $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                // Format each item
                $order['items'] = array_map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'name' => $item['product_name'] ?? 'Unknown Product',
                        'image' => !empty($item['product_image']) 
                            ? $item['product_image'] 
                            : 'https://via.placeholder.com/60x60/6366f1/white?text=Product',
                        'details' => $item['details'] ?? 'No details available',
                        'quantity' => $item['quantity'],
                        'price' => number_format((float)$item['price'], 2)
                    ];
                }, $items);

                // Fetch tracking info
                $trackStmt = $this->db->prepare("
                    SELECT status, date_time 
                    FROM order_tracking 
                    WHERE order_id = ? 
                    ORDER BY date_time ASC
                ");
                $trackStmt->execute([$order['id']]);
                $tracking = $trackStmt->fetchAll(PDO::FETCH_ASSOC);

                // Add fallback if no tracking found
                if (empty($tracking)) {
                    $tracking = [
                        ['status' => 'Order Confirmed', 'date_time' => $order['created_at']],
                        ['status' => ucfirst($order['status']), 'date_time' => date('Y-m-d H:i:s', strtotime($order['created_at'] . ' +1 day'))]
                    ];
                }

                $order['tracking'] = $tracking;
            }

            return $orders;
        } catch (Exception $e) {
            error_log("getUserOrders error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user order statistics
     */
    public function getUserOrderStats($userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_spent,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            FROM orders 
            WHERE user_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * Get user wishlist
     */
   /**
 * Get user wishlist
 */
// In your User class, the getUserWishlist method should be:
public function getUserWishlist($userId) {
    $stmt = $this->db->prepare("
        SELECT 
            w.id as wishlist_id,
            w.created_at as added_date,
            p.id as product_id,
            p.name,
            p.price,
            p.original_price,
            p.image,
            p.description,
            p.short_description,
            p.rating,
            p.review_count,
            p.stock_quantity
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ? AND p.is_active = 1
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
    /**
     * Add item to wishlist
     */
    public function addToWishlist($userId, $productId)
    {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $result = $stmt->execute([$userId, $productId]);

            if ($result && $stmt->rowCount() > 0) {
                logUserActivity($userId, 'wishlist_add', 'Added product ID: ' . $productId . ' to wishlist');
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("addToWishlist error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove item from wishlist
     */
    public function removeFromWishlist($userId, $productId)
    {
        $stmt = $this->db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $result = $stmt->execute([$userId, $productId]);

        if ($result && $stmt->rowCount() > 0) {
            logUserActivity($userId, 'wishlist_remove', 'Removed product ID: ' . $productId . ' from wishlist');
            return true;
        }
        return false;
    }

    /**
     * Get user notification preferences
     */
    public function getNotificationPreferences($userId)
    {
        $stmt = $this->db->prepare("SELECT * FROM user_notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();

        // If no preferences exist, create default ones
        if (!$prefs) {
            $this->createDefaultNotificationPreferences($userId);
            return $this->getNotificationPreferences($userId);
        }

        return $prefs;
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences($userId, $preferences)
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_notifications 
            (user_id, order_updates, promotional_emails, sms_notifications, new_product_alerts, price_drop_alerts)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            order_updates = VALUES(order_updates),
            promotional_emails = VALUES(promotional_emails),
            sms_notifications = VALUES(sms_notifications),
            new_product_alerts = VALUES(new_product_alerts),
            price_drop_alerts = VALUES(price_drop_alerts),
            updated_at = CURRENT_TIMESTAMP
        ");

        $result = $stmt->execute([
            $userId,
            $preferences['order_updates'] ?? 1,
            $preferences['promotional_emails'] ?? 1,
            $preferences['sms_notifications'] ?? 0,
            $preferences['new_product_alerts'] ?? 1,
            $preferences['price_drop_alerts'] ?? 1
        ]);

        if ($result) {
            logUserActivity($userId, 'preferences_update', 'Notification preferences updated');
        }

        return $result;
    }

    /**
     * Create default notification preferences
     */
    private function createDefaultNotificationPreferences($userId)
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO user_notifications (user_id) VALUES (?)");
        return $stmt->execute([$userId]);
    }

    /**
     * Get user activity log
     */
    public function getUserActivity($userId, $limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM user_activity_log 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, (int)$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get user rewards information
     */
    public function getUserRewards($userId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM user_rewards 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $rewards = $stmt->fetch();

        // If no rewards record exists, create default one
        if (!$rewards) {
            $this->createDefaultRewards($userId);
            return $this->getUserRewards($userId);
        }

        return $rewards;
    }

    /**
     * Create default rewards record
     */
    private function createDefaultRewards($userId)
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_rewards (user_id, points_balance, total_points_earned, loyalty_tier) 
            VALUES (?, 0, 0, 'Bronze')
        ");
        return $stmt->execute([$userId]);
    }

    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword)
    {
        $hashedPassword = hashPassword($newPassword);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $userId]);

        if ($result) {
            logUserActivity($userId, 'password_change', 'Password changed');
        }

        return $result;
    }

    /**
     * Verify current password
     */
    public function verifyCurrentPassword($userId, $currentPassword)
    {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user) {
            return verifyPassword($currentPassword, $user['password']);
        }

        return false;
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats($userId)
    {
        // Get order stats
        $orderStats = $this->getUserOrderStats($userId);

        // Get wishlist count
        $stmt = $this->db->prepare("SELECT COUNT(*) as wishlist_count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wishlistCount = $stmt->fetch()['wishlist_count'];

        // Get rewards
        $rewards = $this->getUserRewards($userId);

        // Get recent activity
        $recentActivity = $this->getUserActivity($userId, 5);

        return [
            'total_orders' => $orderStats['total_orders'] ?? 0,
            'total_spent' => $orderStats['total_spent'] ?? 0,
            'avg_order_value' => $orderStats['avg_order_value'] ?? 0,
            'wishlist_count' => $wishlistCount ?? 0,
            'points_balance' => $rewards['points_balance'] ?? 0,
            'loyalty_tier' => $rewards['loyalty_tier'] ?? 'Bronze',
            'recent_activity' => $recentActivity ?? []
        ];
    }

    /**
     * Search products
     */
    public function searchProducts($query, $categoryId = null, $limit = 20)
    {
        $sql = "
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 AND p.name LIKE ?
        ";

        $params = ["%$query%"];

        if ($categoryId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY p.is_featured DESC, p.rating DESC LIMIT ?";
        $params[] = (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get all categories
     */
    public function getCategories()
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Update theme preference
     */
    public function updateThemePreference($userId, $theme)
    {
        $validThemes = ['dark', 'light', 'auto'];
        if (!in_array($theme, $validThemes)) {
            $theme = 'dark';
        }
        
        $stmt = $this->db->prepare("UPDATE users SET theme_preference = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$theme, $userId]);
    }
}