<?php
/**
 * Member Chamber Allotment Application
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष आवंटन हेतु आवेदन (Chamber Application)';
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
        error_log("Failed to load member info: " . $e->getMessage());
    }
}

if (!$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: सदस्य जानकारी लोड करने में विफलता।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit;
}

// Active Status check
$is_active = (strcasecmp($member['membership_status'] ?? '', 'active') === 0);

// Fetch Available Chambers for preferences
$chambers = [];
if ($db) {
    try {
        $ch_stmt = $db->query("SELECT * FROM chambers WHERE status IN ('available', 'partially_occupied') ORDER BY chamber_no ASC");
        $chambers = $ch_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading available chambers list: " . $e->getMessage());
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_active) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $preferred_chamber_id = !empty($_POST['preferred_chamber_id']) ? intval($_POST['preferred_chamber_id']) : null;
        $preferred_block = sanitize(trim($_POST['preferred_block'] ?? ''));
        $occupancy_pref = sanitize(trim($_POST['occupancy_pref'] ?? 'single'));
        $reason = sanitize(trim($_POST['reason'] ?? ''));
        $declaration = isset($_POST['declaration']) ? 1 : 0;

        if (!$declaration) {
            $error = 'कृपया आवेदन की पुष्टि हेतु घोषणा पत्र पर सहमति प्रदान करें।';
        } else {
            try {
                $db->beginTransaction();

                // Check if already has a pending application
                $pending_stmt = $db->prepare("SELECT COUNT(*) FROM chamber_applications WHERE member_id = ? AND status IN ('submitted', 'under_review', 'waiting_list')");
                $pending_stmt->execute([$member_id]);
                if ($pending_stmt->fetchColumn() > 0) {
                    throw new Exception('आपका एक आवेदन पहले से लंबित है। नया आवेदन दर्ज नहीं किया जा सकता।');
                }

                // Resolve current chamber if any
                $curr_allot = $db->prepare("SELECT chamber_id FROM chamber_allotments WHERE member_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                $curr_allot->execute([$member_id]);
                $curr_chamber_id = $curr_allot->fetchColumn() ?: null;

                // Generate application no
                $app_no = generateChamberApplicationNumber($db);
                $pref_label = "Block: $preferred_block, Type: $occupancy_pref";

                // Save
                $insert = $db->prepare("
                    INSERT INTO chamber_applications 
                    (application_no, member_id, preferred_chamber_id, chamber_preference, application_date, reason, current_chamber_id, status)
                    VALUES (?, ?, ?, ?, CURRENT_DATE(), ?, ?, 'submitted')
                ");
                $insert->execute([$app_no, $member_id, $preferred_chamber_id, $pref_label, $reason, $curr_chamber_id]);

                // Audit Log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, old_value, new_value, remarks, performed_by) VALUES ('Application Submitted', NULL, ?, ?, ?)");
                $audit->execute([$app_no, 'अधिवक्ता द्वारा स्वयं ऑनलाइन आवेदन प्रस्तुत किया गया।', $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = "कक्ष आवंटन आवेदन संख्या $app_no सफलतापूर्वक जमा कर दिया गया है।";
                header("Location: applications.php");
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'आवेदन दर्ज करने में विफलता: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष / चैंबर आवंटन हेतु आवेदन (New Allotment Request)</h4>
    <a href="applications.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-clock-history me-1"></i>पूर्व आवेदन इतिहास</a>
</div>

<div class="row justify-content-center font-hindi small">
    <div class="col-lg-8">
        <?php if (!$is_active): ?>
            <div class="alert alert-danger py-3 text-center border-0 rounded-3 shadow-xs">
                <i class="bi bi-exclamation-triangle-fill fs-3 mb-2 d-block text-danger"></i>
                <h5 class="fw-bold mb-1">आवेदन अनुपलब्ध (Apply Restricted)</h5>
                <strong class="fs-6 text-dark d-block my-2">आपकी वर्तमान सदस्यता स्थिति के कारण कक्ष आवंटन आवेदन उपलब्ध नहीं है।</strong>
                <span class="text-muted d-block" style="font-size:0.8rem;">कृपया वार्षिक संघ शुल्क अथवा सदस्यता नवीनीकरण बकाये की पुष्टि के लिए संघ कार्यालय से संपर्क करें।</span>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text text-gold-custom me-2"></i>आवंटन आवेदन पत्र (Chamber Allotment Form)</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php insertCSRF(); ?>

                        <!-- Member Read-only Details Grid -->
                        <div class="row g-2 mb-3 bg-light p-2.5 rounded border border-light text-navy-custom">
                            <div class="col-12"><h6 class="fw-bold border-bottom pb-1" style="font-size: 0.8rem;"><i class="bi bi-person-check-fill me-1"></i>सदस्य विवरण (Official Data - Read-only)</h6></div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">अधिवक्ता का नाम:</span>
                                <strong class="english-text"><?php echo e($member['full_name']); ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">सदस्यता संख्या / DBA Code:</span>
                                <strong class="english-text"><?php echo e($member['membership_no']); ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">बार काउंसिल नामांकन संख्या:</span>
                                <strong class="english-text"><?php echo e($member['enrollment_no']); ?></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">सदस्यता स्थिति (Status):</span>
                                <span class="badge bg-success">सक्रिय (Active)</span>
                            </div>
                        </div>

                        <!-- Preferred Chamber Selection -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-navy-custom">प्राथमिकता कक्ष (Preferred Chamber - optional)</label>
                                <select name="preferred_chamber_id" class="form-select form-select-sm">
                                    <option value="">-- कोई भी उपलब्ध कक्ष --</option>
                                    <?php foreach ($chambers as $ch): ?>
                                        <option value="<?php echo $ch['id']; ?>">
                                            Chamber <?php echo e($ch['chamber_no']); ?> (<?php echo e($ch['block_name']); ?>) - ₹<?php echo number_format($ch['monthly_rent'], 2); ?>/माह
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-navy-custom">पसंदीदा ब्लॉक (Preferred Block - optional)</label>
                                <select name="preferred_block" class="form-select form-select-sm">
                                    <option value="">-- कोई विशेष प्राथमिकता नहीं --</option>
                                    <option value="Main Court Block">Main Court Block (मुख्य भवन)</option>
                                    <option value="Library Block">Library Block (पुस्तकालय भवन)</option>
                                    <option value="New Annex Block">New Annex Block (नवीन एनेक्सी)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Shared preference -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy-custom">साझेदारी प्राथमिकता (Occupancy Preference) <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="occupancy_pref" id="pref_single" value="single" checked>
                                    <label class="form-check-label" for="pref_single">
                                        सिंगल आवंटन (Single Occupancy)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="occupancy_pref" id="pref_shared" value="shared">
                                    <label class="form-check-label" for="pref_shared">
                                        साझा आवंटन (Shared Occupancy)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy-custom">आवंटन का आधार / कारण (Reason / Requirement)</label>
                            <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="उदा. पुस्तकालय के समीप वरिष्ठता आधार पर या अन्य विधिक अभ्यास आवश्यकताओं का विवरण लिखें..."></textarea>
                        </div>

                        <!-- Declaration -->
                        <div class="form-check mb-4 border-top pt-3">
                            <input class="form-check-input" type="checkbox" name="declaration" id="declaration" value="1" required>
                            <label class="form-check-label text-navy-custom fw-semibold" for="declaration" style="font-size:0.75rem;">
                                मैं प्रमाणित करता हूँ कि मेरे द्वारा प्रदान की गई उपरोक्त जानकारी सत्य है और मैं जिला अधिवक्ता संघ, बांदा के चैंबर आवंटन नियमों एवं मासिक किराए के भुगतान की शर्तों का पूर्ण निष्ठा से पालन करने का वचन देता हूँ।
                            </label>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-navy btn-sm px-4"><i class="bi bi-send-fill me-1"></i>आवेदन पत्र प्रेषित करें</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
