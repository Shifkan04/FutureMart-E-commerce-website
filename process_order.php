<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();

    // Validate address
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['address_id'], $userId]);
    $address = $stmt->fetch();
    
    if (!$address) {
        throw new Exception('Invalid shipping address');
    }

    // Fetch cart items with product details
    $stmt = $pdo->prepare("
        SELECT ci.*, p.name, p.price, p.stock_quantity, p.weight 
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.user_id = ? AND p.is_active = 1
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll();

    if (empty($cartItems)) {
        throw new Exception('Cart is empty');
    }

    // Calculate totals
    $subtotal = 0;
    $totalWeight = 0;
    
    foreach ($cartItems as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $totalWeight += $item['weight'] * $item['quantity'];
        
        // Check stock
        if ($item['stock_quantity'] < $item['quantity']) {
            throw new Exception('Insufficient stock for ' . $item['name']);
        }
    }

    // Calculate shipping
    $shippingCharge = 0;
    if ($subtotal < 50) {
        $shippingCharge = 5;
    }
    if ($totalWeight > 5) {
        $extraWeight = ceil($totalWeight - 5);
        $shippingCharge += ($extraWeight * 2);
    }

    $tax = $subtotal * 0.08;
    $total = $subtotal + $tax + $shippingCharge;

    // Generate order number
    $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, status, total_amount, subtotal, 
            tax_amount, shipping_amount, payment_method, payment_status,
            shipping_address_id, billing_address_id
        ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    
    $stmt->execute([
        $orderNumber,
        $userId,
        $total,
        $subtotal,
        $tax,
        $shippingCharge,
        $data['payment_method'],
        $data['address_id'],
        $data['address_id']
    ]);

    $orderId = $pdo->lastInsertId();

    // Insert order items with selected options
    foreach ($cartItems as $item) {
        $productOptions = $data['product_options'][$item['product_id']] ?? [];
        
        $productSnapshot = json_encode([
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'color' => $productOptions['color'] ?? null,
            'size' => $productOptions['size'] ?? null
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, unit_price, 
                total_price, product_snapshot
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['price'] * $item['quantity'],
            $productSnapshot
        ]);

        // Update stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }

    // Store card details if provided (encrypt in production!)
    if (isset($data['card_details']) && $data['payment_method'] === 'card') {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_id = ? 
            WHERE id = ?
        ");
        $stmt->execute(['CARD_' . $data['card_details']['last_four'], $orderId]);
    }

    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, activity_description, ip_address)
        VALUES (?, 'order_placed', ?, ?)
    ");
    $stmt->execute([
        $userId,
        'Placed order ' . $orderNumber,
        $_SERVER['REMOTE_ADDR']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'order_id' => $orderId,
        'message' => 'Order placed successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>