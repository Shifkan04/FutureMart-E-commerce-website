<?php
// config.php - Configuration Constants (Optional)

// Fixed: Start session with check to prevent double session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'futuremart');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutes in seconds
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes in seconds
define('REMEMBER_TOKEN_EXPIRY', 30 * 24 * 60 * 60); // 30 days in seconds

// Application Configuration
define('APP_NAME', 'FutureMart');
define('APP_URL', 'http://localhost/Future%20Mart');
define('DEFAULT_TIMEZONE', 'Asia/Colombo'); // Sri Lanka timezone
define('APP_DESC', 'Admin Panel');

// Site Configuration
define('SITE_URL', 'http://localhost/futuremart');
define('SITE_NAME', 'FutureMart');
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');


// Email Configuration (if you plan to add email features)
// SMTP Configuration for PHPMailer
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'futuremart273@gmail.com'); // Your email
define('SMTP_PASSWORD', 'xpqj ouks rswa xydj'); // Your app password
define('SMTP_FROM_EMAIL', 'futuremart273@gmail.com');
define('SMTP_FROM_NAME', 'FutureMart');

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 15);
define('MAX_OTP_ATTEMPTS', 5);
define('MAX_RESEND_ATTEMPTS', 3);

// Pagination
define('ITEMS_PER_PAGE', 10);

// Environment
define('ENVIRONMENT', 'development'); // development, staging, production

// Initialize PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Error Reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Security Headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Only add HSTS in production with HTTPS
    if (ENVIRONMENT === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// Custom error handler function
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (ENVIRONMENT !== 'development') {
        error_log("Error: [$errno] $errstr in $errfile on line $errline");
        // Don't show detailed errors in production
        return true;
    }
    return false; // Let PHP handle the error in development
}

// Set custom error handler
set_error_handler('customErrorHandler');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check session timeout
function checkSessionTimeout() {
    if (isLoggedIn()) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            // Session expired
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// Function to generate secure random token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to sanitize input safely
function sanitizeInput($input, $type = 'string') {
    if ($input === null || $input === '') {
        return null;
    }

    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}


// Function to validate input
function validateInput($input, $type, $required = true) {
    if ($required && empty($input)) {
        return false;
    }
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false;
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) !== false;
        case 'password':
            return strlen($input) >= 6;
        case 'phone':
            return preg_match('/^[0-9+\-\s()]+$/', $input);
        default:
            return true;
    }
}

// Function to log security events
function logSecurityEvent($event, $details = '') {
    $log_entry = date('Y-m-d H:i:s') . ' - ' . $event . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!empty($details)) {
        $log_entry .= ' - Details: ' . $details;
    }
    $log_entry .= PHP_EOL;
    
    $log_file = __DIR__ . '/logs/security.log';
    $log_dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}


// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Fixed: Changed $_SESSION['role'] to $_SESSION['user_role'] to match login code
function isAdmin() {
    return isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin';
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../login.php');
        exit();
    }
}

function logUserActivity($userId, $activityType, $description, $ipAddress = null) {
    global $pdo;
    
    if (!$ipAddress) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, activity_description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $activityType, $description, $ipAddress]);
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function uploadFile($file, $directory = 'general') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $uploadDir = UPLOAD_PATH . $directory . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $directory . '/' . $filename;
    }
    
    return false;
}

// Pagination helper
function getPaginationData($totalItems, $itemsPerPage = 20, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// Global settings function
function getSetting($key, $default = null) {
    global $pdo;

    try {
        // If you have a "settings" table in DB
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row && isset($row['setting_value'])) {
            return $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("getSetting error: " . $e->getMessage());
    }

    // Fallback to default value if nothing found
    return $default;
}

ini_set('display_errors', 0);
error_reporting(0);

?>