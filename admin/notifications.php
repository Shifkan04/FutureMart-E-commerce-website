<?php
require_once '../config.php';

// Check if user is admin
requireAdmin();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'mark_as_read':
                $notificationId = filter_var($_POST['notification_id'], FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("UPDATE admin_messages SET is_read = 1, read_at = NOW() WHERE id = ?");
                $stmt->execute([$notificationId]);
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                exit;

            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE admin_messages SET is_read = 1, read_at = NOW() WHERE recipient_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                logUserActivity($_SESSION['user_id'], 'notifications_mark_all_read', 'Marked all notifications as read');
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                exit;

            case 'delete_notification':
                $notificationId = filter_var($_POST['notification_id'], FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("DELETE FROM admin_messages WHERE id = ? AND recipient_id = ?");
                $stmt->execute([$notificationId, $_SESSION['user_id']]);
                logUserActivity($_SESSION['user_id'], 'notification_delete', "Deleted notification #$notificationId");
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
                exit;

            case 'delete_all':
                $stmt = $pdo->prepare("DELETE FROM admin_messages WHERE recipient_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                logUserActivity($_SESSION['user_id'], 'notifications_delete_all', 'Deleted all notifications');
                echo json_encode(['success' => true, 'message' => 'All notifications deleted']);
                exit;

            case 'send_reply':
                $messageId = filter_var($_POST['message_id'], FILTER_SANITIZE_NUMBER_INT);
                $replyText = sanitizeInput($_POST['reply_text']);
                $stmt = $pdo->prepare("SELECT * FROM admin_messages WHERE id = ?");
                $stmt->execute([$messageId]);
                $originalMsg = $stmt->fetch();

                if ($originalMsg) {
                    $stmt = $pdo->prepare("INSERT INTO admin_messages (sender_id, recipient_id, sender_type, recipient_type, subject, message, priority, message_type, parent_message_id) VALUES (?, ?, 'admin', 'user', ?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $originalMsg['sender_id'], 'Re: ' . $originalMsg['subject'], $replyText, $originalMsg['priority'], $originalMsg['message_type'], $messageId]);
                    logUserActivity($_SESSION['user_id'], 'notification_reply', "Replied to message #$messageId");
                    echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Original message not found']);
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get filter and pagination
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = "WHERE am.recipient_id = ?";
$params = [$_SESSION['user_id']];

if ($filter === 'unread') {
    $whereClause .= " AND am.is_read = 0";
} elseif ($filter !== 'all') {
    $whereClause .= " AND am.message_type = ?";
    $params[] = $filter;
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM admin_messages am $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalNotifications = $stmt->fetch()['total'];
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications
$query = "SELECT am.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name, u.email as sender_email FROM admin_messages am LEFT JOIN users u ON am.sender_id = u.id $whereClause ORDER BY am.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$stats['unread'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND priority = 'urgent'");
$stmt->execute([$_SESSION['user_id']]);
$stats['critical'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND message_type = 'general'");
$stmt->execute([$_SESSION['user_id']]);
$stats['system'] = $stmt->fetchColumn();

// Get admin info
$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name, email, avatar FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Get pending counts for badges
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pendingVendors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendor' AND status = 'inactive'")->fetchColumn();

function getNotificationIcon($type, $priority) {
    if ($priority === 'urgent') return ['class' => 'danger', 'icon' => 'fa-exclamation-triangle'];
    switch ($type) {
        case 'order_issue': return ['class' => 'warning', 'icon' => 'fa-shopping-cart'];
        case 'vendor_application': return ['class' => 'info', 'icon' => 'fa-user-plus'];
        case 'support': return ['class' => 'info', 'icon' => 'fa-life-ring'];
        default: return ['class' => 'success', 'icon' => 'fa-bell'];
    }
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    if ($difference < 60) return $difference . ' seconds ago';
    if ($difference < 3600) return floor($difference / 60) . ' minutes ago';
    if ($difference < 86400) return floor($difference / 3600) . ' hours ago';
    if ($difference < 604800) return floor($difference / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
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

        *, *::before, *::after {
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

        nav ul, nav ul li {
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
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
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
          margin-bottom: 30px;
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
          margin-bottom: 8px;
        }

        .filter-section {
          margin: 20px 0;
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
        }

        .filter-btn {
          padding: 8px 16px;
          border-radius: 20px;
          border: 2px solid #e2e8f0;
          background: white;
          color: #484d53;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          font-size: 0.9rem;
        }

        .filter-btn:hover, .filter-btn.active {
          background: rgb(73, 57, 113);
          color: white;
          border-color: rgb(73, 57, 113);
          transform: translateY(-2px);
        }

        .notifications-list {
          margin-top: 20px;
        }

        .notification-item {
          background: white;
          border-radius: 15px;
          padding: 15px;
          margin-bottom: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          border-left: 4px solid #e2e8f0;
          transition: all 0.3s ease;
        }

        .notification-item:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .notification-item.unread {
          background: #fef2f2;
          border-left-color: #ef4444;
        }

        .notification-item.info { border-left-color: #3b82f6; }
        .notification-item.success { border-left-color: #10b981; }
        .notification-item.warning { border-left-color: #f59e0b; }
        .notification-item.danger { border-left-color: #ef4444; }

        .notification-header {
          display: flex;
          align-items: center;
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
          font-size: 1.2rem;
        }

        .notification-icon.info { background: #dbeafe; color: #3b82f6; }
        .notification-icon.success { background: #d1fae5; color: #10b981; }
        .notification-icon.warning { background: #fef3c7; color: #f59e0b; }
        .notification-icon.danger { background: #fee2e2; color: #ef4444; }

        .notification-content {
          flex: 1;
        }

        .notification-title {
          font-size: 1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .notification-text {
          font-size: 0.9rem;
          color: #64748b;
          margin-bottom: 5px;
        }

        .notification-meta {
          display: flex;
          justify-content: space-between;
          align-items: center;
          font-size: 0.85rem;
          color: #94a3b8;
          margin-top: 10px;
        }

        .priority-urgent {
          background: linear-gradient(45deg, #ef4444, #dc2626);
          color: white;
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
          display: inline-block;
          margin-left: 10px;
        }

        .priority-high {
          background: linear-gradient(45deg, #f59e0b, #d97706);
          color: white;
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
          display: inline-block;
          margin-left: 10px;
        }

        .notification-actions {
          display: flex;
          gap: 8px;
          margin-top: 10px;
        }

        .btn {
          display: inline-block;
          padding: 6px 12px;
          font-size: 0.85rem;
          font-weight: 600;
          background: rgb(73, 57, 113);
          color: white;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          text-decoration: none;
          transition: all 0.3s ease;
        }

        .btn:hover {
          background: rgb(93, 77, 133);
          transform: translateY(-2px);
        }

        .btn-outline {
          background: white;
          color: rgb(73, 57, 113);
          border: 2px solid rgb(73, 57, 113);
        }

        .btn-outline:hover {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-danger {
          background: #ef4444;
        }

        .btn-danger:hover {
          background: #dc2626;
        }

        .reply-form {
          margin-top: 10px;
          padding: 10px;
          background: #f8fafc;
          border-radius: 10px;
          display: none;
        }

        .reply-form textarea {
          width: 100%;
          padding: 10px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          font-family: inherit;
          font-size: 0.9rem;
          resize: vertical;
          margin-bottom: 10px;
        }

        .right-content {
          display: grid;
          grid-template-rows: 5% 70%;
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
          margin-left: 20px;
          font-size: 1rem;
          color: #484d53;
        }

        .user-info img {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          object-fit: cover;
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
          background: rgb(214, 227, 248);
          padding: 15px;
          margin: 15px 10px 0;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .quick-actions h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .action-buttons {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .action-btn {
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
          border: none;
          cursor: pointer;
          font-family: inherit;
          font-size: 0.95rem;
        }

        .action-btn:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .action-btn i {
          width: 30px;
          height: 30px;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
        }

        .empty-state {
          text-align: center;
          padding: 60px 20px;
          color: #94a3b8;
        }

        .empty-state i {
          font-size: 64px;
          margin-bottom: 20px;
          opacity: 0.3;
        }

        .pagination {
          display: flex;
          justify-content: center;
          gap: 10px;
          margin-top: 20px;
        }

        .pagination a {
          padding: 8px 12px;
          background: white;
          color: #484d53;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 600;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .pagination a:hover, .pagination a.active {
          background: rgb(73, 57, 113);
          color: white;
          transform: translateY(-2px);
        }

        .alert {
          padding: 15px;
          margin-bottom: 15px;
          background: rgba(239, 68, 68, 0.1);
          border-left: 4px solid #ef4444;
          border-radius: 8px;
          color: #ef4444;
          font-weight: 600;
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
          main { grid-template-columns: 6% 94%; }
          .main-menu h1 { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
          .content { grid-template-columns: 70% 30%; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .content { grid-template-columns: 65% 35%; }
          .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .content { grid-template-columns: 55% 45%; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { grid-template-columns: 100%; grid-template-rows: 45% 55%; }
          .left-content { margin: 0 15px 15px 15px; }
          .right-content { margin: 15px; }
          .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><i class="fas fa-rocket" style="margin-right: 8px;"></i><?php echo APP_NAME; ?></h1>
             <small>Admin Panel</small>
            <div class="logo">
                <i class="fa fa-rocket" style="font-size: 24px; color: white;"></i>
            </div>
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
                        <?php if ($pendingOrders > 0): ?>
                        <span class="notification-badge"><?php echo $pendingOrders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="vendors.php">
                        <i class="fa fa-users-cog nav-icon"></i>
                        <span class="nav-text">Vendors</span>
                        <?php if ($pendingVendors > 0): ?>
                        <span class="notification-badge"><?php echo $pendingVendors; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="users.php">
                        <i class="fa fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
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
                    <a href="testimonials.php">
                        <i class="fa fa-star nav-icon"></i>
                        <span class="nav-text">Testimonials
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
    </nav>

        <section class="content">
            <div class="left-content">
                <div class="stats-section">
                    <h1>Notification Center</h1>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-bell"></i>
                            <div>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                                <p>Total Notifications</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h3><?php echo number_format($stats['unread']); ?></h3>
                                <p>Unread Messages</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <h3><?php echo number_format($stats['critical']); ?></h3>
                                <p>Critical Alerts</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-sync-alt"></i>
                            <div>
                                <h3><?php echo number_format($stats['system']); ?></h3>
                                <p>System Updates</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-section">
                    <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All
                    </a>
                    <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i> Unread
                    </a>
                    <a href="?filter=order_issue" class="filter-btn <?php echo $filter === 'order_issue' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                    <a href="?filter=vendor_application" class="filter-btn <?php echo $filter === 'vendor_application' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Vendors
                    </a>
                    <a href="?filter=support" class="filter-btn <?php echo $filter === 'support' ? 'active' : ''; ?>">
                        <i class="fas fa-life-ring"></i> Support
                    </a>
                    <a href="?filter=general" class="filter-btn <?php echo $filter === 'general' ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i> General
                    </a>
                </div>

                <div class="notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>No notifications found</h3>
                            <p>You're all caught up!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            $iconData = getNotificationIcon($notification['message_type'], $notification['priority']);
                            $isUnread = !$notification['is_read'];
                            ?>
                            <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $iconData['class']; ?>" data-id="<?php echo $notification['id']; ?>">
                                <div class="notification-header">
                                    <div class="notification-icon <?php echo $iconData['class']; ?>">
                                        <i class="fas <?php echo $iconData['icon']; ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notification['subject']); ?>
                                            <?php if ($notification['priority'] === 'urgent'): ?>
                                                <span class="priority-urgent">URGENT</span>
                                            <?php elseif ($notification['priority'] === 'high'): ?>
                                                <span class="priority-high">HIGH</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-text">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </div>
                                        <div class="notification-meta">
                                            <span>
                                                <?php if ($notification['sender_name']): ?>
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($notification['sender_name']); ?>
                                                <?php endif; ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-clock"></i> <?php echo timeAgo($notification['created_at']); ?>
                                            </span>
                                        </div>
                                        <div class="notification-actions">
                                            <?php if ($isUnread): ?>
                                                <button class="btn btn-outline" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-check"></i> Mark Read
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline" onclick="showReplyForm(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-reply"></i> Reply
                                            </button>
                                            <button class="btn btn-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                        <div id="reply-form-<?php echo $notification['id']; ?>" class="reply-form">
                                            <textarea id="reply-text-<?php echo $notification['id']; ?>" rows="3" placeholder="Type your reply..."></textarea>
                                            <button class="btn" onclick="sendReply(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-paper-plane"></i> Send Reply
                                            </button>
                                            <button class="btn btn-outline" onclick="hideReplyForm(<?php echo $notification['id']; ?>)">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" 
                                       class="<?php echo $page == $i ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-content">
                <div class="user-info">
                    <div class="icon-container">
                        <i class="fa fa-bell"></i>
                        <i class="fa fa-message"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h4>
                    <?php if (!empty($admin['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quick-actions">
                    <h1>Quick Actions</h1>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="markAllRead()">
                            <i class="fas fa-check-double"></i>
                            <span>Mark All Read</span>
                        </button>
                        <button class="action-btn" onclick="deleteAllNotifications()">
                            <i class="fas fa-trash-alt"></i>
                            <span>Clear All</span>
                        </button>
                        <a href="orders.php?filter=pending" class="action-btn">
                            <i class="fas fa-shopping-cart"></i>
                            <span>View Pending Orders</span>
                        </a>
                        <a href="vendors.php?filter=pending" class="action-btn">
                            <i class="fas fa-user-check"></i>
                            <span>Approve Vendors</span>
                        </a>
                        <a href="settings.php" class="action-btn">
                            <i class="fas fa-cog"></i>
                            <span>Notification Settings</span>
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

        function markAsRead(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', notificationId);

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationItem) {
                        notificationItem.classList.remove('unread');
                        const markReadBtn = notificationItem.querySelector('button[onclick*="markAsRead"]');
                        if (markReadBtn) {
                            markReadBtn.remove();
                        }
                    }
                    updateUnreadCount();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while marking notification as read');
            });
        }

        function markAllRead() {
            if (!confirm('Mark all notifications as read?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while marking all as read');
            });
        }

        function deleteNotification(notificationId) {
            if (!confirm('Delete this notification?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_notification');
            formData.append('notification_id', notificationId);

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationItem) {
                        notificationItem.style.transition = 'opacity 0.3s ease';
                        notificationItem.style.opacity = '0';
                        setTimeout(() => {
                            notificationItem.remove();
                            const remainingNotifications = document.querySelectorAll('.notification-item');
                            if (remainingNotifications.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                    updateUnreadCount();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting notification');
            });
        }

        function deleteAllNotifications() {
            if (!confirm('Are you sure you want to delete ALL notifications? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_all');

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting notifications');
            });
        }

        function showReplyForm(messageId) {
            document.getElementById('reply-form-' + messageId).style.display = 'block';
        }

        function hideReplyForm(messageId) {
            document.getElementById('reply-form-' + messageId).style.display = 'none';
        }

        function sendReply(messageId) {
            const replyText = document.getElementById('reply-text-' + messageId).value.trim();

            if (!replyText) {
                alert('Please enter a reply message');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_reply');
            formData.append('message_id', messageId);
            formData.append('reply_text', replyText);

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reply sent successfully!');
                    hideReplyForm(messageId);
                    document.getElementById('reply-text-' + messageId).value = '';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending reply');
            });
        }

        function updateUnreadCount() {
            const unreadItems = document.querySelectorAll('.notification-item.unread').length;
            const badges = document.querySelectorAll('.notification-badge');

            badges.forEach(badge => {
                if (unreadItems > 0) {
                    badge.textContent = unreadItems;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        // Click notification to mark as read
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!e.target.closest('button') && !e.target.closest('textarea')) {
                    if (this.classList.contains('unread')) {
                        const notificationId = this.getAttribute('data-id');
                        markAsRead(notificationId);
                    }
                }
            });
        });

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.get('page') || urlParams.get('page') === '1') {
                updateUnreadCount();
            }
        }, 30000);

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