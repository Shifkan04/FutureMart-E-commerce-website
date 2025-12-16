<?php
require_once '../config.php';
requireAdmin();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'assign_delivery':
                $orderId = filter_var($_POST['order_id'], FILTER_SANITIZE_NUMBER_INT);
                $deliveryBoy = sanitizeInput($_POST['delivery_boy']);
                
                $stmt = $pdo->prepare("UPDATE orders SET tracking_number = ?, status = 'processing' WHERE id = ?");
                $stmt->execute([$deliveryBoy, $orderId]);
                
                logUserActivity($_SESSION['user_id'], 'delivery_assign', "Assigned delivery boy $deliveryBoy to order #$orderId");
                
                echo json_encode(['success' => true, 'message' => 'Delivery assigned successfully']);
                exit;
                
            case 'update_status':
                $orderId = filter_var($_POST['order_id'], FILTER_SANITIZE_NUMBER_INT);
                $status = sanitizeInput($_POST['status']);
                
                $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                if (!in_array($status, $validStatuses)) {
                    throw new Exception('Invalid status');
                }
                
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $orderId]);
                
                if ($status === 'delivered') {
                    $stmt = $pdo->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ?");
                    $stmt->execute([$orderId]);
                }
                
                logUserActivity($_SESSION['user_id'], 'delivery_status_update', "Updated order #$orderId status to $status");
                
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                exit;
                
            case 'get_delivery_details':
                $orderId = filter_var($_POST['order_id'], FILTER_SANITIZE_NUMBER_INT);
                
                $stmt = $pdo->prepare("
                    SELECT o.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                           u.email as customer_email,
                           u.phone as customer_phone,
                           ua.address_line_1, ua.address_line_2, ua.city, ua.state, ua.postal_code, ua.country
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
                    WHERE o.id = ?
                ");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();
                
                if (!$order) {
                    throw new Exception('Order not found');
                }
                
                $stmt = $pdo->prepare("
                    SELECT oi.*, p.name as product_name 
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$orderId]);
                $order['items'] = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'data' => $order]);
                exit;
                
            case 'export_deliveries':
                $stmt = $pdo->query("
                    SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
                           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                           u.email, u.phone,
                           o.tracking_number,
                           CONCAT(ua.address_line_1, ', ', ua.city, ', ', ua.state) as address
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
                    ORDER BY o.created_at DESC
                ");
                $deliveries = $stmt->fetchAll();
                
                logUserActivity($_SESSION['user_id'], 'delivery_export', 'Exported ' . count($deliveries) . ' deliveries to CSV');
                
                echo json_encode(['success' => true, 'data' => $deliveries]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get filter parameters
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if (!empty($statusFilter)) {
    $whereConditions[] = "o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalOrders = $stmt->fetch()['total'];
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$query = "
    SELECT o.*, 
           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           u.email as customer_email,
           CONCAT(ua.address_line_1, ', ', ua.city) as delivery_address
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    $whereClause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get delivery statistics
$stats = [
    'active' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('processing', 'shipped')")->fetchColumn(),
    'delivered_today' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND DATE(delivered_at) = CURDATE()")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn()
];

// Get delivery personnel
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role = 'delivery' AND status = 'active'");
$deliveryPersonnel = $stmt->fetchAll();

// Get admin details
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");
        *,*::before,*::after{box-sizing:border-box;padding:0;margin:0}
        nav ul,nav ul li{outline:0}
        nav ul li a{text-decoration:none}
        body{font-family:"Nunito",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background-image:url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);background-repeat:no-repeat;background-size:cover}
        main{display:grid;grid-template-columns:13% 87%;width:100%;margin:40px;background:rgb(254,254,254);box-shadow:0 .5px 0 1px rgba(255,255,255,.23)inset,0 1px 0 0 rgba(255,255,255,.66)inset,0 4px 16px rgba(0,0,0,.12);border-radius:15px;z-index:10}
        .main-menu{overflow:hidden;background:rgb(73,57,113);padding-top:10px;border-radius:15px 0 0 15px;padding-bottom: 20px;}
        .main-menu h1 {display: block;font-size: 1.5rem;font-weight: 500; text-align: center; margin: 0;color: #fff;font-family: "Nunito", sans-serif;padding-top: 20px;}
        .main-menu small {display: block;font-size: 1rem;font-weight: 300;text-align: center;margin: 10px 0;color: #fff; }        
        .logo{display:none;width:30px;margin:20px auto}
        .nav-item{position:relative;display:block}
        .nav-item a{position:relative;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;padding:15px 0;margin-left:10px;border-top-left-radius:20px;border-bottom-left-radius:20px}
        .nav-item b:nth-child(1),.nav-item b:nth-child(2){position:absolute;height:15px;width:100%;background:#fff;display:none}
        .nav-item b:nth-child(1){top:-15px}
        .nav-item b:nth-child(2){bottom:-15px}
        .nav-item b:nth-child(1)::before,.nav-item b:nth-child(2)::before{content:"";position:absolute;top:0;left:0;width:100%;height:100%;background:rgb(73,57,113)}
        .nav-item b:nth-child(1)::before{border-bottom-right-radius:20px}
        .nav-item b:nth-child(2)::before{border-top-right-radius:20px}
        .nav-item.active b:nth-child(1),.nav-item.active b:nth-child(2){display:block}
        .nav-item.active a{color:#000;background:rgb(254,254,254)}
        .nav-icon{width:60px;height:20px;font-size:20px;text-align:center}
        .nav-text{display:block;width:120px;height:20px}
        .notification-badge{position:absolute;top:10px;right:20px;background:#ef4444;color:white;border-radius:50%;padding:2px 6px;font-size:11px;font-weight:700}
        .content{background:#f6f7fb;padding:20px;border-radius:0 15px 15px 0;overflow-y:auto;max-height:calc(100vh - 80px)}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .header h1{font-size:1.8rem;font-weight:700;color:#484d53}
        .user-section{display:flex;align-items:center;gap:15px}
        .user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,rgb(124,136,224),#c3f4fc);display:flex;align-items:center;justify-content:center;font-weight:700;color:white;font-size:16px}
        .btn{padding:10px 20px;border:none;border-radius:12px;font-weight:600;cursor:pointer;transition:all .3s ease;font-size:.9rem}
        .btn-primary{background:rgb(73,57,113);color:white}
        .btn-primary:hover{background:rgb(93,77,133);transform:translateY(-2px)}
        .btn-success{background:linear-gradient(240deg,#97e7d1 0%,#ecfcc3 100%);color:#484d53}
        .btn-warning{background:linear-gradient(240deg,#e5a243ab 0%,#f7f7aa 90%);color:#484d53}
        .btn-danger{background:linear-gradient(240deg,#fc8ebe 0%,#fce5c3 100%);color:#484d53}
        .btn-sm{padding:6px 12px;font-size:.85rem}
        .filter-section{background:white;padding:20px;border-radius:15px;box-shadow:rgba(0,0,0,.16)0 1px 3px;margin-bottom:20px}
        .filter-section .form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px}
        .form-group{display:flex;flex-direction:column}
        .form-group label{font-weight:600;color:#484d53;margin-bottom:5px;font-size:.9rem}
        .form-control{padding:10px;border:1px solid #e0e0e0;border-radius:8px;font-size:.9rem}
        .form-control:focus{outline:none;border-color:rgb(124,136,224)}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
        .stat-card{background:white;padding:20px;border-radius:15px;box-shadow:rgba(0,0,0,.16)0 1px 3px;text-align:center}
        .stat-card.active{background:linear-gradient(135deg,rgb(124,136,224)0%,#c3f4fc 100%)}
        .stat-card.delivered{background:linear-gradient(240deg,#97e7d1 0%,#ecfcc3 100%)}
        .stat-card.pending{background:linear-gradient(240deg,#e5a243ab 0%,#f7f7aa 90%)}
        .stat-card.cancelled{background:linear-gradient(240deg,#fc8ebe 0%,#fce5c3 100%)}
        .stat-card i{font-size:2rem;color:#484d53;margin-bottom:10px}
        .stat-card h4{font-size:2rem;font-weight:700;color:#484d53;margin:5px 0}
        .stat-card p{font-size:.9rem;color:#484d53}
        .delivery-section{background:white;padding:20px;border-radius:15px;box-shadow:rgba(0,0,0,.16)0 1px 3px}
        .delivery-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .delivery-header h2{font-size:1.3rem;font-weight:700;color:#484d53}
        .delivery-table{width:100%;border-collapse:collapse}
        .delivery-table th{text-align:left;padding:12px;font-weight:600;font-size:.9rem;color:#484d53;border-bottom:2px solid #f6f7fb;background:#f6f7fb}
        .delivery-table td{padding:12px;font-size:.9rem;color:#484d53;border-bottom:1px solid #f6f7fb;vertical-align:middle}
        .delivery-table tr:hover{background:#f6f7fb}
        .badge{display:inline-block;padding:4px 12px;border-radius:12px;font-size:.75rem;font-weight:600}
        .badge-success{background:rgba(16,185,129,.2);color:#10b981}
        .badge-warning{background:rgba(245,158,11,.2);color:#f59e0b}
        .badge-danger{background:rgba(239,68,68,.2);color:#ef4444}
        .badge-info{background:rgba(59,130,246,.2);color:#3b82f6}
        .badge-primary{background:rgba(124,136,224,.2);color:rgb(73,57,113)}
        .pagination{display:flex;justify-content:center;gap:10px;margin-top:20px}
        .pagination button{padding:8px 15px;border:none;background:#f6f7fb;color:#484d53;border-radius:8px;cursor:pointer;font-weight:600}
        .pagination button.active{background:rgb(124,136,224);color:white}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;justify-content:center;align-items:center}
        .modal.show{display:flex}
        .modal-content{background:white;padding:30px;border-radius:15px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-header h2{font-size:1.5rem;font-weight:700;color:#484d53}
        .close{font-size:2rem;font-weight:300;color:#484d53;cursor:pointer;border:none;background:none}
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
        @media(max-width:1500px){main{grid-template-columns:6% 94%}.main-menu h1{display:none}.logo{display:block}.nav-text{display:none}}
        @media(max-width:910px){main{grid-template-columns:10% 90%;margin:20px}}
        @media(max-width:700px){main{grid-template-columns:15% 85%}.delivery-table{font-size:.8rem}.header{flex-direction:column;align-items:flex-start;gap:15px}.stats-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><i class="fas fa-rocket" style="margin-right: 8px;"></i><?php echo APP_NAME;?></h1>
                 <small>Admin Panel</small>
            <div class="logo"><i class="fa fa-rocket" style="font-size:24px;color:white"></i></div>
            <ul>
                <li class="nav-item"><b></b><b></b><a href="dashboard.php"><i class="fa fa-home nav-icon"></i><span class="nav-text">Dashboard</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="products.php"><i class="fa fa-box nav-icon"></i><span class="nav-text">Products</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="orders.php"><i class="fa fa-shopping-cart nav-icon"></i><span class="nav-text">Orders</span><?php if($pendingOrders>0):?><span class="notification-badge"><?php echo $pendingOrders;?></span><?php endif;?></a></li>
                <li class="nav-item"><b></b><b></b><a href="vendors.php"><i class="fa fa-users-cog nav-icon"></i><span class="nav-text">Vendors</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="users.php"><i class="fa fa-users nav-icon"></i><span class="nav-text">Users</span></a></li>
                <li class="nav-item active"><b></b><b></b><a href="delivery.php"><i class="fa fa-truck nav-icon"></i><span class="nav-text">Delivery</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="analytics.php"><i class="fa fa-chart-bar nav-icon"></i><span class="nav-text">Analytics</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="testimonials.php"><i class="fa fa-star nav-icon"></i><span class="nav-text">Testimonials</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="notifications.php"><i class="fa fa-bell nav-icon"></i><span class="nav-text">Notifications</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="contact.php"><i class="fa fa-envelope nav-icon"></i><span class="nav-text">Contact</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="settings.php"><i class="fa fa-cog nav-icon"></i><span class="nav-text">Settings</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="#" onclick="confirmLogout(event)"><i class="fa fa-sign-out-alt nav-icon"></i><span class="nav-text">Logout</span></a></li>
    </nav>           

        <div class="content">
            <div class="header">
                <h1><i class="fas fa-truck"></i> Delivery Management</h1>
                <div class="user-section">
                    <button class="btn btn-primary" onclick="exportDeliveries()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <?php if(!empty($admin['avatar'])):?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']);?>" alt="Admin" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                    <?php else:?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin['first_name'],0,1));?>
                        </div>
                    <?php endif;?>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" name="status" id="status">
                                <option value="">All Status</option>
                                <option value="pending"<?php echo $statusFilter==='pending'?' selected':'';?>>Pending</option>
                                <option value="processing"<?php echo $statusFilter==='processing'?' selected':'';?>>Processing</option>
                                <option value="shipped"<?php echo $statusFilter==='shipped'?' selected':'';?>>Shipped</option>
                                <option value="delivered"<?php echo $statusFilter==='delivered'?' selected':'';?>>Delivered</option>
                                <option value="cancelled"<?php echo $statusFilter==='cancelled'?' selected':'';?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" class="form-control" name="search" id="search" value="<?php echo htmlspecialchars($searchQuery);?>" placeholder="Search deliveries...">
                        </div>
                        <div class="form-group" style="justify-content:flex-end">
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
                    <i class="fas fa-shipping-fast"></i>
                    <h4><?php echo $stats['active'];?></h4>
                    <p>Active Deliveries</p>
                </div>
                <div class="stat-card delivered">
                    <i class="fas fa-check-circle"></i>
                    <h4><?php echo $stats['delivered_today'];?></h4>
                    <p>Delivered Today</p>
                </div>
                <div class="stat-card pending">
                    <i class="fas fa-clock"></i>
                    <h4><?php echo $stats['pending'];?></h4>
                    <p>Pending Assignments</p>
                </div>
                <div class="stat-card cancelled">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4><?php echo $stats['cancelled'];?></h4>
                    <p>Cancelled Orders</p>
                </div>
            </div>

            <!-- Delivery Table -->
            <div class="delivery-section">
                <div class="delivery-header">
                    <h2>Deliveries (<?php echo number_format($totalOrders);?>)</h2>
                    <button class="btn btn-primary btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                <div style="overflow-x:auto">
                    <table class="delivery-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Address</th>
                                <th>Delivery Person</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($orders)):?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px">
                                    <i class="fas fa-truck" style="font-size:48px;opacity:.3;display:block;margin-bottom:10px"></i>
                                    <span style="opacity:.6">No deliveries found</span>
                                </td>
                            </tr>
                            <?php else:?>
                                <?php foreach($orders as $order):?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number']);?></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']);?><br>
                                        <small style="color:#999"><?php echo htmlspecialchars($order['customer_email']);?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['delivery_address']??'N/A');?></td>
                                    <td><?php echo htmlspecialchars($order['tracking_number']??'Unassigned');?></td>
                                    <td><strong>$<?php echo number_format($order['total_amount'],2);?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $order['status']==='delivered'?'success':($order['status']==='pending'?'warning':($order['status']==='cancelled'?'danger':'info'));?>">
                                            <?php echo ucfirst($order['status']);?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y',strtotime($order['created_at']));?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="viewDetails(<?php echo $order['id'];?>)" style="margin-bottom:5px">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if($order['status']==='pending'):?>
                                        <button class="btn btn-success btn-sm" onclick="showAssignModal(<?php echo $order['id'];?>)">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                        <?php else:?>
                                        <button class="btn btn-warning btn-sm" onclick="showStatusModal(<?php echo $order['id'];?>,'<?php echo $order['status'];?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif;?>
                                    </td>
                                </tr>
                                <?php endforeach;?>
                            <?php endif;?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($totalPages>1):?>
                <div class="pagination">
                    <?php if($page>1):?>
                    <button onclick="window.location.href='?page=<?php echo $page-1;?>&status=<?php echo $statusFilter;?>&search=<?php echo urlencode($searchQuery);?>'">
                        &laquo; Previous
                    </button>
                    <?php endif;?>
                    
                    <?php for($i=1;$i<=$totalPages;$i++):?>
                    <button class="<?php echo $page==$i?'active':'';?>" onclick="window.location.href='?page=<?php echo $i;?>&status=<?php echo $statusFilter;?>&search=<?php echo urlencode($searchQuery);?>'">
                        <?php echo $i;?>
                    </button>
                    <?php endfor;?>
                    
                    <?php if($page<$totalPages):?>
                    <button onclick="window.location.href='?page=<?php echo $page+1;?>&status=<?php echo $statusFilter;?>&search=<?php echo urlencode($searchQuery);?>'">
                        Next &raquo;
                    </button>
                    <?php endif;?>
                </div>
                <?php endif;?>
            </div>
        </div>
    </main>

    <!-- Assign Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Assign Delivery Person</h2>
                <button class="close" onclick="closeModal('assignModal')">&times;</button>
            </div>
            <input type="hidden" id="assign_order_id">
            <div class="form-group">
                <label>Select Delivery Person</label>
                <select class="form-control" id="delivery_person">
                    <option value="">Choose...</option>
                    <?php foreach($deliveryPersonnel as $person):?>
                    <option value="<?php echo htmlspecialchars($person['name']);?>"><?php echo htmlspecialchars($person['name']);?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px">
                <button class="btn btn-primary" onclick="assignDelivery()" style="flex:1">Assign</button>
                <button class="btn" onclick="closeModal('assignModal')" style="flex:1;background:#e5e7eb;color:#484d53">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Status Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update Delivery Status</h2>
                <button class="close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <input type="hidden" id="status_order_id">
            <div class="form-group">
                <label>New Status</label>
                <select class="form-control" id="new_status">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px">
                <button class="btn btn-primary" onclick="updateStatus()" style="flex:1">Update</button>
                <button class="btn" onclick="closeModal('statusModal')" style="flex:1;background:#e5e7eb;color:#484d53">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content" style="max-width:800px">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Delivery Details</h2>
                <button class="close" onclick="closeModal('detailsModal')">&times;</button>
            </div>
            <div id="detailsContent">
                <div style="text-align:center">
                    <div style="display:inline-block;width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid rgb(124,136,224);border-radius:50%;animation:spin 1s linear infinite"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    </style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const navItems=document.querySelectorAll(".nav-item");
        navItems.forEach(navItem=>{
            navItem.addEventListener("click",()=>{
                navItems.forEach(item=>item.classList.remove("active"));
                navItem.classList.add("active");
            });
        });

        function closeModal(modalId){document.getElementById(modalId).classList.remove('show')}
        
        function showAssignModal(orderId){
            document.getElementById('assign_order_id').value=orderId;
            document.getElementById('assignModal').classList.add('show');
        }

        function showStatusModal(orderId,currentStatus){
            document.getElementById('status_order_id').value=orderId;
            document.getElementById('new_status').value=currentStatus;
            document.getElementById('statusModal').classList.add('show');
        }

        function assignDelivery(){
            const orderId=document.getElementById('assign_order_id').value;
            const deliveryBoy=document.getElementById('delivery_person').value;

            if(!deliveryBoy){alert('Please select a delivery person');return}

            const formData=new FormData();
            formData.append('action','assign_delivery');
            formData.append('order_id',orderId);
            formData.append('delivery_boy',deliveryBoy);

            fetch('delivery.php',{method:'POST',body:formData})
            .then(response=>response.json())
            .then(data=>{
                if(data.success){alert(data.message);location.reload()}
                else{alert('Error: '+data.message)}
            })
            .catch(error=>{console.error('Error:',error);alert('An error occurred')});
        }

        function updateStatus(){
            const orderId=document.getElementById('status_order_id').value;
            const newStatus=document.getElementById('new_status').value;

            const formData=new FormData();
            formData.append('action','update_status');
            formData.append('order_id',orderId);
            formData.append('status',newStatus);

            fetch('delivery.php',{method:'POST',body:formData})
            .then(response=>response.json())
            .then(data=>{
                if(data.success){alert(data.message);location.reload()}
                else{alert('Error: '+data.message)}
            })
            .catch(error=>{console.error('Error:',error);alert('An error occurred')});
        }

        function viewDetails(orderId){
            document.getElementById('detailsModal').classList.add('show');

            const formData=new FormData();
            formData.append('action','get_delivery_details');
            formData.append('order_id',orderId);

            fetch('delivery.php',{method:'POST',body:formData})
            .then(response=>response.json())
            .then(data=>{
                if(data.success){
                    const order=data.data;
                    let itemsHtml='';
                    
                    order.items.forEach(item=>{
                        itemsHtml+=`
                            <tr>
                                <td>${item.product_name}</td>
                                <td>${item.quantity}</td>
                                <td>${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td>${parseFloat(item.total_price).toFixed(2)}</td>
                            </tr>
                        `;
                    });

                    const html=`
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                            <div>
                                <h3 style="font-size:1.1rem;margin-bottom:10px;color:#484d53">Order Information</h3>
                                <table style="width:100%;font-size:.85rem">
                                    <tr><td><strong>Order Number:</strong></td><td>${order.order_number}</td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge badge-${order.status==='delivered'?'success':(order.status==='pending'?'warning':(order.status==='cancelled'?'danger':'info'))}">${order.status.charAt(0).toUpperCase()+order.status.slice(1)}</span></td></tr>
                                    <tr><td><strong>Payment Method:</strong></td><td>${order.payment_method||'N/A'}</td></tr>
                                    <tr><td><strong>Payment Status:</strong></td><td>${order.payment_status||'N/A'}</td></tr>
                                </table>
                            </div>
                            <div>
                                <h3 style="font-size:1.1rem;margin-bottom:10px;color:#484d53">Customer Information</h3>
                                <table style="width:100%;font-size:.85rem">
                                    <tr><td><strong>Name:</strong></td><td>${order.customer_name}</td></tr>
                                    <tr><td><strong>Email:</strong></td><td>${order.customer_email}</td></tr>
                                    <tr><td><strong>Phone:</strong></td><td>${order.customer_phone||'N/A'}</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <h3 style="font-size:1.1rem;margin-bottom:10px;color:#484d53">Delivery Address</h3>
                        <p style="margin-bottom:20px;font-size:.9rem">${order.address_line_1||'N/A'}<br>
                        ${order.address_line_2?order.address_line_2+'<br>':''}
                        ${order.city||''}, ${order.state||''} ${order.postal_code||''}<br>
                        ${order.country||''}</p>

                        <h3 style="font-size:1.1rem;margin-bottom:10px;color:#484d53">Order Items</h3>
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
                            <thead>
                                <tr style="background:#f6f7fb">
                                    <th style="padding:8px;text-align:left">Product</th>
                                    <th style="padding:8px;text-align:left">Quantity</th>
                                    <th style="padding:8px;text-align:left">Unit Price</th>
                                    <th style="padding:8px;text-align:left">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>

                        <div style="display:flex;justify-content:flex-end;margin-top:20px">
                            <table style="width:300px;font-size:.9rem">
                                <tr><td><strong>Subtotal:</strong></td><td style="text-align:right">${parseFloat(order.subtotal).toFixed(2)}</td></tr>
                                <tr><td><strong>Tax:</strong></td><td style="text-align:right">${parseFloat(order.tax_amount).toFixed(2)}</td></tr>
                                <tr><td><strong>Shipping:</strong></td><td style="text-align:right">${parseFloat(order.shipping_amount).toFixed(2)}</td></tr>
                                <tr><td><strong>Discount:</strong></td><td style="text-align:right">-${parseFloat(order.discount_amount).toFixed(2)}</td></tr>
                                <tr style="background:#f6f7fb"><td><strong>Total:</strong></td><td style="text-align:right"><strong>${parseFloat(order.total_amount).toFixed(2)}</strong></td></tr>
                            </table>
                        </div>
                    `;
                    
                    document.getElementById('detailsContent').innerHTML=html;
                }else{
                    document.getElementById('detailsContent').innerHTML='<p style="color:#ef4444">Error loading details</p>';
                }
            })
            .catch(error=>{
                console.error('Error:',error);
                document.getElementById('detailsContent').innerHTML='<p style="color:#ef4444">Error loading details</p>';
            });
        }

        function exportDeliveries(){
            const formData=new FormData();
            formData.append('action','export_deliveries');

            fetch('delivery.php',{method:'POST',body:formData})
            .then(response=>response.json())
            .then(data=>{
                if(data.success){
                    const deliveries=data.data;
                    let csv='Order Number,Customer Name,Email,Phone,Address,Status,Total Amount,Date,Tracking Number\n';
                    
                    deliveries.forEach(delivery=>{
                        csv+=`"${delivery.order_number}","${delivery.customer_name}","${delivery.email}","${delivery.phone||''}","${delivery.address||''}","${delivery.status}","${delivery.total_amount}","${delivery.created_at}","${delivery.tracking_number||'N/A'}"\n`;
                    });

                    const blob=new Blob([csv],{type:'text/csv'});
                    const url=window.URL.createObjectURL(blob);
                    const a=document.createElement('a');
                    a.href=url;
                    a.download='deliveries_'+new Date().toISOString().split('T')[0]+'.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }else{
                    alert('Error exporting deliveries');
                }
            })
            .catch(error=>{
                console.error('Error:',error);
                alert('An error occurred while exporting');
            });
        }

        window.onclick=function(event){
            const assignModal=document.getElementById('assignModal');
            const statusModal=document.getElementById('statusModal');
            const detailsModal=document.getElementById('detailsModal');
            if(event.target==assignModal)assignModal.classList.remove('show');
            if(event.target==statusModal)statusModal.classList.remove('show');
            if(event.target==detailsModal)detailsModal.classList.remove('show');
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