<?php
require_once '../config_user.php';
require_once '../User.php';

$successMessage = '';
$errorMessage = '';

// Check if user is logged in
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
    $customerName = sanitizeInput($_POST['customer_name'] ?? '');
    $customerEmail = sanitizeInput($_POST['customer_email'] ?? '', 'email');
    $rating = (int)($_POST['rating'] ?? 5);
    $testimonialText = sanitizeInput($_POST['testimonial_text'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($customerName) || strlen($customerName) < 2) {
        $errors[] = "Name must be at least 2 characters";
    }
    
    if (!validateInput($customerEmail, 'email')) {
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
            $userId = $isLoggedIn ? $_SESSION['user_id'] : null;
            
            // Insert testimonial (requires admin approval by default)
            $stmt = $pdo->prepare("
                INSERT INTO testimonials 
                (user_id, customer_name, customer_email, rating, testimonial_text, is_approved, is_featured) 
                VALUES (?, ?, ?, ?, ?, 0, 0)
            ");
            
            $stmt->execute([
                $userId,
                $customerName,
                $customerEmail,
                $rating,
                $testimonialText
            ]);
            
            $successMessage = "Thank you for your testimonial! It will be reviewed and published soon.";
            
        } catch (Exception $e) {
            $errorMessage = "Error submitting testimonial. Please try again.";
            error_log("Testimonial submission error: " . $e->getMessage());
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

// Get user data
$addresses = $user->getUserAddresses($userId);
$orders = $user->getUserOrders($userId, 10, 0);
$wishlist = $user->getUserWishlist($userId);
$notifications = $user->getNotificationPreferences($userId);
$theme = $userData['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Include the same theme variables and base styles from user.php */
        :root[data-theme="dark"] {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --navbar-bg: rgba(255, 255, 255, 0.95);
            --dropdown-bg: rgba(255, 255, 255, 0.95);
            --border-color: rgba(0, 0, 0, 0.1);
            --navbar-bg: rgba(15, 23, 42, 0.95);
            --dropdown-bg: linear-gradient(to right bottom, rgba(191, 244, 228, 0.31), rgba(155, 209, 245, 0.57));
            --border-color: rgba(255, 255, 255, 0.1);
        }

        :root[data-theme="light"] {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dark-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-light: #1e293b;
            --text-muted: #64748b;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-light);
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Navbar styles - same as user.php */
        .navbar {
            position: relative;
            z-index: 1100;
            background: var(--navbar-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link {
            color: var(--text-light) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--accent-color) !important;
            transform: translateY(-2px);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--gradient-1);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 241, 142, 0.3);
        }

        .dropdown-menu {
            position: absolute;
            z-index: 2000;
            background: var(--dropdown-bg);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            padding: 0.5rem 0;
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }

        .dropdown-menu a {
            color: var(--text-light);
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: var(--dropdown-bg);
            color: var(--text-light);
        }

        .theme-toggle {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 1rem;
        }

        .theme-toggle:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Profile specific styles */
        .profile-container {
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .profile-header {
            background: var(--gradient-1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="none"><defs><radialGradient id="g" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="800" cy="200" r="300" fill="url(%23g)"/><circle cx="200" cy="800" r="250" fill="url(%23g)"/></svg>');
            opacity: 0.3;
        }

        .profile-info {
            position: relative;
            z-index: 2;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin-bottom: 1rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .avatar-upload {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 35px;
            height: 35px;
            background: var(--secondary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid white;
        }

        .avatar-upload:hover {
            background: var(--primary-color);
            transform: scale(1.1);
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
        }

        .profile-email {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .stat-item {
            text-align: center;
            color: white;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .profile-content {
            display: flex;
            gap: 2rem;
        }

        .profile-sidebar {
            width: 280px;
            flex-shrink: 0;
        }

        .profile-main {
            flex: 1;
        }

        .sidebar-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .content-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            color: var(--text-light);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .btn-save {
            background: var(--gradient-2);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.3);
            color: white;
        }

        .main-content {
            background: var(--card-bg);
            color: var(--text-light);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            animation: fadeIn 0.5s ease;
        }

        /* Filter Tabs */
        .filter-tabs .nav-link {
            color: var(--text-light);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 0.5rem 1.2rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .filter-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        }

        .filter-tabs .nav-link:hover {
            /* background: var(--primary-color); */
            color: white;
            transform: translateY(-2px);
        }

        /* Order Cards */
        .order-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
        }

        /* Order Header */
        .order-header {
            padding: 1rem 1.5rem;
            background: rgba(99, 102, 241, 0.05);
            border-bottom: 1px solid var(--border-color);
        }

        .order-status {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
        }

        .status-processing {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary-color);
        }

        .status-shipped {
            background: rgba(6, 182, 212, 0.15);
            color: var(--accent-color);
        }

        .status-delivered {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success-color);
        }

        /* Items */
        .order-item {
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            object-fit: cover;
        }

        /* Tracking */
        .tracking-timeline {
            border-left: 2px solid var(--border-color);
            padding-left: 1rem;
        }

        .tracking-step {
            position: relative;
            margin-bottom: 1rem;
        }

        .tracking-step::before {
            content: '';
            position: absolute;
            left: -1.1rem;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--border-color);
        }

        .tracking-step.completed::before {
            background: var(--success-color);
        }

        .tracking-step .fw-bold {
            color: var(--text-light);
        }

        /* Buttons */
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-outline-danger:hover {
            background: var(--error-color);
            color: white;
        }

        /* Animations */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .review-form {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem;
            background: rgba(99, 102, 241, 0.05);
        }

        .rating-stars .star {
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .rating-stars .star:hover,
        .rating-stars .star.selected {
            color: var(--warning-color);
        }

        textarea.form-control-sm {
            resize: none;
            font-size: 0.9rem;
        }

        /* MAIN CONTENT */
.main-content { 
    padding: 20px;
    min-height: 100vh;
    background:  var(--card-bg);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 30px;
    position: relative;
}

.page-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 0;
    width: 60px;
    height: 4px;
    background: var(--primary-color);
    border-radius: 2px;
}

/* HEADER */
.wishlist-header {
    background:  var(--gradient-1);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(108, 99, 255, 0.3);
}

/* FILTERS */
.wishlist-filters {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.form-select {
    background: var(--card-bg);
    color: var(--text-light);
}

.filter-btn {
    background: var(--card-bg);
    border: 1px solid #dee2e6;
    color: #6c757d;
    padding: 8px 16px;
    border-radius: 20px;
    margin: 5px;
    transition: all 0.3s ease;
}

.filter-btn:hover, .filter-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* WISHLIST CARD */
.wishlist-card {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.wishlist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.product-image {
    width: 100%;
    height: 250px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 15px;
}

.product-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: var(--primary-color);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.remove-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #dc3545;
    border: none;
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    opacity: 0;
}

.wishlist-card:hover .remove-btn {
    opacity: 1;
}

.remove-btn:hover {
    background: #c82333;
    transform: scale(1.1);
}

.product-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
}

.product-description {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 15px;
    line-height: 1.4;
}

.product-price {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.product-rating {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.rating-stars {
    color: #ffc107;
    margin-right: 8px;
}

.rating-text {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.add-to-cart-btn {
    background: var(--primary-color);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.add-to-cart-btn:hover {
    background: #5a52d8;
    transform: translateY(-2px);
}

/* EMPTY WISHLIST */
.empty-wishlist {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 20px;
}

/* RESPONSIVE DESIGN */
@media (max-width: 991px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    .wishlist-header {
        padding: 20px;
        text-align: center;
    }

    .wishlist-header h2 {
        font-size: 1.8rem;
    }

    .wishlist-header p {
        font-size: 0.95rem;
    }

    .wishlist-filters .row {
        flex-direction: column;
        text-align: center;
    }

    .wishlist-filters .col-md-6 {
        margin-bottom: 10px;
    }

    .filter-btn {
        padding: 6px 12px;
        font-size: 0.85rem;
    }

    .wishlist-card {
        padding: 15px;
    }

    .product-image {
        height: 200px;
    }
}

@media (max-width: 768px) {
    .main-content, .footer {
        margin-left: 0;
    }

    .wishlist-header {
        padding: 15px;
        border-radius: 10px;
    }

    .wishlist-header h2 {
        font-size: 1.6rem;
    }

    .wishlist-filters {
        padding: 15px;
    }

    .wishlist-card {
        margin-bottom: 15px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    }

    .add-to-cart-btn {
        font-size: 0.9rem;
        padding: 8px 15px;
    }

    .footer {
        padding: 30px 15px 15px;
        text-align: center;
    }

    .footer .social-icons a {
        width: 35px;
        height: 35px;
        line-height: 35px;
    }
}

@media (max-width: 576px) {
    .wishlist-header h2 {
        font-size: 1.4rem;
    }

    .product-image {
        height: 180px;
    }

    .product-description {
        display: none; /* Hide on small screens for compact look */
    }

    .wishlist-filters {
        text-align: center;
    }

    .filter-btn {
        margin: 4px;
        font-size: 0.8rem;
    }
}

 .rating-input {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        gap: 5px;
    }
    
    .rating-input input[type="radio"] {
        display: none;
    }
    
    .rating-input label {
        cursor: pointer;
        font-size: 2rem;
        color: #d1d5db;
        transition: color 0.2s;
    }
    
    .rating-input label:hover,
    .rating-input label:hover ~ label,
    .rating-input input[type="radio"]:checked ~ label {
        color: #fbbf24;
    }
    
    .form-control {
        background: rgba(99, 102, 241, 0.1);
        color: var(--text-light);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 0.75rem 1rem;
    }
    
    .form-control:focus {
        background: rgba(99, 102, 241, 0.15);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        color: var(--text-light);
    }
    
    .form-label {
        color: var(--text-light);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    
    /* Light mode styles */
    body.light-mode .form-control {
        background: #ffffff;
        color: #1e293b;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    body.light-mode .form-control:focus {
        background: #ffffff;
        color: #1e293b;
    }
    
    body.light-mode .form-label {
        color: #1e293b;
    }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content .text-muted {
            color: var(--text-muted) !important;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            margin-bottom: 1rem;
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error-color);
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: var(--text-muted);
            border-radius: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-switch.active {
            background: var(--primary-color);
        }

        .toggle-switch::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-switch.active::before {
            left: 27px;
        }

        /* Light theme adjustments */
        :root[data-theme="light"] .form-control {
            background: rgba(0, 0, 0, 0.05);
        }

        :root[data-theme="light"] .form-control:focus {
            background: rgba(0, 0, 0, 0.1);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .profile-content {
                flex-direction: column;
            }

            .profile-sidebar {
                width: 100%;
            }

            .profile-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
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
                        <a class="nav-link" href="../Categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center">
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-sun" id="themeIcon"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <span id="userName"><?= htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="user.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-auto">
                    <div class="profile-avatar" onclick="uploadAvatar()">
                        <?php if ($userData['avatar']): ?>
                            <img src="<?= AVATAR_PATH . $userData['avatar'] ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <i class="fas fa-user" id="avatarIcon"></i>
                        <?php endif; ?>
                        <div class="avatar-upload ">
                            <i class="fas fa-camera"></i>
                        </div>
                        <input type="file" id="avatarInput" style="display: none;" accept="image/*">
                    </div>
                </div>
                <div class="col-md">
                    <div class="profile-info">
                        <h1 class="profile-name" id="profileName"><?= htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']) ?></h1>
                        <p class="profile-email" id="profileEmail"><?= htmlspecialchars($userData['email']) ?></p>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= count($orders) ?></span>
                                <span class="stat-label">Orders</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= count($wishlist) ?></span>
                                <span class="stat-label">Wishlist</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">$<?= number_format(array_sum(array_column($orders, 'total_amount')), 0) ?></span>
                                <span class="stat-label">Total Spent</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $userData['loyalty_tier'] ?? 'Bronze' ?></span>
                                <span class="stat-label">Status</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="sidebar-card">
                    <ul class="sidebar-menu">
                        <li><a href="#" class="menu-link active" data-tab="overview"><i class="fas fa-tachometer-alt"></i>Overview</a></li>
                        <li><a href="#" class="menu-link" data-tab="orders"><i class="fas fa-box"></i>Order History</a></li>
                        <li><a href="#" class="menu-link" data-tab="wishlist"><i class="fas fa-heart"></i>Wishlist</a></li>
                        <li><a href="#" class="menu-link" data-tab="Testimonial"><i class="fas fa-quote-left"></i>Testimonial</a></li>
                        <li><a href="#" class="menu-link" data-tab="addresses"><i class="fas fa-map-marker-alt"></i>Addresses</a></li>
                        <li><a href="#" class="menu-link" data-tab="personal"><i class="fas fa-user-edit"></i>Personal Info</a></li>
                        <li><a href="#" class="menu-link" data-tab="security"><i class="fas fa-shield-alt"></i>Security</a></li>
                        <li><a href="#" class="menu-link" data-tab="notifications"><i class="fas fa-bell"></i>Notifications</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-main">
                <!-- Overview Tab -->
                <div class="tab-content active" id="overview">
                    <div class="content-card">
                        <h3 class="card-title"><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</h3>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="content-card">
                                    <h5><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                                    <div class="mt-3" id="recentActivity">
                                        <div class="loading"></div> Loading activity...
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <div class="content-card">
                                    <h5><i class="fas fa-gift me-2"></i>Rewards & Points</h5>
                                    <div class="mt-3">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span>Points Balance</span>
                                                <strong><?= number_format($userData['points_balance'] ?? 0) ?> pts</strong>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span>Loyalty Tier</span>
                                                <small class="text-muted"><?= $userData['loyalty_tier'] ?? 'Bronze' ?></small>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary btn-sm">View Rewards</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div class="tab-content" id="orders">
                    <div class="content-card">
                        <h3 class="card-title"><i class="fas fa-box me-2"></i>Order History</h3>
                        <div id="ordersContainer">
                            <div class="main-content">
                                <!-- Filter Tabs -->
                                <div class="filter-tabs mb-4">
                                    <ul class="nav nav-pills" id="orderTabs">
                                        <li class="nav-item"><a class="nav-link active" href="#" data-filter="all">All Orders</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#" data-filter="pending">Pending</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#" data-filter="processing">Processing</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#" data-filter="shipped">Shipped</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#" data-filter="delivered">Delivered</a></li>
                                    </ul>
                                </div>

                                <!-- Orders List -->
                                <div id="ordersList">
                                    <div class="loading"></div> Loading orders...
                                </div>

                                <!-- Load More Button -->
                                <div class="row mt-4">
                                    <div class="col-12 text-center">
                                        <button class="btn btn-outline-primary" id="loadMoreBtn">
                                            <i class="fas fa-plus me-2"></i>Load More Orders
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Wishlist Tab -->
                <div class="tab-content" id="wishlist">
                    <div class="content-card">
                        <h3 class="card-title"><i class="fas fa-heart me-2"></i>My Wishlist</h3>
                        <div id="wishlistContainer">
                            <!-- Main Content -->
                            <div class="main-content">
                                <!-- Wishlist Header -->
                                <div class="wishlist-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h2 class="mb-2">
                                                <i class="fas fa-heart me-2"></i>My Wishlist
                                            </h2>
                                            <p class="mb-0 opacity-75" id="wishlist-count">Loading...</p>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <button class="btn btn-light btn-lg" id="shareWishlistBtn">
                                                <i class="fas fa-share me-2"></i>Share Wishlist
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filters -->
                                <div class="wishlist-filters mt-4">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-2">Filter by Category:</h6>
                                            <button class="btn filter-btn active">All Items</button>
                                            <button class="btn filter-btn">Electronics</button>
                                            <button class="btn filter-btn">Fashion</button>
                                            <button class="btn filter-btn">Home & Garden</button>
                                            <button class="btn filter-btn">Sports</button>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <div class="d-flex justify-content-md-end align-items-center">
                                                <label class="me-2">Sort by:</label>
                                                <select class="form-select" style="width: auto;" id="sortSelect">
                                                    <option>Recently Added</option>
                                                    <option>Price: Low to High</option>
                                                    <option>Price: High to Low</option>
                                                    <option>Name A-Z</option>
                                                    <option>Rating</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Wishlist Items -->
                                <div class="row mt-4" id="wishlist-items">
                                    <p class="text-center text-muted">Loading wishlist...</p>
                                </div>

                                <!-- Empty Wishlist Message -->
                                <div class="empty-wishlist text-center" id="empty-wishlist" style="display:none;">
                                    <i class="fas fa-heart empty-icon"></i>
                                    <h4>Your wishlist is empty</h4>
                                    <p class="text-muted">Start adding items to your wishlist by clicking the heart icon on products you love!</p>
                                    <button class="btn btn-primary btn-lg">
                                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Add this form to your contact page or create a dedicated testimonials page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="content-card">
            <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%); border-radius: 20px; padding: 2rem; border: 1px solid rgba(255, 255, 255, 0.1);">
                <h3 class="mb-4"><i class="fas fa-star me-2"></i>Share Your Experience</h3>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="submit_testimonial" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Your Name *</label>
                            <input type="text" class="form-control" name="customer_name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="customer_email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Rating *</label>
                        <div class="rating-input">
                            <input type="radio" name="rating" value="5" id="star5" checked>
                            <label for="star5"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Your Testimonial *</label>
                        <textarea class="form-control" name="testimonial_text" rows="5" required 
                                  placeholder="Share your experience with FutureMart..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit Testimonial
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>



                <!-- Addresses Tab -->
                <div class="tab-content" id="addresses">
                    <div class="content-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="card-title"><i class="fas fa-map-marker-alt me-2"></i>My Addresses</h3>
                            <button class="btn btn-primary" onclick="showAddAddressModal()">
                                <i class="fas fa-plus me-2"></i>Add New Address
                            </button>
                        </div>
                        <div id="addressesContainer">
                            <div class="loading"></div> Loading addresses...
                        </div>
                    </div>
                </div>

                <!-- Personal Info Tab -->
                <div class="tab-content" id="personal">
                    <div class="content-card">
                        <h3 class="card-title"><i class="fas fa-user-edit me-2"></i>Personal Information</h3>

                        <form id="personalInfoForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($userData['first_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($userData['last_name']) ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" value="<?= $userData['date_of_birth'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Gender</label>
                                        <select class="form-control" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?= $userData['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= $userData['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                            <option value="other" <?= $userData['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                            <option value="prefer_not_to_say" <?= $userData['gender'] === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bio</label>
                                <textarea class="form-control" rows="3" name="bio" placeholder="Tell us about yourself..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Theme Preference</label>
                                <select class="form-control" name="theme_preference">
                                    <option value="dark" <?= $userData['theme_preference'] === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                                    <option value="light" <?= $userData['theme_preference'] === 'light' ? 'selected' : '' ?>>Light Mode</option>
                                    <option value="auto" <?= $userData['theme_preference'] === 'auto' ? 'selected' : '' ?>>Auto (System)</option>
                                </select>
                            </div>

                            <div id="personalInfoMessage"></div>

                            <button type="submit" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <div class="row">
                <h3 class="card-title"><i class="fas fa-shield-alt me-2"></i>Security Settings</h3>
                <div class="col-lg-8">
                    <div class="profile-card">
                        <h5><i class="fas fa-lock me-2"></i>Change Password</h5>
                        <div class="security-badge security-strong">
                            <i class="fas fa-check-circle me-1"></i>Strong Password
                        </div>
                        <form id="changePasswordForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" placeholder="Enter current password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" placeholder="Enter new password" required>
                                <div class="form-text">Password must be at least 8 characters with uppercase, lowercase, and numbers</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" placeholder="Confirm new password" required>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </form>
                    </div>

                    <div class="profile-card">
                        <h4><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication</h4>
                        <div class="preference-item">
                            <div class="preference-info">
                                <h6>SMS Authentication</h6>
                                <p>Receive verification codes via SMS</p>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sms-2fa" checked>
                            </div>
                        </div>
                        <div class="preference-item">
                            <div class="preference-info">
                                <h6>Email Authentication</h6>
                                <p>Receive verification codes via email</p>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="email-2fa">
                            </div>
                        </div>
                        <div class="preference-item">
                            <div class="preference-info">
                                <h6>Authenticator App</h6>
                                <p>Use Google Authenticator or similar apps</p>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="app-2fa">
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3">
                            <i class="fas fa-mobile-alt me-2"></i>Setup Authenticator App
                        </button>
                    </div>

                    <div class="profile-card">
                        <h4><i class="fas fa-devices me-2"></i>Login Sessions</h4>
                        <div class="activity-item">
                            <div class="activity-icon bg-success text-white">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <div class="activity-content flex-grow-1">
                                <h6>Windows PC - Chrome</h6>
                                <p>Current session • Los Angeles, CA • 192.168.1.1</p>
                            </div>
                            <span class="badge bg-success">Current</span>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon bg-primary text-white">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="activity-content flex-grow-1">
                                <h6>iPhone - Safari</h6>
                                <p>2 hours ago • Los Angeles, CA</p>
                            </div>
                            <button class="btn btn-sm btn-outline-danger">Revoke</button>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon bg-info text-white">
                                <i class="fas fa-tablet-alt"></i>
                            </div>
                            <div class="activity-content flex-grow-1">
                                <h6>iPad - Safari</h6>
                                <p>1 day ago • Los Angeles, CA</p>
                            </div>
                            <button class="btn btn-sm btn-outline-danger">Revoke</button>
                        </div>
                        <button class="btn btn-danger mt-3">
                            <i class="fas fa-sign-out-alt me-2"></i>Sign Out All Sessions
                        </button>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="profile-card">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Security Status</h5>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Password Strength</span>
                                <span class="text-success">Strong</span>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-success" style="width: 85%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Account Security</span>
                                <span class="text-primary">Good</span>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar" style="width: 75%"></div>
                            </div>
                        </div>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Email verified</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Phone verified</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>2FA enabled</li>
                            <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Backup codes not generated</li>
                        </ul>
                        <button class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-download me-2"></i>Generate Backup Codes
                        </button>
                    </div>

                    <div class="profile-card">
                        <h5><i class="fas fa-history me-2"></i>Recent Security Events</h5>
                        <div class="small">
                            <div class="mb-2 pb-2 border-bottom">
                                <div class="text-success">✓ Successful login</div>
                                <div class="text-muted">2 hours ago from Los Angeles, CA</div>
                            </div>
                            <div class="mb-2 pb-2 border-bottom">
                                <div class="text-primary">🔐 Password changed</div>
                                <div class="text-muted">5 days ago</div>
                            </div>
                            <div class="mb-2 pb-2">
                                <div class="text-info">📱 2FA enabled</div>
                                <div class="text-muted">1 week ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


                <!-- Notifications Tab -->
                <div class="tab-content" id="notifications">
                    <div class="content-card">
                        <h3 class="card-title"><i class="fas fa-bell me-2"></i>Notification Preferences</h3>

                        <form id="notificationsForm">
                            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div>
                                    <h6>SMS Notifications</h6>
                                    <small class="text-muted">Text messages for important updates</small>
                                </div>
                                <div class="toggle-switch <?= $notifications['sms_notifications'] ? 'active' : '' ?>" data-field="sms_notifications"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div>
                                    <h6>New Product Alerts</h6>
                                    <small class="text-muted">Get notified about new arrivals</small>
                                </div>
                                <div class="toggle-switch <?= $notifications['new_product_alerts'] ? 'active' : '' ?>" data-field="new_product_alerts"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6>Price Drop Alerts</h6>
                                    <small class="text-muted">Notifications when wishlist items go on sale</small>
                                </div>
                                <div class="toggle-switch <?= $notifications['price_drop_alerts'] ? 'active' : '' ?>" data-field="price_drop_alerts"></div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Save Preferences</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--text-light);">Add New Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <form id="addAddressForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Address Title</label>
                                    <input type="text" class="form-control" name="title" placeholder="e.g., Home, Office" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" placeholder="Phone number">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" name="address_line_1" placeholder="Street address" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 2 (Optional)</label>
                            <input type="text" class="form-control" name="address_line_2" placeholder="Apartment, suite, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" name="state" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" class="form-control" name="postal_code" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" value="United States">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
                            <label class="form-check-label" for="isDefault" style="color: var(--text-light);">
                                Set as default address
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAddress()">Save Address</button>
                </div>
            </div>
        </div>
    </div>

    <!-- update address modal -->
    <div class="modal fade" id="updateAddressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--text-light);">Update Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <form id="updateAddressForm">
                        <input type="hidden" name="address_id" id="updateAddressId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Address Title</label>
                                    <input type="text" class="form-control" name="title" id="updateAddressTitle" placeholder="e.g., Home, Office" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" id="updateAddressPhone" placeholder="Phone number">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" name="address_line_1" id="updateAddressLine1" placeholder="Street address" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 2 (Optional)</label>
                            <input type="text" class="form-control" name="address_line_2" id="updateAddressLine2" placeholder="Apartment, suite, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" id="updateAddressCity" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" name="state" id="updateAddressState" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" id="updateAddressPostalCode" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" id="updateAddressCountry" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateAddress()">Update Address</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Initialize theme
        const currentTheme = '<?= $theme ?>';
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);

        document.addEventListener('DOMContentLoaded', function() {
            initializeProfile();
            loadRecentActivity();
        });

        function initializeProfile() {
            // Tab switching
            document.querySelectorAll('.menu-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Remove active class from all menu items
                    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));

                    // Add active class to clicked item
                    this.classList.add('active');

                    // Hide all tab contents
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));

                    // Show selected tab
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');

                    // Load tab specific data
                    loadTabData(tabId);
                });
            });

            // Form submissions
            document.getElementById('personalInfoForm').addEventListener('submit', handlePersonalInfoSubmit);
            document.getElementById('changePasswordForm').addEventListener('submit', handlePasswordChange);
            document.getElementById('notificationsForm').addEventListener('submit', handleNotificationsUpdate);

            // Toggle switches
            document.querySelectorAll('.toggle-switch').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    this.classList.toggle('active');
                });
            });
        }

        function loadTabData(tabId) {
            switch (tabId) {
                case 'orders':
                    loadOrders();
                    break;
                case 'wishlist':
                    loadWishlist();
                    break;
                case 'addresses':
                    loadAddresses();
                    break;
            }
        }

        function loadRecentActivity() {
            fetch('../ajax.php?action=get_activity&limit=5')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRecentActivity(data.data);
                    }
                })
                .catch(error => console.error('Error loading activity:', error));
        }

        function displayRecentActivity(activities) {
            const container = document.getElementById('recentActivity');
            if (!activities || activities.length === 0) {
                container.innerHTML = '<p class="text-muted">No recent activity</p>';
                return;
            }

            const activityHTML = activities.map(activity => `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${activity.activity_description}</span>
                    <small class="text-muted">${timeAgo(activity.created_at)}</small>
                </div>
            `).join('');

            container.innerHTML = activityHTML;
        }

        function loadOrders() {
            const container = document.getElementById('ordersContainer');
            container.innerHTML = '<div class="loading"></div> Loading orders...';

            fetch('../ajax.php?action=get_orders')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrders(data.data);
                    } else {
                        container.innerHTML = '<p class="text-muted">No orders found</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading orders:', error);
                    container.innerHTML = '<p class="text-danger">Error loading orders</p>';
                });
        }

        let currentPage = 1;
        const limit = 5;

        // Load Orders
        async function loadOrders(page = 1, append = false) {
            const container = document.getElementById('ordersList');
            if (!append) container.innerHTML = '<div class="loading"></div> Loading orders...';

            try {
                const res = await fetch(`../ajax.php?action=get_orders&page=${page}&limit=${limit}`);
                const data = await res.json();

                if (!data.success || !data.data.length) {
                    if (!append) container.innerHTML = '<p class="text-muted">No orders found.</p>';
                    return;
                }

                const ordersHTML = data.data.map((order, index) => renderOrderCard(order, index + (page - 1) * limit)).join('');
                container.innerHTML = append ? container.innerHTML + ordersHTML : ordersHTML;
            } catch (err) {
                container.innerHTML = '<p class="text-danger">Failed to load orders.</p>';
                console.error('Fetch error:', err);
            }
        }

        // Render Order Card
        function renderOrderCard(order, index) {
            const id = index + 1;
            const status = order.status.toLowerCase();
            const total = parseFloat(order.total_amount).toFixed(2);
            const datePlaced = new Date(order.created_at).toLocaleDateString();

            return `
    <div class="card order-card mb-3" data-status="${status}">
        <div class="order-header">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <h6 class="mb-1">Order #${order.order_number}</h6>
                    <small class="text-muted">Placed on ${datePlaced}</small>
                </div>
                <div class="col-md-2"><span class="order-status status-${status}">${capitalize(order.status)}</span></div>
                <div class="col-md-2"><strong>$${total}</strong></div>
                <div class="col-md-3"><small class="text-muted">${order.tracking_number || ''}</small></div>
                <div class="col-md-2 text-end">
                    <button class="btn btn-outline-primary btn-sm" onclick="toggleOrderDetails(${id})">
                        <i class="fas fa-chevron-down" id="arrow-${id}"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body" id="details-${id}" style="display: none;">
            <div class="row">
                <div class="col-md-8">
                    <h6 class="mb-3">Order Items</h6>
                    ${(order.items || []).map(item => `
                        <div class="order-item mb-2">
                            <div class="row align-items-center">
                                <div class="col-md-1"><img src="${item.image}" class="product-img" alt="${item.name}"></div>
                                <div class="col-md-6"><h6>${item.name}</h6><small>${item.details}</small></div>
                                <div class="col-md-2">Qty: ${item.quantity}</div>
                                <div class="col-md-2"><strong>$${item.price}</strong></div>
                            </div>
                            ${order.status === 'delivered' ? renderReviewForm(item, order.id) : ''}
                        </div>
                    `).join('')}
                </div>

                <div class="col-md-4">
                    <h6 class="mb-3">Tracking</h6>
                    <div class="tracking-timeline">
                        ${(order.tracking || []).map(step => `
                            <div class="tracking-step completed">
                                <div class="fw-bold">${step.status}</div>
                                <small class="text-muted">${step.date_time}</small>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
    </div>`;
        }

        // Toggle Details
        function toggleOrderDetails(id) {
            const details = document.getElementById(`details-${id}`);
            const arrow = document.getElementById(`arrow-${id}`);
            const isOpen = details.style.display === 'block';
            details.style.display = isOpen ? 'none' : 'block';
            arrow.classList.toggle('fa-chevron-up', !isOpen);
            arrow.classList.toggle('fa-chevron-down', isOpen);
        }

        // Filter
        document.querySelectorAll('[data-filter]').forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                document.querySelectorAll('[data-filter]').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const filter = tab.dataset.filter;
                document.querySelectorAll('.order-card').forEach(order => {
                    order.style.display = (filter === 'all' || order.dataset.status === filter) ? 'block' : 'none';
                });
            });
        });

        // Load More
        document.getElementById('loadMoreBtn').addEventListener('click', async function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            this.disabled = true;
            setTimeout(() => {
                currentPage++;
                loadOrders(currentPage, true);
                this.innerHTML = '<i class="fas fa-plus me-2"></i>Load More Orders';
                this.disabled = false;
            }, 1000);
        });

        function capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // Init
        document.addEventListener('DOMContentLoaded', () => loadOrders());

        function getStatusColor(status) {
            const colors = {
                'delivered': 'success',
                'shipped': 'info',
                'processing': 'warning',
                'pending': 'secondary',
                'cancelled': 'danger'
            };
            return colors[status] || 'secondary';
        }

       document.addEventListener('DOMContentLoaded', () => {
    loadWishlist();

    document.getElementById('shareWishlistBtn').addEventListener('click', shareWishlist);
    document.getElementById('sortSelect').addEventListener('change', e => sortWishlistItems(e.target.value));

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterWishlistItems(this.textContent.toLowerCase());
        });
    });
});

// Fetch wishlist items from backend
function loadWishlist() {
    const container = document.getElementById('wishlist-items');
    container.innerHTML = '<p class="text-center text-muted">Loading wishlist...</p>';

    fetch('../ajax.php?action=get_wishlist')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                displayWishlist(data.data);
            } else {
                showEmptyWishlist();
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<p class="text-danger text-center">Error loading wishlist.</p>';
        });
}

// Display wishlist
function displayWishlist(items) {
    const container = document.getElementById('wishlist-items');
    const empty = document.getElementById('empty-wishlist');
    container.innerHTML = '';
    empty.style.display = 'none';

    items.forEach(item => {
        container.innerHTML += `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="wishlist-card">
                    <button class="remove-btn" onclick="removeFromWishlist(${item.product_id})">
                        <i class="fas fa-times"></i>
                    </button>
                    <img src="../${item.image || 'assets/img/no-image.png'}" alt="${item.name}" class="product-image">
                    <h5 class="product-title">${item.name}</h5>
                    <p class="product-description">${item.description || 'No description available.'}</p>
                    <div class="product-rating">
                        <div class="rating-stars">${'<i class="fas fa-star"></i>'.repeat(4)}<i class="far fa-star"></i></div>
                        <span class="rating-text">${item.rating || '4.5'} (200 reviews)</span>
                    </div>
                    <div class="product-price">$${parseFloat(item.price).toFixed(2)}</div>
                    <button class="btn add-to-cart-btn" onclick="addToCart('${item.name}')">
                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                    </button>
                </div>
            </div>
        `;
    });

    updateWishlistCount(items.length);
}

// Remove wishlist item (AJAX)
function removeFromWishlist(productId) {
    if (!confirm('Remove this item from wishlist?')) return;

    const formData = new FormData();
    formData.append('action', 'remove_from_wishlist');
    formData.append('product_id', productId);

    fetch('../ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification('Item removed from wishlist', 'success');
                loadWishlist();
            } else {
                showNotification(data.message, 'danger');
            }
        })
        .catch(() => showNotification('Error removing item', 'danger'));
}

// Utility functions
function updateWishlistCount(count) {
    document.getElementById('wishlist-count').textContent = `You have ${count} item${count > 1 ? 's' : ''} in your wishlist`;
}

function showEmptyWishlist() {
    document.getElementById('wishlist-items').innerHTML = '';
    document.getElementById('empty-wishlist').style.display = 'block';
    updateWishlistCount(0);
}

function addToCart(name) {
    showNotification(`${name} added to your cart!`, 'success');
}

function showNotification(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    alert.textContent = message;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

function shareWishlist() {
    if (navigator.share) {
        navigator.share({
            title: 'My Wishlist',
            text: 'Check out my wishlist!',
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href);
        showNotification('Wishlist link copied!', 'success');
    }
}

// Filter & Sort (same as your current logic)
function filterWishlistItems(category) { /* same logic */ }
function sortWishlistItems(sortBy) { /* same logic */ }

        function loadAddresses() {
            const container = document.getElementById('addressesContainer');
            container.innerHTML = '<div class="loading"></div> Loading addresses...';

            fetch('../ajax.php?action=get_addresses')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAddresses(data.data);
                    } else {
                        container.innerHTML = '<p class="text-muted">No addresses found</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading addresses:', error);
                    container.innerHTML = '<p class="text-danger">Error loading addresses</p>';
                });
        }

        function displayAddresses(addresses) {
            const container = document.getElementById('addressesContainer');
            if (!addresses || addresses.length === 0) {
                container.innerHTML = '<p class="text-muted">No addresses found</p>';
                return;
            }

            const addressesHTML = addresses.map(address => `
                <div class="border rounded p-3 mb-3 position-relative" style="border-color: var(--border-color) !important;">
                    ${address.is_default ? '<span class="badge bg-success position-absolute top-0 end-0 m-2">Default</span>' : ''}
                    <h6>${address.title}</h6>
                    <p class="mb-1">${address.address_line_1}</p>
                    ${address.address_line_2 ? `<p class="mb-1">${address.address_line_2}</p>` : ''}
                    <p class="mb-1">${address.city}, ${address.state} ${address.postal_code}</p>
                    <p class="mb-1">${address.country}</p>
                    ${address.phone ? `<p class="text-muted mb-2">Phone: ${address.phone}</p>` : ''}
                    <div class="mt-2">
                        ${!address.is_default ? `<button class="btn btn-outline-success btn-sm me-2" onclick="setDefaultAddress(${address.id})">Set Default</button>` : ''}
                        <button class="btn btn-outline-primary btn-sm me-2" onclick="editAddress(${address.id})">Edit</button>
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteAddress(${address.id})">Delete</button>
                    </div>
                </div>
            `).join('');

            container.innerHTML = addressesHTML;
        }

        function showAddAddressModal() {
            const modal = new bootstrap.Modal(document.getElementById('addAddressModal'));
            modal.show();
        }

        function saveAddress() {
            const form = document.getElementById('addAddressForm');
            const formData = new FormData(form);
            formData.append('action', 'add_address');

            fetch('../ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Address added successfully');
                        bootstrap.Modal.getInstance(document.getElementById('addAddressModal')).hide();
                        form.reset();
                        loadAddresses(); // Reload addresses
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error adding address', 'error');
                });
        }

        function editAddress(addressId) {
            fetch(`../ajax.php?action=get_address&address_id=${addressId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const address = data.data;
                        document.getElementById('updateAddressId').value = address.id;
                        document.getElementById('updateAddressTitle').value = address.title;
                        document.getElementById('updateAddressPhone').value = address.phone || '';
                        document.getElementById('updateAddressLine1').value = address.address_line_1;
                        document.getElementById('updateAddressLine2').value = address.address_line_2 || '';
                        document.getElementById('updateAddressCity').value = address.city;
                        document.getElementById('updateAddressState').value = address.state;
                        document.getElementById('updateAddressPostalCode').value = address.postal_code;
                        document.getElementById('updateAddressCountry').value = address.country;

                        const modal = new bootstrap.Modal(document.getElementById('updateAddressModal'));
                        modal.show();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error fetching address details', 'error');
                });
        }

        function deleteAddress(addressId) {
            if (!confirm('Are you sure you want to delete this address?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_address');
            formData.append('address_id', addressId);

            fetch('../ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Address deleted successfully');
                        loadAddresses(); // Reload addresses
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error deleting address', 'error');
                });
        }

        function handlePersonalInfoSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'update_profile');

            const messageDiv = document.getElementById('personalInfoMessage');
            messageDiv.innerHTML = '<div class="loading"></div> Updating profile...';

            fetch('../ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Profile updated successfully!</div>';
                        // Update UI elements
                        updateProfileDisplay(data.data);
                    } else {
                        messageDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>${data.message}</div>`;
                    }

                    setTimeout(() => {
                        messageDiv.innerHTML = '';
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error updating profile</div>';
                });
        }

        function updateProfileDisplay(userData) {
            document.getElementById('profileName').textContent = userData.first_name + ' ' + userData.last_name;
            document.getElementById('profileEmail').textContent = userData.email;
            document.getElementById('userName').textContent = userData.first_name + ' ' + userData.last_name;

            if (userData.theme_preference !== currentTheme) {
                document.documentElement.setAttribute('data-theme', userData.theme_preference);
                updateThemeIcon(userData.theme_preference);
            }
        }

        // PASSWORD CHANGE
document.getElementById('password-form').addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action','change_password');

    fetch('ajax.php',{
        method:'POST', body:formData
    })
    .then(r=>r.json())
    .then(res=>{
        alert(res.message);
        if(res.success) e.target.reset();
        loadSecurityData(); // refresh security status
    });
});

// TOGGLE 2FA
document.querySelectorAll('.form-check-input').forEach(el=>{
    el.addEventListener('change',()=>{
        const type = el.id.split('-')[0]; // sms/email/app
        const enabled = el.checked ? 1 : 0;

        fetch('ajax.php',{
            method:'POST',
            body: new URLSearchParams({
                action:'toggle_2fa',
                type:type,
                enabled:enabled
            })
        });
    });
});

// REVOKE SESSION
function revokeSession(sessionId){
    fetch('ajax.php',{
        method:'POST',
        body: new URLSearchParams({
            action:'revoke_session',
            session_id:sessionId
        })
    })
    .then(()=>loadSecurityData());
}

// LOAD SECURITY STATUS, EVENTS, SESSIONS
function loadSecurityData(){
    fetch('../ajax.php',{
        method:'POST',
        body: new URLSearchParams({action:'get_security_data'})
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success){
            const data = res.message;
            // Update security status progress bars
            document.querySelector('.progress-bar.password').style.width = data.securityStatus.passwordStrength+'%';
            document.querySelector('.progress-bar.account').style.width = data.securityStatus.accountScore+'%';

            // Update recent events
            const eventsDiv = document.querySelector('#recentEvents');
            eventsDiv.innerHTML = '';
            data.recentEvents.forEach(ev=>{
                eventsDiv.innerHTML += `<div class="mb-2 pb-2 border-bottom"><div>${ev.icon} ${ev.title}</div><div class="text-muted">${ev.time}</div></div>`;
            });

            // Update login sessions
            const sessionsDiv = document.querySelector('#sessionsList');
            sessionsDiv.innerHTML = '';
            data.sessions.forEach(s=>{
                sessionsDiv.innerHTML += `<div class="activity-item">
                    <div class="activity-icon ${s.current?'bg-success':'bg-primary'} text-white"><i class="${s.icon}"></i></div>
                    <div class="activity-content flex-grow-1">
                        <h6>${s.device} - ${s.browser}</h6>
                        <p>${s.lastActive} • ${s.location}</p>
                    </div>
                    ${s.current?'':`<button class="btn btn-sm btn-outline-danger" onclick="revokeSession('${s.id}')">Revoke</button>`}
                </div>`;
            });

            // Update 2FA checkboxes
            document.getElementById('sms-2fa').checked = data.twoFA.sms;
            document.getElementById('email-2fa').checked = data.twoFA.email;
            document.getElementById('app-2fa').checked = data.twoFA.app;
        }
    });
}

loadSecurityData(); // initial load

        function handleNotificationsUpdate(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('action', 'update_notifications');

            // Get toggle states
            document.querySelectorAll('.toggle-switch').forEach(toggle => {
                const field = toggle.getAttribute('data-field');
                formData.append(field, toggle.classList.contains('active') ? '1' : '0');
            });

            fetch('../ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Notification preferences saved');
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error saving preferences', 'error');
                });
        }

        function uploadAvatar() {
            document.getElementById('avatarInput').click();
        }

        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('avatar', file);

            // Show loading state
            const avatarIcon = document.getElementById('avatarIcon');
            if (avatarIcon) {
                avatarIcon.className = 'fas fa-spinner fa-spin';
            }

            fetch('../ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Avatar updated successfully!');
                        // Update avatar display
                        location.reload(); // Simple solution - reload page to show new avatar
                    } else {
                        showNotification(data.message, 'error');
                        if (avatarIcon) {
                            avatarIcon.className = 'fas fa-user';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error uploading avatar', 'error');
                    if (avatarIcon) {
                        avatarIcon.className = 'fas fa-user';
                    }
                });
        });

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            updateThemeIcon(newTheme);

            // Update form value if on personal tab
            const themeSelect = document.querySelector('[name="theme_preference"]');
            if (themeSelect) {
                themeSelect.value = newTheme;
            }
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (minutes < 60) return minutes <= 1 ? 'just now' : `${minutes} minutes ago`;
            if (hours < 24) return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
            if (days < 7) return days === 1 ? '1 day ago' : `${days} days ago`;

            return date.toLocaleDateString();
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

            notification.innerHTML = `
                <div class="alert ${alertClass} position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        function renderReviewForm(item, orderId) {
            return `
   <div class="review-form mt-2" data-product-id="${item.product_id}" data-order-id="${orderId}">
        <div class="rating-stars mb-2">
            ${[1,2,3,4,5].map(num => `
                <i class="fas fa-star star" data-value="${num}"></i>
            `).join('')}
        </div>
        <textarea class="form-control form-control-sm mb-2" placeholder="Write your review..."></textarea>
        <button class="btn btn-sm btn-primary submit-review">Submit Review</button>
        <div class="review-msg mt-1 small text-success" style="display:none;"></div>
    </div>`;
        }


        // Star rating + submit handler
        document.addEventListener('click', async (e) => {
            if (e.target.classList.contains('star')) {
                const stars = e.target.parentNode.querySelectorAll('.star');
                const rating = e.target.dataset.value;
                stars.forEach(s => s.classList.toggle('selected', s.dataset.value <= rating));
                e.target.parentNode.dataset.rating = rating;
            }

            if (e.target.classList.contains('submit-review')) {
                const form = e.target.closest('.review-form');
                if (!form) return;

                const productId = form.dataset.productId;
                const orderId = form.dataset.orderId;
                const rating = form.querySelector('.rating-stars').dataset.rating || 0;
                const comment = form.querySelector('textarea').value.trim();

                console.log('🟣 Review Clicked:', {
                    productId,
                    orderId,
                    rating,
                    comment
                });

                if (rating == 0) {
                    alert('Please select a star rating.');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'submit_review');
                formData.append('product_id', productId);
                formData.append('order_id', orderId);
                formData.append('rating', rating);
                formData.append('comment', comment);

                try {
                    const res = await fetch('../ajax.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        form.querySelector('.review-msg').textContent = '✅ Review added!';
                        form.querySelector('.review-msg').style.display = 'block';
                        form.querySelector('textarea').value = '';
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (err) {
                    console.error('Review Error:', err);
                }
            }
        });


        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>

</html>