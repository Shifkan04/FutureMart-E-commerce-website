<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_address') {
            // Validate required fields
            $required = ['title', 'address_line_1', 'city', 'state', 'postal_code', 'country'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
                    exit();
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO user_addresses 
                (user_id, title, address_line_1, address_line_2, city, state, postal_code, country, phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $_POST['title'],
                $_POST['address_line_1'],
                $_POST['address_line_2'] ?? null,
                $_POST['city'],
                $_POST['state'],
                $_POST['postal_code'],
                $_POST['country'],
                $_POST['phone'] ?? null
            ]);
            
            logUserActivity($userId, 'address_add', 'New address added: ' . $_POST['title']);
            
            echo json_encode(['success' => true, 'message' => 'Address saved successfully!']);
            exit();
        }
        
        if ($action === 'place_order') {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Validate required data
                if (empty($_POST['address_id']) || empty($_POST['shipping_method_id']) || empty($_POST['payment_method'])) {
                    throw new Exception('Please complete all required fields');
                }
                
                // Check terms acceptance
                if (empty($_POST['terms_accepted']) || empty($_POST['privacy_accepted'])) {
                    throw new Exception('Please accept Terms & Conditions and Privacy Policy');
                }
                
                // Get cart items with full details
                // Get cart items with full details INCLUDING color and size
                $stmt = $pdo->prepare("
                    SELECT ci.*, 
                        p.name, p.price, p.stock_quantity, p.weight, p.sku,
                        (ci.quantity * p.price) as subtotal,
                        (ci.quantity * COALESCE(p.weight, 0)) as item_weight,
                        ci.selected_color_id,
                        ci.selected_size_id,
                        c.name as color_name,
                        c.hex_code as color_hex,
                        s.name as size_name
                    FROM cart_items ci
                    JOIN products p ON ci.product_id = p.id
                    LEFT JOIN colors c ON ci.selected_color_id = c.id
                    LEFT JOIN sizes s ON ci.selected_size_id = s.id
                    WHERE ci.user_id = ? AND p.is_active = 1
                ");
                $stmt->execute([$userId]);
                $cartItems = $stmt->fetchAll();
                
                if (empty($cartItems)) {
                    throw new Exception('Your cart is empty');
                }
                
                // Check stock availability
                foreach ($cartItems as $item) {
                    if ($item['quantity'] > $item['stock_quantity']) {
                        throw new Exception("Insufficient stock for {$item['name']}");
                    }
                }
                
                // Calculate totals
                $subtotal = array_sum(array_column($cartItems, 'subtotal'));
                $weightTotal = array_sum(array_column($cartItems, 'item_weight'));
                $shippingAmount = floatval($_POST['shipping_cost'] ?? 0);
                $taxAmount = ($subtotal + $shippingAmount) * 0.08; // 8% tax
                $totalAmount = $subtotal + $shippingAmount + $taxAmount;
                
                // Generate order number
                $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Get shipping method details
                $shippingStmt = $pdo->prepare("SELECT name FROM shipping_methods WHERE id = ?");
                $shippingStmt->execute([$_POST['shipping_method_id']]);
                $shippingMethod = $shippingStmt->fetch();
                
                // Create order
                $orderStmt = $pdo->prepare("
                    INSERT INTO orders (
                        order_number, user_id, status, 
                        subtotal, tax_amount, shipping_amount, total_amount,
                        payment_method, payment_status,
                        shipping_address_id, billing_address_id,
                        shipping_method, shipping_method_id,
                        weight_total, notes,
                        terms_accepted, terms_accepted_at,
                        privacy_accepted, privacy_accepted_at,
                        created_at
                    ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())
                ");
                
                $orderStmt->execute([
                    $orderNumber,
                    $userId,
                    $subtotal,
                    $taxAmount,
                    $shippingAmount,
                    $totalAmount,
                    $_POST['payment_method'],
                    $_POST['address_id'],
                    $_POST['address_id'], // Using same address for billing
                    $shippingMethod['name'] ?? 'Standard Delivery',
                    $_POST['shipping_method_id'],
                    $weightTotal,
                    $_POST['notes'] ?? null,
                    1, // terms_accepted
                    1  // privacy_accepted
                ]);
                
                $orderId = $pdo->lastInsertId();
                
                // Insert order items with color and size details
                $itemStmt = $pdo->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, quantity, 
                        unit_price, total_price,
                        selected_color_id, selected_size_id,
                        item_weight,
                        product_snapshot
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                                foreach ($cartItems as $item) {
                    // Prepare product snapshot with all details
                    $snapshot = [
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'price' => $item['price'],
                        'weight' => $item['weight']
                    ];
                    
                    // Add color details if selected (already fetched in JOIN)
                    if (!empty($item['selected_color_id'])) {
                        $snapshot['selected_color'] = [
                            'id' => $item['selected_color_id'],
                            'name' => $item['color_name'],
                            'hex_code' => $item['color_hex']
                        ];
                    }
                    
                    // Add size details if selected (already fetched in JOIN)
                    if (!empty($item['selected_size_id'])) {
                        $snapshot['selected_size'] = [
                            'id' => $item['selected_size_id'],
                            'name' => $item['size_name']
                        ];
                    }
                                        
                     $itemStmt->execute([
                        $orderId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['subtotal'],
                        $item['selected_color_id'] ?? null,   // ✓ CORRECT
                        $item['selected_size_id'] ?? null,    // ✓ CORRECT
                        $item['item_weight'],
                        json_encode($snapshot)
                    ]);
                    
                    // Update product stock
                    $updateStock = $pdo->prepare("
                        UPDATE products 
                        SET stock_quantity = stock_quantity - ? 
                        WHERE id = ?
                    ");
                    $updateStock->execute([$item['quantity'], $item['product_id']]);
                }
                
                // Log shipping calculation
                $shippingLogStmt = $pdo->prepare("
                    INSERT INTO shipping_calculations (
                        order_id, user_id, shipping_method_id,
                        subtotal, weight_total,
                        base_cost, weight_cost, final_shipping_cost,
                        free_shipping_applied
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Get shipping method details for calculation
                $methodStmt = $pdo->prepare("
                    SELECT base_cost, cost_per_kg, free_shipping_threshold 
                    FROM shipping_methods 
                    WHERE id = ?
                ");
                $methodStmt->execute([$_POST['shipping_method_id']]);
                $methodDetails = $methodStmt->fetch();
                
                $baseCost = floatval($methodDetails['base_cost']);
                $weightCost = floatval($methodDetails['cost_per_kg']) * $weightTotal;
                $freeShipping = ($methodDetails['free_shipping_threshold'] && 
                                $subtotal >= $methodDetails['free_shipping_threshold']) ? 1 : 0;
                
                $shippingLogStmt->execute([
                    $orderId,
                    $userId,
                    $_POST['shipping_method_id'],
                    $subtotal,
                    $weightTotal,
                    $baseCost,
                    $weightCost,
                    $shippingAmount,
                    $freeShipping
                ]);
                
                // Handle payment card if new card is being saved
                if (!empty($_POST['save_card']) && !empty($_POST['card_number'])) {
                    // In production, NEVER store full card numbers or CVV
                    // Use a payment gateway (Stripe, PayPal, etc.)
                    // This is just for demonstration
                    
                    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number']);
                    $lastFour = substr($cardNumber, -4);
                    
                    // Detect card type (simplified)
                    $cardType = 'visa';
                    if (substr($cardNumber, 0, 1) === '5') $cardType = 'mastercard';
                    elseif (substr($cardNumber, 0, 1) === '3') $cardType = 'amex';
                    
                    $expiry = explode('/', $_POST['card_expiry']);
                    
                    $cardStmt = $pdo->prepare("
                        INSERT INTO payment_cards (
                            user_id, card_type, card_last_four,
                            card_holder_name, expiry_month, expiry_year,
                            billing_address_id, is_default
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                    ");
                    
                    $cardStmt->execute([
                        $userId,
                        $cardType,
                        $lastFour,
                        $_POST['card_holder'],
                        $expiry[0],
                        '20' . $expiry[1],
                        $_POST['address_id']
                    ]);
                }
                
                // Clear cart
                $clearCart = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
                $clearCart->execute([$userId]);
                
                // Log activity
                logUserActivity($userId, 'order_placed', 'Placed order ' . $orderNumber);
                
                // Send order notification to admin
                $notifyStmt = $pdo->prepare("
                    INSERT INTO admin_messages (
                        sender_id, recipient_id, sender_type, recipient_type,
                        subject, message, priority, message_type
                    ) 
                    SELECT ?, id, 'user', 'admin',
                           'New Order Received',
                           CONCAT('New order #', ?, ' placed. Order value: $', ?),
                           'normal', 'order_issue'
                    FROM users WHERE role = 'admin'
                ");
                $notifyStmt->execute([$userId, $orderNumber, number_format($totalAmount, 2)]);
                
                // Commit transaction
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Order placed successfully!',
                    'order_id' => $orderId,
                    'order_number' => $orderNumber
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
            exit();
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>