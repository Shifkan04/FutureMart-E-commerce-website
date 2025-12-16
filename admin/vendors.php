<?php
// vendors.php - Vendors Management with Fitness App Design
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
  header('Location: ../login.php');
  exit();
}

checkSessionTimeout();
$adminId = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  header('Content-Type: application/json');

  // Approve Vendor
  if ($_POST['action'] === 'approve_vendor') {
    try {
      $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'vendor'");
      $result = $stmt->execute([$_POST['vendor_id']]);

      if ($result) {
        logUserActivity($adminId, 'vendor_approve', "Approved vendor ID: {$_POST['vendor_id']}");
        echo json_encode(['success' => true, 'message' => 'Vendor approved successfully!']);
      }
    } catch (PDOException $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
  }

  // Reject/Suspend Vendor
  if ($_POST['action'] === 'reject_vendor') {
    try {
      $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'vendor'");
      $result = $stmt->execute([$_POST['vendor_id']]);

      if ($result) {
        logUserActivity($adminId, 'vendor_reject', "Rejected vendor ID: {$_POST['vendor_id']}");
        echo json_encode(['success' => true, 'message' => 'Vendor rejected!']);
      }
    } catch (PDOException $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
  }

  // Update Vendor
  if ($_POST['action'] === 'update_vendor') {
    try {
      $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ? AND role = 'vendor'
            ");

      $nameParts = explode(' ', $_POST['name'], 2);
      $firstName = $nameParts[0];
      $lastName = $nameParts[1] ?? '';

      $result = $stmt->execute([
        $firstName,
        $lastName,
        $_POST['email'],
        $_POST['phone'],
        $_POST['status'],
        $_POST['vendor_id']
      ]);

      if ($result) {
        logUserActivity($adminId, 'vendor_update', "Updated vendor ID: {$_POST['vendor_id']}");
        echo json_encode(['success' => true, 'message' => 'Vendor updated successfully!']);
      }
    } catch (PDOException $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
  }

  // Bulk Approve
  if ($_POST['action'] === 'bulk_approve') {
    try {
      $vendorIds = json_decode($_POST['vendor_ids'], true);
      $placeholders = str_repeat('?,', count($vendorIds) - 1) . '?';

      $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders) AND role = 'vendor'");
      $result = $stmt->execute($vendorIds);

      if ($result) {
        logUserActivity($adminId, 'vendor_bulk_approve', "Bulk approved " . count($vendorIds) . " vendors");
        echo json_encode(['success' => true, 'message' => count($vendorIds) . ' vendors approved!']);
      }
    } catch (PDOException $e) {
      echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
  }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Build query
$whereConditions = ["u.role = 'vendor'"];
$params = [];

if (!empty($statusFilter)) {
  $whereConditions[] = "u.status = ?";
  $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
  $whereConditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
  $searchParam = "%$searchQuery%";
  $params[] = $searchParam;
  $params[] = $searchParam;
  $params[] = $searchParam;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get vendor statistics
$stats = [
  'active' => 0,
  'inactive' => 0,
  'pending' => 0
];

$statsQuery = "SELECT status, COUNT(*) as count FROM users WHERE role = 'vendor' GROUP BY status";
$statsStmt = $pdo->query($statsQuery);
while ($row = $statsStmt->fetch()) {
  $stats[$row['status']] = $row['count'];
}

$pendingCount = $stats['inactive'];

// Get total revenue
$revenueStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) * 0.1 as revenue FROM orders WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(NOW())");
$totalRevenue = $revenueStmt->fetch()['revenue'];

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users u $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalVendors = $stmt->fetch()['total'];
$totalPages = ceil($totalVendors / $itemsPerPage);

// Get vendors with product count
$query = "
    SELECT u.*,
       CONCAT(u.first_name, ' ', u.last_name) AS vendor_name,
       COUNT(DISTINCT p.id) AS product_count
    FROM users u
    LEFT JOIN products p ON p.brand = CONCAT(u.first_name, ' ', u.last_name) 
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $itemsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

// Get admin details
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Get notification counts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$pendingOrders = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1");
$lowStockProducts = $stmt->fetch()['count'];

// Helper functions
function getStatusBadge($status)
{
  $badges = [
    'active' => 'success',
    'inactive' => 'warning',
  ];
  $text = $status === 'inactive' ? 'Pending' : ucfirst($status);
  $class = $badges[$status] ?? 'secondary';
  return "<span class='badge badge-$class'>$text</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendors Management - <?php echo APP_NAME; ?></title>
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
      background: #f6f7fb;
      padding: 20px;
      border-radius: 0 15px 15px 0;
      overflow-y: auto;
      max-height: calc(100vh - 80px);
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .header h1 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #484d53;
    }

    .user-section {
      display: flex;
      align-items: center;
      gap: 15px;
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

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 0.9rem;
      text-decoration: none;
      display: inline-block;
    }

    .btn-primary {
      background: rgb(73, 57, 113);
      color: white;
    }

    .btn-primary:hover {
      background: rgb(93, 77, 133);
      transform: translateY(-2px);
    }

    .btn-success {
      background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%);
      color: #484d53;
    }

    .btn-danger {
      background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%);
      color: #484d53;
    }

    .btn-warning {
      background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%);
      color: #484d53;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 0.85rem;
    }

    .filter-section {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
      margin-bottom: 20px;
    }

    .filter-section .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      color: #484d53;
      margin-bottom: 5px;
      font-size: 0.9rem;
    }

    .form-control {
      padding: 10px;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      font-size: 0.9rem;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: rgb(124, 136, 224);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
    }

    .stat-card.active {
      background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%);
    }

    .stat-card.pending {
      background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%);
    }

    .stat-card.total {
      background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
    }

    .stat-card.revenue {
      background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%);
    }

    .stat-card h6 {
      font-size: 0.9rem;
      font-weight: 600;
      color: #484d53;
      margin-bottom: 5px;
    }

    .stat-card h3 {
      font-size: 2rem;
      font-weight: 700;
      color: #484d53;
      margin: 5px 0;
    }

    .stat-card small {
      font-size: 0.85rem;
      color: #484d53;
    }

    .vendors-section {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
    }

    .vendors-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .vendors-header h2 {
      font-size: 1.3rem;
      font-weight: 700;
      color: #484d53;
    }

    .vendors-table {
      width: 100%;
      border-collapse: collapse;
    }

    .vendors-table th {
      text-align: left;
      padding: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      color: #484d53;
      border-bottom: 2px solid #f6f7fb;
      background: #f6f7fb;
    }

    .vendors-table td {
      padding: 12px;
      font-size: 0.9rem;
      color: #484d53;
      border-bottom: 1px solid #f6f7fb;
      vertical-align: middle;
    }

    .vendors-table tr:hover {
      background: #f6f7fb;
    }

    .vendor-logo {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: white;
      font-size: 1.1rem;
    }

    .badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .badge-success {
      background: rgba(16, 185, 129, 0.2);
      color: #10b981;
    }

    .badge-warning {
      background: rgba(245, 158, 11, 0.2);
      color: #f59e0b;
    }

    .badge-primary {
      background: rgba(124, 136, 224, 0.2);
      color: rgb(73, 57, 113);
    }

    .pagination {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
    }

    .pagination button {
      padding: 8px 15px;
      border: none;
      background: #f6f7fb;
      color: #484d53;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .pagination button.active {
      background: rgb(124, 136, 224);
      color: white;
    }

    .pagination button:hover:not(.active) {
      background: #e5e7eb;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }

    .modal.show {
      display: flex;
    }

    .modal-content {
      background: white;
      padding: 30px;
      border-radius: 15px;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .modal-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #484d53;
    }

    .close {
      font-size: 2rem;
      font-weight: 300;
      color: #484d53;
      cursor: pointer;
      border: none;
      background: none;
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
      }

      .nav-text {
        display: none;
      }
    }

    @media (max-width: 910px) {
      main {
        grid-template-columns: 10% 90%;
        margin: 20px;
      }
    }

    @media (max-width: 700px) {
      main {
        grid-template-columns: 15% 85%;
      }

      .vendors-table {
        font-size: 0.8rem;
      }

      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
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

        <li class="nav-item active">
          <b></b>
          <b></b>
          <a href="vendors.php">
            <i class="fa fa-users-cog nav-icon"></i>
            <span class="nav-text">Vendors</span>
            <?php if ($pendingCount > 0): ?>
              <span class="notification-badge"><?php echo $pendingCount; ?></span>
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

        <li class="nav-item"><b></b><b></b><a href="notifications.php"><i class="fa fa-bell nav-icon"></i><span class="nav-text">Notifications</span></a></li>
        <li class="nav-item"><b></b><b></b><a href="contact.php"><i class="fa fa-envelope nav-icon"></i><span class="nav-text">Contact</span></a></li>

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

    <div class="content">
      <div class="header">
        <h1><i class="fas fa-users-cog"></i> Vendors Management</h1>
        <div class="user-section">
          <?php if ($pendingCount > 0): ?>
            <a href="?status=inactive" class="btn btn-success">
              <i class="fas fa-check-circle"></i> Approve (<?php echo $pendingCount; ?>)
            </a>
          <?php endif; ?>
          <button class="btn btn-primary" onclick="exportVendors()">
            <i class="fas fa-download"></i> Export
          </button>
          <?php if (!empty($admin['avatar'])): ?>
            <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
          <?php else: ?>
            <div class="user-avatar">
              <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="filter-section">
        <form method="GET" action="vendors.php">
          <div class="form-row">
            <div class="form-group">
              <label for="status">Status</label>
              <select class="form-control" name="status" id="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Pending Approval</option>
              </select>
            </div>
            <div class="form-group">
              <label for="search">Search</label>
              <input type="text" class="form-control" name="search" id="search"
                value="<?php echo htmlspecialchars($searchQuery); ?>"
                placeholder="Search vendors...">
            </div>
            <div class="form-group" style="justify-content: flex-end;">
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card active">
          <h6>Active Vendors</h6>
          <h3><?php echo $stats['active']; ?></h3>
          <small>Currently selling products</small>
        </div>
        <div class="stat-card pending">
          <h6>Pending Approval</h6>
          <h3><?php echo $pendingCount; ?></h3>
          <small>Awaiting admin approval</small>
        </div>
        <div class="stat-card total">
          <h6>Total Vendors</h6>
          <h3><?php echo array_sum($stats); ?></h3>
          <small>All registered vendors</small>
        </div>
        <div class="stat-card revenue">
          <h6>Revenue Share</h6>
          <h3>$<?php echo number_format($totalRevenue, 2); ?></h3>
          <small>This month's commission</small>
        </div>
      </div>

      <!-- Vendors Table -->
      <div class="vendors-section">
        <div class="vendors-header">
          <h2>Vendor Directory (<?php echo number_format($totalVendors); ?> total)</h2>
          <button class="btn btn-primary btn-sm" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>

        <div style="overflow-x: auto;">
          <table class="vendors-table">
            <thead>
              <tr>
                <th><input type="checkbox" id="selectAllVendors"></th>
                <th>Vendor</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Products</th>
                <th>Joined Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($vendors)): ?>
                <tr>
                  <td colspan="8" style="text-align: center; padding: 40px;">
                    <i class="fas fa-users-cog" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                    <span style="opacity: 0.6;">No vendors found</span>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($vendors as $vendor): ?>
                  <tr>
                    <td><input type="checkbox" class="vendor-checkbox" value="<?php echo $vendor['id']; ?>"></td>
                    <td>
                      <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="vendor-logo">
                          <?php echo strtoupper(substr($vendor['first_name'], 0, 1) . substr($vendor['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                          <strong><?php echo htmlspecialchars($vendor['vendor_name']); ?></strong><br>
                          <small style="color: #999;">ID: <?php echo $vendor['id']; ?></small>
                        </div>
                      </div>
                    </td>
                    <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                    <td><?php echo htmlspecialchars($vendor['phone'] ?? 'N/A'); ?></td>
                    <td>
                      <span class="badge badge-primary"><?php echo $vendor['product_count']; ?></span><br>
                      <small style="color: #999;">Products</small>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($vendor['created_at'])); ?></td>
                    <td><?php echo getStatusBadge($vendor['status']); ?></td>
                    <td>
                      <?php if ($vendor['status'] === 'inactive'): ?>
                        <button class="btn btn-success btn-sm" onclick="approveVendor(<?php echo $vendor['id']; ?>)" style="margin-bottom: 5px;">
                          <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="rejectVendor(<?php echo $vendor['id']; ?>)">
                          <i class="fas fa-times"></i>
                        </button>
                      <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="viewVendor(<?php echo $vendor['id']; ?>)" style="margin-bottom: 5px;">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="editVendor(<?php echo $vendor['id']; ?>)">
                          <i class="fas fa-edit"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <button onclick="window.location.href='?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo $searchQuery; ?>'">
                &laquo; Previous
              </button>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <button class="<?php echo $page == $i ? 'active' : ''; ?>"
                onclick="window.location.href='?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo $searchQuery; ?>'">
                <?php echo $i; ?>
              </button>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <button onclick="window.location.href='?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo $searchQuery; ?>'">
                Next &raquo;
              </button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Edit Vendor Modal -->
  <div class="modal" id="editVendorModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-edit"></i> Edit Vendor</h2>
        <button class="close" onclick="closeModal('editVendorModal')">&times;</button>
      </div>
      <form id="editVendorForm">
        <input type="hidden" id="editVendorId">
        <div class="form-group">
          <label>Vendor Name *</label>
          <input type="text" class="form-control" id="editVendorName" required>
        </div>
        <div class="form-group" style="margin-top: 15px;">
          <label>Email *</label>
          <input type="email" class="form-control" id="editVendorEmail" required>
        </div>
        <div class="form-group" style="margin-top: 15px;">
          <label>Phone *</label>
          <input type="tel" class="form-control" id="editVendorPhone" required>
        </div>
        <div class="form-group" style="margin-top: 15px;">
          <label>Status *</label>
          <select class="form-control" id="editVendorStatus" required>
            <option value="active">Active</option>
            <option value="inactive">Pending</option>
          </select>
        </div>
        <div style="display: flex; gap: 10px; margin-top: 20px;">
          <button type="button" class="btn btn-primary" onclick="updateVendor()" style="flex: 1;">Update</button>
          <button type="button" class="btn" onclick="closeModal('editVendorModal')" style="flex: 1; background: #e5e7eb; color: #484d53;">Cancel</button>
        </div>
      </form>
    </div>
  </div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // Navigation active state
    const navItems = document.querySelectorAll(".nav-item");
    navItems.forEach((navItem) => {
      navItem.addEventListener("click", () => {
        navItems.forEach((item) => {
          item.classList.remove("active");
        });
        navItem.classList.add("active");
      });
    });

    // Modal functions
    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('show');
    }

    // Select all functionality
    document.getElementById('selectAllVendors').addEventListener('change', function() {
      document.querySelectorAll('.vendor-checkbox').forEach(cb => {
        cb.checked = this.checked;
      });
    });

    function approveVendor(vendorId) {
      if (!confirm('Approve this vendor?')) return;

      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('action', 'approve_vendor');
      formData.append('vendor_id', vendorId);

      fetch('vendors.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert(data.message);
          }
        });
    }

    function rejectVendor(vendorId) {
      if (!confirm('Reject this vendor?')) return;

      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('action', 'reject_vendor');
      formData.append('vendor_id', vendorId);

      fetch('vendors.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert(data.message);
          }
        });
    }

    function viewVendor(vendorId) {
      window.location.href = 'vendor_details.php?id=' + vendorId;
    }

    function editVendor(vendorId) {
      fetch('get_vendor.php?id=' + vendorId)
        .then(response => response.json())
        .then(vendor => {
          document.getElementById('editVendorId').value = vendor.id;
          document.getElementById('editVendorName').value = vendor.first_name + ' ' + vendor.last_name;
          document.getElementById('editVendorEmail').value = vendor.email;
          document.getElementById('editVendorPhone').value = vendor.phone || '';
          document.getElementById('editVendorStatus').value = vendor.status;

          document.getElementById('editVendorModal').classList.add('show');
        })
        .catch(error => {
          alert('Error loading vendor: ' + error);
        });
    }

    function updateVendor() {
      const form = document.getElementById('editVendorForm');
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('action', 'update_vendor');
      formData.append('vendor_id', document.getElementById('editVendorId').value);
      formData.append('name', document.getElementById('editVendorName').value);
      formData.append('email', document.getElementById('editVendorEmail').value);
      formData.append('phone', document.getElementById('editVendorPhone').value);
      formData.append('status', document.getElementById('editVendorStatus').value);

      fetch('vendors.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert(data.message);
          }
        });
    }

    function exportVendors() {
      window.location.href = 'export_vendors.php';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('editVendorModal');
      if (event.target == modal) {
        modal.classList.remove('show');
      }
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