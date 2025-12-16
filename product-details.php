<?php
require_once 'config_user.php';
require_once 'User.php';

startSecureSession();

// Database connection
$pdo = Database::getInstance()->getConnection();

// Check login status FIRST
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Get product ID from URL
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$productId) {
    header('Location: products.php');
    exit();
}

$user = new User();
$db = Database::getInstance();

// Fetch product details
$stmt = $db->prepare("
    SELECT 
        p.*,
        c.name as category_name,
        u.first_name as vendor_name,
        u.id as vendor_id
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.vendor_id = u.id
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit();
}

// Get product images
$imageStmt = $db->prepare("
    SELECT image_path, is_primary, sort_order 
    FROM product_images 
    WHERE product_id = ? 
    ORDER BY sort_order, is_primary DESC
");
$imageStmt->execute([$productId]);
$productImages = $imageStmt->fetchAll();

// Add main product image to the images array if not already included
$allImages = [];
if ($product['image']) {
    $allImages[] = ['image_path' => $product['image'], 'is_primary' => 1];
}
foreach ($productImages as $img) {
    $allImages[] = $img;
}

// Get product colors
$colorStmt = $db->prepare("
    SELECT c.id, c.name, c.hex_code 
    FROM product_colors pc 
    JOIN colors c ON pc.color_id = c.id 
    WHERE pc.product_id = ?
    ORDER BY c.name
");
$colorStmt->execute([$productId]);
$productColors = $colorStmt->fetchAll();

// Get product sizes
$sizeStmt = $db->prepare("
    SELECT s.id, s.name 
    FROM product_sizes ps 
    JOIN sizes s ON ps.size_id = s.id 
    WHERE ps.product_id = ?
    ORDER BY s.sort_order
");
$sizeStmt->execute([$productId]);
$productSizes = $sizeStmt->fetchAll();

// Get user data if logged in
$userData = null;
$isInWishlist = false;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userData = $user->getUserById($userId);

    // Check if product is in wishlist
    $wishStmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishStmt->execute([$userId, $productId]);
    $isInWishlist = $wishStmt->fetch() !== false;
}

// Get cart count for logged-in user
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

// Get product reviews
$reviewStmt = $db->prepare("
    SELECT 
        r.*,
        u.first_name,
        u.last_name,
        u.avatar
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviewStmt->execute([$productId]);
$reviews = $reviewStmt->fetchAll();

// Calculate rating distribution
$ratingStmt = $db->prepare("
    SELECT 
        rating,
        COUNT(*) as count
    FROM reviews
    WHERE product_id = ?
    GROUP BY rating
    ORDER BY rating DESC
");
$ratingStmt->execute([$productId]);
$ratingDistribution = $ratingStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalReviews = array_sum($ratingDistribution);

// Calculate average rating
$avgRating = 0;
if ($totalReviews > 0) {
    $totalRating = 0;
    foreach ($ratingDistribution as $rating => $count) {
        $totalRating += $rating * $count;
    }
    $avgRating = $totalRating / $totalReviews;
} else {
    $avgRating = $product['rating'] ?? 0;
}

$avgRating = number_format($avgRating, 1);

$theme = $userData['theme_preference'] ?? 'dark';

// Get related products
$relatedStmt = $db->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? AND is_active = 1 
    ORDER BY RAND() 
    LIMIT 4
");
$relatedStmt->execute([$product['category_id'], $productId]);
$relatedProducts = $relatedStmt->fetchAll();

// Calculate discount
$discount = 0;
if ($product['original_price'] > 0) {
    $discount = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);
}

$isInStock = $product['stock_quantity'] > 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - FutureMart</title>
    <meta name="description" content="<?= htmlspecialchars($product['short_description'] ?? '') ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="user/assets/css/dashboard-style.css" rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        [data-theme="dark"] {
            --bg-primary: #0f1419;
            --bg-secondary: #1a1f2e;
            --bg-tertiary: #242b3d;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --border-color: #2d3748;
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Breadcrumb */
        .breadcrumb {
            background: transparent;
            padding: 1.5rem 0;
            margin: 0;
        }

        .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--text-primary);
        }

        /* Product Section */
        .product-section {
            padding: 2rem 0;
        }

        /* Enhanced Image Gallery with Swiper */
        .product-image-gallery {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .main-swiper {
            width: 100%;
            height: 500px;
            margin-bottom: 1rem;
            border-radius: 15px;
            overflow: hidden;
        }

        .main-swiper .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-tertiary);
        }

        .main-swiper .swiper-slide img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .thumbs-swiper {
            height: 100px;
            padding: 10px 0;
        }

        .thumbs-swiper .swiper-slide {
            width: 100px;
            height: 100px;
            opacity: 0.4;
            cursor: pointer;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .thumbs-swiper .swiper-slide-thumb-active {
            opacity: 1;
            border-color: var(--primary);
        }

        .thumbs-swiper .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Swiper Navigation Buttons */
        .swiper-button-next,
        .swiper-button-prev {
            color: var(--primary);
            background: var(--bg-secondary);
            width: 45px;
            height: 45px;
            border-radius: 50%;
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 20px;
        }

        .swiper-pagination-bullet-active {
            background: var(--primary);
        }

        /* Product Info */
        .product-info {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--border-color);
        }

        .product-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stars {
            color: #fbbf24;
        }

        .stars .text-muted {
            color: #d1d5db !important;
        }

        .stars i {
            margin-right: 2px;
        }

        .rating-text {
            color: var(--text-secondary);
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .current-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .original-price {
            font-size: 1.5rem;
            text-decoration: line-through;
            color: var(--text-secondary);
        }

        .discount-badge {
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .stock-status.in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stock-status.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        /* Color Selection */
        .color-selection {
            margin-bottom: 1.5rem;
        }

        .color-label {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: block;
            color: var(--text-primary);
        }

        .color-options {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .color-option {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .color-option:hover {
            transform: scale(1.1);
        }

        .color-option.selected {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--bg-secondary), 0 0 0 4px var(--primary);
        }

        .color-option .color-swatch {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid var(--border-color);
        }

        /* Size Selection */
        .size-selection {
            margin-bottom: 1.5rem;
        }

        .size-label {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: block;
            color: var(--text-primary);
        }

        .size-options {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .size-option {
            min-width: 55px;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .size-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .size-option.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .product-description {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        /* Quantity Selector */
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quantity-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            background: var(--bg-tertiary);
            border-radius: 10px;
            overflow: hidden;
        }

        .quantity-btn {
            background: transparent;
            border: none;
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--primary);
            color: white;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Enhanced Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 60px;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn-add-cart,
        .btn-buy-now,
        .btn-wishlist {
            padding: 1rem;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-add-cart {
            background: var(--primary);
            color: white;
        }

        .btn-add-cart:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-buy-now {
            background: var(--success);
            color: white;
        }

        .btn-buy-now:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-wishlist {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            font-size: 1.5rem;
            padding: 0;
            width: 60px;
        }

        .btn-wishlist:hover {
            border-color: var(--danger);
            color: var(--danger);
        }

        .btn-wishlist.active {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        .btn-wishlist.active i {
            animation: heartBeat 0.5s;
        }

        @keyframes heartBeat {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.3); }
            50% { transform: scale(1.1); }
        }

        /* Product Meta */
        .product-meta {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-tertiary);
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .meta-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .meta-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .meta-value {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Tabs */
        .product-tabs {
            margin-top: 3rem;
        }

        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
            border-bottom-color: var(--primary);
        }

        .tab-content {
            padding: 2rem;
            background: var(--bg-secondary);
            border-radius: 0 0 20px 20px;
            border: 1px solid var(--border-color);
            border-top: none;
        }

        /* Specifications Table */
        .specifications-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .specifications-table tr {
            border-bottom: 1px solid var(--border-color);
        }

        .specifications-table tr:last-child {
            border-bottom: none;
        }

        .specifications-table td {
            padding: 1rem;
        }

        .specifications-table td:first-child {
            font-weight: 600;
            color: var(--text-secondary);
            width: 30%;
        }

        .specifications-table td:last-child {
            color: var(--text-primary);
        }

        /* Reviews */
        .review-summary {
            display: flex;
            gap: 2rem;
            padding: 2rem;
            background: var(--bg-tertiary);
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .review-score {
            text-align: center;
            min-width: 150px;
        }

        .score-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
        }

        .review-bars {
            flex: 1;
        }

        .review-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .bar-label {
            min-width: 80px;
            color: var(--text-secondary);
        }

        .bar-fill {
            flex: 1;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
        }

        .bar-progress {
            height: 100%;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .bar-count {
            min-width: 40px;
            text-align: right;
            color: var(--text-secondary);
        }

        .review-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .reviewer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .review-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Related Products */
        .related-products {
            margin-top: 3rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .product-card {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .product-card-img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            border-radius: 15px;
            margin-bottom: 1rem;
        }

        .product-card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .product-card-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .product-title {
                font-size: 1.5rem;
            }

            .current-price {
                font-size: 2rem;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .review-summary {
                flex-direction: column;
            }

            .main-swiper {
                height: 350px;
            }
        }

        /* Light Mode Styles */
        body.light-mode {
            background: #ffffff;
            color: #1e293b;
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body<?php echo ($isLoggedIn && $userData['theme_preference'] === 'light') ? ' class="light-mode"' : ''; ?>>
    <?php if ($isLoggedIn): ?>
        <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>

        <div class="cart-modal" id="cartModal">
            <div class="cart-header">
                <h5><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h5>
                <button class="btn btn-sm btn-outline-light" onclick="toggleCart()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="cart-body">
                <div class="cart-content" id="cartItems">
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                </div>

                <div class="cart-summary">
                    <h6>Order Summary</h6>
                    <div class="summary-details">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span class="text-success">Free</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (8%):</span>
                            <span id="tax">$0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong id="total">$0.00</strong>
                        </div>
                    </div>

                    <button class="btn btn-success w-100 mb-2" onclick="proceedToCheckout()">
                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                    </button>
                    <button class="btn btn-outline-danger w-100" onclick="clearCart()">
                        <i class="fas fa-trash me-2"></i>Clear Cart
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-rocket me-2"></i>FutureMart
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <?php if ($isLoggedIn): ?>
                        <a href="#" class="nav-link cart-icon" onclick="toggleCart(); return false;">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
                        </a>

                        <div class="dropdown">
                            <div class="user-profile" data-bs-toggle="dropdown">
                                <?php if (!empty($userData['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($userData['avatar']); ?>"
                                        alt="Profile" class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <?= strtoupper(substr($userData['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>

                                <span class="d-none d-lg-inline">
                                    <?= htmlspecialchars($userData['first_name']); ?>
                                </span>
                            </div>

                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="user/dashboard.php">
                                        <i class="fas fa-user me-2"></i>Profile
                                    </a></li>
                                <li><a class="dropdown-item" href="user/orders.php">
                                        <i class="fas fa-shopping-bag me-2"></i>My Orders
                                    </a></li>
                                <li><a class="dropdown-item" href="settings.php">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                            </ul>
                        </div>

                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-user me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <li class="breadcrumb-item"><a href="products.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
            </ol>
        </nav>
    </div>

    <!-- Product Section -->
    <div class="container product-section">
        <div class="row">
            <!-- Enhanced Product Images with Swiper Carousel -->
            <div class="col-lg-6 mb-4">
                <div class="product-image-gallery">
                    <!-- Main Image Slider -->
                    <div class="swiper main-swiper">
                        <div class="swiper-wrapper">
                            <?php if (!empty($allImages)): ?>
                                <?php foreach ($allImages as $img): ?>
                                    <div class="swiper-slide">
                                        <img src="<?= htmlspecialchars($img['image_path']) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             onerror="this.src='assets/img/placeholder.jpg'">
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="swiper-slide">
                                    <img src="assets/img/placeholder.jpg" alt="No image">
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Navigation buttons -->
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <!-- Pagination -->
                        <div class="swiper-pagination"></div>
                    </div>

                    <!-- Thumbnail Slider -->
                    <?php if (count($allImages) > 1): ?>
                    <div class="swiper thumbs-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($allImages as $img): ?>
                                <div class="swiper-slide">
                                    <img src="<?= htmlspecialchars($img['image_path']) ?>" 
                                         alt="Thumbnail"
                                         onerror="this.src='assets/img/placeholder.jpg'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info">
                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

                    <div class="product-rating">
                        <div class="stars">
                            <?php
                            $rating = floatval($avgRating);
                            $fullStars = floor($rating);
                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                            $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);

                            for ($i = 0; $i < $fullStars; $i++) {
                                echo '<i class="fas fa-star"></i>';
                            }
                            if ($hasHalfStar) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            }
                            for ($i = 0; $i < $emptyStars; $i++) {
                                echo '<i class="far fa-star"></i>';
                            }
                            ?>
                        </div>
                        <span class="rating-text">
                            <?= $avgRating ?> (<?= $totalReviews ?> reviews)
                        </span>
                    </div>

                    <div class="product-price">
                        <span class="current-price">$<?= number_format($product['price'], 2) ?></span>
                        <?php if ($product['original_price'] > 0): ?>
                            <span class="original-price">$<?= number_format($product['original_price'], 2) ?></span>
                            <span class="discount-badge"><?= $discount ?>% OFF</span>
                        <?php endif; ?>
                    </div>

                    <div class="stock-status <?= $isInStock ? 'in-stock' : 'out-of-stock' ?>">
                        <i class="fas <?= $isInStock ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= $isInStock ? 'In Stock (' . $product['stock_quantity'] . ' available)' : 'Out of Stock' ?>
                    </div>

                    <p class="product-description">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>

                    <!-- Color Selection -->
                    <?php if (!empty($productColors)): ?>
                    <div class="color-selection">
                        <span class="color-label">Available Colors:</span>
                        <div class="color-options">
                            <?php foreach ($productColors as $color): ?>
                                <div class="color-option" 
                                     data-color-id="<?= $color['id'] ?>"
                                     data-color-name="<?= htmlspecialchars($color['name']) ?>"
                                     title="<?= htmlspecialchars($color['name']) ?>"
                                     onclick="selectColor(this)">
                                    <div class="color-swatch" style="background-color: <?= htmlspecialchars($color['hex_code']) ?>;"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted" id="selectedColorText">Select a color</small>
                    </div>
                    <?php endif; ?>

                    <!-- Size Selection -->
                    <?php if (!empty($productSizes)): ?>
                    <div class="size-selection">
                        <span class="size-label">Available Sizes:</span>
                        <div class="size-options">
                            <?php foreach ($productSizes as $size): ?>
                                <div class="size-option" 
                                     data-size-id="<?= $size['id'] ?>"
                                     data-size-name="<?= htmlspecialchars($size['name']) ?>"
                                     onclick="selectSize(this)">
                                    <?= htmlspecialchars($size['name']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted" id="selectedSizeText">Select a size</small>
                    </div>
                    <?php endif; ?>

                    <?php if ($isInStock): ?>
                        <div class="quantity-selector">
                            <span class="quantity-label">Quantity:</span>
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="decreaseQuantity()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" readonly>
                                <button class="quantity-btn" onclick="increaseQuantity()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Enhanced Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($isInStock): ?>
                            <button class="btn-add-cart" onclick="addToCart(<?= $productId ?>)">
                                <i class="fas fa-shopping-cart"></i>
                                Add to Cart
                            </button>
                            <button class="btn-buy-now" onclick="buyNow(<?= $productId ?>)">
                                <i class="fas fa-bolt"></i>
                                Buy Now
                            </button>
                        <?php else: ?>
                            <button class="btn-add-cart" disabled style="grid-column: span 2;">
                                <i class="fas fa-times-circle"></i>
                                Out of Stock
                            </button>
                        <?php endif; ?>

                        <?php if ($userData): ?>
                            <button class="btn-wishlist <?= $isInWishlist ? 'active' : '' ?>"
                                onclick="toggleWishlist(<?= $productId ?>)"
                                id="wishlistBtn"
                                title="<?= $isInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>">
                                <i class="fas fa-heart"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn-wishlist" onclick="location.href='login.php'" title="Login to Save">
                                <i class="fas fa-heart"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="product-meta">
                        <div class="meta-item">
                            <span class="meta-label">SKU:</span>
                            <span class="meta-value"><?= htmlspecialchars($product['sku']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Category:</span>
                            <span class="meta-value"><?= htmlspecialchars($product['category_name']) ?></span>
                        </div>
                        <?php if ($product['brand']): ?>
                            <div class="meta-item">
                                <span class="meta-label">Brand:</span>
                                <span class="meta-value"><?= htmlspecialchars($product['brand']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['vendor_name']): ?>
                            <div class="meta-item">
                                <span class="meta-label">Sold by:</span>
                                <span class="meta-value"><?= htmlspecialchars($product['vendor_name']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs with Specifications -->
        <div class="product-tabs">
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button">
                        Description
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button">
                        Specifications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                        Reviews (<?= $totalReviews ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="productTabContent">
                <!-- Description Tab -->
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <h3>Product Description</h3>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>

                <!-- Specifications Tab -->
                <div class="tab-pane fade" id="specifications" role="tabpanel">
                    <h3>Product Specifications</h3>
                    <table class="specifications-table">
                        <tbody>
                            <tr>
                                <td>Product Name</td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                            </tr>
                            <tr>
                                <td>SKU</td>
                                <td><?= htmlspecialchars($product['sku']) ?></td>
                            </tr>
                            <tr>
                                <td>Brand</td>
                                <td><?= htmlspecialchars($product['brand'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td>Category</td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                            </tr>
                            <?php if ($product['weight']): ?>
                            <tr>
                                <td>Weight</td>
                                <td><?= htmlspecialchars($product['weight']) ?> kg</td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($product['dimensions']): ?>
                            <tr>
                                <td>Dimensions</td>
                                <td><?= htmlspecialchars($product['dimensions']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($productColors)): ?>
                            <tr>
                                <td>Available Colors</td>
                                <td>
                                    <?php 
                                    $colorNames = array_map(function($c) { return $c['name']; }, $productColors);
                                    echo htmlspecialchars(implode(', ', $colorNames));
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($productSizes)): ?>
                            <tr>
                                <td>Available Sizes</td>
                                <td>
                                    <?php 
                                    $sizeNames = array_map(function($s) { return $s['name']; }, $productSizes);
                                    echo htmlspecialchars(implode(', ', $sizeNames));
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td>Stock Status</td>
                                <td>
                                    <?php if ($isInStock): ?>
                                        <span style="color: var(--success); font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> In Stock (<?= $product['stock_quantity'] ?> available)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--danger); font-weight: 600;">
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Price</td>
                                <td>
                                    <strong style="color: var(--primary); font-size: 1.2rem;">$<?= number_format($product['price'], 2) ?></strong>
                                    <?php if ($product['original_price'] > 0 && $product['original_price'] != $product['price']): ?>
                                        <span style="text-decoration: line-through; color: var(--text-secondary); margin-left: 10px;">
                                            $<?= number_format($product['original_price'], 2) ?>
                                        </span>
                                        <span class="discount-badge" style="margin-left: 10px; font-size: 0.9rem;">
                                            <?= $discount ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <?php if ($totalReviews > 0): ?>
                        <div class="review-summary">
                            <div class="review-score">
                                <div class="score-number"><?= $avgRating ?></div>
                                <div class="stars">
                                    <?php
                                    $rating = floatval($avgRating);
                                    $fullStars = floor($rating);
                                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);

                                    for ($i = 0; $i < $fullStars; $i++) {
                                        echo '<i class="fas fa-star"></i>';
                                    }
                                    if ($hasHalfStar) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    }
                                    for ($i = 0; $i < $emptyStars; $i++) {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                </div>
                                <p class="mt-2"><?= $totalReviews ?> Reviews</p>
                            </div>

                            <div class="review-bars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <?php
                                    $count = $ratingDistribution[$i] ?? 0;
                                    $percentage = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
                                    ?>
                                    <div class="review-bar">
                                        <span class="bar-label"><?= $i ?> Stars</span>
                                        <div class="bar-fill">
                                            <div class="bar-progress" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <span class="bar-count"><?= $count ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                <?php if ($review['avatar']): ?>
                                                    <img src="uploads/avatars/<?= htmlspecialchars($review['avatar']) ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($review['first_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="reviewer-name">
                                                    <?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?>
                                                </div>
                                                <div class="stars">
                                                    <?php
                                                    $reviewRating = intval($review['rating']);
                                                    for ($i = 1; $i <= 5; $i++):
                                                    ?>
                                                        <i class="fas fa-star<?= $i <= $reviewRating ? '' : ' text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-date">
                                            <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                        </div>
                                    </div>
                                    <p class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-star" style="font-size: 3rem; color: var(--text-secondary);"></i>
                            <h3 class="mt-3">No Reviews Yet</h3>
                            <p class="text-muted">Be the first to review this product!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <h2 class="section-title">Related Products</h2>
                <div class="row">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="product-card" onclick="location.href='product-details.php?id=<?= $relatedProduct['id'] ?>'">
                                <img src="<?= htmlspecialchars($relatedProduct['image'] ?: 'assets/img/placeholder.jpg') ?>"
                                    alt="<?= htmlspecialchars($relatedProduct['name']) ?>"
                                    class="product-card-img"
                                    onerror="this.src='assets/img/placeholder.jpg'">
                                <h5 class="product-card-title"><?= htmlspecialchars($relatedProduct['name']) ?></h5>
                                <div class="product-card-price">$<?= number_format($relatedProduct['price'], 2) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer style="background: var(--bg-secondary); padding: 2rem 0; margin-top: 4rem; border-top: 1px solid var(--border-color);">
        <div class="container text-center">
            <p style="color: var(--text-secondary); margin: 0;">
                &copy; 2025 FutureMart. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Global variables for selections
        var selectedColorId = null;
        var selectedSizeId = null;

        // Initialize Swiper Carousels on page load
        window.addEventListener('load', function() {
            <?php if (count($allImages) > 1): ?>
            // Initialize thumbnail swiper first
            var thumbsSwiper = new Swiper('.thumbs-swiper', {
                spaceBetween: 10,
                slidesPerView: 4,
                freeMode: true,
                watchSlidesProgress: true,
                breakpoints: {
                    320: {
                        slidesPerView: 3,
                    },
                    640: {
                        slidesPerView: 4,
                    },
                    768: {
                        slidesPerView: 5,
                    }
                }
            });

            // Initialize main swiper with thumbs
            var mainSwiper = new Swiper('.main-swiper', {
                spaceBetween: 10,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                thumbs: {
                    swiper: thumbsSwiper,
                },
            });
            <?php else: ?>
            // Single image - no thumbs
            var mainSwiper = new Swiper('.main-swiper', {
                spaceBetween: 10,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
            });
            <?php endif; ?>
        });

        // Color Selection Function
        function selectColor(element) {
            // Remove selected class from all colors
            document.querySelectorAll('.color-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked color
            element.classList.add('selected');
            
            // Store selected color
            selectedColorId = element.getAttribute('data-color-id');
            const colorName = element.getAttribute('data-color-name');
            
            // Update text
            document.getElementById('selectedColorText').textContent = 'Selected: ' + colorName;
        }

        // Size Selection Function
        function selectSize(element) {
            // Remove selected class from all sizes
            document.querySelectorAll('.size-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked size
            element.classList.add('selected');
            
            // Store selected size
            selectedSizeId = element.getAttribute('data-size-id');
            const sizeName = element.getAttribute('data-size-name');
            
            // Update text
            document.getElementById('selectedSizeText').textContent = 'Selected: ' + sizeName;
        }

        // Quantity Controls
        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const maxQuantity = parseInt(quantityInput.getAttribute('max'));
            let currentQuantity = parseInt(quantityInput.value);
            
            if (currentQuantity < maxQuantity) {
                quantityInput.value = currentQuantity + 1;
            } else {
                showNotification('Maximum stock quantity reached', 'warning');
            }
        }

        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            let currentQuantity = parseInt(quantityInput.value);
            
            if (currentQuantity > 1) {
                quantityInput.value = currentQuantity - 1;
            }
        }

        // Wishlist Toggle
        async function toggleWishlist(productId) {
            const wishlistBtn = document.getElementById('wishlistBtn');
            const originalHTML = wishlistBtn.innerHTML;
            
            wishlistBtn.disabled = true;
            wishlistBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'toggle');
                formData.append('product_id', productId);
                
                const response = await fetch('wishlist_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.action === 'added') {
                        wishlistBtn.classList.add('active');
                        wishlistBtn.title = 'Remove from Wishlist';
                        showNotification('Added to wishlist!', 'success');
                    } else {
                        wishlistBtn.classList.remove('active');
                        wishlistBtn.title = 'Add to Wishlist';
                        showNotification('Removed from wishlist', 'info');
                    }
                    wishlistBtn.innerHTML = '<i class="fas fa-heart"></i>';
                } else {
                    wishlistBtn.innerHTML = originalHTML;
                    showNotification(data.message || 'Error updating wishlist', 'danger');
                }
            } catch (error) {
                console.error('Wishlist error:', error);
                wishlistBtn.innerHTML = originalHTML;
                showNotification('Error updating wishlist', 'danger');
            } finally {
                wishlistBtn.disabled = false;
            }
        }

       // Updated Add to Cart Function
async function addToCart(productId) {
    const quantity = parseInt(document.getElementById('quantity').value) || 1;

    <?php if (!$isLoggedIn): ?>
        showNotification('Please login to add items to cart', 'warning');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 1500);
        return;
    <?php endif; ?>

    // Check if color/size selection is required
    const hasColors = document.querySelectorAll('.color-option').length > 0;
    const hasSizes = document.querySelectorAll('.size-option').length > 0;
    
    if (hasColors && !selectedColorId) {
        showNotification('Please select a color', 'warning');
        return;
    }
    
    if (hasSizes && !selectedSizeId) {
        showNotification('Please select a size', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    // Add selected color and size
    if (selectedColorId) {
        formData.append('selected_color', selectedColorId);
        const colorName = document.querySelector(`.color-option[data-color-id="${selectedColorId}"]`)
                                   ?.getAttribute('data-color-name');
        formData.append('selected_color_name', colorName || '');
    }
    
    if (selectedSizeId) {
        formData.append('selected_size', selectedSizeId);
        const sizeName = document.querySelector(`.size-option[data-size-id="${selectedSizeId}"]`)
                                  ?.getAttribute('data-size-name');
        formData.append('selected_size_name', sizeName || '');
    }

    try {
        const response = await fetch('ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification(`Added ${quantity} item(s) to cart!`, 'success');
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                cartCount.textContent = data.data.cart_count || data.cart_count || (parseInt(cartCount.textContent) + quantity);
            }

            if (document.getElementById('cartModal').classList.contains('open')) {
                loadCartItems();
            }
        } else {
            showNotification(data.message || 'Error adding to cart', 'danger');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('Error adding to cart', 'danger');
    }
}

// Updated Buy Now Function
async function buyNow(productId) {
    <?php if (!$isLoggedIn): ?>
        showNotification('Please login to continue', 'warning');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 1500);
        return;
    <?php endif; ?>

    const quantity = parseInt(document.getElementById('quantity').value) || 1;
    
    // Check if color/size selection is required
    const hasColors = document.querySelectorAll('.color-option').length > 0;
    const hasSizes = document.querySelectorAll('.size-option').length > 0;
    
    if (hasColors && !selectedColorId) {
        showNotification('Please select a color', 'warning');
        return;
    }
    
    if (hasSizes && !selectedSizeId) {
        showNotification('Please select a size', 'warning');
        return;
    }

    // Add to cart first with color and size
    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    if (selectedColorId) {
        formData.append('selected_color', selectedColorId);
        const colorName = document.querySelector(`.color-option[data-color-id="${selectedColorId}"]`)
                                   ?.getAttribute('data-color-name');
        formData.append('selected_color_name', colorName || '');
    }
    
    if (selectedSizeId) {
        formData.append('selected_size', selectedSizeId);
        const sizeName = document.querySelector(`.size-option[data-size-id="${selectedSizeId}"]`)
                                  ?.getAttribute('data-size-name');
        formData.append('selected_size_name', sizeName || '');
    }

    try {
        const response = await fetch('ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Redirect to checkout
            window.location.href = 'checkout.php';
        } else {
            showNotification(data.message || 'Error processing request', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error processing request', 'danger');
    }
}
        // Cart Management Functions
        async function loadCartItems() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_cart');

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    displayCartItems(data.data.items, data.data.total);
                } else {
                    console.error('Error loading cart:', data.message);
                }
            } catch (error) {
                console.error('Error loading cart:', error);
            }
        }

        function displayCartItems(items, total) {
            const cartItemsContainer = document.getElementById('cartItems');

            if (!items || items.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
            } else {
                cartItemsContainer.innerHTML = items.map(item => `
                    <div class="cart-item">
                        <div class="d-flex align-items-center gap-3">
                            <img src="${item.image || 'assets/img/placeholder.jpg'}" 
                                 alt="${item.name}" 
                                 class="cart-item-image"
                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 10px;"
                                 onerror="this.src='assets/img/placeholder.jpg'">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.name}</h6>
                                <p class="text-muted mb-1 small">${parseFloat(item.price).toFixed(2)} each</p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                                <button class="btn btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div>
                                <strong>${parseFloat(item.subtotal).toFixed(2)}</strong>
                                <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            const subtotal = parseFloat(total) || 0;
            const tax = subtotal * 0.08;
            const totalAmount = subtotal + tax;

            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' + tax.toFixed(2);
            document.getElementById('total').textContent = '$' + totalAmount.toFixed(2);
        }

        async function updateCartQuantity(cartItemId, newQuantity) {
            if (newQuantity <= 0) {
                removeFromCart(cartItemId);
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'update_cart_quantity');
                formData.append('cart_item_id', cartItemId);
                formData.append('quantity', newQuantity);

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    loadCartItems();
                    updateCartCount();
                } else {
                    showNotification(data.message || 'Error updating quantity', 'danger');
                }
            } catch (error) {
                console.error('Error updating cart:', error);
                showNotification('Error updating cart', 'danger');
            }
        }

        async function removeFromCart(cartItemId) {
            if (!confirm('Remove this item from cart?')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'remove_from_cart');
                formData.append('cart_item_id', cartItemId);

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Item removed from cart', 'success');
                    loadCartItems();
                    updateCartCount();
                } else {
                    showNotification(data.message || 'Error removing item', 'danger');
                }
            } catch (error) {
                console.error('Error removing item:', error);
                showNotification('Error removing item', 'danger');
            }
        }

        async function clearCart() {
            if (!confirm('Are you sure you want to clear your cart?')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'clear_cart');

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Cart cleared successfully', 'success');
                    loadCartItems();
                    updateCartCount();
                } else {
                    showNotification(data.message || 'Error clearing cart', 'danger');
                }
            } catch (error) {
                console.error('Error clearing cart:', error);
                showNotification('Error clearing cart', 'danger');
            }
        }

        async function updateCartCount() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_cart');

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const cartCount = document.getElementById('cartCount');
                    if (cartCount) {
                        cartCount.textContent = data.data.count || 0;
                    }
                }
            } catch (error) {
                console.error('Error updating cart count:', error);
            }
        }

        function toggleCart() {
            <?php if (!$isLoggedIn): ?>
                showNotification('Please login to view your cart', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            <?php endif; ?>

            const cartModal = document.getElementById('cartModal');
            const cartOverlay = document.getElementById('cartOverlay');

            cartModal.classList.toggle('open');
            cartOverlay.classList.toggle('active');

            if (cartModal.classList.contains('open')) {
                loadCartItems();
            }
        }

        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }

        // Notification System
        function showNotification(message, type = 'success') {
            document.querySelectorAll('.alert.position-fixed').forEach(alert => {
                alert.remove();
            });

            const notification = document.createElement('div');
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'danger' ? 'alert-danger' :
                type === 'warning' ? 'alert-warning' : 'alert-info';
            const icon = type === 'success' ? 'fa-check-circle' :
                type === 'danger' ? 'fa-exclamation-circle' :
                type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';

            notification.innerHTML = `
                <div class="alert ${alertClass} position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none;">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>