<?php
/**
 * Admin Upload New Wakalatnama Document Draft Form
 * District Bar Association, Banda
 */

$pageTitle = 'नया वकालतनामा अपलोड करें (Upload Wakalatnama)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$errors = [];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('create.php');
    }

    $title = trim($_POST['title'] ?? '');
    $title_hindi = trim($_POST['title_hindi'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $effective_from = trim($_POST['effective_from'] ?? '');
    $effective_until = trim($_POST['effective_until'] ?? '');
    $members_only = isset($_POST['members_only']) ? 1 : 0;
    $status = trim($_POST['status'] ?? 'draft');

    // Validation
    if (empty($title)) $errors[] = 'शीर्षक (Title) दर्ज करना आवश्यक है।';
    if (empty($category)) $errors[] = 'दस्तावेज श्रेणी का चयन करना आवश्यक है।';
    if (empty($effective_from)) $errors[] = 'प्रभावी तिथि (Effective Date) दर्ज करना आवश्यक है।';
    
    // File validation
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'कृपया वकालतनामा पीडीएफ फाइल अपलोड करें।';
    } else {
        $file = $_FILES['pdf_file'];
        $max_size = defined('WAKALATNAMA_MAX_FILE_SIZE') ? WAKALATNAMA_MAX_FILE_SIZE : 5242880;
        
        $mime = mime_content_type($file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($mime !== 'application/pdf' || $ext !== 'pdf') {
            $errors[] = 'अमान्य फ़ाइल प्रकार। केवल पीडीएफ (PDF) फ़ाइल ही स्वीकार की जाती है।';
        }
        if ($file['size'] > $max_size) {
            $errors[] = 'फ़ाइल का आकार सीमा ' . round($max_size / 1024 / 1024, 1) . 'MB से अधिक नहीं होना चाहिए।';
        }
    }

    if (empty($errors) && $db) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];

            // Save file with random name
            $random_name = 'waka_' . bin2hex(random_bytes(16)) . '.pdf';
            $dest_dir = __DIR__ . '/../../storage/wakalatnama/';
            
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $dest_dir . $random_name)) {
                // Insert details to database
                $ins_stmt = $db->prepare("
                    INSERT INTO wakalatnamas (title, title_hindi, description, category, file_path, original_file_name, file_size, version, effective_from, effective_until, status, members_only, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([
                    $title,
                    $title_hindi ?: null,
                    $description ?: null,
                    $category,
                    $random_name,
                    $file['name'],
                    $file['size'],
                    $version,
                    $effective_from,
                    $effective_until ?: null,
                    $status,
                    $members_only,
                    $user_id
                ]);
                $doc_id = $db->lastInsertId();

                // Log History
                $hist_stmt = $db->prepare("
                    INSERT INTO wakalatnama_history (wakalatnama_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, 'Created & Uploaded', NULL, ?, ?, ?)
                ");
                $hist_stmt->execute([
                    $doc_id,
                    $status,
                    "वकालतनामा ड्राफ्ट फ़ाइल अपलोड की गई: " . $file['name'],
                    $user_id
                ]);

                // Log Audit
                $log_desc = "Uploaded new Wakalatnama '$title' Version $version as status $status";
                $audit = $db->prepare("
                    INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                    VALUES (0, 'Wakalatnama Upload', 'title', NULL, ?, ?, ?)
                ");
                $audit->execute([$title, $log_desc, $user_id]);

                $db->commit();
                setFlash('success', 'वकालतनामा सफलतापूर्वक अपलोड हुआ।');
                redirect('index.php');
            } else {
                throw new Exception('फ़ाइल सहेजने में विफल (Failed to move uploaded file).');
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to create wakalatnama upload: " . $e->getMessage());
            $errors[] = 'त्रुटि: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">नया वकालतनामा अपलोड (Upload New Document)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">वापस सूची पर जाएं</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small">
        <?php echo implode('<br>', $errors); ?>
    </div>
<?php endif; ?>

<form method="POST" action="create.php" enctype="multipart/form-data" class="font-hindi small">
    <?php csrfField(); ?>
    
    <div class="row g-3 mb-4">
        <!-- Title & Hindi Title -->
        <div class="col-md-6">
            <label for="title" class="form-label text-secondary fw-semibold">दस्तावेज का अंग्रेजी शीर्षक * (English Title)</label>
            <input type="text" class="form-control form-control-sm english-text" id="title" name="title" placeholder="e.g. Civil Wakalatnama" required value="<?php echo e($_POST['title'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
            <label for="title_hindi" class="form-label text-secondary fw-semibold">दस्तावेज का हिंदी शीर्षक (Hindi Title)</label>
            <input type="text" class="form-control form-control-sm" id="title_hindi" name="title_hindi" placeholder="e.g. दीवानी वकालतनामा" value="<?php echo e($_POST['title_hindi'] ?? ''); ?>">
        </div>
        
        <!-- Category & Version -->
        <div class="col-md-6">
            <label for="category" class="form-label text-secondary fw-semibold">श्रेणी / प्रभाग * (Category)</label>
            <select class="form-select form-select-sm" id="category" name="category" required>
                <option value="">श्रेणी चुनें...</option>
                <option value="General" <?php echo (($_POST['category'] ?? '') === 'General') ? 'selected' : ''; ?>>General</option>
                <option value="Civil" <?php echo (($_POST['category'] ?? '') === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                <option value="Criminal" <?php echo (($_POST['category'] ?? '') === 'Criminal') ? 'selected' : ''; ?>>Criminal</option>
                <option value="Family Court" <?php echo (($_POST['category'] ?? '') === 'Family Court') ? 'selected' : ''; ?>>Family Court</option>
                <option value="Revenue" <?php echo (($_POST['category'] ?? '') === 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                <option value="Other" <?php echo (($_POST['category'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="version" class="form-label text-secondary fw-semibold">संस्करण * (Version)</label>
            <input type="text" class="form-control form-control-sm english-text" id="version" name="version" placeholder="e.g. 1.0" required value="<?php echo e($_POST['version'] ?? '1.0'); ?>">
        </div>

        <!-- Dates -->
        <div class="col-md-6">
            <label for="effective_from" class="form-label text-secondary fw-semibold">प्रभावी तिथि से * (Effective From)</label>
            <input type="date" class="form-control form-control-sm english-text" id="effective_from" name="effective_from" required value="<?php echo e($_POST['effective_from'] ?? date('Y-m-d')); ?>">
        </div>
        <div class="col-md-6">
            <label for="effective_until" class="form-label text-secondary fw-semibold">प्रभावी तिथि तक (Optional)</label>
            <input type="date" class="form-control form-control-sm english-text" id="effective_until" name="effective_until" value="<?php echo e($_POST['effective_until'] ?? ''); ?>">
        </div>

        <!-- Description -->
        <div class="col-12">
            <label for="description" class="form-label text-secondary fw-semibold">दस्तावेज विवरण (Description)</label>
            <textarea class="form-control form-control-sm" id="description" name="description" rows="2" placeholder="वकालतनामा के सम्बन्ध में संक्षिप्त विवरण लिखें..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
        </div>

        <!-- File Upload -->
        <div class="col-md-6">
            <label for="pdf_file" class="form-label text-secondary fw-semibold">पीडीएफ फाइल अपलोड करें * (Select PDF)</label>
            <input type="file" class="form-control form-control-sm" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text text-muted" style="font-size:0.65rem;">केवल PDF फाइल (अधिकतम 5MB)। फ़ाइल नाम अपलोड पर रैंडमाइज कर दिया जाएगा।</div>
        </div>

        <!-- Status & Settings -->
        <div class="col-md-6">
            <label for="status" class="form-label text-secondary fw-semibold">अपलोड स्थिति (Initial Status)</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="draft" <?php echo (($_POST['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>ड्राफ्ट (Draft)</option>
                <option value="pending_approval" <?php echo (($_POST['status'] ?? '') === 'pending_approval') ? 'selected' : ''; ?>>स्वीकृति हेतु लंबित (Pending Approval)</option>
            </select>
        </div>

        <div class="col-12 mt-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="members_only" name="members_only" value="1" checked>
                <label class="form-check-label text-navy-custom fw-semibold" for="members_only">
                    केवल बार सदस्यों के लिए सुरक्षित (Members Only Access)
                </label>
            </div>
        </div>
    </div>

    <!-- Buttons -->
    <div class="text-end mb-5">
        <a href="index.php" class="btn btn-outline-secondary px-4 me-2">रद्द करें</a>
        <button type="submit" class="btn btn-navy px-5 fw-semibold"><i class="bi bi-cloud-arrow-up-fill text-gold-custom me-2"></i>अपलोड करें (Upload)</button>
    </div>
</form>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
