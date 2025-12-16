<?php
// export_users.php - Export users to CSV
require_once '../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

try {
    $stmt = $pdo->query("
        SELECT u.id,
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               u.email,
               u.phone,
               u.role,
               u.status,
               u.created_at,
               COUNT(DISTINCT o.id) as order_count,
               COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.role != 'admin'
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    
    $users = $stmt->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d_H-i-s') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'User ID',
        'Full Name',
        'Email',
        'Phone',
        'Role',
        'Status',
        'Order Count',
        'Total Spent',
        'Registration Date'
    ]);
    
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['full_name'],
            $user['email'],
            $user['phone'] ?? 'N/A',
            ucfirst($user['role']),
            ucfirst($user['status']),
            $user['order_count'],
            '$' . number_format($user['total_spent'], 2),
            date('Y-m-d H:i:s', strtotime($user['created_at']))
        ]);
    }
    
    fclose($output);
    
    logUserActivity($_SESSION['user_id'], 'user_export', 'Exported ' . count($users) . ' users to CSV');
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    die("Error exporting users.");
}
?>