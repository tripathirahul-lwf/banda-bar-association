<?php
/**
 * Admin Issued ID Cards A4 Print Register
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

$db = Database::getConnection();
$cards = [];

if ($db) {
    try {
        $stmt = $db->query("
            SELECT c.*, m.full_name, m.membership_no, m.enrollment_no 
            FROM id_cards c
            JOIN members m ON m.id = c.member_id
            WHERE c.status IN ('issued', 'ready', 'printed')
            ORDER BY c.id ASC
        ");
        $cards = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load print register: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>पहचान पत्र निर्गम रजिस्टर - जिला अधिवक्ता संघ, बांदा</title>
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
        .signature-col {
            width: 120px;
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
        <p>अधिवक्ता डिजिटल पहचान पत्र निर्गम रजिस्टर (ID Card Issuance Register)</p>
        <span style="font-size: 9px; display: block; margin-top: 5px;">प्रिंट तिथि: <?php echo date('d-m-Y H:i'); ?></span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">क्र.सं.</th>
                <th>अधिवक्ता का नाम</th>
                <th>सदस्यता संख्या</th>
                <th>पंजीकरण संख्या</th>
                <th>पहचान पत्र संख्या</th>
                <th>वैधता अवधि</th>
                <th>स्थिति</th>
                <th class="signature-col">धारक के हस्ताक्षर</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cards)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">कोई कार्ड जारी नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php $i = 1; foreach ($cards as $c): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo e($c['full_name']); ?></strong></td>
                        <td><?php echo e($c['membership_no']); ?></td>
                        <td><?php echo e($c['enrollment_no']); ?></td>
                        <td><strong><?php echo e($c['card_number']); ?></strong></td>
                        <td><?php echo date('d-m-Y', strtotime($c['valid_from'])); ?> से <?php echo date('d-m-Y', strtotime($c['valid_until'])); ?></td>
                        <td><?php echo e(ucfirst($c['status'])); ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
