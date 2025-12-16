<?php
// get_vendor.php - Get single vendor data for editing
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Vendor ID required']);
    exit();
}

$vendorId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
               CONCAT(u.first_name, ' ', u.last_name) AS vendor_name,
               COUNT(DISTINCT p.id) AS product_count
        FROM users u
        LEFT JOIN products p 
            ON p.brand = CONCAT(u.first_name, ' ', u.last_name) -- TEMP fix
        WHERE u.id = ? AND u.role = 'vendor'
        GROUP BY u.id
    ");
    
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        http_response_code(404);
        echo json_encode(['error' => 'Vendor not found']);
        exit();
    }
    
    // Remove sensitive data
    unset($vendor['password']);
    unset($vendor['remember_token']);
    
    echo json_encode($vendor);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>