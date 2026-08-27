<?php
/**
 * Annual Accounts and Audit Reports Document Publishing Manager
 * District Bar Association, Banda
 */

$pageTitle = 'वित्तीय दस्तावेज प्रबंधन (Financial Documents)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

$errors = [];
$success = false;

// Fetch active financial year
$selected_fy = isset($_GET['fy_id']) ? intval($_GET['fy_id']) : 0;
$financial_years = [];
$active_fy = null;

if ($db) {
    try {
        $financial_years = $db->query("SELECT id, name, status FROM financial_years ORDER BY start_date DESC")->fetchAll();
        foreach ($financial_years as $fy) {
            if ($fy['status'] === 'active') {
                $active_fy = $fy;
            }
        }
        if ($selected_fy <= 0 && $active_fy) {
            $selected_fy = $active_fy['id'];
        }
    } catch (PDOException $e) {
        error_log("Failed loading FY list for docs: " . $e->getMessage());
    }
}

// Handle document uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'सुरक्षा टोकन अमान्य है।';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $document_type = trim($_POST['document_type'] ?? '');
    $visibility = trim($_POST['visibility'] ?? 'public');
    $status = trim($_POST['status'] ?? 'draft');

    if (empty($title)) {
        $errors[] = 'दस्तावेज का शीर्षक लिखना आवश्यक है।';
    }
    if (empty($document_type)) {
        $errors[] = 'दस्तावेज का प्रकार चुनना आवश्यक है।';
    }

    $filename = null;
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pdf_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['type'] !== 'application/pdf' || $ext !== 'pdf') {
            $errors[] = 'केवल PDF दस्तावेज ही अपलोड किए जा सकते हैं।';
        }
        if ($file['size'] > FINANCIAL_ATTACHMENT_MAX_SIZE) {
            $errors[] = 'दस्तावेज का आकार 5MB से कम होना चाहिए।';
        }

        if (empty($errors)) {
            $filename = 'doc_' . uniqid() . '_' . time() . '.pdf';
            $upload_path = __DIR__ . '/../../storage/financials/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                $errors[] = 'दस्तावेज अपलोड विफल।';
                $filename = null;
            }
        }
    } else {
        $errors[] = 'कृपया PDF दस्तावेज फाइल का चयन करें।';
    }

    if (empty($errors) && $db) {
        try {
            $user_id = $_SESSION['auth']['user_id'];
            $pub_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;

            $stmt = $db->prepare("
                INSERT INTO association_financial_documents (
                    financial_year_id, document_type, title, description, file_path, 
                    visibility, status, uploaded_by, published_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $selected_fy,
                $document_type,
                $title,
                $description ?: null,
                $filename,
                $visibility,
                $status,
                $user_id,
                $pub_at
            ]);

            // Auditing Log
            $audit = $db->prepare("
                INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                VALUES (0, 'Financial Document Uploaded', 'status', NULL, ?, ?, ?)
            ");
            $audit->execute([$status, "Financial Document $title ($document_type) uploaded", $user_id]);

            setFlash('success', 'वित्तीय दस्तावेज सफलतापूर्वक अपलोड कर दिया गया है।');
            redirect("documents.php?fy_id=$selected_fy");

        } catch (PDOException $e) {
            error_log("Failed to insert document metadata: " . $e->getMessage());
            $errors[] = 'डेटाबेस त्रुटि: विवरण सहेजने में विफल।';
        }
    }
}

// Fetch existing documents for selected FY
$documents = [];
if ($db && $selected_fy > 0) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, u.username as uploader_name 
            FROM association_financial_documents d
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.financial_year_id = ?
            ORDER BY d.id DESC
        ");
        $stmt->execute([$selected_fy]);
        $documents = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading documents: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">वित्तीय दस्तावेज प्रकाशन (Financial Reports Manager)</h4>
    <a href="index.php" class="btn btn-xs btn-navy fw-semibold">वापस कोष मुख्य पृष्ठ</a>
</div>

