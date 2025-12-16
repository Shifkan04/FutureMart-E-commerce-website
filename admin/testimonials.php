<?php
require_once '../config.php';

// Check if user is admin
requireAdmin();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Fetch current user data
$stmt = $pdo->prepare("
    SELECT u.*, up.language, up.currency, up.date_format, up.time_format, up.timezone
    FROM users u
    LEFT JOIN user_preferences up ON u.id = up.user_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// Get pending counts for badges
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pendingVendors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendor' AND status = 'inactive'")->fetchColumn();
$unreadNotifications = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND is_read = 0");
$unreadNotifications->execute([$userId]);
$unreadCount = $unreadNotifications->fetchColumn();

// Handle testimonial actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['approve_testimonial'])) {
            $testimonialId = (int)$_POST['testimonial_id'];
            $stmt = $pdo->prepare("UPDATE testimonials SET is_approved = 1 WHERE id = ?");
            $stmt->execute([$testimonialId]);
            
            logUserActivity($userId, 'testimonial_approve', "Approved testimonial ID: $testimonialId");
            $message = 'Testimonial approved successfully!';
            $messageType = 'success';
        }
        
        if (isset($_POST['reject_testimonial'])) {
            $testimonialId = (int)$_POST['testimonial_id'];
            $stmt = $pdo->prepare("UPDATE testimonials SET is_approved = 0 WHERE id = ?");
            $stmt->execute([$testimonialId]);
            
            logUserActivity($userId, 'testimonial_reject', "Rejected testimonial ID: $testimonialId");
            $message = 'Testimonial rejected!';
            $messageType = 'warning';
        }
        
        if (isset($_POST['toggle_featured'])) {
            $testimonialId = (int)$_POST['testimonial_id'];
            $stmt = $pdo->prepare("UPDATE testimonials SET is_featured = NOT is_featured WHERE id = ?");
            $stmt->execute([$testimonialId]);
            
            logUserActivity($userId, 'testimonial_feature', "Toggled featured status for testimonial ID: $testimonialId");
            $message = 'Featured status updated!';
            $messageType = 'success';
        }
        
        if (isset($_POST['delete_testimonial'])) {
            $testimonialId = (int)$_POST['testimonial_id'];
            $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
            $stmt->execute([$testimonialId]);
            
            logUserActivity($userId, 'testimonial_delete', "Deleted testimonial ID: $testimonialId");
            $message = 'Testimonial deleted successfully!';
            $messageType = 'success';
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT t.*, u.first_name, u.last_name, u.email 
        FROM testimonials t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE 1=1";

$params = [];

if ($filterStatus === 'pending') {
    $sql .= " AND t.is_approved = 0";
} elseif ($filterStatus === 'approved') {
    $sql .= " AND t.is_approved = 1";
} elseif ($filterStatus === 'featured') {
    $sql .= " AND t.is_featured = 1";
}

if ($searchQuery) {
    $sql .= " AND (t.customer_name LIKE ? OR t.customer_email LIKE ? OR t.testimonial_text LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = [$searchParam, $searchParam, $searchParam];
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

// Get statistics
$totalTestimonials = $pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
$pendingTestimonials = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 0")->fetchColumn();
$approvedTestimonials = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 1")->fetchColumn();
$featuredTestimonials = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_featured = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials Management - <?php echo APP_NAME; ?></title>
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

        .testimonials-header h1 {
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
          background: white;
          padding: 20px;
          border-radius: 12px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 8px;
          text-align: center;
        }

        .stat-card h3 {
          font-size: 2rem;
          font-weight: 700;
          margin: 10px 0;
          color: rgb(73, 57, 113);
        }

        .stat-card p {
          color: #64748b;
          font-size: 0.9rem;
          margin: 0;
        }

        .stat-card i {
          font-size: 2rem;
          color: rgb(73, 57, 113);
          opacity: 0.7;
        }

        .filters-section {
          background: white;
          padding: 20px;
          border-radius: 12px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 8px;
        }

        .filter-row {
          display: grid;
          grid-template-columns: 2fr 1fr auto;
          gap: 15px;
          align-items: end;
        }

        .filter-group {
          display: flex;
          flex-direction: column;
        }

        .filter-group label {
          font-weight: 600;
          color: #484d53;
          margin-bottom: 8px;
          font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
          padding: 10px 12px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-family: inherit;
          font-size: 0.9rem;
        }

        .filter-group input:focus,
        .filter-group select:focus {
          outline: none;
          border-color: rgb(73, 57, 113);
        }

        .btn {
          padding: 10px 20px;
          font-size: 0.9rem;
          font-weight: 600;
          background: rgb(73, 57, 113);
          color: white;
          border: none;
          border-radius: 10px;
          cursor: pointer;
          transition: all 0.3s ease;
          font-family: inherit;
        }

        .btn:hover {
          background: rgb(93, 77, 133);
          transform: translateY(-2px);
        }

        .btn i {
          margin-right: 5px;
        }

        .btn-sm {
          padding: 6px 12px;
          font-size: 0.85rem;
        }

        .btn-success {
          background: #10b981;
        }

        .btn-success:hover {
          background: #059669;
        }

        .btn-warning {
          background: #f59e0b;
        }

        .btn-warning:hover {
          background: #d97706;
        }

        .btn-danger {
          background: #ef4444;
        }

        .btn-danger:hover {
          background: #dc2626;
        }

        .testimonials-grid {
          display: grid;
          gap: 15px;
        }

        .testimonial-card {
          background: white;
          border-radius: 12px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 8px;
          transition: all 0.3s ease;
        }

        .testimonial-card:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.16) 0px 4px 12px;
        }

        .testimonial-header {
          display: flex;
          justify-content: space-between;
          align-items: start;
          margin-bottom: 15px;
        }

        .testimonial-info h4 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .testimonial-info p {
          color: #94a3b8;
          font-size: 0.85rem;
          margin: 0;
        }

        .testimonial-badges {
          display: flex;
          gap: 8px;
        }

        .badge {
          padding: 6px 12px;
          border-radius: 20px;
          font-size: 0.75rem;
          font-weight: 600;
        }

        .badge-pending {
          background: rgba(251, 191, 36, 0.2);
          color: #f59e0b;
        }

        .badge-approved {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge-featured {
          background: rgba(99, 102, 241, 0.2);
          color: #6366f1;
        }

        .rating-stars {
          margin: 15px 0;
        }

        .rating-stars i {
          color: #fbbf24;
          font-size: 1.2rem;
        }

        .testimonial-text {
          color: #64748b;
          line-height: 1.6;
          margin: 15px 0;
        }

        .testimonial-meta {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding-top: 15px;
          border-top: 1px solid #e2e8f0;
        }

        .testimonial-date {
          color: #94a3b8;
          font-size: 0.85rem;
        }

        .testimonial-actions {
          display: flex;
          gap: 8px;
        }

        .alert {
          padding: 15px;
          margin-bottom: 20px;
          border-radius: 10px;
          font-weight: 600;
        }

        .alert-success {
          background: rgba(16, 185, 129, 0.1);
          border-left: 4px solid #10b981;
          color: #10b981;
        }

        .alert-warning {
          background: rgba(251, 191, 36, 0.1);
          border-left: 4px solid #fbbf24;
          color: #f59e0b;
        }

        .alert-danger {
          background: rgba(239, 68, 68, 0.1);
          border-left: 4px solid #ef4444;
          color: #ef4444;
        }

        .right-content {
          display: grid;
          grid-template-rows: 5% 45%;
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

        .quick-stats {
          background: rgb(214, 227, 248);
          padding: 15px;
          margin: 15px 10px 0;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .quick-stats h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .stats-list {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .stat-item {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 10px;
          background: white;
          border-radius: 10px;
        }

        .stat-item p {
          font-size: 0.9rem;
          font-weight: 600;
          color: #484d53;
        }

        .stat-item span {
          font-size: 1.1rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
        }

        .empty-state {
          text-align: center;
          padding: 60px 20px;
          color: #94a3b8;
        }

        .empty-state i {
          font-size: 4rem;
          margin-bottom: 20px;
          opacity: 0.5;
        }

        .empty-state h3 {
          font-size: 1.5rem;
          margin-bottom: 10px;
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
          .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .content { grid-template-columns: 65% 35%; }
          .filter-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .content { grid-template-columns: 55% 45%; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { grid-template-columns: 100%; }
          .left-content { margin: 0 15px 15px 15px; }
          .right-content { margin: 15px; display: none; }
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

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="testimonials.php">
                        <i class="fa fa-star nav-icon"></i>
                        <span class="nav-text">Testimonials</span>
                        <?php if ($pendingTestimonials > 0): ?>
                        <span class="notification-badge"><?php echo $pendingTestimonials; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="notifications.php">
                        <i class="fa fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                        <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
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
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <div class="testimonials-header">
                    <h1><i class="fas fa-star me-2"></i>Testimonials Management</h1>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-comments"></i>
                        <h3><?php echo $totalTestimonials; ?></h3>
                        <p>Total Testimonials</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <h3><?php echo $pendingTestimonials; ?></h3>
                        <p>Pending Review</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo $approvedTestimonials; ?></h3>
                        <p>Approved</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-star"></i>
                        <h3><?php echo $featuredTestimonials; ?></h3>
                        <p>Featured</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Search</label>
                                <input type="text" name="search" placeholder="Search by name, email, or content..." 
                                       value="<?php echo htmlspecialchars($searchQuery); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="featured" <?php echo $filterStatus === 'featured' ? 'selected' : ''; ?>>Featured</option>
                                </select>
                            </div>
                            <button type="submit" class="btn">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Testimonials List -->
                <div class="testimonials-grid">
                    <?php if (empty($testimonials)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Testimonials Found</h3>
                            <p>There are no testimonials matching your filters.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($testimonials as $testimonial): ?>
                            <div class="testimonial-card">
                                <div class="testimonial-header">
                                    <div class="testimonial-info">
                                        <h4><?php echo htmlspecialchars($testimonial['customer_name']); ?></h4>
                                        <p>
                                            <i class="fas fa-envelope"></i> 
                                            <?php echo htmlspecialchars($testimonial['customer_email']); ?>
                                            <?php if ($testimonial['user_id']): ?>
                                                <span style="margin-left: 10px;">
                                                    <i class="fas fa-user-check" title="Verified User"></i>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="testimonial-badges">
                                        <?php if (!$testimonial['is_approved']): ?>
                                            <span class="badge badge-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-approved">
                                                <i class="fas fa-check"></i> Approved
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($testimonial['is_featured']): ?>
                                            <span class="badge badge-featured">
                                                <i class="fas fa-star"></i> Featured
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="rating-stars">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?php echo $i < $testimonial['rating'] ? '#fbbf24' : '#e5e7eb'; ?>"></i>
                                    <?php endfor; ?>
                                    <span style="margin-left: 10px; color: #64748b;">
                                        (<?php echo $testimonial['rating']; ?>/5)
                                    </span>
                                </div>

                                <div class="testimonial-text">
                                    <?php echo nl2br(htmlspecialchars($testimonial['testimonial_text'])); ?>
                                </div>

                                <div class="testimonial-meta">
                                    <div class="testimonial-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?>
                                    </div>
                                    
                                    <div class="testimonial-actions">
                                        <?php if (!$testimonial['is_approved']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                                <button type="submit" name="approve_testimonial" class="btn btn-sm btn-success" 
                                                        onclick="return confirm('Approve this testimonial?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                                <button type="submit" name="reject_testimonial" class="btn btn-sm btn-warning" 
                                                        onclick="return confirm('Reject this testimonial?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                            <button type="submit" name="toggle_featured" class="btn btn-sm" 
                                                    style="background: <?php echo $testimonial['is_featured'] ? '#6366f1' : '#94a3b8'; ?>">
                                                <i class="fas fa-star"></i> 
                                                <?php echo $testimonial['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                            <button type="submit" name="delete_testimonial" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this testimonial? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-content">
                <div class="user-info">
                    <div class="icon-container">
                        <i class="fa fa-bell"></i>
                        <i class="fa fa-message"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></h4>
                    <?php if (!empty($userData['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" alt="Admin">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($userData['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quick-stats">
                    <h1>Quick Stats</h1>
                    <div class="stats-list">
                        <div class="stat-item">
                            <p>Total</p>
                            <span><?php echo $totalTestimonials; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Pending</p>
                            <span style="color: #f59e0b;"><?php echo $pendingTestimonials; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Approved</p>
                            <span style="color: #10b981;"><?php echo $approvedTestimonials; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Featured</p>
                            <span style="color: #6366f1;"><?php echo $featuredTestimonials; ?></span>
                        </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'testimonials.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });

            // Auto-dismiss alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);

            // Animate testimonial cards on load
            const cards = document.querySelectorAll('.testimonial-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Confirmation dialogs with better UX
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button && button.classList.contains('btn-danger')) {
                    if (!confirm('This action cannot be undone. Are you sure?')) {
                        e.preventDefault();
                    }
                }
            });
        });

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