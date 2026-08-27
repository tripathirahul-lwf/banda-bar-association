<?php
/**
 * Admin Bar Fee Management Dashboard
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता शुल्क प्रबन्धन पटल (Advocate Bar Fee Control Panel)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

$stats = [
    'total_due' => 0.00,
    'total_collected' => 0.00,
    'total_outstanding' => 0.00,
    'total_overdue' => 0.00,
    'collected_today' => 0.00,
    'collected_month' => 0.00
];
$recent_payments = [];

if ($db) {
    try {
        // Core metrics from dues
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

        // Overdue total
        $stats['total_overdue'] = floatval($db->query("
            SELECT SUM(outstanding_amount) 
            FROM member_fee_dues
            WHERE status = 'overdue' OR (status IN ('pending', 'partially_paid') AND due_date < CURRENT_DATE())
        ")->fetchColumn() ?: 0.00);

        // Collected today
        $stats['collected_today'] = floatval($db->query("
            SELECT SUM(amount) FROM bar_fee_payments 
            WHERE DATE(payment_date) = CURRENT_DATE() AND status = 'confirmed'
        ")->fetchColumn() ?: 0.00);

        // Collected this month
        $stats['collected_month'] = floatval($db->query("
            SELECT SUM(amount) FROM bar_fee_payments 
            WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'confirmed'
        ")->fetchColumn() ?: 0.00);

        // Recent fee payments
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
        error_log("Failed fetching bar fee stats: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">अधिवक्ता शुल्क प्रबन्धन पटल (Bar Fee Panel)</h4>
    <div class="d-flex gap-2">
        <a href="members.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-people-fill me-1"></i>सदस्य बकाया सूची</a>
        <a href="bulk-assign.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-collection-fill me-1"></i>थोक शुल्क असाइन</a>
        <a href="create-due.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-plus-circle me-1"></i>नया शुल्क जोड़ें</a>
        <a href="payments/create.php" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-cash-stack me-1"></i>भुगतान प्राप्त करें</a>
    </div>
</div>

<!-- Fee Stats Widgets Grid -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <!-- Total Due Generated -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-navy border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल शुल्क देयता (Total Dues)</span>
            <h4 class="fw-bold text-end mb-0 text-navy-custom">₹<?php echo number_format($stats['total_due'], 2); ?></h4>
        </div>
    </div>

    <!-- Total Collected -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-success border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल संग्रहीत शुल्क (Collected)</span>
            <h4 class="fw-bold text-end mb-0 text-success">₹<?php echo number_format($stats['total_collected'], 2); ?></h4>
        </div>
    </div>

    <!-- Total Outstanding -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-warning border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल बकाया (Outstanding)</span>
            <h4 class="fw-bold text-end mb-0 text-warning">₹<?php echo number_format($stats['total_outstanding'], 2); ?></h4>
        </div>
    </div>

    <!-- Overdue Amount -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-danger border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">अवधिपार बकाया (Overdue)</span>
            <h4 class="fw-bold text-end mb-0 text-danger">₹<?php echo number_format($stats['total_overdue'], 2); ?></h4>
        </div>
    </div>
</div>

<!-- Secondary metrics cards row -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-light-custom text-navy-custom h-100">
            <span class="text-secondary font-size-xs d-block mb-1"><i class="bi bi-calendar2-day text-gold-custom me-1"></i>आज का संग्रह (Collected Today):</span>
            <h5 class="fw-bold mb-0">₹<?php echo number_format($stats['collected_today'], 2); ?></h5>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-light-custom text-navy-custom h-100">
            <span class="text-secondary font-size-xs d-block mb-1"><i class="bi bi-calendar2-month text-gold-custom me-1"></i>इस माह का संग्रह (This Month):</span>
            <h5 class="fw-bold mb-0">₹<?php echo number_format($stats['collected_month'], 2); ?></h5>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-navy-custom text-white h-100 d-flex justify-content-between align-items-center">
            <div>
                <span class="text-light-custom font-size-xs d-block mb-1">विवरण बहीखाता (Reports)</span>
                <span class="badge bg-gold-custom text-navy-custom font-size-xs py-1">रिपोर्ट्स उपलब्ध</span>
            </div>
            <div class="d-flex flex-column gap-1">
                <a href="reports/collection.php" class="btn btn-xs btn-gold text-navy-custom fw-semibold font-hindi">संग्रह रिपोर्ट</a>
                <a href="reports/outstanding.php" class="btn btn-xs btn-gold text-navy-custom fw-semibold font-hindi">बकाया रिपोर्ट</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments List -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>हालिया प्राप्त भुगतान (Recent Fee Collections)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">रसीद सं. (Receipt No)</th>
                        <th>अधिवक्ता (Advocate)</th>
                        <th>सदस्यता संख्या</th>
                        <th>भुगतान तिथि</th>
                        <th>भुगतान माध्यम</th>
                        <th>राशि (Amount)</th>
                        <th class="text-end">विकल्प</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">कोई हालिया भुगतान रिकॉर्ड उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_payments as $rp): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold"><?php echo e($rp['receipt_no']); ?></td>
                                <td class="fw-bold text-navy-custom"><?php echo e($rp['member_name']); ?></td>
                                <td class="english-text"><?php echo e($rp['membership_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($rp['payment_date'])); ?></td>
                                <td><?php echo e($rp['payment_mode']); ?></td>
                                <td class="fw-bold text-success">₹<?php echo number_format($rp['amount'], 2); ?></td>
                                <td class="text-end">
                                    <a href="receipt.php?id=<?php echo $rp['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy py-0.5">रसीद</a>
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
