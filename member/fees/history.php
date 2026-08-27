<?php
/**
 * Member Fee Payment History
 * District Bar Association, Banda
 */

$pageTitle = 'मेरा भुगतान इतिहास (My Payment History)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Member Role
requireRole('member');

$db = Database::getConnection();
$user = currentUser();
$member_id = intval($user['member_id'] ?? 0);

$payments = [];

if ($db && $member_id > 0) {
    try {
        // Fetch all confirmed fee payments
        $stmt = $db->prepare("
            SELECT p.*
            FROM bar_fee_payments p
            WHERE p.member_id = ? AND p.status = 'confirmed'
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $stmt->execute([$member_id]);
        $payments = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load member payment history: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">मेरा भुगतान इतिहास (My Payment History)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>मेरा शुल्क</a>
</div>

<!-- Payments Table -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>जमा किये गए शुल्कों का इतिहास (Payments Log)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">रसीद संख्या (Receipt No)</th>
                        <th>भुगतान तिथि</th>
                        <th>भुगतान माध्यम</th>
                        <th>संदर्भ संख्या</th>
                        <th>राशि (Amount)</th>
                        <th>स्थिति</th>
                        <th class="text-end">विकल्प</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">कोई भुगतान इतिहास रिकॉर्ड उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold text-navy-custom"><?php echo e($p['receipt_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                <td><?php echo e($p['payment_mode']); ?></td>
                                <td class="english-text"><?php echo e($p['transaction_reference'] ?: '-'); ?></td>
                                <td class="fw-bold text-success">₹<?php echo number_format($p['amount'], 2); ?></td>
                                <td><span class="badge bg-success">Confirmed</span></td>
                                <td class="text-end">
                                    <a href="receipt.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy py-0.5">रसीद देखें</a>
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
