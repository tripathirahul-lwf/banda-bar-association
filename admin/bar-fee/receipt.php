<?php
/**
 * Advocate Bar Fee Receipt - Printable Page
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce authorized role (Admin, Mahasachiv, or matching Bar Member)
$user = currentUser();
if (!$user) {
    die('अनधिकृत प्रवेश। कृपया पहले लॉगिन करें।');
}

$db = Database::getConnection();
$payment = null;
$member = null;
$allocations = [];

$payment_id = intval($_GET['id'] ?? 0);
$receipt_no = sanitize($_GET['receipt_no'] ?? '');

if ($db) {
    try {
        if ($payment_id > 0) {
            $stmt = $db->prepare("SELECT * FROM bar_fee_payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
        } elseif (!empty($receipt_no)) {
            $stmt = $db->prepare("SELECT * FROM bar_fee_payments WHERE receipt_no = ?");
            $stmt->execute([$receipt_no]);
            $payment = $stmt->fetch();
        }

        if ($payment) {
            // Fetch member details
            $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
            $m_stmt->execute([$payment['member_id']]);
            $member = $m_stmt->fetch();

            // Check authorization: Members can only view their own receipts
            if ($user['role'] === 'member' && intval($user['member_id']) !== intval($payment['member_id'])) {
                die('सुरक्षा उल्लंघन: आप केवल अपनी स्वयं की रसीद देख सकते हैं।');
            }

            // Fetch allocations
            $a_stmt = $db->prepare("
                SELECT a.*, d.period_label, d.financial_year, t.name as fee_name 
                FROM bar_fee_payment_allocations a
                JOIN member_fee_dues d ON d.id = a.due_id
                JOIN bar_fee_types t ON t.id = d.fee_type_id
                WHERE a.payment_id = ?
            ");
            $a_stmt->execute([$payment['id']]);
            $allocations = $a_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed loading payment receipt: " . $e->getMessage());
    }
}

if (!$payment || !$member) {
    die('भुगतान रसीद रिकॉर्ड नहीं मिला।');
}

// Simple Indian numbering conversion helper
function convertNumberToWords($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 55 => 'Fifty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter].$plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . ' Only';
}

$amt_words = convertNumberToWords($payment['amount']);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>रसीद (Receipt) - <?php echo e($payment['receipt_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <style>
        body {
            background: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #222;
        }
        .receipt-container {
            max-width: 800px;
            margin: 30px auto;
            border: 2px solid #002147;
            padding: 30px;
            background: #fff;
            position: relative;
        }
        .receipt-header {
            border-bottom: 2px double #002147;
            padding-bottom: 15px;
            text-align: center;
        }
        .header-title {
            color: #002147;
            font-weight: 800;
            font-size: 1.8rem;
            text-transform: uppercase;
        }
        .header-subtitle {
            color: #b8860b;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 5rem;
            font-weight: bold;
            color: rgba(0, 33, 71, 0.05);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 5px;
            z-index: 1;
        }
        .table-alloc td {
            font-size: 0.85rem;
        }
        @media print {
            body {
                background: none;
                margin: 0;
            }
            .receipt-container {
                border: none;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container no-print text-center mt-4">
    <button onclick="window.print()" class="btn btn-primary px-4 font-hindi"><i class="bi bi-printer me-1"></i>रसीद प्रिंट करें (Print Receipt)</button>
    <a href="javascript:window.close()" class="btn btn-outline-secondary px-3 ms-2">बंद करें</a>
</div>

<div class="receipt-container">
    <div class="watermark">DBA BANDA</div>
    
    <!-- Header -->
    <div class="receipt-header mb-4">
        <h2 class="header-title mb-1">जिला अधिवक्ता संघ, बांदा</h2>
        <h4 class="mb-1 fw-bold text-navy" style="font-size: 1.1rem; color: #002147;">DISTRICT BAR ASSOCIATION, BANDA</h4>
        <p class="header-subtitle mb-0">ESTD. 1937 | REGISTERED UNDER SOCIETIES ACT</p>
        <span class="badge bg-navy text-white px-3 py-1 mt-2 text-uppercase fw-bold" style="background-color: #002147; font-size: 0.8rem; letter-spacing:1px;">Advocate Bar Fee Receipt</span>
    </div>

    <!-- Metadata Grid -->
    <div class="row g-3 mb-4" style="font-size: 0.9rem;">
        <div class="col-6">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-secondary py-1" style="width: 40%;">रसीद संख्या (Receipt No):</td>
                    <td class="fw-bold text-navy py-1"><?php echo e($payment['receipt_no']); ?></td>
                </tr>
                <tr>
                    <td class="text-secondary py-1">भुगतान संख्या (Payment No):</td>
                    <td class="fw-semibold py-1"><?php echo e($payment['payment_no']); ?></td>
                </tr>
                <tr>
                    <td class="text-secondary py-1">दिनांक (Date):</td>
                    <td class="fw-semibold py-1"><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-6 border-start">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-secondary py-1" style="width: 40%;">अधिवक्ता का नाम:</td>
                    <td class="fw-bold text-navy py-1"><?php echo e($member['full_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-secondary py-1">सदस्यता संख्या:</td>
                    <td class="fw-bold py-1"><?php echo e($member['membership_no']); ?></td>
                </tr>
                <tr>
                    <td class="text-secondary py-1">नामांकन संख्या:</td>
                    <td class="fw-semibold py-1"><?php echo e($member['enrollment_no']); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Allocation Details -->
    <h6 class="fw-bold text-navy border-bottom pb-1 mb-3" style="color: #002147;">आबंटित शुल्क विवरण (Allocated Fee Details)</h6>
    <table class="table table-bordered table-alloc mb-4 text-center">
        <thead class="table-light">
            <tr>
                <th class="py-1" style="width: 10%;">क्र. सं.</th>
                <th class="py-1">शुल्क मद विवरण (Particulars)</th>
                <th class="py-1">वित्तीय वर्ष / अवधि</th>
                <th class="py-1 text-end">आबंटित राशि (Allocated Amount)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($allocations as $alloc): 
            ?>
                <tr>
                    <td class="py-1"><?php echo $i++; ?></td>
                    <td class="py-1 fw-bold text-navy text-start"><?php echo e($alloc['fee_name']); ?></td>
                    <td class="py-1"><?php echo e($alloc['period_label'] ?: $alloc['financial_year']); ?></td>
                    <td class="py-1 text-end fw-bold">₹<?php echo number_format($alloc['allocated_amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="table-light">
                <td colspan="3" class="text-end fw-bold py-1">कुल प्राप्त जमा (Total Collected):</td>
                <td class="text-end fw-bold py-1 text-success fs-6">₹<?php echo number_format($payment['amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Amount in Words -->
    <div class="p-3 bg-light rounded mb-4" style="font-size: 0.85rem;">
        <span class="text-secondary d-block fw-semibold mb-1">शब्दों में कुल राशि (Amount in Words):</span>
        <strong class="text-navy-custom"><?php echo $amt_words; ?></strong>
    </div>

    <!-- Bottom Meta Details -->
    <div class="row g-2 mb-4" style="font-size: 0.8rem;">
        <div class="col-6">
            <span class="text-secondary d-block">भुगतान माध्यम (Mode of Payment):</span>
            <strong class="text-navy"><?php echo e($payment['payment_mode']); ?></strong>
            <?php if (!empty($payment['transaction_reference'])): ?>
                <span class="text-secondary d-block mt-1">लेनदेन संदर्भ संख्या (Ref No):</span>
                <span class="fw-semibold"><?php echo e($payment['transaction_reference']); ?></span>
            <?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <span class="text-secondary d-block">टिप्पणी / रिमार्क्स:</span>
            <span><?php echo e($payment['remarks'] ?: '-'); ?></span>
        </div>
    </div>

    <!-- Signatures -->
    <div class="row mt-5 pt-4 align-items-end" style="font-size: 0.85rem;">
        <div class="col-6">
            <span class="text-secondary d-block">रसीद कर्ता हस्ताक्षर (Receipt Collected By):</span>
            <strong class="text-navy d-block mt-4">कार्यालय लिपिक (Office Assistant)</strong>
        </div>
        <div class="col-6 text-end">
            <span class="text-secondary d-block">अधिकृत हस्ताक्षरकर्ता (Authorized Signatory):</span>
            <strong class="text-navy d-block mt-4" style="border-top: 1px dotted #002147; display:inline-block; padding-top:5px; width:150px;">कोषाध्यक्ष / महासचिव</strong>
        </div>
    </div>

    <!-- Notice Footer Info -->
    <div class="text-center mt-5 text-secondary border-top pt-2" style="font-size: 0.75rem;">
        यह जिला अधिवक्ता संघ, बांदा के आधिकारिक Bar Fee खाते की कंप्यूटर जनित सुरक्षित रसीद है।
    </div>
</div>

</body>
</html>
