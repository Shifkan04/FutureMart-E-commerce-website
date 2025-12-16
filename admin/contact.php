<?php
require_once '../config.php';
require_once '../EmailHelper.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_bulk_email') {
    $recipientType = trim($_POST['recipient_type'] ?? '');
    $subject = trim($_POST['bulk_subject'] ?? '');
    $message = trim($_POST['bulk_message'] ?? '');

    if (empty($recipientType) || empty($subject) || empty($message)) {
        $error = "All fields are required.";
    } else {
        try {
            $emailHelper = new EmailHelper();

            switch ($recipientType) {
                case 'user':
                    $query = "SELECT first_name, email FROM users WHERE role = 'user' AND email IS NOT NULL AND email <> ''";
                    break;
                case 'vendor':
                    $query = "SELECT first_name, email FROM users WHERE role = 'vendor' AND email IS NOT NULL AND email <> ''";
                    break;
                case 'delivery':
                    $query = "SELECT first_name, email FROM users WHERE role = 'delivery' AND email IS NOT NULL AND email <> ''";
                    break;
                default:
                    throw new Exception("Invalid recipient type");
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$recipients) {
                $error = "No recipients found for the selected group.";
            } else {
                $sentCount = 0;
                foreach ($recipients as $r) {
                    $email = $r['email'];
                    $name = $r['first_name'] ?? 'User';
                    if ($emailHelper->sendEmail($email, $subject, $message, $name, true)) {
                        $sentCount++;
                    } else {
                        error_log("Failed to send email to {$email}");
                    }
                }
                $success = "Emails successfully sent to {$sentCount} recipients in '{$recipientType}' group.";
            }
        } catch (Exception $e) {
            $error = "Error sending emails: " . $e->getMessage();
        }
    }
}

// Send individual reply
if (isset($_POST['action']) && $_POST['action'] === 'send_reply') {
    $messageId = (int)$_POST['message_id'];
    $reply = sanitizeInput($_POST['reply']);

    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();

    if ($message) {
        $stmt = $pdo->prepare("UPDATE contact_messages SET reply = ?, replied_at = NOW(), replied_by = ?, status = 'resolved' WHERE id = ?");
        $stmt->execute([$reply, $_SESSION['user_id'], $messageId]);

        if ($message['sender_id']) {
            $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $userStmt->execute([$message['sender_id']]);
            $senderInfo = $userStmt->fetch();
            $recipientType = $senderInfo['role'] ?? 'user';

            $notifyStmt = $pdo->prepare("INSERT INTO admin_messages (sender_id, recipient_id, sender_type, recipient_type, subject, message, priority, message_type, created_at) VALUES (?, ?, 'admin', ?, ?, ?, ?, 'support', NOW())");
            $notifyStmt->execute([$_SESSION['user_id'], $message['sender_id'], $recipientType, 'Re: ' . $message['subject'], $reply, $message['priority'] ?? 'normal']);

            logUserActivity($_SESSION['user_id'], 'notification_reply', "Replied to message #$messageId");
        }

        $success = "Reply sent successfully!";
    }
}

