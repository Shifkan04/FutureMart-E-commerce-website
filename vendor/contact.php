<?php
require_once '../config.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
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

// Handle new support ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    try {
        $category = sanitizeInput($_POST['category']);
        $priority = sanitizeInput($_POST['priority']);
        $subject = sanitizeInput($_POST['subject']);
        $description = sanitizeInput($_POST['description']);
        $contact_phone = sanitizeInput($_POST['contact_phone']);
        $preferred_contact = sanitizeInput($_POST['preferred_contact']);

        // Handle file upload if present
        $attachment = null;
        if (isset($_FILES['attachments']) && $_FILES['attachments']['error'][0] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/tickets/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file = $_FILES['attachments'];
            $filename = uniqid() . '_' . basename($file['name'][0]);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'][0], $filepath)) {
                $attachment = 'tickets/' . $filename;
            }
        }

        // Insert support ticket as contact message
        $stmt = $pdo->prepare("
            INSERT INTO contact_messages 
            (sender_id, sender_type, sender_name, sender_email, subject, message, 
             priority, status, attachment, ip_address, user_agent) 
            VALUES (?, 'vendor', ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
        ");

        $full_message = "Category: $category\n\nDescription: $description\n\nPreferred Contact: $preferred_contact";
        if (!empty($contact_phone)) {
            $full_message .= "\nContact Phone: $contact_phone";
        }

        $stmt->execute([
            $user_id,
            $vendor['first_name'] . ' ' . $vendor['last_name'],
            $vendor['email'],
            $subject,
            $full_message,
            $priority,
            $attachment,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $success_message = "Support ticket created successfully!";
    } catch (PDOException $e) {
        error_log("Error creating ticket: " . $e->getMessage());
        $error_message = "Failed to create support ticket. Please try again.";
    }
}

// Handle email support form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
    try {
        $subject = sanitizeInput($_POST['email_subject']);
        $message = sanitizeInput($_POST['email_message']);

        // Handle file upload
        $attachment = null;
        if (isset($_FILES['email_attachments']) && $_FILES['email_attachments']['error'][0] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/tickets/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file = $_FILES['email_attachments'];
            $filename = uniqid() . '_' . basename($file['name'][0]);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'][0], $filepath)) {
                $attachment = 'tickets/' . $filename;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO contact_messages 
            (sender_id, sender_type, sender_name, sender_email, subject, message, 
             priority, status, attachment, ip_address, user_agent) 
            VALUES (?, 'vendor', ?, ?, ?, ?, 'normal', 'pending', ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $vendor['first_name'] . ' ' . $vendor['last_name'],
            $vendor['email'],
            $subject,
            $message,
            $attachment,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $success_message = "Email sent successfully!";
    } catch (PDOException $e) {
        error_log("Error sending email: " . $e->getMessage());
        $error_message = "Failed to send email. Please try again.";
    }
}

// Get vendor's support tickets with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

try {
    // Build query based on filter
    $where_clause = "sender_id = ? AND sender_type = 'vendor'";
    $params = [$user_id];

    if ($filter === 'open') {
        $where_clause .= " AND status IN ('pending', 'in_progress')";
    } elseif ($filter === 'resolved') {
        $where_clause .= " AND status IN ('resolved', 'closed')";
    }

    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE $where_clause");
    $count_stmt->execute($params);
    $total_tickets = $count_stmt->fetchColumn();
    $total_pages = ceil($total_tickets / $per_page);

    // Get tickets
    $stmt = $pdo->prepare("
        SELECT * FROM contact_messages 
        WHERE $where_clause 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching tickets: " . $e->getMessage());
    $tickets = [];
    $total_pages = 1;
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
    <title>Contact & Support - <?php echo APP_NAME; ?></title>
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
            display: grid;
            grid-template-columns: 75% 25%;
        }

        .left-content {
            display: flex;
            flex-direction: column;
            background: #f6f7fb;
            margin: 15px;
            padding: 20px;
            border-radius: 15px;
            overflow-y: auto;
            max-height: calc(100vh - -400px);
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

        .support-options h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .support-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .support-card {
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            transition: all 0.3s ease;
        }

        .support-card:hover {
            transform: translateY(-10px);
            box-shadow: rgba(0, 0, 0, 0.24) 0px 8px 16px;
        }

        .support-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin: 0 auto 20px;
        }

        .support-icon.chat {
            background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
        }

        .support-icon.phone {
            background: linear-gradient(240deg, #97e7d1, #ecfcc3);
        }

        .support-icon.email {
            background: linear-gradient(240deg, #e5a243ab, #f7f7aa);
        }

        .support-card h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #484d53;
        }

        .support-card p {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 15px;
        }

        .support-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            margin-bottom: 15px;
        }

        .support-info {
            margin: 15px 0;
        }

        .support-info h6 {
            font-size: 1rem;
            font-weight: 700;
            color: #484d53;
            margin-bottom: 5px;
        }

        .support-info small {
            font-size: 0.85rem;
            color: #64748b;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
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

        .tickets-section {
            flex: 1;
            min-height: 300px;
        }

        .tickets-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .tickets-header h1 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .filter-tab:hover {
            border-color: rgb(73, 57, 113);
            color: rgb(73, 57, 113);
        }

        .filter-tab.active {
            background: rgb(73, 57, 113);
            color: white;
            border-color: rgb(73, 57, 113);
        }

        .tickets-container {
            background: white;
            border-radius: 15px;
            padding: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .ticket-card {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 10px;
            border-left: 4px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .ticket-card:hover {
            transform: translateX(5px);
            box-shadow: rgba(0, 0, 0, 0.1) 0px 2px 8px;
        }

        .ticket-card.pending {
            border-left-color: #f59e0b;
        }

        .ticket-card.in-progress {
            border-left-color: #3b82f6;
        }

        .ticket-card.resolved {
            border-left-color: #10b981;
        }

        .ticket-card.closed {
            border-left-color: #64748b;
        }

        .ticket-content {
            display: grid;
            grid-template-columns: 15% 35% 15% 15% 20%;
            gap: 15px;
            align-items: center;
        }

        .ticket-id h6 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #484d53;
        }

        .ticket-id small {
            font-size: 0.75rem;
            color: #64748b;
        }

        .ticket-subject h6 {
            margin: 0 0 5px 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #484d53;
        }

        .ticket-subject small {
            font-size: 0.8rem;
            color: #64748b;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            display: block;
            margin-bottom: 15px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: #484d53;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: rgb(73, 57, 113);
            color: white;
        }

        .pagination .active {
            background: rgb(73, 57, 113);
            color: white;
        }

        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
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

        .faq-section {
            padding: 15px 10px;
            overflow-y: auto;
        }

        .faq-section h1 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #484d53;
        }

        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 30px;
        }

        .faq-item {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            transform: translateY(-2px);
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .faq-question {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #484d53;
            font-size: 0.9rem;
        }

        .faq-question i {
            color: rgb(73, 57, 113);
        }

        .quick-links h1 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #484d53;
        }

        .link-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .link-item {
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
        }

        .link-item:hover {
            transform: translateY(-2px);
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .link-item i {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 25px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-header h2 {
            font-size: 1.3rem;
            color: #484d53;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #484d53;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: "Nunito", sans-serif;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: rgb(73, 57, 113);
        }

        .form-group small {
            font-size: 0.8rem;
            color: #64748b;
            display: block;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #484d53;
        }

        .faq-list {
            max-width: 600px;
            margin: 0 auto;
            font-family: "Nunito", sans-serif;
        }

        .faq-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-question {
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #000000ff;
            font-weight: 600;
        }

        .faq-question:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
            color: #080808ff;
            padding: 0 20px;
            transition: max-height 0.4s ease, padding 0.3s ease;
        }

        .faq-item.active .faq-answer {
            max-height: 200px;
            /* enough space for text */
            padding: 15px 20px;
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

            .support-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .ticket-content {
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

            .support-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {
            main {
                grid-template-columns: 15% 85%;
            }

            .left-content {
                margin: 0 15px 15px 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }


        }

        /* 🔹 Responsive */
        @media (max-width: 992px) {
            .reply-box {
                right: 50px;
                width: 350px;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .reply-box {
                position: fixed;
                bottom: 50px;
                right: 20px;
                width: 90%;
                padding: 20px;
                border-radius: 12px;
            }

            .form-group {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group textarea {
                width: 100%;
                min-height: 80px;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .reply-box {
                bottom: 20px;
                right: 10px;
                width: 95%;
                padding: 15px;
            }

            .reply-box h2 {
                font-size: 1rem;
            }

            .btn {
                font-size: 0.9rem;
                padding: 10px;
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
            <div class="left-content">
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

                <div class="support-options">
                    <h1>Contact & Support</h1>
                    <div class="support-grid">
                        <div class="support-card">
                            <div class="support-icon chat">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h2>Live Chat</h2>
                            <p>Get instant help from our support team</p>
                            <span class="support-badge">Available 24/7</span>
                            <button class="btn btn-primary" onclick="alert('Live chat feature coming soon!')">
                                <i class="fas fa-comments"></i> Start Chat
                            </button>
                        </div>

                        <div class="support-card">
                            <div class="support-icon phone">
                                <i class="fas fa-phone"></i>
                            </div>
                            <h2>Phone Support</h2>
                            <p>Speak directly with our experts</p>
                            <div class="support-info">
                                <h6>+1-800-SUPPORT</h6>
                                <small>Mon-Fri: 9AM-6PM EST</small>
                            </div>
                            <button class="btn btn-success">
                                <i class="fas fa-phone"></i> Call Now
                            </button>
                        </div>

                        <div class="support-card">
                            <div class="support-icon email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h2>Email Support</h2>
                            <p>Send us detailed questions</p>
                            <div class="support-info">
                                <h6>support@ecommerce.com</h6>
                                <small>Response within 24 hours</small>
                            </div>
                            <button class="btn btn-warning" onclick="showModal('emailModal')">
                                <i class="fas fa-envelope"></i> Send Email
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tickets-section">
                    <div class="tickets-header">
                        <h1>My Support Tickets</h1>
                        <div class="filter-tabs">
                            <a href="?filter=all" class="filter-tab <?php echo ($filter === 'all') ? 'active' : ''; ?>">All</a>
                            <a href="?filter=open" class="filter-tab <?php echo ($filter === 'open') ? 'active' : ''; ?>">Open</a>
                            <a href="?filter=resolved" class="filter-tab <?php echo ($filter === 'resolved') ? 'active' : ''; ?>">Resolved</a>
                        </div>
                    </div>

                    <div class="tickets-container">
                        <?php if (empty($tickets)): ?>
                            <div class="empty-state">
                                <i class="fas fa-ticket-alt"></i>
                                <h3>No support tickets found</h3>
                                <p>Create a new ticket to get help</p>
                                <button class="btn btn-primary" onclick="showModal('ticketModal')" style="margin-top: 15px;">
                                    <i class="fas fa-plus"></i> New Ticket
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket):
                                $status_class = str_replace('_', '-', $ticket['status']);
                                $status_badges = [
                                    'pending' => 'warning',
                                    'in_progress' => 'primary',
                                    'resolved' => 'success',
                                    'closed' => 'secondary'
                                ];
                            ?>
                                <div class="ticket-card <?php echo $status_class; ?>">
                                    <div class="ticket-content">
                                        <div class="ticket-id">
                                            <h6>#TK-<?php echo str_pad($ticket['id'], 4, '0', STR_PAD_LEFT); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></small>
                                        </div>
                                        <div class="ticket-subject">
                                            <h6><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                                            <small><?php echo htmlspecialchars(substr($ticket['message'], 0, 50)); ?>...</small>
                                        </div>
                                        <div>
                                            <span class="badge <?php echo $status_badges[$ticket['status']]; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <small style="color: #64748b;"><?php echo ucfirst($ticket['priority']); ?> Priority</small>
                                        </div>
                                        <div>
                                            <a href="view_ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.85rem;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($total_pages > 1): ?>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"
                                            class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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

                <div class="faq-section">
                    <h1>Quick Actions</h1>
                    <div class="link-list" style="margin-bottom: 30px;">
                        <button onclick="showModal('ticketModal')" class="link-item" style="border: none; width: 100%; text-align: left;">
                            <i class="fas fa-plus-circle"></i>
                            <span>New Ticket</span>
                        </button>
                        <button onclick="showModal('emailModal')" class="link-item" style="border: none; width: 100%; text-align: left;">
                            <i class="fas fa-envelope"></i>
                            <span>Send Email</span>
                        </button>
                        <a href="?filter=open" class="link-item">
                            <i class="fas fa-folder-open"></i>
                            <span>Open Tickets</span>
                        </a>
                    </div>

                    <h1>FAQ</h1>
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-question-circle"></i>
                                <span>How do I add new products?</span>
                            </div>
                            <div class="faq-answer">
                                Go to the Products section and click the "Add Product" button. Fill in all required fields including name, price, category, and stock quantity.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-question-circle"></i>
                                <span>How to process orders?</span>
                            </div>
                            <div class="faq-answer">
                                Navigate to the Orders section, find pending orders, and click "Process" to move them to processing status. You can then assign delivery personnel.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-question-circle"></i>
                                <span>Managing inventory levels?</span>
                            </div>
                            <div class="faq-answer">
                                Use the Inventory section to monitor stock levels. Set up low stock alerts to get notified when products need restocking.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-question-circle"></i>
                                <span>Assigning delivery personnel?</span>
                            </div>
                            <div class="faq-answer">
                                In the Delivery section, you can view available delivery staff and assign ready orders to them. Track delivery progress in real-time.
                            </div>
                        </div>
                    </div>


                    <h1>Quick Links</h1>
                    <div class="link-list">
                        <a href="#" class="link-item">
                            <i class="fas fa-book"></i>
                            <span>User Manual</span>
                        </a>
                        <a href="#" class="link-item">
                            <i class="fas fa-video"></i>
                            <span>Video Tutorials</span>
                        </a>
                        <a href="#" class="link-item">
                            <i class="fas fa-download"></i>
                            <span>Mobile App</span>
                        </a>
                        <a href="#" class="link-item">
                            <i class="fas fa-globe"></i>
                            <span>Knowledge Base</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- New Ticket Modal -->
    <div class="modal-overlay" id="ticketModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Support Ticket</h2>
                <button class="close-btn" onclick="closeModal('ticketModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_ticket">
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <option value="technical">Technical Issue</option>
                            <option value="billing">Billing & Payments</option>
                            <option value="orders">Order Management</option>
                            <option value="inventory">Inventory Issues</option>
                            <option value="delivery">Delivery Problems</option>
                            <option value="account">Account Settings</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority *</label>
                        <select name="priority" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" placeholder="Brief description of the issue" required>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="5" placeholder="Detailed description of your issue..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Attachments</label>
                    <input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
                    <small>Maximum 5 files, 10MB each</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" placeholder="Optional phone number">
                    </div>
                    <div class="form-group">
                        <label>Preferred Contact Method</label>
                        <select name="preferred_contact">
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="chat">Live Chat</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('ticketModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Support Modal -->
    <div class="modal-overlay" id="emailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Send Email to Support</h2>
                <button class="close-btn" onclick="closeModal('emailModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send_email">

                <div class="form-group">
                    <label>To</label>
                    <input type="email" value="support@ecommerce.com" readonly>
                </div>

                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="email_subject" placeholder="Email subject" required>
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="email_message" rows="6" placeholder="Your message..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Attachments</label>
                    <input type="file" name="email_attachments[]" multiple>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('emailModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Email</button>
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

        // Set active menu based on current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'contact.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 5000);
            });
        });

        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal on outside click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });


        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const item = question.parentElement;
                item.classList.toggle('active');
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