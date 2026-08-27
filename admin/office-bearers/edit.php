<?php
/**
 * Edit Office Bearer Details Form
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce admin/mahasachiv roles
requireRole(['admin', 'mahasachiv']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = Database::getConnection();

$bearer = null;
$error = '';
$success = '';

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT ob.*, p.position_name, p.position_name_hindi, t.title AS term_title
            FROM office_bearers ob
            JOIN office_bearer_positions p ON p.id = ob.position_id
            JOIN office_bearer_terms t ON t.id = ob.term_id
            WHERE ob.id = ?
        ");
        $stmt->execute([$id]);
        $bearer = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load office bearer: " . $e->getMessage());
    }
}

if (!$bearer) {
    echo '<h3>त्रुटि: पदाधिकारी रिकॉर्ड नहीं मिला।</h3>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $display_name = sanitize(trim($_POST['display_name_snapshot'] ?? ''));
        $designation_override = !empty($_POST['designation_override']) ? sanitize(trim($_POST['designation_override'])) : null;
        $message = sanitize(trim($_POST['message'] ?? ''));
        $display_order = intval($_POST['display_order'] ?? 1);
        $start_date = !empty($_POST['start_date']) ? sanitize($_POST['start_date']) : null;
        $end_date = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
        $status = sanitize($_POST['status'] ?? 'active');

        try {
            $db->beginTransaction();

            // Photo snapshot upload logic (Requirement 33)
            $photo_snapshot = $bearer['photo_snapshot'];
            if (!empty($_FILES['term_photo']['name'])) {
                $target_dir = __DIR__ . '/../../uploads/bearers/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['term_photo']['name'], PATHINFO_EXTENSION));
                if (!in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                    throw new Exception('केवल JPG, JPEG, या PNG फाइल ही अपलोड की जा सकती है।');
                }

                $new_filename = 'bearer_' . $id . '_' . time() . '.' . $file_ext;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES['term_photo']['tmp_name'], $target_file)) {
                    // Update photo snapshot path
                    $photo_snapshot = 'bearers/' . $new_filename;
                } else {
                    throw new Exception('फ़ाइल अपलोड करने में असमर्थ।');
                }
            }

            $up = $db->prepare("
                UPDATE office_bearers 
                SET display_name_snapshot = ?, designation_override = ?, message = ?, 
                    display_order = ?, start_date = ?, end_date = ?, status = ?, 
                    photo_snapshot = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $up->execute([
                $display_name, $designation_override, $message,
                $display_order, $start_date, $end_date, $status,
                $photo_snapshot, $id
            ]);

            // Save history log
            $hist = $db->prepare("
                INSERT INTO office_bearer_history (office_bearer_id, action, remarks, performed_by)
                VALUES (?, 'Record Updated', 'Updated details and message.', ?)
            ");
            $hist->execute([$id, $_SESSION['user_id']]);

            $db->commit();
            $_SESSION['flash_success'] = 'पदाधिकारी का विवरण सफलतापूर्वक अद्यतन किया गया।';
            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'अद्यतन करने में विफलता: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'पदाधिकारी विवरण अद्यतन (Edit Office Bearer)';
require_once __DIR__ . '/../../includes/dashboard/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">पदाधिकारी विवरण अद्यतन (Edit Bearer)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>पदाधिकारी सूची</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">पदाधिकारी विवरण संशोधन प्रपत्र</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php insertCSRF(); ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">सम्बद्ध कार्यकाल (Term)</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="<?php echo e($bearer['term_title']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">संवैधानिक पद (Designation)</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="<?php echo e($bearer['position_name_hindi']); ?> (<?php echo e($bearer['position_name']); ?>)" readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">अधिवक्ता का नाम (Snapshot) *</label>
                            <input type="text" name="display_name_snapshot" class="form-control form-control-sm" required value="<?php echo e($bearer['display_name_snapshot']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">पंजीकरण क्रमांक (Snapshot)</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="<?php echo e($bearer['enrollment_no_snapshot']); ?>" readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">विशेष पदनाम (Override designation - Optional)</label>
                            <input type="text" name="designation_override" class="form-control form-control-sm" value="<?php echo e($bearer['designation_override']); ?>" placeholder="उदा. वरिष्ठ अध्यक्ष">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">वरीयता क्रम (Display Order) *</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" required value="<?php echo $bearer['display_order']; ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">कार्यकाल प्रारंभ तिथि</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $bearer['start_date']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">कार्यकाल समाप्ति तिथि</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $bearer['end_date']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">स्थिति (Status) *</label>
                            <select name="status" class="form-select form-select-sm" required>
                                <option value="active" <?php echo $bearer['status'] === 'active' ? 'selected' : ''; ?>>Active (सक्रिय)</option>
                                <option value="inactive" <?php echo $bearer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="resigned" <?php echo $bearer['status'] === 'resigned' ? 'selected' : ''; ?>>Resigned (इस्तीफा)</option>
                                <option value="completed" <?php echo $bearer['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Photo Upload with Preview -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">कार्यकाल विशिष्ट फोटो (Custom snapshot image - Optional)</label>
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $bearer['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" class="rounded border" style="width: 70px; height: 70px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                            <div>
                                <input type="file" name="term_photo" class="form-control form-control-sm" accept=".jpg, .jpeg, .png">
                                <small class="text-muted d-block mt-1">अपलोड करने पर सदस्य मास्टर प्रोफाइल फोटो परिवर्तित नहीं होगी।</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">अधिकारी का संदेश (Message)</label>
                        <textarea name="message" class="form-control form-control-sm" rows="4" placeholder="पदाधिकारी का आधिकारिक संदेश यहाँ प्रविष्ट करें..."><?php echo e($bearer['message']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-navy btn-sm w-100 fw-bold"><i class="bi bi-cloud-upload-fill me-1"></i>अद्यतन विवरण सहेजें</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
