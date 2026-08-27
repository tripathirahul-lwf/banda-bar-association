<?php
/**
 * President Bar Fee View Panel
 * District Bar Association, Banda
 */

$pageTitle = 'अध्यक्षीय शुल्क अवलोकन (Presidential Bar Fee Desk)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President Role
requireRole('president');

$db = Database::getConnection();

$stats = [
    'total_due' => 0.00,
    'total_collected' => 0.00,
    'total_outstanding' => 0.00,
    'total_overdue' => 0.00
];
$recent_payments = [];

if ($db) {
    try {
        $dues_stmt = $db->query("
            SELECT 
                SUM(payable_amount) as total_due,
                SUM(paid_amount) as total_collected,
                SUM(outstanding_amount) as total_outstanding
            FROM member_fee_dues
            WHERE status != 'cancelled'
        ");
        $dues_row = $dues_stmt->fetch();
        if ($dues_row) {
            $stats['total_due'] = floatval($dues_row['total_due'] ?: 0.00);
            $stats['total_collected'] = floatval($dues_row['total_collected'] ?: 0.00);
            $stats['total_outstanding'] = floatval($dues_row['total_outstanding'] ?: 0.00);
        }

        $stats['total_overdue'] = floatval($db->query("
            SELECT SUM(outstanding_amount) 
            FROM member_fee_dues
            WHERE status = 'overdue' OR (status IN ('pending', 'partially_paid') AND due_date < CURRENT_DATE())
        ")->fetchColumn() ?: 0.00);

        // Fetch recent payments
        $stmt = $db->query("
            SELECT p.*, m.full_name as member_name, m.membership_no
            FROM bar_fee_payments p
            JOIN members m ON m.id = p.member_id
            WHERE p.status = 'confirmed'
            ORDER BY p.payment_date DESC, p.id DESC
            LIMIT 5
        ");
        $recent_payments = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Failed to load President bar fee view: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">अध्यक्षीय शुल्क अवलोकन (Presidential Bar Fee Desk)</h4>
</div>

<!-- Stats widgets -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल शुल्क देयता</span>
            <h4 class="fw-bold text-navy-custom text-end mb-0">₹<?php echo number_format($stats['total_due'], 2); ?></h4>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल संग्रहित</span>
            <h4 class="fw-bold text-success text-end mb-0">₹<?php echo number_format($stats['total_collected'], 2); ?></h4>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल शेष बकाया</span>
            <h4 class="fw-bold text-warning text-end mb-0">₹<?php echo number_format($stats['total_outstanding'], 2); ?></h4>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">अवधिपार बकाया</span>
            <h4 class="fw-bold text-danger text-end mb-0">₹<?php echo number_format($stats['total_overdue'], 2); ?></h4>
        </div>
    </div>
</div>

<!-- Recent Collections Log -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>हालिया प्राप्त भुगतान सूची (Recent Payments)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">रसीद संख्या</th>
                        <th>अधिवक्ता का नाम</th>
                        <th>सदस्यता संख्या</th>
                        <th>भुगतान तिथि</th>
                        <th>माध्यम</th>
                        <th>राशि</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_payments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">कोई भुगतान रिकॉर्ड उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_payments as $rp): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold text-navy-custom"><?php echo e($rp['receipt_no']); ?></td>
                                <td class="fw-bold text-navy-custom"><?php echo e($rp['member_name']); ?></td>
                                <td class="english-text"><?php echo e($rp['membership_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($rp['payment_date'])); ?></td>
                                <td><?php echo e($rp['payment_mode']); ?></td>
                                <td class="fw-bold text-success">₹<?php echo number_format($rp['amount'], 2); ?></td>
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
