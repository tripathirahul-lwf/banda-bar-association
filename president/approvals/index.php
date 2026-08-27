<?php
/**
 * President Unified Approval Center
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अध्यक्षीय स्वीकृति केंद्र (Presidential Approvals)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President Role
requireRole(['president']);

$db = Database::getConnection();
$error = '';
$success = '';

$approvals_list = [];

if ($db) {
    try {
        // 1. Pending Notices Approvals
        $stmt = $db->query("
            SELECT id, notice_no AS ref_no, title AS applicant_details, created_at, 'Notices' AS module_name, 'admin/notices/index.php' AS link_url
            FROM notices 
            WHERE status = 'pending_approval'
        ");
        while ($r = $stmt->fetch()) {
            $approvals_list[] = $r;
        }

        // 2. Pending ID Card applications
        $stmt = $db->query("
            SELECT ic.id, ic.application_no AS ref_no, m.full_name AS applicant_details, ic.created_at, 'ID Cards' AS module_name, 'admin/id-cards/index.php' AS link_url
            FROM id_card_applications ic
            JOIN members m ON m.id = ic.member_id
            WHERE ic.status IN ('submitted', 'under_review')
        ");
        while ($r = $stmt->fetch()) {
            $approvals_list[] = $r;
        }

        // 3. Pending Fund transactions
        $stmt = $db->query("
            SELECT ft.id, ft.transaction_no AS ref_no, CONCAT(ft.transaction_type, ': ', ft.remarks, ' (Amount: ', ft.amount, ')') AS applicant_details, ft.created_at, 'Association Fund' AS module_name, 'president/association-fund/index.php' AS link_url
            FROM association_fund_transactions ft
            WHERE ft.status = 'pending_approval'
        ");
        while ($r = $stmt->fetch()) {
            $approvals_list[] = $r;
        }

        // 4. Pending Chamber allotments
        $stmt = $db->query("
            SELECT ca.id, ca.application_no AS ref_no, CONCAT('Chamber ', ca.preferred_chamber_no, ' (Member ID: ', ca.member_id, ')') AS applicant_details, ca.created_at, 'Chamber Allotment' AS module_name, 'admin/rooms/applications.php' AS link_url
            FROM chamber_applications ca
            WHERE ca.status = 'submitted'
        ");
        while ($r = $stmt->fetch()) {
            $approvals_list[] = $r;
        }

        // 5. Pending Election Results approvals
        $stmt = $db->query("
            SELECT e.id, e.election_code AS ref_no, e.title AS applicant_details, e.created_at, 'Election Results' AS module_name, 'president/elections/index.php' AS link_url
            FROM elections e
            WHERE e.status = 'counting' OR e.status = 'completed'
        ");
        while ($r = $stmt->fetch()) {
            $approvals_list[] = $r;
        }

    } catch (PDOException $e) {
        error_log("Failed to load presidential approvals: " . $e->getMessage());
    }
}

// Sort approvals by created_at DESC
usort($approvals_list, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-patch-check-fill text-gold-custom me-2"></i>अध्यक्षीय स्वीकृति केंद्र (Presidential Approval Desk)</h4>
</div>

<div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 font-hindi small text-navy-custom">
    <i class="bi bi-info-circle-fill text-info me-2 fs-5"></i>
    <strong>स्वीकृति समीक्षा:</strong> संघ नियमावली के अंतर्गत अध्यक्ष द्वारा स्वीकृत किए जाने वाले समस्त मॉड्यूल के लंबित प्रविष्टियां नीचे सूचीबद्ध हैं।
</div>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.78rem;">
                <thead class="table-light">
                    <tr>
                        <th>विभाग / मॉड्यूल</th>
                        <th>संदर्भ क्रमांक (Reference)</th>
                        <th>आवेदक / विवरण (Details)</th>
                        <th>प्रस्तुत दिनांक (Submitted Date)</th>
                        <th>स्थिति</th>
                        <th class="text-end">कार्यवाही</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($approvals_list)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">कोई लंबित स्वीकृति अनुरोध मौजूद नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($approvals_list as $app): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-navy-custom border fw-bold"><?php echo e($app['module_name']); ?></span>
                                </td>
                                <td class="english-text"><strong><?php echo e($app['ref_no']); ?></strong></td>
                                <td><?php echo e($app['applicant_details']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y h:i A', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark font-size-xs">Pending Approval</span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL . '/' . ltrim($app['link_url'], '/'); ?>" class="btn btn-xs btn-gold text-navy-custom fw-bold px-3">समीक्षा करें</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
