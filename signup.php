<?php
// signup.php - PDO only
require_once 'config.php'; // must create $pdo here
// session_start();

$errorMsg = "";
$successMsg = "";

if (isset($_POST['submit'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirmPassword']);
    $role = "user";
    $theme_preference = "light";

    $errors = [];

    // Validation
    if (empty($first_name)) {
        $errors[] = "First name is required";
    } elseif (strlen($first_name) < 2 || strlen($first_name) > 50) {
        $errors[] = "First name must be between 2 and 50 characters";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
        $errors[] = "First name can only contain letters and spaces";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required";
    } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
        $errors[] = "Last name must be between 2 and 50 characters";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $errors[] = "Last name can only contain letters and spaces";
    }

    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (strlen($email) > 255) {
        $errors[] = "Email address is too long";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[\+]?[1-9][\d]{7,14}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
        $errors[] = "Invalid phone number format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif (strlen($password) > 255) {
        $errors[] = "Password is too long";
    } elseif (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/', $password)) {
        $errors[] = "Password must contain at least one lowercase, uppercase, number, and special char";
    }

    if (empty($confirmPassword)) {
        $errors[] = "Please confirm your password";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    if (!isset($_POST['agreeTerms']) || $_POST['agreeTerms'] !== 'on') {
        $errors[] = "You must agree to the Terms of Service and Privacy Policy";
    }

    if (!empty($errors)) {
        $errorMsg = implode('<br>', $errors);
    } else {
        try {
            // check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            $existing = $stmt->fetch();

            if ($existing) {
                $errorMsg = "An account with this email or phone already exists";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // insert user
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, theme_preference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role, $theme_preference]);

                $userId = $pdo->lastInsertId();

                // session store
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['verification_pending'] = true;

                // OTP system
                require_once 'config.php';
                require_once 'EmailHelper.php';
                require_once 'OTPManager.php';

                $otpManager = new OTPManager($pdo);
                $otpResult = $otpManager->generateAndSendOTP($userId, $email, $first_name . ' ' . $last_name);

                if ($otpResult['success']) {
                    $successMsg = "Registration successful! Please check your email for verification code.";
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "account-verification.php";
                        }, 2000);
                    </script>';
                } else {
                    error_log("OTP Error: " . $otpResult['message']);
                    $errorMsg = "Registration successful but failed to send verification email.";
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $errorMsg = "Error during registration. Please try again later.";
        }
    }
}

$theme = $userData['theme_preference'] ?? 'light';
?>


