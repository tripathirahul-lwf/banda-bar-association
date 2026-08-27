<?php
/**
 * Room / Chamber Presidential Monitoring & Approval Desk
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अध्यक्ष कक्ष किराया पटल (President Chamber Desk)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President role
requireRole('president');

$db = Database::getConnection();
$error = '';
$success = '';

// Handle President Action if CHAMBER_REQUIRE_PRESIDENT_APPROVAL is enabled
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['president_action'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $app_id = intval($_POST['app_id'] ?? 0);
        $action = sanitize($_POST['president_action']); // approve, reject
        $remarks = sanitize(trim($_POST['remarks'] ?? ''));

        if ($app_id > 0) {
            try {
                $db->beginTransaction();

                $status = ($action === 'approve') ? 'approved' : 'rejected';
                
                $up = $db->prepare("UPDATE chamber_applications SET status = ?, remarks = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                $up->execute([$status, "अध्यक्ष अनुमोदन: $remarks", $_SESSION['user_id'], $app_id]);

                // Audit Log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Application Approved', ?, ?)");
                $audit->execute(["अध्यक्ष द्वारा आवेदन पत्र संख्या $app_id को $status किया गया।", $_SESSION['user_id']]);

                $db->commit();
                $success = 'अनुमोदन स्थिति सफलतापूर्वक दर्ज की गई।';
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'अनुमोदन विफल: ' . $e->getMessage();
            }
        }
    }
}

// Fetch stats
$total_chambers = 0;
$available_chambers = 0;
$occupied_chambers = 0;
$pending_approvals_count = 0;
$outstanding_rent = 0.00;

if ($db) {
    try {
        $total_chambers = $db->query("SELECT COUNT(*) FROM chambers")->fetchColumn() ?: 0;
        $available_chambers = $db->query("SELECT COUNT(*) FROM chambers WHERE status = 'available'")->fetchColumn() ?: 0;
        $occupied_chambers = $db->query("SELECT COUNT(*) FROM chambers WHERE status IN ('occupied', 'partially_occupied')")->fetchColumn() ?: 0;
        
        $pending_approvals_count = $db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'submitted'")->fetchColumn() ?: 0;
        
        $outstanding_rent = $db->query("
            SELECT SUM(outstanding_amount) 
            FROM chamber_rent_dues 
            WHERE status NOT IN ('paid', 'waived', 'cancelled')
        ")->fetchColumn() ?: 0.00;
    } catch (PDOException $e) {
        error_log("Failed loading President chamber stats: " . $e->getMessage());
    }
}

// Fetch Pending Applications Queue
$pending_applications = [];
if ($db) {
    try {
        $pending_applications = $db->query("
            SELECT a.*, m.full_name, m.membership_no, c.chamber_no AS preferred_chamber_no
            FROM chamber_applications a
            JOIN members m ON m.id = a.member_id
            LEFT JOIN chambers c ON c.id = a.preferred_chamber_id
            WHERE a.status = 'submitted'
            ORDER BY a.id ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed loading pending list: " . $e->getMessage());
    }
}

// Fetch Available Chambers list
$chambers_list = [];
if ($db) {
    try {
        $chambers_list = $db->query("SELECT * FROM chambers WHERE status = 'available' ORDER BY chamber_no ASC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed querying available list: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष / चैंबर राष्ट्रपति समीक्षा पटल (President Monitoring Desk)</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- High-level statistics block -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom">
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block">कुल कमरे रजिस्टर</span>
            <strong class="fs-4 d-block english-text my-1"><?php echo $total_chambers; ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block text-success">चैंबर रिक्त (Available)</span>
            <strong class="fs-4 d-block text-success english-text my-1"><?php echo $available_chambers; ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block text-danger">लंबित आवेदन (Pending Reviews)</span>
            <strong class="fs-4 d-block text-danger english-text my-1"><?php echo $pending_approvals_count; ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block text-navy-custom">कुल किराया बकाया (Outstanding)</span>
            <strong class="fs-4 d-block text-danger english-text my-1">₹<?php echo number_format($outstanding_rent, 2); ?></strong>
        </div>
    </div>
</div>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left: Pending Applications Approvals Review -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-gold-custom me-2"></i>लंबित कक्ष आवंटन अनुमोदन (Pending Approvals Review)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                        <thead class="table-light">
                            <tr>
                                <th>आवेदन सं</th>
                                <th>अधिवक्ता विवरण</th>
                                <th>पसंद चैम्बर</th>
                                <th>दिनांक</th>
                                <?php if (CHAMBER_REQUIRE_PRESIDENT_APPROVAL): ?>
                                    <th class="text-end">कार्रवाई (Action)</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_applications)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">कोई लंबित अनुमोदन अनुरोध प्राप्त नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_applications as $pa): ?>
                                    <tr>
                                        <td class="english-text fw-bold"><?php echo e($pa['application_no']); ?></td>
                                        <td>
                                            <strong><?php echo e($pa['full_name']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.7rem;">Code: <?php echo e($pa['membership_no']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pa['preferred_chamber_no']): ?>
                                                <span class="badge bg-light text-navy-custom border english-text">Chamber <?php echo e($pa['preferred_chamber_no']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo e($pa['chamber_preference']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="english-text"><?php echo date('d-m-Y', strtotime($pa['application_date'])); ?></td>
                                        
                                        <?php if (CHAMBER_REQUIRE_PRESIDENT_APPROVAL): ?>
                                            <td class="text-end">
                                                <form method="POST" action="" class="d-inline-flex gap-1">
                                                    <?php insertCSRF(); ?>
                                                    <input type="hidden" name="app_id" value="<?php echo $pa['id']; ?>">
                                                    <input type="text" name="remarks" class="form-control form-control-xs px-1" style="width: 80px; font-size:0.7rem;" placeholder="कारण">
                                                    <button type="submit" name="president_action" value="approve" class="btn btn-xs btn-success py-0.5">स्वीकृत</button>
                                                    <button type="submit" name="president_action" value="reject" class="btn btn-xs btn-danger py-0.5">रद्द</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Available Chambers Registry -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-open text-gold-custom me-2"></i>वर्तमान उपलब्ध कक्ष सूची (Available Chambers)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:0.75rem;">
                        <thead class="table-light">
                            <tr>
                                <th>कक्ष नं.</th>
                                <th>परिसर / तल</th>
                                <th class="text-end">मासिक किराया</th>
                                <th class="text-end">जमानत राशि</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($chambers_list)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">कोई कक्ष उपलब्ध स्थिति में नहीं मिला।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($chambers_list as $cl): ?>
                                    <tr>
                                        <td class="fw-bold text-success english-text">Chamber <?php echo e($cl['chamber_no']); ?></td>
                                        <td><?php echo e($cl['block_name']); ?> (<?php echo e($cl['floor']); ?>)</td>
                                        <td class="text-end text-navy-custom english-text">₹<?php echo number_format($cl['monthly_rent'], 2); ?></td>
                                        <td class="text-end text-secondary english-text">₹<?php echo number_format($cl['security_deposit'], 2); ?></td>
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
