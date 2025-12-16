<?php
/**
 * cleanup.php - Cleanup expired OTPs (run as cron job)
 * Add to crontab: 0 */6 * * * /usr/bin/php /path/to/cleanup.php
 */

require_once 'config.php';
require_once 'EmailHelper.php';
require_once 'OTPManager.php';

try {
    $otpManager = new OTPManager($pdo);
    $otpManager->cleanupExpiredOTPs();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed successfully\n";
    
    // Optional: Log cleanup stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expired_otps,
            COUNT(CASE WHEN is_used = TRUE THEN 1 END) as used_otps,
            COUNT(*) as total_otps
        FROM email_verification_otps 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    echo "Stats - Expired: {$stats['expired_otps']}, Used: {$stats['used_otps']}, Total: {$stats['total_otps']}\n";
    
} catch (Exception $e) {
    echo "[ERROR] Cleanup failed: " . $e->getMessage() . "\n";
    error_log("OTP Cleanup Error: " . $e->getMessage());
}
?>