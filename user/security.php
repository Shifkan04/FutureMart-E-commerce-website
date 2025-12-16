<?php
require_once '../config_user.php';
require_once '../User.php';

startSecureSession();

// Database connection
$pdo = Database::getInstance()->getConnection();

// Check login status FIRST
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

// Get cart count for logged-in user
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

$dashboardStats = $user->getDashboardStats($userId);
$theme = $userData['theme_preference'] ?? 'dark';

// Get security settings
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM security_settings WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId]);
$securitySettings = $stmt->fetch();

// Get recent login sessions
$stmt = $db->prepare("SELECT * FROM login_sessions WHERE user_id = ? ORDER BY login_time DESC LIMIT 5");
$stmt->execute([$userId]);
$loginSessions = $stmt->fetchAll();

// Get recent security events
$stmt = $db->prepare("
    SELECT * FROM user_activity_log 
    WHERE user_id = ? 
    AND activity_type IN ('password_change', 'login', 'logout', 'failed_login', '2fa_enabled', '2fa_disabled')
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$userId]);
$securityEvents = $stmt->fetchAll();

/**
 * Calculate dynamic security score based on user's security settings
 */
function calculateSecurityScore($userData, $securitySettings) {
    $score = 0;
    $maxScore = 4; // Total security checks
    
    // Check 1: Email Verified (25%)
    if (!empty($userData['email_verified_at'])) {
        $score++;
    }
    
    // Check 2: Two-Factor Authentication Enabled (25%)
    if ($securitySettings && $securitySettings['two_factor_enabled']) {
        $score++;
    }
    
    // Check 3: Password Changed Recently (25%)
    if ($securitySettings && $securitySettings['last_password_change']) {
        $score++;
    }
    
    // Check 4: Login Alerts Active (25%)
    if ($securitySettings && $securitySettings['login_alerts']) {
        $score++;
    }
    
    // Calculate percentage
    $percentage = ($score / $maxScore) * 100;
    
    return [
        'score' => $score,
        'percentage' => round($percentage),
        'status' => getSecurityStatus($percentage),
        'checks' => [
            'email_verified' => !empty($userData['email_verified_at']),
            'two_factor_enabled' => $securitySettings && $securitySettings['two_factor_enabled'],
            'password_changed' => $securitySettings && $securitySettings['last_password_change'],
            'login_alerts' => $securitySettings && $securitySettings['login_alerts']
        ]
    ];
}

/**
 * Get security status text based on percentage
 */
function getSecurityStatus($percentage) {
    if ($percentage >= 100) {
        return 'Excellent Security';
    } elseif ($percentage >= 75) {
        return 'Good Security';
    } elseif ($percentage >= 50) {
        return 'Moderate Security';
    } else {
        return 'Weak Security';
    }
}

