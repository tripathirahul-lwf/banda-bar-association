<?php
/**
 * Admin Executive Terms Configuration Manager
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कार्यकारिणी कार्यकाल विन्यास (Executive Terms)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin/mahasachiv roles
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Retrieve active term details
$current_active_term = null;
if ($db) {
    try {
        $stmt = $db->query("
            SELECT t.*, (SELECT COUNT(*) FROM office_bearers WHERE term_id = t.id) AS bearers_count 
            FROM office_bearer_terms t 
            WHERE t.status = 'active' LIMIT 1
        ");
        $current_active_term = $stmt->fetch();
    } catch (PDOException $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        
        try {
            $db->beginTransaction();

            if ($action === 'create_term') {
                $title = sanitize(trim($_POST['title'] ?? ''));
                $start_date = sanitize($_POST['start_date'] ?? '');
                $end_date = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
                $description = sanitize(trim($_POST['description'] ?? ''));

                if (empty($title) || empty($start_date)) {
                    throw new Exception('कृपया कार्यकाल शीर्षक एवं प्रारंभ तिथि अवश्य भरें।');
                }

                $ins = $db->prepare("
                    INSERT INTO office_bearer_terms (title, start_date, end_date, status, description, created_by)
                    VALUES (?, ?, ?, 'draft', ?, ?)
                ");
                $ins->execute([$title, $start_date, $end_date, $description, $_SESSION['user_id']]);

                // Global Audit Log
                $audit = $db->prepare("INSERT INTO notice_history (notice_id, action, remarks, performed_by) VALUES (1, 'Term Created', ?, ?)");
                $audit->execute(["कार्यकाल '{$title}' ड्राफ्ट के रूप में सृजित किया गया।", $_SESSION['user_id']]);

                $db->commit();
                $success = 'नया कार्यकाल ड्राफ्ट सफलतापूर्वक तैयार किया गया।';
            } elseif ($action === 'activate_term') {
                $term_id = intval($_POST['term_id'] ?? 0);
                $complete_old = isset($_POST['complete_old']) ? 1 : 0;
                $old_end_date = sanitize($_POST['old_end_date'] ?? '');

                if ($term_id <= 0) {
                    throw new Exception('अमान्य कार्यकाल आईडी।');
                }

                // If completing old term is checked, update current active term to completed
                if ($complete_old && $current_active_term) {
                    if (empty($old_end_date)) {
                        throw new Exception('निवर्तमान कार्यकाल समाप्त करने हेतु समाप्ति तिथि आवश्यक है।');
                    }
                    // End active bearers status under that old term
                    $end_ob = $db->prepare("UPDATE office_bearers SET status = 'completed', end_date = ? WHERE term_id = ? AND status = 'active'");
                    $end_ob->execute([$old_end_date, $current_active_term['id']]);

                    // Complete term
                    $up_term = $db->prepare("UPDATE office_bearer_terms SET status = 'completed', end_date = ?, updated_at = NOW() WHERE id = ?");
                    $up_term->execute([$old_end_date, $current_active_term['id']]);
                }

                // Deactivate any active terms just in case
                $db->query("UPDATE office_bearer_terms SET status = 'completed' WHERE status = 'active'");

                // Activate new term
                $act_stmt = $db->prepare("UPDATE office_bearer_terms SET status = 'active', updated_at = NOW() WHERE id = ?");
                $act_stmt->execute([$term_id]);

                // Global Audit Log
                $audit = $db->prepare("INSERT INTO notice_history (notice_id, action, remarks, performed_by) VALUES (1, 'Term Activated', ?, ?)");
                $audit->execute(["कार्यकाल आईडी {$term_id} को सक्रिय (Active) किया गया।", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'नया कार्यकाल सफलतापूर्वक सक्रिय कर दिया गया है।';
                header("Location: terms.php");
                exit;

            } elseif ($action === 'complete_term_direct') {
                $term_id = intval($_POST['term_id'] ?? 0);
                $end_date = sanitize($_POST['end_date'] ?? '');

                if ($term_id <= 0 || empty($end_date)) {
                    throw new Exception('अमान्य कार्यकाल या समाप्ति तिथि।');
                }

                // Update active bearers of this term to completed
                $end_ob = $db->prepare("UPDATE office_bearers SET status = 'completed', end_date = ? WHERE term_id = ? AND status = 'active'");
                $end_ob->execute([$end_date, $term_id]);

                // Update term status
                $up_term = $db->prepare("UPDATE office_bearer_terms SET status = 'completed', end_date = ?, updated_at = NOW() WHERE id = ?");
                $up_term->execute([$end_date, $term_id]);

                $db->commit();
                $success = 'कार्यकाल सफलतापूर्वक पूर्ण (Completed) घोषित किया गया।';
            } elseif ($action === 'archive_term') {
                $term_id = intval($_POST['term_id'] ?? 0);
                if ($term_id <= 0) {
                    throw new Exception('अमान्य कार्यकाल।');
                }

                $up_term = $db->prepare("UPDATE office_bearer_terms SET status = 'archived', updated_at = NOW() WHERE id = ?");
                $up_term->execute([$term_id]);

                $db->commit();
                $success = 'कार्यकाल सफलतापूर्वक पुरालेख (Archived) में भेज दिया गया है।';
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'कार्यवाही विफल: ' . $e->getMessage();
        }
    }
}

// Fetch all terms
$terms = [];
if ($db) {
    try {
        $terms = $db->query("
            SELECT t.*, (SELECT COUNT(*) FROM office_bearers WHERE term_id = t.id) AS bearers_count 
            FROM office_bearer_terms t 
            ORDER BY t.id DESC
        ")->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कार्यकारिणी कार्यकाल विन्यास (Executive Terms)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>पदाधिकारी सूची</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left Column: Terms List -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">पंजीकृत कार्यकारिणियों की सूची</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>शीर्षक (Title)</th>
                                <th>प्रारंभ तिथि</th>
                                <th>समाप्ति तिथि</th>
                                <th class="text-center">पदाधिकारी संख्या</th>
                                <th>स्थिति</th>
                                <th class="text-end">कार्यवाही</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($terms)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-muted">कोई कार्यकाल पंजीकृत नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($terms as $t): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($t['title']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.7rem;"><?php echo e($t['description']); ?></span>
                                        </td>
                                        <td class="english-text"><?php echo date('d-m-Y', strtotime($t['start_date'])); ?></td>
                                        <td class="english-text"><?php echo $t['end_date'] ? date('d-m-Y', strtotime($t['end_date'])) : '-'; ?></td>
                                        <td class="english-text text-center fw-semibold"><?php echo $t['bearers_count']; ?></td>
                                        <td>
                                            <?php 
                                            $c = ($t['status'] === 'active') ? 'bg-success' : (($t['status'] === 'draft') ? 'bg-warning text-dark' : (($t['status'] === 'archived') ? 'bg-danger' : 'bg-secondary'));
                                            ?>
                                            <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($t['status'])); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <?php if ($t['status'] === 'draft'): ?>
                                                    <button class="btn btn-xs btn-success py-0.5" data-bs-toggle="modal" data-bs-target="#activateModal<?php echo $t['id']; ?>">Activate</button>
                                                <?php elseif ($t['status'] === 'active'): ?>
                                                    <button class="btn btn-xs btn-outline-danger py-0.5" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $t['id']; ?>">Complete</button>
                                                <?php elseif ($t['status'] === 'completed'): ?>
                                                    <button class="btn btn-xs btn-outline-secondary py-0.5" data-bs-toggle="modal" data-bs-target="#archiveModal<?php echo $t['id']; ?>">Archive</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Activate Modal with Confirm Checks (Requirement 19) -->
                                    <?php if ($t['status'] === 'draft'): ?>
                                        <div class="modal fade" id="activateModal<?php echo $t['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content text-start">
                                                    <div class="modal-header bg-success text-white py-2">
                                                        <h6 class="modal-title fw-bold">कार्यकाल सक्रिय करें (Activate Term)</h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-navy-custom">
                                                        <form method="POST" action="">
                                                            <?php insertCSRF(); ?>
                                                            <input type="hidden" name="action" value="activate_term">
                                                            <input type="hidden" name="term_id" value="<?php echo $t['id']; ?>">

                                                            <div class="p-3 bg-light rounded border mb-3">
                                                                <h6 class="fw-bold mb-1">प्रक्रिया विवरण:</h6>
                                                                <ul class="mb-0 ps-3">
                                                                    <li>सक्रिय करने वाला कार्यकाल: <strong><?php echo e($t['title']); ?></strong></li>
                                                                    <li>पदाधिकारी संख्या: <strong class="english-text"><?php echo $t['bearers_count']; ?></strong></li>
                                                                </ul>
                                                            </div>

                                                            <?php if ($current_active_term): ?>
                                                                <div class="alert alert-warning py-2 mb-3">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="complete_old" value="1" id="completeOldCheck" checked>
                                                                        <label class="form-check-label fw-bold" for="completeOldCheck">
                                                                            हाँ, निवर्तमान सक्रिय कार्यकाल '<?php echo e($current_active_term['title']); ?>' को समाप्त (Completed) घोषित करें।
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3" id="oldEndBlock">
                                                                    <label class="form-label fw-bold">निवर्तमान कार्यकाल समाप्ति तिथि *</label>
                                                                    <input type="date" name="old_end_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                                                                </div>
                                                            <?php endif; ?>

                                                            <div class="text-end">
                                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                                <button type="submit" class="btn btn-xs btn-success px-3">सक्रिय करें (Activate Now)</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Complete Direct Modal -->
                                    <?php if ($t['status'] === 'active'): ?>
                                        <div class="modal fade" id="completeModal<?php echo $t['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content text-start">
                                                    <div class="modal-header bg-danger text-white py-2">
                                                        <h6 class="modal-title fw-bold">कार्यकाल पूर्ण करें (Complete Term)</h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-navy-custom">
                                                        <form method="POST" action="">
                                                            <?php insertCSRF(); ?>
                                                            <input type="hidden" name="action" value="complete_term_direct">
                                                            <input type="hidden" name="term_id" value="<?php echo $t['id']; ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">समाप्ति तिथि (End Date) *</label>
                                                                <input type="date" name="end_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                                            </div>

                                                            <div class="text-end">
                                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                                <button type="submit" class="btn btn-xs btn-danger px-3">Complete Term</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Archive Direct Modal -->
                                    <?php if ($t['status'] === 'completed'): ?>
                                        <div class="modal fade" id="archiveModal<?php echo $t['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content text-start">
                                                    <div class="modal-header bg-secondary text-white py-2">
                                                        <h6 class="modal-title fw-bold">कार्यकाल पुरालेख में भेजें (Archive Term)</h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-navy-custom">
                                                        <form method="POST" action="">
                                                            <?php insertCSRF(); ?>
                                                            <input type="hidden" name="action" value="archive_term">
                                                            <input type="hidden" name="term_id" value="<?php echo $t['id']; ?>">
                                                            <p>क्या आप इस पूर्ण कार्यकाल को आर्काइव (पुरालेख) में भेजना चाहते हैं? ऐतिहासिक रिकॉर्ड सुरक्षित रहेगा।</p>
                                                            <div class="text-end">
                                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                                <button type="submit" class="btn btn-xs btn-secondary px-3">Archive Term</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Create Term Form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-plus-circle me-1"></i>नया कार्यकाल ड्राफ्ट करें</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="create_term">

                    <div class="mb-2">
                        <label class="form-label fw-bold">कार्यकाल का नाम (Title) *</label>
                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="उदा. Executive Committee 2027-28">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">प्रारंभ तिथि (Start Date) *</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">अनुमानित समाप्ति तिथि (End Date - Optional)</label>
                        <input type="date" name="end_date" class="form-control form-control-sm">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">कार्यकाल का संक्षिप्त विवरण</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="उदा. इस कार्यकाल के नीतिगत लक्ष्य..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-navy btn-sm w-100 fw-bold"><i class="bi bi-check-circle-fill me-1"></i>कार्यकाल सहेजें</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