<!-- Selector bar -->
<div class="card p-3 mb-4 border-0 shadow-sm font-hindi small">
    <form method="GET" action="documents.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <label class="input-group-text bg-navy-custom text-white" for="fy_select">वित्तीय वर्ष चुनें (FY):</label>
                <select class="form-select" id="fy_select" name="fy_id" onchange="this.form.submit()">
                    <?php foreach ($financial_years as $fy): ?>
                        <option value="<?php echo $fy['id']; ?>" <?php echo ($selected_fy == $fy['id']) ? 'selected' : ''; ?>>
                            <?php echo e($fy['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small pb-0">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4 font-hindi small">
    <!-- Upload Form -->
    <div class="col-md-5 col-12">
        <div class="card p-3 border-0 bg-white shadow-sm mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-cloud-upload-fill text-gold-dark me-2"></i>नया दस्तावेज अपलोड (Upload Report)</h6>
            
            <form method="POST" action="documents.php?fy_id=<?php echo $selected_fy; ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="mb-3">
                    <label class="form-label text-secondary fw-semibold">दस्तावेज प्रकार (Doc Type) <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="document_type" required>
                        <option value="">चयन करें...</option>
                        <option value="Annual Account">Annual Account (वार्षिक लेखा-जोखा)</option>
                        <option value="Income & Expenditure Statement">Income & Expenditure Statement</option>
                        <option value="Balance Summary">Balance Summary (तुलन-पत्र विवरण)</option>
                        <option value="Audit Report">Audit Report (ऑडिट रिपोर्ट)</option>
                        <option value="Other">Other (अन्य विवरण)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary fw-semibold">शीर्षक (Title) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="title" placeholder="उदा. वार्षिक अंकेक्षण रिपोर्ट वर्ष २०२५-२६" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary fw-semibold">संक्षिप्त विवरण (Description)</label>
                    <textarea class="form-control form-control-sm" name="description" rows="2" placeholder="रिपोर्ट की मुख्य बातें..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary fw-semibold">फाइल अपलोड (Select PDF File) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control form-control-sm" name="pdf_file" accept=".pdf" required>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <label class="form-label text-secondary fw-semibold">दृश्यता (Visibility)</label>
                        <select class="form-select form-select-sm" name="visibility">
                            <option value="public">Public (सार्वजनिक)</option>
                            <option value="members_only">Members Only (केवल सदस्य)</option>
                            <option value="private">Private (गोपनीय)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-secondary fw-semibold">स्थिति (Status)</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="draft">Draft (प्रारूप)</option>
                            <option value="published">Published (प्रकाशित)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="action_upload" class="btn btn-navy w-100 fw-semibold btn-sm">दस्तावेज अपलोड करें</button>
            </form>
        </div>
    </div>

    <!-- Documents List -->
    <div class="col-md-7 col-12">
        <div class="card p-3 border-0 bg-white shadow-sm mb-4">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-pdf-fill text-gold-dark me-2"></i>अपलोड किए गए दस्तावेज़ (Uploaded Documents List)</h6>
            
            <?php if (empty($documents)): ?>
                <p class="text-muted text-center py-4 mb-0">इस वित्तीय वर्ष में अभी कोई दस्तावेज उपलब्ध नहीं है।</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-muted">
                        <thead class="table-light">
                            <tr>
                                <th>शीर्षक / विवरण</th>
                                <th>प्रकार</th>
                                <th>दृश्यता</th>
                                <th>स्थिति</th>
                                <th class="text-end">फाइल</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <strong class="text-navy-custom d-block"><?php echo e($doc['title']); ?></strong>
                                        <span class="text-muted font-size-xs">अपलोडर: <?php echo e($doc['uploader_name']); ?></span>
                                    </td>
                                    <td><?php echo e($doc['document_type']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-navy-custom border px-2 py-0.5">
                                            <?php echo e($doc['visibility']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($doc['status'] === 'published') ? 'bg-success' : 'bg-secondary'; ?> px-2 py-0.5">
                                            <?php echo e($doc['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <!-- In Phase 7 we will write a generic file downloader, or since they are in secure directory we'll link them to public downloader or direct viewer if public -->
                                        <a href="../../uploads/financials/<?php echo e($doc['file_path']); ?>" target="_blank" class="btn btn-xs btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
