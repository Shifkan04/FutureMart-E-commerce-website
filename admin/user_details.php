<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

checkSessionTimeout();

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$userId = (int)$_GET['id'];

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*,
           CONCAT(u.first_name, ' ', u.last_name) as full_name
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit();
}

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Get user's addresses
$stmt = $pdo->prepare("
    SELECT * FROM user_addresses
    WHERE user_id = ?
    ORDER BY is_default DESC
");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

// Get user activity log
$stmt = $pdo->prepare("
    SELECT * FROM user_activity_log
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$activities = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
$stmt->execute([$userId]);
$totalOrders = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE user_id = ? AND payment_status = 'paid'");
$stmt->execute([$userId]);
$totalSpent = $stmt->fetch()['total'];

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

function getInitials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *, *::before, *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        body {
          font-family: "Nunito", sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          background-image: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);
          background-repeat: no-repeat;
          background-size: cover;
          background-attachment: fixed;
        }

        main {
          display: grid;
          grid-template-columns: 13% 87%;
          width: 100%;
          margin: 40px;
          background: rgb(254, 254, 254);
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset, 0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
          border-radius: 15px;
          z-index: 10;
        }

        .main-menu {
          overflow: hidden;
          background: rgb(73, 57, 113);
          padding-top: 10px;
          border-radius: 15px 0 0 15px;
          font-family: "Roboto", sans-serif;
          padding-bottom: 20px;
        }

         .main-menu h1 {
          display: block;
          font-size: 1.5rem;
          font-weight: 500;
          text-align: center;
          margin: 0;
          color: #fff;
          font-family: "Nunito", sans-serif;
          padding-top: 20px;
        }

        .main-menu small {
          display: block;
          font-size: 1rem;
          font-weight: 300;
          text-align: center;
          margin: 10px 0;
          color: #fff;
        }

        .logo {
          display: none;
          width: 30px;
          margin: 20px auto;
        }

        nav {
          user-select: none;
        }

        nav ul, nav ul li {
          outline: 0;
        }

        nav ul li a {
          text-decoration: none;
        }

        .nav-item {
          position: relative;
          display: block;
        }

        .nav-item a {
          position: relative;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #fff;
          font-size: 1rem;
          padding: 15px 0;
          margin-left: 10px;
          border-top-left-radius: 20px;
          border-bottom-left-radius: 20px;
        }

        .nav-item b:nth-child(1) {
          position: absolute;
          top: -15px;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(1)::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border-bottom-right-radius: 20px;
          background: rgb(73, 57, 113);
        }

        .nav-item b:nth-child(2) {
          position: absolute;
          bottom: -15px;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(2)::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border-top-right-radius: 20px;
          background: rgb(73, 57, 113);
        }

        .nav-item.active b:nth-child(1),
        .nav-item.active b:nth-child(2) {
          display: block;
        }

        .nav-item.active a {
          color: #000;
          background: rgb(254, 254, 254);
        }

        .nav-icon {
          width: 60px;
          height: 20px;
          font-size: 20px;
          text-align: center;
        }

        .nav-text {
          display: block;
          width: 120px;
          height: 20px;
        }

        .content {
          display: grid;
          grid-template-columns: 30% 70%;
          gap: 20px;
        }

        .left-content {
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
        }

        .user-avatar-section {
          text-align: center;
          margin-bottom: 30px;
        }

        .user-avatar {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          color: white;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 3rem;
          font-weight: 700;
          margin: 0 auto 20px;
          box-shadow: 0 8px 20px rgba(124, 136, 224, 0.4);
        }

        .user-name {
          font-size: 1.5rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .user-email {
          color: #64748b;
          font-size: 0.95rem;
          margin-bottom: 15px;
        }

        .badge {
          display: inline-block;
          padding: 6px 15px;
          border-radius: 20px;
          font-size: 0.85rem;
          font-weight: 700;
          margin: 0 3px;
        }

        .badge.success {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge.warning {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .badge.info {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .badge.danger {
          background: rgba(239, 68, 68, 0.2);
          color: #ef4444;
        }

        .quick-stats-card {
          background: white;
          padding: 20px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
          margin-top: 20px;
        }

        .quick-stats-card h6 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
          border-bottom: 2px solid #f6f7fb;
          padding-bottom: 10px;
        }

        .stat-row {
          display: flex;
          justify-content: space-between;
          padding: 10px 0;
          border-bottom: 1px solid #f6f7fb;
        }

        .stat-row:last-child {
          border-bottom: none;
        }

        .stat-label {
          color: #64748b;
          font-weight: 600;
        }

        .stat-value {
          color: #484d53;
          font-weight: 700;
        }

        .right-content {
          background: #f6f7fb;
          margin: 15px 15px 15px 0;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
        }

        .header-section {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 30px;
        }

        .header-section h2 {
          font-size: 1.8rem;
          font-weight: 700;
          color: #484d53;
        }

        .header-section h2 i {
          margin-right: 10px;
          color: rgb(124, 136, 224);
        }

        .action-buttons {
          display: flex;
          gap: 10px;
        }

        .btn {
          padding: 10px 20px;
          border: none;
          border-radius: 10px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          gap: 8px;
        }

        .btn-primary {
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          color: #484d53;
        }

        .btn-primary:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 15px rgba(124, 136, 224, 0.3);
        }

        .btn-outline {
          background: white;
          color: rgb(124, 136, 224);
          border: 2px solid rgb(124, 136, 224);
        }

        .btn-outline:hover {
          background: rgba(124, 136, 224, 0.1);
          transform: translateY(-2px);
        }

        .tabs {
          display: flex;
          gap: 10px;
          margin-bottom: 20px;
          border-bottom: 2px solid #e2e8f0;
        }

        .tab-btn {
          padding: 12px 24px;
          background: transparent;
          border: none;
          border-bottom: 3px solid transparent;
          color: #64748b;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
        }

        .tab-btn:hover {
          color: rgb(124, 136, 224);
        }

        .tab-btn.active {
          color: rgb(124, 136, 224);
          border-bottom-color: rgb(124, 136, 224);
        }

        .tab-btn i {
          margin-right: 8px;
        }

        .tab-content {
          display: none;
          animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
          display: block;
        }

        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: translateY(10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .info-card {
          background: white;
          padding: 25px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
        }

        .info-card h5 {
          font-size: 1.2rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 20px;
        }

        .info-row {
          display: grid;
          grid-template-columns: 40% 60%;
          padding: 12px 0;
          border-bottom: 1px solid #f6f7fb;
        }

        .info-row:last-child {
          border-bottom: none;
        }

        .info-label {
          font-weight: 700;
          color: #484d53;
        }

        .info-value {
          color: #64748b;
        }

        .data-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
        }

        .data-table thead {
          background: #f6f7fb;
        }

        .data-table th {
          padding: 12px;
          text-align: left;
          font-weight: 700;
          color: #484d53;
          font-size: 0.9rem;
        }

        .data-table td {
          padding: 12px;
          border-bottom: 1px solid #f6f7fb;
          color: #64748b;
          font-size: 0.9rem;
        }

        .data-table tr:hover {
          background: #f6f7fb;
        }

        .empty-state {
          text-align: center;
          padding: 40px;
          color: #94a3b8;
        }

        .address-card {
          background: white;
          padding: 20px;
          border-radius: 12px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
          margin-bottom: 15px;
        }

        .address-card h6 {
          font-size: 1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 10px;
        }

        .address-card p {
          color: #64748b;
          line-height: 1.8;
          margin: 0;
        }

        .activity-list {
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .activity-item {
          background: white;
          padding: 15px;
          border-radius: 12px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
          border-left: 4px solid rgb(124, 136, 224);
        }

        .activity-header {
          display: flex;
          justify-content: space-between;
          margin-bottom: 8px;
        }

        .activity-title {
          font-weight: 700;
          color: #484d53;
          font-size: 1rem;
        }

        .activity-time {
          font-size: 0.85rem;
          color: #94a3b8;
        }

        .activity-desc {
          color: #64748b;
          margin-bottom: 5px;
        }

        .activity-ip {
          font-size: 0.85rem;
          color: #94a3b8;
        }

         /* lil style glow for the alert */
  .swal2-popup {
    border-radius: 15px !important;
    box-shadow: 0 8px 25px rgba(73, 57, 113, 0.3);
  }

  .swal2-confirm.swal-confirm {
    background: linear-gradient(135deg, rgb(73, 57, 113), #a38cd9) !important;
    border: none !important;
    font-weight: 600;
  }

  .swal2-cancel.swal-cancel {
    background: #f6f7fb !important;
    color: #484d53 !important;
    border: 1px solid #ddd !important;
  }

  .swal2-confirm:hover, .swal2-cancel:hover {
    transform: translateY(-1px);
  }

        @media print {
          body {
            background: white;
            display: block;
          }
          
          main {
            display: block;
            margin: 0;
            padding: 20px;
            box-shadow: none;
            background: white;
            width: 100%;
          }
          
          .no-print, .main-menu {
            display: none !important;
          }
          
          .content {
            display: block;
            grid-template-columns: 1fr;
          }
          
          .left-content {
            margin: 0 0 20px 0;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
          }
          
          .right-content {
            margin: 0;
            background: white;
            max-height: none;
            overflow: visible;
          }
          
          .info-card, .address-card, .activity-item {
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
          }
          
          .data-table {
            page-break-inside: auto;
          }
          
          .data-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
          }
          
          .data-table thead {
            display: table-header-group;
          }
          
          .tabs {
            display: none !important;
          }
          
          .tab-content {
            display: block !important;
            page-break-before: auto;
          }
          
          #info, #orders, #addresses, #activity {
            display: block !important;
            page-break-before: auto;
            margin-bottom: 30px;
          }
          
          #orders::before {
            content: "Order History";
            display: block;
            font-size: 20px;
            font-weight: 700;
            margin: 30px 0 15px 0;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
          }
          
          #addresses::before {
            content: "Saved Addresses";
            display: block;
            font-size: 20px;
            font-weight: 700;
            margin: 30px 0 15px 0;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
          }
          
          #activity::before {
            content: "Recent Activity";
            display: block;
            font-size: 20px;
            font-weight: 700;
            margin: 30px 0 15px 0;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
          }
          
          .badge.success {
            background: #d1fae5 !important;
            color: #065f46 !important;
            border: 1px solid #10b981;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          .badge.warning {
            background: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #f59e0b;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          .badge.info {
            background: #dbeafe !important;
            color: #1e3a8a !important;
            border: 1px solid #3b82f6;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          .badge.danger {
            background: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #ef4444;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          .user-avatar {
            background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc) !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          .activity-item {
            border-left-color: rgb(124, 136, 224) !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          h2, h5, h6 {
            color: #1f2937 !important;
          }
          
          .right-content::before {
            content: "<?php echo APP_NAME ?? 'User Details Report'; ?>";
            display: block;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
          }
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .content { grid-template-columns: 100%; }
          .left-content { margin: 15px 15px 0 15px; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .action-buttons { flex-direction: column; }
          .tabs { flex-wrap: wrap; }
          .info-row { grid-template-columns: 1fr; gap: 5px; }
        }
    </style>
</head>
<body>
    <main>
         <nav class="main-menu">
            <h1><i class="fas fa-rocket" style="margin-right: 8px;"></i><?php echo APP_NAME; ?></h1>
            <small>Admin Panel</small>
            <div class="logo">
                <i class="fa fa-rocket" style="font-size:24px;color:white"></i>
            </div>
            <ul>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="dashboard.php">
                        <i class="fa fa-home nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="products.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="orders.php">
                        <i class="fa fa-shopping-cart nav-icon"></i>
                        <span class="nav-text">Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="vendors.php">
                        <i class="fa fa-users-cog nav-icon"></i>
                        <span class="nav-text">Vendors</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <b></b><b></b>
                    <a href="users.php">
                        <i class="fa fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="delivery.php">
                        <i class="fa fa-truck nav-icon"></i>
                        <span class="nav-text">Delivery</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="analytics.php">
                        <i class="fa fa-chart-bar nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>

                 <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="testimonials.php">
                        <i class="fa fa-star nav-icon"></i>
                        <span class="nav-text">Testimonials
                    </a>
                </li>

                <li class="nav-item"><b></b><b></b><a href="notifications.php"><i class="fa fa-bell nav-icon"></i><span class="nav-text">Notifications</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="contact.php"><i class="fa fa-envelope nav-icon"></i><span class="nav-text">Contact</span></a></li>

                <li class="nav-item">
                    <b></b><b></b>
                    <a href="settings.php">
                        <i class="fa fa-cog nav-icon"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
                <li class="nav-item">
  <b></b>
  <b></b>
  <a href="#" onclick="confirmLogout(event)">
    <i class="fa fa-sign-out-alt nav-icon"></i>
    <span class="nav-text">Logout</span>
  </a>
</li>
    </nav>

        <section class="content">
            <div class="left-content">
                <div class="user-avatar-section">
                    <div class="user-avatar">
                        <?php echo getInitials($user['full_name']); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <div>
                        <span class="badge <?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'vendor' ? 'success' : 'info'); ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <span class="badge <?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="quick-stats-card">
                    <h6><i class="fas fa-chart-line" style="margin-right: 8px;"></i>Quick Stats</h6>
                    <div class="stat-row">
                        <span class="stat-label">User ID:</span>
                        <span class="stat-value"><?php echo $user['id']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Joined:</span>
                        <span class="stat-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Orders:</span>
                        <span class="stat-value"><?php echo $totalOrders; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Total Spent:</span>
                        <span class="stat-value">$<?php echo number_format($totalSpent, 2); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Email Verified:</span>
                        <span class="stat-value"><?php echo $user['email_verified_at'] ? '✓ Yes' : '✗ No'; ?></span>
                    </div>
                </div>
            </div>

            <div class="right-content">
                <div class="header-section no-print">
                    <h2><i class="fas fa-user"></i>User Details</h2>
                    <div class="action-buttons">
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <a href="users.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>

                <div class="tabs no-print">
                    <button class="tab-btn active" onclick="switchTab(event, 'info')">
                        <i class="fas fa-info-circle"></i> Information
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'orders')">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'addresses')">
                        <i class="fas fa-map-marker-alt"></i> Addresses
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'activity')">
                        <i class="fas fa-history"></i> Activity
                    </button>
                </div>

                <div id="info" class="tab-content active">
                    <div class="info-card">
                        <h5>Personal Information</h5>
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Role:</span>
                            <span class="info-value"><?php echo ucfirst($user['role']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value"><?php echo ucfirst($user['status']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth:</span>
                            <span class="info-value"><?php echo $user['date_of_birth'] ? date('M d, Y', strtotime($user['date_of_birth'])) : 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Gender:</span>
                            <span class="info-value"><?php echo $user['gender'] ? ucfirst($user['gender']) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>

                <div id="orders" class="tab-content">
                    <div class="info-card">
                        <h5>Order History (<?php echo $totalOrders; ?> total)</h5>
                        <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                            <p>No orders found</p>
                        </div>
                        <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $order['status'] === 'delivered' ? 'success' : 
                                                ($order['status'] === 'pending' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="addresses" class="tab-content">
                    <h5 style="font-size: 1.2rem; font-weight: 700; color: #484d53; margin-bottom: 20px;">Saved Addresses</h5>
                    <?php if (empty($addresses)): ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                        <p>No addresses found</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($addresses as $address): ?>
                        <div class="address-card">
                            <h6>
                                <?php echo htmlspecialchars($address['title']); ?>
                                <?php if ($address['is_default']): ?>
                                <span class="badge info" style="margin-left: 8px;">Default</span>
                                <?php endif; ?>
                            </h6>
                            <p>
                                <?php echo htmlspecialchars($address['address_line_1']); ?><br>
                                <?php if ($address['address_line_2']): ?>
                                    <?php echo htmlspecialchars($address['address_line_2']); ?><br>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($address['city']); ?>, 
                                <?php echo htmlspecialchars($address['state']); ?> 
                                <?php echo htmlspecialchars($address['postal_code']); ?><br>
                                <?php echo htmlspecialchars($address['country']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="activity" class="tab-content">
                    <h5 style="font-size: 1.2rem; font-weight: 700; color: #484d53; margin-bottom: 20px;">Recent Activity</h5>
                    <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                        <p>No activity found</p>
                    </div>
                    <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-header">
                                <span class="activity-title"><?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                <span class="activity-time"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></span>
                            </div>
                            <p class="activity-desc"><?php echo htmlspecialchars($activity['activity_description']); ?></p>
                            <?php if ($activity['ip_address']): ?>
                            <small class="activity-ip">IP: <?php echo htmlspecialchars($activity['ip_address']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const navItems = document.querySelectorAll(".nav-item");

        navItems.forEach((navItem) => {
            navItem.addEventListener("click", () => {
                navItems.forEach((item) => {
                    item.classList.remove("active");
                });
                navItem.classList.add("active");
            });
        });

        function switchTab(event, tabName) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

         function confirmLogout(e) {
    e.preventDefault();

    Swal.fire({
      title: 'Logout Confirmation',
      text: 'Are you sure you wanna log out?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: 'rgb(73, 57, 113)',   // your purple
      cancelButtonColor: '#aaa',
      confirmButtonText: 'Yes, log me out',
      cancelButtonText: 'Cancel',
      background: '#fefefe',
      color: '#484d53',
      backdrop: `
        rgba(73, 57, 113, 0.4)
        left top
        no-repeat
      `,
      customClass: {
        popup: 'animated fadeInDown',
        title: 'swal-title',
        confirmButton: 'swal-confirm',
        cancelButton: 'swal-cancel'
      }
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'Logging out...',
          text: 'Please wait a moment',
          icon: 'info',
          showConfirmButton: false,
          timer: 1200,
          timerProgressBar: true,
          didClose: () => {
            window.location.href = '../logout.php';
          }
        });
      }
    });
  }
    </script>
</body>
</html>