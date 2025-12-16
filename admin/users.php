<?php
// users.php - Users Management with Fitness App Design
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

checkSessionTimeout();
$adminId = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Add User
    if ($_POST['action'] === 'add_user') {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists!']);
                exit();
            }

            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $hashedPassword,
                $_POST['role'],
                $_POST['status']
            ]);

            if ($result) {
                logUserActivity($adminId, 'user_create', "Created user: {$_POST['email']}");
                echo json_encode(['success' => true, 'message' => 'User created successfully!']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // Update User
    if ($_POST['action'] === 'update_user') {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['role'],
                $_POST['status'],
                $_POST['user_id']
            ]);

            if ($result) {
                logUserActivity($adminId, 'user_update', "Updated user ID: {$_POST['user_id']}");
                echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // Delete User
    if ($_POST['action'] === 'delete_user') {
        try {
            if ($_POST['user_id'] == $adminId) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account!']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$_POST['user_id']]);

            if ($result) {
                logUserActivity($adminId, 'user_delete', "Deleted user ID: {$_POST['user_id']}");
                echo json_encode(['success' => true, 'message' => 'User deleted successfully!']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // Bulk Actions
    if ($_POST['action'] === 'bulk_action') {
        try {
            $userIds = json_decode($_POST['user_ids'], true);
            $bulkAction = $_POST['bulk_action_type'];

            if (empty($userIds)) {
                echo json_encode(['success' => false, 'message' => 'No users selected']);
                exit();
            }

            $userIds = array_filter($userIds, function ($id) use ($adminId) {
                return $id != $adminId;
            });

            if (empty($userIds)) {
                echo json_encode(['success' => false, 'message' => 'Cannot perform bulk action on your own account']);
                exit();
            }

            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';

            if ($bulkAction === 'activate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $message = 'Users activated successfully!';
            } elseif ($bulkAction === 'deactivate') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $message = 'Users deactivated successfully!';
            } elseif ($bulkAction === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $message = 'Users deleted successfully!';
            }

            logUserActivity($adminId, 'user_bulk_action', "Bulk $bulkAction on " . count($userIds) . " users");
            echo json_encode(['success' => true, 'message' => $message]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Get filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Build query
$whereConditions = [];
$params = [];

if (!empty($roleFilter)) {
    $whereConditions[] = "u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "u.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get user statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'new_month' => 0
];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$stats['total'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active' AND role != 'admin'");
$stats['active'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'inactive' AND role != 'admin'");
$stats['inactive'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND role != 'admin'");
$stats['new_month'] = $stmt->fetch()['total'];

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users u $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $itemsPerPage);

// Get users
$query = "
    SELECT u.*,
           CONCAT(u.first_name, ' ', u.last_name) as full_name,
           COUNT(DISTINCT o.id) as order_count,
           COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $itemsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get admin details
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Get notification counts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$pendingOrders = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'vendor' AND status = 'inactive'");
$pendingVendors = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1");
$lowStockProducts = $stmt->fetch()['count'];

// Helper functions
function getStatusBadge($status)
{
    $badges = ['active' => 'success', 'inactive' => 'warning'];
    $class = $badges[$status] ?? 'secondary';
    return "<span class='badge badge-$class'>" . ucfirst($status) . "</span>";
}

function getRoleBadge($role)
{
    $badges = ['user' => 'info', 'vendor' => 'success', 'admin' => 'danger', 'delivery' => 'primary'];
    $class = $badges[$role] ?? 'secondary';
    $displayRole = $role === 'user' ? 'Customer' : ucfirst($role);
    return "<span class='badge badge-$class'>$displayRole</span>";
}

function getInitials($name)
{
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            padding: 0;
            margin: 0
        }

        nav ul,
        nav ul li {
            outline: 0
        }

        nav ul li a {
            text-decoration: none
        }

        body {
            font-family: "Nunito", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-image: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);
            background-repeat: no-repeat;
            background-size: cover
        }

        main {
            display: grid;
            grid-template-columns: 13% 87%;
            width: 100%;
            margin: 40px;
            background: rgb(254, 254, 254);
            box-shadow: 0 .5px 0 1px rgba(255, 255, 255, .23)inset, 0 1px 0 0 rgba(255, 255, 255, .66)inset, 0 4px 16px rgba(0, 0, 0, .12);
            border-radius: 15px;
            z-index: 10
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
            margin: 20px auto
        }

        .nav-item {
            position: relative;
            display: block
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
            border-bottom-left-radius: 20px
        }

        .nav-item b:nth-child(1) {
            position: absolute;
            top: -15px;
            height: 15px;
            width: 100%;
            background: #fff;
            display: none
        }

        .nav-item b:nth-child(1)::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-bottom-right-radius: 20px;
            background: rgb(73, 57, 113)
        }

        .nav-item b:nth-child(2) {
            position: absolute;
            bottom: -15px;
            height: 15px;
            width: 100%;
            background: #fff;
            display: none
        }

        .nav-item b:nth-child(2)::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-top-right-radius: 20px;
            background: rgb(73, 57, 113)
        }

        .nav-item.active b:nth-child(1),
        .nav-item.active b:nth-child(2) {
            display: block
        }

        .nav-item.active a {
            text-decoration: none;
            color: #000;
            background: rgb(254, 254, 254)
        }

        .nav-icon {
            width: 60px;
            height: 20px;
            font-size: 20px;
            text-align: center
        }

        .nav-text {
            display: block;
            width: 120px;
            height: 20px
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
            font-weight: 700
        }

        .content {
            background: #f6f7fb;
            padding: 20px;
            border-radius: 0 15px 15px 0;
            overflow-y: auto;
            max-height: calc(100vh - 80px)
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #484d53
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px
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
            font-size: 16px
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s ease;
            font-size: .9rem;
            text-decoration: none;
            display: inline-block
        }

        .btn-primary {
            background: rgb(73, 57, 113);
            color: white
        }

        .btn-primary:hover {
            background: rgb(93, 77, 133);
            transform: translateY(-2px)
        }

        .btn-success {
            background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%);
            color: #484d53
        }

        .btn-danger {
            background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%);
            color: #484d53
        }

        .btn-warning {
            background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%);
            color: #484d53
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: .85rem
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, .16)0 1px 3px;
            margin-bottom: 20px
        }

        .filter-section .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px
        }

        .form-group {
            display: flex;
            flex-direction: column
        }

        .form-group label {
            font-weight: 600;
            color: #484d53;
            margin-bottom: 5px;
            font-size: .9rem
        }

        .form-control {
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: .9rem;
            transition: all .3s ease
        }

        .form-control:focus {
            outline: none;
            border-color: rgb(124, 136, 224)
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, .16)0 1px 3px
        }

        .stat-card.total {
            background: linear-gradient(135deg, rgb(124, 136, 224)0%, #c3f4fc 100%)
        }

        .stat-card.active {
            background: linear-gradient(240deg, #97e7d1 0%, #ecfcc3 100%)
        }

        .stat-card.new {
            background: linear-gradient(240deg, #e5a243ab 0%, #f7f7aa 90%)
        }

        .stat-card.inactive {
            background: linear-gradient(240deg, #fc8ebe 0%, #fce5c3 100%)
        }

        .stat-card h6 {
            font-size: .9rem;
            font-weight: 600;
            color: #484d53;
            margin-bottom: 5px
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #484d53;
            margin: 5px 0
        }

        .stat-card small {
            font-size: .85rem;
            color: #484d53
        }

        .users-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, .16)0 1px 3px
        }

        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        .users-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #484d53
        }

        .users-table {
            width: 100%;
            border-collapse: collapse
        }

        .users-table th {
            text-align: left;
            padding: 12px;
            font-weight: 600;
            font-size: .9rem;
            color: #484d53;
            border-bottom: 2px solid #f6f7fb;
            background: #f6f7fb
        }

        .users-table td {
            padding: 12px;
            font-size: .9rem;
            color: #484d53;
            border-bottom: 1px solid #f6f7fb;
            vertical-align: middle
        }

        .users-table tr:hover {
            background: #f6f7fb
        }

        .user-logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 1rem
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: .75rem;
            font-weight: 600
        }

        .badge-success {
            background: rgba(16, 185, 129, .2);
            color: #10b981
        }

        .badge-warning {
            background: rgba(245, 158, 11, .2);
            color: #f59e0b
        }

        .badge-danger {
            background: rgba(239, 68, 68, .2);
            color: #ef4444
        }

        .badge-info {
            background: rgba(59, 130, 246, .2);
            color: #3b82f6
        }

        .badge-primary {
            background: rgba(124, 136, 224, .2);
            color: rgb(73, 57, 113)
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px
        }

        .pagination button {
            padding: 8px 15px;
            border: none;
            background: #f6f7fb;
            color: #484d53;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600
        }

        .pagination button.active {
            background: rgb(124, 136, 224);
            color: white
        }

        .pagination button:hover:not(.active) {
            background: #e5e7eb
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            z-index: 9999;
            justify-content: center;
            align-items: center
        }

        .modal.show {
            display: flex
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #484d53
        }

        .close {
            font-size: 2rem;
            font-weight: 300;
            color: #484d53;
            cursor: pointer;
            border: none;
            background: none
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

        @media(max-width:1500px) {
            main {
                grid-template-columns: 6% 94%
            }

            .main-menu h1 {
                display: none
            }

            .logo {
                display: block
            }

            .nav-text {
                display: none
            }
        }

        @media(max-width:910px) {
            main {
                grid-template-columns: 10% 90%;
                margin: 20px
            }
        }

        @media(max-width:700px) {
            main {
                grid-template-columns: 15% 85%
            }

            .users-table {
                font-size: .8rem
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px
            }

            .stats-grid {
                grid-template-columns: 1fr
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
                <i class="fa fa-rocket" style="font-size:24px;color:white"></i>
            </div>
            <ul>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="dashboard.php">
                        <i class="fa fa-home nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="products.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="orders.php">
                        <i class="fa fa-shopping-cart nav-icon"></i>
                        <span class="nav-text">Orders</span>
                        <?php if ($pendingOrders > 0): ?>
                            <span class="notification-badge"><?php echo $pendingOrders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="vendors.php">
                        <i class="fa fa-users-cog nav-icon"></i>
                        <span class="nav-text">Vendors</span>
                        <?php if ($pendingVendors > 0): ?>
                            <span class="notification-badge"><?php echo $pendingVendors; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item active">
                    <b></b><b></b>
                    <a href="users.php">
                        <i class="fa fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="delivery.php">
                        <i class="fa fa-truck nav-icon"></i>
                        <span class="nav-text">Delivery</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
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
                    <b></b><b></b>
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

        <div class="content">
            <div class="header">
                <h1><i class="fas fa-users"></i> Users Management</h1>
                <div class="user-section">
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                    <button class="btn btn-primary" onclick="exportUsers()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <?php if (!empty($admin['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="users.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select class="form-control" name="role" id="role">
                                <option value="">All Roles</option>
                                <option value="user" <?php echo $roleFilter === 'user' ? ' selected' : ''; ?>>Customer</option>
                                <option value="vendor" <?php echo $roleFilter === 'vendor' ? ' selected' : ''; ?>>Vendor</option>
                                <option value="delivery" <?php echo $roleFilter === 'delivery' ? ' selected' : ''; ?>>Delivery</option>
                                <option value="admin" <?php echo $roleFilter === 'admin' ? ' selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" name="status" id="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? ' selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" class="form-control" name="search" id="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search users...">
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
                <div class="stat-card total">
                    <h6>Total Users</h6>
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <small>Registered users</small>
                </div>
                <div class="stat-card active">
                    <h6>Active Users</h6>
                    <h3><?php echo number_format($stats['active']); ?></h3>
                    <small>Currently active</small>
                </div>
                <div class="stat-card new">
                    <h6>New This Month</h6>
                    <h3><?php echo number_format($stats['new_month']); ?></h3>
                    <small>New registrations</small>
                </div>
                <div class="stat-card inactive">
                    <h6>Inactive</h6>
                    <h3><?php echo number_format($stats['inactive']); ?></h3>
                    <small>Inactive accounts</small>
                </div>
            </div>

            <!-- Users Table -->
            <div class="users-section">
                <div class="users-header">
                    <h2>User Directory (<?php echo number_format($totalUsers); ?> total)</h2>
                    <div style="display:flex;gap:10px">
                        <button class="btn btn-warning btn-sm" onclick="showBulkActions()">
                            <i class="fas fa-tasks"></i> Bulk Actions
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllUsers"></th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Registration</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;padding:40px">
                                        <i class="fas fa-users" style="font-size:48px;opacity:.3;display:block;margin-bottom:10px"></i>
                                        <span style="opacity:.6">No users found</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>"></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <div class="user-logo">
                                                    <?php echo getInitials($user['full_name']); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                                    <small style="color:#999"><?php echo htmlspecialchars($user['email']); ?></small><br>
                                                    <small style="color:#999">ID: <?php echo $user['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo getRoleBadge($user['role']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo $user['order_count']; ?></strong><br>
                                            <small style="color:#999">orders</small>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($user['total_spent'], 2); ?></strong><br>
                                            <small style="color:#999">total spent</small>
                                        </td>
                                        <td><?php echo getStatusBadge($user['status']); ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="viewUser(<?php echo $user['id']; ?>)" style="margin-bottom:5px">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $user['id']; ?>)" style="margin-bottom:5px">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $adminId): ?>
                                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <button onclick="window.location.href='?page=<?php echo $page - 1; ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo $searchQuery; ?>'">
                                &laquo; Previous
                            </button>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <button class="<?php echo $page == $i ? 'active' : ''; ?>" onclick="window.location.href='?page=<?php echo $i; ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo $searchQuery; ?>'">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <button onclick="window.location.href='?page=<?php echo $page + 1; ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo $searchQuery; ?>'">
                                Next &raquo;
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                <button class="close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form id="addUserForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" class="form-control" id="addFirstName" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" class="form-control" id="addLastName" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" class="form-control" id="addEmail" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" class="form-control" id="addPhone">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px">
                    <div class="form-group">
                        <label>Role *</label>
                        <select class="form-control" id="addRole" required>
                            <option value="user">Customer</option>
                            <option value="vendor">Vendor</option>
                            <option value="delivery">Delivery</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select class="form-control" id="addStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top:15px">
                    <label>Password *</label>
                    <input type="password" class="form-control" id="addPassword" required minlength="6">
                    <small style="color:#999">Minimum 6 characters</small>
                </div>
                <div style="display:flex;gap:10px;margin-top:20px">
                    <button type="button" class="btn btn-primary" onclick="saveUser()" style="flex:1">Create User</button>
                    <button type="button" class="btn" onclick="closeModal('addUserModal')" style="flex:1;background:#e5e7eb;color:#484d53">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                <button class="close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" class="form-control" id="editFirstName" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" class="form-control" id="editLastName" required>
                    </div>
                </div>
                <div class="form-group" style="margin-top:15px">
                    <label>Email *</label>
                    <input type="email" class="form-control" id="editEmail" required>
                </div>
                <div class="form-group" style="margin-top:15px">
                    <label>Phone</label>
                    <input type="tel" class="form-control" id="editPhone">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px">
                    <div class="form-group">
                        <label>Role *</label>
                        <select class="form-control" id="editRole" required>
                            <option value="user">Customer</option>
                            <option value="vendor">Vendor</option>
                            <option value="delivery">Delivery</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select class="form-control" id="editStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:20px">
                    <button type="button" class="btn btn-primary" onclick="updateUser()" style="flex:1">Update</button>
                    <button type="button" class="btn" onclick="closeModal('editUserModal')" style="flex:1;background:#e5e7eb;color:#484d53">Cancel</button>
                </div>
            </form>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Navigation active state
        const navItems = document.querySelectorAll(".nav-item");
        navItems.forEach(navItem => {
            navItem.addEventListener("click", () => {
                navItems.forEach(item => item.classList.remove("active"));
                navItem.classList.add("active");
            });
        });

        // Modal functions
        function showAddModal() {
            document.getElementById('addUserModal').classList.add('show')
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show')
        }

        // Select all
        document.getElementById('selectAllUsers').addEventListener('change', function() {
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
        });

        // Save new user
        function saveUser() {
            const form = document.getElementById('addUserForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'add_user');
            formData.append('first_name', document.getElementById('addFirstName').value);
            formData.append('last_name', document.getElementById('addLastName').value);
            formData.append('email', document.getElementById('addEmail').value);
            formData.append('phone', document.getElementById('addPhone').value);
            formData.append('role', document.getElementById('addRole').value);
            formData.append('status', document.getElementById('addStatus').value);
            formData.append('password', document.getElementById('addPassword').value);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload()
                    } else {
                        alert(data.message)
                    }
                })
                .catch(error => alert('Error: ' + error));
        }

        // View user
        function viewUser(userId) {
            window.location.href = 'user_details.php?id=' + userId
        }

        // Edit user
        function editUser(userId) {
            fetch('get_user.php?id=' + userId)
                .then(response => response.json())
                .then(user => {
                    document.getElementById('editUserId').value = user.id;
                    document.getElementById('editFirstName').value = user.first_name;
                    document.getElementById('editLastName').value = user.last_name;
                    document.getElementById('editEmail').value = user.email;
                    document.getElementById('editPhone').value = user.phone || '';
                    document.getElementById('editRole').value = user.role;
                    document.getElementById('editStatus').value = user.status;

                    document.getElementById('editUserModal').classList.add('show');
                })
                .catch(error => alert('Error loading user: ' + error));
        }

        // Update user
        function updateUser() {
            const form = document.getElementById('editUserForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'update_user');
            formData.append('user_id', document.getElementById('editUserId').value);
            formData.append('first_name', document.getElementById('editFirstName').value);
            formData.append('last_name', document.getElementById('editLastName').value);
            formData.append('email', document.getElementById('editEmail').value);
            formData.append('phone', document.getElementById('editPhone').value);
            formData.append('role', document.getElementById('editRole').value);
            formData.append('status', document.getElementById('editStatus').value);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload()
                    } else {
                        alert(data.message)
                    }
                })
                .catch(error => alert('Error: ' + error));
        }

        // Delete user
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload()
                    } else {
                        alert(data.message)
                    }
                })
                .catch(error => alert('Error: ' + error));
        }

        // Bulk actions
        function showBulkActions() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select users to perform bulk actions.');
                return
            }

            const action = prompt('Enter action (activate/deactivate/delete):');
            if (!action) return;

            if (!['activate', 'deactivate', 'delete'].includes(action.toLowerCase())) {
                alert('Invalid action. Please enter: activate, deactivate, or delete');
                return
            }

            if (action === 'delete' && !confirm(`Are you sure you want to delete ${checkedBoxes.length} users? This cannot be undone.`)) return;

            const userIds = Array.from(checkedBoxes).map(cb => cb.value);
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'bulk_action');
            formData.append('user_ids', JSON.stringify(userIds));
            formData.append('bulk_action_type', action.toLowerCase());

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload()
                    } else {
                        alert(data.message)
                    }
                })
                .catch(error => alert('Error: ' + error));
        }

        // Export users
        function exportUsers() {
            window.location.href = 'export_users.php'
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addUserModal');
            const editModal = document.getElementById('editUserModal');
            if (event.target == addModal) addModal.classList.remove('show');
            if (event.target == editModal) editModal.classList.remove('show');
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