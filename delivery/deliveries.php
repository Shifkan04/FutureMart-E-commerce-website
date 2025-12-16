<?php
require_once '../config.php';

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
  header('Location: login.php');
  exit();
}

$delivery_person_id = $_SESSION['user_id'];

// Fetch delivery person details
try {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
  $stmt->execute([$delivery_person_id]);
  $delivery_person = $stmt->fetch();

  if (!$delivery_person) {
    session_destroy();
    header('Location: ../login.php');
    exit();
  }

  // Fetch all deliveries for this delivery person
  $stmt = $pdo->prepare("
        SELECT 
            da.*,
            o.order_number,
            o.total_amount,
            o.created_at as order_date,
            o.notes as order_notes,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name,
            u.phone as customer_phone,
            ua.address_line_1,
            ua.address_line_2,
            ua.city,
            ua.state,
            ua.postal_code,
            ua.country
        FROM delivery_assignments da
        INNER JOIN orders o ON da.order_id = o.id
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
        WHERE da.delivery_person_id = ?
        ORDER BY 
            CASE da.status
                WHEN 'assigned' THEN 1
                WHEN 'picked_up' THEN 2
                WHEN 'in_transit' THEN 3
                WHEN 'delivered' THEN 4
                WHEN 'failed' THEN 5
            END,
            da.assigned_at DESC
    ");
  $stmt->execute([$delivery_person_id]);
  $all_deliveries = $stmt->fetchAll();

  // Get order items for each delivery
  $deliveries_with_items = [];
  foreach ($all_deliveries as $delivery) {
    $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name
            FROM order_items oi
            INNER JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
    $stmt->execute([$delivery['order_id']]);
    $items = $stmt->fetchAll();

    $delivery['items'] = $items;
    $deliveries_with_items[] = $delivery;
  }

  // Count by status
  $counts = [
    'all' => count($deliveries_with_items),
    'assigned' => 0,
    'picked_up' => 0,
    'in_transit' => 0,
    'delivered' => 0,
    'failed' => 0
  ];

  foreach ($deliveries_with_items as $d) {
    $counts[$d['status']]++;
  }
} catch (PDOException $e) {
  error_log("Deliveries Page Error: " . $e->getMessage());
  die("An error occurred while loading deliveries.");
}

function getStatusColor($status)
{
  $colors = [
    'assigned' => 'activity-two',
    'picked_up' => 'activity-three',
    'in_transit' => 'activity-one',
    'delivered' => 'activity-four',
    'failed' => 'activity-five'
  ];
  return $colors[$status] ?? 'activity-two';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Deliveries - <?php echo APP_NAME; ?></title>
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
      overflow: hidden auto;

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
    }

    .main-menu h1 {
      display: block;
      font-size: 1.5rem;
      font-weight: 500;
      text-align: center;
      margin: 0;
      color: #fff;
      font-family: "Nunito", sans-serif;
      padding-top: 15px;
    }

    .main-menu small {
      display: block;
      font-size: 1rem;
      font-weight: 300;
      text-align: center;
      margin: 10px 0;
      color: #fff;
      font-family: "Nunito", sans-serif;
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
      overflow-y: auto;
      /* enable vertical scroll */
      -webkit-overflow-scrolling: touch;
      /* smooth inertial scroll on iOS */
      position: relative;
      scrollbar-gutter: stable;
      /* keeps layout from shifting when scrollbar appears */

    }

    .left-content {
      background: #f6f7fb;
      margin: 15px;
      padding: 20px;
      border-radius: 15px;
      overflow-y: auto;
      max-height: calc(100vh - 80px);
    }

    .header-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .header-section h1 {
      font-size: 1.4rem;
      font-weight: 700;
      color: #484d53;
    }

    .btn-refresh {
      padding: 10px 20px;
      background: rgb(73, 57, 113);
      color: white;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-refresh:hover {
      background: rgb(93, 77, 133);
      transform: translateY(-2px);
    }

    .filter-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .filter-btn {
      padding: 10px 20px;
      background: white;
      color: #484d53;
      border: 2px solid transparent;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.3s ease;
      box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
    }

    .filter-btn:hover {
      transform: translateY(-2px);
      box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }

    .filter-btn.active {
      background: rgb(73, 57, 113);
      color: white;
      border-color: rgb(73, 57, 113);
    }

    .filter-btn i {
      margin-right: 5px;
    }

    .deliveries-container {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .delivery-card {
      background: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
      border-left: 5px solid #6c757d;
      transition: all 0.3s ease;
    }

    .delivery-card:hover {
      transform: translateX(5px);
      box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }

    .delivery-card.activity-one {
      border-left-color: #7c88e0;
      background: linear-gradient(to right, rgba(124, 136, 224, 0.05), white);
    }

    .delivery-card.activity-two {
      border-left-color: #e5a243;
      background: linear-gradient(to right, rgba(229, 162, 67, 0.05), white);
    }

    .delivery-card.activity-three {
      border-left-color: #97e7d1;
      background: linear-gradient(to right, rgba(151, 231, 209, 0.05), white);
    }

    .delivery-card.activity-four {
      border-left-color: #fc8ebe;
      background: linear-gradient(to right, rgba(252, 142, 190, 0.05), white);
    }

    .delivery-card.activity-five {
      border-left-color: #ef4444;
      background: linear-gradient(to right, rgba(239, 68, 68, 0.05), white);
    }

    .delivery-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }

    .delivery-header h3 {
      font-size: 1.2rem;
      font-weight: 700;
      color: #484d53;
      margin: 0;
    }

    .status-badge {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .status-badge.assigned {
      background: rgba(108, 117, 125, 0.2);
      color: #6c757d;
    }

    .status-badge.picked_up {
      background: rgba(151, 231, 209, 0.3);
      color: #0d9488;
    }

    .status-badge.in_transit {
      background: rgba(124, 136, 224, 0.3);
      color: #4f46e5;
    }

    .status-badge.delivered {
      background: rgba(252, 142, 190, 0.3);
      color: #ec4899;
    }

    .status-badge.failed {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
    }

    .delivery-info {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      margin-bottom: 15px;
    }

    .info-item {
      display: flex;
      align-items: start;
      gap: 10px;
    }

    .info-item i {
      font-size: 1rem;
      margin-top: 2px;
      min-width: 20px;
    }

    .info-item strong {
      display: block;
      font-weight: 700;
      color: #484d53;
      margin-bottom: 2px;
    }

    .info-item span {
      color: #6b7280;
      font-size: 0.95rem;
    }

    .delivery-items {
      background: #f6f7fb;
      border-radius: 10px;
      padding: 12px;
      margin: 15px 0;
    }

    .delivery-items strong {
      display: block;
      margin-bottom: 8px;
      color: #484d53;
    }

    .delivery-items ul {
      margin: 0;
      padding-left: 20px;
    }

    .delivery-items li {
      margin-bottom: 5px;
      color: #6b7280;
      font-size: 0.9rem;
    }

    .delivery-total {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 2px solid #e5e7eb;
      font-weight: 700;
      color: #484d53;
    }

    .delivery-notes {
      background: rgba(59, 130, 246, 0.1);
      border-left: 3px solid #3b82f6;
      padding: 10px 12px;
      border-radius: 8px;
      margin: 10px 0;
    }

    .delivery-notes i {
      color: #3b82f6;
      margin-right: 8px;
    }

    .delivery-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 15px;
    }

    .btn {
      padding: 8px 16px;
      font-size: 0.9rem;
      font-weight: 600;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1);
    }

    .btn-primary {
      background: rgb(73, 57, 113);
      color: white;
    }

    .btn-success {
      background: #10b981;
      color: white;
    }

    .btn-danger {
      background: #ef4444;
      color: white;
    }

    .btn-outline {
      background: white;
      color: #484d53;
      border: 2px solid #e5e7eb;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #9ca3af;
    }

    .empty-state i {
      font-size: 64px;
      opacity: 0.3;
      display: block;
      margin-bottom: 20px;
    }

    .empty-state h3 {
      font-size: 1.3rem;
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

    .stats-summary {
      padding: 15px;
      margin: 15px 10px 0;
    }

    .stats-summary h2 {
      font-size: 1.2rem;
      font-weight: 700;
      color: #484d53;
      margin-bottom: 20px;
    }

    .stat-item {
      background: white;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 12px;
      box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
      transition: all 0.3s ease;
    }

    .stat-item:hover {
      transform: translateY(-3px);
      box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }

    .stat-item-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }

    .stat-icon {
      width: 35px;
      height: 35px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      color: white;
    }

    .stat-icon.all {
      background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-icon.pending {
      background: linear-gradient(135deg, #e5a243, #f7f7aa);
    }

    .stat-icon.transit {
      background: linear-gradient(135deg, #7c88e0, #c3f4fc);
    }

    .stat-icon.complete {
      background: linear-gradient(135deg, #fc8ebe, #fce5c3);
    }

    .stat-content h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #484d53;
      margin: 0;
    }

    .stat-content p {
      font-size: 0.9rem;
      color: #6b7280;
      margin: 0;
    }

    /* Custom Scrollbar (WebKit browsers) */
    .content::-webkit-scrollbar {
      width: 10px;
    }

    .content::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.06);
      border-radius: 10px;
    }

    .content::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, rgba(0, 102, 255, 0.6), rgba(124, 136, 224, 0.7));
      border-radius: 10px;
      border: 2px solid rgba(255, 255, 255, 0.05);
    }

    .content::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, rgba(0, 102, 255, 0.9), rgba(124, 136, 224, 0.9));
    }

    /* Firefox support */
    .content {
      scrollbar-width: thin;
      scrollbar-color: rgba(124, 136, 224, 0.8) rgba(255, 255, 255, 0.06);
    }



    @media (max-width: 1500px) {
      main {
        grid-template-columns: 6% 94%;
      }

      .main-menu h1,
      .main-menu small {
        display: none;
      }

      .logo {
        display: block;
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

      .delivery-info {
        grid-template-columns: 1fr;
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
      }

      .left-content {
        margin: 15px;
      }

      .right-content {
        display: none;
      }
    }
  </style>
</head>

<body>
  <main>
    <nav class="main-menu">
      <h1><?php echo APP_NAME; ?></h1>
      <small>Delivery Panel</small>
      <div class="logo">
        <i class="fa fa-truck" style="font-size: 24px; color: white;"></i>
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

        <li class="nav-item active">
          <b></b>
          <b></b>
          <a href="deliveries.php">
            <i class="fa fa-box nav-icon"></i>
            <span class="nav-text">My Deliveries</span>
          </a>
        </li>

        <li class="nav-item">
          <b></b>
          <b></b>
          <a href="route.php">
            <i class="fa fa-route nav-icon"></i>
            <span class="nav-text">Route & Map</span>
          </a>
        </li>

        <li class="nav-item">
          <b></b>
          <b></b>
          <a href="profile.php">
            <i class="fa fa-user nav-icon"></i>
            <span class="nav-text">Profile</span>
          </a>
        </li>

        <li class="nav-item">
          <b></b>
          <b></b>
          <a href="messages.php">
            <i class="fa fa-envelope nav-icon"></i>
            <span class="nav-text">Messages</span>
          </a>
        </li>

        <li class="nav-item">
          <b></b>
          <b></b>
          <a href="contact.php">
            <i class="fa fa-phone nav-icon"></i>
            <span class="nav-text">Contact</span>
          </a>
        </li>

        <li class="nav-item">
          <b></b>
          <b></b>
          <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fa fa-sign-out-alt nav-icon"></i>
            <span class="nav-text">Logout</span>
          </a>
        </li>
      </ul>
    </nav>

    <section class="content">
      <div class="left-content">
        <div class="header-section">
          <h1>My Deliveries</h1>
          <button class="btn-refresh" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>

        <div class="filter-tabs">
          <button class="filter-btn active" data-filter="all">
            <i class="fas fa-list"></i> All (<?php echo $counts['all']; ?>)
          </button>
          <button class="filter-btn" data-filter="assigned">
            <i class="fas fa-clock"></i> Pending (<?php echo $counts['assigned']; ?>)
          </button>
          <button class="filter-btn" data-filter="picked_up">
            <i class="fas fa-box-open"></i> Picked Up (<?php echo $counts['picked_up']; ?>)
          </button>
          <button class="filter-btn" data-filter="in_transit">
            <i class="fas fa-truck"></i> In Transit (<?php echo $counts['in_transit']; ?>)
          </button>
          <button class="filter-btn" data-filter="delivered">
            <i class="fas fa-check-circle"></i> Delivered (<?php echo $counts['delivered']; ?>)
          </button>
        </div>

        <div class="deliveries-container">
          <?php if (empty($deliveries_with_items)): ?>
            <div class="empty-state">
              <i class="fas fa-box-open"></i>
              <h3>No deliveries found</h3>
              <p>You don't have any assigned deliveries yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($deliveries_with_items as $delivery): ?>
              <div class="delivery-card <?php echo getStatusColor($delivery['status']); ?>" data-status="<?php echo $delivery['status']; ?>">
                <div class="delivery-header">
                  <h3>Order #<?php echo htmlspecialchars($delivery['order_number']); ?></h3>
                  <span class="status-badge <?php echo $delivery['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                  </span>
                </div>

                <div class="delivery-info">
                  <div class="info-item">
                    <i class="fas fa-user" style="color: #7c88e0;"></i>
                    <div>
                      <strong>Customer</strong>
                      <span><?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?></span>
                    </div>
                  </div>

                  <div class="info-item">
                    <i class="fas fa-phone" style="color: #10b981;"></i>
                    <div>
                      <strong>Phone</strong>
                      <span><?php echo htmlspecialchars($delivery['customer_phone'] ?: 'N/A'); ?></span>
                    </div>
                  </div>

                  <div class="info-item">
                    <i class="fas fa-map-marker-alt" style="color: #ef4444;"></i>
                    <div>
                      <strong>Delivery Address</strong>
                      <span>
                        <?php
                        echo htmlspecialchars($delivery['address_line_1']);
                        if ($delivery['address_line_2']) echo ', ' . htmlspecialchars($delivery['address_line_2']);
                        echo ', ' . htmlspecialchars($delivery['city']);
                        ?>
                      </span>
                    </div>
                  </div>

                  <div class="info-item">
                    <i class="fas fa-calendar" style="color: #f59e0b;"></i>
                    <div>
                      <strong>Order Date</strong>
                      <span><?php echo date('M d, Y', strtotime($delivery['order_date'])); ?></span>
                    </div>
                  </div>

                  <div class="info-item">
                    <i class="fas fa-clock" style="color: #8b5cf6;"></i>
                    <div>
                      <strong>Assigned At</strong>
                      <span><?php echo date('M d, Y g:i A', strtotime($delivery['assigned_at'])); ?></span>
                    </div>
                  </div>

                  <?php if ($delivery['delivered_at']): ?>
                    <div class="info-item">
                      <i class="fas fa-check-circle" style="color: #10b981;"></i>
                      <div>
                        <strong>Delivered At</strong>
                        <span><?php echo date('M d, Y g:i A', strtotime($delivery['delivered_at'])); ?></span>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="delivery-items">
                  <strong><i class="fas fa-box"></i> Order Items (<?php echo count($delivery['items']); ?>)</strong>
                  <ul>
                    <?php foreach ($delivery['items'] as $item): ?>
                      <li>
                        <?php echo htmlspecialchars($item['product_name']); ?>
                        - Qty: <?php echo $item['quantity']; ?>
                        - $<?php echo number_format($item['total_price'], 2); ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                  <div class="delivery-total">
                    Total Amount: $<?php echo number_format($delivery['total_amount'], 2); ?>
                  </div>
                </div>

                <?php if ($delivery['order_notes']): ?>
                  <div class="delivery-notes">
                    <i class="fas fa-info-circle"></i>
                    <strong>Notes:</strong> <?php echo htmlspecialchars($delivery['order_notes']); ?>
                  </div>
                <?php endif; ?>

                <div class="delivery-actions">
                  <?php if ($delivery['status'] === 'assigned'): ?>
                    <button class="btn btn-primary" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'picked_up')">
                      <i class="fas fa-box-open"></i> Pick Up Order
                    </button>
                  <?php elseif ($delivery['status'] === 'picked_up'): ?>
                    <button class="btn btn-primary" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'in_transit')">
                      <i class="fas fa-truck"></i> Start Delivery
                    </button>
                  <?php elseif ($delivery['status'] === 'in_transit'): ?>
                    <button class="btn btn-success" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'delivered')">
                      <i class="fas fa-check"></i> Mark Delivered
                    </button>
                    <button class="btn btn-danger" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'failed')">
                      <i class="fas fa-times"></i> Mark Failed
                    </button>
                  <?php endif; ?>

                  <?php if ($delivery['status'] !== 'delivered' && $delivery['status'] !== 'failed'): ?>
                    <a href="tel:<?php echo htmlspecialchars($delivery['customer_phone']); ?>" class="btn btn-outline">
                      <i class="fas fa-phone"></i> Call Customer
                    </a>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($delivery['address_line_1'] . ', ' . $delivery['city']); ?>" target="_blank" class="btn btn-outline">
                      <i class="fas fa-map"></i> View Location
                    </a>
                  <?php endif; ?>

                  <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-eye"></i> View Details
                  </a>
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
          <h4><?php echo htmlspecialchars($delivery_person['first_name'] . ' ' . $delivery_person['last_name']); ?></h4>
          <?php if (!empty($delivery_person['profile_picture'])): ?>
            <img src="../<?php echo htmlspecialchars($delivery_person['profile_picture']); ?>" alt="Profile">
          <?php else: ?>
            <div class="user-avatar">
              <?php echo strtoupper(substr($delivery_person['first_name'], 0, 1)); ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="stats-summary">
          <h2>Delivery Stats</h2>

          <div class="stat-item">
            <div class="stat-item-header">
              <div class="stat-icon all">
                <i class="fas fa-list"></i>
              </div>
              <div class="stat-content">
                <h3><?php echo $counts['all']; ?></h3>
                <p>Total Deliveries</p>
              </div>
            </div>
          </div>

          <div class="stat-item">
            <div class="stat-item-header">
              <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
              </div>
              <div class="stat-content">
                <h3><?php echo $counts['assigned']; ?></h3>
                <p>Pending Pickup</p>
              </div>
            </div>
          </div>

          <div class="stat-item">
            <div class="stat-item-header">
              <div class="stat-icon transit">
                <i class="fas fa-truck"></i>
              </div>
              <div class="stat-content">
                <h3><?php echo $counts['picked_up'] + $counts['in_transit']; ?></h3>
                <p>Active Deliveries</p>
              </div>
            </div>
          </div>

          <div class="stat-item">
            <div class="stat-item-header">
              <div class="stat-icon complete">
                <i class="fas fa-check-circle"></i>
              </div>
              <div class="stat-content">
                <h3><?php echo $counts['delivered']; ?></h3>
                <p>Completed</p>
              </div>
            </div>
          </div>

          <?php if ($counts['failed'] > 0): ?>
            <div class="stat-item">
              <div class="stat-item-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #fca5a5);">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                  <h3><?php echo $counts['failed']; ?></h3>
                  <p>Failed Deliveries</p>
                </div>
              </div>
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

    // Set active menu based on current page
    document.addEventListener('DOMContentLoaded', function() {
      const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
      navItems.forEach((navItem) => {
        const link = navItem.querySelector('a');
        if (link && link.getAttribute('href') === currentPage) {
          navItems.forEach((item) => item.classList.remove("active"));
          navItem.classList.add("active");
        }
      });
    });

    // Filter functionality
    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        // Update active button
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const filter = this.getAttribute('data-filter');
        const cards = document.querySelectorAll('.delivery-card');

        cards.forEach(card => {
          if (filter === 'all' || card.getAttribute('data-status') === filter) {
            card.style.display = 'block';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });

    function updateDeliveryStatus(deliveryId, newStatus) {
      fetch('update_delivery_status.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            delivery_id: deliveryId,
            status: newStatus
          })
        })
        .then(res => res.text())
        .then(text => {
          console.log("Raw response:", text); // 👀 see what PHP sends
          let data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            alert("⚠️ Invalid JSON received:\n" + text);
            return;
          }

          if (data.success) {
            showNotification('success', data.message);
            setTimeout(() => window.location.reload(), 1500);
          } else {
            showNotification('error', data.message);
          }
        })
        .catch(err => {
          console.error("Fetch error:", err);
          showNotification('error', 'Request failed. Check console.');
        });
    }

    function updateDeliveryStatus(deliveryId, newStatus) {
      // Define dynamic texts + icons for each status
      const statusInfo = {
        picked_up: {
          title: 'Pick Up Order?',
          confirmText: 'Yes, pick it up',
          successTitle: 'Order Picked Up!',
          successMsg: 'You’ve successfully picked up the order.',
          icon: 'box-open',
          color: '#10b981'
        },
        in_transit: {
          title: 'Start Delivery?',
          confirmText: 'Yes, start delivery',
          successTitle: 'Delivery in Progress!',
          successMsg: 'Delivery status updated to "In Transit".',
          icon: 'truck',
          color: '#3b82f6'
        },
        delivered: {
          title: 'Mark as Delivered?',
          confirmText: 'Yes, mark delivered',
          successTitle: 'Delivered Successfully!',
          successMsg: 'You’ve marked this order as delivered 🎉',
          icon: 'check-circle',
          color: '#10b981'
        },
        failed: {
          title: 'Mark as Failed?',
          confirmText: 'Yes, mark failed',
          successTitle: 'Delivery Marked as Failed',
          successMsg: 'Order marked as failed. Please report to admin.',
          icon: 'times-circle',
          color: '#ef4444'
        }
      };

      const info = statusInfo[newStatus] || {
        title: 'Confirm Status Change?',
        confirmText: 'Yes, confirm',
        successTitle: 'Status Updated!',
        successMsg: 'Delivery status updated successfully.',
        icon: 'info-circle',
        color: 'rgb(73, 57, 113)'
      };

      // Step 1: Show confirmation popup
      Swal.fire({
        title: info.title,
        text: 'Do you want to continue?',
        iconHtml: `<i class="fas fa-${info.icon}" style="color:${info.color};"></i>`,
        showCancelButton: true,
        confirmButtonText: info.confirmText,
        cancelButtonText: 'Cancel',
        confirmButtonColor: 'rgb(73, 57, 113)',
        cancelButtonColor: '#aaa',
        background: '#fff',
        color: '#484d53',
      }).then((result) => {
        if (!result.isConfirmed) return;

        // Step 2: Show loading
        Swal.fire({
          title: 'Updating...',
          text: 'Please wait a moment',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading()
        });

        // Step 3: Send request
        fetch('update_delivery_status.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              delivery_id: deliveryId,
              status: newStatus
            })
          })
          .then(res => res.text())
          .then(text => {
            console.log('Raw response:', text);
            let data;
            try {
              data = JSON.parse(text);
            } catch (e) {
              Swal.fire({
                icon: 'error',
                title: 'Invalid Response',
                text: 'Server returned an invalid response.',
                confirmButtonColor: 'rgb(73, 57, 113)'
              });
              return;
            }

            // Step 4: Handle success or error
            if (data.success) {
              Swal.fire({
                iconHtml: `<i class="fas fa-${info.icon}" style="color:${info.color};"></i>`,
                title: info.successTitle,
                text: info.successMsg,
                confirmButtonColor: 'rgb(73, 57, 113)',
                timer: 1800,
                showConfirmButton: false
              }).then(() => {
                // animate the progress line before reload
                animateProgress(data.new_status);

                // little delay before reload for animation
                setTimeout(() => window.location.reload(), 1500);
              });
            }

          })
          .catch(err => {
            console.error('Fetch error:', err);
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'An error occurred while updating status.',
              confirmButtonColor: 'rgb(73, 57, 113)'
            });
          });
      });
    }

    function animateProgress(status) {
      const progressLine = document.querySelector('.progress-line-fill');
      const steps = ['assigned', 'picked_up', 'in_transit', 'delivered'];

      const statusIndex = steps.indexOf(status);
      const percentage = (statusIndex / (steps.length - 1)) * 100;

      // Animate width
      progressLine.style.transition = "width 1s ease-in-out";
      progressLine.style.width = `${percentage}%`;

      // Animate status icons glow
      const allSteps = document.querySelectorAll('.status-step');
      allSteps.forEach((step, index) => {
        const icon = step.querySelector('.status-icon');
        const label = step.querySelector('.status-label');

        if (index <= statusIndex) {
          icon.style.background = 'linear-gradient(135deg, rgb(124,136,224), #c3f4fc)';
          icon.style.color = '#fff';
          icon.style.boxShadow = '0 8px 20px rgba(124,136,224,0.4)';
          label.style.color = 'rgb(73, 57, 113)';
          label.style.fontWeight = '700';
        } else {
          icon.style.background = '#f6f7fb';
          icon.style.color = '#9ca3af';
          icon.style.boxShadow = 'none';
          label.style.color = '#9ca3af';
          label.style.fontWeight = '500';
        }
      });
    }


    function showNotification(type, message) {
      const notification = document.createElement('div');
      notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                font-weight: 600;
                animation: slideIn 0.3s ease;
            `;
      notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;

      document.body.appendChild(notification);

      setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }

    // Add animation keyframes
    const style = document.createElement('style');
    style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
    document.head.appendChild(style);
  </script>
</body>

</html>