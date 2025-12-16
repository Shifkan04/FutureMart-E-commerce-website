<?php
require_once '../config_user.php';
require_once '../User.php';

startSecureSession();

// Database connection
$pdo = Database::getInstance()->getConnection();

// Check login status
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user = new User();
$userId = $_SESSION['user_id'];
$userData = $user->getUserById($userId);

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Get cart count
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'mark_read':
                $messageId = intval($_POST['message_id']);
                $stmt = $pdo->prepare("
                    UPDATE admin_messages 
                    SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND recipient_id = ? AND recipient_type = ?
                ");
                $stmt->execute([$messageId, $userId, $userData['role']]);

                echo json_encode(['success' => true, 'message' => 'Marked as read']);
                exit();

            case 'delete_notification':
                $messageId = intval($_POST['message_id']);
                $stmt = $pdo->prepare("
                    DELETE FROM admin_messages 
                    WHERE id = ? AND recipient_id = ? AND recipient_type = ?
                ");
                $stmt->execute([$messageId, $userId, $userData['role']]);

                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
                exit();

            case 'send_reply':
                $parentId = intval($_POST['parent_id']);
                $replyMessage = sanitizeInput($_POST['message']);

                if (empty($replyMessage)) {
                    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
                    exit();
                }

                // Get parent message to find admin
                $stmt = $pdo->prepare("SELECT sender_id FROM admin_messages WHERE id = ?");
                $stmt->execute([$parentId]);
                $parent = $stmt->fetch();

                if (!$parent) {
                    echo json_encode(['success' => false, 'message' => 'Original message not found']);
                    exit();
                }

                // Insert reply
                $stmt = $pdo->prepare("
                    INSERT INTO admin_messages 
                    (sender_id, recipient_id, sender_type, recipient_type, subject, message, parent_message_id)
                    VALUES (?, ?, ?, 'admin', 'Re: Notification Reply', ?, ?)
                ");
                $stmt->execute([$userId, $parent['sender_id'], $userData['role'], $replyMessage, $parentId]);

                echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
                exit();

            case 'mark_all_read':
                $stmt = $pdo->prepare("
                    UPDATE admin_messages 
                    SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                    WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0
                ");
                $stmt->execute([$userId, $userData['role']]);

                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                exit();
        }
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
        exit();
    }
}

// Get user notifications with conversation threads
$stmt = $pdo->prepare("
    SELECT 
        am.*,
        u.first_name as sender_first_name,
        u.last_name as sender_last_name,
        u.avatar as sender_avatar
    FROM admin_messages am
    LEFT JOIN users u ON am.sender_id = u.id
    WHERE am.recipient_id = ? AND am.recipient_type = ?
    ORDER BY am.created_at DESC
");
$stmt->execute([$userId, $userData['role']]);
$notifications = $stmt->fetchAll();

// Count unread notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count 
    FROM admin_messages 
    WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0
");
$stmt->execute([$userId, $userData['role']]);
$unreadCount = $stmt->fetch()['unread_count'];

date_default_timezone_set('Asia/Colombo'); // set your correct timezone

function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$dashboardStats = $user->getDashboardStats($userId);
$theme = $userData['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard-style.css" rel="stylesheet">
    <style>
        .notification-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .notification-card.unread {
            border-left: 4px solid var(--primary-color);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .notification-sender {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sender-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .sender-avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            border: 2px solid var(--primary-color);
        }

        .sender-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-light);
        }

        .sender-info small {
            color: var(--text-muted);
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .notification-subject {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .notification-message {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-normal {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .priority-high {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .priority-urgent {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        .reply-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            display: none;
        }

        .reply-form.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reply-textarea {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            padding: 0.75rem;
            width: 100%;
            min-height: 100px;
            resize: vertical;
        }

        .reply-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.08);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 5rem;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }

        .empty-state h5 {
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        .action-button {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-read {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .btn-read:hover {
            background: rgba(99, 102, 241, 0.2);
        }

        .btn-reply {
            background: rgba(6, 182, 212, 0.1);
            color: var(--accent-color);
        }

        .btn-reply:hover {
            background: rgba(6, 182, 212, 0.2);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>

<body>
    <?php if ($isLoggedIn): ?>
        <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>

        <div class="cart-modal" id="cartModal">
            <div class="cart-header">
                <h5><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h5>
                <button class="btn btn-sm btn-outline-light" onclick="toggleCart()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="cart-body">
                <div class="cart-content" id="cartItems">
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                </div>

                <div class="cart-summary">
                    <h6>Order Summary</h6>
                    <div class="summary-details">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span class="text-success">Free</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (8%):</span>
                            <span id="tax">$0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong id="total">$0.00</strong>
                        </div>
                    </div>

                    <button class="btn btn-success w-100 mb-2" onclick="proceedToCheckout()">
                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                    </button>
                    <button class="btn btn-outline-danger w-100" onclick="clearCart()">
                        <i class="fas fa-trash me-2"></i>Clear Cart
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-rocket me-2"></i>FutureMart
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../products.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <a href="#" class="nav-link cart-icon" onclick="toggleCart(); return false;">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
                    </a>

                    <div class="dropdown">
                        <div class="user-profile" data-bs-toggle="dropdown">
                            <?php if (!empty($userData['avatar'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($userData['avatar']); ?>"
                                    alt="Profile" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar-placeholder">
                                    <?= strtoupper(substr($userData['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <span class="d-none d-lg-inline">
                                <?= htmlspecialchars($userData['first_name']); ?>
                            </span>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile-settings.php">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a></li>
                            <li><a class="dropdown-item" href="orders.php">
                                    <i class="fas fa-shopping-bag me-2"></i>My Orders
                                </a></li>
                            <li><a class="dropdown-item" href="../settings.php">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid dashboard-wrapper">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 sidebar-wrapper">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li>
                            <a href="dashboard.php">
                                <i class="fas fa-home"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="orders.php">
                                <i class="fas fa-shopping-bag"></i>
                                <span>Orders</span>
                                <span class="badge"><?= $dashboardStats['total_orders'] ?></span>
                            </a>
                        </li>
                        <li>
                            <a href="wishlist.php">
                                <i class="fas fa-heart"></i>
                                <span>Wishlist</span>
                                <span class="badge"><?= $dashboardStats['wishlist_count'] ?></span>
                            </a>
                        </li>
                        <li>
                            <a href="testimonials.php">
                                <i class="fas fa-star"></i>
                                <span>Testimonials</span>
                            </a>
                        </li>
                        <li>
                            <a href="addresses.php">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Addresses</span>
                            </a>
                        </li>
                        <li>
                            <a href="profile-settings.php">
                                <i class="fas fa-user-edit"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <li>
                            <a href="security.php">
                                <i class="fas fa-shield-alt"></i>
                                <span>Security</span>
                            </a>
                        </li>
                        <li class="active">
                            <a href="notifications.php">
                                <i class="fas fa-bell"></i>
                                <span>Notifications</span>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <!-- Header -->
                <div class="welcome-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="welcome-title">
                                <i class="fas fa-bell me-2"></i>Notifications
                            </h1>
                            <p class="welcome-subtitle">
                                <?php if ($unreadCount > 0): ?>
                                    You have <?= $unreadCount ?> unread notification<?= $unreadCount > 1 ? 's' : '' ?>
                                <?php else: ?>
                                    All caught up! No unread notifications
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($unreadCount > 0): ?>
                                <button class="btn btn-primary" onclick="markAllAsRead()">
                                    <i class="fas fa-check-double me-2"></i>Mark All as Read
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-list me-1"></i>All Notifications
                    </button>
                    <button class="filter-btn" data-filter="unread">
                        <i class="fas fa-envelope me-1"></i>Unread (<?= $unreadCount ?>)
                    </button>
                    <button class="filter-btn" data-filter="read">
                        <i class="fas fa-envelope-open me-1"></i>Read
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="content-card">
                    <div id="notificationsList">
                        <?php if (empty($notifications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h5>No Notifications Yet</h5>
                                <p>You'll see notifications from admin replies here</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-card <?= $notification['is_read'] ? 'read' : 'unread' ?>"
                                    data-id="<?= $notification['id'] ?>"
                                    data-status="<?= $notification['is_read'] ? 'read' : 'unread' ?>">

                                    <?php if (!$notification['is_read']): ?>
                                        <span class="notification-badge">New</span>
                                    <?php endif; ?>

                                    <div class="notification-header">
                                        <div class="notification-sender">
                                            <?php if (!empty($notification['sender_avatar'])): ?>
                                                <img src="../admin/uploads/profiles/<?= htmlspecialchars($notification['sender_avatar']); ?>"
                                                    alt="Avatar" class="sender-avatar">
                                            <?php else: ?>
                                                <div class="sender-avatar-placeholder">
                                                    <?= $notification['sender_type'] === 'system' ? 'S' : strtoupper(substr($notification['sender_first_name'] ?? 'A', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="sender-info">
                                                <h6>
                                                    <?php if ($notification['sender_type'] === 'system'): ?>
                                                        System Notification
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($notification['sender_first_name'] . ' ' . $notification['sender_last_name']); ?>
                                                        <small>(Admin)</small>
                                                    <?php endif; ?>
                                                </h6>
                                                <small>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <span class="time"><?= time_elapsed_string($notification['created_at']); ?></span>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <button class="action-button btn-read"
                                                    onclick="markAsRead(<?= $notification['id'] ?>)"
                                                    title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button class="action-button btn-reply"
                                                onclick="toggleReply(<?= $notification['id'] ?>)"
                                                title="Reply">
                                                <i class="fas fa-reply"></i>
                                            </button>

                                            <button class="action-button btn-delete"
                                                onclick="deleteNotification(<?= $notification['id'] ?>)"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="notification-subject">
                                        <?= htmlspecialchars($notification['subject']); ?>
                                    </div>

                                    <div class="notification-message">
                                        <?= nl2br(htmlspecialchars($notification['message'])); ?>
                                    </div>

                                    <div class="notification-meta">
                                        <span class="priority-badge priority-<?= strtolower($notification['priority']); ?>">
                                            <i class="fas fa-flag me-1"></i>
                                            <?= ucfirst($notification['priority']); ?> Priority
                                        </span>

                                        <small class="text-muted">
                                            <?= date('F j, Y \a\t g:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>

                                    <!-- Reply Form -->
                                    <div class="reply-form" id="replyForm<?= $notification['id'] ?>">
                                        <h6 class="mb-3"><i class="fas fa-reply me-2"></i>Reply to Admin</h6>
                                        <textarea class="reply-textarea"
                                            id="replyText<?= $notification['id'] ?>"
                                            placeholder="Type your reply here..."></textarea>
                                        <div class="mt-2 d-flex gap-2">
                                            <button class="btn btn-primary"
                                                onclick="sendReply(<?= $notification['id'] ?>)">
                                                <i class="fas fa-paper-plane me-2"></i>Send Reply
                                            </button>
                                            <button class="btn btn-outline-secondary"
                                                onclick="toggleReply(<?= $notification['id'] ?>)">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/gsap.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // Filter notifications
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                const notifications = document.querySelectorAll('.notification-card');

                notifications.forEach(card => {
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else {
                        card.style.display = card.dataset.status === filter ? 'block' : 'none';
                    }
                });
            });
        });

        // Mark as read
        function markAsRead(id) {
            fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=mark_read&message_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const card = document.querySelector(`[data-id="${id}"]`);
                        card.classList.remove('unread');
                        card.classList.add('read');
                        card.dataset.status = 'read';
                        card.querySelector('.notification-badge')?.remove();
                        card.querySelector('.btn-read')?.remove();

                        showNotification(data.message, 'success');
                        updateUnreadCount();
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to mark as read', 'danger');
                });
        }

        // Mark all as read
        function markAllAsRead() {
            if (!confirm('Mark all notifications as read?')) return;

            fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-card.unread').forEach(card => {
                            card.classList.remove('unread');
                            card.classList.add('read');
                            card.dataset.status = 'read';
                            card.querySelector('.notification-badge')?.remove();
                            card.querySelector('.btn-read')?.remove();
                        });

                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to mark all as read', 'danger');
                });
        }

        // Delete notification
        function deleteNotification(id) {
            if (!confirm('Delete this notification? This action cannot be undone.')) return;

            fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=delete_notification&message_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const card = document.querySelector(`[data-id="${id}"]`);
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(100px)';

                        setTimeout(() => {
                            card.remove();

                            // Check if no notifications left
                            if (document.querySelectorAll('.notification-card').length === 0) {
                                document.getElementById('notificationsList').innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <h5>No Notifications Yet</h5>
                                    <p>You'll see notifications from admin replies here</p>
                                </div>
                            `;
                            }

                            showNotification(data.message, 'success');
                            updateUnreadCount();
                        }, 300);
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to delete notification', 'danger');
                });
        }

        // Toggle reply form
        function toggleReply(id) {
            const form = document.getElementById(`replyForm${id}`);
            form.classList.toggle('active');

            if (form.classList.contains('active')) {
                document.getElementById(`replyText${id}`).focus();
            }
        }

        // Send reply
        function sendReply(id) {
            const textarea = document.getElementById(`replyText${id}`);
            const message = textarea.value.trim();

            if (!message) {
                showNotification('Please enter a message', 'warning');
                return;
            }

            fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=send_reply&parent_id=${id}&message=${encodeURIComponent(message)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = '';
                        toggleReply(id);
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to send reply', 'danger');
                });
        }

        // Update unread count
        function updateUnreadCount() {
            const unreadCards = document.querySelectorAll('.notification-card.unread').length;
            const badge = document.querySelector('.sidebar-menu a[href="notifications.php"] .badge');

            if (unreadCards === 0 && badge) {
                badge.remove();
            } else if (badge) {
                badge.textContent = unreadCards;
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-custom`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 3000);
        }

        // Cart functions
        function toggleCart() {
            <?php if (!$isLoggedIn): ?>
                showNotification('Please login to view your cart', 'warning');
                setTimeout(() => {
                    window.location.href = '../login.php';
                }, 1500);
                return;
            <?php endif; ?>

            document.getElementById('cartModal').classList.toggle('open');
            document.getElementById('cartOverlay').classList.toggle('active');
            if (document.getElementById('cartModal').classList.contains('open')) {
                loadCartItems();
            }
        }

        function loadCartItems() {
            fetch('../cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=get'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCartItems(data.items, data.total);
                    }
                })
                .catch(error => console.error('Error loading cart:', error));
        }

        function displayCartItems(items, total) {
            const cartItemsContainer = document.getElementById('cartItems');

            if (items.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
            } else {
                cartItemsContainer.innerHTML = items.map(item => `
                    <div class="cart-item">
                        <div class="d-flex align-items-center gap-3">
                            <img src="${item.image || 'assets/img/future mart logo.png'}" alt="${item.name}" class="cart-item-image">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.name}</h6>
                                <p class="text-muted mb-1 small">${parseFloat(item.price).toFixed(2)} each</p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                                <button class="btn btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div>
                                <strong>${parseFloat(item.subtotal).toFixed(2)}</strong>
                                <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            const subtotal = parseFloat(total);
            const tax = subtotal * 0.08;
            const totalAmount = subtotal + tax;

            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' + tax.toFixed(2);
            document.getElementById('total').textContent = '$' + totalAmount.toFixed(2);
        }

        function updateCartQuantity(cartItemId, newQuantity) {
            fetch('../cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=update&cart_item_id=${cartItemId}&quantity=${newQuantity}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadCartItems();
                        document.getElementById('cartCount').textContent = data.cartCount;
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => showNotification('Error updating cart', 'danger'));
        }

        function removeFromCart(cartItemId) {
            if (!confirm('Remove this item from cart?')) return;

            fetch('../cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=remove&cart_item_id=${cartItemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        loadCartItems();
                        document.getElementById('cartCount').textContent = data.cartCount;
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => showNotification('Error removing item', 'danger'));
        }

        function clearCart() {
            if (!confirm('Are you sure you want to clear your cart?')) return;

            fetch('../cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=clear'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        loadCartItems();
                        document.getElementById('cartCount').textContent = '0';
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => showNotification('Error clearing cart', 'danger'));
        }

        function proceedToCheckout() {
            window.location.href = '../checkout.php';
        }

        function setupNavbarScroll() {
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupNavbarScroll();

            document.addEventListener('click', function(event) {
                const cartModal = document.getElementById('cartModal');
                const cartIcon = document.querySelector('.cart-icon');

                if (cartModal && cartIcon && !cartModal.contains(event.target) &&
                    !cartIcon.contains(event.target) && cartModal.classList.contains('open')) {
                    toggleCart();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const cartModal = document.getElementById('cartModal');
                    if (cartModal && cartModal.classList.contains('open')) {
                        toggleCart();
                    }
                }
            });

            // Animate notification cards on load
            // gsap.from('.notification-card', {
            //     duration: 0.5,
            //     y: 20,
            //     opacity: 0,
            //     stagger: 0.1,
            //     ease: 'power2.out'
            // });
        });
    </script>
</body>

</html>