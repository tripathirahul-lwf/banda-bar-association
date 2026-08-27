<?php
/**
 * Create Member Advocate Form (Multi-Section)
 * District Bar Association, Banda
 */

$pageTitle = 'नया सदस्य जोड़ें (Add Member)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce management permissions
requireRole(['admin', 'mahasachiv']);
requirePermission('members.manage');

$errors = [];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('create.php');
    }

    // 2. Extract and sanitize inputs
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
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'कृपया वैध ईमेल पता दर्ज करें।';
    }

    if ($db && empty($errors)) {
        try {
            // Uniqueness check for Membership Number
            $check_memb = $db->prepare("SELECT COUNT(*) FROM members WHERE membership_no = ?");
            $check_memb->execute([$membership_no]);
            if ($check_memb->fetchColumn() > 0) {
                $errors[] = 'सदस्यता संख्या पहले से मौजूद है। (Duplicate Membership Number.)';
            }

            // Uniqueness check for Enrollment Number
            $check_enroll = $db->prepare("SELECT COUNT(*) FROM members WHERE enrollment_no = ?");
            $check_enroll->execute([$enrollment_no]);
            if ($check_enroll->fetchColumn() > 0) {
                $errors[] = 'पंजीकरण संख्या पहले से मौजूद है। (Duplicate Enrollment Number.)';
            }
        } catch (PDOException $e) {
            error_log("Uniqueness database check failure: " . $e->getMessage());
        }
    }

    // 4. File Upload Handling
    $photo_filename = 'default_advocate.png';
    $signature_filename = null;

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (empty($errors)) {
        // Photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['photo'];
            $mime = mime_content_type($file_info['tmp_name']);
            if (!in_array($mime, $allowed_mimes)) {
                $errors[] = 'अमान्य फ़ोटो फ़ाइल प्रकार। केवल JPG, PNG, WEBP स्वीकार्य हैं।';
            } elseif ($file_info['size'] > 2097152) { // 2MB
                $errors[] = 'फ़ोटो फ़ाइल का आकार 2MB से अधिक नहीं होना चाहिए।';
            } else {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $photo_filename = 'photo_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = __DIR__ . '/../../uploads/photos/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                move_uploaded_file($file_info['tmp_name'], $target_dir . $photo_filename);
            }
        }

        // Signature upload
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['signature'];
            $mime = mime_content_type($file_info['tmp_name']);
            if (!in_array($mime, $allowed_mimes)) {
                $errors[] = 'अमान्य हस्ताक्षर फ़ाइल प्रकार।';
            } elseif ($file_info['size'] > 2097152) {
                $errors[] = 'हस्ताक्षर फ़ाइल का आकार 2MB से अधिक नहीं होना चाहिए।';
            } else {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $signature_filename = 'sig_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = __DIR__ . '/../../uploads/signatures/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                move_uploaded_file($file_info['tmp_name'], $target_dir . $signature_filename);
            }
        }
    }

    // 5. Save Member Record
    if (empty($errors)) {
        if ($db) {
            try {
                $current_user_id = $_SESSION['auth']['user_id'];
                
                $sql = "INSERT INTO members (
                            membership_no, enrollment_no, full_name, father_or_husband_name, gender, 
                            date_of_birth, mobile, alternate_mobile, email, permanent_address, 
                            local_address, district, state, pincode, photo, signature, 
                            enrollment_date, member_since, membership_category, membership_status, 
                            chamber_no, practice_area, blood_group, emergency_contact_name, 
                            emergency_contact_mobile, is_office_bearer, is_public, 
                            show_mobile_publicly, show_email_publicly, created_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $membership_no, $enrollment_no, $full_name, $father_or_husband_name, $gender ?: null,
                    $date_of_birth ?: null, $mobile ?: null, $alternate_mobile ?: null, $email ?: null, $permanent_address ?: null,
                    $local_address ?: null, $district, $state, $pincode ?: null, $photo_filename, $signature_filename,
                    $enrollment_date ?: null, $member_since, $membership_category, $membership_status,
                    $chamber_no ?: null, $practice_area ?: null, $blood_group ?: null, $emergency_contact_name ?: null,
                    $emergency_contact_mobile ?: null, $is_office_bearer, $is_public,
                    $show_mobile_publicly, $show_email_publicly, $current_user_id
                ]);
                
                $new_member_id = $db->lastInsertId();

                // Add to member_history
                $hist_stmt = $db->prepare("INSERT INTO member_history (member_id, action, remarks, performed_by) VALUES (?, 'Member Created', 'नया अधिवक्ता सदस्य रिकॉर्ड जोड़ा गया।', ?)");
                $hist_stmt->execute([$new_member_id, $current_user_id]);

                setFlash('success', 'सदस्य रिकॉर्ड सफलतापूर्वक सहेजा गया। (Advocate record added successfully.)');
                redirect('index.php');
            } catch (PDOException $e) {
                error_log("Database insertion failed: " . $e->getMessage());
                setFlash('danger', 'डेटाबेस प्रविष्टि विफल: ' . $e->getMessage());
            }
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}

