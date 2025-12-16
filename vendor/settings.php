<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get vendor information with all security settings
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               up.language, up.currency, up.date_format, up.time_format, up.timezone,
               ss.two_factor_enabled, ss.two_factor_secret, ss.login_alerts, ss.session_timeout, ss.last_password_change,
               np.email_notifications, np.sms_notifications, np.push_notifications, 
               np.order_updates, np.marketing_emails, np.security_alerts, np.weekly_reports
        FROM users u
        LEFT JOIN user_preferences up ON u.id = up.user_id
        LEFT JOIN security_settings ss ON u.id = ss.user_id
        LEFT JOIN notification_preferences np ON u.id = np.user_id
        WHERE u.id = ? AND u.role = 'vendor'
    ");
    $stmt->execute([$user_id]);
    $vendor = $stmt->fetch();
    if (!$vendor) {
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die("Database error");
}

// Calculate Security Score
function calculateSecurityScore($userData) {
    $score = 0;
    $maxScore = 4;
    
    if (!empty($userData['email_verified_at'])) $score++;
    if ($userData['two_factor_enabled']) $score++;
    if ($userData['last_password_change']) $score++;
    if ($userData['login_alerts']) $score++;
    
    $percentage = ($score / $maxScore) * 100;
    
    return [
        'score' => $score,
        'percentage' => round($percentage),
        'max_score' => $maxScore
    ];
}

$securityScore = calculateSecurityScore($vendor);

// Get preferences
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch();
    if (!$preferences) {
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $preferences = ['language' => 'en_US', 'currency' => 'USD', 'date_format' => 'MM/DD/YYYY', 'time_format' => '12h', 'timezone' => 'UTC'];
    }
} catch (PDOException $e) {
    $preferences = [];
}

// Get notification preferences
try {
    $stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $notification_prefs = $stmt->fetch();
    if (!$notification_prefs) {
        $stmt = $pdo->prepare("INSERT INTO notification_preferences (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $notification_prefs = ['email_notifications' => 1, 'sms_notifications' => 0, 'push_notifications' => 1, 'order_updates' => 1, 'marketing_emails' => 1, 'security_alerts' => 1, 'weekly_reports' => 1];
    }
} catch (PDOException $e) {
    $notification_prefs = [];
}

// Get security settings
try {
    $stmt = $pdo->prepare("SELECT * FROM security_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $security_settings = $stmt->fetch();
    if (!$security_settings) {
        $stmt = $pdo->prepare("INSERT INTO security_settings (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $security_settings = ['two_factor_enabled' => 0, 'login_alerts' => 1, 'session_timeout' => 1800];
    }
} catch (PDOException $e) {
    $security_settings = [];
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email'], 'email');
        $phone = sanitizeInput($_POST['phone']);
        $bio = sanitizeInput($_POST['bio']);
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
                    $profile_picture = 'uploads/profiles/' . $filename;
                    if (!empty($vendor['profile_picture']) && file_exists('../' . $vendor['profile_picture'])) {
                        unlink('../' . $vendor['profile_picture']);
                    }
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$profile_picture, $user_id]);
                }
            }
        }
        
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $email, $phone, $bio, $user_id]);
        logUserActivity($user_id, 'profile_update', 'Updated profile information', $_SERVER['REMOTE_ADDR']);
        $success_message = "Profile updated successfully!";
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $vendor = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Failed to update profile.";
    }
}

// Handle Preferences Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_preferences') {
    try {
        $language = sanitizeInput($_POST['language']);
        $currency = sanitizeInput($_POST['currency']);
        $date_format = sanitizeInput($_POST['date_format']);
        $time_format = sanitizeInput($_POST['time_format']);
        $timezone = sanitizeInput($_POST['timezone']);
        
        $stmt = $pdo->prepare("UPDATE user_preferences SET language = ?, currency = ?, date_format = ?, time_format = ?, timezone = ? WHERE user_id = ?");
        $stmt->execute([$language, $currency, $date_format, $time_format, $timezone, $user_id]);
        
        $stmt = $pdo->prepare("UPDATE users SET language = ?, currency_preference = ?, date_format = ?, time_format = ? WHERE id = ?");
        $stmt->execute([$language, $currency, $date_format, $time_format, $user_id]);
        
        logUserActivity($user_id, 'preferences_update', 'Updated account preferences', $_SERVER['REMOTE_ADDR']);
        $success_message = "Account preferences updated successfully!";
        
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Failed to update preferences.";
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $vendor['password'])) {
            $error_message = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $stmt = $pdo->prepare("UPDATE security_settings SET last_password_change = NOW() WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logUserActivity($user_id, 'password_change', 'Password changed successfully', $_SERVER['REMOTE_ADDR']);
            $success_message = "Password changed successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Failed to change password.";
    }
}

