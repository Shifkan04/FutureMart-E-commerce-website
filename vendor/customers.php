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
$vendorBrand = $vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Filters
$searchTerm = $_GET['search'] ?? '';
$segmentFilter = $_GET['segment'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Get customer statistics
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_customers,
            COUNT(DISTINCT CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) as new_month,
            COUNT(DISTINCT CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN u.id END) as active_customers,
            COUNT(DISTINCT CASE WHEN customer_total >= 1000 THEN u.id END) as vip_customers
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN products p ON oi.product_id = p.id
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN (
            SELECT o2.user_id, SUM(oi2.total_price) as customer_total
            FROM orders o2
            INNER JOIN order_items oi2 ON o2.id = oi2.order_id
            INNER JOIN products p2 ON oi2.product_id = p2.id
            WHERE p2.brand = ?
            GROUP BY o2.user_id
        ) totals ON u.id = totals.user_id
        WHERE p.brand = ? AND u.role = 'user'
    ");
    $statsStmt->execute([$vendorBrand, $vendorBrand]);
    $stats = $statsStmt->fetch();

    if (!$stats || $stats['total_customers'] == 0) {
        $stats = [
            'total_customers' => 0,
            'new_month' => 0,
            'active_customers' => 0,
            'vip_customers' => 0
        ];
    }
} catch (PDOException $e) {
    $stats = [
        'total_customers' => 0,
        'new_month' => 0,
        'active_customers' => 0,
        'vip_customers' => 0
    ];
}

// Build customer query with filters
$whereClause = "WHERE p.brand = ? AND u.role = 'user'";
$params = [$vendorBrand];

if (!empty($searchTerm)) {
    $whereClause .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($statusFilter)) {
    $whereClause .= " AND u.status = ?";
    $params[] = $statusFilter;
}

// HAVING clause for segment filter
$havingClause = "";
if (!empty($segmentFilter)) {
    switch ($segmentFilter) {
        case 'vip':
            $havingClause = "HAVING total_spent >= 1000";
            break;
        case 'regular':
            $havingClause = "HAVING total_spent >= 200 AND total_spent < 1000";
            break;
        case 'new':
            $havingClause = "HAVING order_count <= 5";
            break;
    }
}

// Count total customers
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) as total
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN products p ON oi.product_id = p.id
        INNER JOIN users u ON o.user_id = u.id
        $whereClause
    ");
    $countStmt->execute($params);
    $totalCustomers = $countStmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $totalCustomers = 0;
}

$totalPages = $totalCustomers > 0 ? ceil($totalCustomers / $itemsPerPage) : 1;

// Fetch customers
$customers = [];
if ($totalCustomers > 0) {
    try {
        $customerParams = $params;
        $customerParams[] = $itemsPerPage;
        $customerParams[] = $offset;

        $customersStmt = $pdo->prepare("
            SELECT 
                u.id, u.first_name, u.last_name, u.email, u.phone, 
                u.status, u.profile_picture, u.created_at,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(oi.total_price), 0) as total_spent,
                MAX(o.created_at) as last_order_date
            FROM users u
            INNER JOIN orders o ON u.id = o.user_id
            INNER JOIN order_items oi ON o.id = oi.order_id
            INNER JOIN products p ON oi.product_id = p.id
            $whereClause
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, 
                     u.status, u.profile_picture, u.created_at
            $havingClause
            ORDER BY total_spent DESC
            LIMIT ? OFFSET ?
        ");
        $customersStmt->execute($customerParams);
        $customers = $customersStmt->fetchAll();
    } catch (PDOException $e) {
        $customers = [];
    }
}

// Unread notifications
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread
        FROM admin_messages
        WHERE recipient_id = ? AND recipient_type = 'vendor' AND is_read = 0
    ");
    $stmt->execute([$vendorId]);
    $unreadNotifications = $stmt->fetch()['unread'] ?? 0;
} catch (PDOException $e) {
    $unreadNotifications = 0;
}

// Function to determine customer segment
function getCustomerSegment($totalSpent, $orderCount)
{
    if ($totalSpent >= 1000) {
        return ['class' => 'vip', 'text' => 'VIP'];
    } elseif ($orderCount <= 5) {
        return ['class' => 'new', 'text' => 'New'];
    } else {
        return ['class' => 'regular', 'text' => 'Regular'];
    }
}

// Function to get rating stars
function getRatingStars($orderCount)
{
    $rating = min(5, max(1, round($orderCount / 2)));
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '★';
        } else {
            $stars .= '☆';
        }
    }
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Management - <?php echo APP_NAME; ?></title>
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

        /* Hide scrollbar but allow scroll */
body {
  overflow: scroll;
}

/* Chrome, Safari, Edge */
body::-webkit-scrollbar {
  display: none;
}

