<?php
/**
 * System Login Activity Log Viewer
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'लॉगिन गतिविधि लॉग (Login Activity)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

$db = Database::getConnection();

// Filters
$filter_status = sanitize($_GET['status'] ?? '');
$filter_user = sanitize($_GET['user_query'] ?? '');
$filter_date = sanitize($_GET['date_query'] ?? '');

$sql = "
    SELECT l.*, u.username AS linked_username
    FROM login_logs l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE 1=1
";
$params = [];

if (!empty($filter_status)) {
    if ($filter_status === 'success') {
        $sql .= " AND l.login_status = 'success'";
    } elseif ($filter_status === 'failed') {
        $sql .= " AND l.login_status = 'failed'";
    } elseif ($filter_status === 'locked') {
        $sql .= " AND l.login_status = 'locked'";
    }
}

if (!empty($filter_user)) {
    $sql .= " AND (l.identifier LIKE ? OR u.username LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(l.created_at) = ?";
    $params[] = $filter_date;
}

$sql .= " ORDER BY l.id DESC LIMIT 150";
$logs = [];

if ($db) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to query login logs: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-person-badge text-gold-custom me-2"></i>पोर्टल लॉगिन लॉग विवरणी (Access Logs)</h4>
    <a href="../audit-logs/index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-shield-lock-fill me-1"></i>केंद्रीय ऑडिट लॉग</a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-xs p-3 bg-light border border-light mb-4 font-hindi small">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- सभी लॉगिन स्थितियां --</option>
                <option value="success" <?php echo ($filter_status === 'success') ? 'selected' : ''; ?>>Success (सफल लॉगिन)</option>
                <option value="failed" <?php echo ($filter_status === 'failed') ? 'selected' : ''; ?>>Failed (विफल प्रयास)</option>
                <option value="locked" <?php echo ($filter_status === 'locked') ? 'selected' : ''; ?>>Locked (अवरुद्ध खाते)</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="user_query" class="form-control form-control-sm" value="<?php echo e($filter_user); ?>" placeholder="उपयोगकर्ता नाम या आईडी...">
        </div>
        <div class="col-md-3">
            <input type="date" name="date_query" class="form-control form-control-sm" value="<?php echo $filter_date; ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-filter me-1"></i>लागू करें</button>
        </div>
    </form>
</div>

<!-- Logs list -->
<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                <thead class="table-light">
                    <tr>
                        <th>दिनांक एवं समय</th>
                        <th>लॉगिन आईडी (Identifier)</th>
                        <th>प्रयासित रोल (Role Attempted)</th>
                        <th>आईपी पता (IP Address)</th>
                        <th>प्रवेश स्थिति (Status)</th>
                        <th>ब्राउज़र / उपकरण (Device Info)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-3 text-muted">कोई लॉगिन गतिविधि दर्ज नहीं है।</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="english-text"><?php echo date('d-m-Y h:i A', strtotime($l['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo e($l['identifier']); ?></strong>
                                    <?php if ($l['linked_username']): ?>
                                        <span class="text-muted" style="font-size: 0.65rem;">(सम्बद्ध: <?php echo e($l['linked_username']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-navy-custom border"><?php echo e(ucfirst($l['role_attempted'])); ?></span></td>
                                <td class="english-text"><?php echo e($l['ip_address']); ?></td>
                                <td>
                                    <?php 
                                    $col = ($l['login_status'] === 'success') ? 'bg-success' : (($l['login_status'] === 'locked') ? 'bg-danger' : 'bg-warning text-dark');
                                    ?>
                                    <span class="badge <?php echo $col; ?> font-size-xs"><?php echo e(ucfirst($l['login_status'])); ?></span>
                                </td>
                                <td class="english-text text-muted" style="font-size:0.65rem; max-width:250px; word-break: break-all;"><?php echo e($l['user_agent']); ?></td>
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
