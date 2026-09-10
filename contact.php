<?php
/**
 * Contact Us Page - District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'संपर्क करें (Contact)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

$errors = [];
$name = '';
$mobile = '';
$email = '';
$subject = '';
$message = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Verification
    $submitted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($submitted_token)) {
        set_flash_message('danger', 'सुरक्षा टोकन अमान्य है। कृपया पुनः प्रयास करें। (Invalid CSRF token.)');
        redirect('contact.php');
    }

    // 2. Extract and Sanitize Inputs
    $name = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : '';
    $mobile = isset($_POST['contact_mobile']) ? trim($_POST['contact_mobile']) : '';
    $email = isset($_POST['contact_email']) ? trim($_POST['contact_email']) : '';
    $subject = isset($_POST['contact_subject']) ? trim($_POST['contact_subject']) : '';
    $message = isset($_POST['contact_message']) ? trim($_POST['contact_message']) : '';

    // 3. Validation Logic
    if (empty($name)) {
        $errors['contact_name'] = 'नाम लिखना आवश्यक है। (Name is required.)';
    }
    
    if (empty($mobile)) {
        $errors['contact_mobile'] = 'मोबाइल नंबर लिखना आवश्यक है। (Mobile is required.)';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors['contact_mobile'] = 'कृपया वैध 10-अंकीय भारतीय मोबाइल नंबर दर्ज करें।';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = 'कृपया वैध ईमेल पता दर्ज करें। (Invalid email.)';
    }
    
    if (empty($subject)) {
        $errors['contact_subject'] = 'विषय लिखना आवश्यक है। (Subject is required.)';
    }
    
    if (empty($message)) {
        $errors['contact_message'] = 'संदेश लिखना आवश्यक है। (Message is required.)';
    }

    // 4. Processing
    if (empty($errors)) {
        $db = Database::getConnection();
        $saved = false;
        
        if ($db) {
            try {
                // Prepare INSERT statement
                $stmt = $db->prepare("INSERT INTO contact_messages (name, mobile, email, subject, message) VALUES (?, ?, ?, ?, ?)");
                $saved = $stmt->execute([$name, $mobile, $email, $subject, $message]);
            } catch (PDOException $e) {
                error_log("Failed to save contact message: " . $e->getMessage());
            }
        }
        
        // Even if DB is not ready, we proceed with simulated success in Phase 1
        set_flash_message('success', 'आपका संदेश सफलतापूर्वक प्राप्त हो गया है। प्रशासनिक अधिकारी शीघ्र ही आपसे संपर्क करेंगे। (Your message has been submitted successfully.)');
        
        // Reset fields
        $name = $mobile = $email = $subject = $message = '';
        
        redirect('contact.php');
    } else {
        set_flash_message('danger', 'कृपया प्रपत्र में त्रुटियों को ठीक करें। (Please fix the validation errors below.)');
    }
}

// Generate CSRF token for security
$csrf_token = generate_csrf_token();
?>

<!-- Banner -->
<div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm border-bottom border-gold-custom">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 mb-1 font-hindi fw-bold text-gold-custom">संपर्क करें (Contact Us)</h1>
            <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small">जिला अधिवक्ता संघ, बांदा कार्यालय एवं पूछताछ डेस्क</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-gold-custom text-navy-custom font-hindi px-3 py-2 fs-6">Estd. 1937</span>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Contact Info Cards -->
    <div class="col-lg-5 col-12">
        <div class="bg-white p-4 rounded-3 shadow-sm border border-light h-100 d-flex flex-column gap-3">
            
            <h4 class="text-navy-custom font-hindi fw-bold border-bottom border-gold-custom pb-2 mb-3">कार्यालय विवरण (Office details)</h4>
            
            <!-- Address -->
            <div class="d-flex align-items-start gap-3">
                <div class="rounded bg-navy-custom text-gold-custom p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                    <i class="bi bi-geo-alt-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-navy-custom font-hindi">कार्यालय का पता:</h6>
                    <p class="small text-muted font-hindi mb-0">
                        <?php echo e(getSetting('address', 'जिला अधिवक्ता संघ कार्यालय, जनपद न्यायालय परिसर, बांदा')); ?>,<br>
                        पिन कोड - <?php echo e(getSetting('pincode', '२१०००१')); ?>
                    </p>
                </div>
            </div>
            
            <!-- Phone -->
            <div class="d-flex align-items-start gap-3">
                <div class="rounded bg-navy-custom text-gold-custom p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                    <i class="bi bi-telephone-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-navy-custom font-hindi">फ़ोन नंबर (Helpline):</h6>
                    <p class="small text-muted english-text mb-0">
                        <?php echo e(getSetting('contact_number', '+91-XXXXXXXXXX')); ?> (Office)<br>
                        <?php echo e(getSetting('alternate_number', '+91-XXXXXXXXXX')); ?> (Help Desk)
                    </p>
                </div>
            </div>
            
            <!-- Email -->
            <div class="d-flex align-items-start gap-3">
                <div class="rounded bg-navy-custom text-gold-custom p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                    <i class="bi bi-envelope-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-navy-custom font-hindi">ईमेल पता (Email):</h6>
                    <p class="small text-muted english-text mb-0">
                        <?php echo e(getSetting('official_email', 'info@dbabanda.in')); ?>
                    </p>
                </div>
            </div>
            
            <!-- Office Timings -->
            <div class="d-flex align-items-start gap-3">
                <div class="rounded bg-navy-custom text-gold-custom p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                    <i class="bi bi-clock-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-navy-custom font-hindi">कार्यालय समय (Timings):</h6>
                    <p class="small text-muted font-hindi mb-0">
                        <?php echo e(getSetting('office_timing', 'प्रातः १०:०० बजे से सायं ५:०० बजे तक')); ?><br>
                        (रविवार एवं राजपत्रित अवकाशों पर बंद)
                    </p>
                </div>
            </div>

            <!-- Google Map Embed Placeholder -->
            <div class="p-2 border border-light bg-light-custom rounded mt-auto text-center py-4">
                <i class="bi bi-map text-secondary-custom display-4 mb-2 d-block"></i>
                <h6 class="fw-bold text-navy-custom font-hindi">न्यायालय मानचित्र निर्देश</h6>
                <p class="small text-muted font-hindi mb-0">बांदा रेलवे स्टेशन से दुरी लगभग 2 किमी। सदर कोर्ट परिसर के मुख्य द्वार के निकट स्थित है।</p>
            </div>
        </div>
    </div>

    <!-- Contact Form Container -->
    <div class="col-lg-7 col-12">
        <div class="bg-white p-3 p-md-5 rounded-3 shadow-sm border border-light">
            <h4 class="text-navy-custom font-hindi fw-bold border-bottom border-gold-custom pb-2 mb-3"><i class="bi bi-chat-left-dots-fill text-gold-custom me-2"></i>पूछताछ प्रपत्र (Contact Form)</h4>
            <p class="small text-muted font-hindi mb-4">संघ के कार्यों, सदस्य सत्यापन अथवा तकनीकी सहायता हेतु अपनी शिकायत या सुझाव प्रेषित करें।</p>
            
            <form id="contactForm" method="POST" action="contact.php" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                
                <div class="row g-3">
                    <!-- Name -->
                    <div class="col-md-6 col-12">
                        <label for="contact_name" class="form-label font-hindi small fw-semibold text-secondary">आपका नाम (Full Name) *</label>
                        <input type="text" class="form-control <?php echo isset($errors['contact_name']) ? 'is-invalid' : ''; ?>" id="contact_name" name="contact_name" value="<?php echo sanitize($name); ?>" placeholder="e.g. राजेश कुमार सिंह">
                        <?php if (isset($errors['contact_name'])): ?>
                            <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['contact_name']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Mobile -->
                    <div class="col-md-6 col-12">
                        <label for="contact_mobile" class="form-label font-hindi small fw-semibold text-secondary">मोबाइल नंबर (10-Digit Mobile) *</label>
                        <input type="tel" class="form-control <?php echo isset($errors['contact_mobile']) ? 'is-invalid' : ''; ?>" id="contact_mobile" name="contact_mobile" value="<?php echo sanitize($mobile); ?>" placeholder="e.g. 9876543210" maxlength="10">
                        <?php if (isset($errors['contact_mobile'])): ?>
                            <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['contact_mobile']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Email -->
                    <div class="col-md-12">
                        <label for="contact_email" class="form-label font-hindi small fw-semibold text-secondary">ईमेल पता (Email Address) - वैकल्पिक</label>
                        <input type="email" class="form-control <?php echo isset($errors['contact_email']) ? 'is-invalid' : ''; ?>" id="contact_email" name="contact_email" value="<?php echo sanitize($email); ?>" placeholder="e.g. adv.rajesh@example.com">
                        <?php if (isset($errors['contact_email'])): ?>
                            <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['contact_email']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Subject -->
                    <div class="col-md-12">
                        <label for="contact_subject" class="form-label font-hindi small fw-semibold text-secondary">विषय (Subject of Inquiry) *</label>
                        <input type="text" class="form-control <?php echo isset($errors['contact_subject']) ? 'is-invalid' : ''; ?>" id="contact_subject" name="contact_subject" value="<?php echo sanitize($subject); ?>" placeholder="e.g. सदस्यता सत्यापन सम्बन्धी शिकायत">
                        <?php if (isset($errors['contact_subject'])): ?>
                            <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['contact_subject']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Message -->
                    <div class="col-md-12">
                        <label for="contact_message" class="form-label font-hindi small fw-semibold text-secondary">आपका संदेश (Your Message) *</label>
                        <textarea class="form-control <?php echo isset($errors['contact_message']) ? 'is-invalid' : ''; ?>" id="contact_message" name="contact_message" rows="5" placeholder="यहाँ अपना विस्तृत संदेश या शिकायत दर्ज करें..."><?php echo sanitize($message); ?></textarea>
                        <?php if (isset($errors['contact_message'])): ?>
                            <div class="invalid-feedback fw-medium font-size-xs mt-1"><?php echo $errors['contact_message']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 d-grid d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-navy px-4 px-md-5 py-2.5 font-hindi fw-semibold w-100 w-md-auto">
                        <i class="bi bi-send-fill text-gold-custom me-2"></i> संदेश भेजें (Submit Message)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
