<?php
require_once '../config_user.php';
require_once '../User.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user = new User();
$userId = $_SESSION['user_id'];
$userData = $user->getUserById($userId);

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Get dashboard statistics
$dashboardStats = $user->getDashboardStats($userId);
$theme = $userData['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --navbar-bg: rgba(15, 23, 42, 0.95);
            --dropdown-bg: linear-gradient(to right bottom, rgba(191, 244, 228, 0.31), rgba(155, 209, 245, 0.57));
            --border-color: rgba(255, 255, 255, 0.1);
        }

        :root[data-theme="light"] {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dark-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-light: #1e293b;
            --text-muted: #64748b;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --navbar-bg: rgba(255, 255, 255, 0.95);
            --dropdown-bg: rgba(255, 255, 255, 0.95);
            --border-color: rgba(0, 0, 0, 0.1);
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
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Navbar */
        .navbar {
            position: relative;
            z-index: 1100;
            background: var(--navbar-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            transition: background-color 0.3s ease;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar.scrolled {
            background: var(--navbar-bg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--accent-color) !important;
            transform: translateY(-2px);
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

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 241, 142, 0.3);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            z-index: 2000;
            background: var(--dropdown-bg);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            padding: 0.5rem 0;
            color: var(--text-light);
            border: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }

        .dropdown-menu hr {
            border: none;
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }

        .dropdown-menu a {
            color: var(--text-light);
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: var(--dropdown-bg);
            color: var(--text-light);
        }

        /* Theme Toggle */
        .theme-toggle {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 1rem;
        }

        .theme-toggle:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Dashboard Layout */
        .dashboard-container {
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .welcome-section {
            background: var(--gradient-1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none"><circle cx="50" cy="50" r="40" fill="white" opacity="0.1"/><circle cx="30" cy="30" r="20" fill="white" opacity="0.05"/></svg>');
            opacity: 0.3;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            transition: all 0.3s ease;
        }

        .stat-card.orders::before { background: var(--gradient-1); }
        .stat-card.revenue::before { background: var(--gradient-2); }
        .stat-card.wishlist::before { background: var(--gradient-3); }
        .stat-card.reviews::before { background: var(--gradient-4); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.orders { background: var(--gradient-1); }
        .stat-icon.revenue { background: var(--gradient-2); }
        .stat-icon.wishlist { background: var(--gradient-3); }
        .stat-icon.reviews { background: var(--gradient-4); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .stat-change {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--error-color);
        }

        /* Quick Actions */
        .quick-actions {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .quick-actions .text-muted {
            color: var(--text-muted)!important;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .action-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        /* Recent Activity */
        .recent-activity {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Progress Bars */
        .progress-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .progress-card .text-muted {
            color: var(--text-muted)!important;
        }

        .custom-progress {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease;
        }

        .loyalty-progress { background: var(--gradient-1); }
        .spending-progress { background: var(--gradient-2); }

        /* Loading States */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .action-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .dashboard-container {
                padding: 1rem 0;
            }

            .welcome-section {
                text-align: center;
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            opacity: 0;
            transform: translateX(-20px);
            animation: slideIn 0.6s ease forwards;
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Light theme specific adjustments */
        :root[data-theme="light"] .action-card {
            background: rgba(0, 0, 0, 0.05);
        }

        :root[data-theme="light"] .action-card:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        :root[data-theme="light"] .custom-progress {
            background: rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-rocket me-2"></i>FutureMart
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
           <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../products.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../Categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-sun" id="themeIcon"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <span id="userName"><?= htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section fade-in">
            <div class="welcome-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Welcome back, <span id="welcomeName"><?= htmlspecialchars($userData['first_name']) ?></span>!</h1>
                        <p class="mb-0">Here's what's happening with your account today.</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="text-white">
                            <div id="currentTime" style="font-size: 1.1rem; font-weight: 600;"></div>
                            <div id="currentDate" style="opacity: 0.8;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card orders slide-in">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="totalOrders"><?= $dashboardStats['total_orders'] ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-icon orders">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up me-1"></i>+2 this month
                </div>
            </div>

            <div class="stat-card revenue slide-in" style="animation-delay: 0.1s;">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="totalSpent">$<?= number_format($dashboardStats['total_spent'], 2) ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up me-1"></i>+$350 this month
                </div>
            </div>

            <div class="stat-card wishlist slide-in" style="animation-delay: 0.2s;">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="wishlistCount"><?= $dashboardStats['wishlist_count'] ?></div>
                        <div class="stat-label">Wishlist Items</div>
                    </div>
                    <div class="stat-icon wishlist">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-plus me-1"></i>2 items added
                </div>
            </div>

            <div class="stat-card reviews slide-in" style="animation-delay: 0.3s;">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="pointsBalance"><?= number_format($dashboardStats['points_balance']) ?></div>
                        <div class="stat-label">Reward Points</div>
                    </div>
                    <div class="stat-icon reviews">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up me-1"></i>+150 points
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-lg-8">
                <div class="quick-actions fade-in">
                    <h4 class="mb-4"><i class="fas fa-zap me-2"></i>Quick Actions</h4>
                    <div class="action-grid">
                        <div class="action-card" onclick="window.location.href='../products.php'">
                            <div class="action-icon" style="background: var(--gradient-1);">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h6>Continue Shopping</h6>
                            <small class="text-muted">Browse products</small>
                        </div>

                        <div class="action-card" onclick="goToPage('profile.php?tab=orders')">
                            <div class="action-icon" style="background: var(--gradient-2);">
                                <i class="fas fa-truck"></i>
                            </div>
                            <h6>Track Orders</h6>
                            <small class="text-muted">View order status</small>
                        </div>

                        <div class="action-card" onclick="window.location.href='profile.php#wishlist'">
                            <div class="action-icon" style="background: var(--gradient-3);">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h6>My Wishlist</h6>
                            <small class="text-muted">Saved items</small>
                        </div>

                        <div class="action-card" onclick="goToPage('../contact.php')">
                            <div class="action-icon" style="background: var(--gradient-4);">
                                <i class="fas fa-headset"></i>
                            </div>
                            <h6>Support</h6>
                            <small class="text-muted">Get help</small>
                        </div>
                    </div>
                </div>

                <!-- Progress Cards -->
                <div class="progress-card fade-in">
                    <h5><i class="fas fa-crown me-2"></i>Loyalty Status</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><?= $dashboardStats['loyalty_tier'] ?> Member</span>
                        <span class="text-muted">Next: Elite (550 points away)</span>
                    </div>
                    <div class="custom-progress">
                        <div class="progress-fill loyalty-progress" style="width: 75%;"></div>
                    </div>
                </div>

                <div class="progress-card fade-in">
                    <h5><i class="fas fa-target me-2"></i>Monthly Spending Goal</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>$350 / $500</span>
                        <span class="text-muted">70% Complete</span>
                    </div>
                    <div class="custom-progress">
                        <div class="progress-fill spending-progress" style="width: 70%;"></div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-4">
                <div class="recent-activity fade-in">
                    <h4 class="mb-4"><i class="fas fa-clock me-2"></i>Recent Activity</h4>
                    <div id="activityContainer">
                        <!-- Activity will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize theme
        const currentTheme = '<?= $theme ?>';
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);

        // Check authentication and load data
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
            loadRecentActivity();
            animateStats();
        });

        function updateDateTime() {
            const now = new Date();
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            };
            const dateOptions = { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);
        }

        function animateStats() {
            // Animate progress bars
            setTimeout(() => {
                const loyaltyProgress = document.querySelector('.loyalty-progress');
                const spendingProgress = document.querySelector('.spending-progress');
                if (loyaltyProgress) loyaltyProgress.style.width = '75%';
                if (spendingProgress) spendingProgress.style.width = '70%';
            }, 500);
        }

        function loadRecentActivity() {
            fetch('../ajax.php?action=get_activity&limit=5')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayActivity(data.data);
                    }
                })
                .catch(error => console.error('Error loading activity:', error));
        }

        function displayActivity(activities) {
            const container = document.getElementById('activityContainer');
            if (!activities || activities.length === 0) {
                container.innerHTML = '<p class="text-muted">No recent activity</p>';
                return;
            }

            const activityHTML = activities.map(activity => {
                const icon = getActivityIcon(activity.activity_type);
                const color = getActivityColor(activity.activity_type);
                return `
                    <div class="activity-item">
                        <div class="activity-icon" style="background: ${color};">
                            <i class="${icon}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">${activity.activity_description}</div>
                            <div class="activity-time">${timeAgo(activity.created_at)}</div>
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = activityHTML;
        }

        function getActivityIcon(type) {
            const icons = {
                'order_delivered': 'fas fa-check',
                'order_placed': 'fas fa-shopping-cart',
                'order_shipped': 'fas fa-truck',
                'wishlist_add': 'fas fa-heart',
                'wishlist_remove': 'fas fa-heart-broken',
                'profile_update': 'fas fa-user-edit',
                'review_added': 'fas fa-star',
                'password_change': 'fas fa-key'
            };
            return icons[type] || 'fas fa-info';
        }

        function getActivityColor(type) {
            const colors = {
                'order_delivered': 'var(--success-color)',
                'order_placed': 'var(--primary-color)',
                'order_shipped': 'var(--warning-color)',
                'wishlist_add': 'var(--secondary-color)',
                'profile_update': 'var(--accent-color)',
                'review_added': 'var(--warning-color)'
            };
            return colors[type] || 'var(--primary-color)';
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 60) return minutes <= 1 ? 'just now' : `${minutes} minutes ago`;
            if (hours < 24) return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
            if (days < 7) return days === 1 ? '1 day ago' : `${days} days ago`;
            
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            updateThemeIcon(newTheme);
            
            // Save theme preference
            fetch('../ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_theme&theme=${newTheme}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Theme updated successfully!');
                }
            })
            .catch(error => console.error('Error updating theme:', error));
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (theme === 'dark') {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
        }

        function goToPage(url) {
            window.location.href = url;
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
            notification.innerHTML = `
                <div class="alert ${alertClass} position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>