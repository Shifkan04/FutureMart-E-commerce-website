<?php
require_once '../config.php';

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}

$delivery_person_id = $_SESSION['user_id'];
$delivery_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$delivery_id) {
    header('Location: deliveries.php');
    exit();
}

// Fetch delivery person details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
    $stmt->execute([$delivery_person_id]);
    $delivery_person = $stmt->fetch();

    if (!$delivery_person) {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Fetch delivery details with order information
    $stmt = $pdo->prepare("
        SELECT 
            da.*,
            o.order_number,
            o.total_amount,
            o.subtotal,
            o.tax_amount,
            o.shipping_amount,
            o.payment_method,
            o.payment_status,
            o.created_at as order_date,
            o.notes as order_notes,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name,
            u.email as customer_email,
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
        WHERE da.id = ? AND da.delivery_person_id = ?
    ");
    $stmt->execute([$delivery_id, $delivery_person_id]);
    $delivery = $stmt->fetch();

    if (!$delivery) {
        $_SESSION['error'] = 'Delivery not found or access denied';
        header('Location: deliveries.php');
        exit();
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image as product_image
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$delivery['order_id']]);
    $order_items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Delivery Details Error: " . $e->getMessage());
    die("An error occurred while loading delivery details.");
}

// Status progression
$status_steps = [
    'assigned' => ['label' => 'Order Assigned', 'icon' => 'fa-clipboard-check', 'position' => 1],
    'picked_up' => ['label' => 'Picked Up', 'icon' => 'fa-box-open', 'position' => 2],
    'in_transit' => ['label' => 'On the Way', 'icon' => 'fa-truck', 'position' => 3],
    'delivered' => ['label' => 'Delivered', 'icon' => 'fa-check-circle', 'position' => 4]
];

