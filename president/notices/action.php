<?php
/**
 * President notices approval handler controller
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce President role
if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'president') {
    http_response_code(403);
    exit("Direct access forbidden. President privileges required.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('index.php');
    }

    $notice_id = intval($_POST['notice_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    $db = Database::getConnection();
    if ($db && $notice_id > 0) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];

            // Fetch current notice data
            $stmt = $db->prepare("SELECT status, publish_at FROM notices WHERE id = ?");
            $stmt->execute([$notice_id]);
            $notice = $stmt->fetch();

            if (!$notice) {
                setFlash('danger', 'अधिसूचना रिकॉर्ड नहीं मिला।');
                redirect('index.php');
            }

            $old_status = $notice['status'];
            $new_status = '';

            if ($action === 'approve') {
                $pub_at = $notice['publish_at'];
                if ($pub_at && strtotime($pub_at) > time()) {
                    $new_status = 'scheduled';
                } else {
                    $new_status = 'published';
                }
                
                $up = $db->prepare("UPDATE notices SET status = ?, approved_by = ?, approved_at = NOW(), published_at = CASE WHEN ? = 'published' THEN NOW() ELSE published_at END WHERE id = ?");
                $up->execute([$new_status, $user_id, $new_status, $notice_id]);
            } elseif ($action === 'reject') {
                $new_status = 'rejected';
                $up = $db->prepare("UPDATE notices SET status = ?, rejection_reason = ? WHERE id = ?");
                $up->execute([$new_status, $remarks ?: 'अस्वीकृत (कारण नहीं दिया गया)', $notice_id]);
            }

            if (!empty($new_status)) {
                // Log history log
                $hist = $db->prepare("
                    INSERT INTO notice_history (notice_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $hist->execute([
                    $notice_id,
                    ucfirst($action) . ' Action',
                    $old_status,
                    $new_status,
                    $remarks ?: "राष्ट्रपति अनुमोदन द्वारा स्थिति संक्रमण $old_status -> $new_status",
                    $user_id
                ]);

                // Audit log
                $audit = $db->prepare("
                    INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                    VALUES (0, 'Notice Approval Status', 'status', ?, ?, ?, ?)
                ");
                $audit->execute([$old_status, $new_status, "Notice ID $notice_id updated by President", $user_id]);
            }

            $db->commit();
            setFlash('success', 'अधिसूचना स्थिति सफलतापूर्वक अद्यतन की गई।');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed President notice approval action: " . $e->getMessage());
            setFlash('danger', 'कार्रवाई निष्पादित करने में विफलता।');
        }
    }
}

redirect('index.php');
