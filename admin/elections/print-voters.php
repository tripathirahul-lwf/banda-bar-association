<?php
/**
 * A4 Printable Voter List Register
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv', 'president']);

$election_id = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;
$signature_col = isset($_GET['signature']) && $_GET['signature'] == 1;

$db = Database::getConnection();
$election = null;
$voters = [];

if ($db && $election_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM elections WHERE id = ?");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        
        if ($election) {
            $v_stmt = $db->prepare("
                SELECT * FROM election_voters 
                WHERE election_id = ? AND eligibility_status = 'eligible'
                ORDER BY CAST(voter_no AS UNSIGNED) ASC, voter_no ASC, id ASC
            ");
            $v_stmt->execute([$election_id]);
            $voters = $v_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load print voters list: " . $e->getMessage());
    }
}

if (!$election) {
    echo '<h3>त्रुटि: चुनाव विवरण नहीं मिला।</h3>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Print Voter List - <?php echo e($election['election_code']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; margin: 0; padding: 0; color: #000; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 4px 6px !important; border: 1px solid #333 !important; }
            .page-break { page-break-after: always; }
        }
        body {
            font-family: 'Times New Roman', 'Sanskrit', sans-serif;
            background-color: #fff;
            color: #000;
        }
        .header-title {
            text-align: center;
            border-bottom: 2px double #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .sign-placeholder {
            min-height: 40px;
            border-bottom: 1px dotted #ccc;
        }
    </style>
</head>
<body onload="window.print();">

<div class="container py-4">
    <!-- Action buttons no-print -->
    <div class="no-print d-flex gap-2 mb-4">
        <button onclick="window.print();" class="btn btn-sm btn-dark"><i class="bi bi-printer"></i> प्रिंट करें</button>
        <a href="view.php?id=<?php echo $election_id; ?>&tab=voters" class="btn btn-sm btn-outline-secondary">वापस जाएं</a>
        <a href="?election_id=<?php echo $election_id; ?>&signature=<?php echo $signature_col ? '0' : '1'; ?>" class="btn btn-sm btn-outline-primary">
            <?php echo $signature_col ? 'हस्ताक्षर कॉलम हटाएं' : 'हस्ताक्षर कॉलम जोड़ें'; ?>
        </a>
    </div>

    <!-- Voter List Sheet -->
    <div class="header-title">
        <h3 class="fw-bold text-uppercase mb-1">जिला अधिवक्ता संघ, बांदा (उत्तर प्रदेश)</h3>
        <h5 class="fw-bold text-muted mb-1"><?php echo e($election['title']); ?> (<?php echo e($election['election_code']); ?>)</h5>
        <h6 class="mb-0"><strong>अंतिम मतदाता सूची (Final Voter List)</strong> | मतदान तिथि: <?php echo date('d-m-Y', strtotime($election['election_date'])); ?></h6>
        <div class="text-end small text-muted mt-2">प्रकाशन तिथि: <?php echo date('d-m-Y'); ?></div>
    </div>

    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 60px; text-align: center;">क्र.सं. (Sr)</th>
                <th style="width: 100px; text-align: center;">मतदाता सं (Voter No)</th>
                <th>अधिवक्ता का नाम (Advocate Name)</th>
                <th style="width: 120px;">सदस्यता सं (DBA Code)</th>
                <th style="width: 150px;">पंजीकरण क्रमांक (COP No)</th>
                <th style="width: 100px;">कक्ष संख्या</th>
                <?php if ($signature_col): ?>
                    <th style="width: 180px; text-align: center;">मतदाता हस्ताक्षर</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($voters)): ?>
                <tr>
                    <td colspan="<?php echo $signature_col ? 7 : 6; ?>" class="text-center py-4 text-muted">मतदाता सूची में कोई भी नाम स्वीकृत नहीं पाया गया।</td>
                </tr>
            <?php else: ?>
                <?php 
                $sr = 1;
                foreach ($voters as $v): 
                ?>
                    <tr>
                        <td class="text-center"><?php echo $sr++; ?></td>
                        <td class="text-center fw-bold"><?php echo e($v['voter_no'] ?: '-'); ?></td>
                        <td class="fw-bold"><?php echo e($v['member_name']); ?></td>
                        <td><?php echo e($v['membership_no']); ?></td>
                        <td><?php echo e($v['enrollment_no'] ?: 'N/A'); ?></td>
                        <td>Chamber <?php echo e($v['chamber_no'] ?: 'N/A'); ?></td>
                        <?php if ($signature_col): ?>
                            <td><div class="sign-placeholder"></div></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-4 text-center small">
        <div class="col-4">
            <p class="mb-5">........................................</p>
            <h6>मुख्य चुनाव अधिकारी (Chief Election Officer)</h6>
        </div>
        <div class="col-4">
            <p class="mb-5">........................................</p>
            <h6>सहायक चुनाव अधिकारी (Asst. Officer)</h6>
        </div>
        <div class="col-4">
            <p class="mb-5">........................................</p>
            <h6>पर्यवेक्षक (Observer)</h6>
        </div>
    </div>
</div>

</body>
</html>
