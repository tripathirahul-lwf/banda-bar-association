<?php
/**
 * Member Rent Payment History Logs
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'मेरा चैंबर किराया भुगतान इतिहास (Chamber Rent Ledger)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Ensure user has Member role
requireRole('member');

$db = Database::getConnection();
$member_id = $_SESSION['member_id'];
$member = null;

if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed load member info: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड लोड करने में विफलता।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit;
}

// Get Chamber Summary
$chamber = getMemberChamberSummary($member_id, $db);

$rent_dues = [];
$rent_payments = [];

if ($db && $chamber['has_chamber']) {
    try {
        // Fetch rent dues
        $d_stmt = $db->prepare("
            SELECT d.* 
            FROM chamber_rent_dues d
            WHERE d.allotment_id = ?
            ORDER BY d.rent_month DESC
        ");
        $d_stmt->execute([$chamber['allotment_id']]);
        $rent_dues = $d_stmt->fetchAll();

        // Fetch payments
        $p_stmt = $db->prepare("
            SELECT p.*
            FROM chamber_rent_payments p
            WHERE p.allotment_id = ? AND p.status = 'confirmed'
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $p_stmt->execute([$chamber['allotment_id']]);
        $rent_payments = $p_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading rent history logs: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">किराया भुगतान बहीखाता विवरण (Rent Statement)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>वापस जाएं</a>
</div>

<div class="row g-3 font-hindi small text-navy-custom">
    <?php if (!$chamber['has_chamber']): ?>
        <div class="col-12 text-center py-5 bg-white border border-light rounded shadow-sm text-muted">
            कोई कक्ष आवंटित नहीं होने के कारण किराया विवरणी उपलब्ध नहीं है।
        </div>
    <?php else: ?>
        <!-- Quick Summary Metrics -->
        <div class="col-12 mb-3 bg-light p-3 rounded border border-light shadow-xs">
            <div class="row g-2 text-center">
                <div class="col-md-3 col-6">
                    <span class="text-muted d-block font-size-xs">आवंटित चैंबर:</span>
                    <strong class="fs-6 english-text"><?php echo e($chamber['chamber_no']); ?> (<?php echo e($chamber['block_name']); ?>)</strong>
                </div>
                <div class="col-md-3 col-6">
                    <span class="text-muted d-block font-size-xs">मासिक किराया:</span>
                    <strong class="fs-6 english-text text-danger">₹<?php echo number_format($chamber['monthly_rent'], 2); ?></strong>
                </div>
                <div class="col-md-3 col-6 border-start">
                    <span class="text-muted d-block font-size-xs">जमानत राशि जमा:</span>
                    <strong class="fs-6 text-success english-text">₹<?php echo number_format($chamber['security_deposit_paid'], 2); ?></strong>
                </div>
                <div class="col-md-3 col-6 border-start">
                    <span class="text-muted d-block font-size-xs">लंबित किराया बकाया:</span>
                    <strong class="fs-6 text-danger english-text">₹<?php echo number_format($chamber['outstanding_rent'], 2); ?></strong>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="col-12">
            <ul class="nav nav-tabs" id="rentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-2 fw-bold" id="dues-tab" data-bs-toggle="tab" data-bs-target="#dues" type="button" role="tab">मासिक किराया देय सूची (Monthly Dues)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 fw-bold" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">प्राप्त भुगतान रसीदें (Payments History)</button>
                </li>
            </ul>
            
            <div class="tab-content bg-white border border-top-0 p-3 rounded-bottom shadow-xs" id="rentTabsContent">
                <!-- Tab 1: Monthly Dues -->
                <div class="tab-pane fade show active" id="dues" role="tabpanel" aria-labelledby="dues-tab">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-muted">
                            <thead class="table-light">
                                <tr>
                                    <th>किराया माह</th>
                                    <th>देय तिथि</th>
                                    <th class="text-end">मूल किराया</th>
                                    <th class="text-end">दंड शुल्क</th>
                                    <th class="text-end">छूट राशि</th>
                                    <th class="text-end">कुल देय</th>
                                    <th class="text-end">जमा राशि</th>
                                    <th class="text-end">बकाया राशि</th>
                                    <th>स्थिति</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rent_dues)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-3">कोई किराया देयता रिकॉर्ड नहीं मिला।</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rent_dues as $rd): ?>
                                        <tr>
                                            <td class="fw-bold text-navy-custom english-text"><?php echo date('F Y', strtotime($rd['rent_month'] . '-01')); ?></td>
                                            <td class="english-text"><?php echo date('d-m-Y', strtotime($rd['due_date'])); ?></td>
                                            <td class="text-end english-text">₹<?php echo number_format($rd['rent_amount'], 2); ?></td>
                                            <td class="text-end text-danger english-text">₹<?php echo number_format($rd['penalty_amount'], 2); ?></td>
                                            <td class="text-end text-secondary english-text">₹<?php echo number_format($rd['discount_amount'], 2); ?></td>
                                            <td class="text-end text-navy-custom fw-semibold english-text">₹<?php echo number_format($rd['payable_amount'], 2); ?></td>
                                            <td class="text-end text-success english-text">₹<?php echo number_format($rd['paid_amount'], 2); ?></td>
                                            <td class="text-end text-danger fw-bold english-text">₹<?php echo number_format($rd['outstanding_amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $st = $rd['status'];
                                                $c = ($st === 'paid') ? 'bg-success' : (($st === 'pending') ? 'bg-secondary' : (($st === 'overdue') ? 'bg-danger' : 'bg-warning text-dark'));
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

                <!-- Tab 2: Payments -->
                <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-muted">
                            <thead class="table-light">
                                <tr>
                                    <th>रसीद संख्या</th>
                                    <th>भुगतान तिथि</th>
                                    <th>माध्यम</th>
                                    <th>लेनदेन सन्दर्भ</th>
                                    <th class="text-end">प्राप्त राशि</th>
                                    <th class="text-end">विवरण</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rent_payments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3">कोई किराया भुगतान रसीद उपलब्ध नहीं है।</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rent_payments as $rp): ?>
                                        <tr>
                                            <td class="fw-bold text-navy-custom english-text"><?php echo e($rp['receipt_no']); ?></td>
                                            <td class="english-text"><?php echo date('d-m-Y', strtotime($rp['payment_date'])); ?></td>
                                            <td><?php echo e($rp['payment_mode']); ?></td>
                                            <td class="english-text"><?php echo e($rp['reference_no'] ?: '-'); ?></td>
                                            <td class="text-end text-success fw-bold english-text">₹<?php echo number_format($rp['amount'], 2); ?></td>
                                            <td class="text-end">
                                                <a href="receipt-print.php?id=<?php echo $rp['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy py-0.5"><i class="bi bi-printer me-1"></i>रसीद प्रिंट</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
