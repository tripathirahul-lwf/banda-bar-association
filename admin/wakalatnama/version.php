<?php
/**
 * Admin Upload New Version of Wakalatnama
 * District Bar Association, Banda
 */

$pageTitle = 'नया संस्करण अपलोड (Upload New Version)';
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
        error_log("Failed to load document for new version: " . $e->getMessage());
    }
}

if (!$doc) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: वकालतनामा रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

// Compute a suggested version increment
$current_ver = floatval($doc['version']);
$suggested_ver = $current_ver > 0 ? sprintf("%.1f", $current_ver + 0.1) : '1.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("version.php?id=$doc_id");
    }

    $new_version = trim($_POST['version'] ?? '');
    $effective_from = trim($_POST['effective_from'] ?? '');
    $archive_old = isset($_POST['archive_old']) ? 1 : 0;
    $status = trim($_POST['status'] ?? 'draft');

    if (empty($new_version)) $errors[] = 'नया संस्करण नंबर दर्ज करना आवश्यक है।';
    if ($new_version === $doc['version']) $errors[] = 'संस्करण संख्या पुराने संस्करण से भिन्न होनी चाहिए।';
    if (empty($effective_from)) $errors[] = 'प्रभावी तिथि दर्ज करना आवश्यक है।';

    // File validation
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'कृपया नया वकालतनामा पीडीएफ फाइल अपलोड करें।';
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
            
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

            if (move_uploaded_file($file['tmp_name'], $dest_dir . $random_name)) {
                // 1. Insert new version row
                $ins_stmt = $db->prepare("
                    INSERT INTO wakalatnamas (title, title_hindi, description, category, file_path, original_file_name, file_size, version, effective_from, status, members_only, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([
                    $doc['title'],
                    $doc['title_hindi'] ?: null,
                    $doc['description'] ?: null,
                    $doc['category'],
                    $random_name,
                    $file['name'],
                    $file['size'],
                    $new_version,
                    $effective_from,
                    $status,
                    $doc['members_only'],
                    $user_id
                ]);
                $new_doc_id = $db->lastInsertId();

                // Log history for new version
                $hist1 = $db->prepare("
                    INSERT INTO wakalatnama_history (wakalatnama_id, action, old_status, new_status, remarks, performed_by) 
                    VALUES (?, 'Created & Uploaded', NULL, ?, ?, ?)
                ");
                $hist1->execute([
                    $new_doc_id,
                    $status,
                    "नया संस्करण v$new_version अपलोड किया गया। पुराना स्रोत ID: $doc_id",
                    $user_id
                ]);

                // 2. Archive old version if requested
                if ($archive_old) {
                    $up_old = $db->prepare("UPDATE wakalatnamas SET status = 'archived' WHERE id = ?");
                    $up_old->execute([$doc_id]);

                    $hist2 = $db->prepare("
                        INSERT INTO wakalatnama_history (wakalatnama_id, action, old_status, new_status, remarks, performed_by) 
                        VALUES (?, 'Archived', ?, 'archived', ?, ?)
                    ");
                    $hist2->execute([
                        $doc_id,
                        $doc['status'],
                        "नए संस्करण v$new_version के कारण स्वतः आर्काइव किया गया।",
                        $user_id
                    ]);
                }

                // Log Audit
                $log_desc = "Uploaded new version v$new_version for Wakalatnama ID $doc_id, old archived: " . ($archive_old ? 'Yes' : 'No');
                $audit = $db->prepare("
                    INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                    VALUES (0, 'Wakalatnama Version Change', 'version', ?, ?, ?, ?)
                ");
                $audit->execute([$doc['version'], $new_version, $log_desc, $user_id]);

                $db->commit();
                setFlash('success', "नया संस्करण v$new_version सफलतापूर्वक अपलोड किया गया।");
                redirect('index.php');
            } else {
                throw new Exception('फ़ाइल सहेजने में विफल।');
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to upload version: " . $e->getMessage());
            $errors[] = 'त्रुटि: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">नया संस्करण अपलोड (New version upload: <?php echo e($doc['title']); ?>)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">वापस जाएं</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small">
        <?php echo implode('<br>', $errors); ?>
    </div>
<?php endif; ?>

<div class="card bg-light-custom p-3 border-0 shadow-xs mb-4 font-hindi small text-muted">
    <h6 class="fw-bold text-navy-custom mb-2"><i class="bi bi-info-circle-fill me-1"></i>वर्तमान सक्रिय संस्करण</h6>
    <span>शीर्षक: <strong class="text-navy-custom english-text"><?php echo e($doc['title']); ?></strong> | वर्तमान संस्करण: <strong class="text-navy-custom english-text">v<?php echo e($doc['version']); ?></strong> | स्थिति: <span class="badge bg-success font-size-xs px-2"><?php echo e($doc['status']); ?></span></span>
</div>

<form method="POST" action="version.php?id=<?php echo $doc['id']; ?>" enctype="multipart/form-data" class="font-hindi small">
    <?php csrfField(); ?>
    
    <div class="row g-3 mb-4">
        <!-- Version Number -->
        <div class="col-md-6">
            <label for="version" class="form-label text-secondary fw-semibold">नया संस्करण संख्या * (New Version No)</label>
            <input type="text" class="form-control form-control-sm english-text" id="version" name="version" required placeholder="e.g. 1.1" value="<?php echo e($_POST['version'] ?? $suggested_ver); ?>">
            <div class="form-text text-muted" style="font-size:0.65rem;">पुराने संस्करण (v<?php echo e($doc['version']); ?>) से अधिक संख्या दर्ज करें।</div>
        </div>

        <!-- Effective Date -->
        <div class="col-md-6">
            <label for="effective_from" class="form-label text-secondary fw-semibold">नवीन प्रभावी तिथि से *</label>
            <input type="date" class="form-control form-control-sm english-text" id="effective_from" name="effective_from" required value="<?php echo e($_POST['effective_from'] ?? date('Y-m-d')); ?>">
        </div>

        <!-- PDF Upload -->
        <div class="col-md-6">
            <label for="pdf_file" class="form-label text-secondary fw-semibold">नवीन पीडीएफ फ़ाइल अपलोड करें *</label>
            <input type="file" class="form-control form-control-sm" id="pdf_file" name="pdf_file" accept=".pdf" required>
        </div>

        <!-- Status -->
        <div class="col-md-6">
            <label for="status" class="form-label text-secondary fw-semibold">नए संस्करण की स्थिति (Status)</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="draft" <?php echo (($_POST['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>ड्राफ्ट (Draft)</option>
                <option value="pending_approval" <?php echo (($_POST['status'] ?? '') === 'pending_approval') ? 'selected' : ''; ?>>स्वीकृति हेतु लंबित (Pending Approval)</option>
                <option value="active" <?php echo (($_POST['status'] ?? '') === 'active') ? 'selected' : ''; ?>>सीधे सक्रिय करें (Direct Active)</option>
            </select>
        </div>

        <!-- Options -->
        <div class="col-12 mt-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="archive_old" name="archive_old" value="1" checked>
                <label class="form-check-label text-danger-custom fw-semibold" for="archive_old">
                    पुराने सक्रिय संस्करण (v<?php echo e($doc['version']); ?>) को स्वतः संग्रहित (Archive) चिह्नित करें।
                </label>
            </div>
        </div>
    </div>

    <!-- Buttons -->
    <div class="text-end mb-5">
        <a href="index.php" class="btn btn-outline-secondary px-4 me-2">रद्द करें</a>
        <button type="submit" class="btn btn-navy px-5 fw-semibold"><i class="bi bi-cloud-arrow-up-fill text-gold-custom me-2"></i>नया संस्करण सहेजें</button>
    </div>
</form>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
