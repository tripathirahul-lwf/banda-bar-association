<?php
/**
 * Member Bar Fee Dashboard
 * District Bar Association, Banda
 */

$pageTitle = 'मेरा बार संघ शुल्क (My Bar Fee Statement)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Member Role
requireRole('member');

$db = Database::getConnection();
$user = currentUser();
$member_id = intval($user['member_id'] ?? 0);

$summary = [
    'total_due' => 0.00,
    'total_paid' => 0.00,
    'total_outstanding' => 0.00,
    'overdue_amount' => 0.00
];
$dues = [];

if ($db && $member_id > 0) {
    try {
        $summary = getMemberFeeSummary($member_id, $db);

        // Fetch Dues
        $stmt = $db->prepare("
            SELECT d.*, t.name as fee_name 
            FROM member_fee_dues d
            JOIN bar_fee_types t ON t.id = d.fee_type_id
            WHERE d.member_id = ? AND d.status != 'cancelled'
            ORDER BY d.due_date ASC, d.id ASC
        ");
        $stmt->execute([$member_id]);
        $dues = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load member fee data: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">मेरा बार संघ शुल्क (My Bar Fee Desk)</h4>
    <a href="history.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-clock-history me-1"></i>भुगतान इतिहास</a>
</div>

<!-- Metrics Row -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल निर्धारित शुल्क (Total Due)</span>
            <h4 class="fw-bold text-navy-custom text-end mb-0">₹<?php echo number_format($summary['total_due'], 2); ?></h4>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल भुगतान जमा (Total Paid)</span>
            <h4 class="fw-bold text-success text-end mb-0">₹<?php echo number_format($summary['total_paid'], 2); ?></h4>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">शेष बकाया (Outstanding)</span>
            <h4 class="fw-bold text-warning text-end mb-0">₹<?php echo number_format($summary['total_outstanding'], 2); ?></h4>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">अवधिपार बकाया (Overdue)</span>
            <h4 class="fw-bold text-danger text-end mb-0">₹<?php echo number_format($summary['overdue_amount'], 2); ?></h4>
        </div>
    </div>
</div>

<!-- Dues Statement Table -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-card-checklist text-gold-custom me-2"></i>मेरी निर्धारित शुल्क देयता सूची (Fee Dues List)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">शुल्क का प्रकार (Fee Type)</th>
                        <th>अवधि / वित्तीय वर्ष</th>
                        <th>अंतिम देय तिथि</th>
                        <th class="text-end">कुल देयता</th>
                        <th class="text-end">कुल भुगतान</th>
                        <th class="text-end">शेष बकाया</th>
                        <th>स्थिति</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dues)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">आपका कोई निर्धारित शुल्क रिकॉर्ड उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dues as $d): ?>
                            <tr>
                                <td class="py-2 fw-bold text-navy-custom"><?php echo e($d['fee_name']); ?></td>
                                <td class="english-text"><?php echo e($d['period_label'] ?: $d['financial_year']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($d['due_date'])); ?></td>
                                <td class="text-end text-navy english-text">₹<?php echo number_format($d['payable_amount'], 2); ?></td>
                                <td class="text-end text-success english-text">₹<?php echo number_format($d['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-danger english-text">₹<?php echo number_format($d['outstanding_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $st = $d['status'];
                                    $c = ($st === 'paid') ? 'bg-success' : (($st === 'pending') ? 'bg-danger' : 'bg-warning text-dark');
                                    ?>
                                    <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($st)); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-warning font-hindi small d-flex align-items-center gap-2 mb-5">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div>
        <strong>शुल्क भुगतान सुचना:</strong> ऑनलाइन भुगतान गेटवे वर्तमान में सक्रिय नहीं है। शुल्क भुगतान दर्ज कराने हेतु कृपया जिला अधिवक्ता संघ, बांदा कार्यालय में रसीद बुक लिपिक या कोषाध्यक्ष से संपर्क कर नकद/UPI द्वारा रसीद प्राप्त करें।
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
