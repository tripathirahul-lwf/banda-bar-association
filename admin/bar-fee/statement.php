<?php
/**
 * Member Fee Statement - Printable Page
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce login
$user = currentUser();
if (!$user) {
    die('अनधिकृत प्रवेश। कृपया पहले लॉगिन करें।');
}

$db = Database::getConnection();
$member_id = intval($_GET['member_id'] ?? 0);
$member = null;
$ledger_items = [];
$summary = [];

if ($db && $member_id > 0) {
    try {
        // Fetch member
        $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $m_stmt->execute([$member_id]);
        $member = $m_stmt->fetch();

        if (!$member) {
            die('अधिवक्ता सदस्य रिकॉर्ड नहीं मिला।');
        }

        // Check authorization: Members can only print their own statement
        if ($user['role'] === 'member' && intval($user['member_id']) !== $member_id) {
            die('सुरक्षा उल्लंघन: आप केवल अपना स्टेटमेंट देख सकते हैं।');
        }

        // Fetch Dues
        $dues_stmt = $db->prepare("
            SELECT d.*, t.name as fee_name 
            FROM member_fee_dues d
            JOIN bar_fee_types t ON t.id = d.fee_type_id
            WHERE d.member_id = ? AND d.status != 'cancelled'
            ORDER BY d.created_at ASC
        ");
        $dues_stmt->execute([$member_id]);
        $dues = $dues_stmt->fetchAll();

        // Fetch Payments
        $pay_stmt = $db->prepare("
            SELECT p.* 
            FROM bar_fee_payments p
            WHERE p.member_id = ? AND p.status = 'confirmed'
            ORDER BY p.payment_date ASC, p.id ASC
        ");
        $pay_stmt->execute([$member_id]);
        $payments = $pay_stmt->fetchAll();

        // Compile ledger items
        foreach ($dues as $d) {
            $ledger_items[] = [
                'date' => $d['created_at'],
                'particulars' => $d['fee_name'] . ' (' . ($d['period_label'] ?: $d['financial_year']) . ')',
                'type' => 'due',
                'due_amount' => floatval($d['original_amount']),
                'payment_amount' => 0.00,
                'adjustment_amount' => floatval($d['discount_amount']) * -1 + floatval($d['penalty_amount']),
                'receipt_no' => ''
            ];
        }

        foreach ($payments as $p) {
            $ledger_items[] = [
                'date' => $p['payment_date'],
                'particulars' => 'भुगतान रसीद सं. ' . $p['receipt_no'],
                'type' => 'payment',
                'due_amount' => 0.00,
                'payment_amount' => floatval($p['amount']),
                'adjustment_amount' => 0.00,
                'receipt_no' => $p['receipt_no']
            ];
        }

        // Sort ledger by date
        usort($ledger_items, function($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        // Summary calculations
        $summary = getMemberFeeSummary($member_id, $db);

    } catch (PDOException $e) {
        error_log("Failed generating member statement: " . $e->getMessage());
    }
} else {
    die('अवैध सदस्य अनुरोध।');
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>शुल्क विवरण विवरण पत्र (Statement) - <?php echo e($member['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <style>
        body {
            background: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #222;
        }
        .statement-container {
            max-width: 900px;
            margin: 30px auto;
            border: 1px solid #002147;
            padding: 30px;
            background: #fff;
        }
        .header-title {
            color: #002147;
            font-weight: 800;
            text-transform: uppercase;
        }
        .statement-header {
            border-bottom: 2px solid #002147;
            padding-bottom: 15px;
            text-align: center;
        }
        @media print {
            body {
                background: none;
                margin: 0;
            }
            .statement-container {
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container no-print text-center mt-4">
    <button onclick="window.print()" class="btn btn-primary px-4 font-hindi"><i class="bi bi-printer me-1"></i>विवरण पत्र प्रिंट करें (Print Statement)</button>
    <a href="javascript:window.close()" class="btn btn-outline-secondary px-3 ms-2">बंद करें</a>
</div>

<div class="statement-container">
    <!-- Header -->
    <div class="statement-header mb-4">
        <h2 class="header-title mb-1">जिला अधिवक्ता संघ, बांदा</h2>
        <h4 class="mb-1 fw-bold text-navy" style="font-size: 1.1rem; color: #002147;">DISTRICT BAR ASSOCIATION, BANDA</h4>
        <p class="text-secondary small mb-0">ESTD. 1937 | REGISTERED UNDER SOCIETIES ACT</p>
        <span class="badge bg-navy text-white px-3 py-1 mt-2 text-uppercase fw-bold" style="background-color: #002147; font-size: 0.8rem;">Advocate Bar Fee Statement</span>
    </div>

    <!-- Member Info -->
    <div class="row g-2 mb-4 font-size-sm" style="font-size: 0.9rem;">
        <div class="col-6">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-secondary py-1" style="width: 40%;">अधिवक्ता का नाम:</td>
                    <td class="fw-bold text-navy py-1"><?php echo e($member['full_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-secondary py-1">सदस्यता संख्या:</td>
                    <td class="fw-bold py-1"><?php echo e($member['membership_no']); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-6 border-start">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-secondary py-1" style="width: 40%;">नामांकन संख्या:</td>
                    <td class="fw-semibold py-1"><?php echo e($member['enrollment_no']); ?></td>
                </tr>
                <tr>
                    <td class="text-secondary py-1">मोबाइल नम्बर:</td>
                    <td class="fw-semibold py-1"><?php echo e($member['mobile']); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="p-3 bg-light rounded mb-4" style="font-size: 0.9rem;">
        <div class="row g-2 text-center text-navy">
            <div class="col-4">
                <span class="text-secondary d-block font-size-xs mb-1">कुल शुल्क देयता (Total Dues):</span>
                <strong class="fs-5">₹<?php echo number_format($summary['total_due'], 2); ?></strong>
            </div>
            <div class="col-4 border-start">
                <span class="text-secondary d-block font-size-xs mb-1">कुल प्राप्त भुगतान (Paid):</span>
                <strong class="fs-5 text-success">₹<?php echo number_format($summary['total_paid'], 2); ?></strong>
            </div>
            <div class="col-4 border-start">
                <span class="text-secondary d-block font-size-xs mb-1">कुल शेष बकाया (Outstanding):</span>
                <strong class="fs-5 text-danger">₹<?php echo number_format($summary['total_outstanding'], 2); ?></strong>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <table class="table table-bordered align-middle mb-5" style="font-size: 0.85rem;">
        <thead class="table-light text-center">
            <tr>
                <th class="py-2" style="width: 15%;">तिथि (Date)</th>
                <th class="text-start">विवरण (Particulars)</th>
                <th class="text-end" style="width: 15%;">देय शुल्क (Debit)</th>
                <th class="text-end" style="width: 15%;">प्राप्त जमा (Credit)</th>
                <th class="text-end" style="width: 15%;">समायोजन (Adjustment)</th>
                <th class="text-end" style="width: 15%;">बकाया (Balance)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $running_bal = 0.00;
            if (empty($ledger_items)): 
            ?>
                <tr>
                    <td colspan="6" class="text-center py-4">कोई प्रविष्टि उपलब्ध नहीं है।</td>
                </tr>
            <?php else: 
                foreach ($ledger_items as $li):
                    $debit = floatval($li['due_amount']);
                    $credit = floatval($li['payment_amount']);
                    $adj = floatval($li['adjustment_amount']);
                    
                    // compute running balance
                    $running_bal += ($debit + $adj - $credit);
            ?>
                <tr>
                    <td class="py-2 text-center english-text"><?php echo date('d-m-Y', strtotime($li['date'])); ?></td>
                    <td class="fw-semibold text-navy"><?php echo e($li['particulars']); ?></td>
                    <td class="text-end text-navy english-text"><?php echo $debit > 0 ? '₹' . number_format($debit, 2) : '-'; ?></td>
                    <td class="text-end text-success english-text"><?php echo $credit > 0 ? '₹' . number_format($credit, 2) : '-'; ?></td>
                    <td class="text-end text-secondary-custom english-text"><?php echo $adj != 0 ? '₹' . number_format($adj, 2) : '-'; ?></td>
                    <td class="text-end fw-bold text-danger english-text">₹<?php echo number_format($running_bal, 2); ?></td>
                </tr>
            <?php 
                endforeach; 
            endif; 
            ?>
        </tbody>
    </table>

    <!-- Footer Signatures -->
    <div class="row mt-5 pt-4" style="font-size: 0.85rem;">
        <div class="col-6">
            <span class="text-secondary d-block">जारी करने की तिथि (Issued Date):</span>
            <strong class="text-navy english-text"><?php echo date('d-m-Y H:i'); ?></strong>
        </div>
        <div class="col-6 text-end">
            <span class="text-secondary d-block">अधिकृत हस्ताक्षरकर्ता:</span>
            <strong class="text-navy d-block mt-4" style="border-top: 1px dotted #002147; display:inline-block; padding-top:5px; width:150px;">कोषाध्यक्ष / महासचिव</strong>
        </div>
    </div>
</div>

</body>
</html>
