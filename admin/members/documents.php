<?php
/**
 * Member Documents Management & Verification Control
 * District Bar Association, Banda
 */

$pageTitle = 'दस्तावेज प्रबंधन (Document Manager)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce management permissions
requireRole(['admin', 'mahasachiv']);
requirePermission('members.manage');

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$db = Database::getConnection();
$member = null;

if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to fetch member for docs: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

$current_user_id = $_SESSION['auth']['user_id'];

// --- 1. HANDLE ACTION SUBMISSIONS (Verify, Reject, Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("documents.php?member_id=" . $member_id);
    }

    $action = trim($_POST['action'] ?? '');
    
    if ($action === 'upload_doc') {
        $doc_type = trim($_POST['document_type'] ?? '');
        $doc_name = trim($_POST['document_name'] ?? '');
        
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        
        if (empty($doc_type) || empty($doc_name)) {
            setFlash('danger', 'दस्तावेज प्रकार और नाम आवश्यक हैं।');
        } elseif (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'दस्तावेज फ़ाइल चुनना आवश्यक है।');
        } else {
            $file = $_FILES['doc_file'];
            $mime = mime_content_type($file['tmp_name']);
            
            if (!in_array($mime, $allowed_mimes)) {
                setFlash('danger', 'अमान्य फ़ाइल प्रकार। केवल PDF, JPG, PNG स्वीकार्य हैं।');
            } elseif ($file['size'] > 2097152) { // 2MB
                setFlash('danger', 'फ़ाइल का आकार 2MB से अधिक नहीं होना चाहिए।');
            } else {
                // Generate secure unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $secure_filename = 'doc_' . bin2hex(random_bytes(16)) . '.' . $ext;
                
                $target_dir = __DIR__ . '/../../uploads/documents/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                
                if (move_uploaded_file($file['tmp_name'], $target_dir . $secure_filename)) {
                    try {
                        $ins = $db->prepare("
                            INSERT INTO member_documents (member_id, document_type, document_name, file_path, verification_status, uploaded_by) 
                            VALUES (?, ?, ?, ?, 'pending', ?)
                        ");
                        $ins->execute([$member_id, $doc_type, $doc_name, $secure_filename, $current_user_id]);
                        
                        // Add history log
                        $hist = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Document Uploaded', ?, ?)");
                        $hist->execute([$member_id, "नया दस्तावेज अपलोड किया गया: " . $doc_name, $current_user_id]);
                        
                        setFlash('success', 'दस्तावेज सफलतापूर्वक अपलोड किया गया। स्थिति: लंबित (Pending Verification).');
                        redirect("documents.php?member_id=" . $member_id);
                    } catch (PDOException $e) {
                        error_log("Failed to save document record: " . $e->getMessage());
                        setFlash('danger', 'डेटाबेस विफलता। दस्तावेज रिकॉर्ड सहेजने में विफल।');
                    }
                } else {
                    setFlash('danger', 'फ़ाइल अपलोड करने में विफल। सर्वर अनुमतियाँ जांचें।');
                }
            }
        }

    } elseif ($action === 'verify_doc') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        
        try {
            $up = $db->prepare("UPDATE member_documents SET verification_status = 'verified', remarks = ? WHERE id = ? AND member_id = ?");
            $up->execute([$remarks, $doc_id, $member_id]);
            
            // Log history
            $hist = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Document Verified', ?, ?)");
            $hist->execute([$member_id, "दस्तावेज आईडी " . $doc_id . " सत्यापित किया गया। टिप्पणी: " . $remarks, $current_user_id]);
            
            setFlash('success', 'दस्तावेज सफलतापूर्वक सत्यापित किया गया।');
            redirect("documents.php?member_id=" . $member_id);
        } catch (PDOException $e) {
            error_log("Failed verifying document: " . $e->getMessage());
            setFlash('danger', 'सत्यापन अद्यतन करने में असमर्थ।');
        }

    } elseif ($action === 'reject_doc') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        
        if (empty($remarks)) {
            setFlash('danger', 'अस्वीकार करने हेतु कारण/टिप्पणी लिखना अनिवार्य है।');
            redirect("documents.php?member_id=" . $member_id);
        }
        
        try {
            $up = $db->prepare("UPDATE member_documents SET verification_status = 'rejected', remarks = ? WHERE id = ? AND member_id = ?");
            $up->execute([$remarks, $doc_id, $member_id]);
            
            // Log history
            $hist = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Document Rejected', ?, ?)");
            $hist->execute([$member_id, "दस्तावेज आईडी " . $doc_id . " अस्वीकार किया गया। कारण: " . $remarks, $current_user_id]);
            
            setFlash('success', 'दस्तावेज अस्वीकृत कर दिया गया है।');
            redirect("documents.php?member_id=" . $member_id);
        } catch (PDOException $e) {
            error_log("Failed rejecting document: " . $e->getMessage());
            setFlash('danger', 'दस्तावेज स्थिति अद्यतन करने में असमर्थ।');
        }
    }
}

