<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to manage wishlist'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'toggle':
            $productId = (int)($_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                throw new Exception('Invalid product');
            }
            
            // Check if product exists
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
            $stmt->execute([$productId]);
            if (!$stmt->fetch()) {
                throw new Exception('Product not found');
            }
            
            // Check if already in wishlist
            $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Remove from wishlist
                $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
                
                // Log activity
                logUserActivity($userId, 'wishlist_remove', "Removed product ID: $productId from wishlist");
                
                echo json_encode([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Removed from wishlist',
                    'inWishlist' => false
                ]);
            } else {
                // Add to wishlist
                $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$userId, $productId]);
                
                // Log activity
                logUserActivity($userId, 'wishlist_add', "Added product ID: $productId to wishlist");
                
                echo json_encode([
                    'success' => true,
                    'action' => 'added',
                    'message' => 'Added to wishlist',
                    'inWishlist' => true
                ]);
            }
            break;
            
        case 'check':
            $productId = (int)($_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                throw new Exception('Invalid product');
            }
            
            $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $exists = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'inWishlist' => (bool)$exists
            ]);
            break;
            
        case 'get_all':
            $stmt = $pdo->prepare("
                SELECT w.*, p.name, p.price, p.image, p.stock_quantity, p.rating
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                WHERE w.user_id = ? AND p.is_active = 1
                ORDER BY w.created_at DESC
            ");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'items' => $items,
                'count' => count($items)
            ]);
            break;
            
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            logUserActivity($userId, 'wishlist_clear', "Cleared entire wishlist");
            
            echo json_encode([
                'success' => true,
                'message' => 'Wishlist cleared'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>