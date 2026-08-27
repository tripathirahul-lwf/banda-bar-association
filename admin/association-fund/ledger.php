<?php
/**
 * Association Fund Ledger Ledger Account Book
 * District Bar Association, Banda
 */

$pageTitle = 'खाता बही (Association Fund Ledger)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Selected FY
$selected_fy = isset($_GET['fy_id']) ? intval($_GET['fy_id']) : 0;
$financial_years = [];
$active_fy = null;

if ($db) {
    try {
        $financial_years = $db->query("SELECT id, name, status FROM financial_years ORDER BY start_date DESC")->fetchAll();
        foreach ($financial_years as $fy) {
            if ($fy['status'] === 'active') {
                $active_fy = $fy;
            }
        }
        if ($selected_fy <= 0 && $active_fy) {
            $selected_fy = $active_fy['id'];
        }
    } catch (PDOException $e) {
        error_log("Failed loading FY list: " . $e->getMessage());
    }
}

// Fetch opening balance details
$opening_balance = 0.00;
$opening_date = '';
$opening_notes = '';

if ($db && $selected_fy > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM association_fund_accounts WHERE financial_year_id = ? LIMIT 1");
        $stmt->execute([$selected_fy]);
        $acc = $stmt->fetch();
        if ($acc) {
            $opening_balance = floatval($acc['opening_balance']);
            $opening_date = $acc['opening_balance_date'];
            $opening_notes = $acc['opening_balance_notes'];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch opening balance: " . $e->getMessage());
    }
}

// Fetch all approved transactions sorted chronologically
$ledger_items = [];
if ($db && $selected_fy > 0) {
    try {
        $stmt = $db->prepare("
            SELECT t.*, c.name as category_name 
            FROM association_fund_transactions t
            LEFT JOIN association_fund_categories c ON c.id = t.category_id
            WHERE t.financial_year_id = ? AND t.status = 'approved'
            ORDER BY t.transaction_date ASC, t.id ASC
        ");
        $stmt->execute([$selected_fy]);
        $ledger_items = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load approved ledger items: " . $e->getMessage());
    }
}

// Compute running balance
$running_balance = $opening_balance;
$computed_ledger = [];

foreach ($ledger_items as $item) {
    $debit = 0.00;
    $credit = 0.00;
    if ($item['transaction_type'] === 'income') {
        $credit = floatval($item['amount']);
        $running_balance += $credit;
    } else {
        $debit = floatval($item['amount']);
        $running_balance -= $debit;
    }
    
    $computed_ledger[] = [
        'id' => $item['id'],
        'date' => $item['transaction_date'],
        'tx_no' => $item['transaction_no'],
        'description' => $item['description'],
        'category' => $item['category_name'],
        'payment_mode' => $item['payment_mode'],
        'reference' => $item['reference_no'] ?: ($item['receipt_no'] ?: ($item['voucher_no'] ?: '')),
        'debit' => $debit,
        'credit' => $credit,
        'balance' => $running_balance
    ];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">खाता बही (Association Fund Ledger)</h4>
    <div class="d-flex gap-2">
        <a href="print-ledger.php?fy_id=<?php echo $selected_fy; ?>" target="_blank" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer-fill me-1"></i>प्रिंट बही (Print)</a>
        <a href="index.php" class="btn btn-xs btn-navy fw-semibold">डैशबोर्ड पर जाएं</a>
    </div>
</div>

<!-- FY selector bar -->
<div class="card p-3 mb-4 border-0 shadow-sm font-hindi small">
    <form method="GET" action="ledger.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <label class="input-group-text bg-navy-custom text-white" for="fy_select">वित्तीय वर्ष चुनें (FY):</label>
                <select class="form-select" id="fy_select" name="fy_id" onchange="this.form.submit()">
                    <?php foreach ($financial_years as $fy): ?>
                        <option value="<?php echo $fy['id']; ?>" <?php echo ($selected_fy == $fy['id']) ? 'selected' : ''; ?>>
                            <?php echo e($fy['name']); ?> (<?php echo ($fy['status'] === 'active') ? 'सक्रिय' : 'बंद'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-8 text-end text-muted font-size-xs">
            बही वर्ष: ₹<?php echo number_format($opening_balance, 2); ?> प्रारंभिक पूंजी के साथ शुरू हुआ।
        </div>
    </form>
</div>

<!-- Ledger Sheet Grid -->
<div class="table-responsive bg-white rounded shadow-sm border font-hindi small mb-5">
    <table class="table table-bordered table-hover align-middle mb-0 text-muted">
        <thead class="table-light text-navy-custom text-center">
            <tr>
                <th class="py-2" style="width: 10%;">तिथि (Date)</th>
                <th style="width: 15%;">लेनदेन संख्या</th>
                <th style="width: 30%;">विवरण (Particulars)</th>
                <th style="width: 12%;">श्रेणी (Category)</th>
                <th style="width: 10%;">डेबिट (Debit / Exp)</th>
                <th style="width: 10%;">क्रेडिट (Credit / Inc)</th>
                <th style="width: 13%;">शेष (Balance)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Opening Balance Row -->
            <tr class="table-warning bg-opacity-10 text-navy-custom fw-semibold">
                <td class="text-center english-text py-2"><?php echo date('d-m-Y', strtotime($opening_date ?: '2026-04-01')); ?></td>
                <td class="text-center english-text">-</td>
                <td>प्रारंभिक शेष (Opening Balance Carry-Forward) <?php echo $opening_notes ? '('.e($opening_notes).')' : ''; ?></td>
                <td class="text-center">-</td>
                <td class="text-end">-</td>
                <td class="text-end">-</td>
                <td class="text-end fw-bold text-navy-custom">₹<?php echo number_format($opening_balance, 2); ?></td>
            </tr>

            <?php if (empty($computed_ledger)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">इस वित्तीय वर्ष में कोई लेन-देन स्वीकृत नहीं है।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($computed_ledger as $row): ?>
                    <tr>
                        <td class="text-center english-text py-2"><?php echo date('d-m-Y', strtotime($row['date'])); ?></td>
                        <td class="english-text text-center fw-semibold">
                            <a href="view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none text-navy-custom"><?php echo e($row['tx_no']); ?></a>
                        </td>
                        <td>
                            <?php echo e($row['description']); ?>
                            <span class="text-muted d-block font-size-xs" style="font-size: 0.72rem;">माध्यम: <?php echo e($row['payment_mode']); ?> <?php echo $row['reference'] ? '| संदर्भ: '.e($row['reference']) : ''; ?></span>
                        </td>
                        <td><?php echo e($row['category']); ?></td>
                        <td class="text-end text-danger font-monospace fw-bold">
                            <?php echo ($row['debit'] > 0) ? '₹' . number_format($row['debit'], 2) : '-'; ?>
                        </td>
                        <td class="text-end text-success font-monospace fw-bold">
                            <?php echo ($row['credit'] > 0) ? '₹' . number_format($row['credit'], 2) : '-'; ?>
                        </td>
                        <td class="text-end font-monospace fw-bold text-navy-custom">
                            ₹<?php echo number_format($row['balance'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
