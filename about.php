<?php
// Start session and include config
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userData = null;

if ($isLoggedIn) {
    // Fetch user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}

// Get cart count for logged-in user
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

// Get site statistics from database
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$totalCustomers = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
$totalProducts = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'");
$totalOrders = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(DISTINCT category_id) as total FROM products");
$totalCategories = $stmt->fetch()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --bg: #f8fafc;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-light);
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Navbar Styles */
        .navbar {
            background: #0f172a;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: #0f172a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }

        .nav-link.active {
            color: var(--primary-color) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--gradient-1);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .cart-icon {
            position: relative;
            cursor: pointer;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .btn-outline {
            background: var(--gradient-2);
            border: none;
            padding: 0.50rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-outline:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(236, 72, 153, 0.4);
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.50rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .user-avatar-placeholder {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid var(--primary-color);
        }

        .dropdown-menu {
            background: linear-gradient(to right bottom, rgba(191, 244, 228, 0.31), rgba(155, 209, 245, 0.57));
            backdrop-filter: blur(200px);
            border-radius: 10px;
            padding: 0.5rem 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown-menu hr {
            border: none;
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
            margin: 0.5rem 0;
        }

        .dropdown-menu a {
            color: var(--card-bg);
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: linear-gradient(to right bottom, rgba(191, 244, 228, 0.5), rgba(155, 209, 245, 0.7));
        }

        /* Hero Section */
        .hero {
            padding: 150px 0 80px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="none"><defs><radialGradient id="g" cx="50%" cy="50%"><stop offset="0%" stop-color="%23667eea" stop-opacity="0.3"/><stop offset="100%" stop-color="%23667eea" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23g)"/><circle cx="800" cy="300" r="150" fill="url(%23g)"/><circle cx="300" cy="700" r="120" fill="url(%23g)"/></svg>');
            opacity: 0.5;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        /* About Section */
        .about-section {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .about-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .about-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .about-card h3 {
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .about-card p {
            color: var(--text-muted);
            line-height: 1.8;
        }

        /* Stats Section */
        .stats-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .stats-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
        }

        .stats-icon.icon-1 { background: var(--gradient-1); }
        .stats-icon.icon-2 { background: var(--gradient-2); }
        .stats-icon.icon-3 { background: var(--gradient-3); }
        .stats-icon.icon-4 { background: var(--gradient-4); }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* Team Section */
        .team-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }

        .team-avatar.avatar-1 { background: var(--gradient-1); }
        .team-avatar.avatar-2 { background: var(--gradient-2); }
        .team-avatar.avatar-3 { background: var(--gradient-3); }
        .team-avatar.avatar-4 { background: var(--gradient-4); }

        .team-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .team-role {
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .team-social {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .team-social a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .team-social a:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }

        /* Values Section */
        .value-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .value-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .value-icon.value-1 { background: var(--gradient-1); }
        .value-icon.value-2 { background: var(--gradient-2); }
        .value-icon.value-3 { background: var(--gradient-3); }
        .value-icon.value-4 { background: var(--gradient-4); }

        .value-card h4 {
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .value-card p {
            color: var(--text-muted);
            line-height: 1.8;
        }

        /* Cart Modal */
        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            z-index: 9998;
        }

        .cart-overlay.active {
            display: block;
        }

        .cart-modal {
            position: fixed;
            top: 0;
            right: -450px;
            width: 400px;
            height: 100%;
            background: #fff;
            z-index: 9999;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.3);
        }

        .cart-modal.open {
            right: 0;
        }

        .cart-header {
            background: #111827;
            color: #fff;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h5 {
            margin: 0;
            font-size: 1.25rem;
        }

        .cart-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .cart-content {
            flex: 1;
            padding: 1rem;
            color: #1e293b;
        }

        .cart-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
        }

        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-summary {
            background: #f9fafb;
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .cart-summary h6 {
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .empty-cart {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Footer */
        .footer {
            background: #0f172a;
            padding: 3rem 0 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-section h6 {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-1);
            color: white;
            text-decoration: none;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .alert-custom {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            .hero p {
                font-size: 1rem;
            }
            .cart-modal {
                width: 100%;
                right: -100%;
            }
        }

        /* ===== LIGHT MODE STYLES ===== */
        body.light-mode {
            background: var(--bg);
            color: #1e293b;
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .navbar-brand {
            -webkit-text-fill-color: var(--primary-dark);
            background: none;
        }

        body.light-mode .nav-link {
            color: #1e293b !important;
        }

        body.light-mode .nav-link:hover,
        body.light-mode .nav-link.active {
            color: var(--primary-dark) !important;
        }

        body.light-mode .user-profile:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        body.light-mode .hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
        }

        body.light-mode .hero h1 {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        body.light-mode .hero p {
            color: #64748b;
        }

        body.light-mode .about-card,
        body.light-mode .stats-card,
        body.light-mode .team-card,
        body.light-mode .value-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body.light-mode .about-card:hover,
        body.light-mode .stats-card:hover,
        body.light-mode .team-card:hover,
        body.light-mode .value-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .about-card h3,
        body.light-mode .stats-number,
        body.light-mode .team-name,
        body.light-mode .value-card h4 {
            color: #1e293b;
        }

        body.light-mode .about-card p,
        body.light-mode .stats-label,
        body.light-mode .team-role,
        body.light-mode .value-card p {
            color: #64748b;
        }

        body.light-mode .section-title p {
            color: #64748b;
        }

        body.light-mode .team-social a {
            background: rgba(0, 0, 0, 0.05);
            color: #1e293b;
        }

        body.light-mode .team-social a:hover {
            background: var(--primary-color);
            color: white;
        }

        body.light-mode .footer {
            background: #f1f5f9;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .footer-section h6 {
            color: #1e293b;
        }

        body.light-mode .footer-links a {
            color: #64748b;
        }

        body.light-mode .footer-links a:hover {
            color: var(--primary-dark);
        }

        body.light-mode .footer .text-light {
            color: #475569 !important;
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
                        <a class="nav-link active" href="about.php">About</a>
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
                                <li><hr class="dropdown-divider"></li>
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

    <div class="hero">
        <div class="container">
            <div class="text-center hero-content">
                <h1>About FutureMart</h1>
                <p>Your trusted partner for cutting-edge products and exceptional shopping experiences</p>
            </div>
        </div>
    </div>

    <!-- About Story Section -->
    <section class="about-section">
        <div class="container">
            <div class="section-title">
                <h2>Our Story</h2>
                <p>Building the future of e-commerce, one customer at a time</p>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="about-card">
                        <h3><i class="fas fa-lightbulb me-2"></i>Our Mission</h3>
                        <p>At FutureMart, we're on a mission to revolutionize online shopping by providing customers with access to the latest products, innovative technology, and exceptional service. We believe that shopping should be seamless, enjoyable, and accessible to everyone.</p>
                        <p>We're committed to bringing you products that enhance your lifestyle while maintaining the highest standards of quality and customer satisfaction.</p>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="about-card">
                        <h3><i class="fas fa-eye me-2"></i>Our Vision</h3>
                        <p>We envision a future where online shopping transcends traditional boundaries, offering personalized experiences powered by cutting-edge technology. Our goal is to become the most trusted e-commerce platform globally.</p>
                        <p>Through innovation, dedication, and a customer-first approach, we're building a shopping ecosystem that anticipates your needs and exceeds your expectations.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="about-section" style="background: rgba(99, 102, 241, 0.03);">
        <div class="container">
            <div class="section-title">
                <h2>Our Impact</h2>
                <p>Numbers that showcase our growth and commitment</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon icon-1">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($totalCustomers); ?>+</div>
                        <div class="stats-label">Happy Customers</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon icon-2">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($totalProducts); ?>+</div>
                        <div class="stats-label">Products Available</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon icon-3">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($totalOrders); ?>+</div>
                        <div class="stats-label">Orders Delivered</div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon icon-4">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stats-number">4.8</div>
                        <div class="stats-label">Average Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="about-section">
        <div class="container">
            <div class="section-title">
                <h2>Our Core Values</h2>
                <p>The principles that guide everything we do</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="value-card">
                        <div class="value-icon value-1">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Customer First</h4>
                        <p>Your satisfaction is our top priority. We go above and beyond to ensure every interaction exceeds expectations.</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="value-card">
                        <div class="value-icon value-2">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Quality Assured</h4>
                        <p>We carefully curate every product to ensure it meets our rigorous quality standards before reaching you.</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="value-card">
                        <div class="value-icon value-3">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h4>Innovation</h4>
                        <p>We continuously evolve our platform with cutting-edge technology to enhance your shopping experience.</p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="value-card">
                        <div class="value-icon value-4">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h4>Sustainability</h4>
                        <p>We're committed to environmentally responsible practices and sustainable business operations.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="about-section" style="background: rgba(99, 102, 241, 0.03);">
        <div class="container">
            <div class="section-title">
                <h2>Meet Our Team</h2>
                <p>The passionate people behind FutureMart</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="team-card">
                        <div class="team-avatar avatar-1">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Sarah Johnson</div>
                        <div class="team-role">CEO & Founder</div>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="team-card">
                        <div class="team-avatar avatar-2">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Michael Chen</div>
                        <div class="team-role">Chief Technology Officer</div>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="team-card">
                        <div class="team-avatar avatar-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Emily Rodriguez</div>
                        <div class="team-role">Head of Customer Experience</div>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="team-card">
                        <div class="team-avatar avatar-4">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">David Kim</div>
                        <div class="team-role">Director of Operations</div>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="about-section">
        <div class="container">
            <div class="about-card text-center">
                <h3 class="mb-4">Join Our Journey</h3>
                <p class="mb-4">Be part of the FutureMart family and experience the future of online shopping. Whether you're a customer, vendor, or partner, we'd love to have you on board.</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="products.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                    </a>
                    <a href="contact.php" class="btn btn-outline btn-lg">
                        <i class="fas fa-envelope me-2"></i>Get In Touch
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="footer-section">
                        <h5 class="navbar-brand mb-3">
                            <i class="fas fa-rocket me-2"></i>FutureMart
                        </h5>
                        <p class="text-light">Your trusted partner for cutting-edge products and exceptional shopping experiences. We're committed to bringing you the future of retail.</p>
                        <div class="social-icons mt-3">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Quick Links</h6>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="products.php">Shop</a></li>
                            <li><a href="categories.php">Categories</a></li>
                            <li><a href="about.php">About</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Categories</h6>
                        <ul class="footer-links">
                            <li><a href="categories.php#electronics">Electronics</a></li>
                            <li><a href="categories.php#fashion">Fashion</a></li>
                            <li><a href="categories.php#home">Home & Living</a></li>
                            <li><a href="categories.php#sports">Sports & Fitness</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Support</h6>
                        <ul class="footer-links">
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Returns</a></li>
                            <li><a href="#">Shipping Info</a></li>
                            <li><a href="settings.php">Settings</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Legal</h6>
                        <ul class="footer-links">
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms of Service</a></li>
                            <li><a href="#">Cookie Policy</a></li>
                            <li><a href="about.php">About Us</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <hr style="border-color: rgba(255, 255, 255, 0.1);">

            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-light mb-0">&copy; 2024 FutureMart. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-light mb-0">
                        <i class="fas fa-heart text-danger"></i>
                        Made with love for amazing customers
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function showNotification(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-custom`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 3000);
        }

        function toggleCart() {
            <?php if (!$isLoggedIn): ?>
                showNotification('Please login to view your cart', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            <?php endif; ?>
            
            document.getElementById('cartModal').classList.toggle('open');
            document.getElementById('cartOverlay').classList.toggle('active');
            if (document.getElementById('cartModal').classList.contains('open')) {
                loadCartItems();
            }
        }

        function loadCartItems() {
            fetch('cart_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayCartItems(data.items, data.total);
                }
            })
            .catch(error => console.error('Error loading cart:', error));
        }

        function displayCartItems(items, total) {
            const cartItemsContainer = document.getElementById('cartItems');
            
            if (items.length === 0) {
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
                            <img src="${item.image || 'placeholder.jpg'}" alt="${item.name}" class="cart-item-image">
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

            const subtotal = parseFloat(total);
            const tax = subtotal * 0.08;
            const totalAmount = subtotal + tax;

            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' + tax.toFixed(2);
            document.getElementById('total').textContent = '$' + totalAmount.toFixed(2);
        }

        function updateCartQuantity(cartItemId, newQuantity) {
            fetch('cart_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update&cart_item_id=${cartItemId}&quantity=${newQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCartItems();
                    document.getElementById('cartCount').textContent = data.cartCount;
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => showNotification('Error updating cart', 'danger'));
        }

        function removeFromCart(cartItemId) {
            if (!confirm('Remove this item from cart?')) return;
            
            fetch('cart_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=remove&cart_item_id=${cartItemId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadCartItems();
                    document.getElementById('cartCount').textContent = data.cartCount;
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => showNotification('Error removing item', 'danger'));
        }

        function clearCart() {
            if (!confirm('Are you sure you want to clear your cart?')) return;
            
            fetch('cart_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=clear'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadCartItems();
                    document.getElementById('cartCount').textContent = '0';
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => showNotification('Error clearing cart', 'danger'));
        }

        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }

        function setupScrollAnimations() {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
        }

        function setupNavbarScroll() {
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupNavbarScroll();

            document.addEventListener('click', function(event) {
                const cartModal = document.getElementById('cartModal');
                const cartIcon = document.querySelector('.cart-icon');
                
                if (cartModal && cartIcon && !cartModal.contains(event.target) && 
                    !cartIcon.contains(event.target) && cartModal.classList.contains('open')) {
                    toggleCart();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const cartModal = document.getElementById('cartModal');
                    if (cartModal && cartModal.classList.contains('open')) {
                        toggleCart();
                    }
                }
            });
        });
    </script>
</body>
</html>