// Handle Security Settings Update (WITHOUT 2FA - handled by AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_security') {
    try {
        $loginAlerts = isset($_POST['login_alerts']) ? 1 : 0;
        $sessionTimeout = (int)($_POST['session_timeout'] ?? 30) * 60;

        $updateSecurity = $pdo->prepare("UPDATE security_settings SET login_alerts = ?, session_timeout = ? WHERE user_id = ?");
        $updateSecurity->execute([$loginAlerts, $sessionTimeout, $user_id]);

        logUserActivity($user_id, 'security_update', 'Updated security settings', $_SERVER['REMOTE_ADDR']);
        $success_message = 'Security settings updated successfully!';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Notification Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $order_updates = isset($_POST['order_updates']) ? 1 : 0;
        $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
        $security_alerts = isset($_POST['security_alerts']) ? 1 : 0;
        $weekly_reports = isset($_POST['weekly_reports']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE notification_preferences SET email_notifications = ?, sms_notifications = ?, push_notifications = ?, order_updates = ?, marketing_emails = ?, security_alerts = ?, weekly_reports = ? WHERE user_id = ?");
        $stmt->execute([$email_notifications, $sms_notifications, $push_notifications, $order_updates, $marketing_emails, $security_alerts, $weekly_reports, $user_id]);
        
        logUserActivity($user_id, 'notification_update', 'Updated notification preferences', $_SERVER['REMOTE_ADDR']);
        $success_message = "Notification preferences updated successfully!";
        
        $stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $notification_prefs = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Failed to update notification preferences.";
    }
}

