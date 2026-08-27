<?php
/**
 * Room / Chamber Master Admin Panel Dashboard
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष / चैंबर मास्टर प्रबंधन (Chamber Master)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permission/role
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Handle quick Mark Maintenance status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_maintenance'])) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $chamber_id = intval($_POST['chamber_id'] ?? 0);
        $current_status = sanitize($_POST['current_status'] ?? '');
        
        if ($chamber_id > 0) {
            try {
                $new_status = ($current_status === 'maintenance') ? 'available' : 'maintenance';
                
                $up = $db->prepare("UPDATE chambers SET status = ? WHERE id = ?");
                $up->execute([$new_status, $chamber_id]);
                
                // Audit log
                $audit = $db->prepare("INSERT INTO chamber_audit_logs (action, remarks, performed_by) VALUES ('Chamber Maintenance Toggle', ?, ?)");
                $audit->execute(["चैंबर आईडी $chamber_id की स्थिति $new_status में बदली गई।", $_SESSION['user_id']]);
                
                $success = 'चैंबर की स्थिति सफलतापूर्वक अपडेट की गई।';
            } catch (PDOException $e) {
                $error = 'अपडेट करने में असमर्थ: ' . $e->getMessage();
            }
        }
    }
}

// Fetch dashboard quick stats
$total_chambers = 0;
$available_chambers = 0;
$occupied_chambers = 0;
$pending_apps = 0;
$waiting_list_count = 0;
$outstanding_rent = 0.00;
$rent_collected_month = 0.00;

if ($db) {
    try {
        $total_chambers = $db->query("SELECT COUNT(*) FROM chambers")->fetchColumn() ?: 0;
        $available_chambers = $db->query("SELECT COUNT(*) FROM chambers WHERE status = 'available'")->fetchColumn() ?: 0;
        $occupied_chambers = $db->query("SELECT COUNT(*) FROM chambers WHERE status IN ('occupied', 'partially_occupied')")->fetchColumn() ?: 0;
        $pending_apps = $db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'submitted'")->fetchColumn() ?: 0;
        $waiting_list_count = $db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'waiting_list'")->fetchColumn() ?: 0;
        
        $outstanding_rent = $db->query("
            SELECT SUM(outstanding_amount) 
            FROM chamber_rent_dues 
            WHERE status NOT IN ('paid', 'waived', 'cancelled')
        ")->fetchColumn() ?: 0.00;

        $rent_collected_month = $db->query("
            SELECT SUM(amount) 
            FROM chamber_rent_payments 
            WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
              AND YEAR(payment_date) = YEAR(CURRENT_DATE()) 
              AND status = 'confirmed'
        ")->fetchColumn() ?: 0.00;
    } catch (PDOException $e) {
        error_log("Failed compiling chamber stats: " . $e->getMessage());
    }
}

// Fetch Chamber Master Lists with active occupancy counts
$chambers_list = [];
if ($db) {
    try {
        $chambers_list = $db->query("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM chamber_allotments WHERE chamber_id = c.id AND status = 'active') AS active_occupants 
            FROM chambers c
            ORDER BY c.chamber_no ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed querying chambers register: " . $e->getMessage());
    }
}

// Translate Status to Hindi label
$status_badge_classes = [
    'available' => 'bg-success',
    'occupied' => 'bg-danger',
    'partially_occupied' => 'bg-warning text-dark',
    'reserved' => 'bg-primary',
    'maintenance' => 'bg-warning text-dark',
    'inactive' => 'bg-secondary'
];

$status_labels = [
    'available' => 'उपलब्ध (Available)',
    'occupied' => 'पूर्ण आवंटित (Occupied)',
    'partially_occupied' => 'आंशिक आवंटित',
    'reserved' => 'आरक्षित',
    'maintenance' => 'रखरखाव (Maintenance)',
    'inactive' => 'निष्क्रिय'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष / चैंबर मास्टर विवरणी (Chamber Master Console)</h4>
    <div class="d-flex gap-2">
        <a href="reports.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-bar-chart-line me-1"></i>किराया रिपोर्ट्स</a>
        <a href="applications.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-file-earmark-text me-1"></i>आवंटन आवेदन (<?php echo $pending_apps; ?>)</a>
        <a href="waiting-list.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-clock me-1"></i>प्रतीक्षा सूची (<?php echo $waiting_list_count; ?>)</a>
        <a href="create.php" class="btn btn-xs btn-navy fw-semibold"><i class="bi bi-plus-circle me-1"></i>नया चैंबर जोड़ें</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Chamber Statistics Grid -->
<div class="row g-3 mb-4 font-hindi small text-navy-custom">
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light-custom text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block">कुल चैंबर क्षमता</span>
            <strong class="fs-4 d-block english-text my-1"><?php echo $total_chambers; ?></strong>
            <span class="text-muted font-size-xs">सक्रिय कक्ष रजिस्टर</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light-custom text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block text-success">चैंबर उपलब्ध (Available)</span>
            <strong class="fs-4 d-block text-success english-text my-1"><?php echo $available_chambers; ?></strong>
            <span class="text-muted font-size-xs">आवंटन हेतु रिक्त</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light-custom text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block text-danger font-hindi">किराया बकाया (Outstanding)</span>
            <strong class="fs-4 d-block text-danger english-text my-1">₹<?php echo number_format($outstanding_rent, 2); ?></strong>
            <span class="text-muted font-size-xs">कुल लंबित किराया राशि</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 border-0 bg-light-custom text-center shadow-xs">
            <span class="text-secondary font-size-xs d-block text-success">किराया संग्रह (This Month)</span>
            <strong class="fs-4 d-block text-success english-text my-1">₹<?php echo number_format($rent_collected_month, 2); ?></strong>
            <span class="text-muted font-size-xs">चालू माह का कुल संग्रह</span>
        </div>
    </div>
</div>

<!-- Chamber Master Register Table -->
<div class="card border-0 shadow-sm font-hindi small">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-table text-gold-custom me-2"></i>चैंबर आवंटन एवं किराया रजिस्टर (Chambers Directory)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2.5">चैंबर नं (Chamber No)</th>
                        <th>परिसर ब्लॉक (Block)</th>
                        <th>तल (Floor)</th>
                        <th>आवंटन प्रकार</th>
                        <th class="text-end">क्षमता (Cap)</th>
                        <th class="text-end">मासिक किराया</th>
                        <th class="text-end">सुरक्षा जमा (Deposit)</th>
                        <th>स्थिति (Status)</th>
                        <th class="text-end">एक्शन (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chambers_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">मास्टर रजिस्टर में कोई चैंबर प्रविष्टि उपलब्ध नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($chambers_list as $c): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold text-navy-custom">Chamber <?php echo e($c['chamber_no']); ?></td>
                                <td><?php echo e($c['block_name'] ?: '-'); ?></td>
                                <td><?php echo e($c['floor'] ?: '-'); ?></td>
                                <td><?php echo ($c['occupancy_type'] === 'shared') ? 'साझा (Shared)' : 'सिंगल (Single)'; ?></td>
                                <td class="text-end english-text"><?php echo $c['active_occupants']; ?> / <?php echo $c['capacity']; ?></td>
                                <td class="text-end text-navy-custom english-text">₹<?php echo number_format($c['monthly_rent'], 2); ?></td>
                                <td class="text-end text-secondary english-text">₹<?php echo number_format($c['security_deposit'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $status_badge_classes[$c['status']] ?? 'bg-secondary'; ?> font-size-xs">
                                        <?php echo $status_labels[$c['status']] ?? $c['status']; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-xs btn-outline-navy dropdown-toggle py-0.5" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            एक्शन
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:0.75rem;">
                                            <li><a class="dropdown-item text-navy-custom" href="view.php?id=<?php echo $c['id']; ?>"><i class="bi bi-eye me-1"></i>चैंबर विवरण देखें (View Allotment)</a></li>
                                            <li><a class="dropdown-item text-navy-custom" href="reports.php?chamber_no=<?php echo urlencode($c['chamber_no']); ?>"><i class="bi bi-clock-history me-1"></i>किराया इतिहास (View Rent)</a></li>
                                            <li>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('क्या आप वास्तव में इस चैंबर की मरम्मत स्थिति बदलना चाहते हैं?');">
                                                    <?php insertCSRF(); ?>
                                                    <input type="hidden" name="toggle_maintenance" value="1">
                                                    <input type="hidden" name="chamber_id" value="<?php echo $c['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $c['status']; ?>">
                                                    <button type="submit" class="dropdown-item text-warning fw-semibold">
                                                        <i class="bi bi-tools me-1"></i> 
                                                        <?php echo ($c['status'] === 'maintenance') ? 'मरम्मत समाप्त करें' : 'मरम्मत हेतु चिन्हित'; ?>
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
