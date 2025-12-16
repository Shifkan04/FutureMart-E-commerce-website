<?php
// export_products.php - Export products to CSV
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

try {
    // Get all products with category names
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.sku, p.description, p.price, p.original_price,
               p.stock_quantity, p.min_stock_level, p.brand, 
               p.is_active, p.is_featured, p.created_at,
               c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC
    ");
    
    $products = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d_H-i-s') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add column headers
    fputcsv($output, [
        'ID',
        'Product Name',
        'SKU',
        'Description',
        'Category',
        'Brand',
        'Price',
        'Original Price',
        'Stock Quantity',
        'Min Stock Level',
        'Status',
        'Featured',
        'Created Date'
    ]);
    
    // Add product data
    foreach ($products as $product) {
        fputcsv($output, [
            $product['id'],
            $product['name'],
            $product['sku'] ?? '',
            $product['description'] ?? '',
            $product['category_name'] ?? 'Uncategorized',
            $product['brand'] ?? '',
            $product['price'],
            $product['original_price'] ?? $product['price'],
            $product['stock_quantity'],
            $product['min_stock_level'],
            $product['is_active'] ? 'Active' : 'Inactive',
            $product['is_featured'] ? 'Yes' : 'No',
            date('Y-m-d H:i:s', strtotime($product['created_at']))
        ]);
    }
    
    fclose($output);
    
    // Log the export activity
    logUserActivity($_SESSION['user_id'], 'product_export', 'Exported ' . count($products) . ' products to CSV');
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    die("Error exporting products. Please try again later.");
}
?>