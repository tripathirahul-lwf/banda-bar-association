<?php
/**
 * Process President Fund approvals and rejections
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce President Role
requireRole('president');

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('index.php');
    }

    $tx_id = intval($_POST['tx_id'] ?? 0);
    $action_type = trim($_POST['action_type'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $user_id = $_SESSION['auth']['user_id'];

    if ($tx_id > 0 && !empty($action_type)) {
        try {
            $db->beginTransaction();

            // Fetch transaction
            $stmt = $db->prepare("SELECT * FROM association_fund_transactions WHERE id = ? AND status = 'pending_approval'");
            $stmt->execute([$tx_id]);
            $tx = $stmt->fetch();

            if ($tx) {
                if ($action_type === 'approve') {
                    // Approved
                    $receipt_no = $tx['receipt_no'];
                    $voucher_no = $tx['voucher_no'];

                    // Generate automatically if empty
                    if ($tx['transaction_type'] === 'income' && empty($receipt_no)) {
                        $receipt_no = generateReceiptOrVoucherNo($db, 'income');
                    } elseif ($tx['transaction_type'] === 'expense' && empty($voucher_no)) {
                        $voucher_no = generateReceiptOrVoucherNo($db, 'expense');
                    }

                    $up = $db->prepare("
                        UPDATE association_fund_transactions 
                        SET status = 'approved', approved_by = ?, approved_at = NOW(), receipt_no = ?, voucher_no = ?
                        WHERE id = ?
                    ");
                    $up->execute([$user_id, $receipt_no, $voucher_no, $tx_id]);

                    // History logs
                    $hist = $db->prepare("
                        INSERT INTO association_fund_history (transaction_id, financial_year_id, action, old_status, new_status, remarks, performed_by) 
                        VALUES (?, ?, 'Transaction Approved', 'pending_approval', 'approved', 'Approved by President', ?)
                    ");
                    $hist->execute([$tx_id, $tx['financial_year_id'], $user_id]);

                    // Audit trail log
                    $audit = $db->prepare("
                        INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                        VALUES (0, 'Fund Transaction Approved', 'status', 'pending_approval', 'approved', ?, ?)
                    ");
                    $audit->execute(["Fund Transaction number " . $tx['transaction_no'] . " approved", $user_id]);

                    setFlash('success', 'लेन-देन सफलतापूर्वक स्वीकृत (Approved) किया गया।');

                } elseif ($action_type === 'reject') {
                    // Rejected
                    $up = $db->prepare("
                        UPDATE association_fund_transactions 
                        SET status = 'rejected', cancellation_reason = ?
                        WHERE id = ?
                    ");
                    $up->execute([$reason, $tx_id]);

                    // History log
                    $hist = $db->prepare("
                        INSERT INTO association_fund_history (transaction_id, financial_year_id, action, old_status, new_status, remarks, performed_by) 
                        VALUES (?, ?, 'Transaction Rejected', 'pending_approval', 'rejected', ?, ?)
                    ");
                    $hist->execute([$tx_id, $tx['financial_year_id'], "Rejection reason: $reason", $user_id]);

                    // Audit trail
                    $audit = $db->prepare("
                        INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                        VALUES (0, 'Fund Transaction Rejected', 'status', 'pending_approval', 'rejected', ?, ?)
                    ");
                    $audit->execute(["Fund Transaction number " . $tx['transaction_no'] . " rejected. Reason: $reason", $user_id]);

                    setFlash('success', 'लेन-देन अस्वीकृत (Rejected) कर दिया गया है।');
                }
            } else {
                setFlash('danger', 'लेन-देन विवरण उपलब्ध नहीं है या यह समीक्षा के लिए लंबित नहीं है।');
            }

            $db->commit();

        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed processing president approval action: " . $e->getMessage());
            setFlash('danger', 'डेटाबेस प्रविष्टि विफल: ' . $e->getMessage());
        }
    }
}
redirect('index.php');
