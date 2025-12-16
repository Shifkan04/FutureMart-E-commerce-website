<?php
require_once '../config.php';

// Check if vendor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: login.php');
    exit();
}

$vendorId = $_SESSION['user_id'];

// Fetch vendor details
$stmt = $pdo->prepare("
    SELECT u.*, vp.company_name, vp.rating
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

$vendorBrand = $vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Update Stock
    if ($_POST['action'] === 'update_stock') {
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = ?, updated_at = NOW()
                WHERE id = ? AND brand = ?
            ");
            $stmt->execute([$_POST['quantity'], $_POST['product_id'], $vendorBrand]);
            
            logUserActivity($vendorId, 'inventory_update', 'Updated stock for product ID: ' . $_POST['product_id']);
            
            echo json_encode(['success' => true, 'message' => 'Stock updated successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating stock: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Export Inventory
    if ($_POST['action'] === 'export') {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.brand = ?
                ORDER BY p.name
            ");
            $stmt->execute([$vendorBrand]);
            $products = $stmt->fetchAll();
            
            logUserActivity($vendorId, 'inventory_export', 'Exported inventory data');
            
            echo json_encode(['success' => true, 'data' => $products]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Filters
$searchTerm = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query
$whereClause = "WHERE p.brand = ?";
$params = [$vendorBrand];

if (!empty($searchTerm)) {
    $whereClause .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($categoryFilter)) {
    $whereClause .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    switch ($statusFilter) {
        case 'in_stock':
            $whereClause .= " AND p.stock_quantity > p.min_stock_level";
            break;
        case 'low_stock':
            $whereClause .= " AND p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level";
            break;
        case 'out_of_stock':
            $whereClause .= " AND p.stock_quantity = 0";
            break;
    }
}

// Get inventory statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock_quantity > min_stock_level THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        COUNT(DISTINCT category_id) as total_categories
    FROM products
    WHERE brand = ?
");
$statsStmt->execute([$vendorBrand]);
$stats = $statsStmt->fetch();

// Count total filtered products
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM products p
    $whereClause
");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $itemsPerPage);

// Fetch products
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereClause
    ORDER BY p.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories
$categoriesStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $categoriesStmt->fetchAll();

// Unread notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread
    FROM admin_messages
    WHERE recipient_id = ? AND recipient_type = 'vendor' AND is_read = 0
");
$stmt->execute([$vendorId]);
$unreadNotifications = $stmt->fetch()['unread'];

// Stock status function
function getStockStatus($stock, $minStock) {
    if ($stock == 0) {
        return ['badge' => 'danger', 'text' => 'Out of Stock'];
    } elseif ($stock <= $minStock) {
        return ['badge' => 'warning', 'text' => 'Low Stock'];
    } else {
        return ['badge' => 'success', 'text' => 'In Stock'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - <?php echo APP_NAME; ?></title>
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
          display: grid;
          grid-template-rows: 35% 70%;
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
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
        }

        .stat-card {
          background-color: rgba(185, 159, 237, 0.6);
          padding: 20px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
        }

        .stat-card:nth-child(2) {
          background-color: rgba(238, 184, 114, 0.6);
        }

        .stat-card:nth-child(3) {
          background-color: rgba(184, 224, 192, 0.6);
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

        .stat-card small {
          font-size: 0.85rem;
          font-weight: 600;
          color: #484d53;
        }

        .inventory-section {
          margin-top: 20px;
        }

        .inventory-section h1 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 15px;
        }

        .filter-container {
          background: white;
          border-radius: 15px;
          padding: 15px;
          margin-bottom: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .filter-row {
          display: grid;
          grid-template-columns: 2fr 1fr 1fr 0.8fr 0.8fr;
          gap: 10px;
          align-items: center;
        }

        .filter-input {
          padding: 10px 15px;
          border: 1px solid #e2e8f0;
          border-radius: 10px;
          font-size: 0.9rem;
          font-family: "Nunito", sans-serif;
          transition: all 0.3s ease;
        }

        .filter-input:focus {
          outline: none;
          border-color: rgb(73, 57, 113);
          box-shadow: 0 0 0 3px rgba(73, 57, 113, 0.1);
        }

        .btn {
          display: inline-block;
          padding: 10px 20px;
          font-size: 0.9rem;
          font-weight: 600;
          outline: none;
          text-decoration: none;
          color: #484b57;
          background: rgba(255, 255, 255, 0.9);
          box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.3);
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          text-align: center;
        }

        .btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 35px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
          background: rgb(73, 57, 113);
          color: white;
          border: none;
        }

        .btn-secondary {
          background: #e2e8f0;
          color: #484d53;
        }

        .inventory-table-container {
          background: white;
          border-radius: 15px;
          padding: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
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
          font-size: 0.9rem;
          color: #484d53;
          border-bottom: 2px solid #f6f7fb;
        }

        table td {
          padding: 12px;
          font-size: 0.9rem;
          color: #484d53;
          border-bottom: 1px solid #f6f7fb;
        }

        table tr:hover {
          background: #f6f7fb;
        }

        .product-info {
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .product-img {
          width: 50px;
          height: 50px;
          border-radius: 10px;
          object-fit: cover;
        }

        .product-name {
          font-weight: 600;
          margin-bottom: 3px;
        }

        .product-desc {
          font-size: 0.8rem;
          color: #64748b;
        }

        .badge {
          display: inline-block;
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.8rem;
          font-weight: 600;
        }

        .badge.success {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge.warning {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .badge.danger {
          background: rgba(239, 68, 68, 0.2);
          color: #ef4444;
        }

        .stock-input {
          width: 80px;
          padding: 6px 10px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          font-size: 0.9rem;
          font-weight: 600;
          text-align: center;
          transition: all 0.3s ease;
        }

        .stock-input:focus {
          outline: none;
          border-color: rgb(73, 57, 113);
        }

        .action-btn {
          padding: 6px 12px;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 0.9rem;
        }

        .action-btn:hover {
          transform: translateY(-2px);
        }

        .action-btn.edit {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .action-btn.history {
          background: rgba(168, 85, 247, 0.2);
          color: #a855f7;
        }

        .pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 5px;
          margin-top: 20px;
        }

        .pagination a,
        .pagination span {
          padding: 8px 12px;
          border-radius: 8px;
          text-decoration: none;
          color: #484d53;
          font-weight: 600;
          transition: all 0.3s ease;
        }

        .pagination a:hover {
          background: rgb(73, 57, 113);
          color: white;
        }

        .pagination .active {
          background: rgb(73, 57, 113);
          color: white;
        }

        .pagination .disabled {
          opacity: 0.5;
          pointer-events: none;
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

        .quick-stats {
          display: flex;
          flex-direction: column;
          align-items: center;
          background-color: rgb(214, 227, 248);
          padding: 15px 10px;
          margin: 15px 10px 0;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .quick-stats h1 {
          margin-top: 10px;
          font-size: 1.2rem;
          margin-bottom: 15px;
        }

        .stats-list {
          width: 100%;
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

        .quick-actions {
          padding: 15px 10px;
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

        .empty-state {
          text-align: center;
          padding: 40px;
          color: #64748b;
        }

        .empty-state i {
          font-size: 48px;
          opacity: 0.3;
          display: block;
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

          .filter-row {
            grid-template-columns: 1fr 1fr;
          }
        }

        @media (max-width: 910px) {
          main {
            grid-template-columns: 10% 90%;
            margin: 20px;
          }

          .content {
            grid-template-columns: 55% 45%;
          }
        }

        @media (max-width: 700px) {
          main {
            grid-template-columns: 15% 85%;
          }

          .content {
            grid-template-columns: 100%;
            grid-template-rows: 45% 55%;
          }

          .left-content {
            margin: 0 15px 15px 15px;
          }

          .right-content {
            margin: 15px;
          }

          .stats-grid {
            grid-template-columns: 1fr;
          }

          .filter-row {
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

          <li class="nav-item active">
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

          <li class="nav-item">
            <b></b>
            <b></b>
            <a href="notifications.php">
              <i class="fa fa-bell nav-icon"></i>
              <span class="nav-text">Notifications</span>
              <?php if ($unreadNotifications > 0): ?>
              <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
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
            <h1>Inventory Overview</h1>
            <div class="stats-grid">
              <div class="stat-card">
                <i class="fas fa-box"></i>
                <div>
                  <h3><?php echo number_format($stats['total_products']); ?></h3>
                  <p>Total Products</p>
                  <small><i class="fas fa-info-circle"></i> Active listings</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                  <h3><?php echo number_format($stats['low_stock']); ?></h3>
                  <p>Low Stock</p>
                  <small><i class="fas fa-warehouse"></i> Needs restock</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-times-circle"></i>
                <div>
                  <h3><?php echo number_format($stats['out_of_stock']); ?></h3>
                  <p>Out of Stock</p>
                  <small><i class="fas fa-ban"></i> Unavailable</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-tags"></i>
                <div>
                  <h3><?php echo number_format($stats['total_categories']); ?></h3>
                  <p>Categories</p>
                  <small><i class="fas fa-list"></i> Product types</small>
                </div>
              </div>
            </div>
          </div>

          <div class="inventory-section">
            <h1>Manage Inventory (<?php echo $totalProducts; ?> items)</h1>
            
            <div class="filter-container">
              <form method="GET" action="inventory.php">
                <div class="filter-row">
                  <input type="text" class="filter-input" name="search" 
                         placeholder="🔍 Search products or SKU..." 
                         value="<?php echo htmlspecialchars($searchTerm); ?>">
                  
                  <select class="filter-input" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" 
                            <?php echo ($categoryFilter == $cat['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  
                  <select class="filter-input" name="status">
                    <option value="">All Status</option>
                    <option value="in_stock" <?php echo ($statusFilter === 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?php echo ($statusFilter === 'low_stock') ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?php echo ($statusFilter === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                  </select>
                  
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                  </button>
                  
                  <a href="inventory.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                  </a>
                </div>
              </form>
            </div>

            <div class="inventory-table-container">
              <?php if (empty($products)): ?>
              <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No products found</p>
              </div>
              <?php else: ?>
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Min Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($products as $product): ?>
                  <?php $stockStatus = getStockStatus($product['stock_quantity'], $product['min_stock_level']); ?>
                  <tr>
                    <td>
                      <div class="product-info">
                        <?php if ($product['image']): ?>
                        <img src="<?php echo htmlspecialchars(UPLOAD_URL . $product['image']); ?>" 
                             class="product-img" alt="Product">
                        <?php else: ?>
                        <img src="https://via.placeholder.com/50" class="product-img" alt="Product">
                        <?php endif; ?>
                        <div>
                          <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                          <div class="product-desc"><?php echo htmlspecialchars(substr($product['short_description'] ?? '', 0, 30)); ?><?php echo strlen($product['short_description'] ?? '') > 30 ? '...' : ''; ?></div>
                        </div>
                      </div>
                    </td>
                    <td><strong><?php echo htmlspecialchars($product['sku']); ?></strong></td>
                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                    <td><strong>$<?php echo number_format($product['price'], 2); ?></strong></td>
                    <td>
                      <input type="number" class="stock-input" 
                             value="<?php echo $product['stock_quantity']; ?>"
                             min="0"
                             data-product-id="<?php echo $product['id']; ?>"
                             onchange="updateStock(this)">
                    </td>
                    <td><?php echo $product['min_stock_level']; ?></td>
                    <td>
                      <span class="badge <?php echo $stockStatus['badge']; ?>">
                        <?php echo $stockStatus['text']; ?>
                      </span>
                    </td>
                    <td>
                      <button class="action-btn edit" 
                              onclick="window.location.href='products.php?id=<?php echo $product['id']; ?>'"
                              title="Edit Product">
                        <i class="fas fa-edit"></i>
                      </button>
                      <br><br>
                      <button class="action-btn history" 
                              onclick="showHistory(<?php echo $product['id']; ?>)"
                              title="View History">
                        <i class="fas fa-history"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <?php if ($totalPages > 1): ?>
              <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo $categoryFilter; ?>&status=<?php echo $statusFilter; ?>">
                  <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                  <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo $categoryFilter; ?>&status=<?php echo $statusFilter; ?>" 
                     class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                  </a>
                  <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                  <span>...</span>
                  <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&category=<?php echo $categoryFilter; ?>&status=<?php echo $statusFilter; ?>">
                  Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
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

          <div class="quick-stats">
            <h1>Quick Statistics</h1>
            <div class="stats-list">
              <div class="stat-item">
                <p>Total Products</p>
                <span><?php echo $stats['total_products']; ?></span>
              </div>
              <div class="stat-item">
                <p>In Stock</p>
                <span><?php echo $stats['in_stock']; ?></span>
              </div>
              <div class="stat-item">
                <p>Low Stock</p>
                <span><?php echo $stats['low_stock']; ?></span>
              </div>
              <div class="stat-item">
                <p>Out of Stock</p>
                <span><?php echo $stats['out_of_stock']; ?></span>
              </div>
            </div>
          </div>

          <div class="quick-actions">
            <h1>Quick Actions</h1>
            <div class="action-list">
              <a href="products.php?action=add" class="action-item">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Product</span>
              </a>
              <a href="?status=low_stock" class="action-item">
                <i class="fas fa-exclamation-triangle"></i>
                <span>View Low Stock</span>
              </a>
              <a href="?status=out_of_stock" class="action-item">
                <i class="fas fa-times-circle"></i>
                <span>View Out of Stock</span>
              </a>
              <button onclick="exportInventory()" class="action-item" style="border: none; cursor: pointer; width: 100%;">
                <i class="fas fa-download"></i>
                <span>Export to CSV</span>
              </button>
              <a href="analytics.php" class="action-item">
                <i class="fas fa-chart-line"></i>
                <span>View Analytics</span>
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
            const currentPage = window.location.pathname.split('/').pop() || 'inventory.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });
        });

        // Update Stock
        function updateStock(input) {
            const productId = input.dataset.productId;
            const quantity = input.value;
            
            if (quantity < 0) {
                alert('Stock quantity cannot be negative');
                input.value = 0;
                return;
            }
            
            fetch('inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=1&action=update_stock&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success feedback
                    input.style.borderColor = '#10b981';
                    input.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                    
                    setTimeout(() => {
                        input.style.borderColor = '';
                        input.style.boxShadow = '';
                    }, 2000);
                    
                    // Reload page to update stats
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('Error updating stock');
                console.error('Error:', error);
            });
        }
        
        // Show History (placeholder)
        function showHistory(productId) {
            alert('Stock history feature coming soon!\nProduct ID: ' + productId);
        }
        
        // Export Inventory to CSV
        function exportInventory() {
            fetch('inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=export'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Convert data to CSV
                    const products = data.data;
                    let csv = 'Product Name,SKU,Category,Price,Stock Quantity,Min Stock Level,Status\n';
                    
                    products.forEach(product => {
                        const status = product.stock_quantity == 0 ? 'Out of Stock' : 
                                     product.stock_quantity <= product.min_stock_level ? 'Low Stock' : 'In Stock';
                        
                        csv += `"${product.name}","${product.sku}","${product.category_name || 'N/A'}",`;
                        csv += `"${product.price}","${product.stock_quantity}","${product.min_stock_level}","${status}"\n`;
                    });
                    
                    // Download CSV file
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'inventory_' + new Date().toISOString().split('T')[0] + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    alert('Inventory exported successfully!');
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('Error exporting inventory');
                console.error('Error:', error);
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