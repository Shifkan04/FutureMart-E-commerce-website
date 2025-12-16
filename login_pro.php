<?php
// login_pro.php - Full PDO Version
require_once 'db.php'; // Make sure $pdo is your PDO instance

// Start session with security settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed."
    ]);
    exit;
}

try {
    // Get and sanitize input
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    $remember_me = isset($_POST['remember_me']);

    // Validate inputs
    if (empty($email)) throw new Exception('Email is required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Please enter a valid email address.');
    if (empty($password)) throw new Exception('Password is required.');
    if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters long.');

    // Check PDO connection
    if (!$pdo) throw new Exception('Database connection failed.');

    // Rate limiting
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_key = 'login_attempts_' . hash('sha256', $ip_address);

    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => time()];
    }

    if (time() - $_SESSION[$rate_limit_key]['time'] > 900) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => time()];
    }

    if ($_SESSION[$rate_limit_key]['count'] >= 5) {
        throw new Exception('Too many login attempts. Please try again in 15 minutes.');
    }

    // Fetch user
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password, role, status, failed_attempts, last_failed_attempt, remember_token, remember_expires FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Check account lockout
        $max_failed_attempts = 5;
        $lockout_time = 15 * 60;

        if ($user['failed_attempts'] >= $max_failed_attempts && (time() - strtotime($user['last_failed_attempt'])) < $lockout_time) {
            throw new Exception('Account temporarily locked due to too many failed attempts. Please try again later.');
        }

        // Check account status
        if (isset($user['status']) && $user['status'] !== 'active') {
            throw new Exception('Your account is not active. Please contact support.');
        }

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Reset failed attempts
            $reset_stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, last_failed_attempt = NULL WHERE id = ?");
            $reset_stmt->execute([$user['id']]);

            // Regenerate session ID
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] ?? $user['last_name'] ?? 'User';
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            // Remember Me
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60);

                $remember_stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expires = ? WHERE id = ?");
                $remember_stmt->execute([$token, $expires, $user['id']]);

                setcookie(
                    'remember_token',
                    $token,
                    $expires,
                    '/',
                    '',
                    isset($_SERVER['HTTPS']),
                    true
                );
            }

            // Reset rate limiting
            $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => time()];

            // Log success
            error_log("User login successful: " . $user['email'] . " from IP: " . $ip_address);

            echo json_encode([
                "status" => "success",
                "message" => "Login successful! Redirecting...",
                "role" => $_SESSION['user_role']
            ]);
        } else {
            // Invalid password - increment failed attempts
            $failed_attempts = ($user['failed_attempts'] ?? 0) + 1;
            $update_stmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, last_failed_attempt = NOW() WHERE id = ?");
            $update_stmt->execute([$failed_attempts, $user['id']]);

            // Increment rate limiting
            $_SESSION[$rate_limit_key]['count']++;
            $_SESSION[$rate_limit_key]['time'] = time();

            error_log("Failed login attempt for: " . $email . " from IP: " . $ip_address);
            throw new Exception('Invalid email or password.');
        }
    } else {
        // User not found
        $_SESSION[$rate_limit_key]['count']++;
        $_SESSION[$rate_limit_key]['time'] = time();

        error_log("Failed login attempt for non-existent user: " . $email . " from IP: " . $ip_address);
        throw new Exception('Invalid email or password.');
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
