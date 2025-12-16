<?php
require_once '../config.php';

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}

$delivery_person_id = $_SESSION['user_id'];

// Fetch delivery person details
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT da.id) as total_deliveries,
               COUNT(DISTINCT CASE WHEN da.status = 'assigned' THEN da.id END) as pending_deliveries,
               COUNT(DISTINCT CASE WHEN da.status = 'in_transit' THEN da.id END) as on_way_deliveries,
               COUNT(DISTINCT CASE WHEN da.status = 'delivered' THEN da.id END) as delivered_count
        FROM users u
        LEFT JOIN delivery_assignments da ON u.id = da.delivery_person_id
        WHERE u.id = ? AND u.role = 'delivery'
        GROUP BY u.id
    ");
    $stmt->execute([$delivery_person_id]);
    $delivery_person = $stmt->fetch();

    if (!$delivery_person) {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Fetch today's active deliveries
    $stmt = $pdo->prepare("
        SELECT 
            da.*,
            o.order_number,
            o.total_amount,
            o.created_at as order_date,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name,
            u.phone as customer_phone,
            ua.address_line_1,
            ua.address_line_2,
            ua.city,
            ua.state,
            ua.postal_code
        FROM delivery_assignments da
        INNER JOIN orders o ON da.order_id = o.id
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
        WHERE da.delivery_person_id = ?
        AND da.status IN ('assigned', 'picked_up', 'in_transit')
        ORDER BY da.assigned_at DESC
        LIMIT 6
    ");
    $stmt->execute([$delivery_person_id]);
    $active_deliveries = $stmt->fetchAll();

    // Fetch recent completed deliveries
    $stmt = $pdo->prepare("
        SELECT 
            da.*,
            o.order_number,
            o.total_amount,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name
        FROM delivery_assignments da
        INNER JOIN orders o ON da.order_id = o.id
        INNER JOIN users u ON o.user_id = u.id
        WHERE da.delivery_person_id = ?
        AND da.status = 'delivered'
        ORDER BY da.delivered_at DESC
        LIMIT 4
    ");
    $stmt->execute([$delivery_person_id]);
    $recent_completed = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    die("An error occurred while loading your dashboard.");
}

$current_date = date('F d, Y');

function getStatusColor($status) {
    $colors = [
        'assigned' => 'activity-two',
        'picked_up' => 'activity-three',
        'in_transit' => 'activity-one',
        'delivered' => 'activity-four'
    ];
    return $colors[$status] ?? 'activity-two';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - <?php echo APP_NAME; ?></title>
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
          grid-template-rows: 40% 60%;
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

        .deliveries-section {
          margin-top: 20px;
        }

        .deliveries-section h1 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 15px;
        }

        .delivery-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          grid-template-rows: repeat(2, 180px);
          gap: 10px;
        }

        .delivery-card {
          background: white;
          border-radius: 15px;
          padding: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          transition: transform 0.3s ease;
        }

        .delivery-card:hover {
          transform: translateY(-5px);
        }

        .activity-one {
          background: linear-gradient(240deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
        }

        .activity-two {
          background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%);
        }

        .activity-three {
          background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%);
        }

        .activity-four {
          background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%);
        }

        .delivery-card h3 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 8px;
        }

        .delivery-card p {
          font-size: 0.85rem;
          color: #484d53;
          margin-bottom: 5px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .delivery-card p i {
          font-size: 0.9rem;
        }

        .btn {
          display: inline-block;
          padding: 8px 16px;
          font-size: 0.9rem;
          font-weight: 600;
          background: rgba(255, 255, 255, 0.9);
          color: #484d53;
          border: 1px solid rgba(255, 255, 255, 0.3);
          border-radius: 25px;
          cursor: pointer;
          text-decoration: none;
          transition: all 0.3s ease;
          text-align: center;
        }

        .btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1);
        }

        .btn i {
          margin-right: 5px;
        }

        .right-content {
          display: grid;
          grid-template-rows: 5% 25%;
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

        .active-deliveries {
          display: flex;
          flex-direction: column;
          align-items: center;
          background: rgb(214, 227, 248);
          padding: 15px 10px;
          margin: 15px 10px 0;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .active-deliveries h1 {
          margin-top: 10px;
          font-size: 1.2rem;
          align-self: flex-start;
          margin-left: 5px;
          color: #484d53;
        }

        .active-deliveries-container {
          display: flex;
          flex-direction: row;
          align-items: center;
          gap: 10px;
          width: 100%;
        }

        .box {
          display: flex;
          justify-content: center;
          align-items: center;
          flex-direction: column;
          position: relative;
          padding: 10px 0;
        }

        .box h2 {
          position: relative;
          text-align: center;
          font-size: 1.25rem;
          color: rgb(91, 95, 111);
          font-weight: 600;
        }

        .box h2 small {
          font-size: 0.8rem;
          font-weight: 600;
        }

        .circle {
          position: relative;
          width: 80px;
          aspect-ratio: 1/1;
          background: conic-gradient(
            from 0deg,
            #590b94 0%,
            #590b94 0% var(--i),
            #b3b2b2 var(--i),
            #b3b2b2 100%
          );
          border-radius: 50%;
          display: flex;
          justify-content: center;
          align-items: center;
        }

        .circle::before {
          content: "";
          position: absolute;
          inset: 10px;
          background: rgb(214, 227, 248);
          border-radius: 50%;
        }

        .deliveries-content {
          flex: 1;
        }

        .deliveries-content p {
          font-size: 0.95rem;
          margin-bottom: 5px;
          color: #484d53;
        }

        .deliveries-content p span {
          font-weight: 700;
        }

        .recent-completed {
          padding: 15px 10px;
        }

        .recent-completed h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .completed-list {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .completed-item {
          background: white;
          border-radius: 12px;
          padding: 12px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .completed-item h3 {
          font-size: 0.95rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .completed-item p {
          font-size: 0.85rem;
          color: #6b7280;
          margin-bottom: 3px;
        }

        .empty-state {
          text-align: center;
          padding: 40px 20px;
          color: #9ca3af;
        }

        .empty-state i {
          font-size: 48px;
          opacity: 0.3;
          display: block;
          margin-bottom: 10px;
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
          .content { grid-template-columns: 70% 30%; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .content { grid-template-columns: 65% 35%; }
          .stats-grid { grid-template-columns: repeat(2, 1fr); }
          .delivery-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .content { grid-template-columns: 55% 45%; }
          .delivery-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { grid-template-columns: 100%; grid-template-rows: 45% 55%; }
          .left-content { grid-template-rows: 50% 50%; margin: 0 15px 15px 15px; }
          .right-content { margin: 15px; }
          .stats-grid { grid-template-columns: 1fr; }
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
                <li class="nav-item active">
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
                    <a href="deliveries.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">My Deliveries</span>
                        <?php if ($delivery_person['pending_deliveries'] > 0): ?>
                        <span class="notification-badge"><?php echo $delivery_person['pending_deliveries']; ?></span>
                        <?php endif; ?>
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
                <div class="stats-section">
                    <h1>Delivery Dashboard Overview</h1>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-box"></i>
                            <div>
                                <h3><?php echo number_format($delivery_person['total_deliveries'] ?: 0); ?></h3>
                                <p>Total Deliveries</p>
                                <small><i class="fas fa-info-circle"></i> All time</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h3><?php echo number_format($delivery_person['pending_deliveries'] ?: 0); ?></h3>
                                <p>Pending</p>
                                <small><i class="fas fa-hourglass-half"></i> Awaiting pickup</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-truck"></i>
                            <div>
                                <h3><?php echo number_format($delivery_person['on_way_deliveries'] ?: 0); ?></h3>
                                <p>On the Way</p>
                                <small><i class="fas fa-shipping-fast"></i> In transit</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h3><?php echo number_format($delivery_person['delivered_count'] ?: 0); ?></h3>
                                <p>Delivered</p>
                                <small><i class="fas fa-thumbs-up"></i> Completed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="deliveries-section">
                    <h1>Active Deliveries</h1>
                    <?php if (empty($active_deliveries)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <span>No active deliveries at the moment</span>
                        </div>
                    <?php else: ?>
                        <div class="delivery-grid">
                            <?php foreach ($active_deliveries as $delivery): ?>
                                <div class="delivery-card <?php echo getStatusColor($delivery['status']); ?>">
                                    <div>
                                        <h3>Order #<?php echo htmlspecialchars($delivery['order_number']); ?></h3>
                                        <p>
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($delivery['city']); ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($delivery['customer_phone'] ?: 'N/A'); ?>
                                        </p>
                                    </div>
                                    <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" class="btn">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
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

                <div class="active-deliveries">
                    <h1>Today's Progress</h1>
                    <div class="active-deliveries-container">
                        <?php 
                        $total = $delivery_person['total_deliveries'] ?: 1;
                        $completed = $delivery_person['delivered_count'] ?: 0;
                        $percentage = round(($completed / $total) * 100);
                        ?>
                        <div class="box" style="--i: <?php echo $percentage; ?>%">
                            <div class="circle">
                                <h2><?php echo $percentage; ?><small>%</small></h2>
                            </div>
                        </div>
                        <div class="deliveries-content">
                            <p><span>Pending:</span> <?php echo $delivery_person['pending_deliveries'] ?: 0; ?></p>
                            <p><span>On the Way:</span> <?php echo $delivery_person['on_way_deliveries'] ?: 0; ?></p>
                            <p><span>Delivered:</span> <?php echo $delivery_person['delivered_count'] ?: 0; ?></p>
                        </div>
                    </div>
                </div>

                <div class="recent-completed">
                    <h1>Recent Completed</h1>
                    <?php if (empty($recent_completed)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <span>No completed deliveries yet</span>
                        </div>
                    <?php else: ?>
                        <div class="completed-list">
                            <?php foreach ($recent_completed as $completed): ?>
                                <div class="completed-item">
                                    <h3>Order #<?php echo htmlspecialchars($completed['order_number']); ?></h3>
                                    <p>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($completed['customer_first_name'] . ' ' . $completed['customer_last_name']); ?>
                                    </p>
                                    <p>
                                        <i class="fas fa-dollar-sign"></i>
                                        $<?php echo number_format($completed['total_amount'], 2); ?>
                                    </p>
                                    <p>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M d, g:i A', strtotime($completed['delivered_at'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

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

        function updateDeliveryStatus(deliveryId, newStatus) {
            if (!confirm('Are you sure you want to update the delivery status?')) {
                return;
            }

            fetch('update_delivery_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    delivery_id: deliveryId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Delivery status updated successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert(data.message || 'Failed to update status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating status');
            });
        }
    </script>
</body>
</html>