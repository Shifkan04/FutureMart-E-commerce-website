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

// Get vendor brand name
$vendorBrand = $vendor['company_name'] ?? ($vendor['first_name'] . ' ' . $vendor['last_name']);

// Date range filter
$dateRange = $_GET['range'] ?? '30';
$dateFilter = '';
$previousDateFilter = '';

switch ($dateRange) {
    case '7':
        $dateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $previousDateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30':
        $dateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $previousDateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90':
        $dateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $previousDateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case '365':
        $dateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
        $previousDateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 730 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)";
        break;
    default:
        $dateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $previousDateFilter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Current period metrics
$metricsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.total_price), 0) as total_revenue,
        COALESCE(AVG(oi.total_price), 0) as avg_order_value
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ? $dateFilter
");
$metricsStmt->execute([$vendorBrand]);
$currentMetrics = $metricsStmt->fetch();

// Previous period metrics
$previousMetricsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.total_price), 0) as total_revenue,
        COALESCE(AVG(oi.total_price), 0) as avg_order_value
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ? $previousDateFilter
"); 
$previousMetricsStmt->execute([$vendorBrand]);
$previousMetrics = $previousMetricsStmt->fetch();

// Calculate percentage changes
function calculateChange($current, $previous) {
    if ($previous == 0) return 0;
    return (($current - $previous) / $previous) * 100;
}

$revenueChange = calculateChange($currentMetrics['total_revenue'], $previousMetrics['total_revenue']);
$ordersChange = calculateChange($currentMetrics['total_orders'], $previousMetrics['total_orders']);
$avgOrderChange = calculateChange($currentMetrics['avg_order_value'], $previousMetrics['avg_order_value']);

