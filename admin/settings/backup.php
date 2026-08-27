<?php
/**
 * Admin Database Backup & Recovery Panel
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $_SESSION['flash_error'] = 'सुरक्षा सत्यापन विफल रहा।';
        header("Location: backup.php");
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'download_sql') {
        try {
            // Check password confirmation
            $password = $_POST['confirm_password'] ?? '';
            
            // Fetch current user password hash to verify
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($password, $hash)) {
                throw new Exception('अमान्य सुरक्षा पासवर्ड। सत्यापन विफल रहा।');
            }

            // Generate SQL Backup
            $tables = [];
            $result = $db->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sql = "-- District Bar Association, Banda\n";
            $sql .= "-- Database Backup generated at: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($tables as $table) {
                // Get table structure
                $res = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $res[1] . ";\n\n";

                // Get table data
                $res_data = $db->query("SELECT * FROM `$table`");
                while ($row = $res_data->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                    $vals = array_map(function($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote($v);
                    }, array_values($row));

                    $sql .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n";
                }
                $sql .= "\n";
            }

            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            // Log Audit trail (immutable)
            logAudit('settings', 'Exported DB Backup', null, null, 'Manual SQL database backup exported by Admin.');

            // Download file headers
            $filename = "dba_banda_backup_" . date('Ymd_His') . ".sql";
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($sql));
            echo $sql;
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'बैकअप विफलता: ' . $e->getMessage();
            header("Location: backup.php");
            exit;
        }
    }
}

$pageTitle = 'डेटाबेस बैकअप एवं रिकवरी (Backup & Restore)';
require_once __DIR__ . '/../../includes/dashboard/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-database-fill-down text-gold-custom me-2"></i>डेटाबेस बैकअप प्रबंधन (Backup Manager)</h4>
    <a href="general.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>वेबसाइट सेटिंग्स</a>
</div>

<?php if (hasFlash('danger')): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo getFlash('danger'); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger py-2 font-hindi small">
        <?php 
        echo $_SESSION['flash_error']; 
        unset($_SESSION['flash_error']);
        ?>
    </div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Manual Download SQL Backup -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">मैनुअल बैकअप (Manual SQL Dump Export)</h6>
            </div>
            <div class="card-body">
                <p class="text-muted leading-relaxed mb-3">यह प्रक्रिया आपके पूरे डेटाबेस स्कीमा और डेटा का संपूर्ण SQL बैकअप तैयार करेगी। इसे भविष्य में किसी भी आपदा पुनर्प्राप्ति (disaster recovery) के लिए सुरक्षित रखा जा सकता है।</p>
                
                <form method="POST" action="">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="download_sql">

                    <div class="mb-3">
                        <label class="form-label fw-bold">सुरक्षा सत्यापन पासवर्ड (Verify Password) *</label>
                        <input type="password" name="confirm_password" class="form-control form-control-sm" required placeholder="अपना एडमिन लॉगिन पासवर्ड दर्ज करें...">
                        <small class="text-muted">सुरक्षा कारणों से, बैकअप फ़ाइल डाउनलोड करने से पहले अपना वर्तमान पासवर्ड दर्ज करना आवश्यक है।</small>
                    </div>

                    <button type="submit" class="btn btn-navy btn-sm"><i class="bi bi-download me-1"></i>बैकअप (.SQL) डाउनलोड करें</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Backup Details sidebar -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold text-navy-custom mb-3"><i class="bi bi-shield-lock-fill text-gold-dark me-1"></i>सुरक्षा दिशानिर्देश</h6>
                <ul class="text-muted ps-3 mb-0" style="font-size: 0.72rem; line-height: 1.6;">
                    <li>**गोपनीयता नियम:** बैकअप फाइलों में पासवर्ड हैश और व्यक्तिगत डेटा होता है। इन्हें कभी भी सार्वजनिक रूट या असुरक्षित स्थान पर न रखें।</li>
                    <li>**अपरिवर्तनीयता:** प्रत्येक बैकअप निर्यात को केंद्रीय ऑडिट लॉग में आईपी पते और समय के साथ स्थायी रूप से रिकॉर्ड किया जाता है।</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
