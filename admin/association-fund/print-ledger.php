<?php
/**
 * Printable Association Fund Ledger
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Selected FY
$selected_fy = isset($_GET['fy_id']) ? intval($_GET['fy_id']) : 0;
$fy_name = 'N/A';
$opening_balance = 0.00;
$opening_date = '2026-04-01';

if ($db && $selected_fy > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM financial_years WHERE id = ?");
        $stmt->execute([$selected_fy]);
        $fy = $stmt->fetch();
        if ($fy) {
            $fy_name = $fy['name'];
        }

        $stmt = $db->prepare("SELECT * FROM association_fund_accounts WHERE financial_year_id = ? LIMIT 1");
        $stmt->execute([$selected_fy]);
        $acc = $stmt->fetch();
        if ($acc) {
            $opening_balance = floatval($acc['opening_balance']);
            $opening_date = $acc['opening_balance_date'];
        }
    } catch (PDOException $e) {
        error_log("Print ledger error: " . $e->getMessage());
    }
}

// Fetch ledger entries
$computed_ledger = [];
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
        $items = $stmt->fetchAll();

        $running_balance = $opening_balance;
        foreach ($items as $item) {
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
                'date' => $item['transaction_date'],
                'tx_no' => $item['transaction_no'],
                'description' => $item['description'],
                'category' => $item['category_name'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running_balance
            ];
        }
    } catch (PDOException $e) {
        error_log("Failed calculating print ledger: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Print Ledger - District Bar Association Banda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 12px;
            color: #333;
            background-color: #fff;
        }
        .header-box {
            border-bottom: 3px double #1a252f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .institution-title {
            color: #0b1d33;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: 0.5px;
        }
        .ledger-table th {
            background-color: #f1f2f6 !important;
            color: #1a252f;
            font-weight: bold;
            border: 1px solid #1a252f !important;
        }
        .ledger-table td {
            border: 1px solid #ddd !important;
        }
        @media print {
            .no-print {
                display: none;
            }
            @page {
                size: A4 landscape;
                margin: 1.5cm;
            }
            body {
                background-color: #fff;
            }
        }
    </style>
</head>
<body class="p-4">

    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 p-2 bg-light rounded no-print">
        <span class="fw-bold">खाता बही प्रिंट प्रारूप (Landscape Layout)</span>
        <div>
            <button onclick="window.print()" class="btn btn-dark btn-sm me-2"><i class="bi bi-printer"></i> प्रिंट करें (Print)</button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm">बंद करें</button>
        </div>
    </div>

    <!-- Printable Area -->
    <div class="header-box text-center">
        <h2 class="institution-title mb-1">जिला अधिवक्ता संघ, बांदा (उत्तर प्रदेश)</h2>
        <h5 class="text-uppercase tracking-wider fw-bold text-secondary mb-1">District Bar Association, Banda (U.P.)</h5>
        <div class="fw-bold text-dark mt-2" style="font-size: 14px;">
            एसोसिएशन फंड खाता बही (Association Fund Ledger Book)
        </div>
        <div class="text-muted mt-1">
            वित्तीय वर्ष: <strong><?php echo e($fy_name); ?></strong> | प्रिंट दिनांक: <?php echo date('d-m-Y H:i'); ?>
        </div>
    </div>

    <table class="table table-bordered table-striped ledger-table align-middle">
        <thead>
            <tr class="text-center">
                <th style="width: 12%;">तिथि (Date)</th>
                <th style="width: 15%;">लेनदेन संख्या</th>
                <th style="width: 35%;">विवरण (Particulars)</th>
                <th style="width: 13%;">श्रेणी</th>
                <th style="width: 11%;">डेबिट (Debit - Dr)</th>
                <th style="width: 11%;">क्रेडिट (Credit - Cr)</th>
                <th style="width: 13%;">शेष (Running Balance)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Opening Balance -->
            <tr class="fw-bold table-warning">
                <td class="text-center"><?php echo date('d-m-Y', strtotime($opening_date)); ?></td>
                <td class="text-center">-</td>
                <td>प्रारंभिक कोष पूंजी (Opening Balance Carry-Forward)</td>
                <td class="text-center">-</td>
                <td class="text-end">-</td>
                <td class="text-end">-</td>
                <td class="text-end">₹<?php echo number_format($opening_balance, 2); ?></td>
            </tr>

            <?php if (empty($computed_ledger)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4">इस वित्तीय वर्ष में कोई लेन-देन स्वीकृत नहीं है।</td>
                </tr>
            <?php else: ?>
                <?php foreach ($computed_ledger as $row): ?>
                    <tr>
                        <td class="text-center"><?php echo date('d-m-Y', strtotime($row['date'])); ?></td>
                        <td class="text-center fw-semibold"><?php echo e($row['tx_no']); ?></td>
                        <td><?php echo e($row['description']); ?></td>
                        <td><?php echo e($row['category']); ?></td>
                        <td class="text-end text-danger fw-bold">
                            <?php echo ($row['debit'] > 0) ? '₹' . number_format($row['debit'], 2) : '-'; ?>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <?php echo ($row['credit'] > 0) ? '₹' . number_format($row['credit'], 2) : '-'; ?>
                        </td>
                        <td class="text-end fw-bold">
                            ₹<?php echo number_format($row['balance'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-5 text-center font-hindi fw-semibold" style="margin-top: 80px !important;">
        <div class="col-4">
            <span class="d-block border-top pt-2" style="border-color:#333 !important;">तैयारकर्ता (Prepared By)</span>
        </div>
        <div class="col-4">
            <span class="d-block border-top pt-2" style="border-color:#333 !important;">महासचिव (Mahasachiv)</span>
        </div>
        <div class="col-4">
            <span class="d-block border-top pt-2" style="border-color:#333 !important;">अध्यक्ष (President)</span>
        </div>
    </div>

</body>
</html>
