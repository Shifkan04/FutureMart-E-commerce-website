<?php
// Start session and include config
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userData = null;

if ($isLoggedIn) {
    // Fetch user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}

// Get cart count for logged-in user
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

// Handle form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    // Get form data
    $firstName = sanitizeInput($_POST['firstName'] ?? '');
    $lastName = sanitizeInput($_POST['lastName'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '', 'email');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $orderNumber = sanitizeInput($_POST['orderNumber'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    
    if (empty($firstName) || strlen($firstName) < 2) {
        $errors[] = "First name must be at least 2 characters";
    }
    
    if (empty($lastName) || strlen($lastName) < 2) {
        $errors[] = "Last name must be at least 2 characters";
    }
    
    if (!validateInput($email, 'email')) {
        $errors[] = "Invalid email address";
    }
    
    if (empty($category)) {
        $errors[] = "Please select a category";
    }
    
    if (empty($subject) || strlen($subject) < 5) {
        $errors[] = "Subject must be at least 5 characters";
    }
    
    if (empty($message) || strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters";
    }
    
    // Handle file upload if exists
    $attachmentPath = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['attachment']['type'], $allowedTypes)) {
            $errors[] = "Invalid file type. Only JPG, PNG, GIF, and PDF allowed";
        } elseif ($_FILES['attachment']['size'] > $maxSize) {
            $errors[] = "File size must be less than 5MB";
        } else {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['attachment']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachmentPath = 'tickets/' . $fileName;
            } else {
                $errors[] = "Failed to upload file";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Prepare full message
            $fullName = $firstName . ' ' . $lastName;
            $fullMessage = "Category: " . $category . "\n\n";
            $fullMessage .= "Message: " . $message . "\n\n";
            if (!empty($orderNumber)) {
                $fullMessage .= "Order Number: " . $orderNumber . "\n";
            }
            if (!empty($phone)) {
                $fullMessage .= "Phone: " . $phone . "\n";
            }
            
            // Determine sender type and ID
            $senderId = $isLoggedIn ? $_SESSION['user_id'] : null;
            $senderType = 'guest';
            
            if ($isLoggedIn) {
                $senderType = $userData['role'];
            }
            
            // Determine priority
            $priority = 'normal';
            if (in_array($category, ['billing', 'technical-support'])) {
                $priority = 'high';
            }
            
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages 
                (sender_id, sender_type, sender_name, sender_email, subject, message, 
                priority, attachment, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $senderId,
                $senderType,
                $fullName,
                $email,
                $subject,
                $fullMessage,
                $priority,
                $attachmentPath,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            // If newsletter checked and email not in newsletter table
            if ($newsletter) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO newsletter_subscribers (email, is_active, ip_address, subscribed_at)
                        VALUES (?, 1, ?, NOW())
                        ON DUPLICATE KEY UPDATE is_active = 1, subscribed_at = NOW()
                    ");
                    $stmt->execute([$email, $_SERVER['REMOTE_ADDR']]);
                } catch (Exception $e) {
                    // Ignore newsletter errors
                }
            }
            
            $successMessage = "Thank you for contacting us! We'll get back to you within 24 hours.";
            
            // Clear form by redirecting
            header("Location: contact.php?success=1");
            exit();
            
        } catch (Exception $e) {
            $errorMessage = "An error occurred. Please try again later.";
            error_log("Contact form error: " . $e->getMessage());
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $successMessage = "Thank you for contacting us! We'll get back to you within 24 hours.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --bg: #f8fafc;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
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
        }

        .navbar {
            background: #0f172a;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: #0f172a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
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
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }

        .nav-link.active {
            color: var(--primary-color) !important;
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

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .cart-icon {
            position: relative;
            cursor: pointer;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            padding: 0.50rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .user-avatar-placeholder {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid var(--primary-color);
        }

        .dropdown-menu {
            background: linear-gradient(to right bottom, rgba(191, 244, 228, 0.31), rgba(155, 209, 245, 0.57));
            backdrop-filter: blur(200px);
            border-radius: 10px;
            padding: 0.5rem 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown-menu hr {
            border: none;
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
            margin: 0.5rem 0;
        }

        .dropdown-menu a {
            color: var(--card-bg);
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: linear-gradient(to right bottom, rgba(191, 244, 228, 0.5), rgba(155, 209, 245, 0.7));
        }

        .hero {
            padding: 150px 0 80px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="none"><defs><radialGradient id="g" cx="50%" cy="50%"><stop offset="0%" stop-color="%23667eea" stop-opacity="0.3"/><stop offset="100%" stop-color="%23667eea" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23g)"/><circle cx="800" cy="300" r="150" fill="url(%23g)"/><circle cx="300" cy="700" r="120" fill="url(%23g)"/></svg>');
            opacity: 0.5;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .main-content {
            padding: 40px 0;
            margin-top: 50px;
        }

        .card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 29px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .contact-card {
            text-align: center;
            padding: 30px 20px;
            height: 100%;
        }

        .contact-card h5,
        .contact-card strong {
            margin-top: 15px;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-light);
        }

        .contact-card .text-muted {
            color: var(--text-muted) !important;
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .contact-icon.email {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .contact-icon.phone {
            background: linear-gradient(135deg, var(--accent-color), #059669);
        }

        .contact-icon.location {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .contact-icon.chat {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .form-control,
        .form-select,
        textarea {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-light);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        textarea.form-control {
            border-radius: 1rem;
            min-height: 140px;
            resize: vertical;
        }

        .form-control::placeholder,
        .form-select::placeholder {
            color: var(--text-muted);
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(236, 72, 153, 0.15) 100%);
            color: var(--text-light);
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-check-input {
            background-color: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-muted);
        }

        .btn-outline-primary {
            border-radius: 25px;
            padding: 12px 30px;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .faq-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 0;
        }

        .faq-item:last-child {
            border-bottom: none;
        }

        .faq-question {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .faq-question:hover {
            color: var(--primary-color);
        }

        .faq-answer {
            color: var(--text-muted);
            margin-top: 15px;
            display: none;
        }

        .map-container {
            height: 300px;
            background: transparent;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .social-links a {
            display: inline-block;
            width: 50px;
            height: 50px;
            line-height: 50px;
            text-align: center;
            border-radius: 50%;
            color: white;
            text-decoration: none;
            margin: 0 10px;
            transition: transform 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(199, 199, 228, 0.3);
        }

        .social-links .facebook {
            background: var(--gradient-1);
        }

        .social-links .twitter {
            background: var(--gradient-3);
        }

        .social-links .instagram {
            background: var(--gradient-2);
        }

        .social-links .linkedin {
            background: var(--primary-color);
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            z-index: 9998;
        }

        .cart-overlay.active {
            display: block;
        }

        .cart-modal {
            position: fixed;
            top: 0;
            right: -450px;
            width: 400px;
            height: 100%;
            background: #fff;
            z-index: 9999;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.3);
        }

        .cart-modal.open {
            right: 0;
        }

        .cart-header {
            background: #111827;
            color: #fff;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h5 {
            margin: 0;
            font-size: 1.25rem;
        }

        .cart-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .cart-content {
            flex: 1;
            padding: 1rem;
            color: #1e293b;
        }

        .cart-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
        }

        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-summary {
            background: #f9fafb;
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .cart-summary h6 {
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .empty-cart {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .footer {
            background: #0f172a;
            padding: 3rem 0 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-section h6 {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-1);
            color: white;
            text-decoration: none;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .alert-custom {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            .hero p {
                font-size: 1rem;
            }
            .cart-modal {
                width: 100%;
                right: -100%;
            }
            .contact-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

          /* Light Mode Styles - Add this section */
        body.light-mode {
            background: #f8fafc;
            color: #1e293b;
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .navbar-brand {
            -webkit-text-fill-color: #4f46e5;
            background: none;
        }

        body.light-mode .nav-link {
            color: #1e293b !important;
        }

        body.light-mode .nav-link:hover,
        body.light-mode .nav-link.active {
            color: #4f46e5 !important;
        }

        body.light-mode .user-profile:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        body.light-mode .hero {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
        }

        body.light-mode .hero h1 {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        body.light-mode .hero p {
            color: #64748b;
        }

        body.light-mode .card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #1e293b;
        }

        body.light-mode .card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .contact-card h5,
        body.light-mode .contact-card strong {
            color: #1e293b;
        }

        body.light-mode .contact-card .text-muted {
            color: #64748b !important;
        }

        body.light-mode .form-control,
        body.light-mode .form-select,
        body.light-mode textarea {
            background: rgba(99, 102, 241, 0.05);
            color: #1e293b;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .form-control::placeholder,
        body.light-mode .form-select::placeholder {
            color: #64748b;
        }

        body.light-mode .form-control:focus,
        body.light-mode .form-select:focus,
        body.light-mode textarea:focus {
            background: rgba(99, 102, 241, 0.08);
            color: #1e293b;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        body.light-mode .form-check-label {
            color: #64748b;
        }

        body.light-mode .faq-item {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .footer {
            background: #f1f5f9;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .footer-section h6 {
            color: #1e293b;
        }

        body.light-mode .footer-links a {
            color: #64748b;
        }

        body.light-mode .footer-links a:hover {
            color: #4f46e5;
        }

        body.light-mode .footer .text-light {
            color: #475569 !important;
        }

        body.light-mode .text-muted {
            color: #64748b !important;
        }

        body.light-mode .cart-header {
            background: #1e293b;
        }

        body.light-mode .cart-modal {
            background: #fff;
        }

        body.light-mode .cart-content,
        body.light-mode .cart-summary {
            color: #1e293b;
        }

        body.light-mode .cart-summary {
            background: #f9fafb;
        }

        body.light-mode .cart-summary h6 {
            color: #1e293b;
        }
    </style>
</head>
<body<?php echo ($isLoggedIn && $userData && $userData['theme_preference'] === 'light') ? ' class="light-mode"' : ''; ?>>
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <?php if ($isLoggedIn): ?>
                        <a href="#" class="nav-link cart-icon" onclick="toggleCart(); return false;">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
                        </a>

                        <div class="dropdown">
                            <div class="user-profile" data-bs-toggle="dropdown">
                                <?php if (!empty($userData['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($userData['avatar']); ?>"
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
                                <li><a class="dropdown-item" href="user/dashboard.php">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a></li>
                                <li><a class="dropdown-item" href="user/orders.php">
                                    <i class="fas fa-shopping-bag me-2"></i>My Orders
                                </a></li>
                                <li><a class="dropdown-item" href="settings.php">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-user me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="hero">
        <div class="container">
            <div class="text-center hero-content">
                <h1>Contact Us</h1>
                <p>We're here to help! Get in touch with our team</p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-5">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card contact-card">
                        <div class="contact-icon email">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5>Email Support</h5>
                        <p class="text-muted">Get help via email within 24 hours</p>
                        <strong>futuremart273@gmail.com.com</strong>
                        <div class="mt-3">
                            <small class="text-muted">Response time: 2-24 hours</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card contact-card">
                        <div class="contact-icon phone">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5>Phone Support</h5>
                        <p class="text-muted">Talk to our experts right away</p>
                        <strong>+94 75 563 8086</strong>
                        <div class="mt-3">
                            <small class="text-muted">Mon-Fri: 9AM-6PM EST</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card contact-card">
                        <div class="contact-icon chat">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h5>Live Chat</h5>
                        <p class="text-muted">Chat with us in real-time</p>
                        <button class="btn btn-outline-primary btn-sm" onclick="startLiveChat()">Start Chat</button>
                        <div class="mt-3">
                            <small class="text-muted">Available 24/7</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card contact-card">
                        <div class="contact-icon location">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5>Visit Our Store</h5>
                        <p class="text-muted">Come visit us in person</p>
                        <strong>44/31/B 2nd Cross St.<br>Thillayadi, Puttalam</strong>
                        <div class="mt-3">
                            <small class="text-muted">Mon-Sat: 10AM-8PM</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Send us a Message</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="contactForm">
                                <input type="hidden" name="submit_contact" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="firstName" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="firstName" name="firstName" placeholder="First Name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="lastName" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Last Name"required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Your Email"required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Your Phone">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="orderNumber" class="form-label">Order Number (if applicable)</label>
                                    <input type="text" class="form-control" id="orderNumber" name="orderNumber" placeholder="ORD-2024-001">
                                </div>

                                <div class="mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select a category</option>
                                        <option value="order-inquiry">Order Inquiry</option>
                                        <option value="product-question">Product Question</option>
                                        <option value="technical-support">Technical Support</option>
                                        <option value="billing">Billing & Payment</option>
                                        <option value="returns">Returns & Refunds</option>
                                        <option value="feedback">Feedback & Suggestions</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required placeholder="Brief description of your inquiry">
                                </div>

                                <div class="mb-4">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="6" required placeholder="Please provide detailed information about your inquiry..."></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="attachment" class="form-label">Attachment (Optional)</label>
                                    <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf">
                                    <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF, PDF</small>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                                        <label class="form-check-label" for="newsletter">
                                            Subscribe to our newsletter for updates and offers
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Business Hours</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Monday - Friday:</span>
                                <span>9:00 AM - 6:00 PM</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Saturday:</span>
                                <span>10:00 AM - 4:00 PM</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Sunday:</span>
                                <span>Closed</span>
                            </div>
                            <hr>
                            <small class="text-muted">All times are in Eastern Standard Time (EST)</small>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-map me-2"></i>Our Location</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="map-container">
                                <div class="text-center">
                                    <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
                                    <h6>Interactive Map</h6>
                                    <p class="text-muted">44/31/B 2nd Cross St.<br>Thillayadi, Puttalam</p>
                                    <button class="btn btn-outline-primary btn-sm" onclick="getDirections()">Get Directions</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-share-alt me-2"></i>Follow Us</h6>
                        </div>
                        <div class="card-body text-center">
                            <p class="text-muted mb-3">Stay connected with us on social media</p>
                            <div class="social-links">
                                <a href="#" class="facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="twitter"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="instagram"><i class="fab fa-instagram"></i></a>
                                <a href="#" class="linkedin"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <div class="faq-question d-flex justify-content-between align-items-center" onclick="toggleFAQ(1)">
                                    <h6 class="mb-0">How long does shipping take?</h6>
                                    <i class="fas fa-chevron-down" id="faq-icon-1"></i>
                                </div>
                                <div class="faq-answer" id="faq-answer-1">
                                    <p>Standard shipping typically takes 3-5 business days. Express shipping is available for 1-2 business days delivery. Free shipping is offered on orders over $50.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <div class="faq-question d-flex justify-content-between align-items-center" onclick="toggleFAQ(2)">
                                    <h6 class="mb-0">What is your return policy?</h6>
                                    <i class="fas fa-chevron-down" id="faq-icon-2"></i>
                                </div>
                                <div class="faq-answer" id="faq-answer-2">
                                    <p>We offer a 30-day return policy for most items. Products must be in original condition with tags attached. Some restrictions may apply for electronics and personal care items.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <div class="faq-question d-flex justify-content-between align-items-center" onclick="toggleFAQ(3)">
                                    <h6 class="mb-0">How can I track my order?</h6>
                                    <i class="fas fa-chevron-down" id="faq-icon-3"></i>
                                </div>
                                <div class="faq-answer" id="faq-answer-3">
                                    <p>Once your order ships, you'll receive a tracking number via email. You can also check your order status by visiting the "My Orders" section in your account.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <div class="faq-question d-flex justify-content-between align-items-center" onclick="toggleFAQ(4)">
                                    <h6 class="mb-0">Do you offer international shipping?</h6>
                                    <i class="fas fa-chevron-down" id="faq-icon-4"></i>
                                </div>
                                <div class="faq-answer" id="faq-answer-4">
                                    <p>Yes, we ship to over 50 countries worldwide. International shipping costs and delivery times vary by destination. Customs duties and taxes may apply.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <div class="faq-question d-flex justify-content-between align-items-center" onclick="toggleFAQ(5)">
                                    <h6 class="mb-0">How do I cancel or modify my order?</h6>
                                    <i class="fas fa-chevron-down" id="faq-icon-5"></i>
                                </div>
                                <div class="faq-answer" id="faq-answer-5">
                                    <p>You can cancel or modify your order within 1 hour of placing it. After that, please contact our customer service team as soon as possible, and we'll do our best to accommodate your request.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="footer-section">
                        <h5 class="navbar-brand mb-3">
                            <i class="fas fa-rocket me-2"></i>FutureMart
                        </h5>
                        <p class="text-light">Your trusted partner for cutting-edge products and exceptional shopping experiences. We're committed to bringing you the future of retail.</p>
                        <div class="social-icons mt-3">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Quick Links</h6>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="products.php">Shop</a></li>
                            <li><a href="categories.php">Categories</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Categories</h6>
                        <ul class="footer-links">
                            <li><a href="categories.php#electronics">Electronics</a></li>
                            <li><a href="categories.php#fashion">Fashion</a></li>
                            <li><a href="categories.php#home">Home & Living</a></li>
                            <li><a href="categories.php#sports">Sports & Fitness</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Support</h6>
                        <ul class="footer-links">
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Returns</a></li>
                            <li><a href="#">Shipping Info</a></li>
                            <li><a href="settings.php">Settings</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Legal</h6>
                        <ul class="footer-links">
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms of Service</a></li>
                            <li><a href="#">Cookie Policy</a></li>
                            <li><a href="#">About Us</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <hr style="border-color: rgba(255, 255, 255, 0.1);">

            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-light mb-0">&copy; 2024 FutureMart. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-light mb-0">
                        <i class="fas fa-heart text-danger"></i>
                        Made with love for amazing customers
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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
            setTimeout(() => alertDiv.remove(), 5000);
        }

        function toggleCart() {
            <?php if (!$isLoggedIn): ?>
                showNotification('Please login to view your cart', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
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
            fetch('cart_handler.php', {
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
                            <img src="${item.image || 'placeholder.jpg'}" alt="${item.name}" class="cart-item-image">
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
            fetch('cart_handler.php', {
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
            
            fetch('cart_handler.php', {
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
            
            fetch('cart_handler.php', {
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
            window.location.href = 'checkout.php';
        }

        function toggleFAQ(index) {
            const answer = document.getElementById(`faq-answer-${index}`);
            const icon = document.getElementById(`faq-icon-${index}`);

            if (answer.style.display === 'block') {
                answer.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                answer.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        function startLiveChat() {
            showNotification('Live chat feature would connect here!', 'info');
        }

        function getDirections() {
            window.open('https://maps.app.goo.gl/7CjMbiMXCiajHX9L7', '_blank');
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
    </script>
</body>
</html>