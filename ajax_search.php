<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_POST['query'])) {
    echo json_encode([]);
    exit;
}

$query = trim($_POST['query']);

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, price, image, rating 
        FROM products 
        WHERE is_active = 1 
        AND (name LIKE ? OR description LIKE ? OR brand LIKE ?)
        ORDER BY rating DESC, name ASC
        LIMIT 10
    ");
    
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode([]);
}
?>