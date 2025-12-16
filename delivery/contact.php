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
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
    $stmt->execute([$delivery_person_id]);
    $delivery_person = $stmt->fetch();

    if (!$delivery_person) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Contact Page Error: " . $e->getMessage());
    die("An error occurred while loading the contact page.");
}

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
    $priority = filter_var($_POST['priority'], FILTER_SANITIZE_STRING);
    $subject = filter_var($_POST['subject'], FILTER_SANITIZE_STRING);
    $order_id = filter_var($_POST['order_id'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

    // Handle file upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/tickets/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['attachment'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $attachment_path = 'tickets/' . $filename;
            } else {
                $_SESSION['error'] = 'Failed to upload attachment.';
            }
        } else {
            $_SESSION['error'] = 'Invalid file type or size. Max 5MB, only images and PDF allowed.';
        }
    }

    // Only proceed if no upload error occurred
    if (!isset($_SESSION['error'])) {
        // Build full message with order ID if provided
        $full_message = $description;
        if (!empty($order_id)) {
            $full_message = "Order ID: " . $order_id . "\n\n" . $description;
        }
        $full_message .= "\n\nCategory: " . ucfirst(str_replace('-', ' ', $category));

        try {
            // Start transaction
            $pdo->beginTransaction();

            // Get admin ID
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch();

            if ($admin) {
                // Insert support ticket into contact_messages table
                $stmt = $pdo->prepare("
                    INSERT INTO contact_messages 
                    (sender_id, sender_type, sender_name, sender_email, subject, message, priority, status, attachment, ip_address, user_agent)
                    VALUES (?, 'user', ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                ");
                $stmt->execute([
                    $delivery_person_id,
                    $delivery_person['first_name'] . ' ' . $delivery_person['last_name'],
                    $delivery_person['email'],
                    $subject,
                    $full_message,
                    $priority,
                    $attachment_path,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                $ticket_id = $pdo->lastInsertId();
                $ticket_number = 'TK' . str_pad($ticket_id, 6, '0', STR_PAD_LEFT);

                // Also create admin message for notification
                $admin_message = $full_message;
                if ($attachment_path) {
                    $admin_message .= "\n\n📎 Attachment: " . $attachment_path;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO admin_messages 
                    (sender_id, recipient_id, sender_type, recipient_type, subject, message, priority, message_type)
                    VALUES (?, ?, 'user', 'admin', ?, ?, ?, 'support')
                ");
                $stmt->execute([
                    $delivery_person_id,
                    $admin['id'],
                    "[Support Ticket: $ticket_number] " . $subject,
                    $admin_message,
                    $priority
                ]);

                // Commit transaction
                $pdo->commit();

                $_SESSION['success'] = "Support ticket $ticket_number submitted successfully! We'll respond within 2 hours.";
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = 'Unable to submit ticket. No admin found.';
            }

        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Support Ticket Error: " . $e->getMessage());
            error_log("SQL Error Details: " . print_r($e->errorInfo, true));
            $_SESSION['error'] = 'An error occurred while submitting your ticket. Please try again. Error: ' . $e->getMessage();
        }
    }

    header('Location: contact.php');
    exit();
}

// Fetch my tickets
try {
    $stmt = $pdo->prepare("
        SELECT * FROM contact_messages 
        WHERE sender_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$delivery_person_id]);
    $my_tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Tickets Error: " . $e->getMessage());
    $my_tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - <?php echo APP_NAME; ?></title>
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

        .content {
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
        }

        /* Alert Messages */
        .alert {
          padding: 15px 20px;
          border-radius: 12px;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
          gap: 10px;
          font-weight: 600;
          animation: slideInDown 0.3s ease;
        }

        .alert-success {
          background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(134, 239, 172, 0.1));
          border-left: 4px solid #10b981;
          color: #065f46;
        }

        .alert-error {
          background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(252, 165, 165, 0.1));
          border-left: 4px solid #ef4444;
          color: #7f1d1d;
        }

        @keyframes slideInDown {
          from { transform: translateY(-20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }

        /* Header */
        .page-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 25px;
        }

        .page-header h1 {
          font-size: 1.5rem;
          font-weight: 700;
          color: #484d53;
          margin: 0;
        }

        .status-badge {
          padding: 8px 16px;
          border-radius: 20px;
          font-size: 0.9rem;
          font-weight: 600;
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
          display: inline-flex;
          align-items: center;
          gap: 6px;
        }

        /* Emergency Banner */
        .emergency-banner {
          background: linear-gradient(135deg, #ef4444, #dc2626);
          border-radius: 15px;
          padding: 25px;
          margin-bottom: 25px;
          color: white;
          box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
          display: flex;
          justify-content: space-between;
          align-items: center;
          flex-wrap: wrap;
          gap: 20px;
        }

        .emergency-content h3 {
          font-size: 1.3rem;
          font-weight: 700;
          margin-bottom: 8px;
        }

        .emergency-content p {
          margin: 0;
          opacity: 0.95;
        }

        .emergency-action .btn-call {
          padding: 12px 24px;
          background: white;
          color: #ef4444;
          border: none;
          border-radius: 12px;
          font-weight: 700;
          font-size: 1.1rem;
          cursor: pointer;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          gap: 10px;
          transition: all 0.3s ease;
        }

        .btn-call:hover {
          transform: scale(1.05);
          box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
        }

        /* Contact Options Grid */
        .contact-options {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 20px;
          margin-bottom: 25px;
        }

        .contact-card {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          border-radius: 15px;
          padding: 30px;
          text-align: center;
          color: #484d53;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .contact-card:hover {
          transform: translateY(-5px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .contact-card:nth-child(2) {
          background: linear-gradient(135deg, #e5a243ab 0%, #f7f7aa 90%);
        }

        .contact-card:nth-child(3) {
          background: linear-gradient(135deg, #97e7d1 0%, #ecfcc3 100%);
        }

        .contact-card i {
          font-size: 3rem;
          margin-bottom: 15px;
        }

        .contact-card h3 {
          font-size: 1.2rem;
          font-weight: 700;
          margin-bottom: 10px;
        }

        .contact-card p {
          font-size: 0.95rem;
          margin-bottom: 15px;
          opacity: 0.9;
        }

        .btn {
          padding: 10px 20px;
          font-size: 0.9rem;
          font-weight: 600;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 8px;
          text-decoration: none;
        }

        .btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-light {
          background: rgba(255, 255, 255, 0.9);
          color: #484d53;
          border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-primary {
          background: linear-gradient(135deg, rgb(73, 57, 113), rgb(93, 77, 133));
          color: white;
        }

        /* Form Section */
        .form-section {
          display: grid;
          grid-template-columns: 65% 35%;
          gap: 20px;
        }

        .form-card {
          background: white;
          border-radius: 15px;
          padding: 25px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .form-card h3 {
          font-size: 1.2rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .form-row {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 20px;
          margin-bottom: 20px;
        }

        .form-group {
          margin-bottom: 20px;
        }

        .form-label {
          font-size: 0.9rem;
          font-weight: 600;
          color: #484d53;
          margin-bottom: 8px;
          display: block;
        }

        .form-control {
          width: 100%;
          padding: 12px 15px;
          border: 2px solid #e5e7eb;
          border-radius: 10px;
          font-size: 0.95rem;
          font-family: "Nunito", sans-serif;
          transition: all 0.3s ease;
        }

        .form-control:focus {
          outline: none;
          border-color: rgb(124, 136, 224);
          box-shadow: 0 0 0 3px rgba(124, 136, 224, 0.1);
        }

        textarea.form-control {
          resize: vertical;
          min-height: 120px;
        }

        /* File Upload */
        .file-upload-wrapper {
          position: relative;
          overflow: hidden;
          display: inline-block;
          width: 100%;
        }

        .file-upload-input {
          position: absolute;
          font-size: 100px;
          opacity: 0;
          right: 0;
          top: 0;
          cursor: pointer;
        }

        .file-upload-label {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 12px 15px;
          background: #f6f7fb;
          border: 2px dashed #e5e7eb;
          border-radius: 10px;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .file-upload-label:hover {
          border-color: rgb(124, 136, 224);
          background: rgba(124, 136, 224, 0.05);
        }

        .file-upload-label i {
          font-size: 1.5rem;
          color: rgb(124, 136, 224);
        }

        .file-upload-text {
          flex: 1;
        }

        .file-upload-text strong {
          display: block;
          color: #484d53;
          font-size: 0.95rem;
        }

        .file-upload-text small {
          color: #9ca3af;
          font-size: 0.85rem;
        }

        .file-preview {
          margin-top: 10px;
          padding: 10px;
          background: #f6f7fb;
          border-radius: 8px;
          display: none;
          align-items: center;
          gap: 10px;
        }

        .file-preview.active {
          display: flex;
        }

        .file-preview-icon {
          width: 40px;
          height: 40px;
          background: rgb(124, 136, 224);
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
        }

        .file-preview-info {
          flex: 1;
        }

        .file-preview-name {
          font-weight: 600;
          color: #484d53;
          font-size: 0.9rem;
        }

        .file-preview-size {
          color: #9ca3af;
          font-size: 0.85rem;
        }

        .file-preview-remove {
          background: #ef4444;
          color: white;
          border: none;
          padding: 8px 12px;
          border-radius: 8px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.3s ease;
        }

        .file-preview-remove:hover {
          background: #dc2626;
        }

        /* My Tickets Section */
        .tickets-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          margin-bottom: 20px;
        }

        .tickets-card h3 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .ticket-item {
          padding: 15px;
          border: 2px solid #e5e7eb;
          border-radius: 10px;
          margin-bottom: 12px;
          transition: all 0.3s ease;
        }

        .ticket-item:hover {
          border-color: rgb(124, 136, 224);
          background: rgba(124, 136, 224, 0.02);
        }

        .ticket-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 8px;
        }

        .ticket-id {
          font-weight: 700;
          color: rgb(73, 57, 113);
        }

        .ticket-status {
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.8rem;
          font-weight: 600;
        }

        .status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-in_progress { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .status-resolved { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-closed { background: rgba(107, 114, 128, 0.2); color: #6b7280; }

        .ticket-subject {
          font-size: 0.95rem;
          color: #484d53;
          margin-bottom: 6px;
        }

        .ticket-meta {
          font-size: 0.85rem;
          color: #9ca3af;
          display: flex;
          align-items: center;
          gap: 15px;
        }

        .ticket-attachment {
          color: rgb(124, 136, 224);
          font-weight: 600;
        }

        /* FAQ Section */
        .faq-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          margin-bottom: 20px;
        }

        .faq-card h3 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .faq-item {
          padding: 15px 0;
          border-bottom: 1px solid #e5e7eb;
        }

        .faq-item:last-child {
          border-bottom: none;
        }

        .faq-question {
          font-size: 0.95rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
          margin-bottom: 8px;
        }

        .faq-answer {
          font-size: 0.9rem;
          color: #6b7280;
          margin: 0;
          line-height: 1.6;
        }

        .faq-answer a {
          color: rgb(73, 57, 113);
          text-decoration: none;
          font-weight: 600;
        }

        /* Support Hours Card */
        .hours-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .hours-card h3 {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .hours-item {
          margin-bottom: 15px;
        }

        .hours-item:last-child {
          margin-bottom: 0;
        }

        .hours-label {
          font-weight: 700;
          color: #484d53;
          display: block;
          margin-bottom: 4px;
        }

        .hours-value {
          color: #6b7280;
          font-size: 0.9rem;
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .form-section { grid-template-columns: 1fr; }
          .contact-options { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .form-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { margin: 15px; padding: 15px; }
          .emergency-banner { flex-direction: column; text-align: center; }
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
                    <a href="deliveries.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">My Deliveries</span>
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

                <li class="nav-item active">
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
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1><i class="fas fa-phone"></i> Contact Support</h1>
                <span class="status-badge">
                    <i class="fas fa-clock"></i> Available 24/7
                </span>
            </div>

            <!-- Emergency Banner -->
            <div class="emergency-banner">
                <div class="emergency-content">
                    <h3><i class="fas fa-exclamation-triangle"></i> Emergency Support</h3>
                    <p>For urgent delivery issues or emergencies, call our hotline immediately</p>
                </div>
                <div class="emergency-action">
                    <a href="tel:+94755638086" class="btn-call">
                        <i class="fas fa-phone"></i> +94 75 563 8086
                    </a>
                </div>
            </div>

            <!-- Contact Options -->
            <div class="contact-options">
                <div class="contact-card">
                    <i class="fas fa-phone"></i>
                    <h3>Phone Support</h3>
                    <p>Talk to our support team</p>
                    <a href="tel:+94755638086" class="btn btn-light">Call Now</a>
                </div>
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h3>Email Support</h3>
                    <p>Send us your queries</p>
                    <a href="mailto:futuremart273@gmail.com" class="btn btn-light">Send Email</a>
                </div>
                <div class="contact-card">
                    <i class="fas fa-comments"></i>
                    <h3>Messages</h3>
                    <p>View your messages</p>
                    <a href="messages.php" class="btn btn-light">Go to Messages</a>
                </div>
            </div>


            <!-- Form Section -->
            <div class="form-section">
                <div class="form-card">
                    <h3><i class="fas fa-ticket-alt"></i> Submit Support Ticket</h3>
                    <form method="POST" action="" enctype="multipart/form-data" id="ticketForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Issue Category</label>
                                <select class="form-control" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="delivery-issue">Delivery Issue</option>
                                    <option value="customer-complaint">Customer Complaint</option>
                                    <option value="technical-problem">Technical Problem</option>
                                    <option value="payment-query">Payment Query</option>
                                    <option value="vehicle-problem">Vehicle Problem</option>
                                    <option value="account-issue">Account Issue</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority" required>
                                    <option value="">Select Priority</option>
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" placeholder="Brief description of the issue" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Order ID (if applicable)</label>
                            <input type="text" class="form-control" name="order_id" placeholder="e.g., ORD-2024-0001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" placeholder="Please provide detailed information about your issue..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Attachment (Optional)</label>
                            <div class="file-upload-wrapper">
                                <input type="file" name="attachment" id="fileInput" class="file-upload-input" accept="image/*,.pdf">
                                <label for="fileInput" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div class="file-upload-text">
                                        <strong>Click to upload or drag and drop</strong>
                                        <small>PNG, JPG, GIF, WEBP or PDF (Max 5MB)</small>
                                    </div>
                                </label>
                            </div>
                            <div class="file-preview" id="filePreview">
                                <div class="file-preview-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="file-preview-info">
                                    <div class="file-preview-name" id="fileName"></div>
                                    <div class="file-preview-size" id="fileSize"></div>
                                </div>
                                <button type="button" class="file-preview-remove" onclick="removeFile()">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Ticket
                        </button>
                    </form>
                </div>

                <div>
                    <!-- FAQ -->
                    <div class="faq-card">
                        <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                        <div class="faq-item">
                            <div class="faq-question">How do I update delivery status?</div>
                            <p class="faq-answer">Use the status buttons on each delivery card to update from Pending → On the Way → Delivered.</p>
                        </div>
                        <div class="faq-item">
                            <div class="faq-question">What if customer is not available?</div>
                            <p class="faq-answer">Try calling the customer first. If no response, contact support for further instructions.</p>
                        </div>
                        <div class="faq-item">
                            <div class="faq-question">How to report damaged items?</div>
                            <p class="faq-answer">Take photos of damaged items and submit a support ticket with "delivery-issue" category.</p>
                        </div>
                        <div class="faq-item">
                            <div class="faq-question">Vehicle breakdown assistance?</div>
                            <p class="faq-answer">Call emergency support immediately at <a href="tel:+94755638086">+94 75 563 8086</a> for roadside assistance.</p>
                        </div>
                    </div>

                    <!-- Support Hours -->
                    <div class="hours-card">
                        <h3><i class="fas fa-info-circle"></i> Support Hours</h3>
                        <div class="hours-item">
                            <span class="hours-label">Phone Support:</span>
                            <span class="hours-value">24/7 Available</span>
                        </div>
                        <div class="hours-item">
                            <span class="hours-label">Email Support:</span>
                            <span class="hours-value">Response within 2 hours</span>
                        </div>
                        <div class="hours-item">
                            <span class="hours-label">Messages:</span>
                            <span class="hours-value">Check your inbox regularly</span>
                        </div>
                    </div>
                </div>
            </div><br>

             <!-- My Recent Tickets -->
            <?php if (!empty($my_tickets)): ?>
            <div class="tickets-card">
                <h3><i class="fas fa-ticket-alt"></i> My Recent Tickets</h3>
                <?php foreach ($my_tickets as $ticket): ?>
                    <div class="ticket-item">
                        <div class="ticket-header">
                            <span class="ticket-id">#TK<?php echo str_pad($ticket['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                        </div>
                        <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                        <div class="ticket-meta">
                            <span><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?></span>
                            <span class="ticket-status status-<?php echo $ticket['priority']; ?>">
                                <i class="fas fa-flag"></i> <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                            <?php if ($ticket['attachment']): ?>
                                <span class="ticket-attachment">
                                    <i class="fas fa-paperclip"></i> Has Attachment
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
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

        // File upload handling
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    fileInput.value = '';
                    return;
                }

                // Display file preview
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('active');
            }
        });

        function removeFile() {
            fileInput.value = '';
            filePreview.classList.remove('active');
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from { transform: translateY(0); opacity: 1; }
                to { transform: translateY(-20px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Drag and drop file upload
        const uploadLabel = document.querySelector('.file-upload-label');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadLabel.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadLabel.addEventListener(eventName, () => {
                uploadLabel.style.borderColor = 'rgb(124, 136, 224)';
                uploadLabel.style.background = 'rgba(124, 136, 224, 0.05)';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadLabel.addEventListener(eventName, () => {
                uploadLabel.style.borderColor = '#e5e7eb';
                uploadLabel.style.background = '#f6f7fb';
            });
        });

        uploadLabel.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            fileInput.files = dt.files;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        });
    </script>
</body>
</html>