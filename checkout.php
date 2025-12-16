<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?redirect=checkout.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch user data with theme preference
$stmt = $pdo->prepare("SELECT u.*, ur.points_balance, ur.loyalty_tier FROM users u LEFT JOIN user_rewards ur ON u.id = ur.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// Fetch cart items with product details including color, size, and weight
$stmt = $pdo->prepare("
    SELECT 
        ci.*, 
        p.name, 
        p.price, 
        p.image, 
        p.stock_quantity,
        p.weight,
        (ci.quantity * p.price) as subtotal,
        (ci.quantity * COALESCE(p.weight, 0)) as item_total_weight
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ? AND p.is_active = 1
    ORDER BY ci.created_at DESC
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// Check if cart is empty
if (empty($cartItems)) {
    header('Location: index.php');
    exit();
}

// Calculate totals
$subtotal = array_sum(array_column($cartItems, 'subtotal'));
$totalWeight = array_sum(array_column($cartItems, 'item_total_weight'));
$tax = $subtotal * 0.08; // 8% tax

// Fetch available shipping methods for Sri Lanka
$shippingMethodsStmt = $pdo->prepare("
    SELECT sm.* 
    FROM shipping_methods sm
    LEFT JOIN shipping_zones sz ON sm.zone_id = sz.id
    WHERE sm.is_active = 1 
    AND (sz.countries LIKE '%LK%' OR sm.zone_id IS NULL)
    ORDER BY sm.sort_order
");
$shippingMethodsStmt->execute();
$shippingMethods = $shippingMethodsStmt->fetchAll();

// Calculate shipping for each method
$shippingOptions = [];
foreach ($shippingMethods as $method) {
    $baseCost = $method['base_cost'];
    $weightCost = $totalWeight * $method['cost_per_kg'];
    $totalShipping = $baseCost + $weightCost;
    
    // Check for free shipping threshold
    $freeShipping = false;
    if ($method['free_shipping_threshold'] && $subtotal >= $method['free_shipping_threshold']) {
        $totalShipping = 0;
        $freeShipping = true;
    }
    
    $shippingOptions[] = [
        'id' => $method['id'],
        'name' => $method['name'],
        'description' => $method['description'],
        'delivery_time' => $method['delivery_time'],
        'cost' => $totalShipping,
        'free_shipping' => $freeShipping,
        'base_cost' => $baseCost,
        'weight_cost' => $weightCost
    ];
}

// Default shipping (first option)
$defaultShipping = $shippingOptions[0] ?? ['cost' => 0];
$shipping = $defaultShipping['cost'];
$total = $subtotal + $tax + $shipping;

// Fetch user addresses
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

// Fetch user's saved payment cards
$cardsStmt = $pdo->prepare("
    SELECT pc.*, ua.city, ua.postal_code 
    FROM payment_cards pc
    LEFT JOIN user_addresses ua ON pc.billing_address_id = ua.id
    WHERE pc.user_id = ?
    ORDER BY pc.is_default DESC, pc.created_at DESC
");
$cardsStmt->execute([$userId]);
$savedCards = $cardsStmt->fetchAll();

// Get theme preference
$theme = $userData['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #475569;
            --card-bg: #1e293b;
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding-top: 80px;
        }

        .navbar {
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
        }

        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .checkout-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .form-control, .form-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-radius: 10px;
        }

        .form-control::placeholder, .form-select::placeholder {
            color: var(--text-muted);
        }

        .form-control:focus, .form-select:focus {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .address-card, .payment-card, .shipping-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            position: relative;
        }

        .address-card:hover, .payment-card:hover, .shipping-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .address-card.selected, .payment-card.selected, .shipping-card.selected {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
        }

        .shipping-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        .item-details {
            flex: 1;
        }

        .item-options {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .option-badge {
            background: var(--bg-tertiary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            border-bottom: none;
            padding-top: 1rem;
        }

        .checkbox-container {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin: 1rem 0;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .card-icon {
            font-size: 2rem;
            margin-right: 10px;
        }

        .weight-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-rocket me-2"></i>FutureMart
            </a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3">Checkout</span>
            </div>
        </div>
    </nav>

    <div class="checkout-container">
        <form id="checkoutForm">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Shipping Address -->
                    <div class="checkout-card">
                        <h2 class="section-title">
                            <i class="fas fa-map-marker-alt me-2"></i>Shipping Address
                        </h2>
                        
                        <?php if (!empty($addresses)): ?>
                            <div id="addressList">
                                <?php foreach ($addresses as $address): ?>
                                <div class="address-card <?php echo $address['is_default'] ? 'selected' : ''; ?>" 
                                     onclick="selectAddress(<?php echo $address['id']; ?>)">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="address" 
                                               value="<?php echo $address['id']; ?>" 
                                               id="address<?php echo $address['id']; ?>"
                                               <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="address<?php echo $address['id']; ?>">
                                            <strong><?php echo htmlspecialchars($address['title']); ?></strong>
                                            <?php if ($address['is_default']): ?>
                                                <span class="badge bg-primary ms-2">Default</span>
                                            <?php endif; ?>
                                            <br>
                                            <?php echo htmlspecialchars($address['address_line_1']); ?>
                                            <?php if ($address['address_line_2']): ?>
                                                , <?php echo htmlspecialchars($address['address_line_2']); ?>
                                            <?php endif; ?>
                                            <br>
                                            <?php echo htmlspecialchars($address['city']); ?>, 
                                            <?php echo htmlspecialchars($address['state']); ?> 
                                            <?php echo htmlspecialchars($address['postal_code']); ?>
                                            <br>
                                            <?php echo htmlspecialchars($address['country']); ?>
                                            <?php if ($address['phone']): ?>
                                                <br>Phone: <?php echo htmlspecialchars($address['phone']); ?>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No saved addresses. Please add a shipping address below.
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-outline-light mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#newAddressForm">
                            <i class="fas fa-plus me-2"></i>Add New Address
                        </button>

                        <div class="collapse mt-3" id="newAddressForm">
                            <div class="card" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-primary);">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Address Title</label>
                                            <input type="text" class="form-control" id="newAddressTitle" placeholder="Home, Office, etc.">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Address Line 1</label>
                                            <input type="text" class="form-control" id="newAddressLine1" placeholder="Street address">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Address Line 2 (Optional)</label>
                                            <input type="text" class="form-control" id="newAddressLine2" placeholder="Apartment, suite, etc.">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" id="newCity">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">State/Province</label>
                                            <input type="text" class="form-control" id="newState">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Postal Code</label>
                                            <input type="text" class="form-control" id="newPostalCode">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Country</label>
                                            <input type="text" class="form-control" id="newCountry" value="Sri Lanka">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" id="newPhone" placeholder="+94">
                                        </div>
                                        <div class="col-md-12">
                                            <button class="btn btn-primary" type="button" onclick="saveNewAddress()">
                                                <i class="fas fa-save me-2"></i>Save Address
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="checkout-card">
                        <h2 class="section-title">
                            <i class="fas fa-truck me-2"></i>Shipping Method
                        </h2>
                        
                        <?php foreach ($shippingOptions as $index => $option): ?>
                        <div class="shipping-card <?php echo $index === 0 ? 'selected' : ''; ?>" 
                             onclick="selectShipping(<?php echo $option['id']; ?>, <?php echo $option['cost']; ?>)">
                            <?php if ($option['free_shipping']): ?>
                                <span class="shipping-badge">FREE</span>
                            <?php endif; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shipping_method" 
                                       value="<?php echo $option['id']; ?>" 
                                       id="shipping<?php echo $option['id']; ?>"
                                       data-cost="<?php echo $option['cost']; ?>"
                                       <?php echo $index === 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="shipping<?php echo $option['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($option['name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($option['description']); ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($option['delivery_time']); ?>
                                            </small>
                                            <div class="weight-info">
                                                <i class="fas fa-weight me-1"></i>Total Weight: <?php echo number_format($totalWeight, 2); ?> kg
                                                <?php if (!$option['free_shipping']): ?>
                                                    (Base: $<?php echo number_format($option['base_cost'], 2); ?> + 
                                                    Weight: $<?php echo number_format($option['weight_cost'], 2); ?>)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($option['free_shipping']): ?>
                                                <span class="text-success fw-bold">FREE</span>
                                            <?php else: ?>
                                                <strong>$<?php echo number_format($option['cost'], 2); ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Payment Method -->
                    <div class="checkout-card">
                        <h2 class="section-title">
                            <i class="fas fa-credit-card me-2"></i>Payment Method
                        </h2>
                        
                        <!-- Cash on Delivery -->
                        <div class="payment-card selected" onclick="selectPayment('cod')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment" value="cod" id="paymentCOD" checked>
                                <label class="form-check-label" for="paymentCOD">
                                    <i class="fas fa-money-bill-wave card-icon"></i>
                                    <strong>Cash on Delivery</strong>
                                    <br>
                                    <small class="text-muted">Pay when you receive your order</small>
                                </label>
                            </div>
                        </div>

                        <!-- Credit/Debit Card -->
                        <div class="payment-card" onclick="selectPayment('card')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment" value="card" id="paymentCard">
                                <label class="form-check-label" for="paymentCard">
                                    <i class="fas fa-credit-card card-icon"></i>
                                    <strong>Credit/Debit Card</strong>
                                    <br>
                                    <small class="text-muted">Pay securely with your card</small>
                                </label>
                            </div>
                        </div>

                        <!-- Card Details (Hidden by default) -->
                        <div id="cardDetailsSection" style="display: none;" class="mt-3 p-3 border rounded" style="background: var(--bg-secondary);">
                            <?php if (!empty($savedCards)): ?>
                                <h6 class="mb-3">Saved Cards</h6>
                                <?php foreach ($savedCards as $card): ?>
                                <div class="saved-card mb-2 p-2 border rounded" style="cursor: pointer;" onclick="selectSavedCard(<?php echo $card['id']; ?>)">
                                    <input type="radio" name="saved_card" value="<?php echo $card['id']; ?>" id="card<?php echo $card['id']; ?>">
                                    <label for="card<?php echo $card['id']; ?>" style="cursor: pointer;">
                                        <i class="fab fa-cc-<?php echo strtolower($card['card_type']); ?> me-2"></i>
                                        <?php echo ucfirst($card['card_type']); ?> ****<?php echo $card['card_last_four']; ?>
                                        <small class="text-muted">(Exp: <?php echo $card['expiry_month']; ?>/<?php echo $card['expiry_year']; ?>)</small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <hr>
                            <?php endif; ?>
                            
                            <h6 class="mb-3">Or Enter New Card</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Card Holder Name</label>
                                    <input type="text" class="form-control" id="cardHolderName" placeholder="John Doe">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Card Number</label>
                                    <input type="text" class="form-control" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="cardExpiry" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="cardCVV" placeholder="123" maxlength="4">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="saveCard">
                                        <label class="form-check-label" for="saveCard">
                                            Save this card for future purchases
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Online Payment -->
                        <div class="payment-card" onclick="selectPayment('online')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment" value="online" id="paymentOnline">
                                <label class="form-check-label" for="paymentOnline">
                                    <i class="fas fa-wallet card-icon"></i>
                                    <strong>Online Payment</strong>
                                    <br>
                                    <small class="text-muted">PayPal, Bank Transfer, etc.</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="checkout-card">
                        <h2 class="section-title">
                            <i class="fas fa-file-contract me-2"></i>Terms & Conditions
                        </h2>
                        
                        <div class="checkbox-container">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                                <label class="form-check-label" for="acceptTerms">
                                    I have read and agree to the <a href="terms.php" target="_blank" class="text-primary">Terms and Conditions</a>
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="acceptPrivacy" required>
                                <label class="form-check-label" for="acceptPrivacy">
                                    I have read and agree to the <a href="privacy.php" target="_blank" class="text-primary">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="checkout-card">
                        <h2 class="section-title">
                            <i class="fas fa-comment me-2"></i>Order Notes (Optional)
                        </h2>
                        <textarea class="form-control" id="orderNotes" rows="3" 
                                  placeholder="Special instructions for delivery..."></textarea>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="order-summary" style="position: sticky; top: 100px;">
                        <div class="checkout-card">
                            <h2 class="section-title">Order Summary</h2>
                            
                            <div class="mb-3">
                                <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                             class="cart-item-image" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             onerror="this.src='assets/img/placeholder.jpg'">
                                    <?php else: ?>
                                        <div class="cart-item-image d-flex align-items-center justify-content-center" style="background: var(--bg-tertiary);">
                                            <i class="fas fa-box fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-details">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <small class="text-muted">
                                            Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?>
                                        </small>
                                        
                                        <div class="item-options">
                                            <?php if ($item['selected_color']): ?>
                                            <span class="option-badge">
                                                <i class="fas fa-palette"></i> <?php echo htmlspecialchars($item['selected_color']); ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($item['selected_size']): ?>
                                            <span class="option-badge">
                                                <i class="fas fa-ruler"></i> <?php echo htmlspecialchars($item['selected_size']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($item['weight']): ?>
                                        <div class="weight-info">
                                            <i class="fas fa-weight-hanging"></i> 
                                            <?php echo number_format($item['item_total_weight'], 2); ?> kg
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="fw-bold mt-1">$<?php echo number_format($item['subtotal'], 2); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Total Weight</span>
                                <span><?php echo number_format($totalWeight, 2); ?> kg</span>
                            </div>
                            <div class="summary-row" id="shippingRow">
                                <span>Shipping</span>
                                <span id="shippingCost">$<?php echo number_format($shipping, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Tax (8%)</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>Total</span>
                                <span id="totalAmount">$<?php echo number_format($total, 2); ?></span>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3" id="placeOrderBtn">
                                <i class="fas fa-check-circle me-2"></i>Place Order
                            </button>
                            <a href="index.php" class="btn btn-outline-light w-100 mt-2">
                                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store initial values
        const subtotal = <?php echo $subtotal; ?>;
        const tax = <?php echo $tax; ?>;
        const totalWeight = <?php echo $totalWeight; ?>;
        let currentShipping = <?php echo $shipping; ?>;

        // Format card number input
        document.getElementById('cardNumber')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Format expiry date
        document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0,2) + '/' + value.slice(2,4);
            }
            e.target.value = value;
        });

        // Only allow numbers in CVV
        document.getElementById('cardCVV')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        function selectAddress(addressId) {
            document.querySelectorAll('.address-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('address' + addressId).checked = true;
        }

        function selectShipping(methodId, cost) {
            document.querySelectorAll('.shipping-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('shipping' + methodId).checked = true;
            
            // Update shipping cost
            currentShipping = cost;
            updateTotal();
        }

        function selectPayment(method) {
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Show/hide card details
            const cardDetails = document.getElementById('cardDetailsSection');
            if (method === 'card') {
                cardDetails.style.display = 'block';
                document.getElementById('paymentCard').checked = true;
            } else {
                cardDetails.style.display = 'none';
                if (method === 'cod') document.getElementById('paymentCOD').checked = true;
                if (method === 'online') document.getElementById('paymentOnline').checked = true;
            }
        }

        function selectSavedCard(cardId) {
            document.querySelectorAll('input[name="saved_card"]').forEach(radio => {
                radio.checked = false;
            });
            document.getElementById('card' + cardId).checked = true;
        }

        function updateTotal() {
            const total = subtotal + tax + currentShipping;
            document.getElementById('shippingCost').textContent = currentShipping === 0 ? 'FREE' : '$'
                 + currentShipping.toFixed(2);
            document.getElementById('totalAmount').textContent = '$'
                 + total.toFixed(2);
        }

        function showNotification(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 10000; min-width: 300px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 3000);
        }

        async function saveNewAddress() {
            const addressData = {
                title: document.getElementById('newAddressTitle').value,
                address_line_1: document.getElementById('newAddressLine1').value,
                address_line_2: document.getElementById('newAddressLine2').value,
                city: document.getElementById('newCity').value,
                state: document.getElementById('newState').value,
                postal_code: document.getElementById('newPostalCode').value,
                country: document.getElementById('newCountry').value,
                phone: document.getElementById('newPhone').value
            };

            if (!addressData.title || !addressData.address_line_1 || !addressData.city || 
                !addressData.state || !addressData.postal_code) {
                showNotification('Please fill in all required fields', 'danger');
                return;
            }

            try {
                const response = await fetch('checkout_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=add_address&' + new URLSearchParams(addressData).toString()
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'danger');
                }
            } catch (error) {
                showNotification('Error saving address', 'danger');
            }
        }

        // Handle form submission
        document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const selectedAddress = document.querySelector('input[name="address"]:checked');
            const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
            const selectedPayment = document.querySelector('input[name="payment"]:checked');
            const termsAccepted = document.getElementById('acceptTerms').checked;
            const privacyAccepted = document.getElementById('acceptPrivacy').checked;
            const orderNotes = document.getElementById('orderNotes').value;

            // Validation
            if (!selectedAddress) {
                showNotification('Please select a shipping address', 'danger');
                return;
            }

            if (!selectedShipping) {
                showNotification('Please select a shipping method', 'danger');
                return;
            }

            if (!selectedPayment) {
                showNotification('Please select a payment method', 'danger');
                return;
            }

            if (!termsAccepted) {
                showNotification('Please accept the Terms and Conditions', 'danger');
                return;
            }

            if (!privacyAccepted) {
                showNotification('Please accept the Privacy Policy', 'danger');
                return;
            }

            // Card validation if card payment selected
            if (selectedPayment.value === 'card') {
                const usingSavedCard = document.querySelector('input[name="saved_card"]:checked');
                
                if (!usingSavedCard) {
                    const cardHolder = document.getElementById('cardHolderName').value;
                    const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
                    const cardExpiry = document.getElementById('cardExpiry').value;
                    const cardCVV = document.getElementById('cardCVV').value;

                    if (!cardHolder || !cardNumber || !cardExpiry || !cardCVV) {
                        showNotification('Please fill in all card details', 'danger');
                        return;
                    }

                    if (cardNumber.length < 13 || cardNumber.length > 19) {
                        showNotification('Invalid card number', 'danger');
                        return;
                    }

                    if (cardCVV.length < 3 || cardCVV.length > 4) {
                        showNotification('Invalid CVV', 'danger');
                        return;
                    }
                }
            }

            // Prepare order data
            const orderData = {
                action: 'place_order',
                address_id: selectedAddress.value,
                shipping_method_id: selectedShipping.value,
                payment_method: selectedPayment.value,
                notes: orderNotes,
                terms_accepted: termsAccepted ? 1 : 0,
                privacy_accepted: privacyAccepted ? 1 : 0,
                subtotal: subtotal,
                tax: tax,
                shipping: currentShipping,
                total: subtotal + tax + currentShipping,
                weight_total: totalWeight
            };

            // Add card details if applicable
            if (selectedPayment.value === 'card') {
                const usingSavedCard = document.querySelector('input[name="saved_card"]:checked');
                
                if (usingSavedCard) {
                    orderData.saved_card_id = usingSavedCard.value;
                } else {
                    orderData.card_holder = document.getElementById('cardHolderName').value;
                    orderData.card_number = document.getElementById('cardNumber').value.replace(/\s/g, '');
                    orderData.card_expiry = document.getElementById('cardExpiry').value;
                    orderData.card_cvv = document.getElementById('cardCVV').value;
                    orderData.save_card = document.getElementById('saveCard').checked ? 1 : 0;
                }
            }

            const btn = document.getElementById('placeOrderBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

            try {
                const response = await fetch('checkout_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(orderData).toString()
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'order-success.php?order=' + data.order_id;
                    }, 1500);
                } else {
                    showNotification(data.message, 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Place Order';
                }
            } catch (error) {
                showNotification('Error placing order', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Place Order';
            }
        });
    </script>
</body>
</html>