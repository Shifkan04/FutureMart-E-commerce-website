<?php
// Start session and include config
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
if (!$isLoggedIn) {
    header('Location: login.php');
    exit();
}

$userData = null;
$userPreferences = [];
$notificationSettings = [];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();

// Get cart count
$cartCount = 0;
$stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch();
$cartCount = $result['total'] ?? 0;

// Get user preferences
$stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userPreferences = $stmt->fetch() ?: [];

// Get notification preferences
$stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$notificationSettings = $stmt->fetch() ?: [];

// Handle settings update
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo->beginTransaction();
        
        // Update notification settings
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $marketingEmails = isset($_POST['marketing_emails']) ? 1 : 0;
        $orderUpdates = isset($_POST['order_updates']) ? 1 : 0;
        $newProductAlerts = isset($_POST['new_product_alerts']) ? 1 : 0;
        $priceDropAlerts = isset($_POST['price_drop_alerts']) ? 1 : 0;
        
        // Update or insert notification settings
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications 
            (user_id, order_updates, promotional_emails, sms_notifications, 
            new_product_alerts, price_drop_alerts, newsletter)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            order_updates = VALUES(order_updates),
            promotional_emails = VALUES(promotional_emails),
            sms_notifications = VALUES(sms_notifications),
            new_product_alerts = VALUES(new_product_alerts),
            price_drop_alerts = VALUES(price_drop_alerts),
            newsletter = VALUES(newsletter)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $orderUpdates,
            $marketingEmails,
            $smsNotifications,
            $newProductAlerts,
            $priceDropAlerts,
            $marketingEmails
        ]);
        
        // Update user preferences
        $language = sanitizeInput($_POST['language'] ?? 'en');
        $currency = sanitizeInput($_POST['currency'] ?? 'USD');
        $dateFormat = sanitizeInput($_POST['date_format'] ?? 'MM/DD/YYYY');
        $timeFormat = sanitizeInput($_POST['time_format'] ?? '12h');
        
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences 
            (user_id, language, currency, date_format, time_format)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            language = VALUES(language),
            currency = VALUES(currency),
            date_format = VALUES(date_format),
            time_format = VALUES(time_format)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $language,
            $currency,
            $dateFormat,
            $timeFormat
        ]);
        
        // Update user theme preference
        $themePreference = sanitizeInput($_POST['theme_preference'] ?? 'dark');
        $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
        $stmt->execute([$themePreference, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userPreferences = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $notificationSettings = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        
        $successMessage = "Settings saved successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Error saving settings. Please try again.";
        error_log("Settings save error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FutureMart</title>
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
        }

        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(15, 23, 42, 0.98);
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

        .section {
            padding: 80px 0;
        }

        .settings-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .settings-card h4 {
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }

        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .settings-item:last-child {
            border-bottom: none;
        }

        .settings-item h6 {
            color: var(--text-light);
            margin-bottom: 0.25rem;
        }

        .settings-item small {
            color: var(--text-muted);
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: #374151;
            border-radius: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-switch.active {
            background: var(--primary-color);
        }

        .toggle-switch::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-switch.active::before {
            left: 27px;
        }

        .form-control,
        .form-select {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-light);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(99, 102, 241, 0.15);
            color: var(--text-light);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-label {
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

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

        .footer {
            background: rgba(15, 23, 42, 0.95);
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
            .cart-modal {
                width: 100%;
                right: -100%;
            }
        }

        body.light-mode {
            background: var(--bg);
            color: #1e293b;
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .navbar-brand {
            -webkit-text-fill-color: initial;
            background: none;
            color: var(--primary-dark);
        }

        body.light-mode .nav-link {
            color: #1e293b !important;
        }

        body.light-mode .settings-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #1e293b;
        }

        body.light-mode .settings-item h6 {
            color: #1e293b;
        }

        body.light-mode .settings-item small {
            color: #6b7280;
        }

        body.light-mode .hero h1 {
            background: linear-gradient(135deg, #404040ff 0%, #252525ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        body.light-mode .footer {
            background: #f1f5f9;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            color: #1e293b;
        }

        body.light-mode .form-control,
        body.light-mode .form-select {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body<?php echo ($userData['theme_preference'] === 'light') ? ' class="light-mode"' : ''; ?>>
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
                    <a href="#" class="nav-link cart-icon" onclick="toggleCart(); return false;">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
                    </a>

                    <div class="dropdown">
                        <div class="user-profile" data-bs-toggle="dropdown">
                            <?php if ($userData['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" 
                                     alt="Profile" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar-placeholder">
                                    <?php echo strtoupper(substr($userData['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <span class="d-none d-lg-inline"><?php echo htmlspecialchars($userData['first_name']); ?></span>
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
                </div>
            </div>
        </div>
    </nav>

    <div class="hero">
        <div class="container">
            <div class="text-center hero-content">
                <h1>Settings</h1>
                <p>Customize your shopping experience</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <form method="POST" id="settingsForm">
                        <input type="hidden" name="save_settings" value="1">
                        
                        <!-- Account Settings -->
                        <div class="settings-card">
                            <h4><i class="fas fa-user me-2"></i>Account Settings</h4>
                            <div class="settings-item">
                                <div>
                                    <h6>Email Notifications</h6>
                                    <small>Receive updates about orders and promotions</small>
                                </div>
                                <div class="toggle-switch <?php echo ($notificationSettings['promotional_emails'] ?? 1) ? 'active' : ''; ?>" 
                                     onclick="toggleSetting(this, 'marketing_emails')">
                                    <input type="checkbox" name="marketing_emails" value="1" 
                                           <?php echo ($notificationSettings['promotional_emails'] ?? 1) ? 'checked' : ''; ?> 
                                           style="display:none;">
                                </div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>SMS Notifications</h6>
                                    <small>Get text messages for order updates</small>
                                </div>
                                <div class="toggle-switch <?php echo ($notificationSettings['sms_notifications'] ?? 0) ? 'active' : ''; ?>" 
                                     onclick="toggleSetting(this, 'sms_notifications')">
                                    <input type="checkbox" name="sms_notifications" value="1" 
                                           <?php echo ($notificationSettings['sms_notifications'] ?? 0) ? 'checked' : ''; ?> 
                                           style="display:none;">
                                </div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>Order Updates</h6>
                                    <small>Receive notifications about your orders</small>
                                </div>
                                <div class="toggle-switch <?php echo ($notificationSettings['order_updates'] ?? 1) ? 'active' : ''; ?>" 
                                     onclick="toggleSetting(this, 'order_updates')">
                                    <input type="checkbox" name="order_updates" value="1" 
                                           <?php echo ($notificationSettings['order_updates'] ?? 1) ? 'checked' : ''; ?> 
                                           style="display:none;">
                                </div>
                            </div>
                        </div>

                        <!-- Privacy Settings -->
                        <div class="settings-card">
                            <h4><i class="fas fa-shield-alt me-2"></i>Privacy Settings</h4>
                            <div class="settings-item">
                                <div>
                                    <h6>New Product Alerts</h6>
                                    <small>Get notified about new products</small>
                                </div>
                                <div class="toggle-switch <?php echo ($notificationSettings['new_product_alerts'] ?? 1) ? 'active' : ''; ?>" 
                                     onclick="toggleSetting(this, 'new_product_alerts')">
                                    <input type="checkbox" name="new_product_alerts" value="1" 
                                           <?php echo ($notificationSettings['new_product_alerts'] ?? 1) ? 'checked' : ''; ?> 
                                           style="display:none;">
                                </div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>Price Drop Alerts</h6>
                                    <small>Show products based on your preferences</small>
                                </div>
                                <div class="toggle-switch <?php echo ($notificationSettings['price_drop_alerts'] ?? 1) ? 'active' : ''; ?>" 
                                     onclick="toggleSetting(this, 'price_drop_alerts')">
                                    <input type="checkbox" name="price_drop_alerts" value="1" 
                                           <?php echo ($notificationSettings['price_drop_alerts'] ?? 1) ? 'checked' : ''; ?> 
                                           style="display:none;">
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings -->
                        <div class="settings-card">
                            <h4><i class="fas fa-palette me-2"></i>Display Settings</h4>
                            <div class="settings-item">
                                <div>
                                    <h6>Theme Mode</h6>
                                    <small id="theme-text"><?php echo ($userData['theme_preference'] === 'light') ? 'Light mode (Currently active)' : 'Dark mode (Currently active)'; ?></small>
                                </div>
                                <div class="toggle-switch <?php echo ($userData['theme_preference'] === 'light') ? 'active' : ''; ?>" 
                                     onclick="toggleTheme(this)">
                                    <input type="hidden" name="theme_preference" id="theme_preference" 
                                           value="<?php echo htmlspecialchars($userData['theme_preference'] ?? 'dark'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Language & Region -->
                        <div class="settings-card">
                            <h4><i class="fas fa-globe me-2"></i>Language & Region</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Language</label>
                                    <select class="form-select" name="language">
                                        <option value="en" <?php echo ($userPreferences['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="es" <?php echo ($userPreferences['language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Español</option>
                                        <option value="fr" <?php echo ($userPreferences['language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                        <option value="de" <?php echo ($userPreferences['language'] ?? 'en') === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select" name="currency">
                                        <option value="USD" <?php echo ($userPreferences['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                        <option value="EUR" <?php echo ($userPreferences['currency'] ?? 'USD') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                        <option value="GBP" <?php echo ($userPreferences['currency'] ?? 'USD') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                        <option value="JPY" <?php echo ($userPreferences['currency'] ?? 'USD') === 'JPY' ? 'selected' : ''; ?>>JPY (¥)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Format</label>
                                    <select class="form-select" name="date_format">
                                        <option value="MM/DD/YYYY" <?php echo ($userPreferences['date_format'] ?? 'MM/DD/YYYY') === 'MM/DD/YYYY' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="DD/MM/YYYY" <?php echo ($userPreferences['date_format'] ?? 'MM/DD/YYYY') === 'DD/MM/YYYY' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="YYYY-MM-DD" <?php echo ($userPreferences['date_format'] ?? 'MM/DD/YYYY') === 'YYYY-MM-DD' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Time Format</label>
                                    <select class="form-select" name="time_format">
                                        <option value="12h" <?php echo ($userPreferences['time_format'] ?? '12h') === '12h' ? 'selected' : ''; ?>>12 Hour</option>
                                        <option value="24h" <?php echo ($userPreferences['time_format'] ?? '12h') === '24h' ? 'selected' : ''; ?>>24 Hour</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
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
                            <li><a href="#">About Us</a></li>
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

        function toggleSetting(toggle, fieldName) {
            toggle.classList.toggle('active');
            const checkbox = toggle.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = toggle.classList.contains('active');
            }
        }

        function toggleTheme(toggle) {
            toggle.classList.toggle('active');
            const themeInput = document.getElementById('theme_preference');
            const themeText = document.getElementById('theme-text');
            
            if (toggle.classList.contains('active')) {
                document.body.classList.add('light-mode');
                themeInput.value = 'light';
                themeText.textContent = 'Light mode (Currently active)';
            } else {
                document.body.classList.remove('light-mode');
                themeInput.value = 'dark';
                themeText.textContent = 'Dark mode (Currently active)';
            }
        }

        function toggleCart() {
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

            // Close cart when clicking outside
            document.addEventListener('click', function(event) {
                const cartModal = document.getElementById('cartModal');
                const cartIcon = document.querySelector('.cart-icon');
                
                if (cartModal && cartIcon && !cartModal.contains(event.target) && 
                    !cartIcon.contains(event.target) && cartModal.classList.contains('open')) {
                    toggleCart();
                }
            });

            // Keyboard navigation
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const cartModal = document.getElementById('cartModal');
                    if (cartModal && cartModal.classList.contains('open')) {
                        toggleCart();
                    }
                }
            });

            // Auto-dismiss alerts after showing
            <?php if ($successMessage || $errorMessage): ?>
                setTimeout(() => {
                    const alerts = document.querySelectorAll('.alert');
                    alerts.forEach(alert => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    });
                }, 5000);
            <?php endif; ?>
        });
    </script>
</body>
</html>