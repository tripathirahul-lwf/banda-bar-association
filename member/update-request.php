<?php
/**
 * Member Profile Update Request Form
 * District Bar Association, Banda
 */

$pageTitle = 'सुधार अनुरोध (Request Profile Update)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce Member Role
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
        error_log("Failed to fetch member details: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../includes/dashboard/footer.php';
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        setFlash('danger', 'सुरक्षा टोकन अमान्य है।');
        redirect('update-request.php');
    }

    // Capture fields allowed to request updates for
    $req_data = [
        'mobile' => trim($_POST['mobile'] ?? ''),
        'alternate_mobile' => trim($_POST['alternate_mobile'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'local_address' => trim($_POST['local_address'] ?? ''),
        'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_mobile' => trim($_POST['emergency_contact_mobile'] ?? ''),
        'show_mobile_publicly' => isset($_POST['show_mobile_publicly']) ? 1 : 0,
        'show_email_publicly' => isset($_POST['show_email_publicly']) ? 1 : 0,
    ];

    $remarks = trim($_POST['remarks'] ?? '');

    // Basic Validation
    $errors = [];
    if (empty($req_data['mobile'])) {
        $errors[] = 'मोबाइल नंबर आवश्यक है।';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $req_data['mobile'])) {
        $errors[] = 'कृपया वैध 10-अंकीय मोबाइल नंबर दर्ज करें।';
    }
    
    if (!empty($req_data['email']) && !filter_var($req_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'कृपया वैध ईमेल दर्ज करें।';
    }

    if (empty($errors)) {
        if ($db) {
            try {
                // Encode requested changes as JSON
                $json_changes = json_encode($req_data, JSON_UNESCAPED_UNICODE);
                
                $stmt = $db->prepare("INSERT INTO member_profile_update_requests (member_id, requested_changes, remarks, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$member_id, $json_changes, $remarks]);

                setFlash('success', 'संशोधन अनुरोध सफलतापूर्वक प्रस्तुत कर दिया गया है। सचिव/प्रशासक द्वारा सत्यापन उपरांत इसे अपडेट कर दिया जाएगा।');
                redirect('profile.php');
            } catch (PDOException $e) {
                error_log("Failed to insert update request: " . $e->getMessage());
                setFlash('danger', 'सिस्टम एरर। अनुरोध सहेजने में विफल।');
            }
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}

$csrf_token = generate_csrf_token();
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0">विवरण सुधार अनुरोध (Profile Update Request)</h4>
    <a href="profile.php" class="btn btn-outline-navy btn-sm font-hindi">मेरी प्रोफाइल देखें</a>
</div>

<div class="alert alert-info border-0 rounded shadow-xs font-hindi small">
    <i class="bi bi-info-circle-fill text-info me-1"></i>
    <strong>महत्वपूर्ण सूचना:</strong> सुरक्षा एवं सत्यता बनाए रखने हेतु सदस्यता क्रमांक, बार काउंसिल पंजीकरण संख्या तथा श्रेणी जैसे विधिक विवरणों को आप स्वयं नहीं बदल सकते। इनके संशोधन के लिए साक्ष्यों के साथ कार्यालय में संपर्क करें। निम्न विवरणों में सुधार हेतु आप अनुरोध भेज सकते हैं।
</div>

<form method="POST" action="update-request.php" class="font-hindi small">
    <!-- CSRF Token -->
    <?php csrfField(); ?>

    <div class="row g-3">
        <!-- Mobile -->
        <div class="col-md-6">
            <label for="mobile" class="form-label text-secondary fw-semibold">मोबाइल नंबर (Mobile) *</label>
            <input type="tel" class="form-control english-text" id="mobile" name="mobile" value="<?php echo e($member['mobile']); ?>" required maxlength="10">
        </div>
        
        <!-- Alternate Mobile -->
        <div class="col-md-6">
            <label for="alternate_mobile" class="form-label text-secondary fw-semibold">वैकल्पिक मोबाइल (Alternate Mobile)</label>
            <input type="tel" class="form-control english-text" id="alternate_mobile" name="alternate_mobile" value="<?php echo e($member['alternate_mobile']); ?>" maxlength="10">
        </div>

        <!-- Email -->
        <div class="col-md-12">
            <label for="email" class="form-label text-secondary fw-semibold">ईमेल पता (Email)</label>
            <input type="email" class="form-control english-text" id="email" name="email" value="<?php echo e($member['email']); ?>">
        </div>

        <!-- Local Address -->
        <div class="col-md-12">
            <label for="local_address" class="form-label text-secondary fw-semibold">स्थानीय पता (Local Address)</label>
            <textarea class="form-control" id="local_address" name="local_address" rows="3"><?php echo e($member['local_address']); ?></textarea>
        </div>

        <!-- Emergency Contact Person -->
        <div class="col-md-6">
            <label for="emergency_contact_name" class="form-label text-secondary fw-semibold">आपातकालीन संपर्क व्यक्ति (Emergency Name)</label>
            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo e($member['emergency_contact_name']); ?>">
        </div>

        <!-- Emergency Mobile -->
        <div class="col-md-6">
            <label for="emergency_contact_mobile" class="form-label text-secondary fw-semibold">आपातकालीन मोबाइल (Emergency Mobile)</label>
            <input type="tel" class="form-control english-text" id="emergency_contact_mobile" name="emergency_contact_mobile" value="<?php echo e($member['emergency_contact_mobile']); ?>" maxlength="10">
        </div>

        <!-- Privacy preferences -->
        <div class="col-md-6">
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="show_mobile_publicly" name="show_mobile_publicly" value="1" <?php echo $member['show_mobile_publicly'] == 1 ? 'checked' : ''; ?>>
                <label class="form-check-label text-navy-custom fw-semibold" for="show_mobile_publicly">पब्लिक डायरेक्टरी में मोबाइल नंबर दिखाएं</label>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="show_email_publicly" name="show_email_publicly" value="1" <?php echo $member['show_email_publicly'] == 1 ? 'checked' : ''; ?>>
                <label class="form-check-label text-navy-custom fw-semibold" for="show_email_publicly">पब्लिक डायरेक्टरी में ईमेल पता दिखाएं</label>
            </div>
        </div>

        <!-- Remarks -->
        <div class="col-md-12 mt-3">
            <label for="remarks" class="form-label text-secondary fw-semibold">सुधार का विवरण / टिप्पणी (Remarks / Description) *</label>
            <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="e.g. मेरा मोबाइल नंबर बदल गया है, कृपया इसे स्वीकृत करें।" required></textarea>
        </div>

        <div class="col-12 mt-4 text-end">
            <button type="submit" class="btn btn-navy px-4 py-2 fw-semibold">
                <i class="bi bi-send-fill text-gold-custom me-2"></i>अनुरोध भेजें (Submit Correction Request)
            </button>
        </div>
    </div>
</form>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
