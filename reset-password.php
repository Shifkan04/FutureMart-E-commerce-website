<?php
session_start();
require_once 'db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$validToken = false;
$email = '';

// Verify token
if ($token) {
    $stmt = $pdo->prepare("SELECT email 
                           FROM password_reset_tokens 
                           WHERE token = :token 
                             AND expires_at > NOW() 
                             AND used = 0");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $validToken = true;
        $email = $row['email'];
    } else {
        $error = 'Invalid or expired reset link.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $newPassword)) {
        $error = 'Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.';
    } else {
        try {
            $pdo->beginTransaction();

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
            $updateStmt->execute([
                ':password' => $hashedPassword,
                ':email' => $email
            ]);

            // Mark token as used
            $tokenStmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = :token");
            $tokenStmt->execute([':token' => $token]);

            $pdo->commit();

            $success = 'Password updated successfully! You can now log in with your new password.';
            $validToken = false; // Prevent further submissions
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #ec4899;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            z-index: -1;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

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
            p { margin-bottom: 0 ;
                color: var(--text-muted)!important;}
        }

        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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

        .reset-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-2);
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

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 1;
        }

        .form-control:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
            color: var(--text-light);
            outline: none;
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
            margin-right: 15px;
        }

        .password-toggle:hover {
            color: var(--secondary-color);
        }

        .btn-reset {
            width: 100%;
            background: var(--gradient-2);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-reset:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.3);
            color: white;
        }

        .btn-reset:disabled {
            opacity: 0.6;
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

        .strength-weak { background: var(--error-color); width: 25%; }
        .strength-fair { background: var(--warning-color); width: 50%; }
        .strength-good { background: var(--accent-color); width: 75%; }
        .strength-strong { background: var(--success-color); width: 100%; }

        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .alert {
            border-radius: 12px;
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
            color: #fca5a5;
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
            margin: 0 1rem;
        }

        .back-links a:hover {
            color: var(--accent-color);
            text-decoration: underline;
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

        .form-control.is-invalid {
            border-color: var(--error-color);
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
        }

        .invalid-feedback {
            color: #fca5a5;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: block;
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="brand-logo">
                <h2><i class="fas fa-rocket"></i> FutureMart</h2>
            </div>

            <?php if (!$validToken && !$success): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error ?: 'Invalid or expired reset link.') ?>
                </div>
                <div class="text-center">
                    <button onclick="location.href='forgot-password.php'" class="btn btn-reset">Request New Reset Link</button>
                </div>
                
            <?php elseif ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center">
                    <button onclick="location.href='login.php'" class="btn btn-reset">Go to Login</button>
                </div>
                
            <?php else: ?>
                <div class="reset-icon">
                    <i class="fas fa-lock"></i>
                </div>

                <h3 class="mb-3 text-center">Reset Your Password</h3>
                <p class="text-muted mb-4 text-center">Create a new secure password for your account</p>

                <div id="alertContainer"></div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="resetPasswordForm">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="newPassword" class="form-control" 
                                   placeholder="Enter new password" required minlength="8">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                                <i class="fas fa-eye" id="newPasswordToggleIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText"></div>
                        </div>
                        <div class="invalid-feedback" id="newPasswordError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" 
                                   placeholder="Confirm new password" required minlength="8">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                                <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback" id="confirmPasswordError"></div>
                    </div>

                    <button type="submit" class="btn btn-reset" id="resetBtn">
                        <span id="resetText">Update Password</span>
                    </button>
                </form>
            <?php endif; ?>

            <div class="back-links">
                <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                <a href="index.php">Home</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (validatePasswords(newPassword, confirmPassword)) {
                // Allow form to submit naturally to PHP
                this.submit();
            }
        });

        function validatePasswords(newPassword, confirmPassword) {
            clearErrors();
            let isValid = true;

            const strength = checkPasswordStrength(newPassword);

            if (strength.score < 3) {
                showFieldError('newPassword', 'Password is too weak. Please choose a stronger password.');
                isValid = false;
            }

            if (newPassword !== confirmPassword) {
                showFieldError('confirmPassword', 'Passwords do not match');
                isValid = false;
            }

            return isValid;
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + 'Error');
            
            field.classList.add('is-invalid');
            errorElement.textContent = message;
        }

        function clearErrors() {
            ['newPassword', 'confirmPassword'].forEach(id => {
                const field = document.getElementById(id);
                const errorElement = document.getElementById(id + 'Error');
                field.classList.remove('is-invalid');
                errorElement.textContent = '';
            });
        }

        function checkPasswordStrength(password) {
            let score = 0;

            if (password.length >= 8) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^A-Za-z0-9]/)) score++;

            return { score };
        }

        function updatePasswordStrength(password) {
            const strength = checkPasswordStrength(password);
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

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

        // Event listeners
        document.getElementById('newPassword')?.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });

        document.getElementById('confirmPassword')?.addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const errorElement = document.getElementById('confirmPasswordError');
            
            if (this.value && this.value !== newPassword) {
                this.classList.add('is-invalid');
                errorElement.textContent = 'Passwords do not match';
            } else {
                this.classList.remove('is-invalid');
                errorElement.textContent = '';
            }
        });
    </script>
</body>

</html>