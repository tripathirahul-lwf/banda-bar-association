<?php
/**
 * Export Notices Register to CSV
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce staff auth
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'mahasachiv'])) {
    http_response_code(403);
    exit("Direct access forbidden. Unauthorized access.");
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

$db = Database::getConnection();
if (!$db) {
    exit("Database connection failed.");
}

try {
    $conditions = ["1=1"];
    $params = [];

    if (!empty($search)) {
        $conditions[] = "(notice_no LIKE ? OR title LIKE ? OR title_hindi LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($status_filter)) {
        $conditions[] = "status = ?";
        $params[] = $status_filter;
    }

    if (!empty($category_filter)) {
        $conditions[] = "category = ?";
        $params[] = $category_filter;
    }

    $where = implode(" AND ", $conditions);

    $stmt = $db->prepare("
        SELECT n.*, u.username as creator_name, app.username as approver_name
        FROM notices n
        LEFT JOIN users u ON u.id = n.created_by
        LEFT JOIN users app ON app.id = n.approved_by
        WHERE $where
        ORDER BY n.id DESC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Clean buffer
    if (ob_get_length()) ob_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Notice_Register_Export_' . date('Ymd_His') . '.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Emit UTF-8 BOM byte sequence so Excel reads Devanagari Hindi characters properly
    fwrite($output, "\xEF\xBB\xBF");

    // CSV headers
    fputcsv($output, [
        'Sr. No.',
        'Notice No.',
        'Title',
        'Hindi Title',
        'Category',
        'Priority',
        'Visibility',
        'Status',
        'Publish Date',
        'Created By',
        'Approved By',
        'Created At'
    ]);

    $i = 1;
    foreach ($records as $row) {
        fputcsv($output, [
            $i++,
            $row['notice_no'] ?: 'Auto-Generated',
            $row['title'],
            $row['title_hindi'] ?: 'N/A',
            $row['category'],
            $row['priority'],
            $row['visibility'],
            $row['status'],
            $row['publish_at'] ? date('d-m-Y H:i', strtotime($row['publish_at'])) : 'Immediate',
            $row['creator_name'],
            $row['approver_name'] ?: 'N/A',
            date('d-m-Y H:i', strtotime($row['created_at']))
        ]);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Failed exporting notices CSV: " . $e->getMessage());
    exit("Export failed.");
}
