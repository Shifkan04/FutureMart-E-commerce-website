<?php
require_once '../config.php';

// prevent any unwanted whitespace or error text
ob_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['delivery_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$delivery_id = filter_var($data['delivery_id'], FILTER_VALIDATE_INT);
$new_status = trim($data['status']);
$delivery_person_id = $_SESSION['user_id'];

$valid_statuses = ['assigned', 'picked_up', 'in_transit', 'delivered', 'failed'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, status, order_id 
        FROM delivery_assignments 
        WHERE id = ? AND delivery_person_id = ?
    ");
    $stmt->execute([$delivery_id, $delivery_person_id]);
    $delivery = $stmt->fetch();

    if (!$delivery) {
        echo json_encode(['success' => false, 'message' => 'Delivery not found or access denied']);
        exit();
    }

    $pdo->beginTransaction();

    $update_fields = ['status' => $new_status];
    if ($new_status === 'picked_up') $update_fields['picked_up_at'] = date('Y-m-d H:i:s');
    if ($new_status === 'delivered') $update_fields['delivered_at'] = date('Y-m-d H:i:s');

    $set_clause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update_fields)));
    $params = array_values($update_fields);
    $params[] = $delivery_id;

    $stmt = $pdo->prepare("UPDATE delivery_assignments SET $set_clause, updated_at = NOW() WHERE id = ?");
    $stmt->execute($params);

    if ($new_status === 'delivered') {
        $pdo->prepare("
            UPDATE orders SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() WHERE id = ?
        ")->execute([$delivery['order_id']]);
    } elseif ($new_status === 'in_transit') {
        $pdo->prepare("
            UPDATE orders SET status = 'shipped', shipped_at = NOW(), updated_at = NOW() WHERE id = ?
        ")->execute([$delivery['order_id']]);
    }

    logUserActivity($delivery_person_id, 'delivery_status_update', "Updated to {$new_status} for order {$delivery['order_id']}");

    $pdo->commit();

    // ✅ final clean JSON output
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Delivery status updated successfully',
        'new_status' => $new_status
    ]);
    exit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    error_log("Delivery update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}
