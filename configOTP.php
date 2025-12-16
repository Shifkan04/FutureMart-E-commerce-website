<?php
/**
 * config.php - Database and SMTP Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'futuremart');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SMTP Configuration for PHPMailer
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email
define('SMTP_PASSWORD', 'your-app-password'); // Your app password
define('SMTP_FROM_EMAIL', 'noreply@futuremart.com');
define('SMTP_FROM_NAME', 'FutureMart');

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 15);
define('MAX_OTP_ATTEMPTS', 5);
define('MAX_RESEND_ATTEMPTS', 3);

// Site Configuration
define('SITE_URL', 'http://localhost/futuremart');
define('SITE_NAME', 'FutureMart');

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

?>