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

$db = Database::getInstance();
$successMessage = '';
$errorMessage = '';

// Handle testimonial submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
    $customerName = sanitizeInput($_POST['customer_name'] ?? '');
    $customerEmail = sanitizeInput($_POST['customer_email'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $testimonialText = sanitizeInput($_POST['testimonial_text'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($customerName) || strlen($customerName) < 2) {
        $errors[] = "Name must be at least 2 characters";
    }
    
    if (!isValidEmail($customerEmail)) {
        $errors[] = "Invalid email address";
    }
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Rating must be between 1 and 5";
    }
    
    if (empty($testimonialText) || strlen($testimonialText) < 10) {
        $errors[] = "Testimonial must be at least 10 characters";
    }
    
    if (empty($errors)) {
        try {
            // Insert testimonial (requires admin approval by default)
            $stmt = $db->prepare("
                INSERT INTO testimonials 
                (user_id, customer_name, customer_email, rating, testimonial_text, is_approved, is_featured) 
                VALUES (?, ?, ?, ?, ?, 0, 0)
            ");
            
            if ($stmt->execute([
                $userId,
                $customerName,
                $customerEmail,
                $rating,
                $testimonialText
            ])) {
                // Log activity
                logUserActivity($userId, 'testimonial_submitted', 'Submitted a testimonial', getUserIP());
                
                $successMessage = "Thank you for your testimonial! It will be reviewed and published soon.";
                
                // Send JSON response for AJAX
                if (isAjaxRequest()) {
                    jsonResponse(true, $successMessage);
                }
            } else {
                $errorMessage = "Error submitting testimonial. Please try again.";
            }
            
        } catch (Exception $e) {
            $errorMessage = "Error submitting testimonial. Please try again.";
            error_log("Testimonial submission error: " . $e->getMessage());
            
            // Send JSON response for AJAX
            if (isAjaxRequest()) {
                jsonResponse(false, $errorMessage);
            }
        }
    } else {
        $errorMessage = implode('<br>', $errors);
        
        // Send JSON response for AJAX
        if (isAjaxRequest()) {
            jsonResponse(false, $errorMessage);
        }
    }
}

// Get user's testimonials
$stmt = $db->prepare("
    SELECT * FROM testimonials 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$myTestimonials = $stmt->fetchAll();

$theme = $userData['theme_preference'] ?? 'dark';
$dashboardStats = $user->getDashboardStats($userId);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Testimonials - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard-style.css" rel="stylesheet">
    <link href="assets/css/testimonials-style.css" rel="stylesheet">
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
                        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                        <li><a href="orders.php"><i class="fas fa-shopping-bag"></i><span>Orders</span><?php if ($dashboardStats['total_orders'] > 0): ?><span class="badge"><?= $dashboardStats['total_orders'] ?></span><?php endif; ?></a></li>
                        <li><a href="wishlist.php"><i class="fas fa-heart"></i><span>Wishlist</span><?php if ($dashboardStats['wishlist_count'] > 0): ?><span class="badge"><?= $dashboardStats['wishlist_count'] ?></span><?php endif; ?></a></li>
                        <li class="active"><a href="testimonials.php"><i class="fas fa-star"></i><span>Testimonials</span></a></li>
                        <li><a href="addresses.php"><i class="fas fa-map-marker-alt"></i><span>Addresses</span></a></li>
                        <li><a href="profile-settings.php"><i class="fas fa-user-edit"></i><span>Profile</span></a></li>
                        <li><a href="security.php"><i class="fas fa-shield-alt"></i><span>Security</span></a></li>
                        <li><a href="notifications.php"><i class="fas fa-bell"></i><span>Notifications</span></a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 main-content">
                <!-- Page Header -->
                <div class="testimonial-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="page-title">
                                <i class="fas fa-star me-2"></i>Share Your Experience
                            </h1>
                            <p class="page-subtitle">Write testimonials and help others make better decisions</p>
                        </div>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= $successMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $errorMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Testimonial Form -->
                <div class="testimonial-form-card">
                    <h4 class="mb-4">
                        <i class="fas fa-pencil-alt me-2"></i>Write a Testimonial
                    </h4>
                    
                    <form id="testimonialForm" method="POST">
                        <input type="hidden" name="submit_testimonial" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Your Name *</label>
                                <input type="text" class="form-control" name="customer_name" 
                                       value="<?= htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="customer_email" 
                                       value="<?= htmlspecialchars($userData['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Rating *</label>
                            <div class="rating-input-large" id="ratingInput">
                                <input type="radio" name="rating" value="5" id="star5" checked hidden>
                                <label for="star5" class="star-label"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="4" id="star4" hidden>
                                <label for="star4" class="star-label"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="3" id="star3" hidden>
                                <label for="star3" class="star-label"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="2" id="star2" hidden>
                                <label for="star2" class="star-label"><i class="fas fa-star"></i></label>
                                
                                <input type="radio" name="rating" value="1" id="star1" hidden>
                                <label for="star1" class="star-label"><i class="fas fa-star"></i></label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Your Testimonial *</label>
                            <textarea class="form-control" name="testimonial_text" rows="5" 
                                placeholder="Share your experience with FutureMart..." required></textarea>
                            <div class="form-text">Minimum 10 characters</div>
                        </div>
                        
                        <div id="testimonialMessage"></div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Submit Testimonial
                        </button>
                    </form>
                </div>

                <!-- My Testimonials -->
                <div class="my-testimonials-section">
                    <h4 class="mb-4">
                        <i class="fas fa-list me-2"></i>My Testimonials
                    </h4>
                    
                    <div id="myTestimonialsList">
                        <?php if (empty($myTestimonials)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>You haven't submitted any testimonials yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($myTestimonials as $testimonial): ?>
                                <div class="testimonial-item">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <div class="mb-2">
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                    <i class="fas fa-star" style="color: <?= $i < $testimonial['rating'] ? '#fbbf24' : '#d1d5db' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= timeAgo($testimonial['created_at']) ?>
                                            </small>
                                        </div>
                                        <span class="testimonial-status <?= $testimonial['is_approved'] ? 'status-approved' : 'status-pending' ?>">
                                            <i class="fas <?= $testimonial['is_approved'] ? 'fa-check-circle' : 'fa-clock' ?> me-1"></i>
                                            <?= $testimonial['is_approved'] ? 'Approved' : 'Pending Review' ?>
                                        </span>
                                    </div>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($testimonial['testimonial_text'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/gsap.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
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

    // Testimonials page script
    document.addEventListener('DOMContentLoaded', function() {
        initRatingInput();
        initFormSubmit();
        animateTestimonials();
    });

    function initRatingInput() {
        document.querySelectorAll('.star-label').forEach(label => {
            label.addEventListener('click', function() {
                const input = document.getElementById(this.getAttribute('for'));
                if (input) {
                    input.checked = true;
                    
                    // Animate star selection
                    gsap.from(this, {
                        duration: 0.3,
                        scale: 1.5,
                        ease: 'back.out(2)'
                    });
                }
            });
        });
    }

    function initFormSubmit() {
        const form = document.getElementById('testimonialForm');
        if (!form) return;
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const messageDiv = document.getElementById('testimonialMessage');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            if (!submitBtn) return;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            try {
                const response = await fetch('testimonials.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                
                // Check if response contains success message
                if (text.includes('Thank you for your testimonial')) {
                    messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Thank you! Your testimonial will be reviewed and published soon.</div>';
                    
                    // Reset form
                    form.reset();
                    document.getElementById('star5').checked = true;
                    
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error submitting testimonial. Please try again.</div>';
                }
                
            } catch (error) {
                console.error('Error:', error);
                messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error submitting testimonial. Please try again.</div>';
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Testimonial';
            }
        });
    }

    function animateTestimonials() {
        // Animate testimonial items
        gsap.from('.testimonial-item', {
            duration: 0.6,
            y: 30,
            opacity: 0,
            stagger: 0.1,
            ease: 'power2.out'
        });
        
        // Animate form card
        gsap.from('.testimonial-form-card', {
            duration: 0.8,
            y: 30,
            opacity: 0,
            ease: 'power2.out',
            delay: 0.2
        });
    }
    </script>
</body>
</html>