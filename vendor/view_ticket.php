<?php
require_once '../config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticket_id) {
    header('Location: contact.php');
    exit();
}

$success_message = '';
$error_message = '';

// Get vendor information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'vendor'");
    $stmt->execute([$user_id]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching vendor: " . $e->getMessage());
    die("Database error occurred");
}

// Get ticket details - ensure it belongs to this vendor
try {
    $stmt = $pdo->prepare("
        SELECT cm.*, u.first_name, u.last_name, u.email 
        FROM contact_messages cm
        LEFT JOIN users u ON cm.replied_by = u.id
        WHERE cm.id = ? AND cm.sender_id = ? AND cm.sender_type = 'vendor'
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        $_SESSION['error'] = "Ticket not found or you don't have permission to view it.";
        header('Location: contact.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching ticket: " . $e->getMessage());
    die("Database error occurred");
}

// Get ticket notes/conversation history
try {
    $stmt = $pdo->prepare("
        SELECT cn.*, u.first_name, u.last_name, u.role
        FROM contact_notes cn
        JOIN users u ON cn.admin_id = u.id
        WHERE cn.contact_message_id = ?
        ORDER BY cn.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching notes: " . $e->getMessage());
    $notes = [];
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_reply') {
    try {
        $reply_message = sanitizeInput($_POST['reply_message']);

        if (empty($reply_message)) {
            $error_message = "Reply message cannot be empty.";
        } else {
            // Insert reply as a note
            $stmt = $pdo->prepare("
                INSERT INTO contact_notes (contact_message_id, admin_id, note, is_internal)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$ticket_id, $user_id, $reply_message]);

            // Update ticket status to in_progress if it was pending
            if ($ticket['status'] === 'pending') {
                $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'in_progress' WHERE id = ?");
                $stmt->execute([$ticket_id]);
            }

            $success_message = "Reply added successfully!";

            // Refresh the page to show new reply
            header("Location: view_ticket.php?id=$ticket_id&success=1");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error adding reply: " . $e->getMessage());
        $error_message = "Failed to add reply. Please try again.";
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Reply added successfully!";
}

// Get unread notifications count
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM admin_messages 
        WHERE recipient_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #TK-<?php echo str_pad($ticket_id, 4, '0', STR_PAD_LEFT); ?> - <?php echo APP_NAME; ?></title>
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
            display: flex;
            flex-direction: column;
            background: #f6f7fb;
            margin: 15px;
            padding: 20px;
            border-radius: 15px;
            overflow-y: auto;
            max-height: calc(100vh - 80px);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #484d53;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            width: fit-content;
        }

        .back-button:hover {
            border-color: rgb(73, 57, 113);
            color: rgb(73, 57, 113);
            transform: translateX(-5px);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        .ticket-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .ticket-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .ticket-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #484d53;
            margin: 0;
        }

        .ticket-badges {
            display: flex;
            gap: 10px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .badge.primary {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .badge.success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge.secondary {
            background: rgba(100, 116, 139, 0.2);
            color: #64748b;
        }

        .badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .badge.info {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .ticket-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 0.9rem;
        }

        .conversation-section {
            flex: 1;
            min-height: 300px;
        }

        .conversation-section h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #484d53;
        }

        .message-bubble {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            transition: all 0.3s ease;
        }

        .message-bubble:hover {
            transform: translateY(-2px);
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .message-bubble.vendor {
            background: linear-gradient(135deg, rgba(124, 136, 224, 0.1), rgba(195, 244, 252, 0.1));
            border-left: 4px solid rgb(124, 136, 224);
            margin-left: 30px;
        }

        .message-bubble.admin {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(236, 252, 195, 0.1));
            border-left: 4px solid #10b981;
            margin-right: 30px;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .message-author {
            font-weight: 700;
            color: #484d53;
            font-size: 0.95rem;
        }

        .message-time {
            font-size: 0.8rem;
            color: #64748b;
        }

        .message-content {
            font-size: 0.95rem;
            color: #484d53;
            line-height: 1.6;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            margin-top: 12px;
            transition: all 0.3s ease;
        }

        .attachment-link:hover {
            background: rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
        }

        .reply-box {
            display: grid;
            position: fixed;
            bottom: 70px;
            right: 110px;
            background: white;
            border-radius: 15px;
            padding: 25px;
            width: 400px;
            z-index: 9999;
            transition: all 0.3s ease;
            height: auto;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            margin-top: 20px;
            margin-bottom: 0;
        }

        .reply-box h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #484d53;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
            /* space between textarea & button */
        }

        .form-group textarea {
            flex: 1;
            /* make textarea take remaining space */
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-family: "Nunito", sans-serif;
            font-size: 0.95rem;
            resize: none;
            transition: all 0.3s ease;
            min-height: 50px;
            /* keep height consistent */
        }

        .form-group textarea:focus {
            outline: none;
            border-color: rgb(73, 57, 113);
            box-shadow: 0 0 0 3px rgba(73, 57, 113, 0.1);
        }


        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: rgb(73, 57, 113);
            color: white;
            height: 52px;
        }

        .info-alert {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 20px;
            color: #3b82f6;
            display: flex;
            align-items: center;
            gap: 10px;
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

            .message-bubble.vendor {
                margin-left: 0;
            }

            .message-bubble.admin {
                margin-right: 0;
            }
        }

        @media (max-width: 700px) {
            main {
                grid-template-columns: 15% 85%;
            }

            .content {
                margin: 0 15px 15px 15px;
            }

            .ticket-title {
                flex-direction: column;
                gap: 15px;
            }

            .ticket-badges {
                flex-wrap: wrap;
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
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item active">
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
            <a href="contact.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Support</span>
            </a>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="ticket-header">
                <div class="ticket-title">
                    <h1>#TK-<?php echo str_pad($ticket_id, 4, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($ticket['subject']); ?></h1>
                    <div class="ticket-badges">
                        <?php
                        $status_badges = [
                            'pending' => 'warning',
                            'in_progress' => 'primary',
                            'resolved' => 'success',
                            'closed' => 'secondary'
                        ];
                        $priority_badges = [
                            'low' => 'info',
                            'normal' => 'secondary',
                            'medium' => 'warning',
                            'high' => 'danger',
                            'urgent' => 'danger'
                        ];
                        ?>
                        <span class="badge <?php echo $status_badges[$ticket['status']]; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                        </span>
                        <span class="badge <?php echo $priority_badges[$ticket['priority']]; ?>">
                            <?php echo ucfirst($ticket['priority']); ?> Priority
                        </span>
                    </div>
                </div>
                <div class="ticket-meta">
                    <i class="fas fa-calendar"></i>
                    <span>Created: <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></span>
                </div>
            </div>

            <div class="conversation-section">
                <h2>Conversation</h2>

                <!-- Original Message -->
                <div class="message-bubble vendor">
                    <div class="message-header">
                        <span class="message-author"><?php echo htmlspecialchars($ticket['sender_name']); ?> (You)</span>
                        <span class="message-time"><?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></span>
                    </div>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                    </div>
                    <?php if ($ticket['attachment']): ?>
                        <a href="../uploads/<?php echo htmlspecialchars($ticket['attachment']); ?>" class="attachment-link" target="_blank">
                            <i class="fas fa-paperclip"></i>
                            <span>View Attachment</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Admin Reply (if exists) -->
                <?php if ($ticket['reply']): ?>
                    <div class="message-bubble admin">
                        <div class="message-header">
                            <span class="message-author">
                                <?php if ($ticket['first_name']): ?>
                                    <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?> (Support Team)
                                <?php else: ?>
                                    Support Team
                                <?php endif; ?>
                            </span>
                            <span class="message-time"><?php echo date('M d, Y h:i A', strtotime($ticket['replied_at'])); ?></span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($ticket['reply'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Conversation History -->
                <?php foreach ($notes as $note): ?>
                    <?php if (!$note['is_internal']): ?>
                        <div class="message-bubble <?php echo $note['role'] === 'vendor' ? 'vendor' : 'admin'; ?>">
                            <div class="message-header">
                                <span class="message-author">
                                    <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?>
                                    <?php echo $note['role'] === 'vendor' ? '(You)' : '(Support Team)'; ?>
                                </span>
                                <span class="message-time"><?php echo date('M d, Y h:i A', strtotime($note['created_at'])); ?></span>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Reply Box -->
            <?php if ($ticket['status'] !== 'closed'): ?>
                <div class="reply-box">
                    <h2>Add Reply</h2>
                    <form method="POST" class="reply-form">
                        <input type="hidden" name="action" value="add_reply">
                        <div class="form-group">
                            <textarea name="reply_message" rows="1" placeholder="Type your reply here..." required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="info-alert">
                    <i class="fas fa-info-circle"></i>
                    <span>This ticket has been closed. Please create a new ticket if you need further assistance.</span>
                </div>
            <?php endif; ?>

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

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 5000);
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