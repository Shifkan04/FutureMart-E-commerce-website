<?php
// export_vendors.php - Export vendors to CSV
require_once '../config.php';

// Make sure only logged-in admins can export
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

try {
    // Ensure PDO throws exceptions for any SQL errors
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /*
     * Adjust this query depending on your DB structure:
     * If 'products' table has a 'vendor_id' column → use that.
     * Otherwise, fallback to the brand name match.
     */
    $query = "
        SELECT 
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) AS vendor_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            COUNT(DISTINCT p.id) AS product_count
        FROM users u
        LEFT JOIN products p 
            ON (
                (p.vendor_id = u.id) 
                OR 
                (p.brand = CONCAT(u.first_name, ' ', u.last_name))
            )
        WHERE u.role = 'vendor'
        GROUP BY 
            u.id, u.first_name, u.last_name, 
            u.email, u.phone, u.status, u.created_at
        ORDER BY u.created_at DESC
    ";

    $stmt = $pdo->query($query);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no vendors found
    if (empty($vendors)) {
        die("No vendors found to export.");
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=vendors_export_' . date('Y-m-d_H-i-s') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM (for Excel compatibility)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Add CSV header columns
    fputcsv($output, [
        'Vendor ID',
        'Vendor Name',
        'Email',
        'Phone',
        'Status',
        'Product Count',
        'Registration Date'
    ]);

    // Loop through vendor data and write to CSV
    foreach ($vendors as $vendor) {
        fputcsv($output, [
            $vendor['id'],
            $vendor['vendor_name'],
            $vendor['email'],
            $vendor['phone'] ?: 'N/A',
            ucfirst($vendor['status']),
            $vendor['product_count'],
            date('Y-m-d H:i:s', strtotime($vendor['created_at']))
        ]);
    }

    fclose($output);

    // Log export activity
    if (function_exists('logUserActivity') && isset($_SESSION['user_id'])) {
        logUserActivity($_SESSION['user_id'], 'vendor_export', 'Exported ' . count($vendors) . ' vendors to CSV');
    }

    exit();

} catch (PDOException $e) {
    // Log to PHP error log
    error_log("Vendor export failed: " . $e->getMessage());
    die("Error exporting vendors. Please try again later.<br><br><strong>Debug Hint:</strong> Check your 'products' table columns (vendor_id or brand).");
}
?>
