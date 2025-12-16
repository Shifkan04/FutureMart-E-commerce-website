<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliveryHub - Delivery Partner Dashboard</title>
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
            --danger-color: #ef4444;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --sidebar-bg: #111827;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
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

        /* Sidebar Styles */
        .sidebar {
            position: absolute;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1000;
            transition: all 0.3s ease;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
        }

        .sidebar.show {
            left: 0;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--gradient-1);
        }

        .sidebar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: white;
            text-decoration: none;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--text-light);
            background: rgba(99, 102, 241, 0.1);
            border-left-color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }

        /* Top Navbar */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            z-index: 999;
            display: flex;
            align-items: center;
            padding: 0 1rem;
        }

        .navbar-toggler {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 0.5rem;
            border-radius: 8px;
            margin-right: 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--text-light);
            text-decoration: none;
            margin-right: auto;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-icon {
            position: relative;
            color: var(--text-light);
            font-size: 1.2rem;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .profile-dropdown .dropdown-menu {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .profile-dropdown .dropdown-item {
            color: var(--text-light);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .profile-dropdown .dropdown-item:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        /* Main Content */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
            transition: all 0.3s ease;
            padding: 2rem;
        }

        .main-content.sidebar-open {
            margin-left: 280px;
        }

        /* Page Containers */
        .page-container {
            display: none;
        }

        .page-container.active {
            display: block;
        }

        /* Dashboard Cards */
        .stats-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .stats-icon {
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

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Table Styles */
        .data-table {
            background: var(--card-bg);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .table {
            color: var(--text-light);
            margin-bottom: 0;
        }

        .table th {
            background: rgba(99, 102, 241, 0.1);
            border-bottom: 1px solid var(--border-color);
            color: var(--text-light);
            font-weight: 600;
            padding: 1rem;
        }

        .table td {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-on-way {
            background: rgba(6, 182, 212, 0.2);
            color: var(--accent-color);
            border: 1px solid rgba(6, 182, 212, 0.3);
        }

        .status-delivered {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            margin: 0 0.2rem;
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
        }

        .btn-success {
            background: var(--gradient-4);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
        }

        /* Form Styles */
        .form-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .form-control {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            color: var(--text-light);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* Message Card */
        .message-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .message-sender {
            font-weight: 600;
            color: var(--primary-color);
        }

        .message-time {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .message-type {
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-admin {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
        }

        .type-customer {
            background: rgba(6, 182, 212, 0.2);
            color: var(--accent-color);
        }

        .type-system {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        /* Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive */
        @media (min-width: 992px) {
            .sidebar {
                left: 0;
                position: relative;
            }

            .main-content {
                margin-left: 280px;
            }

            .navbar-toggler {
                display: none;
            }

            .sidebar-overlay {
                display: none;
            }
        }

        @media (max-width: 991px) {
            .main-content.sidebar-open {
                margin-left: 0;
            }

            .stats-value {
                font-size: 1.5rem;
            }

            .table-responsive {
                font-size: 0.9rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Alert Styles */
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
            border-radius: 10px;
        }

        .alert-info {
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: var(--accent-color);
            border-radius: 10px;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: var(--warning-color);
            border-radius: 10px;
        }

        /* Profile Section */
        .profile-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .profile-info h4 {
            margin-bottom: 0.25rem;
        }

        .profile-info .text-muted {
            font-size: 0.9rem;
        }

        /* Settings Toggle */
        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .settings-item:last-child {
            border-bottom: none;
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

        /* Order Details Modal */
        .order-details {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .customer-info {
            background: rgba(99, 102, 241, 0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        /* Footer */
        .footer {
            background: var(--sidebar-bg);
            padding: 2rem 0;
            border-top: 1px solid var(--border-color);
            margin-top: 3rem;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-truck me-2"></i>DeliveryHub
            </a>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="#" class="nav-link active" onclick="showPage('dashboard')">
                    <i class="fas fa-chart-pie"></i>Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" onclick="showPage('assigned-deliveries')">
                    <i class="fas fa-clipboard-list"></i>Assigned Deliveries
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" onclick="showPage('update-status')">
                    <i class="fas fa-edit"></i>Update Status
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" onclick="showPage('messages')">
                    <i class="fas fa-comments"></i>Messages
                    <span class="badge bg-danger ms-auto">3</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" onclick="showPage('settings')">
                    <i class="fas fa-cog"></i>Settings
                </a>
            </div>
            <div class="nav-item mt-3">
                <a href="#" class="nav-link text-danger" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Top Navbar -->
    <nav class="top-navbar">
        <button class="navbar-toggler d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="#" class="navbar-brand d-lg-none">
            <i class="fas fa-truck me-2"></i>DeliveryHub
        </a>

        <div class="navbar-actions">
            <div class="notification-icon" onclick="showNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">5</span>
            </div>

            <div class="dropdown profile-dropdown">
                <button class="btn btn-link text-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i>John Doe
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="showPage('settings')">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="showProfile()">
                        <i class="fas fa-user me-2"></i>Profile
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="logout()">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        
        <!-- Dashboard Overview Page -->
        <div class="page-container active" id="dashboard">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Dashboard Overview</h2>
                <div class="text-muted">
                    <i class="fas fa-calendar me-2"></i>
                    <span id="currentDate"></span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card fade-in">
                        <div class="stats-icon" style="background: var(--gradient-1)">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stats-value text-primary">24</div>
                        <div class="stats-label">Total Assigned</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card fade-in">
                        <div class="stats-icon" style="background: var(--gradient-2)">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stats-value text-warning">8</div>
                        <div class="stats-label">Pending Deliveries</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card fade-in">
                        <div class="stats-icon" style="background: var(--gradient-3)">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stats-value text-info">5</div>
                        <div class="stats-label">On the Way</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card fade-in">
                        <div class="stats-icon" style="background: var(--gradient-4)">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-value text-success">11</div>
                        <div class="stats-label">Delivered Today</div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="data-table fade-in">
                        <div class="p-3 border-bottom">
                            <h5 class="mb-0">Recent Deliveries</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody id="recentDeliveries">
                                    <!-- Recent deliveries will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="stats-card fade-in">
                        <h5 class="mb-3">Today's Performance</h5>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Delivery Rate</span>
                            <span class="text-success fw-bold">95%</span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 95%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">On-time Delivery</span>
                            <span class="text-info fw-bold">87%</span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: 87%"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Customer Rating</span>
                            <span class="text-warning fw-bold">4.8 ★</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Deliveries Page -->
        <div class="page-container" id="assigned-deliveries">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Assigned Deliveries</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshDeliveries()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <select class="form-select form-select-sm" style="width: auto;" onchange="filterDeliveries(this.value)">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="on-way">On the Way</option>
                        <option value="delivered">Delivered</option>
                    </select>
                </div>
            </div>

            <div class="data-table fade-in">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Address</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="deliveriesTable">
                            <!-- Deliveries will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Update Status Page -->
        <div class="page-container" id="update-status">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Update Delivery Status</h2>
                <div class="text-muted">
                    <i class="fas fa-info-circle me-2"></i>Select an order to update its status
                </div>
            </div>

            <!-- Quick Status Update Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="form-card fade-in">
                        <h5 class="mb-3">
                            <i class="fas fa-truck text-info me-2"></i>Mark as On the Way
                        </h5>
                        <p class="text-muted mb-3">Update orders to "On the Way" status when you start delivery</p>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" id="onWayOrderSelect">
                                    <option value="">Select Order</option>
                                    <!-- Options will be populated by JS -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-info w-100" onclick="updateStatus('on-way')">
                                    <i class="fas fa-truck me-2"></i>Update
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="form-card fade-in">
                        <h5 class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>Mark as Delivered
                        </h5>
                        <p class="text-muted mb-3">Mark orders as delivered when successfully completed</p>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" id="deliveredOrderSelect">
                                    <option value="">Select Order</option>
                                    <!-- Options will be populated by JS -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-success w-100" onclick="updateStatus('delivered')">
                                    <i class="fas fa-check me-2"></i>Delivered
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Status Update -->
            <div class="form-card fade-in">
                <h5 class="mb-3">
                    <i class="fas fa-tasks text-primary me-2"></i>Bulk Status Update
                </h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Multiple Orders</label>
                        <select class="form-select" multiple size="4" id="bulkOrderSelect">
                            <!-- Options will be populated by JS -->
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">New Status</label>
                        <select class="form-select" id="bulkStatusSelect">
                            <option value="on-way">On the Way</option>
                            <option value="delivered">Delivered</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="bulkUpdateStatus()">
                            <i class="fas fa-edit me-2"></i>Update Selected
                        </button>
                    </div>
                </div>
            </div>

            <!-- Status Update History -->
            <div class="data-table fade-in">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0">Recent Status Updates</h5>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Previous Status</th>
                                <th>New Status</th>
                                <th>Updated At</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="statusHistory">
                            <!-- Status history will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Messages Page -->
        <div class="page-container" id="messages">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Messages</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="markAllRead()">
                        <i class="fas fa-check-double me-2"></i>Mark All Read
                    </button>
                    <select class="form-select form-select-sm" style="width: auto;" onchange="filterMessages(this.value)">
                        <option value="all">All Messages</option>
                        <option value="admin">Admin</option>
                        <option value="customer">Customer</option>
                        <option value="system">System</option>
                    </select>
                </div>
            </div>

            <div id="messagesContainer">
                <!-- Messages will be loaded here -->
            </div>

            <!-- Reply Form -->
            <div class="form-card fade-in" id="replyForm" style="display: none;">
                <h5 class="mb-3">Reply to Message</h5>
                <form onsubmit="sendReply(event)">
                    <div class="mb-3">
                        <label class="form-label">Reply Message</label>
                        <textarea class="form-control" rows="4" placeholder="Type your reply..." required></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reply
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="hideReplyForm()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Settings Page -->
        <div class="page-container" id="settings">
            <h2 class="mb-4">Settings</h2>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Profile Settings -->
                    <div class="form-card fade-in">
                        <h5 class="mb-4">
                            <i class="fas fa-user me-2"></i>Profile Information
                        </h5>
                        <div class="profile-section">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info">
                                <h4>John Doe</h4>
                                <p class="text-muted">Delivery Partner • ID: DP001</p>
                                <p class="text-muted">Member since: January 2024</p>
                            </div>
                        </div>
                        
                        <form onsubmit="updateProfile(event)">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="John Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="john.doe@deliveryhub.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" value="+1 (555) 123-4567" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Vehicle Type</label>
                                    <select class="form-select" required>
                                        <option value="bike">Motorcycle</option>
                                        <option value="car" selected>Car</option>
                                        <option value="van">Van</option>
                                        <option value="truck">Truck</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">License Plate</label>
                                    <input type="text" class="form-control" value="ABC-1234" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Emergency Contact</label>
                                    <input type="tel" class="form-control" value="+1 (555) 987-6543">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Password Change -->
                    <div class="form-card fade-in">
                        <h5 class="mb-4">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </h5>
                        <form onsubmit="changePassword(event)">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" placeholder="Enter current password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" placeholder="Enter new password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" placeholder="Confirm new password" required>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Notification Settings -->
                    <div class="form-card fade-in">
                        <h5 class="mb-4">
                            <i class="fas fa-bell me-2"></i>Notification Settings
                        </h5>
                        <div class="settings-item">
                            <div>
                                <h6>New Delivery Alerts</h6>
                                <small class="text-muted">Get notified about new assignments</small>
                            </div>
                            <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                        </div>
                        <div class="settings-item">
                            <div>
                                <h6>SMS Notifications</h6>
                                <small class="text-muted">Receive text messages for urgent updates</small>
                            </div>
                            <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                        </div>
                        <div class="settings-item">
                            <div>
                                <h6>Email Reports</h6>
                                <small class="text-muted">Daily delivery summary reports</small>
                            </div>
                            <div class="toggle-switch" onclick="toggleSetting(this)"></div>
                        </div>
                        <div class="settings-item">
                            <div>
                                <h6>Customer Messages</h6>
                                <small class="text-muted">Notifications for customer communications</small>
                            </div>
                            <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                        </div>
                    </div>

                    <!-- App Settings -->
                    <div class="form-card fade-in">
                        <h5 class="mb-4">
                            <i class="fas fa-mobile-alt me-2"></i>App Preferences
                        </h5>
                        <div class="settings-item">
                            <div>
                                <h6>Dark Mode</h6>
                                <small class="text-muted">Use dark theme (Currently active)</small>
                            </div>
                            <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                        </div>
                        <div class="settings-item">
                            <div>
                                <h6>Location Tracking</h6>
                                <small class="text-muted">Share location for delivery tracking</small>
                            </div>
                            <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                        </div>
                        <div class="settings-item">
                            <div>
                                <h6>Auto-refresh</h6>
                                <small class="text-muted">Automatically refresh delivery list</small>
                            </div>
                            <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2024 DeliveryHub. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted me-3">Terms of Service</a>
                    <a href="#" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sample Data
        const deliveries = [
            {
                id: 'ORD001',
                customer: 'Alice Johnson',
                address: '123 Main St, Apt 4B, New York, NY 10001',
                contact: '+1 (555) 123-4567',
                status: 'pending',
                priority: 'high',
                assignedAt: '2024-01-15 09:30',
                estimatedDelivery: '2024-01-15 14:00',
                items: ['Laptop', 'Mouse', 'Keyboard'],
                total: '$1,299.99'
            },
            {
                id: 'ORD002',
                customer: 'Bob Smith',
                address: '456 Oak Ave, Brooklyn, NY 11201',
                contact: '+1 (555) 987-6543',
                status: 'on-way',
                priority: 'medium',
                assignedAt: '2024-01-15 10:15',
                estimatedDelivery: '2024-01-15 15:30',
                items: ['Headphones', 'Phone Case'],
                total: '$249.99'
            },
            {
                id: 'ORD003',
                customer: 'Carol Davis',
                address: '789 Pine Rd, Queens, NY 11375',
                contact: '+1 (555) 456-7890',
                status: 'delivered',
                priority: 'low',
                assignedAt: '2024-01-15 08:45',
                estimatedDelivery: '2024-01-15 13:00',
                items: ['Coffee Maker', 'Filters'],
                total: '$89.99'
            },
            {
                id: 'ORD004',
                customer: 'David Wilson',
                address: '321 Elm St, Manhattan, NY 10002',
                contact: '+1 (555) 321-0987',
                status: 'pending',
                priority: 'high',
                assignedAt: '2024-01-15 11:00',
                estimatedDelivery: '2024-01-15 16:00',
                items: ['Smart TV', 'Soundbar'],
                total: '$899.99'
            },
            {
                id: 'ORD005',
                customer: 'Emma Brown',
                address: '654 Cedar Lane, Staten Island, NY 10301',
                contact: '+1 (555) 654-3210',
                status: 'on-way',
                priority: 'medium',
                assignedAt: '2024-01-15 09:00',
                estimatedDelivery: '2024-01-15 14:30',
                items: ['Yoga Mat', 'Water Bottle'],
                total: '$59.99'
            }
        ];

        const messages = [
            {
                id: 1,
                sender: 'Admin Team',
                type: 'admin',
                subject: 'New delivery route optimization',
                message: 'We have updated the delivery routes for better efficiency. Please check your new assignments.',
                time: '2024-01-15 08:30',
                read: false
            },
            {
                id: 2,
                sender: 'Alice Johnson',
                type: 'customer',
                subject: 'Delivery timing inquiry',
                message: 'Hi, I wanted to confirm if my order ORD001 will be delivered before 2 PM today as I have a meeting.',
                time: '2024-01-15 10:45',
                read: false
            },
            {
                id: 3,
                sender: 'System',
                type: 'system',
                subject: 'Delivery status updated',
                message: 'Order ORD003 has been marked as delivered successfully. Customer rating: 5 stars.',
                time: '2024-01-15 12:15',
                read: true
            },
            {
                id: 4,
                sender: 'Support Team',
                type: 'admin',
                subject: 'Performance feedback',
                message: 'Great work this week! Your delivery completion rate is 98%. Keep up the excellent service.',
                time: '2024-01-14 17:30',
                read: true
            },
            {
                id: 5,
                sender: 'Bob Smith',
                type: 'customer',
                subject: 'Address clarification',
                message: 'Please call when you arrive. The building entrance is around the back. Thank you!',
                time: '2024-01-15 11:20',
                read: false
            }
        ];

        const statusHistory = [
            {
                orderId: 'ORD003',
                previousStatus: 'on-way',
                newStatus: 'delivered',
                updatedAt: '2024-01-15 12:15',
                notes: 'Customer was satisfied with delivery'
            },
            {
                orderId: 'ORD002',
                previousStatus: 'pending',
                newStatus: 'on-way',
                updatedAt: '2024-01-15 11:30',
                notes: 'Started delivery route'
            },
            {
                orderId: 'ORD005',
                previousStatus: 'pending',
                newStatus: 'on-way',
                updatedAt: '2024-01-15 10:45',
                notes: 'Vehicle loaded, heading to customer'
            }
        ];

        // Initialize Dashboard
        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentDate();
            loadRecentDeliveries();
            loadDeliveriesTable();
            loadMessages();
            loadStatusHistory();
            populateStatusUpdateSelects();
            setupAnimations();
        });

        // Update current date
        function updateCurrentDate() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        }

        // Page Navigation
        function showPage(pageId) {
            // Hide all pages
            document.querySelectorAll('.page-container').forEach(page => {
                page.classList.remove('active');
            });

            // Show selected page
            document.getElementById(pageId).classList.add('active');

            // Update sidebar active state
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Find and activate the correct nav link
            const targetLink = document.querySelector(`.nav-link[onclick="showPage('${pageId}')"]`);
            if (targetLink) {
                targetLink.classList.add('active');
            }

            // Hide sidebar on mobile after navigation
            if (window.innerWidth < 992) {
                hideSidebar();
            }
        }

        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mainContent = document.getElementById('mainContent');

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            if (window.innerWidth >= 992) {
                mainContent.classList.toggle('sidebar-open');
            }
        }

        function hideSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }

        // Load Recent Deliveries for Dashboard
        function loadRecentDeliveries() {
            const container = document.getElementById('recentDeliveries');
            const recentDeliveries = deliveries.slice(0, 5);

            container.innerHTML = recentDeliveries.map(delivery => `
                <tr>
                    <td><strong>${delivery.id}</strong></td>
                    <td>${delivery.customer}</td>
                    <td>
                        <span class="status-badge status-${delivery.status}">${getStatusText(delivery.status)}</span>
                    </td>
                    <td class="text-muted">${new Date(delivery.assignedAt).toLocaleTimeString()}</td>
                </tr>
            `).join('');
        }

        // Load Deliveries Table
        function loadDeliveriesTable() {
            const container = document.getElementById('deliveriesTable');
            
            container.innerHTML = deliveries.map(delivery => `
                <tr>
                    <td><strong>${delivery.id}</strong></td>
                    <td>
                        <div>
                            <strong>${delivery.customer}</strong>
                            <br><small class="text-muted">${delivery.contact}</small>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="${delivery.address}">
                            <i class="fas fa-map-marker-alt text-primary me-1"></i>
                            ${delivery.address}
                        </div>
                    </td>
                    <td>
                        <a href="tel:${delivery.contact}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-phone me-1"></i>Call
                        </a>
                    </td>
                    <td>
                        <span class="status-badge status-${delivery.status}">${getStatusText(delivery.status)}</span>
                    </td>
                    <td>
                        <span class="badge ${delivery.priority === 'high' ? 'bg-danger' : delivery.priority === 'medium' ? 'bg-warning' : 'bg-success'}">
                            ${delivery.priority.toUpperCase()}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-primary btn-action" onclick="viewOrderDetails('${delivery.id}')" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-info btn-action" onclick="startDelivery('${delivery.id}')" title="Start Delivery">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-success btn-action" onclick="markDelivered('${delivery.id}')" title="Mark Delivered">
                                <i class="fas fa-check"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        // Load Messages
        function loadMessages() {
            const container = document.getElementById('messagesContainer');
            
            container.innerHTML = messages.map(message => `
                <div class="message-card ${!message.read ? 'border-primary' : ''}" onclick="openMessage(${message.id})">
                    <div class="message-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="message-sender">${message.sender}</span>
                            <span class="message-type type-${message.type}">${message.type}</span>
                            ${!message.read ? '<i class="fas fa-circle text-primary" style="font-size: 0.5rem;"></i>' : ''}
                        </div>
                        <span class="message-time">${new Date(message.time).toLocaleString()}</span>
                    </div>
                    <h6 class="mb-2">${message.subject}</h6>
                    <p class="text-muted mb-0">${message.message.substring(0, 100)}${message.message.length > 100 ? '...' : ''}</p>
                    <div class="mt-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="replyToMessage(${message.id}); event.stopPropagation();">
                            <i class="fas fa-reply me-1"></i>Reply
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Load Status History
        function loadStatusHistory() {
            const container = document.getElementById('statusHistory');
            
            container.innerHTML = statusHistory.map(history => `
                <tr>
                    <td><strong>${history.orderId}</strong></td>
                    <td><span class="status-badge status-${history.previousStatus}">${getStatusText(history.previousStatus)}</span></td>
                    <td><span class="status-badge status-${history.newStatus}">${getStatusText(history.newStatus)}</span></td>
                    <td class="text-muted">${new Date(history.updatedAt).toLocaleString()}</td>
                    <td class="text-muted">${history.notes}</td>
                </tr>
            `).join('');
        }

        // Populate Status Update Selects
        function populateStatusUpdateSelects() {
            const pendingOrders = deliveries.filter(d => d.status === 'pending');
            const onWayOrders = deliveries.filter(d => d.status === 'on-way');

            // Populate On the Way select
            const onWaySelect = document.getElementById('onWayOrderSelect');
            onWaySelect.innerHTML = '<option value="">Select Order</option>' + 
                pendingOrders.map(order => `<option value="${order.id}">${order.id} - ${order.customer}</option>`).join('');

            // Populate Delivered select
            const deliveredSelect = document.getElementById('deliveredOrderSelect');
            deliveredSelect.innerHTML = '<option value="">Select Order</option>' + 
                onWayOrders.map(order => `<option value="${order.id}">${order.id} - ${order.customer}</option>`).join('');

            // Populate Bulk Update select
            const bulkSelect = document.getElementById('bulkOrderSelect');
            const availableOrders = deliveries.filter(d => d.status !== 'delivered');
            bulkSelect.innerHTML = availableOrders.map(order => 
                `<option value="${order.id}">${order.id} - ${order.customer} (${getStatusText(order.status)})</option>`
            ).join('');
        }

        // Get Status Text
        function getStatusText(status) {
            const statusMap = {
                'pending': 'Pending',
                'on-way': 'On the Way',
                'delivered': 'Delivered',
                'cancelled': 'Cancelled'
            };
            return statusMap[status] || status;
        }

        // Update Status Functions
        function updateStatus(newStatus) {
            const selectId = newStatus === 'on-way' ? 'onWayOrderSelect' : 'deliveredOrderSelect';
            const orderId = document.getElementById(selectId).value;
            
            if (!orderId) {
                showAlert('Please select an order first.', 'warning');
                return;
            }

            const delivery = deliveries.find(d => d.id === orderId);
            if (delivery) {
                const previousStatus = delivery.status;
                delivery.status = newStatus;
                
                // Add to status history
                statusHistory.unshift({
                    orderId: orderId,
                    previousStatus: previousStatus,
                    newStatus: newStatus,
                    updatedAt: new Date().toISOString(),
                    notes: `Updated by delivery partner`
                });

                // Refresh displays
                loadRecentDeliveries();
                loadDeliveriesTable();
                loadStatusHistory();
                populateStatusUpdateSelects();
                
                showAlert(`Order ${orderId} status updated to ${getStatusText(newStatus)}!`, 'success');
            }
        }

        function bulkUpdateStatus() {
            const selectedOrders = Array.from(document.getElementById('bulkOrderSelect').selectedOptions).map(option => option.value);
            const newStatus = document.getElementById('bulkStatusSelect').value;
            
            if (selectedOrders.length === 0) {
                showAlert('Please select at least one order.', 'warning');
                return;
            }

            selectedOrders.forEach(orderId => {
                const delivery = deliveries.find(d => d.id === orderId);
                if (delivery) {
                    const previousStatus = delivery.status;
                    delivery.status = newStatus;
                    
                    statusHistory.unshift({
                        orderId: orderId,
                        previousStatus: previousStatus,
                        newStatus: newStatus,
                        updatedAt: new Date().toISOString(),
                        notes: 'Bulk update by delivery partner'
                    });
                }
            });

            // Refresh displays
            loadRecentDeliveries();
            loadDeliveriesTable();
            loadStatusHistory();
            populateStatusUpdateSelects();
            
            showAlert(`${selectedOrders.length} orders updated to ${getStatusText(newStatus)}!`, 'success');
        }

        // Delivery Actions
        function viewOrderDetails(orderId) {
            const delivery = deliveries.find(d => d.id === orderId);
            if (!delivery) return;

            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content bg-dark">
                            <div class="modal-header border-bottom border-secondary">
                                <h5 class="modal-title">Order Details - ${delivery.id}</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="order-details">
                                    <div class="customer-info">
                                        <h6 class="text-primary mb-2">Customer Information</h6>
                                        <p><strong>Name:</strong> ${delivery.customer}</p>
                                        <p><strong>Contact:</strong> ${delivery.contact}</p>
                                        <p><strong>Address:</strong> ${delivery.address}</p>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6>Order Details</h6>
                                            <p><strong>Estimated:</strong> ${new Date(delivery.estimatedDelivery).toLocaleString()}</p>
                                            <p><strong>Items:</strong> ${delivery.items.join(', ')}</p>
                                            <p><strong>Total:</strong> ${delivery.total}</p>
                                            <p><strong>Priority:</strong> 
                                                <span class="badge ${delivery.priority === 'high' ? 'bg-danger' : delivery.priority === 'medium' ? 'bg-warning' : 'bg-success'}">
                                                    ${delivery.priority.toUpperCase()}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Delivery Information</h6>
                                            <p><strong>Status:</strong> 
                                                <span class="status-badge status-${delivery.status}">${getStatusText(delivery.status)}</span>
                                            </p>
                                            <p><strong>Assigned:</strong> ${new Date(delivery.assignedAt).toLocaleString()}</p>
                                            <p><strong>Notes:</strong> N/A</p>
                                            <p><strong>Delivery Address:</strong> ${delivery.address}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-top border-secondary">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" onclick="startDelivery('${delivery.id}'); this.closest('.modal').querySelector('.btn-close').click();">
                                    <i class="fas fa-truck me-2"></i>Start Delivery
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal.querySelector('.modal'));
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        function startDelivery(orderId) {
            const delivery = deliveries.find(d => d.id === orderId);
            if (delivery && delivery.status === 'pending') {
                delivery.status = 'on-way';
                
                statusHistory.unshift({
                    orderId: orderId,
                    previousStatus: 'pending',
                    newStatus: 'on-way',
                    updatedAt: new Date().toISOString(),
                    notes: 'Delivery started by partner'
                });

                loadRecentDeliveries();
                loadDeliveriesTable();
                loadStatusHistory();
                populateStatusUpdateSelects();
                
                showAlert(`Delivery for order ${orderId} has been started!`, 'info');
            }
        }

        function markDelivered(orderId) {
            const delivery = deliveries.find(d => d.id === orderId);
            if (delivery && delivery.status === 'on-way') {
                delivery.status = 'delivered';
                
                statusHistory.unshift({
                    orderId: orderId,
                    previousStatus: 'on-way',
                    newStatus: 'delivered',
                    updatedAt: new Date().toISOString(),
                    notes: 'Successfully delivered to customer'
                });

                loadRecentDeliveries();
                loadDeliveriesTable();
                loadStatusHistory();
                populateStatusUpdateSelects();
                
                showAlert(`Order ${orderId} marked as delivered!`, 'success');
            }
        }

        function refreshDeliveries() {
            showAlert('Deliveries refreshed!', 'info');
            loadDeliveriesTable();
        }

        function filterDeliveries(status) {
            const rows = document.querySelectorAll('#deliveriesTable tr');
            
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge && statusBadge.classList.contains(`status-${status}`)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        // Message Functions
        function openMessage(messageId) {
            const message = messages.find(m => m.id === messageId);
            if (message) {
                message.read = true;
                loadMessages();
                showAlert(`Message from ${message.sender} opened.`, 'info');
            }
        }

        function replyToMessage(messageId) {
            const message = messages.find(m => m.id === messageId);
            if (message) {
                document.getElementById('replyForm').style.display = 'block';
                document.querySelector('#replyForm textarea').placeholder = `Replying to: ${message.subject}...`;
                document.getElementById('replyForm').scrollIntoView({ behavior: 'smooth' });
            }
        }

        function hideReplyForm() {
            document.getElementById('replyForm').style.display = 'none';
        }

        function sendReply(event) {
            event.preventDefault();
            const message = event.target.querySelector('textarea').value;
            if (message.trim()) {
                showAlert('Reply sent successfully!', 'success');
                event.target.reset();
                hideReplyForm();
            }
        }

        function markAllRead() {
            messages.forEach(message => message.read = true);
            loadMessages();
            showAlert('All messages marked as read.', 'success');
        }

        function filterMessages(type) {
            const messageCards = document.querySelectorAll('.message-card');
            
            messageCards.forEach(card => {
                if (type === 'all') {
                    card.style.display = '';
                } else {
                    const messageType = card.querySelector(`.type-${type}`);
                    if (messageType) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }

        // Settings Functions
        function toggleSetting(toggle) {
            toggle.classList.toggle('active');
            showAlert('Setting updated successfully!', 'success');
        }

        function updateProfile(event) {
            event.preventDefault();
            showAlert('Profile updated successfully!', 'success');
        }

        function changePassword(event) {
            event.preventDefault();
            const newPassword = event.target.querySelector('input[placeholder="Enter new password"]').value;
            const confirmPassword = event.target.querySelector('input[placeholder="Confirm new password"]').value;
            
            if (newPassword !== confirmPassword) {
                showAlert('Passwords do not match!', 'warning');
                return;
            }
            
            showAlert('Password changed successfully!', 'success');
            event.target.reset();
        }

        // Utility Functions
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.innerHTML = `
                <div class="alert alert-${type} position-fixed" style="top: 90px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
            `;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 4000);
        }

        function showNotifications() {
            showAlert('You have 5 new notifications!', 'info');
        }

        function showProfile() {
            showPage('settings');
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showAlert('Logging out...', 'info');
                // Redirect logic would go here
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            }
        }

        // Setup Animations
        function setupAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        }

        // Auto-refresh functionality
        setInterval(() => {
            if (document.getElementById('dashboard').classList.contains('active')) {
                updateCurrentDate();
            }
        }, 60000); // Update every minute

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideSidebar();
                hideReplyForm();
            }
            
            // Alt + number for quick navigation
            if (event.altKey) {
                switch(event.key) {
                    case '1':
                        showPage('dashboard');
                        break;
                    case '2':
                        showPage('assigned-deliveries');
                        break;
                    case '3':
                        showPage('update-status');
                        break;
                    case '4':
                        showPage('messages');
                        break;
                    case '5':
                        showPage('settings');
                        break;
                }
            }
        });

        // Initialize animations on load
        setTimeout(() => {
            setupAnimations();
        }, 100);

        // Mobile optimization
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                document.getElementById('sidebar').classList.remove('show');
                document.getElementById('sidebarOverlay').classList.remove('show');
            }
        });

        // Status update shortcuts
        function quickStatusUpdate(orderId, status) {
            const delivery = deliveries.find(d => d.id === orderId);
            if (delivery) {
                const previousStatus = delivery.status;
                delivery.status = status;
                
                statusHistory.unshift({
                    orderId: orderId,
                    previousStatus: previousStatus,
                    newStatus: status,
                    updatedAt: new Date().toISOString(),
                    notes: `Quick update by delivery partner`
                });

                loadRecentDeliveries();
                loadDeliveriesTable();
                loadStatusHistory();
                populateStatusUpdateSelects();
                
                showAlert(`Order ${orderId} updated to ${getStatusText(status)}!`, 'success');
            }
        }

        // Emergency contact function
        function emergencyContact() {
            if (confirm('Are you sure you want to call emergency support?')) {
                showAlert('Calling emergency support...', 'warning');
                // Emergency call logic would go here
            }
        }

        // GPS/Location functions (placeholder)
        function shareLocation() {
            showAlert('Location shared with admin and customers.', 'success');
        }

        function getDirections(address) {
            showAlert(`Opening directions to: ${address}`, 'info');
            // GPS/Maps integration would go here
        }

        // Customer communication
        function callCustomer(phone) {
            showAlert(`Calling ${phone}...`, 'info');
            // Phone call integration would go here
        }

        function sendSMS(phone) {
            showAlert(`Opening SMS to ${phone}...`, 'info');
            // SMS integration would go here
        }

        // Print delivery receipt
        function printReceipt(orderId) {
            showAlert(`Preparing receipt for order ${orderId}...`, 'info');
            // Print functionality would go here
        }

        // Report issue
        function reportIssue(orderId) {
            const reason = prompt('Please describe the issue:');
            if (reason) {
                showAlert(`Issue reported for order ${orderId}. Admin will contact you soon.`, 'warning');
                // Issue reporting logic would go here
            }
        }
    </script>
</body>

</html>
<!-- >Items:</strong> ${delivery.items.join(', ')}</p>
                                            <p><strong>Total:</strong> ${delivery.total}</p>
                                            <p><strong>Priority:</strong> 
                                                <span class="badge ${delivery.priority === 'high' ? 'bg-danger' : delivery.priority === 'medium' ? 'bg-warning' : 'bg-success'}">
                                                    ${delivery.priority.toUpperCase()}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Delivery Information</h6>
                                            <p><strong>Status:</strong> 
                                                <span class="status-badge status-${delivery.status}">${getStatusText(delivery.status)}</span>
                                            </p>
                                            <p><strong>Assigned:</strong> ${new Date(delivery.assignedAt).toLocaleString()}</p>
                                            <p><strong>Notes:</strong> N/A</p>
                                            <p><strong>Delivery Address:</strong> ${delivery.address}</p> -->