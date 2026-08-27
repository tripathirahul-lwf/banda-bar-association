<?php
/**
 * Room Allotment Application Review Desk
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'आवंटन आवेदन समीक्षा पटल (Application Review)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = Database::getConnection();
$app = null;
$member = null;
$error = '';
$success = '';

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, m.full_name, m.membership_no, m.enrollment_no, m.membership_status, m.mobile, c.chamber_no AS preferred_chamber_no
            FROM chamber_applications a
            JOIN members m ON m.id = a.member_id
            LEFT JOIN chambers c ON c.id = a.preferred_chamber_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $app = $stmt->fetch();
        
        if ($app) {
            $member = $app; // alias
        }
    } catch (PDOException $e) {
        error_log("Failed loading application view: " . $e->getMessage());
    }
}

if (!$app || !$member) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: आवेदन पत्र नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit;
}

// Fetch member's current active chamber allotments if any
$curr_allot = null;
$outstanding_rent = 0.00;
if ($db) {
    try {
        $curr_stmt = $db->prepare("
            SELECT a.*, c.chamber_no, c.block_name 
            FROM chamber_allotments a
            JOIN chambers c ON c.id = a.chamber_id
            WHERE a.member_id = ? AND a.status = 'active'
            LIMIT 1
        ");
        $curr_stmt->execute([$app['member_id']]);
        $curr_allot = $curr_stmt->fetch();
        if ($curr_allot) {
            $outstanding_rent = getChamberOutstandingRent($curr_allot['id'], $db);
        }
    } catch (PDOException $e) {
        error_log("Failed fetching current allotment: " . $e->getMessage());
    }
}

// Fetch member's historical chamber allotments
$history_allotments = [];
if ($db) {
    try {
        $h_stmt = $db->prepare("
            SELECT a.*, c.chamber_no, c.block_name 
            FROM chamber_allotments a
            JOIN chambers c ON c.id = a.chamber_id
            WHERE a.member_id = ? AND a.status != 'active'
            ORDER BY a.id DESC
        ");
        $h_stmt->execute([$app['member_id']]);
        $history_allotments = $h_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed fetching historical allotments: " . $e->getMessage());
    }
}

// Fetch list of available chambers for final allotment select dropdown
$available_chambers = [];
if ($db) {
    try {
        $ch_stmt = $db->query("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM chamber_allotments WHERE chamber_id = c.id AND status = 'active') AS active_count 
            FROM chambers c
            WHERE c.status IN ('available', 'partially_occupied')
            ORDER BY c.chamber_no ASC
        ");
        $available_chambers = $ch_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed querying available chambers: " . $e->getMessage());
    }
}

// Action form processor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action']);
        $remarks = sanitize(trim($_POST['remarks'] ?? ''));

        try {
            $db->beginTransaction();

            if ($action === 'under_review') {
                $up = $db->prepare("UPDATE chamber_applications SET status = 'under_review', remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $up->execute([$remarks, $_SESSION['user_id'], $id]);
                
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Application Approved', ?, ?)");
                $audit->execute(["आवेदन पत्र संख्या {$app['application_no']} की समीक्षा आरंभ की गई।", $_SESSION['user_id']]);
                
                $db->commit();
                $_SESSION['flash_success'] = 'आवेदन स्थिति सफलतापूर्वक समीक्षा के अधीन में बदली गई।';
                header("Location: applications.php");
                exit;

            } elseif ($action === 'waiting_list') {
                $priority = intval($_POST['priority_no'] ?? 0);
                if ($priority <= 0) {
                    throw new Exception('कृपया प्रतीक्षा सूची प्राथमिकता क्रमांक भरें।');
                }

                $up = $db->prepare("UPDATE chamber_applications SET status = 'waiting_list', priority_no = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $up->execute([$priority, $remarks, $_SESSION['user_id'], $id]);

                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Waiting List Added', ?, ?)");
                $audit->execute(["आवेदन पत्र संख्या {$app['application_no']} को प्रतीक्षा सूची क्रमांक #$priority में जोड़ा गया।", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'आवेदन प्रतीक्षा सूची में जोड़ दिया गया है।';
                header("Location: applications.php");
                exit;

            } elseif ($action === 'approve') {
                // If president approval is required, mark as approved but do not allot chamber yet
                $next_status = CHAMBER_REQUIRE_PRESIDENT_APPROVAL ? 'approved' : 'approved';
                $remarks_label = CHAMBER_REQUIRE_PRESIDENT_APPROVAL ? 'कार्यकारिणी राष्ट्रपति अंतिम अनुमोदन हेतु स्वीकृत।' : 'आवेदन स्वीकृत किया गया।';
                
                $up = $db->prepare("UPDATE chamber_applications SET status = 'approved', remarks = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                $up->execute([$remarks . ' ' . $remarks_label, $_SESSION['user_id'], $id]);

                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Application Approved', ?, ?)");
                $audit->execute(["आवेदन पत्र संख्या {$app['application_no']} स्वीकृत।", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'आवेदन सफलतापूर्वक स्वीकृत किया गया।';
                header("Location: applications.php");
                exit;

            } elseif ($action === 'reject') {
                $up = $db->prepare("UPDATE chamber_applications SET status = 'rejected', remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $up->execute([$remarks, $_SESSION['user_id'], $id]);

                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Application Rejected', ?, ?)");
                $audit->execute(["आवेदन पत्र संख्या {$app['application_no']} अस्वीकृत। टिप्पणी: $remarks", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'आवेदन अस्वीकृत किया गया।';
                header("Location: applications.php");
                exit;

            } elseif ($action === 'allot') {
                $chamber_id = intval($_POST['chamber_id'] ?? 0);
                $eff_date = sanitize(trim($_POST['effective_from'] ?? ''));
                $monthly_rent = floatval($_POST['monthly_rent'] ?? 0.00);
                $security_deposit = floatval($_POST['security_deposit'] ?? 0.00);
                
                if ($chamber_id <= 0 || empty($eff_date) || $monthly_rent <= 0) {
                    throw new Exception('कृपया चैंबर संख्या, मासिक किराया और आवंटन प्रभावी तिथि सही भरें।');
                }

                // Database Transaction locks
                // 1. Lock/Fetch Chamber
                $ch_stmt = $db->prepare("SELECT * FROM chambers WHERE id = ? FOR UPDATE");
                $ch_stmt->execute([$chamber_id]);
                $target_chamber = $ch_stmt->fetch();
                if (!$target_chamber) {
                    throw new Exception('चयनित चैंबर डेटाबेस में नहीं मिला।');
                }

                // Check capacity
                $occ_stmt = $db->prepare("SELECT COUNT(*) FROM chamber_allotments WHERE chamber_id = ? AND status = 'active' FOR UPDATE");
                $occ_stmt->execute([$chamber_id]);
                $active_occ = $occ_stmt->fetchColumn();

                if ($active_occ >= $target_chamber['capacity']) {
                    throw new Exception("चैंबर संख्या '{$target_chamber['chamber_no']}' में क्षमता उपलब्ध नहीं है। (Max: {$target_chamber['capacity']} occupants)");
                }

                // Generate Allotment Number
                $allot_no = generateChamberAllotmentNumber($db);

                // Insert Allotment
                $allot_ins = $db->prepare("
                    INSERT INTO chamber_allotments 
                    (chamber_id, member_id, application_id, allotment_no, allotment_date, effective_from, monthly_rent, security_deposit, status, allotted_by)
                    VALUES (?, ?, ?, ?, CURRENT_DATE(), ?, ?, ?, 'active', ?)
                ");
                $allot_ins->execute([$chamber_id, $app['member_id'], $id, $allot_no, $eff_date, $monthly_rent, $security_deposit, $_SESSION['user_id']]);
                $new_allotment_id = $db->lastInsertId();

                // Generate Security Deposit Record
                $receipt_no = 'REC-SD-' . date('Y') . '-' . sprintf("%04d", rand(100, 999));
                $dep_ins = $db->prepare("
                    INSERT INTO chamber_security_deposits 
                    (allotment_id, amount, payment_date, payment_mode, receipt_no, status, remarks, created_by)
                    VALUES (?, ?, CURRENT_DATE(), 'Cash', ?, 'pending', 'आवंटन समय पर देय जमानत राशि', ?)
                ");
                $dep_ins->execute([$new_allotment_id, $security_deposit, $receipt_no, $_SESSION['user_id']]);

                // Update Chamber Status
                $new_occ_count = $active_occ + 1;
                $new_status = ($new_occ_count >= $target_chamber['capacity']) ? 'occupied' : 'partially_occupied';
                
                $ch_up = $db->prepare("UPDATE chambers SET status = ? WHERE id = ?");
                $ch_up->execute([$new_status, $chamber_id]);

                // Close application
                $app_up = $db->prepare("UPDATE chamber_applications SET status = 'allotted', remarks = ? WHERE id = ?");
                $app_up->execute(['चैंबर सफलतापूर्वक आवंटित। आवंटन सं: ' . $allot_no, $id]);

                // Log Audit
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, old_value, new_value, remarks, performed_by) VALUES ('Chamber Allotted', ?, ?, ?, ?)");
                $audit->execute([$app['application_no'], $allot_no, "चैम्बर {$target_chamber['chamber_no']} आवंटित। आवंटन पत्र संख्या {$allot_no}", $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = "कक्ष आवंटन संख्या $allot_no सफलतापूर्वक पूर्ण कर लिया गया है।";
                header("Location: applications.php");
                exit;
            }

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'कार्रवाई करने में विफलता: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">आवेदन पत्र विवरण एवं समीक्षा (Application Review Desk)</h4>
    <a href="applications.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>वापस जाएं</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left Column: Member details and application info -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-circle text-gold-custom me-2"></i>आवेदक अधिवक्ता का विवरण (Member Info)</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <span class="text-muted d-block">अधिवक्ता का नाम:</span>
                        <strong class="fs-6"><?php echo e($app['full_name']); ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">मोबाइल नम्बर:</span>
                        <strong class="english-text"><?php echo e($app['mobile'] ?: '-'); ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">सदस्यता संख्या:</span>
                        <strong class="english-text fw-bold text-navy-custom"><?php echo e($app['membership_no']); ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">पंजीकरण स्थिति (Membership Status):</span>
                        <span class="badge bg-success"><?php echo e($app['membership_status']); ?></span>
                    </div>
                </div>

                <!-- Existing Allotment / Outstanding Rent -->
                <div class="p-2 border rounded bg-light mb-3" style="font-size:0.75rem;">
                    <strong>वर्तमान आवंटित चैम्बर:</strong> 
                    <?php if ($curr_allot): ?>
                        <span class="text-danger fw-bold">Chamber <?php echo e($curr_allot['chamber_no']); ?> (<?php echo e($curr_allot['block_name']); ?>)</span><br>
                        <strong>कुल किराया बकाया:</strong> <span class="text-danger fw-bold">₹<?php echo number_format($outstanding_rent, 2); ?></span>
                    <?php else: ?>
                        <span class="text-success fw-bold">कोई कक्ष आवंटित नहीं है।</span>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold border-bottom pb-1 mb-2 mt-4 text-navy-custom"><i class="bi bi-file-earmark-check me-1"></i>आवेदन पत्र की जानकारी (Application Details)</h6>
                <div class="row g-2">
                    <div class="col-6">
                        <span class="text-muted d-block">आवेदन क्रमांक:</span>
                        <strong class="english-text"><?php echo e($app['application_no']); ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">आवेदन की तिथि:</span>
                        <strong class="english-text"><?php echo date('d-m-Y', strtotime($app['application_date'])); ?></strong>
                    </div>
                    <div class="col-12">
                        <span class="text-muted d-block">वरीयता प्राथमिकता (Preference Details):</span>
                        <strong><?php echo e($app['chamber_preference']); ?></strong>
                    </div>
                    <?php if ($app['preferred_chamber_no']): ?>
                        <div class="col-12">
                            <span class="text-muted d-block">चयनित विशेष चैंबर पसंद (Preferred Chamber):</span>
                            <strong class="text-success english-text">Chamber <?php echo e($app['preferred_chamber_no']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <span class="text-muted d-block">आवेदन का विवरण / कारण:</span>
                        <p class="mb-0 bg-light p-2 rounded"><?php echo e($app['reason'] ?: 'कोई विवरण उपलब्ध नहीं।'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historical Occupancy Dues -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-clock-history me-2"></i>पूर्व आवंटन इतिहास (Chamber History)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.75rem;">
                        <thead>
                            <tr class="table-light">
                                <th>चैंबर नं</th>
                                <th>आवंटन सं.</th>
                                <th>आवंटन तिथि</th>
                                <th>निष्कासन तिथि</th>
                                <th>कारण</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history_allotments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-2 text-muted">कोई पूर्व इतिहास उपलब्ध नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history_allotments as $ha): ?>
                                    <tr>
                                        <td class="fw-bold english-text">Chamber <?php echo e($ha['chamber_no']); ?></td>
                                        <td class="english-text"><?php echo e($ha['allotment_no']); ?></td>
                                        <td class="english-text"><?php echo date('d-m-Y', strtotime($ha['effective_from'])); ?></td>
                                        <td class="english-text"><?php echo date('d-m-Y', strtotime($ha['vacated_at'])); ?></td>
                                        <td><?php echo e($ha['vacate_reason']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Approval Action Center -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock-fill text-gold-custom me-2"></i>आवेदन कार्यवाही केंद्र (Review Actions)</h6>
            </div>
            <div class="card-body">
                <!-- Under review action form -->
                <form method="POST" action="" class="mb-3 border-bottom pb-3">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="under_review">
                    <div class="mb-2">
                        <label class="form-label fw-bold">1. समीक्षा प्रारंभ करें (Under Review)</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="1" placeholder="समीक्षा टिप्पणी..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100 text-dark fw-bold"><i class="bi bi-clock-history me-1"></i>समीक्षा के अधीन करें (Mark Under Review)</button>
                </form>

                <!-- Waiting list form -->
                <form method="POST" action="" class="mb-3 border-bottom pb-3">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="waiting_list">
                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label fw-bold">2. प्रतीक्षा सूची में डालें (Waiting List)</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="टिप्पणी...">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold">वरीयता संख्या</label>
                            <input type="number" name="priority_no" class="form-control form-control-sm" required placeholder="1">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-hourglass-split me-1"></i>प्रतीक्षा सूची में डालें</button>
                </form>

                <!-- Reject form -->
                <form method="POST" action="" class="mb-3 border-bottom pb-3" onsubmit="return confirm('क्या आप वास्तव में यह आवेदन अस्वीकृत करना चाहते हैं?');">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-2">
                        <label class="form-label fw-bold">3. अस्वीकृत करें (Reject Request)</label>
                        <input type="text" name="remarks" class="form-control form-control-sm" required placeholder="अस्वीकृति का स्पष्ट कारण लिखें...">
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm w-100"><i class="bi bi-x-circle me-1"></i>आवेदन अस्वीकृत करें</button>
                </form>

                <!-- Final Allotment form (Direct operational capability) -->
                <?php if ($app['status'] !== 'allotted'): ?>
                    <form method="POST" action="" onsubmit="return confirm('क्या आप चैंबर का अंतिम आवंटन करना चाहते हैं?');">
                        <?php insertCSRF(); ?>
                        <input type="hidden" name="action" value="allot">

                        <h6 class="fw-bold mb-2 text-success"><i class="bi bi-key-fill me-1"></i>4. अंतिम चैंबर आवंटन करें (Allot Chamber)</h6>
                        
                        <div class="mb-2">
                            <label class="form-label fw-bold text-navy-custom">चैंबर चुनें (Select Available Room) <span class="text-danger">*</span></label>
                            <select name="chamber_id" class="form-select form-select-sm" required id="allot_chamber_select" onchange="updateChamberRentValues();">
                                <option value="">-- उपलब्ध चैंबर सूची --</option>
                                <?php foreach ($available_chambers as $ac): ?>
                                    <option value="<?php echo $ac['id']; ?>" data-rent="<?php echo $ac['monthly_rent']; ?>" data-deposit="<?php echo $ac['security_deposit']; ?>">
                                        Chamber <?php echo e($ac['chamber_no']); ?> (<?php echo e($ac['block_name']); ?>) - Occupied: <?php echo $ac['active_count']; ?>/<?php echo $ac['capacity']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label fw-bold text-navy-custom">मासिक किराया (Rent) *</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="monthly_rent" id="allot_rent" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold text-navy-custom">जमानत सुरक्षा निधि *</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="security_deposit" id="allot_deposit" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-navy-custom">आवंटन प्रभावी तिथि (Effective Date) *</label>
                            <input type="date" name="effective_from" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check-circle-fill me-1"></i>चैंबर आवंटित करें (Complete Allotment)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateChamberRentValues() {
    var select = document.getElementById('allot_chamber_select');
    var selectedOption = select.options[select.selectedIndex];
    if(selectedOption.value !== "") {
        document.getElementById('allot_rent').value = selectedOption.getAttribute('data-rent');
        document.getElementById('allot_deposit').value = selectedOption.getAttribute('data-deposit');
    } else {
        document.getElementById('allot_rent').value = "";
        document.getElementById('allot_deposit').value = "";
    }
}
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
