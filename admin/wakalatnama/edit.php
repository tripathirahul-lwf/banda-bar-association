<?php
/**
 * Admin Edit Wakalatnama Metadata Form
 * District Bar Association, Banda
 */

$pageTitle = 'वकालतनामा विवरण सुधार (Edit Metadata)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$doc = null;
$errors = [];
$db = Database::getConnection();

if ($db && $doc_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM wakalatnamas WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load metadata for edit: " . $e->getMessage());
    }
}

if (!$doc) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: वकालतनामा रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("edit.php?id=$doc_id");
    }

    $title = trim($_POST['title'] ?? '');
    $title_hindi = trim($_POST['title_hindi'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $effective_from = trim($_POST['effective_from'] ?? '');
    $effective_until = trim($_POST['effective_until'] ?? '');
    $members_only = isset($_POST['members_only']) ? 1 : 0;

    // Validation
    if (empty($title)) $errors[] = 'शीर्षक (Title) दर्ज करना आवश्यक है।';
    if (empty($category)) $errors[] = 'दस्तावेज श्रेणी का चयन करना आवश्यक है।';
    if (empty($effective_from)) $errors[] = 'प्रभावी तिथि दर्ज करना आवश्यक है।';

    if (empty($errors) && $db) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];

            // Update database details
            $up_stmt = $db->prepare("
                UPDATE wakalatnamas 
                SET title = ?, title_hindi = ?, description = ?, category = ?, effective_from = ?, effective_until = ?, members_only = ? 
                WHERE id = ?
            ");
            $up_stmt->execute([
                $title,
                $title_hindi ?: null,
                $description ?: null,
                $category,
                $effective_from,
                $effective_until ?: null,
                $members_only,
                $doc_id
            ]);

            // Log History
            $hist_stmt = $db->prepare("
                INSERT INTO wakalatnama_history (wakalatnama_id, action, old_status, new_status, remarks, performed_by) 
                VALUES (?, 'Metadata Updated', ?, ?, ?, ?)
            ");
            $hist_stmt->execute([
                $doc_id,
                $doc['status'],
                $doc['status'],
                "दस्तावेज़ की मेटाडेटा विवरण में सुधार किया गया।",
                $user_id
            ]);

            // Log Audit
            $log_desc = "Updated Wakalatnama ID $doc_id metadata: '$title'";
            $audit = $db->prepare("
                INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                VALUES (0, 'Wakalatnama Edit', 'metadata', ?, ?, ?, ?)
            ");
            $audit->execute([$doc['title'], $title, $log_desc, $user_id]);

            $db->commit();
            setFlash('success', 'दस्तावेज मेटाडेटा सफलतापूर्वक सुधारित किया गया।');
            redirect('index.php');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to update metadata: " . $e->getMessage());
            $errors[] = 'त्रुटि: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">मेटाडेटा विवरण सुधार (Edit Metadata)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">वापस जाएं</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small">
        <?php echo implode('<br>', $errors); ?>
    </div>
<?php endif; ?>

<form method="POST" action="edit.php?id=<?php echo $doc['id']; ?>" class="font-hindi small">
    <?php csrfField(); ?>
    
    <div class="row g-3 mb-4">
        <!-- Title & Hindi Title -->
        <div class="col-md-6">
            <label for="title" class="form-label text-secondary fw-semibold">दस्तावेज का अंग्रेजी शीर्षक *</label>
            <input type="text" class="form-control form-control-sm english-text" id="title" name="title" required value="<?php echo e($doc['title']); ?>">
        </div>
        <div class="col-md-6">
            <label for="title_hindi" class="form-label text-secondary fw-semibold">दस्तावेज का हिंदी शीर्षक</label>
            <input type="text" class="form-control form-control-sm" id="title_hindi" name="title_hindi" value="<?php echo e($doc['title_hindi']); ?>">
        </div>
        
        <!-- Category -->
        <div class="col-md-6">
            <label for="category" class="form-label text-secondary fw-semibold">श्रेणी / प्रभाग *</label>
            <select class="form-select form-select-sm" id="category" name="category" required>
                <option value="">श्रेणी चुनें...</option>
                <option value="General" <?php echo ($doc['category'] === 'General') ? 'selected' : ''; ?>>General</option>
                <option value="Civil" <?php echo ($doc['category'] === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                <option value="Criminal" <?php echo ($doc['category'] === 'Criminal') ? 'selected' : ''; ?>>Criminal</option>
                <option value="Family Court" <?php echo ($doc['category'] === 'Family Court') ? 'selected' : ''; ?>>Family Court</option>
                <option value="Revenue" <?php echo ($doc['category'] === 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                <option value="Other" <?php echo ($doc['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label text-secondary fw-semibold">संस्करण (Version - Read Only)</label>
            <input type="text" class="form-control form-control-sm english-text bg-light" disabled value="<?php echo e($doc['version']); ?>">
        </div>

        <!-- Dates -->
        <div class="col-md-6">
            <label for="effective_from" class="form-label text-secondary fw-semibold">प्रभावी तिथि से *</label>
            <input type="date" class="form-control form-control-sm english-text" id="effective_from" name="effective_from" required value="<?php echo e($doc['effective_from']); ?>">
        </div>
        <div class="col-md-6">
            <label for="effective_until" class="form-label text-secondary fw-semibold">प्रभावी तिथि तक (Optional)</label>
            <input type="date" class="form-control form-control-sm english-text" id="effective_until" name="effective_until" value="<?php echo e($doc['effective_until'] ?: ''); ?>">
        </div>

        <!-- Description -->
        <div class="col-12">
            <label for="description" class="form-label text-secondary fw-semibold">दस्तावेज विवरण</label>
            <textarea class="form-control form-control-sm" id="description" name="description" rows="2"><?php echo e($doc['description']); ?></textarea>
        </div>

        <div class="col-12 mt-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="members_only" name="members_only" value="1" <?php echo $doc['members_only'] ? 'checked' : ''; ?>>
                <label class="form-check-label text-navy-custom fw-semibold" for="members_only">
                    केवल बार सदस्यों के लिए सुरक्षित (Members Only Access)
                </label>
            </div>
        </div>
    </div>

    <!-- Buttons -->
    <div class="text-end mb-5">
        <a href="index.php" class="btn btn-outline-secondary px-4 me-2">रद्द करें</a>
        <button type="submit" class="btn btn-navy px-5 fw-semibold"><i class="bi bi-save-fill text-gold-custom me-2"></i>विवरण सुरक्षित करें (Save)</button>
    </div>
</form>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
