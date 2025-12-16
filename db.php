<?php
// config.php - OTP System Configuration

// Database Configuration (use your existing db.php values)
define('DB_HOST', 'localhost');
define('DB_NAME', 'futuremart');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SMTP Configuration - UPDATE THESE WITH YOUR EMAIL CREDENTIALS
define('SMTP_HOST', 'smtp.gmail.com'); // Your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'futuremart273@gmail.com'); // Your email
define('SMTP_PASSWORD', 'xpqj ouks rswa xydj'); // Your Gmail app password
define('SMTP_FROM_EMAIL', 'futuremart273@gmail.com');
define('SMTP_FROM_NAME', 'FutureMart');

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 15);
define('MAX_OTP_ATTEMPTS', 5);
define('MAX_RESEND_ATTEMPTS', 3);

// Site Configuration
define('SITE_URL', 'http://localhost/futuremart');
define('SITE_NAME', 'Future Mart');

// Create PDO connection for OTP system
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
    error_log("PDO Connection failed: " . $e->getMessage());
    // Don't die here, just log the error
}
?>