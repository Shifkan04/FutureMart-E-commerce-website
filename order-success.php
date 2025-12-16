<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$orderId = (int)($_GET['order'] ?? 0);
$userId = $_SESSION['user_id'];

if ($orderId <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.*, 
           ua.address_line_1, ua.address_line_2, ua.city, ua.state, 
           ua.postal_code, ua.country, ua.phone
    FROM orders o
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit();
}

// Fetch order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --success-color: #10b981;
            --dark-bg: #0f172a;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-light);
            padding-top: 80px;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .success-container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 0 1rem;
        }

        .success-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 3rem 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            margin-bottom: 2rem;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        .success-icon i {
            font-size: 3rem;
            color: white;
        }

        .order-card {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .order-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            background: var(--gradient-1);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            padding-top: 1rem;
            margin-top: 0.5rem;
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .info-box {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Light Mode Styles */
body.light-mode {
    background: #f8fafc;
    color: #1e293b;
}

body.light-mode .navbar {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-mode .navbar-brand {
    -webkit-text-fill-color: #4f46e5;
    background: none;
}

body.light-mode .success-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-mode .order-card {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.1);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

body.light-mode .order-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-mode .order-item {
    background: #f8fafc;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

body.light-mode .summary-row.total {
    border-top: 2px solid rgba(0, 0, 0, 0.1);
}

body.light-mode .info-box {
    background: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.2);
    color: #065f46;
}

body.light-mode .btn-outline-light {
    border-color: #d1d5db;
    color: #374151;
}

body.light-mode .btn-outline-light:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    color: #374151;
}

body.light-mode .text-muted {
    color: #64748b !important;
}
    </style>
</head>
<body<?php echo ($userData && $userData['theme_preference'] === 'light') ? ' class="light-mode"' : ''; ?>>
   <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-rocket me-2"></i>FutureMart
        </a>
        
        <div class="d-flex align-items-center gap-3">
            <!-- Theme Toggle Button -->
            <button class="btn btn-outline-light theme-toggle-btn" onclick="toggleTheme()">
                <i class="fas fa-sun"></i>
            </button>
        </div>
    </div>
</nav>

    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="mb-3">Order Placed Successfully!</h1>
            <p class="text-muted mb-4">Thank you for your order. We've received your order and will process it soon.</p>
            <div class="info-box">
                <i class="fas fa-info-circle me-2"></i>
                Order confirmation has been sent to your email
            </div>
        </div>

        <div class="order-card">
            <div class="order-header">
                <div>
                    <h4 class="mb-1">Order #{<?php echo htmlspecialchars($order['order_number']); ?>}</h4>
                    <small class="text-muted">
                        Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                    </small>
                </div>
                <span class="badge bg-warning text-dark px-3 py-2">
                    <?php echo ucfirst($order['status']); ?>
                </span>
            </div>

            <h5 class="mb-3"><i class="fas fa-box me-2"></i>Order Items</h5>
            <?php foreach ($orderItems as $item): ?>
            <div class="order-item">
                <?php if ($item['image']): ?>
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                         class="order-item-image" alt="<?php echo htmlspecialchars($item['name']); ?>">
                <?php else: ?>
                    <div class="order-item-image d-flex align-items-center justify-content-center">
                        <i class="fas fa-box text-white"></i>
                    </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                    <small class="text-muted">
                        Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['unit_price'], 2); ?>
                    </small>
                </div>
                <div class="text-end">
                    <strong>$<?php echo number_format($item['total_price'], 2); ?></strong>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="mt-4">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span class="text-success">Free</span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span>$<?php echo number_format($order['tax_amount'], 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="order-card">
            <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Shipping Address</h5>
            <p class="mb-1"><?php echo htmlspecialchars($order['address_line_1']); ?></p>
            <?php if ($order['address_line_2']): ?>
                <p class="mb-1"><?php echo htmlspecialchars($order['address_line_2']); ?></p>
            <?php endif; ?>
            <p class="mb-1">
                <?php echo htmlspecialchars($order['city']); ?>, 
                <?php echo htmlspecialchars($order['state']); ?> 
                <?php echo htmlspecialchars($order['postal_code']); ?>
            </p>
            <p class="mb-1"><?php echo htmlspecialchars($order['country']); ?></p>
            <?php if ($order['phone']): ?>
                <p class="mb-0">Phone: <?php echo htmlspecialchars($order['phone']); ?></p>
            <?php endif; ?>
        </div>

        <div class="order-card">
            <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Payment Method</h5>
            <p class="mb-0 text-capitalize">
                <?php 
                $paymentMethods = [
                    'cod' => 'Cash on Delivery',
                    'card' => 'Credit/Debit Card',
                    'online' => 'Online Payment'
                ];
                echo $paymentMethods[$order['payment_method']] ?? 'Cash on Delivery';
                ?>
            </p>
            <small class="text-muted">Status: <?php echo ucfirst($order['payment_status']); ?></small>
        </div>

        <div class="text-center">
            <a href="user/orders.php" class="btn btn-primary me-2">
                <i class="fas fa-list me-2"></i>View My Orders
            </a>
            <a href="index.php" class="btn btn-outline-light">
                <i class="fas fa-home me-2"></i>Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
    // Theme Toggle Functionality
    function toggleTheme() {
        const body = document.body;
        const isLightMode = body.classList.contains('light-mode');
        const themeIcon = document.querySelector('.theme-toggle-btn i');
        
        if (isLightMode) {
            body.classList.remove('light-mode');
            localStorage.setItem('theme', 'dark');
            themeIcon.className = 'fas fa-sun';
            <?php if (isset($_SESSION['user_id'])): ?>
            updateThemePreference('dark');
            <?php endif; ?>
        } else {
            body.classList.add('light-mode');
            localStorage.setItem('theme', 'light');
            themeIcon.className = 'fas fa-moon';
            <?php if (isset($_SESSION['user_id'])): ?>
            updateThemePreference('light');
            <?php endif; ?>
        }
    }

    // Update theme preference via AJAX
    function updateThemePreference(theme) {
        const formData = new FormData();
        formData.append('action', 'update_theme');
        formData.append('theme', theme);
        
        fetch('ajax.php', {
            method: 'POST',
            body: formData
        }).catch(error => console.error('Error updating theme:', error));
    }

    // Load saved theme on page load
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme');
        const themeIcon = document.querySelector('.theme-toggle-btn i');
        
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            if (themeIcon) {
                themeIcon.className = 'fas fa-moon';
            }
        }
    });
</script>
</body>
</html>