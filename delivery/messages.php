<?php
require_once '../config.php';

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}

$delivery_person_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'mark_as_read':
                $messageId = filter_var($_POST['message_id'], FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("UPDATE admin_messages SET is_read = 1, read_at = NOW() WHERE id = ? AND recipient_id = ?");
                $stmt->execute([$messageId, $delivery_person_id]);
                echo json_encode(['success' => true, 'message' => 'Message marked as read']);
                exit;

            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE admin_messages SET is_read = 1, read_at = NOW() WHERE recipient_id = ? AND is_read = 0");
                $stmt->execute([$delivery_person_id]);
                echo json_encode(['success' => true, 'message' => 'All messages marked as read']);
                exit;

            case 'delete_message':
                $messageId = filter_var($_POST['message_id'], FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("DELETE FROM admin_messages WHERE id = ? AND recipient_id = ?");
                $stmt->execute([$messageId, $delivery_person_id]);
                echo json_encode(['success' => true, 'message' => 'Message deleted']);
                exit;

            case 'send_message':
                $subject = filter_var($_POST['subject'], FILTER_SANITIZE_STRING);
                $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
                $priority = filter_var($_POST['priority'], FILTER_SANITIZE_STRING);

                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch();

                if ($admin) {
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_messages (sender_id, recipient_id, sender_type, recipient_type, subject, message, priority, message_type)
                        VALUES (?, ?, 'user', 'admin', ?, ?, ?, 'support')
                    ");
                    $stmt->execute([$delivery_person_id, $admin['id'], $subject, $message, $priority]);
                    echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No admin found']);
                }
                exit;

            case 'reply_message':
                $parentMessageId = filter_var($_POST['parent_message_id'], FILTER_SANITIZE_NUMBER_INT);
                $replyMessage = filter_var($_POST['reply_message'], FILTER_SANITIZE_STRING);

                $stmt = $pdo->prepare("SELECT * FROM admin_messages WHERE id = ?");
                $stmt->execute([$parentMessageId]);
                $original_msg = $stmt->fetch();

                if ($original_msg) {
                    $recipient_id = ($original_msg['sender_id'] == $delivery_person_id) 
                        ? $original_msg['recipient_id'] 
                        : $original_msg['sender_id'];
                    
                    $recipient_type = ($original_msg['sender_id'] == $delivery_person_id) 
                        ? $original_msg['recipient_type'] 
                        : 'admin';

                    $stmt = $pdo->prepare("
                        INSERT INTO admin_messages (sender_id, recipient_id, sender_type, recipient_type, subject, message, priority, message_type, parent_message_id)
                        VALUES (?, ?, 'user', ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $delivery_person_id, 
                        $recipient_id, 
                        $recipient_type,
                        'Re: ' . $original_msg['subject'], 
                        $replyMessage, 
                        $original_msg['priority'],
                        $original_msg['message_type'],
                        $parentMessageId
                    ]);

                    echo json_encode(['success' => true, 'message' => 'Reply sent successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Original message not found']);
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

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

    // Get filter
    $filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Build query
    $whereClause = "WHERE (am.recipient_id = ? OR am.sender_id = ?) AND am.parent_message_id IS NULL";
    $params = [$delivery_person_id, $delivery_person_id];

    if ($filter === 'unread') {
        $whereClause .= " AND am.is_read = 0 AND am.recipient_id = ?";
        $params[] = $delivery_person_id;
    } elseif ($filter !== 'all') {
        $whereClause .= " AND am.message_type = ?";
        $params[] = $filter;
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM admin_messages am $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalMessages = $stmt->fetch()['total'];
    $totalPages = ceil($totalMessages / $perPage);

    // Fetch all messages with sender info
    $query = "
        SELECT 
            am.*,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            sender.role as sender_role
        FROM admin_messages am
        LEFT JOIN users sender ON am.sender_id = sender.id
        $whereClause
        ORDER BY am.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Fetch replies and ticket info for each message
    $messages_with_data = [];
    foreach ($messages as $msg) {
        // Get replies
        $stmt = $pdo->prepare("
            SELECT 
                am.*,
                sender.first_name as sender_first_name,
                sender.last_name as sender_last_name,
                sender.role as sender_role
            FROM admin_messages am
            LEFT JOIN users sender ON am.sender_id = sender.id
            WHERE am.parent_message_id = ?
            ORDER BY am.created_at ASC
        ");
        $stmt->execute([$msg['id']]);
        $msg['replies'] = $stmt->fetchAll();
        
        // Get related ticket info if it's a support ticket
        if ($msg['message_type'] === 'support') {
            if (preg_match('/\[Support Ticket: (TK\d+)\]/', $msg['subject'], $matches)) {
                $ticket_id = intval(str_replace('TK', '', $matches[1]));
                $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
                $stmt->execute([$ticket_id]);
                $msg['ticket_info'] = $stmt->fetch();
            }
        }
        
        $messages_with_data[] = $msg;
    }
    $messages = $messages_with_data;

    // Get statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ?");
    $stmt->execute([$delivery_person_id]);
    $stats['total'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$delivery_person_id]);
    $stats['unread'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND priority = 'urgent'");
    $stmt->execute([$delivery_person_id]);
    $stats['urgent'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE sender_id = ? AND status = 'pending'");
    $stmt->execute([$delivery_person_id]);
    $stats['pending_tickets'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Messages Page Error: " . $e->getMessage());
    die("An error occurred while loading messages.");
}

// Helper functions
function isImage($filename) {
    if (!$filename) return false;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    if ($difference < 60) return $difference . ' seconds ago';
    if ($difference < 3600) return floor($difference / 60) . ' minutes ago';
    if ($difference < 86400) return floor($difference / 3600) . ' hours ago';
    if ($difference < 604800) return floor($difference / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}

function getMessageIcon($type, $priority) {
    if ($priority === 'urgent') return ['class' => 'danger', 'icon' => 'fa-exclamation-triangle'];
    switch ($type) {
        case 'order_issue': return ['class' => 'warning', 'icon' => 'fa-shopping-cart'];
        case 'support': return ['class' => 'info', 'icon' => 'fa-life-ring'];
        default: return ['class' => 'success', 'icon' => 'fa-bell'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
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
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
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

        .filter-section {
          margin: 20px 0;
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
        }

        .filter-btn {
          padding: 8px 16px;
          border-radius: 20px;
          border: 2px solid #e2e8f0;
          background: white;
          color: #484d53;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          font-size: 0.9rem;
        }

        .filter-btn:hover, .filter-btn.active {
          background: rgb(73, 57, 113);
          color: white;
          border-color: rgb(73, 57, 113);
          transform: translateY(-2px);
        }

        .messages-list {
          margin-top: 20px;
        }

        .message-item {
          background: white;
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          border-left: 4px solid #e2e8f0;
          transition: all 0.3s ease;
        }

        .message-item:hover {
          transform: translateY(-2px);
          box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        }

        .message-item.unread {
          background: #fef2f2;
          border-left-color: #ef4444;
        }

        .message-item.info { border-left-color: #3b82f6; }
        .message-item.success { border-left-color: #10b981; }
        .message-item.warning { border-left-color: #f59e0b; }
        .message-item.danger { border-left-color: #ef4444; }

        .message-header {
          display: flex;
          align-items: center;
          gap: 15px;
          margin-bottom: 15px;
        }

        .message-icon {
          width: 50px;
          height: 50px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 1.5rem;
          flex-shrink: 0;
        }

        .message-icon.info { background: #dbeafe; color: #3b82f6; }
        .message-icon.success { background: #d1fae5; color: #10b981; }
        .message-icon.warning { background: #fef3c7; color: #f59e0b; }
        .message-icon.danger { background: #fee2e2; color: #ef4444; }

        .message-content-header {
          flex: 1;
        }

        .message-title {
          font-size: 1.1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .message-meta {
          font-size: 0.85rem;
          color: #94a3b8;
          display: flex;
          gap: 15px;
          flex-wrap: wrap;
        }

        .message-body {
          background: #f6f7fb;
          padding: 15px;
          border-radius: 10px;
          margin-bottom: 15px;
          line-height: 1.6;
          color: #484d53;
        }

        .priority-urgent, .priority-high {
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
          display: inline-block;
          margin-left: 10px;
        }

        .priority-urgent {
          background: linear-gradient(45deg, #ef4444, #dc2626);
          color: white;
        }

        .priority-high {
          background: linear-gradient(45deg, #f59e0b, #d97706);
          color: white;
        }

        /* Ticket Info Section */
        .ticket-info-section {
          background: linear-gradient(135deg, rgba(124, 136, 224, 0.05), rgba(195, 244, 252, 0.05));
          border-left: 4px solid rgb(124, 136, 224);
          padding: 20px;
          border-radius: 12px;
          margin-bottom: 15px;
        }

        .ticket-info-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 15px;
        }

        .ticket-info-header h4 {
          font-size: 1.1rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
          margin: 0;
        }

        .ticket-status-badge {
          padding: 6px 12px;
          border-radius: 12px;
          font-size: 0.85rem;
          font-weight: 600;
        }

        .status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-in_progress { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .status-resolved { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-closed { background: rgba(107, 114, 128, 0.2); color: #6b7280; }

        .ticket-info-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 12px;
          margin-bottom: 15px;
        }

        .ticket-info-item {
          display: flex;
          flex-direction: column;
        }

        .ticket-info-label {
          font-size: 0.85rem;
          color: #9ca3af;
          margin-bottom: 4px;
        }

        .ticket-info-value {
          font-weight: 600;
          color: #484d53;
          font-size: 0.95rem;
        }

        /* Attachment Display */
        .attachment-section {
          background: white;
          border: 2px solid #e5e7eb;
          border-radius: 12px;
          padding: 15px;
          margin-top: 15px;
          /* margin-right: 25rem; */
        }

        .attachment-header {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 12px;
          color: rgb(73, 57, 113);
          font-weight: 700;
        }

        .attachment-preview {
          border-radius: 8px;
          overflow: hidden;
          max-width: 100%;
        }

        .attachment-preview img {
          width: 10%;
          height: auto;
          display: block;
          border-radius: 8px;
          cursor: pointer;
          transition: transform 0.3s ease;
        }

        .attachment-preview img:hover {
          transform: scale(1.02);
          width: 30%;
          height: auto;
          display: block;
          border-radius: 8px;
          cursor: pointer;
          transition: transform 0.3s ease;
        }

        #imageModal img,
.modal img {
    max-width: 100%;        /* image won't take full width */
    max-height: 100vh;      /* image won't take full height */
    object-fit: contain;
    border-radius: 8px;
}

        .attachment-file {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 12px;
          background: #f6f7fb;
          border-radius: 8px;
        }

        .attachment-file-icon {
          width: 48px;
          height: 48px;
          background: rgb(124, 136, 224);
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-size: 1.5rem;
        }

        .attachment-file-info {
          flex: 1;
        }

        .attachment-file-name {
          font-weight: 600;
          color: #484d53;
          margin-bottom: 4px;
        }

        .attachment-file-meta {
          font-size: 0.85rem;
          color: #9ca3af;
        }

        .attachment-download {
          padding: 8px 16px;
          background: rgb(124, 136, 224);
          color: white;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          font-weight: 600;
          transition: all 0.3s ease;
        }

        .attachment-download:hover {
          background: rgb(93, 77, 133);
          transform: translateY(-2px);
        }

        /* Image Modal */
        .image-modal {
          display: none;
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.9);
          z-index: 10000;
          align-items: center;
          justify-content: center;
        }

        .image-modal.active {
          display: flex;
        }

        .image-modal-content {
          max-width: 90%;
          max-height: 90%;
          position: relative;
        }

        .image-modal-content img {
          width: 100%;
          height: auto;
          border-radius: 8px;
        }

        .image-modal-close {
          position: absolute;
          top: -40px;
          right: 0;
          background: white;
          border: none;
          width: 40px;
          height: 40px;
          border-radius: 50%;
          cursor: pointer;
          font-size: 1.2rem;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.3s ease;
        }

        .image-modal-close:hover {
          background: #ef4444;
          color: white;
        }

        .replies-section {
          border-top: 2px solid #e5e7eb;
          padding-top: 15px;
          margin-top: 15px;
        }

        .replies-section h4 {
          font-size: 1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
        }

        .reply-item {
          background: linear-gradient(to right, rgba(124, 136, 224, 0.05), transparent);
          border-left: 3px solid rgb(124, 136, 224);
          padding: 12px;
          border-radius: 0 12px 12px 0;
          margin-bottom: 10px;
        }

        .reply-header {
          display: flex;
          justify-content: space-between;
          margin-bottom: 8px;
        }

        .reply-sender {
          font-weight: 700;
          color: rgb(73, 57, 113);
          font-size: 0.9rem;
        }

        .reply-time {
          font-size: 0.8rem;
          color: #9ca3af;
        }

        .reply-content {
          color: #484d53;
          line-height: 1.6;
          font-size: 0.9rem;
        }

        .message-actions {
          display: flex;
          gap: 8px;
          flex-wrap: wrap;
        }

        .btn {
          padding: 8px 16px;
          font-size: 0.85rem;
          font-weight: 600;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 6px;
        }

        .btn:hover {
          transform: translateY(-2px);
        }

        .btn-primary {
          background: linear-gradient(135deg, rgb(73, 57, 113), rgb(93, 77, 133));
          color: white;
        }

        .btn-outline {
          background: white;
          color: rgb(73, 57, 113);
          border: 2px solid rgb(73, 57, 113);
        }

        .btn-outline:hover {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-danger {
          background: #ef4444;
          color: white;
        }

        .btn-danger:hover {
          background: #dc2626;
        }

        .reply-form {
          margin-top: 15px;
          padding: 15px;
          background: #f8fafc;
          border-radius: 10px;
          display: none;
        }

        .reply-form.active {
          display: block;
        }

        .reply-form textarea {
          width: 100%;
          padding: 12px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          font-family: inherit;
          font-size: 0.9rem;
          resize: vertical;
          margin-bottom: 10px;
          min-height: 100px;
        }

        .reply-form textarea:focus {
          outline: none;
          border-color: rgb(124, 136, 224);
        }

        .compose-btn {
          position: fixed;
          bottom: 40px;
          right: 40px;
          width: 60px;
          height: 60px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          border: none;
          box-shadow: 0 8px 20px rgba(124, 136, 224, 0.4);
          cursor: pointer;
          transition: all 0.3s ease;
          z-index: 1000;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .compose-btn:hover {
          transform: scale(1.1);
        }

        .compose-btn i {
          font-size: 24px;
          color: white;
        }

        .modal {
          display: none;
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          z-index: 9999;
          align-items: center;
          justify-content: center;
        }

        .modal.active {
          display: flex;
        }

        .modal-dialog {
          background: white;
          border-radius: 15px;
          max-width: 600px;
          width: 90%;
          max-height: 90vh;
          overflow-y: auto;
        }

        .modal-header {
          background: linear-gradient(135deg, rgb(73, 57, 113), rgb(93, 77, 133));
          color: white;
          padding: 20px;
          border-radius: 15px 15px 0 0;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .modal-header h3 {
          font-size: 1.2rem;
          font-weight: 700;
          margin: 0;
        }

        .modal-close {
          width: 32px;
          height: 32px;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.2);
          border: none;
          color: white;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .modal-body {
          padding: 25px;
        }

        .form-group {
          margin-bottom: 15px;
        }

        .form-label {
          display: block;
          margin-bottom: 5px;
          font-weight: 600;
          color: #484d53;
        }

        .form-control {
          width: 100%;
          padding: 10px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-family: inherit;
          font-size: 0.9rem;
        }

        .form-control:focus {
          outline: none;
          border-color: rgb(124, 136, 224);
        }

        .form-control textarea {
          resize: vertical;
          min-height: 100px;
        }

        .modal-footer {
          padding: 15px 25px;
          border-top: 2px solid #f6f7fb;
          display: flex;
          gap: 10px;
          justify-content: flex-end;
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

        .pagination {
          display: flex;
          justify-content: center;
          gap: 10px;
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

        .pagination a:hover, .pagination a.active {
          background: rgb(73, 57, 113);
          color: white;
          transform: translateY(-2px);
        }

        .alert {
          padding: 15px;
          margin-bottom: 15px;
          border-radius: 10px;
          font-weight: 600;
          display: none;
        }

        .alert.show {
          display: block;
          animation: slideInDown 0.3s ease;
        }

        .alert-success {
          background: rgba(16, 185, 129, 0.1);
          border-left: 4px solid #10b981;
          color: #10b981;
        }

        .alert-error {
          background: rgba(239, 68, 68, 0.1);
          border-left: 4px solid #ef4444;
          color: #ef4444;
        }

        @keyframes slideInDown {
          from { transform: translateY(-20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .stats-grid { grid-template-columns: repeat(2, 1fr); }
          .ticket-info-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .stats-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { margin: 15px; padding: 15px; }
          .message-header { flex-direction: column; align-items: flex-start; }
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

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="messages.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text">Messages</span>
                        <?php if ($stats['unread'] > 0): ?>
                        <span class="notification-badge"><?php echo $stats['unread']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item">
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
            <div id="alertContainer"></div>

            <div class="stats-section">
                <h1>Message Center</h1>
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h3><?php echo number_format($stats['total']); ?></h3>
                            <p>Total Messages</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-envelope-open"></i>
                        <div>
                            <h3><?php echo number_format($stats['unread']); ?></h3>
                            <p>Unread Messages</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h3><?php echo number_format($stats['urgent']); ?></h3>
                            <p>Urgent Alerts</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-ticket-alt"></i>
                        <div>
                            <h3><?php echo number_format($stats['pending_tickets']); ?></h3>
                            <p>Pending Tickets</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All
                </a>
                <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Unread
                </a>
                <a href="?filter=support" class="filter-btn <?php echo $filter === 'support' ? 'active' : ''; ?>">
                    <i class="fas fa-life-ring"></i> Support
                </a>
                <a href="?filter=order_issue" class="filter-btn <?php echo $filter === 'order_issue' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
                <a href="?filter=general" class="filter-btn <?php echo $filter === 'general' ? 'active' : ''; ?>">
                    <i class="fas fa-info-circle"></i> General
                </a>
            </div>

            <div class="messages-list">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No messages found</h3>
                        <p>You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php
                        $iconData = getMessageIcon($msg['message_type'], $msg['priority']);
                        $isUnread = !$msg['is_read'] && $msg['recipient_id'] == $delivery_person_id;
                        ?>
                        <div class="message-item <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $iconData['class']; ?>" data-id="<?php echo $msg['id']; ?>">
                            <div class="message-header">
                                <div class="message-icon <?php echo $iconData['class']; ?>">
                                    <i class="fas <?php echo $iconData['icon']; ?>"></i>
                                </div>
                                <div class="message-content-header">
                                    <div class="message-title">
                                        <?php echo htmlspecialchars($msg['subject']); ?>
                                        <?php if ($msg['priority'] === 'urgent'): ?>
                                            <span class="priority-urgent">URGENT</span>
                                        <?php elseif ($msg['priority'] === 'high'): ?>
                                            <span class="priority-high">HIGH</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-meta">
                                        <span>
                                            <?php 
                                                if ($msg['sender_id'] == $delivery_person_id) {
                                                    echo '<i class="fas fa-paper-plane"></i> To: Admin';
                                                } else {
                                                    echo '<i class="fas fa-user"></i> From: ' . ($msg['sender_type'] === 'system' ? 'System' : 
                                                         htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']));
                                                }
                                            ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i> <?php echo timeAgo($msg['created_at']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($msg['ticket_info']) && $msg['ticket_info']): ?>
                                <div class="ticket-info-section">
                                    <div class="ticket-info-header">
                                        <h4><i class="fas fa-ticket-alt"></i> Support Ticket Details</h4>
                                        <span class="ticket-status-badge status-<?php echo $msg['ticket_info']['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $msg['ticket_info']['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="ticket-info-grid">
                                        <div class="ticket-info-item">
                                            <span class="ticket-info-label">Ticket ID</span>
                                            <span class="ticket-info-value">#TK<?php echo str_pad($msg['ticket_info']['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                        <div class="ticket-info-item">
                                            <span class="ticket-info-label">Priority</span>
                                            <span class="ticket-info-value"><?php echo ucfirst($msg['ticket_info']['priority']); ?></span>
                                        </div>
                                        <div class="ticket-info-item">
                                            <span class="ticket-info-label">Created</span>
                                            <span class="ticket-info-value"><?php echo date('M d, Y g:i A', strtotime($msg['ticket_info']['created_at'])); ?></span>
                                        </div>
                                        <div class="ticket-info-item">
                                            <span class="ticket-info-label">Updated</span>
                                            <span class="ticket-info-value"><?php echo date('M d, Y g:i A', strtotime($msg['ticket_info']['updated_at'])); ?></span>
                                        </div>
                                    </div>

                                    <?php if ($msg['ticket_info']['attachment']): ?>
                                        <div class="attachment-section">
                                            <div class="attachment-header">
                                                <i class="fas fa-paperclip"></i>
                                                <span>Attachment</span>
                                            </div>
                                            <?php if (isImage($msg['ticket_info']['attachment'])): ?>
                                                <div class="attachment-preview">
                                                    <img src="../uploads/<?php echo htmlspecialchars($msg['ticket_info']['attachment']); ?>" 
                                                         alt="Ticket Attachment"
                                                         onclick="openImageModal(this.src)">
                                                </div>
                                            <?php else: ?>
                                                <div class="attachment-file">
                                                    <div class="attachment-file-icon">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </div>
                                                    <div class="attachment-file-info">
                                                        <div class="attachment-file-name">
                                                            <?php echo basename($msg['ticket_info']['attachment']); ?>
                                                        </div>
                                                        <div class="attachment-file-meta">PDF Document</div>
                                                    </div>
                                                    <a href="../uploads/<?php echo htmlspecialchars($msg['ticket_info']['attachment']); ?>" 
                                                       target="_blank" 
                                                       class="attachment-download">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="message-body">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>

                            <?php if (!empty($msg['replies'])): ?>
                                <div class="replies-section">
                                    <h4><i class="fas fa-comments"></i> Replies (<?php echo count($msg['replies']); ?>)</h4>
                                    <?php foreach ($msg['replies'] as $reply): ?>
                                        <div class="reply-item">
                                            <div class="reply-header">
                                                <span class="reply-sender">
                                                    <?php 
                                                        if ($reply['sender_id'] == $delivery_person_id) {
                                                            echo 'You';
                                                        } else {
                                                            echo $reply['sender_type'] === 'system' ? 'System' : 
                                                                 htmlspecialchars($reply['sender_first_name'] . ' ' . $reply['sender_last_name']);
                                                        }
                                                    ?>
                                                </span>
                                                <span class="reply-time">
                                                    <?php echo timeAgo($reply['created_at']); ?>
                                                </span>
                                            </div>
                                            <div class="reply-content">
                                                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="message-actions">
                                <?php if ($isUnread): ?>
                                    <button class="btn btn-outline" onclick="markAsRead(<?php echo $msg['id']; ?>)">
                                        <i class="fas fa-check"></i> Mark Read
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-primary" onclick="toggleReplyForm(<?php echo $msg['id']; ?>)">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                                <button class="btn btn-danger" onclick="deleteMessage(<?php echo $msg['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>

                            <div class="reply-form" id="reply-form-<?php echo $msg['id']; ?>">
                                <textarea id="reply-text-<?php echo $msg['id']; ?>" placeholder="Type your reply here..."></textarea>
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn btn-primary" onclick="sendReply(<?php echo $msg['id']; ?>)">
                                        <i class="fas fa-paper-plane"></i> Send Reply
                                    </button>
                                    <button class="btn btn-outline" onclick="toggleReplyForm(<?php echo $msg['id']; ?>)">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" 
                                   class="<?php echo $page == $i ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Compose Button -->
    <button class="compose-btn" onclick="openComposeModal()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Compose Modal -->
    <div class="modal" id="composeModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3><i class="fas fa-paper-plane"></i> New Message to Admin</h3>
                <button class="modal-close" onclick="closeComposeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" class="form-control" id="compose-subject" placeholder="Message subject">
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-control" id="compose-priority">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" id="compose-message" rows="8" placeholder="Type your message here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeComposeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendNewMessage()">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <div class="image-modal-content" onclick="event.stopPropagation()">
            <button class="image-modal-close" onclick="closeImageModal()">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" alt="Full size image">
        </div>
    </div>

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

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        function markAsRead(messageId) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('message_id', messageId);

            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messageItem = document.querySelector(`[data-id="${messageId}"]`);
                    if (messageItem) {
                        messageItem.classList.remove('unread');
                        const markReadBtn = messageItem.querySelector('button[onclick*="markAsRead"]');
                        if (markReadBtn) {
                            markReadBtn.remove();
                        }
                    }
                    updateUnreadCount();
                    showAlert(data.message);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred', 'error');
            });
        }

        function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_message');
            formData.append('message_id', messageId);

            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messageItem = document.querySelector(`[data-id="${messageId}"]`);
                    if (messageItem) {
                        messageItem.style.transition = 'opacity 0.3s ease';
                        messageItem.style.opacity = '0';
                        setTimeout(() => {
                            messageItem.remove();
                            const remainingMessages = document.querySelectorAll('.message-item');
                            if (remainingMessages.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                    showAlert(data.message);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred', 'error');
            });
        }

        function toggleReplyForm(messageId) {
            const form = document.getElementById('reply-form-' + messageId);
            if (form.classList.contains('active')) {
                form.classList.remove('active');
            } else {
                // Close all other reply forms
                document.querySelectorAll('.reply-form').forEach(f => f.classList.remove('active'));
                form.classList.add('active');
            }
        }

        function sendReply(messageId) {
            const replyText = document.getElementById('reply-text-' + messageId).value.trim();

            if (!replyText) {
                showAlert('Please enter a reply message', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reply_message');
            formData.append('parent_message_id', messageId);
            formData.append('reply_message', replyText);

            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    location.reload();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred', 'error');
            });
        }

        function openComposeModal() {
            document.getElementById('composeModal').classList.add('active');
        }

        function closeComposeModal() {
            document.getElementById('composeModal').classList.remove('active');
            document.getElementById('compose-subject').value = '';
            document.getElementById('compose-priority').value = 'normal';
            document.getElementById('compose-message').value = '';
        }

        function sendNewMessage() {
            const subject = document.getElementById('compose-subject').value.trim();
            const priority = document.getElementById('compose-priority').value;
            const message = document.getElementById('compose-message').value.trim();

            if (!subject || !message) {
                showAlert('Please fill in all fields', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('subject', subject);
            formData.append('priority', priority);
            formData.append('message', message);

            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeComposeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred', 'error');
            });
        }

        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        function updateUnreadCount() {
            const unreadItems = document.querySelectorAll('.message-item.unread').length;
            const badges = document.querySelectorAll('.notification-badge');

            badges.forEach(badge => {
                if (unreadItems > 0) {
                    badge.textContent = unreadItems;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        // Close modals on outside click
        document.getElementById('composeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeComposeModal();
            }
        });

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeComposeModal();
            }
        });

        // Click message to mark as read
        document.querySelectorAll('.message-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!e.target.closest('button') && !e.target.closest('textarea') && !e.target.closest('img')) {
                    if (this.classList.contains('unread')) {
                        const messageId = this.getAttribute('data-id');
                        markAsRead(messageId);
                    }
                }
            });
        });

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.get('page') || urlParams.get('page') === '1') {
                updateUnreadCount();
            }
        }, 30000);
    </script>
</body>
</html>