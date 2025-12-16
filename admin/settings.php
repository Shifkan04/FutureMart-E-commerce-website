<?php
require_once '../config.php';

// Check if user is admin
requireAdmin();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Fetch current user data with all security settings
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
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

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

$securityScore = calculateSecurityScore($userData);

// If preferences don't exist, create default ones
if (!$userData['language']) {
    $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)")->execute([$userId]);
}
if (!isset($userData['two_factor_enabled'])) {
    $pdo->prepare("INSERT INTO security_settings (user_id) VALUES (?)")->execute([$userId]);
}
if (!isset($userData['email_notifications'])) {
    $pdo->prepare("INSERT INTO notification_preferences (user_id) VALUES (?)")->execute([$userId]);
}

// Fetch recent security activity
$activityStmt = $pdo->prepare("
    SELECT activity_type, activity_description, ip_address, created_at 
    FROM user_activity_log 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$activityStmt->execute([$userId]);
$activities = $activityStmt->fetchAll();

// Get pending counts for badges
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pendingVendors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendor' AND status = 'inactive'")->fetchColumn();
$unreadNotifications = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE recipient_id = ? AND is_read = 0");
$unreadNotifications->execute([$userId]);
$unreadCount = $unreadNotifications->fetchColumn();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Profile Update
        if (isset($_POST['update_profile'])) {
            $firstName = sanitizeInput($_POST['first_name']);
            $lastName = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email'], 'email');
            $phone = sanitizeInput($_POST['phone']);
            $bio = sanitizeInput($_POST['bio']);

            if (!validateInput($email, 'email')) {
                throw new Exception('Invalid email format');
            }

            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkEmail->execute([$email, $userId]);
            if ($checkEmail->fetch()) {
                throw new Exception('Email already in use by another account');
            }

            $updateUser = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW() WHERE id = ?");
            $updateUser->execute([$firstName, $lastName, $email, $phone, $bio, $userId]);

            logUserActivity($userId, 'profile_update', 'Updated profile information');
            $message = 'Profile updated successfully!';
            $messageType = 'success';
        }

        // Account Preferences Update
        if (isset($_POST['update_preferences'])) {
            $language = sanitizeInput($_POST['language'] ?? null);
            $currency = sanitizeInput($_POST['currency'] ?? null);
            $dateFormat = sanitizeInput($_POST['date_format'] ?? null);
            $timeFormat = sanitizeInput($_POST['time_format'] ?? null);
            $timezone = sanitizeInput($_POST['timezone'] ?? null);
            $themePreference = $_POST['theme_preference'] ?? 'light';

            $updatePrefs = $pdo->prepare("UPDATE user_preferences SET language = ?, currency = ?, date_format = ?, time_format = ?, timezone = ? WHERE user_id = ?");
            $updatePrefs->execute([$language, $currency, $dateFormat, $timeFormat, $timezone, $userId]);

            $updateUser = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
            $updateUser->execute([$themePreference, $userId]);

            logUserActivity($userId, 'preferences_update', 'Updated account preferences');
            $message = 'Preferences updated successfully!';
            $messageType = 'success';
        }

        // Password Change
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match');
            }

            if (!validateInput($newPassword, 'password')) {
                throw new Exception('Password must be at least 6 characters long');
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePwd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updatePwd->execute([$hashedPassword, $userId]);

            $updateSecurity = $pdo->prepare("UPDATE security_settings SET last_password_change = NOW() WHERE user_id = ?");
            $updateSecurity->execute([$userId]);

            logUserActivity($userId, 'password_change', 'Password changed successfully');
            $message = 'Password changed successfully!';
            $messageType = 'success';
        }

        // Security Settings Update (WITHOUT 2FA toggle - handled by AJAX)
        if (isset($_POST['update_security'])) {
            $loginAlerts = isset($_POST['login_alerts']) ? 1 : 0;
            $sessionTimeout = (int)($_POST['session_timeout'] ?? 30) * 60; // Convert minutes to seconds

            $updateSecurity = $pdo->prepare("UPDATE security_settings SET login_alerts = ?, session_timeout = ? WHERE user_id = ?");
            $updateSecurity->execute([$loginAlerts, $sessionTimeout, $userId]);

            logUserActivity($userId, 'security_update', 'Updated security settings');
            $message = 'Security settings updated successfully!';
            $messageType = 'success';
        }

        // Notification Preferences Update
        if (isset($_POST['update_notifications'])) {
            $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
            $smsNotif = isset($_POST['sms_notifications']) ? 1 : 0;
            $pushNotif = isset($_POST['push_notifications']) ? 1 : 0;
            $orderUpdates = isset($_POST['order_updates']) ? 1 : 0;
            $marketingEmails = isset($_POST['marketing_emails']) ? 1 : 0;
            $securityAlerts = isset($_POST['security_alerts']) ? 1 : 0;
            $weeklyReports = isset($_POST['weekly_reports']) ? 1 : 0;

            $updateNotif = $pdo->prepare("UPDATE notification_preferences SET email_notifications = ?, sms_notifications = ?, push_notifications = ?, order_updates = ?, marketing_emails = ?, security_alerts = ?, weekly_reports = ? WHERE user_id = ?");
            $updateNotif->execute([$emailNotif, $smsNotif, $pushNotif, $orderUpdates, $marketingEmails, $securityAlerts, $weeklyReports, $userId]);

            logUserActivity($userId, 'notification_update', 'Updated notification preferences');
            $message = 'Notification preferences updated successfully!';
            $messageType = 'success';
        }

        // Profile Picture Upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['profile_picture']['type'];

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
            }

            if ($_FILES['profile_picture']['size'] > MAX_FILE_SIZE) {
                throw new Exception('File size exceeds maximum allowed size.');
            }

            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
                $updatePicture = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $updatePicture->execute([$filepath, $userId]);

                logUserActivity($userId, 'profile_picture_update', 'Updated profile picture');
                $message = 'Profile picture updated successfully!';
                $messageType = 'success';
            }
        }

        $pdo->commit();

        // Refresh user data
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();
        $securityScore = calculateSecurityScore($userData);
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}
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
          max-height: calc(100vh - 80px);
        }

        .settings-header h1 {
          margin: 0 0 20px;
          font-size: 1.4rem;
          font-weight: 700;
        }

        .settings-tabs {
          display: flex;
          gap: 10px;
          margin-bottom: 30px;
          flex-wrap: wrap;
        }

        .tab-btn {
          padding: 10px 20px;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 12px;
          font-weight: 600;
          color: #484d53;
          cursor: pointer;
          transition: all 0.3s ease;
          font-family: inherit;
          font-size: 0.9rem;
        }

        .tab-btn:hover, .tab-btn.active {
          background: rgb(73, 57, 113);
          color: white;
          border-color: rgb(73, 57, 113);
          transform: translateY(-2px);
        }

        .tab-btn i {
          margin-right: 8px;
        }

        .tab-content {
          display: none;
        }

        .tab-content.active {
          display: block;
        }

        .settings-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .settings-card h3 {
          font-size: 1.1rem;
          font-weight: 700;
          margin-bottom: 20px;
          color: #484d53;
        }

        .form-group {
          margin-bottom: 20px;
        }

        .form-group label {
          display: block;
          margin-bottom: 8px;
          font-weight: 600;
          color: #484d53;
          font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
          width: 100%;
          padding: 10px 12px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-family: inherit;
          font-size: 0.9rem;
          transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
          outline: none;
          border-color: rgb(73, 57, 113);
        }

        .form-group textarea {
          resize: vertical;
          min-height: 80px;
        }

        .form-row {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 15px;
        }

        .btn {
          display: inline-block;
          padding: 10px 20px;
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

        .btn i {
          margin-right: 5px;
        }

        .profile-section {
          display: grid;
          grid-template-columns: 150px 1fr;
          gap: 30px;
          align-items: start;
        }

        .profile-avatar-container {
          text-align: center;
        }

        .profile-avatar {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          object-fit: cover;
          border: 4px solid white;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 10px;
          margin-bottom: 15px;
        }

        .avatar-placeholder {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 3rem;
          font-weight: 700;
          color: white;
          margin: 0 auto 15px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 10px;
        }

        .switch-container {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 15px;
          background: #f8fafc;
          border-radius: 10px;
          margin-bottom: 15px;
        }

        .switch-info strong {
          display: block;
          color: #484d53;
          font-size: 0.95rem;
          margin-bottom: 3px;
        }

        .switch-info small {
          color: #94a3b8;
          font-size: 0.85rem;
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
          background-color: #cbd5e1;
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
          background-color: rgb(73, 57, 113);
        }

        input:checked + .slider:before {
          transform: translateX(26px);
        }

        .security-badge {
          display: inline-flex;
          align-items: center;
          padding: 10px 20px;
          border-radius: 12px;
          font-size: 0.9rem;
          font-weight: 600;
          background: rgba(16, 185, 129, 0.2);
          color: #10b981;
        }

        .security-badge i {
          margin-right: 8px;
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
        }

        .activity-item.success {
          border-left-color: #10b981;
        }

        .activity-item.warning {
          border-left-color: #f59e0b;
        }

        .activity-item strong {
          display: block;
          color: #484d53;
          margin-bottom: 5px;
        }

        .activity-item small {
          color: #94a3b8;
          font-size: 0.85rem;
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

        .alert {
          padding: 15px;
          margin-bottom: 20px;
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

        /* Security Score Circle */
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

        /* 2FA Modal */
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

        .swal2-popup {
          border-radius: 15px !important;
          box-shadow: 0 8px 25px rgba(73, 57, 113, 0.3);
        }

        .swal2-confirm {
          background: linear-gradient(135deg, rgb(73, 57, 113), #a38cd9) !important;
          border: none !important;
          font-weight: 600;
        }

        .swal2-cancel {
          background: #f6f7fb !important;
          color: #484d53 !important;
          border: 1px solid #ddd !important;
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
          .form-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .content { grid-template-columns: 55% 45%; }
          .profile-section { grid-template-columns: 1fr; text-align: center; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { grid-template-columns: 100%; grid-template-rows: 45% 55%; }
          .left-content { margin: 0 15px 15px 15px; }
          .right-content { margin: 15px; }
          .settings-tabs { flex-direction: column; }
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
                        <span class="nav-text">Testimonials</span>
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

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="contact.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text">Contact</span>
                    </a>
                </li>

                <li class="nav-item active">
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
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <div class="settings-header">
                    <h1>Account Settings</h1>
                </div>

                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="openTab('profile')">
                        <i class="fas fa-user"></i> Profile
                    </button>
                    <button class="tab-btn" onclick="openTab('account')">
                        <i class="fas fa-cog"></i> Preferences
                    </button>
                    <button class="tab-btn" onclick="openTab('security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                    <button class="tab-btn" onclick="openTab('notifications')">
                        <i class="fas fa-bell"></i> Notifications
                    </button>
                </div>

                <!-- Profile Tab -->
                <div id="profile" class="tab-content active">
                    <div class="settings-card">
                        <h3>Profile Information</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="profile-section">
                                <div class="profile-avatar-container">
                                    <?php if (!empty($userData['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" class="profile-avatar" alt="Profile">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?php echo strtoupper(substr($userData['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="profile_picture" id="profile_picture" style="display: none;" accept="image/*">
                                    <button type="button" class="btn" style="font-size: 0.85rem; padding: 8px 16px;" onclick="document.getElementById('profile_picture').click()">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </button>
                                </div>

                                <div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Phone Number</label>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Role</label>
                                            <input type="text" value="<?php echo ucfirst($userData['role']); ?>" disabled style="background: #f8fafc; color: #64748b;">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Bio</label>
                                        <textarea name="bio" rows="3"><?php echo htmlspecialchars($userData['bio'] ?? ''); ?></textarea>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Preferences Tab -->
                <div id="account" class="tab-content">
                    <div class="settings-card">
                        <h3>Account Preferences</h3>
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Language</label>
                                    <select name="language">
                                        <option value="en_US" <?php echo ($userData['language'] ?? 'en_US') === 'en_US' ? 'selected' : ''; ?>>English (US)</option>
                                        <option value="en_GB" <?php echo ($userData['language'] ?? '') === 'en_GB' ? 'selected' : ''; ?>>English (UK)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select name="currency">
                                        <option value="USD" <?php echo ($userData['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                        <option value="EUR" <?php echo ($userData['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                        <option value="GBP" <?php echo ($userData['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                        <option value="LKR" <?php echo ($userData['currency'] ?? '') === 'LKR' ? 'selected' : ''; ?>>LKR (Rs)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Date Format</label>
                                    <select name="date_format">
                                        <option value="MM/DD/YYYY" <?php echo ($userData['date_format'] ?? 'MM/DD/YYYY') === 'MM/DD/YYYY' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="DD/MM/YYYY" <?php echo ($userData['date_format'] ?? '') === 'DD/MM/YYYY' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="YYYY-MM-DD" <?php echo ($userData['date_format'] ?? '') === 'YYYY-MM-DD' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Time Format</label>
                                    <select name="time_format">
                                        <option value="12h" <?php echo ($userData['time_format'] ?? '12h') === '12h' ? 'selected' : ''; ?>>12-hour (AM/PM)</option>
                                        <option value="24h" <?php echo ($userData['time_format'] ?? '') === '24h' ? 'selected' : ''; ?>>24-hour</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Timezone</label>
                                <select name="timezone">
                                    <option value="UTC" <?php echo ($userData['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC (GMT)</option>
                                    <option value="America/New_York" <?php echo ($userData['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                    <option value="America/Los_Angeles" <?php echo ($userData['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                    <option value="Asia/Colombo" <?php echo ($userData['timezone'] ?? '') === 'Asia/Colombo' ? 'selected' : ''; ?>>Sri Lanka Time</option>
                                </select>
                            </div>

                            <h3 style="margin-top: 30px;">Display Settings</h3>
                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Dark Mode</strong>
                                    <small>Switch to dark theme</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="theme_preference" value="dark" <?php echo ($userData['theme_preference'] ?? 'light') === 'dark' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" name="update_preferences" class="btn">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Tab - WITH 2FA INTEGRATION -->
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
                        <h3>Change Password</h3>
                        <form method="POST" id="changePasswordForm">
                            <div class="form-group">
                                <label>Current Password *</label>
                                <div style="position: relative;">
                                    <input type="password" name="current_password" id="currentPassword" required style="padding-right: 45px;">
                                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('currentPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 18px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password *</label>
                                    <div style="position: relative;">
                                        <input type="password" name="new_password" id="newPassword" required minlength="6" style="padding-right: 45px;">
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
                                        <input type="password" name="confirm_password" id="confirmPassword" required minlength="6" style="padding-right: 45px;">
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
                            <button type="submit" name="change_password" class="btn">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>

                    <!-- Security Options with 2FA -->
                    <div class="settings-card">
                        <h3>Security Options</h3>
                        <form method="POST" id="securityForm">
                            <!-- Two-Factor Authentication -->
                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Two-Factor Authentication</strong>
                                    <small>Add extra security layer to your account</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" 
                                           id="2faToggle" 
                                           <?php echo ($userData['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?>
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
                                    <input type="checkbox" name="login_alerts" <?php echo ($userData['login_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label>Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" value="<?php echo intval(($userData['session_timeout'] ?? 1800) / 60); ?>" min="5" max="120">
                                <small style="color: #94a3b8; display: block; margin-top: 5px;">Automatically log out after inactivity</small>
                            </div>

                            <button type="submit" name="update_security" class="btn">
                                <i class="fas fa-shield-alt"></i> Update Security Settings
                            </button>
                        </form>
                    </div>

                    <!-- Recent Security Activity -->
                    <div class="settings-card">
                        <h3>Recent Security Activity</h3>
                        <?php if (empty($activities)): ?>
                            <p style="color: #94a3b8; text-align: center; padding: 20px;">No recent activity found.</p>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item <?php echo in_array($activity['activity_type'], ['login', 'profile_update', 'password_change', '2fa_enabled']) ? 'success' : (in_array($activity['activity_type'], ['failed_login', '2fa_disabled']) ? 'warning' : ''); ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['activity_description']); ?></strong>
                                            <small style="display: block; margin-top: 3px;">
                                                IP: <?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?>
                                            </small>
                                        </div>
                                        <small>
                                            <?php
                                            $time = strtotime($activity['created_at']);
                                            $diff = time() - $time;
                                            if ($diff < 3600) {
                                                echo floor($diff / 60) . ' min ago';
                                            } elseif ($diff < 86400) {
                                                echo floor($diff / 3600) . ' hrs ago';
                                            } else {
                                                echo floor($diff / 86400) . ' days ago';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div id="notifications" class="tab-content">
                    <div class="settings-card">
                        <h3>Email Notifications</h3>
                        <form method="POST">
                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Email Notifications</strong>
                                    <small>Receive email updates</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="email_notifications" <?php echo ($userData['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Order Updates</strong>
                                    <small>New orders and status changes</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="order_updates" <?php echo ($userData['order_updates'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Security Alerts</strong>
                                    <small>Important security updates</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="security_alerts" <?php echo ($userData['security_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Weekly Reports</strong>
                                    <small>Sales and analytics summaries</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="weekly_reports" <?php echo ($userData['weekly_reports'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Marketing Updates</strong>
                                    <small>Promotional offers and news</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="marketing_emails" <?php echo ($userData['marketing_emails'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <h3 style="margin-top: 30px;">Other Notifications</h3>

                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>Push Notifications</strong>
                                    <small>Real-time browser alerts</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="push_notifications" <?php echo ($userData['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="switch-container">
                                <div class="switch-info">
                                    <strong>SMS Notifications</strong>
                                    <small>Text message alerts</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="sms_notifications" <?php echo ($userData['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" name="update_notifications" class="btn">
                                <i class="fas fa-bell"></i> Save Notification Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="right-content">
                <div class="user-info">
                    <div class="icon-container">
                        <i class="fa fa-bell"></i>
                        <i class="fa fa-message"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></h4>
                    <?php if (!empty($userData['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" alt="Admin">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($userData['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quick-stats">
                    <h1>Account Info</h1>
                    <div class="stats-list">
                        <div class="stat-item">
                            <p>Account Type</p>
                            <span><?php echo ucfirst($userData['role']); ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Member Since</p>
                            <span><?php echo date('M Y', strtotime($userData['created_at'])); ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Last Login</p>
                            <span><?php echo isset($userData['last_login_at']) ? date('M d', strtotime($userData['last_login_at'])) : 'N/A'; ?></span>
                        </div>
                        <div class="stat-item">
                            <p>Status</p>
                            <span style="color: #10b981;">Active</span>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h1>Quick Actions</h1>
                    <div class="action-buttons">
                        <a href="dashboard.php" class="action-btn">
                            <i class="fas fa-home"></i>
                            <span>Back to Dashboard</span>
                        </a>
                        <a href="notifications.php" class="action-btn">
                            <i class="fas fa-bell"></i>
                            <span>View Notifications</span>
                        </a>
                        <a href="contact.php" class="action-btn">
                            <i class="fas fa-envelope"></i>
                            <span>Contact Support</span>
                        </a>
                        <a href="../logout.php" class="action-btn" style="color: #ef4444;">
                            <i class="fas fa-sign-out-alt" style="background: linear-gradient(135deg, #ef4444, #dc2626);"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- 2FA Setup Modal -->
    <div id="twoFactorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-mobile-alt me-2"></i>Setup Two-Factor Authentication</h3>
                <button class="close-btn" onclick="close2FAModal()">&times;</button>
            </div>
            <div id="twoFactorSetup">
                <p style="text-align: center; margin-bottom: 20px;">Scan this QR code with your authenticator app</p>
                <div class="qr-code-container" id="qrCodeContainer">
                    <div class="text-center">
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
                    <button type="submit" class="btn" style="width: 100%;">
                        <i class="fas fa-check me-2"></i>Verify & Enable
                    </button>
                </form>
            </div>
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
            const currentPage = window.location.pathname.split('/').pop() || 'settings.php';
            navItems.forEach((navItem) => {
                const link = navItem.querySelector('a');
                if (link && link.getAttribute('href') === currentPage) {
                    navItems.forEach((item) => item.classList.remove("active"));
                    navItem.classList.add("active");
                }
            });

            // Initialize security score animation
            initSecurityScore();

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

            // Animate number
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

            // Update color based on score
            if (scoreValue >= 80) {
                circle.style.stroke = '#10b981';
            } else if (scoreValue >= 60) {
                circle.style.stroke = '#f59e0b';
            } else {
                circle.style.stroke = '#ef4444';
            }
        }

        // Tab switching
        function openTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            const buttons = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
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

        // Password Strength Checker
        document.addEventListener('DOMContentLoaded', function() {
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

                // Update requirement indicators
                updateRequirement('req-length', requirements.length);
                updateRequirement('req-uppercase', requirements.uppercase);
                updateRequirement('req-lowercase', requirements.lowercase);
                updateRequirement('req-number', requirements.number);

                // Calculate strength
                if (requirements.length) strength += 25;
                if (requirements.uppercase) strength += 25;
                if (requirements.lowercase) strength += 25;
                if (requirements.number) strength += 25;

                // Update strength bar
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

            // Form validation before submit
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const newPassword = newPasswordField.value;
                    const confirmPassword = confirmPasswordField.value;

                    // Check minimum length
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

                    // Check if passwords match
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

                    // All validations passed
                    return true;
                });
            }
        });

        // 2FA Toggle Handler
        async function handle2FAToggle(checkbox) {
            if (checkbox.checked) {
                // Enable 2FA
                await enable2FA();
            } else {
                // Disable 2FA
                await disable2FA();
            }
        }

        // Enable 2FA
        async function enable2FA() {
            try {
                const response = await fetch('ajax_2fa.php', {
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
                    const response = await fetch('ajax_2fa.php', {
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
                    const response = await fetch('ajax_2fa.php', {
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

        // Profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.querySelector('.profile-avatar');
                    const placeholder = document.querySelector('.avatar-placeholder');
                    
                    if (img) {
                        img.src = e.target.result;
                    } else if (placeholder) {
                        placeholder.outerHTML = `<img src="${e.target.result}" class="profile-avatar" alt="Profile">`;
                    }
                };
                reader.readAsDataURL(file);

                const form = e.target.closest('form');
                Swal.fire({
                    title: 'Upload Photo?',
                    text: 'Upload this profile picture?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: 'rgb(73, 57, 113)',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Yes, upload it'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        });

        function confirmLogout(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Logout Confirmation',
                text: 'Are you sure you want to log out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'rgb(73, 57, 113)',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Yes, log me out',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Logging out...',
                        text: 'Please wait',
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