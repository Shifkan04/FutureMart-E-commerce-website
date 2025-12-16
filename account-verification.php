<?php
session_start();

// Check if user should be here
if (!isset($_SESSION['verification_pending']) || !isset($_SESSION['user_email'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - FutureMart</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
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
            background: linear-gradient(135deg, rgba(67, 233, 123, 0.1) 0%, rgba(56, 249, 215, 0.1) 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            z-index: -2;
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

        .verification-container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }

        .verification-card {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.8s ease-out;
            p { 
                margin-bottom: 0;
                color: var(--text-muted)!important;
            }
        }

        .verification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-4);
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

        .verification-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--gradient-4);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .brand-logo h2 {
            font-weight: 700;
            font-size: 1.8rem;
            background: var(--gradient-4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .verification-code {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .code-input {
            width: 50px;
            height: 50px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-light);
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            outline: none;
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(67, 233, 123, 0.2);
            background: rgba(15, 23, 42, 0.9);
        }

        .btn-verify {
            width: 100%;
            background: var(--gradient-4);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-verify:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(67, 233, 123, 0.3);
            color: white;
        }

        .btn-verify:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
        }

        .resend-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .resend-text {
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .btn-resend {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .btn-resend:hover:not(:disabled) {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-resend:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .countdown {
            color: var(--warning-color);
            font-weight: 600;
        }

        .back-links {
            margin-top: 2rem;
        }

        .back-links a {
            color: var(--primary-color);
            text-decoration: none;
            margin: 0 1rem;
            transition: color 0.3s ease;
        }

        .back-links a:hover {
            color: var(--success-color);
            text-decoration: underline;
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

        @media (max-width: 576px) {
            .verification-container {
                padding: 1rem;
            }

            .verification-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .code-input {
                width: 45px;
                height: 45px;
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="verification-container">
        <div class="verification-card">
            <div class="brand-logo">
                <h2><i class="fas fa-rocket"></i> FutureMart</h2>
            </div>

            <div class="verification-icon">
                <i class="fas fa-shield-alt"></i>
            </div>

            <h3 class="mb-3">Verify Your Account</h3>
            <p class="text-muted mb-4">
                We've sent a 6-digit verification code to<br>
                <strong><?php echo htmlspecialchars($_SESSION['user_email']); ?></strong>
            </p>

            <form id="verificationForm">
                <div id="alertContainer"></div>

                <div class="verification-code">
                    <input type="text" class="code-input" maxlength="1" id="code1" data-index="0">
                    <input type="text" class="code-input" maxlength="1" id="code2" data-index="1">
                    <input type="text" class="code-input" maxlength="1" id="code3" data-index="2">
                    <input type="text" class="code-input" maxlength="1" id="code4" data-index="3">
                    <input type="text" class="code-input" maxlength="1" id="code5" data-index="4">
                    <input type="text" class="code-input" maxlength="1" id="code6" data-index="5">
                </div>

                <button type="submit" class="btn btn-verify" id="verifyBtn">
                    <span id="verifyText">Verify Account</span>
                </button>
            </form>

            <div class="resend-section">
                <p class="resend-text">Didn't receive the code?</p>
                <button class="btn btn-resend" id="resendBtn" onclick="resendCode()">
                    <span id="resendText">Resend Code</span>
                </button>
                <div id="countdown" class="mt-2"></div>
            </div>

            <div class="back-links">
                <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                <a href="index.php">Home</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let resendCountdown = 60;
        let countdownInterval;

        document.addEventListener('DOMContentLoaded', function() {
            setupCodeInputs();
            startResendCountdown();
            document.getElementById('code1').focus();
        });

        function setupCodeInputs() {
            const inputs = document.querySelectorAll('.code-input');

            inputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    // Only allow digits
                    this.value = this.value.replace(/[^0-9]/g, '');

                    // Move to next input
                    if (this.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }

                    // Check if all inputs are filled
                    checkFormComplete();
                });

                input.addEventListener('keydown', function(e) {
                    // Handle backspace
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                    }

                    // Handle arrow keys
                    if (e.key === 'ArrowLeft' && index > 0) {
                        inputs[index - 1].focus();
                    }
                    if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = e.clipboardData.getData('text').replace(/[^0-9]/g, '');

                    if (paste.length === 6) {
                        inputs.forEach((input, i) => {
                            input.value = paste[i] || '';
                        });
                        checkFormComplete();
                    }
                });
            });
        }

        function checkFormComplete() {
            const inputs = document.querySelectorAll('.code-input');
            const code = Array.from(inputs).map(input => input.value).join('');

            if (code.length === 6) {
                // Auto-submit when complete
                setTimeout(() => {
                    verifyCode(code);
                }, 300);
            }
        }

        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const inputs = document.querySelectorAll('.code-input');
            const code = Array.from(inputs).map(input => input.value).join('');

            if (code.length !== 6) {
                showAlert('error', 'Please enter the complete 6-digit code');
                return;
            }

            verifyCode(code);
        });

        function verifyCode(code) {
            const verifyBtn = document.getElementById('verifyBtn');
            const verifyText = document.getElementById('verifyText');

            // Show loading state
            verifyBtn.disabled = true;
            verifyText.innerHTML = '<div class="spinner"></div>Verifying...';

            // Clear previous alerts
            document.getElementById('alertContainer').innerHTML = '';

            // Send verification request
            fetch('verify.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'otp=' + encodeURIComponent(code)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);

                        // Update verification icon
                        document.querySelector('.verification-icon i').className = 'fas fa-check';

                        // Redirect after success
                        setTimeout(() => {
                            window.location.href = data.redirect || 'login.php';
                        }, 2000);
                    } else {
                        showAlert('error', data.message);

                        // Clear inputs on error
                        document.querySelectorAll('.code-input').forEach(input => {
                            input.value = '';
                            input.style.borderColor = 'var(--error-color)';
                        });

                        // Focus first input
                        document.getElementById('code1').focus();

                        // Reset border colors after animation
                        setTimeout(() => {
                            document.querySelectorAll('.code-input').forEach(input => {
                                input.style.borderColor = '';
                            });
                        }, 2000);
                    }
                })
                .catch(error => {
                    console.error('Verification error:', error);
                    showAlert('error', 'Network error. Please try again.');
                })
                .finally(() => {
                    // Reset button
                    verifyBtn.disabled = false;
                    verifyText.textContent = 'Verify Account';
                });
        }

        function resendCode() {
            const resendBtn = document.getElementById('resendBtn');
            const resendText = document.getElementById('resendText');

            if (resendCountdown > 0) return;

            // Show loading
            resendBtn.disabled = true;
            resendText.innerHTML = '<div class="spinner"></div>Sending...';

            fetch('resend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);

                        // Reset countdown
                        resendCountdown = 60;
                        startResendCountdown();
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Resend error:', error);
                    showAlert('error', 'Failed to resend code. Please try again.');
                })
                .finally(() => {
                    // Reset button
                    resendBtn.disabled = false;
                    resendText.textContent = 'Resend Code';
                });
        }

        function startResendCountdown() {
            const resendBtn = document.getElementById('resendBtn');
            const countdownDiv = document.getElementById('countdown');

            if (resendCountdown <= 0) {
                resendBtn.disabled = false;
                countdownDiv.innerHTML = '';
                return;
            }

            resendBtn.disabled = true;
            countdownDiv.innerHTML = `<span class="countdown">Resend available in ${resendCountdown}s</span>`;

            countdownInterval = setInterval(() => {
                resendCountdown--;

                if (resendCountdown <= 0) {
                    clearInterval(countdownInterval);
                    resendBtn.disabled = false;
                    countdownDiv.innerHTML = '';
                } else {
                    countdownDiv.innerHTML = `<span class="countdown">Resend available in ${resendCountdown}s</span>`;
                }
            }, 1000);
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

            // Auto-hide after 5 seconds for non-success messages
            if (type !== 'success') {
                setTimeout(() => {
                    const alert = alertContainer.querySelector('.alert');
                    if (alert) {
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            alertContainer.innerHTML = '';
                        }, 300);
                    }
                }, 5000);
            }
        }
    </script>
</body>

</html>