// Other POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
            $messageId = (int)$_POST['message_id'];
            $status = sanitizeInput($_POST['status']);
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            $stmt->execute([$status, $messageId]);
            $success = "Status updated successfully!";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_message') {
            $messageId = (int)$_POST['message_id'];
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $success = "Message deleted successfully!";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_resolved') {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE status = 'resolved'");
            $stmt->execute();
            $success = "All resolved messages deleted!";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'add_note') {
            $messageId = (int)$_POST['message_id'];
            $note = sanitizeInput($_POST['note']);
            $stmt = $pdo->prepare("INSERT INTO contact_notes (contact_message_id, admin_id, note) VALUES (?, ?, ?)");
            $stmt->execute([$messageId, $_SESSION['user_id'], $note]);
            $success = "Note added successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

$whereClause = "WHERE 1=1";
$filterParams = [];

if ($statusFilter !== 'all') {
    $whereClause .= " AND cm.status = ?";
    $filterParams[] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $whereClause .= " AND cm.sender_type = ?";
    $filterParams[] = $typeFilter;
}

if (!empty($searchQuery)) {
    $whereClause .= " AND (cm.sender_name LIKE ? OR cm.sender_email LIKE ? OR cm.subject LIKE ?)";
    $searchParam = "%$searchQuery%";
    $filterParams[] = $searchParam;
    $filterParams[] = $searchParam;
    $filterParams[] = $searchParam;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages cm $whereClause");
$stmt->execute($filterParams);
$totalMessages = $stmt->fetchColumn();
$totalPages = ceil($totalMessages / $limit);

$queryParams = array_merge($filterParams, [$limit, $offset]);
$stmt = $pdo->prepare("SELECT cm.*, u.first_name as admin_name, CONCAT(u.first_name, ' ', u.last_name) as replied_by_name FROM contact_messages cm LEFT JOIN users u ON cm.replied_by = u.id $whereClause ORDER BY cm.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute($queryParams);
$messages = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved, SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed FROM contact_messages");
$stats = $stmt->fetch();

// Get admin info
$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name, email, avatar FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Get pending counts for badges
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pendingVendors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendor' AND status = 'inactive'")->fetchColumn();
$unreadNotifications = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND is_read = 0");
$unreadNotifications->execute([$_SESSION['user_id']]);
$unreadCount = $unreadNotifications->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management - <?php echo APP_NAME; ?></title>
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
            background: #f6f7fb;
            margin: 15px;
            padding: 20px;
            border-radius: 15px;
            overflow-y: auto;
            max-height: calc(100vh - 10px);

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
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
            padding: 20px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
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
        }

        .support-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .support-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .support-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 15px;
        }

        .support-icon.chat {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .support-icon.phone {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .support-icon.email {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            margin-bottom: 20px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .filter-row select,
        .filter-row input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            background: rgb(73, 57, 113);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
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

        .messages-table {
            background: white;
            border-radius: 15px;
            padding: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            text-align: left;
            padding: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #484d53;
            border-bottom: 2px solid #f6f7fb;
        }

        table td {
            padding: 12px;
            font-size: 0.9rem;
            color: #484d53;
            border-bottom: 1px solid #f6f7fb;
        }

        table tr:hover {
            background: #f6f7fb;
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

        .badge.secondary {
            background: rgba(148, 163, 184, 0.2);
            color: #64748b;
        }

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
            font-size: 0.95rem;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 0;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 2px solid #f6f7fb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h5 {
            margin: 0;
            font-size: 1.3rem;
            color: #484d53;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 2px solid #f6f7fb;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #484d53;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
            color: #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            color: #ef4444;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a {
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
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

        .swal2-confirm:hover,
        .swal2-cancel:hover {
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
                grid-template-columns: 1fr;
            }

            .filter-row {
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

            .support-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .support-options {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 700px) {
            main {
                grid-template-columns: 15% 85%;
            }

            .content {
                grid-template-columns: 100%;
                grid-template-rows: 45% 55%;
            }

            .left-content {
                margin: 0 15px 15px 15px;
            }

            .right-content {
                margin: 15px;
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
                        <?php if ($pendingOrders > 0): ?>
                            <span class="notification-badge"><?php echo $pendingOrders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="vendors.php">
                        <i class="fa fa-users-cog nav-icon"></i>
                        <span class="nav-text">Vendors</span>
                        <?php if ($pendingVendors > 0): ?>
                            <span class="notification-badge"><?php echo $pendingVendors; ?></span>
                        <?php endif; ?>
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

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="notifications.php">
                        <i class="fa fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount; ?></span>
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
        </nav>

        <section class="content">
            <div class="left-content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="stats-section">
                    <h1>Contact Management</h1>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-inbox"></i>
                            <div>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                                <p>Total Messages</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h3><?php echo number_format($stats['pending']); ?></h3>
                                <p>Pending</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-spinner"></i>
                            <div>
                                <h3><?php echo number_format($stats['in_progress']); ?></h3>
                                <p>In Progress</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h3><?php echo number_format($stats['resolved']); ?></h3>
                                <p>Resolved</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Options -->
                <div class="support-options">
                    <div class="support-card">
                        <div class="support-icon chat">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h5>Live Chat</h5>
                        <p style="font-size: 0.85rem; color: #64748b; margin: 10px 0;">Chat with users in real-time</p>
                        <span class="badge success">Available 24/7</span>
                    </div>

                    <div class="support-card">
                        <div class="support-icon phone">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5>Phone Support</h5>
                        <p style="font-size: 0.85rem; color: #64748b; margin: 10px 0;">+94-755-638086</p>
                        <small style="color: #64748b;">Mon-Fri: 9AM-6PM</small>
                    </div>

                    <div class="support-card">
                        <div class="support-icon email">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                        <h5>Bulk Email</h5>
                        <p style="font-size: 0.85rem; color: #64748b; margin: 10px 0;">Send to groups</p>
                        <button class="btn btn-sm" onclick="openModal('bulkEmailModal')">Send Email</button>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET">
                        <div class="filter-row">
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                            <select name="type" class="form-control" onchange="this.form.submit()">
                                <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="user" <?php echo $typeFilter === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="vendor" <?php echo $typeFilter === 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                                <option value="delivery" <?php echo $typeFilter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                                <option value="guest" <?php echo $typeFilter === 'guest' ? 'selected' : ''; ?>>Guest</option>
                            </select>
                            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Messages Table -->
                <div class="messages-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>No messages found</h3>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($msg['sender_name']); ?></strong>
                                            <?php if ($msg['priority'] === 'urgent' || $msg['priority'] === 'high'): ?>
                                                <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                                            <?php endif; ?>
                                            <br><small style="color: #94a3b8;"><?php echo htmlspecialchars($msg['sender_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($msg['subject'], 0, 40)) . (strlen($msg['subject']) > 40 ? '...' : ''); ?></td>
                                        <td><span class="badge info"><?php echo ucfirst($msg['sender_type']); ?></span></td>
                                        <td>
                                            <?php
                                            $statusColors = ['pending' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
                                            $color = $statusColors[$msg['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge <?php echo $color; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $msg['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm" onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm" onclick="replyMessage(<?php echo $msg['id']; ?>)">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                            <button class="btn btn-sm" onclick="deleteMessage(<?php echo $msg['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Hidden message data for modal -->
                                    <div id="message-data-<?php echo $msg['id']; ?>" style="display: none;">
                                        <?php echo htmlspecialchars(json_encode($msg)); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&type=<?php echo $typeFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&type=<?php echo $typeFilter; ?>&search=<?php echo urlencode($searchQuery); ?>"
                                    class="<?php echo $page == $i ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&type=<?php echo $typeFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
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
                    <h4><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h4>
                    <?php if (!empty($admin['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quick-stats">
                    <h1>Quick Statistics</h1>
                    <div class="stats-list">
                        <div class="stat-item">
                            <p>Total Messages</p>
                            <span><?php echo $stats['total']; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Pending</p>
                            <span><?php echo $stats['pending']; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>In Progress</p>
                            <span><?php echo $stats['in_progress']; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Resolved</p>
                            <span><?php echo $stats['resolved']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h1>Quick Actions</h1>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="openModal('bulkEmailModal')">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>Send Bulk Email</span>
                        </button>
                        <button class="action-btn" onclick="exportMessages()">
                            <i class="fas fa-download"></i>
                            <span>Export Messages</span>
                        </button>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete all resolved messages?')">
                            <input type="hidden" name="action" value="delete_resolved">
                            <button type="submit" class="action-btn" style="width: 100%;">
                                <i class="fas fa-trash-alt"></i>
                                <span>Delete Resolved</span>
                            </button>
                        </form>
                        <a href="dashboard.php" class="action-btn">
                            <i class="fas fa-home"></i>
                            <span>Back to Dashboard</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Bulk Email Modal -->
    <div id="bulkEmailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Send Bulk Email</h5>
                <button class="modal-close" onclick="closeModal('bulkEmailModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="send_bulk_email">

                    <div class="form-group">
                        <label>Recipient Type</label>
                        <select name="recipient_type" required>
                            <option value="">Choose recipient group...</option>
                            <option value="user">All Users</option>
                            <option value="vendor">All Vendors</option>
                            <option value="delivery">All Delivery Personnel</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="bulk_subject" required placeholder="Email subject">
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="bulk_message" required placeholder="Type your message here..."></textarea>
                    </div>

                    <div class="alert" style="background: rgba(59, 130, 246, 0.1); border-left-color: #3b82f6; color: #3b82f6;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> This will send individual emails to all recipients in the selected group.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('bulkEmailModal')" style="background: #94a3b8;">Cancel</button>
                    <button type="submit" class="btn" onclick="return confirm('Send bulk email to selected group?')">
                        <i class="fas fa-paper-plane"></i> Send Bulk Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Message Modal -->
    <div id="viewMessageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Message Details</h5>
                <button class="modal-close" onclick="closeModal('viewMessageModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewMessageContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('viewMessageModal')" style="background: #94a3b8;">Close</button>
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Reply to Message</h5>
                <button class="modal-close" onclick="closeModal('replyModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="send_reply">
                    <input type="hidden" name="message_id" id="replyMessageId">

                    <div class="form-group">
                        <label>To: <span id="replyToEmail"></span></label>
                    </div>

                    <div class="form-group">
                        <label>Subject: <span id="replySubject"></span></label>
                    </div>

                    <div class="form-group">
                        <label>Your Reply</label>
                        <textarea name="reply" required placeholder="Type your reply here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('replyModal')" style="background: #94a3b8;">Cancel</button>
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'contact.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });

            // Auto-dismiss alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function viewMessage(messageId) {
            const messageDataEl = document.getElementById('message-data-' + messageId);
            if (messageDataEl) {
                const message = JSON.parse(messageDataEl.textContent);
                const content = `
                    <div class="form-group">
                        <label>From:</label>
                        <p>${message.sender_name} (${message.sender_email})</p>
                    </div>
                    <div class="form-group">
                        <label>Type:</label>
                        <span class="badge info">${message.sender_type}</span>
                    </div>
                    <div class="form-group">
                        <label>Priority:</label>
                        <span class="badge ${message.priority === 'high' || message.priority === 'urgent' ? 'danger' : 'secondary'}">${message.priority}</span>
                    </div>
                    <div class="form-group">
                        <label>Subject:</label>
                        <p>${message.subject}</p>
                    </div>
                    <div class="form-group">
                        <label>Message:</label>
                        <div style="background: #f6f7fb; padding: 15px; border-radius: 10px; white-space: pre-wrap;">${message.message}</div>
                    </div>
                    ${message.reply ? `
                        <div class="alert alert-success">
                            <strong>Reply sent:</strong><br>
                            ${message.reply}
                        </div>
                    ` : ''}
                `;
                document.getElementById('viewMessageContent').innerHTML = content;
                openModal('viewMessageModal');
            }
        }

        function replyMessage(messageId) {
            const messageDataEl = document.getElementById('message-data-' + messageId);
            if (messageDataEl) {
                const message = JSON.parse(messageDataEl.textContent);
                document.getElementById('replyMessageId').value = messageId;
                document.getElementById('replyToEmail').textContent = message.sender_email;
                document.getElementById('replySubject').textContent = 'Re: ' + message.subject;
                openModal('replyModal');
            }
        }

        function deleteMessage(messageId) {
            if (confirm('Are you sure you want to delete this message?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="${messageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportMessages() {
            Swal.fire({
                title: 'Export Messages',
                text: 'Do you want to export all contact messages to CSV?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, export now',
                cancelButtonText: 'Cancel',
                confirmButtonColor: 'rgb(73, 57, 113)',
                cancelButtonColor: '#aaa'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'export_messages.php';
                }
            });
        }


        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        function confirmLogout(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Logout Confirmation',
                text: 'Are you sure you wanna log out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'rgb(73, 57, 113)', // your purple
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