<?php
/**
 * Export Fund Transactions to CSV
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

if (!$db) {
    die("Database connection failed.");
}

$selected_fy = isset($_GET['fy_id']) ? intval($_GET['fy_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

try {
    $conditions = ["t.financial_year_id = ?"];
    $params = [$selected_fy];

    if (!empty($search)) {
        $conditions[] = "(t.transaction_no LIKE ? OR t.party_name LIKE ? OR t.reference_no LIKE ? OR t.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if (!empty($type_filter)) {
        $conditions[] = "t.transaction_type = ?";
        $params[] = $type_filter;
    }

    if (!empty($status_filter)) {
        $conditions[] = "t.status = ?";
        $params[] = $status_filter;
    }

    $where = implode(" AND ", $conditions);

    $stmt = $db->prepare("
        SELECT t.*, c.name as category_name, u.username as creator_name, app.username as approver_name
        FROM association_fund_transactions t
        LEFT JOIN association_fund_categories c ON c.id = t.category_id
        LEFT JOIN users u ON u.id = t.created_by
        LEFT JOIN users app ON app.id = t.approved_by
        WHERE $where
        ORDER BY t.transaction_date ASC, t.id ASC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // CSV header triggers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="association_fund_transactions_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    
    // Output BOM for Excel UTF-8 Hindi compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, [
        'Transaction No.',
        'Date',
        'Type',
        'Category',
        'Party/Payee',
        'Amount',
        'Payment Mode',
        'Receipt/Voucher',
        'Reference No.',
        'Description',
        'Status',
        'Created By',
        'Approved By'
    ]);

    // Data rows
    foreach ($records as $r) {
        fputcsv($output, [
            $r['transaction_no'],
            $r['transaction_date'],
            strtoupper($r['transaction_type']),
            $r['category_name'],
            $r['party_name'] ?: 'N/A',
            $r['amount'],
            $r['payment_mode'],
            $r['transaction_type'] === 'income' ? ($r['receipt_no'] ?: '-') : ($r['voucher_no'] ?: '-'),
            $r['reference_no'] ?: '-',
            $r['description'],
            strtoupper($r['status']),
            $r['creator_name'],
            $r['approver_name'] ?: '-'
        ]);
    }
    
    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Failed to export fund transactions: " . $e->getMessage());
    die("Export failed: Database error.");
}
