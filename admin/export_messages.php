<?php
// export_messages.php - Export all contact messages to CSV
require_once '../config.php';
requireAdmin();

try {
    // Fetch all messages
    $stmt = $pdo->query("
        SELECT 
            cm.id,
            cm.sender_name,
            cm.sender_email,
            cm.sender_type,
            cm.subject,
            cm.message,
            cm.priority,
            cm.status,
            cm.created_at,
            cm.replied_at,
            u.first_name AS replied_by_first,
            u.last_name AS replied_by_last
        FROM contact_messages cm
        LEFT JOIN users u ON cm.replied_by = u.id
        ORDER BY cm.created_at DESC
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$messages) {
        die('No messages found to export.');
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=contact_messages_export_' . date('Y-m-d_H-i-s') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add column headers
    fputcsv($output, [
        'Message ID',
        'Sender Name',
        'Email',
        'Type',
        'Subject',
        'Message',
        'Priority',
        'Status',
        'Replied By',
        'Created At',
        'Replied At'
    ]);

    // Add message rows
    foreach ($messages as $m) {
        fputcsv($output, [
            $m['id'],
            $m['sender_name'],
            $m['sender_email'],
            ucfirst($m['sender_type']),
            $m['subject'],
            strip_tags($m['message']),
            ucfirst($m['priority']),
            ucfirst(str_replace('_', ' ', $m['status'])),
            trim(($m['replied_by_first'] ?? '') . ' ' . ($m['replied_by_last'] ?? '')),
            date('Y-m-d H:i:s', strtotime($m['created_at'])),
            $m['replied_at'] ? date('Y-m-d H:i:s', strtotime($m['replied_at'])) : 'N/A'
        ]);
    }

    fclose($output);

    // Log export action
    logUserActivity($_SESSION['user_id'], 'contact_export', 'Exported ' . count($messages) . ' contact messages to CSV');
    exit();

} catch (PDOException $e) {
    error_log("Export Messages Error: " . $e->getMessage());
    die("Error exporting messages. Please try again later.");
}
?>