<!-- Rest of your HTML code remains the same -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - FutureMart</title>
    <meta name="description" content="Create your FutureMart account and start shopping with us today!">
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
            --error-color: #ef4444;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 2rem 0;
        }

        /* Background Animation */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="none"><defs><radialGradient id="g1" cx="50%" cy="50%"><stop offset="0%" stop-color="%23667eea" stop-opacity="0.3"/><stop offset="100%" stop-color="%23667eea" stop-opacity="0"/></radialGradient><radialGradient id="g2" cx="50%" cy="50%"><stop offset="0%" stop-color="%23f093fb" stop-opacity="0.2"/><stop offset="100%" stop-color="%23f093fb" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="300" fill="url(%23g1)"/><circle cx="800" cy="600" r="250" fill="url(%23g2)"/><circle cx="400" cy="800" r="200" fill="url(%23g1)"/></svg>');
            opacity: 0.6;
            animation: float 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes gradientShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            33% {
                transform: translateY(-20px) rotate(1deg);
            }

            66% {
                transform: translateY(10px) rotate(-1deg);
            }
        }

        /* Signup Container */
        .signup-container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }

        .signup-card {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            animation: slideIn 0.8s ease-out;
        }

        .signup-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-2);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo h2 {
            font-weight: 700;
            font-size: 2rem;
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .brand-logo p {
            color: var(--text-muted);
            font-size: 0.9rem;
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
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-light);
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
            color: var(--text-light);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 5;
            transition: color 0.3s ease;
        }

        .form-control:focus+.input-icon {
            color: var(--secondary-color);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            z-index: 5;
            transition: color 0.3s ease;
            margin-right: 15px;
        }

        .password-toggle:hover {
            color: var(--secondary-color);
        }

        .btn-signup {
            width: 100%;
            background: var(--gradient-2);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.3);
            color: white;
        }

        .btn-signup:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-meter {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak {
            background: var(--error-color);
            width: 25%;
        }

        .strength-fair {
            background: var(--warning-color);
            width: 50%;
        }

        .strength-good {
            background: var(--accent-color);
            width: 75%;
        }

        .strength-strong {
            background: var(--success-color);
            width: 100%;
        }

        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .terms-checkbox {
            margin: 1.5rem 0;
        }

        .form-check-input {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 0.25rem;
        }

        .form-check-input:checked {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .form-check-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .form-check-label a {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .form-check-label a:hover {
            text-decoration: underline;
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider span {
            background: var(--card-bg);
            padding: 0 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .social-login {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .btn-social {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-social:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--secondary-color);
            color: var(--text-light);
            transform: translateY(-2px);
        }

        .google {
            border-color: #db4437;
        }

        .google:hover {
            border-color: #db4437;
            box-shadow: 0 5px 15px rgba(219, 68, 55, 0.3);
        }

        .facebook {
            border-color: #3b5998;
        }

        .facebook:hover {
            border-color: #3b5998;
            box-shadow: 0 5px 15px rgba(59, 89, 152, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-link p {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        .back-to-home {
            position: fixed;
            top: 2rem;
            left: 2rem;
            z-index: 10;
        }

        .back-to-home a {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .back-to-home a:hover {
            color: var(--secondary-color);
            background: rgba(30, 41, 59, 0.9);
            transform: translateY(-2px);
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            margin-bottom: 1rem;
            border: none;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Input Validation States */
        .form-control.is-valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--error-color);
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
        }

        .invalid-feedback {
            color: #fca5a5;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .valid-feedback {
            color: #6ee7b7;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .signup-container {
                padding: 1rem;
            }

            .signup-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .back-to-home {
                position: relative;
                top: auto;
                left: auto;
                margin-bottom: 2rem;
            }

            .social-login {
                flex-direction: column;
            }
        }

        /* Light Mode Theme */
        body.light-mode {
            --dark-bg: #f8fafc;
            /* light background */
            --card-bg: #ffffff;
            /* white cards */
            --text-light: #1e293b;
            /* dark text */
            --text-muted: #64748b;
            /* muted dark gray */
        }

        body.light-mode .signup-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .signup-card::before {
            background: var(--gradient-2);
        }

        body.light-mode .form-control {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--text-light);
        }

        body.light-mode .form-control:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            color: var(--text-light);
            outline: none;
        }

        body.light-mode .input-group .input-icon {
            color: var(--text-muted);
        }

        body.light-mode .form-check-input {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .form-check-input:checked {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        body.light-mode .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        body.light-mode .back-to-home a {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--text-muted);
        }

        body.light-mode .back-to-home a:hover {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-light);
        }

        body.light-mode .theme-toggle button {
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-light);
        }

        body.light-mode .theme-toggle button:hover {
            background: var(--primary-color);
            color: white;
        }

        body.light-mode .form-group {
            margin-bottom: 1.5rem;
        }

        body.light-mode .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }

        body.light-mode .form-control {

            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-light);
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border-color: rgba(0, 0, 0, 0.1);
        }

        body.light-mode .form-control:focus {

            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
            color: var(--text-light);
            outline: none;
        }

        body.light-mode .form-control::placeholder {
            color: var(--text-muted);
        }

        body.light-mode .form-control:focus+.input-icon {
            color: var(--secondary-color);
        }


        .theme-toggle {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 20;
        }

        .theme-toggle button {
            border-radius: 50%;
            padding: 0.6rem;
            background: rgba(30, 41, 59, 0.8);
            color: var(--text-light);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .theme-toggle button:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Input Validation States */
        body.light-mode .form-control.is-valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
        }

        body.light-mode .form-control.is-invalid {
            border-color: var(--error-color);
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
        }

        body.light-mode .invalid-feedback {
            color: #f26868ff;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        body.light-mode .valid-feedback {
            color: #48e6a7ff;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <!-- Back to Home -->
    <div class="back-to-home">
        <a href="index.php">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>

    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <button onclick="toggleTheme()" class="btn btn-sm" aria-label="Toggle theme">
            <i class="fas fa-sun" id="theme-icon"></i>
        </button>
    </div>

    <div class="signup-container">
        <div class="signup-card">
            <!-- Brand Logo -->
            <div class="brand-logo">
                <h2><i class="fas fa-rocket"></i> FutureMart</h2>
                <p>Create your account and start shopping!</p>
            </div>

            <!-- Display PHP Messages -->
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $errorMsg; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $successMsg; ?>
                </div>
            <?php endif; ?>

            <!-- Signup Form -->
            <form id="signupForm" method="POST" action="signup.php" novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="firstName">First Name *</label>
                            <div class="input-group">
                                <input type="text" name="first_name" class="form-control" id="firstName"
                                    placeholder="Enter first name" required maxlength="50"
                                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                <i class="fas fa-user input-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="firstNameError"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="lastName">Last Name *</label>
                            <div class="input-group">
                                <input type="text" name="last_name" class="form-control" id="lastName"
                                    placeholder="Enter last name" required maxlength="50"
                                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                <i class="fas fa-user input-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="lastNameError"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" id="email"
                            placeholder="Enter your email" required maxlength="255"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                    <div class="invalid-feedback" id="emailError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <div class="input-group">
                        <input type="tel" name="phone" class="form-control" id="phone"
                            placeholder="Enter your phone number" required maxlength="20"
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        <i class="fas fa-phone input-icon"></i>
                    </div>
                    <div class="invalid-feedback" id="phoneError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password *</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control" id="password"
                            placeholder="Create a password" required maxlength="255">
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    <div class="invalid-feedback" id="passwordError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmPassword">Confirm Password *</label>
                    <div class="input-group">
                        <input type="password" name="confirmPassword" class="form-control" id="confirmPassword"
                            placeholder="Confirm your password" required maxlength="255">
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="confirmPasswordError"></div>
                </div>

                <div class="terms-checkbox">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="agreeTerms" name="agreeTerms" required>
                        <label class="form-check-label" for="agreeTerms">
                            I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a> *
                        </label>
                    </div>
                    <div class="invalid-feedback" id="termsError"></div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                    <label class="form-check-label" for="newsletter">
                        Subscribe to our newsletter for updates and exclusive offers
                    </label>
                </div>

                <button type="submit" name="submit" class="btn btn-signup" id="signupBtn">
                    <span id="signupText">Create Account</span>
                </button>
            </form>

            <!-- Login Link -->
            <div class="login-link">
                <p>Already have an account?</p>
                <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced form validation and submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            // Allow form to submit naturally for PHP processing
            // but add client-side validation for better UX
            const isValid = validateForm();
            if (!isValid) {
                e.preventDefault();
            }
        });

        function validateForm() {
            const formData = {
                firstName: document.getElementById('firstName').value.trim(),
                lastName: document.getElementById('lastName').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                password: document.getElementById('password').value,
                confirmPassword: document.getElementById('confirmPassword').value,
                agreeTerms: document.getElementById('agreeTerms').checked
            };

            clearValidation();
            let isValid = true;

            // First Name validation
            if (!formData.firstName) {
                showFieldError('firstName', 'First name is required');
                isValid = false;
            } else if (formData.firstName.length < 2 || formData.firstName.length > 50) {
                showFieldError('firstName', 'First name must be between 2 and 50 characters');
                isValid = false;
            } else if (!/^[a-zA-Z\s]+$/.test(formData.firstName)) {
                showFieldError('firstName', 'First name can only contain letters and spaces');
                isValid = false;
            } else {
                showFieldSuccess('firstName');
            }

            // Last Name validation
            if (!formData.lastName) {
                showFieldError('lastName', 'Last name is required');
                isValid = false;
            } else if (formData.lastName.length < 2 || formData.lastName.length > 50) {
                showFieldError('lastName', 'Last name must be between 2 and 50 characters');
                isValid = false;
            } else if (!/^[a-zA-Z\s]+$/.test(formData.lastName)) {
                showFieldError('lastName', 'Last name can only contain letters and spaces');
                isValid = false;
            } else {
                showFieldSuccess('lastName');
            }

            // Email validation
            if (!formData.email) {
                showFieldError('email', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(formData.email)) {
                showFieldError('email', 'Please enter a valid email address');
                isValid = false;
            } else if (formData.email.length > 255) {
                showFieldError('email', 'Email address is too long');
                isValid = false;
            } else {
                showFieldSuccess('email');
            }

            // Phone validation
            if (!formData.phone) {
                showFieldError('phone', 'Phone number is required');
                isValid = false;
            } else if (!isValidPhone(formData.phone)) {
                showFieldError('phone', 'Please enter a valid phone number (8-15 digits)');
                isValid = false;
            } else {
                showFieldSuccess('phone');
            }

            // Password validation
            const passwordStrength = checkPasswordStrength(formData.password);
            if (!formData.password) {
                showFieldError('password', 'Password is required');
                isValid = false;
            } else if (formData.password.length < 8) {
                showFieldError('password', 'Password must be at least 8 characters long');
                isValid = false;
            } else if (passwordStrength.score < 3) {
                showFieldError('password', 'Password must contain uppercase, lowercase, number, and special character');
                isValid = false;
            } else {
                showFieldSuccess('password');
            }

            // Confirm Password validation
            if (!formData.confirmPassword) {
                showFieldError('confirmPassword', 'Please confirm your password');
                isValid = false;
            } else if (formData.password !== formData.confirmPassword) {
                showFieldError('confirmPassword', 'Passwords do not match');
                isValid = false;
            } else if (formData.password) {
                showFieldSuccess('confirmPassword');
            }

            // Terms validation
            if (!formData.agreeTerms) {
                showFieldError('agreeTerms', 'You must agree to the terms and conditions');
                document.getElementById('termsError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('termsError').style.display = 'none';
            }

            return isValid;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidPhone(phone) {
            // Remove spaces, dashes, parentheses
            const cleanPhone = phone.replace(/[\s\-\(\)]/g, '');
            // Allow international format with + and 8-15 digits
            const phoneRegex = /^[\+]?[1-9][\d]{7,14}$/;
            return phoneRegex.test(cleanPhone);
        }

        function checkPasswordStrength(password) {
            let score = 0;
            let feedback = [];

            if (password.length >= 8) score++;
            else feedback.push('Use 8 or more characters');

            if (password.match(/[a-z]/)) score++;
            else feedback.push('Add lowercase letters');

            if (password.match(/[A-Z]/)) score++;
            else feedback.push('Add uppercase letters');

            if (password.match(/[0-9]/)) score++;
            else feedback.push('Add numbers');

            if (password.match(/[^A-Za-z0-9]/)) score++;
            else feedback.push('Add special characters');

            return {
                score,
                feedback
            };
        }

        function updatePasswordStrength(password) {
            const strength = checkPasswordStrength(password);
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            // Remove all strength classes
            strengthFill.className = 'strength-fill';

            if (password.length === 0) {
                strengthText.textContent = '';
                return;
            }

            switch (strength.score) {
                case 0:
                case 1:
                    strengthFill.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                    strengthText.style.color = 'var(--error-color)';
                    break;
                case 2:
                    strengthFill.classList.add('strength-fair');
                    strengthText.textContent = 'Fair password';
                    strengthText.style.color = 'var(--warning-color)';
                    break;
                case 3:
                case 4:
                    strengthFill.classList.add('strength-good');
                    strengthText.textContent = 'Good password';
                    strengthText.style.color = 'var(--accent-color)';
                    break;
                case 5:
                    strengthFill.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                    strengthText.style.color = 'var(--success-color)';
                    break;
            }
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + 'Error');

            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }

        function showFieldSuccess(fieldId) {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + 'Error');

            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
            }
        }

        function clearValidation() {
            const fields = ['firstName', 'lastName', 'email', 'phone', 'password', 'confirmPassword'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const errorElement = document.getElementById(fieldId + 'Error');

                field.classList.remove('is-valid', 'is-invalid');
                if (errorElement) {
                    errorElement.textContent = '';
                    errorElement.style.display = 'none';
                }
            });

            document.getElementById('termsError').style.display = 'none';
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + 'ToggleIcon');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function toggleTheme() {
            document.body.classList.toggle("light-mode");
            const themeIcon = document.getElementById('theme-icon');

            if (document.body.classList.contains("light-mode")) {
                localStorage.setItem("theme", "light");
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            } else {
                localStorage.setItem("theme", "dark");
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        }

        // Event listeners for real-time validation
        document.getElementById('password').addEventListener('input', function() {
            updatePasswordStrength(this.value);

            // Clear password match error when password changes
            const confirmPassword = document.getElementById('confirmPassword');
            if (confirmPassword.value && confirmPassword.classList.contains('is-invalid')) {
                confirmPassword.classList.remove('is-invalid');
                document.getElementById('confirmPasswordError').style.display = 'none';
            }
        });

        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            if (this.value) {
                if (this.value === password) {
                    showFieldSuccess('confirmPassword');
                } else {
                    showFieldError('confirmPassword', 'Passwords do not match');
                }
            }
        });

        document.getElementById('email').addEventListener('blur', function() {
            if (this.value) {
                if (isValidEmail(this.value)) {
                    showFieldSuccess('email');
                } else {
                    showFieldError('email', 'Please enter a valid email address');
                }
            }
        });

        document.getElementById('phone').addEventListener('blur', function() {
            if (this.value) {
                if (isValidPhone(this.value)) {
                    showFieldSuccess('phone');
                } else {
                    showFieldError('phone', 'Please enter a valid phone number');
                }
            }
        });

        // Name validation on blur
        ['firstName', 'lastName'].forEach(fieldId => {
            document.getElementById(fieldId).addEventListener('blur', function() {
                const value = this.value.trim();
                if (value) {
                    if (value.length < 2 || value.length > 50) {
                        showFieldError(fieldId, 'Name must be between 2 and 50 characters');
                    } else if (!/^[a-zA-Z\s]+$/.test(value)) {
                        showFieldError(fieldId, 'Name can only contain letters and spaces');
                    } else {
                        showFieldSuccess(fieldId);
                    }
                }
            });
        });

        // Load saved theme on page load
        document.addEventListener("DOMContentLoaded", function() {
            const savedTheme = localStorage.getItem("theme");
            const themeIcon = document.getElementById('theme-icon');

            if (savedTheme === "light") {
                document.body.classList.add("light-mode");
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }

            // Focus first field
            document.getElementById('firstName').focus();


            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>