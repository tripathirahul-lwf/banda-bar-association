<?php
/**
 * Member Rent Receipt Security Redirect Wrapper
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce member role
requireRole('member');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = Database::getConnection();

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT member_id FROM chamber_rent_payments WHERE id = ?");
        $stmt->execute([$id]);
        $owner_id = $stmt->fetchColumn();
        
        if ($owner_id && intval($owner_id) === intval($_SESSION['member_id'])) {
            // Redirect to A4 receipt printer page
            // Notice: The receiver page validates session for permission. 
            // We temporarily allow members to print their matching receipt by passing role bypass or matching checks on the receiver page.
            // Let's make sure the receiver page checks: is_admin OR session['member_id'] === payment['member_id'].
            header("Location: " . SITE_URL . "/admin/rooms/rent/receipt.php?id=" . $id);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Failed security check on member receipt wrapper: " . $e->getMessage());
    }
}

http_response_code(403);
echo '<div style="font-family:sans-serif; text-align:center; padding:50px;">';
echo '<h2 style="color:red;">सुरक्षा उल्लंघन (Access Denied)</h2>';
echo '<p>आपको इस किराया भुगतान रसीद को देखने या प्रिंट करने का अधिकार नहीं है।</p>';
echo '<a href="' . SITE_URL . '/member/dashboard.php">डैशबोर्ड पर जाएं</a>';
echo '</div>';
exit;
