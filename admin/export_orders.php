<?php
// export_orders.php - Export orders to CSV
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

try {
    // Get all orders with customer details
    $stmt = $pdo->query("
        SELECT o.id, o.order_number, o.status, o.payment_status, o.payment_method,
               o.total_amount, o.subtotal, o.tax_amount, o.shipping_amount, 
               o.discount_amount, o.tracking_number, o.created_at,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
    ");
    
    $orders = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=orders_export_' . date('Y-m-d_H-i-s') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add column headers
    fputcsv($output, [
        'Order ID',
        'Order Number',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Order Date',
        'Status',
        'Payment Status',
        'Payment Method',
        'Subtotal',
        'Tax',
        'Shipping',
        'Discount',
        'Total Amount',
        'Tracking Number'
    ]);
    
    // Add order data
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['order_number'],
            $order['customer_name'],
            $order['customer_email'],
            $order['customer_phone'] ?? '',
            date('Y-m-d H:i:s', strtotime($order['created_at'])),
            ucfirst($order['status']),
            ucfirst($order['payment_status']),
            $order['payment_method'] ?? 'N/A',
            $order['subtotal'],
            $order['tax_amount'],
            $order['shipping_amount'],
            $order['discount_amount'],
            $order['total_amount'],
            $order['tracking_number'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
    
    // Log the export activity
    logUserActivity($_SESSION['user_id'], 'order_export', 'Exported ' . count($orders) . ' orders to CSV');
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    die("Error exporting orders. Please try again later.");
}
?>