<?php
/**
 * Edit Member Advocate Form
 * District Bar Association, Banda
 */

$pageTitle = 'सदस्य विवरण संशोधन (Edit Member)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce management permissions
requireRole(['admin', 'mahasachiv']);
requirePermission('members.manage');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$member = null;
$db = Database::getConnection();

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to fetch member details: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect("edit.php?id=" . $id);
    }

    // 2. Fetch and sanitize inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $father_or_husband_name = trim($_POST['father_or_husband_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    
    $enrollment_no = trim($_POST['enrollment_no'] ?? '');
    $enrollment_date = trim($_POST['enrollment_date'] ?? '');
    
    $membership_no = trim($_POST['membership_no'] ?? '');
    $membership_category = trim($_POST['membership_category'] ?? '');
    $member_since = trim($_POST['member_since'] ?? '');
    $membership_status = trim($_POST['membership_status'] ?? 'pending');
    
    $mobile = trim($_POST['mobile'] ?? '');
    $alternate_mobile = trim($_POST['alternate_mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $local_address = trim($_POST['local_address'] ?? '');
    $district = trim($_POST['district'] ?? 'Banda');
    $state = trim($_POST['state'] ?? 'Uttar Pradesh');
    $pincode = trim($_POST['pincode'] ?? '');
    
    $chamber_no = trim($_POST['chamber_no'] ?? '');
    $practice_area = trim($_POST['practice_area'] ?? '');
    $is_office_bearer = isset($_POST['is_office_bearer']) ? 1 : 0;
    
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_mobile = trim($_POST['emergency_contact_mobile'] ?? '');
    
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $show_mobile_publicly = isset($_POST['show_mobile_publicly']) ? 1 : 0;
    $show_email_publicly = isset($_POST['show_email_publicly']) ? 1 : 0;

    // 3. Validation Logic
    if (empty($full_name)) $errors[] = 'अधिवक्ता का पूरा नाम आवश्यक है।';
    if (empty($membership_no)) $errors[] = 'सदस्यता संख्या आवश्यक है।';
    if (empty($enrollment_no)) $errors[] = 'बार काउंसिल नामांकन संख्या आवश्यक है।';
    if (empty($member_since)) $errors[] = 'संघ सदस्यता की तिथि आवश्यक है।';
    if (empty($membership_status)) $errors[] = 'सदस्यता स्थिति चुनना आवश्यक है।';
    
    if (!empty($mobile) && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors[] = 'कृपया वैध 10-अंकीय मोबाइल नंबर दर्ज करें।';
    }

    if ($db && empty($errors)) {
        try {
            // Check duplicate Membership number (excluding current ID)
            $check_memb = $db->prepare("SELECT COUNT(*) FROM members WHERE membership_no = ? AND id != ?");
            $check_memb->execute([$membership_no, $id]);
            if ($check_memb->fetchColumn() > 0) {
                $errors[] = 'सदस्यता संख्या पहले से मौजूद है।';
            }

            // Check duplicate Enrollment number (excluding current ID)
            $check_enroll = $db->prepare("SELECT COUNT(*) FROM members WHERE enrollment_no = ? AND id != ?");
            $check_enroll->execute([$enrollment_no, $id]);
            if ($check_enroll->fetchColumn() > 0) {
                $errors[] = 'पंजीकरण संख्या पहले से मौजूद है।';
            }
        } catch (PDOException $e) {
            error_log("Database edit duplicate check failed: " . $e->getMessage());
        }
    }

    // 4. File uploads
    $photo_filename = $member['photo'];
    $signature_filename = $member['signature'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (empty($errors)) {
        // Photo Update
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['photo'];
            $mime = mime_content_type($file_info['tmp_name']);
            if (in_array($mime, $allowed_mimes) && $file_info['size'] <= 2097152) {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $photo_filename = 'photo_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = __DIR__ . '/../../uploads/photos/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                if (move_uploaded_file($file_info['tmp_name'], $target_dir . $photo_filename)) {
                    // Log photo update in history
                    $db->prepare("INSERT INTO member_history (member_id, action, field_name, old_value, new_value, performed_by) VALUES (?, 'Photo Updated', 'photo', ?, ?, ?)")
                       ->execute([$id, $member['photo'], $photo_filename, $_SESSION['auth']['user_id']]);
                }
            } else {
                $errors[] = 'अमान्य फ़ोटो फ़ाइल प्रकार या आकार (Max 2MB, JPG/PNG).';
            }
        }

        // Signature Update
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['signature'];
            $mime = mime_content_type($file_info['tmp_name']);
            if (in_array($mime, $allowed_mimes) && $file_info['size'] <= 2097152) {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $signature_filename = 'sig_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = __DIR__ . '/../../uploads/signatures/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                if (move_uploaded_file($file_info['tmp_name'], $target_dir . $signature_filename)) {
                    // Log signature update in history
                    $db->prepare("INSERT INTO member_history (member_id, action, field_name, old_value, new_value, performed_by) VALUES (?, 'Signature Updated', 'signature', ?, ?, ?)")
                       ->execute([$id, $member['signature'] ?? 'None', $signature_filename, $_SESSION['auth']['user_id']]);
                }
            } else {
                $errors[] = 'अमान्य हस्ताक्षर फ़ाइल प्रकार या आकार।';
            }
        }
    }

    // 5. Update and Audit Logging
    if (empty($errors)) {
        if ($db) {
            try {
                $current_user_id = $_SESSION['auth']['user_id'];
                
                // Audit Critical Fields before saving
                $critical_fields = [
                    'membership_no' => 'Membership Number',
                    'enrollment_no' => 'Enrollment Number',
                    'membership_status' => 'Membership Status',
                    'member_since' => 'Member Since'
                ];

                foreach ($critical_fields as $field_key => $field_name) {
                    $submitted_value = $$field_key;
                    $existing_value = $member[$field_key];
                    if ($submitted_value !== $existing_value) {
                        $hist_stmt = $db->prepare("
                            INSERT INTO member_history (member_id, action, field_name, old_value, new_value, performed_by, remarks) 
                            VALUES (?, 'Critical Update', ?, ?, ?, ?, 'विवरण संशोधन फ़ॉर्म के माध्यम से परिवर्तित।')
                        ");
                        $hist_stmt->execute([$id, $field_key, $existing_value, $submitted_value, $current_user_id]);
                    }
                }

                // Update database
                $sql = "UPDATE members SET 
                            membership_no = ?, enrollment_no = ?, full_name = ?, father_or_husband_name = ?, gender = ?, 
                            date_of_birth = ?, mobile = ?, alternate_mobile = ?, email = ?, permanent_address = ?, 
                            local_address = ?, district = ?, state = ?, pincode = ?, photo = ?, signature = ?, 
                            enrollment_date = ?, member_since = ?, membership_category = ?, membership_status = ?, 
                            chamber_no = ?, practice_area = ?, blood_group = ?, emergency_contact_name = ?, 
                            emergency_contact_mobile = ?, is_office_bearer = ?, is_public = ?, 
                            show_mobile_publicly = ?, show_email_publicly = ?, updated_by = ?, updated_at = NOW() 
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $membership_no, $enrollment_no, $full_name, $father_or_husband_name, $gender ?: null,
                    $date_of_birth ?: null, $mobile ?: null, $alternate_mobile ?: null, $email ?: null, $permanent_address ?: null,
                    $local_address ?: null, $district, $state, $pincode ?: null, $photo_filename, $signature_filename,
                    $enrollment_date ?: null, $member_since, $membership_category, $membership_status,
                    $chamber_no ?: null, $practice_area ?: null, $blood_group ?: null, $emergency_contact_name ?: null,
                    $emergency_contact_mobile ?: null, $is_office_bearer, $is_public,
                    $show_mobile_publicly, $show_email_publicly, $current_user_id, $id
                ]);

                // Log general contact updates if changed
                if ($mobile !== $member['mobile'] || $email !== $member['email']) {
                    $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Contact Updated', 'मोबाइल/ईमेल संपर्क विवरण अपडेट किया गया।', ?)")
                       ->execute([$id, $current_user_id]);
                }

                setFlash('success', 'सदस्य रिकॉर्ड सफलतापूर्वक संशोधित किया गया।');
                redirect('view.php?id=' . $id);
            } catch (PDOException $e) {
                error_log("Database update failed: " . $e->getMessage());
                setFlash('danger', 'अपडेट विफल: ' . $e->getMessage());
            }
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>सदस्य विवरण संशोधन (Edit Member: <?php echo e($member['full_name']); ?>)</h4>
    <a href="view.php?id=<?php echo e($id); ?>" class="btn btn-outline-navy btn-sm font-hindi"><i class="bi bi-arrow-left me-1"></i>विवरण देखें (Cancel)</a>
</div>

<form id="editMemberForm" method="POST" action="edit.php?id=<?php echo e($id); ?>" enctype="multipart/form-data" class="font-hindi small">
    <!-- CSRF Token -->
    <?php csrfField(); ?>

    <!-- SECTION 1: PERSONAL DETAILS -->
    <div class="border rounded p-3 mb-4">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-person-fill text-gold-custom me-2"></i>1. व्यक्तिगत विवरण (Personal Details)</h6>
        <div class="row g-3">
            <div class="col-md-4 col-12">
                <label for="full_name" class="form-label text-secondary fw-semibold">अधिवक्ता का पूरा नाम *</label>
                <input type="text" class="form-control form-control-sm" id="full_name" name="full_name" value="<?php echo e($member['full_name']); ?>" required>
            </div>
            <div class="col-md-4 col-12">
                <label for="father_or_husband_name" class="form-label text-secondary fw-semibold">पिता / पति का नाम</label>
                <input type="text" class="form-control form-control-sm" id="father_or_husband_name" name="father_or_husband_name" value="<?php echo e($member['father_or_husband_name']); ?>">
            </div>
            <div class="col-md-2 col-6">
                <label for="gender" class="form-label text-secondary fw-semibold">लिंग (Gender)</label>
                <select class="form-select form-select-sm" id="gender" name="gender">
                    <option value="">चुनें...</option>
                    <option value="Male" <?php echo ($member['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($member['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($member['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label for="blood_group" class="form-label text-secondary fw-semibold">रक्त समूह</label>
                <select class="form-select form-select-sm" id="blood_group" name="blood_group">
                    <option value="">चुनें...</option>
                    <option value="A+" <?php echo ($member['blood_group'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                    <option value="A-" <?php echo ($member['blood_group'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                    <option value="B+" <?php echo ($member['blood_group'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                    <option value="B-" <?php echo ($member['blood_group'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                    <option value="O+" <?php echo ($member['blood_group'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                    <option value="O-" <?php echo ($member['blood_group'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                    <option value="AB+" <?php echo ($member['blood_group'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                    <option value="AB-" <?php echo ($member['blood_group'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                </select>
            </div>
            <div class="col-md-3 col-12">
                <label for="date_of_birth" class="form-label text-secondary fw-semibold">जन्म तिथि (Date of Birth)</label>
                <input type="date" class="form-control form-control-sm english-text" id="date_of_birth" name="date_of_birth" value="<?php echo e($member['date_of_birth']); ?>">
            </div>
            <div class="col-md-4 col-12">
                <label for="photo" class="form-label text-secondary fw-semibold">फ़ोटो अपलोड करें (2MB Max, JPG/PNG)</label>
                <input type="file" class="form-control form-control-sm" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
                <small class="text-muted">मौजूदा फ़ाइल: <span class="english-text"><?php echo e(basename($member['photo'])); ?></span></small>
            </div>
            <div class="col-md-5 col-12">
                <label for="signature" class="form-label text-secondary fw-semibold">हस्ताक्षर बदलें (2MB Max, JPG/PNG)</label>
                <input type="file" class="form-control form-control-sm" id="signature" name="signature" accept=".jpg,.jpeg,.png,.webp">
                <small class="text-muted">मौजूदा: <span class="english-text"><?php echo $member['signature'] ? e(basename($member['signature'])) : 'None'; ?></span></small>
            </div>
        </div>
    </div>

    <!-- SECTION 2: ENROLLMENT & ASSOCIATION -->
    <div class="border rounded p-3 mb-4 bg-light-custom">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-shield-check text-gold-custom me-2"></i>2. नामांकन एवं संघ विवरण (Enrollment & DBA Membership)</h6>
        <div class="row g-3">
            <div class="col-md-3 col-12">
                <label for="enrollment_no" class="form-label text-secondary fw-semibold">पंजीकरण संख्या * (Locked change logs)</label>
                <input type="text" class="form-control form-control-sm english-text" id="enrollment_no" name="enrollment_no" value="<?php echo e($member['enrollment_no']); ?>" required>
            </div>
            <div class="col-md-3 col-12">
                <label for="enrollment_date" class="form-label text-secondary fw-semibold">पंजीकरण की तिथि (Enroll Date)</label>
                <input type="date" class="form-control form-control-sm english-text" id="enrollment_date" name="enrollment_date" value="<?php echo e($member['enrollment_date']); ?>">
            </div>
            <div class="col-md-3 col-12">
                <label for="membership_no" class="form-label text-secondary fw-semibold">सदस्यता संख्या * (Memb No)</label>
                <input type="text" class="form-control form-control-sm english-text" id="membership_no" name="membership_no" value="<?php echo e($member['membership_no']); ?>" required>
            </div>
            <div class="col-md-3 col-12">
                <label for="member_since" class="form-label text-secondary fw-semibold">संघ सदस्यता तिथि *</label>
                <input type="date" class="form-control form-control-sm english-text" id="member_since" name="member_since" value="<?php echo e($member['member_since']); ?>" required>
            </div>
            <div class="col-md-4 col-12">
                <label for="membership_category" class="form-label text-secondary fw-semibold">सदस्यता श्रेणी</label>
                <select class="form-select form-select-sm" id="membership_category" name="membership_category">
                    <option value="Regular Member" <?php echo ($member['membership_category'] === 'Regular Member') ? 'selected' : ''; ?>>Regular Member (सामान्य सदस्य)</option>
                    <option value="Life Member" <?php echo ($member['membership_category'] === 'Life Member') ? 'selected' : ''; ?>>Life Member (आजीवन सदस्य)</option>
                    <option value="Senior Member" <?php echo ($member['membership_category'] === 'Senior Member') ? 'selected' : ''; ?>>Senior Member (वरिष्ठ सदस्य)</option>
                    <option value="Honorary Member" <?php echo ($member['membership_category'] === 'Honorary Member') ? 'selected' : ''; ?>>Honorary Member (मानद सदस्य)</option>
                    <option value="Other" <?php echo ($member['membership_category'] === 'Other') ? 'selected' : ''; ?>>Other (अन्य)</option>
                </select>
            </div>
            <div class="col-md-4 col-12">
                <label for="membership_status" class="form-label text-secondary fw-semibold">सदस्यता स्थिति (Critical change log will trigger)</label>
                <select class="form-select form-select-sm" id="membership_status" name="membership_status">
                    <option value="active" <?php echo ($member['membership_status'] === 'active') ? 'selected' : ''; ?>>Active (सक्रिय)</option>
                    <option value="inactive" <?php echo ($member['membership_status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (निष्क्रिय)</option>
                    <option value="suspended" <?php echo ($member['membership_status'] === 'suspended') ? 'selected' : ''; ?>>Suspended (निलंबित)</option>
                    <option value="expired" <?php echo ($member['membership_status'] === 'expired') ? 'selected' : ''; ?>>Expired (समाप्त)</option>
                    <option value="deceased" <?php echo ($member['membership_status'] === 'deceased') ? 'selected' : ''; ?>>Deceased (दिवंगत)</option>
                    <option value="pending" <?php echo ($member['membership_status'] === 'pending') ? 'selected' : ''; ?>>Pending (लंबित)</option>
                    <option value="rejected" <?php echo ($member['membership_status'] === 'rejected') ? 'selected' : ''; ?>>Rejected (अस्वीकृत)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- SECTION 3: CONTACT DETAILS & ADDRESS -->
    <div class="border rounded p-3 mb-4">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-telephone-fill text-gold-custom me-2"></i>3. संपर्क विवरण एवं पता (Contact & Address Details)</h6>
        <div class="row g-3">
            <div class="col-md-4 col-12">
                <label for="mobile" class="form-label text-secondary fw-semibold">मोबाइल नंबर *</label>
                <input type="tel" class="form-control form-control-sm english-text" id="mobile" name="mobile" value="<?php echo e($member['mobile']); ?>" required maxlength="10">
            </div>
            <div class="col-md-4 col-12">
                <label for="alternate_mobile" class="form-label text-secondary fw-semibold">वैकल्पिक मोबाइल</label>
                <input type="tel" class="form-control form-control-sm english-text" id="alternate_mobile" name="alternate_mobile" value="<?php echo e($member['alternate_mobile']); ?>" maxlength="10">
            </div>
            <div class="col-md-4 col-12">
                <label for="email" class="form-label text-secondary fw-semibold">ईमेल पता (Email)</label>
                <input type="email" class="form-control form-control-sm english-text" id="email" name="email" value="<?php echo e($member['email']); ?>">
            </div>
            <div class="col-md-6 col-12">
                <label for="local_address" class="form-label text-secondary fw-semibold">स्थानीय पता (Local Address)</label>
                <textarea class="form-control form-control-sm" id="local_address" name="local_address" rows="2"><?php echo e($member['local_address']); ?></textarea>
            </div>
            <div class="col-md-6 col-12">
                <label for="permanent_address" class="form-label text-secondary fw-semibold">स्थायी पता (Permanent Address)</label>
                <textarea class="form-control form-control-sm" id="permanent_address" name="permanent_address" rows="2"><?php echo e($member['permanent_address']); ?></textarea>
            </div>
            <div class="col-md-4 col-6">
                <label for="district" class="form-label text-secondary fw-semibold">जिला (District)</label>
                <input type="text" class="form-control form-control-sm" id="district" name="district" value="<?php echo e($member['district']); ?>">
            </div>
            <div class="col-md-4 col-6">
                <label for="state" class="form-label text-secondary fw-semibold">राज्य (State)</label>
                <input type="text" class="form-control form-control-sm" id="state" name="state" value="<?php echo e($member['state']); ?>">
            </div>
            <div class="col-md-4 col-12">
                <label for="pincode" class="form-label text-secondary fw-semibold">पिन कोड (Pincode)</label>
                <input type="text" class="form-control form-control-sm english-text" id="pincode" name="pincode" value="<?php echo e($member['pincode']); ?>" maxlength="6">
            </div>
        </div>
    </div>

    <!-- SECTION 4: PROFESSIONAL & EMERGENCY & PRIVACY -->
    <div class="border rounded p-3 mb-4">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-briefcase-fill text-gold-custom me-2"></i>4. व्यावसायिक, आपातकालीन एवं विजिबिलिटी (Other Details)</h6>
        <div class="row g-3">
            <div class="col-md-4 col-12">
                <label for="chamber_no" class="form-label text-secondary fw-semibold">चैंबर संख्या (Chamber Number)</label>
                <input type="text" class="form-control form-control-sm" id="chamber_no" name="chamber_no" value="<?php echo e($member['chamber_no']); ?>">
            </div>
            <div class="col-md-4 col-12">
                <label for="practice_area" class="form-label text-secondary fw-semibold">कार्य क्षेत्र (Practice Area)</label>
                <input type="text" class="form-control form-control-sm" id="practice_area" name="practice_area" value="<?php echo e($member['practice_area']); ?>">
            </div>
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-4 pt-1">
                    <input class="form-check-input" type="checkbox" id="is_office_bearer" name="is_office_bearer" value="1" <?php echo $member['is_office_bearer'] == 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label text-navy-custom fw-semibold" for="is_office_bearer">कार्यकारिणी पदाधिकारी हैं (Office Bearer?)</label>
                </div>
            </div>
            
            <div class="col-md-6 col-12">
                <label for="emergency_contact_name" class="form-label text-secondary fw-semibold">आपातकालीन संपर्क व्यक्ति का नाम</label>
                <input type="text" class="form-control form-control-sm" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo e($member['emergency_contact_name']); ?>">
            </div>
            <div class="col-md-6 col-12">
                <label for="emergency_contact_mobile" class="form-label text-secondary fw-semibold">आपातकालीन संपर्क मोबाइल</label>
                <input type="tel" class="form-control form-control-sm english-text" id="emergency_contact_mobile" name="emergency_contact_mobile" value="<?php echo e($member['emergency_contact_mobile']); ?>" maxlength="10">
            </div>

            <!-- Privacy -->
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" <?php echo $member['is_public'] == 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label text-navy-custom fw-semibold" for="is_public">प्रोफाइल सार्वजनिक दिखाएं (Show Publicly)</label>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="show_mobile_publicly" name="show_mobile_publicly" value="1" <?php echo $member['show_mobile_publicly'] == 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label text-navy-custom fw-semibold" for="show_mobile_publicly">सार्वजनिक मोबाइल (Show Mobile publicly)</label>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="show_email_publicly" name="show_email_publicly" value="1" <?php echo $member['show_email_publicly'] == 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label text-navy-custom fw-semibold" for="show_email_publicly">सार्वजनिक ईमेल (Show Email publicly)</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission -->
    <div class="text-end mb-5">
        <a href="view.php?id=<?php echo e($id); ?>" class="btn btn-outline-secondary px-4 py-2 me-2">रद्द करें (Cancel)</a>
        <button type="submit" class="btn btn-navy px-5 py-2 fw-semibold">
            <i class="bi bi-save2-fill text-gold-custom me-2"></i> संशोधन सहेजें (Save Changes)
        </button>
    </div>
</form>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
