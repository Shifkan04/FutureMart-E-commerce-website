<?php
require_once '../config.php';

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}

$delivery_person_id = $_SESSION['user_id'];

// Fetch delivery person details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
    $stmt->execute([$delivery_person_id]);
    $delivery_person = $stmt->fetch();

    if (!$delivery_person) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Change Password Page Error: " . $e->getMessage());
    die("An error occurred.");
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long';
    } elseif (!password_verify($current_password, $delivery_person['password'])) {
        $_SESSION['error'] = 'Current password is incorrect';
    } else {
        try {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $delivery_person_id]);

            // Log activity
            logUserActivity($delivery_person_id, 'password_change', 'Password changed successfully', $_SERVER['REMOTE_ADDR'] ?? null);

            $_SESSION['success'] = 'Password changed successfully!';
            header('Location: profile.php');
            exit();
        } catch (PDOException $e) {
            error_log("Password Change Error: " . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while changing your password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *, *::before, *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        body {
          font-family: "Nunito", sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          background-image: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);
          background-repeat: no-repeat;
          background-size: cover;
          padding: 20px;
        }

        .container {
          width: 100%;
          max-width: 500px;
          animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(-20px); }
          to { opacity: 1; transform: translateY(0); }
        }

        .password-card {
          background: white;
          border-radius: 15px;
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset,
            0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
          overflow: hidden;
        }

        .card-header {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          padding: 30px;
          text-align: center;
        }

        .card-header-icon {
          width: 70px;
          height: 70px;
          background: white;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto 15px;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header-icon i {
          font-size: 32px;
          color: rgb(73, 57, 113);
        }

        .card-header h1 {
          font-size: 1.8rem;
          font-weight: 700;
          color: #484d53;
          margin: 0;
        }

        .card-header p {
          font-size: 0.95rem;
          color: #6b7280;
          margin: 8px 0 0 0;
        }

        .card-body {
          padding: 30px;
        }

        /* Alert Messages */
        .alert {
          padding: 15px 20px;
          border-radius: 12px;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
          gap: 10px;
          font-weight: 600;
          animation: slideInDown 0.3s ease;
        }

        .alert-error {
          background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(252, 165, 165, 0.1));
          border-left: 4px solid #ef4444;
          color: #7f1d1d;
        }

        @keyframes slideInDown {
          from { transform: translateY(-20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }

        .form-group {
          margin-bottom: 20px;
        }

        .form-label {
          font-size: 0.9rem;
          font-weight: 600;
          color: #484d53;
          margin-bottom: 8px;
          display: block;
        }

        .password-input-wrapper {
          position: relative;
        }

        .form-control {
          width: 100%;
          padding: 12px 45px 12px 15px;
          border: 2px solid #e5e7eb;
          border-radius: 10px;
          font-size: 0.95rem;
          font-family: "Nunito", sans-serif;
          transition: all 0.3s ease;
        }

        .form-control:focus {
          outline: none;
          border-color: rgb(124, 136, 224);
          box-shadow: 0 0 0 3px rgba(124, 136, 224, 0.1);
        }

        .password-toggle {
          position: absolute;
          right: 15px;
          top: 50%;
          transform: translateY(-50%);
          cursor: pointer;
          color: #9ca3af;
          transition: color 0.3s ease;
        }

        .password-toggle:hover {
          color: rgb(73, 57, 113);
        }

        .form-hint {
          font-size: 0.85rem;
          color: #6b7280;
          margin-top: 6px;
          display: block;
        }

        .password-strength {
          margin-top: 8px;
          height: 4px;
          background: #e5e7eb;
          border-radius: 2px;
          overflow: hidden;
        }

        .password-strength-bar {
          height: 100%;
          width: 0%;
          transition: all 0.3s ease;
          border-radius: 2px;
        }

        .password-strength-bar.weak {
          width: 33%;
          background: #ef4444;
        }

        .password-strength-bar.medium {
          width: 66%;
          background: #f59e0b;
        }

        .password-strength-bar.strong {
          width: 100%;
          background: #10b981;
        }

        .password-requirements {
          margin-top: 12px;
          padding: 12px;
          background: #f6f7fb;
          border-radius: 8px;
          font-size: 0.85rem;
        }

        .requirement {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-bottom: 6px;
          color: #6b7280;
        }

        .requirement:last-child {
          margin-bottom: 0;
        }

        .requirement i {
          font-size: 12px;
        }

        .requirement.met {
          color: #10b981;
        }

        .requirement.met i {
          color: #10b981;
        }

        .btn {
          padding: 12px 24px;
          font-size: 0.95rem;
          font-weight: 600;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          width: 100%;
          text-decoration: none;
        }

        .btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
          background: linear-gradient(135deg, rgb(73, 57, 113), rgb(93, 77, 133));
          color: white;
          margin-bottom: 12px;
        }

        .btn-secondary {
          background: white;
          color: #484d53;
          border: 2px solid #e5e7eb;
        }

        .security-tips {
          margin-top: 25px;
          padding: 20px;
          background: linear-gradient(135deg, rgba(124, 136, 224, 0.05), rgba(195, 244, 252, 0.05));
          border-left: 4px solid rgb(124, 136, 224);
          border-radius: 0 10px 10px 0;
        }

        .security-tips h4 {
          font-size: 1rem;
          font-weight: 700;
          color: rgb(73, 57, 113);
          margin-bottom: 12px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .security-tips ul {
          list-style: none;
          padding: 0;
          margin: 0;
        }

        .security-tips li {
          font-size: 0.9rem;
          color: #6b7280;
          margin-bottom: 8px;
          padding-left: 20px;
          position: relative;
        }

        .security-tips li:before {
          content: "•";
          position: absolute;
          left: 0;
          color: rgb(124, 136, 224);
          font-weight: 700;
        }

        @media (max-width: 600px) {
          body {
            padding: 10px;
          }
          
          .card-body {
            padding: 20px;
          }
          
          .card-header {
            padding: 20px;
          }
          
          .card-header h1 {
            font-size: 1.5rem;
          }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="password-card">
            <div class="card-header">
                <div class="card-header-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Change Password</h1>
                <p>Update your password to keep your account secure</p>
            </div>

            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('currentPassword', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6" oninput="checkPasswordStrength()">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('newPassword', this)"></i>
                        </div>
                        <span class="form-hint">Password must be at least 6 characters long</span>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle"></i>
                                <span>At least 6 characters</span>
                            </div>
                            <div class="requirement" id="req-letter">
                                <i class="fas fa-circle"></i>
                                <span>Contains a letter</span>
                            </div>
                            <div class="requirement" id="req-number">
                                <i class="fas fa-circle"></i>
                                <span>Contains a number (recommended)</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required minlength="6" oninput="checkPasswordMatch()">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirmPassword', this)"></i>
                        </div>
                        <span class="form-hint" id="matchHint"></span>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Change Password
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </form>

                <div class="security-tips">
                    <h4>
                        <i class="fas fa-shield-alt"></i>
                        Security Tips
                    </h4>
                    <ul>
                        <li>Use a unique password you don't use elsewhere</li>
                        <li>Mix letters, numbers, and symbols for stronger security</li>
                        <li>Never share your password with anyone</li>
                        <li>Change your password regularly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            
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

        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthBar = document.getElementById('strengthBar');
            const reqLength = document.getElementById('req-length');
            const reqLetter = document.getElementById('req-letter');
            const reqNumber = document.getElementById('req-number');

            // Reset classes
            strengthBar.className = 'password-strength-bar';
            
            // Check length
            if (password.length >= 6) {
                reqLength.classList.add('met');
            } else {
                reqLength.classList.remove('met');
            }

            // Check letter
            if (/[a-zA-Z]/.test(password)) {
                reqLetter.classList.add('met');
            } else {
                reqLetter.classList.remove('met');
            }

            // Check number
            if (/[0-9]/.test(password)) {
                reqNumber.classList.add('met');
            } else {
                reqNumber.classList.remove('met');
            }

            // Calculate strength
            let strength = 0;
            if (password.length >= 6) strength++;
            if (/[a-zA-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            if (strength <= 1) {
                strengthBar.classList.add('weak');
            } else if (strength <= 2) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }

        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchHint = document.getElementById('matchHint');

            if (confirmPassword.length === 0) {
                matchHint.textContent = '';
                matchHint.style.color = '';
                return;
            }

            if (newPassword === confirmPassword) {
                matchHint.textContent = '✓ Passwords match';
                matchHint.style.color = '#10b981';
            } else {
                matchHint.textContent = '✗ Passwords do not match';
                matchHint.style.color = '#ef4444';
            }
        }

        // Validate on submit
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showNotification('error', 'New passwords do not match!');
            }
        });

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                border-radius: 12px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                font-weight: 600;
                font-size: 1rem;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" style="font-size: 20px;"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>