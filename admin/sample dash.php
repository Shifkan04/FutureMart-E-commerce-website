<?php
// dashboard.php - Admin Dashboard with Modern Glassmorphic Design
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Check session timeout
checkSessionTimeout();

// Get admin user details
$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name, email, avatar FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Fetch Dashboard Statistics
try {
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $totalProducts = $stmt->fetch()['count'];
    
    // Previous month products for comparison
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products 
                        WHERE is_active = 1 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                        AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $prevMonthProducts = $stmt->fetch()['count'];
    $productGrowth = $prevMonthProducts > 0 ? round((($totalProducts - $prevMonthProducts) / $prevMonthProducts) * 100) : 0;
    
    // Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $totalOrders = $stmt->fetch()['count'];
    
    // Previous month orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                        AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $prevMonthOrders = $stmt->fetch()['count'];
    $orderGrowth = $prevMonthOrders > 0 ? round((($totalOrders - $prevMonthOrders) / $prevMonthOrders) * 100) : 0;
    
    // Active Vendors
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'vendor' AND status = 'active'");
    $activeVendors = $stmt->fetch()['count'];
    
    // Pending Vendor Approvals
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'vendor' AND status = 'inactive'");
    $pendingVendors = $stmt->fetch()['count'];
    
    // Total Revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE payment_status = 'paid'");
    $totalRevenue = $stmt->fetch()['revenue'];
    
    // Previous month revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders 
                        WHERE payment_status = 'paid'
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                        AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $prevMonthRevenue = $stmt->fetch()['revenue'];
    $revenueGrowth = $prevMonthRevenue > 0 ? round((($totalRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100) : 0;
    
    // Pending Orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $pendingOrders = $stmt->fetch()['count'];
    
    // Low Stock Products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products 
                        WHERE stock_quantity <= min_stock_level AND is_active = 1");
    $lowStockProducts = $stmt->fetch()['count'];
    
    // Recent Orders (last 5)
    $stmt = $pdo->query("
        SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll();
    
    $totalNotifications = $pendingOrders + $pendingVendors + $lowStockProducts;
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard statistics. Please try again later.";
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
}

function formatMoney($amount) {
    return '$' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=SF+Pro+Display:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #0066ff;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #2a2a72 0%, #009ffd 74%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow: hidden;
        }

        .container {
            display: flex;
            width: 95%;
            height: 92vh;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            overflow: hidden;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100%;
            padding: 30px 15px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            transition: width var(--transition-speed) ease;
            position: relative;
            overflow-y: auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 36px;
            color: #fff;
            opacity: 0.9;
        }

        .logo h4 {
            font-size: 18px;
            margin-top: 10px;
            font-weight: 600;
        }

        .menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu li {
            margin-bottom: 8px;
            border-radius: 16px;
            transition: all 0.2s ease;
            position: relative;
        }

        .menu li:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .menu li.active {
            background: rgba(255, 255, 255, 0.25);
        }

        .menu li.active::before {
            content: '';
            position: absolute;
            left: -15px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 0 4px 4px 0;
        }

        .menu a {
            display: flex;
            align-items: center;
            color: #fff;
            padding: 12px 16px;
            text-decoration: none;
            font-weight: 500;
            letter-spacing: 0.3px;
            position: relative;
        }

        .menu a i {
            font-size: 20px;
            margin-right: 14px;
            min-width: 22px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .menu li:hover a i {
            transform: scale(1.1);
        }

        .notification-badge {
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 11px;
            margin-left: auto;
            font-weight: 600;
        }

        .profile {
            margin-top: auto;
            display: flex;
            align-items: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h3 {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 12px;
            opacity: 0.8;
        }

        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .content::-webkit-scrollbar {
            width: 8px;
        }

        .content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        header {
            margin-bottom: 30px;
        }

        header h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        header p {
            font-size: 16px;
            opacity: 0.8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 22px;
        }

        .stat-icon.success { background: var(--success-color); }
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.danger { background: var(--danger-color); }
        .stat-icon.info { background: var(--info-color); }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .growth-indicator {
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .growth-up { color: var(--success-color); }
        .growth-down { color: var(--danger-color); }

        .data-section {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            text-align: left;
            padding: 12px;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0.9;
        }

        table td {
            padding: 12px;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.success { background: rgba(16, 185, 129, 0.2); color: var(--success-color); }
        .badge.warning { background: rgba(245, 158, 11, 0.2); color: var(--warning-color); }
        .badge.danger { background: rgba(239, 68, 68, 0.2); color: var(--danger-color); }
        .badge.info { background: rgba(59, 130, 246, 0.2); color: var(--info-color); }
        .badge.primary { background: rgba(0, 102, 255, 0.2); color: var(--primary-color); }

        .btn {
            padding: 8px 16px;
            border-radius: 12px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #0052cc;
            transform: translateY(-2px);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 16px;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            display: block;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .action-btn i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
            }

            .menu a span,
            .user-info,
            .logo h4 {
                display: none;
            }

            .profile {
                justify-content: center;
            }

            .avatar {
                margin-right: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                width: 98%;
                height: 95vh;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-rocket"></i>
                <h4><?php echo APP_NAME; ?></h4>
            </div>
            <nav class="menu">
                <ul>
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php">
                            <i class="fas fa-box"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                            <?php if ($pendingOrders > 0): ?>
                            <span class="notification-badge"><?php echo $pendingOrders; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="vendors.php">
                            <i class="fas fa-users-cog"></i>
                            <span>Vendors</span>
                            <?php if ($pendingVendors > 0): ?>
                            <span class="notification-badge"><?php echo $pendingVendors; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="delivery.php">
                            <i class="fas fa-truck"></i>
                            <span>Delivery</span>
                        </a>
                    </li>
                    <li>
                        <a href="analytics.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="profile">
                <div class="avatar">
                    <?php if (!empty($admin['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin">
                    <?php else: ?>
                        <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h3>
                    <p>Administrator</p>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <header>
                <h1>Welcome Back, <?php echo htmlspecialchars($admin['first_name']); ?></h1>
                <p>Check out your business statistics for today</p>
            </header>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalProducts); ?></h3>
                        <p>Total Products</p>
                        <span class="growth-indicator <?php echo $productGrowth >= 0 ? 'growth-up' : 'growth-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $productGrowth >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($productGrowth); ?>% from last month
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalOrders); ?></h3>
                        <p>Total Orders</p>
                        <span class="growth-indicator <?php echo $orderGrowth >= 0 ? 'growth-up' : 'growth-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $orderGrowth >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($orderGrowth); ?>% from last month
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($activeVendors); ?></h3>
                        <p>Active Vendors</p>
                        <span class="growth-indicator" style="color: var(--warning-color);">
                            <i class="fas fa-clock"></i>
                            <?php echo $pendingVendors; ?> pending approval
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatMoney($totalRevenue); ?></h3>
                        <p>Total Revenue</p>
                        <span class="growth-indicator <?php echo $revenueGrowth >= 0 ? 'growth-up' : 'growth-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $revenueGrowth >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($revenueGrowth); ?>% from last month
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="data-section">
                <div class="section-header">
                    <h2>Quick Actions</h2>
                </div>
                <div class="quick-actions">
                    <a href="products.php?action=add" class="action-btn">
                        <i class="fas fa-plus"></i>
                        Add Product
                    </a>
                    <a href="vendors.php?filter=pending" class="action-btn">
                        <i class="fas fa-user-check"></i>
                        Approve Vendors
                    </a>
                    <a href="delivery.php" class="action-btn">
                        <i class="fas fa-truck"></i>
                        Manage Delivery
                    </a>
                    <a href="analytics.php" class="action-btn">
                        <i class="fas fa-chart-line"></i>
                        View Analytics
                    </a>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="data-section">
                <div class="section-header">
                    <h2>Recent Orders</h2>
                    <a href="orders.php" class="btn btn-primary">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; opacity: 0.6;">
                                    <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                                    No orders yet
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                        <small style="opacity: 0.7;"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadge($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo formatMoney($order['total_amount']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="orders.php?view=<?php echo $order['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuItems = document.querySelectorAll('.menu li');

            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    const icon = item.querySelector('i');
                    icon.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        icon.style.transform = 'scale(1)';
                    }, 200);
                });

                item.addEventListener('mouseenter', (e) => {
                    const highlight = document.createElement('div');
                    highlight.style.position = 'absolute';
                    highlight.style.top = '0';
                    highlight.style.left = '0';
                    highlight.style.width = '100%';
                    highlight.style.height = '100%';
                    highlight.style.borderRadius = '16px';
                    highlight.style.background = `radial-gradient(circle at ${e.offsetX}px ${e.offsetY}px, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%)`;
                    highlight.style.pointerEvents = 'none';
                    highlight.style.transition = 'opacity 0.3s ease';

                    item.appendChild(highlight);

                    setTimeout(() => {
                        highlight.style.opacity = '0';
                        setTimeout(() => {
                            if (item.contains(highlight)) {
                                item.removeChild(highlight);
                            }
                        }, 300);
                    }, 500);
                });
            });

            // Stat cards animation on hover
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    const rotateY = (x / rect.width - 0.5) * 8;
                    const rotateX = (y / rect.height - 0.5) * -8;

                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px) scale(1.02)`;
                });

                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0) scale(1)';
                    card.style.transition = 'transform 0.5s ease';
                });

                card.addEventListener('mouseenter', () => {
                    card.style.transition = 'none';
                });
            });

            // Highlight active navigation
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
            document.querySelectorAll('.menu li').forEach(li => {
                li.classList.remove('active');
                const link = li.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    li.classList.add('active');
                }
            });

            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });

            // Action buttons hover effect
            const actionBtns = document.querySelectorAll('.action-btn');
            actionBtns.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Table row hover effect
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.transition = 'transform 0.2s ease';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>