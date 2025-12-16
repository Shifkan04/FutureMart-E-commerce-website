<?php
require_once '../config.php';

// Check if vendor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: ../login.php');
    exit();
}

$vendorId = $_SESSION['user_id'];

// Fetch vendor details
$stmt = $pdo->prepare("
    SELECT u.*, vp.company_name
    FROM users u
    LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
    WHERE u.id = ? AND u.role = 'vendor'
");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Mark as read
    if ($_POST['action'] === 'mark_read') {
        try {
            $stmt = $pdo->prepare("
                UPDATE admin_messages 
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND recipient_id = ?
            ");
            $stmt->execute([$_POST['notification_id'], $vendorId]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Mark all as read
    if ($_POST['action'] === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare("
                UPDATE admin_messages 
                SET is_read = 1, read_at = NOW()
                WHERE recipient_id = ? AND is_read = 0
            ");
            $stmt->execute([$vendorId]);
            
            logUserActivity($vendorId, 'notifications_mark_all_read', 'Marked all notifications as read');
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Delete notification
    if ($_POST['action'] === 'delete') {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM admin_messages 
                WHERE id = ? AND recipient_id = ?
            ");
            $stmt->execute([$_POST['notification_id'], $vendorId]);
            
            logUserActivity($vendorId, 'notification_delete', 'Deleted notification #' . $_POST['notification_id']);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';

// Get notification statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as critical,
        SUM(CASE WHEN sender_type = 'system' THEN 1 ELSE 0 END) as system_count
    FROM admin_messages
    WHERE recipient_id = ? AND recipient_type = 'vendor'
");
$statsStmt->execute([$vendorId]);
$stats = $statsStmt->fetch();

// Build query based on filter
$whereClause = "WHERE recipient_id = ? AND recipient_type = 'vendor'";
$params = [$vendorId];

switch ($filter) {
    case 'unread':
        $whereClause .= " AND is_read = 0";
        break;
    case 'orders':
        $whereClause .= " AND message_type = 'order_issue'";
        break;
    case 'inventory':
        $whereClause .= " AND subject LIKE '%stock%'";
        break;
    case 'system':
        $whereClause .= " AND sender_type = 'system'";
        break;
    case 'critical':
        $whereClause .= " AND priority IN ('high', 'urgent')";
        break;
}

// Fetch notifications
$notificationsStmt = $pdo->prepare("
    SELECT * FROM admin_messages
    $whereClause
    ORDER BY created_at DESC
    LIMIT 50
");
$notificationsStmt->execute($params);
$notifications = $notificationsStmt->fetchAll();

// Function to get notification icon and color
function getNotificationStyle($priority, $messageType) {
    $styles = [
        'urgent' => ['icon' => 'exclamation-triangle', 'color' => 'danger'],
        'high' => ['icon' => 'exclamation-circle', 'color' => 'warning'],
        'order_issue' => ['icon' => 'shopping-cart', 'color' => 'info'],
        'general' => ['icon' => 'info-circle', 'color' => 'info'],
        'support' => ['icon' => 'question-circle', 'color' => 'warning'],
        'system' => ['icon' => 'sync-alt', 'color' => 'success']
    ];
    
    if ($priority === 'urgent' || $priority === 'high') {
        return $styles[$priority];
    }
    
    return $styles[$messageType] ?? $styles['general'];
}

// Function to calculate time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *,
        *::before,
        *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        nav {
          user-select: none;
          -webkit-user-select: none;
          -moz-user-select: none;
          -ms-user-select: none;
          -o-user-select: none;
        }

        nav ul,
        nav ul li {
          outline: 0;
        }

        nav ul li a {
          text-decoration: none;
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
        }

        main {
          display: grid;
          grid-template-columns: 13% 87%;
          width: 100%;
          margin: 40px;
          background: rgb(254, 254, 254);
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset,
            0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
          border-radius: 15px;
          z-index: 10;
          overflow: hidden;
        }

        .main-menu {
            overflow: hidden;
            background: rgb(73, 57, 113);
            padding-top: 10px;
            border-radius: 15px 0 0 15px;
            font-family: "Roboto", sans-serif;
            padding-top: 20px;
        }

        .main-menu h1 {
          display: block;
          font-size: 1.5rem;
          font-weight: 500;
          text-align: center;
          margin:  0;
          color: #fff;
          font-family: "Nunito", sans-serif;
        }

        .main-menu small {
          display: block;
          font-size: 1rem;
          font-weight: 300;
          text-align: center;
          margin: 10px 0 ;
          color: #fff;
          font-family: "Nunito", sans-serif;
          padding-bottom: 10px;
        }

        .logo {
          display: none;
        }

        .nav-item {
          position: relative;
          display: block;
        }

        .nav-item a {
          position: relative;
          display: flex;
          flex-direction: row;
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
          text-decoration: none;
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

        .notification-badge {
          position: absolute;
          top: 10px;
          right: 20px;
          background: #ef4444;
          color: white;
          border-radius: 50%;
          padding: 2px 6px;
          font-size: 11px;
          font-weight: 700;
        }

        .content {
          display: grid;
          grid-template-columns: 75% 25%;
        }

        .left-content {
          display: flex;
          flex-direction: column;
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - -80px);
        }

        .stats-section h1 {
          margin: 0 0 20px;
          font-size: 1.4rem;
          font-weight: 700;
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 15px;
          margin-bottom: 20px;
        }

        .stat-card {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          padding: 20px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
        }

        .stat-card:nth-child(2) {
          background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%);
        }

        .stat-card:nth-child(3) {
          background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%);
        }

        .stat-card:nth-child(4) {
          background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%);
        }

        .stat-card i {
          font-size: 28px;
          color: #484d53;
          margin-bottom: 10px;
        }

        .stat-card h3 {
          font-size: 2rem;
          font-weight: 700;
          color: #484d53;
          margin: 5px 0;
        }

        .stat-card p {
          font-size: 0.95rem;
          font-weight: 600;
          color: #484d53;
        }

        .filter-section {
          background: white;
          border-radius: 15px;
          padding: 15px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .filter-section h2 {
          font-size: 1rem;
          font-weight: 700;
          margin-bottom: 15px;
          color: #484d53;
        }

        .filter-buttons {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
        }

        .filter-btn {
          padding: 8px 16px;
          border: 2px solid #e2e8f0;
          background: white;
          color: #64748b;
          border-radius: 20px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          font-size: 0.9rem;
        }

        .filter-btn:hover {
          border-color: rgb(73, 57, 113);
          color: rgb(73, 57, 113);
          transform: translateY(-2px);
        }

        .filter-btn.active {
          background: rgb(73, 57, 113);
          color: white;
          border-color: rgb(73, 57, 113);
        }

        .notifications-section {
          flex: 1;
          min-height: 400px;
        }

        .notifications-section h1 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 15px;
        }

        .notifications-container {
          background: white;
          border-radius: 15px;
          padding: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .notification-item {
          padding: 15px;
          border-radius: 12px;
          margin-bottom: 10px;
          border-left: 4px solid #e2e8f0;
          background: #f8fafc;
          transition: all 0.3s ease;
          cursor: pointer;
        }

        .notification-item:hover {
          transform: translateX(5px);
          box-shadow: rgba(0, 0, 0, 0.1) 0px 2px 8px;
        }

        .notification-item.unread {
          background: #fef2f2;
          border-left-color: #ef4444;
        }

        .notification-item.danger {
          border-left-color: #ef4444;
        }

        .notification-item.warning {
          border-left-color: #f59e0b;
        }

        .notification-item.info {
          border-left-color: #3b82f6;
        }

        .notification-item.success {
          border-left-color: #10b981;
        }

        .notification-header {
          display: flex;
          align-items: start;
          gap: 15px;
          margin-bottom: 10px;
        }

        .notification-icon {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
        }

        .notification-icon.danger {
          background: rgba(239, 68, 68, 0.2);
          color: #ef4444;
        }

        .notification-icon.warning {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .notification-icon.info {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .notification-icon.success {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .notification-content {
          flex: 1;
        }

        .notification-title {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 5px;
        }

        .notification-title h6 {
          margin: 0;
          font-size: 1rem;
          font-weight: 700;
          color: #484d53;
        }

        .notification-time {
          font-size: 0.8rem;
          color: #64748b;
        }

        .notification-message {
          font-size: 0.9rem;
          color: #64748b;
          margin-bottom: 10px;
        }

        .notification-actions {
          display: flex;
          gap: 10px;
        }

        .btn {
          padding: 6px 12px;
          border: none;
          border-radius: 8px;
          font-size: 0.85rem;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .btn:hover {
          transform: translateY(-2px);
        }

        .btn-primary {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-danger {
          background: #ef4444;
          color: white;
        }

        .btn-outline {
          background: white;
          border: 2px solid #e2e8f0;
          color: #64748b;
        }

        .empty-state {
          text-align: center;
          padding: 60px 20px;
          color: #64748b;
        }

        .empty-state i {
          font-size: 64px;
          opacity: 0.3;
          display: block;
          margin-bottom: 15px;
        }

        .right-content {
          display: grid;
          grid-template-rows: 5% 95%;
          background: #f6f7fb;
          margin: 15px 15px 15px 0;
          padding: 10px 0;
          border-radius: 15px;
        }

        .user-info {
          display: grid;
          grid-template-columns: 30% 55% 15%;
          align-items: center;
          padding: 0 10px;
        }

        .icon-container {
          display: flex;
          gap: 15px;
        }

        .icon-container i {
          font-size: 18px;
          color: #484d53;
          cursor: pointer;
        }

        .user-info h4 {
          margin-left: 40px;
        }

        .user-info img {
          width: 40px;
          aspect-ratio: 1/1;
          border-radius: 50%;
        }

        .user-avatar {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 700;
          color: white;
          font-size: 16px;
        }

        .quick-actions {
          padding: 15px 10px;
          overflow-y: auto;
        }

        .quick-actions h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .action-list {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .action-item {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 12px;
          background: white;
          border-radius: 12px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          text-decoration: none;
          color: #484d53;
          font-weight: 600;
          transition: all 0.3s ease;
          cursor: pointer;
          border: none;
          width: 100%;
          text-align: left;
        }

        .action-item:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .action-item i {
          width: 30px;
          height: 30px;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
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

        @media (max-width: 1500px) {
          main {
            grid-template-columns: 6% 94%;
          }

          .main-menu h1 {
            display: none;
          }

          .logo {
            display: block;
            width: 30px;
            margin: 20px auto;
          }

          .nav-text {
            display: none;
          }

          .content {
            grid-template-columns: 70% 30%;
          }
        }

        @media (max-width: 1310px) {
          main {
            grid-template-columns: 8% 92%;
            margin: 30px;
          }

          .content {
            grid-template-columns: 65% 35%;
          }

          .stats-grid {
            grid-template-columns: repeat(2, 1fr);
          }
        }

        @media (max-width: 910px) {
          main {
            grid-template-columns: 10% 90%;
            margin: 20px;
          }

          .content {
            grid-template-columns: 100%;
          }

          .right-content {
            margin: 15px;
          }
        }

        @media (max-width: 700px) {
          main {
            grid-template-columns: 15% 85%;
          }

          .left-content {
            margin: 0 15px 15px 15px;
          }

          .stats-grid {
            grid-template-columns: 1fr;
          }
        }
    </style>
</head>
<body>
    <main>
      <nav class="main-menu">
        <h1><?php echo APP_NAME; ?></h1>
        <small>Vendor Panel</small>
        <img class="logo" src="https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/4cfdcb5a-0137-4457-8be1-6e7bd1f29ebb" alt="" />
        <ul>
          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="dashboard.php">
              <i class="fa fa-home nav-icon"></i>
              <span class="nav-text">Dashboard</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="products.php">
              <i class="fa fa-box nav-icon"></i>
              <span class="nav-text">Products</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="orders.php">
              <i class="fa fa-shopping-cart nav-icon"></i>
              <span class="nav-text">Orders</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="inventory.php">
              <i class="fa fa-boxes nav-icon"></i>
              <span class="nav-text">Inventory</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="delivery.php">
              <i class="fa fa-truck nav-icon"></i>
              <span class="nav-text">Delivery</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="analytics.php">
              <i class="fa fa-chart-bar nav-icon"></i>
              <span class="nav-text">Analytics</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="customers.php">
              <i class="fa fa-users nav-icon"></i>
              <span class="nav-text">Customers</span>
            </a>
          </li>

          <li class="nav-item active">
            <b></b>
            <b></b>
            <a href="notifications.php">
              <i class="fa fa-bell nav-icon"></i>
              <span class="nav-text">Notifications</span>
              <?php if ($stats['unread'] > 0): ?>
              <span class="notification-badge"><?php echo $stats['unread']; ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="contact.php">
              <i class="fa fa-envelope nav-icon"></i>
              <span class="nav-text">Contact</span>
            </a>
          </li>

          <li class="nav-item">
            <b></b>
            <b></b>
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
        </ul>
      </nav>

      <section class="content">
        <div class="left-content">
          <div class="stats-section">
            <h1>Notifications Overview</h1>
            <div class="stats-grid">
              <div class="stat-card">
                <i class="fas fa-bell"></i>
                <div>
                  <h3><?php echo number_format($stats['total']); ?></h3>
                  <p>Total</p>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-envelope"></i>
                <div>
                  <h3><?php echo number_format($stats['unread']); ?></h3>
                  <p>Unread</p>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                  <h3><?php echo number_format($stats['critical']); ?></h3>
                  <p>Critical</p>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-sync-alt"></i>
                <div>
                  <h3><?php echo number_format($stats['system_count']); ?></h3>
                  <p>System</p>
                </div>
              </div>
            </div>
          </div>

          <div class="filter-section">
            <h2>Filter Notifications</h2>
            <div class="filter-buttons">
              <a href="notifications.php?filter=all" class="filter-btn <?php echo ($filter === 'all') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All
              </a>
              <a href="notifications.php?filter=unread" class="filter-btn <?php echo ($filter === 'unread') ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Unread
              </a>
              <a href="notifications.php?filter=orders" class="filter-btn <?php echo ($filter === 'orders') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Orders
              </a>
              <a href="notifications.php?filter=inventory" class="filter-btn <?php echo ($filter === 'inventory') ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Inventory
              </a>
              <a href="notifications.php?filter=system" class="filter-btn <?php echo ($filter === 'system') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> System
              </a>
              <a href="notifications.php?filter=critical" class="filter-btn <?php echo ($filter === 'critical') ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-circle"></i> Critical
              </a>
            </div>
          </div>

          <div class="notifications-section">
            <h1>Notifications (<?php echo count($notifications); ?>)</h1>
            <div class="notifications-container">
              <?php if (empty($notifications)): ?>
              <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications</h3>
                <p>You're all caught up!</p>
              </div>
              <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <?php $style = getNotificationStyle($notification['priority'], $notification['message_type']); ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?> <?php echo $style['color']; ?>"
                     data-id="<?php echo $notification['id']; ?>">
                  <div class="notification-header">
                    <div class="notification-icon <?php echo $style['color']; ?>">
                      <i class="fas fa-<?php echo $style['icon']; ?>"></i>
                    </div>
                    <div class="notification-content">
                      <div class="notification-title">
                        <h6><?php echo htmlspecialchars($notification['subject']); ?></h6>
                        <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                      </div>
                      <div class="notification-message">
                        <?php echo htmlspecialchars($notification['message']); ?>
                      </div>
                      <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                        <button class="btn btn-primary" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                          <i class="fas fa-check"></i> Mark Read
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="right-content">
          <div class="user-info">
            <div class="icon-container">
              <i class="fa fa-bell"></i>
              <i class="fa fa-message"></i>
            </div>
            <h4><?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></h4>
            <?php if (!empty($vendor['profile_picture'])): ?>
                <img src="../<?php echo htmlspecialchars($vendor['profile_picture']); ?>" alt="Vendor">
            <?php else: ?>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($vendor['first_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
          </div>

          <div class="quick-actions">
            <h1>Quick Actions</h1>
            <div class="action-list">
              <button onclick="markAllRead()" class="action-item">
                <i class="fas fa-check-double"></i>
                <span>Mark All Read</span>
              </button>
              <a href="notifications.php?filter=unread" class="action-item">
                <i class="fas fa-envelope"></i>
                <span>View Unread</span>
              </a>
              <a href="notifications.php?filter=critical" class="action-item">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Critical Only</span>
              </a>
              <a href="notifications.php?filter=orders" class="action-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Order Alerts</span>
              </a>
              <a href="notifications.php?filter=inventory" class="action-item">
                <i class="fas fa-boxes"></i>
                <span>Inventory Alerts</span>
              </a>
              <a href="settings.php" class="action-item">
                <i class="fas fa-cog"></i>
                <span>Notification Settings</span>
              </a>
            </div>

            <h1 style="margin-top: 30px;">Navigation</h1>
            <div class="action-list">
              <a href="dashboard.php" class="action-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
              </a>
              <a href="orders.php" class="action-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
              </a>
              <a href="analytics.php" class="action-item">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
              </a>
              <a href="customers.php" class="action-item">
                <i class="fas fa-users"></i>
                <span>Customers</span>
              </a>
            </div>
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

        // Set active menu based on current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'notifications.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });
        });

        // Mark as Read
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&action=mark_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error marking notification as read');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        // Mark All Read
        function markAllRead() {
            if (!confirm('Mark all notifications as read?')) return;
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ajax=1&action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error marking all as read');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        // Delete Notification
        function deleteNotification(notificationId) {
            if (!confirm('Delete this notification?')) return;
            
            fetch('notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&action=delete&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting notification');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
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