$current_status_position = $status_steps[$delivery['status']]['position'] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Details - <?php echo APP_NAME; ?></title>
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

        .content {
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
          margin-bottom: 25px;
          flex-wrap: wrap;
          gap: 15px;
        }

        .back-btn {
          padding: 10px 20px;
          background: white;
          color: #484d53;
          border: 2px solid #e5e7eb;
          border-radius: 12px;
          cursor: pointer;
          font-weight: 600;
          text-decoration: none;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 8px;
        }

        .back-btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1);
          color: #484d53;
        }

        .header-title h1 {
          font-size: 1.5rem;
          font-weight: 700;
          color: #484d53;
          margin: 5px 0 0 0;
        }

        .status-badge-large {
          padding: 10px 20px;
          border-radius: 25px;
          font-size: 0.95rem;
          font-weight: 700;
        }

        .status-badge-large.assigned { background: rgba(108, 117, 125, 0.2); color: #6c757d; }
        .status-badge-large.picked_up { background: rgba(151, 231, 209, 0.3); color: #0d9488; }
        .status-badge-large.in_transit { background: rgba(124, 136, 224, 0.3); color: #4f46e5; }
        .status-badge-large.delivered { background: rgba(252, 142, 190, 0.3); color: #ec4899; }
        .status-badge-large.failed { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        /* Status Progress */
        .status-progress-card {
          background: white;
          border-radius: 15px;
          padding: 25px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .status-progress-card h2 {
          font-size: 1.3rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 25px;
        }

        .status-timeline {
          position: relative;
          display: flex;
          justify-content: space-between;
          padding: 30px 0;
        }

        .progress-line {
          position: absolute;
          top: 50px;
          left: 12.5%;
          right: 12.5%;
          height: 4px;
          background: #e5e7eb;
          z-index: 1;
        }

        .progress-line-fill {
          height: 100%;
          background: linear-gradient(90deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          transition: width 0.5s ease;
        }

        .status-step {
          position: relative;
          flex: 1;
          text-align: center;
          z-index: 2;
        }

        .status-icon {
          width: 70px;
          height: 70px;
          margin: 0 auto 12px;
          border-radius: 50%;
          background: #f6f7fb;
          color: #9ca3af;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 28px;
          transition: all 0.3s ease;
          border: 4px solid #f6f7fb;
        }

        .status-step.completed .status-icon,
        .status-step.active .status-icon {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          color: white;
          border-color: white;
          transform: scale(1.1);
          box-shadow: 0 8px 20px rgba(124, 136, 224, 0.4);
        }

        .status-label {
          font-size: 0.95rem;
          font-weight: 700;
          color: #9ca3af;
          margin-bottom: 5px;
        }

        .status-step.completed .status-label,
        .status-step.active .status-label {
          color: rgb(73, 57, 113);
        }

        .status-time {
          font-size: 0.85rem;
          color: #9ca3af;
        }

        /* Info Cards */
        .info-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 20px;
          margin-bottom: 20px;
        }

        .info-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .info-card:hover {
          transform: translateY(-5px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .info-card h3 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .info-card h3 i {
          font-size: 1.2rem;
        }

        .info-item {
          margin-bottom: 15px;
        }

        .info-label {
          font-size: 0.85rem;
          font-weight: 600;
          color: #6b7280;
          margin-bottom: 5px;
        }

        .info-value {
          font-size: 1rem;
          color: #484d53;
          font-weight: 500;
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

        .btn-info {
          background: #3b82f6;
          color: white;
        }

        .btn-sm {
          padding: 6px 12px;
          font-size: 0.85rem;
        }

        /* Order Items */
        .order-items-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .order-items-card h3 {
          font-size: 1.2rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 20px;
        }

        .product-item {
          display: flex;
          align-items: center;
          gap: 15px;
          padding: 15px;
          background: #f6f7fb;
          border-radius: 12px;
          margin-bottom: 12px;
          transition: all 0.3s ease;
        }

        .product-item:hover {
          transform: translateX(5px);
        }

        .product-image {
          width: 60px;
          height: 60px;
          border-radius: 10px;
          object-fit: cover;
          background: white;
        }

        .product-image-placeholder {
          width: 60px;
          height: 60px;
          border-radius: 10px;
          background: linear-gradient(135deg, #e5e7eb, #d1d5db);
          display: flex;
          align-items: center;
          justify-content: center;
          color: #6b7280;
          font-size: 24px;
        }

        .product-info {
          flex: 1;
        }

        .product-name {
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .product-details {
          font-size: 0.85rem;
          color: #6b7280;
        }

        .product-price {
          font-size: 1.1rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
        }

        .order-summary {
          border-top: 3px solid #e5e7eb;
          padding-top: 15px;
          margin-top: 15px;
        }

        .summary-row {
          display: flex;
          justify-content: space-between;
          margin-bottom: 10px;
          font-size: 0.95rem;
          color: #6b7280;
        }

        .summary-total {
          display: flex;
          justify-content: space-between;
          font-size: 1.3rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
          margin-top: 15px;
        }

        /* Notes */
        .notes-card {
          background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 197, 253, 0.1));
          border-left: 4px solid #3b82f6;
          border-radius: 12px;
          padding: 15px 20px;
          margin-bottom: 20px;
        }

        .notes-card h4 {
          font-size: 1rem;
          font-weight: 700;
          color: #1e40af;
          margin-bottom: 10px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .notes-card p {
          color: #1e3a8a;
          margin: 0;
          line-height: 1.6;
        }

        /* Actions */
        .actions-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .actions-card h3 {
          font-size: 1.2rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 20px;
        }

        .action-buttons {
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
        }

        .btn-lg {
          padding: 12px 24px;
          font-size: 1rem;
        }

        .btn-success {
          background: #10b981;
          color: white;
        }

        .btn-danger {
          background: #ef4444;
          color: white;
        }

        .success-message {
          background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(134, 239, 172, 0.1));
          border-left: 4px solid #10b981;
          border-radius: 12px;
          padding: 15px 20px;
          color: #065f46;
          display: flex;
          align-items: center;
          gap: 10px;
          font-weight: 600;
        }

        @keyframes pulseGlow {
  0% { box-shadow: 0 0 0 0 rgba(124,136,224, 0.5); }
  70% { box-shadow: 0 0 0 10px rgba(124,136,224, 0); }
  100% { box-shadow: 0 0 0 0 rgba(124,136,224, 0); }
}
.status-step.active .status-icon {
  animation: pulseGlow 2s infinite;
}


        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .info-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { margin: 15px; padding: 15px; }
          .status-timeline { flex-direction: column; gap: 20px; }
          .progress-line { display: none; }
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
            <div class="header-section">
                <div>
                    <a href="deliveries.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Deliveries
                    </a>
                    <div class="header-title">
                        <h1>Order #<?php echo htmlspecialchars($delivery['order_number']); ?></h1>
                    </div>
                </div>
                <span class="status-badge-large <?php echo $delivery['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                </span>
            </div>

            <!-- Status Progress -->
            <div class="status-progress-card">
                <h2><i class="fas fa-route"></i> Delivery Status</h2>
                <div class="status-timeline">
                    <div class="progress-line">
                        <div class="progress-line-fill" style="width: <?php echo (($current_status_position - 1) / 3) * 100; ?>%"></div>
                    </div>
                    <?php foreach ($status_steps as $status_key => $step): ?>
                        <div class="status-step <?php echo $step['position'] <= $current_status_position ? 'completed' : ''; ?> <?php echo $step['position'] == $current_status_position ? 'active' : ''; ?>">
                            <div class="status-icon">
                                <i class="fas <?php echo $step['icon']; ?>"></i>
                            </div>
                            <div class="status-label"><?php echo $step['label']; ?></div>
                            <?php 
                                $timestamp = null;
                                if ($status_key === 'assigned' && $delivery['assigned_at']) {
                                    $timestamp = $delivery['assigned_at'];
                                } elseif ($status_key === 'picked_up' && $delivery['picked_up_at']) {
                                    $timestamp = $delivery['picked_up_at'];
                                } elseif ($status_key === 'delivered' && $delivery['delivered_at']) {
                                    $timestamp = $delivery['delivered_at'];
                                } elseif ($status_key === 'in_transit' && $delivery['status'] === 'in_transit') {
                                    $timestamp = $delivery['updated_at'];
                                }
                                
                                if ($timestamp): 
                            ?>
                                <div class="status-time"><?php echo date('M d, g:i A', strtotime($timestamp)); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Customer & Address Info -->
            <div class="info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-user" style="color: #7c88e0;"></i> Customer Information</h3>
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($delivery['customer_phone'] ?: 'N/A'); ?>
                            <?php if ($delivery['customer_phone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($delivery['customer_phone']); ?>" class="btn btn-primary btn-sm" style="margin-left: 10px;">
                                    <i class="fas fa-phone"></i> Call
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($delivery['customer_email']); ?></div>
                    </div>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> Delivery Address</h3>
                    <div class="info-item">
                        <div class="info-value">
                            <?php 
                                echo htmlspecialchars($delivery['address_line_1']);
                                if ($delivery['address_line_2']) echo '<br>' . htmlspecialchars($delivery['address_line_2']);
                                echo '<br>' . htmlspecialchars($delivery['city'] . ', ' . $delivery['state']);
                                echo '<br>' . htmlspecialchars($delivery['postal_code']);
                                if ($delivery['country']) echo '<br>' . htmlspecialchars($delivery['country']);
                            ?>
                        </div>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($delivery['address_line_1'] . ', ' . $delivery['city']); ?>" target="_blank" class="btn btn-info btn-sm" style="margin-top: 10px;">
                        <i class="fas fa-map"></i> Open in Google Maps
                    </a>
                </div>
            </div>

            <!-- Order Items -->
            <div class="order-items-card">
                <h3><i class="fas fa-box"></i> Order Items (<?php echo count($order_items); ?>)</h3>
                <?php foreach ($order_items as $item): ?>
                    <div class="product-item">
                        <?php if ($item['product_image']): ?>
                            <img src="../<?php echo htmlspecialchars($item['product_image']); ?>" alt="Product" class="product-image">
                        <?php else: ?>
                            <div class="product-image-placeholder">
                                <i class="fas fa-box"></i>
                            </div>
                        <?php endif; ?>
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="product-details">
                                Quantity: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['unit_price'], 2); ?>
                            </div>
                        </div>
                        <div class="product-price">
                            $<?php echo number_format($item['total_price'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>$<?php echo number_format($delivery['subtotal'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span>$<?php echo number_format($delivery['tax_amount'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>$<?php echo number_format($delivery['shipping_amount'], 2); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Total Amount:</span>
                        <span>$<?php echo number_format($delivery['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Notes -->
            <?php if ($delivery['order_notes']): ?>
                <div class="notes-card">
                    <h4><i class="fas fa-sticky-note"></i> Order Notes</h4>
                    <p><?php echo nl2br(htmlspecialchars($delivery['order_notes'])); ?></p>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="actions-card">
                <h3><i class="fas fa-tasks"></i> Actions</h3>
                <?php if ($delivery['status'] === 'delivered'): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                        <span>This delivery has been completed successfully!</span>
                    </div>
                <?php elseif ($delivery['status'] === 'failed'): ?>
                    <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(252, 165, 165, 0.1)); border-left: 4px solid #ef4444; border-radius: 12px; padding: 15px 20px; color: #7f1d1d; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                        <span>This delivery was marked as failed.</span>
                    </div>
                <?php else: ?>
                    <div class="action-buttons">
                        <?php if ($delivery['status'] === 'assigned'): ?>
                            <button class="btn btn-primary btn-lg" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'picked_up')">
                                <i class="fas fa-box-open"></i> Pick Up Order
                            </button>
                        <?php elseif ($delivery['status'] === 'picked_up'): ?>
                            <button class="btn btn-primary btn-lg" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'in_transit')">
                                <i class="fas fa-truck"></i> Start Delivery
                            </button>
                        <?php elseif ($delivery['status'] === 'in_transit'): ?>
                            <button class="btn btn-success btn-lg" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'delivered')">
                                <i class="fas fa-check"></i> Mark as Delivered
                            </button>
                            <button class="btn btn-danger btn-lg" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'failed')">
                                <i class="fas fa-times"></i> Mark as Failed
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
                const href = link.getAttribute('href');
                if (href && (href === currentPage || (href === 'deliveries.php' && currentPage.includes('delivery')))) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });
        });

       function updateDeliveryStatus(deliveryId, newStatus) {
    fetch('update_delivery_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ delivery_id: deliveryId, status: newStatus })
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
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ delivery_id: deliveryId, status: newStatus })
    })
    .then(res => res.text())
    .then(text => {
      console.log('Raw response:', text);
      let data;
      try { data = JSON.parse(text); }
      catch (e) {
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
                padding: 15px 25px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                border-radius: 12px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                font-weight: 600;
                font-size: 1rem;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" style="font-size: 20px;"></i>
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