<?php
/**
 * Export Search Filtered Members to UTF-8 CSV (Excel-Compatible)
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce authentication
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Replicate search and filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$bearer = trim($_GET['bearer'] ?? '');
$chamber = trim($_GET['chamber'] ?? '');
$visibility = trim($_GET['visibility'] ?? '');
$enroll_year = trim($_GET['enroll_year'] ?? '');

$sql_where = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_where .= " AND (full_name LIKE :search OR membership_no LIKE :search OR enrollment_no LIKE :search OR mobile LIKE :search OR chamber_no LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {
    $sql_where .= " AND membership_status = :status";
    $params[':status'] = $status;
}

if ($category !== '') {
    $sql_where .= " AND membership_category = :category";
    $params[':category'] = $category;
}

if ($gender !== '') {
    $sql_where .= " AND gender = :gender";
    $params[':gender'] = $gender;
}

if ($bearer !== '') {
    $sql_where .= " AND is_office_bearer = :bearer";
    $params[':bearer'] = ($bearer === 'yes') ? 1 : 0;
}

if ($chamber !== '') {
    if ($chamber === 'assigned') {
        $sql_where .= " AND chamber_no IS NOT NULL AND chamber_no != '' AND chamber_no != 'N/A'";
    } else {
        $sql_where .= " AND (chamber_no IS NULL OR chamber_no = '' OR chamber_no = 'N/A')";
    }
}

if ($visibility !== '') {
    $sql_where .= " AND is_public = :visibility";
    $params[':visibility'] = ($visibility === 'public') ? 1 : 0;
}

if ($enroll_year !== '' && is_numeric($enroll_year)) {
    $sql_where .= " AND YEAR(enrollment_date) = :enroll_year";
    $params[':enroll_year'] = $enroll_year;
}

$members = [];
if ($db) {
    try {
        $sql_query = "SELECT * FROM members" . $sql_where . " ORDER BY membership_no ASC";
        $stmt = $db->prepare($sql_query);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch export members: " . $e->getMessage());
        die("System Database Error.");
    }
}

// Emitting HTTP headers for file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="banda_bar_members_' . date('Y-m-d') . '.csv"');

// Open memory stream
$output = fopen('php://output', 'w');

// WRITE UTF-8 BOM so Excel opens Hindi characters correctly!
fwrite($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'Advocate Name (अधिवक्ता का नाम)',
    'Membership Number (सदस्यता क्रमांक)',
    'Enrollment Number (नामांकन संख्या)',
    'Gender (लिंग)',
    'Category (श्रेणी)',
    'Status (स्थिति)',
    'Mobile (मोबाइल)',
    'Email (ईमेल)',
    'Member Since (सदस्यता तिथि)',
    'Chamber Number (चैंबर)'
]);

foreach ($members as $m) {
    fputcsv($output, [
        $m['full_name'],
        $m['membership_no'],
        $m['enrollment_no'],
        $m['gender'] ?? 'N/A',
        $m['membership_category'],
        ucfirst($m['membership_status']),
        $m['mobile'] ?? '',
        $m['email'] ?? '',
        $m['member_since'],
        $m['chamber_no'] ?? 'N/A'
    ]);
}

fclose($output);
exit();