// Get security activity
try {
    $stmt = $pdo->prepare("SELECT * FROM user_activity_log WHERE user_id = ? AND activity_type IN ('login', 'logout', 'password_change', 'failed_login', '2fa_enabled', '2fa_disabled') ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $security_activities = $stmt->fetchAll();
} catch (PDOException $e) {
    $security_activities = [];
}

// Get stats
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE brand = ?");
    $stmt->execute([$vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name']]);
    $totalProducts = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT o.id) as total FROM orders o INNER JOIN order_items oi ON o.id = oi.order_id INNER JOIN products p ON oi.product_id = p.id WHERE p.brand = ? AND o.status IN ('pending', 'processing')");
    $stmt->execute([$vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name']]);
    $pendingOrders = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE brand = ? AND stock_quantity <= min_stock_level");
    $stmt->execute([$vendor['company_name'] ?? $vendor['first_name'] . ' ' . $vendor['last_name']]);
    $lowStockItems = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalProducts = 0;
    $pendingOrders = 0;
    $lowStockItems = 0;
    $unread_count = 0;
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    if ($difference < 60) return 'Just now';
    elseif ($difference < 3600) return floor($difference / 60) . ' min ago';
    elseif ($difference < 86400) return floor($difference / 3600) . ' hours ago';
    elseif ($difference < 604800) return floor($difference / 86400) . ' days ago';
    else return date('M d, Y', $timestamp);
}


$securityScore = calculateSecurityScore($vendor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *, *::before, *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
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
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset, 0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
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

        nav {
          user-select: none;
        }

        nav ul, nav ul li {
          outline: 0;
        }

        nav ul li a {
          text-decoration: none;
        }

        .nav-item {
          position: relative;
          display: block;
        }

        .nav-item a {
          position: relative;
          display: flex;
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
        }

        .settings-header h1 {
          font-size: 1.8rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 5px;
        }

        .settings-header p {
          color: #64748b;
          margin-bottom: 20px;
        }

        .settings-tabs {
          display: grid;
          grid-template-columns: repeat(5, 1fr);
          gap: 10px;
          margin-bottom: 30px;
        }

        .tab-btn {
          padding: 12px 20px;
          background: white;
          border: none;
          border-radius: 12px;
          font-size: 0.95rem;
          font-weight: 600;
          color: #64748b;
          cursor: pointer;
          transition: all 0.3s ease;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 4px;
        }

        .tab-btn:hover {
          background: rgba(124, 136, 224, 0.1);
          color: rgb(73, 57, 113);
          transform: translateY(-2px);
        }

        .tab-btn.active {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          color: #484d53;
          font-weight: 700;
        }

        .tab-btn i {
          margin-right: 8px;
        }

        .tab-content {
          display: none;
          animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
          display: block;
        }

        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: translateY(10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .settings-card {
          background: white;
          padding: 25px;
          border-radius: 15px;
          box-shadow: rgba(0, 0, 0, 0.08) 0px 2px 8px;
          margin-bottom: 20px;
        }

        .settings-card h2 {
          font-size: 1.3rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 20px;
          border-bottom: 2px solid #f6f7fb;
          padding-bottom: 10px;
        }

        .form-group {
          margin-bottom: 20px;
        }

        .form-group label {
          display: block;
          font-weight: 600;
          color: #484d53;
          margin-bottom: 8px;
          font-size: 0.95rem;
        }

        .form-control, select.form-control, textarea.form-control {
          width: 100%;
          padding: 12px 15px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-size: 1rem;
          font-family: "Nunito", sans-serif;
          transition: all 0.3s ease;
        }

        .form-control:focus, select.form-control:focus, textarea.form-control:focus {
          outline: none;
          border-color: rgb(124, 136, 224);
          box-shadow: 0 0 0 3px rgba(124, 136, 224, 0.1);
        }

        .form-row {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 20px;
        }

        .btn-primary {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          color: #484d53;
          padding: 12px 30px;
          border: none;
          border-radius: 10px;
          font-size: 1rem;
          font-weight: 700;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .btn-primary:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 15px rgba(124, 136, 224, 0.3);
        }

        .profile-upload {
          display: flex;
          align-items: center;
          gap: 20px;
          margin-bottom: 25px;
          padding: 20px;
          background: #f6f7fb;
          border-radius: 12px;
        }

        .profile-avatar {
          width: 100px;
          height: 100px;
          border-radius: 50%;
          object-fit: cover;
          border: 4px solid white;
          box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .upload-btn {
          padding: 10px 20px;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .upload-btn:hover {
          border-color: rgb(124, 136, 224);
          background: rgba(124, 136, 224, 0.1);
        }

        .switch {
          position: relative;
          display: inline-block;
          width: 50px;
          height: 24px;
        }

        .switch input {
          opacity: 0;
          width: 0;
          height: 0;
        }

        .slider {
          position: absolute;
          cursor: pointer;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-color: #ccc;
          transition: .4s;
          border-radius: 24px;
        }

        .slider:before {
          position: absolute;
          content: "";
          height: 18px;
          width: 18px;
          left: 3px;
          bottom: 3px;
          background-color: white;
          transition: .4s;
          border-radius: 50%;
        }

        input:checked + .slider {
          background-color: rgb(124, 136, 224);
        }

        input:checked + .slider:before {
          transform: translateX(26px);
        }

        .setting-item {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 15px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          margin-bottom: 12px;
          transition: all 0.3s ease;
        }

        .setting-item:hover {
          border-color: rgb(124, 136, 224);
          background: #f6f7fb;
        }

        .setting-info h4 {
          font-size: 1rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 3px;
        }

        .setting-info p {
          font-size: 0.85rem;
          color: #64748b;
          margin: 0;
        }

        .alert {
          padding: 15px 20px;
          border-radius: 10px;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
          gap: 12px;
          font-weight: 600;
        }

        .alert-success {
          background: rgba(16, 185, 129, 0.1);
          color: #10b981;
          border: 2px solid #10b981;
        }

        .alert-danger {
          background: rgba(239, 68, 68, 0.1);
          color: #ef4444;
          border: 2px solid #ef4444;
        }

        .security-badge {
          display: inline-flex;
          align-items: center;
          padding: 8px 16px;
          border-radius: 20px;
          font-size: 0.9rem;
          font-weight: 700;
        }

        .security-badge.high {
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .security-badge.medium {
          background: rgba(245, 158, 11, 0.2);
          color: #f59e0b;
        }

        .activity-item {
          padding: 15px;
          border-left: 4px solid #e2e8f0;
          margin-bottom: 12px;
          background: #f8fafc;
          border-radius: 0 10px 10px 0;
          transition: all 0.3s ease;
        }

        .activity-item:hover {
          transform: translateX(5px);
          border-left-color: rgb(124, 136, 224);
        }

        .activity-item.success {
          border-left-color: #10b981;
        }

        .activity-item.warning {
          border-left-color: #f59e0b;
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

        .permission-item {
          padding: 15px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          margin-bottom: 12px;
          transition: all 0.3s ease;
        }

        .permission-item:hover {
          background-color: #f8fafc;
          border-color: rgb(124, 136, 224);
        }

        .badge {
          display: inline-block;
          padding: 6px 12px;
          border-radius: 12px;
          font-size: 0.85rem;
          font-weight: 700;
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

        .modal {
          display: none;
          position: fixed;
          z-index: 9999;
          left: 0;
          top: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(5px);
        }

        .modal.show {
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .modal-content {
          background: white;
          border-radius: 15px;
          padding: 30px;
          max-width: 500px;
          width: 90%;
          max-height: 90vh;
          overflow-y: auto;
          box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
        }

        .modal-header h3 {
          margin: 0;
          color: #484d53;
        }

        .close-btn {
          background: none;
          border: none;
          font-size: 24px;
          color: #94a3b8;
          cursor: pointer;
          transition: color 0.3s ease;
        }

        .close-btn:hover {
          color: #ef4444;
        }

        .qr-code-container {
          text-align: center;
          padding: 20px;
          background: #f8fafc;
          border-radius: 10px;
          margin: 20px 0;
        }

        .secret-key {
          background: #f8fafc;
          padding: 15px;
          border-radius: 10px;
          margin: 15px 0;
          text-align: center;
        }

        .secret-key label {
          display: block;
          font-weight: 600;
          margin-bottom: 10px;
          color: #484d53;
        }

        .key-display {
          font-family: 'Courier New', monospace;
          font-size: 1.1rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
          padding: 10px;
          background: white;
          border-radius: 8px;
          word-break: break-all;
        }

        .security-status-card {
          background: linear-gradient(135deg, #667eea, #764ba2);
          color: white;
          padding: 20px;
          border-radius: 15px;
          margin-bottom: 20px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .security-status-card h4 {
          margin-bottom: 5px;
        }

        .security-score-container {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 20px;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 15px;
          margin-bottom: 20px;
        }

        .score-circle {
          position: relative;
          width: 120px;
          height: 120px;
          margin-bottom: 15px;
        }

        .score-circle svg {
          transform: rotate(-90deg);
        }

        .score-circle circle {
          fill: none;
          stroke-width: 8;
        }

        .score-bg {
          stroke: rgba(255, 255, 255, 0.2);
        }

        .score-fill {
          stroke: #10b981;
          stroke-linecap: round;
          transition: stroke-dashoffset 2s ease;
        }

        .score-text {
          position: absolute;
          top: 43%;
          left: 50%;
          width: 100%;
          transform: translate(-50%, -50%);
          text-align: center;
        }

        .score-value {
          font-size: 2rem;
          font-weight: 700;
          color: white;
        }

        .score-label {
          font-size: 1rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .password-toggle-btn {
          transition: all 0.3s ease;
        }

        .password-toggle-btn:hover {
          color: rgb(73, 57, 113) !important;
          transform: translateY(-50%) scale(1.1);
        }

        .password-toggle-btn:active {
          transform: translateY(-50%) scale(0.95);
        }

        #passwordRequirements {
          animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
          from {
            opacity: 0;
            transform: translateY(-10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        #strengthBar {
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
          .content { grid-template-columns: 70% 30%; }
          .settings-tabs { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .content { grid-template-columns: 65% 35%; }
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
          .settings-tabs { grid-template-columns: 1fr; }
          .form-row { grid-template-columns: 1fr; }
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
                    <a href="inventory.php">
                        <i class="fa fa-boxes nav-icon"></i>
                        <span class="nav-text">Inventory</span>
                        <?php if ($lowStockItems > 0): ?>
                        <span class="notification-badge"><?php echo $lowStockItems; ?></span>
                        <?php endif; ?>
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
                    <b></b><b></b>
                    <a href="customers.php">
                        <i class="fa fa-users nav-icon"></i>
                        <span class="nav-text">Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="notifications.php">
                        <i class="fa fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                        <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <b></b><b></b>
                    <a href="contact.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text">Contact</span>
                    </a>
                </li>
                <li class="nav-item active">
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
            </ul>
        </nav>

        <section class="content">
            <div class="left-content">
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <div class="settings-header">
                    <h1>Account Settings</h1>
                    <p>Manage your profile, preferences, and security settings</p>
                </div>

                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="switchTab(event, 'profile')">
                        <i class="fas fa-user"></i> Profile
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'account')">
                        <i class="fas fa-cog"></i> Account
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'notifications')">
                        <i class="fas fa-bell"></i> Notifications
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'permissions')">
                        <i class="fas fa-key"></i> Permissions
                    </button>
                </div>

                <!-- Profile Tab -->
                <div id="profile" class="tab-content active">
                    <div class="settings-card">
                        <h2>Profile Information</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="profile-upload">
                                <?php if (!empty($vendor['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($vendor['profile_picture']); ?>" class="profile-avatar" alt="Profile" id="preview-image">
                                <?php else: ?>
                                <img src="https://via.placeholder.com/100" class="profile-avatar" alt="Profile" id="preview-image">
                                <?php endif; ?>
                                <div>
                                    <input type="file" name="profile_picture" id="profile_picture" style="display: none;" accept="image/*" onchange="previewImage(this)">
                                    <button type="button" class="upload-btn" onclick="document.getElementById('profile_picture').click()">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </button>
                                    <p style="margin-top: 5px; color: #64748b; font-size: 0.85rem;">JPG, PNG or GIF (MAX. 5MB)</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($vendor['first_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($vendor['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($vendor['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Bio</label>
                                <textarea class="form-control" name="bio" rows="4"><?php echo htmlspecialchars($vendor['bio'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Tab -->
                <div id="account" class="tab-content">
                    <div class="settings-card">
                        <h2>Account Preferences</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Language</label>
                                    <select class="form-control" name="language">
                                        <option value="en_US" <?php echo ($preferences['language'] ?? 'en_US') === 'en_US' ? 'selected' : ''; ?>>English (US)</option>
                                        <option value="en_GB" <?php echo ($preferences['language'] ?? '') === 'en_GB' ? 'selected' : ''; ?>>English (UK)</option>
                                        <option value="es" <?php echo ($preferences['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                        <option value="fr" <?php echo ($preferences['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select class="form-control" name="currency">
                                        <option value="USD" <?php echo ($preferences['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                        <option value="EUR" <?php echo ($preferences['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                        <option value="GBP" <?php echo ($preferences['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                        <option value="LKR" <?php echo ($preferences['currency'] ?? '') === 'LKR' ? 'selected' : ''; ?>>LKR (Rs)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Date Format</label>
                                    <select class="form-control" name="date_format">
                                        <option value="MM/DD/YYYY" <?php echo ($preferences['date_format'] ?? 'MM/DD/YYYY') === 'MM/DD/YYYY' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="DD/MM/YYYY" <?php echo ($preferences['date_format'] ?? '') === 'DD/MM/YYYY' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="YYYY-MM-DD" <?php echo ($preferences['date_format'] ?? '') === 'YYYY-MM-DD' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Time Format</label>
                                    <select class="form-control" name="time_format">
                                        <option value="12h" <?php echo ($preferences['time_format'] ?? '12h') === '12h' ? 'selected' : ''; ?>>12-hour (AM/PM)</option>
                                        <option value="24h" <?php echo ($preferences['time_format'] ?? '') === '24h' ? 'selected' : ''; ?>>24-hour</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Timezone</label>
                                <select class="form-control" name="timezone">
                                    <option value="UTC" <?php echo ($preferences['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC (GMT)</option>
                                    <option value="America/New_York" <?php echo ($preferences['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>UTC-5 (Eastern Time)</option>
                                    <option value="America/Los_Angeles" <?php echo ($preferences['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>UTC-8 (Pacific Time)</option>
                                    <option value="Asia/Colombo" <?php echo ($preferences['timezone'] ?? '') === 'Asia/Colombo' ? 'selected' : ''; ?>>UTC+5:30 (Sri Lanka Time)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </form>
                    </div>
                </div>

                 <!-- Security Tab Content -->
    <div id="security" class="tab-content">
        <!-- Security Score Display -->
        <div class="security-status-card">
            <div>
                <h4>Security Status</h4>
                <p style="opacity: 0.9; margin: 5px 0 0 0;"><?php echo $securityScore['score']; ?> of <?php echo $securityScore['max_score']; ?> security measures enabled</p>
            </div>
            <div class="security-score-container" style="margin: 0; padding: 0; background: transparent;">
                <div class="score-circle" id="securityScoreCircle">
                    <svg width="100" height="100">
                        <circle cx="50" cy="50" r="40" class="score-bg"></circle>
                        <circle cx="50" cy="50" r="40" class="score-fill" id="scoreProgress"></circle>
                    </svg>
                    <div class="score-text">
                        <span class="score-value" id="securityScore"><?php echo $securityScore['percentage']; ?></span>
                        <span class="score-label">%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="settings-card">
            <h2>Change Password</h2>
            <form method="POST" id="changePasswordForm">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Current Password *</label>
                    <div style="position: relative;">
                        <input type="password" class="form-control" name="current_password" id="currentPassword" required style="padding-right: 45px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('currentPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 18px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password *</label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6" style="padding-right: 45px;">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('newPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 18px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordStrength" style="margin-top: 8px;">
                            <div style="height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                                <div id="strengthBar" style="height: 100%; width: 0%; transition: all 0.3s ease; background: #cbd5e1;"></div>
                            </div>
                            <small id="strengthText" style="color: #94a3b8; font-size: 0.8rem; display: block; margin-top: 4px;">Password must be at least 6 characters</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required minlength="6" style="padding-right: 45px;">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 18px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small id="passwordMatch" style="font-size: 0.8rem; display: block; margin-top: 4px;"></small>
                    </div>
                </div>
                
                <div id="passwordRequirements" style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                    <strong style="font-size: 0.9rem; color: #484d53; display: block; margin-bottom: 8px;">Password Requirements:</strong>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                        <div id="req-length" style="font-size: 0.85rem; color: #94a3b8;">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 6px;"></i>At least 6 characters
                        </div>
                        <div id="req-uppercase" style="font-size: 0.85rem; color: #94a3b8;">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 6px;"></i>One uppercase letter
                        </div>
                        <div id="req-lowercase" style="font-size: 0.85rem; color: #94a3b8;">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 6px;"></i>One lowercase letter
                        </div>
                        <div id="req-number" style="font-size: 0.85rem; color: #94a3b8;">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 6px;"></i>One number
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>

        <!-- Security Options with 2FA -->
        <div class="settings-card">
            <h2>Security Options</h2>
            <form method="POST" id="securityForm">
                <input type="hidden" name="action" value="update_security">
                
                <!-- Two-Factor Authentication -->
                <div class="switch-container">
                    <div class="switch-info">
                        <strong>Two-Factor Authentication</strong>
                        <small>Add extra security layer to your account</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" 
                               id="2faToggle" 
                               <?php echo ($vendor['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?>
                               onchange="handle2FAToggle(this)">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="switch-container">
                    <div class="switch-info">
                        <strong>Login Alerts</strong>
                        <small>Get notified of new login attempts</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="login_alerts" <?php echo ($security_settings['login_alerts'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Session Timeout (minutes)</label>
                    <input type="number" class="form-control" name="session_timeout" value="<?php echo intval(($security_settings['session_timeout'] ?? 1800) / 60); ?>" min="5" max="120">
                    <small style="color: #94a3b8; display: block; margin-top: 5px;">Automatically log out after inactivity</small>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-shield-alt"></i> Update Security Settings
                </button>
            </form>
        </div>

        <!-- Recent Security Activity -->
        <div class="settings-card">
            <h2>Recent Security Activity</h2>
            <?php if (empty($security_activities)): ?>
                <p style="color: #94a3b8; text-align: center; padding: 20px;">No recent activity found.</p>
            <?php else: ?>
                <?php foreach ($security_activities as $activity): ?>
                    <div class="activity-item <?php echo in_array($activity['activity_type'], ['login', 'profile_update', 'password_change', '2fa_enabled']) ? 'success' : (in_array($activity['activity_type'], ['failed_login', '2fa_disabled']) ? 'warning' : ''); ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <strong><?php echo htmlspecialchars($activity['activity_description']); ?></strong>
                                <small style="display: block; margin-top: 3px;">
                                    IP: <?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?>
                                </small>
                            </div>
                            <small><?php echo timeAgo($activity['created_at']); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 2FA Setup Modal -->
    <div id="twoFactorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-mobile-alt" style="margin-right: 8px;"></i>Setup Two-Factor Authentication</h3>
                <button class="close-btn" onclick="close2FAModal()">&times;</button>
            </div>
            <div id="twoFactorSetup">
                <p style="text-align: center; margin-bottom: 20px;">Scan this QR code with your authenticator app</p>
                <div class="qr-code-container" id="qrCodeContainer">
                    <div style="text-align: center;">
                        <i class="fas fa-spinner fa-spin fa-2x" style="color: rgb(73, 57, 113);"></i>
                        <p style="margin-top: 10px;">Generating QR Code...</p>
                    </div>
                </div>
                
                <div class="secret-key">
                    <label>Or enter this key manually:</label>
                    <div class="key-display" id="secretKey">Loading...</div>
                </div>

                <form id="verify2FAForm" style="margin-top: 20px;">
                    <div class="form-group">
                        <label>Enter 6-digit verification code:</label>
                        <input type="text" 
                               name="verification_code" 
                               class="form-control" 
                               style="text-align: center; font-size: 1.2rem; letter-spacing: 5px;"
                               placeholder="000000" 
                               maxlength="6" 
                               required>
                    </div>
                    <div id="verify2FAMessage"></div>
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-check"></i> Verify & Enable
                    </button>
                </form>
            </div>
        </div>
    </div>

                <!-- Notifications Tab -->
                <div id="notifications" class="tab-content">
                    <div class="settings-card">
                        <h2>Notification Preferences</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <h3 style="font-size: 1.1rem; margin: 20px 0 15px 0; color: #484d53;">Email Notifications</h3>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Order Updates</h4>
                                    <p>New orders and status changes</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="order_updates" <?php echo ($notification_prefs['order_updates'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Email Notifications</h4>
                                    <p>Receive notifications via email</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="email_notifications" <?php echo ($notification_prefs['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Weekly Reports</h4>
                                    <p>Sales and analytics summaries</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="weekly_reports" <?php echo ($notification_prefs['weekly_reports'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Marketing Emails</h4>
                                    <p>Promotional offers and news</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="marketing_emails" <?php echo ($notification_prefs['marketing_emails'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Security Alerts</h4>
                                    <p>Account security notifications</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="security_alerts" <?php echo ($notification_prefs['security_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <h3 style="font-size: 1.1rem; margin: 30px 0 15px 0; color: #484d53;">Other Notifications</h3>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>SMS Notifications</h4>
                                    <p>Receive notifications via SMS</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="sms_notifications" <?php echo ($notification_prefs['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Browser Notifications</h4>
                                    <p>Real-time alerts in browser</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="push_notifications" <?php echo ($notification_prefs['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" class="btn-primary" style="margin-top: 20px;">
                                <i class="fas fa-save"></i> Save Notification Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Permissions Tab -->
                <div id="permissions" class="tab-content">
                    <div class="settings-card">
                        <h2>Role & Permissions</h2>
                        <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 10px; border-left: 4px solid #3b82f6; margin-bottom: 20px;">
                            <i class="fas fa-info-circle" style="color: #3b82f6;"></i>
                            <strong style="color: #3b82f6;"> Current Role:</strong> Vendor - You have access to inventory, analytics, customers, and notification management.
                        </div>

                        <h3 style="font-size: 1.1rem; margin-bottom: 15px; color: #484d53;">Your Permissions</h3>

                        <div class="permission-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #484d53;">Inventory Management</h4>
                                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Add, edit, and manage product inventory</p>
                                </div>
                                <span class="badge success">
                                    <i class="fas fa-check"></i> Granted
                                </span>
                            </div>
                        </div>

                        <div class="permission-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #484d53;">Analytics Access</h4>
                                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">View sales reports and performance data</p>
                                </div>
                                <span class="badge success">
                                    <i class="fas fa-check"></i> Granted
                                </span>
                            </div>
                        </div>

                        <div class="permission-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #484d53;">Customer Management</h4>
                                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">View and manage customer information</p>
                                </div>
                                <span class="badge success">
                                    <i class="fas fa-check"></i> Granted
                                </span>
                            </div>
                        </div>

                        <div class="permission-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #484d53;">Order Processing</h4>
                                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Process and fulfill customer orders</p>
                                </div>
                                <span class="badge success">
                                    <i class="fas fa-check"></i> Granted
                                </span>
                            </div>
                        </div>

                        <div class="permission-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #484d53;">Financial Reports</h4>
                                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Access detailed financial analytics</p>
                                </div>
                                <span class="badge warning">
                                    <i class="fas fa-clock"></i> Limited
                                </span>
                            </div>
                        </div>

                        <div class="permission-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #484d53;">User Management</h4>
                                    <p style="margin: 0; color: #64748b; font-size: 0.9rem;">Add or remove team members</p>
                                </div>
                                <span class="badge danger">
                                    <i class="fas fa-times"></i> Restricted
                                </span>
                            </div>
                        </div>

                        <hr style="margin: 30px 0; border: none; border-top: 2px solid #e2e8f0;">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0 0 5px 0; color: #484d53;">Need additional permissions?</h3>
                                <p style="margin: 0; color: #64748b;">Contact your administrator to request access to restricted features.</p>
                            </div>
                            <a href="contact.php" class="btn-primary" style="text-decoration: none;">
                                <i class="fas fa-envelope"></i> Request Access
                            </a>
                        </div>
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

                <div class="quick-stats">
                    <h1>Quick Statistics</h1>
                    <div class="stats-list">
                        <div class="stat-item">
                            <p><i class="fas fa-box" style="margin-right: 8px; color: rgb(124, 136, 224);"></i> Total Products</p>
                            <span><?php echo $totalProducts; ?></span>
                        </div>
                        <div class="stat-item">
                            <p><i class="fas fa-shopping-cart" style="margin-right: 8px; color: rgb(124, 136, 224);"></i> Pending Orders</p>
                            <span><?php echo $pendingOrders; ?></span>
                        </div>
                        <div class="stat-item">
                            <p><i class="fas fa-exclamation-triangle" style="margin-right: 8px; color: #f59e0b;"></i> Low Stock</p>
                            <span><?php echo $lowStockItems; ?></span>
                        </div>
                        <div class="stat-item">
                            <p><i class="fas fa-bell" style="margin-right: 8px; color: #ef4444;"></i> Notifications</p>
                            <span><?php echo $unread_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
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

        // Set active menu based on current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'settings.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });
        });

        // Tab switching functionality
        function switchTab(event, tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tab buttons
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Preview image before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-image').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'all 0.3s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

          // Initialize security score animation
        document.addEventListener('DOMContentLoaded', function() {
            initSecurityScore();
            
            // Password strength checker
            const newPasswordField = document.getElementById('newPassword');
            const confirmPasswordField = document.getElementById('confirmPassword');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const passwordMatchText = document.getElementById('passwordMatch');
            const changePasswordForm = document.getElementById('changePasswordForm');

            if (newPasswordField) {
                newPasswordField.addEventListener('input', function() {
                    const password = this.value;
                    checkPasswordStrength(password);
                    checkPasswordMatch();
                });
            }

            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', checkPasswordMatch);
            }

            function checkPasswordStrength(password) {
                let strength = 0;
                const requirements = {
                    length: password.length >= 6,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password)
                };

                updateRequirement('req-length', requirements.length);
                updateRequirement('req-uppercase', requirements.uppercase);
                updateRequirement('req-lowercase', requirements.lowercase);
                updateRequirement('req-number', requirements.number);

                if (requirements.length) strength += 25;
                if (requirements.uppercase) strength += 25;
                if (requirements.lowercase) strength += 25;
                if (requirements.number) strength += 25;

                strengthBar.style.width = strength + '%';
                
                if (strength === 0) {
                    strengthBar.style.background = '#cbd5e1';
                    strengthText.textContent = 'Password must be at least 6 characters';
                    strengthText.style.color = '#94a3b8';
                } else if (strength <= 25) {
                    strengthBar.style.background = '#ef4444';
                    strengthText.textContent = 'Weak password';
                    strengthText.style.color = '#ef4444';
                } else if (strength <= 50) {
                    strengthBar.style.background = '#f59e0b';
                    strengthText.textContent = 'Fair password';
                    strengthText.style.color = '#f59e0b';
                } else if (strength <= 75) {
                    strengthBar.style.background = '#3b82f6';
                    strengthText.textContent = 'Good password';
                    strengthText.style.color = '#3b82f6';
                } else {
                    strengthBar.style.background = '#10b981';
                    strengthText.textContent = 'Strong password';
                    strengthText.style.color = '#10b981';
                }
            }

            function updateRequirement(elementId, isMet) {
                const element = document.getElementById(elementId);
                if (element) {
                    const icon = element.querySelector('i');
                    if (isMet) {
                        element.style.color = '#10b981';
                        icon.classList.remove('fa-circle');
                        icon.classList.add('fa-check-circle');
                    } else {
                        element.style.color = '#94a3b8';
                        icon.classList.remove('fa-check-circle');
                        icon.classList.add('fa-circle');
                    }
                }
            }

            function checkPasswordMatch() {
                const newPassword = newPasswordField.value;
                const confirmPassword = confirmPasswordField.value;

                if (confirmPassword === '') {
                    passwordMatchText.textContent = '';
                    return;
                }

                if (newPassword === confirmPassword) {
                    passwordMatchText.textContent = '✓ Passwords match';
                    passwordMatchText.style.color = '#10b981';
                } else {
                    passwordMatchText.textContent = '✗ Passwords do not match';
                    passwordMatchText.style.color = '#ef4444';
                }
            }

            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const newPassword = newPasswordField.value;
                    const confirmPassword = confirmPasswordField.value;

                    if (newPassword.length < 6) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Invalid Password',
                            text: 'Password must be at least 6 characters long',
                            icon: 'error',
                            confirmButtonColor: 'rgb(73, 57, 113)'
                        });
                        return false;
                    }

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Password Mismatch',
                            text: 'New password and confirm password do not match',
                            icon: 'error',
                            confirmButtonColor: 'rgb(73, 57, 113)'
                        });
                        return false;
                    }

                    return true;
                });
            }
        });

        // Security Score Animation
        function initSecurityScore() {
            const scoreValue = parseInt(document.getElementById('securityScore').textContent);
            const circle = document.getElementById('scoreProgress');
            const circumference = 2 * Math.PI * 40;
            const offset = circumference - (scoreValue / 100) * circumference;

            circle.style.strokeDasharray = circumference;
            circle.style.strokeDashoffset = circumference;

            setTimeout(() => {
                circle.style.strokeDashoffset = offset;
            }, 300);

            let currentScore = 0;
            const scoreEl = document.getElementById('securityScore');
            const scoreInterval = setInterval(() => {
                if (currentScore >= scoreValue) {
                    clearInterval(scoreInterval);
                } else {
                    currentScore++;
                    scoreEl.textContent = currentScore;
                }
            }, 20);

            if (scoreValue >= 80) {
                circle.style.stroke = '#10b981';
            } else if (scoreValue >= 60) {
                circle.style.stroke = '#f59e0b';
            } else {
                circle.style.stroke = '#ef4444';
            }
        }

        // Password Show/Hide Toggle
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // 2FA Toggle Handler
        async function handle2FAToggle(checkbox) {
            if (checkbox.checked) {
                await enable2FA();
            } else {
                await disable2FA();
            }
        }

        // Enable 2FA
        async function enable2FA() {
            try {
                const response = await fetch('ajax_2fa_vendor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=generate_2fa_secret'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('twoFactorModal').classList.add('show');
                    generateQRCode(data.data.qr_code_url);
                    document.getElementById('secretKey').textContent = data.data.secret;
                    init2FAVerification();
                } else {
                    Swal.fire('Error', data.message || 'Failed to generate 2FA secret', 'error');
                    document.getElementById('2faToggle').checked = false;
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to setup 2FA', 'error');
                document.getElementById('2faToggle').checked = false;
            }
        }

        // Generate QR Code
        function generateQRCode(url) {
            const qrContainer = document.getElementById('qrCodeContainer');
            
            const apiMethods = [
                `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(url)}`,
                `https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=${encodeURIComponent(url)}&choe=UTF-8`
            ];
            
            let methodIndex = 0;
            
            function tryNextMethod() {
                if (methodIndex >= apiMethods.length) {
                    qrContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            QR Code unavailable. Please use manual key entry below.
                        </div>
                    `;
                    return;
                }
                
                const img = new Image();
                img.onload = function() {
                    qrContainer.innerHTML = '';
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'padding: 15px; background: white; border-radius: 12px; display: inline-block; box-shadow: 0 4px 20px rgba(0,0,0,0.15);';
                    img.style.cssText = 'display: block; width: 250px; height: 250px;';
                    wrapper.appendChild(img);
                    qrContainer.appendChild(wrapper);
                };
                
                img.onerror = function() {
                    methodIndex++;
                    setTimeout(tryNextMethod, 500);
                };
                
                img.src = apiMethods[methodIndex];
                
                setTimeout(() => {
                    if (!img.complete || img.naturalHeight === 0) {
                        img.onerror();
                    }
                }, 5000);
            }
            
            tryNextMethod();
        }

        // Initialize 2FA Verification
        function init2FAVerification() {
            const form = document.getElementById('verify2FAForm');
            const codeInput = form.querySelector('input[name="verification_code"]');
            
            codeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });
            
            form.onsubmit = async function(e) {
                e.preventDefault();
                
                const code = codeInput.value.trim();
                
                if (code.length !== 6) {
                    showMessage('verify2FAMessage', 'Please enter a complete 6-digit code', 'danger');
                    return;
                }
                
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalHTML = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                submitBtn.disabled = true;
                
                const formData = new FormData(form);
                formData.append('action', 'verify_2fa');
                
                try {
                    const response = await fetch('ajax_2fa_vendor.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        let codesHTML = '<div style="background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 10px; margin-top: 15px;">';
                        codesHTML += '<h6 style="color: #10b981; margin-bottom: 10px;"><i class="fas fa-shield-alt"></i> 2FA Enabled Successfully!</h6>';
                        codesHTML += '<p style="font-size: 0.9rem; margin-bottom: 10px;">Save these backup codes:</p>';
                        codesHTML += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">';
                        data.data.backup_codes.forEach(code => {
                            codesHTML += `<code style="background: white; padding: 8px; border-radius: 5px; text-align: center; font-weight: 600;">${code}</code>`;
                        });
                        codesHTML += '</div></div>';
                        
                        showMessage('verify2FAMessage', codesHTML, 'success');
                        
                        setTimeout(() => {
                            location.reload();
                        }, 5000);
                    } else {
                        showMessage('verify2FAMessage', data.message, 'danger');
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    showMessage('verify2FAMessage', 'Error verifying code', 'danger');
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                }
            };
        }

        // Disable 2FA
        async function disable2FA() {
            const result = await Swal.fire({
                title: 'Disable 2FA?',
                text: 'This will make your account less secure',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, disable it'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('ajax_2fa_vendor.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=disable_2fa'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire('Disabled!', data.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                        document.getElementById('2faToggle').checked = true;
                    }
                } catch (error) {
                    Swal.fire('Error', 'Failed to disable 2FA', 'error');
                    document.getElementById('2faToggle').checked = true;
                }
            } else {
                document.getElementById('2faToggle').checked = true;
            }
        }

        // Close 2FA Modal
        function close2FAModal() {
            document.getElementById('twoFactorModal').classList.remove('show');
            document.getElementById('2faToggle').checked = false;
        }

        // Show Message Helper
        function showMessage(containerId, message, type) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            if (type !== 'success') {
                setTimeout(() => {
                    container.innerHTML = '';
                }, 5000);
            }
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