/* Firefox */
body {
  scrollbar-width: none;
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
          display: flex;
          flex-direction: column;
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - -100px);
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
        }

        .segments-section {
          margin-top: 20px;
        }

        .segments-section h1 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 15px;
        }

        .segments-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 15px;
        }

        .segment-card {
          background: white;
          padding: 20px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          text-align: center;
          transition: all 0.3s ease;
        }

        .segment-card:hover {
          transform: translateY(-5px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .segment-card i {
          font-size: 48px;
          margin-bottom: 15px;
        }

        .segment-card.vip i {
          color: #fbbf24;
        }

        .segment-card.regular i {
          color: #3b82f6;
        }

        .segment-card.new i {
          color: #10b981;
        }

        .segment-card h2 {
          font-size: 1.1rem;
          font-weight: 700;
          margin-bottom: 5px;
          color: #484d53;
        }

        .segment-card p {
          font-size: 0.85rem;
          color: #64748b;
          margin-bottom: 10px;
        }

        .segment-card h3 {
          font-size: 2rem;
          font-weight: 700;
          margin: 10px 0;
        }

        .segment-card.vip h3 {
          color: #fbbf24;
        }

        .segment-card.regular h3 {
          color: #3b82f6;
        }

        .segment-card.new h3 {
          color: #10b981;
        }

        .customers-section {
          margin-top: 20px;
          flex: 1;
          min-height: 400px;
          overflow-y: auto;
        }

        .customers-section h1 {
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
          border: none;
          border-radius: 10px;
          cursor: pointer;
          transition: all 0.3s ease;
          text-align: center;
        }

        .btn:hover {
          transform: translateY(-2px);
        }

        .btn-primary {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-secondary {
          background: #e2e8f0;
          color: #484d53;
        }

        .customers-table {
          background: white;
          border-radius: 15px;
          padding: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          overflow-x: auto;
          min-height: 300px;
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

        .customer-info {
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .customer-avatar {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          object-fit: cover;
        }

        .customer-avatar-placeholder {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 700;
          color: white;
        }

        .customer-name {
          font-weight: 600;
          margin-bottom: 3px;
        }

        .customer-joined {
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

        .badge.vip {
          background: rgba(251, 191, 36, 0.2);
          color: #f59e0b;
        }

        .badge.regular {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .badge.new {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge.info {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .badge.success {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .badge.warning {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .rating-stars {
          color: #fbbf24;
          font-size: 1.1rem;
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

        .action-item.active {
          background: rgb(73, 57, 113);
          color: white;
        }

        .action-item.active i {
          background: white;
          color: rgb(73, 57, 113);
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

          .segments-grid {
            grid-template-columns: 1fr;
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

          <li class="nav-item active">
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
            <h1>Customer Overview</h1>
            <div class="stats-grid">
              <div class="stat-card">
                <i class="fas fa-users"></i>
                <div>
                  <h3><?php echo number_format($stats['total_customers']); ?></h3>
                  <p>Total Customers</p>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-user-plus"></i>
                <div>
                  <h3><?php echo number_format($stats['new_month']); ?></h3>
                  <p>New This Month</p>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <div>
                  <h3><?php echo number_format($stats['active_customers']); ?></h3>
                  <p>Active Customers</p>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-crown"></i>
                <div>
                  <h3><?php echo number_format($stats['vip_customers']); ?></h3>
                  <p>VIP Customers</p>
                </div>
              </div>
            </div>
          </div>

          <div class="segments-section">
            <h1>Customer Segments</h1>
            <div class="segments-grid">
              <div class="segment-card vip">
                <i class="fas fa-crown"></i>
                <h2>VIP Customers</h2>
                <p>High-value customers ($1000+)</p>
                <h3><?php echo $stats['vip_customers']; ?></h3>
                <small style="color: #10b981; font-weight: 600;"><i class="fas fa-arrow-up"></i> Growth</small>
              </div>

              <div class="segment-card regular">
                <i class="fas fa-user-friends"></i>
                <h2>Regular Customers</h2>
                <p>Loyal repeat customers</p>
                <h3><?php echo max(0, $stats['active_customers'] - $stats['vip_customers']); ?></h3>
                <small style="color: #10b981; font-weight: 600;"><i class="fas fa-arrow-up"></i> Stable</small>
              </div>

              <div class="segment-card new">
                <i class="fas fa-seedling"></i>
                <h2>New Customers</h2>
                <p>Recently joined (≤5 orders)</p>
                <h3><?php echo $stats['new_month']; ?></h3>
                <small style="color: #10b981; font-weight: 600;"><i class="fas fa-arrow-up"></i> Growing</small>
              </div>
            </div>
          </div>

          <div class="customers-section">
            <h1>Customer List (<?php echo $totalCustomers; ?> customers)</h1>
            
            <div class="filter-container">
              <form method="GET" action="customers.php">
                <div class="filter-row">
                  <input type="text" class="filter-input" name="search" 
                         placeholder="🔍 Search customers..." 
                         value="<?php echo htmlspecialchars($searchTerm); ?>">
                  
                  <select class="filter-input" name="segment">
                    <option value="">All Segments</option>
                    <option value="vip" <?php echo ($segmentFilter === 'vip') ? 'selected' : ''; ?>>VIP</option>
                    <option value="regular" <?php echo ($segmentFilter === 'regular') ? 'selected' : ''; ?>>Regular</option>
                    <option value="new" <?php echo ($segmentFilter === 'new') ? 'selected' : ''; ?>>New</option>
                  </select>
                  
                  <select class="filter-input" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                  
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                  </button>
                  
                  <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                  </a>
                </div>
              </form>
            </div>

            <div class="customers-table">
              <?php if (empty($customers)): ?>
              <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No customers found</p>
              </div>
              <?php else: ?>
              <table>
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Segment</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Last Order</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($customers as $customer): ?>
                  <?php $segment = getCustomerSegment($customer['total_spent'], $customer['order_count']); ?>
                  <tr>
                    <td>
                      <div class="customer-info">
                        <?php if ($customer['profile_picture']): ?>
                        <img src="../<?php echo htmlspecialchars($customer['profile_picture']); ?>" 
                             class="customer-avatar" alt="Customer">
                        <?php else: ?>
                        <div class="customer-avatar-placeholder">
                          <?php echo strtoupper(substr($customer['first_name'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div>
                          <div class="customer-name"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></div>
                          <div class="customer-joined">Joined: <?php echo date('M d, Y', strtotime($customer['created_at'])); ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                    <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                    <td><span class="badge info"><?php echo $customer['order_count']; ?></span></td>
                    <td><strong>$<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                    <td><span class="badge <?php echo $segment['class']; ?>"><?php echo $segment['text']; ?></span></td>
                    <td>
                      <div class="rating-stars">
                        <?php echo getRatingStars($customer['order_count']); ?>
                      </div>
                    </td>
                    <td>
                      <?php if ($customer['status'] === 'active'): ?>
                      <span class="badge success">Active</span>
                      <?php else: ?>
                      <span class="badge warning">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <small><?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?></small>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <?php if ($totalPages > 1): ?>
              <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&segment=<?php echo $segmentFilter; ?>&status=<?php echo $statusFilter; ?>">
                  <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                  <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&segment=<?php echo $segmentFilter; ?>&status=<?php echo $statusFilter; ?>" 
                     class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                  </a>
                  <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                  <span>...</span>
                  <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&segment=<?php echo $segmentFilter; ?>&status=<?php echo $statusFilter; ?>">
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

          <div class="quick-actions">
            <h1>Quick Filters</h1>
            <div class="action-list">
              <a href="customers.php" class="action-item <?php echo (empty($segmentFilter) && empty($statusFilter)) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>All Customers</span>
              </a>
              <a href="customers.php?segment=vip" class="action-item <?php echo ($segmentFilter === 'vip') ? 'active' : ''; ?>">
                <i class="fas fa-crown"></i>
                <span>VIP Customers</span>
              </a>
              <a href="customers.php?segment=regular" class="action-item <?php echo ($segmentFilter === 'regular') ? 'active' : ''; ?>">
                <i class="fas fa-user-friends"></i>
                <span>Regular Customers</span>
              </a>
              <a href="customers.php?segment=new" class="action-item <?php echo ($segmentFilter === 'new') ? 'active' : ''; ?>">
                <i class="fas fa-seedling"></i>
                <span>New Customers</span>
              </a>
              <a href="customers.php?status=active" class="action-item <?php echo ($statusFilter === 'active') ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Active Only</span>
              </a>
              <a href="customers.php?status=inactive" class="action-item <?php echo ($statusFilter === 'inactive') ? 'active' : ''; ?>">
                <i class="fas fa-pause-circle"></i>
                <span>Inactive Only</span>
              </a>
            </div>

            <h1 style="margin-top: 30px;">Quick Actions</h1>
            <div class="action-list">
              <a href="analytics.php" class="action-item">
                <i class="fas fa-chart-line"></i>
                <span>View Analytics</span>
              </a>
              <a href="orders.php" class="action-item">
                <i class="fas fa-shopping-cart"></i>
                <span>View Orders</span>
              </a>
              <a href="products.php" class="action-item">
                <i class="fas fa-box"></i>
                <span>View Products</span>
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
            const currentPage = window.location.pathname.split('/').pop() || 'customers.php';
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
</html>