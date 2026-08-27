<?php
/**
 * Role Synchronization & Tenure Execution Handler
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce Admin / Mahasachiv permission
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $_SESSION['flash_error'] = 'सुरक्षा सत्यापन विफल रहा।';
        header("Location: index.php");
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    try {
        $db->beginTransaction();

        if ($action === 'end_tenure') {
            $bearer_id = intval($_POST['bearer_id'] ?? 0);
            $end_date = sanitize($_POST['end_date'] ?? '');
            $status = sanitize($_POST['status'] ?? 'completed');
            $remarks = sanitize(trim($_POST['remarks'] ?? ''));

            if ($bearer_id <= 0 || empty($end_date)) {
                throw new Exception('अमान्य डेटा। कृपया सभी फ़ील्ड सही से भरें।');
            }

            // Fetch office bearer record
            $ob_stmt = $db->prepare("SELECT * FROM office_bearers WHERE id = ?");
            $ob_stmt->execute([$bearer_id]);
            $ob = $ob_stmt->fetch();
            if (!$ob) {
                throw new Exception('पदाधिकारी रिकॉर्ड नहीं मिला।');
            }

            if ($ob['status'] !== 'active') {
                throw new Exception('यह पदाधिकारी वर्तमान में सक्रिय नहीं है।');
            }

            // Update record
            $up = $db->prepare("UPDATE office_bearers SET status = ?, end_date = ?, updated_at = NOW() WHERE id = ?");
            $up->execute([$status, $end_date, $bearer_id]);

            // Save history
            $hist = $db->prepare("
                INSERT INTO office_bearer_history (office_bearer_id, action, old_status, new_status, remarks, performed_by)
                VALUES (?, 'Tenure Ended', 'active', ?, ?, ?)
            ");
            $hist->execute([$bearer_id, $status, $remarks ?: 'Tenure ended.', $_SESSION['user_id']]);

            // Auto sync roles if config is enabled
            if (AUTO_SYNC_OFFICE_BEARER_LOGIN_ROLES) {
                // Fetch member's position code
                $pos_stmt = $db->prepare("SELECT code FROM office_bearer_positions WHERE id = ?");
                $pos_stmt->execute([$ob['position_id']]);
                $pos_code = $pos_stmt->fetchColumn();

                if (in_array($pos_code, ['PRESIDENT', 'MAHASACHIV'])) {
                    // Downgrade user role
                    $u_up = $db->prepare("UPDATE users SET role = 'member' WHERE member_id = ? AND role IN ('president', 'mahasachiv')");
                    $u_up->execute([$ob['member_id']]);
                }
            }

            // Log in global member history
            $m_hist = $db->prepare("
                INSERT INTO member_history (member_id, action, remarks, performed_by)
                VALUES (?, 'Office Bearer Tenure Ended', ?, ?)
            ");
            $m_hist->execute([$ob['member_id'], "Tenure ended for position ID {$ob['position_id']}. Status: $status.", $_SESSION['user_id']]);

            $db->commit();
            $_SESSION['flash_success'] = 'पदाधिकारी का कार्यकाल सफलतापूर्वक समाप्त कर दिया गया है।';

        } else {
            // Manual Role sync logic (Sync Portal Role)
            $user_id = intval($_POST['user_id'] ?? 0);
            $target_role = sanitize($_POST['target_role'] ?? '');

            if ($user_id <= 0 || !in_array($target_role, ['president', 'mahasachiv', 'member'])) {
                throw new Exception('अमान्य भूमिका या उपयोगकर्ता क्रमांक।');
            }

            // Fetch user
            $u_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $u = $u_stmt->fetch();
            if (!$u) {
                throw new Exception('उपयोगकर्ता नहीं मिला।');
            }

            // Update user role
            $up = $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
            $up->execute([$target_role, $user_id]);

            // If user has a linked member, log history
            if ($u['member_id']) {
                $m_hist = $db->prepare("
                    INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by)
                    VALUES (?, 'Portal Role Synced', 'role', ?, ?, 'मैनुअल सिंक क्रियान्वित की गई।', ?)
                ");
                $m_hist->execute([$u['member_id'], $u['role'], $target_role, $_SESSION['user_id']]);
            }

            $db->commit();
            $_SESSION['flash_success'] = 'उपयोगकर्ता सुरक्षा रोल सफलतापूर्वक सिंक किया गया।';
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash_error'] = 'सिंक प्रक्रिया त्रुटि: ' . $e->getMessage();
    }
}

header("Location: index.php");
exit;
