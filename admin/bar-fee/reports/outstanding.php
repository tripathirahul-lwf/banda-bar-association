<?php
/**
 * Advocate Bar Fee Outstanding Report
 * District Bar Association, Banda
 */

$pageTitle = 'बकाया शुल्क विवरण (Advocate Bar Fee Outstanding Report)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Filter parameters
$fee_type = intval($_GET['fee_type'] ?? 0);
$financial_year = sanitize(trim($_GET['financial_year'] ?? ''));
$status = sanitize(trim($_GET['status'] ?? ''));

$fee_types = [];
$financial_years = [];
$dues = [];
$total_outstanding = 0.00;

if ($db) {
    try {
        $fee_types = $db->query("SELECT id, name FROM bar_fee_types WHERE status = 'active'")->fetchAll();
        $financial_years = $db->query("SELECT id, name FROM financial_years ORDER BY name DESC")->fetchAll();

        // Build Query
        $where = ["d.outstanding_amount > 0", "d.status != 'cancelled'"];
        $params = [];

        if ($fee_type > 0) {
            $where[] = "d.fee_type_id = :fee_type";
            $params[':fee_type'] = $fee_type;
        }

        if (!empty($financial_year)) {
            $where[] = "d.financial_year = :fy";
            $params[':fy'] = $financial_year;
        }

        if (!empty($status)) {
            $where[] = "d.status = :status";
            $params[':status'] = $status;
        }

        $where_sql = implode(" AND ", $where);
        $query = "
            SELECT d.*, m.full_name as member_name, m.membership_no, m.enrollment_no, t.name as fee_name
            FROM member_fee_dues d
            JOIN members m ON m.id = d.member_id
            JOIN bar_fee_types t ON t.id = d.fee_type_id
            WHERE $where_sql
            ORDER BY d.due_date ASC, d.id ASC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $dues = $stmt->fetchAll();

        // Aggregate sum
        foreach ($dues as $d) {
            $total_outstanding += floatval($d['outstanding_amount']);
        }

    } catch (PDOException $e) {
        error_log("Failed generating outstanding report: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi no-print">
    <h4 class="text-navy-custom fw-bold mb-0">बकाया शुल्क रिपोर्ट (Outstanding Fees Report)</h4>
    <div class="d-flex gap-2">
        <a href="../index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-speedometer2 me-1"></i>डैशबोर्ड</a>
        <button onclick="window.print()" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>रिपोर्ट प्रिंट</button>
    </div>
</div>

<!-- Filters (no-print) -->
<div class="card border-0 bg-light-custom p-3 font-hindi small mb-4 no-print">
    <form method="GET" action="outstanding.php" class="row g-2">
        <div class="col-md-3">
            <label class="form-label fw-bold text-navy-custom">शुल्क प्रकार</label>
            <select name="fee_type" class="form-select form-select-sm">
                <option value="">-- सभी शुल्क --</option>
                <?php foreach ($fee_types as $ft): ?>
                    <option value="<?php echo $ft['id']; ?>" <?php echo ($fee_type === intval($ft['id'])) ? 'selected' : ''; ?>><?php echo e($ft['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold text-navy-custom">वित्तीय वर्ष</label>
            <select name="financial_year" class="form-select form-select-sm">
                <option value="">-- सभी वर्ष --</option>
                <?php foreach ($financial_years as $fy): ?>
                    <option value="<?php echo e($fy['name']); ?>" <?php echo ($financial_year === $fy['name']) ? 'selected' : ''; ?>><?php echo e($fy['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold text-navy-custom">स्थिति (Status)</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">-- बकाया स्थिति --</option>
                <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pending (लंबित)</option>
                <option value="partially_paid" <?php echo ($status === 'partially_paid') ? 'selected' : ''; ?>>Partially Paid (आंशिक)</option>
                <option value="overdue" <?php echo ($status === 'overdue') ? 'selected' : ''; ?>>Overdue (अवधिपार)</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end gap-1">
            <button type="submit" class="btn btn-navy btn-sm flex-fill font-hindi"><i class="bi bi-funnel me-1"></i>फ़िल्टर</button>
            <a href="outstanding.php" class="btn btn-secondary btn-sm font-hindi">रीसेट</a>
        </div>
    </form>
</div>

<!-- Print Report Header -->
<div class="text-center mb-4 d-none d-print-block font-hindi">
    <h3 class="fw-bold mb-0">जिला अधिवक्ता संघ, बांदा</h3>
    <h5 class="text-muted mb-1">अधिवक्ता शुल्क बकाया विवरण सूची (Outstanding Dues Statement)</h5>
    <p class="small mb-0">दिनांक: <?php echo date('d-m-Y'); ?></p>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल बकाया (Total Outstanding)</span>
            <h4 class="fw-bold text-danger mb-0 text-end">₹<?php echo number_format($total_outstanding, 2); ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-white border border-light shadow-xs h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल बकाया मामले (Count)</span>
            <h4 class="fw-bold text-navy-custom mb-0 text-end"><?php echo count($dues); ?></h4>
        </div>
    </div>
</div>

<!-- Dues Table -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2 no-print">
        <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-octagon-fill text-gold-custom me-2"></i>बकाया प्रविष्टियाँ (Outstanding Records)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">अधिवक्ता (Advocate)</th>
                        <th>सदस्यता</th>
                        <th>नामांकन</th>
                        <th>शुल्क प्रकार</th>
                        <th>वित्तीय वर्ष</th>
                        <th>अंतिम तिथि</th>
                        <th class="text-end">कुल देय</th>
                        <th class="text-end">प्राप्त जमा</th>
                        <th class="text-end">शेष बकाया</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dues)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">कोई बकाया शुल्क रिकॉर्ड नहीं मिला।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dues as $d): ?>
                            <tr>
                                <td class="py-2 fw-bold text-navy-custom"><?php echo e($d['member_name']); ?></td>
                                <td class="english-text"><?php echo e($d['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($d['enrollment_no']); ?></td>
                                <td class="fw-bold text-navy-custom"><?php echo e($d['fee_name']); ?></td>
                                <td class="english-text"><?php echo e($d['financial_year']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($d['due_date'])); ?></td>
                                <td class="text-end text-navy english-text">₹<?php echo number_format($d['payable_amount'], 2); ?></td>
                                <td class="text-end text-success english-text">₹<?php echo number_format($d['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-danger english-text">₹<?php echo number_format($d['outstanding_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold text-navy-custom text-end">
                            <td colspan="8" class="py-2">कुल योग (Grand Total):</td>
                            <td class="text-danger">₹<?php echo number_format($total_outstanding, 2); ?></td>
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
