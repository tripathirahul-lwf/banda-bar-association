<?php
/**
 * President Fund Review and Approval Workspace
 * District Bar Association, Banda
 */

$pageTitle = 'अध्यक्षीय कोष समीक्षा पटल (Presidential Fund Desk)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President Role
requireRole('president');

$db = Database::getConnection();

$active_fy = null;
$summary = [
    'opening_balance' => 0.00,
    'total_income' => 0.00,
    'total_expense' => 0.00,
    'current_balance' => 0.00
];
$pending_list = [];
$recent_list = [];

if ($db) {
    try {
        // Resolve active year
        $active_fy = $db->query("SELECT * FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();

        if ($active_fy) {
            $summary = getAssociationFundSummary($active_fy['id'], $db);

            // Fetch pending approvals
            $stmt = $db->prepare("
                SELECT t.*, c.name as category_name, u.username as creator_name 
                FROM association_fund_transactions t
                LEFT JOIN association_fund_categories c ON c.id = t.category_id
                LEFT JOIN users u ON u.id = t.created_by
                WHERE t.financial_year_id = ? AND t.status = 'pending_approval'
                ORDER BY t.transaction_date ASC, t.id ASC
            ");
            $stmt->execute([$active_fy['id']]);
            $pending_list = $stmt->fetchAll();

            // Fetch recent transactions (approved/rejected/cancelled)
            $stmt = $db->prepare("
                SELECT t.*, c.name as category_name 
                FROM association_fund_transactions t
                LEFT JOIN association_fund_categories c ON c.id = t.category_id
                WHERE t.financial_year_id = ? AND t.status != 'pending_approval' AND t.status != 'draft'
                ORDER BY t.transaction_date DESC, t.id DESC 
                LIMIT 5
            ");
            $stmt->execute([$active_fy['id']]);
            $recent_list = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("President fund loading error: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">अध्यक्षीय कोष समीक्षा पटल (Presidential Fund Desk)</h4>
    <span class="badge bg-warning text-navy-custom px-3 py-1 fw-bold">अध्यक्ष जोन (Secure)</span>
</div>

<!-- Fund Summary Stats Widgets -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <!-- Opening Balance -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-navy border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">प्रारंभिक शेष</span>
            <h4 class="fw-bold text-end mb-0 text-navy-custom">₹<?php echo number_format($summary['opening_balance'], 2); ?></h4>
        </div>
    </div>

    <!-- Income -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-success border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल आय</span>
            <h4 class="fw-bold text-end mb-0 text-success">₹<?php echo number_format($summary['total_income'], 2); ?></h4>
        </div>
    </div>

    <!-- Expenditure -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-danger border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल व्यय</span>
            <h4 class="fw-bold text-end mb-0 text-danger">₹<?php echo number_format($summary['total_expense'], 2); ?></h4>
        </div>
    </div>

    <!-- Current Balance -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-navy-custom text-white shadow-xs h-100">
            <span class="text-light-custom font-size-xs d-block mb-1">वर्तमान शेष</span>
            <h4 class="fw-bold text-end mb-0 text-gold-custom">₹<?php echo number_format($summary['current_balance'], 2); ?></h4>
        </div>
    </div>
</div>

<!-- Pending Approvals Section -->
<div class="card border-0 shadow-sm font-hindi small mb-4">
    <div class="card-header bg-warning text-dark py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-patch-question-fill me-2"></i>अनुमोदन हेतु लंबित लेन-देन (Pending Approvals)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">लेनदेन सं.</th>
                        <th>तारीख</th>
                        <th>प्रकार</th>
                        <th>श्रेणी</th>
                        <th>प्राप्तकर्ता/भुगतानकर्ता</th>
                        <th>विवरण (Description)</th>
                        <th>भुगतान माध्यम</th>
                        <th class="text-end">राशि (Amount)</th>
                        <th class="text-end" style="width: 180px;">कार्य (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">अनुमोदन के लिए कोई लेन-देन लंबित नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_list as $pt): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold"><?php echo e($pt['transaction_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($pt['transaction_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo ($pt['transaction_type'] === 'income') ? 'bg-success' : 'bg-danger'; ?> font-size-xs px-2 py-0.5">
                                        <?php echo e($pt['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($pt['category_name']); ?></td>
                                <td><?php echo e($pt['party_name'] ?: '-'); ?></td>
                                <td><?php echo e($pt['description']); ?></td>
                                <td><?php echo e($pt['payment_mode']); ?></td>
                                <td class="text-end fw-bold <?php echo ($pt['transaction_type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                                    ₹<?php echo number_format($pt['amount'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="action.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="tx_id" value="<?php echo $pt['id']; ?>">
                                        
                                        <button type="submit" name="action_type" value="approve" class="btn btn-xs btn-success fw-semibold py-0.5 px-2 me-1" onclick="return confirm('क्या आप इस लेन-देन को स्वीकृत करना चाहते हैं?');">स्वीकारें</button>
                                        <button type="button" class="btn btn-xs btn-danger fw-semibold py-0.5 px-2" onclick="var r = prompt('अस्वीकार करने का कारण दर्ज करें:'); if(r){ var f = this.form; var inp = document.createElement('input'); inp.type='hidden'; inp.name='reason'; inp.value=r; f.appendChild(inp); var act = document.createElement('input'); act.type='hidden'; act.name='action_type'; act.value='reject'; f.appendChild(act); f.submit(); }">अस्वीकार</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent approved/rejected list -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-list-check text-gold-custom me-2"></i>हालिया इतिहास (Recent Audit Trail)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">लेनदेन सं.</th>
                        <th>तारीख</th>
                        <th>प्रकार</th>
                        <th>श्रेणी</th>
                        <th>विवरण</th>
                        <th class="text-end">राशि (Amount)</th>
                        <th>स्थिति</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">कोई हालिया लेन-देन रिकॉर्ड नहीं मिला।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_list as $rl): ?>
                            <tr>
                                <td class="py-2 english-text"><?php echo e($rl['transaction_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($rl['transaction_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo ($rl['transaction_type'] === 'income') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'; ?> font-size-xs px-2 py-0.5">
                                        <?php echo e($rl['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($rl['category_name']); ?></td>
                                <td><?php echo e($rl['description']); ?></td>
                                <td class="text-end fw-bold <?php echo ($rl['transaction_type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                                    ₹<?php echo number_format($rl['amount'], 2); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($rl['status'] === 'approved') ? 'bg-success' : 'bg-danger'; ?> font-size-xs">
                                        <?php echo e($rl['status']); ?>
                                    </span>
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
