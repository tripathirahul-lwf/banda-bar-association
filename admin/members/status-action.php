<?php
/**
 * Member Membership Status Transition Processor
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce login and permission
requireRole(['admin', 'mahasachiv']);
requirePermission('members.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('../../index.php');
    }

    $member_id = intval($_POST['member_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    $status_reason = trim($_POST['status_reason'] ?? '');

    if ($member_id <= 0 || empty($new_status) || empty($status_reason)) {
        setFlash('danger', 'सभी फ़ील्ड भरना आवश्यक है।');
        redirect('view.php?id=' . $member_id . '&tab=status');
    }

    $db = Database::getConnection();
    if ($db) {
        try {
            // Get current membership status
            $stmt = $db->prepare("SELECT membership_status FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $old_status = $stmt->fetchColumn();

            if (!$old_status) {
                setFlash('danger', 'सदस्य रिकॉर्ड नहीं मिला।');
                redirect('index.php');
            }

            if ($old_status === $new_status) {
                setFlash('warning', 'नवीन स्थिति पुरानी स्थिति के समान है।');
                redirect('view.php?id=' . $member_id);
            }

            // Update status
            $update_stmt = $db->prepare("UPDATE members SET membership_status = ?, status_reason = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$new_status, $status_reason, $_SESSION['auth']['user_id'], $member_id]);

            // Log change in history
            $hist_stmt = $db->prepare("
                INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                VALUES (?, 'Membership Status Changed', 'membership_status', ?, ?, ?, ?)
            ");
            $hist_stmt->execute([$member_id, $old_status, $new_status, $status_reason, $_SESSION['auth']['user_id']]);

            setFlash('success', 'सदस्यता स्थिति सफलतापूर्वक परिवर्तित कर दी गई है।');
            redirect('view.php?id=' . $member_id);
        } catch (PDOException $e) {
            error_log("Failed to transition member status: " . $e->getMessage());
            setFlash('danger', 'डेटाबेस विफलता: ' . $e->getMessage());
            redirect('view.php?id=' . $member_id . '&tab=status');
        }
    }
} else {
    redirect('index.php');
}
