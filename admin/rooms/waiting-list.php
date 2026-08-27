<?php
/**
 * Room Allotment Waiting List Priorities Manager
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष आवंटन प्रतीक्षा सूची (Waiting List Manager)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Handle priority update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_priority'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $app_id = intval($_POST['app_id'] ?? 0);
        $new_priority = intval($_POST['priority_no'] ?? 0);
        
        if ($app_id > 0 && $new_priority >= 0) {
            try {
                $db->beginTransaction();
                
                // Fetch application info
                $stmt = $db->prepare("SELECT application_no, priority_no FROM chamber_applications WHERE id = ?");
                $stmt->execute([$app_id]);
                $app = $stmt->fetch();
                
                if ($app) {
                    $old_priority = $app['priority_no'];
                    
                    // Update
                    $up = $db->prepare("UPDATE chamber_applications SET priority_no = ? WHERE id = ?");
                    $up->execute([$new_priority, $app_id]);
                    
                    // Audit Log
                    $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, old_value, new_value, remarks, performed_by) VALUES ('Priority Changed', ?, ?, ?, ?)");
                    $audit->execute([$old_priority, $new_priority, "आवेदन सं. {$app['application_no']} की प्रतीक्षा प्राथमिकता बदली गई।", $_SESSION['user_id']]);
                    
                    $db->commit();
                    $success = 'वरीयता प्राथमिकता संख्या सफलतापूर्वक अपडेट की गई।';
                } else {
                    throw new Exception('आवेदन रिकॉर्ड नहीं मिला।');
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'अपडेट करने में असमर्थ: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current waiting list applications
$waiting_list = [];
if ($db) {
    try {
        $waiting_list = $db->query("
            SELECT a.*, m.full_name, m.membership_no, m.enrollment_no, c.chamber_no AS preferred_chamber_no
            FROM chamber_applications a
            JOIN members m ON m.id = a.member_id
            LEFT JOIN chambers c ON c.id = a.preferred_chamber_id
            WHERE a.status = 'waiting_list'
            ORDER BY a.priority_no ASC, a.id ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading waiting list: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष आवंटन प्रतीक्षा सूची प्रबंधन (Waiting List Priorities)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-grid-fill me-1"></i>चैंबर मास्टर पैनल</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="alert alert-info py-2 font-hindi small mb-3 border-0">
    <i class="bi bi-info-circle-fill text-primary me-2"></i><strong>निर्देश:</strong> यहाँ दी गई प्राथमिकता संख्या बार एसोसिएशन कार्यकारिणी के नियमानुसार स्टाफ द्वारा नियत की जाती है। आवश्यकतानुसार इसे संशोधित करने हेतु प्राथमिकता मान बदलें और अपडेट करें।
</div>

<!-- Waiting List Table Grid -->
<div class="card border-0 shadow-sm font-hindi small">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock text-gold-custom me-2"></i>लंबित प्रतीक्षा कतार (Waiting List Queue)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2.5" style="width: 100px;">प्राथमिकता (Priority)</th>
                        <th>आवेदन क्रमांक</th>
                        <th>अधिवक्ता का नाम</th>
                        <th>सदस्यता संख्या</th>
                        <th>नामांकन संख्या</th>
                        <th>पसंदीदा चैम्बर / ब्लॉक</th>
                        <th>आवेदन तिथि</th>
                        <th class="text-end">प्राथमिकता बदलें</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($waiting_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">प्रतीक्षा सूची में कोई लंबित आवंटन अनुरोध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($waiting_list as $w): ?>
                            <tr>
                                <td class="py-2 text-danger fw-bold text-center fs-6 english-text">#<?php echo e($w['priority_no']); ?></td>
                                <td class="english-text fw-bold text-navy-custom"><?php echo e($w['application_no']); ?></td>
                                <td><strong><?php echo e($w['full_name']); ?></strong></td>
                                <td class="english-text"><?php echo e($w['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($w['enrollment_no']); ?></td>
                                <td>
                                    <?php if ($w['preferred_chamber_no']): ?>
                                        <span class="badge bg-light text-navy-custom border english-text">Chamber <?php echo e($w['preferred_chamber_no']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo e($w['chamber_preference']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($w['application_date'])); ?></td>
                                <td class="text-end">
                                    <form method="POST" action="" class="d-inline-flex gap-1">
                                        <?php insertCSRF(); ?>
                                        <input type="hidden" name="update_priority" value="1">
                                        <input type="hidden" name="app_id" value="<?php echo $w['id']; ?>">
                                        <input type="number" name="priority_no" class="form-control form-control-xs px-1 text-center" style="width: 60px; font-size: 0.72rem;" value="<?php echo $w['priority_no']; ?>" required min="1">
                                        <button type="submit" class="btn btn-xs btn-navy py-0.5 font-hindi">अपडेट</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
