<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to add items to cart'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);
            
            if ($productId <= 0 || $quantity <= 0) {
                throw new Exception('Invalid product or quantity');
            }
            
            // Check if product exists and has stock
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            if ($product['stock_quantity'] < $quantity) {
                throw new Exception('Insufficient stock available');
            }
            
            // Check if product already in cart
            $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $existingItem = $stmt->fetch();
            
            if ($existingItem) {
                // Update quantity
                $newQuantity = $existingItem['quantity'] + $quantity;
                
                if ($newQuantity > $product['stock_quantity']) {
                    throw new Exception('Cannot add more items than available stock');
                }
                
                $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newQuantity, $existingItem['id']]);
            } else {
                // Insert new item
                $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $productId, $quantity]);
            }
            
            // Get updated cart count
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            $cartCount = $result['total'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'cartCount' => $cartCount
            ]);
            break;
            
        case 'update':
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 0);
            
            if ($cartItemId <= 0) {
                throw new Exception('Invalid cart item');
            }
            
            if ($quantity <= 0) {
                // Remove item if quantity is 0
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $stmt->execute([$cartItemId, $userId]);
            } else {
                // Update quantity
                $stmt = $pdo->prepare("
                    UPDATE cart_items ci
                    JOIN products p ON ci.product_id = p.id
                    SET ci.quantity = ?
                    WHERE ci.id = ? AND ci.user_id = ? AND p.stock_quantity >= ?
                ");
                $stmt->execute([$quantity, $cartItemId, $userId, $quantity]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Insufficient stock or invalid item');
                }
            }
            
            // Get updated cart count
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            $cartCount = $result['total'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'message' => 'Cart updated successfully',
                'cartCount' => $cartCount
            ]);
            break;
            
        case 'remove':
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
            
            if ($cartItemId <= 0) {
                throw new Exception('Invalid cart item');
            }
            
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartItemId, $userId]);
            
            // Get updated cart count
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            $cartCount = $result['total'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'message' => 'Item removed from cart',
                'cartCount' => $cartCount
            ]);
            break;
            
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'cartCount' => 0
            ]);
            break;
            
        case 'get':
            // Get all cart items with product details
            $stmt = $pdo->prepare("
                SELECT ci.*, p.name, p.price, p.image, p.stock_quantity,
                       (ci.quantity * p.price) as subtotal
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.user_id = ? AND p.is_active = 1
                ORDER BY ci.created_at DESC
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll();
            
            $total = array_sum(array_column($cartItems, 'subtotal'));
            
            echo json_encode([
                'success' => true,
                'items' => $cartItems,
                'total' => $total,
                'count' => array_sum(array_column($cartItems, 'quantity'))
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