// Fetch member documents
$docs = [];
if ($db) {
    try {
        $doc_stmt = $db->prepare("SELECT * FROM member_documents WHERE member_id = ? ORDER BY created_at DESC");
        $doc_stmt->execute([$member_id]);
        $docs = $doc_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch documents: " . $e->getMessage());
    }
}

// Document Types Hindi labels mapping
$doc_labels = [
    'Enrollment Certificate' => 'नामांकन प्रमाण पत्र (Enrollment Cert)',
    'Certificate of Practice' => 'विधिक अभ्यास प्रमाण पत्र (COP)',
    'Membership Form' => 'बार सदस्यता आवेदन पत्र',
    'Photo ID' => 'पहचान पत्र (Aadhar/Voter)',
    'Address Proof' => 'निवास प्रमाण पत्र',
    'Other' => 'अन्य विलेख'
];
?>

<div class="mb-3 font-hindi">
    <a href="view.php?id=<?php echo e($member_id); ?>&tab=documents" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>सदस्य प्रोफाइल पर वापस जाएं</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">दस्तावेज अपलोड एवं सत्यापन (Member Document Center)</h4>
    <span class="badge bg-gold-custom text-navy-custom px-3 py-1 fw-bold"><?php echo e($member['full_name']); ?></span>
</div>

