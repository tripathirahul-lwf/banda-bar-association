<?php
/**
 * President Approvals Action Controller
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce president role
if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'president') {
    http_response_code(403);
    exit("Direct access forbidden.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    exit("Invalid request method.");
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
    redirect('index.php');
}

$doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
$action = trim($_POST['action'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($doc_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    setFlash('danger', 'अमान्य अनुरोध पैरामीटर।');
    redirect('index.php');
}

$db = Database::getConnection();
if ($db) {
    try {
        $db->beginTransaction();
        $user_id = $_SESSION['auth']['user_id'];

        // Get current details
        $stmt = $db->prepare("SELECT title, status FROM wakalatnamas WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();

        if (!$doc) {
            throw new Exception("दस्तावेज रिकॉर्ड नहीं मिला।");
        }

        $new_status = ($action === 'approve') ? 'active' : 'inactive';
        $action_label = ($action === 'approve') ? 'President Approved' : 'President Rejected';

        // Update database
        $up_stmt = $db->prepare("UPDATE wakalatnamas SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $up_stmt->execute([$new_status, $user_id, $doc_id]);

        // Log History
        $hist = $db->prepare("
            INSERT INTO wakalatnama_history (wakalatnama_id, action, old_status, new_status, remarks, performed_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $hist->execute([
            $doc_id,
            $action_label,
            $doc['status'],
            $new_status,
            $remarks ?: "अध्यक्षीय समीक्षा टिप्पणी: $remarks",
            $user_id
        ]);

        // Log Audit Log
        $log_desc = "President processed document ID $doc_id ($action_label), remarks: $remarks";
        $audit = $db->prepare("
            INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
            VALUES (0, 'Presidential Action', 'status', ?, ?, ?, ?)
        ");
        $audit->execute([$doc['status'], $new_status, $log_desc, $user_id]);

        $db->commit();
        setFlash('success', "दस्तावेज सफलतापूर्वक " . ($action === 'approve' ? 'अनुमोदित' : 'अस्वीकृत') . " किया गया।");
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("President approvals action failed: " . $e->getMessage());
        setFlash('danger', 'कार्रवाई करने में विफलता: ' . $e->getMessage());
    }
} else {
    setFlash('danger', 'डेटाबेस कनेक्शन विफलता।');
}

redirect('index.php');
