<?php
/**
 * Room / Chamber Detailed Master Card & Operations View
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष / चैंबर विस्तृत विवरण (Chamber View Card)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = Database::getConnection();
$chamber = null;
$error = '';
$success = '';

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM chambers WHERE id = ?");
        $stmt->execute([$id]);
        $chamber = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load chamber details: " . $e->getMessage());
    }
}

if (!$chamber) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: चैंबर रिकॉर्ड नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit;
}

// Handle Vacate Allotment Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vacate') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $allotment_id = intval($_POST['allotment_id'] ?? 0);
        $vacate_date = sanitize(trim($_POST['vacate_date'] ?? ''));
        $vacate_reason = sanitize(trim($_POST['vacate_reason'] ?? ''));
        $deposit_status = sanitize(trim($_POST['deposit_status'] ?? 'refunded'));
        
        if ($allotment_id <= 0 || empty($vacate_date) || empty($vacate_reason)) {
            $error = 'कृपया खाली करने की तिथि एवं कारण अवश्य लिखें।';
        } else {
            try {
                $db->beginTransaction();

                // Fetch allotment details
                $allot_stmt = $db->prepare("SELECT * FROM chamber_allotments WHERE id = ? AND status = 'active'");
                $allot_stmt->execute([$allotment_id]);
                $allot = $allot_stmt->fetch();
                if (!$allot) {
                    throw new Exception('सक्रिय आवंटन रिकॉर्ड नहीं मिला।');
                }

                // Check outstanding rent
                $outstanding = getChamberOutstandingRent($allotment_id, $db);

                // Update allotment
                $up_allot = $db->prepare("
                    UPDATE chamber_allotments 
                    SET status = 'vacated', vacated_at = ?, vacate_reason = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $up_allot->execute([$vacate_date, $vacate_reason, $allotment_id]);

                // Update deposit status
                $up_dep = $db->prepare("
                    UPDATE chamber_security_deposits 
                    SET status = ?, remarks = ? 
                    WHERE allotment_id = ? AND status = 'paid'
                ");
                $up_dep->execute([$deposit_status, "निष्कासन उपरांत समायोजन: $vacate_reason", $allotment_id]);

                // Check remaining active occupants in this chamber to update chamber status
                $occ_stmt = $db->prepare("SELECT COUNT(*) FROM chamber_allotments WHERE chamber_id = ? AND status = 'active'");
                $occ_stmt->execute([$chamber['id']]);
                $active_occ = $occ_stmt->fetchColumn();

                $new_chamber_status = 'available';
                if ($active_occ > 0) {
                    $new_chamber_status = ($active_occ >= $chamber['capacity']) ? 'occupied' : 'partially_occupied';
                }
                
                $up_ch = $db->prepare("UPDATE chambers SET status = ? WHERE id = ?");
                $up_ch->execute([$new_chamber_status, $chamber['id']]);

                // Audit log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, old_value, new_value, remarks, performed_by) VALUES ('Chamber Vacated', ?, ?, ?, ?)");
                $audit->execute([$chamber['chamber_no'], 'vacated', "आवंटन सं. {$allot['allotment_no']} समाप्त। लंबित बकाया: ₹$outstanding", $_SESSION['user_id']]);

                $db->commit();
                $success = 'आवंटन सफलतापूर्वक समाप्त किया गया और चैंबर खाली कर दिया गया।';
                
                // Reload chamber details
                $stmt->execute([$id]);
                $chamber = $stmt->fetch();

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'निष्कासन प्रक्रिया विफल: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Active Occupants
$active_allotments = [];
if ($db) {
    try {
        $acc_stmt = $db->prepare("
            SELECT a.*, m.full_name, m.membership_no, m.enrollment_no, m.mobile 
            FROM chamber_allotments a
            JOIN members m ON m.id = a.member_id
            WHERE a.chamber_id = ? AND a.status = 'active'
            ORDER BY a.effective_from ASC
        ");
        $acc_stmt->execute([$id]);
        $active_allotments = $acc_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed querying active occupants: " . $e->getMessage());
    }
}

// Fetch Previous Occupants History
$previous_allotments = [];
if ($db) {
    try {
        $prev_stmt = $db->prepare("
            SELECT a.*, m.full_name, m.membership_no, m.enrollment_no 
            FROM chamber_allotments a
            JOIN members m ON m.id = a.member_id
            WHERE a.chamber_id = ? AND a.status != 'active'
            ORDER BY a.vacated_at DESC, a.id DESC
        ");
        $prev_stmt->execute([$id]);
        $previous_allotments = $prev_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed querying previous occupants: " . $e->getMessage());
    }
}

// Fetch Rent Dues Registry for this Chamber
$rent_dues = [];
if ($db) {
    try {
        $rd_stmt = $db->prepare("
            SELECT d.*, a.allotment_no, m.full_name 
            FROM chamber_rent_dues d
            JOIN chamber_allotments a ON a.id = d.allotment_id
            JOIN members m ON m.id = a.member_id
            WHERE a.chamber_id = ?
            ORDER BY d.rent_month DESC
        ");
        $rd_stmt->execute([$id]);
        $rent_dues = $rd_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading rent dues list: " . $e->getMessage());
    }
}

// Fetch Applications preferring this chamber
$pref_applications = [];
if ($db) {
    try {
        $app_stmt = $db->prepare("
            SELECT a.*, m.full_name, m.membership_no 
            FROM chamber_applications a
            JOIN members m ON m.id = a.member_id
            WHERE a.preferred_chamber_id = ? AND a.status IN ('submitted', 'under_review', 'waiting_list')
            ORDER BY a.id ASC
        ");
        $app_stmt->execute([$id]);
        $pref_applications = $app_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading preferences: " . $e->getMessage());
    }
}

// Status labels
$status_badge_classes = [
    'available' => 'bg-success',
    'occupied' => 'bg-danger',
    'partially_occupied' => 'bg-warning text-dark',
    'reserved' => 'bg-primary',
    'maintenance' => 'bg-warning text-dark',
    'inactive' => 'bg-secondary'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष / चैंबर मास्टर कार्ड (Chamber Specification Card)</h4>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-left me-1"></i>चैंबर सूची</a>
        <a href="applications.php?preferred_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-file-earmark-text me-1"></i>आवेदन देखें</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left Column: Specs and occupancy -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle-fill text-gold-custom me-2"></i>चैंबर संरचना विवरण</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-5 text-secondary">चैम्बर संख्या:</div>
                    <div class="col-7 fw-bold english-text">Chamber <?php echo e($chamber['chamber_no']); ?></div>
                    <div class="col-5 text-secondary">भवन ब्लॉक:</div>
                    <div class="col-7 fw-semibold"><?php echo e($chamber['block_name'] ?: 'General Court Block'); ?></div>
                    <div class="col-5 text-secondary">तल स्थान (Floor):</div>
                    <div class="col-7 english-text"><?php echo e($chamber['floor'] ?: 'Ground Floor'); ?></div>
                    <div class="col-5 text-secondary">श्रेणी (Type):</div>
                    <div class="col-7"><?php echo e($chamber['chamber_type'] ?: 'General Cabin'); ?></div>
                    <div class="col-5 text-secondary">आवंटन प्रकार:</div>
                    <div class="col-7"><?php echo ($chamber['occupancy_type'] === 'shared') ? 'साझा (Shared Occupancy)' : 'एकल (Single Occupancy)'; ?></div>
                    <div class="col-5 text-secondary">क्षमता (Capacity):</div>
                    <div class="col-7 english-text"><?php echo count($active_allotments); ?> / <?php echo $chamber['capacity']; ?> आवंटी</div>
                    <div class="col-5 text-secondary">मासिक किराया:</div>
                    <div class="col-7 fw-bold text-danger english-text">₹<?php echo number_format($chamber['monthly_rent'], 2); ?>/माह</div>
                    <div class="col-5 text-secondary">जमानत सुरक्षा निधि:</div>
                    <div class="col-7 fw-bold english-text">₹<?php echo number_format($chamber['security_deposit'], 2); ?></div>
                    <div class="col-5 text-secondary">वर्तमान स्थिति:</div>
                    <div class="col-7">
                        <span class="badge <?php echo $status_badge_classes[$chamber['status']] ?? 'bg-secondary'; ?>">
                            <?php echo e(ucfirst($chamber['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Preferences Applications -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-clock-history me-2"></i>लंबित पसंद आवेदन (Chamber Preference Dues)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.75rem;">
                        <thead>
                            <tr class="table-light">
                                <th>आवेदन नं.</th>
                                <th>अधिवक्ता</th>
                                <th>स्थिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pref_applications)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-2 text-muted">कोई प्राथमिकता आवेदन लंबित नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pref_applications as $pa): ?>
                                    <tr>
                                        <td class="english-text fw-bold"><?php echo e($pa['application_no']); ?></td>
                                        <td><?php echo e($pa['full_name']); ?></td>
                                        <td><span class="badge bg-warning text-dark font-size-xs"><?php echo e($pa['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Current Occupants and Allotment History -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-check text-gold-custom me-2"></i>वर्तमान आवंटित सदस्य (Current Allottees)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                        <thead class="table-light">
                            <tr>
                                <th>आवंटी विवरण</th>
                                <th>आवंटन पत्र क्रमांक</th>
                                <th>आवंटन तिथि</th>
                                <th class="text-end">बकाया किराया</th>
                                <th class="text-end">एक्शन</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_allotments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">चैंबर वर्तमान में रिक्त (Unoccupied) है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_allotments as $aa): 
                                    $allot_out = getChamberOutstandingRent($aa['id'], $db);
                                ?>
                                    <tr>
                                        <td>
                                            <strong class="text-navy-custom"><?php echo e($aa['full_name']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.7rem;">Code: <?php echo e($aa['membership_no']); ?> | Cop: <?php echo e($aa['enrollment_no']); ?></span>
                                        </td>
                                        <td class="english-text"><?php echo e($aa['allotment_no']); ?></td>
                                        <td class="english-text"><?php echo date('d-m-Y', strtotime($aa['allotment_date'])); ?></td>
                                        <td class="text-end fw-bold text-danger english-text">₹<?php echo number_format($allot_out, 2); ?></td>
                                        <td class="text-end">
                                            <button class="btn btn-xs btn-outline-danger py-0.5" data-bs-toggle="modal" data-bs-target="#vacateModal<?php echo $aa['id']; ?>">खाली करें</button>
                                        </td>
                                    </tr>

                                    <!-- Vacate Modal -->
                                    <div class="modal fade" id="vacateModal<?php echo $aa['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white py-2">
                                                    <h6 class="modal-title fw-bold">चेंबर आवंटन समापन (Vacate Chamber)</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-start text-navy-custom">
                                                    <div class="alert alert-warning py-2 mb-3" style="font-size: 0.72rem;">
                                                        <strong>अधिवक्ता का नाम:</strong> <?php echo e($aa['full_name']); ?><br>
                                                        <strong>कुल बकाया किराया देय:</strong> <span class="text-danger fw-bold">₹<?php echo number_format($allot_out, 2); ?></span>
                                                        <br><small>* आवंटन समाप्त करने से किराया बकाया स्वतः माफ़ नहीं होगा।</small>
                                                    </div>

                                                    <form method="POST" action="">
                                                        <?php insertCSRF(); ?>
                                                        <input type="hidden" name="action" value="vacate">
                                                        <input type="hidden" name="allotment_id" value="<?php echo $aa['id']; ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">खाली करने की वास्तविक तिथि <span class="text-danger">*</span></label>
                                                            <input type="date" name="vacate_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">सुरक्षा जमा राशि निपटान (Security Deposit Status) <span class="text-danger">*</span></label>
                                                            <select name="deposit_status" class="form-select form-select-sm" required>
                                                                <option value="refunded">Refunded (सुरक्षा जमा वापस की गई)</option>
                                                                <option value="adjusted">Adjusted (किराए में समायोजित)</option>
                                                                <option value="forfeited">Forfeited (जब्त की गई)</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">खाली करने का स्पष्ट कारण / टिप्पणी <span class="text-danger">*</span></label>
                                                            <textarea name="vacate_reason" class="form-control form-control-sm" rows="2" required placeholder="उदा. स्वैच्छिक त्यागपत्र, बकाया न चुका पाने पर निरस्तीकरण आदि..."></textarea>
                                                        </div>

                                                        <div class="text-end">
                                                            <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">रद्द करें</button>
                                                            <button type="submit" class="btn btn-xs btn-danger px-3">आवंटन समाप्त करें</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rent Dues History Statement -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet text-gold-custom me-2"></i>चैंबर किराया इतिहास (Rent Ledger History)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.75rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>माह</th>
                                <th>आवंटी</th>
                                <th class="text-end">किराया</th>
                                <th class="text-end">दंड/छूट</th>
                                <th class="text-end">कुल देय</th>
                                <th class="text-end">प्राप्त जमा</th>
                                <th class="text-end">बकाया</th>
                                <th>स्थिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rent_dues)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-2 text-muted">कोई किराया देयता इतिहास दर्ज नहीं मिला।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rent_dues as $rd): ?>
                                    <tr>
                                        <td class="english-text fw-bold"><?php echo date('m/Y', strtotime($rd['rent_month'] . '-01')); ?></td>
                                        <td><?php echo e($rd['full_name']); ?></td>
                                        <td class="text-end english-text">₹<?php echo number_format($rd['rent_amount'], 2); ?></td>
                                        <td class="text-end text-muted english-text">₹<?php echo number_format($rd['penalty_amount'] - $rd['discount_amount'], 2); ?></td>
                                        <td class="text-end fw-semibold english-text">₹<?php echo number_format($rd['payable_amount'], 2); ?></td>
                                        <td class="text-end text-success english-text">₹<?php echo number_format($rd['paid_amount'], 2); ?></td>
                                        <td class="text-end text-danger fw-bold english-text">₹<?php echo number_format($rd['outstanding_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $st = $rd['status'];
                                            $c = ($st === 'paid') ? 'bg-success' : (($st === 'pending') ? 'bg-secondary' : (($st === 'overdue') ? 'bg-danger' : 'bg-warning text-dark'));
                                            ?>
                                            <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($st)); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Previous Occupants Register -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>पूर्व आवंटी इतिहास (Previous Occupants History)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                        <thead class="table-light">
                            <tr>
                                <th>अधिवक्ता विवरण</th>
                                <th>आवंटन सं.</th>
                                <th>आवंटन अवधि</th>
                                <th>निष्कासन कारण</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($previous_allotments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-2 text-muted">कोई पूर्व आवंटन रिकॉर्ड उपलब्ध नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($previous_allotments as $pa): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($pa['full_name']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.7rem;">Code: <?php echo e($pa['membership_no']); ?> | COP: <?php echo e($pa['enrollment_no']); ?></span>
                                        </td>
                                        <td class="english-text"><?php echo e($pa['allotment_no']); ?></td>
                                        <td class="english-text">
                                            <?php echo date('d-m-Y', strtotime($pa['effective_from'])); ?> से<br>
                                            <?php echo date('d-m-Y', strtotime($pa['vacated_at'])); ?>
                                        </td>
                                        <td><?php echo e($pa['vacate_reason'] ?: 'स्थान परिवर्तन / समापन'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
