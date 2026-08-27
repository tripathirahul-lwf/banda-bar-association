<?php
/**
 * Admin Wakalatnama Download Reports A4 Print Layout
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Auth Check
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'mahasachiv'])) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$db = Database::getConnection();
$records = [];

if ($db) {
    try {
        $conditions = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ? OR w.title LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($category_filter)) {
            $conditions[] = "w.category = ?";
            $params[] = $category_filter;
        }

        if (!empty($start_date)) {
            $conditions[] = "DATE(d.downloaded_at) >= ?";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $conditions[] = "DATE(d.downloaded_at) <= ?";
            $params[] = $end_date;
        }

        $where = implode(" AND ", $conditions);

        $stmt = $db->prepare("
            SELECT d.*, m.full_name, m.membership_no, m.enrollment_no, w.title, w.category
            FROM wakalatnama_downloads d
            JOIN members m ON m.id = d.member_id
            JOIN wakalatnamas w ON w.id = d.wakalatnama_id
            WHERE $where
            ORDER BY d.id ASC
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading records for printing: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>वकालतनामा डाउनलोड रिपोर्ट - जिला अधिवक्ता संघ, बांदा</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #000000;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 11px;
            font-weight: bold;
        }
        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 9px;
            font-style: italic;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000000;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print();" style="padding: 5px 15px; font-weight: bold; cursor: pointer;">प्रिंट (Print)</button>
    </div>

    <div class="header">
        <h2>जिला अधिवक्ता संघ, बांदा (स्थापित 1937)</h2>
        <p>वकालतनामा डाउनलोड रिपोर्ट (Wakalatnama Download Report)</p>
    </div>

    <div class="meta-info">
        <div>
            <span>श्रेणी: <?php echo !empty($category_filter) ? $category_filter : 'सभी'; ?></span>
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                <span style="margin-left: 15px;">अवधि: <?php echo $start_date ?: 'शुरू से'; ?> से <?php echo $end_date ?: 'आज तक'; ?></span>
            <?php endif; ?>
        </div>
        <div>प्रिंट तिथि: <?php echo date('d-m-Y H:i'); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">क्र.सं.</th>
                <th>अधिवक्ता का नाम</th>
                <th>सदस्यता संख्या</th>
                <th>पंजीकरण संख्या</th>
                <th>वकालतनामा फ़ाइल</th>
                <th>श्रेणी</th>
                <th>डाउनलोड संस्करण</th>
                <th>डाउनलोड तिथि व समय</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">कोई डाउनलोड रिकॉर्ड नहीं मिले।</td>
                </tr>
            <?php else: ?>
                <?php $i = 1; foreach ($records as $r): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo e($r['full_name']); ?></strong></td>
                        <td><?php echo e($r['membership_no']); ?></td>
                        <td><?php echo e($r['enrollment_no']); ?></td>
                        <td><?php echo e($r['title']); ?></td>
                        <td><?php echo e($r['category']); ?></td>
                        <td>v<?php echo e($r['version']); ?></td>
                        <td><?php echo date('d-m-Y H:i', strtotime($r['downloaded_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
