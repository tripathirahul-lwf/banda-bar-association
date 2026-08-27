<?php
/**
 * President - View Detailed Member Profile (Read-Only)
 * District Bar Association, Banda
 */

$pageTitle = 'अधिवक्ता प्रोफ़ाइल निरीक्षण';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President Role
requireRole('president');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$member = null;

$db = Database::getConnection();
if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("President member detail query failure: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit();
}
?>

<div class="mb-3 font-hindi">
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>सदस्य सूची पर वापस जाएं</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">विस्तृत सदस्य प्रोफ़ाइल निरीक्षण</h4>
    <span class="badge bg-warning text-navy-custom px-3 py-1 fw-bold">अध्यक्ष जोन</span>
</div>

<div class="row g-4 font-hindi small">
    <!-- Photo and Quick Status Card -->
    <div class="col-md-4 text-center">
        <div class="p-3 border rounded bg-light-custom">
            <div class="mx-auto rounded-circle overflow-hidden bg-light border border-gold-custom border-2 mb-3 d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                <?php if (!empty($member['photo']) && $member['photo'] !== 'default_advocate.png'): ?>
                    <img src="../../uploads/photos/<?php echo e($member['photo']); ?>" alt="Photo" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="bi bi-person-fill text-muted" style="font-size: 5rem;"></i>
                <?php endif; ?>
            </div>
            
            <h5 class="fw-bold mb-1 text-navy-custom"><?php echo e($member['full_name']); ?></h5>
            <span class="badge bg-navy-custom text-white px-3 py-1.5 mb-2"><?php echo e($member['membership_category']); ?></span>
            
            <div class="text-start border-top border-light pt-2 mt-2 font-hindi small text-muted">
                <div class="d-flex justify-content-between py-1">
                    <span>सदस्यता स्थिति (Status):</span>
                    <span class="fw-bold text-navy-custom"><?php echo e(ucfirst($member['membership_status'])); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span>चैंबर (Chamber):</span>
                    <span class="fw-bold text-navy-custom"><?php echo !empty($member['chamber_no']) ? e($member['chamber_no']) : 'N/A'; ?></span>
                </div>
                <?php if (!empty($member['status_reason'])): ?>
                    <div class="mt-2 p-2 bg-white rounded border border-warning">
                        <span class="d-block fw-bold text-danger">निलंबन/बदलाव का कारण:</span>
                        <span style="font-size: 0.75rem;"><?php echo e($member['status_reason']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Member Details List -->
    <div class="col-md-8">
        <div class="bg-white p-2 h-100">
            <!-- Section 1: Credentials -->
            <div class="border rounded p-3 mb-3 bg-light-custom">
                <span class="text-gold-dark fw-bold d-block mb-2"><i class="bi bi-shield-check me-1"></i>आधिकारिक विवरण (Official Credentials)</span>
                <div class="row g-2">
                    <div class="col-md-6">
                        <span class="text-muted">सदस्यता संख्या (Membership No):</span>
                        <strong class="d-block text-navy-custom english-text mt-0.5"><?php echo e($member['membership_no']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">पंजीकरण संख्या (Enrollment No):</span>
                        <strong class="d-block text-navy-custom english-text mt-0.5"><?php echo e($member['enrollment_no']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">नामांकन तिथि (Enrollment Date):</span>
                        <strong class="d-block text-navy-custom english-text mt-0.5"><?php echo !empty($member['enrollment_date']) ? date('d-m-Y', strtotime($member['enrollment_date'])) : 'N/A'; ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">संघ सदस्य तिथि (Member Since):</span>
                        <strong class="d-block text-navy-custom english-text mt-0.5"><?php echo date('d-m-Y', strtotime($member['member_since'])); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Section 2: Personal -->
            <div class="border rounded p-3 mb-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-person-lines-fill me-1"></i>व्यक्तिगत विवरण (Personal Details)</span>
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted">पिता/पति का नाम:</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['father_or_husband_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">लिंग (Gender) / जन्म तिथि (DOB):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['gender'] ?? 'N/A'); ?> | <?php echo !empty($member['date_of_birth']) ? date('d-m-Y', strtotime($member['date_of_birth'])) : 'N/A'; ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">रक्त समूह (Blood Group):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['blood_group'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">कार्य क्षेत्र (Practice Area):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['practice_area'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Contact & Address -->
            <div class="border rounded p-3 mb-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-telephone-fill me-1"></i>संपर्क एवं पता (Contact & Address)</span>
                <div class="row g-2">
                    <div class="col-md-6">
                        <span class="text-muted">मोबाइल (Mobile):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5 english-text"><?php echo e($member['mobile'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">ईमेल पता (Email):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5 english-text"><?php echo e($member['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-12 mt-2">
                        <span class="text-muted">स्थानीय पता (Local Address):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['local_address'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted">स्थायी पता (Permanent Address):</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['permanent_address'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Emergency Contacts -->
            <div class="border rounded p-3">
                <span class="text-navy-custom fw-bold d-block mb-2 border-bottom pb-1"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>आपातकालीन संपर्क (Emergency Contact)</span>
                <div class="row g-2">
                    <div class="col-md-6">
                        <span class="text-muted">आपातकालीन संपर्क व्यक्ति:</span>
                        <div class="fw-semibold text-navy-custom mt-0.5"><?php echo e($member['emergency_contact_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">आपातकालीन संपर्क मोबाइल:</span>
                        <div class="fw-semibold text-navy-custom mt-0.5 english-text"><?php echo e($member['emergency_contact_mobile'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
