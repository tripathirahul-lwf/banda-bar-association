<?php
/**
 * Member Self Profile View - Read Only for official values
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता स्व-प्रोफ़ाइल (My Profile)';
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
        error_log("Failed to fetch member own profile: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../includes/dashboard/footer.php';
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0">मेरी प्रोफाइल विवरण (My Official Profile)</h4>
    <a href="update-request.php" class="btn btn-navy btn-sm font-hindi">
        <i class="bi bi-pencil-square me-1 text-gold-custom"></i>प्रोफाइल सुधार अनुरोध (Request Update)
    </a>
</div>

<div class="row g-4">
    <!-- Left Column: Photo Card -->
    <div class="col-md-4 text-center">
        <div class="p-3 border rounded bg-light-custom">
            <div class="mx-auto rounded-circle overflow-hidden bg-light border border-gold-custom border-2 mb-3 d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                <?php if (!empty($member['photo']) && $member['photo'] !== 'default_advocate.png'): ?>
                    <img src="../uploads/photos/<?php echo e($member['photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="bi bi-person-fill text-muted animate-fade-in" style="font-size: 5rem;"></i>
                <?php endif; ?>
            </div>
            
            <h5 class="fw-bold mb-1 text-navy-custom"><?php echo e($member['full_name']); ?></h5>
            <span class="badge bg-navy-custom text-white font-hindi font-size-xs px-3 py-1.5 mb-3">
                <?php echo e($member['membership_category']); ?>
            </span>
            
            <div class="text-start border-top border-light pt-2 mt-2 font-hindi small text-muted">
                <div class="d-flex justify-content-between py-1">
                    <span>स्थिति (Status):</span>
                    <span class="badge bg-success text-white px-2 py-0.5"><?php echo e(ucfirst($member['membership_status'])); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span>चैंबर (Chamber):</span>
                    <span class="fw-bold text-navy-custom"><?php echo !empty($member['chamber_no']) ? e($member['chamber_no']) : 'N/A'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Full Details -->
    <div class="col-md-8">
        <div class="font-hindi small">
            
            <!-- Section 1: Official Bar Credentials (READ-ONLY) -->
            <div class="border rounded p-3 mb-3 bg-light-custom">
                <span class="text-gold-dark fw-bold d-block mb-2 font-hindi"><i class="bi bi-lock-fill me-1"></i>आधिकारिक विवरण (Official Details - Locked)</span>
                <div class="row g-2 text-muted">
                    <div class="col-md-6">
                        <span>सदस्यता संख्या (Membership Number):</span>
                        <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo e($member['membership_no']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span>पंजीकरण संख्या (Enrollment Number):</span>
                        <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo e($member['enrollment_no']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span>नामांकन तिथि (Enrollment Date):</span>
                        <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo !empty($member['enrollment_date']) ? date('d-m-Y', strtotime($member['enrollment_date'])) : 'N/A'; ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span>संघ से जुड़ने की तिथि (Member Since):</span>
                        <strong class="d-block text-navy-custom mt-0.5 english-text"><?php echo date('d-m-Y', strtotime($member['member_since'])); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal Details -->
            <div class="border rounded p-3 mb-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-person-lines-fill me-1"></i>व्यक्तिगत विवरण (Personal Details)</span>
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted">पिता/पति का नाम:</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom"><?php echo !empty($member['father_or_husband_name']) ? e($member['father_or_husband_name']) : 'N/A'; ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">लिंग (Gender):</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom"><?php echo e($member['gender'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">जन्म तिथि (DOB):</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom english-text"><?php echo !empty($member['date_of_birth']) ? date('d-m-Y', strtotime($member['date_of_birth'])) : 'N/A'; ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">रक्त समूह (Blood Group):</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom"><?php echo e($member['blood_group'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Contact & Privacy -->
            <div class="border rounded p-3 mb-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-envelope-open-fill me-1"></i>संपर्क एवं गोपनीयता (Contact & Privacy)</span>
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted">मोबाइल नंबर:</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom english-text"><?php echo e($member['mobile'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">वैकल्पिक मोबाइल:</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom english-text"><?php echo e($member['alternate_mobile'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted">ईमेल पता (Email):</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom english-text"><?php echo e($member['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">पब्लिक प्रोफाइल पर मोबाइल दिखाएं:</span>
                        <div class="fw-semibold mt-0.5">
                            <?php echo $member['show_mobile_publicly'] == 1 ? '<span class="text-success">हां (Yes)</span>' : '<span class="text-danger">नहीं (Hidden)</span>'; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">पब्लिक प्रोफाइल पर ईमेल दिखाएं:</span>
                        <div class="fw-semibold mt-0.5">
                            <?php echo $member['show_email_publicly'] == 1 ? '<span class="text-success">हां (Yes)</span>' : '<span class="text-danger">नहीं (Hidden)</span>'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Address Details -->
            <div class="border rounded p-3 mb-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-geo-alt-fill me-1"></i>पता विवरण (Addresses)</span>
                <div class="row g-2">
                    <div class="col-md-12">
                        <span class="text-muted">स्थानीय पता (Local Address):</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom"><?php echo !empty($member['local_address']) ? e($member['local_address']) : 'N/A'; ?></div>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted">स्थायी पता (Permanent Address):</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom"><?php echo !empty($member['permanent_address']) ? e($member['permanent_address']) : 'N/A'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Emergency Contacts -->
            <div class="border rounded p-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-telephone-outbound-fill me-1"></i>आपातकालीन संपर्क (Emergency Contact)</span>
                <div class="row g-2">
                    <div class="col-md-6">
                        <span class="text-muted">संपर्क व्यक्ति का नाम:</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom"><?php echo e($member['emergency_contact_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">संपर्क मोबाइल नंबर:</span>
                        <div class="fw-semibold mt-0.5 text-navy-custom english-text"><?php echo e($member['emergency_contact_mobile'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
