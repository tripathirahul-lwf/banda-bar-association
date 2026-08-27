<?php
/**
 * Member Chamber Dashboard / Info Panel
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'मेरा कक्ष/चैंबर विवरण (My Chamber Details)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Ensure user has Member role
requireRole('member');

$db = Database::getConnection();
$member_id = $_SESSION['member_id'];
$member = null;
$error = '';
$success = '';

if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed loading member profile: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य रिकॉर्ड लोड करने में विफलता।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit;
}

// Get Chamber Summary
$chamber = getMemberChamberSummary($member_id, $db);

// Handle Vacate Request Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_vacate'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $reason = sanitize(trim($_POST['vacate_reason'] ?? ''));
        $req_date = sanitize(trim($_POST['requested_date'] ?? ''));

        if (empty($reason) || empty($req_date)) {
            $error = 'कृपया खाली करने का कारण एवं तिथि अवश्य प्रेषित करें।';
        } else {
            try {
                $db->beginTransaction();
                
                // Verify pending request doesn't exist
                $pen = $db->prepare("SELECT COUNT(*) FROM chamber_vacate_requests WHERE allotment_id = ? AND status = 'submitted'");
                $pen->execute([$chamber['allotment_id']]);
                if ($pen->fetchColumn() > 0) {
                    throw new Exception('आपका एक निष्कासन आवेदन पहले से लंबित है।');
                }

                // Insert
                $ins = $db->prepare("
                    INSERT INTO chamber_vacate_requests (member_id, allotment_id, requested_date, reason, status) 
                    VALUES (?, ?, ?, ?, 'submitted')
                ");
                $ins->execute([$member_id, $chamber['allotment_id'], $req_date, $reason]);

                // Audit Log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Vacate Requested', ?, ?)");
                $audit->execute(['चैम्बर खाली करने हेतु आवेदन प्रेषित। तिथि: ' . $req_date, $_SESSION['user_id']]);

                $db->commit();
                $success = 'चैंबर खाली करने का अनुरोध सफलतापूर्वक भेज दिया गया है। स्वीकृति की प्रतीक्षा करें।';
                // Reload summary
                $chamber = getMemberChamberSummary($member_id, $db);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'अनुरोध विफल: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष/चैंबर सदस्य सेवा पटल (Chamber Service Hub)</h4>
    <div class="d-flex gap-2">
        <a href="rent-history.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-clock-history me-1"></i>किराया इतिहास (Rent History)</a>
        <a href="applications.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-card-list me-1"></i>आवेदन ट्रैक करें</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small">
    <?php if (!$chamber['has_chamber']): ?>
        <!-- Not Allotted Layout -->
        <div class="col-12">
            <div class="text-center py-5 bg-white border border-light rounded shadow-sm">
                <div class="logo-placeholder bg-light text-navy-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 70px; height: 70px;">
                    <i class="bi bi-door-closed fs-1"></i>
                </div>
                <h5 class="fw-bold mb-2">कोई कक्ष आवंटित नहीं है।</h5>
                <p class="text-muted mb-4" style="font-size:0.8rem;">आपकी प्रोफाइल के अंतर्गत वर्तमान में जिला अधिवक्ता संघ द्वारा कोई कमरा या केबिन आवंटित नहीं पाया गया है।</p>
                <a href="apply.php" class="btn btn-navy px-4 py-2 fw-semibold">आवंटन हेतु नया आवेदन करें (Apply Now)</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Allotted Chamber Details Info Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm text-navy-custom h-100">
                <div class="card-header bg-navy-custom text-white py-2.5">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-house-check text-gold-custom me-2"></i>आवंटित चैंबर विवरण (Active Allotment Details)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 border-bottom pb-2">
                            <span class="text-muted d-block">चैम्बर संख्या (Chamber No):</span>
                            <strong class="fs-5 text-success english-text"><?php echo e($chamber['chamber_no']); ?></strong>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <span class="text-muted d-block">भवन / ब्लॉक परिसर:</span>
                            <strong class="fs-6"><?php echo e($chamber['block_name'] ?: 'General Block'); ?></strong>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <span class="text-muted d-block">तल (Floor):</span>
                            <strong class="english-text"><?php echo e($chamber['floor'] ?: 'N/A'); ?></strong>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <span class="text-muted d-block font-hindi">लाइसेंस मासिक किराया (Monthly Rent):</span>
                            <strong class="english-text text-danger">₹<?php echo number_format($chamber['monthly_rent'], 2); ?>/माह</strong>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <span class="text-muted d-block">आवंटन पत्र क्रमांक:</span>
                            <strong class="english-text text-navy-custom"><?php echo e($chamber['allotment_no']); ?></strong>
                        </div>
                        <div class="col-6 border-bottom pb-2">
                            <span class="text-muted d-block">आवंटन तिथि:</span>
                            <strong class="english-text"><?php echo date('d-m-Y', strtotime($chamber['allotment_date'])); ?></strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">जमानत राशि देय (Deposit Due):</span>
                            <strong class="english-text">₹<?php echo number_format($chamber['security_deposit'], 2); ?></strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block text-success fw-bold">जमानत राशि प्राप्त (Deposit Paid):</span>
                            <strong class="english-text text-success">₹<?php echo number_format($chamber['security_deposit_paid'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rent Outstanding Status Widget -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100 text-navy-custom">
                <div class="card-header bg-light py-2.5">
                    <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-wallet2 text-gold-dark me-2"></i>किराया देयता स्थिति (Rent Due Summary)</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="text-center py-3 bg-light rounded shadow-xs mb-3">
                        <span class="text-muted d-block font-hindi">कुल बकाया किराया राशि (Outstanding)</span>
                        <strong class="fs-2 text-danger d-block english-text my-2">₹<?php echo number_format($chamber['outstanding_rent'], 2); ?></strong>
                        <?php 
                        $status_label = ($chamber['outstanding_rent'] <= 0) ? 'किराया पूर्ण चुकता (Paid)' : 'किराया बकाया लंबित (Rent Overdue)';
                        $status_class = ($chamber['outstanding_rent'] <= 0) ? 'bg-success' : 'bg-danger';
                        ?>
                        <span class="badge <?php echo $status_class; ?> fs-6 font-hindi"><?php echo $status_label; ?></span>
                    </div>

                    <div class="alert alert-secondary border-0 p-2.5 mb-3" style="font-size:0.72rem; line-height:1.4;">
                        <i class="bi bi-info-circle me-1 text-primary"></i><strong>सूचना:</strong> मासिक किराया भुगतान नकद अथवा बैंक माध्यम से बार एसोसिएशन काउंटर पर ही स्वीकार्य है। ऑनलाइन रिकॉर्डिंग हेतु रसीद अवश्य प्राप्त करें।
                    </div>

                    <!-- Trigger Request Vacate Modal -->
                    <button class="btn btn-sm btn-outline-danger w-100 py-2 font-hindi fw-semibold" data-bs-toggle="modal" data-bs-target="#vacateModal">
                        <i class="bi bi-door-open me-1"></i> चेंबर खाली करने का अनुरोध (Request Vacate)
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Vacate Chamber Request Modal -->
<?php if ($chamber['has_chamber']): ?>
<div class="modal fade font-hindi small" id="vacateModal" tabindex="-1" aria-labelledby="vacateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold" id="vacateModalLabel">चेंबर निष्कासन हेतु अनुरोध आवेदन (Request Chamber Vacation)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-shadow="none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-navy-custom">
                <!-- Check outstanding -->
                <?php if ($chamber['outstanding_rent'] > 0): ?>
                    <div class="alert alert-warning p-2.5" style="font-size:0.75rem;">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i><strong>चेतावनी:</strong> आपका <strong>₹<?php echo number_format($chamber['outstanding_rent'], 2); ?></strong> किराया बकाया लंबित है। बकाया राशि का भुगतान किए बिना आवंटन को अंतिम रूप से समाप्त नहीं किया जा सकता।
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="request_vacate" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">चैंबर खाली करने की प्रस्तावित तिथि <span class="text-danger">*</span></label>
                        <input type="date" name="requested_date" class="form-control form-control-sm" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">खाली करने का स्पष्ट कारण <span class="text-danger">*</span></label>
                        <textarea name="vacate_reason" class="form-control form-control-sm" rows="3" required placeholder="उदा. व्यक्तिगत कारण, प्रेक्टिस स्थान परिवर्तन या अन्य..."></textarea>
                    </div>

                    <p class="text-muted border-top pt-2" style="font-size:0.68rem; line-height:1.3;">
                        * नोट: यह आवेदन केवल एक अनुरोध है। अंतिम स्वीकृति एवं बकायों के पूर्ण समायोजन (Settlement) के उपरांत ही आवंटन रद्द माना जाएगा।
                    </p>

                    <div class="text-end mt-3 border-top pt-2">
                        <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-xs btn-danger px-3"><i class="bi bi-send-fill me-1"></i>अनुरोध भेजें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
