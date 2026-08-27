<?php
/**
 * Export ID Card Applications to CSV (Supports Devanagari Hindi Characters)
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Auth Check
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'mahasachiv'])) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';

$db = Database::getConnection();
$records = [];

if ($db) {
    try {
        $conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $conditions[] = "(m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($status_filter)) {
            $conditions[] = "a.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($type_filter)) {
            $conditions[] = "a.application_type = ?";
            $params[] = $type_filter;
        }
        
        $where_clause = '';
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }
        
        $query = "
            SELECT a.id, m.full_name, m.membership_no, m.enrollment_no, a.application_type, a.status, a.submitted_at 
            FROM id_card_applications a
            JOIN members m ON m.id = a.member_id
            $where_clause
            ORDER BY a.id DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to export ID card application: " . $e->getMessage());
    }
}

// Clean buffer
if (ob_get_length()) ob_clean();

// Set Headers for CSV Download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="id_card_applications_' . date('Ymd_His') . '.csv"');

// Create stream handle
$output = fopen('php://output', 'w');

// Emit UTF-8 BOM to prevent Excel from mangling Devanagari Hindi characters
fwrite($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'आवेदन आईडी',
    'अधिवक्ता का नाम',
    'सदस्यता संख्या',
    'पंजीकरण संख्या',
    'आवेदन प्रकार',
    'स्थिति',
    'प्रस्तुत तिथि'
]);

// Data rows
foreach ($records as $r) {
    fputcsv($output, [
        $r['id'],
        $r['full_name'],
        $r['membership_no'],
        $r['enrollment_no'],
        ucfirst($r['application_type']),
        ucfirst($r['status']),
        $r['submitted_at']
    ]);
}

fclose($output);
exit();
