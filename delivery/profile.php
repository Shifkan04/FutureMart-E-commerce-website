<?php
require_once '../config.php';
require_once '../languages.php'; // Add the languages file

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}

$delivery_person_id = $_SESSION['user_id'];

// Fetch delivery person details with statistics
try {
    $stmt = $pdo->prepare("
        SELECT u.*,
               COUNT(DISTINCT da.id) as total_deliveries,
               COUNT(DISTINCT CASE WHEN da.status = 'delivered' THEN da.id END) as completed_deliveries,
               AVG(CASE WHEN da.status = 'delivered' AND da.delivered_at IS NOT NULL 
                   THEN TIMESTAMPDIFF(MINUTE, da.assigned_at, da.delivered_at) END) as avg_delivery_time
        FROM users u
        LEFT JOIN delivery_assignments da ON u.id = da.delivery_person_id
        WHERE u.id = ? AND u.role = 'delivery'
        GROUP BY u.id
    ");
    $stmt->execute([$delivery_person_id]);
    $delivery_person = $stmt->fetch();

    if (!$delivery_person) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Set user language in session
    $_SESSION['user_language'] = $delivery_person['language'] ?? 'en_US';
    $translations = loadLanguage($_SESSION['user_language']);

    // Calculate on-time delivery percentage
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at) <= 30 THEN 1 ELSE 0 END) as on_time
        FROM delivery_assignments
        WHERE delivery_person_id = ? AND status = 'delivered'
    ");
    $stmt->execute([$delivery_person_id]);
    $delivery_stats = $stmt->fetch();
    $on_time_percentage = $delivery_stats['total'] > 0 
        ? round(($delivery_stats['on_time'] / $delivery_stats['total']) * 100) 
        : 0;

    // Get this month's statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as month_deliveries,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as month_completed
        FROM delivery_assignments
        WHERE delivery_person_id = ? 
        AND MONTH(assigned_at) = MONTH(CURRENT_DATE())
        AND YEAR(assigned_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$delivery_person_id]);
    $month_stats = $stmt->fetch();

    // Get notification preferences
    $stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ?");
    $stmt->execute([$delivery_person_id]);
    $notif_prefs = $stmt->fetch();
    
    if (!$notif_prefs) {
        $notif_prefs = [
            'order_updates' => 1,
            'promotional_emails' => 1,
            'sms_notifications' => 0
        ];
    }

} catch (PDOException $e) {
    error_log("Profile Page Error: " . $e->getMessage());
    die("An error occurred while loading your profile.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'update_profile') {
                $first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
                $last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
                $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
                $bio = filter_var($_POST['bio'], FILTER_SANITIZE_STRING);

                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, bio = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $bio, $delivery_person_id]);

                logUserActivity($delivery_person_id, 'profile_update', 'Updated profile information', $_SERVER['REMOTE_ADDR'] ?? null);
                
                $_SESSION['success'] = t('profile_updated');
                header('Location: profile.php');
                exit();
            }
            
            elseif ($action === 'update_settings') {
                $theme = filter_var($_POST['theme'], FILTER_SANITIZE_STRING);
                $language = filter_var($_POST['language'], FILTER_SANITIZE_STRING);

                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET theme_preference = ?, language = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$theme, $language, $delivery_person_id]);

                // Update session language
                $_SESSION['user_language'] = $language;

                logUserActivity($delivery_person_id, 'preferences_update', 'Updated account preferences', $_SERVER['REMOTE_ADDR'] ?? null);
                
                $_SESSION['success'] = t('settings_updated');
                header('Location: profile.php');
                exit();
            }
            
            elseif ($action === 'update_profile_picture') {
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['profile_picture'];
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024;

                    if (!in_array($file['type'], $allowed_types)) {
                        $_SESSION['error'] = t('invalid_file_type');
                        header('Location: profile.php');
                        exit();
                    }

                    if ($file['size'] > $max_size) {
                        $_SESSION['error'] = t('file_size_error');
                        header('Location: profile.php');
                        exit();
                    }

                    $upload_dir = '../uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $delivery_person_id . '_' . time() . '.' . $extension;
                    $filepath = $upload_dir . $filename;

                    if ($delivery_person['profile_picture'] && file_exists('../' . $delivery_person['profile_picture'])) {
                        unlink('../' . $delivery_person['profile_picture']);
                    }

                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $db_path = 'uploads/profiles/' . $filename;
                        
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$db_path, $delivery_person_id]);

                        logUserActivity($delivery_person_id, 'profile_picture_update', 'Updated profile picture', $_SERVER['REMOTE_ADDR'] ?? null);

                        $_SESSION['success'] = t('picture_updated');
                    } else {
                        $_SESSION['error'] = t('upload_failed');
                    }
                } else {
                    $_SESSION['error'] = t('no_file_uploaded');
                }
                
                header('Location: profile.php');
                exit();
            }
            
            elseif ($action === 'update_notifications') {
                $email_notif = isset($_POST['email_notif']) ? 1 : 0;
                $sms_notif = isset($_POST['sms_notif']) ? 1 : 0;
                $push_notif = isset($_POST['push_notif']) ? 1 : 0;

                $stmt = $pdo->prepare("SELECT id FROM user_notifications WHERE user_id = ?");
                $stmt->execute([$delivery_person_id]);
                
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        UPDATE user_notifications 
                        SET order_updates = ?, promotional_emails = ?, sms_notifications = ?, updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$email_notif, $push_notif, $sms_notif, $delivery_person_id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_notifications (user_id, order_updates, promotional_emails, sms_notifications) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$delivery_person_id, $email_notif, $push_notif, $sms_notif]);
                }

                logUserActivity($delivery_person_id, 'notification_update', 'Updated notification preferences', $_SERVER['REMOTE_ADDR'] ?? null);
                
                $_SESSION['success'] = t('notifications_updated');
                header('Location: profile.php');
                exit();
            }

        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while updating your profile.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo substr($_SESSION['user_language'] ?? 'en_US', 0, 2); ?>" data-theme="<?php echo htmlspecialchars($delivery_person['theme_preference'] ?? 'light'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('profile_title'); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");
        <?php if ($_SESSION['user_language'] === 'ta_LK'): ?>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Tamil:wght@300;400;500;600;700&display=swap');
        <?php elseif ($_SESSION['user_language'] === 'si_LK'): ?>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@300;400;500;600;700&display=swap');
        <?php endif; ?>

        :root {
            --bg-primary: #f6f7fb;
            --bg-secondary: #ffffff;
            --bg-gradient-start: rgb(124, 136, 224);
            --bg-gradient-end: #c3f4fc;
            --text-primary: #484d53;
            --text-secondary: #6b7280;
            --text-inverse: #ffffff;
            --border-color: #e5e7eb;
            --shadow: rgba(0, 0, 0, 0.1);
            --shadow-hover: rgba(0, 0, 0, 0.15);
            --menu-bg: rgb(73, 57, 113);
            --menu-text: #ffffff;
            --menu-active-bg: rgb(254, 254, 254);
            --menu-active-text: #000000;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --bg-gradient-start: rgb(73, 57, 113);
            --bg-gradient-end: rgb(93, 77, 133);
            --text-primary: #e4e4e7;
            --text-secondary: #a1a1aa;
            --text-inverse: #ffffff;
            --border-color: #27272a;
            --shadow: rgba(0, 0, 0, 0.3);
            --shadow-hover: rgba(0, 0, 0, 0.5);
            --menu-bg: rgb(53, 37, 93);
            --menu-text: #e4e4e7;
            --menu-active-bg: rgb(73, 57, 113);
            --menu-active-text: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            [data-theme="auto"] {
                --bg-primary: #1a1a2e;
                --bg-secondary: #16213e;
                --bg-gradient-start: rgb(73, 57, 113);
                --bg-gradient-end: rgb(93, 77, 133);
                --text-primary: #e4e4e7;
                --text-secondary: #a1a1aa;
                --text-inverse: #ffffff;
                --border-color: #27272a;
                --shadow: rgba(0, 0, 0, 0.3);
                --shadow-hover: rgba(0, 0, 0, 0.5);
                --menu-bg: rgb(53, 37, 93);
                --menu-text: #e4e4e7;
                --menu-active-bg: rgb(73, 57, 113);
                --menu-active-text: #ffffff;
            }
        }

        *, *::before, *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        body {
          font-family: <?php 
              if ($_SESSION['user_language'] === 'ta_LK') {
                  echo "'Noto Sans Tamil', 'Nunito', sans-serif";
              } elseif ($_SESSION['user_language'] === 'si_LK') {
                  echo "'Noto Sans Sinhala', 'Nunito', sans-serif";
              } else {
                  echo "'Nunito', sans-serif";
              }
          ?>;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          background: var(--bg-primary);
          background-image: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);
          background-repeat: no-repeat;
          background-size: cover;
          transition: background-color 0.3s ease;
        }

        main {
          display: grid;
          grid-template-columns: 13% 87%;
          width: 100%;
          margin: 40px;
          background: var(--bg-secondary);
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset,
            0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px var(--shadow);
          border-radius: 15px;
          z-index: 10;
          transition: all 0.3s ease;
        }

        .main-menu {
          overflow: hidden;
          background: var(--menu-bg);
          padding-top: 10px;
          border-radius: 15px 0 0 15px;
          font-family: inherit;
          transition: background-color 0.3s ease;
        }

        .main-menu h1 {
          display: block;
          font-size: 1.5rem;
          font-weight: 500;
          text-align: center;
          margin: 0;
          color: var(--menu-text);
          padding-top: 15px;
        }

        .main-menu small {
          display: block;
          font-size: 1rem;
          font-weight: 300;
          text-align: center;
          margin: 10px 0;
          color: var(--menu-text);
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
          list-style: none;
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
          flex-direction: row;
          align-items: center;
          justify-content: center;
          color: var(--menu-text);
          font-size: 1rem;
          padding: 15px 0;
          margin-left: 10px;
          border-top-left-radius: 20px;
          border-bottom-left-radius: 20px;
          transition: all 0.3s ease;
        }

        .nav-item b:nth-child(1) {
          position: absolute;
          top: -15px;
          height: 15px;
          width: 100%;
          background: var(--bg-secondary);
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
          background: var(--menu-bg);
        }

        .nav-item b:nth-child(2) {
          position: absolute;
          bottom: -15px;
          height: 15px;
          width: 100%;
          background: var(--bg-secondary);
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
          background: var(--menu-bg);
        }

        .nav-item.active b:nth-child(1),
        .nav-item.active b:nth-child(2) {
          display: block;
        }

        .nav-item.active a {
          text-decoration: none;
          color: var(--menu-active-text);
          background: var(--menu-active-bg);
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
          background: var(--bg-primary);
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
          transition: background-color 0.3s ease;
        }

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

        [data-theme="dark"] .alert-success {
          color: #86efac;
        }

        .alert-error {
          background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(252, 165, 165, 0.1));
          border-left: 4px solid #ef4444;
          color: #7f1d1d;
        }

        [data-theme="dark"] .alert-error {
          color: #fca5a5;
        }

        @keyframes slideInDown {
          from { transform: translateY(-20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }

        .profile-header-card {
          background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
          border-radius: 15px;
          padding: 30px;
          margin-bottom: 20px;
          box-shadow: var(--shadow) 0px 1px 3px;
          position: relative;
          overflow: hidden;
          transition: all 0.3s ease;
        }

        .profile-header-content {
          display: grid;
          grid-template-columns: auto 1fr auto;
          gap: 25px;
          align-items: center;
        }

        .profile-image-wrapper {
          position: relative;
        }

        .profile-image {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          object-fit: cover;
          border: 5px solid white;
          box-shadow: 0 8px 20px var(--shadow);
        }

        .camera-btn {
          position: absolute;
          bottom: 5px;
          right: 5px;
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: white;
          border: none;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 10px var(--shadow);
          transition: all 0.3s ease;
        }

        .camera-btn:hover {
          transform: scale(1.1);
        }

        .profile-info {
          color: var(--text-primary);
        }

        .profile-info h2 {
          font-size: 1.8rem;
          font-weight: 700;
          margin-bottom: 10px;
          color: var(--text-inverse);
        }

        .profile-meta {
          display: flex;
          gap: 20px;
          flex-wrap: wrap;
          margin-top: 10px;
        }

        .profile-meta-item {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 0.95rem;
          font-weight: 600;
          color: var(--text-inverse);
        }

        .profile-stats-quick {
          background: var(--bg-secondary);
          border-radius: 12px;
          padding: 20px;
          text-align: center;
          box-shadow: var(--shadow) 0px 2px 8px;
          transition: all 0.3s ease;
        }

        .profile-stats-quick h3 {
          font-size: 2rem;
          font-weight: 700;
          color: var(--bg-gradient-start);
          margin-bottom: 5px;
        }

        .profile-stats-quick p {
          font-size: 0.9rem;
          font-weight: 600;
          color: var(--text-secondary);
          margin: 0;
        }

        .tab-navigation {
          display: flex;
          gap: 10px;
          margin-bottom: 20px;
          background: var(--bg-secondary);
          padding: 10px;
          border-radius: 15px;
          box-shadow: var(--shadow) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .tab-btn {
          flex: 1;
          padding: 12px 20px;
          background: transparent;
          border: none;
          border-radius: 10px;
          font-weight: 600;
          font-size: 0.95rem;
          color: var(--text-secondary);
          cursor: pointer;
          transition: all 0.3s ease;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
        }

        .tab-btn:hover {
          background: var(--bg-primary);
        }

        .tab-btn.active {
          background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
          color: white;
        }

        .tab-content {
          display: none;
        }

        .tab-content.active {
          display: block;
        }

        .form-card {
          background: var(--bg-secondary);
          border-radius: 15px;
          padding: 25px;
          margin-bottom: 20px;
          box-shadow: var(--shadow) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .form-card h3 {
          font-size: 1.2rem;
          font-weight: 700;
          color: var(--text-primary);
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
          display: flex;
          flex-direction: column;
          gap: 8px;
        }

        .form-group label {
          font-size: 0.9rem;
          font-weight: 600;
          color: var(--text-primary);
        }

        .form-control {
          padding: 12px 15px;
          border: 2px solid var(--border-color);
          border-radius: 10px;
          font-size: 0.95rem;
          font-family: inherit;
          transition: all 0.3s ease;
          background: var(--bg-primary);
          color: var(--text-primary);
        }

        .form-control:focus {
          outline: none;
          border-color: var(--bg-gradient-start);
          box-shadow: 0 0 0 3px rgba(124, 136, 224, 0.1);
        }

        .form-control:disabled {
          background: var(--bg-primary);
          cursor: not-allowed;
          opacity: 0.6;
        }

        textarea.form-control {
          resize: vertical;
          min-height: 100px;
        }

        .form-hint {
          font-size: 0.85rem;
          color: var(--text-secondary);
        }

        .btn {
          padding: 12px 24px;
          font-size: 0.95rem;
          font-weight: 600;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 8px;
        }

        .btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 20px var(--shadow-hover);
        }

        .btn-primary {
          background: linear-gradient(135deg, rgb(73, 57, 113), rgb(93, 77, 133));
          color: white;
        }

        .btn-outline {
          background: var(--bg-secondary);
          color: rgb(73, 57, 113);
          border: 2px solid rgb(73, 57, 113);
        }

        .checkbox-group {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 12px;
          background: var(--bg-primary);
          border-radius: 10px;
          margin-bottom: 12px;
          transition: all 0.3s ease;
        }

        .checkbox-group:hover {
          background: var(--border-color);
        }

        .checkbox-group input[type="checkbox"] {
          width: 20px;
          height: 20px;
          cursor: pointer;
        }

        .checkbox-group label {
          flex: 1;
          cursor: pointer;
          margin: 0;
          font-weight: 500;
          color: var(--text-primary);
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 20px;
          margin-bottom: 20px;
        }

        .stat-card {
          background: var(--bg-secondary);
          border-radius: 15px;
          padding: 25px;
          text-align: center;
          box-shadow: var(--shadow) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .stat-card:hover {
          transform: translateY(-5px);
          box-shadow: var(--shadow-hover) 0px 3px 8px;
        }

        .stat-card i {
          font-size: 3rem;
          margin-bottom: 15px;
        }

        .stat-card.trophy i { color: #f59e0b; }
        .stat-card.clock i { color: #10b981; }
        .stat-card.chart i { color: #3b82f6; }

        .stat-card h4 {
          font-size: 2rem;
          font-weight: 700;
          color: var(--bg-gradient-start);
          margin-bottom: 5px;
        }

        .stat-card p {
          font-size: 0.9rem;
          font-weight: 600;
          color: var(--text-secondary);
          margin: 0;
        }

        .performance-details {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 20px;
        }

        .detail-card {
          background: var(--bg-secondary);
          border-radius: 15px;
          padding: 20px;
          box-shadow: var(--shadow) 0px 1px 3px;
          transition: all 0.3s ease;
        }

        .detail-card h4 {
          font-size: 1.1rem;
          font-weight: 700;
          color: var(--text-primary);
          margin-bottom: 15px;
        }

        .detail-list {
          list-style: none;
          padding: 0;
          margin: 0;
        }

        .detail-item {
          display: flex;
          justify-content: space-between;
          padding: 12px 0;
          border-bottom: 1px solid var(--border-color);
        }

        .detail-item:last-child {
          border-bottom: none;
        }

        .detail-label {
          color: var(--text-secondary);
          font-weight: 500;
        }

        .detail-value {
          font-weight: 700;
          color: var(--bg-gradient-start);
        }

        .badges-container {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
        }

        .badge {
          padding: 8px 16px;
          border-radius: 20px;
          font-size: 0.85rem;
          font-weight: 600;
          display: inline-flex;
          align-items: center;
          gap: 6px;
        }

        .badge.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge.info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .badge.primary { background: rgba(124, 136, 224, 0.2); color: rgb(73, 57, 113); }

        [data-theme="dark"] .badge.primary { color: #a5b4fc; }

        .theme-indicator {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 8px 12px;
          background: var(--bg-primary);
          border-radius: 8px;
          font-size: 0.85rem;
          color: var(--text-secondary);
          margin-left: 10px;
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .form-row, .stats-grid, .performance-details { grid-template-columns: 1fr; }
          .profile-header-content { grid-template-columns: 1fr; text-align: center; }
          .profile-stats-quick { margin-top: 20px; }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { margin: 15px; padding: 15px; }
          .tab-navigation { flex-direction: column; }
        }
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><?php echo APP_NAME; ?></h1>
            <small><?php echo t('delivery_panel'); ?></small>
            <div class="logo">
                <i class="fa fa-truck" style="font-size: 24px; color: white;"></i>
            </div>
            <ul>
                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="dashboard.php">
                        <i class="fa fa-home nav-icon"></i>
                        <span class="nav-text"><?php echo t('dashboard'); ?></span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="deliveries.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text"><?php echo t('my_deliveries'); ?></span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="route.php">
                        <i class="fa fa-route nav-icon"></i>
                        <span class="nav-text"><?php echo t('route_map'); ?></span>
                    </a>
                </li>

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="profile.php">
                        <i class="fa fa-user nav-icon"></i>
                        <span class="nav-text"><?php echo t('profile'); ?></span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="messages.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text"><?php echo t('messages'); ?></span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="contact.php">
                        <i class="fa fa-phone nav-icon"></i>
                        <span class="nav-text"><?php echo t('contact'); ?></span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="../logout.php" onclick="return confirm('<?php echo t('confirm_logout'); ?>')">
                        <i class="fa fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text"><?php echo t('logout'); ?></span>
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

            <!-- Profile Header -->
            <div class="profile-header-card">
                <div class="profile-header-content">
                    <div class="profile-image-wrapper">
                        <?php if ($delivery_person['profile_picture']): ?>
                            <img src="../<?php echo htmlspecialchars($delivery_person['profile_picture']); ?>" alt="Profile" class="profile-image" id="profileImage">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/120x120/ffffff/7c88e0?text=<?php echo strtoupper(substr($delivery_person['first_name'], 0, 1) . substr($delivery_person['last_name'], 0, 1)); ?>" alt="Profile" class="profile-image" id="profileImage">
                        <?php endif; ?>
                        <button class="camera-btn" onclick="document.getElementById('profilePictureInput').click()">
                            <i class="fas fa-camera" style="color: rgb(73, 57, 113);"></i>
                        </button>
                        <form method="POST" enctype="multipart/form-data" id="profilePictureForm" style="display: none;">
                            <input type="hidden" name="action" value="update_profile_picture">
                            <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" onchange="previewAndUpload(this)">
                        </form>
                    </div>

                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($delivery_person['first_name'] . ' ' . $delivery_person['last_name']); ?></h2>
                        <div class="profile-meta">
                            <div class="profile-meta-item">
                                <i class="fas fa-id-badge"></i>
                                <span><?php echo t('id'); ?>: DP<?php echo str_pad($delivery_person['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo t('joined'); ?>: <?php echo date('M Y', strtotime($delivery_person['created_at'])); ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <i class="fas fa-star"></i>
                                <span><?php echo t('status'); ?>: <?php echo ucfirst($delivery_person['status']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-stats-quick">
                        <h3><?php echo $delivery_person['total_deliveries'] ?: 0; ?></h3>
                        <p><?php echo t('total_deliveries'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="switchTab('personal')">
                    <i class="fas fa-user"></i>
                    <span><?php echo t('personal_info'); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('settings')">
                    <i class="fas fa-cog"></i>
                    <span><?php echo t('settings'); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('performance')">
                    <i class="fas fa-chart-line"></i>
                    <span><?php echo t('performance'); ?></span>
                </button>
            </div>

            <!-- Personal Info Tab -->
            <div class="tab-content active" id="personal-tab">
                <div class="form-card">
                    <h3><i class="fas fa-edit"></i> <?php echo t('edit_personal_info'); ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo t('first_name'); ?></label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($delivery_person['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('last_name'); ?></label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($delivery_person['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo t('email'); ?></label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($delivery_person['email']); ?>" disabled>
                                <small class="form-hint"><?php echo t('email_cannot_change'); ?></small>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('phone'); ?></label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($delivery_person['phone'] ?: ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?php echo t('bio_notes'); ?></label>
                            <textarea class="form-control" name="bio"><?php echo htmlspecialchars($delivery_person['bio'] ?: ''); ?></textarea>
                        </div><br>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('save_changes'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-content" id="settings-tab">
                <div class="form-card">
                    <h3><i class="fas fa-bell"></i> <?php echo t('notification_preferences'); ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_notifications">
                        <div class="checkbox-group">
                            <input type="checkbox" name="email_notif" id="emailNotif" <?php echo $notif_prefs['order_updates'] ? 'checked' : ''; ?>>
                            <label for="emailNotif"><?php echo t('email_notifications'); ?></label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="sms_notif" id="smsNotif" <?php echo $notif_prefs['sms_notifications'] ? 'checked' : ''; ?>>
                            <label for="smsNotif"><?php echo t('sms_notifications'); ?></label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="push_notif" id="pushNotif" <?php echo $notif_prefs['promotional_emails'] ? 'checked' : ''; ?>>
                            <label for="pushNotif"><?php echo t('push_notifications'); ?></label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('save_notification_prefs'); ?>
                        </button>
                    </form>
                </div>

                <div class="form-card">
                    <h3>
                        <i class="fas fa-cog"></i> <?php echo t('application_settings'); ?>
                        <span class="theme-indicator" id="themeIndicator">
                            <i class="fas fa-moon"></i>
                            <span id="themeText"><?php echo t('dark'); ?></span>
                        </span>
                    </h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo t('theme'); ?></label>
                                <select class="form-control" name="theme" id="themeSelect" onchange="changeTheme(this.value)">
                                    <option value="light" <?php echo $delivery_person['theme_preference'] === 'light' ? 'selected' : ''; ?>><?php echo t('light'); ?></option>
                                    <option value="dark" <?php echo $delivery_person['theme_preference'] === 'dark' ? 'selected' : ''; ?>><?php echo t('dark'); ?></option>
                                    <option value="auto" <?php echo $delivery_person['theme_preference'] === 'auto' ? 'selected' : ''; ?>><?php echo t('auto'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('language'); ?></label>
                                <select class="form-control" name="language" id="languageSelect">
                                    <option value="en_US" <?php echo $delivery_person['language'] === 'en_US' ? 'selected' : ''; ?>><?php echo t('english'); ?></option>
                                    <option value="si_LK" <?php echo $delivery_person['language'] === 'si_LK' ? 'selected' : ''; ?>><?php echo t('sinhala'); ?></option>
                                    <option value="ta_LK" <?php echo $delivery_person['language'] === 'ta_LK' ? 'selected' : ''; ?>><?php echo t('tamil'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div style="margin: 20px 0;">
                            <a href="change_password.php" class="btn btn-outline">
                                <i class="fas fa-key"></i> <?php echo t('change_password'); ?>
                            </a>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('save_settings'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Performance Tab -->
            <div class="tab-content" id="performance-tab">
                <div class="stats-grid">
                    <div class="stat-card trophy">
                        <i class="fas fa-trophy"></i>
                        <h4><?php echo $delivery_person['completed_deliveries'] ?: 0; ?></h4>
                        <p><?php echo t('completed_deliveries'); ?></p>
                    </div>
                    <div class="stat-card clock">
                        <i class="fas fa-clock"></i>
                        <h4><?php echo $on_time_percentage; ?>%</h4>
                        <p><?php echo t('on_time_delivery'); ?></p>
                    </div>
                    <div class="stat-card chart">
                        <i class="fas fa-chart-line"></i>
                        <h4><?php echo round($delivery_person['avg_delivery_time'] ?: 0); ?> <?php echo t('min'); ?></h4>
                        <p><?php echo t('avg_delivery_time'); ?></p>
                    </div>
                </div>

                <div class="performance-details">
                    <div class="detail-card">
                        <h4><i class="fas fa-calendar-alt"></i> <?php echo t('this_month'); ?></h4>
                        <ul class="detail-list">
                            <li class="detail-item">
                                <span class="detail-label"><?php echo t('deliveries_assigned'); ?></span>
                                <span class="detail-value"><?php echo $month_stats['month_deliveries'] ?: 0; ?></span>
                            </li>
                            <li class="detail-item">
                                <span class="detail-label"><?php echo t('completed_deliveries'); ?></span>
                                <span class="detail-value"><?php echo $month_stats['month_completed'] ?: 0; ?></span>
                            </li>
                            <li class="detail-item">
                                <span class="detail-label"><?php echo t('average_time'); ?></span>
                                <span class="detail-value"><?php echo round($delivery_person['avg_delivery_time'] ?: 0); ?> <?php echo t('mins'); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="detail-card">
                        <h4><i class="fas fa-award"></i> <?php echo t('performance_status'); ?></h4>
                        <div class="badges-container">
                            <?php if ($delivery_person['completed_deliveries'] >= 100): ?>
                                <span class="badge warning"><i class="fas fa-star"></i> <?php echo t('century_club'); ?></span>
                            <?php endif; ?>
                            <?php if ($on_time_percentage >= 90): ?>
                                <span class="badge success"><i class="fas fa-clock"></i> <?php echo t('punctual_pro'); ?></span>
                            <?php endif; ?>
                            <?php if ($delivery_person['completed_deliveries'] >= 50): ?>
                                <span class="badge info"><i class="fas fa-trophy"></i> <?php echo t('top_performer'); ?></span>
                            <?php endif; ?>
                            <?php if ($delivery_person['status'] === 'active'): ?>
                                <span class="badge primary"><i class="fas fa-check"></i> <?php echo t('active_member'); ?></span>
                            <?php endif; ?>
                            <?php if (empty($delivery_person['completed_deliveries'])): ?>
                                <p style="color: var(--text-secondary);"><?php echo t('complete_more_deliveries'); ?></p>
                            <?php endif; ?>
                        </div>
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

        // Tab switching
        function switchTab(tabName) {
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            event.target.closest('.tab-btn').classList.add('active');

            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
        }

        // Theme Management
        function changeTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeIndicator(theme);
        }

        function updateThemeIndicator(theme) {
            const indicator = document.getElementById('themeIndicator');
            const text = document.getElementById('themeText');
            const icon = indicator.querySelector('i');
            
            const themeNames = {
                'dark': '<?php echo t('dark'); ?>',
                'light': '<?php echo t('light'); ?>',
                'auto': '<?php echo t('auto'); ?>'
            };
            
            if (theme === 'dark') {
                icon.className = 'fas fa-moon';
                text.textContent = themeNames['dark'];
            } else if (theme === 'light') {
                icon.className = 'fas fa-sun';
                text.textContent = themeNames['light'];
            } else {
                icon.className = 'fas fa-circle-half-stroke';
                text.textContent = themeNames['auto'];
            }
        }

        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || '<?php echo $delivery_person['theme_preference'] ?? 'light'; ?>';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.getElementById('themeSelect').value = savedTheme;
            updateThemeIndicator(savedTheme);
        });

        // Profile picture preview and upload
        function previewAndUpload(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('error', '<?php echo t('file_size_error'); ?>');
                    input.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification('error', '<?php echo t('invalid_file_type'); ?>');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImage').src = e.target.result;
                    
                    if (confirm('<?php echo t('upload_confirm'); ?>')) {
                        const btn = document.querySelector('.camera-btn');
                        const originalHTML = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="color: rgb(73, 57, 113);"></i>';
                        btn.disabled = true;
                        
                        document.getElementById('profilePictureForm').submit();
                    } else {
                        input.value = '';
                        location.reload();
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // Show notification
        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                border-radius: 12px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                font-weight: 600;
                font-size: 1rem;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" style="font-size: 20px;"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>