<?php
/**
 * Admin Constitutional Positions Configuration Manager
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'संवैधानिक पद विन्यास (Bearer Positions)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin/mahasachiv roles
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        
        try {
            $db->beginTransaction();

            if ($action === 'create_position') {
                $name = sanitize(trim($_POST['position_name'] ?? ''));
                $name_hindi = sanitize(trim($_POST['position_name_hindi'] ?? ''));
                $code = strtoupper(sanitize(trim(str_replace(' ', '_', $_POST['code'] ?? ''))));
                $order = intval($_POST['display_order'] ?? 0);
                $max_holders = intval($_POST['max_holders'] ?? 1);
                $is_executive = isset($_POST['is_executive']) ? 1 : 0;
                $status = sanitize($_POST['status'] ?? 'active');

                if (empty($name) || empty($code)) {
                    throw new Exception('कृपया पद का अंग्रेजी नाम एवं कोड अवश्य भरें।');
                }

                $ins = $db->prepare("
                    INSERT INTO office_bearer_positions 
                    (position_name, position_name_hindi, code, display_order, max_holders, is_executive, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$name, $name_hindi, $code, $order, $max_holders, $is_executive, $status]);

                $db->commit();
                $success = 'नया संवैधानिक पद सफलतापूर्वक जोड़ा गया।';
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'पद जोड़ने में विफल: ' . $e->getMessage();
        }
    }
}

// Fetch all positions
$positions = [];
if ($db) {
    try {
        $positions = $db->query("SELECT * FROM office_bearer_positions ORDER BY display_order ASC, id ASC")->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">संवैधानिक पद विन्यास (DBA Bearer Positions)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>पदाधिकारी सूची</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left Column: Positions List Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">परिभाषित संवैधानिक पदों की सूची</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">वरीयता</th>
                                <th>पद का नाम (English)</th>
                                <th>पद का नाम (Hindi)</th>
                                <th>यूनिक कोड</th>
                                <th class="text-center">अधिकतम सीट</th>
                                <th>कार्यकारिणी</th>
                                <th>स्थिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($positions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted">कोई पद कॉन्फ़िगर नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($positions as $pos): ?>
                                    <tr>
                                        <td class="english-text text-center fw-bold">#<?php echo $pos['display_order']; ?></td>
                                        <td class="fw-bold"><?php echo e($pos['position_name']); ?></td>
                                        <td><?php echo e($pos['position_name_hindi']); ?></td>
                                        <td class="english-text text-secondary"><?php echo e($pos['code']); ?></td>
                                        <td class="english-text text-center fw-semibold"><?php echo $pos['max_holders']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $pos['is_executive'] ? 'bg-light text-navy-custom border' : 'bg-light text-muted border'; ?>">
                                                <?php echo $pos['is_executive'] ? 'Executive' : 'General'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $pos['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo e($pos['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Add Position Form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-plus-circle me-1"></i>नया पद सृजित करें</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="create_position">

                    <div class="mb-2">
                        <label class="form-label fw-bold">पद नाम (English) *</label>
                        <input type="text" name="position_name" class="form-control form-control-sm" required placeholder="उदा. President, Treasurer">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">पद नाम (हिन्दी)</label>
                        <input type="text" name="position_name_hindi" class="form-control form-control-sm" placeholder="उदा. अध्यक्ष, कोषाध्यक्ष">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">यूनिक कोड (Unique Code) *</label>
                        <input type="text" name="code" class="form-control form-control-sm text-uppercase" required placeholder="उदा. PRESIDENT, TREASURER">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label fw-bold">वरीयता क्रम *</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" required value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">अधिकतम धारक *</label>
                            <input type="number" name="max_holders" class="form-control form-control-sm" required value="1" min="1">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">कार्यकारिणी श्रेणी (Is Executive)</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_executive" value="1" checked id="execSwitch">
                            <label class="form-check-label text-muted" for="execSwitch">कार्यकारिणी का सदस्य है</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">स्थिति (Status) *</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="active">Active (सक्रिय)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-gold btn-sm w-100 text-navy-custom fw-bold"><i class="bi bi-check-circle-fill me-1"></i>सहेजें</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
