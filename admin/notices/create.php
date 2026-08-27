<?php
/**
 * Create New Notice or Condolence Notification Form
 * District Bar Association, Banda
 */

$pageTitle = 'नई सूचना निर्मित करें (Create Notice)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce Admin / Mahasachiv Role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$errors = [];
$members_list = [];

if ($db) {
    try {
        $stmt = $db->query("SELECT id, full_name, membership_no, enrollment_no FROM members WHERE membership_status = 'active' ORDER BY full_name ASC");
        $members_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading active members list: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('create.php');
    }

    $auto_notice_no = isset($_POST['auto_notice_no']) ? 1 : 0;
    $manual_notice_no = trim($_POST['manual_notice_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $title_hindi = trim($_POST['title_hindi'] ?? '');
    $category = trim($_POST['category'] ?? '');
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
    if (empty($title)) $errors[] = 'अधिसूचना शीर्षक (Title) आवश्यक है।';
    if (empty($category)) $errors[] = 'अधिसूचना श्रेणी (Category) आवश्यक है।';
    if (empty($description)) $errors[] = 'सूचना का पूर्ण विवरण (Description) आवश्यक है।';
    
    if ($category === 'Condolence Notice' && empty($advocate_name)) {
        $errors[] = 'शोक संदेश हेतु दिवंगत अधिवक्ता का नाम आवश्यक है।';
    }

    // Slug generation
    $slug_base = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $title));
    $slug = trim(preg_replace('/-+/', '-', $slug_base), '-');
    if (empty($slug)) {
        $slug = 'notice-' . time();
    }

    // Check slug uniqueness
    if ($db) {
        $slug_stmt = $db->prepare("SELECT COUNT(*) FROM notices WHERE slug = ?");
        $slug_stmt->execute([$slug]);
        if ($slug_stmt->fetchColumn() > 0) {
            $slug .= '-' . rand(100, 999);
        }
    }

    // Notice number resolution
    $notice_no = null;
    if ($auto_notice_no == 0 && !empty($manual_notice_no)) {
        $notice_no = $manual_notice_no;
    } else {
        $notice_no = generateNoticeNumber($db);
    }

    // Upload attachment processing
    $random_file_name = null;
    $orig_file_name = null;
    $file_type = null;
    $file_size = 0;

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pdf_file'];
        $max_size = defined('NOTICE_MAX_FILE_SIZE') ? NOTICE_MAX_FILE_SIZE : 5242880;
        
        $mime = mime_content_type($file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
            $errors[] = 'अमान्य संलग्नक फ़ाइल प्रकार। केवल PDF, JPG, PNG ही समर्थित हैं।';
        }
        if ($file['size'] > $max_size) {
            $errors[] = 'फ़ाइल का आकार सीमा 5MB से अधिक नहीं होना चाहिए।';
        }

        if (empty($errors)) {
            $random_file_name = 'notice_' . bin2hex(random_bytes(16)) . '.' . $ext;
            // Public attachments are placed in uploads/notices/
            // Members only attachments are placed in storage/notices/
            if ($visibility === 'members_only') {
                $dest_dir = __DIR__ . '/../../storage/notices/';
            } else {
                $dest_dir = __DIR__ . '/../../uploads/notices/';
            }

            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $dest_dir . $random_file_name)) {
                $orig_file_name = $file['name'];
                $file_type = $ext;
                $file_size = $file['size'];
            } else {
                $errors[] = 'सर्वर पर फ़ाइल सहेजने में विफल।';
            }
        }
    }

    // Condolence Photo Upload
    $photo_name = 'default_advocate.png';
    if ($category === 'Condolence Notice' && isset($_FILES['deceased_photo']) && $_FILES['deceased_photo']['error'] === UPLOAD_ERR_OK) {
        $photo_file = $_FILES['deceased_photo'];
        $p_ext = strtolower(pathinfo($photo_file['name'], PATHINFO_EXTENSION));
        if (in_array($p_ext, ['jpg', 'jpeg', 'png'])) {
            $photo_name = 'condolence_' . bin2hex(random_bytes(16)) . '.' . $p_ext;
            $dest_photo_dir = __DIR__ . '/../../uploads/photos/';
            if (!is_dir($dest_photo_dir)) {
                mkdir($dest_photo_dir, 0755, true);
            }
            move_uploaded_file($photo_file['tmp_name'], $dest_photo_dir . $photo_name);
        }
    } elseif ($category === 'Condolence Notice' && $member_id > 0) {
        // Copy photo from existing member master
        $m_photo_stmt = $db->prepare("SELECT photo FROM members WHERE id = ?");
        $m_photo_stmt->execute([$member_id]);
        $m_photo = $m_photo_stmt->fetchColumn();
        if ($m_photo) {
            $photo_name = $m_photo;
        }
    }

    if (empty($errors) && $db) {
        try {
            $db->beginTransaction();
            $user_id = $_SESSION['auth']['user_id'];
            $publish_datetime = $pub_date . ' ' . $pub_time . ':00';

            // Insert Notice Row
            $ins = $db->prepare("
                INSERT INTO notices (notice_no, title, title_hindi, slug, category, short_description, description, priority, visibility, status, publish_at, expire_at, attachment_path, attachment_name, attachment_type, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $notice_no,
                $title,
                $title_hindi ?: null,
                $slug,
                $category,
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
                $user_id
            ]);
            $inserted_notice_id = $db->lastInsertId();

            // Insert Condolence Details if category match
            if ($category === 'Condolence Notice') {
                $cond_stmt = $db->prepare("
                    INSERT INTO condolence_notices (notice_id, member_id, advocate_name, advocate_photo, membership_no, enrollment_no, date_of_death, condolence_message, prayer_meeting_details, family_message) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $cond_stmt->execute([
                    $inserted_notice_id,
                    $member_id ?: null,
                    $advocate_name,
                    $photo_name,
                    $membership_no ?: null,
                    $enrollment_no ?: null,
                    $date_of_death ?: null,
                    $condolence_message ?: $description,
                    $prayer_meeting ?: null,
                    $family_message ?: null
                ]);

                // Option: Mark member deceased
                if ($mark_deceased && $member_id > 0) {
                    $m_up = $db->prepare("UPDATE members SET membership_status = 'deceased', status_reason = ? WHERE id = ?");
                    $m_up->execute(["शोक संदेश अधिसूचना संख्या $notice_no द्वारा दिवंगत घोषित।", $member_id]);

                    // Audit member transition history log
                    $m_hist = $db->prepare("
                        INSERT INTO member_history (member_id, action, field_name, old_value, new_value, remarks, performed_by) 
                        VALUES (?, 'Status Change', 'membership_status', 'active', 'deceased', ?, ?)
                    ");
                    $m_hist->execute([$member_id, "Marked Deceased in Master via Notice ID $inserted_notice_id", $user_id]);
                }
            }

            // Log notice history
            $hist = $db->prepare("
                INSERT INTO notice_history (notice_id, action, old_status, new_status, remarks, performed_by) 
                VALUES (?, 'Created & Uploaded', NULL, ?, 'अधिसूचना सफलतापूर्वक अपलोड एवं रक्षित की गई।', ?)
            ");
            $hist->execute([$inserted_notice_id, $status, $user_id]);

            $db->commit();
            setFlash('success', 'अधिसूचना सफलतापूर्वक बनाई गई।');
            redirect('index.php');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed notice creation transactional workflow: " . $e->getMessage());
            $errors[] = 'डेटाबेस त्रुटि: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">नई अधिसूचना निर्मित करें (Create Notice)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm">वापस सूची पर जाएं</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger font-hindi small">
        <?php echo implode('<br>', $errors); ?>
    </div>
<?php endif; ?>

<form method="POST" action="create.php" enctype="multipart/form-data" class="font-hindi small">
    <?php csrfField(); ?>
    
    <div class="card p-3 mb-4 border border-light shadow-xs bg-white">
        <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2"><i class="bi bi-info-circle-fill text-gold-dark me-2"></i>सामान्य विवरण (Basic Information)</h6>
        
        <div class="row g-3">
            <!-- Notice Number -->
            <div class="col-md-4">
                <label for="manual_notice_no" class="form-label text-secondary fw-semibold">अधिसूचना संख्या (Notice No)</label>
                <input type="text" class="form-control form-control-sm english-text" id="manual_notice_no" name="manual_notice_no" placeholder="e.g. DBA/NOTICE/2026/001" disabled>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="auto_notice_no" name="auto_notice_no" value="1" checked onchange="document.getElementById('manual_notice_no').disabled = this.checked;">
                    <label class="form-check-label text-muted font-size-xs" for="auto_notice_no">समानुक्रमिक संख्या स्वतः उत्पन्न करें (Auto-Generate)</label>
                </div>
            </div>

            <!-- Title & Hindi Title -->
            <div class="col-md-4">
                <label for="title" class="form-label text-secondary fw-semibold">अधिसूचना का शीर्षक (अंग्रेजी) *</label>
                <input type="text" class="form-control form-control-sm english-text" id="title" name="title" required placeholder="e.g. Executive Meeting Notice" value="<?php echo e($_POST['title'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="title_hindi" class="form-label text-secondary fw-semibold">अधिसूचना का शीर्षक (हिंदी)</label>
                <input type="text" class="form-control form-control-sm" id="title_hindi" name="title_hindi" placeholder="e.g. कार्यकारिणी बैठक की सूचना" value="<?php echo e($_POST['title_hindi'] ?? ''); ?>">
            </div>

            <!-- Category & Priority -->
            <div class="col-md-4">
                <label for="category" class="form-label text-secondary fw-semibold">सूचना श्रेणी *</label>
                <select class="form-select form-select-sm" id="category" name="category" required onchange="toggleCondolenceFields(this.value)">
                    <option value="">श्रेणी चुनें...</option>
                    <option value="General Notice">General Notice</option>
                    <option value="Important Notice">Important Notice</option>
                    <option value="Court Notice">Court Notice</option>
                    <option value="Meeting Notice">Meeting Notice</option>
                    <option value="Election Notice">Election Notice</option>
                    <option value="Holiday Notice">Holiday Notice</option>
                    <option value="Association Notice">Association Notice</option>
                    <option value="Condolence Notice">Condolence Notice (शोक संदेश)</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="priority" class="form-label text-secondary fw-semibold">प्राथमिकता *</label>
                <select class="form-select form-select-sm" id="priority" name="priority">
                    <option value="normal">सामान्य (Normal)</option>
                    <option value="important">महत्वपूर्ण (Important)</option>
                    <option value="urgent">अति आवश्यक (Urgent)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="visibility" class="form-label text-secondary fw-semibold">दृश्यता (Visibility) *</label>
                <select class="form-select form-select-sm" id="visibility" name="visibility">
                    <option value="public">सार्वजनिक (Public)</option>
                    <option value="members_only">केवल सदस्य (Members Only)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- CONDOLENCE SPECIFIC SECTION -->
    <div class="card p-3 mb-4 border border-dark shadow-xs bg-light-custom d-none" id="condolence-section">
        <h6 class="fw-bold text-dark-custom mb-3 border-bottom pb-2"><i class="bi bi-flower1 me-2 text-muted"></i>शोक संदेश विशेष विवरण (Condolence Details)</h6>
        
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label for="existing_member" class="form-label text-secondary fw-semibold">पंजीकृत सदस्य सूची से चुनें (Optional)</label>
                <select class="form-select form-select-sm" id="existing_member" onchange="autoFillMemberDetails(this)">
                    <option value="">सदस्य चुनें...</option>
                    <?php foreach ($members_list as $m): ?>
                        <option value="<?php echo $m['id']; ?>" data-name="<?php echo e($m['full_name']); ?>" data-membership="<?php echo e($m['membership_no']); ?>" data-enrollment="<?php echo e($m['enrollment_no']); ?>">
                            <?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="member_id" id="hidden_member_id" value="0">
            </div>
            <div class="col-md-4">
                <label for="advocate_name" class="form-label text-secondary fw-semibold">दिवंगत अधिवक्ता का नाम *</label>
                <input type="text" class="form-control form-control-sm" id="advocate_name" name="advocate_name" placeholder="स्व० श्री/श्रीमती...">
            </div>
            <div class="col-md-4">
                <label for="membership_no" class="form-label text-secondary fw-semibold">सदस्यता संख्या</label>
                <input type="text" class="form-control form-control-sm english-text" id="membership_no" name="membership_no">
            </div>
            <div class="col-md-4">
                <label for="enrollment_no" class="form-label text-secondary fw-semibold">बार काउंसिल पंजीकरण संख्या</label>
                <input type="text" class="form-control form-control-sm english-text" id="enrollment_no" name="enrollment_no">
            </div>
            <div class="col-md-4">
                <label for="date_of_death" class="form-label text-secondary fw-semibold">निधन तिथि (Date of Death)</label>
                <input type="date" class="form-control form-control-sm english-text" id="date_of_death" name="date_of_death">
            </div>
            <div class="col-md-4">
                <label for="deceased_photo" class="form-label text-secondary fw-semibold">दिवंगत सदस्य का फोटो</label>
                <input type="file" class="form-control form-control-sm" id="deceased_photo" name="deceased_photo" accept="image/*">
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="prayer_meeting" class="form-label text-secondary fw-semibold">प्रार्थना सभा / श्रद्धांजलि तिथि-स्थान विवरण</label>
                <textarea class="form-control form-control-sm" id="prayer_meeting" name="prayer_meeting" rows="2" placeholder="e.g. प्रार्थना सभा संघ सभागार में अपराह्न २ बजे..."></textarea>
            </div>
            <div class="col-md-6">
                <label for="family_message" class="form-label text-secondary fw-semibold">परिवार का संदेश या अतिरिक्त विवरण</label>
                <textarea class="form-control form-control-sm" id="family_message" name="family_message" rows="2" placeholder="शोकाकुल परिवार संदेश..."></textarea>
            </div>
            <div class="col-12 mt-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="mark_deceased" name="mark_deceased" value="1">
                    <label class="form-check-label text-danger-custom fw-semibold" for="mark_deceased">
                        स्वीकृति उपरांत सदस्य को मास्टर डेटाबेस में "दिवंगत (Deceased)" चिह्नित करें।
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT SECTION -->
    <div class="card p-3 mb-4 border border-light shadow-xs bg-white">
        <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2"><i class="bi bi-file-earmark-text text-gold-dark me-2"></i>अधिसूचना सामग्री (Content)</h6>
        
        <div class="row g-3">
            <div class="col-12">
                <label for="short_description" class="form-label text-secondary fw-semibold">संक्षिप्त विवरण (Short Description)</label>
                <input type="text" class="form-control form-control-sm" id="short_description" name="short_description" placeholder="त्वरित सूचिका में प्रदर्शित होने वाला संक्षिप्त विवरण...">
            </div>
            
            <div class="col-12">
                <label for="description" class="form-label text-secondary fw-semibold">पूर्ण अधिसूचना विवरण * (Notice Content)</label>
                <textarea class="form-control" id="description" name="description" rows="5" placeholder="अधिसूचना का पूर्ण विवरण यहाँ लिखें..." required></textarea>
            </div>
        </div>
    </div>

    <!-- PUBLICATION & ATTACHMENT -->
    <div class="card p-3 mb-4 border border-light shadow-xs bg-white">
        <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2"><i class="bi bi-calendar-event text-gold-dark me-2"></i>प्रकाशन तिथि एवं फ़ाइल संलग्नक (Scheduling & Files)</h6>
        
        <div class="row g-3">
            <div class="col-md-3">
                <label for="publish_date" class="form-label text-secondary fw-semibold">प्रकाशन दिनांक से</label>
                <input type="date" class="form-control form-control-sm english-text" id="publish_date" name="publish_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label for="publish_time" class="form-label text-secondary fw-semibold">प्रकाशन समय से</label>
                <input type="time" class="form-control form-control-sm english-text" id="publish_time" name="publish_time" value="<?php echo date('H:i'); ?>">
            </div>
            <div class="col-md-3">
                <label for="expiry_date" class="form-label text-secondary fw-semibold">अंतिम तिथि (Optional Expiry)</label>
                <input type="date" class="form-control form-control-sm english-text" id="expiry_date" name="expiry_date">
            </div>
            <div class="col-md-3">
                <label for="pdf_file" class="form-label text-secondary fw-semibold">दस्तावेज संलग्नक (PDF, JPG, PNG)</label>
                <input type="file" class="form-control form-control-sm" id="pdf_file" name="pdf_file" accept=".pdf, .jpg, .jpeg, .png">
            </div>
        </div>
    </div>

    <!-- WORKFLOW OPTIONS -->
    <div class="card p-3 mb-4 border border-light shadow-xs bg-white">
        <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2"><i class="bi bi-gear-fill text-gold-dark me-2"></i>कार्यप्रवाह विकल्प (Workflow Options)</h6>
        
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <label for="status" class="form-label text-secondary fw-semibold">अधिसूचना कार्यप्रवाह स्थिति</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="draft">ड्राफ्ट सुरक्षित करें (Draft)</option>
                    <option value="pending_approval">अनुमोदन हेतु भेजें (Pending Approval)</option>
                    <option value="published">सीधे प्रकाशित करें (Publish Now)</option>
                </select>
            </div>
            <div class="col-md-6 text-end">
                <a href="index.php" class="btn btn-outline-secondary px-4 me-2">रद्द करें</a>
                <button type="submit" class="btn btn-navy px-5 fw-semibold"><i class="bi bi-save-fill text-gold-custom me-2"></i>अधिसूचना सहेजें</button>
            </div>
        </div>
    </div>
</form>

<script>
function toggleCondolenceFields(category) {
    var condolenceBox = document.getElementById('condolence-section');
    if (category === 'Condolence Notice') {
        condolenceBox.classList.remove('d-none');
    } else {
        condolenceBox.classList.add('d-none');
    }
}

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
