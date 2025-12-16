<?php
// Add this PHP code at the very top of your existing forgot-password.php file
session_start();

require_once 'config.php';   // should contain $pdo = new PDO(...)
require_once 'EmailHelper.php';

$response = ['success' => false, 'message' => ''];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'send_reset_email') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

        // Validate email
        if (empty($email)) {
            $response['message'] = 'Email address is required.';
            echo json_encode($response);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please enter a valid email address.';
            echo json_encode($response);
            exit;
        }

        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, first_name, last_name 
                                   FROM users 
                                   WHERE email = :email AND status = 'active'");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Don't reveal if email exists or not for security
                $response['success'] = true;
                $response['message'] = 'If an account with this email exists, you will receive a password reset link shortly.';
                echo json_encode($response);
                exit;
            }

            $userId = $user['id'];
            $userName = $user['first_name'] . ' ' . $user['last_name'];

            // Generate secure reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour expiry

            // Delete any existing reset tokens for this user
            $deleteStmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = :email");
            $deleteStmt->execute([':email' => $email]);

            // Insert new reset token
            $insertStmt = $pdo->prepare("INSERT INTO password_reset_tokens 
                                         (email, token, expires_at, created_at) 
                                         VALUES (:email, :token, :expires_at, NOW())");
            $inserted = $insertStmt->execute([
                ':email' => $email,
                ':token' => $resetToken,
                ':expires_at' => $expiresAt
            ]);

            if ($inserted) {
                // Send reset email
                $emailHelper = new EmailHelper();
                $resetLink = SITE_URL . '/reset-password.php?token=' . $resetToken;

                if ($emailHelper->sendPasswordResetEmail($email, $userName, $resetLink)) {
                    $response['success'] = true;
                    $response['message'] = 'Password reset link has been sent to your email address.';
                } else {
                    $response['message'] = 'Failed to send reset email. Please try again later.';
                }
            } else {
                $response['message'] = 'An error occurred. Please try again later.';
            }

        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $response['message'] = 'An error occurred. Please try again later.';
        }

        echo json_encode($response);
        exit;
    }
}
?>


<!-- Your existing HTML code stays exactly the same, just update the JavaScript -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FutureMart</title>
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
            --error-color: #ef4444;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }

        /* Background Animation */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" fill="none"><defs><radialGradient id="g1" cx="50%" cy="50%"><stop offset="0%" stop-color="%23667eea" stop-opacity="0.2"/><stop offset="100%" stop-color="%23667eea" stop-opacity="0"/></radialGradient><radialGradient id="g2" cx="50%" cy="50%"><stop offset="0%" stop-color="%234facfe" stop-opacity="0.15"/><stop offset="100%" stop-color="%234facfe" stop-opacity="0"/></radialGradient></defs><circle cx="300" cy="300" r="200" fill="url(%23g1)"/><circle cx="700" cy="700" r="300" fill="url(%23g2)"/></svg>');
            opacity: 0.6;
            animation: float 25s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(2deg); }
        }

        /* Reset Container */
        .reset-container {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
        }

        .reset-card {
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

        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-3);
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
            background: var(--gradient-3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .reset-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
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
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
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

        .form-control:focus + .input-icon {
            color: var(--accent-color);
        }

        .btn-reset {
            width: 100%;
            background: var(--gradient-3);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(6, 182, 212, 0.3);
            color: white;
        }

        .btn-reset:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
        }

        .back-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .back-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin: 0 1rem;
            transition: color 0.3s ease;
        }

        .back-links a:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        .info-text {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .success-state {
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--gradient-3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
            animation: successPulse 2s ease-in-out infinite;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Input Validation */
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

        @media (max-width: 576px) {
            .reset-container {
                padding: 1rem;
            }

            .reset-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <!-- Initial State -->
            <div id="resetForm">
                <!-- Brand Logo -->
                <div class="brand-logo">
                    <h2><i class="fas fa-rocket"></i> FutureMart</h2>
                </div>

                <div class="reset-icon">
                    <i class="fas fa-key"></i>
                </div>

                <!-- Reset Form -->
                <form id="forgotPasswordForm">
                    <div id="alertContainer"></div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="email" placeholder="Enter your email address" required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                        <div class="invalid-feedback" id="emailError"></div>
                    </div>

                    <button type="submit" class="btn btn-reset" id="resetBtn">
                        <span id="resetText">Send Reset Link</span>
                    </button>
                </form>

                <div class="back-links">
                    <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                    <a href="index.php">Home</a>
                </div>
            </div>

            <!-- Success State -->
            <div id="successState" style="display: none;">
                <div class="success-state">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    
                    <h3>Check Your Email</h3>
                    <p class="info-text">
                        We've sent a password reset link to <strong id="sentEmail"></strong>. 
                        Please check your inbox and follow the instructions to reset your password.
                    </p>

                    <div class="mt-4">
                        <button class="btn btn-reset" onclick="resendEmail()">
                            <i class="fas fa-redo me-2"></i>Resend Email
                        </button>
                    </div>

                    <div class="back-links">
                        <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                        <a href="index.php">Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            
            // Reset validation
            clearValidation();
            
            // Validate email
            if (!email) {
                showFieldError('email', 'Email is required');
                return;
            }
            
            if (!isValidEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
                return;
            }
            
            // Send reset request - Updated to use real backend
            sendResetEmail(email);
        });

        function sendResetEmail(email) {
            const resetBtn = document.getElementById('resetBtn');
            const resetText = document.getElementById('resetText');
            
            // Show loading state
            resetBtn.disabled = true;
            resetText.innerHTML = '<div class="spinner"></div>Sending...';
            
            // Make actual AJAX call to backend
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=send_reset_email&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state
                    document.getElementById('resetForm').style.display = 'none';
                    document.getElementById('successState').style.display = 'block';
                    document.getElementById('sentEmail').textContent = email;
                } else {
                    // Show error
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Network error. Please try again.');
            })
            .finally(() => {
                // Reset button state
                resetBtn.disabled = false;
                resetText.textContent = 'Send Reset Link';
            });
        }

        function resendEmail() {
            const email = document.getElementById('sentEmail').textContent;
            
            // Show temporary loading
            event.target.innerHTML = '<div class="spinner"></div>Sending...';
            event.target.disabled = true;
            
            // Make actual resend request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=send_reset_email&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const notification = document.createElement('div');
                    notification.innerHTML = `
                        <div class="alert alert-success position-fixed" style="top: 20px; right: 20px; z-index: 10000; min-width: 300px;">
                            <i class="fas fa-check-circle me-2"></i>
                            Reset email sent again!
                        </div>
                    `;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Failed to resend email. Please try again.');
            })
            .finally(() => {
                event.target.innerHTML = '<i class="fas fa-redo me-2"></i>Resend Email';
                event.target.disabled = false;
            });
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;
        }

        // Keep all your existing utility functions
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + 'Error');
            
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            errorElement.textContent = message;
        }

        function clearValidation() {
            const field = document.getElementById('email');
            const errorElement = document.getElementById('emailError');
            
            field.classList.remove('is-valid', 'is-invalid');
            errorElement.textContent = '';
            document.getElementById('alertContainer').innerHTML = '';
        }

        // Focus on email input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>