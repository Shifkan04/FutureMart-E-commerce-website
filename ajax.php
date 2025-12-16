<?php
require_once 'config_user.php';
require_once 'User.php';
$db = Database::getInstance();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'User not authenticated');
}

$user = new User();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'update_profile':
            $data = [
                'first_name' => sanitizeInput($_POST['first_name']),
                'last_name' => sanitizeInput($_POST['last_name']),
                'email' => sanitizeInput($_POST['email']),
                'phone' => sanitizeInput($_POST['phone']),
                'date_of_birth' => $_POST['date_of_birth'] ?: null,
                'gender' => $_POST['gender'] ?: null,
                'bio' => sanitizeInput($_POST['bio']),
                'theme_preference' => $_POST['theme_preference'] ?? 'dark'
            ];

            if (!isValidEmail($data['email'])) {
                jsonResponse(false, 'Invalid email address');
            }

            $existingUser = $user->getUserByEmail($data['email']);
            if ($existingUser && $existingUser['id'] != $userId) {
                jsonResponse(false, 'Email address is already taken');
            }

            if ($user->updateProfile($userId, $data)) {
                $userData = $user->getUserById($userId);
                $_SESSION['user_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
                $_SESSION['user_email'] = $userData['email'];

                jsonResponse(true, 'Profile updated successfully', $userData);
            } else {
                jsonResponse(false, 'Failed to update profile');
            }
            break;

        case 'upload_avatar':
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(false, 'No file uploaded or upload error');
            }

            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024;

            if (!in_array($file['type'], $allowedTypes)) {
                jsonResponse(false, 'Invalid file type. Please upload JPG, PNG, or GIF');
            }

            if ($file['size'] > $maxSize) {
                jsonResponse(false, 'File size too large. Maximum 5MB allowed');
            }

            $fileName = $userId . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $uploadPath = AVATAR_PATH . $fileName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                if ($user->updateAvatar($userId, $fileName)) {
                    jsonResponse(true, 'Avatar updated successfully', ['avatar' => $fileName]);
                } else {
                    jsonResponse(false, 'Failed to update avatar in database');
                }
            } else {
                jsonResponse(false, 'Failed to upload file');
            }
            break;

        case 'get_orders':
            header('Content-Type: application/json; charset=utf-8');
            try {
                $page = intval($_GET['page'] ?? 1);
                $limit = intval($_GET['limit'] ?? 10);
                $offset = ($page - 1) * $limit;
                $filter = $_GET['filter'] ?? 'all';

                // Build WHERE clause for filter
                $whereClause = "WHERE o.user_id = ?";
                $params = [$userId];

                // Add filter condition if not 'all'
                if ($filter !== 'all') {
                    $whereClause .= " AND o.status = ?";
                    $params[] = $filter;
                }

                // Get orders with addresses
                $orderQuery = "
            SELECT o.*, 
                   ua.address_line_1, ua.address_line_2, ua.city, ua.state, ua.postal_code, ua.country
            FROM orders o
            LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
            $whereClause
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";

                $params[] = $limit;
                $params[] = $offset;

                $stmt = $db->prepare($orderQuery);
                $stmt->execute($params);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get items for each order with color and size details
                foreach ($orders as &$order) {
                    // Get order items with color and size information
                    $itemsStmt = $db->prepare("
                SELECT oi.*,
                       p.name, p.image, p.sku,
                       c.name as color_name,
                       c.hex_code as color_hex,
                       s.name as size_name
                FROM order_items oi
                INNER JOIN products p ON oi.product_id = p.id
                LEFT JOIN colors c ON oi.selected_color_id = c.id
                LEFT JOIN sizes s ON oi.selected_size_id = s.id
                WHERE oi.order_id = ?
            ");
                    $itemsStmt->execute([$order['id']]);
                    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Process each item to ensure color and size data is available
                    foreach ($items as &$item) {
                        // Try to get color/size from product_snapshot first
                        if (!empty($item['product_snapshot'])) {
                            try {
                                $snapshot = json_decode($item['product_snapshot'], true);

                                // Override with snapshot data if available
                                if (isset($snapshot['color']) && isset($snapshot['color']['name'])) {
                                    $item['color_name'] = $snapshot['color']['name'];
                                    $item['color_hex'] = $snapshot['color']['hex_code'] ?? '#cccccc';
                                }

                                if (isset($snapshot['size']) && isset($snapshot['size']['name'])) {
                                    $item['size_name'] = $snapshot['size']['name'];
                                }
                            } catch (Exception $e) {
                                error_log("Error parsing product_snapshot: " . $e->getMessage());
                            }
                        }

                        // Fallback: If still no color/size data, try to fetch from IDs
                        if (empty($item['color_name']) && !empty($item['selected_color_id'])) {
                            try {
                                $colorStmt = $db->prepare("SELECT name, hex_code FROM colors WHERE id = ?");
                                $colorStmt->execute([$item['selected_color_id']]);
                                $colorData = $colorStmt->fetch(PDO::FETCH_ASSOC);
                                if ($colorData) {
                                    $item['color_name'] = $colorData['name'];
                                    $item['color_hex'] = $colorData['hex_code'];
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching color: " . $e->getMessage());
                            }
                        }

                        if (empty($item['size_name']) && !empty($item['selected_size_id'])) {
                            try {
                                $sizeStmt = $db->prepare("SELECT name FROM sizes WHERE id = ?");
                                $sizeStmt->execute([$item['selected_size_id']]);
                                $sizeData = $sizeStmt->fetch(PDO::FETCH_ASSOC);
                                if ($sizeData) {
                                    $item['size_name'] = $sizeData['name'];
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching size: " . $e->getMessage());
                            }
                        }

                        // Format the price
                        $item['price'] = number_format($item['total_price'], 2);

                        // Build details string
                        $item['details'] = '';
                        if (!empty($item['color_name'])) {
                            $item['details'] .= 'Color: ' . $item['color_name'];
                        }
                        if (!empty($item['size_name'])) {
                            $item['details'] .= (!empty($item['details']) ? ' | ' : '') . 'Size: ' . $item['size_name'];
                        }

                        // Add product_id for review functionality
                        $item['product_id'] = $item['product_id'];
                    }
                    unset($item); // Break reference

                    $order['items'] = $items;

                    // Get tracking information
                    try {
                        $trackingStmt = $db->prepare("
                    SELECT * FROM order_tracking 
                    WHERE order_id = ? 
                    ORDER BY date_time DESC
                ");
                        $trackingStmt->execute([$order['id']]);
                        $order['tracking'] = $trackingStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        error_log("Error fetching tracking: " . $e->getMessage());
                        $order['tracking'] = [];
                    }
                }
                unset($order); // Break reference

                echo json_encode([
                    'success' => true,
                    'message' => 'Orders retrieved successfully',
                    'data' => $orders,
                    'page' => $page,
                    'total' => count($orders),
                    'filter' => $filter
                ]);
            } catch (Throwable $e) {
                error_log("Get orders error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Server error: ' . $e->getMessage(),
                    'data' => []
                ]);
            }
            exit;

        case 'submit_review':
            header('Content-Type: application/json; charset=utf-8');
            $productId = intval($_POST['product_id'] ?? 0);
            $orderId = intval($_POST['order_id'] ?? 0);
            $rating = intval($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');

            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'User not logged in']);
                exit;
            }

            if ($productId <= 0 || $orderId <= 0 || $rating <= 0 || $rating > 5) {
                echo json_encode(['success' => false, 'message' => 'Invalid input']);
                exit;
            }

            try {
                $check = $db->prepare("SELECT id FROM reviews WHERE user_id=? AND product_id=? AND order_id=?");
                $check->execute([$userId, $productId, $orderId]);
                if ($check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'You already reviewed this item.']);
                    exit;
                }

                $stmt = $db->prepare("
                    INSERT INTO reviews (user_id, product_id, order_id, rating, comment)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $productId, $orderId, $rating, $comment]);
                echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
            }
            exit;

        case 'check_review':
            header('Content-Type: application/json; charset=utf-8');
            $productId = intval($_GET['product_id'] ?? 0);
            $orderId = intval($_GET['order_id'] ?? 0);

            try {
                $stmt = $db->prepare("SELECT id FROM reviews WHERE user_id=? AND product_id=? AND order_id=?");
                $stmt->execute([$userId, $productId, $orderId]);
                $exists = $stmt->fetch() ? true : false;

                echo json_encode(['success' => true, 'exists' => $exists]);
            } catch (Throwable $e) {
                error_log("Check review error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error checking review']);
            }
            exit;

        case 'get_order_reviews':
            header('Content-Type: application/json; charset=utf-8');
            $orderNumber = $_GET['order_number'] ?? '';

            try {
                $stmt = $db->prepare("
            SELECT r.*, o.id as order_id, p.name as product_name
            FROM reviews r
            INNER JOIN orders o ON r.order_id = o.id
            INNER JOIN products p ON r.product_id = p.id
            WHERE o.order_number = ? AND r.user_id = ?
            ORDER BY r.created_at DESC
        ");
                $stmt->execute([$orderNumber, $userId]);
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'reviews' => $reviews]);
            } catch (Throwable $e) {
                error_log("Get order reviews error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error loading reviews']);
            }
            exit;

        case 'get_wishlist':
            $wishlist = $user->getUserWishlist($userId);
            foreach ($wishlist as &$item) {
                if (!isset($item['description']) && !isset($item['short_description'])) {
                    $item['short_description'] = 'Product description not available';
                }
            }
            jsonResponse(true, 'Wishlist retrieved successfully', $wishlist);
            break;

        case 'add_to_wishlist':
            $productId = intval($_POST['product_id']);
            if ($user->addToWishlist($userId, $productId)) {
                jsonResponse(true, 'Item added to wishlist');
            } else {
                jsonResponse(false, 'Failed to add item to wishlist');
            }
            break;

        case 'remove_from_wishlist':
            $productId = intval($_POST['product_id']);
            if ($user->removeFromWishlist($userId, $productId)) {
                jsonResponse(true, 'Item removed from wishlist');
            } else {
                jsonResponse(false, 'Failed to remove item from wishlist');
            }
            break;

        case 'get_addresses':
            $addresses = $user->getUserAddresses($userId);
            jsonResponse(true, 'Addresses retrieved successfully', $addresses);
            break;

        case 'add_address':
            $addressData = [
                'title' => sanitizeInput($_POST['title']),
                'address_line_1' => sanitizeInput($_POST['address_line_1']),
                'address_line_2' => sanitizeInput($_POST['address_line_2']),
                'city' => sanitizeInput($_POST['city']),
                'state' => sanitizeInput($_POST['state']),
                'postal_code' => sanitizeInput($_POST['postal_code']),
                'country' => sanitizeInput($_POST['country']),
                'phone' => sanitizeInput($_POST['phone']),
                'is_default' => isset($_POST['is_default']) && $_POST['is_default'] == '1'
            ];

            $addressId = $user->addAddress($userId, $addressData);
            if ($addressId) {
                jsonResponse(true, 'Address added successfully', ['address_id' => $addressId]);
            } else {
                jsonResponse(false, 'Failed to add address');
            }
            break;

        case 'update_address':
            $addressId = intval($_POST['address_id']);
            $addressData = [
                'title' => sanitizeInput($_POST['title']),
                'address_line_1' => sanitizeInput($_POST['address_line_1']),
                'address_line_2' => sanitizeInput($_POST['address_line_2']),
                'city' => sanitizeInput($_POST['city']),
                'state' => sanitizeInput($_POST['state']),
                'postal_code' => sanitizeInput($_POST['postal_code']),
                'country' => sanitizeInput($_POST['country']),
                'phone' => sanitizeInput($_POST['phone']),
                'is_default' => isset($_POST['is_default']) && $_POST['is_default'] == '1'
            ];

            if ($user->updateAddress($userId, $addressId, $addressData)) {
                jsonResponse(true, 'Address updated successfully');
            } else {
                jsonResponse(false, 'Failed to update address');
            }
            break;

        case 'delete_address':
            $addressId = intval($_POST['address_id']);
            if ($user->deleteAddress($userId, $addressId)) {
                jsonResponse(true, 'Address deleted successfully');
            } else {
                jsonResponse(false, 'Failed to delete address');
            }
            break;

        case 'get_notifications':
            $preferences = $user->getNotificationPreferences($userId);
            jsonResponse(true, 'Notification preferences retrieved', $preferences);
            break;

        case 'update_notifications':
            $preferences = [
                'order_updates' => isset($_POST['order_updates']) ? 1 : 0,
                'promotional_emails' => isset($_POST['promotional_emails']) ? 1 : 0,
                'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                'new_product_alerts' => isset($_POST['new_product_alerts']) ? 1 : 0,
                'price_drop_alerts' => isset($_POST['price_drop_alerts']) ? 1 : 0
            ];

            if ($user->updateNotificationPreferences($userId, $preferences)) {
                jsonResponse(true, 'Notification preferences updated');
            } else {
                jsonResponse(false, 'Failed to update notification preferences');
            }
            break;

        case 'change_password':
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            if (strlen($newPassword) < 8) {
                jsonResponse(false, 'New password must be at least 8 characters long');
            }

            if ($newPassword !== $confirmPassword) {
                jsonResponse(false, 'New passwords do not match');
            }

            if (!$user->verifyCurrentPassword($userId, $currentPassword)) {
                jsonResponse(false, 'Current password is incorrect');
            }

            if ($user->updatePassword($userId, $newPassword)) {
                jsonResponse(true, 'Password updated successfully');
            } else {
                jsonResponse(false, 'Failed to update password');
            }
            break;

        case 'get_dashboard_stats':
            $stats = $user->getDashboardStats($userId);
            jsonResponse(true, 'Dashboard stats retrieved', $stats);
            break;

        case 'get_activity':
            $limit = intval($_GET['limit'] ?? 20);
            $activity = $user->getUserActivity($userId, $limit);
            jsonResponse(true, 'Activity retrieved successfully', $activity);
            break;

        case 'update_theme':
            $theme = $_POST['theme'];
            if (!in_array($theme, ['light', 'dark', 'auto'])) {
                jsonResponse(false, 'Invalid theme');
            }

            if ($user->updateThemePreference($userId, $theme)) {
                $_SESSION['theme'] = $theme;
                jsonResponse(true, 'Theme preference updated');
            } else {
                jsonResponse(false, 'Failed to update theme preference');
            }
            break;

        case 'search_products':
            $query = sanitizeInput($_GET['q']);
            $categoryId = intval($_GET['category_id'] ?? 0) ?: null;
            $limit = intval($_GET['limit'] ?? 20);

            if (strlen($query) < 2) {
                jsonResponse(false, 'Search query must be at least 2 characters');
            }

            $products = $user->searchProducts($query, $categoryId, $limit);
            jsonResponse(true, 'Products found', $products);
            break;

        // ===== UPDATED ADD TO CART WITH COLOR & SIZE SUPPORT =====
        case 'add_to_cart':
            $productId = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity'] ?? 1);
            $selectedColor = isset($_POST['selected_color']) ? intval($_POST['selected_color']) : null;
            $selectedSize = isset($_POST['selected_size']) ? intval($_POST['selected_size']) : null;
            $selectedColorName = $_POST['selected_color_name'] ?? null;
            $selectedSizeName = $_POST['selected_size_name'] ?? null;

            if ($productId <= 0 || $quantity <= 0) {
                jsonResponse(false, 'Invalid product or quantity');
            }

            try {
                // Check if product exists and is available
                $stmt = $db->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    jsonResponse(false, 'Product not found');
                }

                if ($product['stock_quantity'] < $quantity) {
                    jsonResponse(false, 'Insufficient stock available');
                }

                // Build options JSON for color and size
                $options = [];
                if ($selectedColor && $selectedColorName) {
                    // Get color hex code from database
                    $colorStmt = $db->prepare("SELECT hex_code FROM colors WHERE id = ?");
                    $colorStmt->execute([$selectedColor]);
                    $colorData = $colorStmt->fetch();

                    $options['color'] = [
                        'id' => $selectedColor,
                        'name' => $selectedColorName,
                        'hex_code' => $colorData['hex_code'] ?? '#000000'
                    ];
                }

                if ($selectedSize && $selectedSizeName) {
                    $options['size'] = [
                        'id' => $selectedSize,
                        'name' => $selectedSizeName
                    ];
                }

                $optionsJson = !empty($options) ? json_encode($options) : null;

                // Check if item already exists in cart with same color/size combination
                $checkStmt = $db->prepare("
                    SELECT id, quantity FROM cart_items 
                    WHERE user_id = ? AND product_id = ? 
                    AND (selected_color_id = ? OR (selected_color_id IS NULL AND ? IS NULL))
                    AND (selected_size_id = ? OR (selected_size_id IS NULL AND ? IS NULL))
                ");
                $checkStmt->execute([
                    $userId,
                    $productId,
                    $selectedColor,
                    $selectedColor,
                    $selectedSize,
                    $selectedSize
                ]);
                $existingItem = $checkStmt->fetch();

                if ($existingItem) {
                    // Update quantity
                    $newQuantity = $existingItem['quantity'] + $quantity;

                    if ($newQuantity > $product['stock_quantity']) {
                        jsonResponse(false, 'Cannot add more items than available in stock');
                    }

                    $updateStmt = $db->prepare("
                        UPDATE cart_items 
                        SET quantity = ?, options = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$newQuantity, $optionsJson, $existingItem['id']]);

                    logUserActivity($userId, 'cart_update', "Updated cart item: {$product['name']} (quantity: {$newQuantity})");
                } else {
                    // Add new item to cart with color and size
                    $insertStmt = $db->prepare("
                        INSERT INTO cart_items 
                        (user_id, product_id, quantity, selected_color_id, selected_size_id, options, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ");
                    $insertStmt->execute([
                        $userId,
                        $productId,
                        $quantity,
                        $selectedColor,
                        $selectedSize,
                        $optionsJson
                    ]);

                    logUserActivity($userId, 'cart_add', "Added to cart: {$product['name']} (quantity: {$quantity})");
                }

                // Get total cart count
                $countStmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
                $countStmt->execute([$userId]);
                $cartCount = $countStmt->fetch()['total'] ?? 0;

                jsonResponse(true, 'Product added to cart successfully', ['cart_count' => $cartCount]);
            } catch (Exception $e) {
                error_log("Add to cart error: " . $e->getMessage());
                jsonResponse(false, 'Error adding product to cart');
            }
            break;

        case 'get_cart':
            try {
                $stmt = $db->prepare("
                    SELECT 
                        ci.id,
                        ci.product_id,
                        ci.quantity,
                        ci.selected_color_id,
                        ci.selected_size_id,
                        ci.options,
                        p.name,
                        p.price,
                        p.image,
                        p.stock_quantity,
                        (ci.quantity * p.price) as subtotal
                    FROM cart_items ci
                    JOIN products p ON ci.product_id = p.id
                    WHERE ci.user_id = ? AND p.is_active = 1
                    ORDER BY ci.created_at DESC
                ");
                $stmt->execute([$userId]);
                $cartItems = $stmt->fetchAll();

                // Parse options JSON for each item
                foreach ($cartItems as &$item) {
                    if ($item['options']) {
                        $item['options'] = json_decode($item['options'], true);
                    }
                }

                $total = array_sum(array_column($cartItems, 'subtotal'));

                jsonResponse(true, 'Cart retrieved successfully', [
                    'items' => $cartItems,
                    'total' => $total,
                    'count' => count($cartItems)
                ]);
            } catch (Exception $e) {
                error_log("Get cart error: " . $e->getMessage());
                jsonResponse(false, 'Error retrieving cart');
            }
            break;

        case 'update_cart_quantity':
            $cartItemId = intval($_POST['cart_item_id']);
            $quantity = intval($_POST['quantity']);

            if ($quantity <= 0) {
                jsonResponse(false, 'Invalid quantity');
            }

            try {
                $stmt = $db->prepare("
                    SELECT ci.id, p.stock_quantity, p.name 
                    FROM cart_items ci
                    JOIN products p ON ci.product_id = p.id
                    WHERE ci.id = ? AND ci.user_id = ?
                ");
                $stmt->execute([$cartItemId, $userId]);
                $item = $stmt->fetch();

                if (!$item) {
                    jsonResponse(false, 'Cart item not found');
                }

                if ($quantity > $item['stock_quantity']) {
                    jsonResponse(false, 'Quantity exceeds available stock');
                }

                $updateStmt = $db->prepare("UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$quantity, $cartItemId]);

                logUserActivity($userId, 'cart_update', "Updated cart quantity: {$item['name']}");
                jsonResponse(true, 'Cart quantity updated');
            } catch (Exception $e) {
                error_log("Update cart error: " . $e->getMessage());
                jsonResponse(false, 'Error updating cart');
            }
            break;

        case 'remove_from_cart':
            $cartItemId = intval($_POST['cart_item_id']);

            try {
                $stmt = $db->prepare("
                    SELECT ci.id, p.name 
                    FROM cart_items ci
                    JOIN products p ON ci.product_id = p.id
                    WHERE ci.id = ? AND ci.user_id = ?
                ");
                $stmt->execute([$cartItemId, $userId]);
                $item = $stmt->fetch();

                if (!$item) {
                    jsonResponse(false, 'Cart item not found');
                }

                $deleteStmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $deleteStmt->execute([$cartItemId, $userId]);

                logUserActivity($userId, 'cart_remove', "Removed from cart: {$item['name']}");
                jsonResponse(true, 'Item removed from cart');
            } catch (Exception $e) {
                error_log("Remove from cart error: " . $e->getMessage());
                jsonResponse(false, 'Error removing item from cart');
            }
            break;

        case 'clear_cart':
            try {
                $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
                $stmt->execute([$userId]);

                logUserActivity($userId, 'cart_clear', 'Cart cleared');
                jsonResponse(true, 'Cart cleared successfully');
            } catch (Exception $e) {
                error_log("Clear cart error: " . $e->getMessage());
                jsonResponse(false, 'Error clearing cart');
            }
            break;

        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again later.');
}
