<?php
/**
 * Advocate Bar Fee Collection Report
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता शुल्क संग्रह रिपोर्ट (Advocate Bar Fee Collection Report)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Filter parameters
$start_date = sanitize(trim($_GET['start_date'] ?? date('Y-m-01')));
$end_date = sanitize(trim($_GET['end_date'] ?? date('Y-m-d')));
$fee_type = intval($_GET['fee_type'] ?? 0);
$payment_mode = sanitize(trim($_GET['payment_mode'] ?? ''));

$fee_types = [];
$payments = [];
$total_collected = 0.00;
$payment_modes_count = [];

if ($db) {
    try {
        $fee_types = $db->query("SELECT id, name FROM bar_fee_types WHERE status = 'active'")->fetchAll();

        // Build Query
        $where = ["p.status = 'confirmed'", "p.payment_date BETWEEN :start AND :end"];
        $params = [
            ':start' => $start_date,
            ':end' => $end_date
        ];

        if ($fee_type > 0) {
            // Filter by allocated due type
            $where[] = "EXISTS (
                SELECT 1 FROM bar_fee_payment_allocations a
                JOIN member_fee_dues d ON d.id = a.due_id
                WHERE a.payment_id = p.id AND d.fee_type_id = :fee_type
            )";
            $params[':fee_type'] = $fee_type;
        }

        if (!empty($payment_mode)) {
            $where[] = "p.payment_mode = :mode";
            $params[':mode'] = $payment_mode;
        }

        $where_sql = implode(" AND ", $where);
        $query = "
            SELECT p.*, m.full_name as member_name, m.membership_no
            FROM bar_fee_payments p
            JOIN members m ON m.id = p.member_id
            WHERE $where_sql
            ORDER BY p.payment_date ASC, p.id ASC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        // Compute aggregations
        foreach ($payments as $p) {
            $amt = floatval($p['amount']);
            $total_collected += $amt;
            
            $mode = $p['payment_mode'];
            $payment_modes_count[$mode] = ($payment_modes_count[$mode] ?? 0.00) + $amt;
        }

    } catch (PDOException $e) {
        error_log("Failed generating collection report: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi no-print">
    <h4 class="text-navy-custom fw-bold mb-0">अधिवक्ता शुल्क संग्रह विवरण (Collection Report)</h4>
    <div class="d-flex gap-2">
        <a href="../index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-speedometer2 me-1"></i>डैशबोर्ड</a>
        <button onclick="window.print()" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>रिपोर्ट प्रिंट</button>
    </div>
</div>

<!-- Filters Panel (no-print) -->
<div class="card border-0 bg-light-custom p-3 font-hindi small mb-4 no-print">
    <form method="GET" action="collection.php" class="row g-2">
        <div class="col-md-3">
            <label class="form-label fw-bold text-navy-custom">प्रारंभ तिथि</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo e($start_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold text-navy-custom">अंतिम तिथि</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo e($end_date); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold text-navy-custom">शुल्क प्रकार</label>
            <select name="fee_type" class="form-select form-select-sm">
                <option value="">-- सभी शुल्क --</option>
                <?php foreach ($fee_types as $ft): ?>
                    <option value="<?php echo $ft['id']; ?>" <?php echo ($fee_type === intval($ft['id'])) ? 'selected' : ''; ?>><?php echo e($ft['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold text-navy-custom">भुगतान माध्यम</label>
            <select name="payment_mode" class="form-select form-select-sm">
                <option value="">-- सभी माध्यम --</option>
                <option value="Cash" <?php echo ($payment_mode === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="UPI" <?php echo ($payment_mode === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                <option value="Bank Transfer" <?php echo ($payment_mode === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="Cheque" <?php echo ($payment_mode === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end gap-1">
            <button type="submit" class="btn btn-navy btn-sm flex-fill font-hindi"><i class="bi bi-funnel me-1"></i>फ़िल्टर</button>
            <a href="collection.php" class="btn btn-secondary btn-sm font-hindi">रीसेट</a>
        </div>
    </form>
</div>

<!-- Print Report Header -->
<div class="text-center mb-4 d-none d-print-block font-hindi">
    <h3 class="fw-bold mb-0">जिला अधिवक्ता संघ, बांदा</h3>
    <h5 class="text-muted mb-1">अधिवक्ता शुल्क दैनिक संग्रह रिपोर्ट (Collection Statement)</h5>
    <p class="small mb-0">अवधि: <?php echo date('d-m-Y', strtotime($start_date)); ?> से <?php echo date('d-m-Y', strtotime($end_date)); ?></p>
</div>

<!-- Analytics cards -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल संकलन (Total Collected)</span>
            <h4 class="fw-bold text-success mb-0 text-end">₹<?php echo number_format($total_collected, 2); ?></h4>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-2">माध्यम-वार सारांश (Payment Mode Breakdown)</span>
            <div class="row g-2 text-center text-secondary font-size-xs">
                <?php if (empty($payment_modes_count)): ?>
                    <div class="col-12 text-center">कोई भुगतान वर्गीकरण उपलब्ध नहीं है।</div>
                <?php else: ?>
                    <?php foreach ($payment_modes_count as $mode => $val): ?>
                        <div class="col-3 border-end">
                            <span class="d-block text-muted"><?php echo e($mode); ?></span>
                            <strong class="text-navy-custom">₹<?php echo number_format($val, 2); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2 no-print">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>संग्रह लेन-देन विवरण (Payments Log)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">रसीद सं.</th>
                        <th>भुगतान तिथि</th>
                        <th>अधिवक्ता (Advocate)</th>
                        <th>सदस्यता</th>
                        <th>भुगतान माध्यम</th>
                        <th>संदर्भ संख्या</th>
                        <th class="text-end">प्राप्त राशि</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">चयनित अवधि या फ़िल्टर में कोई संग्रह नहीं मिला।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold text-navy-custom"><?php echo e($p['receipt_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                <td class="fw-bold text-navy-custom"><?php echo e($p['member_name']); ?></td>
                                <td class="english-text"><?php echo e($p['membership_no']); ?></td>
                                <td><?php echo e($p['payment_mode']); ?></td>
                                <td class="english-text"><?php echo e($p['transaction_reference'] ?: '-'); ?></td>
                                <td class="fw-bold text-success text-end">₹<?php echo number_format($p['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold text-navy-custom text-end">
                            <td colspan="6" class="py-2">कुल योग (Grand Total):</td>
                            <td class="text-success">₹<?php echo number_format($total_collected, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white;
    }
}
</style>

<?php 
require_once __DIR__ . '/../../../includes/dashboard/footer.php';
?>
