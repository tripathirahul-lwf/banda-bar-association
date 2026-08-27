<?php
/**
 * Member ID Card Application Submission Form
 * District Bar Association, Banda
 */

$pageTitle = 'पहचान पत्र आवेदन (ID Card Application)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce member role
requireRole('member');

$user = currentUser();
$member_id = $user['member_id'] ?? 0;
$member = null;

$db = Database::getConnection();

if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load member profile for application: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य मास्टर रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

$app_type = isset($_GET['type']) ? trim($_GET['type']) : 'new';
if (!in_array($app_type, ['new', 'renewal', 'duplicate', 'lost', 'replacement'])) {
    $app_type = 'new';
}

$existing_card_id = isset($_GET['card_id']) ? intval($_GET['card_id']) : null;
$existing_card = null;

if ($db && $existing_card_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM id_cards WHERE id = ? AND member_id = ?");
        $stmt->execute([$existing_card_id, $member_id]);
        $existing_card = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed loading existing card details: " . $e->getMessage());
    }
}

// Check if they already have a pending application
$has_pending = false;
if ($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM id_card_applications WHERE member_id = ? AND status IN ('submitted', 'under_review')");
        $stmt->execute([$member_id]);
        if ($stmt->fetchColumn() > 0) {
            $has_pending = true;
        }
    } catch (PDOException $e) {
        error_log("Pending applications count failure: " . $e->getMessage());
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_pending) {
    // Verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("apply.php?type=$app_type");
    }

    $reason = trim($_POST['reason'] ?? '');
    $declaration = isset($_POST['declaration']) ? 1 : 0;
    
    $errors = [];
    if (!$declaration) {
        $errors[] = 'कृपया घोषणा पत्र स्वीकार करें।';
    }
    if (in_array($app_type, ['duplicate', 'lost', 'replacement']) && empty($reason)) {
        $errors[] = 'इस आवेदन प्रकार के लिए कारण/स्पष्टीकरण देना अनिवार्य है।';
    }

    // Capture file uploads (if member wants to update photo/sig for this card)
    $uploaded_photo = null;
    $uploaded_signature = null;
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (empty($errors)) {
        // Photo upload
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['photo_file'];
            $mime = mime_content_type($file_info['tmp_name']);
            if (!in_array($mime, $allowed_mimes)) {
                $errors[] = 'अमान्य फ़ोटो फ़ाइल प्रकार। केवल JPG/PNG स्वीकार्य हैं।';
            } elseif ($file_info['size'] > 2097152) {
                $errors[] = 'फ़ोटो फ़ाइल 2MB से अधिक बड़ी नहीं होनी चाहिए।';
            } else {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $uploaded_photo = 'photo_app_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = __DIR__ . '/../../uploads/photos/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                move_uploaded_file($file_info['tmp_name'], $target_dir . $uploaded_photo);
            }
        }

        // Signature upload
        if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['signature_file'];
            $mime = mime_content_type($file_info['tmp_name']);
            if (!in_array($mime, $allowed_mimes)) {
                $errors[] = 'अमान्य हस्ताक्षर फ़ाइल प्रकार।';
            } elseif ($file_info['size'] > 2097152) {
                $errors[] = 'हस्ताक्षर फ़ाइल 2MB से अधिक बड़ी नहीं होनी चाहिए।';
            } else {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $uploaded_signature = 'sig_app_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = __DIR__ . '/../../uploads/signatures/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                move_uploaded_file($file_info['tmp_name'], $target_dir . $uploaded_signature);
            }
        }
    }

    if (empty($errors)) {
        if ($db) {
            try {
                $db->beginTransaction();

                // Save application
                $stmt = $db->prepare("
                    INSERT INTO id_card_applications (member_id, existing_card_id, application_type, reason, photo_path, signature_path, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'submitted')
                ");
                $stmt->execute([
                    $member_id, 
                    $existing_card_id ?: null, 
                    $app_type, 
                    $reason ?: null, 
                    $uploaded_photo ?: null, 
                    $uploaded_signature ?: null
                ]);
                $new_app_id = $db->lastInsertId();

                // If application type is LOST or DUPLICATE/RENEWAL and card exists, mark the old card as cancelled immediately!
                if ($existing_card && in_array($app_type, ['lost', 'duplicate', 'renewal'])) {
                    $cancel_stmt = $db->prepare("UPDATE id_cards SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
                    $cancel_stmt->execute([$existing_card_id]);
                    
                    // Log old card cancellation in history
                    $hist = $db->prepare("
                        INSERT INTO id_card_history (id_card_id, member_id, action, old_status, new_status, remarks, performed_by) 
                        VALUES (?, ?, 'Cancelled', ?, 'cancelled', ?, ?)
                    ");
                    $hist->execute([
                        $existing_card_id, 
                        $member_id, 
                        $existing_card['status'], 
                        "कार्ड खो जाने/नया आवेदन के कारण स्वतः निरस्त।", 
                        $_SESSION['auth']['user_id']
                    ]);
                }

                $db->commit();
                
                setFlash('success', 'ID Card आवेदन सफलतापूर्वक जमा हुआ। बार कार्यालय द्वारा समीक्षा उपरांत इसे स्वीकृत किया जाएगा।');
                redirect('../id-card.php');
            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Failed to insert card application: " . $e->getMessage());
                setFlash('danger', 'डेटाबेस प्रविष्टि त्रुटि: ' . $e->getMessage());
            }
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}

$app_labels = [
    'new' => 'नवीन पहचान पत्र (New Digital ID Card)',
    'renewal' => 'आईडी कार्ड नवीनीकरण (ID Card Renewal)',
    'duplicate' => 'डुप्लीकेट आईडी कार्ड (Duplicate Card)',
    'lost' => 'खोया हुआ आईडी कार्ड रिपोर्ट (Lost Card Application)',
    'replacement' => 'आईडी कार्ड रिप्लेसमेंट (Replacement Card)'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-file-earmark-plus-fill me-2 text-gold-custom"></i><?php echo $app_labels[$app_type] ?? 'पहचान पत्र आवेदन'; ?></h4>
    <a href="../id-card.php" class="btn btn-outline-navy btn-sm">वापस जाएं</a>
</div>

<?php if ($has_pending): ?>
    <div class="alert alert-warning border-0 font-hindi shadow-xs p-4">
        <i class="bi bi-clock-history display-5 text-warning d-block mb-2"></i>
        <h5>आपका एक आवेदन पहले से ही प्रक्रिया में है!</h5>
        <p class="mb-0 text-muted small">प्रशासन द्वारा वर्तमान लंबित आवेदन का निस्तारण किए जाने के पश्चात ही आप पुनः आवेदन कर सकते हैं। स्थिति देखने के लिए <a href="history.php" class="fw-bold">इतिहास (History)</a> पर जाएं।</p>
    </div>
<?php else: ?>
    <!-- Form -->
    <form method="POST" action="apply.php?type=<?php echo e($app_type); ?>&card_id=<?php echo e($existing_card_id); ?>" enctype="multipart/form-data" class="font-hindi small">
        <?php csrfField(); ?>
        
        <div class="row g-4 mb-4">
            <!-- Left Side: Preloaded Credentials -->
            <div class="col-md-6">
                <div class="border rounded p-3 bg-light-custom h-100">
                    <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-person-lines-fill me-1"></i>सदस्य विवरण (Prefilled Official Records)</h6>
                    
                    <div class="row g-2 text-muted">
                        <div class="col-md-6">
                            <span>अधिवक्ता का नाम:</span>
                            <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo e($member['full_name']); ?></strong>
                        </div>
                        <div class="col-md-6">
                            <span>सदस्यता संख्या:</span>
                            <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo e($member['membership_no']); ?></strong>
                        </div>
                        <div class="col-md-6 mt-2">
                            <span>पंजीकरण संख्या:</span>
                            <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo e($member['enrollment_no']); ?></strong>
                        </div>
                        <div class="col-md-6 mt-2">
                            <span>रक्त समूह:</span>
                            <strong class="d-block text-navy-custom mt-0.5"><?php echo e($member['blood_group'] ?: 'N/A'); ?></strong>
                        </div>
                    </div>
                    
                    <!-- Exising Card info -->
                    <?php if ($existing_card): ?>
                        <div class="p-2 border rounded border-warning bg-white mt-3 text-warning-custom">
                            <span class="d-block fw-bold text-navy-custom" style="font-size: 0.7rem;"><i class="bi bi-shield-exclamation me-1"></i>सम्बद्ध पुराना कार्ड विवरण:</span>
                            <span style="font-size: 0.65rem;" class="english-text">Card No: <?php echo e($existing_card['card_number']); ?> | Expire: <?php echo date('d-m-Y', strtotime($existing_card['valid_until'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Reason and Upload Overrides -->
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-plus-circle-fill text-gold-dark me-2"></i>आवेदन विवरण (Application Details)</h6>
                    
                    <!-- Upload overides -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">नई फोटो अपलोड करें (Optional - If changing card photo)</label>
                        <input type="file" class="form-control form-control-sm" name="photo_file" accept=".jpg,.jpeg,.png,.webp">
                        <div class="form-text text-muted" style="font-size: 0.65rem;">खाली रखने पर आपके मास्टर प्रोफाइल की फ़ोटो का उपयोग किया जाएगा।</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">नया हस्ताक्षर अपलोड करें (Optional)</label>
                        <input type="file" class="form-control form-control-sm" name="signature_file" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <!-- Reason (Required for renewal/lost/duplicate) -->
                    <div class="mb-3">
                        <label for="reason" class="form-label text-secondary fw-semibold">आवेदन / खो जाने / नवीनीकरण का स्पष्ट कारण *</label>
                        <textarea class="form-control form-control-sm" id="reason" name="reason" rows="2" placeholder="e.g. मेरा पिछला कार्ड खो गया है / कार्ड की वैधता समाप्त हो गई है।" <?php echo ($app_type !== 'new') ? 'required' : ''; ?>></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Declaration -->
        <div class="border rounded p-3 mb-4 bg-light-custom">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="declaration" name="declaration" value="1" required>
                <label class="form-check-label fw-bold text-navy-custom" for="declaration" style="font-size: 0.72rem;">
                    मैं घोषणा करता हूँ कि उपर्युक्त समस्त सूचनाएं सत्य एवं शुद्ध हैं। कार्ड जारी होने के बाद यदि कोई सूचना असत्य पाई जाती है, तो बार संघ मेरा कार्ड निरस्त करने हेतु स्वतंत्र है।
                </label>
            </div>
        </div>

        <!-- Buttons -->
        <div class="text-end mb-5">
            <a href="../id-card.php" class="btn btn-outline-secondary px-4 py-2 me-2">रद्द करें</a>
            <button type="submit" class="btn btn-navy px-5 py-2 fw-semibold">
                <i class="bi bi-send-fill text-gold-custom me-2"></i> आवेदन प्रस्तुत करें (Submit Application)
            </button>
        </div>
    </form>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