<div class="row g-4 font-hindi small">
    <!-- Left Column: Upload Form -->
    <div class="col-md-5">
        <div class="border rounded p-3 bg-light-custom h-100">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-file-earmark-arrow-up-fill text-gold-dark me-2"></i>नया दस्तावेज अपलोड करें (Upload New Document)</h6>
            
            <form method="POST" action="documents.php?member_id=<?php echo e($member_id); ?>" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="upload_doc">
                
                <div class="mb-3">
                    <label for="document_type" class="form-label text-secondary fw-semibold">दस्तावेज प्रकार *</label>
                    <select class="form-select form-select-sm" id="document_type" name="document_type" required>
                        <option value="">चुनें...</option>
                        <?php foreach ($doc_labels as $key => $label): ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="document_name" class="form-label text-secondary fw-semibold">दस्तावेज का संक्षिप्त नाम *</label>
                    <input type="text" class="form-control form-control-sm english-text" id="document_name" name="document_name" required placeholder="e.g. Aadhar Card Front, COP Cert 2026">
                </div>

                <div class="mb-3">
                    <label for="doc_file" class="form-label text-secondary fw-semibold">फाइल चुनें (Max 2MB, PDF/JPG/PNG) *</label>
                    <input type="file" class="form-control form-control-sm" id="doc_file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-sm btn-navy w-100 py-2 fw-semibold">
                        <i class="bi bi-cloud-upload-fill text-gold-custom me-2"></i> अपलोड करें (Upload Doc)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Document List & Action Panel -->
    <div class="col-md-7">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-shield-check text-gold-dark me-2"></i>अपलोड किए गए दस्तावेज एवं सत्यापन</h6>
            
            <?php if (empty($docs)): ?>
                <div class="text-center py-5 text-muted">इस सदस्य के पास कोई दस्तावेज नहीं है।</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($docs as $d): ?>
                        <div class="border rounded p-3 bg-light-custom position-relative">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong class="d-block text-navy-custom mb-0.5"><?php echo e($doc_labels[$d['document_type']] ?? $d['document_type']); ?></strong>
                                    <span class="text-muted d-block font-size-xs english-text"><?php echo e($d['document_name']); ?></span>
                                    <span class="text-muted d-block font-size-xs font-hindi">अपलोड: <?php echo date('d-m-Y H:i', strtotime($d['created_at'])); ?></span>
                                </div>
                                <div class="text-end">
                                    <?php 
                                    $st = $d['verification_status'];
                                    if ($st === 'verified') echo '<span class="badge bg-success font-size-xs px-2 py-0.5">सत्यापित</span>';
                                    elseif ($st === 'rejected') echo '<span class="badge bg-danger font-size-xs px-2 py-0.5">अस्वीकृत</span>';
                                    else echo '<span class="badge bg-warning text-dark font-size-xs px-2 py-0.5">सत्यापन लंबित</span>';
                                    ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($d['remarks'])): ?>
                                <div class="p-2 bg-white rounded border small mt-1 mb-2 text-danger-custom" style="font-size: 0.75rem;">
                                    <strong>टिप्पणी:</strong> <?php echo e($d['remarks']); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Actions bar -->
                            <div class="d-flex gap-2 justify-content-between mt-2 pt-2 border-top border-light">
                                <a href="download-doc.php?id=<?php echo e($d['id']); ?>" class="btn btn-xs btn-outline-navy py-1" target="_blank">
                                    <i class="bi bi-download me-1"></i>दस्तावेज डाउनलोड (View/Download)
                                </a>
                                
                                <div class="d-flex gap-1">
                                    <!-- Verify/Reject Triggers -->
                                    <button class="btn btn-xs btn-success py-1" onclick="triggerVerifyModal(<?php echo $d['id']; ?>)">
                                        सत्यापित (Verify)
                                    </button>
                                    <button class="btn btn-xs btn-danger py-1" onclick="triggerRejectModal(<?php echo $d['id']; ?>)">
                                        अस्वीकार (Reject)
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Dialogs for Verification & Rejection Remarks -->
<div class="modal fade font-hindi small" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="modalForm" method="POST" action="documents.php?member_id=<?php echo e($member_id); ?>">
            <?php csrfField(); ?>
            <input type="hidden" name="action" id="modalAction" value="">
            <input type="hidden" name="doc_id" id="modalDocId" value="">
            
            <div class="modal-content">
                <div class="modal-header bg-navy-custom text-white">
                    <h6 class="modal-title fw-bold" id="modalTitle">दस्तावेज सत्यापन</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modalRemarks" class="form-label text-secondary fw-semibold" id="remarksLabel">टिप्पणी / रिमार्क्स</label>
                        <textarea class="form-control" name="remarks" id="modalRemarks" rows="3" placeholder="टिप्पणी दर्ज करें..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                    <button type="submit" class="btn btn-xs btn-navy" id="modalSubmitBtn">अपडेट करें</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function triggerVerifyModal(docId) {
    document.getElementById('modalAction').value = 'verify_doc';
    document.getElementById('modalDocId').value = docId;
    document.getElementById('modalTitle').innerText = 'दस्तावेज सत्यापित करें (Verify Document)';
    document.getElementById('remarksLabel').innerText = 'सत्यापन टिप्पणी (Optional Verification Remarks)';
    document.getElementById('modalRemarks').required = false;
    document.getElementById('modalSubmitBtn').className = 'btn btn-xs btn-success';
    document.getElementById('modalSubmitBtn').innerText = 'सत्यापित घोषित करें (Verify)';
    
    var myModal = new bootstrap.Modal(document.getElementById('actionModal'));
    myModal.show();
}

function triggerRejectModal(docId) {
    document.getElementById('modalAction').value = 'reject_doc';
    document.getElementById('modalDocId').value = docId;
    document.getElementById('modalTitle').innerText = 'दस्तावेज अस्वीकार करें (Reject Document)';
    document.getElementById('remarksLabel').innerText = 'अस्वीकार करने का स्पष्ट कारण (Required Remarks) *';
    document.getElementById('modalRemarks').required = true;
    document.getElementById('modalSubmitBtn').className = 'btn btn-xs btn-danger';
    document.getElementById('modalSubmitBtn').innerText = 'अस्वीकृत घोषित करें (Reject)';
    
    var myModal = new bootstrap.Modal(document.getElementById('actionModal'));
    myModal.show();
}
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
