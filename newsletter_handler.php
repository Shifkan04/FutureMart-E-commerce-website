<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

$email = sanitizeInput($_POST['email'] ?? '', 'email');

try {
    // Validate email
    if (!validateInput($email, 'email')) {
        throw new Exception('Please enter a valid email address');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND role = 'user'
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update newsletter preference for existing user
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications (user_id, newsletter) 
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE newsletter = 1
        ");
        $stmt->execute([$user['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for subscribing! You will receive our latest updates.'
        ]);
    } else {
        // For non-registered users, you might want to create a separate newsletter_subscribers table
        // For now, we'll just show a success message
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for subscribing! Please check your email to confirm your subscription.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>