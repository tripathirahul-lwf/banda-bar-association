<?php
/**
 * Printable Income Receipt
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$tx_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tx = null;

$db = Database::getConnection();

if ($db && $tx_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT t.*, c.name as category_name, u.username as creator_name
            FROM association_fund_transactions t
            LEFT JOIN association_fund_categories c ON c.id = t.category_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.id = ? AND t.transaction_type = 'income' AND t.status = 'approved'
        ");
        $stmt->execute([$tx_id]);
        $tx = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Receipt printable error: " . $e->getMessage());
    }
}

if (!$tx) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: रसीद विवरण नहीं मिला या लेन-देन स्वीकृत नहीं है।</div>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Receipt - District Bar Association Banda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Georgia', serif;
            font-size: 13px;
        }
        .receipt-container {
            border: 2px solid #1a252f;
            padding: 30px;
            max-width: 700px;
            margin: 40px auto;
            background-color: #fff;
        }
        .header-box {
            border-bottom: 2px solid #1a252f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .receipt-title {
            background-color: #1a252f;
            color: #fff;
            padding: 4px 15px;
            font-weight: bold;
            display: inline-block;
            text-transform: uppercase;
        }
        @media print {
            .no-print {
                display: none;
            }
            .receipt-container {
                margin: 0 auto;
                border: 2px solid #000;
            }
        }
    </style>
</head>
<body class="bg-light">

    <div class="container no-print text-center my-3">
        <button onclick="window.print()" class="btn btn-dark px-4 btn-sm me-2">प्रिंट करें (Print)</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">बंद करें</button>
    </div>

    <div class="receipt-container shadow-sm">
        <div class="header-box text-center">
            <h3 class="fw-bold mb-0 text-navy-custom" style="color: #0b1d33;">जिला अधिवक्ता संघ, बांदा</h3>
            <h6 class="text-uppercase small text-secondary fw-bold mb-2">District Bar Association, Banda (U.P.)</h6>
            <span class="receipt-title font-hindi mt-1">कोष प्राप्ति रसीद (FUND RECEIPT)</span>
        </div>

        <div class="row g-3 font-hindi">
            <div class="col-6">
                <span>रसीद संख्या (Receipt No): </span>
                <strong class="english-text"><?php echo e($tx['receipt_no'] ?: 'REC-'.$tx['id']); ?></strong>
            </div>
            <div class="col-6 text-end">
                <span>दिनांक (Date): </span>
                <strong class="english-text"><?php echo date('d-m-Y', strtotime($tx['transaction_date'])); ?></strong>
            </div>

            <div class="col-12 mt-4 border-bottom pb-2">
                <span>प्राप्तकर्ता / आदाता का नाम (Received From): </span>
                <strong class="fs-6 ms-2 text-navy-custom"><?php echo e($tx['party_name'] ?: 'N/A'); ?></strong>
            </div>

            <div class="col-6">
                <span>प्राप्ति माध्यम (Payment Mode): </span>
                <strong><?php echo e($tx['payment_mode']); ?></strong>
            </div>
            <div class="col-6 text-end">
                <span>संदर्भ संख्या (Ref / UTR No): </span>
                <strong class="english-text"><?php echo e($tx['reference_no'] ?: '-'); ?></strong>
            </div>

            <div class="col-12 border-bottom pb-2">
                <span>प्राप्ति विवरण / उद्देश्य (Purpose / Category): </span>
                <strong class="ms-2"><?php echo e($tx['category_name']); ?> - <?php echo e($tx['description']); ?></strong>
            </div>

            <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                <div class="p-3 border border-dark rounded bg-light" style="width: 250px;">
                    <span class="d-block font-size-xs text-muted text-uppercase mb-1">राशि (Amount Received)</span>
                    <h4 class="fw-bold mb-0 text-success">₹<?php echo number_format($tx['amount'], 2); ?></h4>
                </div>
                
                <div class="text-center" style="width: 200px; margin-top: 40px;">
                    <span class="d-block border-top pt-2" style="border-color:#333 !important;">अधिकृत हस्ताक्षरकर्ता</span>
                    <small class="text-muted">(Authorized Signatory)</small>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
