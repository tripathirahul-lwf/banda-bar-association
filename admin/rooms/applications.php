<?php
/**
 * Room Allotment Applications Dashboard List
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'आवंटन आवेदन प्रबंधन (Chamber Allotment Requests)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();

// Filters & Search Inputs
$search = sanitize(trim($_GET['search'] ?? ''));
$status_filter = sanitize(trim($_GET['status'] ?? ''));
$block_filter = sanitize(trim($_GET['block'] ?? ''));

// Base Query
$query_str = "
    SELECT a.*, m.full_name, m.membership_no, m.enrollment_no, m.membership_status, c.chamber_no AS preferred_chamber_no, curr.chamber_no AS current_chamber_no
    FROM chamber_applications a
    JOIN members m ON m.id = a.member_id
    LEFT JOIN chambers c ON c.id = a.preferred_chamber_id
    LEFT JOIN chambers curr ON curr.id = a.current_chamber_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (m.full_name LIKE ? OR m.membership_no LIKE ? OR m.enrollment_no LIKE ? OR a.application_no LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $query_str .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($block_filter)) {
    $query_str .= " AND a.chamber_preference LIKE ?";
    $params[] = "%Block: $block_filter%";
}

$query_str .= " ORDER BY a.id DESC";

$applications = [];
if ($db) {
    try {
        $stmt = $db->prepare($query_str);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed searching chamber applications: " . $e->getMessage());
    }
}

// Status translation mappings
$status_badges = [
    'submitted' => 'bg-info text-dark',
    'under_review' => 'bg-warning text-dark',
    'waiting_list' => 'bg-primary text-white',
    'approved' => 'bg-success text-white',
    'rejected' => 'bg-danger text-white',
    'withdrawn' => 'bg-secondary text-white',
    'allotted' => 'bg-success text-white'
];

$status_labels = [
    'submitted' => 'Submitted (लंबित)',
    'under_review' => 'Under Review',
    'waiting_list' => 'Waiting List',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'withdrawn' => 'Withdrawn',
    'allotted' => 'Allotted'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">कक्ष आवंटन आवेदन विवरणी (Allotment Requests)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-grid-fill me-1"></i>चैंबर मास्टर पैनल</a>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success py-2 font-hindi small">
        <?php 
        echo $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
        ?>
    </div>
<?php endif; ?>

<!-- Search and Filter Form Panel -->
<div class="card border-0 shadow-xs mb-4 font-hindi small">
    <div class="card-body p-3 bg-light rounded border border-light">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-5">
                <label class="form-label fw-bold text-navy-custom">खोजें (सदस्य नाम/सदस्यता सं/आवेदन संख्या)</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?php echo e($search); ?>" placeholder="उदा. Amit, DBA-003, DBA/ROOM/2026/0001">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-navy-custom">आवेदन स्थिति (Status)</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- सभी स्थितियां --</option>
                    <?php foreach ($status_labels as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($status_filter === $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold text-navy-custom">पसंदीदा ब्लॉक</label>
                <select name="block" class="form-select form-select-sm">
                    <option value="">-- सभी ब्लॉक --</option>
                    <option value="Main Court Block" <?php echo ($block_filter === 'Main Court Block') ? 'selected' : ''; ?>>Main Court Block</option>
                    <option value="Library Block" <?php echo ($block_filter === 'Library Block') ? 'selected' : ''; ?>>Library Block</option>
                    <option value="New Annex Block" <?php echo ($block_filter === 'New Annex Block') ? 'selected' : ''; ?>>New Annex Block</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-filter-circle me-1"></i>फिल्टर करें</button>
            </div>
        </form>
    </div>
</div>

<!-- Grid Table of Applications -->
<div class="card border-0 shadow-sm font-hindi small">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text text-gold-custom me-2"></i>आवंटन आवेदन पत्र सूची (Allotment Queue)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-muted">
                <thead class="table-light">
                    <tr>
                        <th class="py-2.5">आवेदन संख्या (Application No)</th>
                        <th>अधिवक्ता सदस्य</th>
                        <th>सदस्यता संख्या</th>
                        <th>नामांकन क्रमांक</th>
                        <th>आवेदन तिथि</th>
                        <th>पसंदीदा चैंबर/ब्लॉक</th>
                        <th>वर्तमान चैंबर</th>
                        <th>प्रतीक्षा सूची</th>
                        <th>स्थिति</th>
                        <th class="text-end">कार्रवाई</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4">कोई आवेदन पत्र प्राप्त नहीं हुआ है या खोज मानदंडों के अनुसार कोई परिणाम नहीं मिला।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td class="py-2 english-text fw-bold text-navy-custom"><?php echo e($app['application_no']); ?></td>
                                <td>
                                    <strong><?php echo e($app['full_name']); ?></strong><br>
                                    <span class="badge bg-success font-size-xs" style="font-size:0.65rem;"><?php echo e(ucfirst($app['membership_status'])); ?></span>
                                </td>
                                <td class="english-text"><?php echo e($app['membership_no']); ?></td>
                                <td class="english-text"><?php echo e($app['enrollment_no']); ?></td>
                                <td class="english-text"><?php echo date('d-m-Y', strtotime($app['application_date'])); ?></td>
                                <td>
                                    <?php if ($app['preferred_chamber_no']): ?>
                                        <span class="fw-semibold text-navy-custom english-text">Chamber <?php echo e($app['preferred_chamber_no']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo e($app['chamber_preference']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $app['current_chamber_no'] ? '<span class="badge bg-light text-navy-custom border">Chamber ' . e($app['current_chamber_no']) . '</span>' : 'कोई नहीं'; ?></td>
                                <td class="english-text fw-bold text-danger"><?php echo $app['priority_no'] ? '#' . $app['priority_no'] : '-'; ?></td>
                                <td>
                                    <span class="badge <?php echo $status_badges[$app['status']] ?? 'bg-secondary'; ?> font-size-xs">
                                        <?php echo $status_labels[$app['status']] ?? $app['status']; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="application-view.php?id=<?php echo $app['id']; ?>" class="btn btn-xs btn-navy py-0.5"><i class="bi bi-gear-fill me-1"></i>समीक्षा</a>
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
