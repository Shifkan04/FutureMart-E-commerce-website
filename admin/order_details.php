<?php
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

checkSessionTimeout();

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$orderId = (int)$_GET['id'];

// Get order details with customer info
$stmt = $pdo->prepare("
    SELECT o.*, 
           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           u.email as customer_email,
           u.phone as customer_phone,
           sa.address_line_1, sa.address_line_2, sa.city, sa.state, 
           sa.postal_code, sa.country
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN user_addresses sa ON o.shipping_address_id = sa.id
    WHERE o.id = ?
");

$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            padding: 0;
            margin: 0;
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
            background-attachment: fixed;
        }

        main {
            display: grid;
            grid-template-columns: 13% 87%;
            width: 100%;
            margin: 40px;
            background: rgb(254, 254, 254);
            box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset, 0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
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

        nav {
            user-select: none;
        }

        nav ul,
        nav ul li {
            outline: 0;
        }

        nav ul li a {
            text-decoration: none;
        }

        .nav-item {
            position: relative;
            display: block;
        }

        .nav-item a {
            position: relative;
            display: flex;
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
            margin-bottom: 30px;
        }

        .header-section h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #484d53;
        }

        .header-section h2 i {
            margin-right: 10px;
            color: rgb(124, 136, 224);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
            color: #484d53;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(124, 136, 224, 0.3);
        }

        .btn-outline {
            background: white;
            color: rgb(124, 136, 224);
            border: 2px solid rgb(124, 136, 224);
        }

        .btn-outline:hover {
            background: rgba(124, 136, 224, 0.1);
            transform: translateY(-2px);
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
        }

        .info-card h5 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #484d53;
            margin-bottom: 20px;
            border-bottom: 2px solid #f6f7fb;
            padding-bottom: 10px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 40% 60%;
            padding: 10px 0;
            border-bottom: 1px solid #f6f7fb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 700;
            color: #484d53;
        }

        .info-value {
            color: #64748b;
        }

        .badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .badge.success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .badge.info {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .items-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
            margin-bottom: 20px;
        }

        .items-card h5 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #484d53;
            margin-bottom: 20px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead {
            background: #f6f7fb;
        }

        .items-table th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #484d53;
            font-size: 0.9rem;
        }

        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #f6f7fb;
            color: #64748b;
        }

        .items-table tfoot td {
            padding: 12px 15px;
            font-weight: 600;
            color: #484d53;
        }

        .items-table tfoot tr:last-child {
            background: linear-gradient(135deg, rgba(124, 136, 224, 0.1), rgba(195, 244, 252, 0.1));
        }

        .items-table tfoot tr:last-child td {
            font-weight: 700;
            font-size: 1.1rem;
            color: rgb(73, 57, 113);
        }

        .timeline-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
        }

        .timeline-card h5 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #484d53;
            margin-bottom: 25px;
        }

        .order-timeline {
            border-left: 3px solid rgb(124, 136, 224);
            padding-left: 30px;
            margin-left: 15px;
        }

        .timeline-item {
            margin-bottom: 30px;
            position: relative;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -37px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: rgb(124, 136, 224);
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(124, 136, 224, 0.4);
        }

        .timeline-item strong {
            color: #484d53;
            font-size: 1.1rem;
            display: block;
            margin-bottom: 5px;
        }

        .timeline-item small {
            color: #94a3b8;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 8px;
        }

        .timeline-item p {
            color: #64748b;
            margin: 0;
            line-height: 1.6;
        }

        .address-section {
            background: #f6f7fb;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .address-section address {
            margin: 0;
            color: #64748b;
            line-height: 1.8;
            font-style: normal;
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

        @media print {
            body {
                background: white;
                display: block;
            }

            main {
                display: block;
                margin: 0;
                padding: 20px;
                box-shadow: none;
                background: white;
                width: 100%;
            }

            .no-print,
            .main-menu {
                display: none !important;
            }

            .content {
                margin: 0;
                padding: 0;
                max-height: none;
                background: white;
                overflow: visible;
            }

            .order-grid {
                display: block;
                page-break-inside: avoid;
            }

            .info-card {
                page-break-inside: avoid;
                margin-bottom: 20px;
                border: 1px solid #e2e8f0;
            }

            .items-card {
                page-break-inside: avoid;
                margin-bottom: 20px;
                border: 1px solid #e2e8f0;
            }

            .timeline-card {
                page-break-inside: avoid;
                border: 1px solid #e2e8f0;
            }

            .items-table {
                page-break-inside: auto;
            }

            .items-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .items-table thead {
                display: table-header-group;
            }

            .items-table tfoot {
                display: table-footer-group;
            }

            /* Print specific colors */
            .badge.success {
                background: #d1fae5 !important;
                color: #065f46 !important;
                border: 1px solid #10b981;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge.warning {
                background: #fef3c7 !important;
                color: #92400e !important;
                border: 1px solid #f59e0b;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge.info {
                background: #dbeafe !important;
                color: #1e3a8a !important;
                border: 1px solid #3b82f6;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge.danger {
                background: #fee2e2 !important;
                color: #991b1b !important;
                border: 1px solid #ef4444;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .items-table tfoot tr:last-child {
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .timeline-item::before {
                background: rgb(124, 136, 224) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .order-timeline {
                border-left-color: rgb(124, 136, 224) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            h2,
            h5 {
                color: #1f2937 !important;
            }

            .info-label,
            .info-value {
                color: #374151 !important;
            }

            /* Add company header for print */
            .content::before {
                content: "<?php echo APP_NAME ?? 'Order Invoice'; ?>";
                display: block;
                font-size: 24px;
                font-weight: 700;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e2e8f0;
            }
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
        }

        @media (max-width: 1310px) {
            main {
                grid-template-columns: 8% 92%;
                margin: 30px;
            }
        }

        @media (max-width: 910px) {
            main {
                grid-template-columns: 10% 90%;
                margin: 20px;
            }

            .order-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            main {
                grid-template-columns: 15% 85%;
            }

            .action-buttons {
                flex-direction: column;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 5px;
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
                    </a>
                </li>

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="vendors.php">
                        <i class="fa fa-users-cog nav-icon"></i>
                        <span class="nav-text">Vendors</span>
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

        <section class="content">
            <div class="header-section no-print">
                <h2><i class="fas fa-file-invoice"></i>Order Details</h2>
                <div class="action-buttons">
                    <button class="btn btn-outline" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>

            <div class="order-grid">
                <div class="info-card">
                    <h5><i class="fas fa-shopping-bag" style="margin-right: 8px; color: rgb(124, 136, 224);"></i>Order Information</h5>
                    <div class="info-row">
                        <span class="info-label">Order ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="badge <?php
                                                echo $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'processing' ? 'info' : ($order['status'] === 'delivered' ? 'success' : 'danger'));
                                                ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment:</span>
                        <span class="info-value">
                            <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php if ($order['tracking_number']): ?>
                        <div class="info-row">
                            <span class="info-label">Tracking:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h5><i class="fas fa-user" style="margin-right: 8px; color: rgb(124, 136, 224);"></i>Customer Information</h5>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></span>
                    </div>

                    <?php if ($order['address_line_1']): ?>
                        <div style="margin-top: 20px;">
                            <strong class="info-label" style="display: block; margin-bottom: 10px;">
                                <i class="fas fa-map-marker-alt" style="margin-right: 8px; color: rgb(124, 136, 224);"></i>Shipping Address
                            </strong>
                            <div class="address-section">
                                <address>
                                    <?php echo htmlspecialchars($order['address_line_1']); ?><br>
                                    <?php if ($order['address_line_2']): ?>
                                        <?php echo htmlspecialchars($order['address_line_2']); ?><br>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($order['city']); ?>,
                                    <?php echo htmlspecialchars($order['state']); ?>
                                    <?php echo htmlspecialchars($order['postal_code']); ?><br>
                                    <?php echo htmlspecialchars($order['country']); ?>
                                </address>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="items-card">
                <h5><i class="fas fa-list" style="margin-right: 8px; color: rgb(124, 136, 224);"></i>Order Items</h5>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name'] ?? 'Product N/A'); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td><strong>$<?php echo number_format($item['total_price'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right;">Subtotal:</td>
                            <td>$<?php echo number_format($order['subtotal'], 2); ?></td>
                        </tr>
                        <?php if ($order['tax_amount'] > 0): ?>
                            <tr>
                                <td colspan="3" style="text-align: right;">Tax:</td>
                                <td>$<?php echo number_format($order['tax_amount'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($order['shipping_amount'] > 0): ?>
                            <tr>
                                <td colspan="3" style="text-align: right;">Shipping:</td>
                                <td>$<?php echo number_format($order['shipping_amount'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($order['discount_amount'] > 0): ?>
                            <tr>
                                <td colspan="3" style="text-align: right;">Discount:</td>
                                <td style="color: #10b981;">-$<?php echo number_format($order['discount_amount'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>Total Amount:</strong></td>
                            <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($order['notes']): ?>
                <div class="info-card" style="margin-bottom: 20px;">
                    <h5><i class="fas fa-sticky-note" style="margin-right: 8px; color: rgb(124, 136, 224);"></i>Order Notes</h5>
                    <p style="color: #64748b; line-height: 1.8; margin: 0;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="timeline-card">
                <h5><i class="fas fa-history" style="margin-right: 8px; color: rgb(124, 136, 224);"></i>Order Timeline</h5>
                <div class="order-timeline">
                    <div class="timeline-item">
                        <strong>Order Placed</strong>
                        <small><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></small>
                        <p>Order received and confirmed</p>
                    </div>

                    <?php if ($order['payment_status'] === 'paid'): ?>
                        <div class="timeline-item">
                            <strong>Payment Processed</strong>
                            <small><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></small>
                            <p>Payment successfully processed</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'processing' || $order['status'] === 'shipped' || $order['status'] === 'delivered'): ?>
                        <div class="timeline-item">
                            <strong>Order Processing</strong>
                            <small><?php echo date('M d, Y h:i A', strtotime($order['updated_at'])); ?></small>
                            <p>Order is being prepared for shipment</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'shipped' || $order['status'] === 'delivered'): ?>
                        <div class="timeline-item">
                            <strong>Order Shipped</strong>
                            <small><?php echo $order['shipped_at'] ? date('M d, Y h:i A', strtotime($order['shipped_at'])) : 'N/A'; ?></small>
                            <p>Order has been shipped</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'delivered'): ?>
                        <div class="timeline-item">
                            <strong>Order Delivered</strong>
                            <small><?php echo $order['delivered_at'] ? date('M d, Y h:i A', strtotime($order['delivered_at'])) : 'N/A'; ?></small>
                            <p>Order successfully delivered to customer</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['status'] === 'cancelled'): ?>
                        <div class="timeline-item">
                            <strong>Order Cancelled</strong>
                            <small><?php echo date('M d, Y h:i A', strtotime($order['updated_at'])); ?></small>
                            <p>Order was cancelled</p>
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