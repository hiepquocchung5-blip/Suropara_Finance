<?php
// Simple Export Handler
require_once __DIR__ . '/../config.php';
requireFinanceAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = $_POST['q'] ?? '';
    $type = $_POST['type'] ?? 'all';
    $status = $_POST['status'] ?? 'all';
    $dateFrom = $_POST['date_from'];
    $dateTo = $_POST['date_to'];
    
    // Build Query (Same logic as history.php)
    $where = ["t.status != 'pending'"]; 
    $params = [];

    $where[] = "t.created_at BETWEEN ? AND ?";
    $params[] = "$dateFrom 00:00:00";
    $params[] = "$dateTo 23:59:59";

    if ($q) {
        $where[] = "(u.username LIKE ? OR u.phone LIKE ? OR t.id = ?)";
        $params[] = "%$q%"; $params[] = "%$q%"; $params[] = $q;
    }
    if ($type !== 'all') { $where[] = "t.type = ?"; $params[] = $type; }
    if ($status !== 'all') { $where[] = "t.status = ?"; $params[] = $status; }

    $whereSQL = implode(" AND ", $where);

    $sql = "
        SELECT t.id, t.created_at, u.username, u.phone, t.type, t.amount, 
               pm.provider_name, t.transaction_last_digits, t.status, 
               a.username as processed_by, t.admin_note
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
        LEFT JOIN admin_users a ON t.processed_by_admin_id = a.id
        WHERE $whereSQL 
        ORDER BY t.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    $filename = "suro_report_" . date('Ymd_Hi') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'User', 'Phone', 'Type', 'Amount', 'Bank', 'Ref', 'Status', 'Admin', 'Note']);
    
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
} else {
    header("Location: history.php");
    exit;
}
?>