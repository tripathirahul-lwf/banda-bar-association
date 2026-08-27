<?php
/**
 * Printable Bar Association Member Register (A4 Optimized)
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce authentication
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Replicate search and filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$bearer = trim($_GET['bearer'] ?? '');
$chamber = trim($_GET['chamber'] ?? '');
$visibility = trim($_GET['visibility'] ?? '');
$enroll_year = trim($_GET['enroll_year'] ?? '');

$sql_where = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_where .= " AND (full_name LIKE :search OR membership_no LIKE :search OR enrollment_no LIKE :search OR mobile LIKE :search OR chamber_no LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {
    $sql_where .= " AND membership_status = :status";
    $params[':status'] = $status;
}

if ($category !== '') {
    $sql_where .= " AND membership_category = :category";
    $params[':category'] = $category;
}

if ($gender !== '') {
    $sql_where .= " AND gender = :gender";
    $params[':gender'] = $gender;
}

if ($bearer !== '') {
    $sql_where .= " AND is_office_bearer = :bearer";
    $params[':bearer'] = ($bearer === 'yes') ? 1 : 0;
}

if ($chamber !== '') {
    if ($chamber === 'assigned') {
        $sql_where .= " AND chamber_no IS NOT NULL AND chamber_no != '' AND chamber_no != 'N/A'";
    } else {
        $sql_where .= " AND (chamber_no IS NULL OR chamber_no = '' OR chamber_no = 'N/A')";
    }
}

if ($visibility !== '') {
    $sql_where .= " AND is_public = :visibility";
    $params[':visibility'] = ($visibility === 'public') ? 1 : 0;
}

if ($enroll_year !== '' && is_numeric($enroll_year)) {
    $sql_where .= " AND YEAR(enrollment_date) = :enroll_year";
    $params[':enroll_year'] = $enroll_year;
}

$members = [];
if ($db) {
    try {
        $sql_query = "SELECT * FROM members" . $sql_where . " ORDER BY membership_no ASC";
        $stmt = $db->prepare($sql_query);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch print register: " . $e->getMessage());
        die("System Database Error.");
    }
}

$status_labels = [
    'active' => 'सक्रिय',
    'inactive' => 'निष्क्रिय',
    'suspended' => 'निलंबित',
    'expired' => 'समाप्त',
    'deceased' => 'दिवंगत',
    'pending' => 'लंबित',
    'rejected' => 'अस्वीकृत'
];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>सदस्य रजिस्टर (Member Register) - जिला अधिवक्ता संघ, बांदा</title>
    <!-- Include Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #000000;
        }
        .print-header {
            border-bottom: 3px double #102A43;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header-title {
            color: #102A43;
            font-weight: 700;
        }
        table {
            font-size: 0.8rem;
        }
        th {
            background-color: #f5f7fb !important;
            color: #102A43 !important;
            font-weight: 600;
            border-color: #000000 !important;
        }
        td {
            border-color: #dddddd !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 10mm;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="container-fluid mt-3">
        
        <!-- Controls row -->
        <div class="row no-print mb-4 p-2 bg-light border rounded">
            <div class="col-6">
                <span class="text-navy-custom fw-semibold small">प्रिंट प्रीव्यू लोड हो चुका है।</span>
            </div>
            <div class="col-6 text-end">
                <button class="btn btn-sm btn-secondary me-2" onclick="window.close();">विंडो बंद करें</button>
                <button class="btn btn-sm btn-primary" onclick="window.print();">प्रिंटर भेजें</button>
            </div>
        </div>

        <!-- Document Header -->
        <div class="print-header text-center">
            <h3 class="header-title mb-1 font-hindi">जिला अधिवक्ता संघ, बांदा (उत्तर प्रदेश)</h3>
            <h5 class="text-secondary mb-1">DISTRICT BAR ASSOCIATION, BANDA (U.P.)</h5>
            <span class="small text-muted d-block font-hindi">स्थापित: १९३७ | सम्बद्ध: बार काउंसिल ऑफ उत्तर प्रदेश</span>
            <hr class="my-2 border-dark" style="opacity: 0.5;">
            
            <div class="d-flex justify-content-between align-items-center mt-2 px-2 small">
                <span><strong>दस्तावेज:</strong> सदस्य रजिस्टर (Advocate Register)</span>
                <span><strong>दिनांक:</strong> <?php echo date('d-m-Y H:i'); ?></span>
                <span><strong>कुल रिकॉर्ड:</strong> <?php echo count($members); ?></span>
            </div>
        </div>

        <!-- Members Table -->
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr class="text-center">
                    <th style="width: 5%;">क्र०</th>
                    <th style="width: 25%;" class="text-start">नाम (Advocate Name)</th>
                    <th style="width: 12%;">सदस्यता क्र०</th>
                    <th style="width: 15%;">नामांकन संख्या (UP Bar)</th>
                    <th style="width: 12%;">मोबाइल</th>
                    <th style="width: 12%;">सदस्यता तिथि</th>
                    <th style="width: 10%;">चैंबर नंबर</th>
                    <th style="width: 9%;">स्थिति</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">कोई सदस्य रिकॉर्ड नहीं मिला।</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $counter = 1;
                    foreach ($members as $m): 
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $counter++; ?></td>
                            <td class="fw-bold"><?php echo e($m['full_name']); ?></td>
                            <td class="text-center"><?php echo e($m['membership_no']); ?></td>
                            <td class="text-center"><?php echo e($m['enrollment_no']); ?></td>
                            <td class="text-center"><?php echo e($m['mobile'] ?? 'N/A'); ?></td>
                            <td class="text-center"><?php echo date('d-m-Y', strtotime($m['member_since'])); ?></td>
                            <td class="text-center"><?php echo e($m['chamber_no'] ?? 'N/A'); ?></td>
                            <td class="text-center small">
                                <?php echo e($status_labels[$m['membership_status']] ?? $m['membership_status']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Printable signature block -->
        <div class="row mt-5 pt-4 text-center">
            <div class="col-4">
                <br><br>
                <span class="d-block border-top border-dark mx-auto" style="width: 150px;"></span>
                <small class="font-hindi text-muted">तैयारकर्ता लिपिक</small>
            </div>
            <div class="col-4">
                <br><br>
                <span class="d-block border-top border-dark mx-auto" style="width: 150px;"></span>
                <small class="font-hindi text-muted">महासचिव (DBA Banda)</small>
            </div>
            <div class="col-4">
                <br><br>
                <span class="d-block border-top border-dark mx-auto" style="width: 150px;"></span>
                <small class="font-hindi text-muted">अध्यक्ष (DBA Banda)</small>
            </div>
        </div>

    </div>

</body>
</html>
