<?php
/**
 * Detailed Transaction Info & Timeline Audit Profile
 * District Bar Association, Banda
 */

$pageTitle = 'लेन-देन विवरण (Transaction Profile)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$tx_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tx = null;
$history = [];

$db = Database::getConnection();

if ($db && $tx_id > 0) {
    try {
        // Fetch transaction details
        $stmt = $db->prepare("
            SELECT t.*, c.name as category_name, 
                   u.username as creator_name,
                   app.username as approver_name,
                   can.username as canceller_name
            FROM association_fund_transactions t
            LEFT JOIN association_fund_categories c ON c.id = t.category_id
            LEFT JOIN users u ON u.id = t.created_by
            LEFT JOIN users app ON app.id = t.approved_by
            LEFT JOIN users can ON can.id = t.cancelled_by
            WHERE t.id = ?
        ");
        $stmt->execute([$tx_id]);
        $tx = $stmt->fetch();

        if ($tx) {
            // Fetch audit timeline logs
            $hist_stmt = $db->prepare("
                SELECT h.*, u.username as performer_name 
                FROM association_fund_history h
                LEFT JOIN users u ON u.id = h.performed_by
                WHERE h.transaction_id = ?
                ORDER BY h.id ASC
            ");
            $hist_stmt->execute([$tx_id]);
            $history = $hist_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch transaction details: " . $e->getMessage());
    }
}

if (!$tx) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: लेन-देन विवरण नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

$status_badges = [
    'draft' => 'bg-secondary',
    'pending_approval' => 'bg-warning text-dark',
    'approved' => 'bg-success',
    'cancelled' => 'bg-danger',
    'rejected' => 'bg-danger'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h5 class="text-navy-custom fw-bold mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>लेन-देन विवरण सं. <?php echo e($tx['transaction_no']); ?></h5>
    <div class="d-flex gap-2">
        <?php if ($tx['transaction_type'] === 'income' && $tx['status'] === 'approved'): ?>
            <a href="receipt.php?id=<?php echo $tx['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>रसीद प्रिंट</a>
        <?php elseif ($tx['transaction_type'] === 'expense' && $tx['status'] === 'approved'): ?>
            <a href="voucher.php?id=<?php echo $tx['id']; ?>" target="_blank" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>वाउचर प्रिंट</a>
        <?php endif; ?>
        <a href="transactions.php" class="btn btn-xs btn-navy fw-semibold">वापस सूची पर जाएं</a>
    </div>
</div>

<div class="row g-4 font-hindi small">
    <!-- Detailed transaction stats card -->
    <div class="col-lg-7 col-12">
        <div class="card p-3 border-0 bg-white shadow-sm mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-info-circle text-gold-dark me-2"></i>मूल विवरण (General Details)</h6>
            
            <div class="row g-3">
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs">लेन-देन संख्या (Transaction No)</span>
                    <strong class="text-navy-custom english-text"><?php echo e($tx['transaction_no']); ?></strong>
                </div>
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs">दिनांक (Date)</span>
                    <strong class="text-navy-custom english-text"><?php echo date('d-m-Y', strtotime($tx['transaction_date'])); ?></strong>
                </div>
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs">प्रकार (Type)</span>
                    <span class="badge <?php echo ($tx['transaction_type'] === 'income') ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?> font-size-xs border px-3 py-1">
                        <?php echo e($tx['transaction_type'] === 'income' ? 'आय (Income)' : 'व्यय (Expense)'); ?>
                    </span>
                </div>
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs">लेन-देन श्रेणी (Category)</span>
                    <strong class="text-navy-custom"><?php echo e($tx['category_name']); ?></strong>
                </div>
                
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs">भुगतान माध्यम (Payment Mode)</span>
                    <strong class="text-navy-custom"><?php echo e($tx['payment_mode']); ?></strong>
                </div>
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs">राशि (Amount)</span>
                    <h5 class="fw-bold mb-0 text-navy-custom">₹<?php echo number_format($tx['amount'], 2); ?></h5>
                </div>

                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs"><?php echo ($tx['transaction_type'] === 'income') ? 'स्त्रोत / भुगतानकर्ता (Party Name)' : 'आदाता / प्राप्तकर्ता (Payee Name)'; ?></span>
                    <strong class="text-navy-custom"><?php echo e($tx['party_name'] ?: 'N/A'); ?></strong>
                </div>
                <div class="col-md-6 col-12">
                    <span class="text-muted d-block font-size-xs"><?php echo ($tx['transaction_type'] === 'income') ? 'रसीद संख्या (Receipt No)' : 'वाउचर संख्या (Voucher No)'; ?></span>
                    <strong class="text-navy-custom english-text"><?php echo e($tx['transaction_type'] === 'income' ? ($tx['receipt_no'] ?: 'N/A') : ($tx['voucher_no'] ?: 'N/A')); ?></strong>
                </div>

                <?php if ($tx['reference_no']): ?>
                    <div class="col-md-6 col-12">
                        <span class="text-muted d-block font-size-xs">संदर्भ संख्या / यूटीआर (Reference No / UTR)</span>
                        <strong class="text-navy-custom english-text"><?php echo e($tx['reference_no']); ?></strong>
                    </div>
                <?php endif; ?>

                <div class="col-12 border-top pt-2">
                    <span class="text-muted d-block font-size-xs">लेन-देन का विवरण (Description)</span>
                    <p class="mb-0 text-dark-custom"><?php echo nl2br(e($tx['description'])); ?></p>
                </div>

                <?php if ($tx['attachment_path']): ?>
                    <div class="col-12 border-top pt-3">
                        <span class="text-muted d-block font-size-xs mb-1">संलग्न प्रमाण दस्तावेज़ (Supporting Document)</span>
                        <!-- Direct downloads are blocked by htaccess, so we stream them, but since this is admin zone we can make a direct viewer or stream link -->
                        <div class="p-2 border rounded bg-light-custom d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i><?php echo e($tx['attachment_path']); ?></span>
                            <!-- In Phase 7 we will write a generic file downloader, or we can build a downloader for admin zones -->
                            <a href="../../uploads/financials/<?php echo e($tx['attachment_path']); ?>" target="_blank" class="btn btn-xs btn-navy px-3">देखें (View)</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($tx['status'] === 'cancelled'): ?>
            <div class="alert alert-danger border-0 font-hindi shadow-xs">
                <h6 class="fw-bold mb-1"><i class="bi bi-x-circle-fill me-2"></i>लेन-देन निरस्त विवरण (Cancellation Details)</h6>
                <p class="mb-1 small">निरस्तकर्ता: <?php echo e($tx['canceller_name']); ?> | दिनांक: <?php echo date('d-m-Y H:i', strtotime($tx['cancelled_at'])); ?></p>
                <p class="mb-0 small">निरस्तीकरण का कारण: <em><?php echo e($tx['cancellation_reason']); ?></em></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Timeline History Log column -->
    <div class="col-lg-5 col-12">
        <div class="card p-3 border-0 bg-white shadow-sm mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-clock-history text-gold-dark me-2"></i>ऑडिट इतिहास लॉग (Timeline History)</h6>
            
            <div class="position-relative ps-3 border-start border-light" style="margin-left: 10px;">
                <?php if (empty($history)): ?>
                    <p class="text-muted text-center py-2 mb-0">कोई इतिहास रिकॉर्ड उपलब्ध नहीं है।</p>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <div class="mb-3 position-relative">
                            <!-- Dot indicators -->
                            <span class="position-absolute bg-navy-custom rounded-circle" style="width: 10px; height: 10px; left: -20px; top: 4px;"></span>
                            <strong class="text-navy-custom d-block"><?php echo e($h['action']); ?></strong>
                            <small class="text-muted d-block font-size-xs english-text"><?php echo date('d-m-Y H:i', strtotime($h['created_at'])); ?></small>
                            <p class="mb-0 text-muted mt-1" style="font-size:0.75rem;">
                                कर्ता: <?php echo e($h['performer_name']); ?>
                                <?php if ($h['remarks']): ?>
                                    <br><em>टिप्पणी: <?php echo e($h['remarks']); ?></em>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
