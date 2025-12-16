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
    
    if ($_POST['action'] === 'update_status') {
        try {
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
            
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['order_id']]);
            
            logUserActivity($vendorId, 'order_status_update', 'Updated order #' . $_POST['order_id'] . ' to ' . $_POST['status']);
            
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating order: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Replace the get_order case in orders.php with this:

// Replace the get_order case in orders.php with this updated version:

if ($_POST['action'] === 'get_order') {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.*, 
                   u.first_name, u.last_name, u.email, u.phone,
                   ua.address_line_1, ua.address_line_2, ua.city, ua.state, ua.postal_code, ua.country
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            INNER JOIN products p ON oi.product_id = p.id
            INNER JOIN users u ON o.user_id = u.id
            LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
            WHERE o.id = ? AND p.brand = ?
        ");
        $stmt->execute([$_POST['order_id'], $vendorBrand]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Get order items with full product details INCLUDING color and size names
            $itemsStmt = $pdo->prepare("
                SELECT oi.*, 
                       p.name as product_name, 
                       p.brand,
                       p.image as product_image,
                       p.sku,
                       c.name as color_name,
                       c.hex_code as color_hex,
                       s.name as size_name
                FROM order_items oi
                INNER JOIN products p ON oi.product_id = p.id
                LEFT JOIN colors c ON oi.selected_color_id = c.id
                LEFT JOIN sizes s ON oi.selected_size_id = s.id
                WHERE oi.order_id = ? AND p.brand = ?
            ");
            $itemsStmt->execute([$_POST['order_id'], $vendorBrand]);
            $items = $itemsStmt->fetchAll();
            
            // Enhance each item with selected color, size, and images
            foreach ($items as &$item) {
                // Parse product_snapshot JSON to get selected color and size WITH details
                if (!empty($item['product_snapshot'])) {
                    $snapshot = json_decode($item['product_snapshot'], true);
                    if ($snapshot) {
                        // Get selected color with hex code
                        if (isset($snapshot['color'])) {
                            $colorId = $snapshot['color']['id'] ?? $item['selected_color_id'];
                            if ($colorId) {
                                $colorStmt = $pdo->prepare("SELECT id, name, hex_code FROM colors WHERE id = ?");
                                $colorStmt->execute([$colorId]);
                                $colorData = $colorStmt->fetch();
                                if ($colorData) {
                                    $snapshot['color'] = [
                                        'id' => $colorData['id'],
                                        'name' => $colorData['name'],
                                        'hex_code' => $colorData['hex_code']
                                    ];
                                }
                            }
                        }
                        
                        // Get selected size
                        if (isset($snapshot['size'])) {
                            $sizeId = $snapshot['size']['id'] ?? $item['selected_size_id'];
                            if ($sizeId) {
                                $sizeStmt = $pdo->prepare("SELECT id, name FROM sizes WHERE id = ?");
                                $sizeStmt->execute([$sizeId]);
                                $sizeData = $sizeStmt->fetch();
                                if ($sizeData) {
                                    $snapshot['size'] = [
                                        'id' => $sizeData['id'],
                                        'name' => $sizeData['name']
                                    ];
                                }
                            }
                        }
                        
                        $item['product_snapshot'] = json_encode($snapshot);
                    }
                } else {
                    // Fallback: if no snapshot, create one from selected_color_id and selected_size_id
                    $snapshot = [];
                    
                    if ($item['selected_color_id'] && $item['color_name']) {
                        $snapshot['color'] = [
                            'id' => $item['selected_color_id'],
                            'name' => $item['color_name'],
                            'hex_code' => $item['color_hex']
                        ];
                    }
                    
                    if ($item['selected_size_id'] && $item['size_name']) {
                        $snapshot['size'] = [
                            'id' => $item['selected_size_id'],
                            'name' => $item['size_name']
                        ];
                    }
                    
                    if (!empty($snapshot)) {
                        $item['product_snapshot'] = json_encode($snapshot);
                    }
                }
                
                // Get product images
                $imageStmt = $pdo->prepare("
                    SELECT id, image_path, is_primary, sort_order
                    FROM product_images
                    WHERE product_id = ?
                    ORDER BY is_primary DESC, sort_order ASC
                ");
                $imageStmt->execute([$item['product_id']]);
                $item['images'] = $imageStmt->fetchAll();
            }
            
            $order['items'] = $items;
            
            echo json_encode(['success' => true, 'order' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date_filter'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$whereClause = "WHERE p.brand = ?";
$params = [$vendorBrand];

if (!empty($searchTerm)) {
    $whereClause .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($statusFilter)) {
    $whereClause .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFilter)) {
    switch ($dateFilter) {
        case 'today':
            $whereClause .= " AND DATE(o.created_at) = CURDATE()";
            break;
        case 'week':
            $whereClause .= " AND YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $whereClause .= " AND YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
            break;
    }
}

if (!empty($dateFrom)) {
    $whereClause .= " AND DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClause .= " AND DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

// Status counts
$statusCountStmt = $pdo->prepare("
    SELECT o.status, COUNT(DISTINCT o.id) as count
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ?
    GROUP BY o.status
");
$statusCountStmt->execute([$vendorBrand]);
$statusCounts = [];
$totalOrders = 0;
while ($row = $statusCountStmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
    $totalOrders += $row['count'];
}

// Count filtered orders
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) 
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN users u ON o.user_id = u.id
    $whereClause
");
$countStmt->execute($params);
$filteredTotal = $countStmt->fetchColumn();
$totalPages = ceil($filteredTotal / $itemsPerPage);

// Fetch orders
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $pdo->prepare("
    SELECT DISTINCT o.*, 
           u.first_name, u.last_name, u.email, u.phone,
           COUNT(DISTINCT oi.id) as item_count,
           SUM(oi.total_price) as vendor_total
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN users u ON o.user_id = u.id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM admin_messages WHERE recipient_id = ? AND recipient_type = 'vendor' AND is_read = 0");
$stmt->execute([$vendorId]);
$unreadNotifications = $stmt->fetch()['unread'];

// Pending orders count
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.brand = ? AND o.status IN ('pending', 'processing')
");
$stmt->execute([$vendorBrand]);
$pendingOrdersCount = $stmt->fetch()['total'];

// Low stock count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE brand = ? AND stock_quantity <= min_stock_level");
$stmt->execute([$vendorBrand]);
$lowStockItems = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *, *::before, *::after {
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

        .nav-item b:nth-child(1), .nav-item b:nth-child(2) {
          position: absolute;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(1) { top: -15px; }
        .nav-item b:nth-child(2) { bottom: -15px; }

        .nav-item b:nth-child(1)::before, .nav-item b:nth-child(2)::before {
          content: "";
          position: absolute;
          width: 100%;
          height: 100%;
          background: rgb(73, 57, 113);
        }

        .nav-item b:nth-child(1)::before { border-bottom-right-radius: 20px; }
        .nav-item b:nth-child(2)::before { border-top-right-radius: 20px; }

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
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
        }

        .page-header h1 {
          font-size: 1.4rem;
          font-weight: 700;
          margin-bottom: 20px;
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(6, 1fr);
          gap: 15px;
          margin-bottom: 20px;
        }

        .stat-card-sm {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          padding: 15px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          text-align: center;
        }

        .stat-card-sm:nth-child(2) { background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%); }
        .stat-card-sm:nth-child(3) { background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%); }
        .stat-card-sm:nth-child(4) { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card-sm:nth-child(5) { background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%); }
        .stat-card-sm:nth-child(6) { background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%); }

        .stat-card-sm h3 {
          font-size: 1.5rem;
          font-weight: 700;
          color: #484d53;
          margin: 5px 0;
        }

        .stat-card-sm small {
          font-size: 0.85rem;
          font-weight: 600;
          color: #484d53;
        }

        .search-filter-card {
          background: white;
          border-radius: 15px;
          padding: 15px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .filter-row {
          display: grid;
          grid-template-columns: 2fr 1fr 1fr 2fr 0.8fr;
          gap: 10px;
        }

        .form-control, .form-select {
          padding: 8px 12px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-family: inherit;
          font-size: 0.9rem;
          width: 100%;
        }

        .btn {
          padding: 8px 16px;
          font-size: 0.9rem;
          font-weight: 600;
          background: rgb(73, 57, 113);
          color: white;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          font-family: inherit;
        }

        .btn:hover {
          background: rgb(93, 77, 133);
          transform: translateY(-2px);
        }

        .btn-sm {
          padding: 5px 10px;
          font-size: 0.85rem;
        }

        .orders-list {
          background: white;
          border-radius: 15px;
          padding: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .order-item {
          padding: 15px;
          border-left: 4px solid #e2e8f0;
          margin-bottom: 15px;
          background: #f8fafc;
          border-radius: 0 10px 10px 0;
          transition: all 0.3s ease;
        }

        .order-item:hover {
          transform: translateX(5px);
          box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 8px;
        }

        .order-item.pending { border-left-color: #f59e0b; }
        .order-item.processing { border-left-color: #3b82f6; }
        .order-item.shipped { border-left-color: #0dcaf0; }
        .order-item.delivered { border-left-color: #10b981; }

        .order-header {
          display: grid;
          grid-template-columns: 1.5fr 2fr 1fr 1fr 1fr 1.5fr;
          gap: 15px;
          align-items: center;
        }

        .badge {
          display: inline-block;
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.8rem;
          font-weight: 600;
        }

        .badge.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge.danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge.info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .badge.primary { background: rgba(124, 136, 224, 0.2); color: rgb(73, 57, 113); }

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

        .quick-stats {
          background: rgb(214, 227, 248);
          padding: 15px;
          margin: 15px 10px 0;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .quick-stats h1 {
          font-size: 1.2rem;
          margin-bottom: 15px;
          color: #484d53;
        }

        .stats-list {
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

        .action-buttons {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .action-btn {
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
          border: none;
          cursor: pointer;
          font-family: inherit;
        }

        .action-btn:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .action-btn i {
          width: 30px;
          height: 30px;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
        }

        /* Product Details Styles */
        .product-details-card {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 12px;
          padding: 15px;
          margin-bottom: 10px;
        }

        .product-header {
          display: flex;
          gap: 15px;
          margin-bottom: 12px;
        }

        .product-image-thumb {
          width: 80px;
          height: 80px;
          border-radius: 8px;
          object-fit: cover;
          border: 2px solid #e2e8f0;
        }

        .product-main-info {
          flex: 1;
        }

        .product-main-info h5 {
          font-size: 1rem;
          color: #484d53;
          margin-bottom: 5px;
        }

        .product-main-info .sku {
          font-size: 0.85rem;
          color: #94a3b8;
          margin-bottom: 8px;
        }

        .product-attributes {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 10px;
          margin-top: 10px;
          padding-top: 10px;
          border-top: 1px solid #e2e8f0;
        }

        .attribute-group {
          background: white;
          padding: 10px;
          border-radius: 8px;
        }

        .attribute-group label {
          font-size: 0.75rem;
          color: #94a3b8;
          text-transform: uppercase;
          font-weight: 600;
          display: block;
          margin-bottom: 5px;
        }

        .color-swatches {
          display: flex;
          gap: 5px;
          flex-wrap: wrap;
        }

        .color-swatch {
          width: 24px;
          height: 24px;
          border-radius: 50%;
          border: 2px solid #e2e8f0;
          cursor: pointer;
          transition: transform 0.2s;
        }

        .color-swatch:hover {
          transform: scale(1.15);
          border-color: rgb(73, 57, 113);
        }

        .size-tags {
          display: flex;
          gap: 5px;
          flex-wrap: wrap;
        }

        .size-tag {
          padding: 4px 10px;
          background: rgb(73, 57, 113);
          color: white;
          border-radius: 6px;
          font-size: 0.8rem;
          font-weight: 600;
        }

        .product-gallery {
          display: flex;
          gap: 5px;
          margin-top: 10px;
          flex-wrap: wrap;
        }

        .gallery-image {
          width: 60px;
          height: 60px;
          border-radius: 6px;
          object-fit: cover;
          border: 2px solid #e2e8f0;
          cursor: pointer;
          transition: all 0.3s;
        }

        .gallery-image:hover {
          border-color: rgb(73, 57, 113);
          transform: scale(1.05);
        }

        /* SWEETALERT2 STYLES */
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

        .swal2-html-container {
             text-align: initial !important; 
        }

        .swal2-html-container table th,
        .swal2-html-container table td {
            text-align: left;
        }
        
        .swal-status-btn {
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: white;
            opacity: 0.8;
        }
        .swal-status-btn:hover:not(:disabled) {
            opacity: 1;
        }

        .pagination {
          display: flex;
          justify-content: center;
          gap: 5px;
          margin-top: 20px;
        }

        .pagination a, .pagination span {
          padding: 8px 12px;
          background: white;
          color: #484d53;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 600;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .pagination a:hover,
        .pagination a.active {
          background: rgb(73, 57, 113);
          color: white;
          transform: translateY(-2px);
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1 { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
          .content { grid-template-columns: 70% 30%; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .content { grid-template-columns: 65% 35%; }
          .stats-grid { grid-template-columns: repeat(3, 1fr); }
          .filter-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .content { grid-template-columns: 55% 45%; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { grid-template-columns: 100%; grid-template-rows: 45% 55%; }
          .left-content { margin: 0 15px 15px 15px; }
          .right-content { margin: 15px; }
          .stats-grid { grid-template-columns: repeat(2, 1fr); }
          .product-attributes { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><?php echo APP_NAME; ?></h1>
            <small>Vendor Panel</small>
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

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="orders.php">
                        <i class="fa fa-shopping-cart nav-icon"></i>
                        <span class="nav-text">Orders</span>
                        <?php if ($pendingOrdersCount > 0): ?>
                        <span class="notification-badge"><?php echo $pendingOrdersCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="inventory.php">
                        <i class="fa fa-boxes nav-icon"></i>
                        <span class="nav-text">Inventory</span>
                        <?php if ($lowStockItems > 0): ?>
                        <span class="notification-badge"><?php echo $lowStockItems; ?></span>
                        <?php endif; ?>
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
                <div class="page-header">
                    <h1>Orders Management</h1>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card-sm">
                        <h3><?php echo $totalOrders; ?></h3>
                        <small>Total Orders</small>
                    </div>
                    <div class="stat-card-sm">
                        <h3><?php echo $statusCounts['pending'] ?? 0; ?></h3>
                        <small>Pending</small>
                    </div>
                    <div class="stat-card-sm">
                        <h3><?php echo $statusCounts['processing'] ?? 0; ?></h3>
                        <small>Processing</small>
                    </div>
                    <div class="stat-card-sm">
                        <h3><?php echo $statusCounts['shipped'] ?? 0; ?></h3>
                        <small>Shipped</small>
                    </div>
                    <div class="stat-card-sm">
                        <h3><?php echo $statusCounts['delivered'] ?? 0; ?></h3>
                        <small>Delivered</small>
                    </div>
                    <div class="stat-card-sm">
                        <h3><?php echo $statusCounts['cancelled'] ?? 0; ?></h3>
                        <small>Cancelled</small>
                    </div>
                </div>

                <!-- Search & Filter -->
                <div class="search-filter-card">
                    <form method="GET">
                        <div class="filter-row">
                            <input type="text" class="form-control" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo ($statusFilter === 'processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo ($statusFilter === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo ($statusFilter === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                            </select>
                            <select class="form-select" name="date_filter">
                                <option value="">All Time</option>
                                <option value="today" <?php echo ($dateFilter === 'today') ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo ($dateFilter === 'week') ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo ($dateFilter === 'month') ? 'selected' : ''; ?>>This Month</option>
                            </select>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                            <button type="submit" class="btn"><i class="fas fa-filter"></i> Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Orders List -->
                <div class="orders-list">
                    <h3 style="margin-bottom: 15px; color: #484d53;">Orders (<?php echo $filteredTotal; ?> found)</h3>
                    
                    <?php if (empty($orders)): ?>
                        <div style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-inbox" style="font-size: 64px; opacity: 0.3; display: block; margin-bottom: 20px;"></i>
                            <span style="opacity: 0.6;">No orders found</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-item <?php echo $order['status']; ?>">
                                <div class="order-header">
                                    <div>
                                        <strong style="display: block; margin-bottom: 3px;"><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                        <small style="color: #94a3b8;"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></small>
                                    </div>
                                    <div>
                                        <strong style="display: block; margin-bottom: 3px;"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                        <small style="color: #94a3b8;"><?php echo htmlspecialchars($order['email']); ?></small>
                                    </div>
                                    <div>
                                        <strong><?php echo $order['item_count']; ?></strong> item(s)
                                    </div>
                                    <div>
                                        <strong style="font-size: 1.1rem; color: rgb(73, 57, 113);">$<?php echo number_format($order['vendor_total'], 2); ?></strong>
                                    </div>
                                    <div>
                                        <?php
                                        $statusBadges = [
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'shipped' => 'primary',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $badgeClass = $statusBadges[$order['status']] ?? 'info';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="btn btn-sm" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn btn-sm" style="background: #10b981;" onclick="updateStatus(<?php echo $order['id']; ?>, 'processing')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php elseif ($order['status'] === 'processing'): ?>
                                            <button class="btn btn-sm" style="background: #0dcaf0;" onclick="updateStatus(<?php echo $order['id']; ?>, 'shipped')">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo $statusFilter; ?>&date_filter=<?php echo $dateFilter; ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo $statusFilter; ?>&date_filter=<?php echo $dateFilter; ?>" 
                                       class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo $statusFilter; ?>&date_filter=<?php echo $dateFilter; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
                    <h1>Order Summary</h1>
                    <div class="stats-list">
                        <div class="stat-item">
                            <p>Total Orders</p>
                            <span><?php echo $totalOrders; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Pending</p>
                            <span><?php echo $statusCounts['pending'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Processing</p>
                            <span><?php echo $statusCounts['processing'] ?? 0; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Delivered</p>
                            <span><?php echo $statusCounts['delivered'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h1>Quick Actions</h1>
                    <div class="action-buttons">
                        <a href="?status=pending" class="action-btn">
                            <i class="fas fa-clock"></i>
                            <span>View Pending Orders</span>
                        </a>
                        <a href="?status=processing" class="action-btn">
                            <i class="fas fa-sync"></i>
                            <span>View Processing</span>
                        </a>
                        <a href="?date_filter=today" class="action-btn">
                            <i class="fas fa-calendar-day"></i>
                            <span>Today's Orders</span>
                        </a>
                        <a href="analytics.php" class="action-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>View Analytics</span>
                        </a>
                    </div>
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

    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || 'orders.php';
        navItems.forEach((navItem) => {
            const link = navItem.querySelector('a');
            if (link && link.getAttribute('href') === currentPage) {
                navItems.forEach((item) => item.classList.remove("active"));
                navItem.classList.add("active");
            }
        });
    });

    let currentOrderId = null;

    function showSwalResult(data, successTitle = 'Success', errorTitle = 'Error') {
        Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? successTitle : errorTitle,
            text: data.message,
            customClass: {
                confirmButton: 'swal-confirm'
            }
        }).then(() => {
            if (data.success) {
                location.reload();
            }
        });
    }
    
    async function postData(action, formData) {
        const fetchOptions = {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=1&action=${action}&${formData}`
        };

        try {
            const response = await fetch('orders.php', fetchOptions);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        } catch (error) {
            console.error('Fetch error:', error);
            return { success: false, message: 'Could not connect to the server or process the request.' };
        }
    }

    function updateStatus(orderId, status) {
        const statusNames = {
            'pending': 'Pending',
            'processing': 'Processing',
            'shipped': 'Shipped',
            'delivered': 'Delivered',
            'cancelled': 'Cancelled'
        };
        
        Swal.fire({
            title: 'Confirm Status Change',
            text: `Are you sure you want to change the order status to "${statusNames[status]}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Change Status',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            customClass: {
                confirmButton: 'swal-confirm',
                cancelButton: 'swal-cancel',
            },
            preConfirm: () => {
                const formData = `order_id=${orderId}&status=${status}`;
                return postData('update_status', formData);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showSwalResult(result.value, 'Status Updated!', 'Status Update Failed');
            }
        });
    }
    
    function updateStatusFromModal(status) {
        if (currentOrderId) {
            updateStatus(currentOrderId, status);
        }
    }

 async function viewOrder(orderId) {
    currentOrderId = orderId;
    
    Swal.fire({
        title: 'Loading Order Details...',
        text: 'Please wait while we fetch the order data.',
        didOpen: () => {
            Swal.showLoading();
        },
        allowOutsideClick: false,
        showConfirmButton: false
    });

    const data = await postData('get_order', 'order_id=' + orderId);
    
    if (!data.success) {
        Swal.fire('Error', data.message, 'error');
        return;
    }

    const order = data.order;
    const statusBadges = {
        'pending': 'warning',
        'processing': 'info',
        'shipped': 'primary',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    
    let productsHtml = '';
    let subtotal = 0;
    
    order.items.forEach(item => {
        const itemTotal = parseFloat(item.total_price);
        subtotal += itemTotal;
        
        // Product Image
        const productImage = item.product_image 
            ? `<img src="../${item.product_image}" class="product-image-thumb" alt="${item.product_name}">`
            : `<div style="width:80px;height:80px;background:#e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-image" style="color:#94a3b8;"></i></div>`;
        
        // Parse product_snapshot for selected color and size
        let selectedColorHtml = '';
        let selectedSizeHtml = '';
        let colorData = null;
        let sizeData = null;
        
        // Try to get from product_snapshot first
        if (item.product_snapshot) {
            try {
                const snapshot = JSON.parse(item.product_snapshot);
                
                if (snapshot.color && snapshot.color.name) {
                    colorData = snapshot.color;
                }
                
                if (snapshot.size && snapshot.size.name) {
                    sizeData = snapshot.size;
                }
            } catch (e) {
                console.error('Error parsing product_snapshot:', e, item.product_snapshot);
            }
        }
        
        // Fallback: Use selected_color_id and selected_size_id if snapshot is empty
        // The backend should have already fetched the names in the item object
        if (!colorData && item.selected_color_id) {
            // Check if color name is already in the item
            colorData = {
                id: item.selected_color_id,
                name: item.color_name || 'Color',
                hex_code: item.color_hex || '#cccccc'
            };
        }
        
        if (!sizeData && item.selected_size_id) {
            // Check if size name is already in the item
            sizeData = {
                id: item.selected_size_id,
                name: item.size_name || 'Size'
            };
        }
        
        // Display color if available
        if (colorData && colorData.name) {
            const hexCode = colorData.hex_code || '#cccccc';
            selectedColorHtml = `
                <div class="attribute-group">
                    <label>Selected Color</label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;background-color:${hexCode};border-radius:50%;border:3px solid #10b981;box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
                        <span style="font-weight:700;color:#10b981;font-size:0.95rem;">${colorData.name}</span>
                    </div>
                </div>
            `;
        }
        
        // Display size if available
        if (sizeData && sizeData.name) {
            selectedSizeHtml = `
                <div class="attribute-group">
                    <label>Selected Size</label>
                    <div>
                        <span class="size-tag" style="background:#10b981;padding:6px 14px;font-size:0.9rem;">${sizeData.name}</span>
                    </div>
                </div>
            `;
        }
        
        // Additional Images
        let galleryHtml = '';
        if (item.images && item.images.length > 0) {
            galleryHtml = `
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <label style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:600;display:block;margin-bottom:8px;">
                        Product Gallery
                    </label>
                    <div class="product-gallery">
            `;
            item.images.forEach(img => {
                galleryHtml += `<img src="../${img.image_path}" class="gallery-image" alt="Product" onclick="viewImage('../${img.image_path}')">`;
            });
            galleryHtml += '</div></div>';
        }
        
        productsHtml += `
            <div class="product-details-card">
                <div class="product-header">
                    ${productImage}
                    <div class="product-main-info">
                        <h5>${item.product_name}</h5>
                        <div class="sku">SKU: ${item.sku || 'N/A'}</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                            <div>
                                <span style="font-size:0.85rem;color:#94a3b8;">Qty: <strong>${item.quantity}</strong></span>
                                <span style="margin:0 10px;">•</span>
                                <span style="font-size:0.85rem;color:#94a3b8;">Price: <strong>$${parseFloat(item.unit_price).toFixed(2)}</strong></span>
                            </div>
                            <div style="font-size:1.1rem;font-weight:700;color:rgb(73,57,113);">
                                $${itemTotal.toFixed(2)}
                            </div>
                        </div>
                    </div>
                </div>
                ${(selectedColorHtml || selectedSizeHtml) ? `
                    <div class="product-attributes">
                        ${selectedColorHtml}
                        ${selectedSizeHtml}
                    </div>
                ` : `
                    <div style="margin-top:10px;padding:8px;background:#fef3c7;border-radius:8px;font-size:0.85rem;color:#92400e;">
                        ⚠️ No color or size selected for this item
                    </div>
                `}
                ${galleryHtml}
            </div>
        `;
    });
    
    const shippingAddress = order.address_line_1 
        ? `${order.address_line_1}${order.address_line_2 ? ', ' + order.address_line_2 : ''}<br>
           ${order.city}, ${order.state} ${order.postal_code}<br>
           ${order.country}`
        : 'No shipping address provided';
    
    const contentHtml = `
        <div style="text-align: left; padding: 0 10px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <h4 style="margin-bottom: 10px; color: #484d53;"><i class="fas fa-user"></i> Customer</h4>
                    <p><strong>Name:</strong> ${order.first_name} ${order.last_name}</p>
                    <p><strong>Email:</strong> ${order.email}</p>
                    <p><strong>Phone:</strong> ${order.phone || 'N/A'}</p>
                </div>
                <div>
                    <h4 style="margin-bottom: 10px; color: #484d53;"><i class="fas fa-box"></i> Order Info</h4>
                    <p><strong>Date:</strong> ${new Date(order.created_at).toLocaleDateString()}</p>
                    <p><strong>Status:</strong> <span class="badge ${statusBadges[order.status] ?? 'info'}">${ucfirst(order.status)}</span></p>
                    <p><strong>Payment:</strong> ${order.payment_method || 'N/A'}</p>
                </div>
            </div>
            
            <h4 style="margin: 20px 0 10px; color: #484d53;"><i class="fas fa-map-marker-alt"></i> Shipping Address</h4>
            <p style="background: #f8fafc; padding: 10px; border-radius: 8px;">${shippingAddress}</p>
            
            <h4 style="margin: 20px 0 10px; color: #484d53;"><i class="fas fa-shopping-cart"></i> Order Items (${order.items.length})</h4>
            <div style="max-height: 400px; overflow-y: auto;">
                ${productsHtml}
            </div>
            
            <div style="background:#f8fafc;padding:15px;border-radius:8px;margin-top:15px;">
                <div style="display:flex;justify-content:space-between;font-size:1.1rem;">
                    <strong>Vendor Subtotal:</strong>
                    <strong style="color:rgb(73,57,113);">$${subtotal.toFixed(2)}</strong>
                </div>
            </div>

            <h4 style="margin: 20px 0 10px; color: #484d53;">Update Order Status</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="swal-status-btn" style="background: #f59e0b;" onclick="updateStatusFromModal('pending')" ${order.status === 'pending' ? 'disabled' : ''}>Pending</button>
                <button class="swal-status-btn" style="background: #3b82f6;" onclick="updateStatusFromModal('processing')" ${order.status === 'processing' ? 'disabled' : ''}>Processing</button>
                <button class="swal-status-btn" style="background: #0dcaf0;" onclick="updateStatusFromModal('shipped')" ${order.status === 'shipped' ? 'disabled' : ''}>Shipped</button>
                <button class="swal-status-btn" style="background: #10b981;" onclick="updateStatusFromModal('delivered')" ${order.status === 'delivered' ? 'disabled' : ''}>Delivered</button>
                <button class="swal-status-btn" style="background: #ef4444;" onclick="updateStatusFromModal('cancelled')" ${order.status === 'cancelled' ? 'disabled' : ''}>Cancelled</button>
            </div>
        </div>
    `;

    Swal.fire({
        title: `Order Details - #${order.order_number}`,
        html: contentHtml,
        width: '900px',
        showCloseButton: true,
        showConfirmButton: true,
        confirmButtonText: 'Close',
        customClass: {
            confirmButton: 'swal-confirm',
        },
    });
}
    
    function viewImage(imageSrc) {
        Swal.fire({
            imageUrl: imageSrc,
            imageAlt: 'Product Image',
            showConfirmButton: false,
            showCloseButton: true,
            background: 'transparent',
            backdrop: 'rgba(0,0,0,0.8)',
            customClass: {
                popup: 'swal-image-popup'
            }
        });
    }
    
    function ucfirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
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