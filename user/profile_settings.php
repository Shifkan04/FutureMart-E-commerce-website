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

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$theme = $userData['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard-style.css" rel="stylesheet">
    <link href="assets/css/profile-style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
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
                    <li class="nav-item"><a class="nav-link" href="../categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="../about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Contact</a></li>
                </ul>
                
                <div class="d-flex align-items-center gap-3">
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-sun" id="themeIcon"></i>
                    </button>
                    
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
                            <li><a class="dropdown-item" href="profile-settings.php"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
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
            <!-- Sidebar -->
            <?php $dashboardStats = $user->getDashboardStats($userId); ?>
            <div class="col-lg-2 sidebar-wrapper">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                        <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Orders</span><?php if ($dashboardStats['total_orders'] > 0): ?><span class="badge"><?= $dashboardStats['total_orders'] ?></span><?php endif; ?></a></li>
                        <li><a href="wishlist.php"><i class="fas fa-heart"></i><span>Wishlist</span><?php if ($dashboardStats['wishlist_count'] > 0): ?><span class="badge"><?= $dashboardStats['wishlist_count'] ?></span><?php endif; ?></a></li>
                        <li><a href="testimonials.php"><i class="fas fa-star"></i><span>Testimonials</span></a></li>
                        <li><a href="addresses.php"><i class="fas fa-map-marker-alt"></i><span>Addresses</span></a></li>
                        <li class="active"><a href="profile-settings.php"><i class="fas fa-user-edit"></i><span>Profile</span></a></li>
                        <li><a href="security.php"><i class="fas fa-shield-alt"></i><span>Security</span></a></li>
                        <li><a href="notifications.php"><i class="fas fa-bell"></i><span>Notifications</span></a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <!-- Page Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="page-title">
                                <i class="fas fa-user-edit me-2"></i>Profile Settings
                            </h1>
                            <p class="page-subtitle">Manage your personal information</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Avatar Section -->
                    <div class="col-lg-4 mb-4">
                        <div class="avatar-card">
                            <h5 class="mb-3"><i class="fas fa-camera me-2"></i>Profile Picture</h5>
                            
                            <div class="avatar-preview" id="avatarPreview">
                                <?php if (!empty($userData['avatar'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($userData['avatar']) ?>" alt="Avatar" id="currentAvatar">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?= strtoupper(substr($userData['first_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="avatar-overlay" onclick="document.getElementById('avatarInput').click()">
                                    <i class="fas fa-camera"></i>
                                    <p>Change Photo</p>
                                </div>
                            </div>
                            
                            <input type="file" id="avatarInput" accept="image/*" style="display: none;" onchange="uploadAvatar()">
                            
                            <div class="avatar-info">
                                <p class="text-muted mb-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    JPG, PNG or GIF. Max size 5MB
                                </p>
                                <div id="avatarMessage"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <div class="col-lg-8 mb-4">
                        <div class="profile-form-card">
                            <h5 class="mb-4"><i class="fas fa-user me-2"></i>Personal Information</h5>
                            
                            <form id="profileForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($userData['first_name']) ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($userData['last_name']) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" value="<?= $userData['date_of_birth'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-control" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?= ($userData['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= ($userData['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                            <option value="other" <?= ($userData['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Bio</label>
                                    <textarea class="form-control" name="bio" rows="3" placeholder="Tell us about yourself..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Theme Preference</label>
                                    <select class="form-control" name="theme_preference">
                                        <option value="dark" <?= ($userData['theme_preference'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                                        <option value="light" <?= ($userData['theme_preference'] ?? 'dark') === 'light' ? 'selected' : '' ?>>Light Mode</option>
                                        <option value="auto" <?= ($userData['theme_preference'] ?? 'dark') === 'auto' ? 'selected' : '' ?>>Auto (System)</option>
                                    </select>
                                </div>
                                
                                <div id="profileMessage"></div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Changes
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
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/profile.js"></script>
</body>
</html>