// Calculate security score
$securityScore = calculateSecurityScore($userData, $securitySettings);

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard-style.css" rel="stylesheet">
    <link href="assets/css/security-style.css" rel="stylesheet">
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

                <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-sun" id="themeIcon"></i>
                    </button>

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
                        <li><hr class="dropdown-divider"></li>
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
                                <?php if ($dashboardStats['total_orders'] > 0): ?>
                                    <span class="badge"><?= $dashboardStats['total_orders'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="wishlist.php">
                                <i class="fas fa-heart"></i>
                                <span>Wishlist</span>
                                <?php if ($dashboardStats['wishlist_count'] > 0): ?>
                                    <span class="badge"><?= $dashboardStats['wishlist_count'] ?></span>
                                <?php endif; ?>
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
                        <li class="active">
                            <a href="security.php">
                                <i class="fas fa-shield-alt"></i>
                                <span>Security</span>
                            </a>
                        </li>
                        <li>
                            <a href="notifications.php">
                                <i class="fas fa-bell"></i>
                                <span>Notifications</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <!-- Page Header -->
                <div class="security-header">
                    <h1 class="page-title">
                        <i class="fas fa-shield-alt me-2"></i>Security Settings
                    </h1>
                    <p class="page-subtitle">Manage your account security and privacy</p>
                </div>

                <div class="row">
                    <!-- Security Status -->
                   <div class="col-lg-4 mb-4">
    <div class="security-card security-status-card">
        <h5 class="card-section-title">
            <i class="fas fa-shield-check me-2"></i>Security Status
        </h5>
        
        <div class="security-score">
            <div class="score-circle" id="securityScoreCircle">
                <svg width="150" height="150">
                    <circle cx="75" cy="75" r="65" class="score-bg"></circle>
                    <circle cx="75" cy="75" r="65" class="score-fill" id="scoreProgress"></circle>
                </svg>
                <div class="score-text">
                    <span class="score-value" id="securityScore"><?= $securityScore['percentage'] ?></span>
                    <span class="score-label">%</span>
                </div>
            </div>
            <p class="score-status" id="securityStatus"><?= $securityScore['status'] ?></p>
            <p class="text-muted small mt-2">
                <?= $securityScore['score'] ?> of 4 security measures enabled
            </p>
        </div>

        <div class="security-checklist">
            <div class="checklist-item <?= $securityScore['checks']['email_verified'] ? 'completed' : '' ?>">
                <i class="fas <?= $securityScore['checks']['email_verified'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                <span>Email Verified</span>
            </div>
            <div class="checklist-item <?= $securityScore['checks']['two_factor_enabled'] ? 'completed' : '' ?>">
                <i class="fas <?= $securityScore['checks']['two_factor_enabled'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                <span>Two-Factor Auth</span>
            </div>
            <div class="checklist-item <?= $securityScore['checks']['password_changed'] ? 'completed' : '' ?>">
                <i class="fas <?= $securityScore['checks']['password_changed'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                <span>Strong Password</span>
            </div>
            <div class="checklist-item <?= $securityScore['checks']['login_alerts'] ? 'completed' : '' ?>">
                <i class="fas <?= $securityScore['checks']['login_alerts'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                <span>Login Alerts Active</span>
            </div>
        </div>

        <?php if ($securityScore['percentage'] < 100): ?>
        <div class="security-recommendations mt-3">
            <h6 class="small mb-2"><i class="fas fa-lightbulb me-2"></i>Recommendations:</h6>
            <ul class="small text-muted mb-0">
                <?php if (!$securityScore['checks']['email_verified']): ?>
                <li>Verify your email address</li>
                <?php endif; ?>
                <?php if (!$securityScore['checks']['two_factor_enabled']): ?>
                <li>Enable two-factor authentication</li>
                <?php endif; ?>
                <?php if (!$securityScore['checks']['password_changed']): ?>
                <li>Update your password regularly</li>
                <?php endif; ?>
                <?php if (!$securityScore['checks']['login_alerts']): ?>
                <li>Enable login alerts</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="security-card mt-4">
        <h5 class="card-section-title">
            <i class="fas fa-bolt me-2"></i>Quick Actions
        </h5>
        
        <?php if (!$securityScore['checks']['two_factor_enabled']): ?>
        <button class="btn btn-primary w-100 mb-2" onclick="scrollToSection('twoFactor')">
            <i class="fas fa-mobile-alt me-2"></i>Enable 2FA Now
        </button>
        <?php endif; ?>
        
        <button class="btn btn-outline-primary w-100 mb-2" onclick="scrollToSection('changePassword')">
            <i class="fas fa-key me-2"></i>Change Password
        </button>
        
        <?php if (!$securityScore['checks']['login_alerts']): ?>
        <button class="btn btn-outline-primary w-100 mb-2" onclick="scrollToSection('securityPreferencesForm'); enableLoginAlerts()">
            <i class="fas fa-bell me-2"></i>Enable Login Alerts
        </button>
        <?php endif; ?>
        
        <button class="btn btn-outline-danger w-100" onclick="terminateAllSessions()">
            <i class="fas fa-sign-out-alt me-2"></i>Logout All Devices
        </button>
    </div>
</div>

                    <!-- Main Security Settings -->
                    <div class="col-lg-8 mb-4">
                        <!-- Change Password -->
                        <div class="security-card" id="changePassword">
                            <h5 class="card-section-title">
                                <i class="fas fa-key me-2"></i>Change Password
                            </h5>
                            
                            <?php if ($securitySettings && $securitySettings['last_password_change']): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Last changed: <?= timeAgo($securitySettings['last_password_change']) ?>
                                </div>
                            <?php endif; ?>

                            <form id="changePasswordForm">
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" class="form-control" name="current_password" 
                                               id="currentPassword" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" class="form-control" name="new_password" 
                                               id="newPassword" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength">
                                        <div class="strength-bar">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                        <span class="strength-text" id="strengthText">Password Strength</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" class="form-control" name="confirm_password" 
                                               id="confirmPassword" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div id="passwordMessage"></div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Password
                                </button>
                            </form>
                        </div>

                        <!-- Two-Factor Authentication -->
                        <div class="security-card mt-4" id="twoFactor">
                            <h5 class="card-section-title">
                                <i class="fas fa-mobile-alt me-2"></i>Two-Factor Authentication
                            </h5>

                            <div class="two-factor-status">
                                <?php if ($securitySettings && $securitySettings['two_factor_enabled']): ?>
                                    <div class="status-badge status-enabled">
                                        <i class="fas fa-check-circle me-2"></i>Enabled
                                    </div>
                                    <p class="mt-3">Two-factor authentication is currently enabled on your account. This adds an extra layer of security by requiring a verification code.</p>
                                    
                                    <button class="btn btn-outline-danger mt-3" onclick="disable2FA()">
                                        <i class="fas fa-times-circle me-2"></i>Disable 2FA
                                    </button>
                                <?php else: ?>
                                    <div class="status-badge status-disabled">
                                        <i class="fas fa-times-circle me-2"></i>Disabled
                                    </div>
                                    <p class="mt-3">Protect your account with an additional security layer. When enabled, you'll need to enter a verification code from your phone in addition to your password.</p>
                                    
                                    <button class="btn btn-primary mt-3" onclick="enable2FA()">
                                        <i class="fas fa-shield-alt me-2"></i>Enable 2FA
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div id="twoFactorMessage"></div>
                        </div>

                        <!-- Security Preferences -->
                        <div class="security-card mt-4">
                            <h5 class="card-section-title">
                                <i class="fas fa-cog me-2"></i>Security Preferences
                            </h5>

                            <form id="securityPreferencesForm">
                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h6><i class="fas fa-bell me-2"></i>Login Alerts</h6>
                                        <p>Get notified when someone logs into your account</p>
                                    </div>
                                    <div class="preference-toggle">
                                        <label class="switch">
                                            <input type="checkbox" name="login_alerts" 
                                                   <?= $securitySettings && $securitySettings['login_alerts'] ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h6><i class="fas fa-clock me-2"></i>Session Timeout</h6>
                                        <p>Automatically logout after inactivity</p>
                                    </div>
                                    <div class="preference-select">
                                        <select class="form-control" name="session_timeout">
                                            <option value="900" <?= $securitySettings && $securitySettings['session_timeout'] == 900 ? 'selected' : '' ?>>15 minutes</option>
                                            <option value="1800" <?= $securitySettings && $securitySettings['session_timeout'] == 1800 ? 'selected' : '' ?>>30 minutes</option>
                                            <option value="3600" <?= !$securitySettings || $securitySettings['session_timeout'] == 3600 ? 'selected' : '' ?>>1 hour</option>
                                            <option value="7200" <?= $securitySettings && $securitySettings['session_timeout'] == 7200 ? 'selected' : '' ?>>2 hours</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="preferencesMessage"></div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </form>
                        </div>

                        <!-- Active Sessions -->
                        <div class="security-card mt-4">
                            <h5 class="card-section-title">
                                <i class="fas fa-desktop me-2"></i>Active Sessions
                            </h5>

                            <div id="activeSessions">
                                <?php if (empty($loginSessions)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-desktop"></i>
                                        <p>No active sessions found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($loginSessions as $session): ?>
                                        <div class="session-item">
                                            <div class="session-icon">
                                                <i class="fas <?= strpos($session['device_type'], 'mobile') !== false ? 'fa-mobile-alt' : 'fa-desktop' ?>"></i>
                                            </div>
                                            <div class="session-details">
                                                <h6><?= htmlspecialchars($session['browser'] ?? 'Unknown Browser') ?> on <?= htmlspecialchars($session['device_type'] ?? 'Unknown Device') ?></h6>
                                                <p>
                                                    <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($session['location'] ?? $session['ip_address']) ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?= $session['is_active'] ? 'Active now' : 'Last seen: ' . timeAgo($session['logout_time'] ?? $session['login_time']) ?>
                                                </small>
                                            </div>
                                            <?php if ($session['is_active']): ?>
                                                <span class="session-badge active">Current</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Security Events -->
                        <div class="security-card mt-4">
                            <h5 class="card-section-title">
                                <i class="fas fa-history me-2"></i>Recent Security Events
                            </h5>

                            <div class="security-timeline">
                                <?php if (empty($securityEvents)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-shield-alt"></i>
                                        <p>No security events recorded</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($securityEvents as $event): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon <?= $event['activity_type'] ?>">
                                                <i class="fas <?= getEventIcon($event['activity_type']) ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6><?= htmlspecialchars($event['activity_description']) ?></h6>
                                                <p>
                                                    <i class="fas fa-clock me-2"></i><?= timeAgo($event['created_at']) ?>
                                                    <?php if ($event['ip_address']): ?>
                                                        <span class="ms-3"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($event['ip_address']) ?></span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2FA Setup Modal -->
    <div class="modal fade" id="twoFactorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-mobile-alt me-2"></i>Setup Two-Factor Authentication
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="twoFactorSetup">
                        <div class="text-center">
                            <div class="qr-code-container" id="qrCodeContainer">
                                <!-- QR Code will be generated here -->
                            </div>
                            <p class="mt-3">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
                            
                            <div class="secret-key mt-3">
                                <label>Or enter this key manually:</label>
                                <div class="key-display" id="secretKey">Loading...</div>
                            </div>

                            <form id="verify2FAForm" class="mt-4">
                                <label class="form-label">Enter verification code:</label>
                                <input type="text" class="form-control text-center" name="verification_code" 
                                       placeholder="000000" maxlength="6" required>
                                <div id="verify2FAMessage" class="mt-3"></div>
                                <button type="submit" class="btn btn-primary w-100 mt-3">
                                    <i class="fas fa-check me-2"></i>Verify & Enable
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/security.js"></script>
    <script>
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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

        function setupScrollAnimations() {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
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
        });
// Fallback QR Code checker
window.addEventListener('load', function() {
    if (typeof QRCode === 'undefined') {
        console.warn('Primary QRCode library failed, loading alternative...');
        
        // Load alternative QR Code library
        const script = document.createElement('script');
        script.src = 'https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js';
        script.onload = function() {
            console.log('Alternative QR Code library loaded successfully');
        };
        script.onerror = function() {
            console.error('Both QR Code libraries failed to load');
        };
        document.body.appendChild(script);
    } else {
        console.log('QRCode library loaded successfully');
    }
});


// Add this function to enable login alerts quickly
function enableLoginAlerts() {
    const checkbox = document.querySelector('input[name="login_alerts"]');
    if (checkbox && !checkbox.checked) {
        checkbox.checked = true;
        checkbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Highlight the preference
        const preferenceItem = checkbox.closest('.preference-item');
        if (preferenceItem) {
            preferenceItem.style.transition = 'all 0.3s ease';
            preferenceItem.style.background = 'rgba(99, 102, 241, 0.1)';
            preferenceItem.style.borderRadius = '10px';
            preferenceItem.style.padding = '15px';
            
            setTimeout(() => {
                preferenceItem.style.background = '';
                preferenceItem.style.padding = '';
            }, 2000);
        }
    }
}

// Update the security score animation to use dynamic value
document.addEventListener('DOMContentLoaded', function() {
    const scoreValue = parseInt(document.getElementById('securityScore').textContent);
    const circle = document.getElementById('scoreProgress');
    const circumference = 2 * Math.PI * 65;
    const offset = circumference - (scoreValue / 100) * circumference;

    circle.style.strokeDasharray = circumference;
    circle.style.strokeDashoffset = circumference;

    setTimeout(() => {
        circle.style.transition = 'stroke-dashoffset 2s ease';
        circle.style.strokeDashoffset = offset;
    }, 300);

    // Animate the number
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

    // Update status color based on score
    const statusEl = document.getElementById('securityStatus');
    if (scoreValue >= 100) {
        statusEl.style.color = '#10b981'; // Green
        circle.style.stroke = '#10b981';
    } else if (scoreValue >= 75) {
        statusEl.style.color = '#6366f1'; // Blue
        circle.style.stroke = '#6366f1';
    } else if (scoreValue >= 50) {
        statusEl.style.color = '#f59e0b'; // Orange
        circle.style.stroke = '#f59e0b';
    } else {
        statusEl.style.color = '#ef4444'; // Red
        circle.style.stroke = '#ef4444';
    }
});
</script>

</body>
</html>

<?php
function getEventIcon($type) {
    $icons = [
        'password_change' => 'fa-key',
        'login' => 'fa-sign-in-alt',
        'logout' => 'fa-sign-out-alt',
        'failed_login' => 'fa-exclamation-triangle',
        '2fa_enabled' => 'fa-shield-alt',
        '2fa_disabled' => 'fa-shield-alt'
    ];
    return $icons[$type] ?? 'fa-info-circle';
}
?>