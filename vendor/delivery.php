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

$vendorBrand = $vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Assign Delivery
    if ($_POST['action'] === 'assign_delivery') {
        try {
            // Verify order belongs to vendor
            $checkStmt = $pdo->prepare("
                SELECT DISTINCT o.id 
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                INNER JOIN products p ON oi.product_id = p.id
                WHERE o.id = ? AND p.brand = ?
            ");
            $checkStmt->execute([$_POST['order_id'], $vendorBrand]);
            
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
                exit();
            }
            
            // Assign delivery
            $stmt = $pdo->prepare("
                INSERT INTO delivery_assignments 
                (order_id, delivery_person_id, status, notes, created_at)
                VALUES (?, ?, 'assigned', ?, NOW())
            ");
            $stmt->execute([
                $_POST['order_id'],
                $_POST['delivery_person_id'],
                $_POST['notes'] ?? null
            ]);
            
            // Update order status
            $updateStmt = $pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?");
            $updateStmt->execute([$_POST['order_id']]);
            
            logUserActivity($vendorId, 'delivery_assign', 'Assigned delivery for order #' . $_POST['order_id']);
            
            echo json_encode(['success' => true, 'message' => 'Delivery assigned successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Update Delivery Status
    if ($_POST['action'] === 'update_delivery_status') {
        try {
            $stmt = $pdo->prepare("
                UPDATE delivery_assignments 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_POST['status'], $_POST['delivery_id']]);
            
            // Update timestamps based on status
            if ($_POST['status'] === 'picked_up') {
                $pdo->prepare("UPDATE delivery_assignments SET picked_up_at = NOW() WHERE id = ?")
                    ->execute([$_POST['delivery_id']]);
            } elseif ($_POST['status'] === 'delivered') {
                $pdo->prepare("UPDATE delivery_assignments SET delivered_at = NOW() WHERE id = ?")
                    ->execute([$_POST['delivery_id']]);
                    
                // Update order status
                $orderStmt = $pdo->prepare("
                    UPDATE orders o
                    INNER JOIN delivery_assignments da ON o.id = da.order_id
                    SET o.status = 'delivered', o.delivered_at = NOW()
                    WHERE da.id = ?
                ");
                $orderStmt->execute([$_POST['delivery_id']]);
            }
            
            logUserActivity($vendorId, 'delivery_status_update', 'Updated delivery status to ' . $_POST['status']);
            
            echo json_encode(['success' => true, 'message' => 'Delivery status updated!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Get delivery personnel
$deliveryStmt = $pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT da.id) as total_deliveries,
           SUM(CASE WHEN da.status IN ('assigned', 'picked_up', 'in_transit') THEN 1 ELSE 0 END) as active_orders,
           AVG(CASE WHEN da.status = 'delivered' THEN 5 ELSE NULL END) as rating
    FROM users u
    LEFT JOIN delivery_assignments da ON u.id = da.delivery_person_id
    WHERE u.role = 'delivery' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY active_orders ASC, u.first_name ASC
");
$deliveryPersonnel = $deliveryStmt->fetchAll();

// Generate delivery personnel options for SweetAlert
$deliveryPersonOptions = '<option value="">Choose delivery person...</option>';
foreach ($deliveryPersonnel as $person) {
    $statusText = ($person['active_orders'] == 0) ? 'Available' : 'Busy (' . $person['active_orders'] . ' orders)';
    $disabled = ($person['active_orders'] > 0) ? 'disabled' : '';
    $deliveryPersonOptions .= "<option value=\"{$person['id']}\" {$disabled}>" . htmlspecialchars($person['first_name'] . ' ' . $person['last_name']) . " - {$statusText}</option>";
}


// Get vendor's orders that need delivery
$readyOrdersStmt = $pdo->prepare("
    SELECT DISTINCT o.*, 
           u.first_name, u.last_name, u.email,
           ua.city, ua.state,
           COUNT(DISTINCT oi.id) as item_count
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN users u ON o.user_id = u.id
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    LEFT JOIN delivery_assignments da ON o.id = da.order_id
    WHERE p.brand = ? 
    AND o.status IN ('processing', 'shipped')
    AND (da.id IS NULL OR da.status = 'failed')
    GROUP BY o.id
    ORDER BY o.created_at ASC
");
$readyOrdersStmt->execute([$vendorBrand]);
$readyOrders = $readyOrdersStmt->fetchAll();

// Get active deliveries
$activeDeliveriesStmt = $pdo->prepare("
    SELECT DISTINCT da.*, o.order_number, o.total_amount,
           u.first_name as customer_first, u.last_name as customer_last,
           ua.city, ua.state,
           dp.first_name as delivery_first, dp.last_name as delivery_last,
           dp.phone as delivery_phone
    FROM delivery_assignments da
    INNER JOIN orders o ON da.order_id = o.id
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN users u ON o.user_id = u.id
    INNER JOIN users dp ON da.delivery_person_id = dp.id
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    WHERE p.brand = ?
    AND da.status IN ('assigned', 'picked_up', 'in_transit')
    GROUP BY da.id
    ORDER BY da.created_at DESC
");
$activeDeliveriesStmt->execute([$vendorBrand]);
$activeDeliveries = $activeDeliveriesStmt->fetchAll();

// Get completed deliveries
$completedDeliveriesStmt = $pdo->prepare("
    SELECT DISTINCT da.*, o.order_number, o.total_amount,
           u.first_name as customer_first, u.last_name as customer_last,
           dp.first_name as delivery_first, dp.last_name as delivery_last
    FROM delivery_assignments da
    INNER JOIN orders o ON da.order_id = o.id
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN users u ON o.user_id = u.id
    INNER JOIN users dp ON da.delivery_person_id = dp.id
    WHERE p.brand = ?
    AND da.status = 'delivered'
    GROUP BY da.id
    ORDER BY da.delivered_at DESC
    LIMIT 10
");
$completedDeliveriesStmt->execute([$vendorBrand]);
$completedDeliveries = $completedDeliveriesStmt->fetchAll();

// Calculate stats
$stats = [
    'total_personnel' => count($deliveryPersonnel),
    'available' => count(array_filter($deliveryPersonnel, fn($p) => $p['active_orders'] == 0)),
    'on_delivery' => count(array_filter($deliveryPersonnel, fn($p) => $p['active_orders'] > 0)),
    'pending_assignments' => count($readyOrders)
];

// Unread notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread
    FROM admin_messages
    WHERE recipient_id = ? AND recipient_type = 'vendor' AND is_read = 0
");
$stmt->execute([$vendorId]);
$unreadNotifications = $stmt->fetch()['unread'];

// Status badge function
function getDeliveryStatusBadge($status) {
    $badges = [
        'assigned' => 'info',
        'picked_up' => 'warning',
        'in_transit' => 'warning',
        'delivered' => 'success',
        'failed' => 'danger'
    ];
    $text = [
        'assigned' => 'Assigned',
        'picked_up' => 'Picked Up',
        'in_transit' => 'In Transit',
        'delivered' => 'Delivered',
        'failed' => 'Failed'
    ];
    $badge = $badges[$status] ?? 'secondary';
    $label = $text[$status] ?? ucfirst($status);
    return ['badge' => $badge, 'text' => $label];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            padding-bottom: 20px;
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
          grid-template-rows: 45% 75%;
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

        .delivery-section {
          margin-top: 20px;
        }

        .delivery-section h1 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 15px;
        }

        .view-tabs {
          display: flex;
          gap: 10px;
          margin-bottom: 15px;
        }

        .tab-btn {
          padding: 10px 20px;
          border: none;
          background: white;
          border-radius: 12px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .tab-btn:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .tab-btn.active {
          background: rgb(73, 57, 113);
          color: white;
        }

        .delivery-list {
          background: white;
          border-radius: 15px;
          padding: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .delivery-card {
          padding: 15px;
          border-radius: 12px;
          margin-bottom: 10px;
          border-left: 4px solid #e2e8f0;
          background: #f8fafc;
          transition: all 0.3s ease;
        }

        .delivery-card:hover {
          transform: translateX(5px);
          box-shadow: rgba(0, 0, 0, 0.1) 0px 2px 8px;
        }

        .delivery-card.assigned {
          border-left-color: #3b82f6;
          background: rgba(59, 130, 246, 0.05);
        }

        .delivery-card.picked-up,
        .delivery-card.in-transit {
          border-left-color: #f59e0b;
          background: rgba(245, 158, 11, 0.05);
        }

        .delivery-card.delivered {
          border-left-color: #10b981;
          background: rgba(16, 185, 129, 0.05);
        }

        .delivery-info {
          display: grid;
          grid-template-columns: 15% 25% 25% 15% 20%;
          gap: 10px;
          align-items: center;
        }

        .delivery-person {
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .delivery-avatar {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          object-fit: cover;
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

        .badge.info {
          background: rgba(59, 130, 246, 0.2);
          color: #3b82f6;
        }

        .btn {
          display: inline-block;
          padding: 8px 16px;
          font-size: 0.85rem;
          font-weight: 600;
          outline: none;
          text-decoration: none;
          border: none;
          border-radius: 10px;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .btn:hover {
          transform: translateY(-2px);
        }

        .btn-primary {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-success {
          background: #10b981;
          color: white;
        }

        .btn-warning {
          background: #f59e0b;
          color: white;
        }

        .btn-sm {
          padding: 6px 12px;
          font-size: 0.8rem;
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

        .personnel-section {
          padding: 15px 10px;
          overflow-y: auto;
          max-height: calc(100vh - 150px);
        }

        .personnel-section h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .personnel-card {
          background: white;
          border-radius: 12px;
          padding: 12px;
          margin-bottom: 10px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .personnel-card:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .personnel-header {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 10px;
        }

        .personnel-avatar {
          width: 45px;
          height: 45px;
          border-radius: 50%;
          object-fit: cover;
        }

        .personnel-status {
          margin-left: auto;
        }

        .status-dot {
          display: inline-block;
          width: 10px;
          height: 10px;
          border-radius: 50%;
          margin-right: 5px;
        }

        .status-dot.available {
          background: #10b981;
        }

        .status-dot.busy {
          background: #f59e0b;
        }

        .personnel-stats {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 5px;
          text-align: center;
          padding: 10px 0;
          border-top: 1px solid #e2e8f0;
        }

        .personnel-stats div {
          font-size: 0.85rem;
        }

        .personnel-stats small {
          display: block;
          color: #64748b;
          font-size: 0.75rem;
        }

        .personnel-stats strong {
          display: block;
          color: #484d53;
          font-size: 1rem;
        }

        /* --- SWEETALERT2 STYLES (Customized) --- */
        .swal2-popup {
          border-radius: 15px !important;
          box-shadow: 0 8px 25px rgba(73, 57, 113, 0.3);
          font-family: "Nunito", sans-serif;
        }

        .swal2-confirm.swal-confirm {
          background: linear-gradient(135deg, rgb(73, 57, 113), #a38cd9) !important;
          border: none !important;
          font-weight: 600;
          border-radius: 10px;
        }

        .swal2-cancel.swal-cancel {
          background: #f6f7fb !important;
          color: #484d53 !important;
          border: 1px solid #ddd !important;
          font-weight: 600;
          border-radius: 10px;
        }

        .swal2-confirm:hover, .swal2-cancel:hover {
          transform: translateY(-1px);
        }
        
        /* Custom class to enforce text centering in the content area for simple messages */
        .swal2-html-container-center {
            text-align: center !important;
        }
        
        /* Styles for form elements inside SweetAlert */
        .swal2-html-container .form-group label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            color: #484d53;
        }
        .swal2-html-container .form-control,
        .swal2-html-container .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .swal2-html-container .form-control:focus,
        .swal2-html-container .form-select:focus {
             outline: none;
             border-color: rgb(73, 57, 113);
        }
        /* --- END SWEETALERT2 STYLES --- */


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

          .delivery-info {
            grid-template-columns: 1fr;
            gap: 10px;
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

          <li class="nav-item active">
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
            <h1>Delivery Overview</h1>
            <div class="stats-grid">
              <div class="stat-card">
                <i class="fas fa-users"></i>
                <div>
                  <h3><?php echo number_format($stats['total_personnel']); ?></h3>
                  <p>Delivery Staff</p>
                  <small><i class="fas fa-user-check"></i> Total personnel</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div>
                  <h3><?php echo number_format($stats['available']); ?></h3>
                  <p>Available</p>
                  <small><i class="fa-solid fa-check-to-slot"></i> Ready for delivery</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-truck"></i>
                <div>
                  <h3><?php echo number_format($stats['on_delivery']); ?></h3>
                  <p>On Delivery</p>
                  <small><i class="fas fa-shipping-fast"></i> Active deliveries</small>
                </div>
              </div>

              <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div>
                  <h3><?php echo number_format($stats['pending_assignments']); ?></h3>
                  <p>Pending</p>
                  <small><i class="fas fa-hourglass-half"></i> Awaiting assignment</small>
                </div>
              </div>
            </div>
          </div>

          <div class="delivery-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
              <h1>Manage Deliveries</h1>
              <div class="view-tabs">
                <button class="tab-btn active" id="activeTab" onclick="showView('active')">
                  <i class="fas fa-truck"></i> Active
                </button>
                <button class="tab-btn" id="pendingTab" onclick="showView('pending')">
                  <i class="fas fa-clock"></i> Pending
                </button>
                <button class="tab-btn" id="completedTab" onclick="showView('completed')">
                  <i class="fas fa-check-circle"></i> Completed
                </button>
              </div>
            </div>

            <!-- Active Deliveries -->
            <div class="delivery-list" id="activeView">
              <?php if (empty($activeDeliveries)): ?>
              <div class="empty-state">
                <i class="fas fa-truck"></i>
                <p>No active deliveries</p>
              </div>
              <?php else: ?>
                <?php foreach ($activeDeliveries as $delivery): ?>
                <?php $statusInfo = getDeliveryStatusBadge($delivery['status']); ?>
                <div class="delivery-card <?php echo str_replace('_', '-', $delivery['status']); ?>">
                  <div class="delivery-info">
                    <div>
                      <h6 style="margin: 0; font-weight: 700;"><?php echo htmlspecialchars($delivery['order_number']); ?></h6>
                      <small style="color: #64748b;">Order</small>
                    </div>
                    <div class="delivery-person">
                      <img src="https://via.placeholder.com/40x40" class="delivery-avatar" alt="Delivery Person">
                      <div>
                        <h6 style="margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($delivery['delivery_first'] . ' ' . $delivery['delivery_last']); ?></h6>
                        <small style="color: #64748b;"><?php echo htmlspecialchars($delivery['delivery_phone'] ?? 'N/A'); ?></small>
                      </div>
                    </div>
                    <div>
                      <h6 style="margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($delivery['customer_first'] . ' ' . $delivery['customer_last']); ?></h6>
                      <small style="color: #64748b;"><?php echo htmlspecialchars($delivery['city'] . ', ' . $delivery['state']); ?></small>
                    </div>
                    <div>
                      <span class="badge <?php echo $statusInfo['badge']; ?>">
                        <?php echo $statusInfo['text']; ?>
                      </span>
                    </div>
                    <div>
                      <?php if ($delivery['status'] === 'assigned'): ?>
                      <button class="btn btn-success btn-sm" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'picked_up')">
                        <i class="fas fa-check"></i> Picked Up
                      </button>
                      <?php elseif ($delivery['status'] === 'picked_up'): ?>
                      <button class="btn btn-warning btn-sm" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'in_transit')">
                        <i class="fas fa-truck"></i> In Transit
                      </button>
                      <?php elseif ($delivery['status'] === 'in_transit'): ?>
                      <button class="btn btn-primary btn-sm" onclick="updateDeliveryStatus(<?php echo $delivery['id']; ?>, 'delivered')">
                        <i class="fas fa-check-circle"></i> Delivered
                      </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Pending Deliveries -->
            <div class="delivery-list" id="pendingView" style="display: none;">
              <?php if (empty($readyOrders)): ?>
              <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>No pending deliveries</p>
              </div>
              <?php else: ?>
                <?php foreach ($readyOrders as $order): ?>
                <div class="delivery-card">
                  <div class="delivery-info">
                    <div>
                      <h6 style="margin: 0; font-weight: 700;"><?php echo htmlspecialchars($order['order_number']); ?></h6>
                      <small style="color: #64748b;">Order</small>
                    </div>
                    <div>
                      <span style="color: #64748b; font-style: italic;">Not Assigned</span>
                    </div>
                    <div>
                      <h6 style="margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></h6>
                      <small style="color: #64748b;"><?php echo htmlspecialchars($order['city'] . ', ' . $order['state']); ?></small>
                    </div>
                    <div>
                      <span class="badge warning">Pending</span>
                    </div>
                    <div>
                      <button class="btn btn-primary btn-sm" onclick="showAssignModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                        <i class="fas fa-user-plus"></i> Assign
                      </button>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Completed Deliveries -->
            <div class="delivery-list" id="completedView" style="display: none;">
              <?php if (empty($completedDeliveries)): ?>
              <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No completed deliveries</p>
              </div>
              <?php else: ?>
                <?php foreach ($completedDeliveries as $delivery): ?>
                <div class="delivery-card delivered">
                  <div class="delivery-info">
                    <div>
                      <h6 style="margin: 0; font-weight: 700;"><?php echo htmlspecialchars($delivery['order_number']); ?></h6>
                      <small style="color: #64748b;">Order</small>
                    </div>
                    <div class="delivery-person">
                      <img src="https://via.placeholder.com/40x40" class="delivery-avatar" alt="Delivery Person">
                      <div>
                        <h6 style="margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($delivery['delivery_first'] . ' ' . $delivery['delivery_last']); ?></h6>
                      </div>
                    </div>
                    <div>
                      <h6 style="margin: 0; font-size: 0.9rem;"><?php echo htmlspecialchars($delivery['customer_first'] . ' ' . $delivery['customer_last']); ?></h6>
                      <small style="color: #64748b;"><?php echo date('M d, g:i A', strtotime($delivery['delivered_at'])); ?></small>
                    </div>
                    <div>
                      <span class="badge success">Delivered</span>
                    </div>
                    <div>
                      <div style="color: #f59e0b;">
                        ★★★★★
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
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

          <div class="personnel-section">
            <h1>Delivery Personnel</h1>
            
            <?php if (empty($deliveryPersonnel)): ?>
            <div class="empty-state" style="padding: 20px;">
              <i class="fas fa-users" style="font-size: 32px;"></i>
              <p style="font-size: 0.9rem;">No delivery personnel available</p>
            </div>
            <?php else: ?>
              <?php foreach ($deliveryPersonnel as $person): ?>
              <div class="personnel-card">
                <div class="personnel-header">
                  <?php if ($person['profile_picture']): ?>
                  <img src="<?php echo htmlspecialchars(UPLOAD_URL . $person['profile_picture']); ?>" 
                       class="personnel-avatar" alt="<?php echo htmlspecialchars($person['first_name']); ?>">
                  <?php else: ?>
                  <img src="https://via.placeholder.com/45x45" class="personnel-avatar" alt="Delivery Person">
                  <?php endif; ?>
                  <div style="flex: 1;">
                    <h6 style="margin: 0; font-size: 0.95rem;"><?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name']); ?></h6>
                    <small style="color: #64748b;">ID: DP<?php echo str_pad($person['id'], 3, '0', STR_PAD_LEFT); ?></small>
                  </div>
                  <div class="personnel-status">
                    <?php if ($person['active_orders'] == 0): ?>
                    <span class="status-dot available"></span>
                    <small style="color: #10b981; font-weight: 600;">Available</small>
                    <?php else: ?>
                    <span class="status-dot busy"></span>
                    <small style="color: #f59e0b; font-weight: 600;">Busy</small>
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="personnel-stats">
                  <div>
                    <small>Active</small>
                    <strong><?php echo $person['active_orders']; ?></strong>
                  </div>
                  <div>
                    <small>Total</small>
                    <strong><?php echo $person['total_deliveries']; ?></strong>
                  </div>
                  <div>
                    <small>Phone</small>
                    <strong style="font-size: 0.75rem;"><?php echo htmlspecialchars(substr($person['phone'] ?? 'N/A', 0, 10)); ?></strong>
                  </div>
                </div>

                <?php if ($person['active_orders'] == 0 && !empty($readyOrders)): ?>
                <button class="btn btn-primary btn-sm" style="width: 100%; margin-top: 10px;"
                        onclick="showAssignModal(<?php echo $readyOrders[0]['id']; ?>, '<?php echo htmlspecialchars($readyOrders[0]['order_number']); ?>', <?php echo $person['id']; ?>)">
                  <i class="fas fa-plus"></i> Assign Order
                </button>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>

    <!-- The custom assign modal HTML block was removed here -->
    
<script>
    // PHP variable for select options
    const DELIVERY_PERSONNEL_OPTIONS = `<?php echo $deliveryPersonOptions; ?>`;

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
        const currentPage = window.location.pathname.split('/').pop() || 'delivery.php';
        navItems.forEach((navItem) => {
            const link = navItem.querySelector('a');
            if (link && link.getAttribute('href') === currentPage) {
                navItems.forEach((item) => item.classList.remove("active"));
                navItem.classList.add("active");
            }
        });
    });

    // Show View
    function showView(view) {
        document.getElementById('activeView').style.display = 'none';
        document.getElementById('pendingView').style.display = 'none';
        document.getElementById('completedView').style.display = 'none';

        document.getElementById('activeTab').classList.remove('active');
        document.getElementById('pendingTab').classList.remove('active');
        document.getElementById('completedTab').classList.remove('active');

        if (view === 'active') {
            document.getElementById('activeView').style.display = 'block';
            document.getElementById('activeTab').classList.add('active');
        } else if (view === 'pending') {
            document.getElementById('pendingView').style.display = 'block';
            document.getElementById('pendingTab').classList.add('active');
        } else if (view === 'completed') {
            document.getElementById('completedView').style.display = 'block';
            document.getElementById('completedTab').classList.add('active');
        }
    }
    
    // --- UTILITY FUNCTIONS ---
    
    // Function to display SweetAlert2 results with centered text
    function showSwalResult(data, successTitle = 'Success', errorTitle = 'Error') {
        Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? successTitle : errorTitle,
            text: data.message,
            // Ensure text alignment is default center for alert messages
            customClass: {
                confirmButton: 'swal-confirm',
                htmlContainer: 'swal2-html-container-center' // Custom class for centering
            }
        }).then(() => {
            if (data.success) {
                location.reload();
            }
        });
    }
    
    // Function to handle AJAX post requests
    async function postData(action, formData, isFormData = false) {
        const fetchOptions = {
            method: 'POST',
            body: isFormData ? formData : new URLSearchParams(formData),
        };
        
        // Add AJAX flag and action
        if (isFormData) {
            formData.append('ajax', '1');
            formData.append('action', action);
        } else {
            fetchOptions.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            fetchOptions.body += '&ajax=1&action=' + action;
        }

        try {
            const response = await fetch('delivery.php', fetchOptions);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        } catch (error) {
            console.error('Fetch error:', error);
            // Return a failure object to be handled by the caller
            return { success: false, message: 'Could not connect to the server or process the request.' };
        }
    }
    
    // --- DELIVERY ACTIONS (SweetAlert2) ---

    // Show Assign Modal (Replaces old showAssignModal and confirmAssignment)
    function showAssignModal(orderId, orderNumber, deliveryPersonId = null) {
        const formHtml = `
            <form id="swalAssignDeliveryForm">
                <input type="hidden" name="order_id" value="${orderId}">
                
                <div class="form-group">
                    <label>Order Number</label>
                    <input type="text" value="${orderNumber}" readonly class="form-control" style="font-weight: bold; background: #f6f7fb;">
                </div>

                <div class="form-group">
                    <label for="deliveryPersonSelect">Select Delivery Person *</label>
                    <select id="deliveryPersonSelect" name="delivery_person_id" class="form-select" required>
                        ${DELIVERY_PERSONNEL_OPTIONS}
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Special Instructions</label>
                    <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Any special delivery instructions..."></textarea>
                </div>
            </form>
        `;

        Swal.fire({
            title: `Assign Delivery for Order #${orderNumber}`,
            html: formHtml,
            width: '500px',
            showCancelButton: true,
            confirmButtonText: 'Assign Order',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            customClass: {
                confirmButton: 'swal-confirm',
                cancelButton: 'swal-cancel',
            },
            didOpen: () => {
                // Set default selection if a delivery person was pre-selected (e.g., from Personnel card)
                if (deliveryPersonId) {
                    document.getElementById('deliveryPersonSelect').value = deliveryPersonId;
                }
            },
            preConfirm: () => {
                const form = document.getElementById('swalAssignDeliveryForm');
                
                // Manual validation check for the required select field
                if (!form.querySelector('[name="delivery_person_id"]').value) {
                    Swal.showValidationMessage('Please select a delivery person.');
                    return false;
                }
                
                const formData = new URLSearchParams(new FormData(form));
                return postData('assign_delivery', formData.toString(), false);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showSwalResult(result.value, 'Assignment Successful!', 'Assignment Failed');
            }
        });
    }
    
    // Update Delivery Status (Replaces old updateDeliveryStatus)
    function updateDeliveryStatus(deliveryId, status) {
        const statusNames = {
            'picked_up': 'Picked Up',
            'in_transit': 'In Transit',
            'delivered': 'Delivered'
        };
        const title = `Update Status to ${statusNames[status]}?`;
        
        Swal.fire({
            title: title,
            text: `Are you sure you want to mark this delivery as "${statusNames[status]}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Update Status',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            customClass: {
                confirmButton: 'swal-confirm',
                cancelButton: 'swal-cancel',
            },
            preConfirm: () => {
                const formData = `delivery_id=${deliveryId}&status=${status}`;
                return postData('update_delivery_status', formData, false);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showSwalResult(result.value, 'Status Updated!', 'Status Update Failed');
            }
        });
    }

    function confirmLogout(e) {
        e.preventDefault();

        Swal.fire({
          title: 'Logout Confirmation',
          text: 'Are you sure you want to log out?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, log me out',
          cancelButtonText: 'Cancel',
          customClass: {
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
              timer: 1000,
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
