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

// Fetch featured products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 AND p.is_featured = 1 
    LIMIT 3
");
$stmt->execute();
$featuredProducts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM testimonials 
    WHERE is_approved = 1 AND is_featured = 1 
    ORDER BY created_at DESC 
    LIMIT 6
");
$stmt->execute();
$testimonials = $stmt->fetchAll();

// Get cart count for logged-in user
$cartCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?? 0;
}

$theme = $userData['theme_preference'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FutureMart - Modern E-commerce</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url("https://fonts.googleapis.com/css?family=Raleway");


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
            --glow-color: hsla(229, 100%, 69%, 1.00);
        }

        * {
            box-sizing: border-box;
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
            background: #030303ff;
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

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
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
            position: absolute;
            z-index: 2000;
            background: linear-gradient(to right bottom, rgba(191, 244, 228, 0.31), rgba(155, 209, 245, 0.57));
            backdrop-filter: blur(200px);
            border-radius: 10px;
            padding: 0.5rem 0;
            color: var(--card-bg);
            min-width: 200px;
        }

        .dropdown-menu hr {
            border: none;
            height: 1px;
            background: var(--dark-bg);
            margin: 0.5rem 0;
        }

        .dropdown-menu a {
            color: var(--card-bg);
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: linear-gradient(to right bottom, rgba(191, 244, 228, 0.31), rgba(155, 209, 245, 0.57));
        }

        .hero {
            position: relative;
            height: 100vh;
            overflow: hidden;
            color: #fff;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.8;
        }

        .hero-content,
        .hero .text-center {
            position: relative;
            z-index: 2;
            margin-top: 100px;
        }

        .hero::before {
            /* Optional dark overlay for readability */
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }


        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(5deg);
            }
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
            color: var(--text-muted) !important;
            margin-bottom: 2rem;
        }

        .glowing-btn-container {
            position: absolute;
            /* Position relative to the viewport */
            /* bottom: 180px;
            left: 42%;  */
            transform: translateX(-50%);
            /* Offset the container by 50% of its width to center it */
            display: flex;
            justify-content: center;
            width: auto;
            /* Ensure the width is auto to allow centering */
        }



        .glowing-btn {
            width: 340px;
            position: absolute;
            color: var(--glow-color);
            cursor: pointer;
            padding: 0.35em 1em;
            border: 0.10em solid var(--glow-color);
            border-radius: 2em;
            background: none;
            perspective: 2em;
            font-family: "Raleway", sans-serif;
            font-size: 2em;
            font-weight: 900;
            letter-spacing: 0.5em;
            -webkit-box-shadow: inset 0px 0px 0.5em 0px var(--glow-color),
                0px 0px 0.5em 0px var(--glow-color);
            -moz-box-shadow: inset 0px 0px 0.5em 0px var(--glow-color),
                0px 0px 0.5em 0px var(--glow-color);
            box-shadow: inset 0px 0px 0.5em 0px var(--glow-color),
                0px 0px 0.5em 0px var(--glow-color);
            animation: border-flicker 2s linear infinite;
        }

        .glowing-txt {
            float: left;
            margin-right: -0.8em;
            -webkit-text-shadow: 0 0 0.125em hsl(0 0% 100% / 0.3),
                0 0 0.45em var(--glow-color);
            -moz-text-shadow: 0 0 0.125em hsl(0 0% 100% / 0.3),
                0 0 0.45em var(--glow-color);
            text-shadow: 0 0 0.125em hsl(0 0% 100% / 0.3), 0 0 0.45em var(--glow-color);
            animation: text-flicker 3s linear infinite;
        }

        .faulty-letter {
            opacity: 0.5;
            animation: faulty-flicker 2s linear infinite;
        }

        .glowing-btn::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            opacity: 0.7;
            filter: blur(1em);
            transform: translateY(120%) rotateX(95deg) scale(1, 0.35);
            background: var(--glow-color);
            pointer-events: none;
        }

        .glowing-btn::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0;
            z-index: -1;
            border-radius: 2em;
            background-color: var(--glow-color);
            box-shadow: 0 0 2em 0.2em var(--glow-color);
            transition: opacity 100ms linear;
        }

        .glowing-btn:hover {
            color: rgba(0, 0, 0, 0.8);
            text-shadow: none;
            animation: none;
        }

        .glowing-btn:hover .glowing-txt {
            animation: none;
        }

        .glowing-btn:hover .faulty-letter {
            animation: none;
            text-shadow: none;
            opacity: 1;
        }

        .glowing-btn:hover:before {
            filter: blur(1.5em);
            opacity: 1;
        }

        .glowing-btn:hover:after {
            opacity: 1;
        }

        @keyframes faulty-flicker {
            0% {
                opacity: 0.1;
            }

            2% {
                opacity: 0.1;
            }

            4% {
                opacity: 0.5;
            }

            19% {
                opacity: 0.5;
            }

            21% {
                opacity: 0.1;
            }

            23% {
                opacity: 1;
            }

            80% {
                opacity: 0.5;
            }

            83% {
                opacity: 0.4;
            }

            87% {
                opacity: 1;
            }
        }

        @keyframes text-flicker {
            0% {
                opacity: 0.1;
            }

            2% {
                opacity: 1;
            }

            8% {
                opacity: 0.1;
            }

            9% {
                opacity: 1;
            }

            12% {
                opacity: 0.1;
            }

            20% {
                opacity: 1;
            }

            25% {
                opacity: 0.3;
            }

            30% {
                opacity: 1;
            }

            70% {
                opacity: 0.7;
            }

            72% {
                opacity: 0.2;
            }

            77% {
                opacity: 0.9;
            }

            100% {
                opacity: 0.9;
            }
        }

        @keyframes border-flicker {
            0% {
                opacity: 0.1;
            }

            2% {
                opacity: 1;
            }

            4% {
                opacity: 0.1;
            }

            8% {
                opacity: 1;
            }

            70% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
        }

        @media only screen and (max-width: 600px) {
            .glowing-btn {
                font-size: 1em;
                width: 170px;
            }
        }

        .section {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            color: var(--text-muted) !important;
            font-size: 1.1rem;
        }

        .product-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-1);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .product-card:hover::before {
            opacity: 0.05;
        }

        .product-image {
            width: 100%;
            height: 200px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
            background: var(--gradient-1);
        }

        .product-image i {
            font-size: 3rem;
            color: white;
            opacity: 0.8;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .product-rating {
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
            color: var(--text-muted) !important;
        }

        .product-rating .stars {
            color: #fbbf24;
        }

        .btn-add-cart {
            width: 100%;
            background: var(--gradient-3);
            border: none;
            padding: 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3);
        }

        .btn-add-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .category-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .category-card p,
        .category-card .text-muted {
            margin: 0;
            color: var(--text-muted) !important;
        }

        .category-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
        }

        .category-1 {
            background: var(--gradient-1);
        }

        .category-2 {
            background: var(--gradient-2);
        }

        .category-3 {
            background: var(--gradient-3);
        }

        .category-4 {
            background: var(--gradient-4);
        }

        .testimonial-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
        }

        .testimonial-card .text-muted {
            margin: 0;
            color: var(--text-muted) !important;
        }

        .testimonial-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            overflow: hidden;
        }

        .stars {
            color: #fbbf24;
            margin-bottom: 1rem;
        }

        .stars i {
            margin: 0 2px;
        }

        /* Light Mode Styles */
        body.light-mode .testimonial-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body.light-mode .testimonial-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .testimonial-card h6 {
            color: #1e293b;
        }

        body.light-mode .testimonial-card p {
            color: #64748b;
        }

        body.light-mode .testimonial-card .text-muted {
            color: #64748b !important;
        }

        .newsletter {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            padding: 4rem 0;
            text-align: center;
        }

        .newsletter-form {
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            gap: 1rem;
        }

        .newsletter-input {
            flex: 1;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            color: var(--text-light);
        }

        .newsletter-input::placeholder {
            color: var(--text-muted);
        }

        .newsletter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--text-muted) !important;
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

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
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
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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

        .summary-details {
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

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .newsletter-form {
                flex-direction: column;
            }

            .section {
                padding: 60px 0;
            }

            .cart-modal {
                width: 100%;
                right: -100%;
            }
        }

        /* Light Mode Styles */
