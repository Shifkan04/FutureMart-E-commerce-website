<?php
// =============================================================================
class OTPManager {
    private $pdo;
    private $emailHelper;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->emailHelper = new EmailHelper();
    }
    
    /**
     * Generate and send OTP to user
     */
    public function generateAndSendOTP($userId, $email, $name) {
        try {
            // Check rate limiting
            if (!$this->canSendOTP($userId, $email)) {
                throw new Exception('Too many OTP requests. Please wait before requesting again.');
            }
            
            // Generate 6-digit OTP
            $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));
            
            // Invalidate existing OTPs
            $this->invalidateExistingOTPs($userId);
            
            // Store OTP in database
            $stmt = $this->pdo->prepare("
                INSERT INTO email_verification_otps 
                (user_id, email, otp_hash, expires_at, max_attempts, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $email,
                $otpHash,
                $expiresAt,
                MAX_OTP_ATTEMPTS,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // Send OTP via email
            $emailSent = $this->emailHelper->sendOTPEmail($email, $name, $otp, OTP_EXPIRY_MINUTES);
            
            if (!$emailSent) {
                throw new Exception('Failed to send verification email. Please try again.');
            }
            
            // Log resend attempt
            $this->logResendAttempt($userId, $email);
            
            return [
                'success' => true,
                'message' => 'Verification code sent to your email address.',
                'expires_in_minutes' => OTP_EXPIRY_MINUTES
            ];
            
        } catch (Exception $e) {
            error_log("OTP Generation Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify OTP entered by user
     */
    public function verifyOTP($userId, $enteredOTP) {
        try {
            // Get the latest valid OTP
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_verification_otps 
                WHERE user_id = ? AND is_used = FALSE AND expires_at > NOW() 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $otpRecord = $stmt->fetch();
            
            if (!$otpRecord) {
                return [
                    'success' => false,
                    'message' => 'No valid verification code found. Please request a new code.'
                ];
            }
            
            // Check max attempts
            if ($otpRecord['attempts'] >= $otpRecord['max_attempts']) {
                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts exceeded. Please request a new code.'
                ];
            }
            
            // Increment attempt counter
            $this->incrementAttempts($otpRecord['id']);
            
            // Verify OTP
            if (!password_verify($enteredOTP, $otpRecord['otp_hash'])) {
                $remainingAttempts = $otpRecord['max_attempts'] - ($otpRecord['attempts'] + 1);
                return [
                    'success' => false,
                    'message' => "Invalid verification code. {$remainingAttempts} attempts remaining.",
                    'remaining_attempts' => $remainingAttempts
                ];
            }
            
            // OTP is valid - mark as used and verify user
            $this->markOTPAsUsed($otpRecord['id']);
            $this->markUserAsVerified($userId);
            
            return [
                'success' => true,
                'message' => 'Account verified successfully!'
            ];
            
        } catch (Exception $e) {
            error_log("OTP Verification Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Verification failed. Please try again.'
            ];
        }
    }
    
    private function canSendOTP($userId, $email) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM otp_resend_log 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] < MAX_RESEND_ATTEMPTS;
    }
    
    private function invalidateExistingOTPs($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE email_verification_otps 
            SET is_used = TRUE 
            WHERE user_id = ? AND is_used = FALSE
        ");
        $stmt->execute([$userId]);
    }
    
    private function incrementAttempts($otpId) {
        $stmt = $this->pdo->prepare("
            UPDATE email_verification_otps 
            SET attempts = attempts + 1 
            WHERE id = ?
        ");
        $stmt->execute([$otpId]);
    }
    
    private function markOTPAsUsed($otpId) {
        $stmt = $this->pdo->prepare("
            UPDATE email_verification_otps 
            SET is_used = TRUE, verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$otpId]);
    }
    
    private function markUserAsVerified($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET is_verified = TRUE, email_verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    private function logResendAttempt($userId, $email) {
        $stmt = $this->pdo->prepare("
            INSERT INTO otp_resend_log (user_id, email, ip_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $email, $this->getClientIP()]);
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    public function cleanupExpiredOTPs() {
        $stmt = $this->pdo->prepare("
            DELETE FROM email_verification_otps 
            WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute();
        
        $stmt = $this->pdo->prepare("
            DELETE FROM otp_resend_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
    }
}
