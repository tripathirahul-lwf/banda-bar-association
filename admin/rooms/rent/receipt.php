<?php
/**
 * Room / Chamber Rent Receipt Print Layout (A4 format)
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Enforce authentication (Requirement 38)
requireLogin();

// Authorization check: Admin, President, Mahasachiv OR Allotted Member owner
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = Database::getConnection();
$payment = null;
$allocations = [];
$member = null;
$chamber = null;

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, u.username AS collector_name 
            FROM chamber_rent_payments p
            LEFT JOIN users u ON u.id = p.collected_by
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            // Verify ownership if role is member (Requirement 10 & 38)
            if ($_SESSION['role'] === 'member' && intval($_SESSION['member_id']) !== intval($payment['member_id'])) {
                http_response_code(403);
                exit("Unauthorized access to this receipt.");
            }

            // Fetch member details
            $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
            $m_stmt->execute([$payment['member_id']]);
            $member = $m_stmt->fetch();

            // Fetch chamber details
            $c_stmt = $db->prepare("
                SELECT c.*, a.allotment_no 
                FROM chambers c
                JOIN chamber_allotments a ON a.chamber_id = c.id
                WHERE a.id = ?
            ");
            $c_stmt->execute([$payment['allotment_id']]);
            $chamber = $c_stmt->fetch();

            // Fetch allocations (rent months paid by this receipt)
            $a_stmt = $db->prepare("
                SELECT a.*, d.rent_month 
                FROM chamber_rent_payment_allocations a
                JOIN chamber_rent_dues d ON d.id = a.rent_due_id
                WHERE a.payment_id = ?
            ");
            $a_stmt->execute([$id]);
            $allocations = $a_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load rent receipt: " . $e->getMessage());
    }
}

if (!$payment || !$member) {
    echo '<div style="padding:50px; text-align:center; font-family:sans-serif;"><h3>त्रुटि: भुगतान रसीद उपलब्ध नहीं है।</h3></div>';
    exit;
}

// Helper to convert number to words
function amountToWords($amount) {
    $number = floatval($amount);
    $no = floor($number);
    $point = round(($number - $no) * 100);
    $hundred = null;
    $digits_1 = strlen($no);
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
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_1 ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($point > 0) ? "And " . ($words[$point - ($point % 10)] . " " . $words[$point % 10]) . " Paise" : '';
    return ($Rupees ? $Rupees . "Rupees " : "") . $paise . " Only";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chamber Rent Receipt - <?php echo e($payment['receipt_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; }
        .receipt-card { background: #fff; border: 2px solid #1a252f; padding: 25px; max-width: 800px; margin: 30px auto; box-shadow: 0 0 10px rgba(0,0,0,0.15); position: relative; }
        .logo-box { border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 20px; }
        .header-title { color: #1a252f; font-weight: 800; font-size: 20px; }
        .receipt-title { border: 1px solid #1a252f; background: #1a252f; color: #fff; padding: 4px 15px; font-weight: bold; border-radius: 4px; display: inline-block; font-size: 14px; }
        .table-summary th, .table-summary td { padding: 6px 10px; border: 1px solid #dee2e6; }
        .amount-highlight { font-size: 18px; color: #1a252f; border: 2px dashed #1a252f; padding: 5px 15px; font-weight: 800; background: #fffdf0; display: inline-block; }
        .footer-sign { margin-top: 50px; text-align: right; }
        .sign-line { border-bottom: 1px solid #333; width: 180px; display: inline-block; }
        @media print {
            body { background: #fff; }
            .receipt-card { border: none; box-shadow: none; padding: 0; margin: 0; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container text-center my-3 no-print">
    <button onclick="window.print();" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> प्रिंट रसीद (Print A4)</button>
    <a href="javascript:window.close();" class="btn btn-secondary btn-sm ms-2">खिड़की बंद करें</a>
</div>

<div class="receipt-card">
    <!-- Watermark / Header DBA -->
    <div class="logo-box text-center">
        <h4 class="mb-0 fw-bold header-title text-uppercase">जिला अधिवक्ता संघ, बांदा (उत्तर प्रदेश)</h4>
        <h5 class="mb-1 text-uppercase fw-bold text-secondary" style="font-size:14px; letter-spacing:0.5px;">District Bar Association, Banda (U.P.)</h5>
        <span class="small text-muted d-block">Established: 1937 | Registration No: 182</span>
        <div class="my-2">
            <span class="receipt-title text-uppercase">Chamber / Room Rent Receipt</span>
        </div>
    </div>

    <!-- Payment details metadata -->
    <div class="row g-2 mb-4">
        <div class="col-6">
            <strong>Receipt No (रसीद संख्या):</strong> <span class="text-navy-custom fw-bold"><?php echo e($payment['receipt_no']); ?></span><br>
            <strong>Payment No (भुगतान संख्या):</strong> <span class="text-muted"><?php echo e($payment['payment_no']); ?></span><br>
            <strong>Date of Payment (भुगतान तिथि):</strong> <?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?>
        </div>
        <div class="col-6 text-end">
            <strong>Member Name:</strong> <?php echo e($member['full_name']); ?><br>
            <strong>Membership No (DBA Code):</strong> <?php echo e($member['membership_no']); ?><br>
            <strong>Enrollment No (COP):</strong> <?php echo e($member['enrollment_no']); ?>
        </div>
    </div>

    <!-- Chamber info metadata -->
    <div class="p-2 border rounded mb-4 bg-light">
        <div class="row text-center text-navy-custom">
            <div class="col-4">
                <span class="text-secondary small d-block">Chamber No.</span>
                <strong class="fs-6"><?php echo e($chamber['chamber_no'] ?? '-'); ?></strong>
            </div>
            <div class="col-4 border-start">
                <span class="text-secondary small d-block">Allotment Code</span>
                <strong class="fs-6"><?php echo e($chamber['allotment_no'] ?? '-'); ?></strong>
            </div>
            <div class="col-4 border-start">
                <span class="text-secondary small d-block">Block / Floor Location</span>
                <strong class="fs-6"><?php echo e($chamber['block_name'] ?? '-'); ?> / <?php echo e($chamber['floor'] ?? '-'); ?></strong>
            </div>
        </div>
    </div>

    <!-- Particulars table -->
    <table class="table table-bordered table-summary mb-4 align-middle">
        <thead class="table-light">
            <tr>
                <th>क्र.सं. (S.N.)</th>
                <th>विवरण (Particulars)</th>
                <th>अवधि (Period Paid)</th>
                <th class="text-end">प्राप्त राशि (Paid Amount)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($allocations as $al): 
                $month_label = date('F Y', strtotime($al['rent_month'] . '-01'));
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>कक्ष मासिक किराया भुगतान (Chamber License Rent)</td>
                    <td class="text-navy-custom fw-semibold"><?php echo $month_label; ?></td>
                    <td class="text-end fw-bold">₹<?php echo number_format($al['allocated_amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="table-light">
                <td colspan="3" class="text-end fw-bold text-navy-custom">कुल जमा राशि (Total Receipt Amount)</td>
                <td class="text-end fw-bold text-success fs-6">₹<?php echo number_format($payment['amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Amount in words -->
    <div class="mb-4">
        <strong>Amount in words (शब्दों में):</strong> 
        <span class="fst-italic text-navy-custom fw-bold"><?php echo amountToWords($payment['amount']); ?></span>
    </div>

    <!-- Mode and description -->
    <div class="row g-2 mb-4">
        <div class="col-8">
            <strong>Payment Mode (माध्यम):</strong> <?php echo e($payment['payment_mode']); ?> 
            <?php if (!empty($payment['reference_no'])): ?>
                (Ref: <?php echo e($payment['reference_no']); ?>)
            <?php endif; ?><br>
            <strong>Status (स्थिति):</strong> <span class="badge bg-success">Confirmed (सत्यापित)</span>
            <?php if (!empty($payment['remarks'])): ?>
                <br><strong>Remarks:</strong> <span class="text-muted"><?php echo e($payment['remarks']); ?></span>
            <?php endif; ?>
        </div>
        <div class="col-4 text-end">
            <span class="amount-highlight">₹<?php echo number_format($payment['amount'], 2); ?></span>
        </div>
    </div>

    <!-- Receipt Disclaimer -->
    <p class="text-muted mt-4 border-top pt-2" style="font-size:10px; line-height:1.3;">
        * यह रसीद केवल कक्ष किराया (Chamber License Rent) की प्राप्ति हेतु निर्गत की गई है। इसे संघ सदस्यता (Bar Membership Fee) या कल्याणकारी निधि (Welfare Fund) के साक्ष्य के रूप में स्वीकार नहीं किया जाएगा। त्रुटिपूर्ण प्रविष्टियों की सूचना तत्काल कार्यालय में दें।
    </p>

    <!-- Signatures -->
    <div class="row mt-5">
        <div class="col-6">
            <span class="small text-muted">प्रविष्टिकर्ता: <?php echo e($payment['collector_name'] ?: 'Office Clerk'); ?></span>
        </div>
        <div class="col-6 text-end">
            <div class="footer-sign">
                <span class="sign-line mb-1"></span>
                <span class="small d-block text-navy-custom fw-bold">अधिकृत हस्ताक्षरकर्ता (Authorized Signatory)</span>
                <span class="small text-muted d-block" style="font-size:10px;">जिला अधिवक्ता संघ, बांदा</span>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto trigger print menu if print param is set
    <?php if (isset($_GET['print'])): ?>
        window.print();
    <?php endif; ?>
</script>
</body>
</html>
