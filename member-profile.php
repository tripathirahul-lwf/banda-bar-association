<?php
/**
 * Public Safe Advocate Profile View - District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अधिवक्ता प्रोफ़ाइल विवरण (Advocate Profile)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$member = null;

$db = Database::getConnection();
if ($db && $member_id > 0) {
    try {
        // Enforce membership_status = 'active' AND is_public = 1 for public visibility
        $stmt = $db->prepare("
            SELECT m.*, ob.designation_override, p.position_name_hindi 
            FROM members m 
            LEFT JOIN office_bearers ob ON ob.member_id = m.id AND ob.status = 'active'
            LEFT JOIN office_bearer_positions p ON p.id = ob.position_id
            LEFT JOIN office_bearer_terms t ON t.id = ob.term_id AND t.status = 'active'
            WHERE m.id = ? AND m.membership_status = 'active' AND m.is_public = 1
        ");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in profile fetch: " . $e->getMessage());
    }
}
?>

<!-- Banner Navigation Back -->
<div class="mb-4">
    <a href="members.php" class="btn btn-outline-navy btn-sm font-hindi fw-semibold">
        <i class="bi bi-arrow-left me-1"></i>अधिवक्ता सूची पर वापस जाएं (Back to List)
    </a>
</div>

<?php if (!$member): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 p-4 text-center font-hindi">
        <i class="bi bi-exclamation-octagon-fill display-5 text-danger d-block mb-2"></i>
        <h4>सदस्य प्रोफाइल नहीं मिली (Profile Not Found)</h4>
        <p class="mb-0 text-muted small">प्रदान की गई आईडी से संबद्ध कोई सक्रिय सदस्य विवरण मौजूद नहीं है अथवा प्रोफाइल को सार्वजनिक रूप से छिपाया गया है।</p>
    </div>
<?php else: ?>
    <div class="row g-4 mb-5">
        <!-- Left Side: Profile Card -->
        <div class="col-lg-4">
            <div class="bg-white p-4 rounded-3 shadow-sm border border-light text-center h-100">
                <div class="mx-auto rounded-circle overflow-hidden bg-light border border-gold-custom border-2 mb-3 d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; position: relative;">
                    <?php if (!empty($member['photo']) && $member['photo'] !== 'default_advocate.png'): ?>
                        <img src="uploads/photos/<?php echo e($member['photo']); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill text-muted" style="font-size: 6rem;"></i>
                    <?php endif; ?>
                </div>
                
                <h4 class="fw-bold text-navy-custom english-text mb-1"><?php echo e($member['full_name']); ?></h4>
                <p class="text-muted font-hindi small mb-3"><i class="bi bi-hash text-gold-dark me-1"></i>पंजीकृत सदस्य | Registered Member</p>
                
                <div class="mb-4">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-4 py-2 font-hindi fs-6">सक्रिय (Active Member)</span>
                </div>

                <?php 
                $curr_designation = $member['designation_override'] ?: $member['position_name_hindi'];
                if (!empty($curr_designation)): 
                ?>
                    <div class="mb-3">
                        <span class="badge bg-navy-custom text-white border-start border-3 border-warning px-3 py-1.5 font-hindi"><?php echo e($curr_designation); ?></span>
                    </div>
                <?php endif; ?>

                <hr class="border-gold-custom my-4">

                <!-- Verification QR Code Placeholder -->
                <div class="p-3 border border-light bg-light-custom rounded-3">
                    <span class="d-block font-size-xs text-navy-custom fw-semibold mb-2 font-hindi"><i class="bi bi-qr-code text-gold-dark me-1"></i>डिजिटल सत्यापन (QR Verification)</span>
                    <div class="bg-white p-3 d-inline-block rounded border border-light mx-auto mb-2" style="width: 130px; height: 130px;">
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 border border-secondary border-dashed text-muted">
                            <i class="bi bi-qr-code fs-1"></i>
                            <span style="font-size: 0.5rem;" class="english-text">DBA VERIFIED</span>
                        </div>
                    </div>
                    <small class="d-block text-muted font-hindi" style="font-size: 0.75rem;">बार एसोसिएशन द्वारा प्रमाणित सुरक्षा क्यूआर। इसे स्कैन कर डिजिटल पहचान सत्यापित की जा सकती है।</small>
                </div>
            </div>
        </div>

        <!-- Right Side: Safe Public Profile Information -->
        <div class="col-lg-8">
            <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                <h3 class="text-navy-custom font-hindi fw-bold mb-4 border-bottom border-gold-custom pb-2">सदस्यता विवरण (Profile Details)</h3>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">अधिवक्ता का नाम (Advocate Name)</span>
                        <strong class="text-navy-custom english-text"><?php echo e($member['full_name']); ?></strong>
                    </div>
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">सदस्यता संख्या (Membership Number)</span>
                        <strong class="text-navy-custom english-text"><?php echo e($member['membership_no']); ?></strong>
                    </div>
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">पंजीकरण संख्या (Enrollment Number)</span>
                        <strong class="text-navy-custom english-text"><?php echo e($member['enrollment_no']); ?></strong>
                    </div>
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">नामांकन वर्ष (Enrollment Year)</span>
                        <strong class="text-navy-custom english-text"><?php echo e($member['enrollment_year']); ?></strong>
                    </div>
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">चेम्बर आवंटन (Chamber Number)</span>
                        <strong class="text-navy-custom font-hindi text-secondary-custom"><?php echo !empty($member['chamber_no']) ? 'Chamber ' . e($member['chamber_no']) : 'चैंबर आवंटित नहीं'; ?></strong>
                    </div>
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">संघ से जुड़ने की तिथि (Member Since)</span>
                        <strong class="text-navy-custom english-text"><?php echo date('d-m-Y', strtotime($member['member_since'])); ?></strong>
                    </div>
                    
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">कार्य क्षेत्र (Practice Area)</span>
                        <strong class="text-navy-custom font-hindi"><?php echo !empty($member['practice_area']) ? e($member['practice_area']) : 'सामान्य विधिक अभ्यास'; ?></strong>
                    </div>

                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">श्रेणी (Category)</span>
                        <strong class="text-navy-custom font-hindi"><?php echo e($member['membership_category']); ?></strong>
                    </div>

                    <?php if (!empty($curr_designation)): ?>
                        <div class="col-md-12 border-bottom border-light pb-2">
                            <span class="d-block text-muted font-hindi small">कार्यकारिणी पदभार (Office Designation)</span>
                            <strong class="text-gold-dark font-hindi"><?php echo e($curr_designation); ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- Mobile and Email only if allowed by user preferences -->
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">मोबाइल नंबर (Mobile)</span>
                        <?php if ($member['show_mobile_publicly'] == 1): ?>
                            <strong class="text-navy-custom english-text"><?php echo e($member['mobile']); ?></strong>
                        <?php else: ?>
                            <strong class="text-muted font-hindi font-size-xs" style="font-style:italic;"><i class="bi bi-lock-fill text-gold-custom me-1"></i>सुरक्षित/गोपनीय</strong>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 border-bottom border-light pb-2">
                        <span class="d-block text-muted font-hindi small">ईमेल पता (Email)</span>
                        <?php if ($member['show_email_publicly'] == 1): ?>
                            <strong class="text-navy-custom english-text"><?php echo e($member['email']); ?></strong>
                        <?php else: ?>
                            <strong class="text-muted font-hindi font-size-xs" style="font-style:italic;"><i class="bi bi-lock-fill text-gold-custom me-1"></i>सुरक्षित/गोपनीय</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Safe Public Data Message -->
                <div class="p-3 bg-light-custom rounded border border-light mt-auto">
                    <h6 class="fw-bold font-hindi text-navy-custom mb-2"><i class="bi bi-info-circle-fill text-gold-custom me-1"></i>विवरण सुरक्षा सम्बन्धी सूचना</h6>
                    <p class="small text-muted font-hindi mb-0" style="line-height: 1.6;">
                        बार काउंसिल उत्तर प्रदेश के निजता मानकों के अनुपालन में सदस्यों के व्यक्तिगत संपर्क नंबर, गृह पता, रक्त समूह तथा अन्य विधिक पहचान पत्र (आधार, पैन, जन्म तिथि, हस्ताक्षर) सार्वजनिक पटल पर प्रदर्शित नहीं किए गए हैं। आवश्यक विधिक कार्यों हेतु कृपया सीधे न्यायालय परिसर में संबंधित सदस्य के चेंबर में संपर्क स्थापित करें।
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
require_once 'includes/footer.php';
?>
