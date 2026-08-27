<?php
/**
 * Edit Notice or Condolence Notification Form
 * District Bar Association, Banda
 */

$pageTitle = 'अधिसूचना संपादित करें (Edit Notice)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$notice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$notice = null;
$condolence = null;
$errors = [];
$members_list = [];

$db = Database::getConnection();

if ($db && $notice_id > 0) {
    try {
        // Fetch notice details
        $stmt = $db->prepare("SELECT * FROM notices WHERE id = ?");
        $stmt->execute([$notice_id]);
        $notice = $stmt->fetch();

        if ($notice) {
            // Fetch condolence specifics
            $stmt = $db->prepare("SELECT * FROM condolence_notices WHERE notice_id = ?");
            $stmt->execute([$notice_id]);
            $condolence = $stmt->fetch();

            // Fetch active members list
            $stmt = $db->query("SELECT id, full_name, membership_no, enrollment_no FROM members WHERE membership_status = 'active' ORDER BY full_name ASC");
            $members_list = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Failed to load notice details: " . $e->getMessage());
    }
}

if (!$notice) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: अधिसूचना रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("edit.php?id=$notice_id");
    }

    $title = trim($_POST['title'] ?? '');
    $title_hindi = trim($_POST['title_hindi'] ?? '');
    $priority = trim($_POST['priority'] ?? 'normal');
    $visibility = trim($_POST['visibility'] ?? 'public');
    
    $short_desc = trim($_POST['short_description'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    $pub_date = trim($_POST['publish_date'] ?? date('Y-m-d'));
    $pub_time = trim($_POST['publish_time'] ?? date('H:i'));
    $exp_date = trim($_POST['expiry_date'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');

    // Condolence specifics
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $advocate_name = trim($_POST['advocate_name'] ?? '');
    $membership_no = trim($_POST['membership_no'] ?? '');
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $date_of_death = trim($_POST['date_of_death'] ?? '');
    $condolence_message = trim($_POST['condolence_message'] ?? '');
    $prayer_meeting = trim($_POST['prayer_meeting'] ?? '');
    $family_message = trim($_POST['family_message'] ?? '');
    $mark_deceased = isset($_POST['mark_deceased']) ? 1 : 0;

    // Validation
    if (empty($title)) $errors[] = 'अधिसूचना शीर्षक आवश्यक है।';
    if (empty($description)) $errors[] = 'सूचना का पूर्ण विवरण आवश्यक है।';
    if ($notice['category'] === 'Condolence Notice' && empty($advocate_name)) {
        $errors[] = 'दिवंगत अधिवक्ता का नाम आवश्यक है।';
    }

    // Handle attachment replacement
    $random_file_name = $notice['attachment_path'];
    $orig_file_name = $notice['attachment_name'];
    $file_type = $notice['attachment_type'];

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pdf_file'];
        $max_size = defined('NOTICE_MAX_FILE_SIZE') ? NOTICE_MAX_FILE_SIZE : 5242880;
        
        $mime = mime_content_type($file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
            $errors[] = 'अमान्य फ़ाइल प्रकार।';
        }
        if ($file['size'] > $max_size) {
            $errors[] = 'फ़ाइल का आकार सीमा 5MB से अधिक नहीं होना चाहिए।';
        }

        if (empty($errors)) {
            $random_file_name = 'notice_' . bin2hex(random_bytes(16)) . '.' . $ext;
            if ($visibility === 'members_only') {
                $dest_dir = __DIR__ . '/../../storage/notices/';
            } else {
                $dest_dir = __DIR__ . '/../../uploads/notices/';
            }

            if (move_uploaded_file($file['tmp_name'], $dest_dir . $random_file_name)) {
                $orig_file_name = $file['name'];
                $file_type = $ext;
            } else {
                $errors[] = 'सर्वर पर फ़ाइल सहेजने में विफल।';
            }
        }
    }

    // Condolence Photo Upload replacement
    $photo_name = $condolence['advocate_photo'] ?? 'default_advocate.png';
    if ($notice['category'] === 'Condolence Notice' && isset($_FILES['deceased_photo']) && $_FILES['deceased_photo']['error'] === UPLOAD_ERR_OK) {
        $photo_file = $_FILES['deceased_photo'];
        $p_ext = strtolower(pathinfo($photo_file['name'], PATHINFO_EXTENSION));
        if (in_array($p_ext, ['jpg', 'jpeg', 'png'])) {
            $photo_name = 'condolence_' . bin2hex(random_bytes(16)) . '.' . $p_ext;
            $dest_photo_dir = __DIR__ . '/../../uploads/photos/';
            move_uploaded_file($photo_file['tmp_name'], $dest_photo_dir . $photo_name);
        }
    }

    if (empty($errors) && $db) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];
            $publish_datetime = $pub_date . ' ' . $pub_time . ':00';

            // Update notices table
            $up = $db->prepare("
                UPDATE notices 
                SET title = ?, title_hindi = ?, short_description = ?, description = ?, priority = ?, visibility = ?, status = ?, publish_at = ?, expire_at = ?, attachment_path = ?, attachment_name = ?, attachment_type = ? 
                WHERE id = ?
            ");
            $up->execute([
                $title,
                $title_hindi ?: null,
                $short_desc ?: null,
                $description,
                $priority,
                $visibility,
                $status,
                $publish_datetime,
                $exp_date ?: null,
                $random_file_name,
                $orig_file_name,
                $file_type,
                $notice_id
            ]);

            // Update condolence table if applicable
            if ($notice['category'] === 'Condolence Notice' && $condolence) {
                $cond_up = $db->prepare("
                    UPDATE condolence_notices 
                    SET member_id = ?, advocate_name = ?, advocate_photo = ?, membership_no = ?, enrollment_no = ?, date_of_death = ?, condolence_message = ?, prayer_meeting_details = ?, family_message = ? 
                    WHERE id = ?
                ");
                $cond_up->execute([
                    $member_id ?: null,
                    $advocate_name,
                    $photo_name,
                    $membership_no ?: null,
                    $enrollment_no ?: null,
                    $date_of_death ?: null,
                    $condolence_message ?: $description,
                    $prayer_meeting ?: null,
                    $family_message ?: null,
                    $condolence['id']
                ]);

                // Option: Mark member deceased
                if ($mark_deceased && $member_id > 0) {
                    $m_up = $db->prepare("UPDATE members SET membership_status = 'deceased', status_reason = ? WHERE id = ?");
                    $m_up->execute(["शोक संदेश अधिसूचना द्वारा दिवंगत घोषित (संशोधन)।", $member_id]);

                    $m_hist = $db->prepare("
                        INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                        VALUES (?, 'Status Change', 'membership_status', 'active', 'deceased', ?, ?)
                    ");
                    $m_hist->execute([$member_id, "Marked Deceased in Master via updated Notice ID $notice_id", $user_id]);
                }
            }

            // Log notice history
            $hist = $db->prepare("
                INSERT INTO notice_history (notice_id, action, old_status, new_status, remarks, performed_by) 
                VALUES (?, 'Edited', ?, ?, 'अधिसूचना के मेटाडेटा और विलेख में संशोधन किया गया।', ?)
            ");
            $hist->execute([$notice_id, $notice['status'], $status, $user_id]);

            $db->commit();
            setFlash('success', 'अधिसूचना विवरण सफलतापूर्वक अद्यतन हुआ।');
            redirect('index.php');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed updating notice detail page: " . $e->getMessage());
            $errors[] = 'डेटाबेस विफलता: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="fa-solid fa-pen-to-square text-gold-dark me-2"></i>अधिसूचना सुधार (Edit Notice - <?php echo e($notice['title']); ?>)</h4>
    <a href="index.php" class="btn btn-sm btn-outline-secondary fw-semibold"><i class="fa-solid fa-arrow-left me-1"></i>वापस जाएं</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small">
        <?php echo implode('<br>', $errors); ?>
    </div>
<?php endif; ?>

<form method="POST" action="edit.php?id=<?php echo $notice['id']; ?>" enctype="multipart/form-data" class="font-hindi small">
    <?php csrfField(); ?>
    
    <div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px; overflow: hidden;">
        <div class="card-header bg-transparent border-bottom py-3">
            <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-circle-info text-gold-dark me-2"></i>सामान्य विवरण (Basic Information)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <!-- Notice Number -->
                <div class="col-md-4">
                    <label class="form-label text-secondary fw-semibold small">अधिसूचना संख्या (Notice No)</label>
                    <input type="text" class="form-control form-control-sm english-text bg-light text-muted border-light-subtle" disabled style="background-color: #f8fafc;" value="<?php echo e($notice['notice_no'] ?: 'Auto-Generate'); ?>">
                </div>

                <!-- Title & Hindi Title -->
                <div class="col-md-4">
                    <label for="title" class="form-label text-secondary fw-semibold small">अधिसूचना का शीर्षक (अंग्रेजी) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm english-text" id="title" name="title" required value="<?php echo e($notice['title']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="title_hindi" class="form-label text-secondary fw-semibold small">अधिसूचना का शीर्षक (हिंदी)</label>
                    <input type="text" class="form-control form-control-sm" id="title_hindi" name="title_hindi" value="<?php echo e($notice['title_hindi']); ?>">
                </div>

                <!-- Category & Priority -->
                <div class="col-md-4">
                    <label class="form-label text-secondary fw-semibold small">सूचना श्रेणी (Read-Only)</label>
                    <input type="text" class="form-control form-control-sm bg-light text-muted border-light-subtle" disabled style="background-color: #f8fafc;" value="<?php echo e($notice['category']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="priority" class="form-label text-secondary fw-semibold small">प्राथमिकता <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="priority" name="priority">
                        <option value="normal" <?php echo ($notice['priority'] === 'normal') ? 'selected' : ''; ?>>सामान्य (Normal)</option>
                        <option value="important" <?php echo ($notice['priority'] === 'important') ? 'selected' : ''; ?>>महत्वपूर्ण (Important)</option>
                        <option value="urgent" <?php echo ($notice['priority'] === 'urgent') ? 'selected' : ''; ?>>अति आवश्यक (Urgent)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="visibility" class="form-label text-secondary fw-semibold small">दृश्यता <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="visibility" name="visibility">
                        <option value="public" <?php echo ($notice['visibility'] === 'public') ? 'selected' : ''; ?>>सार्वजनिक (Public)</option>
                        <option value="members_only" <?php echo ($notice['visibility'] === 'members_only') ? 'selected' : ''; ?>>केवल सदस्य (Members Only)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- CONDOLENCE SECTION (reveals if category is condolence notice) -->
    <?php if ($notice['category'] === 'Condolence Notice' && $condolence): ?>
        <div class="card border-0 border-top border-4 border-danger shadow-sm mb-4 bg-white" style="border-radius: 8px; overflow: hidden;" id="condolence-section">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-square-poll-horizontal text-danger me-2"></i>शोक संदेश विशेष विवरण (Condolence Details)</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="existing_member" class="form-label text-secondary fw-semibold small">पंजीकृत सदस्य सूची से बदलें (Optional)</label>
                        <select class="form-select form-select-sm" id="existing_member" onchange="autoFillMemberDetails(this)">
                            <option value="">सदस्य चुनें...</option>
                            <?php foreach ($members_list as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo ($condolence['member_id'] == $m['id']) ? 'selected' : ''; ?> data-name="<?php echo e($m['full_name']); ?>" data-membership="<?php echo e($m['membership_no']); ?>" data-enrollment="<?php echo e($m['enrollment_no']); ?>">
                                    <?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="member_id" id="hidden_member_id" value="<?php echo $condolence['member_id'] ?: 0; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="advocate_name" class="form-label text-secondary fw-semibold small">दिवंगत अधिवक्ता का नाम <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="advocate_name" name="advocate_name" value="<?php echo e($condolence['advocate_name']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="membership_no" class="form-label text-secondary fw-semibold small">सदस्यता संख्या</label>
                        <input type="text" class="form-control form-control-sm english-text" id="membership_no" name="membership_no" value="<?php echo e($condolence['membership_no']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="enrollment_no" class="form-label text-secondary fw-semibold small">बार काउंसिल पंजीकरण संख्या</label>
                        <input type="text" class="form-control form-control-sm english-text" id="enrollment_no" name="enrollment_no" value="<?php echo e($condolence['enrollment_no']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="date_of_death" class="form-label text-secondary fw-semibold small">निधन तिथि</label>
                        <input type="date" class="form-control form-control-sm english-text" id="date_of_death" name="date_of_death" value="<?php echo e($condolence['date_of_death']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="deceased_photo" class="form-label text-secondary fw-semibold small">दिवंगत सदस्य का फोटो बदलें</label>
                        <input type="file" class="form-control form-control-sm" id="deceased_photo" name="deceased_photo" accept="image/*">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="prayer_meeting" class="form-label text-secondary fw-semibold small">प्रार्थना सभा विवरण</label>
                        <textarea class="form-control form-control-sm" id="prayer_meeting" name="prayer_meeting" rows="2"><?php echo e($condolence['prayer_meeting_details']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="family_message" class="form-label text-secondary fw-semibold small">परिवार का संदेश</label>
                        <textarea class="form-control form-control-sm" id="family_message" name="family_message" rows="2"><?php echo e($condolence['family_message']); ?></textarea>
                    </div>
                    <div class="col-12 mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="mark_deceased" name="mark_deceased" value="1">
                            <label class="form-check-label text-danger fw-semibold" for="mark_deceased" style="font-size: 0.8rem;">
                                इस सदस्य को मास्टर डेटाबेस में "दिवंगत (Deceased)" चिह्नित करें।
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CONTENT SECTION -->
    <div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px; overflow: hidden;">
        <div class="card-header bg-transparent border-bottom py-3">
            <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-file-lines text-gold-dark me-2"></i>अधिसूचना सामग्री (Content)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label for="short_description" class="form-label text-secondary fw-semibold small">संक्षिप्त विवरण (Short Description)</label>
                    <input type="text" class="form-control form-control-sm" id="short_description" name="short_description" value="<?php echo e($notice['short_description']); ?>">
                </div>
                
                <div class="col-12">
                    <label for="description" class="form-label text-secondary fw-semibold small">पूर्ण अधिसूचना विवरण <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="description" name="description" rows="5" required><?php echo e($notice['description']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- PUBLICATION & FILES -->
    <div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px; overflow: hidden;">
        <div class="card-header bg-transparent border-bottom py-3">
            <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-calendar-day text-gold-dark me-2"></i>प्रकाशन तिथि एवं फ़ाइल संलग्नक (Scheduling & Files)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="publish_date" class="form-label text-secondary fw-semibold small">प्रकाशन दिनांक से</label>
                    <input type="date" class="form-control form-control-sm english-text" id="publish_date" name="publish_date" value="<?php echo date('Y-m-d', strtotime($notice['publish_at'] ?: $notice['created_at'])); ?>">
                </div>
                <div class="col-md-3">
                    <label for="publish_time" class="form-label text-secondary fw-semibold small">प्रकाशन समय से</label>
                    <input type="time" class="form-control form-control-sm english-text" id="publish_time" name="publish_time" value="<?php echo date('H:i', strtotime($notice['publish_at'] ?: $notice['created_at'])); ?>">
                </div>
                <div class="col-md-3">
                    <label for="expiry_date" class="form-label text-secondary fw-semibold small">अंतिम तिथि</label>
                    <input type="date" class="form-control form-control-sm english-text" id="expiry_date" name="expiry_date" value="<?php echo $notice['expire_at'] ? date('Y-m-d', strtotime($notice['expire_at'])) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="pdf_file" class="form-label text-secondary fw-semibold small">दस्तावेज संलग्नक बदलें</label>
                    <input type="file" class="form-control form-control-sm" id="pdf_file" name="pdf_file" accept=".pdf, .jpg, .jpeg, .png">
                </div>
            </div>
        </div>
    </div>

    <!-- WORKFLOW OPTIONS -->
    <div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px; overflow: hidden;">
        <div class="card-header bg-transparent border-bottom py-3">
            <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-sliders text-gold-dark me-2"></i>कार्यप्रवाह विकल्प (Workflow Options)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <label for="status" class="form-label text-secondary fw-semibold small">कार्यप्रवाह स्थिति बदलें</label>
                    <select class="form-select form-select-sm" id="status" name="status">
                        <option value="draft" <?php echo ($notice['status'] === 'draft') ? 'selected' : ''; ?>>ड्राफ्ट सुरक्षित करें (Draft)</option>
                        <option value="pending_approval" <?php echo ($notice['status'] === 'pending_approval') ? 'selected' : ''; ?>>अनुमोदन हेतु भेजें (Pending Approval)</option>
                        <option value="published" <?php echo ($notice['status'] === 'published') ? 'selected' : ''; ?>>सीधे प्रकाशित करें (Publish Now)</option>
                    </select>
                </div>
                <div class="col-md-6 text-end pt-3">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary px-4 me-2 py-2">रद्द करें</a>
                    <button type="submit" class="btn btn-sm btn-primary px-5 fw-semibold py-2"><i class="fa-solid fa-floppy-disk me-2"></i>विवरण अद्यतन करें</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function autoFillMemberDetails(selectEl) {
    var option = selectEl.options[selectEl.selectedIndex];
    if (option.value !== '') {
        document.getElementById('hidden_member_id').value = option.value;
        document.getElementById('advocate_name').value = option.getAttribute('data-name');
        document.getElementById('membership_no').value = option.getAttribute('data-membership');
        document.getElementById('enrollment_no').value = option.getAttribute('data-enrollment');
    } else {
        document.getElementById('hidden_member_id').value = "0";
        document.getElementById('advocate_name').value = "";
        document.getElementById('membership_no').value = "";
        document.getElementById('enrollment_no').value = "";
    }
}
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
