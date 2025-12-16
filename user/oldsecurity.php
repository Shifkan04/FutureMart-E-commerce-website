<?php
require_once '../config_user.php';
require_once '../User.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user = new User();
$userId = $_SESSION['user_id'];
$userData = $user->getUserById($userId);
$theme = $userData['theme_preference'] ?? 'dark';
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
    <!-- Same Navbar as other pages -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-rocket me-2"></i>FutureMart</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../products.php">Shop</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <button class="theme-toggle" onclick="toggleTheme()"><i class="fas fa-sun" id="themeIcon"></i></button>
                    <div class="dropdown">
                        <button class="profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <div class="profile-circle">
                                <?php if (!empty($userData['avatar'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($userData['avatar']) ?>" alt="Profile">
                                <?php else: ?>
                                    <?= strtoupper(substr($userData['first_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <span class="profile-name"><?= htmlspecialchars($userData['first_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid dashboard-wrapper">
        <div class="row">
            <?php $dashboardStats = $user->getDashboardStats($userId); ?>
            <div class="col-lg-2 sidebar-wrapper">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                        <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Orders</span></a></li>
                        <li><a href="wishlist.php"><i class="fas fa-heart"></i><span>Wishlist</span></a></li>
                        <li><a href="testimonials.php"><i class="fas fa-star"></i><span>Testimonials</span></a></li>
                        <li><a href="addresses.php"><i class="fas fa-map-marker-alt"></i><span>Addresses</span></a></li>
                        <li><a href="profile-settings.php"><i class="fas fa-user-edit"></i><span>Profile</span></a></li>
                        <li class="active"><a href="security.php"><i class="fas fa-shield-alt"></i><span>Security</span></a></li>
                        <li><a href="notifications.php"><i class="fas fa-bell"></i><span>Notifications</span></a></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-10 main-content">
                <div class="security-header">
                    <h1 class="page-title"><i class="fas fa-shield-alt me-2"></i>Security Settings</h1>
                    <p class="page-subtitle">Manage your password and security preferences</p>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="security-card">
                            <h5 class="mb-4"><i class="fas fa-lock me-2"></i>Change Password</h5>
                            <form id="passwordForm">
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                    <div class="form-text">Minimum 8 characters</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <div id="passwordMessage"></div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Update Password
                                </button>
                            </form>
                        </div>

                        <div class="security-card mt-4">
                            <h5 class="mb-4"><i class="fas fa-mobile-alt me-2"></i>Login Activity</h5>
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon"><i class="fas fa-laptop"></i></div>
                                    <div class="activity-info">
                                        <h6>Windows PC - Chrome</h6>
                                        <p class="text-muted">Current session • IP: 192.168.1.1</p>
                                    </div>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="security-card">
                            <h5 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Security Status</h5>
                            <div class="security-status">
                                <div class="status-item">
                                    <span>Password Strength</span>
                                    <span class="badge bg-success">Strong</span>
                                </div>
                                <div class="status-item">
                                    <span>Email Verified</span>
                                    <span class="badge bg-success">Yes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/gsap.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/security.js"></script>
</body>
</html>