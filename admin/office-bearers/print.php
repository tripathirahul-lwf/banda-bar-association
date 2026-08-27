<?php
/**
 * A4 Printable Executive Committee Register
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce permission
requireRole(['admin', 'mahasachiv', 'president']);

$term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
$db = Database::getConnection();

$term = null;
$bearers = [];

if ($db && $term_id > 0) {
    try {
        $t_stmt = $db->prepare("SELECT * FROM office_bearer_terms WHERE id = ?");
        $t_stmt->execute([$term_id]);
        $term = $t_stmt->fetch();

        if ($term) {
            $b_stmt = $db->prepare("
                SELECT ob.*, p.position_name, p.position_name_hindi 
                FROM office_bearers ob
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = ? AND ob.status = 'active'
                ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC
            ");
            $b_stmt->execute([$term_id]);
            $bearers = $b_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed loading print bearers: " . $e->getMessage());
    }
}

if (!$term) {
    echo '<h3>त्रुटि: कार्यकारिणी विवरण नहीं मिला।</h3>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Executive Committee Print - <?php echo e($term['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; margin: 0; padding: 0; color: #000; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 6px 8px !important; border: 1px solid #333 !important; }
        }
        body {
            font-family: 'Times New Roman', 'Sanskrit', sans-serif;
            background-color: #fff;
            color: #000;
        }
        .header-title {
            text-align: center;
            border-bottom: 2px double #000;
            padding-bottom: 12px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body onload="window.print();">

<div class="container py-4">
    <!-- Action buttons no-print -->
    <div class="no-print d-flex gap-2 mb-4">
        <button onclick="window.print();" class="btn btn-sm btn-dark">प्रिंट करें</button>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">वापस जाएं</a>
    </div>

    <!-- Official Header -->
    <div class="header-title">
        <h3 class="fw-bold text-uppercase mb-1">जिला अधिवक्ता संघ, बांदा (उत्तर प्रदेश)</h3>
        <h5 class="fw-bold text-muted mb-1"><?php echo e($term['title']); ?></h5>
        <h6 class="mb-0"><strong>कार्यकारिणी सदस्य सूची (Executive Committee Bearers Register)</strong></h6>
        <div class="text-end small text-muted mt-2">प्रकाशन तिथि: <?php echo date('d-m-Y'); ?></div>
    </div>

    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 60px; text-align: center;">क्र.सं.</th>
                <th>पदभार (Position Designation)</th>
                <th>अधिवक्ता का नाम (Advocate Name)</th>
                <th style="width: 150px; text-align: center;">सदस्यता कोड (DBA Code)</th>
                <th style="width: 180px; text-align: center;">पंजीकरण संख्या (COP No)</th>
                <th style="width: 120px; text-align: center;">कार्यकाल प्रारंभ तिथि</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bearers)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">इस कार्यकाल में कोई सक्रिय पदाधिकारी नियुक्त नहीं है।</td>
                </tr>
            <?php else: ?>
                <?php 
                $sr = 1;
                foreach ($bearers as $b): 
                ?>
                    <tr>
                        <td class="text-center"><?php echo $sr++; ?></td>
                        <td>
                            <strong><?php echo e($b['position_name_hindi']); ?></strong><br>
                            <small class="text-muted text-uppercase"><?php echo e($b['position_name']); ?></small>
                        </td>
                        <td class="fw-bold"><?php echo e($b['display_name_snapshot']); ?></td>
                        <td class="text-center"><?php echo e($b['membership_no_snapshot'] ?: '-'); ?></td>
                        <td class="text-center"><?php echo e($b['enrollment_no_snapshot'] ?: '-'); ?></td>
                        <td class="text-center"><?php echo $b['start_date'] ? date('d-m-Y', strtotime($b['start_date'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-5 text-center small">
        <div class="col-4">
            <p class="mb-5">........................................</p>
            <h6>महासचिव (General Secretary)</h6>
        </div>
        <div class="col-4">
            <p class="mb-5">........................................</p>
            <h6>अध्यक्ष (President)</h6>
        </div>
        <div class="col-4">
            <p class="mb-5">........................................</p>
            <h6>प्रशासनिक अधिकारी (Admin Officer)</h6>
        </div>
    </div>
</div>

</body>
</html>
