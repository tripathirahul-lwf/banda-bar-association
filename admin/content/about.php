<?php
/**
 * Admin Content Management - About Us Section
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'परिचय एवं इतिहास प्रबंधन (Content Manager)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();
$error = '';
$success = '';

$keys = ['about_association', 'history', 'mission'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        try {
            $db->beginTransaction();

            $old_values = [];
            $new_values = [];

            $stmt_get = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt_up = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");

            foreach ($keys as $key) {
                // Fetch old value
                $stmt_get->execute([$key]);
                $old_values[$key] = $stmt_get->fetchColumn();

                // Build new value (sanitized content)
                $new_val = sanitize(trim($_POST[$key] ?? ''));

                $new_values[$key] = $new_val;
                $stmt_up->execute([$new_val, $_SESSION['user_id'], $key]);
            }

            // Log Audit
            logAudit('content', 'Updated About Us Content', 'system_settings', null, 'Association profile information, history, and mission content updated.', $old_values, $new_values);

            $db->commit();
            $success = 'परिचय एवं इतिहास जानकारी सफलतापूर्वक सहेज ली गई है।';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'सामग्री सहेजने में विफल: ' . $e->getMessage();
        }
    }
}

// Fetch settings again to display
$config = [];
foreach ($keys as $k) {
    $config[$k] = getSetting($k, '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">परिचय एवं इतिहास प्रबंधन (About Content Manager)</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold">संघ परिचय विवरण प्रपत्र</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php insertCSRF(); ?>

            <div class="mb-3">
                <label class="form-label fw-bold">संघ का विस्तृत परिचय (About Association) *</label>
                <textarea name="about_association" class="form-control form-control-sm" required rows="6" placeholder="उदा. जिला अधिवक्ता संघ बांदा उत्तर प्रदेश का..."><?php echo e($config['about_association']); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">स्थापना एवं इतिहास (History of DBA) *</label>
                <textarea name="history" class="form-control form-control-sm" required rows="6" placeholder="उदा. संघ की स्थापना वर्ष 1937 में हुई थी..."><?php echo e($config['history']); ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">लक्ष्य एवं विजन (Mission Statement) *</label>
                <textarea name="mission" class="form-control form-control-sm" required rows="4" placeholder="उदा. हमारा उद्देश्य अधिवक्ता हितों का संरक्षण..."><?php echo e($config['mission']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-gold btn-sm text-navy-custom fw-bold"><i class="bi bi-check-circle-fill me-1"></i>विवरण सहेजें</button>
        </form>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
