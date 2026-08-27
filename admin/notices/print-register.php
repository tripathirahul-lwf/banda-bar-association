<?php
/**
 * Printable Notice Register Report
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce staff roles
if (!isset($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'mahasachiv'])) {
    http_response_code(403);
    exit("Forbidden.");
}

$db = Database::getConnection();
$records = [];

if ($db) {
    try {
        $stmt = $db->query("
            SELECT n.*, u.username as creator_name, app.username as approver_name
            FROM notices n
            LEFT JOIN users u ON u.id = n.created_by
            LEFT JOIN users app ON app.id = n.approved_by
            ORDER BY n.id DESC
        ");
        $records = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed generating printable notices register: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Notice Register | District Bar Association, Banda</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            color: #000000;
            background: #ffffff;
            margin: 30px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000000;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 13px;
            margin: 0;
            color: #444;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 10px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table, th, td {
            border: 1px solid #000000;
        }
        th, td {
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .no-print-btn {
            background-color: #002244;
            color: #ffffff;
            border: none;
            padding: 8px 15px;
            font-size: 11px;
            cursor: pointer;
            border-radius: 3px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        @media print {
            .no-print-btn {
                display: none !important;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <button onclick="window.print();" class="no-print-btn">प्रिंट करें (Print)</button>

    <div class="header">
        <h1>जिला अधिवक्ता संघ, बांदा (जिला न्यायालय परिसर)</h1>
        <h2>अधिसूचना रजिस्टर (Notice Register) - स्थापित: 1937</h2>
        <p>उत्पन्न तिथि: <?php echo date('d-m-Y H:i'); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">क्र. सं.</th>
                <th style="width: 15%;">सूचना संख्या</th>
                <th style="width: 15%;">प्रकाशन दिनांक</th>
                <th style="width: 30%;">अधिसूचना शीर्षक (Title)</th>
                <th style="width: 15%;">श्रेणी</th>
                <th style="width: 10%;">स्थिति</th>
                <th style="width: 10%;">अनुमोदक</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr>
                    <td colspan="7" class="text-center">कोई रिकॉर्ड नहीं मिला।</td>
                </tr>
            <?php else: ?>
                <?php $i = 1; foreach ($records as $row): ?>
                    <tr>
                        <td class="text-center"><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['notice_no'] ?: 'Auto-Generated'); ?></td>
                        <td><?php echo $row['publish_at'] ? date('d-m-Y H:i', strtotime($row['publish_at'])) : 'Immediate'; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                            <?php if ($row['title_hindi']): ?>
                                <br><small><?php echo htmlspecialchars($row['title_hindi']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td><?php echo htmlspecialchars($row['approver_name'] ?: 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