// Top performing products
$topProductsStmt = $pdo->prepare("
    SELECT p.name, p.price,
           COUNT(DISTINCT o.id) as order_count,
           SUM(oi.quantity) as total_sold,
           SUM(oi.total_price) as total_revenue
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE p.brand = ? $dateFilter
    GROUP BY p.id, p.name, p.price
    ORDER BY total_revenue DESC
    LIMIT 5
");
$topProductsStmt->execute([$vendorBrand]);
$topProducts = $topProductsStmt->fetchAll();

// Find max revenue for percentage calculation
$maxRevenue = !empty($topProducts) ? $topProducts[0]['total_revenue'] : 1;

// Sales by category
$categoryStmt = $pdo->prepare("
    SELECT c.name as category_name,
           COUNT(DISTINCT o.id) as order_count,
           SUM(oi.total_price) as total_revenue
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN orders o ON oi.order_id = o.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.brand = ? $dateFilter
    GROUP BY c.id, c.name
    ORDER BY total_revenue DESC
    LIMIT 5
");
$categoryStmt->execute([$vendorBrand]);
$categories = $categoryStmt->fetchAll();

// Daily performance data (last 7 days)
$dailyStmt = $pdo->prepare("
    SELECT 
        DATE(o.created_at) as date,
        COUNT(DISTINCT o.id) as orders,
        COALESCE(SUM(oi.total_price), 0) as revenue,
        COUNT(DISTINCT o.user_id) as customers
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ?
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY date DESC
");
$dailyStmt->execute([$vendorBrand]);
$dailyData = $dailyStmt->fetchAll();

// Customer metrics
$customerStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.user_id) as unique_customers
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ? $dateFilter
");
$customerStmt->execute([$vendorBrand]);
$customerMetrics = $customerStmt->fetch();

// Unread notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread
    FROM admin_messages
    WHERE recipient_id = ? AND recipient_type = 'vendor' AND is_read = 0
");
$stmt->execute([$vendorId]);
$unreadNotifications = $stmt->fetch()['unread'];

// Handle Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Analytics Report - ' . $vendorBrand]);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: Last ' . $dateRange . ' days']);
    fputcsv($output, []);
    
    fputcsv($output, ['Key Metrics']);
    fputcsv($output, ['Metric', 'Value', 'Change']);
    fputcsv($output, ['Total Revenue', '$' . number_format($currentMetrics['total_revenue'], 2), number_format($revenueChange, 1) . '%']);
    fputcsv($output, ['Total Orders', $currentMetrics['total_orders'], number_format($ordersChange, 1) . '%']);
    fputcsv($output, ['Average Order Value', '$' . number_format($currentMetrics['avg_order_value'], 2), number_format($avgOrderChange, 1) . '%']);
    fputcsv($output, []);
    
    fputcsv($output, ['Top Products']);
    fputcsv($output, ['Product Name', 'Orders', 'Units Sold', 'Revenue']);
    foreach ($topProducts as $product) {
        fputcsv($output, [
            $product['name'],
            $product['order_count'],
            $product['total_sold'],
            '$' . number_format($product['total_revenue'], 2)
        ]);
    }
    
    logUserActivity($vendorId, 'analytics_export', 'Exported analytics report');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo APP_NAME; ?></title>
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
          grid-template-rows: 30% 42%;
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

        .stat-card small {
          font-size: 0.85rem;
          font-weight: 600;
          color: #484d53;
        }

        .trend-up {
          color: #10b981;
        }

        .trend-down {
          color: #ef4444;
        }

        .analytics-section {
          margin-top: 20px;
        }

        .analytics-section h1 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 15px;
        }

        .analytics-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 15px;
        }

        .analytics-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .analytics-card h2 {
          font-size: 1.1rem;
          font-weight: 700;
          margin-bottom: 15px;
          color: #484d53;
        }

        .product-item {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
        }

        .product-info h6 {
          margin: 0;
          font-size: 0.95rem;
          font-weight: 600;
          color: #484d53;
        }

        .product-info small {
          color: #64748b;
          font-size: 0.8rem;
        }

        .progress-bar-container {
          flex: 1;
          margin: 0 15px;
        }

        .progress-bar {
          height: 8px;
          background: #e2e8f0;
          border-radius: 10px;
          overflow: hidden;
        }

        .progress-fill {
          height: 100%;
          background: linear-gradient(90deg, #10b981, #3b82f6);
          border-radius: 10px;
          transition: width 0.3s ease;
        }

        .category-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 12px;
          background: #f8fafc;
          border-radius: 10px;
          margin-bottom: 10px;
          transition: all 0.3s ease;
        }

        .category-item:hover {
          transform: translateX(5px);
          box-shadow: rgba(0, 0, 0, 0.1) 0px 2px 8px;
        }

        .category-icon {
          width: 40px;
          height: 40px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          margin-right: 12px;
        }

        .category-icon.blue {
          background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .category-icon.green {
          background: linear-gradient(135deg, #10b981, #059669);
        }

        .category-icon.yellow {
          background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .category-icon.purple {
          background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .category-icon.red {
          background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .badge {
          display: inline-block;
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.8rem;
          font-weight: 600;
        }

        .badge.blue {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .badge.green {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge.yellow {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .badge.purple {
          background: rgba(139, 92, 246, 0.2);
          color: #8b5cf6;
        }

        .badge.red {
          background: rgba(239, 68, 68, 0.2);
          color: #ef4444;
        }

        .table-section {
          margin-top: 20px;
        }

        .table-container {
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

        .right-content {
          display: grid;
          grid-template-rows: 5% 30%;
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

        .filter-section {
          background: rgb(214, 227, 248);
          padding: 15px;
          margin: 15px 10px 0;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .filter-section h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .filter-group {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .filter-btn {
          padding: 10px;
          background: white;
          border: none;
          border-radius: 10px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          text-align: left;
          color: #484d53;
        }

        .filter-btn:hover {
          background: rgb(73, 57, 113);
          color: white;
          transform: translateX(5px);
        }

        .filter-btn.active {
          background: rgb(73, 57, 113);
          color: white;
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

          .analytics-grid {
            grid-template-columns: 1fr;
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

          <li class="nav-item active">
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
            <h1>Analytics Overview</h1>
            <div class="stats-grid">
              <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <div>
                  <h3>$<?php echo number_format($currentMetrics['total_revenue'], 2); ?></h3>
                  <p>Total Revenue</p>
                  <small>
                    <i class="fas fa-arrow-<?php echo ($revenueChange >= 0) ? 'up trend-up' : 'down trend-down'; ?>"></i> 
                    <?php echo number_format(abs($revenueChange), 1); ?>% vs last period
                  </small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <div>
                  <h3><?php echo number_format($currentMetrics['total_orders']); ?></h3>
                  <p>Total Orders</p>
                  <small>
                    <i class="fas fa-arrow-<?php echo ($ordersChange >= 0) ? 'up trend-up' : 'down trend-down'; ?>"></i> 
                    <?php echo number_format(abs($ordersChange), 1); ?>% vs last period
                  </small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-users"></i>
                <div>
                  <h3><?php echo number_format($customerMetrics['unique_customers']); ?></h3>
                  <p>Unique Customers</p>
                  <small><i class="fas fa-user-check"></i> In this period</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <div>
                  <h3>$<?php echo number_format($currentMetrics['avg_order_value'], 2); ?></h3>
                  <p>Avg Order Value</p>
                  <small>
                    <i class="fas fa-arrow-<?php echo ($avgOrderChange >= 0) ? 'up trend-up' : 'down trend-down'; ?>"></i> 
                    <?php echo number_format(abs($avgOrderChange), 1); ?>% vs last period
                  </small>
                </div>
              </div>
            </div>
          </div>

          <div class="analytics-section">
            <h1>Performance Insights</h1>
            <div class="analytics-grid">
              <div class="analytics-card">
                <h2>Top Performing Products</h2>
                <?php if (empty($topProducts)): ?>
                <div class="empty-state" style="padding: 20px;">
                  <i class="fas fa-box-open" style="font-size: 32px;"></i>
                  <p style="font-size: 0.9rem;">No sales data available</p>
                </div>
                <?php else: ?>
                  <?php foreach ($topProducts as $product): ?>
                  <?php $percentage = ($product['total_revenue'] / $maxRevenue) * 100; ?>
                  <div class="product-item">
                    <div class="product-info">
                      <h6><?php echo htmlspecialchars($product['name']); ?></h6>
                      <small><?php echo $product['total_sold']; ?> units • $<?php echo number_format($product['total_revenue'], 2); ?></small>
                    </div>
                    <div class="progress-bar-container">
                      <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                      </div>
                    </div>
                    <small style="color: #10b981; font-weight: 600;"><?php echo number_format($percentage, 0); ?>%</small>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div class="analytics-card">
                <h2>Sales by Category</h2>
                <?php if (empty($categories)): ?>
                <div class="empty-state" style="padding: 20px;">
                  <i class="fas fa-tags" style="font-size: 32px;"></i>
                  <p style="font-size: 0.9rem;">No category data available</p>
                </div>
                <?php else: ?>
                  <?php 
                  $colors = ['blue', 'green', 'yellow', 'purple', 'red'];
                  $i = 0;
                  foreach ($categories as $category): 
                  ?>
                  <div class="category-item">
                    <div style="display: flex; align-items: center;">
                      <div class="category-icon <?php echo $colors[$i % 5]; ?>">
                        <i class="fas fa-tags"></i>
                      </div>
                      <div>
                        <h6 style="margin: 0; font-size: 0.95rem;"><?php echo htmlspecialchars($category['category_name'] ?? 'Uncategorized'); ?></h6>
                        <small style="color: #64748b;"><?php echo $category['order_count']; ?> orders</small>
                      </div>
                    </div>
                    <span class="badge <?php echo $colors[$i % 5]; ?>">$<?php echo number_format($category['total_revenue'], 2); ?></span>
                  </div>
                  <?php $i++; endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="table-section">
            <h1>Recent Performance (Last 7 Days)</h1>
            <div class="table-container">
              <?php if (empty($dailyData)): ?>
              <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No data available for the last 7 days</p>
              </div>
              <?php else: ?>
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Customers</th>
                    <th>Orders</th>
                    <th>Revenue</th>
                    <th>Avg Order</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dailyData as $day): ?>
                  <tr>
                    <td><strong><?php echo date('M d, Y', strtotime($day['date'])); ?></strong></td>
                    <td><?php echo $day['customers']; ?></td>
                    <td><?php echo $day['orders']; ?></td>
                    <td><strong>$<?php echo number_format($day['revenue'], 2); ?></strong></td>
                    <td>$<?php echo ($day['orders'] > 0) ? number_format($day['revenue'] / $day['orders'], 2) : '0.00'; ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
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

          <div class="filter-section">
            <h1>Time Period</h1>
            <div class="filter-group">
              <button class="filter-btn <?php echo ($dateRange === '7') ? 'active' : ''; ?>" 
                      onclick="window.location.href='analytics.php?range=7'">
                <i class="fas fa-calendar-day"></i> Last 7 Days
              </button>
              <button class="filter-btn <?php echo ($dateRange === '30') ? 'active' : ''; ?>" 
                      onclick="window.location.href='analytics.php?range=30'">
                <i class="fas fa-calendar-week"></i> Last 30 Days
              </button>
              <button class="filter-btn <?php echo ($dateRange === '90') ? 'active' : ''; ?>" 
                      onclick="window.location.href='analytics.php?range=90'">
                <i class="fas fa-calendar-alt"></i> Last 3 Months
              </button>
              <button class="filter-btn <?php echo ($dateRange === '365') ? 'active' : ''; ?>" 
                      onclick="window.location.href='analytics.php?range=365'">
                <i class="fas fa-calendar"></i> Last Year
              </button>
            </div>
          </div>

          <div class="quick-actions">
            <h1>Quick Actions</h1>
            <div class="action-list">
              <a href="analytics.php?range=<?php echo $dateRange; ?>&export=csv" class="action-item">
                <i class="fas fa-download"></i>
                <span>Export Report</span>
              </a>
              <a href="products.php" class="action-item">
                <i class="fas fa-box"></i>
                <span>View Products</span>
              </a>
              <a href="orders.php" class="action-item">
                <i class="fas fa-shopping-cart"></i>
                <span>View Orders</span>
              </a>
              <a href="customers.php" class="action-item">
                <i class="fas fa-users"></i>
                <span>View Customers</span>
              </a>
              <a href="dashboard.php" class="action-item">
                <i class="fas fa-home"></i>
                <span>Back to Dashboard</span>
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
            const currentPage = window.location.pathname.split('/').pop() || 'analytics.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
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
</html>>
            