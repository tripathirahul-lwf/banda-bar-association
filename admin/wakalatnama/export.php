<?php
/**
 * Export Member-wise Download Records to CSV (Hindi Character Compatibility)
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
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$db = Database::getConnection();
$records = [];

if ($db) {
    try {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ? OR w.title LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($category_filter)) {
            $conditions[] = "w.category = ?";
            $params[] = $category_filter;
        }

        if (!empty($start_date)) {
            $conditions[] = "DATE(d.downloaded_at) >= ?";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $conditions[] = "DATE(d.downloaded_at) <= ?";
            $params[] = $end_date;
        }

        $where = implode(" AND ", $conditions);

        $query = "
            SELECT d.id, m.full_name, m.membership_no, m.enrollment_no, w.title, w.category, d.version, d.downloaded_at, d.ip_address 
            FROM wakalatnama_downloads d
            JOIN members m ON m.id = d.member_id
            JOIN wakalatnamas w ON w.id = d.wakalatnama_id
            WHERE $where
            ORDER BY d.id DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to export download report: " . $e->getMessage());
    }
}

// Clean buffer
if (ob_get_length()) ob_clean();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="wakalatnama_downloads_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');

// Emit UTF-8 BOM to prevent Excel from mangling Devanagari Hindi characters
fwrite($output, "\xEF\xBB\xBF");

// Headers
fputcsv($output, [
    'डाउनलोड आईडी',
    'अधिवक्ता का नाम',
    'सदस्यता संख्या',
    'पंजीकरण संख्या',
    'वकालतनामा शीर्षक',
    'श्रेणी',
    'डाउनलोड संस्करण',
    'डाउनलोड तिथि व समय',
    'आईपी पता'
]);

// Data
foreach ($records as $r) {
    fputcsv($output, [
        $r['id'],
        $r['full_name'],
        $r['membership_no'],
        $r['enrollment_no'],
        $r['title'],
        $r['category'],
        'v' . $r['version'],
        $r['downloaded_at'],
        $r['ip_address']
    ]);
}

fclose($output);
exit();
