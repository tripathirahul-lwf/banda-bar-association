<?php
/**
 * UTF-8 CSV Exporter for Executive Bearers List
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce permission
requireRole(['admin', 'mahasachiv']);

$term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
$filter_pos = isset($_GET['position_id']) ? intval($_GET['position_id']) : 0;
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$db = Database::getConnection();

if ($db && $term_id > 0) {
    try {
        // Fetch term title
        $t_stmt = $db->prepare("SELECT title FROM office_bearer_terms WHERE id = ?");
        $t_stmt->execute([$term_id]);
        $term_title = $t_stmt->fetchColumn() ?: 'executive_committee';

        // Prepare query
        $sql = "
            SELECT ob.*, p.position_name, p.position_name_hindi, t.title AS term_title
            FROM office_bearers ob
            JOIN office_bearer_positions p ON p.id = ob.position_id
            JOIN office_bearer_terms t ON t.id = ob.term_id
            WHERE ob.term_id = ?
        ";
        $params = [$term_id];

        if ($filter_pos > 0) {
            $sql .= " AND ob.position_id = ?";
            $params[] = $filter_pos;
        }
        if (!empty($filter_status)) {
            $sql .= " AND ob.status = ?";
            $params[] = $filter_status;
        }

        $sql .= " ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC";
        $b_stmt = $db->prepare($sql);
        $b_stmt->execute($params);
        $bearers = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send headers for file download
        $filename = "bearers_export_" . sanitize_filename($term_title) . "_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Output UTF-8 BOM for Excel
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        
        // Header columns
        fputcsv($output, [
            'कार्यकाल (Term)', 
            'संवैधानिक पद (Designation)', 
            'अंग्रेजी नाम (Designation EN)', 
            'अधिवक्ता का नाम (Advocate Name)', 
            'सदस्यता कोड (DBA Code)', 
            'पंजीकरण संख्या (COP No)', 
            'प्रारंभ तिथि (Start Date)', 
            'समाप्ति तिथि (End Date)', 
            'स्थिति (Status)'
        ]);

        foreach ($bearers as $b) {
            fputcsv($output, [
                $b['term_title'],
                $b['position_name_hindi'],
                $b['position_name'],
                $b['display_name_snapshot'],
                $b['membership_no_snapshot'],
                $b['enrollment_no_snapshot'],
                $b['start_date'] ?: '',
                $b['end_date'] ?: '',
                ucfirst($b['status'])
            ]);
        }

        fclose($output);
        exit;

    } catch (PDOException $e) {
        error_log("Failed to export bearers CSV: " . $e->getMessage());
        echo 'त्रुटि: निर्यात करने में असमर्थ।';
    }
} else {
    echo 'अमान्य अनुरोध।';
}

function sanitize_filename($str) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $str);
}
