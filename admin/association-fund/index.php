<?php
/**
 * Association Fund Control Panel Dashboard
 * District Bar Association, Banda
 */

$pageTitle = 'संघीय कोष नियंत्रण पटल (Association Fund Control Panel)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Load financial years
$financial_years = [];
$active_fy = null;
$selected_fy_id = isset($_GET['fy_id']) ? intval($_GET['fy_id']) : 0;

if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM financial_years ORDER BY start_date DESC");
        $financial_years = $stmt->fetchAll();

        // Resolve active year
        foreach ($financial_years as $fy) {
            if ($fy['status'] === 'active') {
                $active_fy = $fy;
            }
        }

        if ($selected_fy_id <= 0 && $active_fy) {
            $selected_fy_id = $active_fy['id'];
        }
    } catch (PDOException $e) {
        error_log("Failed to load FY configurations: " . $e->getMessage());
    }
}

// Fetch summaries
$summary = [
    'opening_balance' => 0.00,
    'total_income' => 0.00,
    'total_expense' => 0.00,
    'current_balance' => 0.00
];
$pending_count = 0;
$recent_transactions = [];

if ($db && $selected_fy_id > 0) {
    $summary = getAssociationFundSummary($selected_fy_id, $db);
    
    try {
        // Pending
        $pending_count = $db->query("SELECT COUNT(*) FROM association_fund_transactions WHERE status = 'pending_approval'")->fetchColumn();

        // Recent approved transactions
        $stmt = $db->prepare("
            SELECT t.*, c.name as category_name 
            FROM association_fund_transactions t
            LEFT JOIN association_fund_categories c ON c.id = t.category_id
            WHERE t.financial_year_id = ?
            ORDER BY t.transaction_date DESC, t.id DESC 
            LIMIT 5
        ");
        $stmt->execute([$selected_fy_id]);
        $recent_transactions = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading fund dashboard lists: " . $e->getMessage());
    }
}

// Resolve selected FY details
$current_fy_details = null;
foreach ($financial_years as $fy) {
    if ($fy['id'] == $selected_fy_id) {
        $current_fy_details = $fy;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">संघीय कोष डैशबोर्ड (Association Fund Management)</h4>
    <div class="d-flex gap-2">
        <a href="ledger.php?fy_id=<?php echo $selected_fy_id; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-journal-text me-1"></i>खाता बही (Ledger)</a>
        <a href="transactions.php?fy_id=<?php echo $selected_fy_id; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-list-columns-reverse me-1"></i>लेन-देन रजिस्टर</a>
        <a href="documents.php?fy_id=<?php echo $selected_fy_id; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-file-earmark-pdf-fill me-1"></i>ऑडिट रिपोर्ट</a>
        <a href="income/create.php" class="btn btn-xs btn-success fw-semibold"><i class="bi bi-plus-circle-fill me-1"></i>आय दर्ज करें</a>
        <a href="expense/create.php" class="btn btn-xs btn-danger fw-semibold"><i class="bi bi-dash-circle-fill me-1"></i>व्यय दर्ज करें</a>
    </div>
</div>

<!-- Financial Year Selection bar -->
<div class="card p-3 mb-4 border-0 shadow-sm font-hindi small">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <label class="input-group-text bg-navy-custom text-white" for="fy_select">वित्तीय वर्ष चुनें (FY):</label>
                <select class="form-select" id="fy_select" name="fy_id" onchange="this.form.submit()">
                    <?php foreach ($financial_years as $fy): ?>
                        <option value="<?php echo $fy['id']; ?>" <?php echo ($selected_fy_id == $fy['id']) ? 'selected' : ''; ?>>
                            <?php echo e($fy['name']); ?> (<?php echo ($fy['status'] === 'active') ? 'सक्रिय / Active' : ($fy['status'] === 'closed' ? 'बंद / Closed' : 'भविष्य'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-8 text-end text-muted font-size-xs">
            <?php if ($current_fy_details): ?>
                अवधि: <?php echo date('d-m-Y', strtotime($current_fy_details['start_date'])); ?> से <?php echo date('d-m-Y', strtotime($current_fy_details['end_date'])); ?>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Fund Summary Stats Widgets -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom text-uppercase">
    <!-- Opening Balance -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-navy border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">प्रारंभिक शेष (Opening Balance)</span>
            <h4 class="fw-bold text-end mb-0 text-navy-custom">₹<?php echo number_format($summary['opening_balance'], 2); ?></h4>
        </div>
    </div>

    <!-- Income -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-success border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल आय (Total Income)</span>
            <h4 class="fw-bold text-end mb-0 text-success">₹<?php echo number_format($summary['total_income'], 2); ?></h4>
        </div>
    </div>

    <!-- Expenditure -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-white shadow-xs border-start border-danger border-4 h-100">
            <span class="text-secondary font-size-xs d-block mb-1">कुल व्यय (Total Expenditure)</span>
            <h4 class="fw-bold text-end mb-0 text-danger">₹<?php echo number_format($summary['total_expense'], 2); ?></h4>
        </div>
    </div>

    <!-- Current Balance -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-navy-custom text-white shadow-xs h-100">
            <span class="text-light-custom font-size-xs d-block mb-1">वर्तमान शेष (Current Balance)</span>
            <h4 class="fw-bold text-end mb-0 text-gold-custom">₹<?php echo number_format($summary['current_balance'], 2); ?></h4>
        </div>
    </div>
</div>

<!-- Alert for pending approvals -->
<?php if ($pending_count > 0): ?>
    <div class="alert alert-warning border-0 rounded-3 mb-4 shadow-xs font-hindi small d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>कोष में <strong><?php echo $pending_count; ?> लेन-देन</strong> अनुमोदन समीक्षा के लिए लंबित हैं।</span>
        <a href="transactions.php?status=pending_approval" class="btn btn-xs btn-navy fw-semibold">समीक्षा करें</a>
    </div>
<?php endif; ?>

<!-- Recent Transactions Table list -->
<div class="card border-0 shadow-sm font-hindi small mb-5">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-list-check text-gold-custom me-2"></i>हालिया लेन-देन (Recent Approved Transactions)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2">लेनदेन सं.</th>
                        <th>तिथि</th>
                        <th>प्रकार</th>
                        <th>श्रेणी (Category)</th>
                        <th>विवरण (Description)</th>
                        <th>भुगतान माध्यम</th>
                        <th class="text-end">राशि (Amount)</th>
                        <th class="text-end">विवरण</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_transactions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">इस वित्तीय वर्ष में कोई लेन-देन स्वीकृत नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $rt): ?>
                            <tr>
                                <td class="py-2 english-text"><?php echo e($rt['transaction_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($rt['transaction_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo ($rt['transaction_type'] === 'income') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'; ?> font-size-xs px-2 py-0.5">
                                        <?php echo e($rt['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($rt['category_name']); ?></td>
                                <td><?php echo e($rt['description']); ?></td>
                                <td><?php echo e($rt['payment_mode']); ?></td>
                                <td class="text-end fw-bold <?php echo ($rt['transaction_type'] === 'income') ? 'text-success' : 'text-danger'; ?>">
                                    ₹<?php echo number_format($rt['amount'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <a href="view.php?id=<?php echo $rt['id']; ?>" class="btn btn-xs btn-outline-navy py-0.5">विवरण</a>
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
