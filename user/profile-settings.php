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
    <link href="assets/css/personal-settings-style.css" rel="stylesheet">
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
                            <li>
                                <hr class="dropdown-divider">
                            </li>
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
                        <li class="active">
                            <a href="profile-settings.php">
                                <i class="fas fa-user-edit"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <li>
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
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="page-title">
                                <i class="fas fa-user-edit me-2"></i>Profile Settings
                            </h1>
                            <p class="page-subtitle">Manage your personal information and preferences</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Profile Picture Section -->
                    <div class="col-lg-4 mb-4">
                        <div class="settings-card profile-picture-card">
                            <h5 class="card-section-title">
                                <i class="fas fa-camera me-2"></i>Profile Picture
                            </h5>
                            <div class="profile-upload-area">
                                <div class="current-avatar" id="currentAvatar">
                                    <?php if (!empty($userData['avatar'])): ?>
                                        <img src="../uploads/avatars/<?= htmlspecialchars($userData['avatar']) ?>"
                                            alt="Profile"
                                            id="avatarPreview"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="avatar-placeholder" style="display:none;">
                                            <?= strtoupper(substr($userData['first_name'], 0, 1)) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($userData['first_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="avatar-overlay">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                </div>
                                <input type="file" id="avatarInput" accept="image/*" hidden>
                                <button class="btn btn-primary btn-upload" onclick="document.getElementById('avatarInput').click()">
                                    <i class="fas fa-upload me-2"></i>Upload Photo
                                </button>
                                <?php if (!empty($userData['avatar'])): ?>
                                    <button class="btn btn-outline-danger btn-remove mt-2" onclick="removeAvatar()">
                                        <i class="fas fa-trash me-2"></i>Remove Photo
                                    </button>
                                <?php endif; ?>
                                <p class="upload-hint">JPG, PNG or GIF. Max 5MB</p>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="settings-card mt-4">
                            <h5 class="card-section-title">
                                <i class="fas fa-chart-line me-2"></i>Account Stats
                            </h5>
                            <div class="quick-stat-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div>
                                    <small>Member Since</small>
                                    <strong><?= date('M d, Y', strtotime($userData['created_at'])) ?></strong>
                                </div>
                            </div>
                            <div class="quick-stat-item">
                                <i class="fas fa-shopping-bag"></i>
                                <div>
                                    <small>Total Orders</small>
                                    <strong><?= $dashboardStats['total_orders'] ?></strong>
                                </div>
                            </div>
                            <div class="quick-stat-item">
                                <i class="fas fa-star"></i>
                                <div>
                                    <small>Reward Points</small>
                                    <strong><?= number_format($dashboardStats['points_balance']) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="col-lg-8 mb-4">
                        <div class="settings-card">
                            <h5 class="card-section-title">
                                <i class="fas fa-user me-2"></i>Personal Information
                            </h5>
                            <form id="personalInfoForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name *</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-user"></i>
                                            <input type="text" class="form-control" name="first_name"
                                                value="<?= htmlspecialchars($userData['first_name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-user"></i>
                                            <input type="text" class="form-control" name="last_name"
                                                value="<?= htmlspecialchars($userData['last_name']) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($userData['email']) ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-phone"></i>
                                        <input type="tel" class="form-control" name="phone"
                                            value="<?= htmlspecialchars($userData['phone'] ?? '') ?>"
                                            placeholder="+94 123 456 789">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-calendar"></i>
                                            <input type="date" class="form-control" name="date_of_birth"
                                                value="<?= htmlspecialchars($userData['date_of_birth'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-venus-mars"></i>
                                            <select class="form-control" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="male" <?= ($userData['gender'] ?? '') == 'male' ? 'selected' : '' ?>>Male</option>
                                                <option value="female" <?= ($userData['gender'] ?? '') == 'female' ? 'selected' : '' ?>>Female</option>
                                                <option value="other" <?= ($userData['gender'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                                                <option value="prefer_not_to_say" <?= ($userData['gender'] ?? '') == 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Bio</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-align-left"></i>
                                        <textarea class="form-control" name="bio" rows="4"
                                            placeholder="Tell us about yourself..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div id="personalInfoMessage"></div>

                                <button type="submit" class="btn btn-primary btn-save">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </form>
                        </div>

                        <!-- Preferences Section -->
                        <div class="settings-card mt-4">
                            <h5 class="card-section-title">
                                <i class="fas fa-sliders-h me-2"></i>Preferences
                            </h5>
                            <form id="preferencesForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Language</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-language"></i>
                                            <select class="form-control" name="language">
                                                <option value="en_US" <?= ($userData['language'] ?? 'en_US') == 'en_US' ? 'selected' : '' ?>>English (US)</option>
                                                <option value="en_GB" <?= ($userData['language'] ?? '') == 'en_GB' ? 'selected' : '' ?>>English (UK)</option>
                                                <option value="es" <?= ($userData['language'] ?? '') == 'es' ? 'selected' : '' ?>>Español</option>
                                                <option value="fr" <?= ($userData['language'] ?? '') == 'fr' ? 'selected' : '' ?>>Français</option>
                                                <option value="de" <?= ($userData['language'] ?? '') == 'de' ? 'selected' : '' ?>>Deutsch</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Currency</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-dollar-sign"></i>
                                            <select class="form-control" name="currency_preference">
                                                <option value="USD" <?= ($userData['currency_preference'] ?? 'USD') == 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                                <option value="EUR" <?= ($userData['currency_preference'] ?? '') == 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                                <option value="GBP" <?= ($userData['currency_preference'] ?? '') == 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                                <option value="JPY" <?= ($userData['currency_preference'] ?? '') == 'JPY' ? 'selected' : '' ?>>JPY (¥)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date Format</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-calendar-alt"></i>
                                            <select class="form-control" name="date_format">
                                                <option value="MM/DD/YYYY" <?= ($userData['date_format'] ?? 'MM/DD/YYYY') == 'MM/DD/YYYY' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                                                <option value="DD/MM/YYYY" <?= ($userData['date_format'] ?? '') == 'DD/MM/YYYY' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                                                <option value="YYYY-MM-DD" <?= ($userData['date_format'] ?? '') == 'YYYY-MM-DD' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Time Format</label>
                                        <div class="input-group-custom">
                                            <i class="fas fa-clock"></i>
                                            <select class="form-control" name="time_format">
                                                <option value="12h" <?= ($userData['time_format'] ?? '12h') == '12h' ? 'selected' : '' ?>>12 Hour</option>
                                                <option value="24h" <?= ($userData['time_format'] ?? '') == '24h' ? 'selected' : '' ?>>24 Hour</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div id="preferencesMessage"></div>

                                <button type="submit" class="btn btn-primary btn-save">
                                    <i class="fas fa-save me-2"></i>Save Preferences
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
    <script src="assets/js/personal-settings.js"></script>
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
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
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
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
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
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
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
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
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
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

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

        // Upload Avatar to Server
async function uploadAvatar(file) {
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('action', 'upload_avatar');
    
    try {
        const response = await fetch('ajax_profile.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('personalInfoMessage', data.message, 'success');
            
            // Update current avatar display with correct path
            const avatarImg = document.getElementById('avatarPreview');
            if (avatarImg) {
                avatarImg.src = data.data.avatar_url; // This now includes ../uploads/avatars/
            }
            
            // Update navbar avatar
            updateNavbarAvatar(data.data.avatar_url);
            
            // Show remove button if not exists
            showRemoveButton();
            
            // Reload page after 1 second to update all avatars
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showMessage('personalInfoMessage', data.message, 'danger');
        }
    } catch (error) {
        console.error('Error uploading avatar:', error);
        showMessage('personalInfoMessage', 'Error uploading image. Please try again.', 'danger');
    }
}

// Update Navbar Avatar
function updateNavbarAvatar(avatarUrl) {
    // Update navbar profile image
    const navAvatarImg = document.querySelector('.user-profile .user-avatar');
    if (navAvatarImg) {
        navAvatarImg.src = avatarUrl;
        navAvatarImg.style.display = 'block';
        
        // Hide placeholder if exists
        const placeholder = document.querySelector('.user-profile .user-avatar-placeholder');
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }
}
    </script>
</body>

</html>