$csrf_token = generate_csrf_token();
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0"><i class="bi bi-person-plus-fill me-2"></i>नया अधिवक्ता सदस्य जोड़ें (Add Member)</h4>
    <a href="index.php" class="btn btn-outline-navy btn-sm font-hindi"><i class="bi bi-arrow-left me-1"></i>वापस सूची पर जाएं</a>
</div>

<form id="createMemberForm" method="POST" action="create.php" enctype="multipart/form-data" class="font-hindi small">
    <!-- CSRF Token -->
    <?php csrfField(); ?>

    <!-- SECTION 1: PERSONAL DETAILS -->
    <div class="border rounded p-3 mb-4">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-person-fill text-gold-custom me-2"></i>1. व्यक्तिगत विवरण (Personal Details)</h6>
        <div class="row g-3">
            <div class="col-md-4 col-12">
                <label for="full_name" class="form-label text-secondary fw-semibold">अधिवक्ता का पूरा नाम *</label>
                <input type="text" class="form-control form-control-sm" id="full_name" name="full_name" required placeholder="e.g. राजेश कुमार सिंह">
            </div>
            <div class="col-md-4 col-12">
                <label for="father_or_husband_name" class="form-label text-secondary fw-semibold">पिता / पति का नाम</label>
                <input type="text" class="form-control form-control-sm" id="father_or_husband_name" name="father_or_husband_name" placeholder="e.g. श्री राम सिंह">
            </div>
            <div class="col-md-2 col-6">
                <label for="gender" class="form-label text-secondary fw-semibold">लिंग (Gender)</label>
                <select class="form-select form-select-sm" id="gender" name="gender">
                    <option value="">चुनें...</option>
                    <option value="Male">Male (पुरुष)</option>
                    <option value="Female">Female (महिला)</option>
                    <option value="Other">Other (अन्य)</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label for="blood_group" class="form-label text-secondary fw-semibold">रक्त समूह</label>
                <select class="form-select form-select-sm" id="blood_group" name="blood_group">
                    <option value="">चुनें...</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                </select>
            </div>
            <div class="col-md-3 col-12">
                <label for="date_of_birth" class="form-label text-secondary fw-semibold">जन्म तिथि (Date of Birth)</label>
                <input type="date" class="form-control form-control-sm english-text" id="date_of_birth" name="date_of_birth">
            </div>
            <div class="col-md-4 col-12">
                <label for="photo" class="form-label text-secondary fw-semibold">अधिवक्ता फोटो (Max 2MB, JPG/PNG)</label>
                <input type="file" class="form-control form-control-sm" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-md-5 col-12">
                <label for="signature" class="form-label text-secondary fw-semibold">हस्ताक्षर (Max 2MB, JPG/PNG)</label>
                <input type="file" class="form-control form-control-sm" id="signature" name="signature" accept=".jpg,.jpeg,.png,.webp">
            </div>
        </div>
    </div>

    <!-- SECTION 2: ENROLLMENT & ASSOCIATION -->
    <div class="border rounded p-3 mb-4 bg-light-custom">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-shield-check text-gold-custom me-2"></i>2. नामांकन एवं संघ विवरण (Enrollment & DBA Membership)</h6>
        <div class="row g-3">
            <div class="col-md-3 col-12">
                <label for="enrollment_no" class="form-label text-secondary fw-semibold">पंजीकरण संख्या * (Bar Council Enroll No)</label>
                <input type="text" class="form-control form-control-sm english-text" id="enrollment_no" name="enrollment_no" required placeholder="e.g. UP/1234/2005">
            </div>
            <div class="col-md-3 col-12">
                <label for="enrollment_date" class="form-label text-secondary fw-semibold">पंजीकरण की तिथि (Enroll Date)</label>
                <input type="date" class="form-control form-control-sm english-text" id="enrollment_date" name="enrollment_date">
            </div>
            <div class="col-md-3 col-12">
                <label for="membership_no" class="form-label text-secondary fw-semibold">संघ सदस्यता संख्या * (DBA Code)</label>
                <input type="text" class="form-control form-control-sm english-text" id="membership_no" name="membership_no" required placeholder="e.g. DBA-001">
            </div>
            <div class="col-md-3 col-12">
                <label for="member_since" class="form-label text-secondary fw-semibold">संघ सदस्यता तिथि *</label>
                <input type="date" class="form-control form-control-sm english-text" id="member_since" name="member_since" required>
            </div>
            <div class="col-md-4 col-12">
                <label for="membership_category" class="form-label text-secondary fw-semibold">सदस्यता श्रेणी</label>
                <select class="form-select form-select-sm" id="membership_category" name="membership_category">
                    <option value="Regular Member">Regular Member (सामान्य सदस्य)</option>
                    <option value="Life Member">Life Member (आजीवन सदस्य)</option>
                    <option value="Senior Member">Senior Member (वरिष्ठ सदस्य)</option>
                    <option value="Honorary Member">Honorary Member (मानद सदस्य)</option>
                    <option value="Other">Other (अन्य)</option>
                </select>
            </div>
            <div class="col-md-4 col-12">
                <label for="membership_status" class="form-label text-secondary fw-semibold">प्रारंभिक सदस्यता स्थिति *</label>
                <select class="form-select form-select-sm" id="membership_status" name="membership_status">
                    <option value="active">Active (सक्रिय)</option>
                    <option value="pending">Pending (लंबित)</option>
                    <option value="inactive">Inactive (निष्क्रिय)</option>
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
                <input type="tel" class="form-control form-control-sm english-text" id="mobile" name="mobile" required maxlength="10" placeholder="e.g. 9450123456">
            </div>
            <div class="col-md-4 col-12">
                <label for="alternate_mobile" class="form-label text-secondary fw-semibold">वैकल्पिक मोबाइल</label>
                <input type="tel" class="form-control form-control-sm" id="alternate_mobile" name="alternate_mobile" maxlength="10">
            </div>
            <div class="col-md-4 col-12">
                <label for="email" class="form-label text-secondary fw-semibold">ईमेल पता (Email)</label>
                <input type="email" class="form-control form-control-sm english-text" id="email" name="email" placeholder="e.g. name@domain.com">
            </div>
            <div class="col-md-6 col-12">
                <label for="local_address" class="form-label text-secondary fw-semibold">स्थानीय पता (Local Address)</label>
                <textarea class="form-control form-control-sm" id="local_address" name="local_address" rows="2" placeholder="चैंबर या शहर का पता..."></textarea>
            </div>
            <div class="col-md-6 col-12">
                <label for="permanent_address" class="form-label text-secondary fw-semibold">स्थायी पता (Permanent Address)</label>
                <textarea class="form-control form-control-sm" id="permanent_address" name="permanent_address" rows="2" placeholder="गृह निवास पता..."></textarea>
            </div>
            <div class="col-md-4 col-6">
                <label for="district" class="form-label text-secondary fw-semibold">जिला (District)</label>
                <input type="text" class="form-control form-control-sm" id="district" name="district" value="Banda">
            </div>
            <div class="col-md-4 col-6">
                <label for="state" class="form-label text-secondary fw-semibold">राज्य (State)</label>
                <input type="text" class="form-control form-control-sm" id="state" name="state" value="Uttar Pradesh">
            </div>
            <div class="col-md-4 col-12">
                <label for="pincode" class="form-label text-secondary fw-semibold">पिन कोड (Pincode)</label>
                <input type="text" class="form-control form-control-sm english-text" id="pincode" name="pincode" maxlength="6">
            </div>
        </div>
    </div>

    <!-- SECTION 4: PROFESSIONAL & EMERGENCY CONTACTS & PRIVACY -->
    <div class="border rounded p-3 mb-4">
        <h6 class="fw-bold text-navy-custom border-bottom pb-2 mb-3"><i class="bi bi-briefcase-fill text-gold-custom me-2"></i>4. व्यावसायिक, आपातकालीन एवं विजिबिलिटी (Other Details)</h6>
        <div class="row g-3">
            <div class="col-md-4 col-12">
                <label for="chamber_no" class="form-label text-secondary fw-semibold">चैंबर संख्या (Chamber Number)</label>
                <input type="text" class="form-control form-control-sm" id="chamber_no" name="chamber_no" placeholder="e.g. Chamber 28">
            </div>
            <div class="col-md-4 col-12">
                <label for="practice_area" class="form-label text-secondary fw-semibold">कार्य क्षेत्र (Practice Area)</label>
                <input type="text" class="form-control form-control-sm" id="practice_area" name="practice_area" placeholder="e.g. Civil & Criminal">
            </div>
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-4 pt-1">
                    <input class="form-check-input" type="checkbox" id="is_office_bearer" name="is_office_bearer" value="1">
                    <label class="form-check-label text-navy-custom fw-semibold" for="is_office_bearer">कार्यकारिणी पदाधिकारी हैं (Office Bearer?)</label>
                </div>
            </div>
            
            <div class="col-md-6 col-12">
                <label for="emergency_contact_name" class="form-label text-secondary fw-semibold">आपातकालीन संपर्क व्यक्ति का नाम</label>
                <input type="text" class="form-control form-control-sm" id="emergency_contact_name" name="emergency_contact_name">
            </div>
            <div class="col-md-6 col-12">
                <label for="emergency_contact_mobile" class="form-label text-secondary fw-semibold">आपातकालीन संपर्क मोबाइल</label>
                <input type="tel" class="form-control form-control-sm english-text" id="emergency_contact_mobile" name="emergency_contact_mobile" maxlength="10">
            </div>

            <!-- Privacy preferences -->
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" checked>
                    <label class="form-check-label text-navy-custom fw-semibold" for="is_public">प्रोफाइल सार्वजनिक दिखाएं (Show Publicly)</label>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="show_mobile_publicly" name="show_mobile_publicly" value="1">
                    <label class="form-check-label text-navy-custom fw-semibold" for="show_mobile_publicly">सार्वजनिक मोबाइल (Show Mobile publicly)</label>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="show_email_publicly" name="show_email_publicly" value="1">
                    <label class="form-check-label text-navy-custom fw-semibold" for="show_email_publicly">सार्वजनिक ईमेल (Show Email publicly)</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission -->
    <div class="text-end mb-5">
        <a href="index.php" class="btn btn-outline-secondary px-4 py-2 me-2">रद्द करें (Cancel)</a>
        <button type="submit" class="btn btn-navy px-5 py-2 fw-semibold">
            <i class="bi bi-person-plus-fill text-gold-custom me-2"></i> सदस्य सहेजें (Save Member)
        </button>
    </div>
</form>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
