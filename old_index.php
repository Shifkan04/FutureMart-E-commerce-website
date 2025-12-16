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

        /* Navbar Styles */
        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(15, 23, 42, 0.98);
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
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
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

        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            z-index: 2000;
            background: #ffffff;
            background: linear-gradient(to right bottom,
                    rgba(191, 244, 228, 0.31),
                    rgba(155, 209, 245, 0.57));
            backdrop-filter: blur(200px);
            border-radius: 10px;
            padding: 0.5rem 0;
            color: var(--card-bg);

            hr {
                border: none;
                height: 1px;
                background: var(--dark-bg);
                margin: 0.5rem 0;
            }
        }

        .dropdown-menu a {
            color: var(--card-bg);
            font-weight: 500;
            transition: background 0.3s ease;

            &:hover {
                background: #ffffff;
                background: linear-gradient(to right bottom,
                        rgba(191, 244, 228, 0.31),
                        rgba(155, 209, 245, 0.57));
                backdrop-filter: blur(200px);
            }
        }

        /* Hero Section */
        .hero {
            padding: 120px 0 80px;
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

        .btn-hero {
            background: var(--gradient-2);
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(236, 72, 153, 0.4);
        }

        /* Section Styles */
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

        /* Product Cards */
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

        /* Category Cards */
        .category-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
            cursor: pointer;

            p,
            .text-muted {
                margin: 0;
                color: var(--text-muted) !important;
            }
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
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

        /* Testimonials */
        .testimonial-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
            transition: all 0.3s ease;

            .text-muted {
                margin: 0;
                color: var(--text-muted) !important;
            }
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
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
        }

        .stars {
            color: #fbbf24;
            margin-bottom: 1rem;
        }

        /* Newsletter */
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

        /* Contact Form */
        .main-content {
            padding: 5px;
            margin-top: 50px;
            margin-bottom: 20px;
            justify-content: center;
        }

        /* Cards */
        .contact-card {
            text-align: center;
            padding: 30px 20px;
            height: 100%;
            background-color: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            transition: all 0.3s ease;

            .text-muted {
                margin: 0;
                color: var(--text-muted) !important;
            }

            h5,
            stro {
                margin-top: 15px;
                margin-bottom: 10px;
                font-weight: 600;
                color: var(--text-light);
            }
        }

        .contact-card:hover {
            transform: translateY(-5px);
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

        .contact-form {
            /* background: var(--card-bg); */
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .contact-form h5 {
            color: var(--text-light);
            font-weight: 600;
        }

        /* Input + Select fields */
        .form-select {
            background:  var(--card-bg)!important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            transition: all 0.3s ease;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
        }

        .form-control {
            background:  rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            transition: all 0.3s ease;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
        }

        .form-control::placeholder,
        .form-select::placeholder {
            color: var(--text-muted);
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .form-control,
        .form-select,
        textarea {
            background:  rgba(99, 102, 241, 0.1);
            color: var(--text-light) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            color: var(--text-light) !important;
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }


        /* Textarea */
        textarea.form-control {
            border-radius: 1rem;
            min-height: 140px;
            resize: vertical;
        }

        /* Checkbox */
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

        .btn-primary {
            background: var(--gradient-1);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary {
            border-radius: 25px;
            padding: 12px 30px;
        }

        .text-muted {
            color: var(--text-muted) !important;
            /* or any color you want */
        }

        .faq-item {
            border-bottom: 1px solid #e5e7eb;
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
            color: #6b7280;
            margin-top: 15px;
            display: none;
        }

        .cart-badge {
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.8rem;
            position: absolute;
            top: -8px;
            right: -8px;
        }

        .map-container {
            height: 300px;
            background: transparent;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
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

        /* Responsive tweaks */
        @media (max-width: 991px) {

            /* Tablets */
            .main-content {
                padding: 20px;
            }

            .contact-card {
                margin-bottom: 20px;
            }

            .btn-primary,
            .btn-outline-primary {
                padding: 10px 25px;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 767px) {

            /* Mobiles */
            .main-content {
                padding: 15px;
            }

            .contact-card {
                padding: 20px 15px;
            }

            .contact-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .form-control {
                padding: 10px 12px;
                font-size: 0.9rem;
            }

            .btn-primary,
            .btn-outline-primary {
                padding: 8px 20px;
                font-size: 0.9rem;
            }

            .social-links a {
                width: 40px;
                height: 40px;
                line-height: 40px;
                margin: 0 5px;
            }
        }

        /* Cart Modal */
        .cart-modal {
            position: fixed;
            top: 0;
            right: -100%;
            width: 400px;
            height: 100vh;
            background: var(--card-bg);
            z-index: 9999;
            transition: all 0.3s ease;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }

        .cart-modal.open {
            right: 0;
        }

        .cart-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-content {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }

        .cart-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-item {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .cart-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Settings Page */
        .settings-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .settings-item:last-child {
            border-bottom: none;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: #374151;
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

        /* Footer */
        .footer {
            background: rgba(15, 23, 42, 0.95);
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

        /* Mobile Responsive */
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

        /* Animation Classes */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .page-container {
            display: none;
        }

        .page-container.active {
            display: block;
        }

        /* Filter Controls */
        .filter-controls {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filter-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            margin: 0.25rem;
            transition: all 0.3s ease;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        /* Alert Styles */
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
            border-radius: 10px;
        }

        .alert-info {
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: var(--accent-color);
            border-radius: 10px;
        }

        /* Price Range Slider */
        .price-range {
            margin: 1rem 0;
        }

        .range-slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #374151;
            outline: none;
            -webkit-appearance: none;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }

        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            border: none;
        }

        /* Light Mode Overrides */
        body.light-mode {
            background: var(--bg);
            color: #1e293b;
            /* dark text for light bg */
        }

        body.light-mode .navbar {
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .navbar-brand {
            -webkit-text-fill-color: initial;
            /* restore normal text */
            background: none;
            color: var(--primary-dark);
        }

        body.light-mode .nav-link {
            color: #1e293b !important;
        }

        body.light-mode .nav-link:hover {
            color: var(--primary-dark) !important;
        }

        /* Cards, modals, sections */
        body.light-mode .product-card,
        body.light-mode .category-card,
        body.light-mode .testimonial-card,
        body.light-mode .contact-form,
        body.light-mode .settings-card,
        body.light-mode .cart-modal {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #1e293b;
        }

        body.light-mode .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #404040ff 0%, #252525ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        body.light-mode .footer {
            background: #f1f5f9;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            color: #1e293b;
        }

        body.light-mode .footer-links a {
            color: #475569 !important;
        }

        body.light-mode .footer-links a:hover {
            color: var(--primary-dark);
        }

        body.light-mode .form-control {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .form-control::placeholder {
            color: #94a3b8;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#" onclick="showPage('home')">
                <i class="fas fa-rocket me-2"></i>FutureMart
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showPage('home')">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="shopLink" href="#" onclick="showPage('shop')">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showPage('categories')">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="showPage('contact')">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center">
                    <a href="#" class="nav-link cart-icon me-3" onclick="toggleCart()">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-badge" id="cartCount">0</span>
                    </a>

                    <a href="login.php" class="btn btn-primary me-3">
                        <i class="fas fa-user me-1"></i> Login
                    </a>

                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="#" onclick="showPage('settings')"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="user/user.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-question-circle me-2"></i>Help</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Cart Modal -->
    <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
    <div class="cart-modal" id="cartModal">
        <div class="cart-header">
            <h5><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h5>
            <button class="btn btn-sm btn-outline-light" onclick="toggleCart()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cart-content" id="cartItems">
            <div class="text-center text-muted py-4">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>Your cart is empty</p>
            </div>
        </div>
        <div class="cart-footer">
            <div class="d-flex justify-content-between mb-3">
                <strong>Total: <span id="cartTotal">$0.00</span></strong>
            </div>
            <button class="btn btn-primary w-100 mb-2">
                <i class="fas fa-credit-card me-2"></i>Checkout
            </button>
            <button class="btn btn-outline-light w-100" onclick="clearCart()">
                <i class="fas fa-trash me-2"></i>Clear Cart
            </button>
        </div>
    </div>

    <!-- Home Page -->
    <div class="page-container active" id="home">
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6 hero-content">
                        <h1 class="fade-in">Welcome to the Future of Shopping</h1>
                        <p class="fade-in">Discover cutting-edge products with innovative technology and exceptional quality. Your journey to tomorrow starts here.</p>
                        <a href="shop.php" class="btn btn-hero fade-in">
                            <i class="fas fa-rocket me-2"></i>Start Shopping
                        </a><br><br>
                    </div>
                    <div class="col-lg-6">
                        <div class="text-center">
                            <i class="fa-solid fa-cart-shopping" style="font-size: 15rem; opacity: 0.2;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="section">
            <div class="container">
                <div class="section-title fade-in">
                    <h2>Featured Products</h2>
                    <p>Discover our handpicked selection of premium products</p>
                </div>

                <div class="row g-4" id="featuredProducts">
                    <!-- Featured products will be loaded here -->
                </div>
            </div>
        </section>

        <!-- Categories Preview -->
        <section class="section">
            <div class="container">
                <div class="section-title fade-in">
                    <h2>Shop by Category</h2>
                    <p>Explore our diverse range of product categories</p>
                </div>

                <div class="row g-4">
                    <div class="col-lg-3 col-md-6 fade-in">
                        <div class="category-card" onclick="filterByCategory('electronics')">
                            <div class="category-icon category-1">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h5>Electronics</h5>
                            <p class="text-muted">Latest gadgets and tech accessories</p>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 fade-in">
                        <div class="category-card" onclick="filterByCategory('fashion')">
                            <div class="category-icon category-2">
                                <i class="fas fa-tshirt"></i>
                            </div>
                            <h5>Fashion</h5>
                            <p class="text-muted">Trendy clothing and accessories</p>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 fade-in">
                        <div class="category-card" onclick="filterByCategory('home')">
                            <div class="category-icon category-3">
                                <i class="fas fa-home"></i>
                            </div>
                            <h5>Home & Living</h5>
                            <p class="text-muted">Beautiful items for your home</p>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 fade-in">
                        <div class="category-card" onclick="filterByCategory('sports')">
                            <div class="category-icon category-4">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <h5>Sports & Fitness</h5>
                            <p class="text-muted">Equipment for active lifestyle</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="section">
            <div class="container">
                <div class="section-title fade-in">
                    <h2>What Our Customers Say</h2>
                    <p>Real feedback from our satisfied customers</p>
                </div>

                <div class="row g-4">
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
                </div>
            </div>
        </section>

        <!-- Newsletter -->
        <section class="newsletter">
            <div class="container">
                <div class="fade-in">
                    <h2 class="mb-3">Stay Updated</h2>
                    <p class="mb-4">Get the latest updates on new products and exclusive offers</p>
                    <form class="newsletter-form" onsubmit="subscribeNewsletter(event)">
                        <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Subscribe
                        </button>
                    </form>
                    <div id="newsletterMessage" class="mt-3"></div>
                </div>
            </div>
        </section>
    </div>

    <!-- Shop Page -->
    <div class="page-container" id="shop">
        <div class="hero" style="padding: 150px 0 80px;">
            <div class="container">
                <div class="text-center">
                    <h1>Our Shop</h1>
                    <p>Discover amazing products at unbeatable prices</p>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="container">
                <!-- Filter Controls -->
                <div class="filter-controls">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-3">Filter by Category:</h6>
                            <button class="filter-btn active" onclick="filterProducts('all')">All Products</button>
                            <button class="filter-btn" onclick="filterProducts('electronics')">Electronics</button>
                            <button class="filter-btn" onclick="filterProducts('fashion')">Fashion</button>
                            <button class="filter-btn" onclick="filterProducts('home')">Home & Living</button>
                            <button class="filter-btn" onclick="filterProducts('sports')">Sports</button>
                        </div>
                        <div class="col-md-6">
                            <div class="price-range">
                                <h6 class="mb-2">Price Range: $<span id="priceValue">0 - 2000</span></h6>
                                <input type="range" class="range-slider" min="0" max="2000" value="2000" onchange="filterByPrice(this.value)">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4" id="allProducts">
                    <!-- Products will be loaded here -->
                </div>
            </div>
        </section>
    </div>

    <!-- Categories Page -->
    <div class="page-container" id="categories">
        <div class="hero" style="padding: 150px 0 80px;">
            <div class="container">
                <div class="text-center">
                    <h1>Categories</h1>
                    <p>Browse products by category</p>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-4 col-md-6">
                        <div class="category-card" onclick="filterByCategory('electronics')">
                            <div class="category-icon category-1">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h5>Electronics</h5>
                            <p class="text-muted">Latest gadgets and tech accessories</p>
                            <div class="mt-3">
                                <small class="text-muted">15 Products Available</small>
                            </div>
                            <button class="btn btn-primary mt-3" onclick="filterByCategory('electronics')">View Products</button>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="category-card" onclick="filterByCategory('fashion')">
                            <div class="category-icon category-2">
                                <i class="fas fa-tshirt"></i>
                            </div>
                            <h5>Fashion</h5>
                            <p class="text-muted">Trendy clothing and accessories</p>
                            <div class="mt-3">
                                <small class="text-muted">22 Products Available</small>
                            </div>
                            <button class="btn btn-primary mt-3" onclick="filterByCategory('fashion')">View Products</button>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="category-card" onclick="filterByCategory('home')">
                            <div class="category-icon category-3">
                                <i class="fas fa-home"></i>
                            </div>
                            <h5>Home & Living</h5>
                            <p class="text-muted">Beautiful items for your home</p>
                            <div class="mt-3">
                                <small class="text-muted">18 Products Available</small>
                            </div>
                            <button class="btn btn-primary mt-3" onclick="filterByCategory('home')">View Products</button>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="category-card" onclick="filterByCategory('sports')">
                            <div class="category-icon category-4">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <h5>Sports & Fitness</h5>
                            <p class="text-muted">Equipment for active lifestyle</p>
                            <div class="mt-3">
                                <small class="text-muted">12 Products Available</small>
                            </div>
                            <button class="btn btn-primary mt-3" onclick="filterByCategory('sports')">View Products</button>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="category-card" onclick="filterByCategory('books')">
                            <div class="category-icon category-1">
                                <i class="fas fa-book"></i>
                            </div>
                            <h5>Books & Media</h5>
                            <p class="text-muted">Educational and entertainment content</p>
                            <div class="mt-3">
                                <small class="text-muted">8 Products Available</small>
                            </div>
                            <button class="btn btn-primary mt-3" onclick="filterByCategory('books')">View Products</button>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="category-card" onclick="filterByCategory('beauty')">
                            <div class="category-icon category-2">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h5>Beauty & Health</h5>
                            <p class="text-muted">Personal care and wellness products</p>
                            <div class="mt-3">
                                <small class="text-muted">14 Products Available</small>
                            </div>
                            <button class="btn btn-primary mt-3" onclick="filterByCategory('beauty')">View Products</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Contact Page -->
    <div class="page-container" id="contact">
        <div class="hero" style="padding: 150px 0 80px;">
            <div class="container">
                <div class="text-center">
                    <h1>Contact Us</h1>
                    <p>We're here to help! Get in touch with our team</p>
                </div>
            </div>
        </div>
        <!-- Main Content -->
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md- col-lg-15">
                    <div class="main-content">
                        <!-- Contact Methods -->
                        <div class="row mb-5 d-flex justify-content-center">
                            <div class="col-md-3 col-sm-6 mb-4">
                                <div class="card contact-card">
                                    <div class="contact-icon email">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <h5>Email Support</h5>
                                    <p class="text-muted">Get help via email within 24 hours</p>
                                    <strong>support@shopease.com</strong>
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
                                    <strong>+1 (555) 123-4567</strong>
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
                                    <button class="btn btn-outline-primary btn-sm">Start Chat</button>
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
                                    <strong>123 Shopping St.<br>New York, NY 10001</strong>
                                    <div class="mt-3">
                                        <small class="text-muted">Mon-Sat: 10AM-8PM</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Contact Form -->
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Send us a Message</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="contactForm">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="firstName" class="form-label">First Name *</label>
                                                    <input type="text" class="form-control" id="firstName" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="lastName" class="form-label">Last Name *</label>
                                                    <input type="text" class="form-control" id="lastName" required>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label">Email Address *</label>
                                                    <input type="email" class="form-control" id="email" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="phone" class="form-label">Phone Number</label>
                                                    <input type="tel" class="form-control" id="phone">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="orderNumber" class="form-label">Order Number (if applicable)</label>
                                                <input type="text" class="form-control" id="orderNumber" placeholder="ORD-2024-001">
                                            </div>
                                            <div class="mb-3">
                                                <label for="category" class="form-label">Category *</label>
                                                <select class="form-select" id="category" required>
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
                                                <input type="text" class="form-control" id="subject" required placeholder="Brief description of your inquiry">
                                            </div>
                                            <div class="mb-4">
                                                <label for="message" class="form-label">Message *</label>
                                                <textarea class="form-control" id="message" rows="6" required placeholder="Please provide detailed information about your inquiry..."></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="newsletter">
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

                            <!-- Additional Info -->
                            <div class="col-lg-4">
                                <!-- Business Hours -->
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

                                <!-- Map -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-map me-2"></i>Our Location</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="map-container">
                                            <div class="text-center">
                                                <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
                                                <h6>Interactive Map</h6>
                                                <p class="text-muted">123 Shopping Street<br>New York, NY 10001</p>
                                                <button class="btn btn-outline-primary btn-sm">Get Directions</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Social Media -->
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

                        <!-- FAQ Section -->
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
            </div>
        </div>
    </div>


    <!-- Settings Page -->
    <div class="page-container" id="settings">
        <div class="hero" style="padding: 150px 0 80px;">
            <div class="container">
                <div class="text-center">
                    <h1>Settings</h1>
                    <p>Customize your shopping experience</p>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <!-- Account Settings -->
                        <div class="settings-card">
                            <h4 class="mb-4"><i class="fas fa-user me-2"></i>Account Settings</h4>
                            <div class="settings-item">
                                <div>
                                    <h6>Email Notifications</h6>
                                    <small class="text-muted">Receive updates about orders and promotions</small>
                                </div>
                                <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>SMS Notifications</h6>
                                    <small class="text-muted">Get text messages for order updates</small>
                                </div>
                                <div class="toggle-switch" onclick="toggleSetting(this)"></div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>Marketing Communications</h6>
                                    <small class="text-muted">Receive promotional offers and deals</small>
                                </div>
                                <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                            </div>
                        </div>

                        <!-- Privacy Settings -->
                        <div class="settings-card">
                            <h4 class="mb-4"><i class="fas fa-shield-alt me-2"></i>Privacy Settings</h4>
                            <div class="settings-item">
                                <div>
                                    <h6>Data Analytics</h6>
                                    <small class="text-muted">Help improve our services with usage data</small>
                                </div>
                                <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>Personalized Recommendations</h6>
                                    <small class="text-muted">Show products based on your preferences</small>
                                </div>
                                <div class="toggle-switch active" onclick="toggleSetting(this)"></div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>Location Services</h6>
                                    <small class="text-muted">Use location for better delivery experience</small>
                                </div>
                                <div class="toggle-switch" onclick="toggleSetting(this)"></div>
                            </div>
                        </div>

                        <!-- Display Settings -->
                        <div class="settings-card">
                            <h4 class="mb-4"><i class="fas fa-palette me-2"></i>Display Settings</h4>
                            <div class="settings-item">
                                <div>
                                    <h6>Dark Mode</h6>
                                    <small class="text-muted">Switch to dark theme (Currently active)</small>
                                </div>
                                <div class="toggle-switch" onclick="toggleSetting(this)"></div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>Compact View</h6>
                                    <small class="text-muted">Show more items per page</small>
                                </div>
                                <div class="toggle-switch" onclick="toggleSetting(this)"></div>
                            </div>
                            <div class="settings-item">
                                <div>
                                    <h6>High Contrast</h6>
                                    <small class="text-muted">Improve accessibility with high contrast</small>
                                </div>
                                <div class="toggle-switch" onclick="toggleSetting(this)"></div>
                            </div>
                        </div>

                        <!-- Language & Region -->
                        <div class="settings-card">
                            <h4 class="mb-4"><i class="fas fa-globe me-2"></i>Language & Region</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Language</label>
                                    <select class="form-control">
                                        <option value="en">English</option>
                                        <option value="es">Español</option>
                                        <option value="fr">Français</option>
                                        <option value="de">Deutsch</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Currency</label>
                                    <select class="form-control">
                                        <option value="usd">USD ($)</option>
                                        <option value="eur">EUR (€)</option>
                                        <option value="gbp">GBP (£)</option>
                                        <option value="jpy">JPY (¥)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="text-center">
                            <button class="btn btn-primary btn-lg" onclick="saveSettings()">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
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
                            <li><a href="#" onclick="showPage('home')">Home</a></li>
                            <li><a href="#" onclick="showPage('shop')">Shop</a></li>
                            <li><a href="#" onclick="showPage('categories')">Categories</a></li>
                            <li><a href="#" onclick="showPage('contact')">Contact</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 mb-4">
                    <div class="footer-section">
                        <h6>Categories</h6>
                        <ul class="footer-links">
                            <li><a href="#" onclick="filterByCategory('electronics')">Electronics</a></li>
                            <li><a href="#" onclick="filterByCategory('fashion')">Fashion</a></li>
                            <li><a href="#" onclick="filterByCategory('home')">Home & Living</a></li>
                            <li><a href="#" onclick="filterByCategory('sports')">Sports & Fitness</a></li>
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
                            <li><a href="#" onclick="showPage('settings')">Settings</a></li>
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
        // Sample Products Data
        const products = [{
                id: 1,
                name: "Ultra Laptop Pro",
                price: 1299.99,
                category: "electronics",
                rating: 4.8,
                image: "fas fa-laptop",
                gradient: "linear-gradient(45deg, #667eea, #764ba2)"
            },
            {
                id: 2,
                name: "Wireless Headphones",
                price: 199.99,
                category: "electronics",
                rating: 4.6,
                image: "fas fa-headphones",
                gradient: "linear-gradient(45deg, #f093fb, #f5576c)"
            },
            {
                id: 3,
                name: "Smart Phone X",
                price: 899.99,
                category: "electronics",
                rating: 4.9,
                image: "fas fa-mobile-alt",
                gradient: "linear-gradient(45deg, #4facfe, #00f2fe)"
            },
            {
                id: 4,
                name: "Designer T-Shirt",
                price: 49.99,
                category: "fashion",
                rating: 4.3,
                image: "fas fa-tshirt",
                gradient: "linear-gradient(45deg, #43e97b, #38f9d7)"
            },
            {
                id: 5,
                name: "Leather Jacket",
                price: 299.99,
                category: "fashion",
                rating: 4.7,
                image: "fas fa-user-tie",
                gradient: "linear-gradient(45deg, #667eea, #764ba2)"
            },
            {
                id: 6,
                name: "Running Shoes",
                price: 129.99,
                category: "fashion",
                rating: 4.5,
                image: "fas fa-shoe-prints",
                gradient: "linear-gradient(45deg, #f093fb, #f5576c)"
            },
            {
                id: 7,
                name: "Smart TV 55\"",
                price: 599.99,
                category: "electronics",
                rating: 4.6,
                image: "fas fa-tv",
                gradient: "linear-gradient(45deg, #4facfe, #00f2fe)"
            },
            {
                id: 8,
                name: "Coffee Maker",
                price: 89.99,
                category: "home",
                rating: 4.4,
                image: "fas fa-coffee",
                gradient: "linear-gradient(45deg, #43e97b, #38f9d7)"
            },
            {
                id: 9,
                name: "Yoga Mat",
                price: 39.99,
                category: "sports",
                rating: 4.2,
                image: "fas fa-dumbbell",
                gradient: "linear-gradient(45deg, #667eea, #764ba2)"
            },
            {
                id: 10,
                name: "Bluetooth Speaker",
                price: 79.99,
                category: "electronics",
                rating: 4.5,
                image: "fas fa-volume-up",
                gradient: "linear-gradient(45deg, #f093fb, #f5576c)"
            },
            {
                id: 11,
                name: "Home Decor Lamp",
                price: 159.99,
                category: "home",
                rating: 4.3,
                image: "fas fa-lightbulb",
                gradient: "linear-gradient(45deg, #4facfe, #00f2fe)"
            },
            {
                id: 12,
                name: "Fitness Tracker",
                price: 249.99,
                category: "sports",
                rating: 4.7,
                image: "fas fa-heart",
                gradient: "linear-gradient(45deg, #43e97b, #38f9d7)"
            }
        ];

        // Cart Management
        let cart = [];
        let currentFilter = 'all';
        let maxPrice = 2000;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadFeaturedProducts();
            loadAllProducts();
            setupScrollAnimations();
            setupNavbarScroll();
        });

        // Page Navigation
        function showPage(pageId) {
            // Hide all pages
            document.querySelectorAll('.page-container').forEach(page => {
                page.classList.remove('active');
            });

            // Show selected page
            document.getElementById(pageId).classList.add('active');

            // Update navbar active state
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            // Scroll to top
            window.scrollTo(0, 0);

            // Load page-specific content
            if (pageId === 'shop') {
                loadAllProducts();
            }
        }

        // Load Featured Products (for home page)
        function loadFeaturedProducts() {
            const featuredContainer = document.getElementById('featuredProducts');
            const featuredProducts = products.slice(0, 3); // Show first 3 products

            featuredContainer.innerHTML = featuredProducts.map(product => `
                <div class="col-lg-4 col-md-6 fade-in">
                    <div class="product-card">
                        <div class="product-image" style="background: ${product.gradient}">
                            <i class="${product.image}"></i>
                        </div>
                        <h5 class="product-title">${product.name}</h5>
                        <div class="product-rating">
                            <span class="stars">${generateStars(product.rating)}</span>
                            <small class="text-muted ms-2">(${product.rating})</small>
                        </div>
                        <div class="product-price">${product.price}</div>
                        <button class="btn btn-add-cart" onclick="addToCart(${product.id})">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Load All Products (for shop page)
        function loadAllProducts() {
            const allProductsContainer = document.getElementById('allProducts');
            const filteredProducts = filterProductsByCategory(currentFilter).filter(product => product.price <= maxPrice);

            if (filteredProducts.length === 0) {
                allProductsContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No products found matching your criteria.
                        </div>
                    </div>
                `;
                return;
            }

            allProductsContainer.innerHTML = filteredProducts.map(product => `
                <div class="col-lg-4 col-md-6 fade-in">
                    <div class="product-card">
                        <div class="product-image" style="background: ${product.gradient}">
                            <i class="${product.image}"></i>
                        </div>
                        <h5 class="product-title">${product.name}</h5>
                        <div class="product-rating">
                            <span class="stars">${generateStars(product.rating)}</span>
                            <small class="text-muted ms-2">(${product.rating})</small>
                        </div>
                        <div class="product-price">${product.price}</div>
                        <button class="btn btn-add-cart" onclick="addToCart(${product.id})">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Generate star rating
        function generateStars(rating) {
            const fullStars = Math.floor(rating);
            const halfStar = rating % 1 !== 0;
            let stars = '';

            for (let i = 0; i < fullStars; i++) {
                stars += '<i class="fas fa-star"></i>';
            }

            if (halfStar) {
                stars += '<i class="fas fa-star-half-alt"></i>';
            }

            const emptyStars = 5 - Math.ceil(rating);
            for (let i = 0; i < emptyStars; i++) {
                stars += '<i class="far fa-star"></i>';
            }

            return stars;
        }

        // Filter products by category
        function filterProductsByCategory(category) {
            if (category === 'all') {
                return products;
            }
            return products.filter(product => product.category === category);
        }

        // Filter Products
        function filterProducts(category) {
            currentFilter = category;

            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            loadAllProducts();
        }

        // Filter by Price
        function filterByPrice(value) {
            maxPrice = parseInt(value);
            document.getElementById('priceValue').textContent = `0 - ${value}`;
            loadAllProducts();
        }

        // Filter by Category (from category cards)
        function filterByCategory(category) {
            showPage('shop');
            setTimeout(() => {
                filterProducts(category);
                // Update the active filter button
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.textContent.toLowerCase().includes(category) ||
                        (category === 'electronics' && btn.textContent === 'Electronics') ||
                        (category === 'fashion' && btn.textContent === 'Fashion') ||
                        (category === 'home' && btn.textContent === 'Home & Living') ||
                        (category === 'sports' && btn.textContent === 'Sports')) {
                        btn.classList.add('active');
                    }
                });
            }, 100);
        }

        // Add to Cart
        function addToCart(productId) {
            const product = products.find(p => p.id === productId);
            if (!product) return;

            const existingItem = cart.find(item => item.id === productId);

            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    image: product.image,
                    quantity: 1
                });
            }

            updateCartUI();
            showCartNotification();
        }

        // Update Cart UI
        function updateCartUI() {
            const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
            const cartTotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);

            document.getElementById('cartCount').textContent = cartCount;
            document.getElementById('cartTotal').textContent = `${cartTotal.toFixed(2)}`;

            const cartItemsContainer = document.getElementById('cartItems');

            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
            } else {
                cartItemsContainer.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div>
                            <h6>${item.name}</h6>
                            <p class="text-muted mb-0">${item.price} x ${item.quantity}</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-light me-2" onclick="updateQuantity(${item.id}, -1)">-</button>
                            <span class="mx-2">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-light me-2" onclick="updateQuantity(${item.id}, 1)">+</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Update Quantity
        function updateQuantity(productId, change) {
            const item = cart.find(item => item.id === productId);
            if (!item) return;

            item.quantity += change;

            if (item.quantity <= 0) {
                removeFromCart(productId);
            } else {
                updateCartUI();
            }
        }

        // Remove from Cart
        function removeFromCart(productId) {
            cart = cart.filter(item => item.id !== productId);
            updateCartUI();
        }

        // Clear Cart
        function clearCart() {
            cart = [];
            updateCartUI();
        }

        // Toggle Cart
        function toggleCart() {
            const cartModal = document.getElementById('cartModal');
            const cartOverlay = document.getElementById('cartOverlay');

            cartModal.classList.toggle('open');
            cartOverlay.classList.toggle('active');
        }

        // Show Cart Notification
        function showCartNotification() {
            // Create a temporary notification
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div class="alert alert-success position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas fa-check-circle me-2"></i>
                    Item added to cart successfully!
                </div>
            `;
            document.body.appendChild(notification);

            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Newsletter Subscription
        function subscribeNewsletter(event) {
            event.preventDefault();
            const email = event.target.querySelector('input[type="email"]').value;
            const messageDiv = document.getElementById('newsletterMessage');

            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Thank you for subscribing! Welcome to FutureMart community.
                </div>
            `;

            event.target.reset();

            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 5000);
        }

        // Contact Form Submission
        function submitContact(event) {
            event.preventDefault();
            const messageDiv = document.getElementById('contactMessage');

            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Thank you for your message! We'll get back to you within 24 hours.
                </div>
            `;

            event.target.reset();

            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 5000);
        }

        // Settings Toggle
        function toggleSetting(toggle) {
            toggle.classList.toggle('active');

            // Add visual feedback
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div class="alert alert-info position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 250px;">
                    <i class="fas fa-info-circle me-2"></i>
                    Setting updated successfully!
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 2000);
        }

        // Save Settings
        function saveSettings() {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div class="alert alert-success position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas fa-check-circle me-2"></i>
                    All settings saved successfully!
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Scroll Animations
        function setupScrollAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, observerOptions);

            // Observe all fade-in elements
            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        }

        // Navbar Scroll Effect
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

        // Search Functionality
        function searchProducts(query) {
            const filteredProducts = products.filter(product =>
                product.name.toLowerCase().includes(query.toLowerCase()) ||
                product.category.toLowerCase().includes(query.toLowerCase())
            );

            const allProductsContainer = document.getElementById('allProducts');

            if (filteredProducts.length === 0) {
                allProductsContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-search me-2"></i>
                            No products found for "${query}". Try different keywords.
                        </div>
                    </div>
                `;
            } else {
                allProductsContainer.innerHTML = filteredProducts.map(product => `
                    <div class="col-lg-4 col-md-6 fade-in">
                        <div class="product-card">
                            <div class="product-image" style="background: ${product.gradient}">
                                <i class="${product.image}"></i>
                            </div>
                            <h5 class="product-title">${product.name}</h5>
                            <div class="product-rating">
                                <span class="stars">${generateStars(product.rating)}</span>
                                <small class="text-muted ms-2">(${product.rating})</small>
                            </div>
                            <div class="product-price">${product.price}</div>
                            <button class="btn btn-add-cart" onclick="addToCart(${product.id})">
                                <i class="fas fa-cart-plus me-2"></i>Add to Cart
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Close cart when clicking outside
        document.addEventListener('click', function(event) {
            const cartModal = document.getElementById('cartModal');
            const cartIcon = document.querySelector('.cart-icon');

            if (!cartModal.contains(event.target) && !cartIcon.contains(event.target) && cartModal.classList.contains('open')) {
                toggleCart();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const cartModal = document.getElementById('cartModal');
                if (cartModal.classList.contains('open')) {
                    toggleCart();
                }
            }
        });

        // Enhanced product interactions
        function quickView(productId) {
            const product = products.find(p => p.id === productId);
            if (!product) return;

            // This would open a modal with product details
            alert(`Quick view for ${product.name}\nPrice: ${product.price}\nRating: ${product.rating} stars`);
        }

        // Wishlist functionality (placeholder)
        function addToWishlist(productId) {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div class="alert alert-info position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas fa-heart me-2"></i>
                    Added to wishlist!
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 2000);
        }

        // Compare products functionality (placeholder)
        function addToCompare(productId) {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div class="alert alert-info position-fixed" style="top: 100px; right: 20px; z-index: 10000; min-width: 300px;">
                    <i class="fas fa-balance-scale me-2"></i>
                    Added to compare list!
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 2000);
        }

        // Loading animation helper
        function showLoading(containerId) {
            document.getElementById(containerId).innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading products...</p>
                </div>
            `;
        }

        // Initialize animations after page load
        setTimeout(() => {
            setupScrollAnimations();
        }, 100);

        // Add some interactive elements on hover
        document.addEventListener('mouseover', function(event) {
            if (event.target.closest('.product-card')) {
                event.target.closest('.product-card').style.transform = 'translateY(-10px) scale(1.02)';
            }
        });

        document.addEventListener('mouseout', function(event) {
            if (event.target.closest('.product-card')) {
                event.target.closest('.product-card').style.transform = 'translateY(0) scale(1)';
            }
        });

        // Add click ripple effect
        function createRipple(event) {
            const button = event.currentTarget;
            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            `;

            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        }

        // Add ripple effect to all buttons
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', createRipple);
            });
        });

        function toggleSetting(el) {
            // toggle the switch UI
            el.classList.toggle("active");

            // toggle theme class
            document.body.classList.toggle("light-mode");

            // save preference
            localStorage.setItem(
                "theme",
                document.body.classList.contains("light-mode") ? "light" : "dark"
            );

            // update text under the title
            const small = el.parentElement.querySelector("small");
            if (document.body.classList.contains("light-mode")) {
                small.textContent = "Switch to light theme (Currently active)";
            } else {
                small.textContent = "Switch to dark theme (Currently active)";
            }
        }

        // load preference when page opens
        document.addEventListener("DOMContentLoaded", () => {
            if (localStorage.getItem("theme") === "light") {
                document.body.classList.add("light-mode");
                document.querySelector(".toggle-switch").classList.add("active");
            }
        });

        // Contact form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form data
            const formData = {
                firstName: document.getElementById('firstName').value,
                lastName: document.getElementById('lastName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                orderNumber: document.getElementById('orderNumber').value,
                category: document.getElementById('category').value,
                subject: document.getElementById('subject').value,
                message: document.getElementById('message').value,
                newsletter: document.getElementById('newsletter').checked
            };

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;

            // Simulate form submission
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;

                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Message sent successfully!</strong> We'll get back to you within 24 hours.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

                document.querySelector('.card-body').prepend(alertDiv);
                this.reset();

                // Remove alert after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);

            }, 2000);
        });

        // Toggle FAQ answers
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

        // Update cart count
        function updateCartCount() {
            document.getElementById('cartCount').textContent = '3';
        }

        // Live chat functionality
        document.querySelector('.contact-card .btn').addEventListener('click', function() {
            alert('Live chat feature would open here! This would connect to your chat system.');
        });

        // Initialize
        updateCartCount();
    </script>
</body>

</html>