<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FutureMart</title>
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

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
        }

        .login-card {
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

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-1);
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
            background: var(--gradient-1);
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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
            color: var(--primary-color);
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
            color: var(--primary-color);
        }

        .btn-login {
            width: 100%;
            background: var(--gradient-1);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
            color: white;
        }

        .btn-login:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
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
            border-color: var(--primary-color);
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

        .form-check {
            margin: 1rem 0;
        }

        .form-check-input {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 0.25rem;
        }

        .form-check-input:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .signup-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .signup-link p {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .signup-link a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .signup-link a:hover {
            color: var(--primary-color);
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
            color: var(--primary-color);
            background: rgba(30, 41, 59, 0.9);
            transform: translateY(-2px);
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
            display: inline-block;
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
            display: block;
        }

        .valid-feedback {
            color: #6ee7b7;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: block;
        }

        /* Light Mode Theme */
        body.light-mode {
            --dark-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-light: #1e293b;
            --text-muted: #64748b;
        }

        body.light-mode .login-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
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
        }

        body.light-mode .form-check-input {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        body.light-mode .back-to-home a {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--text-muted);
        }

        body.light-mode .theme-toggle button {
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-light);
        }

        body.light-mode .form-check-input:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }

            .login-card {
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

    <div class="theme-toggle">
        <button onclick="toggleTheme()" class="btn btn-sm btn-outline-light">
            <i class="fas fa-sun"></i>
        </button>
    </div>

    <div class="login-container">
        <div class="login-card">
            <!-- Brand Logo -->
            <div class="brand-logo">
                <h2><i class="fas fa-rocket"></i> FutureMart</h2>
                <p>Welcome back! Please sign in to your account</p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" method="POST">
                <div id="alertContainer"></div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                    <div class="invalid-feedback" id="emailError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password" required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordError"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">
                            Remember me
                        </label>
                    </div>
                    <div class="forgot-password">
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-login" id="loginBtn">
                    <span id="loginText">Sign In</span>
                </button>
            </form>

            <!-- Social Login -->
            <div class="divider">
                <span>Or continue with</span>
            </div>

            <div class="social-login">
                <a href="#" class="btn-social google" onclick="socialLogin('google')">
                    <i class="fab fa-google"></i>
                    Google
                </a>
                <a href="#" class="btn-social facebook" onclick="socialLogin('facebook')">
                    <i class="fab fa-facebook-f"></i>
                    Facebook
                </a>
            </div>

            <!-- Signup Link -->
            <div class="signup-link">
                <p>Don't have an account?</p>
                <a href="signup.php">Create Account</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation + submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe').checked;

            // Reset validation
            clearValidation();

            // Validate form
            if (validateForm(email, password)) {
                login(email, password, rememberMe);
            }
        });

        function validateForm(email, password) {
            let isValid = true;

            // Email validation
            if (!email) {
                showFieldError('email', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
                isValid = false;
            } else {
                showFieldSuccess('email');
            }

            // Password validation
            if (!password) {
                showFieldError('password', 'Password is required');
                isValid = false;
            } else if (password.length < 6) {
                showFieldError('password', 'Password must be at least 6 characters');
                isValid = false;
            } else {
                showFieldSuccess('password');
            }

            return isValid;
        }

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

        function showFieldSuccess(fieldId) {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + 'Error');

            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
            errorElement.textContent = '';
        }

        function clearValidation() {
            const fields = ['email', 'password'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const errorElement = document.getElementById(fieldId + 'Error');

                field.classList.remove('is-valid', 'is-invalid');
                errorElement.textContent = '';
            });

            document.getElementById('alertContainer').innerHTML = '';
        }

        function login(email, password, rememberMe) {
            const loginBtn = document.getElementById('loginBtn');
            const loginText = document.getElementById('loginText');

            loginBtn.disabled = true;
            loginText.innerHTML = '<div class="spinner"></div>Signing In...';

            // Create form data
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            formData.append('submit', '1');
            if (rememberMe) {
                formData.append('remember_me', '1');
            }

            fetch("login_pro.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.status === "success") {
                        showAlert("success", data.message);

                        // Store session info
                        const userData = {
                            email: email,
                            loginTime: new Date().toISOString(),
                            rememberMe: rememberMe,
                            role: data.role // save role
                        };
                        sessionStorage.setItem("userSession", JSON.stringify(userData));

                        // Redirect based on role
                        let redirectUrl = "";
                        switch (data.role) {
                            case "admin":
                                redirectUrl = "admin/dashboard.php";
                                break;
                            case "vendor":
                                redirectUrl = "vendor/dashboard.php";
                                break;
                            case "delivery":
                                redirectUrl = "delivery/dashboard.php";
                                break;
                            case "user":
                            default:
                                redirectUrl = "user/Dashboard.php";
                                break;
                        }

                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 1500);
                    } else {
                        showAlert("error", data.message);
                        loginBtn.disabled = false;
                        loginText.textContent = "Sign In";
                    }
                })
                .catch(error => {
                    console.error('Login error:', error);
                    showAlert("error", "Connection error. Please check your internet and try again.");
                    loginBtn.disabled = false;
                    loginText.textContent = "Sign In";
                });
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            let alertClass, icon;

            if (type === "success") {
                alertClass = "alert-success";
                icon = "fa-check-circle";
            } else {
                alertClass = "alert-danger";
                icon = "fa-exclamation-circle";
            }

            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;

            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        alertContainer.innerHTML = '';
                    }, 300);
                }
            }, 5000);
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

        function socialLogin(provider) {
            showAlert('error', `${provider.charAt(0).toUpperCase() + provider.slice(1)} login is not implemented yet. Please contact support if you need this feature.`);
        }

        function toggleTheme() {
            document.body.classList.toggle("light-mode");
            const themeButton = document.querySelector('.theme-toggle button i');

            if (document.body.classList.contains("light-mode")) {
                // Store theme preference securely in sessionStorage
                sessionStorage.setItem("theme", "light");
                themeButton.classList.remove('fa-sun');
                themeButton.classList.add('fa-moon');
            } else {
                sessionStorage.setItem("theme", "dark");
                themeButton.classList.remove('fa-moon');
                themeButton.classList.add('fa-sun');
            }
        }

        // Initialize page
        document.addEventListener("DOMContentLoaded", function() {
            // Load theme preference
            const savedTheme = sessionStorage.getItem("theme");
            const themeButton = document.querySelector('.theme-toggle button i');

            if (savedTheme === "light") {
                document.body.classList.add("light-mode");
                themeButton.classList.remove('fa-sun');
                themeButton.classList.add('fa-moon');
            }

            // Focus on email field
            document.getElementById('email').focus();

            // Enhanced keyboard navigation
            document.getElementById('email').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('password').focus();
                }
            });

            document.getElementById('password').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('loginForm').dispatchEvent(new Event('submit'));
                }
            });
        });

        // Security: Clear sensitive data on page unload
        window.addEventListener('beforeunload', function() {
            // Clear any temporary form data
            document.getElementById('password').value = '';
        });

        // Prevent form resubmission on browser back
        if (performance.navigation.type === 2) {
            location.reload();
        }
    </script>
</body>

</html>