body.light-mode {
    background: var(--bg);
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
    -webkit-text-fill-color: var(--primary-dark);
    background: none;
}

body.light-mode .nav-link {
    color: #1e293b !important;
}

body.light-mode .nav-link:hover,
body.light-mode .nav-link.active {
    color: var(--primary-dark) !important;
}

body.light-mode .user-profile:hover {
    background: rgba(0, 0, 0, 0.05);
}

body.light-mode .hero {
    background: linear-gradient(135deg, rgba(213, 213, 213, 1) 0%, rgba(130, 130, 130, 1) 100%);
}

body.light-mode .hero h1 {
    background: linear-gradient(135deg, #6db6ffff 0%, #7a8eabff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

body.light-mode .hero p {
    color: #64748b;
}

body.light-mode .section {
    background: transparent;
}

body.light-mode .product-card,
body.light-mode .category-card,
body.light-mode .testimonial-card {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.1);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

body.light-mode .product-card:hover,
body.light-mode .category-card:hover,
body.light-mode .testimonial-card:hover {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

body.light-mode .product-title,
body.light-mode .category-card h5,
body.light-mode .testimonial-card h6 {
    color: #1e293b;
}

body.light-mode .product-rating,
body.light-mode .category-card p,
body.light-mode .testimonial-card p {
    color: #64748b;
}

body.light-mode .newsletter {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
}

body.light-mode .newsletter-input {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.1);
    color: #1e293b;
}

body.light-mode .newsletter-input::placeholder {
    color: #64748b;
}

body.light-mode .footer {
    background: #f1f5f9;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-mode .footer-section h6 {
    color: #1e293b;
}

body.light-mode .footer-links a {
    color: #64748b !important;
}

body.light-mode .footer-links a:hover {
    color: var(--primary-dark) !important;
}

body.light-mode .footer .text-light {
    color: #475569 !important;
}

body.light-mode .cart-header {
    background: #f8fafc;
    color: #1e293b;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

body.light-mode .cart-summary {
    background: #f1f5f9;
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

                    <button class="btn btn-success w-100 mb-2" id="procheckout" onclick="proceedToCheckout()">
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
                <i class="fas fa-rocket me-2"></i> <?php echo APP_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
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
                        <a class="nav-link" href="contact.php">Contact</a>
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
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
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

    <section class="hero position-relative">
        <!-- Load Spline Script once -->
        <script type="module" src="https://unpkg.com/@splinetool/viewer@1.10.77/build/spline-viewer.js"></script>

        <!-- Hero Background Spline -->
        <spline-viewer
            url="https://prod.spline.design/MMOS4R3WuxBkB8z4/scene.splinecode"
            class="hero-bg"></spline-viewer>

        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="fade-in">Welcome to the Future of Shopping</h1>
                    <p class="fade-in">
                        Discover cutting-edge products with innovative technology and exceptional quality.
                        Your journey to tomorrow starts here.
                    </p>
                    <div class="glowing-btn-container">
                        <a href="products.php">
                            <button class='glowing-btn'>
                                <span class='glowing-txt'>SH<span class='faulty-letter'>O</span>P<span class='faulty-letter'>PI</span>NG </button>
                        </a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="text-center">
                        <!-- Right Side Spline -->
                        <spline-viewer
                            url="https://prod.spline.design/T1O1lb51c6bsdzf4/scene.splinecode"
                            style="width: 100%; height: 550px; opacity: 0.9;"></spline-viewer>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <section class="section">
        <div class="container">
            <div class="section-title fade-in">
                <h2>Featured Products</h2>
                <p>Discover our handpicked selection of premium products</p>
            </div>

            <div class="row g-4">
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="col-lg-4 col-md-6 fade-in">
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <div class="product-rating">
                                <span class="stars">
                                    <?php
                                    $rating = $product['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= floor($rating)) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i - 0.5 <= $rating) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </span>
                                <small class="text-muted ms-2">(<?php echo number_format($rating, 1); ?>)</small>
                            </div>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <?php if ($isLoggedIn): ?>
                                <button class="btn btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                            <?php else: ?>
                                <button class="btn btn-add-cart" disabled title="Please login to add to cart">
                                    <i class="fas fa-lock me-2"></i>Login to Purchase
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title fade-in">
                <h2>Shop by Category</h2>
                <p>Explore our diverse range of product categories</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6 fade-in">
                    <a href="categories.php#electronics" style="text-decoration: none; color: inherit;">
                        <div class="category-card">
                            <div class="category-icon category-1">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h5>Electronics</h5>
                            <p class="text-muted">Latest gadgets and tech accessories</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6 fade-in">
                    <a href="categories.php#fashion" style="text-decoration: none; color: inherit;">
                        <div class="category-card">
                            <div class="category-icon category-2">
                                <i class="fas fa-tshirt"></i>
                            </div>
                            <h5>Fashion</h5>
                            <p class="text-muted">Trendy clothing and accessories</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6 fade-in">
                    <a href="categories.php#home" style="text-decoration: none; color: inherit;">
                        <div class="category-card">
                            <div class="category-icon category-3">
                                <i class="fas fa-home"></i>
                            </div>
                            <h5>Home & Living</h5>
                            <p class="text-muted">Beautiful items for your home</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6 fade-in">
                    <a href="categories.php#sports" style="text-decoration: none; color: inherit;">
                        <div class="category-card">
                            <div class="category-icon category-4">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <h5>Sports & Fitness</h5>
                            <p class="text-muted">Equipment for active lifestyle</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title fade-in">
                <h2>What Our Customers Say</h2>
                <p>Real feedback from our satisfied customers</p>
            </div>

            <div class="row g-4">
                <?php if (!empty($testimonials)): ?>
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="col-lg-4 fade-in">
                            <div class="testimonial-card">
                                <div class="testimonial-avatar">
                                    <?php if ($testimonial['customer_image']): ?>
                                        <img src="<?php echo htmlspecialchars($testimonial['customer_image']); ?>"
                                            alt="<?php echo htmlspecialchars($testimonial['customer_name']); ?>"
                                            style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="stars mb-3">
                                    <?php
                                    $rating = (int)$testimonial['rating'];
                                    // Display filled stars
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>

                                <p>"<?php echo htmlspecialchars($testimonial['testimonial_text']); ?>"</p>

                                <h6 class="mt-3"><?php echo htmlspecialchars($testimonial['customer_name']); ?></h6>

                                <small class="text-muted">
                                    <?php if ($testimonial['is_verified']): ?>
                                        <i class="fas fa-check-circle text-success"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($testimonial['designation']); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback if no testimonials in database -->
                    <div class="col-lg-4 fade-in">
                        <div class="testimonial-card">
                            <div class="testimonial-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="stars mb-3">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p>"Amazing products and exceptional service. FutureMart has exceeded all my expectations!"</p>
                            <h6 class="mt-3">Sarah Johnson</h6>
                            <small class="text-muted">Verified Customer</small>
                        </div>
                    </div>

                    <div class="col-lg-4 fade-in">
                        <div class="testimonial-card">
                            <div class="testimonial-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="stars mb-3">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p>"Fast delivery and top-quality products. I'm a customer for life!"</p>
                            <h6 class="mt-3">Mike Chen</h6>
                            <small class="text-muted">Verified Customer</small>
                        </div>
                    </div>

                    <div class="col-lg-4 fade-in">
                        <div class="testimonial-card">
                            <div class="testimonial-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="stars mb-3">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p>"The user experience is incredible. Shopping has never been this easy!"</p>
                            <h6 class="mt-3">Emma Davis</h6>
                            <small class="text-muted">Verified Customer</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="newsletter">
        <div class="container">
            <div class="fade-in">
                <h2 class="mb-3">Stay Updated</h2>
                <p class="mb-4">Get the latest updates on new products and exclusive offers</p>
                <form class="newsletter-form" id="newsletterForm">
                    <input type="email" class="newsletter-input" name="email" placeholder="Enter your email address" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Subscribe
                    </button>
                </form>
            </div>
        </div>
    </section>

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
            setTimeout(() => alertDiv.remove(), 3000);
        }

        function toggleCart() {
            document.getElementById('cartModal').classList.toggle('open');
            document.getElementById('cartOverlay').classList.toggle('active');
            if (document.getElementById('cartModal').classList.contains('open')) {
                loadCartItems();
            }
        }

        function loadCartItems() {
            fetch('cart_handler.php', {
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

            fetch('cart_handler.php', {
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

            fetch('cart_handler.php', {
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
            window.location.href = 'checkout.php';
        }

        function addToCart(productId) {
            const button = event.currentTarget;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            button.disabled = true;

            fetch('cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=add&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                        button.style.background = '#ff00a2ff';
                        document.getElementById('cartCount').textContent = data.cartCount;
                        showNotification(data.message, 'success');
                    } else {
                        button.innerHTML = originalText;
                        showNotification(data.message, 'danger');
                    }
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                        button.style.background = '';
                    }, 2000);
                })
                .catch(error => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showNotification('Error adding to cart', 'danger');
                });
        }

        // Load More
        document.getElementById('procheckout').addEventListener('click', async function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            this.disabled = true;
            setTimeout(() => {
                currentPage++;
                loadOrders(currentPage, true);
                this.innerHTML = '<i class="fas fa-plus me-2"></i>Load More Orders';
                this.disabled = false;
            }, 1000);
        });

        document.getElementById('newsletterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('newsletter_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        this.reset();
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => showNotification('Error subscribing to newsletter', 'danger'));
        });

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
            setupScrollAnimations();
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

        // Add this function to handle cart clicks when not logged in
document.addEventListener('DOMContentLoaded', function() {
    const cartIcon = document.querySelector('.cart-icon');
    
    if (cartIcon && !<?php echo $isLoggedIn ? 'true' : 'false'; ?>) {
        cartIcon.addEventListener('click', function(e) {
            e.preventDefault();
            showNotification('Please login to view your cart', 'warning');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 1500);
        });
    }
});
    </script>
</body>

</html>