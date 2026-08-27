<?php
/**
 * Room / Chamber Creation Master Form
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'नया कक्ष / चैंबर जोड़ें (Add New Chamber)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Handle Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $chamber_no = sanitize(trim($_POST['chamber_no'] ?? ''));
        $block_name = sanitize(trim($_POST['block_name'] ?? ''));
        $floor = sanitize(trim($_POST['floor'] ?? ''));
        $chamber_type = sanitize(trim($_POST['chamber_type'] ?? ''));
        $monthly_rent = floatval($_POST['monthly_rent'] ?? 0.00);
        $security_deposit = floatval($_POST['security_deposit'] ?? 0.00);
        $occupancy_type = sanitize(trim($_POST['occupancy_type'] ?? 'single'));
        $capacity = intval($_POST['capacity'] ?? 1);
        $status = sanitize(trim($_POST['status'] ?? 'available'));
        $description = sanitize(trim($_POST['description'] ?? ''));

        if (empty($chamber_no) || $monthly_rent <= 0 || $security_deposit < 0 || $capacity <= 0) {
            $error = 'कृपया चैंबर नंबर, मासिक किराया, क्षमता और जमानत राशि सही रूप से भरें।';
        } else {
            try {
                $db->beginTransaction();

                // Validate uniqueness
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM chambers WHERE chamber_no = ?");
                $check_stmt->execute([$chamber_no]);
                if ($check_stmt->fetchColumn() > 0) {
                    throw new Exception("चैंबर संख्या '$chamber_no' पहले से ही डेटाबेस में पंजीकृत है।");
                }

                // Insert
                $ins = $db->prepare("
                    INSERT INTO chambers 
                    (chamber_no, block_name, floor, chamber_type, monthly_rent, security_deposit, occupancy_type, capacity, status, description, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$chamber_no, $block_name, $floor, $chamber_type, $monthly_rent, $security_deposit, $occupancy_type, $capacity, $status, $description, $_SESSION['user_id']]);

                // Audit Log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, new_value, remarks, performed_by) VALUES ('Chamber Created', ?, ?, ?)");
                $audit->execute([$chamber_no, "नया कक्ष $chamber_no जोड़ा गया। मासिक किराया: ₹$monthly_rent", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = "नया चैंबर संख्या $chamber_no सफलतापूर्वक मास्टर रजिस्टर में जोड़ दिया गया है।";
                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'चैंबर पंजीकरण विफल: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">नया कक्ष/चैंबर पंजीकृत करें (Create Chamber Master)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>चैंबर सूची</a>
</div>

<div class="row justify-content-center font-hindi small">
    <div class="col-lg-8">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm text-navy-custom">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle-fill text-gold-custom me-2"></i>चैंबर विवरण प्रपत्र (Chamber Specifications)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php insertCSRF(); ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">चैंबर संख्या (Chamber No) <span class="text-danger">*</span></label>
                            <input type="text" name="chamber_no" class="form-control form-control-sm" required placeholder="उदा. A-12, B-04">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">परिसर ब्लॉक / भवन</label>
                            <input type="text" name="block_name" class="form-control form-control-sm" placeholder="उदा. Main Court Block">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">तल (Floor Location)</label>
                            <input type="text" name="floor" class="form-control form-control-sm" placeholder="उदा. Ground Floor">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">चैंबर श्रेणी (Chamber Type)</label>
                            <input type="text" name="chamber_type" class="form-control form-control-sm" placeholder="उदा. General Room, AC Cabin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">आवंटन प्रकार (Occupancy) <span class="text-danger">*</span></label>
                            <select name="occupancy_type" class="form-select form-select-sm" required>
                                <option value="single">एकल आवंटन (Single Occupancy)</option>
                                <option value="shared">साझा आवंटन (Shared Occupancy)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">कुल क्षमता (Max Occupants) <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" class="form-control form-control-sm" required value="1" min="1">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">मासिक लाइसेंस किराया (Rent) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="monthly_rent" class="form-control" required placeholder="1000.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">जमानत सुरक्षा निधि (Security Deposit) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="security_deposit" class="form-control" required placeholder="5000.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">चैंबर स्थिति (Initial Status) <span class="text-danger">*</span></label>
                            <select name="status" class="form-select form-select-sm" required>
                                <option value="available">उपलब्ध (Available)</option>
                                <option value="maintenance">रखरखाव में (Maintenance)</option>
                                <option value="inactive">निष्क्रिय (Inactive)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">कमरे का विवरण / टिप्पणी (Description)</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="चैंबर के आकार, फर्नीचर सुविधाओं या विशेष टिप्पणियों का विवरण..."></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-navy btn-sm px-4"><i class="bi bi-save me-1"></i>मास्टर रजिस्टर में सुरक्षित करें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
